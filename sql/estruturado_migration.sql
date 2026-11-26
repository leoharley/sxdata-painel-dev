-- Formulários estruturados (domicílio + moradores, campos calculados).
-- docs/PLANO_EVOLUCAO_GERDAU_SRA.md, seção 2. Idempotente: pode rodar de novo.
--
-- Não altera nada do que existe: os questionários atuais ficam com
-- formato = 'simples' e continuam no editor, nas respostas e no app como hoje.
-- Um questionário 'estruturado' reaproveita o cabeçalho (projeto, aplicadores,
-- status) e cada entrevista reaproveita form_responses (client_id, localização,
-- fotos, áudio, KMZ); as respostas ficam em respostas_estruturadas.
--
-- Rodar: sudo -u postgres psql -d <banco> -v ON_ERROR_STOP=1 -f estruturado_migration.sql
--        (com SET ROLE para o dono do banco, se as tabelas forem dele)

BEGIN;

-- Questionário: formato e fonte (texto editado no painel)
ALTER TABLE questionnaires ADD COLUMN IF NOT EXISTS formato VARCHAR(20) NOT NULL DEFAULT 'simples';
ALTER TABLE questionnaires ADD COLUMN IF NOT EXISTS estrutura_fonte TEXT;
ALTER TABLE questionnaires ADD COLUMN IF NOT EXISTS estrutura_versao INTEGER;
CREATE INDEX IF NOT EXISTS idx_questionnaires_formato ON questionnaires (formato);

-- Versões publicadas: o app baixa a definição; cada entrevista guarda a versão usada
CREATE TABLE IF NOT EXISTS estrutura_versoes (
    id               SERIAL PRIMARY KEY,
    questionnaire_id INTEGER NOT NULL REFERENCES questionnaires(id) ON DELETE CASCADE,
    versao           INTEGER NOT NULL,
    definicao        JSONB NOT NULL,
    fonte            TEXT NOT NULL,
    publicado_por    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    publicado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (questionnaire_id, versao)
);

-- Entrevista (form_responses): versão, calculados, alertas e fila de revisão
ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS estrutura_versao_id INTEGER REFERENCES estrutura_versoes(id) ON DELETE SET NULL;
ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS calculados JSONB;
ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS alertas JSONB;
ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS revisao_status VARCHAR(20);
ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS revisao_erros JSONB;
CREATE INDEX IF NOT EXISTS idx_form_responses_revisao ON form_responses (revisao_status) WHERE revisao_status IS NOT NULL;

-- Moradores do domicílio (1:N), cada um com o UUID gerado no aparelho
CREATE TABLE IF NOT EXISTS moradores (
    id               SERIAL PRIMARY KEY,
    form_response_id INTEGER NOT NULL REFERENCES form_responses(id) ON DELETE CASCADE,
    client_id        VARCHAR(64) NOT NULL,
    ordem            INTEGER NOT NULL DEFAULT 1,
    nome             VARCHAR(200) NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'concluido',
    justificativa    TEXT,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (form_response_id, client_id)
);
CREATE INDEX IF NOT EXISTS idx_moradores_form ON moradores (form_response_id);

-- Respostas: uma linha por questão respondida (do domicílio ou de um morador).
-- valor guarda a resposta como o app envia (texto, número, lista, objeto);
-- valor_numero duplica o número para somas e cruzamentos.
CREATE TABLE IF NOT EXISTS respostas_estruturadas (
    id               BIGSERIAL PRIMARY KEY,
    form_response_id INTEGER NOT NULL REFERENCES form_responses(id) ON DELETE CASCADE,
    morador_id       INTEGER REFERENCES moradores(id) ON DELETE CASCADE,
    codigo           VARCHAR(60) NOT NULL,
    valor            JSONB,
    valor_numero     NUMERIC(16,2),
    nsnr             BOOLEAN NOT NULL DEFAULT FALSE,
    complementos     JSONB,
    calculado        BOOLEAN NOT NULL DEFAULT FALSE,
    incompleto       BOOLEAN NOT NULL DEFAULT FALSE,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_respostas_estruturadas
    ON respostas_estruturadas (form_response_id, COALESCE(morador_id, 0), codigo);
CREATE INDEX IF NOT EXISTS idx_respostas_estruturadas_codigo ON respostas_estruturadas (codigo);

COMMIT;
