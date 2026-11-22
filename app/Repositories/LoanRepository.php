<?php

class LoanRepository extends BaseRepository
{
    public function paginate(int|string $userId, ?string $search, ?string $situation, int $page, int $perPage): array
    {
        $userId = FinPlanId::normalize($userId);

        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId, $search, $situation, $page, $perPage): array {
            $conditions = ['c.usuario_id = :usuario_id'];
            $parameters = ['usuario_id' => $userId];

            if ($search !== null && $search !== '') {
                $conditions[] = 'c.nome ILIKE :search';
                $parameters['search'] = '%' . $search . '%';
            }

            if ($situation !== null && $situation !== '') {
                $conditions[] = 'e.situacao = :situacao';
                $parameters['situacao'] = $situation;
            }

            $where = ' WHERE ' . implode(' AND ', $conditions);

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

    public function findById(int|string $userId, int|string $loanId): ?array
    {
        $userId = FinPlanId::normalize($userId);
        $loanId = FinPlanId::normalize($loanId);

        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId, $loanId): ?array {
            return $this->findByIdInConnection($pdo, $userId, $loanId);
        });
    }

    public function create(int|string $userId, array $data): array
    {
        $userId = FinPlanId::normalize($userId);
        $clientId = FinPlanId::normalize($data['cliente_id']);

        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId, $clientId, $data): array {
            $client = $pdo->prepare(
                'SELECT id
                 FROM finplan.clientes
                 WHERE id = :id AND usuario_id = :usuario_id AND ativo IS TRUE
                 LIMIT 1'
            );
            $client->execute(['id' => $clientId, 'usuario_id' => $userId]);

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
                'cliente_id' => $clientId,
                'valor_principal' => $data['valor_principal'],
                'taxa_juros_mensal' => $data['taxa_juros_mensal'],
                'qtd_parcelas' => $data['qtd_parcelas'],
                'data_emprestimo' => $data['data_emprestimo'],
                'primeiro_vencimento' => $data['primeiro_vencimento'],
                'sistema' => $data['sistema_amortizacao'],
                'observacoes' => $data['observacoes']
            ]);

            $loanId = $statement->fetchColumn();
            if ($loanId === false || $loanId === null || $loanId === '') {
                throw new RuntimeException('O banco não retornou o identificador do empréstimo criado.');
            }

            try {
                $loanId = FinPlanId::normalize($loanId);
            } catch (InvalidArgumentException $exception) {
                throw new RuntimeException('O banco retornou um identificador de empréstimo inválido.', 0, $exception);
            }

            $loan = $this->findByIdInConnection($pdo, $userId, $loanId);

            if ($loan === null) {
                throw new RuntimeException('O banco não retornou o empréstimo criado.');
            }

            $schedule = $pdo->prepare(
                'SELECT numero, valor, valor_juros, valor_amortizacao,
                        saldo_devedor_inicial, saldo_devedor_final
                 FROM finplan.vw_parcelas
                 WHERE emprestimo_id = :loan_id
                 ORDER BY numero ASC'
            );
            $schedule->execute(['loan_id' => $loanId]);

            $this->assertScheduleConsistent(
                $schedule->fetchAll(),
                (int) $data['qtd_parcelas'],
                (float) $data['valor_principal']
            );

            return $loan;
        });
    }

    public function installments(int|string $userId, int|string $loanId): array
    {
        $userId = FinPlanId::normalize($userId);
        $loanId = FinPlanId::normalize($loanId);

        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId, $loanId): array {
            if ($this->findByIdInConnection($pdo, $userId, $loanId) === null) {
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

        // A simulação usa a assinatura pública de fn_calcular_cronograma.
        // Nem toda instalação expõe as colunas de saldo/amortização nessa
        // função, por isso a validação completa fica restrita à criação, em
        // que as parcelas são lidas de finplan.vw_parcelas.
        $this->assertScheduleConsistent(
            $items,
            (int) $data['qtd_parcelas'],
            (float) $data['valor_principal'],
            false
        );

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

    public function registerReceipt(int|string $userId, int|string $installmentId, array $data): array
    {
        $userId = FinPlanId::normalize($userId);
        $installmentId = FinPlanId::normalize($installmentId);

        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId, $installmentId, $data): array {
            $installment = $pdo->prepare(
                'SELECT p.id, p.valor, p.saldo, p.emprestimo_id,
                        e.status AS emprestimo_status
                 FROM finplan.vw_parcelas p
                 JOIN finplan.emprestimos e ON e.id = p.emprestimo_id
                 JOIN finplan.clientes c ON c.id = e.cliente_id
                 WHERE p.id = :id AND c.usuario_id = :usuario_id
                 LIMIT 1'
            );
            $installment->execute(['id' => $installmentId, 'usuario_id' => $userId]);
            $current = $installment->fetch();

            if (!$current) {
                throw new OutOfBoundsException('Parcela não encontrada.');
            }

            if (($current['emprestimo_status'] ?? '') !== 'ativo') {
                throw new DomainException('O contrato não está ativo e não pode receber pagamentos.');
            }

            if ((float) $current['saldo'] <= 0) {
                throw new DomainException('Esta parcela já foi quitada.');
            }

            $accountId = $data['conta_id'];
            if ($accountId === null) {
                $account = $pdo->prepare(
                    "SELECT id
                     FROM finplan.contas
                     WHERE nome = 'Carteira' AND ativa IS TRUE AND usuario_id = :usuario_id
                     ORDER BY id
                     LIMIT 1"
                );
                $account->execute(['usuario_id' => $userId]);
                $accountId = $account->fetchColumn();
            } else {
                $account = $pdo->prepare(
                    'SELECT id
                     FROM finplan.contas
                     WHERE id = :id AND ativa IS TRUE AND usuario_id = :usuario_id
                     LIMIT 1'
                );
                $account->execute(['id' => $accountId, 'usuario_id' => $userId]);
                $accountId = $account->fetchColumn();
            }

            $accountId = FinPlanId::tryNormalize($accountId);
            if ($accountId === null) {
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
                'conta_id' => $accountId,
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

    public function cancel(int|string $userId, int|string $loanId): array
    {
        $userId = FinPlanId::normalize($userId);
        $loanId = FinPlanId::normalize($loanId);

        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId, $loanId): array {
            $current = $pdo->prepare(
                'SELECT e.id, e.status, e.situacao, e.total_pago
                 FROM finplan.vw_emprestimos e
                 JOIN finplan.clientes c ON c.id = e.cliente_id
                 WHERE e.id = :id AND c.usuario_id = :usuario_id
                 LIMIT 1'
            );
            $current->execute(['id' => $loanId, 'usuario_id' => $userId]);
            $existing = $current->fetch();

            if (!$existing) {
                throw new OutOfBoundsException('Empréstimo não encontrado.');
            }

            if ((string) $existing['status'] !== 'ativo') {
                throw new OutOfBoundsException('Empréstimo não encontrado ou já cancelado.');
            }

            if ((float) ($existing['total_pago'] ?? 0) > 0.0) {
                throw new DomainException('Não é possível cancelar um empréstimo que já possui recebimentos registrados.');
            }

            $statement = $pdo->prepare(
                "UPDATE finplan.emprestimos AS e
                 SET status = 'cancelado'
                 WHERE e.id = :id
                   AND e.status = 'ativo'
                   AND EXISTS (
                       SELECT 1
                       FROM finplan.clientes c
                       WHERE c.id = e.cliente_id AND c.usuario_id = :usuario_id
                   )
                   AND NOT EXISTS (
                       SELECT 1
                       FROM finplan.recebimentos r
                       JOIN finplan.parcelas p ON p.id = r.parcela_id
                       WHERE p.emprestimo_id = e.id
                   )
                 RETURNING e.id, e.status"
            );
            $statement->execute(['id' => $loanId, 'usuario_id' => $userId]);
            $loan = $statement->fetch();

            if (!$loan) {
                throw new DomainException('O empréstimo não pode ser cancelado porque foi alterado por outra operação.');
            }

            return [
                'id' => FinPlanId::normalize($loan['id']),
                'status' => $loan['status'],
                'message' => 'Empréstimo cancelado. O contrato e o histórico de parcelas foram preservados.'
            ];
        });
    }

    private function findByIdInConnection(PDO $pdo, int|string $userId, int|string $loanId): ?array
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
             JOIN finplan.clientes c ON c.id = e.cliente_id AND c.usuario_id = :usuario_id
             WHERE e.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $loanId, 'usuario_id' => $userId]);
        $loan = $statement->fetch();

        return $loan ?: null;
    }

    private function assertScheduleConsistent(array $items, int $expectedCount, float $principal, bool $requireFullSchedule = true): void
    {
        if ($expectedCount < 1) {
            throw new InvalidArgumentException('A quantidade de parcelas deve ser maior que zero.');
        }

        if (count($items) !== $expectedCount) {
            throw new RuntimeException('O cronograma gerado pelo banco não possui a quantidade de parcelas contratada.');
        }

        $firstItem = $items[0] ?? [];
        $hasInterest = array_key_exists('valor_juros', $firstItem);
        $hasAmortization = array_key_exists('valor_amortizacao', $firstItem);
        $hasBalances = array_key_exists('saldo_devedor_inicial', $firstItem)
            && array_key_exists('saldo_devedor_final', $firstItem);

        if (!array_key_exists('valor', $firstItem)) {
            throw new RuntimeException('O cronograma gerado pelo banco não informa o valor das parcelas.');
        }

        if ($requireFullSchedule && (!$hasInterest || !$hasAmortization || !$hasBalances)) {
            throw new RuntimeException('O cronograma gerado pelo banco não expõe todas as colunas necessárias para validar o contrato.');
        }

        $expectedNumber = 1;
        $sumInterest = 0.0;
        $sumAmortization = 0.0;
        $sumValue = 0.0;
        $firstOpeningBalance = null;
        $lastClosingBalance = null;

        foreach ($items as $item) {
            if ((int) ($item['numero'] ?? 0) !== $expectedNumber) {
                throw new RuntimeException('O cronograma gerado pelo banco possui numeração de parcelas inválida.');
            }

            $interest = $hasInterest ? (float) ($item['valor_juros'] ?? 0) : 0.0;
            $amortization = $hasAmortization ? (float) ($item['valor_amortizacao'] ?? 0) : 0.0;
            $value = (float) ($item['valor'] ?? 0);

            if ($interest < -0.01 || $amortization < -0.01 || $value < -0.01) {
                throw new RuntimeException('O cronograma gerado pelo banco possui valores negativos.');
            }

            if ($hasInterest && $hasAmortization && abs($value - ($interest + $amortization)) > 0.02) {
                throw new RuntimeException('O cronograma gerado pelo banco possui parcela com valor inconsistente.');
            }

            if ($hasBalances) {
                $openingBalance = (float) ($item['saldo_devedor_inicial'] ?? 0);
                $closingBalance = (float) ($item['saldo_devedor_final'] ?? 0);

                if ($hasAmortization && abs($openingBalance - $amortization - $closingBalance) > 0.02) {
                    throw new RuntimeException('O cronograma gerado pelo banco possui saldo devedor inconsistente.');
                }

                if ($expectedNumber === 1) {
                    $firstOpeningBalance = $openingBalance;
                }

                $lastClosingBalance = $closingBalance;
            }

            $sumInterest += $interest;
            $sumAmortization += $amortization;
            $sumValue += $value;
            $expectedNumber++;
        }

        if ($hasBalances && ($firstOpeningBalance === null || abs($firstOpeningBalance - $principal) > 0.02)) {
            throw new RuntimeException('O cronograma gerado pelo banco não inicia com o valor principal contratado.');
        }

        if ($hasBalances && ($lastClosingBalance === null || abs($lastClosingBalance) > 0.02)) {
            throw new RuntimeException('O cronograma gerado pelo banco não zera o saldo devedor na última parcela.');
        }

        if ($hasAmortization && abs($sumAmortization - $principal) > 0.05) {
            throw new RuntimeException('O cronograma gerado pelo banco não amortiza integralmente o valor principal.');
        }

        if ($hasInterest && abs($sumValue - ($principal + $sumInterest)) > 0.10) {
            throw new RuntimeException('O cronograma gerado pelo banco possui total de parcelas inconsistente.');
        }
    }
}
