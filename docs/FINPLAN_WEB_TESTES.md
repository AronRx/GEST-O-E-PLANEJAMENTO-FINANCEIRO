# Checklist de homologação — interface web Nymbrio

Use este roteiro após instalar a atualização no XAMPP.

1. Acesse `http://localhost/SistemaFinanceiro/` e entre com um usuário cadastrado no banco Nymbrio.
2. Confirme que o dashboard abre e apresenta os dados da conta autenticada.
3. Em Clientes, confirme que o cliente ativo criado na homologação aparece. O cliente desativado não deve aparecer na lista padrão; marque “Incluir inativos” para consultá-lo.
4. Cadastre um cliente pela tela, abra seus detalhes, edite um campo e confira a alteração.
5. Em Empréstimos, abra o contrato de homologação ID 1 e confira os saldos atualizados após os recebimentos realizados.
6. Em Parcelas, confira as parcelas pagas e pendentes. Abra o recebimento de uma parcela com saldo e registre um pagamento de teste apenas se desejar gravar outra movimentação.
7. Clique em Sair e confirme que o sistema retorna à tela de login. Tente abrir `index.php` novamente e confirme que a autenticação é solicitada.

## Dados que já existem na homologação (conforme testes feitos no Postman)

- Cliente ID 4: `Cliente Teste API`, desativado.
- Cliente ID 5: `Cliente Teste Empréstimo`, ativo.
- Empréstimo ID 1: principal R$ 500,00; total contratado R$ 715,67.
- Recebimentos existentes no contrato 1: R$ 119,28 no total.
- Saldo devedor de principal esperado: R$ 438,94.
- Saldo a receber esperado: R$ 596,39.

Os dados acima são dados de homologação já gravados no banco; o pacote não os recria nem os apaga.
