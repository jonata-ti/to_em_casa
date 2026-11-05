# Manual de Uso
# Tô em Casa Adega & Conveniência

## 1. Introdução

O sistema Tô em Casa foi desenvolvido para atender às necessidades de gestão operacional de uma adega e conveniência, com foco em controle de estoque, vendas, caixa, financeiro, relatórios e auditoria.

Seu objetivo é centralizar as operações do negócio em uma solução local, simples de operar e segura para uso diário.

Este manual descreve o funcionamento dos módulos, os procedimentos principais e a rotina recomendada para uso do sistema.

## 2. Acesso ao sistema

### 2.1. Pré-requisitos

Antes de iniciar, confirme que:

- o XAMPP está instalado e em execução;
- o Apache e o MySQL estão ativos;
- o projeto foi copiado para a pasta `C:\xampp\htdocs\to_em_casa`;
- o banco foi importado corretamente;
- o arquivo `config.php` contém as credenciais válidas do ambiente.

### 2.2. Acesso inicial

Para acessar o sistema, utilize a URL:

`http://localhost/to_em_casa/`

Se ainda não houver usuário cadastrado, acesse a página de cadastro inicial:

`setup_admin.php`

### 2.3. Login

A tela de autenticação solicita:

- usuário;
- senha.

Após a validação, o usuário entra no painel principal do sistema.

### 2.4. Perfis de acesso

O sistema possui dois perfis principais:

- `admin`: acesso completo ao sistema, incluindo gestão de usuários, estoque, caixa, financeiro, relatórios e auditoria.
- `vendedor`: acesso restrito às operações de venda, caixa e tarefas diretamente relacionadas ao atendimento.

## 3. Estrutura do sistema

Os principais módulos do sistema são:

- `index.php` — dashboard e visão geral do negócio;
- `products.php` — cadastro e manutenção de produtos;
- `stock_in.php` — entrada de mercadoria;
- `stock_out.php` — saída e ajuste de estoque;
- `stock_history.php` — histórico de movimentações de estoque;
- `pos.php` — ponto de venda e fechamento de pedidos;
- `cash_register.php` — abertura, suprimento, sangria e fechamento do caixa;
- `financial.php` — controle de receitas e despesas;
- `reports.php` — relatórios e exportação de dados;
- `audit.php` — acompanhamento de eventos e auditoria;
- `users.php` — gerenciamento de usuários;
- `login.php` — autenticação;
- `logout.php` — encerramento de sessão;
- `auth.php` — regras de autenticação e permissões.

## 4. Fluxo de utilização recomendado

### 4.1. Primeiro acesso

1. Acesse `setup_admin.php`.
2. Cadastre o primeiro usuário administrador.
3. Informe usuário, nome e senha com no mínimo 8 caracteres.
4. Faça login em `login.php`.

Observação: mantenha a senha em segurança e evite compartilhá-la.

### 4.2. Cadastro de produtos

Para registrar um produto:

1. Acesse o módulo `Produtos`.
2. Clique na opção de cadastro.
3. Informe os dados do produto:
   - código de barras;
   - nome;
   - categoria;
   - custo;
   - preço de venda;
   - estoque mínimo;
   - validade, quando aplicável.
4. Salve as informações.

O sistema valida o código de barras e evita inconsistências na busca durante o atendimento.

### 4.3. Entrada de mercadoria

Quando houver compra ou reposição de estoque:

1. Acesse `Estoque` e escolha `Entrada de mercadoria`.
2. Selecione o produto.
3. Informe a quantidade recebida.
4. Confirme a operação.

Esse processo aumenta o saldo do estoque e registra a movimentação no histórico do sistema.

### 4.4. Ajuste e saída de mercadoria

Para registrar perda, venda, ajuste ou baixa de estoque:

1. Acesse o módulo de saída de estoque.
2. Selecione o produto.
3. Informe a quantidade a ser removida.
4. Confirme a ação.

O sistema bloqueia operações que excedam o estoque disponível e mantém o registro da movimentação.

### 4.5. Histórico de estoque

O módulo de histórico permite:

- verificar entradas e saídas;
- analisar datas e responsáveis;
- conferir movimentações do dia;
- identificar inconsistências no inventário.

## 5. Módulos do sistema

### 5.1. Dashboard (`index.php`)

O dashboard apresenta uma visão geral do negócio, incluindo:

- faturamento do período;
- ticket médio;
- despesas;
- contas vencidas;
- vendas recentes;
- indicadores de caixa;
- produtos com maior movimentação.

Essa tela é usada para acompanhamento rápido do desempenho operacional.

### 5.2. Produtos (`products.php`)

Este módulo permite:

- cadastrar novos produtos;
- editar informações existentes;
- buscar produtos por nome, código ou categoria;
- controlar preço de custo e preço de venda;
- ajustar estoque mínimo;
- manter os itens organizados por categoria.

### 5.3. PDV (`pos.php`)

O PDV é a área principal de venda do sistema.

Atividades disponíveis:

- leitura de código de barras;
- busca de produto pelo código;
- ajuste de quantidade;
- aplicação de desconto;
- cálculo automático do total;
- recebimento por múltiplas formas de pagamento;
- cálculo de troco;
- finalização da venda;
- emissão de comprovante para impressão térmica;
- geração de QR Code Pix quando aplicável.

#### Fluxo padrão de venda

1. Acesse o módulo de PDV.
2. Leia ou digite o código do produto.
3. Verifique a quantidade e o valor.
4. Adicione mais itens, se necessário.
5. Informe o valor recebido.
6. Confirme o pagamento.
7. Finalize a venda.
8. O sistema imprime a notinha diretamente para a impressora térmica.

#### Impressão da notinha

Ao concluir a venda, o sistema gera uma janela de impressão com o comprovante do cliente.

A notinha é enviada diretamente para a impressora térmica, sem ser exibida na tela do cliente durante a operação. Essa abordagem melhora a experiência de atendimento e reduz a exposição do conteúdo em telas públicas durante a venda.

#### Pagamentos

O sistema aceita diferentes formas de pagamento, como:

- dinheiro;
- cartão de débito;
- cartão de crédito;
- Pix;
- pagamento dividido entre diferentes métodos.

### 5.4. Caixa (`cash_register.php`)

Este módulo gerencia o movimento financeiro do caixa da loja, permitindo:

- abertura do caixa;
- suprimento;
- sangria;
- fechamento do caixa;
- comparação entre valor contado e valor esperado;
- análise da diferença final.

### 5.5. Financeiro (`financial.php`)

O módulo financeiro controla:

- receitas;
- despesas;
- categorias;
- status de pagamento;
- contas a pagar e receber;
- registros financeiros por período.

É usado para monitorar a saúde financeira da operação e acompanhar pendências.

### 5.6. Relatórios (`reports.php`)

Este módulo oferece relatórios e filtros para análise do desempenho do negócio, incluindo:

- faturamento por período;
- movimento financeiro;
- histórico de vendas;
- exportação em CSV;
- indicadores operacionais.

### 5.7. Usuários (`users.php`)

A gestão de usuários permite:

- criação de novos usuários;
- alteração de dados cadastrais;
- troca de perfil;
- ativação ou desativação de contas;
- redefinição de senha;
- registro de ações executadas no sistema.

### 5.8. Auditoria (`audit.php`)

A auditoria registra as principais ações realizadas no sistema, como:

- login e logout;
- alterações em produtos;
- movimentações de estoque;
- vendas;
- abertura e fechamento de caixa;
- eventos financeiros.

Essa funcionalidade é essencial para rastreabilidade e segurança operacional.

## 6. Boas práticas de uso

- mantenha o estoque sempre atualizado;
- confira as movimentações antes de fechar o caixa;
- utilize os perfis de acesso corretamente;
- revise os relatórios com frequência;
- faça backup do banco regularmente;
- não compartilhe senhas e mantenha o controle de acesso;
- confirme a impressão da notinha antes de concluir a operação final.

## 7. Segurança e controle

O sistema utiliza autenticação e autorização para garantir que cada usuário acesse apenas os módulos adequados ao seu perfil.

As principais medidas incluem:

- validação de usuário e senha;
- restrição de acesso por perfil;
- auditoria de ações importantes;
- controle de movimentações financeiras e de estoque;
- utilização de sessão segura para manter o usuário autenticado.

## 8. Resumo operacional

O sistema foi pensado para facilitar a rotina de uma adega e conveniência, oferecendo:

- controle eficiente de estoque;
- agilidade no atendimento no PDV;
- organização do caixa;
- visão financeira clara;
- rastreio de ações e movimentações;
- maior confiabilidade no processo de gestão.

## 9. Considerações finais

O Tô em Casa é uma ferramenta de apoio operacional para gestão local do negócio. Para manter a confiabilidade das informações, é recomendável realizar processos regulares de conferência, controle do estoque, fechamento de caixa e backup do banco.

Com uso correto, o sistema contribui para uma operação mais organizada, segura e eficiente.
