# Roteiro de regressão do painel

Rodar antes de cada publicação na produção. Na evolução: `https://painel.sxdata.online`.

## Automático

- [ ] `php vendor/bin/phpunit -c tests/sxdata/phpunit.xml` (motor dos estruturados, expressões, TOTP, política de senha, validação do formato simples, IA, cruzamento, PEA, BancoDOC).
- [ ] `php -l` em todos os arquivos de `application/` (nenhum erro).
- [ ] Migrações novas rodadas, em ordem: `estruturado`, `conjuntos`, `robustez`, `ia_provedores`, `cruzamento`, `pea`.

## O que já existia (não pode mudar)

- [ ] Login de administrador, supervisor e cliente. O cliente cai no painel dele e não abre telas da equipe pela URL.
- [ ] Criar e editar um questionário simples no editor de sempre, com lógica condicional.
- [ ] App: baixar questionários, coletar offline, enviar.
  - A coleta aparece em Respostas.
  - Reenvio não duplica.
  - Edição substitui.
- [ ] Formulário por QR Code (link público): responder, conferir a resposta, limite por IP.
- [ ] Respostas: lista (mostra as 2.000 mais recentes e o total), ver entrevista, exportar Excel e dados brutos.
- [ ] Relatórios e análises, relatórios automatizados (municipais), mapa integrado, KMZ.
- [ ] IA: com uma chave configurada, uma função (por exemplo, análise estatística) gera texto e aparece em Logs de IA com o provedor.

## Formulários estruturados

- [ ] Prévia do Formulário Geral da JMC: moradores, questões por morador, renda total e per capita, pulos.
- [ ] Entrevista enviada pelo app aparece com moradores e calculados. A divergência vai para a fila de revisão.
- [ ] Excel com abas: domicílios, moradores, grupos e dicionário.

## Controle e segurança

- [ ] 5 senhas erradas bloqueiam o usuário por 15 minutos. O administrador desbloqueia em Usuários.
- [ ] Minha conta: ativar o 2FA com o aplicativo autenticador; no próximo login, o código é pedido.
- [ ] Inatividade maior que o tempo de Configurações derruba a sessão.
- [ ] Excluir e restaurar uma entrevista, um morador e um usuário (Lixeira).
- [ ] Auditoria registra login, visualização, exportação e exclusão. O CSV abre no Excel.
- [ ] Erros do sistema: um erro do PHP aparece agrupado. Marcar como resolvido.

## Novas frentes

- [ ] Cruzamento: duas perguntas, % na linha, qui-quadrado, gráfico, Excel/CSV/imagem/PDF, salvar, liberar ao cliente. O cliente vê "< 5".
- [ ] IA: cadastrar a chave da Anthropic, testar a conexão, comparar dois modelos, limite mensal, IA desligada no projeto.
- [ ] PEA: criar uma ação, abrir o QR, responder pelo celular e conferir a adesão e os indicadores na ficha.
  - Registrar a presença por CPF.
  - Encerrar a ação.
  - Gerar o relatório anual, enviar para conferência, aprovar e imprimir.
  - Exportar participações para o BancoDOC.
