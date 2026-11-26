-- Frente L: biblioteca de conjuntos de perguntas, temas, entrevistados
-- pré-cadastrados e grupos repetidos (@repetir). Rodar depois de
-- estruturado_migration.sql. Idempotente.

BEGIN;

-- Conjuntos de perguntas reaproveitáveis (formato-texto com uma etapa)
CREATE TABLE IF NOT EXISTS conjuntos (
    id            SERIAL PRIMARY KEY,
    codigo        VARCHAR(60) NOT NULL UNIQUE,
    nome          VARCHAR(200) NOT NULL,
    descricao     TEXT,
    fonte         TEXT NOT NULL DEFAULT '',
    versao_atual  INTEGER,
    ativo         BOOLEAN NOT NULL DEFAULT TRUE,
    created_by    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS conjunto_versoes (
    id            SERIAL PRIMARY KEY,
    conjunto_id   INTEGER NOT NULL REFERENCES conjuntos(id) ON DELETE CASCADE,
    versao        INTEGER NOT NULL,
    fonte         TEXT NOT NULL,
    publicado_por INTEGER REFERENCES users(id) ON DELETE SET NULL,
    publicado_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (conjunto_id, versao)
);

-- Temas: catálogo com os conjuntos de cada tema (o formulário declara "# temas:")
CREATE TABLE IF NOT EXISTS temas (
    id         SERIAL PRIMARY KEY,
    nome       VARCHAR(150) NOT NULL UNIQUE,
    descricao  TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS tema_conjuntos (
    tema_id     INTEGER NOT NULL REFERENCES temas(id) ON DELETE CASCADE,
    conjunto_id INTEGER NOT NULL REFERENCES conjuntos(id) ON DELETE CASCADE,
    PRIMARY KEY (tema_id, conjunto_id)
);

-- Entrevistados pré-cadastrados por projeto (individual ou por planilha)
CREATE TABLE IF NOT EXISTS entrevistados (
    id              SERIAL PRIMARY KEY,
    project_id      INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    codigo          VARCHAR(60) NOT NULL,
    nome            VARCHAR(200) NOT NULL,
    categoria       VARCHAR(120),
    localidade      VARCHAR(200),
    latitude        NUMERIC(10,7),
    longitude       NUMERIC(10,7),
    campos          JSONB,
    ativo           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (project_id, codigo)
);
CREATE INDEX IF NOT EXISTS idx_entrevistados_atualizado ON entrevistados (project_id, updated_at);

ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS entrevistado_id INTEGER REFERENCES entrevistados(id) ON DELETE SET NULL;

-- Repetições de grupos (@repetir): cada item com o UUID do aparelho
CREATE TABLE IF NOT EXISTS repeticoes (
    id               SERIAL PRIMARY KEY,
    form_response_id INTEGER NOT NULL REFERENCES form_responses(id) ON DELETE CASCADE,
    grupo            VARCHAR(60) NOT NULL,
    client_id        VARCHAR(64) NOT NULL,
    indice           INTEGER NOT NULL DEFAULT 1,
    rotulo           VARCHAR(200),
    UNIQUE (form_response_id, grupo, client_id)
);
CREATE INDEX IF NOT EXISTS idx_repeticoes_form ON repeticoes (form_response_id);

ALTER TABLE respostas_estruturadas ADD COLUMN IF NOT EXISTS repeticao_id INTEGER REFERENCES repeticoes(id) ON DELETE CASCADE;
DROP INDEX IF EXISTS uq_respostas_estruturadas;
CREATE UNIQUE INDEX IF NOT EXISTS uq_respostas_estruturadas2
    ON respostas_estruturadas (form_response_id, COALESCE(morador_id, 0), COALESCE(repeticao_id, 0), codigo);

COMMIT;
