BEGIN;

CREATE SCHEMA IF NOT EXISTS legacy_app;

CREATE OR REPLACE FUNCTION legacy_app.current_usuario_id()
RETURNS uuid
LANGUAGE sql
STABLE
AS $$
    SELECT NULLIF(current_setting('app.sf_usuario_id', true), '')::uuid;
$$;

CREATE OR REPLACE FUNCTION legacy_app.read_payload(p_value text, p_prefix text)
RETURNS jsonb
LANGUAGE plpgsql
IMMUTABLE
AS $$
DECLARE
    v_payload text;
BEGIN
    IF p_value IS NULL OR left(p_value, length(p_prefix)) <> p_prefix THEN
        RETURN '{}'::jsonb;
    END IF;

    v_payload := substring(p_value FROM length(p_prefix) + 1);

    BEGIN
        RETURN v_payload::jsonb;
    EXCEPTION WHEN others THEN
        RETURN '{}'::jsonb;
    END;
END;
$$;

CREATE OR REPLACE FUNCTION legacy_app.pack_client_observations(
    p_note text,
    p_rg text,
    p_whatsapp text
)
RETURNS text
LANGUAGE sql
IMMUTABLE
AS $$
    SELECT '__SF_OBS_V1__' || jsonb_build_object(
        'note', p_note,
        'rg', p_rg,
        'whatsapp', p_whatsapp
    )::text;
$$;

CREATE OR REPLACE FUNCTION legacy_app.pack_client_address(
    p_cep text,
    p_rua text,
    p_numero text,
    p_bairro text,
    p_cidade text,
    p_estado text
)
RETURNS text
LANGUAGE sql
IMMUTABLE
AS $$
    SELECT '__SF_ADDR_V1__' || jsonb_build_object(
        'cep', p_cep,
        'rua', p_rua,
        'numero', p_numero,
        'bairro', p_bairro,
        'cidade', p_cidade,
        'estado', p_estado
    )::text;
$$;

DROP VIEW IF EXISTS legacy_app.parcelas CASCADE;
DROP VIEW IF EXISTS legacy_app.emprestimos CASCADE;
DROP VIEW IF EXISTS legacy_app.clientes CASCADE;

CREATE VIEW legacy_app.clientes AS
SELECT
    c.id,
    c.usuario_id,
    c.nome,
    c.documento AS cpf,
    legacy_app.read_payload(c.observacoes, '__SF_OBS_V1__')->>'rg' AS rg,
    c.telefone,
    legacy_app.read_payload(c.observacoes, '__SF_OBS_V1__')->>'whatsapp' AS whatsapp,
    c.email,
    legacy_app.read_payload(c.endereco, '__SF_ADDR_V1__')->>'cep' AS cep,
    CASE WHEN left(coalesce(c.endereco, ''), length('__SF_ADDR_V1__')) = '__SF_ADDR_V1__'
        THEN legacy_app.read_payload(c.endereco, '__SF_ADDR_V1__')->>'rua'
        ELSE c.endereco
    END AS rua,
    legacy_app.read_payload(c.endereco, '__SF_ADDR_V1__')->>'numero' AS numero,
    legacy_app.read_payload(c.endereco, '__SF_ADDR_V1__')->>'bairro' AS bairro,
    legacy_app.read_payload(c.endereco, '__SF_ADDR_V1__')->>'cidade' AS cidade,
    legacy_app.read_payload(c.endereco, '__SF_ADDR_V1__')->>'estado' AS estado,
    CASE
        WHEN left(coalesce(c.observacoes, ''), length('__SF_OBS_V1__')) = '__SF_OBS_V1__'
        THEN legacy_app.read_payload(c.observacoes, '__SF_OBS_V1__')->>'note'
        ELSE c.observacoes
    END AS observacoes,
    c.ativo,
    c.criado_em AS data_cadastro,
    c.criado_em,
    c.atualizado_em
FROM public.clientes c
WHERE c.usuario_id = legacy_app.current_usuario_id();

CREATE OR REPLACE FUNCTION legacy_app.write_clientes()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    v_user_id uuid := legacy_app.current_usuario_id();
    v_endereco text;
    v_observacoes text;
BEGIN
    v_endereco := legacy_app.pack_client_address(
        NEW.cep, NEW.rua, NEW.numero, NEW.bairro, NEW.cidade, NEW.estado
    );
    v_observacoes := legacy_app.pack_client_observations(
        NEW.observacoes, NEW.rg, NEW.whatsapp
    );

    IF TG_OP = 'INSERT' THEN
        IF NULLIF(NEW.cpf, '') IS NOT NULL AND EXISTS (
            SELECT 1 FROM public.clientes c
            WHERE c.usuario_id = v_user_id AND c.documento = NEW.cpf
        ) THEN
            RAISE EXCEPTION 'CPF já cadastrado.' USING ERRCODE = '23505';
        END IF;

        INSERT INTO public.clientes (
            usuario_id, nome, documento, telefone, email, endereco, observacoes, ativo
        ) VALUES (
            v_user_id, NEW.nome, NULLIF(NEW.cpf, ''), NULLIF(NEW.telefone, ''),
            NULLIF(NEW.email, ''), v_endereco, v_observacoes, COALESCE(NEW.ativo, TRUE)
        ) RETURNING id INTO NEW.id;

        NEW.usuario_id := v_user_id;
        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        IF NULLIF(NEW.cpf, '') IS NOT NULL AND EXISTS (
            SELECT 1 FROM public.clientes c
            WHERE c.usuario_id = v_user_id
              AND c.documento = NEW.cpf
              AND c.id <> OLD.id
        ) THEN
            RAISE EXCEPTION 'CPF já cadastrado.' USING ERRCODE = '23505';
        END IF;

        UPDATE public.clientes
        SET nome = NEW.nome,
            documento = NULLIF(NEW.cpf, ''),
            telefone = NULLIF(NEW.telefone, ''),
            email = NULLIF(NEW.email, ''),
            endereco = v_endereco,
            observacoes = v_observacoes,
            ativo = COALESCE(NEW.ativo, TRUE),
            atualizado_em = NOW()
        WHERE id = OLD.id AND usuario_id = v_user_id;

        NEW.id := OLD.id;
        NEW.usuario_id := v_user_id;
        RETURN NEW;
    ELSE
        DELETE FROM public.clientes
        WHERE id = OLD.id AND usuario_id = v_user_id;
        RETURN OLD;
    END IF;
END;
$$;

CREATE TRIGGER clientes_write
INSTEAD OF INSERT OR UPDATE OR DELETE ON legacy_app.clientes
FOR EACH ROW EXECUTE FUNCTION legacy_app.write_clientes();

CREATE VIEW legacy_app.emprestimos AS
SELECT
    e.id,
    e.usuario_id,
    e.cliente_id,
    e.valor_principal AS valor_emprestado,
    e.taxa_juros AS juros,
    COALESCE((
        SELECT COUNT(*)::integer
        FROM public.parcelas p
        WHERE p.emprestimo_id = e.id
    ), 0) AS quantidade_parcelas,
    COALESCE((
        SELECT SUM(p.valor)
        FROM public.parcelas p
        WHERE p.emprestimo_id = e.id
    ), e.valor_principal * (1 + e.taxa_juros / 100)) AS valor_total,
    COALESCE((
        SELECT AVG(p.valor)
        FROM public.parcelas p
        WHERE p.emprestimo_id = e.id
    ), CASE
        WHEN e.data_vencimento IS NOT NULL THEN e.valor_principal * (1 + e.taxa_juros / 100)
        ELSE e.valor_principal * (1 + e.taxa_juros / 100)
    END) AS valor_parcela,
    COALESCE((
        SELECT MIN(p.data_vencimento)
        FROM public.parcelas p
        WHERE p.emprestimo_id = e.id
    ), e.data_vencimento) AS primeiro_vencimento,
    e.criado_em AS data_cadastro,
    e.data_emprestimo,
    e.data_vencimento,
    e.status,
    e.observacoes,
    e.criado_em,
    e.atualizado_em
FROM public.emprestimos e
WHERE e.usuario_id = legacy_app.current_usuario_id();

CREATE OR REPLACE FUNCTION legacy_app.write_emprestimos()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    v_user_id uuid := legacy_app.current_usuario_id();
    v_first_due date;
    v_count integer;
    v_final_due date;
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NOT EXISTS (
            SELECT 1 FROM public.clientes c
            WHERE c.id = NEW.cliente_id AND c.usuario_id = v_user_id
        ) THEN
            RAISE EXCEPTION 'Cliente não encontrado para o usuário atual.';
        END IF;

        v_first_due := COALESCE(NEW.primeiro_vencimento, NEW.data_vencimento, CURRENT_DATE);
        v_count := GREATEST(COALESCE(NEW.quantidade_parcelas, 1), 1);
        v_final_due := (v_first_due + ((v_count - 1) * INTERVAL '1 month'))::date;

        INSERT INTO public.emprestimos (
            usuario_id, cliente_id, valor_principal, taxa_juros, tipo_juros,
            data_emprestimo, data_vencimento, status, observacoes
        ) VALUES (
            v_user_id, NEW.cliente_id, NEW.valor_emprestado, COALESCE(NEW.juros, 0),
            'simples', CURRENT_DATE, v_final_due, 'ativo', NEW.observacoes
        ) RETURNING id INTO NEW.id;

        NEW.usuario_id := v_user_id;
        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        IF NOT EXISTS (
            SELECT 1 FROM public.clientes c
            WHERE c.id = NEW.cliente_id AND c.usuario_id = v_user_id
        ) THEN
            RAISE EXCEPTION 'Cliente não encontrado para o usuário atual.';
        END IF;

        v_first_due := COALESCE(NEW.primeiro_vencimento, NEW.data_vencimento);
        v_count := GREATEST(COALESCE(NEW.quantidade_parcelas, 1), 1);
        v_final_due := CASE
            WHEN v_first_due IS NULL THEN NEW.data_vencimento
            ELSE (v_first_due + ((v_count - 1) * INTERVAL '1 month'))::date
        END;

        UPDATE public.emprestimos
        SET cliente_id = NEW.cliente_id,
            valor_principal = NEW.valor_emprestado,
            taxa_juros = COALESCE(NEW.juros, 0),
            data_vencimento = v_final_due,
            status = COALESCE(NULLIF(NEW.status, ''), status),
            observacoes = NEW.observacoes,
            atualizado_em = NOW()
        WHERE id = OLD.id AND usuario_id = v_user_id;

        NEW.id := OLD.id;
        NEW.usuario_id := v_user_id;
        RETURN NEW;
    ELSE
        DELETE FROM public.transacoes t
        WHERE t.usuario_id = v_user_id
          AND t.origem_tipo = 'parcela_pagamento'
          AND t.origem_id IN (
              SELECT p.id FROM public.parcelas p WHERE p.emprestimo_id = OLD.id
          );

        DELETE FROM public.emprestimos
        WHERE id = OLD.id AND usuario_id = v_user_id;
        RETURN OLD;
    END IF;
END;
$$;

CREATE TRIGGER emprestimos_write
INSTEAD OF INSERT OR UPDATE OR DELETE ON legacy_app.emprestimos
FOR EACH ROW EXECUTE FUNCTION legacy_app.write_emprestimos();

CREATE VIEW legacy_app.parcelas AS
SELECT
    p.id,
    p.emprestimo_id,
    p.numero_parcela,
    p.valor AS valor_parcela,
    p.data_vencimento AS vencimento,
    p.data_pagamento,
    p.valor_pago,
    CASE
        WHEN p.data_pagamento IS NOT NULL THEN 'PAGA'
        WHEN lower(p.status) = 'cancelada' THEN 'CANCELADA'
        WHEN p.data_vencimento < CURRENT_DATE THEN 'ATRASADA'
        WHEN p.data_vencimento <= CURRENT_DATE + 5 THEN 'VENCENDO'
        ELSE 'EM_DIA'
    END AS status,
    (
        SELECT t.observacoes
        FROM public.transacoes t
        WHERE t.usuario_id = e.usuario_id
          AND t.origem_tipo = 'parcela_pagamento'
          AND t.origem_id = p.id
        ORDER BY t.criado_em DESC
        LIMIT 1
    ) AS observacoes,
    p.criado_em,
    p.atualizado_em
FROM public.parcelas p
INNER JOIN public.emprestimos e ON e.id = p.emprestimo_id
WHERE e.usuario_id = legacy_app.current_usuario_id();

CREATE OR REPLACE FUNCTION legacy_app.write_parcelas()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    v_user_id uuid := legacy_app.current_usuario_id();
    v_loan_user_id uuid;
    v_status text;
BEGIN
    IF TG_OP = 'INSERT' THEN
        SELECT e.usuario_id INTO v_loan_user_id
        FROM public.emprestimos e
        WHERE e.id = NEW.emprestimo_id;

        IF v_loan_user_id IS DISTINCT FROM v_user_id THEN
            RAISE EXCEPTION 'Empréstimo não encontrado para o usuário atual.';
        END IF;

        v_status := CASE upper(COALESCE(NEW.status, 'EM_DIA'))
            WHEN 'PAGA' THEN 'paga'
            WHEN 'ATRASADA' THEN 'atrasada'
            WHEN 'CANCELADA' THEN 'cancelada'
            ELSE 'pendente'
        END;

        INSERT INTO public.parcelas (
            emprestimo_id, numero_parcela, valor, data_vencimento,
            data_pagamento, valor_pago, status
        ) VALUES (
            NEW.emprestimo_id, NEW.numero_parcela, NEW.valor_parcela, NEW.vencimento,
            NEW.data_pagamento, NEW.valor_pago,
            CASE WHEN NEW.data_pagamento IS NOT NULL THEN 'paga' ELSE v_status END
        ) RETURNING id INTO NEW.id;

        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        SELECT e.usuario_id INTO v_loan_user_id
        FROM public.emprestimos e
        WHERE e.id = NEW.emprestimo_id;

        IF v_loan_user_id IS DISTINCT FROM v_user_id THEN
            RAISE EXCEPTION 'Empréstimo não encontrado para o usuário atual.';
        END IF;

        v_status := CASE upper(COALESCE(NEW.status, 'EM_DIA'))
            WHEN 'PAGA' THEN 'paga'
            WHEN 'ATRASADA' THEN 'atrasada'
            WHEN 'CANCELADA' THEN 'cancelada'
            ELSE 'pendente'
        END;

        UPDATE public.parcelas
        SET emprestimo_id = NEW.emprestimo_id,
            numero_parcela = NEW.numero_parcela,
            valor = NEW.valor_parcela,
            data_vencimento = NEW.vencimento,
            data_pagamento = NEW.data_pagamento,
            valor_pago = NEW.valor_pago,
            status = CASE
                WHEN NEW.data_pagamento IS NOT NULL THEN 'paga'
                WHEN v_status = 'cancelada' THEN 'cancelada'
                WHEN NEW.vencimento < CURRENT_DATE THEN 'atrasada'
                ELSE 'pendente'
            END,
            atualizado_em = NOW()
        WHERE id = OLD.id;

        IF NEW.data_pagamento IS NOT NULL THEN
            UPDATE public.transacoes
            SET tipo = 'receita',
                descricao = 'Recebimento da parcela ' || NEW.numero_parcela,
                valor = COALESCE(NEW.valor_pago, NEW.valor_parcela),
                data_transacao = NEW.data_pagamento,
                observacoes = NEW.observacoes
            WHERE usuario_id = v_user_id
              AND origem_tipo = 'parcela_pagamento'
              AND origem_id = NEW.id;

            IF NOT FOUND THEN
                INSERT INTO public.transacoes (
                    usuario_id, tipo, descricao, valor, data_transacao,
                    origem_tipo, origem_id, observacoes
                ) VALUES (
                    v_user_id, 'receita', 'Recebimento da parcela ' || NEW.numero_parcela,
                    COALESCE(NEW.valor_pago, NEW.valor_parcela), NEW.data_pagamento,
                    'parcela_pagamento', NEW.id, NEW.observacoes
                );
            END IF;
        ELSE
            DELETE FROM public.transacoes
            WHERE usuario_id = v_user_id
              AND origem_tipo = 'parcela_pagamento'
              AND origem_id = NEW.id;
        END IF;

        NEW.id := OLD.id;
        RETURN NEW;
    ELSE
        DELETE FROM public.transacoes
        WHERE usuario_id = v_user_id
          AND origem_tipo = 'parcela_pagamento'
          AND origem_id = OLD.id;

        DELETE FROM public.parcelas p
        USING public.emprestimos e
        WHERE p.id = OLD.id
          AND e.id = p.emprestimo_id
          AND e.usuario_id = v_user_id;
        RETURN OLD;
    END IF;
END;
$$;

CREATE TRIGGER parcelas_write
INSTEAD OF INSERT OR UPDATE OR DELETE ON legacy_app.parcelas
FOR EACH ROW EXECUTE FUNCTION legacy_app.write_parcelas();

COMMIT;
