# Nymbrio API — Empréstimos e parcelas

Base local: `http://localhost/SistemaFinanceiro/api`

Todas as rotas exigem `Authorization: Bearer <access_token>`, exceto chamadas públicas de autenticação e health check. Requisições com corpo devem enviar `Content-Type: application/json`.

## Simular cronograma

`POST /emprestimos/simular`

```json
{
  "valor_principal": "500.00",
  "taxa_juros_mensal": "6.0000",
  "qtd_parcelas": 12,
  "data_emprestimo": "2026-11-17",
  "primeiro_vencimento": "2026-12-17",
  "sistema_amortizacao": "price"
}
```

A API chama `finplan.fn_calcular_cronograma`. A função do banco é a fonte do cálculo. O sistema SAC ainda não está implementado e é recusado pela API.

## Listar empréstimos

`GET /emprestimos?page=1&per_page=20&q=nome&situacao=ativo`

Valores aceitos para `situacao`: `ativo`, `atrasado`, `quitado`, `cancelado`.

## Criar empréstimo

`POST /emprestimos`

```json
{
  "cliente_id": 5,
  "valor_principal": "500.00",
  "taxa_juros_mensal": "6.0000",
  "qtd_parcelas": 12,
  "data_emprestimo": "2026-11-17",
  "primeiro_vencimento": "2026-12-17",
  "sistema_amortizacao": "price",
  "observacoes": "Empréstimo de homologação"
}
```

A API exige que o cliente pertença ao usuário autenticado e esteja ativo. A criação chama `finplan.fn_criar_emprestimo`, que grava o contrato e todas as parcelas em uma transação e valida o cronograma no commit. O backend não calcula as parcelas.

Os identificadores (`cliente_id`, `conta_id`, `parcela_id` e o `{id}` das rotas) aceitam UUID ou inteiro, conforme o tipo real das colunas do schema `finplan`. O PHP normaliza o valor antes de enviá-lo ao banco e não converte UUID para inteiro.

## Consultar empréstimo

`GET /emprestimos/{id}`

## Consultar parcelas

`GET /emprestimos/{id}/parcelas`

Inclui valor contratual, juros, amortização, saldo restante, pagamentos parciais, datas e situação.

## Registrar recebimento de parcela

`POST /parcelas/{id}/recebimentos`

```json
{
  "valor": "59.64",
  "data_recebimento": "2026-12-17",
  "forma_pagamento": "pix",
  "observacoes": "Primeiro recebimento de homologação"
}
```

`conta_id` é opcional. Quando omitido, será utilizada a conta ativa `Carteira` do próprio usuário. Formas aceitas: `dinheiro`, `pix`, `transferencia`, `boleto`, `cartao`, `outro`. O trigger do banco gera a transação financeira correspondente. O banco bloqueia recebimentos acima do valor restante da parcela.

## Cancelar empréstimo

`PATCH /emprestimos/{id}/cancelar`

Sem corpo. O contrato e o cronograma permanecem armazenados; o status passa para `cancelado`.

## Convenções financeiras e limites

- Taxa informada em percentual mensal, não em fração: `6.0000` significa 6% ao mês.
- O sistema implementado nesta versão é Tabela Price.
- A taxa incide sobre o saldo devedor, conforme a função do banco.
- A última parcela ajusta centavos de arredondamento para amortizar exatamente o principal.
- Pagamentos parciais são alocados primeiro a juros e depois a amortização; o cronograma contratual não é recalculado.
- Esta versão não implementa SAC, multa/juros de mora, carência nem amortização extraordinária.
