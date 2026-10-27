# Documentação do Sistema Tô em Casa Adega & Conveniência

## Visão geral
Este projeto é um sistema local em PHP + MySQL para gestão de uma adega e conveniência, cobrindo autenticação, cadastro de produtos, controle de estoque, PDV, movimentação de caixa, financeiro, relatórios e auditoria.

## Objetivo
Permitir que a operação do negócio funcione com controle de:
- usuários e permissões;
- produtos e estoque;
- vendas e pagamentos;
- caixa;
- receitas e despesas;
- relatórios gerenciais;
- auditoria de operações importantes.

## Tecnologias
- PHP 8+
- MySQL / MariaDB
- PDO para acesso ao banco
- HTML + CSS + JavaScript nativo
- Sessões PHP
- XAMPP como ambiente local recomendado

## Estrutura inicial do projeto
Arquivos principais:
- `config.php` — configuração da conexão com o banco.
- `auth.php` — sessão e validação de permissões.
- `login.php` — tela de login.
- `logout.php` — encerramento seguro da sessão.
- `setup_admin.php` — criação do primeiro administrador.
- `index.php` — dashboard principal.
- `products.php` — cadastro e busca de produtos, com modal para cadastro/edição.
- `stock_in.php` — entrada de mercadoria.
- `stock_out.php` — saída e ajuste de estoque.
- `stock_history.php` — histórico de movimentações.
- `pos.php` — ponto de venda.
- `cash_register.php` — abertura, suprimento, sangria e fechamento de caixa.
- `financial.php` — financeiro com receitas/despesas.
- `reports.php` — relatórios e exportação CSV.
- `audit.php` — auditoria do sistema.
- `style.css` — estilos visuais e layout.
- `database.sql` — esquema principal.
- `migrations/` — migrações SQL compatíveis para evoluir o banco sem apagar dados.

## Módulos e regras de negócio

### 1. Autenticação e permissões
- Usuários possuem perfil `admin` ou `vendedor`.
- O login valida usuário e senha com `password_hash()` e `password_verify()`.
- Sessões usam cookies seguros e `session_regenerate_id()`.
- Páginas restritas usam `requireRole([...])`.
- Usuários sem permissão recebem página 403.
- O administrador pode gerenciar usuários pelo painel `users.php` com criação, edição, ativação e desativação.

### 2. Gestão de usuários
- A criação e atualização de usuários ficam centralizadas em `auth.php` por meio de funções reutilizáveis.
- O painel `users.php` permite:
  - criar usuários novos;
  - editar nome, usuário, perfil e senha;
  - ativar/desativar usuários sem excluir registros;
  - manter auditoria de ações com `logAudit()`.
- A senha é validada com mínimo de 8 caracteres e armazenada em hash.

### 3. Produtos e estoque
- Produtos têm código de barras, nome, categoria, custo, venda, estoque mínimo e validade.
- O estoque pode ser aumentado ou reduzido.
- Movimentações são registradas com antes/depois e responsável.
- Estoque insuficiente bloqueia venda.
- O cadastro pode ser realizado por modal, acessado pelo menu de `Produtos` com `?open=1`.

### 4. PDV
- O vendedor pode processar vendas cadastradas com código de barras.
- A venda valida total, desconto, pagamentos e estoque.
- A transação é feita em banco para evitar inconsistência.
- O pagamento pode ser dividido entre formas diferentes.

### 5. Caixa
- O caixa pode ser aberto por usuário.
- Há operação de suprimento e sangria.
- O fechamento calcula a diferença entre valor contado e esperado.
- A movimentação do caixa fica registrada.

### 6. Financeiro
- Receitas e despesas são lançadas com categoria, valor, data, vencimento e status.
- Status possíveis: pendente, pago, recebido, vencido e cancelado.
- O módulo permite acompanhar contas a receber e a pagar.

### 7. Dashboard e relatórios
- O painel mostra faturamento, ticket médio, despesas, contas vencidas e produtos em destaque.
- Os relatórios incluem filtros por período e exportação em CSV.
- O faturamento não deve ser confundido com lucro.

### 8. Auditoria
- Registra eventos de login, produto, estoque, vendas, caixa e financeiros.
- Guarda usuário responsável, operação, entidade e detalhe da ação.
- Não armazena senha em claro ou dados sensíveis extras.

## Perfis e acessos
### Administrador
Pode acessar:
- produtos
- estoque
- entrada e saída de itens
- histórico
- PDV
- caixa
- financeiro
- relatórios
- auditoria
- painel de usuários

### Vendedor
Pode acessar:
- PDV
- caixa relacionado à operação de venda
- somente módulos diretamente usados no atendimento

Não pode acessar:
- relatórios administrativos
- custos e lucros restritos
- auditoria
- painel de usuários
- módulos financeiros sensíveis

## Menus do sistema
A navegação principal foi padronizada com estrutura de menu em todos os módulos. Os itens principais incluem:
- Início
- Produtos
- PDV
- Caixa
- Financeiro
- Relatórios
- Auditoria
- Usuários (somente admin)
- Usuário e Sair

## Migrações SQL e ordem de execução
Na pasta `migrations/`, a ordem correta é:
1. `001_create_users.sql`
2. `002_stock_control.sql`
3. `003_cash_and_pdv.sql`
4. `004_cash_views.sql`
5. `005_financial_and_audit.sql`
6. `006_fix_module_tables.sql`

## Atualizações registradas
- Implementado módulo financeiro.
- Criado dashboard gerencial.
- Adicionados relatórios com filtros e exportação CSV.
- Criada auditoria de ações importantes.
- Padronização dos menus de navegação.
- Implementado painel administrativo de usuários com criação, edição e ativação.
- Ajustado cadastro de produtos em modal para agilizar a rotina de atendimento.
- Atualização desta documentação.

## Observações finais
Este sistema foi pensado para uso local e acompanhamento operacional, não como sistema fiscal oficial. Ele deve ser usado com controle de qualidade, backup e validação de processos antes de operação crítica.
