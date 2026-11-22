#!/usr/bin/env bash
# ============================================================
# run_migrations.sh - Executa migrations do schema finplan
# ============================================================
# Ordem de execução:
#   1. database/schema.sql
#   2. migrations/008_fix_remaining_modules.sql
#   3. database/finplan_parcelas_recebimentos.sql
#   4. migrations/009_fix_rls.sql
#   5. database/verificar_emprestimos.sql
#
# Requer: .env com FINPLAN_DB_HOST, FINPLAN_DB_PORT, FINPLAN_DB_NAME,
#         FINPLAN_DB_USER, FINPLAN_DB_PASS
# ============================================================

set -euo pipefail

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Diretório do script (raiz do projeto)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Carrega variáveis do .env
if [[ -f ".env" ]]; then
    # shellcheck source=/dev/null
    source .env
else
    echo -e "${RED}ERRO: Arquivo .env não encontrado em $SCRIPT_DIR${NC}"
    exit 1
fi

# Valida variáveis obrigatórias
REQUIRED_VARS=(
    "FINPLAN_DB_HOST"
    "FINPLAN_DB_PORT"
    "FINPLAN_DB_NAME"
    "FINPLAN_DB_USER"
    "FINPLAN_DB_PASS"
)

for var in "${REQUIRED_VARS[@]}"; do
    if [[ -z "${!var:-}" ]]; then
        echo -e "${RED}ERRO: Variável $var não definida no .env${NC}"
        exit 1
    fi
done

# Exporta PGPASSWORD para psql não pedir senha
export PGPASSWORD="$FINPLAN_DB_PASS"

# Array com os arquivos de migration em ordem
MIGRATIONS=(
    "database/schema.sql"
    "migrations/008_fix_remaining_modules.sql"
    "database/finplan_parcelas_recebimentos.sql"
    "migrations/009_fix_rls.sql"
    "database/verificar_emprestimos.sql"
)

echo -e "${BLUE}============================================================${NC}"
echo -e "${BLUE}  Iniciando execução das migrations do schema finplan${NC}"
echo -e "${BLUE}============================================================${NC}"
echo ""
echo -e "Host:     ${YELLOW}$FINPLAN_DB_HOST${NC}"
echo -e "Porta:    ${YELLOW}$FINPLAN_DB_PORT${NC}"
echo -e "Banco:    ${YELLOW}$FINPLAN_DB_NAME${NC}"
echo -e "Usuário:  ${YELLOW}$FINPLAN_DB_USER${NC}"
echo ""

# Função para executar um arquivo SQL
run_migration() {
    local file="$1"
    local step="$2"
    local total="$3"

    if [[ ! -f "$file" ]]; then
        echo -e "${RED}  ✗ Arquivo não encontrado: $file${NC}"
        return 1
    fi

    echo -e "${BLUE}[$step/$total] Executando: ${YELLOW}$file${NC}"

    # Executa psql com ON_ERROR_STOP=1
    if psql \
        -h "$FINPLAN_DB_HOST" \
        -p "$FINPLAN_DB_PORT" \
        -d "$FINPLAN_DB_NAME" \
        -U "$FINPLAN_DB_USER" \
        -v ON_ERROR_STOP=1 \
        -f "$file" \
        -q 2>&1; then
        echo -e "${GREEN}  ✓ Concluído: $file${NC}"
        echo ""
        return 0
    else
        echo -e "${RED}  ✗ FALHA ao executar: $file${NC}"
        echo ""
        return 1
    fi
}

# Executa todas as migrations
TOTAL=${#MIGRATIONS[@]}
STEP=0
FAILED=0

for file in "${MIGRATIONS[@]}"; do
    STEP=$((STEP + 1))
    if ! run_migration "$file" "$STEP" "$TOTAL"; then
        FAILED=1
        break
    fi
done

# Resultado final
echo -e "${BLUE}============================================================${NC}"
if [[ $FAILED -eq 0 ]]; then
    echo -e "${GREEN}  ✓ TODAS AS MIGRATIONS EXECUTADAS COM SUCESSO${NC}"
else
    echo -e "${RED}  ✗ EXECUÇÃO INTERROMPIDA DEVIDO A ERRO${NC}"
fi
echo -e "${BLUE}============================================================${NC}"

# Limpa PGPASSWORD
unset PGPASSWORD

exit $FAILED