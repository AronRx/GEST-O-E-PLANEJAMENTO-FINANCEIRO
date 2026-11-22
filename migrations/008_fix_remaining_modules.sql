-- ============================================================
-- Migration 008: Fix remaining modules
-- contas, categorias, receitas, despesas, metas, projetos,
-- planejamentos, relatorios, transacoes, aportes, etc.
-- ============================================================
-- Execute APÓS schema.sql e finplan_parcelas_recebimentos.sql
-- ============================================================

-- ---------------------------------------------------------------------------
-- 1. Tabela contas (FALTANTE - referenciada pelo PHP mas não existe no schema)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS finplan.contas (
    id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id          BIGINT NOT NULL REFERENCES finplan.usuarios(id) ON DELETE CASCADE,
    nome                VARCHAR(150) NOT NULL,
    tipo                VARCHAR(30) NOT NULL DEFAULT 'carteira', -- carteira, conta_corrente, poupanca, investimento, cartao_credito, outro
    saldo_inicial       DECIMAL(15,2) NOT NULL DEFAULT 0,
    saldo_atual         DECIMAL(15,2) NOT NULL DEFAULT 0,
    ativa               BOOLEAN DEFAULT TRUE,
    cor                 VARCHAR(7), -- hex color para UI
    icone               VARCHAR(50), -- nome do ícone (ex: bi-wallet, bi-bank)
    observacoes         TEXT,
    criado_em           TIMESTAMPTZ DEFAULT NOW(),
    atualizado_em       TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (usuario_id, nome)
);

CREATE INDEX IF NOT EXISTS idx_contas_usuario ON finplan.contas(usuario_id);
CREATE INDEX IF NOT EXISTS idx_contas_ativa ON finplan.contas(ativa);

-- ---------------------------------------------------------------------------
-- 2. Colunas faltantes em parcelas (já em finplan_parcelas_recebimentos.sql,
--    incluídas aqui para idempotência)
-- ---------------------------------------------------------------------------
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'finplan' AND table_name = 'parcelas' AND column_name = 'valor_juros') THEN
        ALTER TABLE finplan.parcelas ADD COLUMN valor_juros NUMERIC(15,2) NOT NULL DEFAULT 0;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'finplan' AND table_name = 'parcelas' AND column_name = 'valor_amortizacao') THEN
        ALTER TABLE finplan.parcelas ADD COLUMN valor_amortizacao NUMERIC(15,2) NOT NULL DEFAULT 0;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'finplan' AND table_name = 'parcelas' AND column_name = 'saldo_devedor_inicial') THEN
        ALTER TABLE finplan.parcelas ADD COLUMN saldo_devedor_inicial NUMERIC(15,2) NOT NULL DEFAULT 0;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'finplan' AND table_name = 'parcelas' AND column_name = 'saldo_devedor_final') THEN
        ALTER TABLE finplan.parcelas ADD COLUMN saldo_devedor_final NUMERIC(15,2) NOT NULL DEFAULT 0;
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- 3. Tabela recebimentos (FALTANTE)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS finplan.recebimentos (
    id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id          BIGINT NOT NULL REFERENCES finplan.usuarios(id) ON DELETE CASCADE,
    parcela_id          BIGINT NOT NULL REFERENCES finplan.parcelas(id) ON DELETE CASCADE,
    conta_id            BIGINT NOT NULL REFERENCES finplan.contas(id) ON DELETE RESTRICT,
    valor               NUMERIC(15,2) NOT NULL,
    data_recebimento    DATE NOT NULL,
    forma_pagamento     VARCHAR(20) NOT NULL CHECK (forma_pagamento IN ('dinheiro','pix','transferencia','boleto','cartao','outro')),
    observacoes         TEXT,
    criado_em           TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_recebimentos_parcela ON finplan.recebimentos(parcela_id);
CREATE INDEX IF NOT EXISTS idx_recebimentos_usuario ON finplan.recebimentos(usuario_id);
CREATE INDEX IF NOT EXISTS idx_recebimentos_data ON finplan.recebimentos(data_recebimento);
CREATE INDEX IF NOT EXISTS idx_recebimentos_conta ON finplan.recebimentos(conta_id);

-- ---------------------------------------------------------------------------
-- 4. Adicionar colunas faltantes nas tabelas existentes
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    t text;
BEGIN
    -- adicionar atualizado_em
    FOREACH t IN ARRAY ARRAY['categorias','transacoes','projeto_categorias','aportes','planejamentos_etapas','mensagens_ia','notificacoes'] LOOP
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'finplan' AND table_name = t AND column_name = 'atualizado_em') THEN
            EXECUTE format('ALTER TABLE finplan.%I ADD COLUMN atualizado_em TIMESTAMPTZ DEFAULT NOW()', t);
        END IF;
    END LOOP;

    -- adicionar conta_id em transacoes (para vincular transação à conta)
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'finplan' AND table_name = 'transacoes' AND column_name = 'conta_id') THEN
        ALTER TABLE finplan.transacoes ADD COLUMN conta_id BIGINT REFERENCES finplan.contas(id) ON DELETE SET NULL;
    END IF;

    -- adicionar recorrente em transacoes
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'finplan' AND table_name = 'transacoes' AND column_name = 'recorrente') THEN
        ALTER TABLE finplan.transacoes ADD COLUMN recorrente BOOLEAN DEFAULT FALSE;
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- 5. Funções de contexto RLS (devem vir ANTES das policies)
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION finplan.fn_current_usuario_id()
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_user_id BIGINT;
BEGIN
    BEGIN
        v_user_id := current_setting('finplan.current_usuario_id')::BIGINT;
        RETURN v_user_id;
    EXCEPTION WHEN OTHERS THEN
        RETURN NULL;
    END;
END $$;

CREATE OR REPLACE FUNCTION finplan.fn_iniciar_contexto(p_user_id BIGINT)
RETURNS VOID LANGUAGE plpgsql AS $$
BEGIN
    PERFORM set_config('finplan.current_usuario_id', p_user_id::TEXT, FALSE);
END $$;

-- ---------------------------------------------------------------------------
-- 6. Função genérica para atualizar atualizado_em
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION finplan.fn_atualizar_timestamp()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.atualizado_em = NOW();
    RETURN NEW;
END $$;

-- Aplicar trigger em todas as tabelas com atualizado_em
DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'usuarios','clientes','emprestimos','parcelas','contas',
        'categorias','receitas','despesas','transacoes',
        'metas','projetos','projeto_categorias','aportes',
        'planejamentos','planejamentos_etapas',
        'conversas_ia','mensagens_ia',
        'alertas','notificacoes','relatorios'
    ] LOOP
        EXECUTE format($f$
            DROP TRIGGER IF EXISTS trg_%1$I_atualizado_em ON finplan.%1$I;
            CREATE TRIGGER trg_%1$I_atualizado_em
            BEFORE UPDATE ON finplan.%1$I
            FOR EACH ROW EXECUTE FUNCTION finplan.fn_atualizar_timestamp();
        $f$, t);
    END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- 7. Views para cada módulo
-- ---------------------------------------------------------------------------

-- vw_contas - saldos e contagem de transações
CREATE OR REPLACE VIEW finplan.vw_contas AS
SELECT 
    c.id,
    c.usuario_id,
    c.nome,
    c.tipo,
    c.saldo_inicial,
    c.saldo_atual,
    c.ativa,
    c.cor,
    c.icone,
    c.observacoes,
    c.criado_em,
    c.atualizado_em,
    COALESCE(t.creditos, 0)::NUMERIC(15,2) AS total_creditos,
    COALESCE(t.debitos, 0)::NUMERIC(15,2) AS total_debitos,
    COALESCE(t.qtd_transacoes, 0) AS qtd_transacoes
FROM finplan.contas c
LEFT JOIN (
    SELECT 
        conta_id,
        SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS creditos,
        SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS debitos,
        COUNT(*) AS qtd_transacoes
    FROM finplan.transacoes
    WHERE conta_id IS NOT NULL
    GROUP BY conta_id
) t ON t.conta_id = c.id;

-- vw_categorias - com totais de receitas/despesas
CREATE OR REPLACE VIEW finplan.vw_categorias AS
SELECT 
    c.id,
    c.usuario_id,
    c.nome,
    c.tipo,
    c.cor,
    c.icone,
    c.ativo,
    c.criado_em,
    c.atualizado_em,
    COALESCE(r.total_receitas, 0)::NUMERIC(15,2) AS total_receitas,
    COALESCE(d.total_despesas, 0)::NUMERIC(15,2) AS total_despesas,
    COALESCE(r.qtd_receitas, 0) + COALESCE(d.qtd_despesas, 0) AS qtd_lancamentos
FROM finplan.categorias c
LEFT JOIN (
    SELECT categoria_id, SUM(valor) AS total_receitas, COUNT(*) AS qtd_receitas
    FROM finplan.receitas
    GROUP BY categoria_id
) r ON r.categoria_id = c.id
LEFT JOIN (
    SELECT categoria_id, SUM(valor) AS total_despesas, COUNT(*) AS qtd_despesas
    FROM finplan.despesas
    GROUP BY categoria_id
) d ON d.categoria_id = c.id;

-- vw_receitas - com nome da categoria
CREATE OR REPLACE VIEW finplan.vw_receitas AS
SELECT 
    r.id,
    r.usuario_id,
    r.categoria_id,
    c.nome AS categoria_nome,
    c.cor AS categoria_cor,
    c.icone AS categoria_icone,
    r.descricao,
    r.valor,
    r.data_receita,
    r.recorrente,
    r.observacoes,
    r.criado_em,
    r.atualizado_em
FROM finplan.receitas r
LEFT JOIN finplan.categorias c ON c.id = r.categoria_id;

-- vw_despesas - com nome da categoria
CREATE OR REPLACE VIEW finplan.vw_despesas AS
SELECT 
    d.id,
    d.usuario_id,
    d.categoria_id,
    c.nome AS categoria_nome,
    c.cor AS categoria_cor,
    c.icone AS categoria_icone,
    d.descricao,
    d.valor,
    d.data_despesa,
    d.recorrente,
    d.observacoes,
    d.criado_em,
    d.atualizado_em
FROM finplan.despesas d
LEFT JOIN finplan.categorias c ON c.id = d.categoria_id;

-- vw_transacoes - unifica receitas, despesas, parcelas, aportes
CREATE OR REPLACE VIEW finplan.vw_transacoes AS
SELECT 
    t.id,
    t.usuario_id,
    t.categoria_id,
    cat.nome AS categoria_nome,
    cat.cor AS categoria_cor,
    t.tipo,
    t.descricao,
    t.valor,
    t.data_transacao,
    t.origem_tipo,
    t.origem_id,
    t.observacoes,
    t.criado_em
FROM finplan.transacoes t
LEFT JOIN finplan.categorias cat ON cat.id = t.categoria_id;

-- vw_metas - progresso e status calculado
CREATE OR REPLACE VIEW finplan.vw_metas AS
SELECT 
    m.id,
    m.usuario_id,
    m.titulo,
    m.descricao,
    m.valor_alvo,
    m.valor_atual,
    m.data_inicio,
    m.data_limite,
    m.status,
    m.criado_em,
    m.atualizado_em,
    CASE 
        WHEN m.valor_alvo = 0 THEN 0
        ELSE ROUND((m.valor_atual / m.valor_alvo) * 100, 2)
    END AS percentual_concluido,
    CASE
        WHEN m.valor_atual >= m.valor_alvo THEN 'concluida'
        WHEN m.data_limite IS NOT NULL AND m.data_limite < CURRENT_DATE AND m.valor_atual < m.valor_alvo THEN 'atrasada'
        ELSE m.status
    END AS status_calculado,
    COALESCE(a.total_aportes, 0)::NUMERIC(15,2) AS total_aportes
FROM finplan.metas m
LEFT JOIN (
    SELECT meta_id, SUM(valor) AS total_aportes
    FROM finplan.aportes
    GROUP BY meta_id
) a ON a.meta_id = m.id;

-- vw_projetos - com orçamento gasto
CREATE OR REPLACE VIEW finplan.vw_projetos AS
SELECT 
    p.id,
    p.usuario_id,
    p.nome,
    p.descricao,
    p.status,
    p.data_inicio,
    p.data_fim,
    p.orcamento,
    p.criado_em,
    p.atualizado_em,
    COALESCE(g.gasto_total, 0)::NUMERIC(15,2) AS gasto_total,
    COALESCE(a.total_aportes, 0)::NUMERIC(15,2) AS total_aportes,
    CASE 
        WHEN p.orcamento IS NULL OR p.orcamento = 0 THEN NULL
        ELSE ROUND((COALESCE(g.gasto_total, 0) / p.orcamento) * 100, 2)
    END AS percentual_orcamento
FROM finplan.projetos p
LEFT JOIN (
    SELECT origem_id AS projeto_id, SUM(valor) AS gasto_total
    FROM finplan.transacoes
    WHERE origem_tipo = 'projeto'
    GROUP BY origem_id
) g ON g.projeto_id = p.id
LEFT JOIN (
    SELECT projeto_id, SUM(valor) AS total_aportes
    FROM finplan.aportes
    GROUP BY projeto_id
) a ON a.projeto_id = p.id;

-- vw_projeto_categorias - categorias do projeto com totais
CREATE OR REPLACE VIEW finplan.vw_projeto_categorias AS
SELECT 
    pc.id,
    pc.projeto_id,
    pc.nome,
    pc.tipo,
    pc.criado_em,
    COALESCE(t.total, 0)::NUMERIC(15,2) AS total_gasto
FROM finplan.projeto_categorias pc
LEFT JOIN (
    SELECT origem_id, SUM(valor) AS total
    FROM finplan.transacoes
    WHERE origem_tipo = 'projeto_categoria'
    GROUP BY origem_id
) t ON t.origem_id = pc.id;

-- vw_aportes - com nomes de meta/projeto
CREATE OR REPLACE VIEW finplan.vw_aportes AS
SELECT 
    a.id,
    a.usuario_id,
    a.projeto_id,
    p.nome AS projeto_nome,
    a.meta_id,
    m.titulo AS meta_titulo,
    a.valor,
    a.data_aporte,
    a.descricao,
    a.criado_em
FROM finplan.aportes a
LEFT JOIN finplan.projetos p ON p.id = a.projeto_id
LEFT JOIN finplan.metas m ON m.id = a.meta_id;

-- vw_planejamentos - com contagem de etapas
CREATE OR REPLACE VIEW finplan.vw_planejamentos AS
SELECT 
    pl.id,
    pl.usuario_id,
    pl.projeto_id,
    p.nome AS projeto_nome,
    pl.titulo,
    pl.descricao,
    pl.data_inicio,
    pl.data_fim,
    pl.status,
    pl.criado_em,
    pl.atualizado_em,
    COUNT(pe.id) AS qtd_etapas,
    COUNT(pe.id) FILTER (WHERE pe.status = 'concluida') AS qtd_concluidas,
    CASE 
        WHEN COUNT(pe.id) = 0 THEN 0
        ELSE ROUND((COUNT(pe.id) FILTER (WHERE pe.status = 'concluida')::NUMERIC / COUNT(pe.id)) * 100, 2)
    END AS percentual_concluido
FROM finplan.planejamentos pl
LEFT JOIN finplan.projetos p ON p.id = pl.projeto_id
LEFT JOIN finplan.planejamentos_etapas pe ON pe.planejamento_id = pl.id
GROUP BY pl.id, pl.usuario_id, pl.projeto_id, p.nome, pl.titulo, pl.descricao, pl.data_inicio, pl.data_fim, pl.status, pl.criado_em, pl.atualizado_em;

-- vw_planejamentos_etapas
CREATE OR REPLACE VIEW finplan.vw_planejamentos_etapas AS
SELECT 
    pe.id,
    pe.planejamento_id,
    pl.titulo AS planejamento_titulo,
    pe.titulo,
    pe.descricao,
    pe.ordem,
    pe.status,
    pe.data_prevista,
    pe.data_conclusao,
    pe.criado_em
FROM finplan.planejamentos_etapas pe
LEFT JOIN finplan.planejamentos pl ON pl.id = pe.planejamento_id;

-- vw_relatorios
CREATE OR REPLACE VIEW finplan.vw_relatorios AS
SELECT 
    r.id,
    r.usuario_id,
    r.tipo,
    r.titulo,
    r.parametros,
    r.arquivo_url,
    r.gerado_em,
    r.criado_em
FROM finplan.relatorios r;

-- ---------------------------------------------------------------------------
-- 7. Triggers de negócio
-- ---------------------------------------------------------------------------

-- 7.1 Trigger: Atualizar saldo_atual da conta quando houver transação
CREATE OR REPLACE FUNCTION finplan.fn_atualizar_saldo_conta()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.conta_id IS NOT NULL THEN
        IF TG_OP = 'INSERT' THEN
            UPDATE finplan.contas
            SET saldo_atual = saldo_atual + 
                CASE WHEN NEW.tipo = 'receita' THEN NEW.valor ELSE -NEW.valor END,
                atualizado_em = NOW()
            WHERE id = NEW.conta_id;
        ELSIF TG_OP = 'UPDATE' THEN
            UPDATE finplan.contas
            SET saldo_atual = saldo_atual - 
                CASE WHEN OLD.tipo = 'receita' THEN OLD.valor ELSE -OLD.valor END +
                CASE WHEN NEW.tipo = 'receita' THEN NEW.valor ELSE -NEW.valor END,
                atualizado_em = NOW()
            WHERE id = NEW.conta_id;
        ELSIF TG_OP = 'DELETE' THEN
            UPDATE finplan.contas
            SET saldo_atual = saldo_atual - 
                CASE WHEN OLD.tipo = 'receita' THEN OLD.valor ELSE -OLD.valor END,
                atualizado_em = NOW()
            WHERE id = OLD.conta_id;
        END IF;
    END IF;
    RETURN NEW;
END $$;

DROP TRIGGER IF EXISTS trg_atualizar_saldo_conta ON finplan.transacoes;
CREATE TRIGGER trg_atualizar_saldo_conta
AFTER INSERT OR UPDATE OR DELETE ON finplan.transacoes
FOR EACH ROW EXECUTE FUNCTION finplan.fn_atualizar_saldo_conta();

-- 7.2 Trigger: Atualizar valor_atual da meta quando houver aporte
CREATE OR REPLACE FUNCTION finplan.fn_atualizar_valor_meta()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.meta_id IS NOT NULL THEN
        IF TG_OP = 'INSERT' THEN
            UPDATE finplan.metas
            SET valor_atual = valor_atual + NEW.valor,
                atualizado_em = NOW()
            WHERE id = NEW.meta_id;
        ELSIF TG_OP = 'UPDATE' THEN
            UPDATE finplan.metas
            SET valor_atual = valor_atual - OLD.valor + NEW.valor,
                atualizado_em = NOW()
            WHERE id = NEW.meta_id;
        ELSIF TG_OP = 'DELETE' THEN
            UPDATE finplan.metas
            SET valor_atual = valor_atual - OLD.valor,
                atualizado_em = NOW()
            WHERE id = OLD.meta_id;
        END IF;
    END IF;
    RETURN NEW;
END $$;

DROP TRIGGER IF EXISTS trg_atualizar_valor_meta ON finplan.aportes;
CREATE TRIGGER trg_atualizar_valor_meta
AFTER INSERT OR UPDATE OR DELETE ON finplan.aportes
FOR EACH ROW EXECUTE FUNCTION finplan.fn_atualizar_valor_meta();

-- 7.3 Trigger: Bloquear exclusão de categoria se houver lançamentos
CREATE OR REPLACE FUNCTION finplan.fn_proteger_categoria()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF EXISTS (SELECT 1 FROM finplan.receitas WHERE categoria_id = OLD.id) OR
           EXISTS (SELECT 1 FROM finplan.despesas WHERE categoria_id = OLD.id) OR
           EXISTS (SELECT 1 FROM finplan.transacoes WHERE categoria_id = OLD.id) THEN
            RAISE EXCEPTION 'Não é possível excluir a categoria "%" pois possui lançamentos vinculados.', OLD.nome;
        END IF;
    END IF;
    RETURN OLD;
END $$;

DROP TRIGGER IF EXISTS trg_proteger_categoria ON finplan.categorias;
CREATE TRIGGER trg_proteger_categoria
BEFORE DELETE ON finplan.categorias
FOR EACH ROW EXECUTE FUNCTION finplan.fn_proteger_categoria();

-- 7.4 Trigger: Bloquear exclusão de conta se houver transações
CREATE OR REPLACE FUNCTION finplan.fn_proteger_conta()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF EXISTS (SELECT 1 FROM finplan.transacoes WHERE conta_id = OLD.id) OR
           EXISTS (SELECT 1 FROM finplan.recebimentos WHERE conta_id = OLD.id) THEN
            RAISE EXCEPTION 'Não é possível excluir a conta "%" pois possui transações ou recebimentos vinculados.', OLD.nome;
        END IF;
    END IF;
    RETURN OLD;
END $$;

DROP TRIGGER IF EXISTS trg_proteger_conta ON finplan.contas;
CREATE TRIGGER trg_proteger_conta
BEFORE DELETE ON finplan.contas
FOR EACH ROW EXECUTE FUNCTION finplan.fn_proteger_conta();

-- 7.5 Trigger: Validar recebimento (já existe em finplan_parcelas_recebimentos.sql)
-- 7.6 Trigger: Sincronizar parcelas (já existe em finplan_parcelas_recebimentos.sql)

-- ---------------------------------------------------------------------------
-- 8. RLS (Row Level Security) - Habilitar nas tabelas principais
--    O PHP usa filtros explícitos (defesa em profundidade), mas RLS é a 1ª camada
--    Apenas tabelas que têm usuario_id diretamente recebem policies simples
-- ---------------------------------------------------------------------------

DO $$
DECLARE
    t text;
BEGIN
    -- Tabelas com usuario_id direto
    FOREACH t IN ARRAY ARRAY[
        'contas','categorias','receitas','despesas','transacoes',
        'metas','projetos','planejamentos','relatorios'
    ] LOOP
        EXECUTE format('ALTER TABLE finplan.%I ENABLE ROW LEVEL SECURITY', t);
        
        EXECUTE format($p$
            DROP POLICY IF EXISTS %1$I_select_own ON finplan.%1$I;
            CREATE POLICY %1$I_select_own ON finplan.%1$I
            FOR SELECT USING (usuario_id = finplan.fn_current_usuario_id());
        $p$, t);
        
        EXECUTE format($p$
            DROP POLICY IF EXISTS %1$I_insert_own ON finplan.%1$I;
            CREATE POLICY %1$I_insert_own ON finplan.%1$I
            FOR INSERT WITH CHECK (usuario_id = finplan.fn_current_usuario_id());
        $p$, t);
        
        EXECUTE format($p$
            DROP POLICY IF EXISTS %1$I_update_own ON finplan.%1$I;
            CREATE POLICY %1$I_update_own ON finplan.%1$I
            FOR UPDATE USING (usuario_id = finplan.fn_current_usuario_id());
        $p$, t);
        
        EXECUTE format($p$
            DROP POLICY IF EXISTS %1$I_delete_own ON finplan.%1$I;
            CREATE POLICY %1$I_delete_own ON finplan.%1$I
            FOR DELETE USING (usuario_id = finplan.fn_current_usuario_id());
        $p$, t);
    END LOOP;
    
    -- Tabelas sem usuario_id direto: habilitar RLS mas sem policies automáticas
    -- O acesso é controlado via JOIN com tabelas pai (projetos, metas, planejamentos)
    -- ou via filtros explícitos no PHP (defesa em profundidade)
    FOREACH t IN ARRAY ARRAY[
        'projeto_categorias','aportes','planejamentos_etapas'
    ] LOOP
        EXECUTE format('ALTER TABLE finplan.%I ENABLE ROW LEVEL SECURITY', t);
        -- Policies customizadas seriam necessárias aqui (JOIN com tabela pai)
        -- Por enquanto, o PHP filtra explicitamente
    END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- 11. Permissões para role finplan_app (condicional - só se a role existir)
-- ---------------------------------------------------------------------------
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'finplan_app') THEN
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.contas TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.categorias TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.receitas TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.despesas TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.transacoes TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.metas TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.projetos TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.projeto_categorias TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.aportes TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.planejamentos TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.planejamentos_etapas TO finplan_app;
        GRANT SELECT, INSERT, UPDATE, DELETE ON finplan.relatorios TO finplan_app;

        GRANT SELECT ON finplan.vw_contas TO finplan_app;
        GRANT SELECT ON finplan.vw_categorias TO finplan_app;
        GRANT SELECT ON finplan.vw_receitas TO finplan_app;
        GRANT SELECT ON finplan.vw_despesas TO finplan_app;
        GRANT SELECT ON finplan.vw_transacoes TO finplan_app;
        GRANT SELECT ON finplan.vw_metas TO finplan_app;
        GRANT SELECT ON finplan.vw_projetos TO finplan_app;
        GRANT SELECT ON finplan.vw_projeto_categorias TO finplan_app;
        GRANT SELECT ON finplan.vw_aportes TO finplan_app;
        GRANT SELECT ON finplan.vw_planejamentos TO finplan_app;
        GRANT SELECT ON finplan.vw_planejamentos_etapas TO finplan_app;
        GRANT SELECT ON finplan.vw_relatorios TO finplan_app;

        GRANT EXECUTE ON FUNCTION finplan.fn_atualizar_timestamp() TO finplan_app;
        GRANT EXECUTE ON FUNCTION finplan.fn_atualizar_saldo_conta() TO finplan_app;
        GRANT EXECUTE ON FUNCTION finplan.fn_atualizar_valor_meta() TO finplan_app;
        GRANT EXECUTE ON FUNCTION finplan.fn_proteger_categoria() TO finplan_app;
        GRANT EXECUTE ON FUNCTION finplan.fn_proteger_conta() TO finplan_app;
        GRANT EXECUTE ON FUNCTION finplan.fn_current_usuario_id() TO finplan_app;
        GRANT EXECUTE ON FUNCTION finplan.fn_iniciar_contexto(BIGINT) TO finplan_app;
    END IF;
END $$;

-- ============================================================
-- FIM DA MIGRATION 008
-- ============================================================