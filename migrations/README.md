# Migrations - Ordem de Execução

Este diretório contém as migrations do schema `finplan`. Execute na ordem numérica abaixo.

---

## Ordem de Execução Obrigatória

```bash
# 1. Schema base (tabelas do schema.sql)
psql -h HOST -U USUARIO -d BANCO -f database/schema.sql

# 2. Parcelas e Recebimentos (views completas, triggers anti-sobpagamento)
psql -h HOST -U USUARIO -d BANCO -f database/finplan_parcelas_recebimentos.sql

# 3. Correção clientes (colunas + backfill legado)
psql -h HOST -U USUARIO -d BANCO -f migrations/005_fix_clientes.sql
psql -h HOST -U USUARIO -d BANCO -f migrations/006_backfill_clientes.sql

# 4. Correção usuarios (perfil + ultimo_login):
#    O banco usa BIGINT (identity). Execute:
psql -h HOST -U USUARIO -d BANCO -f migrations/007_fix_usuarios_bigint.sql

# 5. Módulos restantes (contas, views, triggers, RLS básico)
psql -h HOST -U USUARIO -d BANCO -f migrations/008_fix_remaining_modules.sql

# 6. RLS completo com JOINs para tabelas sem usuario_id direto
psql -h HOST -U USUARIO -d BANCO -f migrations/009_fix_rls.sql

# 7. Verificação (somente leitura)
psql -h HOST -U USUARIO -d BANCO -f database/verificar_emprestimos.sql
```

---

## Descrição de Cada Migration

### 005_fix_clientes.sql
**Propósito**: Adiciona colunas ausentes em `finplan.clientes` exigidas pelo PHP.
**Colunas**: `cpf_cnpj`, `rg`, `whatsapp`, `cep`, `rua`, `numero`, `bairro`, `cidade`, `estado`
**Idempotente**: Sim (`ADD COLUMN IF NOT EXISTS`)
**Dados**: Não altera/apaga dados existentes.

---

### 006_backfill_clientes.sql
**Propósito**: Preenche as colunas criadas no 005 a partir dos dados legados JSON gravados em `observacoes` (`__SF_OBS_V1__`) e `endereco` (`__SF_ADDR_V1__`).
**Regras**:
- `cpf_cnpj` ← `documento` (se nulo)
- `rg`/`whatsapp` ← JSON de `observacoes`
- `cep`/`rua`/`numero`/`bairro`/`cidade`/`estado` ← JSON de `endereco`
- Endereço legado sem JSON: preserva em `rua` e extrai CEP por regex
**Idempotente**: Sim (usa `COALESCE` só preenche nulos)
**Dependência**: Deve rodar **após** 005.

---

### 007_fix_usuarios_bigint.sql
**Propósito**: Adiciona colunas `perfil` e `ultimo_login` em `finplan.usuarios` exigidas pelo fluxo de autenticação.
**O banco usa BIGINT (identity)**. Execute apenas `007_fix_usuarios_bigint.sql`.
**Verificação prévia**:
```sql
SELECT data_type FROM information_schema.columns
WHERE table_schema = 'finplan' AND table_name = 'usuarios' AND column_name = 'id';
-- Deve retornar 'bigint'
```
**Idempotente**: Sim (usa `ADD COLUMN IF NOT EXISTS`)
**Dados**: Não altera/apaga dados; apenas adiciona colunas com defaults.

---

### 008_fix_remaining_modules.sql
**Propósito**: Cria toda a infraestrutura dos módulos financeiros restantes.
**Conteúdo**:
1. **Tabela `finplan.contas`** (faltava no schema base) — com `saldo_inicial`, `saldo_atual`, `tipo`, `cor`, `icone`
2. **Colunas em `parcelas`**: `valor_juros`, `valor_amortizacao`, `saldo_devedor_inicial`, `saldo_devedor_final` (idempotente)
3. **Tabela `finplan.recebimentos`** — FKs, índices, CHECK em `forma_pagamento`
4. **Colunas `atualizado_em`** nas tabelas que não tinham (`categorias`, `transacoes`, `projeto_categorias`, `aportes`, `planejamentos_etapas`, `mensagens_ia`, `notificacoes`)
5. **Colunas em `transacoes`**: `conta_id` (FK), `recorrente`
6. **Funções RLS**: `fn_current_usuario_id()`, `fn_iniciar_contexto()`
7. **Trigger genérico `fn_atualizar_timestamp()`** em 19 tabelas
8. **Views completas**: `vw_contas`, `vw_categorias`, `vw_receitas`, `vw_despesas`, `vw_transacoes`, `vw_metas`, `vw_projetos`, `vw_projeto_categorias`, `vw_aportes`, `vw_planejamentos`, `vw_planejamentos_etapas`, `vw_relatorios`, `vw_parcelas`, `vw_emprestimos`
9. **Triggers de negócio**:
   - `trg_atualizar_saldo_conta` (atualiza `saldo_atual` da conta em transações)
   - `trg_atualizar_valor_meta` (atualiza `valor_atual` da meta em aportes)
   - `trg_proteger_categoria` (bloqueia DELETE se houver lançamentos)
   - `trg_proteger_conta` (bloqueia DELETE se houver transações/recebimentos)
10. **RLS básico**: Habilita RLS em todas as tabelas; policies simples para tabelas com `usuario_id` direto; habilita RLS (sem policies) nas tabelas sem `usuario_id` direto (`projeto_categorias`, `aportes`, `planejamentos_etapas`)
11. **Permissões** para role `finplan_app` (condicional)

**⚠️ CONFLITO CONHECIDO**: As views `vw_parcelas` e `vw_emprestimos` são criadas aqui de forma simplificada. O arquivo `database/finplan_parcelas_recebimentos.sql` define versões completas com lógica de alocação "juros primeiro". **Execute `finplan_parcelas_recebimentos.sql` DEPOIS do 008** para sobrescrever com as versões corretas.

---

### 009_fix_rls.sql
**Propósito**: Implementa RLS completo com policies usando JOIN para tabelas sem `usuario_id` direto.
**Conteúdo**:
1. Recria `fn_current_usuario_id()` e `fn_iniciar_contexto()` (idempotente via `CREATE OR REPLACE`)
2. **Policies simples** (reforço) para tabelas com `usuario_id` direto: `contas`, `categorias`, `receitas`, `despesas`, `transacoes`, `metas`, `projetos`, `planejamentos`, `relatorios`, `aportes`, `clientes`, `emprestimos`, `usuarios`
3. **Policies com JOIN** para tabelas sem `usuario_id` direto:
   - `parcelas` → JOIN `emprestimos.usuario_id`
   - `recebimentos` → JOIN `parcelas` → `emprestimos.usuario_id`
   - `projeto_categorias` → JOIN `projetos.usuario_id`
   - `planejamentos_etapas` → JOIN `planejamentos.usuario_id`
4. `security_invoker = true` nas views
5. **Sobrescreve** as policies básicas criadas no 008 (usa `DROP POLICY IF EXISTS` + `CREATE POLICY`)

**Dependência**: Deve rodar **APÓS** o 008.

---

## Arquivos em `database/`

| Arquivo | Descrição | Quando executar |
|---------|-----------|-----------------|
| `schema.sql` | Schema base (tabelas, índices) | **1º** - Antes de tudo |
| `finplan_parcelas_recebimentos.sql` | Views completas `vw_parcelas`/`vw_emprestimos` + triggers anti-sobpagamento + `fn_criar_emprestimo`/`fn_calcular_cronograma` | **Após** 008 (sobrescreve views) |
| `verificar_emprestimos.sql` | 17 consultas de auditoria (tipos, functions, RLS, consistência, recálculo Price) | **Último** (somente leitura) |
| `postgresql_compat.sql` | Camada legada `legacy_app` (views/triggers de compatibilidade) | Não necessário para o fluxo novo |

---

## Conflitos Conhecidos e Resoluções

| Item | Conflito | Resolução |
|------|----------|-----------|
| Colunas `parcelas` (`valor_juros`, etc.) | 008 + `finplan_parcelas_recebimentos.sql` | Ambos usam `IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` → **OK** |
| Tabela `recebimentos` | 008 + `finplan_parcelas_recebimentos.sql` | Ambos usam `CREATE TABLE IF NOT EXISTS` → **OK** |
| Views `vw_parcelas` / `vw_emprestimos` | 008 (simples) vs `finplan_parcelas_recebimentos.sql` (completas) | **EXECUTAR `finplan_parcelas_recebimentos.sql` DEPOIS DO 008** |
| Triggers `fn_validar_recebimento_saldo` / `fn_atualizar_parcela_pagamento` | Só em `finplan_parcelas_recebimentos.sql` | 008 apenas comenta → **OK** |
| RLS policies básicas | 008 cria → 009 sobrescreve com JOINs | 009 usa `DROP POLICY IF EXISTS` → **OK** |
| `fn_current_usuario_id` / `fn_iniciar_contexto` | 008 cria → 009 recria | Ambos `CREATE OR REPLACE` → **OK** |
| `fn_atualizar_timestamp` + triggers | Só no 008 | 009 não toca → **OK** |

---

## Verificação Pós-Deploy

```sql
-- 1. Verificar colunas parcelas
SELECT column_name, data_type FROM information_schema.columns
WHERE table_schema = 'finplan' AND table_name = 'parcelas'
AND column_name IN ('valor_juros','valor_amortizacao','saldo_devedor_inicial','saldo_devedor_final');

-- 2. Verificar tabela recebimentos
SELECT to_regclass('finplan.recebimentos') AS tabela;

-- 3. Verificar views completas
SELECT viewname FROM pg_views WHERE schemaname = 'finplan' AND viewname IN ('vw_parcelas','vw_emprestimos');

-- 4. Verificar RLS
SELECT schemaname, tablename, rowsecurity FROM pg_tables WHERE schemaname = 'finplan';

-- 5. Verificar policies
SELECT schemaname, tablename, policyname, cmd FROM pg_policies WHERE schemaname = 'finplan' ORDER BY tablename, policyname;

-- 6. Rodar verificação completa
\i database/verificar_emprestimos.sql
```

---

## Referência: Schema Esperado (finplan)

### Tabelas Principais
- `usuarios` (id BIGINT GENERATED ALWAYS AS IDENTITY, perfil, ultimo_login)
- `clientes` (id, usuario_id, cpf_cnpj, rg, whatsapp, endereço estruturado...)
- `contas` (id, usuario_id, saldo_inicial, saldo_atual, tipo, cor, icone)
- `emprestimos` (id, usuario_id, cliente_id, valor_principal, taxa_juros_mensal, qtd_parcelas, sistema_amortizacao, datas, status)
- `parcelas` (id, emprestimo_id, numero_parcela, valor, **valor_juros**, **valor_amortizacao**, **saldo_devedor_inicial**, **saldo_devedor_final**, data_vencimento, ...)
- `recebimentos` (id, usuario_id, parcela_id, conta_id, valor, data_recebimento, forma_pagamento)
- `categorias` (id, usuario_id, nome, tipo, cor, icone, ativo, atualizado_em)
- `receitas` / `despesas` (id, usuario_id, categoria_id, valor, data, recorrente, atualizado_em)
- `transacoes` (id, usuario_id, categoria_id, conta_id, tipo, valor, data, origem_tipo, origem_id, recorrente, atualizado_em)
- `metas` (id, usuario_id, titulo, valor_alvo, valor_atual, data_limite, status, atualizado_em)
- `projetos` (id, usuario_id, nome, orcamento, status, datas, atualizado_em)
- `projeto_categorias` (id, projeto_id, nome, tipo)
- `aportes` (id, usuario_id, projeto_id, meta_id, valor, data_aporte)
- `planejamentos` (id, usuario_id, projeto_id, titulo, datas, status, atualizado_em)
- `planejamentos_etapas` (id, planejamento_id, titulo, ordem, status, datas)

### Views Principais
- `vw_parcelas` — com `juros_pago`, `amortizacao_paga`, `juros_pendente`, `amortizacao_pendente`, `data_quitacao`, `status` (regra: juros primeiro)
- `vw_emprestimos` — agregações: `valor_total`, `total_juros`, `total_pago`, `juros_recebidos`, `amortizacao_recebida`, `saldo_a_receber`, `saldo_devedor`, `situacao`
- `vw_contas` — `saldo_atual`, `total_creditos`, `total_debitos`
- `vw_categorias` — `total_receitas`, `total_despesas`
- `vw_metas` — `percentual_concluido`, `status_calculado`, `total_aportes`
- `vw_projetos` — `gasto_total`, `total_aportes`, `percentual_orcamento`
- `vw_transacoes`, `vw_receitas`, `vw_despesas`, `vw_aportes`, `vw_planejamentos`, `vw_planejamentos_etapas`, `vw_relatorios`

### Funções Críticas
- `fn_criar_emprestimo()` — cria empréstimo + parcelas com cronograma Price
- `fn_calcular_cronograma()` — simula cronograma Price (não persiste)
- `fn_iniciar_contexto(user_id)` — define `finplan.current_usuario_id` para RLS
- `fn_current_usuario_id()` — lê `finplan.current_usuario_id`
- `fn_atualizar_saldo_conta()` — trigger AFTER INSERT/UPDATE/DELETE em `transacoes`
- `fn_atualizar_valor_meta()` — trigger AFTER INSERT/UPDATE/DELETE em `aportes`
- `fn_validar_recebimento_saldo()` — trigger BEFORE INSERT em `recebimentos` (bloqueia sobpagamento)
- `fn_atualizar_parcela_pagamento()` — trigger AFTER INSERT em `recebimentos` (sincroniza `parcelas.data_pagamento`, `valor_pago`, `status`)
- `fn_proteger_categoria()` / `fn_proteger_conta()` — bloqueiam DELETE com FKs
- `fn_atualizar_timestamp()` — trigger BEFORE UPDATE em 19 tabelas (`atualizado_em`)

---

## Contato

Em caso de dúvidas sobre a ordem ou conflitos, consulte o arquivo `docs/FINPLAN_AUDITORIA_EMPRESTIMOS.md` para o relatório completo da auditoria.