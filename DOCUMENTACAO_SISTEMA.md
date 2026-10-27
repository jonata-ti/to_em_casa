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
- O código de barras é normalizado antes de salvar e consultar, evitando falhas por espaço, ponto ou hífen.
- O estoque pode ser aumentado ou reduzido.
- Movimentações são registradas com antes/depois e responsável.
- Estoque insuficiente bloqueia venda.
- O cadastro pode ser realizado por modal, acessado pelo menu de `Produtos` com `?open=1`.

### 4. PDV
- O vendedor pode processar vendas cadastradas com código de barras.
- A venda valida total, desconto, pagamentos e estoque.
- A transação é feita em banco para evitar inconsistência.
- O pagamento pode ser dividido entre formas diferentes.
- O sistema calcula automaticamente o troco com base no valor recebido.
- Se o pagamento for Pix, o sistema gera o QR Code da cobrança ao finalizar a venda.

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

## Configuração do Pix
- Ajuste a chave Pix e o nome do recebedor em `config.php`.
- Exemplo:
  - `PIX_KEY` = chave Pix da conta
  - `PIX_NAME` = nome que aparece no QR Code
- Quando o cliente paga com Pix, o QR Code é exibido automaticamente na tela após a venda ser finalizada.

## Fluxo de venda recomendado
1. Login como administrador ou vendedor.
2. Abra o módulo `PDV`.
3. Escaneie o código de barras do produto ou digite o código.
4. Confirme o total e aplique desconto, se necessário.
5. Informe o valor recebido em cada forma de pagamento.
6. Verifique o troco calculado automaticamente.
7. Se a forma for Pix, o QR Code será gerado na tela.
8. Finalize a venda e consulte o histórico de caixa.

## Atualizações registradas
- Implementado módulo financeiro.
- Criado dashboard gerencial.
- Adicionados relatórios com filtros e exportação CSV.
- Criada auditoria de ações importantes.
- Padronização dos menus de navegação.
- Implementado painel administrativo de usuários com criação, edição e ativação.
- Ajustado cadastro de produtos em modal para agilizar a rotina de atendimento.
- Normalização do código de barras para evitar falhas na busca do PDV.
- Cálculo automático de troco em vendas.
- Geração de QR Code Pix na finalização da venda.
- Atualização desta documentação.

## Observações finais
Este sistema foi pensado para uso local e acompanhamento operacional, não como sistema fiscal oficial. Ele deve ser usado com controle de qualidade, backup e validação de processos antes de operação crítica.
