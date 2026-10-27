# Tô em Casa Adega & Conveniência — MVP + Gestão

Sistema local em PHP 8+ / MySQL para operação de adega e conveniência, com autenticação, produtos, estoque, PDV, caixa e módulos financeiros e gerenciais.

## Requisitos
- PHP 8.1+
- MySQL/MariaDB com XAMPP
- Apache em execução
- Navegador moderno
- Leitor de código de barras USB configurado para enviar Enter ao final da leitura

## Instalação no XAMPP
1. Copie a pasta do projeto para `C:\xampp\htdocs\to_em_casa`.
2. Inicie Apache e MySQL no XAMPP.
3. Abra o phpMyAdmin e importe `database.sql`.
4. Se o banco já existir, siga a ordem das migrações em `migrations/` e execute sem apagar dados históricos.
5. Ajuste as credenciais em `config.php` se necessário.
6. Acesse `http://localhost/to_em_casa/`.

## Primeiro administrador
1. Acesse `setup_admin.php` quando não existir usuário no sistema.
2. Informe usuário, nome e senha com pelo menos 8 caracteres.
3. Depois do cadastro, acesse `login.php`.
4. Não use senha padrão nem a exponha em código fonte, commits ou documentação pública.

## Perfis e permissões
- `admin`: acesso completo a produtos, estoque, PDV, caixa, financeiro, relatórios e auditoria.
- `vendedor`: acesso ao PDV, caixa, e operações diretamente relacionadas à venda, sem acesso a custos, lucros, relatórios administrativos ou financeiro restrito.

## Migrações SQL e ordem de execução
Na pasta `migrations/`, a ordem correta é:
1. `001_create_users.sql`
2. `002_stock_control.sql`
3. `003_cash_and_pdv.sql`
4. `004_cash_views.sql`
5. `005_financial_and_audit.sql`
6. `006_fix_module_tables.sql`

Use as migrações em ordem porque elas ampliam os campos e tabelas existentes sem apagar registros históricos.

## Backups e restauração
- Backup do banco: use o phpMyAdmin ou `mysqldump`.
- Exemplo: `C:\xampp\mysql\bin\mysqldump.exe --user=root --databases to_em_casa > backup.sql`
- Restauração: `C:\xampp\mysql\bin\mysql.exe --user=root < backup.sql`
- Mantenha os backups fora do diretório do projeto e teste a restauração antes de operar em produção.

## Funcionalidades concluídas
- Autenticação com sessão segura e controle de acesso por perfil.
- Cadastro e edição de produtos com estoque mínimo e alertas.
- Normalização e validação de código de barras para localizar produtos no PDV mesmo com variações de formatação.
- Cadastro de produtos em modal ao clicar em `Produtos`, sem sair da tela atual.
- Entrada e saída de mercadoria com histórico de movimentações.
- PDV com leitor de código de barras, pagamentos divididos, desconto, cálculo automático de troco e validações.
- Pagamento em Pix com geração de QR Code para cobrança a partir da chave configurada no sistema.
- Caixa com abertura, suprimento, sangria e fechamento.
- Módulo financeiro com despesas/receitas, categorias, status e filtros.
- Dashboard gerencial e relatórios em CSV.
- Auditoria de eventos relevantes do sistema.

## Configuração do Pix
- Defina a chave Pix e o nome do recebedor em `config.php`:
  - `PIX_KEY`
  - `PIX_NAME`
- Quando a venda for paga com Pix, o sistema gera o QR Code automaticamente na tela de fechamento da venda.
- O QR Code pode ser usado em apps de pagamento para finalizar a cobrança com rapidez.

## Fluxo de venda com Pagamento
1. Login como administrador ou vendedor.
2. Abra o módulo `PDV`.
3. Escaneie o código de barras ou digite o código.
4. Verifique o total e o desconto.
5. Informe o valor recebido por cada forma de pagamento.
6. O sistema mostra o troco automaticamente.
7. Se o pagamento for Pix, o QR Code aparece ao final da venda.
8. Finalize a venda e confirme o registro no caixa.

## Pendências e limitações
- O sistema é local e não substitui contabilidade fiscal/tributária oficial.
- Não há integração com gateways de pagamento, ERP ou API de bancos.
- O cálculo de lucro estimado é uma aproximação e deve considerar custo dos itens e despesas operacionais.
- O saldo de caixa não deve ser tratado como lucro.
- O projeto não foi validado em ambiente de produção; use apenas em ambiente controlado e com backups.

## Teste integrado recomendado
1. Login como administrador.
2. Cadastrar vendedor.
3. Cadastrar produto com código de barras via modal.
4. Registrar entrada de mercadoria.
5. Abrir caixa.
6. Realizar venda com leitor de código de barras.
7. Pagar por dinheiro e Pix.
8. Emitir/visualizar comprovante.
9. Validar baixa de estoque.
10. Registrar despesa.
11. Fechar caixa e revisar diferença.
12. Consultar relatórios financeiros.
13. Testar acesso administrativo usando vendedor.
14. Registrar cancelamento autorizado e conferir estorno.
15. Verificar histórico de auditoria e movimentações.

## Segurança e consistência
- Sessões e permissões controladas por `auth.php`.
- Senhas protegidas com `password_hash()` / `password_verify()`.
- Validação no servidor em formulários e operações sensíveis.
- Uso de transações em vendas e estoque.
- Auditoria mínima para eventos importantes.
- Evita duplicidade de venda e movimentações de estoque via validação e transação.

## Ações tomadas na etapa 4
- implementação do módulo financeiro;
- criação do dashboard gerencial;
- relatórios por período e exportação CSV;
- auditoria de ações relevantes;
- revisão de segurança e consistência;
- ajustes de documentação e compatibilidade;
- melhoria do cadastro de produtos com modal de criação/edição.

# to_em_casa
