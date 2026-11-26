-- Frente K: escolha do modelo de IA (OpenAI e Anthropic). Idempotente.

BEGIN;

-- Provedor e modelo por função, com alternativa quando o principal falhar
ALTER TABLE ai_settings ADD COLUMN IF NOT EXISTS provider          VARCHAR(20) NOT NULL DEFAULT 'openai';
ALTER TABLE ai_settings ADD COLUMN IF NOT EXISTS fallback_provider VARCHAR(20);
ALTER TABLE ai_settings ADD COLUMN IF NOT EXISTS fallback_model    VARCHAR(100);

-- Rastreabilidade: quem gerou, com qual provedor e modelo, e se usou a alternativa
ALTER TABLE ai_execution_logs ADD COLUMN IF NOT EXISTS provider     VARCHAR(20);
ALTER TABLE ai_execution_logs ADD COLUMN IF NOT EXISTS fallback_de  VARCHAR(140);
ALTER TABLE ai_execution_logs ADD COLUMN IF NOT EXISTS project_id   INTEGER;
ALTER TABLE ai_execution_logs ADD COLUMN IF NOT EXISTS anonimizado  BOOLEAN NOT NULL DEFAULT FALSE;
CREATE INDEX IF NOT EXISTS idx_ai_exec_mes ON ai_execution_logs (created_at, status);

-- Preço de cada modelo (US$ por milhão de tokens), editável na tela
CREATE TABLE IF NOT EXISTS ia_modelos (
    id            SERIAL PRIMARY KEY,
    provedor      VARCHAR(20)   NOT NULL,
    modelo        VARCHAR(100)  NOT NULL UNIQUE,
    nome          VARCHAR(100)  NOT NULL,
    preco_entrada NUMERIC(10,4) NOT NULL DEFAULT 0,
    preco_saida   NUMERIC(10,4) NOT NULL DEFAULT 0,
    ativo         BOOLEAN       NOT NULL DEFAULT TRUE,
    observacao    VARCHAR(300),
    atualizado_em TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO ia_modelos (provedor, modelo, nome, preco_entrada, preco_saida, observacao) VALUES
    ('openai',    'gpt-4o',            'GPT-4o',             2.50, 10.00, NULL),
    ('openai',    'gpt-4o-mini',       'GPT-4o mini',        0.15,  0.60, NULL),
    ('openai',    'gpt-4-turbo',       'GPT-4 Turbo',       10.00, 30.00, NULL),
    ('openai',    'gpt-3.5-turbo',     'GPT-3.5 Turbo',      0.50,  1.50, NULL),
    ('anthropic', 'claude-opus-5-5',   'Claude Opus 5.5',    4.00, 20.00, 'Mais capaz; relatórios e análises'),
    ('anthropic', 'claude-sonnet-5-5', 'Claude Sonnet 5.5',  2.00, 10.00, 'Equilíbrio entre qualidade e custo'),
    ('anthropic', 'claude-haiku-5-5',  'Claude Haiku 5.5',   0.10,  0.50, 'Rápido e barato; tarefas simples (até 100 mil tokens de entrada)')
ON CONFLICT (modelo) DO NOTHING;

-- Chaves de API cifradas (Cripto_chaves). Nunca voltam para a tela.
CREATE TABLE IF NOT EXISTS ia_chaves (
    provedor       VARCHAR(20) PRIMARY KEY,
    chave_cifrada  TEXT        NOT NULL,
    final          VARCHAR(10),
    atualizado_por INTEGER     REFERENCES users(id) ON DELETE SET NULL,
    atualizado_em  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- IA desligada por projeto
ALTER TABLE projects ADD COLUMN IF NOT EXISTS ia_desligada BOOLEAN NOT NULL DEFAULT FALSE;

COMMIT;
