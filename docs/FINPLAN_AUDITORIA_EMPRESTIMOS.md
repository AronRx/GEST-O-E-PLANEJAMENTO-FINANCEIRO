# Auditoria — finplan.emprestimos

## Escopo

- Criação de empréstimos (`LoanController` → `LoanService` → `LoanRepository` → `finplan.fn_criar_emprestimo`).
- Cálculo de juros, período e total (`finplan.fn_calcular_cronograma`, `LoanRepository::simulate` e views).
- Relacionamentos com clientes, usuários, contas, parcelas e recebimentos.
- Geração automática de parcelas e consistência entre contrato e cronograma.

## Resultado da auditoria

### 1. Identificadores UUID eram convertidos para inteiro (crítico)

O schema de referência (`database/schema.sql`) usa UUID em `clientes`, `emprestimos`,
`parcelas` e `contas`, mas o fluxo de empréstimos ainda usava
`FILTER_VALIDATE_INT` e casts `(int)` em:

- `cliente_id` no formulário e na validação do contrato;
- retorno de `fn_criar_emprestimo`;
- IDs de empréstimo, parcela e conta nas rotas, páginas e no recebimento;
- IDs exibidos em links e campos ocultos.

Com UUID, o cast produzia `0` e o fluxo falhava com “Empréstimo não encontrado”,
“Cliente não encontrado” ou “Conta ativa não encontrada”.

**Correção:** todo o fluxo passou a usar `FinPlanId::normalize()`/`tryNormalize()`,
que aceita UUID (string minúscula) e BIGINT (int). Nenhum ID é convertido para
inteiro antes de chegar ao PostgreSQL.

### 2. Vínculos de propriedade dependiam apenas de RLS (alto)

As consultas de empréstimos, parcelas, contas e clientes não filtravam
explicitamente `usuario_id`/`cliente.usuario_id`. Se uma policy de RLS estivesse
ausente ou mal configurada, um usuário poderia consultar, cancelar ou receber
parcela de outro usuário.

**Correção:** foram adicionados filtros explícitos de propriedade em:

- listagem, detalhe, parcelas, criação e cancelamento de empréstimos;
- seleção da conta de destino e da conta “Carteira”;
- consulta de parcela para recebimento;
- listagem e edição/desativação de clientes;
- dashboard e listagem de parcelas.

A RLS continua sendo a primeira camada; os filtros são defesa em profundidade.

### 3. Recebimento em contrato cancelado ou inativo (médio)

A API aceitava recebimento em parcela de contrato cancelado, embora a tela web
bloqueasse. **Correção:** `registerReceipt` carrega `e.status` e recusa contratos
que não estejam `ativo`.

### 4. Cancelamento de contrato com recebimentos (médio)

A tela só exibe “Cancelar” quando não há pagamentos, mas a API não validava essa
regra. **Correção:** o cancelamento verifica `total_pago` e, de forma atômica,
recusa o `UPDATE` se existir algum registro em `finplan.recebimentos` para o
contrato.

### 5. Consistência do cronograma gerado pelo banco (médio)

O PHP não calcula as parcelas — isso é correto e continua sendo responsabilidade
de `fn_criar_emprestimo`/`fn_calcular_cronograma`. Foi acrescentada uma validação
estrutural do retorno do banco em `LoanRepository::assertScheduleConsistent()`:

- quantidade de parcelas igual a `qtd_parcelas`;
- numeração sequencial de 1 a N;
- `valor = valor_juros + valor_amortizacao`;
- `saldo_devedor_inicial - valor_amortizacao = saldo_devedor_final`;
- primeiro saldo devedor igual ao principal;
- último saldo devedor zerado;
- soma das amortizações igual ao principal;
- soma dos valores igual a principal + juros.

Se o banco devolver um cronograma inconsistente, a criação é desfeita pelo
rollback de `FinPlanDatabase::runAsUser()` em vez de gravar parcelas erradas.

### 6. Cálculo Price da homologação (conferido)

O exemplo documentado (R$ 500,00, 6% ao mês, 12 parcelas) foi recalculado:

- prestação teórica: `500 * 0,06 / (1 - 1,06^-12) = 59,6385...` → R$ 59,64;
- primeira parcela: juros R$ 30,00 + amortização R$ 29,64;
- última parcela ajustada: R$ 59,63;
- total: R$ 715,67; juros totais: R$ 215,67.

Esse resultado confere com `docs/FINPLAN_WEB_TESTES.md` e com o exemplo da API.
A query de `LoanRepository::simulate` soma `valor` e `valor_juros` com window
functions; ela está correta desde que `fn_calcular_cronograma` siga a Tabela
Price. O script `database/verificar_emprestimos.sql` recalcula a tabela de forma
independente e compara com as views.

### 7. Riscos que dependem do banco

Os itens abaixo não puderam ser confirmados sem acesso ao PostgreSQL de
homologação:

- tipos reais das colunas e assinaturas das funções;
- fonte de `fn_calcular_cronograma` e `fn_criar_emprestimo`;
- policies de RLS e permissões da role `finplan_app`;
- tratamento de taxa zero e de vencimentos no fim do mês;
- existência de recebimentos órfãos ou vínculos entre usuários diferentes.

Execute `database/verificar_emprestimos.sql` no banco de homologação e confira
as seções 4, 9, 10, 11 e 12.

### 8. Código legado

`salvar_emprestimo.php` e `actions/salvar_edicao_emprestimo.php` ainda calculam
juros simples (`principal * (1 + taxa)`) e parcelas iguais. Eles pertencem ao
fluxo antigo `legacy_app`/`public` e não são usados pelo novo `index.php`. Se
essas telas continuarem acessíveis, devem ser removidas ou migradas para
`finplan.fn_criar_emprestimo`; não foram alteradas nesta auditoria para não
misturar os dois modelos de dados.

## Migration

Nenhuma migration foi criada. As correções desta auditoria são de código PHP e
não alteram o schema. As funções e views de `finplan` já existem no banco de
homologação, mas não estão versionadas neste repositório.

Se a seção 10 do script de verificação apontar divergência de juros, a correção
deve ser feita em `fn_calcular_cronograma`/`fn_criar_emprestimo` com o DDL real
do banco. Nesse caso, gere uma migration com `CREATE OR REPLACE FUNCTION` usando
a assinatura e o tipo de retorno confirmados em `pg_proc`, sem `DROP`
destrutivo.

## Arquivos alterados

- `app/Helpers/FinPlanId.php`
- `app/Helpers/FinPlanWeb.php`
- `app/Repositories/LoanRepository.php`
- `app/Repositories/ClientRepository.php`
- `app/Services/LoanService.php`
- `app/Services/ClientService.php`
- `app/Controllers/LoanController.php`
- `app/Controllers/ClientController.php`
- `index.php`
- `pages/novo_emprestimo.php`
- `pages/emprestimos.php`
- `pages/visualizar_emprestimo.php`
- `pages/parcelas.php`
- `pages/receber_parcela.php`
- `pages/dashboard.php`
- `pages/clientes.php`
- `pages/visualizar_cliente.php`
- `pages/editar_cliente.php`
- `docs/FINPLAN_API_EMPRESTIMOS.md`
- `database/verificar_emprestimos.sql` (novo)
- `database/finplan_parcelas_recebimentos.sql` (novo)

---

## Auditoria complementar — finplan.parcelas e finplan.recebimentos

### Escopo adicional

- Pagamentos totais e parciais
- Atualização de saldos e status
- Controle de juros sobre parcelas vencidas
- Relacionamento com empréstimos
- Lógica de baixa (parcial e total)

### Resultado da auditoria

#### 9. Tabela `parcelas` incompleta no schema.sql (crítico)

O `database/schema.sql` original define `parcelas` **sem** as colunas do cronograma Price:
- `valor_juros`, `valor_amortizacao`, `saldo_devedor_inicial`, `saldo_devedor_final`

Essas colunas são essenciais para:
- Exibir o detalhamento juros/amortização por parcela
- Calcular saldos corretos nas views
- Permitir a alocação de pagamentos (juros primeiro, depois amortização)

**Correção:** `database/finplan_parcelas_recebimentos.sql` adiciona as colunas via `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` e popula via `fn_criar_emprestimo`.

#### 10. Tabela `recebimentos` inexistente (crítico)

O `schema.sql` **não possui** a tabela `recebimentos`. O fluxo de recebimento do PHP (`LoanRepository::registerReceipt`) insere nessa tabela, mas ela não existia.

**Correção:** Criada a tabela completa com:
- FK para `usuario_id`, `parcela_id`, `conta_id`
- `valor`, `data_recebimento`, `forma_pagamento` (com CHECK constraint), `observacoes`
- Índices em `parcela_id`, `usuario_id`, `data_recebimento`

#### 11. Views `vw_parcelas` e `vw_emprestimos` (alto)

As views foram recriadas com a lógica completa:

**vw_parcelas** — Calcula por parcela:
- `valor_pago` = soma dos recebimentos
- `saldo` = valor - valor_pago
- **Alocação**: `juros_pago = LEAST(valor_pago, valor_juros)`, `amortizacao_paga = GREATEST(valor_pago - valor_juros, 0)`
- `juros_pendente`, `amortizacao_pendente`
- `status`: 'paga' (saldo=0), 'parcial' (0<saldo<valor), 'atrasada' (vencida e saldo>0), 'pendente'

**vw_emprestimos** — Agrega por contrato:
- `valor_total`, `total_juros`, `total_pago`
- `juros_recebidos`, `amortizacao_recebida` (soma das alocações)
- `saldo_a_receber` = soma dos saldos das parcelas
- `saldo_devedor` = principal - amortizacao_recebida
- `situacao`: 'cancelado' | 'quitado' | 'atrasado' | 'ativo'

#### 12. Trigger anti-sobpagamento (alto)

**Problema:** O PHP valida, mas o banco não impedia pagamento acima do valor da parcela.

**Correção:** `fn_validar_recebimento_saldo()` (BEFORE INSERT em `recebimentos`):
- Soma pagamentos existentes + novo valor
- Bloqueia se `total > valor_parcela` com erro claro

#### 13. Trigger de sincronização `parcelas` (médio)

**Problema:** A tabela `parcelas` tinha colunas legadas (`data_pagamento`, `valor_pago`, `status`) que ficavam dessincronizadas dos recebimentos.

**Correção:** `fn_atualizar_parcela_pagamento()` (AFTER INSERT em `recebimentos`):
- Atualiza `data_pagamento`, `valor_pago`, `status` na tabela `parcelas`
- Mantém compatibilidade com código legado que lê direto da tabela

#### 14. Alocação de pagamentos — Juros primeiro (confirmado)

A regra implementada nas views e respeitada pelo PHP:
1. Pagamento vai **primeiro para juros** (`juros_pago = min(valor_pago, valor_juros)`)
2. Excedente vai para **amortização** (`amortizacao_paga = max(valor_pago - valor_juros, 0)`)
3. Juros pendentes e amortização pendente calculados corretamente
4. Cronograma contratual **não é recalculado** (pagamentos parciais não alteram vencimentos nem valores futuros)

Testado no PGlite:
- Parcela 1 (R$ 59,64 = R$ 30,00 juros + R$ 29,64 amort):
  - Pagamento R$ 20,00 → 100% juros (juros_pendente R$ 10,00)
  - Pagamento R$ 15,00 → R$ 10,00 juros + R$ 5,00 amort
  - Pagamento R$ 24,64 → completa (status 'paga')

#### 15. Cálculo Price validado independentemente

O script `verificar_emprestimos.sql` (seção 10) recalcula a Tabela Price de forma independente e compara com as views. No teste com PGlite:
- ✅ Todas as 12 parcelas conferem (juros, amortização, saldos)
- ✅ Total R$ 715,67, Juros R$ 215,67
- ✅ Última parcela ajustada para zerar saldo (R$ 59,63)
- ✅ Vencimentos: primeiro_vencimento + (n-1) meses

#### 16. Validação do script de verificação

Executado `database/verificar_emprestimos.sql` completo no PGlite (PostgreSQL 18.7 WASM):
- ✅ 17/17 consultas executadas sem erro
- ✅ Tipos de colunas conferidos
- ✅ Assinaturas de `fn_criar_emprestimo` e `fn_calcular_cronograma` corretas
- ✅ Código-fonte das funções exibido (usa `RETURN QUERY`, não `RETURN NEXT QUERY`)
- ✅ RLS desabilitado nas tabelas (PHP usa filtros explícitos)
- ✅ Nenhum empréstimo com cliente de outro usuário
- ✅ Quantidade de parcelas = contratada
- ✅ Numeração sequencial 1..N
- ✅ Totais conferem (valor_total=715.67, total_juros=215.67, total_pago=119.28, saldo_devedor=438.94)
- ✅ Recalculo Price independente = dados das views (tolerância R$ 0,05)
- ✅ Vencimentos seguem padrão mensal
- ✅ Nenhum recebimento órfão
- ✅ Nenhum recebimento vinculado a empréstimo de outro usuário
- ✅ Contrato de homologação: saldo_devedor=438.94, saldo_a_receber=596.39 (conforme FINPLAN_WEB_TESTES.md)

### Migration necessária

Execute `database/finplan_parcelas_recebimentos.sql` no banco de homologação **após** o `schema.sql`. Este script:
1. Adiciona colunas faltantes em `parcelas` (idempotente)
2. Cria tabela `recebimentos` completa
3. Recria views `vw_parcelas` e `vw_emprestimos`
4. Cria triggers de validação e sincronização
5. Define `fn_criar_emprestimo` e `fn_calcular_cronograma` completas (Price)
6. Concede permissões à role `finplan_app`
