-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 28/10/2026 às 17:26
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `to_em_casa`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `details` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cash_movements`
--

CREATE TABLE `cash_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cash_register_id` bigint(20) UNSIGNED NOT NULL,
  `movement_type` enum('opening','sale','supply','sangria','cancelled_sale','closing') NOT NULL,
  `related_sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(160) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cash_movements`
--

INSERT INTO `cash_movements` (`id`, `cash_register_id`, `movement_type`, `related_sale_id`, `user_id`, `amount`, `reason`, `created_at`) VALUES
(1, 1, 'opening', NULL, 3, 1.00, 'Abertura de caixa', '2026-10-26 18:11:48'),
(2, 1, 'closing', NULL, 3, 1.00, 'Fechamento de caixa', '2026-10-26 18:12:47'),
(3, 2, 'opening', NULL, 4, 3.00, 'Abertura de caixa', '2026-10-27 14:53:04'),
(4, 2, 'sale', 1, 5, 30.00, 'Venda V20261027174637-365', '2026-10-27 16:46:37'),
(5, 2, 'sale', 2, 5, 30.00, 'Venda V20261027174804-756', '2026-10-27 16:48:04'),
(6, 2, 'sale', 3, 5, 90.00, 'Venda V20261027211634-895', '2026-10-27 20:16:34'),
(7, 2, 'sale', 4, 5, 60.00, 'Venda V20261027212933-270', '2026-10-27 20:29:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cash_registers`
--

CREATE TABLE `cash_registers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `opened_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `opened_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `expected_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `counted_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `difference_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cash_registers`
--

INSERT INTO `cash_registers` (`id`, `opened_by_user_id`, `opened_amount`, `status`, `opened_at`, `closed_at`, `expected_amount`, `counted_amount`, `difference_amount`, `notes`) VALUES
(1, 3, 1.00, 'closed', '2026-10-26 18:11:48', '2026-10-26 18:12:47', 1.00, 1.00, 0.00, 'TESTE3'),
(2, 4, 3.00, 'open', '2026-10-27 14:53:04', NULL, 3.00, 3.00, 0.00, 'Abertura de caixa');

-- --------------------------------------------------------

--
-- Estrutura para tabela `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categories`
--

INSERT INTO `categories` (`id`, `name`, `active`) VALUES
(1, 'Cervejas', 1),
(2, 'Destilados', 1),
(3, 'Refrigerantes', 1),
(4, 'Água', 1),
(5, 'Gelo', 1),
(6, 'Snacks', 1),
(7, 'Doces e chocolates', 1),
(8, 'Conveniência', 1),
(9, 'Outros', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `financial_categories`
--

CREATE TABLE `financial_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `financial_entries`
--

CREATE TABLE `financial_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entry_type` enum('income','expense') NOT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(180) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entry_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','paid','received','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(40) NOT NULL DEFAULT 'outro',
  `reference_key` varchar(120) DEFAULT NULL,
  `related_entity_type` varchar(40) DEFAULT NULL,
  `related_entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `payment_tickets`
--

CREATE TABLE `payment_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `ticket_number` varchar(64) NOT NULL,
  `printed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `barcode` varchar(64) NOT NULL,
  `name` varchar(160) NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'un',
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `min_stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `expires_on` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `products`
--

INSERT INTO `products` (`id`, `barcode`, `name`, `category_id`, `brand`, `unit`, `cost_price`, `sale_price`, `stock_qty`, `min_stock`, `expires_on`, `active`, `created_at`, `updated_at`) VALUES
(1, '85246984', 'teste1', 5, 'teste1', 'l', 30.00, 35.00, 10.000, 5.000, NULL, 1, '2026-10-26 18:59:13', '2026-10-27 20:15:55'),
(2, '789967012760', 'LIMPA CONTATO', 9, 'M5', 'ml', 20.00, 30.00, 5.000, 4.000, NULL, 1, '2026-10-27 16:14:54', '2026-10-27 20:29:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sales`
--

CREATE TABLE `sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_number` varchar(32) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL,
  `status` enum('completed','cancelled') NOT NULL DEFAULT 'completed',
  `seller_id` bigint(20) UNSIGNED DEFAULT NULL,
  `cash_id` bigint(20) UNSIGNED DEFAULT NULL,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `cancelled_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `sales`
--

INSERT INTO `sales` (`id`, `sale_number`, `total`, `status`, `seller_id`, `cash_id`, `discount`, `notes`, `cancelled_by_user_id`, `cancelled_at`, `cancel_reason`, `created_at`) VALUES
(1, 'V20261027174637-365', 30.00, 'completed', 5, 2, 0.00, 'Venda registrada no PDV', NULL, NULL, NULL, '2026-10-27 16:46:37'),
(2, 'V20261027174804-756', 30.00, 'completed', 5, 2, 0.00, 'Venda registrada no PDV', NULL, NULL, NULL, '2026-10-27 16:48:04'),
(3, 'V20261027211634-895', 90.00, 'completed', 5, 2, 0.00, 'Venda registrada no PDV', NULL, NULL, NULL, '2026-10-27 20:16:34'),
(4, 'V20261027212933-270', 60.00, 'completed', 5, 2, 0.00, 'Venda registrada no PDV', NULL, NULL, NULL, '2026-10-27 20:29:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sale_items`
--

CREATE TABLE `sale_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_name` varchar(160) NOT NULL,
  `barcode` varchar(64) NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `product_name`, `barcode`, `quantity`, `unit_price`, `unit_cost`, `line_total`) VALUES
(1, 1, 2, 'LIMPA CONTATO', '789967012760', 1.000, 30.00, 20.00, 30.00),
(2, 2, 2, 'LIMPA CONTATO', '789967012760', 1.000, 30.00, 20.00, 30.00),
(3, 3, 2, 'LIMPA CONTATO', '789967012760', 3.000, 30.00, 20.00, 90.00),
(4, 4, 2, 'LIMPA CONTATO', '789967012760', 2.000, 30.00, 20.00, 60.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `sale_payments`
--

CREATE TABLE `sale_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `method` enum('dinheiro','pix','debito','credito','outro') NOT NULL,
  `amount` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `sale_payments`
--

INSERT INTO `sale_payments` (`id`, `sale_id`, `method`, `amount`) VALUES
(1, 1, 'pix', 30.00),
(2, 2, 'pix', 30.00),
(3, 3, 'credito', 90.00),
(4, 4, 'pix', 60.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `movement_type` enum('initial','sale','adjustment_in','adjustment_out') NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `before_qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `after_qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `supplier` varchar(120) DEFAULT NULL,
  `movement_reason` varchar(160) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `before_qty`, `after_qty`, `reference_id`, `user_id`, `notes`, `supplier`, `movement_reason`, `created_at`) VALUES
(1, 1, 'initial', 1.000, 0.000, 1.000, NULL, 3, 'Estoque inicial no cadastro', NULL, 'Cadastro inicial', '2026-10-26 18:59:13'),
(2, 2, 'initial', 1.000, 0.000, 1.000, NULL, 5, 'Estoque inicial no cadastro', NULL, 'Cadastro inicial', '2026-10-27 16:14:54'),
(3, 2, 'sale', -1.000, 1.000, 0.000, 1, 5, 'Venda #1', NULL, 'venda', '2026-10-27 16:46:37'),
(4, 2, 'sale', -1.000, 1.000, 0.000, 2, 5, 'Venda #2', NULL, 'venda', '2026-10-27 16:48:04'),
(5, 2, 'sale', -3.000, 10.000, 7.000, 3, 5, 'Venda #3', NULL, 'venda', '2026-10-27 20:16:34'),
(6, 2, 'sale', -2.000, 7.000, 5.000, 4, 5, 'Venda #4', NULL, 'venda', '2026-10-27 20:29:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','vendedor') NOT NULL DEFAULT 'vendedor',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `username`, `name`, `password_hash`, `role`, `active`, `created_at`, `updated_at`) VALUES
(3, 'admin', 'Administrador', '$2y$10$rxzscKzWceVZfDEjt.yYjOJdWlNIj3EZbxPBWm.2iflKN13kh.fIG', 'admin', 0, '2026-10-23 18:08:04', '2026-10-27 16:24:26'),
(4, 'vendedor1', 'Vendedor 1', '$2y$10$VNBHMlzd5uBHhgaTtZcsbulBjapua3MNJntPoRRtxbG8TWHi2yFwC', 'vendedor', 1, '2026-10-23 18:08:04', '2026-10-27 16:29:24'),
(5, 'jonata.mesquita', 'JONATA MESQUITA', '$2y$10$rNKxGXYb4JZhnBVpJ60UfefdhwyEjfn9dEdtixQyJ7wG4CnECB7sq', 'admin', 1, '2026-10-27 14:39:28', '2026-10-27 14:39:28'),
(6, 'fabio.dev', 'FABIO', '$2y$10$..ct2cTvyHwRg.b8ZmEmfOnlEVB/Z0aRWDl4cpxvI6YB8FwRqdi5O', 'admin', 1, '2026-10-27 16:32:04', '2026-10-27 16:32:04');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_type` (`event_type`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Índices de tabela `cash_movements`
--
ALTER TABLE `cash_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cash_movements_sale` (`related_sale_id`),
  ADD KEY `fk_cash_movements_user` (`user_id`),
  ADD KEY `idx_cash_movements_register` (`cash_register_id`,`created_at`);

--
-- Índices de tabela `cash_registers`
--
ALTER TABLE `cash_registers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cash_registers_status` (`status`),
  ADD KEY `idx_cash_registers_user` (`opened_by_user_id`);

--
-- Índices de tabela `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Índices de tabela `financial_categories`
--
ALTER TABLE `financial_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_financial_category_name` (`name`,`type`);

--
-- Índices de tabela `financial_entries`
--
ALTER TABLE `financial_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_financial_category` (`category_id`),
  ADD KEY `fk_financial_user` (`user_id`),
  ADD KEY `idx_financial_type` (`entry_type`),
  ADD KEY `idx_financial_status` (`status`),
  ADD KEY `idx_financial_date` (`entry_date`),
  ADD KEY `idx_financial_reference` (`reference_key`);

--
-- Índices de tabela `payment_tickets`
--
ALTER TABLE `payment_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ticket_number` (`ticket_number`),
  ADD KEY `fk_payment_tickets_sale` (`sale_id`);

--
-- Índices de tabela `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `fk_products_category` (`category_id`),
  ADD KEY `idx_products_name` (`name`),
  ADD KEY `idx_products_active` (`active`);

--
-- Índices de tabela `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sales_sale_number` (`sale_number`),
  ADD KEY `idx_sales_seller` (`seller_id`),
  ADD KEY `idx_sales_cash` (`cash_id`),
  ADD KEY `idx_sales_cancelled_by` (`cancelled_by_user_id`);

--
-- Índices de tabela `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sale_items_product` (`product_id`),
  ADD KEY `idx_sale_items_sale` (`sale_id`);

--
-- Índices de tabela `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sale_payments_sale` (`sale_id`);

--
-- Índices de tabela `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_product_date` (`product_id`,`created_at`),
  ADD KEY `idx_stock_movements_user` (`user_id`),
  ADD KEY `idx_stock_movements_reason` (`movement_reason`),
  ADD KEY `idx_stock_movements_date` (`created_at`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_active` (`active`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cash_movements`
--
ALTER TABLE `cash_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `cash_registers`
--
ALTER TABLE `cash_registers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `financial_categories`
--
ALTER TABLE `financial_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `financial_entries`
--
ALTER TABLE `financial_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `payment_tickets`
--
ALTER TABLE `payment_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `sales`
--
ALTER TABLE `sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `sale_payments`
--
ALTER TABLE `sale_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `cash_movements`
--
ALTER TABLE `cash_movements`
  ADD CONSTRAINT `fk_cash_movements_register` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers` (`id`),
  ADD CONSTRAINT `fk_cash_movements_sale` FOREIGN KEY (`related_sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cash_movements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `cash_registers`
--
ALTER TABLE `cash_registers`
  ADD CONSTRAINT `fk_cash_registers_user` FOREIGN KEY (`opened_by_user_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `financial_entries`
--
ALTER TABLE `financial_entries`
  ADD CONSTRAINT `fk_financial_category` FOREIGN KEY (`category_id`) REFERENCES `financial_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_financial_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `payment_tickets`
--
ALTER TABLE `payment_tickets`
  ADD CONSTRAINT `fk_payment_tickets_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);

--
-- Restrições para tabelas `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_cash` FOREIGN KEY (`cash_id`) REFERENCES `cash_registers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sales_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);

--
-- Restrições para tabelas `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD CONSTRAINT `fk_sale_payments_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);

--
-- Restrições para tabelas `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stock_movements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
