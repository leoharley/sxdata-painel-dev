# Prompt para o agente do App (sxdata-app-dev)

Para: o agente de código do aplicativo Flutter `sxdata-app-dev`. O painel e a
API já estão prontos e publicados no ambiente de evolução
(`https://painel.sxdata.online/api`).

Repositório do painel, para consulta: `sxdata-painel-dev`. Leia
**`docs/FORMULARIOS_ESTRUTURADOS.md`** inteiro antes de começar; é o contrato
(formato da definição, linguagem de expressões, API).

## Contexto

O cliente (SocialX) contratou a evolução descrita em
`docs/PLANO_EVOLUCAO_GERDAU_SRA.md` (painel). A parte que envolve o app é o
**formulário estruturado**: questionário domiciliar com lista de moradores,
bloco de questões repetido por morador, campos calculados (renda total e per
capita), pulos, opções filtradas e alertas.

**As entrevistas de campo da pesquisa socioeconômica da JMC (Itapicuru e
Jaboticaba) começam na semana que vem**: a Parte A é prioridade absoluta e
precisa estar estável offline. Os dois formulários reais já estão publicados
e ativos na evolução:

- "JMC - Pesquisa Socioeconômica das Unidades Familiares (Formulário Geral)":
  197 questões, lista de moradores na 17, questões 18 a 67 por morador, renda
  na 109 e 110.
- "JMC - Pesquisa dos Pequenos Negócios": 137 questões, pulos para a 121.
- As fontes estão em `sxdata-painel-dev/docs/formularios/*.txt`. Use-as para
  conferir o comportamento esperado de cada questão.

## Regras

1. **Os questionários de hoje não mudam.** `GET api/questionnaires` e
   `POST api/forms/submit` continuam iguais, assim como as telas atuais.
   O formulário estruturado é um **fluxo novo, separado**. O painel já tira
   os estruturados de `api/questionnaires`.
2. **Nenhuma entrevista pode se perder**: nem na coleta (fechar o app no meio),
   nem no envio, nem ao atualizar o app. Hoje a entrevista em andamento fica
   só na memória; no fluxo novo ela **precisa** ser salva como rascunho
   persistente a cada resposta.
3. Siga as convenções do projeto (Provider, `LocalStorageService`, mensagens
   de commit em português sem acento, testes com `flutter_test`), com mudança
   de escopo mínima no fluxo antigo.
4. Um commit por etapa, com testes. **Não faça push, build de loja nem
   publicação sem a confirmação do Leonardo.** A versão web vai para
   `app.sxdata.online` pelo `deploy/evolucao/publicar-local.sh app` do painel,
   que roda no computador do Leonardo.

## Parte A: formulário estruturado (urgente, para o campo)

### A1. Download e cache

- **Download:**
  - `GET api/estruturado/formularios` (Bearer token) devolve
    `data: [{id, titulo, descricao, project_id, project_name, versao, versao_id, definicao}]`.
  - Baixe junto com os questionários de sempre e guarde em cache, para uso
    offline, com a mesma lógica de reconciliação de `QuestionnaireProvider`:
    mostra o cache, troca pelo da rede e, se falhar, mantém o cache.
- **Na tela inicial:** mostre os estruturados com um selo ("Domiciliar").
- **Versão:**
  - Guarde a `versao` com cada entrevista.
  - Rascunho iniciado na versão N continua na N, mesmo que chegue a N+1.
  - Guarde no cache as definições que tenham rascunhos ou entrevistas pendentes.

### A2. Motor de expressões e regras (Dart)

Implemente em Dart **a mesma linguagem e o mesmo motor** do painel:
- `application/libraries/Expressao.php`: léxico, parser e avaliação, incluindo nulo, NS/NR e "incompleto";
- `application/libraries/Estrutura_motor.php`: visibilidade com pulo, `opcoes_de`, obrigatoriedade, complementos, opções exclusivas, calculados com arredondamento por formato, regras da lista de moradores, alerta de divergência entre a lista e a questão declarada (`declarado`), alertas por questão e `[NOME]`.

- **Casos compartilhados:** o arquivo `tests/fixtures/expressoes.json` do
  painel tem os casos que **os dois motores** precisam passar. Copie-o para o
  app e crie um teste que o percorra. O comportamento tem de ser idêntico: o
  servidor recalcula tudo e manda para revisão o que divergir.
- **Teste de integração do motor:** reproduza em Dart os cenários de
  `tests/sxdata/Estrutura_motor_test.php`:
  - renda excluindo pensionista;
  - NS/NR deixando a renda incompleta;
  - CPF só para chefe e cônjuge;
  - idade < 5 escondendo a 25;
  - pulo da 6 para a 121;
  - 144 mostrando só o que foi marcado na 143.

  Use as definições compiladas dos dois formulários: baixe-as da API ou
  gere o JSON a partir dos `.txt`.

### A3. Telas da entrevista

- **Ordem:** as etapas seguem a ordem da definição.
  - `perguntas`/`domicilio`: uma questão por página, como no fluxo atual, mostrando bloco e seção.
  - `moradores`: lista editável (adicionar, editar, remover, reordenar).
    - O 1º morador é o respondente: sugira o nome da questão `respondente` (q12).
    - Mostre o alerta quando a lista divergir da questão `declarado` (q15).
  - `perguntas`/`morador`:
    - **lista de moradores com status** (não iniciado, em andamento, concluído);
    - tocar num morador abre **todas** as questões dele, em sequência, com `[NOME]` trocado pelo nome;
    - ao concluir, volta à lista.
  - Mais de uma etapa por morador, separadas por etapas do domicílio: trate
    cada etapa por morador como um bloco próprio com a mesma lista.
- **Navegação:** livre entre etapas já visitadas. Mostre um sumário das pendências.
- **Questão:** tipos e marcas conforme o manual:
  - `unica`, `multipla` (com exclusivas), complemento de opção ("Sim. Quantas:" pede um número), `complemento` da questão;
  - NS/NR (`nsnr`) num campo de digitar, como uma caixa "NS/NR" que desabilita o campo;
  - `moeda` com máscara R$, `inteiro`, `decimal`, `data`, `hora`, `telefone` (máscara atual), `cpf`/`cnpj` (máscara);
  - `composto` (campos lado a lado), `lista` (N campos), `texto_longo`, `mensagem`.
- **Calculado:** somente leitura, atualizado a cada resposta. Mostre "incompleta" quando houver NS/NR na conta.
- **Preenchimento automático:**
  - `auto=data` e `auto=hora`: preenchidos ao iniciar, editáveis;
  - `auto=hora_fim`: preenchido ao finalizar.
- **`coordenadas`:** captura automática com o `LocationService` atual (retry ou seguir sem), com botão para recapturar.
- **`foto`:**
  - usa a captura e a fila de fotos atuais;
  - o valor da resposta é o **nome do arquivo** enviado;
  - a foto vai também para `photo_path` (campo `destino: "foto"`).
- **Pergunta oculta:** quando uma pergunta fica oculta, a resposta dela
  continua no rascunho, mas **não vai** no envio. O servidor também descarta.
- **Finalizar:** só com zero erros (obrigatórias, tipos, complementos,
  regras `bloqueia`) e todos os moradores concluídos. Alertas aparecem num
  resumo antes de confirmar, mas não bloqueiam.
- **Áudio:** a gravação contínua da entrevista funciona como hoje.

### A4. Rascunho persistente e armazenamento

- **Rascunho:** é gravado a cada resposta (com debounce curto). Reabrir o
  app mostra "Entrevistas em andamento", que você retoma de onde parou.
  Também é possível excluir um rascunho, com confirmação.
- **Armazenamento:** não use SharedPreferences/localStorage. Uma entrevista
  com 8 moradores passa de 100 KB, e o limite do web é de ~5 MB.
  - **Nativo:** `sqflite` (já está no pubspec) ou arquivos JSON em `path_provider`.
  - **Web:** um object store novo no IndexedDB `sxdata_media`, que exige subir a versão e um ramo em `onUpgradeNeeded`.
  - Abstraia num serviço, como já é feito com `web_media_store`.
- **Compatibilidade:** atualizar o app (Play Store ou versão web) não pode
  apagar rascunhos nem entrevistas pendentes. Siga o mesmo cuidado dos
  testes `update_keeps_pending_test`.

### A5. Envio

- **Endpoint:** `POST api/estruturado/enviar`, com o corpo descrito no manual (seção 5):
  - `client_id` da entrevista e de cada morador (UUID gerado no aparelho, estável entre reenvios);
  - respostas visíveis, moradores com respostas, `calculados` do app.
- **Fila:** a mesma dos formulários atuais. Mídia primeiro (fotos e áudio pelos endpoints atuais), depois a entrevista.
- **Prova de gravação:** a entrevista só sai da fila com `success` e `id`
  **e** com `partes.moradores` igual ao número de moradores enviados. Se
  vier `missing_media`, reenvie as mídias e depois a entrevista (mesmo
  `client_id`).
- **Erros permanentes:** `400` com `json_error`, `404` e `422` marcam a
  entrevista como "erro" e a mantêm no aparelho. `413` mantém pendente,
  avisando. O resto tenta de novo no próximo sincronizar.
- **Edição antes de enviar:** permita editar a entrevista enquanto estiver
  pendente. Depois de enviada, a edição reenvia com `edited_at`, e o
  servidor substitui por inteiro.
- **Histórico:** mostre as estruturadas no histórico e nas telas de enviados
  e pendentes, com o número de moradores.

### A6. Testes da Parte A (obrigatórios)

- Fixture compartilhada (`expressoes.json`) passando inteira.
- Motor com os formulários da JMC (cenários do item A2).
- Rascunho sobrevive a "fechar e reabrir", simulado no storage.
- Envio: sucesso, duplicado, `missing_media`, falha de rede (fica pendente), `partes.moradores` divergente (não libera).
- Widget: lista de moradores com status; finalizar bloqueado com pendência e liberado sem ela.

### Como testar contra a evolução

- **Build web:** `bash deploy/evolucao/publicar-local.sh app` no painel. O
  build usa `API_BASE_URL=https://painel.sxdata.online/api`; para builds
  locais, use o mesmo `--dart-define`.
- **Login:** um aplicador da evolução (o Leonardo informa as credenciais).
- **Prévia:** abra o formulário no painel (Formulários estruturados → Prévia)
  e compare com a mesma entrevista no app. A prévia usa o motor do servidor,
  que é a referência.
- **Conferência:** depois de enviar, confira a entrevista em Formulários
  estruturados → Entrevistas. Ela mostra moradores, calculados, alertas e a
  fila de revisão. Uma entrevista "a revisar" indica divergência entre os
  motores ou regra quebrada.

## Parte B: conjuntos, temas, entrevistados e repetições (depois da Parte A)

Leia a seção 7 de `docs/FORMULARIOS_ESTRUTURADOS.md`. O motor do painel já
trata tudo isto. Os testes de referência estão em
`tests/sxdata/Estrutura_conjuntos_test.php`; reproduza-os em Dart.

1. **Condição de etapa:** etapa com `"se"` falso tem todas as questões
   ocultas. Aplique ao calcular a visibilidade, antes da condição da
   questão. Na lista de etapas, uma etapa fechada não aparece.
2. **Temas:**
   - a questão `temas` é uma múltipla escolha comum, já incluída na definição;
   - nenhum tratamento especial é necessário, além de mostrá-la no início com o texto de ajuda.
3. **Entrevistados:**
   - **Download:** baixe `GET api/estruturado/entrevistados` dos projetos do
     aplicador e guarde offline no mesmo armazenamento do rascunho (não em
     SharedPreferences). Atualize de forma incremental com
     `desde = servidor_em` do último download e remova os que vierem com
     `ativo: false`.
   - **Escolha:** se a definição tem `"entrevistado"`, mostre antes da
     primeira etapa uma busca por nome ou código entre os entrevistados do
     projeto do formulário. No modo `obrigatorio`, a entrevista não começa
     sem a escolha; no `opcional`, há o botão "Sem cadastro".
   - **Contexto:** o `contexto` do entrevistado escolhido alimenta as
     referências `ent_*` do motor. As questões com `preencher` recebem o
     valor como sugestão, e o entrevistador pode alterar.
   - **Envio:** mande `entrevistado_id`.
4. **Repetições (`nivel: grupo`):**
   - **Tela:** igual à lista de moradores: itens com rótulo
     (`repetir.rotulo` com `{n}`), status de cada item e os botões
     adicionar e remover (com confirmação).
   - **Quantidade:** com `repetir.por`, mostre "X de N informados" e
     impeça finalizar se não bater; respeite `max` e `min`.
   - **Motor:** cada item é um escopo que enxerga o domicílio. As funções
     `soma_grupo`, `contar_grupo`, `existe_grupo` e `todos_grupo` agregam
     os itens. Os casos estão em `tests/fixtures/expressoes.json` e no
     teste PHP citado acima.
   - **Envio:** `grupos: {codigo: [{client_id, indice, respostas}]}`, com
     o UUID de cada item estável. A entrevista só sai da fila quando
     `partes.repeticoes` conferir.
5. **Testes da Parte B:**
   - etapa aberta e fechada por resposta, por tema e por categoria;
   - entrevistado obrigatório e opcional, com preenchimento;
   - grupo com `por`, `max` e soma com NS/NR marcando incompleto;
   - download incremental de entrevistados (novo, alterado e removido).

## Parte C: robustez (depois da Parte B)

A frente I do painel está publicada na evolução. Os endpoints abaixo já
existem; os campos novos não quebram as versões atuais do app.

1. **Confirmação por parte no fluxo antigo (`POST api/forms/submit`).**
   - A resposta agora traz `partes: {respostas, recebidas, revisao, midias_pendentes}`.
   - Só tire a coleta da fila quando `success` e `id` vierem **e**
     `partes.respostas` for igual ao número de itens com `question_id`
     enviados. Se divergir, mantenha pendente e reenvie (mesmo `client_id`;
     o servidor não duplica).
   - Versões antigas do servidor não mandam `partes`: nesse caso, mantenha a
     regra de hoje (sucesso + id).
   - `partes.revisao = "pendente"` não é erro: o servidor gravou e mandou para
     a fila de revisão do painel. Não mostre como falha ao entrevistador.
2. **Identificador único em toda coleta.** Toda coleta (simples ou
   estruturada) nasce com `client_id` UUID v4, guardado no rascunho e estável
   em reenvios e edições. Confira que nenhuma tela ou migração de storage
   gera um `client_id` novo para uma coleta que já tinha um.
3. **Situação do aparelho: `POST api/system/dispositivo`** (com o token do aplicador).
   - Corpo: `{device_id, app_versao, plataforma, pendentes, pendentes_midias, rascunhos, ultimo_envio_em}`.
   - `device_id`: UUID gerado na primeira execução e guardado (não use IMEI nem dado pessoal).
   - Envie ao abrir o app, ao fim de cada sincronização (com ou sem sucesso) e,
     com o app aberto, no máximo a cada 15 minutos.
   - Falha nessa chamada nunca bloqueia nada: só tente de novo na próxima.
   - O painel mostra em Controle e segurança > Aparelhos quem tem fila parada.
4. **Erros do app: `POST api/system/erro`** (com token).
   - Corpo: `{mensagem, nivel: "erro"|"aviso"|"fatal", tela, app_versao, plataforma, device_id, detalhes, pilha}`.
   - Mande nos erros não tratados (`FlutterError.onError`,
     `PlatformDispatcher.instance.onError`), em falhas de envio com status
     inesperado e em falhas de leitura do storage.
   - **Nunca** mande respostas da entrevista, nomes, CPF, telefone ou token
     no corpo.
   - Limite: no máximo 20 erros por execução; sem rede, guarde os 20 últimos
     e mande depois.
   - O servidor agrupa erros iguais (Controle > Erros do sistema).
5. **Tela de envios pendentes.** Mostre, por coleta: situação (pendente, erro,
   aguardando mídia), número de tentativas, último erro e o botão
   "Tentar agora". Dê também a opção "Exportar pendências" (arquivo JSON
   cifrado ou compartilhável) para o suporte recuperar uma coleta presa.
6. **Testes da Parte C:**
   - `partes.respostas` divergente mantém pendente;
   - resposta sem `partes` (servidor antigo) segue a regra antiga;
   - heartbeat enviado após sincronizar e falha dele não bloqueia;
   - erro não tratado vai para a fila de erros sem dados pessoais.

## Parte D: PEA Gerdau (formulário de avaliação no modo assistido)

O módulo PEA do painel usa um **questionário simples** já publicado na
evolução: "PEA Gerdau — Avaliação da atividade" (10 perguntas). Ele chega ao
app pelo `GET api/questionnaires` de sempre. Nada muda no fluxo antigo;
apenas:

1. **Opções por público.** As opções da pergunta 1 têm `option_value` com
   prefixo do público: `int_` (interno), `ext_` (comunidades), `esc_`
   (escolar).
   - Ao abrir esse questionário (reconheça pelo título), pergunte primeiro
     "Público da atividade" (Interno, Comunidade, Escolar) e mostre só as
     opções do prefixo escolhido.
   - Esse campo não é enviado como resposta.
   - Na pergunta 4, troque `{apoio}` no texto por: " (no seu trabalho e em
     casa)", " (na sua comunidade)" ou " (na escola, em casa e na
     comunidade)".
2. **Anônimo.** Não peça nome, CPF nem consentimento identificado nesse
   questionário.
3. **Ligação com a ação.** As respostas do app chegam sem ação; a equipe as
   liga à ação na ficha (o painel mostra as feitas no dia da ação). Mande
   sempre `completed_at` com a hora real do aparelho.
4. **Modo de leitura assistida.** No público externo e no escolar, ofereça
   fonte grande (1,3×) e botões maiores nesse questionário.

