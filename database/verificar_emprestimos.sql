-- verificar_emprestimos.sql
-- Auditoria somente leitura do módulo de empréstimos do schema finplan.
--
-- Execute com um usuário que possa ler information_schema, pg_catalog e as
-- tabelas/views de finplan. Nenhuma consulta abaixo altera dados.
--
--   psql -h HOST -U USUARIO -d BANCO -f database/verificar_emprestimos.sql
--
-- As seções marcadas com "AJUSTE" dependem do nome real de alguma coluna.
-- Se o banco usar nomes diferentes dos esperados pelo PHP, a consulta falha
-- com "column does not exist"; isso também é um resultado da auditoria.

-- ---------------------------------------------------------------------------
-- 1. Tipos das colunas usadas pelo fluxo de empréstimos
-- ---------------------------------------------------------------------------
SELECT table_name,
       ordinal_position,
       column_name,
       data_type,
       udt_name,
       numeric_precision,
       numeric_scale,
       is_nullable,
       column_default
FROM information_schema.columns
WHERE table_schema = 'finplan'
  AND table_name IN ('clientes', 'emprestimos', 'parcelas', 'recebimentos', 'contas')
ORDER BY table_name, ordinal_position;

-- ---------------------------------------------------------------------------
-- 2. Colunas expostas pelas views consumidas pelo PHP
-- ---------------------------------------------------------------------------
SELECT table_name,
       ordinal_position,
       column_name,
       data_type,
       udt_name
FROM information_schema.columns
WHERE table_schema = 'finplan'
  AND table_name IN ('vw_emprestimos', 'vw_parcelas')
ORDER BY table_name, ordinal_position;

-- ---------------------------------------------------------------------------
-- 3. Assinaturas das funções do fluxo
-- ---------------------------------------------------------------------------
SELECT p.proname,
       pg_get_function_identity_arguments(p.oid) AS argumentos,
       pg_get_function_result(p.oid) AS retorno,
       p.provolatile AS volatilidade,
       p.prosecdef AS security_definer
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'finplan'
  AND p.proname IN (
      'fn_criar_emprestimo',
      'fn_calcular_cronograma',
      'fn_iniciar_contexto',
      'fn_autenticar',
      'fn_criar_usuario'
  )
ORDER BY p.proname;

-- ---------------------------------------------------------------------------
-- 4. Código-fonte das funções de empréstimos
--    Confira manualmente: taxa em percentual, Tabela Price, arredondamento
--    da última parcela, vencimentos mensais e vínculo com o usuário.
-- ---------------------------------------------------------------------------
SELECT p.proname,
       pg_get_functiondef(p.oid) AS definicao
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'finplan'
  AND p.proname IN ('fn_criar_emprestimo', 'fn_calcular_cronograma')
ORDER BY p.proname;

-- ---------------------------------------------------------------------------
-- 5. RLS e políticas das tabelas do módulo
-- ---------------------------------------------------------------------------
SELECT c.relname,
       c.relrowsecurity AS rls_habilitado,
       c.relforcerowsecurity AS rls_forcado
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'finplan'
  AND c.relname IN ('clientes', 'emprestimos', 'parcelas', 'recebimentos', 'contas')
ORDER BY c.relname;

SELECT schemaname,
       tablename,
       policyname,
       permissive,
       roles,
       cmd,
       qual,
       with_check
FROM pg_policies
WHERE schemaname = 'finplan'
ORDER BY tablename, policyname;

-- ---------------------------------------------------------------------------
-- 6. Empréstimo vinculado a cliente de outro usuário
-- ---------------------------------------------------------------------------
SELECT e.id AS emprestimo_id,
       e.usuario_id AS usuario_emprestimo,
       c.usuario_id AS usuario_cliente
FROM finplan.emprestimos e
JOIN finplan.clientes c ON c.id = e.cliente_id
WHERE e.usuario_id <> c.usuario_id
ORDER BY e.id;

-- ---------------------------------------------------------------------------
-- 7. Quantidade de parcelas divergente do contrato
-- ---------------------------------------------------------------------------
SELECT e.id AS emprestimo_id,
       e.qtd_parcelas AS parcelas_contratadas,
       COUNT(p.id) AS parcelas_geradas
FROM finplan.vw_emprestimos e
LEFT JOIN finplan.vw_parcelas p ON p.emprestimo_id = e.id
GROUP BY e.id, e.qtd_parcelas
HAVING COUNT(p.id) <> e.qtd_parcelas
ORDER BY e.id;

-- ---------------------------------------------------------------------------
-- 8. Numeração das parcelas fora de 1..N ou duplicada
-- ---------------------------------------------------------------------------
SELECT emprestimo_id,
       MIN(numero) AS primeiro_numero,
       MAX(numero) AS ultimo_numero,
       COUNT(*) AS total,
       COUNT(DISTINCT numero) AS numeros_distintos
FROM finplan.vw_parcelas
GROUP BY emprestimo_id
HAVING MIN(numero) <> 1
    OR MAX(numero) <> COUNT(*)
    OR COUNT(DISTINCT numero) <> COUNT(*)
ORDER BY emprestimo_id;

-- ---------------------------------------------------------------------------
-- 9. Totais do contrato divergentes da soma das parcelas
-- ---------------------------------------------------------------------------
SELECT e.id AS emprestimo_id,
       e.valor_principal,
       COUNT(p.id) AS parcelas,
       SUM(p.valor) AS soma_valor,
       e.valor_total,
       SUM(p.valor_juros) AS soma_juros,
       e.total_juros,
       SUM(p.valor_amortizacao) AS soma_amortizacao,
       MAX(p.saldo_devedor_final) FILTER (WHERE p.numero = e.qtd_parcelas) AS saldo_final
FROM finplan.vw_emprestimos e
JOIN finplan.vw_parcelas p ON p.emprestimo_id = e.id
WHERE e.status <> 'cancelado'
GROUP BY e.id, e.valor_principal, e.valor_total, e.total_juros, e.qtd_parcelas
HAVING ABS(COALESCE(SUM(p.valor_amortizacao), 0) - e.valor_principal) > 0.05
    OR ABS(COALESCE(SUM(p.valor), 0) - e.valor_total) > 0.05
    OR ABS(COALESCE(SUM(p.valor_juros), 0) - e.total_juros) > 0.05
    OR ABS(COALESCE(MAX(p.saldo_devedor_final) FILTER (WHERE p.numero = e.qtd_parcelas), 0)) > 0.02
ORDER BY e.id;

-- ---------------------------------------------------------------------------
-- 10. Recalculo independente da Tabela Price e comparação com o banco
--     Tolerância de R$ 0,05 por parcela para acomodar arredondamentos.
-- ---------------------------------------------------------------------------
WITH RECURSIVE base AS (
    SELECT e.id AS emprestimo_id,
           e.valor_principal,
           (e.taxa_juros_mensal / 100.0) AS taxa,
           e.qtd_parcelas,
           CASE
               WHEN e.taxa_juros_mensal = 0 THEN e.valor_principal / e.qtd_parcelas
               ELSE e.valor_principal * (e.taxa_juros_mensal / 100.0)
                    / (1 - POWER(1 + (e.taxa_juros_mensal / 100.0), -e.qtd_parcelas))
           END AS prestacao
    FROM finplan.vw_emprestimos e
    WHERE e.status <> 'cancelado'
      AND e.qtd_parcelas > 0
),
schedule AS (
    SELECT b.emprestimo_id,
           1 AS numero,
           b.valor_principal::numeric AS saldo_inicial,
           ROUND(b.valor_principal * b.taxa, 2) AS juros,
           CASE
               WHEN b.qtd_parcelas = 1 THEN b.valor_principal
               ELSE ROUND(b.prestacao - ROUND(b.valor_principal * b.taxa, 2), 2)
           END AS amortizacao,
           CASE
               WHEN b.qtd_parcelas = 1 THEN 0
               ELSE b.valor_principal - ROUND(b.prestacao - ROUND(b.valor_principal * b.taxa, 2), 2)
           END AS saldo_final,
           b.qtd_parcelas,
           b.taxa,
           b.prestacao
    FROM base b
    UNION ALL
    SELECT s.emprestimo_id,
           s.numero + 1,
           s.saldo_final,
           ROUND(s.saldo_final * s.taxa, 2),
           CASE
               WHEN s.numero + 1 = s.qtd_parcelas THEN s.saldo_final
               ELSE ROUND(s.prestacao - ROUND(s.saldo_final * s.taxa, 2), 2)
           END,
           CASE
               WHEN s.numero + 1 = s.qtd_parcelas THEN 0
               ELSE s.saldo_final - ROUND(s.prestacao - ROUND(s.saldo_final * s.taxa, 2), 2)
           END,
           s.qtd_parcelas,
           s.taxa,
           s.prestacao
    FROM schedule s
    WHERE s.numero < s.qtd_parcelas
)
SELECT s.emprestimo_id,
       s.numero,
       s.juros AS juros_esperado,
       p.valor_juros AS juros_atual,
       s.amortizacao AS amortizacao_esperada,
       p.valor_amortizacao AS amortizacao_atual,
       s.saldo_final AS saldo_esperado,
       p.saldo_devedor_final AS saldo_atual
FROM schedule s
JOIN finplan.vw_parcelas p
  ON p.emprestimo_id = s.emprestimo_id
 AND p.numero = s.numero
WHERE ABS(s.juros - p.valor_juros) > 0.05
   OR ABS(s.amortizacao - p.valor_amortizacao) > 0.05
   OR ABS(s.saldo_final - p.saldo_devedor_final) > 0.05
ORDER BY s.emprestimo_id, s.numero;

-- ---------------------------------------------------------------------------
-- 11. Vencimentos fora do padrão "primeiro vencimento + (n-1) meses"
--     Esta consulta detecta o desvio clássico de somar 1 mês sobre a data
--     anterior (31/01 -> 28/02 -> 28/03 em vez de 31/03).
-- ---------------------------------------------------------------------------
SELECT e.id AS emprestimo_id,
       p.numero,
       p.vencimento,
       (e.primeiro_vencimento + ((p.numero - 1) * INTERVAL '1 month'))::date AS vencimento_esperado
FROM finplan.vw_emprestimos e
JOIN finplan.vw_parcelas p ON p.emprestimo_id = e.id
WHERE p.vencimento <> (e.primeiro_vencimento + ((p.numero - 1) * INTERVAL '1 month'))::date
ORDER BY e.id, p.numero;

-- ---------------------------------------------------------------------------
-- 12. Recebimentos órfãos ou vinculados a empréstimo de outro usuário
-- ---------------------------------------------------------------------------
SELECT r.id AS recebimento_id,
       r.parcela_id
FROM finplan.recebimentos r
LEFT JOIN finplan.parcelas p ON p.id = r.parcela_id
WHERE p.id IS NULL
ORDER BY r.id;

SELECT r.id AS recebimento_id,
       r.usuario_id AS usuario_recebimento,
       e.usuario_id AS usuario_emprestimo
FROM finplan.recebimentos r
JOIN finplan.parcelas p ON p.id = r.parcela_id
JOIN finplan.emprestimos e ON e.id = p.emprestimo_id
WHERE r.usuario_id <> e.usuario_id
ORDER BY r.id;

-- ---------------------------------------------------------------------------
-- 13. Usuários sem conta ativa "Carteira" (fallback do recebimento)
-- ---------------------------------------------------------------------------
SELECT u.id, u.nome, u.email
FROM finplan.usuarios u
WHERE NOT EXISTS (
    SELECT 1
    FROM finplan.contas c
    WHERE c.usuario_id = u.id
      AND c.nome = 'Carteira'
      AND c.ativa IS TRUE
)
ORDER BY u.nome;

-- ---------------------------------------------------------------------------
-- 14. Permissão de execução das funções para a role da aplicação
-- ---------------------------------------------------------------------------
SELECT p.proname,
       CASE
           WHEN to_regrole('finplan_app') IS NULL THEN NULL
           ELSE has_function_privilege('finplan_app', p.oid, 'EXECUTE')
       END AS finplan_app_pode_executar
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'finplan'
  AND p.proname IN ('fn_criar_emprestimo', 'fn_calcular_cronograma')
ORDER BY p.proname;

-- ---------------------------------------------------------------------------
-- 15. Amostra dos contratos para conferência manual
-- ---------------------------------------------------------------------------
SELECT e.id,
       e.cliente_id,
       e.valor_principal,
       e.taxa_juros_mensal,
       e.qtd_parcelas,
       e.data_emprestimo,
       e.primeiro_vencimento,
       e.valor_total,
       e.total_juros,
       e.total_pago,
       e.saldo_devedor,
       e.status,
       e.situacao
FROM finplan.vw_emprestimos e
ORDER BY e.criado_em DESC
LIMIT 20;
