# API dos pontos de campo (mapa integrado)

Autenticação: `Authorization: Bearer <token>` de `api/auth/login`, como as
demais rotas do app. Requer `sql/field_points_migration.sql`; sem ela, as
rotas respondem 503.

**Acesso a um projeto:** supervisor e administrador acessam todos. O
aplicador acessa os projetos com pelo menos um questionário ativo liberado
para ele (mesma regra de `GET api/questionnaires`), em projeto não concluído
nem cancelado.

## POST api/points/submit

```json
{
  "client_id": "uuid gerado no aparelho",
  "project_id": 4,
  "codigo_ibge": "1713601",
  "nome": "Comunidade Serra Azul",
  "tipo": "comunidade",
  "latitude": -10.7611,
  "longitude": -48.1114,
  "origem_coord": "gps"
}
```

- Obrigatórios: `client_id` (até 64 caracteres), `project_id`, `codigo_ibge`
  (7 dígitos, presente em `ibge_municipios`) e `nome`.
- `tipo`: comunidade | localidade | equipamento | outro (desconhecido vira
  `outro`; vazio vira `comunidade`). `origem_coord`: gps | mapa.
- Coordenada ausente ou inválida (inclusive 0,0) é gravada como NULL, sem
  erro. Nome com mais de 150 caracteres é cortado. Assim um ponto nunca fica
  preso na fila por um detalhe.
- Mesmo `client_id` de novo: atualiza o ponto (não duplica).
- O servidor confere a coordenada com a malha do IBGE
  (`application/cache/malhas/`). Fora do município: grava assim mesmo, com
  `dentro_municipio: false` e um `aviso`.
- Coletas que chegaram antes com `point_client_id` igual a este `client_id`
  são vinculadas agora (`coletas_vinculadas`).

Resposta 200 (o `id` é a prova de gravação; só então o ponto sai da fila):

```json
{"success": true, "id": 1, "point_id": 1, "client_id": "...", "created": true,
 "dentro_municipio": true, "coletas_vinculadas": 1, "aviso": null, "message": "Ponto gravado"}
```

Erros: 400 (validação, `errors`), 401 (token), 403 (sem acesso ao projeto
do envio ou ao projeto atual do ponto), 500 (falha de gravação; reenviar).

## GET api/points?codigo_ibge=&project_id=

Pontos dos projetos a que o usuário tem acesso. `project_id` restringe a um
projeto (403 se não tiver acesso); `codigo_ibge` restringe ao município.
Cada item: `id`, `client_id`, `project_id`, `projeto_nome`, `codigo_ibge`,
`municipio_nome`, `uf`, `nome`, `tipo`, `latitude`, `longitude`,
`origem_coord`, `dentro_municipio`, `criado_por`, `criado_por_nome`,
`criado_em`, `atualizado_em`, `coletas` (nº de coletas vinculadas).

## POST api/forms/submit (campo novo)

`point_client_id` (opcional): `client_id` do ponto ao qual a coleta se
vincula. Grava `form_responses.field_point_client_id` e, se o ponto já
estiver no servidor, `field_point_id`. Numa edição, omitir o campo mantém o
vínculo; enviar `""` desvincula.
