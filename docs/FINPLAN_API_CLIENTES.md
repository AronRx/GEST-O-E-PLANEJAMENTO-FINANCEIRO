# API de clientes — Nymbrio

Todas as rotas abaixo exigem `Authorization: Bearer <access_token>` e utilizam o usuário autenticado no token. O backend define o contexto RLS no início de cada transação. Não envie `usuario_id` no JSON: ele é obtido exclusivamente da sessão autenticada.

## Listar clientes

`GET /api/clientes?page=1&per_page=20&q=texto&incluir_inativos=false`

Retorna `items` e `pagination`. Por padrão, lista somente clientes ativos. `incluir_inativos=true` inclui clientes desativados.

## Cadastrar cliente

`POST /api/clientes`

JSON:

```json
{
  "nome": "Cliente de Teste",
  "cpf_cnpj": "123.456.789-00",
  "rg": "1234567",
  "telefone": "81999990000",
  "whatsapp": "81999990000",
  "email": "cliente@example.com",
  "cep": "50000-000",
  "rua": "Rua de Teste",
  "numero": "100",
  "bairro": "Centro",
  "cidade": "Recife",
  "estado": "PE",
  "observacoes": "Observação opcional"
}
```

Também aceita `cpf` como alias de `cpf_cnpj` por compatibilidade com o formulário antigo. A unicidade de CPF/CNPJ é aplicada por usuário, desconsiderando caracteres de formatação.

## Visualizar cliente

`GET /api/clientes/{id}`

## Atualizar cliente

`PUT /api/clientes/{id}` ou `PATCH /api/clientes/{id}`

Envie os campos que deseja alterar; os campos omitidos mantêm os valores existentes.

## Excluir cliente

`DELETE /api/clientes/{id}`

A exclusão é lógica: define `ativo=false`, preservando empréstimos, parcelas, recebimentos e histórico financeiro. Para listar desativados, use `incluir_inativos=true`.

## Códigos HTTP

- `200`: consulta, atualização ou desativação concluída.
- `201`: cadastro concluído.
- `401`: token ausente, inválido ou expirado.
- `404`: cliente não encontrado no escopo do usuário autenticado.
- `409`: documento duplicado.
- `422`: dados inválidos.

As respostas mantêm o envelope JSON `success` e `data`/`message` já utilizado pela API.
