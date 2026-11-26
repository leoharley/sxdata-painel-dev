# Módulo PEA Gerdau (Parte I)

Menu **PEA Gerdau**, só para administrador e supervisor. Estrutura, regras e
aprovação de relatórios: só administrador. Migração: `sql/pea_migration.sql`.
Carga do programa: `application/config/pea_2026_2031.json` (botão "Carregar o
PEA 2026–2031" em Estrutura). Na evolução, o programa já está carregado.

## Telas

| Tela | O que faz |
|---|---|
| Painel | Cartões de ICC, IP, IS, IIA e IE com a faixa do PEA, alerta de ações atrasadas, execução por projeto, IE e componentes por projeto, cronograma previsto × realizado por trimestre, satisfação por item, P9, EIEAP por unidade (base, atual, meta, falta) e respostas abertas. Filtros: ano, trimestre, público, unidade, comunidade/ABEA, projeto. |
| Ações | Lista e ficha da ação: dados de controle, QR Code, adesão (avaliações ÷ participantes), indicadores, satisfação por item, P7 e P9, evidências (fora do acesso público), lista de presença (com busca na base do BancoDOC), ligar avaliações feitas pelo app, encerrar. |
| Relatórios | Ficha da ação, formulário semestral, relatório anual e do ciclo. Rascunho → conferência → aprovado. PDF pela impressão (rascunho sai com marca d'água) e base de cálculo em Excel. |
| Estrutura e cronograma | Projetos (eixo, periodicidade, faixas de IP, se tem avaliação), cronograma trimestral editável, unidades, comunidades, escolas e linhas temáticas. |
| Regras de ponderação | Pesos e escolhas das fórmulas, versionadas com motivo e responsável. |
| Percepção (EIEAP) | Árvore de indicadores com peso, base 2021, valor atual e meta por unidade. |
| BancoDOC | Importação da base de terceiros por planilha, arquivo de participações, colaboradores mobilizados sem Módulo Básico e histórico. |

## Fórmulas (regras v1)

- **IS** = (Satisfeito + Muito satisfeito na P6) ÷ participantes da lista × 100.
  - Sem nenhuma avaliação recebida, o IS fica vazio ("não aplicado") e não entra na média.
  - Denominador configurável: participantes ou respondentes (ponto 1).
- **IIA** = [0,8·a + 0,6·c + 0,2·(d − 1)] ÷ 0,6 × 100, com a, c e d em proporção sobre quem respondeu a P7. Piso em 0 (ponto 2).
- **IP**: participantes convertidos em 30, 60 ou 100 pelas faixas do projeto (Tabelas 11, 17 e 23).
  - Não realizada vale 0. Abaixo da faixa baixa vale 30 (ponto 3).
  - Projeto sem faixa (Comunicados, Workshop) fica fora do IP e do IE (ponto 5).
- **ICC** = realizadas no trimestre previsto ÷ previstas × 100. Previstas é o maior entre o cronograma e as fichas abertas no período. Tolerância em trimestres configurável (ponto 4).
  - Na ficha, a ação vale 100 (no prazo) ou 0.
  - Com ICC abaixo de 50, a justificativa é obrigatória ao encerrar.
- **IE** = média ponderada de ICC, IP, IS e IIA (pesos 1). Componente vazio fica fora.
- **Agregação**: ICC pelo total do período; IP, IS e IIA pela média das ações; IE dos quatro.
- **Faixas de leitura**: as do PEA (Tabelas 02, 10 e 12 a 14).

Ao encerrar, a ação congela os indicadores com a versão das regras. O
relatório congela os dados na geração. Mudar as regras depois não altera
números já enviados.

## Formulário de avaliação

- Questionário simples "PEA Gerdau — Avaliação da atividade", com 10 perguntas, conforme a versão teste de 23/10/2026.
- Cada ação tem o seu link público e QR Code. As respostas ficam ligadas à ação pelo link.
- As perguntas 1 e 4 se adaptam ao público da ação: opções com prefixo `int_`, `ext_` e `esc_`, e o texto `{apoio}`.
- A opção do público escolar foi incluída (ponto 6).
- Ao encerrar a ação, o QR Code é desativado.
- Preenchimento assistido pelo app: as respostas do dia da ação aparecem na ficha para a equipe ligar.

## Carga do PEA 2026–2031: o que foi interpretado

- **Cronograma**: o PEA marca só "X" por trimestre, sem quantidade. Cada trimestre marcado conta como 1 ação prevista; ajuste em Estrutura.
  - Cada ano do ciclo tem 5 colunas no documento (3º, 4º, 1º, 2º e 3º trimestres civis).
  - No módulo, os anos vão de agosto a julho, com trimestres 1 a 4. A 5ª coluna (3º trimestre do fim do ano) entrou no 4º trimestre.
  - As linhas de formulário semestral e relatório anual não são ações e ficaram de fora.
- **Workshop de Planejamento** não tem cronograma nem faixa de IP no PEA.
- **EIEAP**: os 20 índices da Tabela 03 (valores de 2025) e a linha de base e as metas da Tabela 04 (8 índices), com pesos iguais.
  - As perguntas e os pesos do método dependem da Gerdau (ponto 7).
  - MVL "Interesse e Motivação Geral": usado 36,7 (Tabela 03); a Tabela 04 traz 64,1, que parece erro de cópia.
- **Comunidades** da AID do transporte de minério não pertencem a uma unidade (tipo "AID transporte de minério").

## Relatórios

- Foi feito um fluxo próprio (`pea_relatorios` e `pea_relatorio_eventos`), no mesmo padrão dos relatórios municipais: rascunho, conferência, aprovação e histórico.
- `municipal_reports` não foi alterado, para não arriscar os relatórios municipais em uso.

## BancoDOC (frente H)

- Por arquivo: planilha de colaboradores e empresas (CSV ou Excel, cabeçalhos flexíveis, CPF validado, atualização por CPF ou matrícula) e arquivo CSV de participações das ações encerradas.
- O layout do arquivo de participações é provisório até a Gerdau indicar o de importação do BancoDOC.
- A interface `Bancodoc_conector` tem a implementação por API à espera de documentação e credenciais (ponto 9).
- As avaliações nunca vão para o BancoDOC.
- Os arquivos de evidência ficam em `uploads/pea/`, bloqueado no nginx. O download é só pelo painel e entra na auditoria.
