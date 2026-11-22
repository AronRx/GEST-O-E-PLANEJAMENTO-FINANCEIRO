-- 006_backfill_clientes.sql
-- Preenche as colunas criadas na migration 005 a partir dos dados legados
-- já existentes em finplan.clientes. Nenhum dado é apagado ou sobrescrito.
--
-- Formatos legados gravados por database/postgresql_compat.sql:
--   observacoes = '__SF_OBS_V1__'  + JSON {"note": ..., "rg": ..., "whatsapp": ...}
--   endereco    = '__SF_ADDR_V1__' + JSON {"cep": ..., "rua": ..., "numero": ...,
--                                          "bairro": ..., "cidade": ..., "estado": ...}
--
-- Regras:
--   * cpf_cnpj recebe documento somente quando cpf_cnpj IS NULL.
--   * rg/whatsapp recebem os valores do JSON de observacoes somente quando nulos.
--   * cep/rua/numero/bairro/cidade/estado recebem os valores do JSON de endereco
--     somente quando nulos.
--   * Para endereco antigo sem o marcador JSON, preserva-se o texto em rua
--     (equivalente ao comportamento da view legacy_app.clientes) e tenta-se
--     extrair o CEP por regex, novamente apenas em colunas nulas.
--   * As colunas originais (documento, observacoes e endereco) não são alteradas.
--
-- Execute após migrations/005_fix_clientes.sql.
-- A migration é idempotente: pode ser reexecutada sem sobrescrever valores
-- preenchidos manualmente.

BEGIN;

-- 1) CPF/CNPJ: preserva o documento já existente no schema oficial.
UPDATE finplan.clientes
SET cpf_cnpj = documento
WHERE cpf_cnpj IS NULL
  AND NULLIF(btrim(documento), '') IS NOT NULL;

-- 2) RG e WhatsApp: desempacota o JSON legado de observacoes.
UPDATE finplan.clientes AS c
SET rg = COALESCE(c.rg, NULLIF(btrim(p.payload ->> 'rg'), '')),
    whatsapp = COALESCE(c.whatsapp, NULLIF(btrim(p.payload ->> 'whatsapp'), ''))
FROM (
    SELECT id,
           substring(observacoes FROM length('__SF_OBS_V1__') + 1)::jsonb AS payload
    FROM finplan.clientes
    WHERE left(COALESCE(observacoes, ''), length('__SF_OBS_V1__')) = '__SF_OBS_V1__'
      AND (rg IS NULL OR whatsapp IS NULL)
) AS p
WHERE c.id = p.id
  AND (c.rg IS NULL OR c.whatsapp IS NULL);

-- 3) Endereço estruturado: desempacota o JSON legado de endereco.
UPDATE finplan.clientes AS c
SET cep = COALESCE(c.cep, NULLIF(btrim(p.payload ->> 'cep'), '')),
    rua = COALESCE(c.rua, NULLIF(btrim(p.payload ->> 'rua'), '')),
    numero = COALESCE(c.numero, NULLIF(btrim(p.payload ->> 'numero'), '')),
    bairro = COALESCE(c.bairro, NULLIF(btrim(p.payload ->> 'bairro'), '')),
    cidade = COALESCE(c.cidade, NULLIF(btrim(p.payload ->> 'cidade'), '')),
    estado = COALESCE(c.estado, NULLIF(btrim(p.payload ->> 'estado'), ''))
FROM (
    SELECT id,
           substring(endereco FROM length('__SF_ADDR_V1__') + 1)::jsonb AS payload
    FROM finplan.clientes
    WHERE left(COALESCE(endereco, ''), length('__SF_ADDR_V1__')) = '__SF_ADDR_V1__'
      AND (
          cep IS NULL OR rua IS NULL OR numero IS NULL OR
          bairro IS NULL OR cidade IS NULL OR estado IS NULL
      )
) AS p
WHERE c.id = p.id
  AND (
      c.cep IS NULL OR c.rua IS NULL OR c.numero IS NULL OR
      c.bairro IS NULL OR c.cidade IS NULL OR c.estado IS NULL
  );

-- 4) Endereço legado em texto livre: preserva o conteúdo em rua e tenta obter
--    o CEP por regex. Aplica-se apenas quando o marcador JSON não está presente.
UPDATE finplan.clientes
SET rua = COALESCE(rua, NULLIF(btrim(endereco), '')),
    cep = COALESCE(cep, NULLIF(substring(endereco FROM '([0-9]{5}-?[0-9]{3})'), ''))
WHERE endereco IS NOT NULL
  AND btrim(endereco) <> ''
  AND left(endereco, length('__SF_ADDR_V1__')) <> '__SF_ADDR_V1__'
  AND (rua IS NULL OR cep IS NULL);

COMMIT;

-- Verificação pós-migration (opcional):
-- SELECT COUNT(*) AS sem_cpf_cnpj
-- FROM finplan.clientes
-- WHERE cpf_cnpj IS NULL AND NULLIF(btrim(documento), '') IS NOT NULL;
--
-- SELECT COUNT(*) AS observacoes_legadas_pendentes
-- FROM finplan.clientes
-- WHERE left(COALESCE(observacoes, ''), length('__SF_OBS_V1__')) = '__SF_OBS_V1__'
--   AND (rg IS NULL OR whatsapp IS NULL);
--
-- SELECT COUNT(*) AS enderecos_legados_pendentes
-- FROM finplan.clientes
-- WHERE left(COALESCE(endereco, ''), length('__SF_ADDR_V1__')) = '__SF_ADDR_V1__'
--   AND (
--       cep IS NULL OR rua IS NULL OR numero IS NULL OR
--       bairro IS NULL OR cidade IS NULL OR estado IS NULL
--   );
