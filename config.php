<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'to_em_casa';
$user = getenv('DB_USER') ?: 'to_em_casa';
$pass = getenv('DB_PASSWORD') ?: 'm35qu174'; // Configure conforme seu MySQL local.

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Não foi possível conectar ao banco. Confira config.php e o serviço MySQL.');
}

function tableExists(PDO $pdo, string $tableName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $tableName) . '` LIKE ?');
        $stmt->execute([$columnName]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function ensureRequiredSchema(PDO $pdo): void
{
    $createStatements = [
        "CREATE TABLE IF NOT EXISTS users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, password_hash VARCHAR(255) NOT NULL, role ENUM('admin','vendedor') NOT NULL DEFAULT 'vendedor', active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS categories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE, active TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS products (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, barcode VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, category_id INT UNSIGNED NULL, brand VARCHAR(100) NULL, unit VARCHAR(20) NOT NULL DEFAULT 'un', cost_price DECIMAL(12,2) NOT NULL DEFAULT 0, sale_price DECIMAL(12,2) NOT NULL DEFAULT 0, stock_qty INT NOT NULL DEFAULT 0, min_stock INT NOT NULL DEFAULT 0, expires_on DATE NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS sales (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sale_number VARCHAR(32) NULL, total DECIMAL(12,2) NOT NULL, status ENUM('completed','cancelled') NOT NULL DEFAULT 'completed', seller_id BIGINT UNSIGNED NULL, cash_id BIGINT UNSIGNED NULL, discount DECIMAL(12,2) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS sale_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sale_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, product_name VARCHAR(160) NOT NULL, barcode VARCHAR(64) NOT NULL, quantity INT NOT NULL, unit_price DECIMAL(12,2) NOT NULL, unit_cost DECIMAL(12,2) NOT NULL, line_total DECIMAL(12,2) NOT NULL) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS sale_payments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sale_id BIGINT UNSIGNED NOT NULL, method ENUM('dinheiro','pix','debito','credito','outro') NOT NULL, amount DECIMAL(12,2) NOT NULL) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS stock_movements (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL, movement_type ENUM('initial','sale','adjustment_in','adjustment_out') NOT NULL, quantity INT NOT NULL, before_qty INT NOT NULL DEFAULT 0, after_qty INT NOT NULL DEFAULT 0, reference_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NULL, notes VARCHAR(255) NULL, supplier VARCHAR(120) NULL, movement_reason VARCHAR(160) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS cash_registers (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, opened_by_user_id BIGINT UNSIGNED NOT NULL, opened_amount DECIMAL(12,2) NOT NULL DEFAULT 0, status ENUM('open','closed') NOT NULL DEFAULT 'open', opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, closed_at TIMESTAMP NULL, expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0, counted_amount DECIMAL(12,2) NOT NULL DEFAULT 0, difference_amount DECIMAL(12,2) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS cash_movements (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cash_register_id BIGINT UNSIGNED NOT NULL, movement_type ENUM('opening','sale','supply','sangria','cancelled_sale','closing') NOT NULL, related_sale_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(12,2) NOT NULL DEFAULT 0, reason VARCHAR(160) NOT NULL DEFAULT '', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS financial_categories (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, type ENUM('income','expense') NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_financial_category_name (name, type)) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS financial_entries (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, entry_type ENUM('income','expense') NOT NULL, category_id BIGINT UNSIGNED NULL, description VARCHAR(180) NOT NULL, amount DECIMAL(12,2) NOT NULL DEFAULT 0, entry_date DATE NOT NULL, due_date DATE NULL, status ENUM('pending','paid','received','overdue','cancelled') NOT NULL DEFAULT 'pending', payment_method VARCHAR(40) NOT NULL DEFAULT 'outro', reference_key VARCHAR(120) NULL, related_entity_type VARCHAR(40) NULL, related_entity_id BIGINT UNSIGNED NULL, notes VARCHAR(255) NULL, user_id BIGINT UNSIGNED NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, cancelled_at TIMESTAMP NULL, cancel_reason VARCHAR(255) NULL) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_type VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_id BIGINT UNSIGNED NULL, action VARCHAR(80) NOT NULL, details TEXT NULL, user_id BIGINT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS payment_tickets (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sale_id BIGINT UNSIGNED NOT NULL, ticket_number VARCHAR(64) NOT NULL, printed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_ticket_number (ticket_number)) ENGINE=InnoDB",
    ];

    $integerColumns = [
        ['products', 'stock_qty', "ALTER TABLE products MODIFY COLUMN stock_qty INT NOT NULL DEFAULT 0"],
        ['products', 'min_stock', "ALTER TABLE products MODIFY COLUMN min_stock INT NOT NULL DEFAULT 0"],
        ['sale_items', 'quantity', "ALTER TABLE sale_items MODIFY COLUMN quantity INT NOT NULL"],
        ['stock_movements', 'quantity', "ALTER TABLE stock_movements MODIFY COLUMN quantity INT NOT NULL"],
        ['stock_movements', 'before_qty', "ALTER TABLE stock_movements MODIFY COLUMN before_qty INT NOT NULL DEFAULT 0"],
        ['stock_movements', 'after_qty', "ALTER TABLE stock_movements MODIFY COLUMN after_qty INT NOT NULL DEFAULT 0"],
    ];

    foreach ($integerColumns as [$table, $column, $sql]) {
        if (columnExists($pdo, $table, $column)) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // ignora se a coluna já estiver em formato compatível.
            }
        }
    }

    $columnAdds = [
        ['sales', 'sale_number', "ALTER TABLE sales ADD COLUMN sale_number VARCHAR(32) NULL AFTER id"],
        ['sales', 'seller_id', "ALTER TABLE sales ADD COLUMN seller_id BIGINT UNSIGNED NULL AFTER status"],
        ['sales', 'cash_id', "ALTER TABLE sales ADD COLUMN cash_id BIGINT UNSIGNED NULL AFTER seller_id"],
        ['sales', 'discount', "ALTER TABLE sales ADD COLUMN discount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cash_id"],
        ['sales', 'notes', "ALTER TABLE sales ADD COLUMN notes VARCHAR(255) NULL AFTER discount"],
        ['stock_movements', 'before_qty', "ALTER TABLE stock_movements ADD COLUMN before_qty INT NOT NULL DEFAULT 0 AFTER quantity"],
        ['stock_movements', 'after_qty', "ALTER TABLE stock_movements ADD COLUMN after_qty INT NOT NULL DEFAULT 0 AFTER before_qty"],
        ['stock_movements', 'user_id', "ALTER TABLE stock_movements ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER reference_id"],
        ['stock_movements', 'supplier', "ALTER TABLE stock_movements ADD COLUMN supplier VARCHAR(120) NULL AFTER notes"],
        ['stock_movements', 'movement_reason', "ALTER TABLE stock_movements ADD COLUMN movement_reason VARCHAR(160) NULL AFTER supplier"],
        ['cash_registers', 'opened_by_user_id', "ALTER TABLE cash_registers ADD COLUMN opened_by_user_id BIGINT UNSIGNED NOT NULL AFTER id"],
        ['cash_movements', 'related_sale_id', "ALTER TABLE cash_movements ADD COLUMN related_sale_id BIGINT UNSIGNED NULL AFTER movement_type"],
        ['financial_entries', 'due_date', "ALTER TABLE financial_entries ADD COLUMN due_date DATE NULL AFTER entry_date"],
    ];

    foreach ($columnAdds as [$table, $column, $sql]) {
        if (!columnExists($pdo, $table, $column)) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // ignora se a estrutura já foi criada em outra migração.
            }
        }
    }
}

function normalizeBarcode(string $value): string
{
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x1F\x7F\s\-\.]+/u', '', $value);
    return strtoupper($value);
}

define('PIX_KEY', getenv('PIX_KEY') ?: 'SEU_PIX_KEY_AQUI');
define('PIX_NAME', getenv('PIX_NAME') ?: 'TÔ EM CASA');

ensureRequiredSchema($pdo);
