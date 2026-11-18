# Nymbrio — interface web integrada ao PostgreSQL

## O que mudou

- O `index.php` agora autentica pela conta Nymbrio (`finplan_app`) e não inicializa a conexão de compatibilidade legada.
- O login web utiliza o mesmo `AuthService` e o mesmo token HMAC da API. O token fica no servidor, dentro da sessão PHP; o navegador recebe somente o cookie de sessão `HttpOnly`.
- As telas de clientes e empréstimos utilizam `ClientService` e `LoanService`, já homologados pela API.
- Cada leitura ou gravação de dados de usuário chama `FinPlanDatabase::runAsUser()`, que abre transação e executa `finplan.fn_iniciar_contexto()` antes das consultas protegidas por RLS.
- Os formulários POST usam token CSRF.
- O layout usa o CSS e Bootstrap já presentes no projeto; não foi redesenhado.

## Telas integradas

- Dashboard com indicadores do PostgreSQL.
- Clientes: listar, pesquisar, criar, visualizar, editar e desativar.
- Empréstimos: listar, criar, visualizar e cancelar quando permitido pelo banco.
- Parcelas: listar por contrato, consultar situação e registrar recebimentos parciais ou totais.
- Relatórios: resumo consolidado pelos dados das views.

## Instalação local

1. Faça backup da pasta atual `SistemaFinanceiro`.
2. Extraia o pacote atualizado sobre uma cópia da pasta.
3. Preserve o `.env` local. Não publique o `.env` nem envie suas senhas.
4. Confira `FINPLAN_DB_HOST`, `FINPLAN_DB_PORT`, `FINPLAN_DB_NAME`, `FINPLAN_DB_USER` e `FINPLAN_DB_PASS` no `.env`.
5. Confirme que `APP_KEY` é uma chave aleatória forte e privada.
6. Inicie Apache no XAMPP e abra `http://localhost/SistemaFinanceiro/`.
7. Entre com o e-mail e senha cadastrados no Nymbrio.

As variáveis `DB_*` antigas e arquivos de compatibilidade foram mantidos no pacote por enquanto, mas as telas principais integradas neste passo não os utilizam. Não execute os scripts antigos de schema durante esta atualização.

## Importante

- Este pacote integra as telas principais ao banco Nymbrio v3; não significa que todos os módulos antigos foram migrados. Telas de planejamento/IA e scripts legados ainda exigem uma etapa própria de revisão.
- O logout remove o token da sessão web do servidor. Tokens emitidos para clientes externos da API permanecem válidos até expirar, pois a revogação global de JWT ainda não foi implementada.
- O módulo de contratos usa Tabela Price. SAC não está implementado no schema atual.
- O endpoint e as telas web usam a role `finplan_app`; nunca configure o PHP para conectar como `postgres` ou `finplan_owner`.
- Homologar novamente em ambiente local: login/logout, listagem/cadastro/edição de cliente, criação de empréstimo, recebimentos e permissões RLS.
