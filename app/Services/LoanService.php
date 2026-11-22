<?php

class LoanService
{
    private LoanRepository $loanRepository;

    public function __construct()
    {
        $this->loanRepository = new LoanRepository();
    }

    public function paginate(int|string $userId, array $query): array
    {
        $search = trim((string) ($query['q'] ?? ''));
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        $perPage = filter_var($query['per_page'] ?? 20, FILTER_VALIDATE_INT);
        $page = $page === false || $page < 1 ? 1 : $page;
        $perPage = $perPage === false || $perPage < 1 ? 20 : min($perPage, 100);
        $situation = isset($query['situacao']) ? strtolower(trim((string) $query['situacao'])) : null;

        if ($situation !== null && $situation !== '' && !in_array($situation, ['ativo', 'atrasado', 'quitado', 'cancelado'], true)) {
            throw new InvalidArgumentException('Situação de empréstimo inválida.');
        }

        return $this->loanRepository->paginate(
            $userId,
            $search === '' ? null : $search,
            $situation === '' ? null : $situation,
            $page,
            $perPage
        );
    }

    public function find(int|string $userId, int|string $loanId): array
    {
        $loanId = $this->normalizeId($loanId);
        $loan = $this->loanRepository->findById($userId, $loanId);
        if ($loan === null) {
            throw new OutOfBoundsException('Empréstimo não encontrado.');
        }
        return $loan;
    }

    public function create(int|string $userId, array $input): array
    {
        $data = $this->normalizeContract($input, true);
        try {
            return $this->loanRepository->create($userId, $data);
        } catch (PDOException $exception) {
            $this->translateDatabaseException($exception);
            throw $exception;
        }
    }

    public function simulate(array $input): array
    {
        return $this->loanRepository->simulate($this->normalizeContract($input, false));
    }

    public function installments(int|string $userId, int|string $loanId): array
    {
        $loanId = $this->normalizeId($loanId);
        return $this->loanRepository->installments($userId, $loanId);
    }

    public function registerReceipt(int|string $userId, int|string $installmentId, array $input): array
    {
        $installmentId = $this->normalizeId($installmentId);
        $value = $this->decimal($input['valor'] ?? null, 'valor', 13, 2, true);
        $date = $this->date($input['data_recebimento'] ?? date('Y-m-d'), 'data_recebimento');
        $method = strtolower(trim((string) ($input['forma_pagamento'] ?? 'pix')));
        $methods = ['dinheiro', 'pix', 'transferencia', 'boleto', 'cartao', 'outro'];
        if (!in_array($method, $methods, true)) {
            throw new InvalidArgumentException('Forma de pagamento inválida.');
        }

        $accountId = $input['conta_id'] ?? null;
        if ($accountId !== null && $accountId !== '') {
            $accountId = FinPlanId::tryNormalize($accountId);
            if ($accountId === null) {
                throw new InvalidArgumentException('Identificador de conta inválido.');
            }
        } else {
            $accountId = null;
        }

        $notes = $this->nullableString($input['observacoes'] ?? null);
        if ($notes !== null && self::stringLength($notes) > 255) {
            throw new InvalidArgumentException('As observações devem possuir no máximo 255 caracteres.');
        }

        try {
            return $this->loanRepository->registerReceipt($userId, $installmentId, [
                'valor' => $value,
                'data_recebimento' => $date,
                'forma_pagamento' => $method,
                'conta_id' => $accountId,
                'observacoes' => $notes
            ]);
        } catch (PDOException $exception) {
            $this->translateDatabaseException($exception);
            throw $exception;
        }
    }

    public function cancel(int|string $userId, int|string $loanId): array
    {
        $loanId = $this->normalizeId($loanId);
        return $this->loanRepository->cancel($userId, $loanId);
    }

    private function normalizeContract(array $input, bool $requiresClient): array
    {
        $clientId = null;
        if ($requiresClient) {
            $clientId = FinPlanId::tryNormalize($input['cliente_id'] ?? null);
            if ($clientId === null) {
                throw new InvalidArgumentException('Informe um cliente válido.');
            }
        }

        $principal = $this->decimal($input['valor_principal'] ?? null, 'valor_principal', 13, 2, true);
        $rate = $this->decimal($input['taxa_juros_mensal'] ?? '0', 'taxa_juros_mensal', 3, 4, false);
        $installments = filter_var($input['qtd_parcelas'] ?? null, FILTER_VALIDATE_INT);
        if ($installments === false || $installments < 1 || $installments > 600) {
            throw new InvalidArgumentException('A quantidade de parcelas deve estar entre 1 e 600.');
        }

        $loanDate = $this->date($input['data_emprestimo'] ?? date('Y-m-d'), 'data_emprestimo');
        $firstDueDate = $this->date($input['primeiro_vencimento'] ?? null, 'primeiro_vencimento');
        if ($firstDueDate < $loanDate) {
            throw new InvalidArgumentException('O primeiro vencimento não pode ser anterior à data do empréstimo.');
        }

        $system = strtolower(trim((string) ($input['sistema_amortizacao'] ?? 'price')));
        if ($system !== 'price') {
            if ($system === 'sac') {
                throw new InvalidArgumentException('O sistema SAC ainda não está implementado. Utilize price.');
            }
            throw new InvalidArgumentException('Sistema de amortização inválido.');
        }

        $observations = $this->nullableString($input['observacoes'] ?? null);
        if ($observations !== null && self::stringLength($observations) > 5000) {
            throw new InvalidArgumentException('As observações devem possuir no máximo 5000 caracteres.');
        }

        return [
            'cliente_id' => $clientId,
            'valor_principal' => $principal,
            'taxa_juros_mensal' => $rate,
            'qtd_parcelas' => $installments,
            'data_emprestimo' => $loanDate,
            'primeiro_vencimento' => $firstDueDate,
            'sistema_amortizacao' => $system,
            'observacoes' => $observations
        ];
    }

    private function decimal(mixed $value, string $field, int $integerDigits, int $scale, bool $positive): string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException("O campo {$field} deve ser numérico.");
        }

        $value = trim((string) $value);
        $pattern = '/^\d{1,' . $integerDigits . '}(?:\.\d{1,' . $scale . '})?$/';
        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException("O campo {$field} possui formato ou precisão inválidos.");
        }

        $numeric = (float) $value;
        if (!is_finite($numeric) || ($positive ? $numeric <= 0 : $numeric < 0)) {
            throw new InvalidArgumentException($positive
                ? "O campo {$field} deve ser maior que zero."
                : "O campo {$field} não pode ser negativo.");
        }

        return $value;
    }

    private function date(mixed $value, string $field): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException("O campo {$field} deve usar o formato AAAA-MM-DD.");
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("O campo {$field} contém uma data inválida.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('As observações devem conter texto simples.');
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        $matched = preg_match_all('/./us', $value, $characters);
        return $matched === false ? strlen($value) : $matched;
    }

    private function normalizeId(int|string $id): int|string
    {
        return FinPlanId::normalize($id);
    }

    private function translateDatabaseException(PDOException $exception): void
    {
        $state = $exception->getCode();
        if ($state === '23503') {
            throw new DomainException('O cliente, parcela ou conta informada não existe ou não pertence ao usuário autenticado.');
        }
        if ($state === '23514' || $state === '22003' || $state === '22P02') {
            throw new InvalidArgumentException('Um ou mais valores informados são incompatíveis com as regras do banco.');
        }
        if ($state === 'P0001') {
            throw new DomainException('A operação foi recusada por uma regra financeira do banco: ' . $exception->getMessage());
        }
        if ($state === '42501') {
            throw new DomainException('A operação não está autorizada para este usuário.');
        }
    }
}
