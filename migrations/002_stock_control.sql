ALTER TABLE stock_movements
  ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL AFTER reference_id,
  ADD COLUMN IF NOT EXISTS supplier VARCHAR(120) NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS movement_reason VARCHAR(160) NULL AFTER supplier,
  ADD COLUMN IF NOT EXISTS before_qty DECIMAL(12,3) NOT NULL DEFAULT 0 AFTER quantity,
  ADD COLUMN IF NOT EXISTS after_qty DECIMAL(12,3) NOT NULL DEFAULT 0 AFTER before_qty;

ALTER TABLE stock_movements
  ADD INDEX idx_stock_movements_user (user_id),
  ADD INDEX idx_stock_movements_reason (movement_reason),
  ADD INDEX idx_stock_movements_date (created_at);

ALTER TABLE stock_movements
  ADD CONSTRAINT fk_stock_movements_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
