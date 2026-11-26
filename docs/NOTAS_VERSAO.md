# Notas de versão do painel SXData

Cada publicação no ambiente de evolução ganha uma entrada aqui e o número em
`application/config/versao.php`. A tela **Controle e segurança > Versão e
novidades** mostra este arquivo.

## 2.0.0 (27/11/2026)

### Formulários estruturados (questionário domiciliar)
- Tela nova **Formulários estruturados**. O editor de questionários de hoje continua igual.
- Moradores, perguntas por morador, campos calculados (renda total e per capita) e regras de consistência.
- Conjuntos de perguntas reutilizáveis, temas, entrevistados pré-cadastrados e grupos repetidos (benfeitorias, bens).
- Prévia interativa, versões publicadas, fila de revisão e Excel com abas (domicílios, moradores, grupos, dicionário).
- Questionários JMC carregados: Formulário Geral e Pequenos Negócios.

### Controle e segurança (frente I)
- **Fila de revisão**: o servidor confere cada envio do app (simples e estruturado). Nada é recusado; o que tiver inconsistência aparece para conferir.
- **Trilha de auditoria**: logins, tentativas, visualização de entrevistas, edições, exclusões e exportações, com filtro e CSV.
- **Lixeira**: excluir entrevista, morador, questionário, projeto ou usuário guarda tudo; restaurar devolve como estava.
- **Verificação em duas etapas** (aplicativo autenticador) em **Minha conta**, troca de senha e política de senha.
- **Bloqueio por tentativas** e **expiração por inatividade**: os campos de Configurações passam a valer.
- **Erros do sistema** do painel, da API e do app, agrupados, com aviso no menu.
- **Aparelhos em campo**: o que cada aparelho ainda tem para enviar.
- `forms/submit` devolve a confirmação por parte (`partes`).
- Supervisor não altera administradores. Descrição automática de gráfico só para questionários do próprio cliente, com limite por hora.
- A lista de Respostas mostra as 2.000 mais recentes e o total, testada com 200 mil entrevistas simuladas.
- Configurações: as caixas desmarcadas agora são gravadas, e uma configuração nova passa a ser criada (antes a tela não gravava nada num banco sem configurações).

### Inteligência artificial (frente K)
- Cada função usa **OpenAI ou Anthropic (Claude Opus 5.5, Sonnet 5.5, Haiku 5.5)**, com um modelo de alternativa se o principal falhar.
- Tela **Provedores e custos**: chaves guardadas cifradas, teste de conexão, preço por modelo, gasto do mês, limite mensal com aviso e IA desligada por projeto.
- Tela **Comparar modelos**: o mesmo pedido em dois modelos, lado a lado, com tempo, tokens e custo.
- Antes de qualquer envio, **CPF, CNPJ, telefone, e-mail e nomes** de entrevistados e moradores saem do texto. O log guarda o texto já limpo.
- O log de IA mostra o provedor, se foi usada a alternativa e se o texto foi anonimizado.

### Cruzamento de variáveis (frente J)
- Tela nova para a equipe e para o cliente: perguntas de escolha, números em faixas, mês, entrevistador e local.
  - Nos estruturados, a unidade é domicílio ou morador.
- Contagem, % na linha, na coluna e do total, média e mediana.
- Qui-quadrado com valor-p e V de Cramér, com aviso de base pequena.
- Gráfico, Excel, CSV, imagem e PDF.
- Análises salvas, liberadas ao cliente e com interpretação por IA revisada.
- Cliente: só os projetos dele, sem variáveis que identificam pessoas, e células com menos de 5 respostas ocultas.

### Módulo PEA Gerdau (Parte I)
- PEA 2026–2031 carregado: 3 unidades, 17 comunidades, 14 escolas, 4 eixos, 16 projetos com faixas de IP, linhas temáticas, cronograma trimestral e índices EIEAP.
- Fichas das ações com QR Code da avaliação (10 perguntas, anônima, adaptada ao público), adesão, evidências e lista de presença.
- Motor de ponderação: ICC, IP, IS, IIA e IE com as fórmulas e faixas do PEA.
  - Regras editáveis e versionadas.
  - Ações encerradas e relatórios guardam a versão usada.
- Painel do PEA, árvore EIEAP e relatórios (ficha, semestral, anual, ciclo) com conferência e aprovação. PDF e Excel.
- BancoDOC por arquivo: base de terceiros, presença por CPF ou matrícula, arquivo de participações. Pronto para a API quando houver documentação.
