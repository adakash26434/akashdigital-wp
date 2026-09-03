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
    // information_schema works with native PDO prepares; SHOW TABLES LIKE ? often does not
    $r = queryOne(
        "SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1",
        [$table]
    );
    return (bool)$r;
}

/** Check whether a column exists in a table (MySQL or SQLite) */
function dbColumnExists(string $table, string $column): bool {
    if (!dbTableExists($table)) return false;
    if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
        $cols = query("PRAGMA table_info(" . $table . ")");
        foreach ($cols as $c) {
            if (($c['name'] ?? '') === $column) return true;
        }
        return false;
    }
    // information_schema works with native PDO prepares; SHOW COLUMNS LIKE ? often does not
    $r = queryOne(
        "SELECT 1 AS ok FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1",
        [$table, $column]
    );
    return (bool)$r;
}

/**
 * Add missing columns one-by-one (safe on older live MySQL / SQLite).
 * $cols = ['column_name' => 'VARCHAR(255) NULL', ...]
 */
function dbEnsureColumns(string $table, array $cols): void {
    if (!dbTableExists($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) return;
    foreach ($cols as $col => $definition) {
        if (!is_string($col) || !preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
        try {
            if (!dbColumnExists($table, $col)) {
                execute("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'duplicate') === false) {
                error_log("[db-migrations] ensure {$table}.{$col}: " . $msg);
            }
        }
    }
}

/**
 * Older live MySQL often has ENUM('client','partner') etc. Admin forms send
 * extra values (channel/solution/investor) → SQLSTATE 1265 Data truncated.
 * Widen ENUM/SET/short VARCHAR to the given MySQL definition. No-op on SQLite.
 */
function dbEnsureFlexibleStringColumn(string $table, string $column, string $mysqlDefinition): void {
    if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') return;
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) return;
    if (!dbTableExists($table)) return;

    try {
        if (!dbColumnExists($table, $column)) {
            execute("ALTER TABLE `$table` ADD COLUMN `$column` $mysqlDefinition");
            return;
        }

        $info = queryOne(
            "SELECT DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1",
            [$table, $column]
        );
        if (!$info) return;

        $dataType = strtolower((string)($info['DATA_TYPE'] ?? ''));
        $needsWiden = in_array($dataType, ['enum', 'set'], true);

        if (!$needsWiden && in_array($dataType, ['char', 'varchar'], true)) {
            $len = (int)($info['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
            if (preg_match('/VARCHAR\((\d+)\)/i', $mysqlDefinition, $m) && $len > 0 && $len < (int)$m[1]) {
                $needsWiden = true;
            }
        }

        if ($needsWiden) {
            execute("ALTER TABLE `$table` MODIFY COLUMN `$column` $mysqlDefinition");
        }
    } catch (\Throwable $e) {
        error_log("[db-migrations] flexible {$table}.{$column}: " . $e->getMessage());
    }
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
        // Note: TEXT DEFAULT is invalid in MySQL 5.7+; use VARCHAR(50) so DEFAULT works on both MySQL and SQLite.
        if (!dbColumnExists('team_members', 'category')) {
            execute("ALTER TABLE team_members ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'management'");
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
        // Migration 4: Add contact/location columns to partners (live DBs often predate these)
        if (dbTableExists('partners')) {
            $partnerCols = [
                'email'           => 'ALTER TABLE partners ADD COLUMN email VARCHAR(255) NULL',
                'phone'           => 'ALTER TABLE partners ADD COLUMN phone VARCHAR(50) NULL',
                'address'         => 'ALTER TABLE partners ADD COLUMN address TEXT NULL',
                'district'        => 'ALTER TABLE partners ADD COLUMN district VARCHAR(100) NULL',
                'show_on_contact' => 'ALTER TABLE partners ADD COLUMN show_on_contact TINYINT NOT NULL DEFAULT 0',
            ];
            foreach ($partnerCols as $col => $sql) {
                try {
                    if (!dbColumnExists('partners', $col)) {
                        execute($sql);
                    }
                } catch (\Throwable $eCol) {
                    error_log('[db-migrations] M4 ' . $col . ': ' . $eCol->getMessage());
                }
            }
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
            execute("ALTER TABLE clients ADD COLUMN installation_cost DECIMAL(12,2) DEFAULT NULL");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M19: ' . $e->getMessage()); }

    try {
        // Migration 20: Add position, starts_at columns to job_listings for ordering and scheduled publishing
        if (dbTableExists('job_listings')) {
            if (!dbColumnExists('job_listings', 'position')) {
                execute("ALTER TABLE job_listings ADD COLUMN position INTEGER NOT NULL DEFAULT 0");
            }
            if (!dbColumnExists('job_listings', 'starts_at')) {
                execute("ALTER TABLE job_listings ADD COLUMN starts_at TEXT");
            }
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M20: ' . $e->getMessage()); }

    try {
        // Migration 21: Add cv_file column to job_applications for file upload
        if (dbTableExists('job_applications') && !dbColumnExists('job_applications', 'cv_file')) {
            execute("ALTER TABLE job_applications ADD COLUMN cv_file TEXT");
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M21: ' . $e->getMessage()); }

    try {
        // Migration 22: Widen job_listings text columns (live DBs may have smaller limits)
        if (dbTableExists('job_listings')) {
            if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
                // SQLite columns are already flexible TEXT types
            } else {
                execute("ALTER TABLE job_listings MODIFY COLUMN experience VARCHAR(255) NULL");
                execute("ALTER TABLE job_listings MODIFY COLUMN salary_range VARCHAR(255) NULL");
                execute("ALTER TABLE job_listings MODIFY COLUMN department VARCHAR(255) NULL");
                execute("ALTER TABLE job_listings MODIFY COLUMN location VARCHAR(255) NULL");
            }
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M22: ' . $e->getMessage()); }

    // ── M23+: Live-safe column/table backfills (older production schemas) ──

    try {
        // Migration 23: products — home cards, lucide, demo screenshot, content fields
        dbEnsureColumns('products', [
            'tagline'             => "VARCHAR(255) NULL",
            'summary'             => "TEXT NULL",
            'description'         => "LONGTEXT NULL",
            'icon'                => "VARCHAR(100) DEFAULT 'box'",
            'lucide_icon'         => "VARCHAR(100) DEFAULT 'package'",
            'icon_color'          => "VARCHAR(50) DEFAULT 'blue'",
            'badge'               => "VARCHAR(50) NULL",
            'category'            => "VARCHAR(100) NULL",
            'highlights'          => "TEXT NULL",
            'features'            => "TEXT NULL",
            'price_from'          => "DECIMAL(12,2) NULL",
            'show_on_home'        => "TINYINT NOT NULL DEFAULT 0",
            'home_position'       => "INT NOT NULL DEFAULT 0",
            'home_card_wide'      => "TINYINT NOT NULL DEFAULT 0",
            'home_card_dark'      => "TINYINT NOT NULL DEFAULT 0",
            'home_bg_css'         => "TEXT NULL",
            'demo_screenshot_url' => "VARCHAR(500) NULL",
            'tab_label'           => "VARCHAR(100) NULL",
            'position'            => "INT NOT NULL DEFAULT 0",
            'active'              => "TINYINT NOT NULL DEFAULT 1",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M23: ' . $e->getMessage()); }

    try {
        // Migration 24: services — detail page + listing fields
        dbEnsureColumns('services', [
            'tagline'        => "VARCHAR(255) DEFAULT ''",
            'summary'        => "TEXT NULL",
            'description'    => "LONGTEXT NULL",
            'icon'           => "VARCHAR(100) DEFAULT 'settings'",
            'lucide_icon'    => "VARCHAR(100) DEFAULT 'layers'",
            'icon_color'     => "VARCHAR(50) DEFAULT 'blue'",
            'badge'          => "VARCHAR(50) DEFAULT ''",
            'price_from'     => "DECIMAL(12,2) NULL",
            'highlights'     => "TEXT NULL",
            'features'       => "TEXT NULL",
            'screenshot_url' => "VARCHAR(500) NULL",
            'position'       => "INT NOT NULL DEFAULT 0",
            'active'         => "TINYINT NOT NULL DEFAULT 1",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M24: ' . $e->getMessage()); }

    try {
        // Migration 25: news — source_url + common CMS fields
        dbEnsureColumns('news', [
            'excerpt'      => "TEXT NULL",
            'content'      => "LONGTEXT NULL",
            'image_url'    => "VARCHAR(500) NULL",
            'cover_url'    => "VARCHAR(500) NULL",
            'author_name'  => "VARCHAR(100) DEFAULT 'Company'",
            'author_title' => "VARCHAR(100) DEFAULT 'Team'",
            'read_time'    => "INT NULL",
            'category'     => "VARCHAR(100) DEFAULT 'News'",
            'tags'         => "TEXT NULL",
            'featured'     => "TINYINT NOT NULL DEFAULT 0",
            'active'       => "TINYINT NOT NULL DEFAULT 1",
            'published'    => "TINYINT NOT NULL DEFAULT 0",
            'published_at' => "DATETIME NULL",
            'views'        => "INT NOT NULL DEFAULT 0",
            'source_url'   => "VARCHAR(500) NULL",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M25: ' . $e->getMessage()); }

    try {
        // Migration 26: team_members contact/leadership fields
        dbEnsureColumns('team_members', [
            'bio'           => "TEXT NULL",
            'photo_url'     => "VARCHAR(500) NULL",
            'email'         => "VARCHAR(255) NULL",
            'linkedin_url'  => "VARCHAR(500) NULL",
            'is_leadership' => "TINYINT NOT NULL DEFAULT 0",
            'category'      => "VARCHAR(50) NOT NULL DEFAULT 'management'",
            'active'        => "TINYINT NOT NULL DEFAULT 1",
            'position'      => "INT NOT NULL DEFAULT 0",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M26: ' . $e->getMessage()); }

    try {
        // Migration 27: clients — geo, billing, sale, product fields used by client-form
        dbEnsureColumns('clients', [
            'logo_url'             => "VARCHAR(500) NULL",
            'contact_name'         => "VARCHAR(255) NULL",
            'contact_email'        => "VARCHAR(255) NULL",
            'contact_phone'        => "VARCHAR(30) NULL",
            'billing_email'        => "VARCHAR(255) NULL",
            'client_code'          => "VARCHAR(50) NULL",
            'user_id'              => "INT NULL",
            'claimed_at'           => "DATETIME NULL",
            'product'              => "TEXT NULL",
            'cbs_use'              => "TINYINT NOT NULL DEFAULT 1",
            'integration'          => "VARCHAR(255) NULL",
            'integration_charge'   => "DECIMAL(12,2) NULL",
            'installation_cost'    => "DECIMAL(12,2) NULL",
            'district'             => "VARCHAR(100) NULL",
            'province'             => "VARCHAR(100) NULL",
            'local_govt'           => "VARCHAR(100) NULL",
            'ward_no'              => "VARCHAR(10) NULL",
            'address'              => "TEXT NULL",
            'pan_no'               => "VARCHAR(20) NULL",
            'reg_no'               => "VARCHAR(50) NULL",
            'established_year'     => "SMALLINT NULL",
            'total_members'        => "INT NULL",
            'total_branches'       => "INT NULL",
            'website'              => "VARCHAR(255) NULL",
            'notes'                => "TEXT NULL",
            'status'               => "VARCHAR(30) NOT NULL DEFAULT 'active'",
            'head_office_amc'      => "DECIMAL(12,2) NULL",
            'branch_office_amc'    => "DECIMAL(12,2) NULL",
            'cloud_charge_ho'      => "DECIMAL(12,2) NULL",
            'cloud_charge_branch'  => "DECIMAL(12,2) NULL",
            'custom_charge_type'   => "VARCHAR(50) NULL",
            'custom_charge_value'  => "DECIMAL(12,2) NULL",
            'agreement_date'       => "DATE NULL",
            'installation_date'    => "DATE NULL",
            'num_branches'         => "INT NOT NULL DEFAULT 1",
            'cloud_gb'             => "DECIMAL(10,2) NULL",
            'sale_type'            => "VARCHAR(30) NOT NULL DEFAULT 'office_sale'",
            'channel_partner_id'   => "INT NULL",
            'sale_date'            => "DATE NULL",
            'sale_by'              => "INT NULL",
            'assigned_by'          => "INT NULL",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M27: ' . $e->getMessage()); }

    try {
        // Migration 28: invoices money columns + invoice_items table
        dbEnsureColumns('invoices', [
            'user_id'             => "INT NULL",
            'billing_period_from' => "DATE NULL",
            'billing_period_to'   => "DATE NULL",
            'subtotal'            => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'tax_rate'            => "DECIMAL(5,2) NOT NULL DEFAULT 0",
            'tax_amount'          => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'discount_amount'     => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'total_amount'        => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'currency'            => "VARCHAR(3) DEFAULT 'NPR'",
            'amount_paid'         => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'amount_due'          => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'due_date'            => "DATE NULL",
            'status'              => "VARCHAR(30) NOT NULL DEFAULT 'draft'",
            'notes'               => "TEXT NULL",
            'terms'               => "TEXT NULL",
            'attachment_url'      => "VARCHAR(500) NULL",
            'attachment_name'     => "VARCHAR(255) NULL",
            'paid_at'             => "DATETIME NULL",
            'created_by'          => "INT NULL",
        ]);
        if (!dbTableExists('invoice_items')) {
            if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
                execute("CREATE TABLE IF NOT EXISTS invoice_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    invoice_id INTEGER NOT NULL,
                    description TEXT NOT NULL,
                    item_type TEXT NOT NULL DEFAULT 'other',
                    quantity REAL NOT NULL DEFAULT 1,
                    unit_price REAL NOT NULL DEFAULT 0,
                    total_price REAL NOT NULL DEFAULT 0,
                    effective_date TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT (datetime('now'))
                )");
            } else {
                execute("CREATE TABLE IF NOT EXISTS invoice_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    invoice_id INT NOT NULL,
                    description VARCHAR(500) NOT NULL,
                    item_type VARCHAR(50) NOT NULL DEFAULT 'other',
                    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    total_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    effective_date DATE NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_invoice (invoice_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M28: ' . $e->getMessage()); }

    try {
        // Migration 29: CRM client satellite tables — align columns used by admin code
        dbEnsureColumns('client_agreements', [
            'notes'            => "TEXT NULL",
            'approval_notes'   => "TEXT NULL",
            'document_url'     => "VARCHAR(500) NULL",
            'document_name'    => "VARCHAR(255) NULL",
            'effective_date'   => "DATE NULL",
            'expiry_date'      => "DATE NULL",
            'amount'           => "DECIMAL(12,2) DEFAULT 0",
            'status'           => "VARCHAR(20) DEFAULT 'pending'",
            'approved_by'      => "INT NULL",
            'approved_at'      => "DATETIME NULL",
            'sale_by'          => "INT NULL",
            'sale_type'        => "VARCHAR(30) NULL",
            'channel_partner_id' => "INT NULL",
            'created_by'       => "INT NULL",
            'uploaded_by'      => "VARCHAR(20) DEFAULT 'admin'",
        ]);
        dbEnsureColumns('client_documents', [
            'document_type'    => "VARCHAR(50) DEFAULT 'other'",
            'doc_type'         => "VARCHAR(50) NULL",
            'title'            => "VARCHAR(255) NULL",
            'document_name'    => "VARCHAR(255) NULL",
            'document_url'     => "VARCHAR(500) NULL",
            'expiry_date'      => "DATE NULL",
            'status'           => "VARCHAR(30) NOT NULL DEFAULT 'pending'",
            'notes'            => "TEXT NULL",
            'rejection_reason' => "TEXT NULL",
            'approved_by'      => "INT NULL",
            'approved_at'      => "DATETIME NULL",
            'verified'         => "TINYINT NOT NULL DEFAULT 0",
            'verified_by'      => "INT NULL",
            'verified_at'      => "DATETIME NULL",
            'created_by'       => "INT NULL",
        ]);
        dbEnsureColumns('client_charge_history', [
            'amount'          => "DECIMAL(12,2) NULL",
            'description'     => "TEXT NULL",
            'old_value'       => "DECIMAL(12,2) NULL",
            'new_value'       => "DECIMAL(12,2) NULL",
            'effective_date'  => "DATE NULL",
            'reason'          => "VARCHAR(255) NULL",
            'changed_by'      => "INT NULL",
            'created_by'      => "INT NULL",
            'invoice_id'      => "INT NULL",
        ]);
        dbEnsureColumns('client_termination', [
            'termination_type' => "VARCHAR(50) NULL",
            'termination_date' => "DATE NULL",
            'terminated_at'    => "DATETIME NULL",
            'reason'           => "TEXT NULL",
            'remarks'          => "TEXT NULL",
            'document_url'     => "VARCHAR(500) NULL",
            'terminated_by'    => "INT NULL",
            'final_amount'     => "DECIMAL(12,2) DEFAULT 0",
            'settled'          => "TINYINT NOT NULL DEFAULT 0",
            'settled_by'       => "INT NULL",
            'settled_at'       => "DATETIME NULL",
            'approved_by'      => "INT NULL",
            'approved_at'      => "DATETIME NULL",
            'created_by'       => "INT NULL",
        ]);
        if (!dbTableExists('client_status_history')) {
            if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
                execute("CREATE TABLE IF NOT EXISTS client_status_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    client_id INTEGER NOT NULL,
                    old_status TEXT NULL,
                    new_status TEXT NOT NULL,
                    changed_at TEXT NOT NULL DEFAULT (datetime('now')),
                    changed_by INTEGER NULL,
                    reason TEXT NULL
                )");
            } else {
                execute("CREATE TABLE IF NOT EXISTS client_status_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL,
                    old_status VARCHAR(30) NULL,
                    new_status VARCHAR(30) NOT NULL,
                    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    changed_by INT NULL,
                    reason VARCHAR(255) NULL,
                    INDEX idx_client (client_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M29: ' . $e->getMessage()); }

    try {
        // Migration 30: license activation fields on client_subscriptions (used by includes/license.php)
        dbEnsureColumns('client_subscriptions', [
            'license_key'       => "VARCHAR(255) NULL",
            'deployment_type'   => "VARCHAR(50) NULL",
            'branches'          => "INT NOT NULL DEFAULT 1",
            'members_limit'     => "INT NULL",
            'max_users'         => "INT NULL",
            'amount'            => "DECIMAL(12,2) NULL",
            'billing_cycle'     => "VARCHAR(30) DEFAULT 'monthly'",
            'status'            => "VARCHAR(30) NOT NULL DEFAULT 'active'",
            'activation_status' => "VARCHAR(30) NOT NULL DEFAULT 'inactive'",
            'hardware_id'       => "VARCHAR(255) NULL",
            'activated_at'      => "DATETIME NULL",
            'last_seen_at'      => "DATETIME NULL",
            'starts_at'         => "DATE NULL",
            'expires_at'        => "DATE NULL",
            'next_renewal'      => "DATE NULL",
            'notes'             => "TEXT NULL",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M30: ' . $e->getMessage()); }

    try {
        // Migration 31: users 2FA columns
        dbEnsureColumns('users', [
            'client_code'      => "VARCHAR(50) NULL",
            'phone'            => "VARCHAR(20) NULL",
            'org_name'         => "VARCHAR(255) NULL",
            'district'         => "VARCHAR(100) NULL",
            'bio'              => "TEXT NULL",
            'avatar_url'       => "VARCHAR(500) NULL",
            'theme_pref'       => "VARCHAR(20) NOT NULL DEFAULT 'dark'",
            'totp_secret'      => "VARCHAR(255) NULL",
            'totp_enabled'     => "TINYINT NOT NULL DEFAULT 0",
            'totp_backup_code' => "VARCHAR(20) NULL",
            'require_2fa'      => "TINYINT NOT NULL DEFAULT 0",
            'last_login_at'    => "DATETIME NULL",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M31: ' . $e->getMessage()); }

    try {
        // Migration 32: portfolio — admin form uses summary/tags/url (schema also has excerpt)
        dbEnsureColumns('portfolio', [
            'client_name'   => "VARCHAR(255) NULL",
            'category'      => "VARCHAR(100) NULL",
            'excerpt'       => "TEXT NULL",
            'summary'       => "TEXT NULL",
            'description'   => "LONGTEXT NULL",
            'image_url'     => "VARCHAR(500) NULL",
            'result_metric' => "VARCHAR(255) NULL",
            'tags'          => "TEXT NULL",
            'url'           => "VARCHAR(500) NULL",
            'featured'      => "TINYINT NOT NULL DEFAULT 0",
            'active'        => "TINYINT NOT NULL DEFAULT 1",
            'position'      => "INT NOT NULL DEFAULT 0",
            'published_at'  => "DATETIME NULL",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M32: ' . $e->getMessage()); }

    try {
        // Migration 33: demo_requests — support both live column name variants
        dbEnsureColumns('demo_requests', [
            'product'        => "VARCHAR(255) NULL",
            'product_name'   => "VARCHAR(255) NULL",
            'org_name'       => "VARCHAR(255) NULL",
            'contact_name'   => "VARCHAR(255) NULL",
            'email'          => "VARCHAR(255) NULL",
            'contact_email'  => "VARCHAR(255) NULL",
            'phone'          => "VARCHAR(50) NULL",
            'contact_phone'  => "VARCHAR(50) NULL",
            'message'        => "TEXT NULL",
            'status'         => "VARCHAR(30) NOT NULL DEFAULT 'new'",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M33: ' . $e->getMessage()); }

    try {
        // Migration 34: pricing_plans fields used by admin/pricing.php
        dbEnsureColumns('pricing_plans', [
            'tag'         => "VARCHAR(100) NULL",
            'price_label' => "VARCHAR(100) NULL",
            'period'      => "VARCHAR(50) NULL",
            'cta_label'   => "VARCHAR(100) NULL",
            'cta_url'     => "VARCHAR(500) NULL",
            'is_popular'  => "TINYINT NOT NULL DEFAULT 0",
            'features'    => "TEXT NULL",
            'product_id'  => "INT NULL",
            'active'      => "TINYINT NOT NULL DEFAULT 1",
            'position'    => "INT NOT NULL DEFAULT 0",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M34: ' . $e->getMessage()); }

    try {
        // Migration 35: widen ENUM `type` columns that admin forms write freely
        // (fixes: SQLSTATE[01000] Warning: 1265 Data truncated for column 'type')
        dbEnsureFlexibleStringColumn('partners', 'type', "VARCHAR(30) NOT NULL DEFAULT 'client'");
        dbEnsureFlexibleStringColumn('job_listings', 'type', "VARCHAR(30) NOT NULL DEFAULT 'full-time'");
        dbEnsureFlexibleStringColumn('announcements', 'type', "VARCHAR(30) NOT NULL DEFAULT 'info'");
        dbEnsureFlexibleStringColumn('support_contacts', 'type', "VARCHAR(30) NOT NULL DEFAULT 'phone'");
        dbEnsureFlexibleStringColumn('crm_followups', 'type', "VARCHAR(30) NOT NULL DEFAULT 'call'");
        dbEnsureFlexibleStringColumn('activity_log', 'type', "VARCHAR(50) NOT NULL");
    } catch (\Throwable $e) { error_log('[db-migrations] M35: ' . $e->getMessage()); }

    try {
        // Migration 36: news — optional portal / press name for external coverage links
        dbEnsureColumns('news', [
            'source_name' => "VARCHAR(120) NULL",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M36: ' . $e->getMessage()); }

    try {
        // Migration 37: team org-chart level (1 = top alone, 2+, null/0 = auto from role)
        dbEnsureColumns('team_members', [
            'org_tier' => "TINYINT NULL DEFAULT 0",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M37: ' . $e->getMessage()); }

    try {
        // Migration 38: products + services — optional JSON gallery (max 2 URLs on detail pages)
        dbEnsureColumns('products', [
            'screenshots' => "TEXT NULL",
        ]);
        dbEnsureColumns('services', [
            'screenshots' => "TEXT NULL",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M38: ' . $e->getMessage()); }

    try {
        // Migration 39: product/service billing cycle (month, day, year, license)
        dbEnsureColumns('products', [
            'price_period' => "VARCHAR(20) NOT NULL DEFAULT 'month'",
        ]);
        dbEnsureColumns('services', [
            'price_period' => "VARCHAR(20) NOT NULL DEFAULT 'month'",
        ]);
    } catch (\Throwable $e) { error_log('[db-migrations] M39: ' . $e->getMessage()); }

    try {
        // Migration 40: Support & Ticket Desk — invalid lucide names render an empty icon box
        if (dbTableExists('products') && dbColumnExists('products', 'lucide_icon')) {
            execute(
                "UPDATE products SET lucide_icon = 'ticket' WHERE (" .
                "LOWER(COALESCE(name,'')) LIKE '%ticket%' OR LOWER(COALESCE(slug,'')) LIKE '%ticket%' OR LOWER(COALESCE(slug,'')) IN ('support','support-desk')" .
                ") AND LOWER(TRIM(COALESCE(lucide_icon,''))) NOT IN (" .
                "'ticket','tickets','ticket-check','life-buoy','headset','headphones','inbox'" .
                ")"
            );
        }
    } catch (\Throwable $e) { error_log('[db-migrations] M40: ' . $e->getMessage()); }
}
