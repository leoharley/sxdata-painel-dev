-- ============================================================
-- Mapa integrado: pontos de campo (comunidades, localidades, equipamentos)
-- ============================================================
-- PostgreSQL. Pode ser executado mais de uma vez sem efeito colateral.
--
-- Ordem: municipal_reports_migration.sql -> ibge_municipios_seed.sql ->
--        form_responses_sync_migration.sql -> este arquivo.
--
-- Ver docs/PLANO_MAPA_INTEGRADO.md.

-- Um ponto pertence a um projeto (obrigatório) e a um município.
-- client_id: UUID gerado no aparelho, como em form_responses. Um reenvio do
-- mesmo ponto atualiza o registro em vez de duplicar.
-- Projeto excluído leva os pontos junto; as coletas ficam, sem o vínculo
-- (field_point_id volta a NULL).
CREATE TABLE IF NOT EXISTS field_points (
    id               SERIAL       PRIMARY KEY,
    client_id        VARCHAR(64)  NOT NULL UNIQUE,
    project_id       INTEGER      NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    codigo_ibge      INTEGER      NOT NULL REFERENCES ibge_municipios(codigo),
    nome             VARCHAR(150) NOT NULL,
    tipo             VARCHAR(40)  NOT NULL DEFAULT 'comunidade', -- comunidade | localidade | equipamento | outro
    latitude         NUMERIC(10,7),
    longitude        NUMERIC(10,7),
    origem_coord     VARCHAR(10),                                -- gps | mapa
    dentro_municipio BOOLEAN,                                    -- NULL = não validado (sem malha ou sem coordenada)
    criado_por       INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    criado_em        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_field_points_municipio ON field_points (codigo_ibge);
CREATE INDEX IF NOT EXISTS idx_field_points_projeto   ON field_points (project_id, codigo_ibge);

-- Vínculo coleta -> ponto.
-- field_point_client_id: client_id do ponto, enviado pelo app junto com a
-- coleta. Se o ponto ainda não chegou ao servidor, a coleta é gravada só com
-- ele; quando o ponto chega em api/points/submit, o field_point_id das
-- coletas que esperavam é preenchido.
ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS field_point_id INTEGER
    REFERENCES field_points(id) ON DELETE SET NULL;
ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS field_point_client_id VARCHAR(64);
CREATE INDEX IF NOT EXISTS idx_form_responses_field_point ON form_responses (field_point_id);

-- Só as coletas ainda sem vínculo: é a busca feita quando o ponto chega
CREATE INDEX IF NOT EXISTS idx_form_responses_field_point_pendente
    ON form_responses (field_point_client_id) WHERE field_point_id IS NULL;
