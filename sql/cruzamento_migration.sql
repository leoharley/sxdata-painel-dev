-- Frente J: análises de cruzamento salvas (e liberadas ao cliente). Idempotente.

BEGIN;

CREATE TABLE IF NOT EXISTS cruzamentos_salvos (
    id               SERIAL PRIMARY KEY,
    questionnaire_id INTEGER      NOT NULL REFERENCES questionnaires(id) ON DELETE CASCADE,
    titulo           VARCHAR(200) NOT NULL,
    config           JSONB        NOT NULL,   -- unidade, linha, coluna, medida, medida_tipo, filtros, faixas
    interpretacao    TEXT,                    -- texto da IA (revisado por quem salvou)
    ia_log_id        INTEGER,
    liberado_cliente BOOLEAN      NOT NULL DEFAULT FALSE,
    criado_por       INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    criado_em        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_cruzamentos_salvos_q ON cruzamentos_salvos (questionnaire_id);

COMMIT;
