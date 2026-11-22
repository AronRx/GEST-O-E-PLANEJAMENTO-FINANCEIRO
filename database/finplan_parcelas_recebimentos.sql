-- ============================================================
-- finplan.parcelas e finplan.recebimentos - Definição completa
-- ============================================================
-- Este script cria/atualiza as tabelas e views necessárias para o
-- módulo de empréstimos, parcelas e recebimentos.
--
-- Execute APÓS o schema.sql base.
-- ============================================================

-- ---------------------------------------------------------------------------
-- 1. Tabela parcelas - ADICIONA colunas do cronograma Price
-- ---------------------------------------------------------------------------
-- O schema.sql original não tem valor_juros, valor_amortizacao,
-- saldo_devedor_inicial, saldo_devedor_final.
-- Estas colunas são populadas por fn_criar_emprestimo e lidas pelas views.
-- ---------------------------------------------------------------------------

DO $$
BEGIN
    -- valor_juros
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'finplan' AND table_name = 'parcelas' AND column_name = 'valor_juros'
    ) THEN
        ALTER TABLE finplan.parcelas ADD COLUMN valor_juros NUMERIC(15,2) NOT NULL DEFAULT 0;
    END IF;

    -- valor_amortizacao
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'finplan' AND table_name = 'parcelas' AND column_name = 'valor_amortizacao'
    ) THEN
        ALTER TABLE finplan.parcelas ADD COLUMN valor_amortizacao NUMERIC(15,2) NOT NULL DEFAULT 0;
    END IF;

    -- saldo_devedor_inicial
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'finplan' AND table_name = 'parcelas' AND column_name = 'saldo_devedor_inicial'
    ) THEN
        ALTER TABLE finplan.parcelas ADD COLUMN saldo_devedor_inicial NUMERIC(15,2) NOT NULL DEFAULT 0;
    END IF;

    -- saldo_devedor_final
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'finplan' AND table_name = 'parcelas' AND column_name = 'saldo_devedor_final'
    ) THEN
        ALTER TABLE finplan.parcelas ADD COLUMN saldo_devedor_final NUMERIC(15,2) NOT NULL DEFAULT 0;
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- 2. Tabela recebimentos - NOVA (não existe no schema.sql)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS finplan.recebimentos (
    id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    usuario_id          BIGINT NOT NULL REFERENCES finplan.usuarios(id) ON DELETE CASCADE,
    parcela_id          BIGINT NOT NULL REFERENCES finplan.parcelas(id) ON DELETE CASCADE,
    conta_id            BIGINT NOT NULL REFERENCES finplan.contas(id) ON DELETE RESTRICT,
    valor               NUMERIC(15,2) NOT NULL,
    data_recebimento    DATE NOT NULL,
    forma_pagamento     VARCHAR(20) NOT NULL CHECK (forma_pagamento IN (
        'dinheiro', 'pix', 'transferencia', 'boleto', 'cartao', 'outro'
    )),
    observacoes         TEXT,
    criado_em           TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_recebimentos_parcela ON finplan.recebimentos(parcela_id);
CREATE INDEX IF NOT EXISTS idx_recebimentos_usuario ON finplan.recebimentos(usuario_id);
CREATE INDEX IF NOT EXISTS idx_recebimentos_data ON finplan.recebimentos(data_recebimento);

-- ---------------------------------------------------------------------------
-- 3. View vw_parcelas - Cálculo de saldos e alocação de pagamentos
-- ---------------------------------------------------------------------------
-- Regra de alocação: pagamento vai PRIMEIRO para juros, DEPOIS para amortização.
-- Status: 'paga' (saldo=0), 'parcial' (0<saldo<valor), 'atrasada' (vencida e saldo>0), 'pendente'
-- ---------------------------------------------------------------------------

CREATE OR REPLACE VIEW finplan.vw_parcelas AS
SELECT 
    p.id,
    p.emprestimo_id,
    p.numero_parcela AS numero,
    p.data_vencimento AS vencimento,
    p.valor,
    p.valor_juros,
    p.valor_amortizacao,
    p.saldo_devedor_inicial,
    p.saldo_devedor_final,
    COALESCE(r.valor_pago, 0)::NUMERIC(15,2) AS valor_pago,
    (p.valor - COALESCE(r.valor_pago, 0))::NUMERIC(15,2) AS saldo,
    -- Alocação: juros primeiro, depois amortização
    LEAST(COALESCE(r.valor_pago, 0), p.valor_juros)::NUMERIC(15,2) AS juros_pago,
    GREATEST(COALESCE(r.valor_pago, 0) - p.valor_juros, 0)::NUMERIC(15,2) AS amortizacao_paga,
    GREATEST(p.valor_juros - COALESCE(r.valor_pago, 0), 0)::NUMERIC(15,2) AS juros_pendente,
    GREATEST(p.valor_amortizacao - GREATEST(COALESCE(r.valor_pago, 0) - p.valor_juros, 0), 0)::NUMERIC(15,2) AS amortizacao_pendente,
    COALESCE(r.qtd, 0) AS qtd_recebimentos,
    r.primeiro_recebimento,
    r.ultimo_recebimento,
    CASE WHEN COALESCE(r.valor_pago, 0) >= p.valor THEN p.data_vencimento END AS data_quitacao,
    CASE
        WHEN COALESCE(r.valor_pago, 0) >= p.valor THEN 'paga'
        WHEN p.data_vencimento < CURRENT_DATE THEN 'atrasada'
        WHEN COALESCE(r.valor_pago, 0) > 0 THEN 'parcial'
        ELSE 'pendente'
    END AS status
FROM finplan.parcelas p
LEFT JOIN (
    SELECT 
        parcela_id, 
        SUM(valor) AS valor_pago, 
        COUNT(*) AS qtd,
        MIN(data_recebimento) AS primeiro_recebimento,
        MAX(data_recebimento) AS ultimo_recebimento
    FROM finplan.recebimentos
    GROUP BY parcela_id
) r ON r.parcela_id = p.id;

-- ---------------------------------------------------------------------------
-- 4. View vw_emprestimos - Agregações por contrato
-- ---------------------------------------------------------------------------

CREATE OR REPLACE VIEW finplan.vw_emprestimos AS
SELECT 
    e.id,
    e.usuario_id,
    e.cliente_id,
    e.valor_principal,
    e.taxa_juros_mensal,
    e.qtd_parcelas,
    e.sistema_amortizacao,
    e.data_emprestimo,
    e.primeiro_vencimento,
    e.status,
    e.observacoes,
    COALESCE(SUM(p.valor), 0)::NUMERIC(15,2) AS valor_total,
    COALESCE(SUM(p.valor_juros), 0)::NUMERIC(15,2) AS total_juros,
    COALESCE(SUM(p.valor_pago), 0)::NUMERIC(15,2) AS total_pago,
    COALESCE(SUM(p.juros_pago), 0)::NUMERIC(15,2) AS juros_recebidos,
    COALESCE(SUM(p.amortizacao_paga), 0)::NUMERIC(15,2) AS amortizacao_recebida,
    COALESCE(SUM(p.saldo), 0)::NUMERIC(15,2) AS saldo_a_receber,
    (e.valor_principal - COALESCE(SUM(p.amortizacao_paga), 0))::NUMERIC(15,2) AS saldo_devedor,
    COUNT(*) FILTER (WHERE p.status = 'atrasada') AS parcelas_atrasadas,
    CASE
        WHEN e.status = 'cancelado' THEN 'cancelado'
        WHEN COALESCE(SUM(p.saldo), 0) <= 0 THEN 'quitado'
        WHEN COUNT(*) FILTER (WHERE p.status = 'atrasada') > 0 THEN 'atrasado'
        ELSE 'ativo'
    END AS situacao,
    e.criado_em,
    e.atualizado_em
FROM finplan.emprestimos e
LEFT JOIN finplan.vw_parcelas p ON p.emprestimo_id = e.id
GROUP BY e.id;

-- ---------------------------------------------------------------------------
-- 5. Trigger para BLOQUEAR recebimento acima do saldo da parcela
-- ---------------------------------------------------------------------------
-- O PHP já valida, mas o banco deve ser a última linha de defesa.
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION finplan.fn_validar_recebimento_saldo()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_saldo NUMERIC(15,2);
    v_valor_parcela NUMERIC(15,2);
    v_total_pago NUMERIC(15,2);
BEGIN
    -- Busca o valor da parcela e o total já pago (incluindo este insert)
    SELECT p.valor, COALESCE(SUM(r.valor), 0) + NEW.valor
    INTO v_valor_parcela, v_total_pago
    FROM finplan.parcelas p
    LEFT JOIN finplan.recebimentos r ON r.parcela_id = p.id
    WHERE p.id = NEW.parcela_id
    GROUP BY p.valor;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Parcela não encontrada: %', NEW.parcela_id;
    END IF;

    IF v_total_pago > v_valor_parcela THEN
        RAISE EXCEPTION 'Recebimento excede o valor da parcela. Parcela: %, Tentativa de pagar: %, Total seria: %',
            v_valor_parcela, NEW.valor, v_total_pago;
    END IF;

    RETURN NEW;
END $$;

DROP TRIGGER IF EXISTS trg_validar_recebimento_saldo ON finplan.recebimentos;
CREATE TRIGGER trg_validar_recebimento_saldo
BEFORE INSERT ON finplan.recebimentos
FOR EACH ROW EXECUTE FUNCTION finplan.fn_validar_recebimento_saldo();

-- ---------------------------------------------------------------------------
-- 6. Trigger para atualizar data_pagamento e status na tabela parcelas
--    (opcional - para compatibilidade com legado)
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION finplan.fn_atualizar_parcela_pagamento()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_total_pago NUMERIC(15,2);
    v_valor_parcela NUMERIC(15,2);
BEGIN
    IF TG_OP = 'INSERT' THEN
        SELECT p.valor, COALESCE(SUM(r.valor), 0)
        INTO v_valor_parcela, v_total_pago
        FROM finplan.parcelas p
        LEFT JOIN finplan.recebimentos r ON r.parcela_id = p.id
        WHERE p.id = NEW.parcela_id
        GROUP BY p.valor;

        IF v_total_pago >= v_valor_parcela THEN
            UPDATE finplan.parcelas
            SET data_pagamento = NEW.data_recebimento,
                valor_pago = v_valor_parcela,
                status = 'paga',
                atualizado_em = NOW()
            WHERE id = NEW.parcela_id;
        ELSE
            UPDATE finplan.parcelas
            SET valor_pago = v_total_pago,
                status = CASE WHEN v_total_pago > 0 THEN 'parcial' ELSE 'pendente' END,
                atualizado_em = NOW()
            WHERE id = NEW.parcela_id;
        END IF;
    END IF;
    RETURN NEW;
END $$;

DROP TRIGGER IF EXISTS trg_atualizar_parcela_pagamento ON finplan.recebimentos;
CREATE TRIGGER trg_atualizar_parcela_pagamento
AFTER INSERT ON finplan.recebimentos
FOR EACH ROW EXECUTE FUNCTION finplan.fn_atualizar_parcela_pagamento();

-- ---------------------------------------------------------------------------
-- 7. Função fn_criar_emprestimo - Exemplo de implementação completa
--    (Gera parcelas com cronograma Price e insere na tabela parcelas)
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION finplan.fn_criar_emprestimo(
    p_cliente_id BIGINT,
    p_valor_principal NUMERIC(15,2),
    p_taxa_juros_mensal NUMERIC(8,4),
    p_qtd_parcelas INTEGER,
    p_data_emprestimo DATE,
    p_primeiro_vencimento DATE,
    p_sistema_amortizacao TEXT,
    p_observacoes TEXT
) RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_emprestimo_id BIGINT;
    v_usuario_id BIGINT;
    v_taxa NUMERIC(10,8);
    v_prestacao NUMERIC(15,2);
    v_saldo NUMERIC(15,2);
    v_juros NUMERIC(15,2);
    v_amortizacao NUMERIC(15,2);
    v_vencimento DATE;
    i INTEGER;
BEGIN
    -- Validações básicas
    IF p_valor_principal <= 0 THEN
        RAISE EXCEPTION 'Valor principal deve ser positivo';
    END IF;
    IF p_qtd_parcelas < 1 OR p_qtd_parcelas > 600 THEN
        RAISE EXCEPTION 'Quantidade de parcelas deve estar entre 1 e 600';
    END IF;
    IF p_primeiro_vencimento < p_data_emprestimo THEN
        RAISE EXCEPTION 'Primeiro vencimento não pode ser anterior à data do empréstimo';
    END IF;
    IF p_sistema_amortizacao <> 'price' THEN
        RAISE EXCEPTION 'Apenas o sistema Price está implementado';
    END IF;

    -- Obtém usuario_id do cliente
    SELECT usuario_id INTO v_usuario_id
    FROM finplan.clientes
    WHERE id = p_cliente_id AND ativo IS TRUE;
    
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Cliente não encontrado ou inativo';
    END IF;

    -- Insere o empréstimo
    INSERT INTO finplan.emprestimos (
        usuario_id, cliente_id, valor_principal, taxa_juros_mensal,
        qtd_parcelas, sistema_amortizacao, data_emprestimo,
        primeiro_vencimento, status, observacoes
    ) VALUES (
        v_usuario_id, p_cliente_id, p_valor_principal, p_taxa_juros_mensal,
        p_qtd_parcelas, p_sistema_amortizacao, p_data_emprestimo,
        p_primeiro_vencimento, 'ativo', p_observacoes
    ) RETURNING id INTO v_emprestimo_id;

    -- Calcula cronograma Price
    v_taxa := p_taxa_juros_mensal / 100.0;
    v_saldo := p_valor_principal;
    v_vencimento := p_primeiro_vencimento;

    IF p_taxa_juros_mensal = 0 THEN
        v_prestacao := ROUND(p_valor_principal / p_qtd_parcelas, 2);
    ELSE
        v_prestacao := ROUND(
            p_valor_principal * v_taxa / (1 - POWER(1 + v_taxa, -p_qtd_parcelas)),
            2
        );
    END IF;

    FOR i IN 1..p_qtd_parcelas LOOP
        v_juros := ROUND(v_saldo * v_taxa, 2);
        
        IF i = p_qtd_parcelas THEN
            -- Última parcela: ajusta para zerar o saldo
            v_amortizacao := v_saldo;
            v_prestacao := ROUND(v_juros + v_amortizacao, 2);
        ELSE
            v_amortizacao := ROUND(v_prestacao - v_juros, 2);
        END IF;

        INSERT INTO finplan.parcelas (
            emprestimo_id, numero_parcela, valor, valor_juros, valor_amortizacao,
            saldo_devedor_inicial, saldo_devedor_final, data_vencimento
        ) VALUES (
            v_emprestimo_id, i, 
            ROUND(v_juros + v_amortizacao, 2),
            v_juros, v_amortizacao,
            v_saldo, v_saldo - v_amortizacao,
            v_vencimento
        );

        v_saldo := v_saldo - v_amortizacao;
        v_vencimento := (v_vencimento + INTERVAL '1 month')::DATE;
    END LOOP;

    RETURN v_emprestimo_id;
END $$;

-- ---------------------------------------------------------------------------
-- 8. Função fn_calcular_cronograma - Para simulação (não persiste)
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION finplan.fn_calcular_cronograma(
    p_sistema TEXT,
    p_valor_principal NUMERIC(15,2),
    p_taxa_juros_mensal NUMERIC(8,4),
    p_qtd_parcelas INTEGER,
    p_primeiro_vencimento DATE
) RETURNS TABLE (
    numero INTEGER,
    vencimento DATE,
    valor NUMERIC(15,2),
    valor_juros NUMERIC(15,2),
    valor_amortizacao NUMERIC(15,2),
    saldo_devedor_inicial NUMERIC(15,2),
    saldo_devedor_final NUMERIC(15,2)
) LANGUAGE plpgsql AS $$
DECLARE
    v_taxa NUMERIC(10,8);
    v_prestacao NUMERIC(15,2);
    v_saldo NUMERIC(15,2);
    v_juros NUMERIC(15,2);
    v_amortizacao NUMERIC(15,2);
    v_vencimento DATE;
    i INTEGER;
BEGIN
    IF p_sistema <> 'price' THEN
        RAISE EXCEPTION 'Apenas o sistema Price está implementado';
    END IF;

    v_taxa := p_taxa_juros_mensal / 100.0;
    v_saldo := p_valor_principal;
    v_vencimento := p_primeiro_vencimento;

    IF p_taxa_juros_mensal = 0 THEN
        v_prestacao := ROUND(p_valor_principal / p_qtd_parcelas, 2);
    ELSE
        v_prestacao := ROUND(
            p_valor_principal * v_taxa / (1 - POWER(1 + v_taxa, -p_qtd_parcelas)),
            2
        );
    END IF;

    FOR i IN 1..p_qtd_parcelas LOOP
        v_juros := ROUND(v_saldo * v_taxa, 2);
        
        IF i = p_qtd_parcelas THEN
            v_amortizacao := v_saldo;
        ELSE
            v_amortizacao := ROUND(v_prestacao - v_juros, 2);
        END IF;

        -- Use RETURN QUERY (not RETURN NEXT QUERY) for RETURNS TABLE functions
        RETURN QUERY SELECT 
            i,
            v_vencimento,
            ROUND(v_juros + v_amortizacao, 2),
            v_juros,
            v_amortizacao,
            v_saldo,
            v_saldo - v_amortizacao;
        
        v_saldo := v_saldo - v_amortizacao;
        v_vencimento := (v_vencimento + INTERVAL '1 month')::DATE;
    END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- 9. Permissões para role finplan_app
-- ---------------------------------------------------------------------------

GRANT SELECT, INSERT, UPDATE ON finplan.parcelas TO finplan_app;
GRANT SELECT, INSERT ON finplan.recebimentos TO finplan_app;
GRANT SELECT ON finplan.vw_parcelas TO finplan_app;
GRANT SELECT ON finplan.vw_emprestimos TO finplan_app;
GRANT EXECUTE ON FUNCTION finplan.fn_criar_emprestimo TO finplan_app;
GRANT EXECUTE ON FUNCTION finplan.fn_calcular_cronograma TO finplan_app;

-- ============================================================
-- FIM
-- ============================================================