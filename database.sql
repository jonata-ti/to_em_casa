CREATE DATABASE IF NOT EXISTS to_em_casa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE to_em_casa;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barcode VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  category_id INT UNSIGNED NULL,
  brand VARCHAR(100) NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'un',
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  min_stock DECIMAL(12,3) NOT NULL DEFAULT 0,
  expires_on DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_products_name (name),
  INDEX idx_products_active (active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  total DECIMAL(12,2) NOT NULL,
  status ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sale_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_name VARCHAR(160) NOT NULL,
  barcode VARCHAR(64) NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
  CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_sale_items_sale (sale_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sale_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id BIGINT UNSIGNED NOT NULL,
  method ENUM('dinheiro','pix','debito','credito','outro') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_sale_payments_sale FOREIGN KEY (sale_id) REFERENCES sales(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('initial','sale','adjustment_in','adjustment_out') NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  reference_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_stock_product_date (product_id, created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name) VALUES
('Cervejas'),('Destilados'),('Refrigerantes'),('Água'),('Gelo'),
('Snacks'),('Doces e chocolates'),('Conveniência'),('Outros');

CREATE TABLE IF NOT EXISTS cash_registers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  opened_by_user_id BIGINT UNSIGNED NOT NULL,
  opened_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL,
  expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  counted_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  difference_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  CONSTRAINT fk_cash_registers_user FOREIGN KEY (opened_by_user_id) REFERENCES users(id),
  INDEX idx_cash_registers_status (status),
  INDEX idx_cash_registers_user (opened_by_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cash_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cash_register_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('opening','sale','supply','sangria','cancelled_sale','closing') NOT NULL,
  related_sale_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  reason VARCHAR(160) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cash_movements_register FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id),
  CONSTRAINT fk_cash_movements_sale FOREIGN KEY (related_sale_id) REFERENCES sales(id) ON DELETE SET NULL,
  CONSTRAINT fk_cash_movements_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_cash_movements_register (cash_register_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id BIGINT UNSIGNED NOT NULL,
  ticket_number VARCHAR(64) NOT NULL,
  printed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ticket_number (ticket_number),
  CONSTRAINT fk_payment_tickets_sale FOREIGN KEY (sale_id) REFERENCES sales(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS financial_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('income','expense') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_financial_category_name (name, type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS financial_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entry_type ENUM('income','expense') NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  description VARCHAR(180) NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  entry_date DATE NOT NULL,
  due_date DATE NULL,
  status ENUM('pending','paid','received','overdue','cancelled') NOT NULL DEFAULT 'pending',
  payment_method VARCHAR(40) NOT NULL DEFAULT 'outro',
  reference_key VARCHAR(120) NULL,
  related_entity_type VARCHAR(40) NULL,
  related_entity_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  cancelled_at TIMESTAMP NULL,
  cancel_reason VARCHAR(255) NULL,
  CONSTRAINT fk_financial_category FOREIGN KEY (category_id) REFERENCES financial_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_financial_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_financial_type (entry_type),
  INDEX idx_financial_status (status),
  INDEX idx_financial_date (entry_date),
  INDEX idx_financial_reference (reference_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  details TEXT NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_type (event_type),
  INDEX idx_audit_entity (entity_type, entity_id),
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
