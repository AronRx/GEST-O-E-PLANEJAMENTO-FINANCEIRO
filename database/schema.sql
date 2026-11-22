CREATE EXTENSION IF NOT EXISTS "pgcrypto";

CREATE TABLE usuarios (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    nome            VARCHAR(150) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    senha_hash      VARCHAR(255) NOT NULL,
    telefone        VARCHAR(20),
    avatar_url      TEXT,
    ativo           BOOLEAN DEFAULT TRUE,
    criado_em       TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE clientes (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    nome            VARCHAR(150) NOT NULL,
    documento       VARCHAR(20), 
    telefone        VARCHAR(20),
    email           VARCHAR(255),
    endereco        TEXT,
    observacoes     TEXT,
    ativo           BOOLEAN DEFAULT TRUE,
    criado_em       TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE emprestimos (
    id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id          BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    cliente_id          BIGINT NOT NULL REFERENCES clientes(id) ON DELETE RESTRICT,
    valor_principal     DECIMAL(15,2) NOT NULL,
    taxa_juros          DECIMAL(8,4) NOT NULL, 
    tipo_juros          VARCHAR(20) DEFAULT 'simples', 
    data_emprestimo     DATE NOT NULL,
    data_vencimento     DATE,
    status              VARCHAR(30) DEFAULT 'ativo', 
    observacoes         TEXT,
    criado_em           TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em       TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE parcelas (
    id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    emprestimo_id       BIGINT NOT NULL REFERENCES emprestimos(id) ON DELETE CASCADE,
    numero_parcela      INTEGER NOT NULL,
    valor               DECIMAL(15,2) NOT NULL,
    data_vencimento     DATE NOT NULL,
    data_pagamento      DATE,
    valor_pago          DECIMAL(15,2),
    status              VARCHAR(20) DEFAULT 'pendente', 
    criado_em           TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em       TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(emprestimo_id, numero_parcela)
);

CREATE TABLE categorias (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    nome            VARCHAR(100) NOT NULL,
    tipo            VARCHAR(20) NOT NULL, 
    cor             VARCHAR(7), 
    icone           VARCHAR(50),
    ativo           BOOLEAN DEFAULT TRUE,
    criado_em       TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(usuario_id, nome, tipo)
);

CREATE TABLE receitas (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    categoria_id    BIGINT REFERENCES categorias(id) ON DELETE SET NULL,
    descricao       VARCHAR(255) NOT NULL,
    valor           DECIMAL(15,2) NOT NULL,
    data_receita    DATE NOT NULL,
    recorrente      BOOLEAN DEFAULT FALSE,
    observacoes     TEXT,
    criado_em       TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE despesas (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    categoria_id    BIGINT REFERENCES categorias(id) ON DELETE SET NULL,
    descricao       VARCHAR(255) NOT NULL,
    valor           DECIMAL(15,2) NOT NULL,
    data_despesa    DATE NOT NULL,
    recorrente      BOOLEAN DEFAULT FALSE,
    observacoes     TEXT,
    criado_em       TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE transacoes (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    categoria_id    BIGINT REFERENCES categorias(id) ON DELETE SET NULL,
    tipo            VARCHAR(20) NOT NULL, 
    descricao       VARCHAR(255) NOT NULL,
    valor           DECIMAL(15,2) NOT NULL,
    data_transacao  DATE NOT NULL,
    origem_tipo     VARCHAR(50), 
    origem_id       BIGINT, 
    observacoes     TEXT,
    criado_em       TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE metas (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    titulo          VARCHAR(150) NOT NULL,
    descricao       TEXT,
    valor_alvo      DECIMAL(15,2) NOT NULL,
    valor_atual     DECIMAL(15,2) DEFAULT 0,
    data_inicio     DATE,
    data_limite     DATE,
    status          VARCHAR(20) DEFAULT 'em_andamento', 
    criado_em       TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE projetos (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    nome            VARCHAR(150) NOT NULL,
    descricao       TEXT,
    status          VARCHAR(30) DEFAULT 'ativo', 
    data_inicio     DATE,
    data_fim        DATE,
    orcamento       DECIMAL(15,2),
    criado_em       TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE projeto_categorias (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    projeto_id      BIGINT NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
    nome            VARCHAR(100) NOT NULL,
    tipo            VARCHAR(20) DEFAULT 'despesa', 
    criado_em       TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE aportes (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    projeto_id      BIGINT REFERENCES projetos(id) ON DELETE SET NULL,
    meta_id         BIGINT REFERENCES metas(id) ON DELETE SET NULL,
    valor           DECIMAL(15,2) NOT NULL,
    data_aporte     DATE NOT NULL,
    descricao       VARCHAR(255),
    criado_em       TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE planejamentos (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    projeto_id      BIGINT REFERENCES projetos(id) ON DELETE SET NULL,
    titulo          VARCHAR(150) NOT NULL,
    descricao       TEXT,
    data_inicio     DATE,
    data_fim        DATE,
    status          VARCHAR(30) DEFAULT 'planejado', 
    criado_em       TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE planejamentos_etapas (
    id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    planejamento_id     BIGINT NOT NULL REFERENCES planejamentos(id) ON DELETE CASCADE,
    titulo              VARCHAR(150) NOT NULL,
    descricao           TEXT,
    ordem               INTEGER DEFAULT 0,
    status              VARCHAR(20) DEFAULT 'pendente', 
    data_prevista       DATE,
    data_conclusao      DATE,
    criado_em           TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE conversas_ia (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    titulo          VARCHAR(200),
    contexto        TEXT, 
    criado_em       TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE mensagens_ia (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    conversa_id     BIGINT NOT NULL REFERENCES conversas_ia(id) ON DELETE CASCADE,
    role            VARCHAR(20) NOT NULL, 
    conteudo        TEXT NOT NULL,
    tokens_usados   INTEGER,
    criado_em       TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE alertas (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    tipo            VARCHAR(50) NOT NULL, 
    titulo          VARCHAR(150) NOT NULL,
    mensagem        TEXT,
    referencia_tipo VARCHAR(50), 
    referencia_id   BIGINT,
    lido            BOOLEAN DEFAULT FALSE,
    data_alerta     TIMESTAMPTZ DEFAULT NOW(),
    criado_em       TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE notificacoes (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    titulo          VARCHAR(150) NOT NULL,
    mensagem        TEXT,
    tipo            VARCHAR(30) DEFAULT 'info', 
    lida            BOOLEAN DEFAULT FALSE,
    link            TEXT, 
    criado_em       TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE relatorios (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id      BIGINT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    tipo            VARCHAR(50) NOT NULL, 
    titulo          VARCHAR(150) NOT NULL,
    parametros      JSONB, 
    arquivo_url     TEXT, 
    gerado_em       TIMESTAMPTZ DEFAULT NOW(),
    criado_em       TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_clientes_usuario ON clientes(usuario_id);
CREATE INDEX idx_emprestimos_usuario ON emprestimos(usuario_id);
CREATE INDEX idx_emprestimos_cliente ON emprestimos(cliente_id);
CREATE INDEX idx_parcelas_emprestimo ON parcelas(emprestimo_id);
CREATE INDEX idx_parcelas_status ON parcelas(status);
CREATE INDEX idx_receitas_usuario ON receitas(usuario_id);
CREATE INDEX idx_despesas_usuario ON despesas(usuario_id);
CREATE INDEX idx_transacoes_usuario ON transacoes(usuario_id);
CREATE INDEX idx_transacoes_data ON transacoes(data_transacao);
CREATE INDEX idx_metas_usuario ON metas(usuario_id);
CREATE INDEX idx_projetos_usuario ON projetos(usuario_id);
CREATE INDEX idx_aportes_usuario ON aportes(usuario_id);
CREATE INDEX idx_planejamentos_usuario ON planejamentos(usuario_id);
CREATE INDEX idx_conversas_usuario ON conversas_ia(usuario_id);
CREATE INDEX idx_mensagens_conversa ON mensagens_ia(conversa_id);
CREATE INDEX idx_alertas_usuario ON alertas(usuario_id);
CREATE INDEX idx_notificacoes_usuario ON notificacoes(usuario_id);
CREATE INDEX idx_relatorios_usuario ON relatorios(usuario_id);
