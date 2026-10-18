<?php

declare(strict_types=1);

return [
    'up' => static function (\PDO $pdo, string $driver): void {
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $big = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';
        $bool = $driver === 'mysql' ? 'TINYINT(1)' : 'INTEGER';
        $json = $driver === 'mysql' ? 'JSON' : 'TEXT';
        $options = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

        $sql = [];
        $sql[] = "CREATE TABLE companies (
            id {$id}, legal_name VARCHAR(191) NOT NULL, trade_name VARCHAR(191) NOT NULL,
            document VARCHAR(20), email VARCHAR(191), phone VARCHAR(30), timezone VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo',
            active {$bool} NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (document)
        ){$options}";
        $sql[] = "CREATE TABLE branches (
            id {$id}, company_id {$big} NOT NULL, name VARCHAR(191) NOT NULL, code VARCHAR(40) NOT NULL,
            document VARCHAR(20), address_json {$json}, active {$bool} NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, code), FOREIGN KEY (company_id) REFERENCES companies(id)
        ){$options}";
        $sql[] = "CREATE TABLE roles (
            id {$id}, company_id {$big} NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255),
            created_at DATETIME NOT NULL, UNIQUE (company_id, name),
            FOREIGN KEY (company_id) REFERENCES companies(id)
        ){$options}";
        $sql[] = "CREATE TABLE permissions (
            id {$id}, code VARCHAR(100) NOT NULL UNIQUE, description VARCHAR(255) NOT NULL
        ){$options}";
        $sql[] = "CREATE TABLE role_permissions (
            role_id {$big} NOT NULL, permission_id {$big} NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ){$options}";
        $sql[] = "CREATE TABLE users (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, role_id {$big} NOT NULL,
            name VARCHAR(191) NOT NULL, email VARCHAR(191) NOT NULL, password_hash VARCHAR(255) NOT NULL,
            active {$bool} NOT NULL DEFAULT 1, last_login_at DATETIME, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, email),
            FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (role_id) REFERENCES roles(id)
        ){$options}";
        $sql[] = "CREATE TABLE password_reset_tokens (
            id {$id}, user_id {$big} NOT NULL, token_hash VARCHAR(255) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL, used_at DATETIME, created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ){$options}";
        $sql[] = "CREATE TABLE login_attempts (
            id {$id}, email VARCHAR(191) NOT NULL, ip_address VARCHAR(64) NOT NULL,
            succeeded {$bool} NOT NULL, attempted_at DATETIME NOT NULL
        ){$options}";
        $sql[] = "CREATE TABLE audit_logs (
            id {$id}, company_id {$big}, branch_id {$big}, user_id {$big}, action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(100) NOT NULL, entity_id VARCHAR(100), before_json {$json}, after_json {$json},
            ip_address VARCHAR(64), user_agent VARCHAR(500), created_at DATETIME NOT NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        ){$options}";
        $sql[] = "CREATE TABLE settings (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big}, user_id {$big},
            setting_key VARCHAR(191) NOT NULL, setting_value {$json}, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, branch_id, user_id, setting_key),
            FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        ){$options}";

        $sql[] = "CREATE TABLE categories (
            id {$id}, company_id {$big} NOT NULL, parent_id {$big}, name VARCHAR(191) NOT NULL,
            active {$bool} NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, parent_id, name), FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (parent_id) REFERENCES categories(id)
        ){$options}";
        $sql[] = "CREATE TABLE brands (
            id {$id}, company_id {$big} NOT NULL, name VARCHAR(191) NOT NULL,
            active {$bool} NOT NULL DEFAULT 1, UNIQUE (company_id, name),
            FOREIGN KEY (company_id) REFERENCES companies(id)
        ){$options}";
        $sql[] = "CREATE TABLE units (
            id {$id}, company_id {$big} NOT NULL, code VARCHAR(12) NOT NULL, name VARCHAR(80) NOT NULL,
            decimal_places INTEGER NOT NULL DEFAULT 0, active {$bool} NOT NULL DEFAULT 1,
            UNIQUE (company_id, code), FOREIGN KEY (company_id) REFERENCES companies(id)
        ){$options}";
        $sql[] = "CREATE TABLE suppliers (
            id {$id}, company_id {$big} NOT NULL, name VARCHAR(191) NOT NULL, document VARCHAR(20),
            email VARCHAR(191), phone VARCHAR(30), address_json {$json}, notes TEXT,
            active {$bool} NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, document), FOREIGN KEY (company_id) REFERENCES companies(id)
        ){$options}";
        $sql[] = "CREATE TABLE customers (
            id {$id}, company_id {$big} NOT NULL, name VARCHAR(191) NOT NULL, document VARCHAR(20),
            email VARCHAR(191), phone VARCHAR(30), address_json {$json}, notes TEXT,
            active {$bool} NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, document), FOREIGN KEY (company_id) REFERENCES companies(id)
        ){$options}";
        $sql[] = "CREATE TABLE products (
            id {$id}, company_id {$big} NOT NULL, category_id {$big}, brand_id {$big}, unit_id {$big} NOT NULL,
            main_supplier_id {$big}, internal_code VARCHAR(64) NOT NULL, type VARCHAR(20) NOT NULL DEFAULT 'product',
            description VARCHAR(400) NOT NULL, complementary_name VARCHAR(191), model VARCHAR(100),
            cost_price DECIMAL(15,2) NOT NULL DEFAULT 0, sale_price DECIMAL(15,2) NOT NULL DEFAULT 0,
            promotional_price DECIMAL(15,2), promotion_starts_at DATETIME, promotion_ends_at DATETIME,
            minimum_stock DECIMAL(15,4) NOT NULL DEFAULT 0, track_stock {$bool} NOT NULL DEFAULT 1,
            active {$bool} NOT NULL DEFAULT 1, notes TEXT, image_path VARCHAR(255),
            ncm VARCHAR(10), cest VARCHAR(10), origin_code VARCHAR(4), cfop VARCHAR(8), cst_csosn VARCHAR(8),
            version INTEGER NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, internal_code),
            FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (category_id) REFERENCES categories(id),
            FOREIGN KEY (brand_id) REFERENCES brands(id),
            FOREIGN KEY (unit_id) REFERENCES units(id),
            FOREIGN KEY (main_supplier_id) REFERENCES suppliers(id),
            CHECK (type IN ('product','service')),
            CHECK (cost_price >= 0 AND sale_price >= 0)
        ){$options}";
        $sql[] = "CREATE TABLE product_barcodes (
            id {$id}, company_id {$big} NOT NULL, product_id {$big} NOT NULL, barcode VARCHAR(64) NOT NULL,
            symbology VARCHAR(20) NOT NULL DEFAULT 'CODE128', is_primary {$bool} NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL, UNIQUE (company_id, barcode),
            FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ){$options}";
        $sql[] = "CREATE TABLE product_history (
            id {$id}, product_id {$big} NOT NULL, user_id {$big}, snapshot_json {$json} NOT NULL,
            reason VARCHAR(255), created_at DATETIME NOT NULL,
            FOREIGN KEY (product_id) REFERENCES products(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        ){$options}";

        $sql[] = "CREATE TABLE stock_locations (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, code VARCHAR(40) NOT NULL,
            name VARCHAR(191) NOT NULL, active {$bool} NOT NULL DEFAULT 1,
            UNIQUE (company_id, code), FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id)
        ){$options}";
        $sql[] = "CREATE TABLE stock_movements (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, location_id {$big} NOT NULL,
            product_id {$big} NOT NULL, movement_type VARCHAR(30) NOT NULL, quantity DECIMAL(15,4) NOT NULL,
            unit_cost DECIMAL(15,2), source_type VARCHAR(40) NOT NULL, source_id VARCHAR(100) NOT NULL,
            source_line VARCHAR(100) NOT NULL DEFAULT '', reason VARCHAR(255), user_id {$big}, occurred_at DATETIME NOT NULL,
            UNIQUE (company_id, source_type, source_id, source_line),
            FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (location_id) REFERENCES stock_locations(id), FOREIGN KEY (product_id) REFERENCES products(id),
            FOREIGN KEY (user_id) REFERENCES users(id), CHECK (quantity <> 0)
        ){$options}";
        $sql[] = "CREATE TABLE inventory_counts (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, location_id {$big} NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open', notes TEXT, started_by {$big} NOT NULL,
            started_at DATETIME NOT NULL, closed_by {$big}, closed_at DATETIME,
            FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (location_id) REFERENCES stock_locations(id), FOREIGN KEY (started_by) REFERENCES users(id),
            FOREIGN KEY (closed_by) REFERENCES users(id)
        ){$options}";
        $sql[] = "CREATE TABLE inventory_count_items (
            id {$id}, inventory_count_id {$big} NOT NULL, product_id {$big} NOT NULL,
            expected_quantity DECIMAL(15,4) NOT NULL, counted_quantity DECIMAL(15,4),
            UNIQUE (inventory_count_id, product_id),
            FOREIGN KEY (inventory_count_id) REFERENCES inventory_counts(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id)
        ){$options}";

        $sql[] = "CREATE TABLE cash_registers (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, name VARCHAR(100) NOT NULL,
            code VARCHAR(40) NOT NULL, active {$bool} NOT NULL DEFAULT 1,
            UNIQUE (company_id, code), FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id)
        ){$options}";
        $sql[] = "CREATE TABLE cash_sessions (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, cash_register_id {$big} NOT NULL,
            user_id {$big} NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'open',
            opening_amount DECIMAL(15,2) NOT NULL DEFAULT 0, opened_at DATETIME NOT NULL, opening_notes TEXT,
            expected_cash DECIMAL(15,2), counted_cash DECIMAL(15,2), difference_amount DECIMAL(15,2),
            closing_notes TEXT, closed_by {$big}, closed_at DATETIME,
            FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id), FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (closed_by) REFERENCES users(id)
        ){$options}";
        $sql[] = "CREATE TABLE cash_movements (
            id {$id}, cash_session_id {$big} NOT NULL, movement_type VARCHAR(30) NOT NULL,
            amount DECIMAL(15,2) NOT NULL, reason VARCHAR(255) NOT NULL, source_type VARCHAR(40),
            source_id VARCHAR(100), user_id {$big} NOT NULL, authorized_by {$big}, created_at DATETIME NOT NULL,
            FOREIGN KEY (cash_session_id) REFERENCES cash_sessions(id),
            FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (authorized_by) REFERENCES users(id),
            CHECK (amount > 0)
        ){$options}";
        $sql[] = "CREATE TABLE payment_methods (
            id {$id}, company_id {$big} NOT NULL, code VARCHAR(40) NOT NULL, name VARCHAR(100) NOT NULL,
            method_type VARCHAR(30) NOT NULL, requires_confirmation {$bool} NOT NULL DEFAULT 1,
            max_installments INTEGER NOT NULL DEFAULT 1, active {$bool} NOT NULL DEFAULT 1,
            UNIQUE (company_id, code), FOREIGN KEY (company_id) REFERENCES companies(id)
        ){$options}";

        $sql[] = "CREATE TABLE sales (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, cash_session_id {$big},
            customer_id {$big}, operator_id {$big} NOT NULL, sale_number VARCHAR(40) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft', subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0, total DECIMAL(15,2) NOT NULL DEFAULT 0,
            idempotency_key VARCHAR(100) NOT NULL, notes TEXT, completed_at DATETIME, cancelled_at DATETIME,
            cancelled_by {$big}, cancellation_reason VARCHAR(255), created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, sale_number), UNIQUE (company_id, idempotency_key),
            FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (cash_session_id) REFERENCES cash_sessions(id), FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (operator_id) REFERENCES users(id), FOREIGN KEY (cancelled_by) REFERENCES users(id)
        ){$options}";
        $sql[] = "CREATE TABLE sale_items (
            id {$id}, sale_id {$big} NOT NULL, product_id {$big} NOT NULL, barcode VARCHAR(64),
            description VARCHAR(400) NOT NULL, quantity DECIMAL(15,4) NOT NULL,
            unit_price DECIMAL(15,2) NOT NULL, discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(15,2) NOT NULL, returned_quantity DECIMAL(15,4) NOT NULL DEFAULT 0,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id), CHECK (quantity > 0)
        ){$options}";
        $sql[] = "CREATE TABLE sale_payments (
            id {$id}, sale_id {$big} NOT NULL, payment_method_id {$big} NOT NULL,
            amount DECIMAL(15,2) NOT NULL, installments INTEGER NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'confirmed', operational_confirmation {$bool} NOT NULL DEFAULT 0,
            reference VARCHAR(100), created_at DATETIME NOT NULL,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id), CHECK (amount > 0)
        ){$options}";

        $sql[] = "CREATE TABLE purchase_orders (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, supplier_id {$big} NOT NULL,
            order_number VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft',
            expected_at DATETIME, subtotal DECIMAL(15,2) NOT NULL DEFAULT 0, discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            freight_amount DECIMAL(15,2) NOT NULL DEFAULT 0, total DECIMAL(15,2) NOT NULL DEFAULT 0,
            notes TEXT, created_by {$big} NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, order_number), FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id), FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        ){$options}";
        $sql[] = "CREATE TABLE purchase_order_items (
            id {$id}, purchase_order_id {$big} NOT NULL, product_id {$big} NOT NULL,
            quantity DECIMAL(15,4) NOT NULL, received_quantity DECIMAL(15,4) NOT NULL DEFAULT 0,
            unit_cost DECIMAL(15,2) NOT NULL, discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            subtotal DECIMAL(15,2) NOT NULL,
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id), CHECK (quantity > 0)
        ){$options}";
        $sql[] = "CREATE TABLE purchase_receipts (
            id {$id}, purchase_order_id {$big} NOT NULL, receipt_number VARCHAR(40) NOT NULL,
            location_id {$big} NOT NULL, received_by {$big} NOT NULL, received_at DATETIME NOT NULL, notes TEXT,
            UNIQUE (purchase_order_id, receipt_number),
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
            FOREIGN KEY (location_id) REFERENCES stock_locations(id), FOREIGN KEY (received_by) REFERENCES users(id)
        ){$options}";

        $sql[] = "CREATE TABLE quotes (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, customer_id {$big},
            quote_number VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft',
            subtotal DECIMAL(15,2) NOT NULL DEFAULT 0, discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            total DECIMAL(15,2) NOT NULL DEFAULT 0, valid_until DATE, converted_sale_id {$big},
            created_by {$big} NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, quote_number), FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id), FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (converted_sale_id) REFERENCES sales(id), FOREIGN KEY (created_by) REFERENCES users(id)
        ){$options}";
        $sql[] = "CREATE TABLE quote_items (
            id {$id}, quote_id {$big} NOT NULL, product_id {$big} NOT NULL, description VARCHAR(400) NOT NULL,
            quantity DECIMAL(15,4) NOT NULL, unit_price DECIMAL(15,2) NOT NULL,
            discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0, subtotal DECIMAL(15,2) NOT NULL,
            FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id)
        ){$options}";
        $sql[] = "CREATE TABLE sale_returns (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, sale_id {$big} NOT NULL,
            return_number VARCHAR(40) NOT NULL, return_type VARCHAR(20) NOT NULL DEFAULT 'refund',
            total DECIMAL(15,2) NOT NULL, reason VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
            created_by {$big} NOT NULL, authorized_by {$big}, created_at DATETIME NOT NULL,
            UNIQUE (company_id, return_number), FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id), FOREIGN KEY (sale_id) REFERENCES sales(id),
            FOREIGN KEY (created_by) REFERENCES users(id), FOREIGN KEY (authorized_by) REFERENCES users(id)
        ){$options}";
        $sql[] = "CREATE TABLE sale_return_items (
            id {$id}, sale_return_id {$big} NOT NULL, sale_item_id {$big} NOT NULL,
            quantity DECIMAL(15,4) NOT NULL, amount DECIMAL(15,2) NOT NULL, return_to_stock {$bool} NOT NULL DEFAULT 1,
            FOREIGN KEY (sale_return_id) REFERENCES sale_returns(id) ON DELETE CASCADE,
            FOREIGN KEY (sale_item_id) REFERENCES sale_items(id), CHECK (quantity > 0)
        ){$options}";

        $sql[] = "CREATE TABLE financial_accounts (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big}, name VARCHAR(191) NOT NULL,
            account_type VARCHAR(30) NOT NULL, bank_code VARCHAR(10), agency VARCHAR(30), account_number VARCHAR(40),
            active {$bool} NOT NULL DEFAULT 1, FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (branch_id) REFERENCES branches(id)
        ){$options}";
        $sql[] = "CREATE TABLE financial_categories (
            id {$id}, company_id {$big} NOT NULL, parent_id {$big}, name VARCHAR(191) NOT NULL,
            direction VARCHAR(10) NOT NULL, active {$bool} NOT NULL DEFAULT 1,
            UNIQUE (company_id, parent_id, name, direction), FOREIGN KEY (company_id) REFERENCES companies(id),
            FOREIGN KEY (parent_id) REFERENCES financial_categories(id)
        ){$options}";
        $sql[] = "CREATE TABLE financial_entries (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, category_id {$big},
            customer_id {$big}, supplier_id {$big}, direction VARCHAR(10) NOT NULL,
            description VARCHAR(255) NOT NULL, document_number VARCHAR(80), issue_date DATE NOT NULL, due_date DATE NOT NULL,
            amount DECIMAL(15,2) NOT NULL, settled_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'open', source_type VARCHAR(40), source_id VARCHAR(100),
            installment_number INTEGER NOT NULL DEFAULT 1, installment_count INTEGER NOT NULL DEFAULT 1,
            created_by {$big} NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            UNIQUE (company_id, source_type, source_id, installment_number),
            FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (category_id) REFERENCES financial_categories(id), FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id), FOREIGN KEY (created_by) REFERENCES users(id)
        ){$options}";
        $sql[] = "CREATE TABLE financial_settlements (
            id {$id}, financial_entry_id {$big} NOT NULL, financial_account_id {$big} NOT NULL,
            amount DECIMAL(15,2) NOT NULL, interest_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0, settled_at DATETIME NOT NULL,
            user_id {$big} NOT NULL, notes VARCHAR(255),
            FOREIGN KEY (financial_entry_id) REFERENCES financial_entries(id),
            FOREIGN KEY (financial_account_id) REFERENCES financial_accounts(id),
            FOREIGN KEY (user_id) REFERENCES users(id), CHECK (amount > 0)
        ){$options}";

        $sql[] = "CREATE TABLE print_history (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, user_id {$big} NOT NULL,
            product_id {$big}, quantity INTEGER NOT NULL, method VARCHAR(30) NOT NULL,
            settings_json {$json}, printed_at DATETIME NOT NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (product_id) REFERENCES products(id)
        ){$options}";
        $sql[] = "CREATE TABLE import_jobs (
            id {$id}, company_id {$big} NOT NULL, branch_id {$big} NOT NULL, user_id {$big} NOT NULL,
            import_type VARCHAR(40) NOT NULL, original_name VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL,
            total_rows INTEGER NOT NULL DEFAULT 0, valid_rows INTEGER NOT NULL DEFAULT 0, invalid_rows INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL, confirmed_at DATETIME,
            FOREIGN KEY (company_id) REFERENCES companies(id), FOREIGN KEY (branch_id) REFERENCES branches(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        ){$options}";
        $sql[] = "CREATE TABLE import_job_rows (
            id {$id}, import_job_id {$big} NOT NULL, row_number INTEGER NOT NULL,
            data_json {$json} NOT NULL, errors_json {$json}, status VARCHAR(20) NOT NULL,
            FOREIGN KEY (import_job_id) REFERENCES import_jobs(id) ON DELETE CASCADE,
            UNIQUE (import_job_id, row_number)
        ){$options}";

        foreach ($sql as $statement) {
            $pdo->exec($statement);
        }

        $indexes = [
            'idx_login_attempts_lookup' => 'login_attempts (email, ip_address, attempted_at)',
            'idx_audit_logs_entity' => 'audit_logs (company_id, entity_type, entity_id, created_at)',
            'idx_products_search' => 'products (company_id, active, description)',
            'idx_stock_product' => 'stock_movements (company_id, location_id, product_id, occurred_at)',
            'idx_cash_sessions_open' => 'cash_sessions (company_id, cash_register_id, status)',
            'idx_sales_period' => 'sales (company_id, branch_id, status, completed_at)',
            'idx_financial_due' => 'financial_entries (company_id, direction, status, due_date)',
        ];
        foreach ($indexes as $name => $definition) {
            $prefix = $driver === 'sqlite' ? 'CREATE INDEX IF NOT EXISTS ' : 'CREATE INDEX ';
            $pdo->exec($prefix . $name . ' ON ' . $definition);
        }
    },
    'down' => static function (\PDO $pdo, string $driver): void {
        $tables = [
            'import_job_rows', 'import_jobs', 'print_history', 'financial_settlements',
            'financial_entries', 'financial_categories', 'financial_accounts',
            'sale_return_items', 'sale_returns', 'quote_items', 'quotes',
            'purchase_receipts', 'purchase_order_items', 'purchase_orders',
            'sale_payments', 'sale_items', 'sales', 'payment_methods',
            'cash_movements', 'cash_sessions', 'cash_registers',
            'inventory_count_items', 'inventory_counts', 'stock_movements', 'stock_locations',
            'product_history', 'product_barcodes', 'products', 'customers', 'suppliers',
            'units', 'brands', 'categories', 'settings', 'audit_logs', 'login_attempts',
            'password_reset_tokens', 'users', 'role_permissions', 'permissions', 'roles',
            'branches', 'companies',
        ];
        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        } else {
            $pdo->exec('PRAGMA foreign_keys=OFF');
        }
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } else {
            $pdo->exec('PRAGMA foreign_keys=ON');
        }
    },
];

