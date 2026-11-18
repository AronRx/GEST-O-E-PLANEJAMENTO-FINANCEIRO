# Nymbrio — marca e identificadores técnicos

Nymbrio é o nome comercial exibido na interface, no login, no título das páginas e nas mensagens ao usuário.

Para preservar a instalação já homologada, os identificadores internos do banco e da aplicação permanecem inalterados nesta versão:

- Schema PostgreSQL: `finplan`
- Banco local de homologação: `finplan_homologacao`
- Role PHP: `finplan_app`
- Variáveis de ambiente: `FINPLAN_DB_*`
- Classes internas: `FinPlanDatabase` e `FinPlanWeb`
- Funções PostgreSQL: `finplan.fn_*`

Esses nomes são contratos técnicos usados pelo backend e pelo schema. Renomeá-los exigiria uma migração coordenada do PostgreSQL, das roles, das variáveis do `.env`, do backend e dos scripts de implantação. Esta atualização altera somente a marca apresentada, sem modificar o banco ou as credenciais.
