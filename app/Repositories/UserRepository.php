<?php

class UserRepository extends BaseRepository
{
    public function findByEmail(string $email): ?array
    {
        $statement = $this->database()->prepare(
            'SELECT id, nome, senha_hash, perfil, ativo FROM finplan.fn_autenticar(:email)'
        );

        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function findById(int|string $id): ?array
    {
        return FinPlanDatabase::runAsUser($id, function (PDO $pdo): ?array {
            $statement = $pdo->query(
                'SELECT id, nome, email, perfil, ativo, ultimo_login, criado_em
                 FROM finplan.vw_meu_perfil
                 LIMIT 1'
            );

            $user = $statement->fetch();

            return $user ?: null;
        });
    }

    public function create(array $data): int|string
    {
        $statement = $this->database()->prepare(
            'SELECT finplan.fn_criar_usuario(:nome, :email, :senha_hash) AS id'
        );

        $statement->execute([
            'nome' => $data['nome'],
            'email' => strtolower(trim($data['email'])),
            'senha_hash' => $data['senha_hash']
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException('O banco não retornou o identificador do usuário criado.');
        }

        return FinPlanId::normalize($id);
    }

    public function registerLogin(int|string $id): void
    {
        $id = FinPlanId::normalize($id);

        FinPlanDatabase::runAsUser($id, function (PDO $pdo) use ($id): void {
            $statement = $pdo->prepare('SELECT finplan.fn_registrar_login(:user_id)');
            $statement->execute(['user_id' => $id]);
        });
    }
}
