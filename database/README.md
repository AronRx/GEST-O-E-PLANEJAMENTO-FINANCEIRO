# Configuração PostgreSQL do Sistema Financeiro

O pacote inclui em `schema.sql` uma cópia idêntica do arquivo `Gestão e planejamento financeiro/database/schema.sql` enviado pela equipe no repositório, além da camada de compatibilidade `postgresql_compat.sql`. Confirme com a pessoa responsável se o esquema já foi aplicado no banco de destino antes de executar qualquer SQL. A camada `legacy_app` mantém as telas antigas funcionando sem adicionar colunas às tabelas oficiais em `public`.

## Ordem de instalação

1. Confirme com a pessoa responsável pelo banco o nome correto do banco e se o conteúdo de `schema.sql` é a estrutura aprovada pela equipe.
2. Crie o banco PostgreSQL somente depois dessa confirmação. Execute `schema.sql` apenas se a equipe confirmar que ele é o esquema correto e que as tabelas ainda não existem.
3. Execute `postgresql_compat.sql` depois do esquema oficial. Esse script cria views e gatilhos de compatibilidade no schema `legacy_app`.
4. Copie `.env.example` para `.env` na raiz do projeto e configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASS`.
5. Crie pelo menos um usuário ativo em `public.usuarios`. Para criar o primeiro usuário pela linha de comando, execute na pasta do projeto:

   ```powershell
   C:\xampp\php\php.exe database\criar_usuario.php
   ```

6. Se houver mais de um usuário ativo, defina `APP_USER_ID` no `.env` com o UUID do usuário que será dono dos dados das telas antigas. Se houver exatamente um usuário ativo, a aplicação o seleciona automaticamente.
7. Inicie Apache e acesse `http://localhost/SistemaFinanceiro/`.
8. Teste a API em `http://localhost/SistemaFinanceiro/api/health`.

## Mapeamento legado

- `clientes.cpf` é apresentado sobre `public.clientes.documento`.
- `emprestimos.valor_emprestado` e `juros` são apresentados sobre `valor_principal` e `taxa_juros`.
- `quantidade_parcelas`, `valor_total`, `valor_parcela` e `primeiro_vencimento` são calculados a partir de `public.parcelas`.
- `parcelas.valor_parcela` e `vencimento` são apresentados sobre `valor` e `data_vencimento`.
- O esquema oficial não possui campos próprios para RG, WhatsApp e os componentes do endereço. Para preservar esses dados sem alterar as tabelas oficiais, a camada de compatibilidade os serializa em JSON com marcadores dentro dos campos `endereco` e `observacoes`. A aplicação deve acessar esses valores pelas views em `legacy_app`, não diretamente nas tabelas `public`.
- O pagamento de parcela também gera/atualiza uma linha de receita em `public.transacoes`, com `origem_tipo = 'parcela_pagamento'`.

## Importante

- Não execute `schema.sql` por cima de tabelas existentes sem backup.
- Não use o banco `study_planner` identificado na instalação local anterior; ele pertence a outra estrutura.
- O ZIP não contém `.env` real, credenciais, nem a pasta `.git`. Crie seu `.env` local a partir do modelo.
- O backend foi preparado para trabalhar com UUIDs, conforme o esquema oficial.
- A camada `legacy_app` é uma ponte de compatibilidade para as telas existentes. Em uma evolução futura, o ideal é substituir gradualmente as chamadas antigas por repositórios PDO nativos.
