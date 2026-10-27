# Tô em Casa Adega & Conveniência — MVP

MVP local em PHP 8+ / MySQL para cadastro de produtos e venda por leitor de código de barras.

## Requisitos
- PHP 8.1+ com PDO MySQL
- MySQL/MariaDB (XAMPP recomendado para desenvolvimento)
- Navegador moderno
- Leitor de código de barras USB configurado para enviar Enter após a leitura (modo teclado)

## Instalação
1. Extraia `to_em_casa_sistema.zip` em `C:\xampp\htdocs\to_em_casa`.
2. Inicie Apache e MySQL pelo XAMPP.
3. Importe `database.sql` no phpMyAdmin.
4. Edite `config.php` e confira usuário/senha do MySQL.
5. Acesse `http://localhost/to_em_casa/`.
6. Cadastre os produtos em **Produtos** e depois use **PDV** para ler o código de barras.

## Observações importantes
- Este é um MVP inicial, não um sistema fiscal/contábil completo.
- Antes de usar em produção, configure HTTPS, backups, usuários/perfis, auditoria reforçada e testes de restauração.
- Os pagamentos do MVP registram forma e valor informados pelo operador; não há integração automática com bancos ou adquirentes.
- O leitor funciona como teclado. Coloque o foco no campo de código de barras e configure o leitor para acrescentar Enter ao final da leitura.
