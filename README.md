Estou deixando aqui uma documentação do que foi desenvolvido até agora
no Nymbrio, principalmente na parte de backend e banco de dados.

A ideia é que todo mundo consiga entender o que já foi feito, como o
sistema funciona, onde mexer e quais cuidados precisamos ter para não
acabar quebrando algo que já está funcionando.

## 1. O que foi feito até agora?

Peguei a estrutura que já tínhamos e trabalhei na integração do backend
com o banco de dados PostgreSQL, conectando também as principais telas
do frontend.

O objetivo foi deixar de ter apenas uma interface e começar a ter um
sistema realmente funcional, com dados sendo cadastrados, consultados e
atualizados no banco.

As principais coisas que já implementei foram:

-   Sistema de cadastro e login de usuários.
-   Autenticação por token.
-   Separação dos dados de cada usuário.
-   Cadastro, consulta, edição e desativação de clientes.
-   Simulação e criação de empréstimos.
-   Geração automática das parcelas.
-   Registro de pagamentos completos e parciais.
-   Cálculo de juros e saldo devedor.
-   Integração do Dashboard com os dados reais.
-   Integração da área de relatórios.
-   Conexão do PHP com o PostgreSQL usando PDO.
-   Regras de segurança para impedir que um usuário acesse os dados de
    outro.

Também foram feitos testes de cadastro, autenticação, isolamento entre
usuários, clientes, empréstimos e recebimentos.

## 2. Tecnologias que estamos usando

  Tecnologia               Para que usamos
  ------------------------ ----------------------------
  PHP 8.2                  Backend
  PostgreSQL 18            Banco de dados
  PDO                      Conexão do PHP com o banco
  HTML, CSS e JavaScript   Frontend
  Bootstrap 5.3            Componentes e layout
  Bootstrap Icons          Ícones
  Apache / XAMPP           Ambiente local
  Git e GitHub             Versionamento do projeto

Não estamos usando Laravel nem outro framework PHP. A estrutura foi
feita em PHP, separando as responsabilidades por pastas.

## 3. Como o sistema está organizado?

O fluxo funciona mais ou menos assim:

```
Usuário
  |
  v
Frontend
  |
  v
API
  |
  v
Controllers
  |
  v
Services
  |
  v
Repositories
  |
  v
PostgreSQL
```

Resumindo:

-   **Frontend:** é a parte visual, onde o usuário interage.
-   **API:** recebe as requisições do frontend.
-   **Controllers:** recebem e organizam essas requisições.
-   **Services:** ficam com as regras de negócio e os cálculos.
-   **Repositories:** fazem as consultas e operações no banco.
-   **PostgreSQL:** armazena os dados e aplica regras de segurança e
    integridade.

A ideia dessa separação é evitar que toda a lógica fique misturada em um
único arquivo.

## 4. Onde mexer?

As pastas principais estão separadas por função. Os nomes exatos podem
variar um pouco dependendo da versão que estiver no repositório.

  Pasta                 O que encontramos nela
  --------------------- --------------------------------
  `api/`                Entrada e organização da API
  `app/Controllers/`    Controle das requisições
  `app/Helpers/`        Funções auxiliares
  `app/Middleware/`     Autenticação e verificações
  `app/Repositories/`   Consultas e operações no banco
  `app/Services/`       Regras de negócio
  `app/Validators/`     Validação dos dados
  `assets/css/`         Estilos da interface
  `config/`             Configurações e conexão
  `database/`           Scripts relacionados ao banco
  `includes/`           Componentes compartilhados
  `pages/`              Páginas do sistema
  `routes/`             Rotas da aplicação

### Se você trabalha no frontend

Pode focar principalmente em:

-   `pages/`
-   `includes/`
-   `assets/css/`
-   Arquivos JavaScript da interface.

Pode continuar melhorando o visual, responsividade, formulários, tabelas
e navegação.

Só é importante conferir como cada tela conversa com a API. Os dados
devem vir do backend, e não de valores fictícios colocados diretamente
na página.

**Importante:** a ideia é preservar o visual que já temos. Não precisa
refazer a interface do zero.

### Se você trabalha no backend

O foco fica principalmente em:

-   `api/`
-   `app/Controllers/`
-   `app/Services/`
-   `app/Repositories/`
-   `app/Middleware/`
-   `app/Validators/`
-   `routes/`

É nessa parte que devemos mexer quando precisarmos criar endpoints,
alterar regras, validar informações ou implementar novas
funcionalidades.

### Se você trabalha no banco de dados

O foco fica em:

-   `database/`
-   Scripts SQL.
-   Tabelas.
-   Índices.
-   Views.
-   Funções.
-   Triggers.
-   Políticas de segurança.

Qualquer alteração no banco precisa ser combinada e testada, porque pode
afetar diretamente o backend e o frontend.

## 5. O que já funciona?

### Login e usuários

-   Cadastro de usuários.
-   Login com email e senha.
-   Verificação de senha.
-   Autenticação por token.
-   Consulta dos dados do usuário autenticado.
-   Isolamento dos dados de cada usuário.

### Clientes

-   Cadastrar clientes.
-   Listar clientes.
-   Consultar um cliente específico.
-   Atualizar informações.
-   Desativar clientes.
-   Consultar clientes inativos quando necessário.

A exclusão é lógica: o cliente é desativado, mas seus registros
continuam no banco para preservar o histórico.

### Empréstimos

-   Simular empréstimos.
-   Criar empréstimos.
-   Consultar empréstimos.
-   Gerar parcelas automaticamente.
-   Calcular juros e valores totais.
-   Consultar o saldo devedor.

O cálculo implementado utiliza a Tabela Price, com parcelas fixas e
ajuste de arredondamento na última parcela.

### Parcelas e recebimentos

-   Consultar parcelas.
-   Registrar pagamentos.
-   Registrar pagamentos parciais.
-   Controlar o valor que ainda falta pagar.
-   Atualizar o saldo devedor.
-   Acompanhar juros recebidos e amortização.

Nos pagamentos parciais, o valor recebido é direcionado primeiro para os
juros da parcela e depois para a amortização do principal.

O sistema também impede que seja registrado um recebimento maior que o
saldo restante da parcela.

### Dashboard e relatórios

As principais informações dessas telas já estão sendo carregadas com
dados reais do banco, como:

-   Clientes ativos.
-   Empréstimos.
-   Capital emprestado.
-   Total recebido.
-   Saldo a receber.
-   Empréstimos ativos e quitados.
-   Parcelas atrasadas.

## 6. Como funciona o banco?

O banco principal utiliza PostgreSQL.

O banco de homologação atual se chama:

`finplan_homologacao`

O schema utilizado continua sendo:

`finplan`

Mesmo com a mudança do nome FinPlan para Nymbrio, mantivemos esses
identificadores internos para não quebrar a estrutura que já estava
implementada.

### Segurança dos dados

Uma parte importante do trabalho foi configurar o isolamento entre
usuários.

Isso significa que cada pessoa deve conseguir acessar somente os
próprios dados. Essa proteção também é aplicada no banco por meio de
políticas RLS (Row-Level Security).

A aplicação utiliza a role `finplan_app`, que possui permissões
limitadas.

**Não alterem as políticas RLS, permissões ou funções de contexto sem
verificar o impacto.** Uma mudança errada pode permitir acesso indevido
aos dados ou impedir que o sistema funcione.

Outro detalhe: as operações autenticadas precisam iniciar o contexto do
usuário no banco usando a função `fn_iniciar_contexto`. O ID utilizado
deve vir da sessão autenticada no backend, nunca de um valor enviado
livremente pelo navegador.

### Regras financeiras

Atualmente, temos:

-   Tabela Price.
-   Juros calculados sobre o saldo devedor.
-   Parcelas fixas.
-   Ajuste de arredondamento na última parcela.
-   Pagamentos parciais.
-   Recebimentos alocados primeiro aos juros e depois ao principal.

Ainda não estão implementados recursos como SAC, multas, juros de mora,
amortização extraordinária e carência. Não considerem essas
funcionalidades prontas.

## 7. API: o que já existe?

A API fica disponível pelo caminho `/api`.

No ambiente local, o endereço base é:

`http://localhost/SistemaFinanceiro/api`

Algumas das operações já implementadas:

  Método   Endpoint                        O que faz
  -------- ------------------------------- ------------------------------------------
  GET      `/health`                       Verifica se backend e banco estão online
  POST     `/auth/register`                Cadastra usuário
  POST     `/auth/login`                   Faz login
  GET      `/auth/me`                      Consulta usuário autenticado
  GET      `/clientes`                     Lista clientes
  POST     `/clientes`                     Cadastra cliente
  GET      `/clientes/{id}`                Consulta cliente
  PATCH    `/clientes/{id}`                Atualiza cliente
  DELETE   `/clientes/{id}`                Desativa cliente
  POST     `/emprestimos/simular`          Simula empréstimo
  POST     `/emprestimos`                  Cria empréstimo
  GET      `/emprestimos/{id}`             Consulta empréstimo
  GET      `/emprestimos/{id}/parcelas`    Consulta parcelas
  POST     `/parcelas/{id}/recebimentos`   Registra recebimento

As rotas e os métodos devem ser conferidos no código antes de criar uma
integração nova. As operações protegidas precisam de autenticação.

O token é enviado no cabeçalho da requisição:

``` http
Authorization: Bearer SEU_TOKEN
```

## 8. Como rodar o projeto no computador?

### O que precisa estar instalado

-   XAMPP com Apache.
-   PHP 8.2 ou compatível.
-   PostgreSQL.
-   Extensão `pdo_pgsql` habilitada no PHP.
-   VS Code.
-   Git.

### Passo 1 --- Colocar o projeto no XAMPP

A pasta deve ficar em:

``` text
C:\xampp\htdocs\SistemaFinanceiro
```

### Passo 2 --- Configurar o `.env`

Cada pessoa deve criar seu próprio `.env`, usando o `.env.example` como
referência.

As variáveis de conexão com o banco são:

``` env
FINPLAN_DB_HOST=127.0.0.1
FINPLAN_DB_PORT=5432
FINPLAN_DB_NAME=finplan_homologacao
FINPLAN_DB_USER=finplan_app
FINPLAN_DB_PASS=SUA_SENHA_LOCAL
```

A senha deve ser fornecida pelo responsável pelo ambiente. Não coloquem
credenciais reais no GitHub nem enviem senhas no grupo.

### Passo 3 --- Conferir o PHP

No terminal, executem:

``` bash
php -v
```

Depois:

``` bash
php -m
```

Confiram se aparecem:

``` text
PDO
pdo_pgsql
```

Se `pdo_pgsql` não aparecer, será necessário habilitar a extensão no PHP
utilizado.

### Passo 4 --- Iniciar os serviços

Abram o XAMPP e iniciem o Apache. Também confiram se o PostgreSQL está
rodando.

### Passo 5 --- Abrir o sistema

Acessem:

`http://localhost/SistemaFinanceiro`

Para testar o backend:

`http://localhost/SistemaFinanceiro/api/health`

A resposta deve indicar que o backend e o banco estão online.

## 9. Como adicionar ou modificar alguma coisa?

Antes de sair alterando arquivos, tentem seguir esta ordem:

1.  Identifiquem o que precisa ser alterado.
2.  Descubram quais arquivos estão envolvidos.
3.  Verifiquem se a mudança afeta frontend, backend ou banco.
4.  Confiram quais endpoints a funcionalidade utiliza.
5.  Façam a alteração na parte responsável.
6.  Testem no ambiente local.
7.  Confiram se as outras funcionalidades continuam funcionando.
8.  Avisem o grupo sobre o que foi alterado.

### Exemplo: adicionar um campo no cadastro de clientes

Não adianta adicionar somente um campo no formulário.

Precisamos verificar se:

1.  A coluna existe no banco.
2.  A API recebe o campo.
3.  O Controller processa a informação.
4.  O Service valida o valor.
5.  O Repository salva corretamente.
6.  A resposta da API retorna o campo.
7.  O frontend exibe e envia a informação.

Ou seja, uma alteração pode envolver várias partes do projeto.

### Exemplo: criar uma nova funcionalidade

Antes de começar, definam o que ela precisa fazer, quais dados serão
utilizados, quais regras serão aplicadas e quais telas serão afetadas.

Assim evitamos criar algo no frontend que o backend não consegue
atender, ou alterar o banco sem atualizar a aplicação.

## 10. GitHub: como trabalhar sem atrapalhar o restante

A ideia é que cada pessoa trabalhe em sua própria branch e depois envie
as alterações para revisão.

**Evitem trabalhar diretamente na `main`.**

A branch de integração do backend desta etapa é:

`integracao-nymbrio-backend`

Para criar uma branch nova:

``` bash
git switch -c feature/nome-da-funcionalidade
```

Para conferir o que foi alterado:

``` bash
git status
```

Para adicionar as alterações:

``` bash
git add .
```

Para criar um commit:

``` bash
git commit -m "Descrição do que foi feito"
```

Para enviar a branch:

``` bash
git push -u origin feature/nome-da-funcionalidade
```

Depois, abram um Pull Request no GitHub para que as alterações sejam
revisadas antes de entrar na branch principal.

Alguns cuidados:

-   Não enviem o `.env`.
-   Não enviem senhas, tokens ou chaves.
-   Não misturem várias alterações sem relação no mesmo commit.
-   Não sobrescrevam arquivos de outras pessoas sem conversar.
-   Não façam alterações grandes no banco sem avisar o grupo.
-   Sempre testem antes de abrir o Pull Request.

## 11. O que ainda podemos desenvolver?

A estrutura atual já permite continuar evoluindo o sistema. Algumas
ideias para as próximas etapas são:

-   Investimentos.
-   Planejamento financeiro.
-   Controle de receitas e despesas.
-   Categorias e transações.
-   Projetos e metas financeiras.
-   Recorrências.
-   Notificações.
-   Automações.
-   Relatórios mais completos.
-   Exportação de relatórios.
-   Testes automatizados.
-   Preparação para hospedagem.

Essas são possibilidades para as próximas etapas, não funcionalidades
que já estão prontas.

## 12. Resumo do estado atual

### Já implementado e testado

-   [x] Cadastro e login de usuários.
-   [x] Autenticação por token.
-   [x] Isolamento dos dados entre usuários.
-   [x] Integração com PostgreSQL.
-   [x] API de clientes.
-   [x] API de empréstimos.
-   [x] Simulação de empréstimos.
-   [x] Geração de parcelas.
-   [x] Recebimentos completos e parciais.
-   [x] Controle de saldo devedor.
-   [x] Integração do Dashboard.
-   [x] Integração do resumo de relatórios.
-   [x] Integração das principais telas do frontend.
-   [x] Ajustes na tela de login.

### Ainda não implementado ou não validado como concluído

-   [ ] Investimentos.
-   [ ] Planejamento financeiro.
-   [ ] Recorrências.
-   [ ] Notificações.
-   [ ] Automações.
-   [ ] Exportação de relatórios.
-   [ ] Testes automatizados abrangentes.
-   [ ] Implantação em produção.

## 13. Recado final

Basicamente, a parte principal que fiz foi estruturar e integrar o
backend com o banco, fazer as funcionalidades principais trabalharem com
dados reais e deixar a base preparada para continuarmos desenvolvendo.

Agora podemos evoluir o frontend sem precisar reconstruir toda a parte
de backend que já está funcionando.

Só peço que, antes de mexer em alguma coisa mais estrutural, conversem
com quem está responsável por aquela parte. Principalmente quando
envolver banco de dados, autenticação, permissões ou cálculos
financeiros.

A ideia não é cada um fazer uma parte isolada e depois tentar juntar
tudo. É todo mundo trabalhar em cima da mesma estrutura, mantendo as
partes compatíveis.

**Se forem adicionar algo, adicionem em cima do que já existe. Não
precisamos refazer o que já está funcionando.**
