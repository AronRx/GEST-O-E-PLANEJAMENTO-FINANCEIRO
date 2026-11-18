<?php

class HealthRepository extends BaseRepository
{
    public function check(): array
    {
        try {
            $database = $this->database();

            $statement = $database->query('SELECT 1');

            $result = $statement->fetchColumn();

            if ((int) $result !== 1) {
                return [
                    'database' => 'error'
                ];
            }

            return [
                'database' => 'online'
            ];
        } catch (Throwable $exception) {
            error_log(
                '[SistemaFinanceiro] Falha na verificação do banco de dados: ' .
                $exception->getMessage()
            );

            return [
                'database' => 'offline'
            ];
        }
    }
}
