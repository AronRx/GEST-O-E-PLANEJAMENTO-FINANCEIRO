-- 007_fix_usuarios_uuid.sql
-- Corrige as colunas de finplan.usuarios exigidas pelo fluxo de autenticação/perfil
-- quando finplan.usuarios.id é UUID.
--
-- >>> NÃO RODE ESTA MIGRATION SEM CONFIRMAR O TIPO DO ID. <<<
-- Execute SOMENTE UMA das duas versões (uuid OU bigint).
--
-- Confirme com:
--   SELECT data_type
--   FROM information_schema.columns
--   WHERE table_schema = 'finplan'
--     AND table_name = 'usuarios'
--     AND column_name = 'id';
--
--   'uuid'   -> rode esta migration (007_fix_usuarios_uuid.sql)
--   'bigint' -> rode migrations/007_fix_usuarios_bigint.sql
--
-- Esta migration NÃO altera o tipo do id, não apaga dados e é idempotente.
-- Ela apenas adiciona as colunas ausentes perfil e ultimo_login.
--
-- Compatibilidade com o PHP:
--   - Com id UUID, UserRepository/AuthService/AuthMiddleware/FinPlanWeb
--     normalizam o identificador como string via FinPlanId::normalize().
--   - O token e fn_iniciar_contexto(:user_id) recebem essa string UUID.
--
-- Referência: database/schema.sql (usuarios.id UUID).

BEGIN;

DO $migration$
DECLARE
    v_id_type text;
BEGIN
    SELECT c.data_type
      INTO v_id_type
      FROM information_schema.columns c
     WHERE c.table_schema = 'finplan'
       AND c.table_name = 'usuarios'
       AND c.column_name = 'id';

    IF v_id_type IS NULL THEN
        RAISE EXCEPTION
            'finplan.usuarios.id não foi encontrado. Confirme o schema/tabela antes de rodar esta migration.';
    END IF;

    IF v_id_type <> 'uuid' THEN
        RAISE EXCEPTION
            'Esta migration é exclusiva para finplan.usuarios.id UUID, mas o banco informa %. Rode migrations/007_fix_usuarios_bigint.sql.',
            v_id_type;
    END IF;
END;
$migration$;

ALTER TABLE finplan.usuarios
    ADD COLUMN IF NOT EXISTS perfil VARCHAR(20) NOT NULL DEFAULT 'usuario';

ALTER TABLE finplan.usuarios
    ADD COLUMN IF NOT EXISTS ultimo_login TIMESTAMPTZ;

COMMIT;

-- Verificação pós-migration:
-- SELECT column_name, data_type, is_nullable, column_default
-- FROM information_schema.columns
-- WHERE table_schema = 'finplan'
--   AND table_name = 'usuarios'
--   AND column_name IN ('id', 'perfil', 'ultimo_login')
-- ORDER BY column_name;

-- Auditoria de dados padrão (somente leitura; não altera nada):
-- SELECT to_regclass('finplan.usuarios')      AS usuarios,
--        to_regclass('finplan.vw_meu_perfil') AS perfil_view,
--        to_regclass('finplan.contas')        AS contas;
--
-- Contas "Carteira" ausentes por usuário:
-- SELECT u.id, u.nome, u.email
-- FROM finplan.usuarios u
-- WHERE NOT EXISTS (
--     SELECT 1
--     FROM finplan.contas c
--     WHERE c.usuario_id = u.id
--       AND c.nome = 'Carteira'
--       AND c.ativa IS TRUE
-- );
--
-- Observação: a criação de contas/categorias iniciais não é feita pelo PHP.
-- Ela deve ocorrer dentro de finplan.fn_criar_usuario(). O PHP apenas chama a
-- função no cadastro; LoanRepository depende de uma conta ativa "Carteira" para
-- receber parcelas quando conta_id não é informado.
