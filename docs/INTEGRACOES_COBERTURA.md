# Integrações de dados: cobertura dos indicadores

Tarefa 1 de `docs/PROMPT_SERGIO_INTEGRACOES.md`. Situação em 10/11/2026.

## Conectores registrados

`application/config/relatorio_conectores.php`:

| Conector | Tipo | Eixos | Fonte |
|---|---|---|---|
| `ibge_comum.js` | (funções compartilhadas, não é conector) | | |
| `ibge_caracterizacao` | JS | caracterizacao | IBGE Biblioteca, IBGE Cidades, SIDRA |
| `ibge_populacao` | JS | territorio_populacao | SIDRA (estimativas e Censos) |
| `ibge_economia` | JS | economia_trabalho | SIDRA (PIB, CEMPRE, PAM, Censo), IBGE Cidades (frota) |
| `ibge_educacao` | JS | educacao | IBGE Cidades (IDEB, Censo Escolar), SIDRA (Censo 2022) |
| `ibge_meio_ambiente` | JS | meio_ambiente | SIDRA (Censo 2022), IBGE Cidades |
| `ibge_saude` | JS | saude | IBGE Cidades (SIM/SINASC) |
| `Conector_cnes` | PHP | saude, saude_aps, saude_mac | API de dados abertos do Ministério da Saúde (sem CORS, por isso PHP) |
| `Conector_esus_aps` | PHP | saude_aps | e-SUS do município (aguardando acesso) |
| `Conector_esus_mac` | PHP | saude_mac | e-SUS do município (aguardando acesso) |

Todas as APIs usadas pelos conectores JS (`servicodados.ibge.gov.br`) respondem
`Access-Control-Allow-Origin: *`, então funcionam em `painel.sxdata.online` e
em `painel.sxdata.com.br`.

## Indicadores cobertos

| Indicador | Conector | Origem do dado | Abrangência |
|---|---|---|---|
| historia | ibge_caracterizacao | IBGE Biblioteca (histórico, formação administrativa, gentílico) | município |
| quadro_politico | ibge_caracterizacao | IBGE Cidades 29170 (prefeito, dados do TSE). **Parcial:** só o prefeito do mandato atual | município |
| area_territorial | ibge_caracterizacao | SIDRA 4714 (Censo 2022) | 3 níveis |
| populacao_censo | ibge_caracterizacao | SIDRA 4714 (2022) + IBGE Cidades 96385 (2010) | 3 níveis (2010 só município) |
| populacao_estimada | ibge_populacao | SIDRA 6579 (2001 em diante) + 4714 (2022, ano sem estimativa) | 3 níveis |
| densidade_demografica | ibge_populacao | SIDRA 4714 | 3 níveis |
| taxa_urbanizacao | ibge_populacao | SIDRA 202 (1991, 2000, 2010) + 9923 (2022) | 3 níveis |
| populacao_faixa_sexo | ibge_populacao | SIDRA 9514 (Censo 2022), grupos de 5 anos, 80+ somados | município |
| domicilios | ibge_populacao | SIDRA 4712 | 3 níveis |
| densidade_domiciliar | ibge_populacao | SIDRA 4712 | 3 níveis |
| saneamento_basico | ibge_populacao | SIDRA 6803, 6805, 6892 (% dos domicílios, 2022) | 3 níveis |
| pib | ibge_economia | SIDRA 5938, últimos 10 anos | 3 níveis |
| pib_per_capita | ibge_economia | município: IBGE Cidades 47001; estado e Brasil: PIB / população estimada do ano | 3 níveis |
| pib_composicao | ibge_economia | SIDRA 5938 (valor adicionado + impostos), ano mais recente com dado no município | município |
| pea | ibge_economia | SIDRA 10299 (14 anos ou mais na força de trabalho, Censo 2022) | 3 níveis |
| taxa_ocupacao | ibge_economia | IBGE Cidades 60036 (pessoal ocupado / população, CEMPRE) | município |
| ocupados_por_setor | ibge_economia | SIDRA 9528 (CEMPRE por seção da CNAE), ano mais recente | município |
| empresas | ibge_economia | SIDRA 1685 (até 2021) + 9510 (2022 em diante), unidades locais | 3 níveis |
| frota_veiculos | ibge_economia | IBGE Cidades, pesquisa 22 (SENATRAN), por tipo | município |
| producao_agricola | ibge_economia | SIDRA 5457 (PAM), valor por produto | município |
| ideb_anos_iniciais / ideb_anos_finais / ideb_ensino_medio | ibge_educacao | IBGE Cidades 78187 / 78192 / 78197 (INEP, rede pública) | município e UF |
| taxa_analfabetismo | ibge_educacao | 100 − SIDRA 9543 (alfabetização 15+, 2022) | 3 níveis |
| taxa_escolarizacao_6_14 | ibge_educacao | SIDRA 10139 (2022) + IBGE Cidades 60045 (2010) | 3 níveis (2010 só município) |
| matriculas_etapa | ibge_educacao | IBGE Cidades, pesquisa 13 (Censo Escolar): creche, pré-escola, fundamental, médio | município |
| esgotamento_adequado | ibge_meio_ambiente | SIDRA 6805 (rede geral, pluvial ou fossa ligada à rede, 2022) | 3 níveis |
| informacoes_ambientais | ibge_meio_ambiente | texto com bioma, sistema costeiro, arborização, urbanização de vias, área urbanizada, população em área de risco | município |
| mortalidade_infantil | ibge_saude | IBGE Cidades 30279 | município e UF |
| estabelecimentos_saude | Conector_cnes | CNES (só ativos) | município |
| estabelecimentos_nivel | Conector_cnes | CNES por tipo de unidade, em 4 categorias (ver abaixo) | município |
| ubs_quantidade | Conector_cnes | CNES tipos 01 (posto) e 02 (centro de saúde/UBS) | município |
| estabelecimentos_mac | Conector_cnes | CNES: média complexidade + atenção hospitalar | município |

**Categorias de `estabelecimentos_nivel`.** O CNES não classifica o
estabelecimento por complexidade; a classificação é pelo tipo de unidade
(`Conector_cnes::NIVEIS`):

- Atenção básica: tipos 01, 02, 32, 40, 71, 72, 74;
- Média complexidade (ambulatorial e urgência): 04, 20, 21, 22, 36, 39, 42, 61, 69, 70, 73, 77, 78, 79
  (inclui consultórios isolados, que dominam em cidades grandes);
- Atenção hospitalar: 05, 07, 15, 62;
- Gestão, vigilância e apoio: os demais.

## Indicadores sem fonte (complementação manual na tela Dados)

| Indicador | Eixo | Motivo |
|---|---|---|
| renda_domiciliar_per_capita | economia_trabalho | O Censo 2022 publica o rendimento domiciliar per capita médio só para o Brasil (SIDRA 10315); não há valor por município no SIDRA nem no IBGE Cidades. |
| politica_educacao | educacao | Levantamento municipal: não existe em base pública. |
| idhm, idhm_educacao, idhm_longevidade, idhm_renda | social_gestao | O Atlas Brasil (PNUD) não tem API aberta; no IBGE Cidades o IDH (indicador 30255) só vem para as UFs. |
| ivs | social_gestao | IPEA não tem API pública do IVS (só a plataforma e planilhas). |
| gestao_fiscal | social_gestao | IFGF da FIRJAN só é publicado em planilha/site, sem API. |
| taxa_homicidios | social_gestao | A API do Atlas da Violência (IPEA) está fora do ar (HTTP 404 em 10/11/2026). |
| assistencia_social | social_gestao | Levantamento municipal. |
| politica_ambiental, coleta_seletiva | meio_ambiente | Vêm da MUNIC/SNIS, publicadas só como planilhas por edição; não há API por indicador. |
| leitos_por_mil | saude | Leitos do CNES não estão na API de dados abertos (só estabelecimentos); o TABNET não tem API. |
| taxa_mortalidade_geral | saude | SIM só pelo TABNET (formulário HTML) ou arquivos DBC do DATASUS. |
| internacoes_por_causa | saude | SIH só pelo TABNET/DBC. |
| incidencia_aids | saude | SINAN só pelo TABNET/DBC. |
| politica_saude | saude | Levantamento municipal. |
| equipes_esf, cobertura_aps, profissionais_aps, atendimentos_aps, procedimentos_aps | saude_aps | Dependem do e-SUS do município (acesso ainda não liberado). Equipes e profissionais também existem no CNES, mas não na API aberta. |
| leitos_sus, internacoes_total, atendimentos_ambulatoriais_mac, procedimentos_mac | saude_mac | Dependem do e-SUS do município ou do SIH/SIA (TABNET/DBC, sem API). |

**Parciais:** `quadro_politico` traz só o prefeito do mandato atual (partido,
vice e câmara ficam para complementar). `taxa_ocupacao`, `matriculas_etapa`,
`frota_veiculos` e `producao_agricola` vêm só do município (a fonte não dá
estado e Brasil no mesmo formato, e os gráficos de ranking usam só o
município).

**Fase 2 possível:** um conector PHP para o TABNET (POST no formulário do
DATASUS e leitura da tabela devolvida) cobriria leitos, mortalidade geral,
internações por causa, AIDS e boa parte de `saude_mac`, sem depender do
e-SUS.

## e-SUS

Os conectores `Conector_esus_aps` e `Conector_esus_mac` estão registrados com
a estrutura pronta (`Conector_esus_base`): conexão PostgreSQL somente leitura
(`SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY`, `statement_timeout`
de 30 s, só aceita `SELECT`). As credenciais vão no `.env` do servidor:

```
ESUS_<COD_IBGE>_HOST=
ESUS_<COD_IBGE>_PORT=5432
ESUS_<COD_IBGE>_DB=
ESUS_<COD_IBGE>_USER=
ESUS_<COD_IBGE>_PASS=
ESUS_<COD_IBGE>_SSLMODE=prefer
```

Sem a credencial, a tela de Processamento mostra "Credencial do e-SUS não
configurada para <município> (<código>)..." e os demais conectores seguem.
Com a credencial, o conector testa a conexão e avisa que as consultas ainda
não foram escritas: o esquema da base precisa ser conferido numa base real.

## Tempo de execução

- Conectores JS: de 0,1 s a 4 s cada (Monte do Carmo, Brasília, São Paulo).
- `Conector_cnes`: a API devolve 20 estabelecimentos por página; as páginas
  são buscadas 20 em paralelo. Monte do Carmo: 0,4 s. **São Paulo: 65 s**
  (cerca de 30 mil estabelecimentos ativos). O conector eleva o
  `set_time_limit` para 300 s, mas o proxy/PHP-FPM do servidor pode cortar
  antes: para capitais, vale mover para execução em segundo plano.

## Lista de municípios

`sql/ibge_municipios_seed.sql`: 5.571 municípios, gerado em 10/11/2026 de
`servicodados.ibge.gov.br/api/v1/localidades/municipios`, com
`ON CONFLICT (codigo) DO UPDATE`. Pode rodar mais de uma vez.

## Como testar

1. Rode `sql/municipal_reports_migration.sql` (se ainda não rodou) e
   `sql/ibge_municipios_seed.sql`.
2. Em Relatórios Automatizados > Gerar relatório, digite "Monte do Carmo":
   a busca sugere "Monte do Carmo - TO (1713601)".
3. Gere o relatório com todos os eixos. Na tela de Processamento, os
   conectores IBGE e CNES terminam com ✔ e "N valor(es) gravado(s)"; os dois
   do e-SUS terminam com a mensagem de credencial não configurada.
4. Confira na tela Dados, por exemplo: área 3.601,21 km², população do Censo
   2022 de 5.694 habitantes, 1.929 domicílios.
5. Na Revisão, os gráficos dos eixos cobertos aparecem preenchidos, com a
   fonte e o ano de referência.
