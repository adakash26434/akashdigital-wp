<?php
/**
 * Database Schema Migrations
 * Runs automatically on admin pages to apply incremental schema changes.
 *
 * SQLite note: fresh SQLite installs get all tables via sqlite-init.php.
 * These migrations only need to add NEW columns / tables to EXISTING installs.
 * SHOW TABLES / SHOW COLUMNS are MySQL-only; use dbTableExists() / dbColumnExists()
 * which branch on DB_DRIVER automatically.
 */

/** Check whether a table exists (MySQL or SQLite) */
function dbTableExists(string $table): bool {
    if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
        $r = queryOne("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table]);
        return (bool)$r;
    }
    $r = query("SHOW TABLES LIKE ?", [$table]);
    return !empty($r);
}

/** Check whether a column exists in a table (MySQL or SQLite) */
function dbColumnExists(string $table, string $column): bool {
    if (!dbTableExists($table)) return false;
    if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
        $cols = query("PRAGMA table_info(" . $table . ")");
        foreach ($cols as $c) {
            if (($c['name'] ?? '') === $column) return true;
        }
        return false;
    }
    $r = query("SHOW COLUMNS FROM `$table` LIKE ?", [$column]);
    return !empty($r);
}

function runDbMigrations() {
    // Migration 0: Create site_settings table if it doesn't exist
    try {
        if (!dbTableExists('site_settings')) {
            if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
                execute("CREATE TABLE IF NOT EXISTS site_settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    setting_key TEXT NOT NULL UNIQUE,
                    setting_val TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
            } else {
                execute("CREATE TABLE IF NOT EXISTS site_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) NOT NULL UNIQUE,
                    setting_val LONGTEXT,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_key (setting_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            saveSetting('site_name', defined('SITE_NAME') ? SITE_NAME : 'Company');
            saveSetting('site_tagline', 'Cooperative Software for Nepal');
            saveSetting('company_name', defined('SITE_NAME') ? SITE_NAME : 'Company');
            saveSetting('address', 'Kathmandu, Nepal');
        }
    } catch (\Throwable $e) {
        error_log('[db-migrations] site_settings: ' . $e->getMessage());
    }

    try {
        // Migration 1: Add team category field
        if (!dbColumnExists('team_members', 'category')) {
            execute("ALTER TABLE team_members ADD COLUMN category TEXT DEFAULT 'management'");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M1: ' . $e->getMessage()); }

    try {
        // Migration 2: Seed company_name + developer attribution if not exist
        $check = queryOne("SELECT id FROM site_settings WHERE setting_key=?", ['company_name']);
        if (!$check) {
            saveSetting('company_name', defined('SITE_NAME') ? SITE_NAME : 'Company');
            saveSetting('developed_by_name', defined('SITE_NAME') ? SITE_NAME : 'Company');
            saveSetting('developed_by_url', '');
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M2: ' . $e->getMessage()); }

    try {
        // Migration 3: Add client_code column to users table
        if (!dbColumnExists('users', 'client_code')) {
            execute("ALTER TABLE users ADD COLUMN client_code VARCHAR(50) NULL");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M3: ' . $e->getMessage()); }

    try {
        // Migration 4: Add email, phone, address columns to partners table
        if (!dbColumnExists('partners', 'email')) {
            execute("ALTER TABLE partners ADD COLUMN email VARCHAR(255) NULL");
            execute("ALTER TABLE partners ADD COLUMN phone VARCHAR(50) NULL");
            execute("ALTER TABLE partners ADD COLUMN address TEXT NULL");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M4: ' . $e->getMessage()); }

    try {
        // Migration 5: Notices table
        if (!dbTableExists('notices')) {
            execute("CREATE TABLE IF NOT EXISTS notices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                image_url VARCHAR(500) DEFAULT NULL,
                target_pages VARCHAR(255) DEFAULT 'all',
                is_active TINYINT(1) DEFAULT 1,
                starts_at DATETIME DEFAULT NULL,
                ends_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER DEFAULT NULL
            )");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M5: ' . $e->getMessage()); }

    try {
        // Migration 6: Agreement templates table
        if (!dbTableExists('agreement_templates')) {
            execute("CREATE TABLE IF NOT EXISTS agreement_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                template_type VARCHAR(50) NOT NULL,
                template_content TEXT DEFAULT NULL,
                word_file_path VARCHAR(500) DEFAULT NULL,
                is_default TINYINT(1) DEFAULT 0,
                created_by INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M6: ' . $e->getMessage()); }

    try {
        // Migration 7: AMC renewal configuration table
        if (!dbTableExists('amc_renewal_config')) {
            execute("CREATE TABLE IF NOT EXISTS amc_renewal_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                renewal_cycle VARCHAR(20) DEFAULT 'yearly',
                cycle_months INTEGER DEFAULT 12,
                increment_type VARCHAR(20) DEFAULT 'fixed',
                increment_value DECIMAL(10,2) DEFAULT 0,
                base_amc_ho DECIMAL(10,2) DEFAULT 0,
                base_amc_branch DECIMAL(10,2) DEFAULT 0,
                next_renewal_date DATE DEFAULT NULL,
                last_renewal_date DATE DEFAULT NULL,
                last_revision_date DATE DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M7: ' . $e->getMessage()); }

    try {
        // Migration 8: Client agreements table
        if (!dbTableExists('client_agreements')) {
            execute("CREATE TABLE IF NOT EXISTS client_agreements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                agreement_type VARCHAR(50) DEFAULT 'service',
                title VARCHAR(255) NOT NULL,
                document_url VARCHAR(500) DEFAULT NULL,
                document_name VARCHAR(255) DEFAULT NULL,
                effective_date DATE DEFAULT NULL,
                expiry_date DATE DEFAULT NULL,
                amount DECIMAL(12,2) DEFAULT 0,
                status VARCHAR(20) DEFAULT 'pending',
                approved_by INTEGER DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                approval_notes TEXT DEFAULT NULL,
                sale_by INTEGER DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                uploaded_by VARCHAR(20) DEFAULT 'admin',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M8: ' . $e->getMessage()); }

    try {
        // Migration 9: Client charge history table
        if (!dbTableExists('client_charge_history')) {
            execute("CREATE TABLE IF NOT EXISTS client_charge_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                charge_type VARCHAR(50) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                description TEXT DEFAULT NULL,
                invoice_id INTEGER DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M9: ' . $e->getMessage()); }

    try {
        // Migration 10: Client documents table
        if (!dbTableExists('client_documents')) {
            execute("CREATE TABLE IF NOT EXISTS client_documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                document_type VARCHAR(50) DEFAULT 'other',
                document_name VARCHAR(255) NOT NULL,
                document_url VARCHAR(500) DEFAULT NULL,
                expiry_date DATE DEFAULT NULL,
                verified TINYINT(1) DEFAULT 0,
                verified_by INTEGER DEFAULT NULL,
                verified_at DATETIME DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M10: ' . $e->getMessage()); }

    try {
        // Migration 11: Client termination table
        if (!dbTableExists('client_termination')) {
            execute("CREATE TABLE IF NOT EXISTS client_termination (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                termination_type VARCHAR(50) DEFAULT 'voluntary',
                reason TEXT DEFAULT NULL,
                termination_date DATE NOT NULL,
                final_amount DECIMAL(12,2) DEFAULT 0,
                settled TINYINT(1) DEFAULT 0,
                settled_by INTEGER DEFAULT NULL,
                settled_at DATETIME DEFAULT NULL,
                approved_by INTEGER DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M11: ' . $e->getMessage()); }

    try {
        // Migration 12: Invoices table
        if (!dbTableExists('invoices')) {
            execute("CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_number VARCHAR(50) UNIQUE NOT NULL,
                client_id INTEGER DEFAULT NULL,
                user_id INTEGER DEFAULT NULL,
                due_date DATE DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                terms TEXT DEFAULT NULL,
                tax_rate DECIMAL(5,2) DEFAULT 0,
                subtotal DECIMAL(12,2) DEFAULT 0,
                tax_amount DECIMAL(12,2) DEFAULT 0,
                total_amount DECIMAL(12,2) DEFAULT 0,
                status VARCHAR(20) DEFAULT 'draft',
                paid_at DATETIME DEFAULT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M12: ' . $e->getMessage()); }

    try {
        // Migration 14: Add missing columns to clients table
        $cols = ['user_id', 'client_code', 'head_office_amc', 'branch_office_amc'];
        foreach ($cols as $col) {
            if (!dbColumnExists('clients', $col)) {
                $type = $col === 'user_id' ? 'INTEGER DEFAULT NULL' : 'TEXT DEFAULT NULL';
                execute("ALTER TABLE clients ADD COLUMN $col $type");
            }
        }
        if (!dbColumnExists('clients', 'contact_name')) {
            execute("ALTER TABLE clients ADD COLUMN contact_name TEXT DEFAULT NULL");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M14: ' . $e->getMessage()); }

    try {
        // Migration 15: Add indexes for performance
        // Each index is guarded individually so a missing column in one table
        // does not prevent all subsequent indexes from being created.
        $m15 = [
            "CREATE INDEX IF NOT EXISTS idx_clients_status        ON clients(status)",
            "CREATE INDEX IF NOT EXISTS idx_clients_user_id       ON clients(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_invoices_client_id    ON invoices(client_id)",
            "CREATE INDEX IF NOT EXISTS idx_invoices_status       ON invoices(status)",
            // tickets uses user_id, not client_id
            "CREATE INDEX IF NOT EXISTS idx_tickets_user_id       ON tickets(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_tickets_status        ON tickets(status)",
            // client_subscriptions uses user_id, not client_id
            "CREATE INDEX IF NOT EXISTS idx_client_subscriptions_user_id ON client_subscriptions(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_notices_active        ON notices(is_active, starts_at, ends_at)",
            "CREATE INDEX IF NOT EXISTS idx_users_email           ON users(email)",
            // users uses active flag, not a status column
            "CREATE INDEX IF NOT EXISTS idx_users_active          ON users(active)",
            "CREATE INDEX IF NOT EXISTS idx_crm_leads_stage       ON crm_leads(stage)",
            "CREATE INDEX IF NOT EXISTS idx_crm_leads_next_followup ON crm_leads(next_followup)",
            "CREATE INDEX IF NOT EXISTS idx_crm_leads_assigned    ON crm_leads(assigned_to)",
            "CREATE INDEX IF NOT EXISTS idx_crm_followups_lead_id ON crm_followups(lead_id)",
            "CREATE INDEX IF NOT EXISTS idx_api_tokens_client     ON api_tokens(client_id)",
            "CREATE INDEX IF NOT EXISTS idx_demo_requests_status  ON demo_requests(status)",
            "CREATE INDEX IF NOT EXISTS idx_contact_submissions_status ON contact_submissions(status)",
        ];
        foreach ($m15 as $idxSql) {
            try { execute($idxSql); } catch (\Throwable $e2) {
                error_log('[db-migrations] M15 index: ' . $e2->getMessage() . ' | SQL: ' . substr($idxSql, 0, 80));
            }
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M15: ' . $e->getMessage()); }

    try {
        // Migration 16: Add indexes for high-traffic tables missing coverage
        $m16 = [
            "CREATE INDEX IF NOT EXISTS idx_audit_log_user_id      ON audit_log(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_audit_log_action       ON audit_log(action)",
            "CREATE INDEX IF NOT EXISTS idx_audit_log_created_at   ON audit_log(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_ticket_replies_ticket   ON ticket_replies(ticket_id)",
            "CREATE INDEX IF NOT EXISTS idx_support_messages_conv   ON support_messages(conversation_id)",
            "CREATE INDEX IF NOT EXISTS idx_notifications_user_id   ON notifications(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_notifications_seen_at   ON notifications(seen_at)",
            "CREATE INDEX IF NOT EXISTS idx_crm_proposals_lead_id   ON crm_proposals(lead_id)",
            "CREATE INDEX IF NOT EXISTS idx_orders_user_id          ON orders(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_orders_status           ON orders(status)",
            "CREATE INDEX IF NOT EXISTS idx_activity_log_user_id    ON activity_log(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log(created_at)",
        ];
        foreach ($m16 as $idxSql) {
            try { execute($idxSql); } catch (\Throwable $e2) {
                error_log('[db-migrations] M16 index: ' . $e2->getMessage() . ' | SQL: ' . substr($idxSql, 0, 80));
            }
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M16: ' . $e->getMessage()); }

    try {
        // Migration 17: Add missing columns to invoices + sla_breached to tickets
        $m17 = [
            "ALTER TABLE invoices ADD COLUMN user_id INTEGER",
            "ALTER TABLE invoices ADD COLUMN terms TEXT",
            "ALTER TABLE invoices ADD COLUMN tax_rate REAL NOT NULL DEFAULT 0",
            "ALTER TABLE invoices ADD COLUMN created_by INTEGER",
            "ALTER TABLE invoices ADD COLUMN billing_period_from TEXT",
            "ALTER TABLE invoices ADD COLUMN billing_period_to TEXT",
            "ALTER TABLE tickets ADD COLUMN sla_breached INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE client_documents ADD COLUMN status TEXT NOT NULL DEFAULT 'pending'",
            "ALTER TABLE client_documents ADD COLUMN approved_by INTEGER",
            "ALTER TABLE client_documents ADD COLUMN approved_at TEXT",
            "ALTER TABLE client_documents ADD COLUMN notes TEXT",
            "ALTER TABLE client_documents ADD COLUMN rejection_reason TEXT",
            "ALTER TABLE clients ADD COLUMN custom_charge_type TEXT",
            "ALTER TABLE clients ADD COLUMN custom_charge_value REAL DEFAULT 0",
            "ALTER TABLE amc_renewal_config ADD COLUMN last_revision_date TEXT",
        ];
        foreach ($m17 as $sql) {
            try { execute($sql); } catch (\Throwable $e2) {
                // Ignore "duplicate column" errors (already applied)
                if (stripos($e2->getMessage(), 'duplicate column') === false) {
                    error_log('[db-migrations] M17: ' . $e2->getMessage() . ' | SQL: ' . substr($sql, 0, 80));
                }
            }
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M17: ' . $e->getMessage()); }

    try {
        // Migration 18: Add service tracking columns to clients table
        $m18 = [
            "ALTER TABLE clients ADD COLUMN agreement_date DATE",
            "ALTER TABLE clients ADD COLUMN installation_date DATE",
            "ALTER TABLE clients ADD COLUMN num_branches INTEGER NOT NULL DEFAULT 1",
            "ALTER TABLE clients ADD COLUMN cloud_gb REAL",
            "ALTER TABLE clients ADD COLUMN assigned_by INTEGER",
        ];
        foreach ($m18 as $sql) {
            try { execute($sql); } catch (\Throwable $e2) {
                // Ignore "duplicate column" errors (already applied)
                if (stripos($e2->getMessage(), 'duplicate column') === false &&
                    stripos($e2->getMessage(), 'can\'t overwrite') === false) {
                    error_log('[db-migrations] M18: ' . $e2->getMessage() . ' | SQL: ' . substr($sql, 0, 80));
                }
            }
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M18: ' . $e->getMessage()); }

    try {
        // Migration 19: Add installation_cost column
        if (!dbColumnExists('clients', 'installation_cost')) {
            execute("ALTER TABLE clients ADD COLUMN installation_cost REAL DEFAULT NULL AFTER integration_charge");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M19: ' . $e->getMessage()); }
}
