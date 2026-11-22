-- 005_fix_clientes.sql
-- Corrige o schema de finplan.clientes para o backend PHP (API e telas web).
--
-- Referência de schema: database/schema.sql (tabela clientes).
-- Referência de uso: app/Repositories/ClientRepository.php (SELECT, INSERT, UPDATE
-- e desativação lógica), app/Repositories/LoanRepository.php, app/Helpers/FinPlanWeb.php
-- e pages/parcelas.php.
--
-- Colunas ausentes identificadas na auditoria:
--   cpf_cnpj, rg, whatsapp, cep, rua, numero, bairro, cidade, estado
--
-- A migration é idempotente e somente adiciona colunas.
-- Não executa DROP, DELETE, TRUNCATE nem altera/apaga dados existentes.

BEGIN;

ALTER TABLE finplan.clientes
    ADD COLUMN IF NOT EXISTS cpf_cnpj VARCHAR(18);

ALTER TABLE finplan.clientes
    ADD COLUMN IF NOT EXISTS rg VARCHAR(30);

ALTER TABLE finplan.clientes
    ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20);

ALTER TABLE finplan.clientes
    ADD COLUMN IF NOT EXISTS cep VARCHAR(10);

ALTER TABLE finplan.clientes
    ADD COLUMN IF NOT EXISTS rua VARCHAR(180);

ALTER TABLE finplan.clientes
    ADD COLUMN IF NOT EXISTS numero VARCHAR(20);

ALTER TABLE finplan.clientes
    ADD COLUMN IF NOT EXISTS bairro VARCHAR(100);

ALTER TABLE finplan.clientes
    ADD COLUMN IF NOT EXISTS cidade VARCHAR(100);

ALTER TABLE finplan.clientes
    ADD COLUMN IF NOT EXISTS estado VARCHAR(2);

COMMIT;

-- Verificação pós-migration:
-- SELECT column_name, data_type, character_maximum_length
-- FROM information_schema.columns
-- WHERE table_schema = 'finplan'
--   AND table_name = 'clientes'
-- ORDER BY ordinal_position;
