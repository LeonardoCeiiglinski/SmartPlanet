-- @@statement
DO $seed$
DECLARE
    now_at timestamp := CURRENT_TIMESTAMP;
    master_email text := 'leonardoceiglinskid@gmail.com';
    master_hash text := '$2y$12$E3Y6g3xZyC71sRMarWPkuOcfTrirfjbFnk7xPXBHy0ChxudGxV2z6';
    master_name text := 'Leonardo Ceiglinski';
    v_company bigint;
    v_branch bigint;
    v_admin bigint;
    v_manager bigint;
    v_operator bigint;
BEGIN
    IF EXISTS (SELECT 1 FROM users WHERE LOWER(email) = master_email) THEN
        UPDATE users
           SET password_hash = master_hash,
               is_master = 1,
               active = 1,
               name = master_name,
               updated_at = now_at
         WHERE LOWER(email) = master_email;
        RETURN;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM companies) THEN
        INSERT INTO companies (legal_name, trade_name, email, timezone, active, created_at, updated_at)
        VALUES ('SmartPlanet', 'SmartPlanet', master_email, 'America/Sao_Paulo', 1, now_at, now_at)
        RETURNING id INTO v_company;

        INSERT INTO branches (company_id, name, code, active, created_at, updated_at)
        VALUES (v_company, 'Matriz', 'MATRIZ', 1, now_at, now_at)
        RETURNING id INTO v_branch;

        INSERT INTO roles (company_id, name, description, created_at)
        VALUES (v_company, 'Administrador', 'Acesso integral ao sistema', now_at)
        RETURNING id INTO v_admin;
        INSERT INTO roles (company_id, name, description, created_at)
        VALUES (v_company, 'Gerente', 'Gestão comercial e autorizações', now_at)
        RETURNING id INTO v_manager;
        INSERT INTO roles (company_id, name, description, created_at)
        VALUES (v_company, 'Operador de caixa', 'Operação de PDV e caixa', now_at)
        RETURNING id INTO v_operator;

        INSERT INTO role_permissions (role_id, permission_id)
        SELECT v_admin, id FROM permissions;
        INSERT INTO role_permissions (role_id, permission_id)
        SELECT v_manager, id FROM permissions
        WHERE code NOT IN ('users.manage', 'settings.manage');
        INSERT INTO role_permissions (role_id, permission_id)
        SELECT v_operator, id FROM permissions
        WHERE code IN (
            'dashboard.view', 'products.view', 'labels.print', 'stock.view', 'pos.sell',
            'sales.view', 'cash.open', 'cash.move', 'cash.close', 'procedures.view'
        );

        INSERT INTO units (company_id, code, name, decimal_places, active) VALUES
            (v_company, 'UN', 'Unidade', 0, 1),
            (v_company, 'KG', 'Quilograma', 3, 1),
            (v_company, 'LT', 'Litro', 3, 1),
            (v_company, 'SV', 'Serviço', 0, 1);
        INSERT INTO stock_locations (company_id, branch_id, code, name, active)
        VALUES (v_company, v_branch, 'PRINCIPAL', 'Estoque principal', 1);
        INSERT INTO cash_registers (company_id, branch_id, name, code, active)
        VALUES (v_company, v_branch, 'Caixa principal', 'CX01', 1);
        INSERT INTO payment_methods (company_id, code, name, method_type, requires_confirmation, max_installments, active)
        VALUES
            (v_company, 'DINHEIRO', 'Dinheiro', 'cash', 0, 1, 1),
            (v_company, 'PIX', 'PIX', 'pix', 1, 1, 1),
            (v_company, 'DEBITO', 'Cartão de débito', 'debit', 1, 1, 1),
            (v_company, 'CREDITO', 'Cartão de crédito', 'credit', 1, 12, 1);
        INSERT INTO financial_accounts (company_id, branch_id, name, account_type, active)
        VALUES (v_company, v_branch, 'Caixa', 'cash', 1);
        INSERT INTO financial_categories (company_id, name, direction, active, code) VALUES
            (v_company, 'Vendas', 'in', 1, 'vendas'),
            (v_company, 'Custo das mercadorias vendidas', 'out', 1, 'cmv'),
            (v_company, 'Devoluções de venda', 'out', 1, 'devolucoes'),
            (v_company, 'Compras a pagar', 'out', 1, 'compras'),
            (v_company, 'Reforço de caixa', 'in', 1, 'caixa_reforco'),
            (v_company, 'Sangria de caixa', 'out', 1, 'caixa_sangria'),
            (v_company, 'Ajuste de estoque', 'out', 1, 'ajuste_estoque'),
            (v_company, 'Lançamento manual', 'in', 1, 'manual');
    ELSE
        SELECT id INTO v_company FROM companies ORDER BY id LIMIT 1;
        SELECT id INTO v_branch FROM branches WHERE company_id = v_company ORDER BY id LIMIT 1;
        SELECT id INTO v_admin FROM roles WHERE company_id = v_company AND name = 'Administrador' ORDER BY id LIMIT 1;
    END IF;

    IF v_company IS NULL OR v_branch IS NULL OR v_admin IS NULL THEN
        RAISE EXCEPTION 'Estrutura da empresa incompleta para cadastrar o administrador master';
    END IF;

    INSERT INTO users (
        company_id, branch_id, role_id, name, email, password_hash, active, is_master, created_at, updated_at
    ) VALUES (
        v_company, v_branch, v_admin, master_name, master_email, master_hash, 1, 1, now_at, now_at
    );
END
$seed$;
