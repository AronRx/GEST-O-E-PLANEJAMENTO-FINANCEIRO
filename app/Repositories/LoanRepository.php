<?php

class LoanRepository extends BaseRepository
{
    public function paginate(int $userId, ?string $search, ?string $situation, int $page, int $perPage): array
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($search, $situation, $page, $perPage): array {
            $conditions = [];
            $parameters = [];

            if ($search !== null && $search !== '') {
                $conditions[] = 'c.nome ILIKE :search';
                $parameters['search'] = '%' . $search . '%';
            }

            if ($situation !== null && $situation !== '') {
                $conditions[] = 'e.situacao = :situacao';
                $parameters['situacao'] = $situation;
            }

            $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

            $count = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM finplan.vw_emprestimos e
                 JOIN finplan.clientes c ON c.id = e.cliente_id' . $where
            );
            $count->execute($parameters);
            $total = (int) $count->fetchColumn();

            $statement = $pdo->prepare(
                'SELECT e.id, e.cliente_id, c.nome AS cliente_nome,
                        e.valor_principal, e.taxa_juros_mensal, e.qtd_parcelas,
                        e.sistema_amortizacao, e.data_emprestimo, e.primeiro_vencimento,
                        e.status, e.observacoes, e.valor_total, e.total_juros,
                        e.total_pago, e.juros_recebidos, e.amortizacao_recebida,
                        e.saldo_a_receber, e.saldo_devedor, e.parcelas_atrasadas,
                        e.situacao, e.criado_em, e.atualizado_em
                 FROM finplan.vw_emprestimos e
                 JOIN finplan.clientes c ON c.id = e.cliente_id' . $where . '
                 ORDER BY e.data_emprestimo DESC, e.id DESC
                 LIMIT :limit OFFSET :offset'
            );

            foreach ($parameters as $name => $value) {
                $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
            }
            $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
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

    public function findById(int $userId, int $loanId): ?array
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($loanId): ?array {
            return $this->findByIdInConnection($pdo, $loanId);
        });
    }

    public function create(int $userId, array $data): array
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($data): array {
            $client = $pdo->prepare(
                'SELECT id FROM finplan.clientes WHERE id = :id AND ativo IS TRUE LIMIT 1'
            );
            $client->execute(['id' => $data['cliente_id']]);

            if (!$client->fetchColumn()) {
                throw new OutOfBoundsException('Cliente não encontrado ou inativo.');
            }

            $statement = $pdo->prepare(
                'SELECT finplan.fn_criar_emprestimo(
                    :cliente_id,
                    :valor_principal,
                    :taxa_juros_mensal,
                    :qtd_parcelas,
                    :data_emprestimo,
                    :primeiro_vencimento,
                    :sistema,
                    :observacoes
                ) AS id'
            );
            $statement->execute([
                'cliente_id' => $data['cliente_id'],
                'valor_principal' => $data['valor_principal'],
                'taxa_juros_mensal' => $data['taxa_juros_mensal'],
                'qtd_parcelas' => $data['qtd_parcelas'],
                'data_emprestimo' => $data['data_emprestimo'],
                'primeiro_vencimento' => $data['primeiro_vencimento'],
                'sistema' => $data['sistema_amortizacao'],
                'observacoes' => $data['observacoes']
            ]);

            $loanId = (int) $statement->fetchColumn();
            $loan = $this->findByIdInConnection($pdo, $loanId);

            if ($loan === null) {
                throw new RuntimeException('O banco não retornou o empréstimo criado.');
            }

            return $loan;
        });
    }

    public function installments(int $userId, int $loanId): array
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($loanId): array {
            if ($this->findByIdInConnection($pdo, $loanId) === null) {
                throw new OutOfBoundsException('Empréstimo não encontrado.');
            }

            $statement = $pdo->prepare(
                'SELECT id, emprestimo_id, numero, vencimento, valor,
                        valor_juros, valor_amortizacao,
                        saldo_devedor_inicial, saldo_devedor_final,
                        valor_pago, saldo, juros_pago, amortizacao_paga,
                        juros_pendente, amortizacao_pendente,
                        qtd_recebimentos, primeiro_recebimento,
                        ultimo_recebimento, data_quitacao, status
                 FROM finplan.vw_parcelas
                 WHERE emprestimo_id = :loan_id
                 ORDER BY numero ASC'
            );
            $statement->execute(['loan_id' => $loanId]);

            return [
                'emprestimo_id' => $loanId,
                'items' => $statement->fetchAll()
            ];
        });
    }

    public function simulate(array $data): array
    {
        $pdo = $this->database();
        $statement = $pdo->prepare(
            'SELECT cronograma.*,
                    SUM(cronograma.valor) OVER () AS valor_total,
                    SUM(cronograma.valor_juros) OVER () AS total_juros
             FROM finplan.fn_calcular_cronograma(
                :sistema,
                :valor_principal,
                :taxa_juros_mensal,
                :qtd_parcelas,
                :primeiro_vencimento
             ) AS cronograma
             ORDER BY cronograma.numero'
        );
        $statement->execute([
            'sistema' => $data['sistema_amortizacao'],
            'valor_principal' => $data['valor_principal'],
            'taxa_juros_mensal' => $data['taxa_juros_mensal'],
            'qtd_parcelas' => $data['qtd_parcelas'],
            'primeiro_vencimento' => $data['primeiro_vencimento']
        ]);

        $items = $statement->fetchAll();
        if ($items === []) {
            throw new RuntimeException('O banco não retornou o cronograma calculado.');
        }

        $total = $items[0]['valor_total'];
        $interest = $items[0]['total_juros'];
        foreach ($items as &$item) {
            unset($item['valor_total'], $item['total_juros']);
        }
        unset($item);

        return [
            'sistema_amortizacao' => $data['sistema_amortizacao'],
            'valor_principal' => $data['valor_principal'],
            'taxa_juros_mensal' => $data['taxa_juros_mensal'],
            'qtd_parcelas' => $data['qtd_parcelas'],
            'valor_total' => $total,
            'total_juros' => $interest,
            'parcelas' => $items
        ];
    }

    public function registerReceipt(int $userId, int $installmentId, array $data): array
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId, $installmentId, $data): array {
            $installment = $pdo->prepare(
                'SELECT id, valor, saldo
                 FROM finplan.vw_parcelas
                 WHERE id = :id
                 LIMIT 1'
            );
            $installment->execute(['id' => $installmentId]);
            $current = $installment->fetch();

            if (!$current) {
                throw new OutOfBoundsException('Parcela não encontrada.');
            }

            if ((float) $current['saldo'] <= 0) {
                throw new DomainException('Esta parcela já foi quitada.');
            }

            $accountId = $data['conta_id'];
            if ($accountId === null) {
                $account = $pdo->query(
                    "SELECT id FROM finplan.contas WHERE nome = 'Carteira' AND ativa IS TRUE ORDER BY id LIMIT 1"
                );
                $accountId = $account->fetchColumn();
            } else {
                $account = $pdo->prepare(
                    'SELECT id FROM finplan.contas WHERE id = :id AND ativa IS TRUE LIMIT 1'
                );
                $account->execute(['id' => $accountId]);
                $accountId = $account->fetchColumn();
            }

            if (!$accountId) {
                throw new OutOfBoundsException('Conta ativa não encontrada. Informe uma conta válida ou crie uma conta Carteira.');
            }

            $exceedsBalance = $pdo->prepare(
                'SELECT CASE WHEN CAST(:valor AS NUMERIC) > CAST(:saldo AS NUMERIC) THEN 1 ELSE 0 END'
            );
            $exceedsBalance->execute([
                'valor' => $data['valor'],
                'saldo' => $current['saldo']
            ]);
            if ($exceedsBalance->fetchColumn()) {
                throw new DomainException('O recebimento não pode ser maior que o saldo restante da parcela.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO finplan.recebimentos (
                    usuario_id, parcela_id, conta_id, valor,
                    data_recebimento, forma_pagamento, observacoes
                 ) VALUES (
                    :usuario_id, :parcela_id, :conta_id, :valor,
                    :data_recebimento, :forma_pagamento, :observacoes
                 )
                 RETURNING id, parcela_id, conta_id, valor,
                           data_recebimento, forma_pagamento, observacoes, criado_em'
            );
            $statement->execute([
                'usuario_id' => $userId,
                'parcela_id' => $installmentId,
                'conta_id' => (int) $accountId,
                'valor' => $data['valor'],
                'data_recebimento' => $data['data_recebimento'],
                'forma_pagamento' => $data['forma_pagamento'],
                'observacoes' => $data['observacoes']
            ]);

            $receipt = $statement->fetch();
            if (!$receipt) {
                throw new RuntimeException('O banco não retornou o recebimento criado.');
            }

            return $receipt;
        });
    }

    public function cancel(int $userId, int $loanId): array
    {
        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($loanId): array {
            $statement = $pdo->prepare(
                "UPDATE finplan.emprestimos
                 SET status = 'cancelado'
                 WHERE id = :id AND status = 'ativo'
                 RETURNING id, status"
            );
            $statement->execute(['id' => $loanId]);
            $loan = $statement->fetch();

            if (!$loan) {
                throw new OutOfBoundsException('Empréstimo não encontrado ou já cancelado.');
            }

            return [
                'id' => (int) $loan['id'],
                'status' => $loan['status'],
                'message' => 'Empréstimo cancelado. O contrato e o histórico de parcelas foram preservados.'
            ];
        });
    }

    private function findByIdInConnection(PDO $pdo, int $loanId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT e.id, e.cliente_id, c.nome AS cliente_nome,
                    e.valor_principal, e.taxa_juros_mensal, e.qtd_parcelas,
                    e.sistema_amortizacao, e.data_emprestimo, e.primeiro_vencimento,
                    e.status, e.observacoes, e.valor_total, e.total_juros,
                    e.total_pago, e.juros_recebidos, e.amortizacao_recebida,
                    e.saldo_a_receber, e.saldo_devedor, e.parcelas_atrasadas,
                    e.situacao, e.criado_em, e.atualizado_em
             FROM finplan.vw_emprestimos e
             JOIN finplan.clientes c ON c.id = e.cliente_id
             WHERE e.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $loanId]);
        $loan = $statement->fetch();

        return $loan ?: null;
    }
}
