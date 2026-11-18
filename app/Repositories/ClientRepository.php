<?php

class ClientRepository extends BaseRepository
{
    private const SELECT_COLUMNS = 'id, nome, cpf_cnpj, rg, telefone, whatsapp, email, cep, rua, numero, bairro, cidade, estado, endereco, observacoes, ativo, criado_em, atualizado_em';

    public function paginate(int $userId, ?string $search, int $page, int $perPage, bool $includeInactive = false): array
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($search, $page, $perPage, $includeInactive): array {
            $conditions = [];
            $parameters = [];

            if (!$includeInactive) {
                $conditions[] = 'ativo IS TRUE';
            }

            if ($search !== null && $search !== '') {
                $conditions[] = '(nome ILIKE :search_nome OR cpf_cnpj ILIKE :search_cpf OR telefone ILIKE :search_telefone OR whatsapp ILIKE :search_whatsapp OR email ILIKE :search_email)';
                $searchValue = '%' . $search . '%';
                $parameters = [
                    'search_nome' => $searchValue,
                    'search_cpf' => $searchValue,
                    'search_telefone' => $searchValue,
                    'search_whatsapp' => $searchValue,
                    'search_email' => $searchValue
                ];
            }

            $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

            $countStatement = $pdo->prepare('SELECT COUNT(*) FROM finplan.clientes' . $where);
            $countStatement->execute($parameters);
            $total = (int) $countStatement->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $sql = 'SELECT ' . self::SELECT_COLUMNS . '
                    FROM finplan.clientes' . $where . '
                    ORDER BY nome ASC, id ASC
                    LIMIT :limit OFFSET :offset';

            $statement = $pdo->prepare($sql);
            foreach ($parameters as $name => $value) {
                $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
            }
            $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->execute();

            return [
                'items' => $statement->fetchAll(),
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $total === 0 ? 0 : (int) ceil($total / $perPage)
                ]
            ];
        });
    }

    public function findById(int $userId, int $clientId): ?array
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($clientId): ?array {
            $statement = $pdo->prepare(
                'SELECT ' . self::SELECT_COLUMNS . '
                 FROM finplan.clientes
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $clientId]);
            $client = $statement->fetch();

            return $client ?: null;
        });
    }

    public function create(int $userId, array $data): array
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId, $data): array {
            $statement = $pdo->prepare(
                'INSERT INTO finplan.clientes (
                    usuario_id, nome, cpf_cnpj, rg, telefone, whatsapp, email,
                    cep, rua, numero, bairro, cidade, estado, endereco, observacoes
                 ) VALUES (
                    :usuario_id, :nome, :cpf_cnpj, :rg, :telefone, :whatsapp, :email,
                    :cep, :rua, :numero, :bairro, :cidade, :estado, :endereco, :observacoes
                 )
                 RETURNING ' . self::SELECT_COLUMNS
            );

            $statement->execute($this->parameters($userId, $data));
            $client = $statement->fetch();

            if (!$client) {
                throw new RuntimeException('O banco não retornou o cliente criado.');
            }

            return $client;
        });
    }

    public function update(int $userId, int $clientId, array $data): ?array
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId, $clientId, $data): ?array {
            $statement = $pdo->prepare(
                'UPDATE finplan.clientes SET
                    nome = :nome,
                    cpf_cnpj = :cpf_cnpj,
                    rg = :rg,
                    telefone = :telefone,
                    whatsapp = :whatsapp,
                    email = :email,
                    cep = :cep,
                    rua = :rua,
                    numero = :numero,
                    bairro = :bairro,
                    cidade = :cidade,
                    estado = :estado,
                    endereco = :endereco,
                    observacoes = :observacoes
                 WHERE id = :id
                 RETURNING ' . self::SELECT_COLUMNS
            );

            $parameters = $this->parameters($userId, $data);
            unset($parameters['usuario_id']);
            $parameters['id'] = $clientId;
            $statement->execute($parameters);
            $client = $statement->fetch();

            return $client ?: null;
        });
    }

    public function deactivate(int $userId, int $clientId): bool
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($clientId): bool {
            $statement = $pdo->prepare(
                'UPDATE finplan.clientes
                 SET ativo = FALSE
                 WHERE id = :id AND ativo IS TRUE'
            );
            $statement->execute(['id' => $clientId]);

            return $statement->rowCount() > 0;
        });
    }

    private function parameters(int $userId, array $data): array
    {
        return [
            'usuario_id' => $userId,
            'nome' => $data['nome'],
            'cpf_cnpj' => $data['cpf_cnpj'],
            'rg' => $data['rg'],
            'telefone' => $data['telefone'],
            'whatsapp' => $data['whatsapp'],
            'email' => $data['email'],
            'cep' => $data['cep'],
            'rua' => $data['rua'],
            'numero' => $data['numero'],
            'bairro' => $data['bairro'],
            'cidade' => $data['cidade'],
            'estado' => $data['estado'],
            'endereco' => $data['endereco'],
            'observacoes' => $data['observacoes']
        ];
    }
}
