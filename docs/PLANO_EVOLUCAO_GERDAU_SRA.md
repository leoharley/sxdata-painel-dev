# Plano: evolução contratada em nov/2026 (PEA Gerdau, SRA e questionário domiciliar)

Fontes:
- `Proposta_PEA_Gerdau_e_Evolucao_SXDATA.pdf` (revisão 2, de 19/11/2026). A Parte I tem as frentes A a H e a Parte II, as frentes I a L.
- Prompt "questionário domiciliar com múltiplos moradores e campos calculados de renda".

**Status: aguardando aprovação do Leonardo.** Nenhum código foi alterado.

## 0. Regras

1. **O editor de questionários atual não muda.** A estrutura nova tem tela, tabelas e endpoints próprios. Os questionários de hoje, as respostas, as exportações e o app continuam funcionando como estão.
2. Repositórios: `sxdata-painel-dev` e `sxdata-app-dev`. A produção fica para depois, pelo agente da produção.
3. Faço um commit por etapa, testes PHPUnit nas regras puras e publico na evolução (Hostinger) ao fim de cada etapa, para validação.
4. As migrações são idempotentes, em `sql/*.sql`, e cada uma vem com a ordem de execução documentada.

## 1. Diagnóstico do que existe

| Tema | Hoje | Consequência |
|---|---|---|
| Motor de formulários | O questionário é uma lista plana de perguntas. A lógica só mostra, oculta ou torna obrigatória uma pergunta. Há uma linha em `question_responses` por pergunta. | Não há grupos repetíveis, moradores nem campos calculados. Precisa de estrutura nova. |
| Envio do app | `api/forms/submit` já elimina duplicatas pelo `client_id`. | Isso é reaproveitado. |
| Validação | O servidor **não valida** o envio do app: não confere tipo, obrigatoriedade, lógica nem se a pergunta pertence ao questionário. | Corrigido na frente I. |
| App | A entrevista em andamento fica **só na memória**: fechar o app perde a entrevista. O armazenamento usa SharedPreferences (no web, localStorage de cerca de 5 MB). | Entrevistas domiciliares longas exigem rascunho persistido e outro armazenamento (prompt do app). |
| IA | Só OpenAI, chamada por cURL em `Ai_service`. Os preços ficam no código. Os textos vão para a API e para o log sem remover dados pessoais. | Frente K. |
| Autenticação | Não há bloqueio por tentativas, 2FA nem trilha de auditoria. Os campos `max_login_attempts` e `session_timeout` existem em Configurações, mas nada os lê. A exclusão é física. | Frente I. |
| Relatórios | O fluxo de conferência, aprovação e versão (`municipal_reports`) funciona, mas é preso ao município (`codigo_ibge NOT NULL`). | Frente F (PEA): generalizar com cuidado. |
| PDF | Não há biblioteca; tudo sai por `window.print()`. | Mantenho esse padrão. |

**Bugs encontrados no caminho** (fora do escopo; corrijo só se você autorizar):
- **Condições compararam texto com valor.** O editor grava a condição com o texto da opção ("Sim"). O formulário por QR Code e o app comparam com o valor da opção ("sim"). A comparação é estrita, então essas perguntas provavelmente nunca aparecem no formulário público.
- **Descrição de gráfico sem checar o cliente.** `Client::generate_chart_description` não confere se o questionário pertence ao cliente, e qualquer usuário logado gasta crédito de IA com ela.
- **Importação de questionário quebrada.** `Questionnaires::import_with_logic` não funciona.

## 2. A estrutura nova: "Formulários estruturados"

O formulário estruturado reaproveita o cabeçalho de hoje:
- `questionnaires`, com `formato = 'estruturado'`. Assim valem o projeto, os aplicadores permitidos e o status.
- `form_responses`, que fica sendo o domicílio ou a entrevista. Assim valem o `client_id`, a localização, as fotos, o áudio, o KMZ e o ponto de campo.

Tudo o que é novo fica em tabelas próprias. Os questionários com `formato = 'simples'` (todos os atuais) continuam no editor antigo, e o editor nunca abre um estruturado.

### 2.1 Modelo de dados (`sql/estruturado_migration.sql`)

| Tabela | Conteúdo |
|---|---|
| `question_sets` | **Conjunto de perguntas** (frente L): nome, descrição, projeto (nulo = global), `nivel` (domicilio ou morador), `repetivel` (não, por pergunta ou livre), pergunta que dá o número de repetições, rótulo (`Morador {n}`), máximo. |
| `question_set_versions` | Versão publicada do conjunto: número, data, quem publicou. Alterar o conjunto cria uma versão nova, e a entrevista guarda a versão que usou. |
| `set_questions` | Pergunta de uma versão do conjunto: `codigo` (nome da variável, estável entre versões, ex.: `renda_formal_valor`), texto, tipo, obrigatória, ordem, lógica (o mesmo JSON de hoje, referenciando `codigo`), opções (JSON), `formula` (para o tipo `calculado`) e `formato` (moeda, número, porcentagem). |
| `themes`, `theme_sets` | **Temas** e os conjuntos de cada tema. |
| `interviewees` | **Entrevistados pré-cadastrados** (frente L): projeto, código, nome, categoria, localidade, coordenadas e `campos` (JSON). Cadastro individual ou por planilha. |
| `interviewee_categories`, `set_links` | **Vínculos:** cada conjunto abre por resposta (pergunta, operador, valor), por tema, por entrevistado ou por categoria. |
| `structured_forms` | Composição do formulário: etapas na ordem, com os conjuntos do domicílio, a **etapa de moradores** (campos mínimos, quem é o respondente) e os conjuntos **por morador**. |
| `household_members` | **Moradores** (1:N com `form_responses`): `client_id` (UUID do aparelho), ordem, nome, parentesco, idade, sexo, respondente, status (não iniciado, em andamento, concluído, justificado), justificativa. |
| `set_instances` | Cada abertura de um conjunto numa entrevista: versão, morador (ou nulo), índice da repetição, rótulo e como abriu (resposta, tema, entrevistado ou fixo). |
| `structured_answers` | Respostas da estrutura nova: `form_response_id`, `instance_id`, `set_question_id` e as mesmas colunas de valor de `question_responses`, mais `calculado_app` e `calculado_servidor`. |

**Unidades de análise:** domicílio (`form_responses`), morador (`household_members`) e repetição (`set_instances`). Todas se ligam pelo id da entrevista. Isso atende à relação domicílio 1:N moradores e às abas de exportação.

### 2.2 Campos calculados

O tipo novo `calculado` é somente leitura e usa uma fórmula com linguagem pequena e segura, sem `eval`:

```
soma(renda_formal_valor, renda_informal_valor, beneficio_valor)    -- "Não" ou oculta vale 0
soma_moradores(renda_total_morador)                                 -- agrega entre moradores
contar_moradores()        div(a, b)        arred(x, 2)        se(cond, a, b)
```

Exemplos:
- `renda_total = soma_moradores(renda_total_morador) + soma(renda_domicilio_*)`
- `renda_per_capita = div(renda_total, contar_moradores())`

Regras:
- Na divisão por zero, o campo fica vazio.
- O valor é guardado com 2 casas.
- A fórmula é recalculada sempre que uma resposta de origem muda.
- Ao editar ou remover um morador, tudo é recalculado.

**Os mesmos casos de teste valem para os dois motores.** Um arquivo único, `tests/fixtures/formulas.json`, é usado pelo PHPUnit (motor PHP, `libraries/Formula.php`) e pelo `flutter test` (motor Dart), para os dois não divergirem.

O servidor recalcula ao receber e guarda as duas versões, a do app e a do servidor. Se divergirem, a entrevista entra na fila de revisão (frente I).

### 2.3 Tela nova no painel: "Formulários estruturados"

- **Conjuntos de perguntas.** Lista e editor do conjunto: perguntas, tipos (os atuais mais `calculado`), lógica interna, configuração de repetição, versões, onde é usado e prévia. Essa tela segue a Figura 5 da proposta.
- **Temas e Entrevistados.** Cadastros, mais importação de entrevistados por planilha (XLSX/CSV) com relatório de erros por linha.
- **Montagem do formulário.** Etapas do domicílio, a etapa de moradores (campos mínimos) e os conjuntos por morador, os vínculos (por resposta, tema ou entrevistado) e os campos calculados do domicílio.
- **Entrevista no painel.** Domicílio, lista de moradores com status e as respostas de cada um, repetições e campos calculados, com a divergência entre app e servidor marcada.

O editor é salvo **por JSON numa só requisição**. Ele não tem o limite de `max_input_vars` nem apaga perguntas que não vieram no POST, como o editor antigo.

### 2.4 API do app (novos endpoints; os atuais não mudam)

| Rota | O que faz |
|---|---|
| `GET api/estruturado/formularios` | Formulários estruturados do aplicador, com conjuntos, versões, temas e vínculos. |
| `GET api/estruturado/entrevistados?project_id=&desde=` | Entrevistados atribuídos à equipe, de forma incremental, para uso offline. |
| `POST api/estruturado/submit` | Envia o domicílio inteiro numa transação: cabeçalho, moradores, repetições, respostas e calculados. Duplicatas são eliminadas pelo `client_id` da entrevista e de cada morador. A validação é feita no servidor e o envio é **atômico**: nunca fica um domicílio sem moradores nem um morador sem domicílio. A resposta confirma cada parte (`partes: {respostas, moradores, midias_pendentes[]}`). |

As fotos e o áudio continuam em `api/photos/upload` e `api/audios/upload`, com a mesma confirmação de mídia faltante de hoje.

### 2.5 Exportações

Excel do formulário estruturado:
- uma aba **Domicílios**, com a renda total e a per capita;
- uma aba **Moradores**, com uma linha por morador e o id do domicílio;
- uma aba para cada conjunto repetível que não seja o de moradores.

O KMZ continua com um ponto por domicílio. O CSV sai pelo mesmo gerador.

## 3. Parte II: as outras frentes

**I. Robustez e confiabilidade**
- **Validação no servidor e fila de revisão.** Vale para os dois formatos. No formato simples, o envio continua sendo aceito como hoje; a inconsistência entra na fila, mas não bloqueia.
- **Confirmação por parte** no `forms/submit`. O campo é novo e não quebra o app atual.
- **Trilha de auditoria** (tabela `audit_log`): login, visualização de dados identificados, edição com o valor antigo e o novo, exclusão, exportação e download. Tem tela de consulta e exportação.
- **Exclusão lógica** (`deleted_at`) em entrevistas, moradores e usuários, com restauração.
- **Acesso:** 2FA por aplicativo autenticador (TOTP em PHP puro, sem dependência), política de senha, bloqueio por tentativas e expiração por inatividade (os campos já existentes em Configurações passam a valer), `sess_regenerate` no login e permissões por projeto.
- **Erros:** registro centralizado (tabela `system_errors`) do painel, da API e do app (endpoint novo), com tela e alerta.
- **Volume e versões:**
  - índices e paginação;
  - exportações grandes em segundo plano (fila mais um timer na VPS);
  - teste de carga com 200 mil respostas num **banco separado** da evolução;
  - número de versão e notas de cada publicação no painel.

**J. Cruzamento de variáveis** (tela nova, para administrador e cliente; Figura 4)
- **Variáveis:** perguntas de escolha e numéricas agrupadas em faixas, data por mês, entrevistador, local e ponto de campo, dados do entrevistado, temas e conjuntos. Nos estruturados, a unidade é domicílio ou morador.
- **Medidas:** contagem; percentual na linha, na coluna e no total; média e mediana.
- **Estatística:** qui-quadrado com valor-p, implementado em PHP puro e testado contra valores de referência, com aviso de base pequena.
- **Saídas:** gráficos em Chart.js, PDF por impressão, Excel (PhpSpreadsheet), CSV e imagem.
- **Análises salvas**, que podem ser liberadas ao cliente e incluídas no relatório.
- **Perfil cliente:** só vê os projetos dele e as variáveis liberadas. Campos identificadores ficam fora e células com menos de 5 respostas são ocultadas (limite configurável).

**K. Escolha do modelo de IA**
- `Ai_service` passa a ter provedores: OpenAI e Anthropic, esta pela API Messages via cURL, sem SDK, como hoje.
- `ai_settings` ganha provedor, modelo, provedor e modelo de alternativa, e limite mensal.
- O preço de cada modelo fica numa tabela, e não no código.
- Antes de qualquer envio, **CPF, telefone e e-mail são removidos**, e também o nome do entrevistado, quando conhecido. Isso vale também para o que fica no log.
- A IA pode ser desligada por projeto.
- As chaves são guardadas criptografadas (sodium, com chave do `.env`) e nunca aparecem na tela.
- Há tela de comparação entre dois modelos, lado a lado.
- Cada texto gerado registra o provedor, o modelo e quem aprovou.
- A transcrição continua no Whisper.

**L. Conjuntos, temas e entrevistados**: coberta pela seção 2.

## 4. Parte I: módulo PEA Gerdau

**A a G (módulo)** (tabelas `pea_*`, menu "PEA Gerdau", só para a equipe)
- **Estrutura:** ciclo, anos e trimestres, públicos, unidades, ABEAs, eixos, os 16 projetos com faixas de IP e o cronograma. O PEA 2026–2031 vem carregado por seed.
- **Ficha da ação:** evidências e QR Code da ação, que já leva público, projeto e unidade. Usa o link público de hoje com parâmetros.
- **Formulário de avaliação de 10 perguntas:** é um questionário simples. As opções que mudam por público (perguntas 1 e 4) vêm filtradas pelo público da ação.
- **Motor de ponderação:** cada pergunta tem um papel (segmentação, IS por item, IS oficial, IIA oficial, apoio, qualitativo), as opções têm valores e as regras são versionadas. Calcula ICC, IP, IS, IIA e IE e a árvore EIEAP. As regras ficam numa biblioteca pura, testada contra uma planilha de referência.
- **Painel do PEA** (Figura 1) e **relatórios:** ficha da ação, formulário semestral, relatório anual e relatório do ciclo. Usam o fluxo de conferência e aprovação de `municipal_reports`, que passa a aceitar `modulo = 'pea'` com o município opcional. O comportamento atual dos relatórios municipais fica coberto por testes.

**H. BancoDOC.** Primeiro **por arquivo**:
- importação da planilha de colaboradores e empresas;
- lista de presença por CPF ou matrícula;
- exportação das participações no layout que a Gerdau indicar.

A interface do conector fica pronta para a API, quando houver documentação e credenciais (ponto 9).

**Depende da Gerdau, conforme a seção 20 da proposta:**
- os pontos 1 a 6 (uso os padrões propostos até virem as respostas);
- o EIEAP (ponto 7);
- o acesso ao BancoDOC (ponto 9).

## 5. Ordem de entrega

As entrevistas do questionário domiciliar começam na semana que vem, então ele vem primeiro.

| Etapa | Conteúdo | Painel | App |
|---|---|---|---|
| 1 | Estrutura nova básica: conjuntos, moradores, calculados, vínculo por resposta, API, visualização, Excel com abas | sim | prompt da etapa A |
| 2 | Frente L completa: temas, entrevistados, vínculos por tema e entrevistado, versões | sim | etapa B |
| 3 | Frente I: robustez | sim | etapa C |
| 4 | Frente K: IA | sim | não |
| 5 | Frente J: cruzamento | sim | não |
| 6 | Parte I A–G: PEA | sim | não (usa o QR Code e o app atuais) |
| 7 | Frente H: BancoDOC por arquivo | sim | não |

O prompt do agente do app é gerado ao final, completo. Proponho entregar já na etapa 1 a parte A dele (formulário estruturado, moradores, calculados offline, rascunho persistido e envio atômico), para o app andar em paralelo e chegar a tempo do campo.

## 6. Decisões do questionário domiciliar (Leonardo, 25/11/2026)

1. **A renda é por morador.** Cada morador responde as fontes de renda dele, e a renda total do domicílio é a soma de todos os moradores. A estrutura continua aceitando fontes no domicílio, para outros formulários.
2. **O número de moradores da per capita vem da contagem do cadastro de moradores.** Se a pergunta "número de pessoas" for mantida, a divergência gera um alerta na coleta. Esta é a sugestão do próprio prompt.
3. **As perguntas de trabalho e renda valem a partir de 14 anos.** É o corte do IBGE, PNAD Contínua. Fica como lógica do conjunto, editável.
4. **Campos obrigatórios do morador:** nome, parentesco, idade e sexo. Quem é o respondente é marcado na lista de moradores.
5. **Prompt do app:** a parte A sai já na etapa 1. O prompt completo sai no final.
