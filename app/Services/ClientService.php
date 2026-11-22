<?php

class ClientService
{
    private ClientRepository $clientRepository;

    public function __construct()
    {
        $this->clientRepository = new ClientRepository();
    }

    public function paginate(int|string $userId, array $query): array
    {
        $search = trim((string) ($query['q'] ?? ''));
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        $perPage = filter_var($query['per_page'] ?? 20, FILTER_VALIDATE_INT);

        $page = $page === false || $page < 1 ? 1 : $page;
        $perPage = $perPage === false || $perPage < 1 ? 20 : min($perPage, 100);
        $includeInactive = in_array(strtolower((string) ($query['incluir_inativos'] ?? 'false')), ['1', 'true', 'sim'], true);

        return $this->clientRepository->paginate(
            $userId,
            $search === '' ? null : $search,
            $page,
            $perPage,
            $includeInactive
        );
    }

    public function find(int|string $userId, int|string $clientId): array
    {
        $clientId = $this->normalizeId($clientId);
        $client = $this->clientRepository->findById($userId, $clientId);

        if ($client === null) {
            throw new OutOfBoundsException('Cliente não encontrado.');
        }

        return $client;
    }

    public function create(int|string $userId, array $input): array
    {
        $data = $this->normalizeAndValidate($input);

        try {
            return $this->clientRepository->create($userId, $data);
        } catch (PDOException $exception) {
            $this->translateDatabaseException($exception);
            throw $exception;
        }
    }

    public function update(int|string $userId, int|string $clientId, array $input): array
    {
        $clientId = $this->normalizeId($clientId);
        $existing = $this->clientRepository->findById($userId, $clientId);

        if ($existing === null) {
            throw new OutOfBoundsException('Cliente não encontrado.');
        }

        $merged = array_merge($existing, $input);
        if (array_key_exists('cpf', $input) && !array_key_exists('cpf_cnpj', $input)) {
            $merged['cpf_cnpj'] = $input['cpf'];
        }

        $addressFields = ['cep', 'rua', 'numero', 'bairro', 'cidade', 'estado'];
        $addressWasSubmitted = count(array_intersect($addressFields, array_keys($input))) > 0;
        $hasAddressValue = false;
        foreach ($addressFields as $field) {
            if (isset($merged[$field]) && trim((string) $merged[$field]) !== '') {
                $hasAddressValue = true;
                break;
            }
        }
        if ($addressWasSubmitted && !$hasAddressValue && !array_key_exists('endereco', $input)) {
            $merged['endereco'] = null;
        }

        $data = $this->normalizeAndValidate($merged);

        try {
            $updated = $this->clientRepository->update($userId, $clientId, $data);
        } catch (PDOException $exception) {
            $this->translateDatabaseException($exception);
            throw $exception;
        }

        if ($updated === null) {
            throw new OutOfBoundsException('Cliente não encontrado.');
        }

        return $updated;
    }

    public function deactivate(int|string $userId, int|string $clientId): array
    {
        $clientId = $this->normalizeId($clientId);

        if (!$this->clientRepository->deactivate($userId, $clientId)) {
            throw new OutOfBoundsException('Cliente não encontrado ou já está inativo.');
        }

        return [
            'id' => $clientId,
            'ativo' => false,
            'message' => 'Cliente desativado com sucesso. Os registros financeiros vinculados foram preservados.'
        ];
    }

    private function normalizeAndValidate(array $input): array
    {
        $cpf = $input['cpf_cnpj'] ?? $input['cpf'] ?? null;
        $data = [
            'nome' => $this->nullableString($input['nome'] ?? null),
            'cpf_cnpj' => $this->nullableString($cpf),
            'rg' => $this->nullableString($input['rg'] ?? null),
            'telefone' => $this->nullableString($input['telefone'] ?? null),
            'whatsapp' => $this->nullableString($input['whatsapp'] ?? null),
            'email' => $this->nullableString($input['email'] ?? null),
            'cep' => $this->nullableString($input['cep'] ?? null),
            'rua' => $this->nullableString($input['rua'] ?? null),
            'numero' => $this->nullableString($input['numero'] ?? null),
            'bairro' => $this->nullableString($input['bairro'] ?? null),
            'cidade' => $this->nullableString($input['cidade'] ?? null),
            'estado' => $this->nullableString($input['estado'] ?? null),
            'observacoes' => $this->nullableString($input['observacoes'] ?? null)
        ];

        if ($data['nome'] === null) {
            throw new InvalidArgumentException('O nome do cliente é obrigatório.');
        }

        $limits = [
            'nome' => 120,
            'cpf_cnpj' => 18,
            'rg' => 30,
            'telefone' => 20,
            'whatsapp' => 20,
            'email' => 160,
            'cep' => 10,
            'rua' => 180,
            'numero' => 20,
            'bairro' => 100,
            'cidade' => 100,
            'estado' => 2,
            'observacoes' => 5000
        ];

        foreach ($limits as $field => $limit) {
            if ($data[$field] !== null && self::stringLength($data[$field]) > $limit) {
                throw new InvalidArgumentException("O campo {$field} excede o limite de {$limit} caracteres.");
            }
        }

        if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Informe um e-mail válido para o cliente.');
        }

        if ($data['estado'] !== null) {
            $data['estado'] = strtoupper($data['estado']);
            if (!preg_match('/^[A-Z]{2}$/', $data['estado'])) {
                throw new InvalidArgumentException('O estado deve conter a sigla com duas letras.');
            }
        }

        $enderecoParts = [];
        $ruaNumero = trim(implode(', ', array_filter([$data['rua'], $data['numero']], static fn ($value): bool => $value !== null && $value !== '')));
        if ($ruaNumero !== '') {
            $enderecoParts[] = $ruaNumero;
        }
        foreach (['bairro', 'cidade', 'estado'] as $field) {
            if ($data[$field] !== null && $data[$field] !== '') {
                $enderecoParts[] = $data[$field];
            }
        }
        if ($data['cep'] !== null && $data['cep'] !== '') {
            $enderecoParts[] = 'CEP ' . $data['cep'];
        }

        $data['endereco'] = $enderecoParts === []
            ? $this->nullableString($input['endereco'] ?? null)
            : implode(', ', $enderecoParts);

        if ($data['endereco'] !== null && self::stringLength($data['endereco']) > 255) {
            throw new InvalidArgumentException('O endereço completo excede o limite de 255 caracteres.');
        }

        return $data;
    }

    private static function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        $matched = preg_match_all('/./us', $value, $characters);
        return $matched === false ? strlen($value) : $matched;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Todos os campos do cliente devem conter valores simples.');
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeId(int|string $clientId): int|string
    {
        return FinPlanId::normalize($clientId);
    }

    private function translateDatabaseException(PDOException $exception): void
    {
        if ($exception->getCode() === '23505') {
            throw new DomainException('Já existe um cliente com este CPF/CNPJ na sua conta.');
        }

        if ($exception->getCode() === '23503') {
            throw new DomainException('Não foi possível concluir a operação devido a um vínculo financeiro existente.');
        }
    }
}
