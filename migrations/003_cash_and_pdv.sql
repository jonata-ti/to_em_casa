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

ALTER TABLE sales
  ADD COLUMN IF NOT EXISTS sale_number VARCHAR(32) NULL AFTER id,
  ADD COLUMN IF NOT EXISTS seller_id BIGINT UNSIGNED NULL AFTER status,
  ADD COLUMN IF NOT EXISTS cash_id BIGINT UNSIGNED NULL AFTER seller_id,
  ADD COLUMN IF NOT EXISTS discount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cash_id,
  ADD COLUMN IF NOT EXISTS notes VARCHAR(255) NULL AFTER discount;

ALTER TABLE sales
  ADD INDEX idx_sales_sale_number (sale_number),
  ADD INDEX idx_sales_seller (seller_id),
  ADD INDEX idx_sales_cash (cash_id);

ALTER TABLE sales
  ADD CONSTRAINT fk_sales_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_sales_cash FOREIGN KEY (cash_id) REFERENCES cash_registers(id) ON DELETE SET NULL;

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
