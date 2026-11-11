# Plano: mapa integrado (Tarefa 2)

Seção 7 de `docs/PROMPT_SERGIO_INTEGRACOES.md`. **Aprovado pelo Leonardo em
10/11/2026, com 3 ajustes** (já incorporados abaixo):

1. Vínculo coleta–ponto adiado: `form_responses` guarda também o
   `field_point_client_id`; quando o ponto chega em `api/points/submit`, o
   `field_point_id` das coletas que esperavam por ele é preenchido.
2. Pontos por projeto: `project_id` obrigatório em `field_points`;
   `GET api/points` devolve só os pontos dos projetos a que o aplicador tem
   acesso, e a tela do painel filtra por projeto.
3. Só `sxdata-painel-dev` e `sxdata-app-dev`, nunca os de produção; ordem de
   entrega abaixo, um commit por etapa.

Repositórios: painel `sxdata-painel-dev` (CodeIgniter 3 + PostgreSQL) e app
`sxdata-app-dev` (Flutter).

## 1. Modelo de dados e migração

Migração `sql/field_points_migration.sql` (idempotente). Resumo:

- `field_points`: `client_id` (UUID do aparelho, único), **`project_id NOT
  NULL`** (projeto excluído leva os pontos; as coletas ficam, sem vínculo),
  `codigo_ibge`, `nome`, `tipo` (comunidade | localidade | equipamento |
  outro), `latitude`, `longitude`, `origem_coord` (gps | mapa),
  `dentro_municipio` (NULL = não validado), `criado_por`, datas.
- `form_responses.field_point_id` (FK, `ON DELETE SET NULL`) e
  **`form_responses.field_point_client_id VARCHAR(64)`**, com índice parcial
  das coletas ainda sem vínculo.
- O ponto se liga ao projeto, ao município pelo código IBGE (escolhido no
  app; sugerido pela coordenada quando houver internet) e às coletas por
  `form_responses.field_point_id`.
- As coordenadas da coleta continuam intocadas: o vínculo com o ponto é uma
  coluna nova, e a edição de coleta não altera `latitude/longitude`.
- Ordem de execução: `municipal_reports_migration.sql` →
  `ibge_municipios_seed.sql` → `form_responses_sync_migration.sql` →
  `field_points_migration.sql`.

## 2. Endpoints

**Painel** (controller novo `Mapa_integrado.php`, rota `mapa-integrado`,
restrito à equipe pelo padrão `equipe()` de `Relatorios_municipais.php`):

| Método e rota | O que faz |
|---|---|
| `GET mapa-integrado` | Tela do mapa |
| `GET relatorios-automatizados/municipios?q=&uf=` | Busca de município (já existe; reaproveitada) |
| `GET mapa-integrado/malha/{codigo}` | GeoJSON do contorno, com cache (seção 3) |
| `GET mapa-integrado/indicadores/{codigo}?eixo=` | Indicadores do tema: valor, ano, fonte, unidade |
| `GET mapa-integrado/pontos/{codigo}?projeto=` | Pontos do município no projeto filtrado (GeoJSON), com nº de coletas |
| `GET mapa-integrado/ponto/{id}` | Popup: coletas do ponto com respostas, fotos, data, coletor e link para o registro |

**API do app** (autenticação por `Api_token.php`):

| Método e rota | O que faz |
|---|---|
| `POST api/points/submit` | Cria/atualiza ponto pelo `client_id` (reenvio não duplica), só em projeto a que o aplicador tem acesso; preenche o `field_point_id` das coletas que esperavam o ponto; devolve `id` e aviso se a coordenada cair fora do município |
| `GET api/points?codigo_ibge=&project_id=` | Pontos já sincronizados, só dos projetos a que o aplicador tem acesso (para o app mostrar e vincular) |
| `POST api/forms/submit` (alterado) | Aceita `point_client_id` opcional e grava `field_point_client_id`; se o ponto já está no servidor, grava também `field_point_id`. Se ainda não chegou, o vínculo é feito quando o ponto sincronizar (o app envia pontos antes das coletas na fila) |

**Acesso do aplicador a um projeto:** o projeto tem pelo menos um questionário
ativo liberado para ele (mesma regra de `GET api/questionnaires`:
`Questionnaire_model::is_aplicador_allowed`, projeto não concluído/cancelado).
Supervisor e administrador acessam todos.

## 3. Malhas municipais

- Fonte: API de malhas do IBGE
  (`servicodados.ibge.gov.br/api/v3/malhas/municipios/{codigo}?formato=application/vnd.geo+json&qualidade=intermediaria`,
  cerca de 5 KB por município; a `minima` tem vértices demais espaçados para
  validar pontos perto da divisa,
  com CORS liberado).
- Cache no servidor: `application/cache/malhas/{codigo}.geojson`, gravado na
  primeira consulta e sem expiração (malhas mudam raramente; apagar o arquivo
  força nova busca). O servidor precisa da malha para validar "ponto dentro
  do município".
- Validação: classe PHP pura `Geo_municipio` (ponto em polígono por
  ray casting, com MultiPolygon e buracos), sem banco, testável com PHPUnit (`tests/sxdata`).
  Fora do polígono: grava assim mesmo, com `dentro_municipio = false`, e a
  tela mostra o aviso.
- App: guarda o GeoJSON do município no armazenamento local quando houver
  internet; offline, o contorno vem do cache. Tiles sem internet não carregam
  (aceitável, como dito no pendências).

## 4. De onde o mapa lê os indicadores

**Do relatório mais recente do município**, sem cache novo nesta fase:

1. relatório `published`/`approved` mais recente daquele código IBGE (dados
   revisados pela equipe);
2. se não houver, o mais recente em qualquer status, com o selo "não
   revisado";
3. se não houver relatório, a tela roda no navegador os conectores JS da
   Tarefa 1 só do tema escolhido (`SXConectores`, sem gravar nada) e mostra
   os valores como "consulta direta à fonte".

Temas do filtro = eixos de `relatorio_catalogo.php` (Saúde junta `saude`,
`saude_aps` e `saude_mac`; Demografia = `territorio_populacao` +
`caracterizacao`). Cada indicador aparece com valor formatado, ano
(`periodo`) e fonte, usando `Relatorio_graficos::formatar`. A regra
"filtrar por tema" fica numa função pura testável.

Se o uso mostrar que muitos municípios são consultados sem relatório, a
fase 2 cria `municipio_indicadores_cache` por código IBGE.

## 5. Painel (tela)

Leaflet 1.9.4 (já carregado no `footer.php`). Filtro de projeto (obrigatório para ver pontos) e busca de um ou mais municípios →
contorno destacado e `fitBounds`; painel lateral com o filtro de tema e a
lista de indicadores; camada de pontos (marcador por tipo); clique no ponto
abre o painel lateral com as coletas (respostas, fotos, data, coletor, link
para o registro). Se o popup mostrar resumo de respostas, usa
`Client_analytics_model`. KMZ: `Kmz_generator` ganha uma pasta "Pontos"
com os pontos do filtro, sem mudar o que já sai hoje.

## 6. App

- Registrar ponto: tela nova com o mapa (`flutter_map` 6.1 + `latlong2`),
  botão "usar minha localização" (`location_service.dart`) ou toque no mapa;
  nome, tipo e município (busca na lista de municípios do projeto/cache).
- Na coleta, opção "vincular a um ponto" (pontos locais + sincronizados do
  município).
- Offline: fila no mesmo mecanismo de `form_provider.dart`, com `client_id`
  (UUID). O ponto sai da fila quando o servidor devolve o `id`; os pontos
  vão antes das coletas na fila.
- Sem mudar `br.com.sxdata.app`, sem reintroduzir estratificação de cotas,
  e mantendo os 81 testes passando. Versão web: o GeoJSON fica no
  armazenamento local, compatível com o patch do service worker.

## 7. Testes

- PHP (PHPUnit, já no `require-dev`): `Geo_municipio` (dentro, fora, borda,
  MultiPolygon, buraco) e o filtro por tema.
- Flutter: modelo do ponto (serialização, `client_id`), fila de sincronização
  (ponto antes da coleta, reenvio sem duplicar) e vínculo coleta–ponto.

## 8. Fase 2

- Resumo de indicadores e mapa dos pontos no app com cache offline completo.
- Cache de indicadores por código IBGE independente de relatório.
- Mapa coroplético comparando vários municípios por indicador.
- Edição/mesclagem de pontos duplicados no painel.
- Conector TABNET (Tarefa 1) para os indicadores de saúde sem fonte.

## Ordem de entrega (após aprovação)

1. Migração + `Geo_municipio` + testes PHP.
2. API do app (`api/points`, alteração do `api/forms/submit`).
3. Tela do painel + KMZ.
4. App (registro de ponto, vínculo, fila offline) + testes Flutter.

## Andamento

| Etapa | Situação |
|---|---|
| 1. Migração + `Geo_municipio` + testes PHP | Feita. Testes: `php vendor/bin/phpunit -c tests/sxdata/phpunit.xml` |
| 2. API do app | — |
| 3. Tela do painel + KMZ | — |
| 4. App | — |
