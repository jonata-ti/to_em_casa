CREATE TABLE IF NOT EXISTS payment_tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id BIGINT UNSIGNED NOT NULL,
  ticket_number VARCHAR(64) NOT NULL,
  printed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_tickets_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
  UNIQUE KEY uniq_ticket_number (ticket_number)
) ENGINE=InnoDB;
