-- ============================================================
-- Migration 009: Fix RLS policies for schema finplan
-- Garante que cada usuário só acessa seus próprios dados.
-- Adiciona policies com JOIN para tabelas sem usuario_id direto.
-- ============================================================
-- Execute APÓS migration 008
-- ============================================================

-- ---------------------------------------------------------------------------
-- 1. Verifica e cria função fn_current_usuario_id se não existir
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

-- ---------------------------------------------------------------------------
-- 2. Tabelas COM usuario_id direto - policies simples (reforço)
--    (já criadas na migration 008, mas garantimos aqui)
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'contas','categorias','receitas','despesas','transacoes',
        'metas','projetos','planejamentos','relatorios',
        'aportes'  -- tem usuario_id direto
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
END $$;

-- ---------------------------------------------------------------------------
-- 3. Tabelas SEM usuario_id direto - policies com JOIN
-- ---------------------------------------------------------------------------

-- 3.1 parcelas -> JOIN emprestimos.usuario_id
DO $$
BEGIN
    EXECUTE 'ALTER TABLE finplan.parcelas ENABLE ROW LEVEL SECURITY';
    
    EXECUTE $p$
        DROP POLICY IF EXISTS parcelas_select_own ON finplan.parcelas;
        CREATE POLICY parcelas_select_own ON finplan.parcelas
        FOR SELECT USING (
            emprestimo_id IN (
                SELECT id FROM finplan.emprestimos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS parcelas_insert_own ON finplan.parcelas;
        CREATE POLICY parcelas_insert_own ON finplan.parcelas
        FOR INSERT WITH CHECK (
            emprestimo_id IN (
                SELECT id FROM finplan.emprestimos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS parcelas_update_own ON finplan.parcelas;
        CREATE POLICY parcelas_update_own ON finplan.parcelas
        FOR UPDATE USING (
            emprestimo_id IN (
                SELECT id FROM finplan.emprestimos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS parcelas_delete_own ON finplan.parcelas;
        CREATE POLICY parcelas_delete_own ON finplan.parcelas
        FOR DELETE USING (
            emprestimo_id IN (
                SELECT id FROM finplan.emprestimos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
END $$;

-- 3.2 recebimentos -> JOIN parcelas -> emprestimos.usuario_id
DO $$
BEGIN
    EXECUTE 'ALTER TABLE finplan.recebimentos ENABLE ROW LEVEL SECURITY';
    
    EXECUTE $p$
        DROP POLICY IF EXISTS recebimentos_select_own ON finplan.recebimentos;
        CREATE POLICY recebimentos_select_own ON finplan.recebimentos
        FOR SELECT USING (
            parcela_id IN (
                SELECT p.id FROM finplan.parcelas p
                JOIN finplan.emprestimos e ON e.id = p.emprestimo_id
                WHERE e.usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS recebimentos_insert_own ON finplan.recebimentos;
        CREATE POLICY recebimentos_insert_own ON finplan.recebimentos
        FOR INSERT WITH CHECK (
            parcela_id IN (
                SELECT p.id FROM finplan.parcelas p
                JOIN finplan.emprestimos e ON e.id = p.emprestimo_id
                WHERE e.usuario_id = finplan.fn_current_usuario_id()
            )
            AND usuario_id = finplan.fn_current_usuario_id()
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS recebimentos_update_own ON finplan.recebimentos;
        CREATE POLICY recebimentos_update_own ON finplan.recebimentos
        FOR UPDATE USING (
            parcela_id IN (
                SELECT p.id FROM finplan.parcelas p
                JOIN finplan.emprestimos e ON e.id = p.emprestimo_id
                WHERE e.usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS recebimentos_delete_own ON finplan.recebimentos;
        CREATE POLICY recebimentos_delete_own ON finplan.recebimentos
        FOR DELETE USING (
            parcela_id IN (
                SELECT p.id FROM finplan.parcelas p
                JOIN finplan.emprestimos e ON e.id = p.emprestimo_id
                WHERE e.usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
END $$;

-- 3.3 projeto_categorias -> JOIN projetos.usuario_id
DO $$
BEGIN
    EXECUTE 'ALTER TABLE finplan.projeto_categorias ENABLE ROW LEVEL SECURITY';
    
    EXECUTE $p$
        DROP POLICY IF EXISTS projeto_categorias_select_own ON finplan.projeto_categorias;
        CREATE POLICY projeto_categorias_select_own ON finplan.projeto_categorias
        FOR SELECT USING (
            projeto_id IN (
                SELECT id FROM finplan.projetos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS projeto_categorias_insert_own ON finplan.projeto_categorias;
        CREATE POLICY projeto_categorias_insert_own ON finplan.projeto_categorias
        FOR INSERT WITH CHECK (
            projeto_id IN (
                SELECT id FROM finplan.projetos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS projeto_categorias_update_own ON finplan.projeto_categorias;
        CREATE POLICY projeto_categorias_update_own ON finplan.projeto_categorias
        FOR UPDATE USING (
            projeto_id IN (
                SELECT id FROM finplan.projetos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS projeto_categorias_delete_own ON finplan.projeto_categorias;
        CREATE POLICY projeto_categorias_delete_own ON finplan.projeto_categorias
        FOR DELETE USING (
            projeto_id IN (
                SELECT id FROM finplan.projetos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
END $$;

-- 3.4 planejamentos_etapas -> JOIN planejamentos.usuario_id
DO $$
BEGIN
    EXECUTE 'ALTER TABLE finplan.planejamentos_etapas ENABLE ROW LEVEL SECURITY';
    
    EXECUTE $p$
        DROP POLICY IF EXISTS planejamentos_etapas_select_own ON finplan.planejamentos_etapas;
        CREATE POLICY planejamentos_etapas_select_own ON finplan.planejamentos_etapas
        FOR SELECT USING (
            planejamento_id IN (
                SELECT id FROM finplan.planejamentos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS planejamentos_etapas_insert_own ON finplan.planejamentos_etapas;
        CREATE POLICY planejamentos_etapas_insert_own ON finplan.planejamentos_etapas
        FOR INSERT WITH CHECK (
            planejamento_id IN (
                SELECT id FROM finplan.planejamentos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS planejamentos_etapas_update_own ON finplan.planejamentos_etapas;
        CREATE POLICY planejamentos_etapas_update_own ON finplan.planejamentos_etapas
        FOR UPDATE USING (
            planejamento_id IN (
                SELECT id FROM finplan.planejamentos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
    
    EXECUTE $p$
        DROP POLICY IF EXISTS planejamentos_etapas_delete_own ON finplan.planejamentos_etapas;
        CREATE POLICY planejamentos_etapas_delete_own ON finplan.planejamentos_etapas
        FOR DELETE USING (
            planejamento_id IN (
                SELECT id FROM finplan.planejamentos 
                WHERE usuario_id = finplan.fn_current_usuario_id()
            )
        );
    $p$;
END $$;

-- ---------------------------------------------------------------------------
-- 4. Tabelas com usuario_id mas que podem precisar de políticas adicionais
--    Verifica e corrige clientes, emprestimos, usuarios
-- ---------------------------------------------------------------------------

DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'clientes','emprestimos','usuarios'
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
END $$;

-- ---------------------------------------------------------------------------
-- 5. Tabelas auxiliares - verifica RLS
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    t text;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'conversas_ia','mensagens_ia','alertas','notificacoes',
        'projeto_categorias'  -- já feito acima, mas garantimos
    ] LOOP
        EXECUTE format('ALTER TABLE finplan.%I ENABLE ROW LEVEL SECURITY', t);
        -- Policies já criadas acima ou simples
    END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- 6. Views não precisam de RLS (herdam das tabelas base)
--    Mas garantimos que as views usam security_invoker
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    v text;
BEGIN
    FOREACH v IN ARRAY ARRAY[
        'vw_contas','vw_categorias','vw_receitas','vw_despesas',
        'vw_transacoes','vw_metas','vw_projetos','vw_projeto_categorias',
        'vw_aportes','vw_planejamentos','vw_planejamentos_etapas',
        'vw_emprestimos','vw_parcelas','vw_relatorios','vw_meu_perfil'
    ] LOOP
        EXECUTE format('ALTER VIEW finplan.%I SET (security_invoker = true)', v);
    END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- 7. Teste de verificação - lista todas as policies criadas
-- ---------------------------------------------------------------------------
-- SELECT schemaname, tablename, policyname, cmd, qual, with_check
-- FROM pg_policies
-- WHERE schemaname = 'finplan'
-- ORDER BY tablename, policyname;

-- ============================================================
-- FIM DA MIGRATION 009
-- ============================================================