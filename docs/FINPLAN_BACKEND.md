# Nymbrio — primeira integração do backend

## O que foi implementado

- Conexão PDO independente para o banco Nymbrio usando `FINPLAN_DB_*` e a role `finplan_app`.
- Cadastro por `finplan.fn_criar_usuario`, com hash produzido por `password_hash()`.
- Login por `finplan.fn_autenticar`, verificação por `password_verify()` e registro por `finplan.fn_registrar_login()`.
- Token Bearer assinado pela chave `APP_KEY`.
- Middleware que valida assinatura, expiração e se a conta continua ativa.
- Consulta do próprio perfil usando `finplan.fn_iniciar_contexto()` e `finplan.vw_meu_perfil`.
- Health check da API e repositórios passam a usar a conexão Nymbrio.

## Configuração local

1. Preserve seu `.env` atual. Não o substitua pelo `.env.example`.
2. Acrescente ao `.env` as variáveis `FINPLAN_DB_HOST`, `FINPLAN_DB_PORT`, `FINPLAN_DB_NAME`, `FINPLAN_DB_USER` e `FINPLAN_DB_PASS`.
3. Use `FINPLAN_DB_NAME=finplan_homologacao` e `FINPLAN_DB_USER=finplan_app` durante a homologação.
4. Informe em `FINPLAN_DB_PASS` a senha local que foi definida para `finplan_app` no PostgreSQL. Não a coloque no Git ou em arquivos compartilhados.
5. Configure `APP_KEY` com uma chave aleatória forte. Exemplo para gerar uma chave no terminal PHP: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.
6. Verifique se a extensão `pdo_pgsql` está habilitada no PHP usado pelo Apache.

As variáveis `DB_*` continuam reservadas à camada antiga do frontend, que ainda depende de `legacy_app`. Não as troque para `finplan_homologacao` nesta etapa: `config/conexao.php` usa a camada de compatibilidade antiga e deixaria as telas existentes indisponíveis. A migração das telas para o schema Nymbrio será feita módulo por módulo.

## Rotas de autenticação

Base local: `http://localhost/SistemaFinanceiro/api`

- `GET /api/health`
- `POST /api/auth/register` — JSON com `nome`, `email`, `senha`.
- `POST /api/auth/login` — JSON com `email`, `senha`.
- `GET /api/auth/me` — cabeçalho `Authorization: Bearer <token>`.
- `POST /api/auth/logout` — cabeçalho `Authorization: Bearer <token>`.

## Observação sobre logout

Os tokens atuais são stateless. O endpoint exige um token válido e instrui o cliente a removê-lo, mas ainda não existe tabela de sessões/revogação no schema; portanto, o token não é revogado no servidor e continua válido até expirar. Para logout com revogação imediata, será necessária uma migração dedicada para sessões ou tokens revogados.

## Contexto RLS obrigatório

Toda operação autenticada que consultar ou alterar tabelas protegidas deve executar uma transação e chamar `finplan.fn_iniciar_contexto(:user_id)` como primeira instrução SQL da transação. Use `FinPlanDatabase::runAsUser($userId, $callback)` para esse padrão. O ID deve vir do token validado, nunca do corpo ou query string da requisição.

## Limite desta etapa

A API de autenticação e a conexão nova foram implementadas. As telas PHP atuais ainda utilizam a camada legada `legacy_app`; elas não foram migradas para as tabelas Nymbrio nesta entrega. Nenhuma conexão real ao PostgreSQL do computador local foi executada a partir deste ambiente. Faça os testes indicados abaixo no XAMPP antes de considerar esta etapa integrada.
