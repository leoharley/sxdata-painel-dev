-- Parte I: módulo PEA Gerdau (frentes A a H). Idempotente.
-- A carga do PEA 2026–2031 (unidades, comunidades, projetos, cronograma...)
-- é feita pela tela PEA > Estrutura > "Carregar o PEA 2026–2031", a partir de
-- application/config/pea_2026_2031.json.

BEGIN;

CREATE TABLE IF NOT EXISTS pea_ciclos (
    id          SERIAL PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    inicio      DATE         NOT NULL,           -- início do ano 1 (os anos vão de agosto a agosto)
    anos        INTEGER      NOT NULL DEFAULT 5,
    mes_inicio  INTEGER      NOT NULL DEFAULT 8, -- mês em que começa cada ano do ciclo
    ativo       BOOLEAN      NOT NULL DEFAULT TRUE,
    criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pea_unidades (
    id    SERIAL PRIMARY KEY,
    sigla VARCHAR(10)  NOT NULL UNIQUE,
    nome  VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS pea_comunidades (
    id         SERIAL PRIMARY KEY,
    unidade_id INTEGER      REFERENCES pea_unidades(id) ON DELETE SET NULL,
    nome       VARCHAR(200) NOT NULL,
    municipio  VARCHAR(120),
    tipo       VARCHAR(60),   -- ABEA, AID do transporte, ...
    ativo      BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS pea_escolas (
    id            SERIAL PRIMARY KEY,
    unidade_id    INTEGER      REFERENCES pea_unidades(id) ON DELETE SET NULL,
    comunidade_id INTEGER      REFERENCES pea_comunidades(id) ON DELETE SET NULL,
    nome          VARCHAR(250) NOT NULL,
    municipio     VARCHAR(120),
    ativo         BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS pea_eixos (
    id   SERIAL PRIMARY KEY,
    nome VARCHAR(60) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS pea_projetos (
    id            SERIAL PRIMARY KEY,
    ciclo_id      INTEGER      NOT NULL REFERENCES pea_ciclos(id) ON DELETE CASCADE,
    publico       VARCHAR(30)  NOT NULL,   -- interno, externo_comunidade, externo_escolar
    eixo_id       INTEGER      REFERENCES pea_eixos(id) ON DELETE SET NULL,
    nome          VARCHAR(200) NOT NULL,
    descricao     TEXT,
    periodicidade VARCHAR(100),
    ip_faixas     JSONB,                   -- {"baixa":[min,max],"media":[min,max],"alta":[min,null]}; nulo = sem IP
    avaliacao     BOOLEAN      NOT NULL DEFAULT TRUE,  -- tem formulário de avaliação por participante
    ordem         INTEGER      NOT NULL DEFAULT 0,
    ativo         BOOLEAN      NOT NULL DEFAULT TRUE
);
CREATE INDEX IF NOT EXISTS idx_pea_projetos_ciclo ON pea_projetos (ciclo_id, publico);

CREATE TABLE IF NOT EXISTS pea_linhas (
    id        SERIAL PRIMARY KEY,
    ciclo_id  INTEGER      NOT NULL REFERENCES pea_ciclos(id) ON DELETE CASCADE,
    publico   VARCHAR(30)  NOT NULL,
    linha     VARCHAR(250) NOT NULL,
    conteudos JSONB
);

-- Cronograma previsto: quantas ações de cada projeto em cada trimestre de cada ano do ciclo
CREATE TABLE IF NOT EXISTS pea_cronograma (
    id         SERIAL PRIMARY KEY,
    projeto_id INTEGER NOT NULL REFERENCES pea_projetos(id) ON DELETE CASCADE,
    ano        INTEGER NOT NULL,   -- 1 a 5
    trimestre  INTEGER NOT NULL,   -- 1 a 4 (do ano do ciclo)
    previstas  INTEGER NOT NULL DEFAULT 0,
    UNIQUE (projeto_id, ano, trimestre)
);

-- Regras de ponderação versionadas (o relatório guarda a versão usada)
CREATE TABLE IF NOT EXISTS pea_regras (
    id         SERIAL PRIMARY KEY,
    versao     INTEGER   NOT NULL UNIQUE,
    regras     JSONB     NOT NULL,
    nota       TEXT,
    criado_por INTEGER   REFERENCES users(id) ON DELETE SET NULL,
    criado_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Ficha da ação
CREATE TABLE IF NOT EXISTS pea_acoes (
    id                 SERIAL PRIMARY KEY,
    projeto_id         INTEGER      NOT NULL REFERENCES pea_projetos(id) ON DELETE RESTRICT,
    unidade_id         INTEGER      REFERENCES pea_unidades(id) ON DELETE SET NULL,
    comunidade_id      INTEGER      REFERENCES pea_comunidades(id) ON DELETE SET NULL,
    escola_id          INTEGER      REFERENCES pea_escolas(id) ON DELETE SET NULL,
    linha_id           INTEGER      REFERENCES pea_linhas(id) ON DELETE SET NULL,
    titulo             VARCHAR(250) NOT NULL,
    data_prevista      DATE,
    ano_previsto       INTEGER,
    trimestre_previsto INTEGER,
    data_realizacao    DATE,
    carga_horaria      NUMERIC(6,2),
    participantes      INTEGER,
    local              VARCHAR(250),
    status             VARCHAR(20)  NOT NULL DEFAULT 'planejada',  -- planejada, realizada, encerrada, cancelada
    justificativa      TEXT,                    -- obrigatória com ICC < 50
    observacoes        TEXT,
    public_link_id     INTEGER      REFERENCES questionnaire_public_links(id) ON DELETE SET NULL,
    indicadores        JSONB,                   -- foto dos indicadores no encerramento
    regra_versao       INTEGER,
    criado_por         INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    criado_em          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    encerrada_em       TIMESTAMP,
    encerrada_por      INTEGER      REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_pea_acoes_projeto ON pea_acoes (projeto_id, ano_previsto, trimestre_previsto);
CREATE INDEX IF NOT EXISTS idx_pea_acoes_link ON pea_acoes (public_link_id);

CREATE TABLE IF NOT EXISTS pea_evidencias (
    id            SERIAL PRIMARY KEY,
    acao_id       INTEGER      NOT NULL REFERENCES pea_acoes(id) ON DELETE CASCADE,
    tipo          VARCHAR(30)  NOT NULL,   -- lista_presenca, foto, relatorio, outro
    arquivo       VARCHAR(200) NOT NULL,   -- nome em uploads/pea/
    nome_original VARCHAR(250),
    tamanho       INTEGER,
    enviado_por   INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    criado_em     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Lista de presença (identificada), separada das respostas (anônimas)
CREATE TABLE IF NOT EXISTS pea_presencas (
    id              SERIAL PRIMARY KEY,
    acao_id         INTEGER      NOT NULL REFERENCES pea_acoes(id) ON DELETE CASCADE,
    colaborador_id  INTEGER,                 -- pea_colaboradores (base do BancoDOC), quando houver
    nome            VARCHAR(200) NOT NULL,
    cpf             VARCHAR(14),
    matricula       VARCHAR(40),
    empresa         VARCHAR(200),
    origem          VARCHAR(20)  NOT NULL DEFAULT 'manual',  -- manual, bancodoc, planilha
    criado_em       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_pea_presencas_acao ON pea_presencas (acao_id);

-- Árvore EIEAP: nós (pergunta -> indicador -> índice -> Indicador de Educação Ambiental)
CREATE TABLE IF NOT EXISTS pea_eieap_nos (
    id      SERIAL PRIMARY KEY,
    codigo  VARCHAR(60)  NOT NULL UNIQUE,
    nome    VARCHAR(200) NOT NULL,
    pai     VARCHAR(60),
    peso    NUMERIC(8,4) NOT NULL DEFAULT 1,
    ordem   INTEGER      NOT NULL DEFAULT 0
);
-- Valores por nó, unidade e ano: linha de base (2021), metas e resultados medidos
CREATE TABLE IF NOT EXISTS pea_eieap_valores (
    id      SERIAL PRIMARY KEY,
    no      VARCHAR(60)  NOT NULL,
    unidade VARCHAR(10)  NOT NULL,   -- UOB, MVL, MMB, Gerdau
    tipo    VARCHAR(10)  NOT NULL,   -- base, meta, atual
    ano     INTEGER      NOT NULL,
    valor   NUMERIC(8,2),
    UNIQUE (no, unidade, tipo, ano)
);

-- Relatórios: ficha, semestral, anual e do ciclo (rascunho -> conferência -> aprovado)
CREATE TABLE IF NOT EXISTS pea_relatorios (
    id           SERIAL PRIMARY KEY,
    tipo         VARCHAR(20)  NOT NULL,   -- ficha, semestral, anual, ciclo
    ciclo_id     INTEGER      NOT NULL REFERENCES pea_ciclos(id) ON DELETE CASCADE,
    ano          INTEGER,
    semestre     INTEGER,
    acao_id      INTEGER      REFERENCES pea_acoes(id) ON DELETE SET NULL,
    titulo       VARCHAR(250) NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'rascunho',
    dados        JSONB        NOT NULL,   -- indicadores e tabelas congelados na geração
    textos       JSONB,                   -- textos interpretativos editáveis
    regra_versao INTEGER,
    versao       INTEGER      NOT NULL DEFAULT 1,
    criado_por   INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    criado_em    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    conferido_por INTEGER     REFERENCES users(id) ON DELETE SET NULL,
    aprovado_por INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    aprovado_em  TIMESTAMP
);
CREATE TABLE IF NOT EXISTS pea_relatorio_eventos (
    id           SERIAL PRIMARY KEY,
    relatorio_id INTEGER     NOT NULL REFERENCES pea_relatorios(id) ON DELETE CASCADE,
    user_id      INTEGER     REFERENCES users(id) ON DELETE SET NULL,
    evento       VARCHAR(30) NOT NULL,   -- gerado, editado, enviado, comentario, devolvido, aprovado, exportado
    comentario   TEXT,
    criado_em    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Frente H: BancoDOC por arquivo (base de terceiros e envios de participações)
CREATE TABLE IF NOT EXISTS pea_empresas (
    id         SERIAL PRIMARY KEY,
    cnpj       VARCHAR(18),
    nome       VARCHAR(250) NOT NULL,
    situacao   VARCHAR(60),
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (nome)
);
CREATE TABLE IF NOT EXISTS pea_colaboradores (
    id          SERIAL PRIMARY KEY,
    cpf         VARCHAR(14),
    matricula   VARCHAR(40),
    nome        VARCHAR(200) NOT NULL,
    empresa_id  INTEGER      REFERENCES pea_empresas(id) ON DELETE SET NULL,
    funcao      VARCHAR(200),
    unidade     VARCHAR(10),
    situacao    VARCHAR(60),             -- mobilizado, desmobilizado, ...
    mobilizado_em DATE,
    origem      VARCHAR(20)  NOT NULL DEFAULT 'planilha',
    atualizado_em TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS uq_pea_colaboradores_cpf ON pea_colaboradores (cpf) WHERE cpf IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_pea_colaboradores_matricula ON pea_colaboradores (matricula);
CREATE TABLE IF NOT EXISTS pea_bancodoc_envios (
    id          SERIAL PRIMARY KEY,
    sentido     VARCHAR(10)  NOT NULL,   -- entrada (importação), saida (participações)
    conector    VARCHAR(20)  NOT NULL DEFAULT 'arquivo',  -- arquivo, api
    status      VARCHAR(20)  NOT NULL,   -- ok, erro, parcial, pendente
    registros   INTEGER      NOT NULL DEFAULT 0,
    arquivo     VARCHAR(200),
    detalhes    JSONB,
    user_id     INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE pea_presencas ADD COLUMN IF NOT EXISTS enviado_bancodoc_em TIMESTAMP;

COMMIT;
