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
