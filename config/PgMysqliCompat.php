<?php

final class PgMysqliCompatResult
{
    public int $num_rows;
    private array $rows;
    private int $position = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc(): array|false
    {
        if (!array_key_exists($this->position, $this->rows)) {
            return false;
        }

        return $this->rows[$this->position++];
    }
}

final class PgMysqliCompatStatement
{
    private PDO $pdo;
    private PgMysqliCompat $connection;
    private string $sql;
    private ?PDOStatement $statement = null;
    private array $boundValues = [];

    public function __construct(PDO $pdo, PgMysqliCompat $connection, string $sql)
    {
        $this->pdo = $pdo;
        $this->connection = $connection;
        $this->sql = self::translateSql($sql);
    }

    public function bind_param(string $types, &...$variables): bool
    {
        $this->boundValues = [];

        foreach ($variables as &$variable) {
            $this->boundValues[] = $variable;
        }

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $sql = rtrim(trim($this->sql), "; \t\n\r\0\x0B");
        $isInsert = preg_match('/^INSERT\s+INTO\b/i', $sql) === 1;

        if ($isInsert && !preg_match('/\bRETURNING\b/i', $sql)) {
            $sql .= ' RETURNING id';
        }

        $this->statement = $this->pdo->prepare($sql);
        $values = $params ?? $this->boundValues;
        $this->statement->execute(array_values($values));

        if ($isInsert && $this->statement->columnCount() > 0) {
            $insertedId = $this->statement->fetchColumn();
            if ($insertedId !== false) {
                $this->connection->setInsertId((string) $insertedId);
            }
        }

        return true;
    }

    public function get_result(): PgMysqliCompatResult
    {
        if (!$this->statement) {
            throw new RuntimeException('Execute a consulta antes de obter os resultados.');
        }

        if ($this->statement->columnCount() === 0) {
            return new PgMysqliCompatResult([]);
        }

        return new PgMysqliCompatResult(
            $this->statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function close(): bool
    {
        $this->statement = null;
        return true;
    }

    private static function translateSql(string $sql): string
    {
        return preg_replace('/\bCURDATE\s*\(\s*\)/i', 'CURRENT_DATE', $sql) ?? $sql;
    }
}

final class PgMysqliCompat
{
    public mixed $insert_id = null;
    public int $errno = 0;
    public string $error = '';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function prepare(string $sql): PgMysqliCompatStatement
    {
        return new PgMysqliCompatStatement($this->pdo, $this, $sql);
    }

    public function query(string $sql): PgMysqliCompatResult|bool
    {
        $sql = preg_replace('/\bCURDATE\s*\(\s*\)/i', 'CURRENT_DATE', $sql) ?? $sql;
        $statement = $this->pdo->query($sql);

        if ($statement->columnCount() > 0) {
            return new PgMysqliCompatResult(
                $statement->fetchAll(PDO::FETCH_ASSOC)
            );
        }

        return true;
    }

    public function begin_transaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }

        return true;
    }

    public function setInsertId(string $id): void
    {
        $this->insert_id = $id;
    }
}
