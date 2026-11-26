-- Frente I: robustez e confiabilidade (docs/PLANO_EVOLUCAO_GERDAU_SRA.md, seção 3).
-- Idempotente: pode rodar mais de uma vez. Não altera dados existentes.
-- Depende de sql/estruturado_migration.sql (colunas revisao_* de form_responses).

BEGIN;

-- Trilha de auditoria: quem fez o quê, quando e de onde.
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGSERIAL PRIMARY KEY,
    criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id     INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    username    VARCHAR(100),
    papel       VARCHAR(30),
    acao        VARCHAR(40)  NOT NULL,   -- login, login_falha, login_bloqueado, logout, 2fa_*, visualizar, editar, excluir, restaurar, exportar, baixar, senha, config
    entidade    VARCHAR(40),             -- usuario, entrevista, questionario, projeto, configuracao, ...
    entidade_id VARCHAR(60),
    descricao   TEXT,
    antes       JSONB,
    depois      JSONB,
    ip          VARCHAR(64),
    user_agent  VARCHAR(300)
);
CREATE INDEX IF NOT EXISTS idx_audit_log_data     ON audit_log (criado_em DESC);
CREATE INDEX IF NOT EXISTS idx_audit_log_user     ON audit_log (user_id, criado_em DESC);
CREATE INDEX IF NOT EXISTS idx_audit_log_acao     ON audit_log (acao, criado_em DESC);
CREATE INDEX IF NOT EXISTS idx_audit_log_entidade ON audit_log (entidade, entidade_id);

-- Acesso: 2FA (TOTP), bloqueio por tentativas, exclusão lógica de usuário.
ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret        VARCHAR(64);
ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_ativo         BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_ultimo_passo  BIGINT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS falhas_login       INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS bloqueado_ate      TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS senha_alterada_em  TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at         TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_by         INTEGER;
CREATE INDEX IF NOT EXISTS idx_users_deleted ON users (deleted_at) WHERE deleted_at IS NOT NULL;

-- Lixeira: a exclusão de entrevista, morador, questionário e projeto guarda
-- uma cópia completa (linhas de todas as tabelas envolvidas) antes de apagar.
-- Restaurar devolve as linhas com os mesmos ids. Assim as consultas atuais
-- continuam iguais (não precisam filtrar deleted_at).
CREATE TABLE IF NOT EXISTS lixeira (
    id             BIGSERIAL PRIMARY KEY,
    tipo           VARCHAR(30)  NOT NULL,   -- entrevista, morador, questionario, projeto
    ref_id         INTEGER      NOT NULL,
    descricao      TEXT,
    dados          JSONB        NOT NULL,
    motivo         TEXT,
    excluido_por   INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    excluido_em    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    restaurado_por INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    restaurado_em  TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_lixeira_tipo ON lixeira (tipo, ref_id);
CREATE INDEX IF NOT EXISTS idx_lixeira_data ON lixeira (excluido_em DESC);

-- Erros do painel, da API e do app, agrupados pela mesma assinatura.
CREATE TABLE IF NOT EXISTS erros_sistema (
    id            BIGSERIAL PRIMARY KEY,
    origem        VARCHAR(20)  NOT NULL,   -- painel, api, app, cli
    nivel         VARCHAR(20)  NOT NULL DEFAULT 'erro',
    mensagem      TEXT         NOT NULL,
    arquivo       VARCHAR(300),
    linha         INTEGER,
    url           VARCHAR(500),
    user_id       INTEGER,
    contexto      JSONB,
    assinatura    CHAR(40)     NOT NULL,
    ocorrencias   INTEGER      NOT NULL DEFAULT 1,
    primeiro_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_em     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolvido_em  TIMESTAMP,
    resolvido_por INTEGER
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_erros_sistema_aberto ON erros_sistema (assinatura) WHERE resolvido_em IS NULL;
CREATE INDEX IF NOT EXISTS idx_erros_sistema_ultimo ON erros_sistema (ultimo_em DESC);

-- Aparelhos: o app informa o que ainda está na fila dele (heartbeat).
CREATE TABLE IF NOT EXISTS dispositivos (
    id                BIGSERIAL PRIMARY KEY,
    device_id         VARCHAR(100) NOT NULL,
    user_id           INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    app_versao        VARCHAR(40),
    plataforma        VARCHAR(40),
    pendentes         INTEGER      NOT NULL DEFAULT 0,
    pendentes_midias  INTEGER      NOT NULL DEFAULT 0,
    rascunhos         INTEGER      NOT NULL DEFAULT 0,
    ultimo_envio_em   TIMESTAMP,
    visto_em          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip                VARCHAR(64),
    UNIQUE (device_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_dispositivos_visto ON dispositivos (visto_em DESC);

-- Fila de revisão: quem revisou e quando (o status já existe em form_responses).
ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS revisado_por INTEGER;
ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS revisado_em  TIMESTAMP;
ALTER TABLE form_responses ADD COLUMN IF NOT EXISTS revisao_nota TEXT;

-- Volume: índices que as listagens e relatórios usam com muitas respostas.
CREATE INDEX IF NOT EXISTS idx_form_responses_q_completed ON form_responses (questionnaire_id, completed_at DESC);
CREATE INDEX IF NOT EXISTS idx_form_responses_created     ON form_responses (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_question_responses_fq      ON question_responses (form_response_id, question_id);

COMMIT;
