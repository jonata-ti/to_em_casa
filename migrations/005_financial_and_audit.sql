ALTER TABLE sales
  ADD COLUMN IF NOT EXISTS sale_number VARCHAR(32) NULL AFTER id,
  ADD COLUMN IF NOT EXISTS seller_id BIGINT UNSIGNED NULL AFTER status,
  ADD COLUMN IF NOT EXISTS cash_id BIGINT UNSIGNED NULL AFTER seller_id,
  ADD COLUMN IF NOT EXISTS discount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cash_id,
  ADD COLUMN IF NOT EXISTS notes VARCHAR(255) NULL AFTER discount,
  ADD COLUMN IF NOT EXISTS cancelled_by_user_id BIGINT UNSIGNED NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL AFTER cancelled_by_user_id,
  ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) NULL AFTER cancelled_at;

ALTER TABLE sales
  ADD INDEX idx_sales_sale_number (sale_number),
  ADD INDEX idx_sales_seller (seller_id),
  ADD INDEX idx_sales_cash (cash_id),
  ADD INDEX idx_sales_cancelled_by (cancelled_by_user_id);

ALTER TABLE sales
  ADD CONSTRAINT fk_sales_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_sales_cash FOREIGN KEY (cash_id) REFERENCES cash_registers(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_sales_cancelled_by FOREIGN KEY (cancelled_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE stock_movements
  ADD COLUMN IF NOT EXISTS movement_reason VARCHAR(160) NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS supplier VARCHAR(120) NULL AFTER movement_reason,
  ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL AFTER supplier,
  ADD COLUMN IF NOT EXISTS before_qty DECIMAL(12,3) NOT NULL DEFAULT 0 AFTER quantity,
  ADD COLUMN IF NOT EXISTS after_qty DECIMAL(12,3) NOT NULL DEFAULT 0 AFTER before_qty;

ALTER TABLE stock_movements
  ADD INDEX idx_stock_movements_user (user_id),
  ADD INDEX idx_stock_movements_reason (movement_reason),
  ADD INDEX idx_stock_movements_date (created_at);

ALTER TABLE stock_movements
  ADD CONSTRAINT fk_stock_movements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

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
