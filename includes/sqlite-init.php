<?php
/**
 * SQLite database initializer for  dev environment.
 * Called automatically from db.php when using SQLite driver.
 */

function sqliteIsInitialized(PDO $pdo): bool {
    $r = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
    return (bool)$r;
}

function sqliteInit(PDO $pdo): void {
    $pdo->exec("PRAGMA journal_mode=WAL");
    $pdo->exec("PRAGMA foreign_keys=ON");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        display_name TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'client',
        avatar_url TEXT,
        phone TEXT,
        org_name TEXT,
        client_code TEXT,
        district TEXT,
        bio TEXT,
        email_verified INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1,
        last_login_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS site_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT NOT NULL UNIQUE,
        setting_val TEXT,
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS team_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT '',
        bio TEXT,
        photo_url TEXT,
        email TEXT,
        linkedin_url TEXT,
        is_leadership INTEGER NOT NULL DEFAULT 0,
        category TEXT NOT NULL DEFAULT 'management',
        active INTEGER NOT NULL DEFAULT 1,
        position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        tagline TEXT DEFAULT '',
        summary TEXT,
        description TEXT,
        icon TEXT DEFAULT 'settings',
        lucide_icon TEXT DEFAULT 'layers',
        icon_color TEXT DEFAULT 'blue',
        badge TEXT DEFAULT '',
        price_from REAL DEFAULT NULL,
        highlights TEXT DEFAULT '[]',
        features TEXT,
        screenshot_url TEXT DEFAULT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        tagline TEXT,
        summary TEXT,
        description TEXT,
        icon TEXT DEFAULT 'box',
        lucide_icon TEXT DEFAULT 'package',
        icon_color TEXT DEFAULT 'blue',
        badge TEXT,
        category TEXT,
        highlights TEXT,
        features TEXT,
        price_from REAL,
        show_on_home INTEGER NOT NULL DEFAULT 0,
        home_position INTEGER NOT NULL DEFAULT 0,
        home_card_wide INTEGER NOT NULL DEFAULT 0,
        home_card_dark INTEGER NOT NULL DEFAULT 0,
        home_bg_css TEXT DEFAULT NULL,
        demo_screenshot_url TEXT DEFAULT NULL,
        tab_label TEXT DEFAULT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS pricing_plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        tag TEXT,
        price_label TEXT NOT NULL DEFAULT 'Contact us',
        period TEXT DEFAULT '/ month',
        cta_label TEXT DEFAULT 'Get started',
        cta_url TEXT,
        is_popular INTEGER NOT NULL DEFAULT 0,
        features TEXT,
        active INTEGER NOT NULL DEFAULT 1,
        position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS testimonials (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        author_name TEXT NOT NULL,
        author_role TEXT,
        author_org TEXT,
        photo_url TEXT,
        quote TEXT NOT NULL,
        rating INTEGER NOT NULL DEFAULT 5,
        product_ref TEXT,
        active INTEGER NOT NULL DEFAULT 1,
        position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS gallery (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        description TEXT,
        image_url TEXT NOT NULL,
        category TEXT DEFAULT 'General',
        active INTEGER NOT NULL DEFAULT 1,
        position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS partners (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        logo_url TEXT,
        url TEXT,
        type TEXT NOT NULL DEFAULT 'client',
        district TEXT,
        active INTEGER NOT NULL DEFAULT 1,
        position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS news (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        excerpt TEXT,
        content TEXT,
        image_url TEXT,
        cover_url TEXT,
        author_name TEXT DEFAULT 'Company',
        author_title TEXT DEFAULT 'Team',
        read_time INTEGER,
        category TEXT DEFAULT 'News',
        tags TEXT,
        featured INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1,
        published INTEGER NOT NULL DEFAULT 0,
        published_at TEXT,
        views INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS faqs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL DEFAULT 'General',
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS job_listings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        department TEXT,
        location TEXT DEFAULT 'Kathmandu, Nepal',
        type TEXT NOT NULL DEFAULT 'full-time',
        experience TEXT,
        salary_range TEXT,
        short_desc TEXT,
        description TEXT,
        requirements TEXT,
        perks TEXT,
        deadline TEXT,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS contact_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT,
        org_name TEXT,
        subject TEXT DEFAULT 'General Enquiry',
        message TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'new',
        notes TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS demo_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product TEXT NOT NULL,
        org_name TEXT NOT NULL,
        contact_name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT,
        members INTEGER,
        message TEXT,
        status TEXT NOT NULL DEFAULT 'new',
        notes TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS announcements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        body TEXT,
        type TEXT NOT NULL DEFAULT 'info',
        scope TEXT NOT NULL DEFAULT 'banner',
        page_target TEXT,
        btn_text TEXT,
        btn_url TEXT,
        active INTEGER NOT NULL DEFAULT 1,
        dismissible INTEGER NOT NULL DEFAULT 1,
        starts_at TEXT,
        ends_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS clients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        org_name TEXT NOT NULL,
        logo_url TEXT,
        contact_name TEXT,
        contact_email TEXT,
        contact_phone TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        district TEXT,
        province TEXT,
        local_govt TEXT,
        ward_no TEXT,
        address TEXT,
        product TEXT,
        client_code TEXT,
        user_id INTEGER DEFAULT NULL,
        head_office_amc TEXT,
        branch_office_amc TEXT,
        cloud_charge_ho TEXT,
        cloud_charge_branch TEXT,
        cloud_gb TEXT,
        cbs_use TEXT,
        agreement_date TEXT,
        installation_date TEXT,
        num_branches INTEGER DEFAULT 1,
        integration TEXT,
        integration_charge TEXT,
        notes TEXT,
        assigned_by INTEGER DEFAULT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS portfolio (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        client_name TEXT,
        category TEXT,
        excerpt TEXT,
        description TEXT,
        image_url TEXT,
        result_metric TEXT,
        featured INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1,
        position INTEGER NOT NULL DEFAULT 0,
        published_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    -- Missing tables added for full feature support
    CREATE TABLE IF NOT EXISTS email_verifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        token TEXT NOT NULL UNIQUE,
        expires_at TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        token TEXT NOT NULL UNIQUE,
        expires_at TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identifier TEXT NOT NULL UNIQUE,
        attempts INTEGER NOT NULL DEFAULT 1,
        last_attempt_at TEXT NOT NULL DEFAULT (datetime('now')),
        locked_until TEXT DEFAULT NULL
    );
    CREATE TABLE IF NOT EXISTS api_rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_hash TEXT NOT NULL UNIQUE,
        hits INTEGER NOT NULL DEFAULT 1,
        window_start TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS user_services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'active',
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS client_subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        product_id INTEGER DEFAULT NULL,
        product_name TEXT NOT NULL,
        plan_name TEXT NOT NULL,
        license_key TEXT DEFAULT NULL,
        deployment_type TEXT DEFAULT NULL,
        branches INTEGER NOT NULL DEFAULT 1,
        members_limit INTEGER DEFAULT NULL,
        amount REAL DEFAULT NULL,
        billing_cycle TEXT DEFAULT 'monthly',
        status TEXT NOT NULL DEFAULT 'active',
        starts_at TEXT DEFAULT NULL,
        expires_at TEXT DEFAULT NULL,
        next_renewal TEXT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS crm_leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        org_name TEXT DEFAULT NULL,
        email TEXT DEFAULT NULL,
        phone TEXT DEFAULT NULL,
        district TEXT DEFAULT NULL,
        source TEXT DEFAULT NULL,
        source_ref_id INTEGER DEFAULT NULL,
        products_interest TEXT DEFAULT NULL,
        stage TEXT NOT NULL DEFAULT 'prospect',
        stage_notes TEXT DEFAULT NULL,
        deal_value REAL DEFAULT NULL,
        next_followup TEXT DEFAULT NULL,
        last_contact_at TEXT DEFAULT NULL,
        assigned_to INTEGER DEFAULT NULL,
        won_at TEXT DEFAULT NULL,
        lost_reason TEXT DEFAULT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS crm_followups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER NOT NULL,
        user_id INTEGER DEFAULT NULL,
        type TEXT NOT NULL DEFAULT 'call',
        notes TEXT DEFAULT NULL,
        outcome TEXT DEFAULT NULL,
        next_followup TEXT DEFAULT NULL,
        followup_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE TABLE IF NOT EXISTS crm_proposals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lead_id INTEGER NOT NULL,
        user_id INTEGER DEFAULT NULL,
        title TEXT NOT NULL,
        products TEXT DEFAULT NULL,
        amount REAL DEFAULT NULL,
        valid_until TEXT DEFAULT NULL,
        status TEXT NOT NULL DEFAULT 'draft',
        notes TEXT DEFAULT NULL,
        file_url TEXT DEFAULT NULL,
        sent_at TEXT DEFAULT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    ");

    // ── Tables added after initial release (idempotent) ──────
    sqliteMigrate($pdo);

    // Seed default data
    _sqliteInitSeedData($pdo);
}

/**
 * Creates any tables that were added after the initial sqliteInit().
 * Safe to run on every request — uses CREATE TABLE IF NOT EXISTS.
 *
 * NOTE: Do NOT add ALTER TABLE statements here for columns that already exist
 * in the CREATE TABLE definitions above — doing so causes suppressed PDO
 * errors on every request once the schema is initialised.
 */
function sqliteMigrate(PDO $pdo): void {
    // ── Add missing columns to existing clients table ─────────────
    $missingClientsCols = [
        'local_govt' => 'ALTER TABLE clients ADD COLUMN local_govt TEXT',
        'ward_no'    => 'ALTER TABLE clients ADD COLUMN ward_no TEXT',
        'notes'       => 'ALTER TABLE clients ADD COLUMN notes TEXT',
    ];
    foreach ($missingClientsCols as $col => $sql) {
        try {
            $pdo->exec("SELECT $col FROM clients LIMIT 1");
        } catch (\Throwable $e) {
            try { $pdo->exec($sql); } catch (\Throwable $e2) { /* ignore if exists */ }
        }
    }

    // ── Add missing columns to existing client_agreements table ─────
    $missingAgreementCols = [
        'document_url'       => 'ALTER TABLE client_agreements ADD COLUMN document_url TEXT',
        'document_name'      => 'ALTER TABLE client_agreements ADD COLUMN document_name TEXT',
        'effective_date'     => 'ALTER TABLE client_agreements ADD COLUMN effective_date TEXT',
        'expiry_date'        => 'ALTER TABLE client_agreements ADD COLUMN expiry_date TEXT',
        'amount'             => 'ALTER TABLE client_agreements ADD COLUMN amount REAL DEFAULT 0',
        'uploaded_by'        => 'ALTER TABLE client_agreements ADD COLUMN uploaded_by TEXT',
        'sale_by'            => 'ALTER TABLE client_agreements ADD COLUMN sale_by INTEGER DEFAULT NULL',
        'sale_type'          => 'ALTER TABLE client_agreements ADD COLUMN sale_type TEXT DEFAULT \'office_sale\'',
        'channel_partner_id' => 'ALTER TABLE client_agreements ADD COLUMN channel_partner_id INTEGER DEFAULT NULL',
        'created_by'         => 'ALTER TABLE client_agreements ADD COLUMN created_by INTEGER DEFAULT NULL',
    ];
    foreach ($missingAgreementCols as $col => $sql) {
        try {
            $pdo->exec("SELECT $col FROM client_agreements LIMIT 1");
        } catch (\Throwable $e) {
            try { $pdo->exec($sql); } catch (\Throwable $e2) { /* ignore if exists */ }
        }
    }

    // Create new tables for additional features
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS notifications (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        type       TEXT NOT NULL DEFAULT 'info',
        title      TEXT NOT NULL,
        body       TEXT,
        link_url   TEXT,
        icon       TEXT DEFAULT 'bell',
        seen_at    TEXT DEFAULT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS site_pages (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        slug       TEXT NOT NULL UNIQUE,
        title      TEXT NOT NULL,
        content    TEXT,
        meta_desc  TEXT,
        active     INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS client_termination (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id     INTEGER NOT NULL UNIQUE,
        terminated_at TEXT NOT NULL DEFAULT (datetime('now')),
        reason        TEXT NOT NULL,
        remarks       TEXT,
        document_url  TEXT DEFAULT NULL,
        terminated_by INTEGER DEFAULT NULL
    );

    CREATE TABLE IF NOT EXISTS client_status_history (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id  INTEGER NOT NULL,
        old_status TEXT DEFAULT NULL,
        new_status TEXT NOT NULL,
        changed_at TEXT NOT NULL DEFAULT (datetime('now')),
        changed_by INTEGER DEFAULT NULL,
        reason     TEXT DEFAULT NULL
    );

    CREATE TABLE IF NOT EXISTS onboarding_progress (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id      INTEGER NOT NULL UNIQUE,
        current_step INTEGER NOT NULL DEFAULT 1,
        total_steps  INTEGER NOT NULL DEFAULT 5,
        completed    INTEGER NOT NULL DEFAULT 0,
        data         TEXT,
        updated_at   TEXT NOT NULL DEFAULT (datetime('now')),
        created_at   TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS activity_log (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER DEFAULT NULL,
        type       TEXT NOT NULL,
        title      TEXT NOT NULL,
        body       TEXT,
        link_url   TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS status_components (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL,
        description TEXT,
        status      TEXT NOT NULL DEFAULT 'operational',
        sort_order  INTEGER NOT NULL DEFAULT 10,
        active      INTEGER NOT NULL DEFAULT 1,
        updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS status_incidents (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        title        TEXT NOT NULL,
        body         TEXT,
        severity     TEXT NOT NULL DEFAULT 'investigating',
        impact       TEXT NOT NULL DEFAULT 'minor',
        component_id INTEGER DEFAULT NULL,
        started_at   TEXT NOT NULL DEFAULT (datetime('now')),
        resolved_at  TEXT DEFAULT NULL
    );

    CREATE TABLE IF NOT EXISTS status_incident_updates (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        incident_id   INTEGER NOT NULL,
        status        TEXT NOT NULL DEFAULT 'investigating',
        message       TEXT,
        created_at    TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS orders (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id         INTEGER DEFAULT NULL,
        order_no        TEXT NOT NULL UNIQUE,
        customer_email  TEXT,
        product_name    TEXT,
        plan_name       TEXT,
        total           REAL DEFAULT 0,
        currency        TEXT NOT NULL DEFAULT 'NPR',
        status          TEXT NOT NULL DEFAULT 'pending',
        notes           TEXT,
        created_at      TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS amc_renewal_config (
        id                  INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id           INTEGER NOT NULL UNIQUE,
        renewal_cycle       TEXT NOT NULL DEFAULT '2years',
        cycle_months        INTEGER DEFAULT 24,
        increment_type      TEXT NOT NULL DEFAULT 'percentage',
        increment_value     REAL NOT NULL DEFAULT 0,
        base_amc_ho         REAL DEFAULT NULL,
        base_amc_branch     REAL DEFAULT NULL,
        next_renewal_date   TEXT DEFAULT NULL,
        last_renewal_date   TEXT DEFAULT NULL,
        created_at          TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at          TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS client_subscriptions (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id          INTEGER NOT NULL,
        product_id       INTEGER DEFAULT NULL,
        product_name     TEXT NOT NULL,
        plan_name        TEXT NOT NULL,
        license_key      TEXT DEFAULT NULL,
        deployment_type  TEXT DEFAULT NULL,
        branches         INTEGER NOT NULL DEFAULT 1,
        members_limit    INTEGER DEFAULT NULL,
        amount           REAL DEFAULT NULL,
        billing_cycle    TEXT DEFAULT 'monthly',
        status           TEXT NOT NULL DEFAULT 'active',
        starts_at        TEXT DEFAULT NULL,
        expires_at       TEXT DEFAULT NULL,
        created_at       TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at       TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS client_charge_history (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id       INTEGER NOT NULL,
        charge_type     TEXT NOT NULL,
        old_value       REAL DEFAULT NULL,
        new_value       REAL NOT NULL,
        effective_date  TEXT NOT NULL,
        reason          TEXT DEFAULT NULL,
        changed_by      INTEGER DEFAULT NULL,
        created_at      TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS branches (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id  INTEGER NOT NULL,
        code       TEXT,
        name       TEXT NOT NULL,
        address    TEXT,
        district   TEXT,
        province   TEXT,
        phone      TEXT,
        manager    TEXT,
        is_head    INTEGER DEFAULT 0,
        active     INTEGER DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS tickets (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id         INTEGER NOT NULL,
        number          TEXT NOT NULL UNIQUE,
        subject         TEXT NOT NULL,
        body            TEXT,
        category        TEXT DEFAULT 'General',
        product         TEXT DEFAULT NULL,
        priority        TEXT NOT NULL DEFAULT 'normal',
        status          TEXT NOT NULL DEFAULT 'open',
        sla_deadline    TEXT DEFAULT NULL,
        last_message_at TEXT DEFAULT NULL,
        created_at      TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS ticket_replies (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_id      INTEGER NOT NULL,
        author_id      INTEGER DEFAULT NULL,
        author_role    TEXT NOT NULL DEFAULT 'client',
        body           TEXT NOT NULL,
        attachment_url TEXT DEFAULT NULL,
        created_at     TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS support_contacts (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        label       TEXT NOT NULL,
        type        TEXT NOT NULL DEFAULT 'phone',
        department  TEXT DEFAULT NULL,
        value       TEXT NOT NULL,
        description TEXT DEFAULT NULL,
        is_primary  INTEGER NOT NULL DEFAULT 0,
        active      INTEGER NOT NULL DEFAULT 1,
        position    INTEGER NOT NULL DEFAULT 0,
        created_at  TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS support_conversations (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        visitor_name   TEXT NOT NULL DEFAULT 'Guest',
        visitor_email  TEXT DEFAULT NULL,
        status         TEXT NOT NULL DEFAULT 'open',
        last_message_at TEXT DEFAULT NULL,
        unread_visitor INTEGER NOT NULL DEFAULT 0,
        unread_admin   INTEGER NOT NULL DEFAULT 0,
        created_at     TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS support_messages (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        conversation_id INTEGER NOT NULL,
        sender          TEXT NOT NULL DEFAULT 'visitor',
        message         TEXT NOT NULL,
        created_at      TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS user_sessions (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id       INTEGER NOT NULL,
        event         TEXT NOT NULL DEFAULT 'login',
        ip            TEXT,
        user_agent    TEXT,
        device        TEXT,
        session_token TEXT,
        created_at    TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS api_tokens (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        name          TEXT NOT NULL,
        token_prefix  TEXT,
        token_hash    TEXT,
        client_id     INTEGER DEFAULT NULL,
        scopes        TEXT,
        last_used_at  TEXT,
        revoked_at    TEXT,
        created_at    TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS api_request_log (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        token_id    INTEGER DEFAULT NULL,
        path        TEXT,
        method      TEXT DEFAULT 'GET',
        status_code INTEGER,
        ip          TEXT,
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS audit_log (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id      INTEGER DEFAULT NULL,
        action       TEXT NOT NULL,
        target_table TEXT,
        target_id    INTEGER,
        old_val      TEXT,
        new_val      TEXT,
        ip           TEXT,
        user_agent   TEXT,
        created_at   TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS banners (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        title        TEXT NOT NULL,
        subtitle     TEXT,
        image_url    TEXT,
        link_url     TEXT,
        btn_text     TEXT,
        banner_style TEXT DEFAULT 'default',
        page_target  TEXT DEFAULT 'all',
        position     INTEGER NOT NULL DEFAULT 10,
        active       INTEGER NOT NULL DEFAULT 1,
        created_at   TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at   TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS client_licenses (
        id                INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id         INTEGER DEFAULT NULL,
        license_key       TEXT UNIQUE,
        product           TEXT,
        activation_status TEXT NOT NULL DEFAULT 'inactive',
        hardware_id       TEXT,
        activated_at      TEXT,
        expires_at        TEXT,
        created_at        TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS cron_runs (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        job         TEXT NOT NULL,
        status      TEXT NOT NULL DEFAULT 'ok',
        output      TEXT,
        started_at  TEXT,
        finished_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS email_intake_log (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        from_email  TEXT,
        to_email    TEXT,
        subject     TEXT,
        body        TEXT,
        processed   INTEGER NOT NULL DEFAULT 0,
        ticket_id   INTEGER DEFAULT NULL,
        fetched_at  TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS job_applications (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        job_listing_id INTEGER DEFAULT NULL,
        name           TEXT NOT NULL,
        email          TEXT NOT NULL,
        phone          TEXT,
        position       TEXT,
        resume_url     TEXT,
        cover_letter   TEXT,
        status         TEXT NOT NULL DEFAULT 'new',
        notes          TEXT,
        created_at     TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at     TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS kb_categories (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL,
        slug        TEXT UNIQUE,
        description TEXT,
        icon        TEXT,
        position    INTEGER NOT NULL DEFAULT 10,
        active      INTEGER NOT NULL DEFAULT 1,
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS kb_articles (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id  INTEGER DEFAULT NULL,
        author_id    INTEGER DEFAULT NULL,
        title        TEXT NOT NULL,
        slug         TEXT UNIQUE,
        excerpt      TEXT,
        body         TEXT,
        tags         TEXT,
        status       TEXT NOT NULL DEFAULT 'draft',
        language     TEXT NOT NULL DEFAULT 'en',
        views        INTEGER NOT NULL DEFAULT 0,
        published_at TEXT,
        created_at   TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at   TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS renewal_reminders (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id       INTEGER DEFAULT NULL,
        subscription_id INTEGER DEFAULT NULL,
        remind_at       TEXT NOT NULL,
        days_before     INTEGER NOT NULL DEFAULT 30,
        sent            INTEGER NOT NULL DEFAULT 0,
        sent_at         TEXT,
        created_at      TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS sla_policies (
        id                 INTEGER PRIMARY KEY AUTOINCREMENT,
        name               TEXT NOT NULL,
        description        TEXT,
        priority           TEXT NOT NULL DEFAULT 'normal',
        response_minutes   INTEGER NOT NULL DEFAULT 240,
        resolution_minutes INTEGER NOT NULL DEFAULT 1440,
        active             INTEGER NOT NULL DEFAULT 1,
        created_at         TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS subscribers (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        email        TEXT NOT NULL UNIQUE,
        name         TEXT,
        status       TEXT NOT NULL DEFAULT 'active',
        source       TEXT,
        confirmed_at TEXT,
        created_at   TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS ticket_internal_notes (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_id  INTEGER NOT NULL,
        author_id  INTEGER DEFAULT NULL,
        body       TEXT NOT NULL,
        note       TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS tech_expertise (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL,
        category    TEXT,
        icon_url    TEXT,
        proficiency INTEGER NOT NULL DEFAULT 80,
        active      INTEGER NOT NULL DEFAULT 1,
        position    INTEGER NOT NULL DEFAULT 0,
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    );

    -- Agreement Templates for client agreements
    CREATE TABLE IF NOT EXISTS agreement_templates (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        name             TEXT NOT NULL,
        template_type    TEXT NOT NULL DEFAULT 'contract',
        template_content TEXT,
        word_file_path   TEXT,
        is_default       INTEGER NOT NULL DEFAULT 0,
        created_by       INTEGER,
        created_at       TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at       TEXT NOT NULL DEFAULT (datetime('now'))
    );
    ");

    // ── Missing tables for client management ──────────────────────
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS client_agreements (
        id                   INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id            INTEGER NOT NULL,
        agreement_type       TEXT NOT NULL DEFAULT 'service',
        title                TEXT NOT NULL,
        content              TEXT,
        document_url         TEXT,
        document_name        TEXT,
        effective_date       TEXT,
        expiry_date          TEXT,
        amount               REAL DEFAULT 0,
        status               TEXT NOT NULL DEFAULT 'active',
        notes                TEXT,
        uploaded_by          TEXT,
        sale_by              INTEGER DEFAULT NULL,
        sale_type            TEXT DEFAULT 'office_sale',
        channel_partner_id   INTEGER DEFAULT NULL,
        created_by           INTEGER DEFAULT NULL,
        created_at           TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at           TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS client_documents (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id   INTEGER NOT NULL,
        doc_type    TEXT NOT NULL,
        title       TEXT NOT NULL,
        file_url    TEXT,
        file_name   TEXT,
        file_size   INTEGER,
        uploaded_by INTEGER,
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS invoices (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        client_id       INTEGER,
        invoice_number  TEXT NOT NULL UNIQUE,
        issue_date      TEXT,
        due_date        TEXT,
        amount          REAL NOT NULL DEFAULT 0,
        tax_amount      REAL DEFAULT 0,
        total_amount    REAL NOT NULL DEFAULT 0,
        currency        TEXT NOT NULL DEFAULT 'NPR',
        status          TEXT NOT NULL DEFAULT 'draft',
        notes           TEXT,
        paid_at         TEXT,
        created_at      TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS invoice_items (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_id  INTEGER NOT NULL,
        description TEXT NOT NULL,
        quantity    REAL NOT NULL DEFAULT 1,
        unit_price REAL NOT NULL DEFAULT 0,
        amount      REAL NOT NULL DEFAULT 0,
        sort_order  INTEGER NOT NULL DEFAULT 0
    );
    ");

}

function _sqliteInitSeedData(PDO $pdo): void
{
    // Seed dev admin user — password comes from env vars (same as superadmin.php).
    // SUPERADMIN_PASS_HASH (bcrypt) or SUPERADMIN_PASS_PLAIN must be set; falls back to
    // a random one-time token printed to error_log so the developer can log in once and change it.
    $siteHost  = parse_url(defined('SITE_URL') ? SITE_URL : '', PHP_URL_HOST) ?: 'example.com';
    $siteName  = defined('SITE_NAME') ? SITE_NAME : 'Company';
    $saHash    = getenv('SUPERADMIN_PASS_HASH') ?: '';
    $saPlain   = getenv('SUPERADMIN_PASS_PLAIN') ?: '';
    if ($saHash) {
        $hash = $saHash;
    } elseif ($saPlain) {
        $hash = password_hash($saPlain, PASSWORD_BCRYPT, ['cost' => 12]);
    } else {
        // No env var set — generate a random one-time password for first login.
        $onetime = bin2hex(random_bytes(8));
        $hash    = password_hash($onetime, PASSWORD_BCRYPT, ['cost' => 10]);
        error_log('[sqlite-init] Dev admin seeded. One-time password: ' . $onetime . ' — change immediately after first login.');
    }
    $seedEmail = getenv('SUPERADMIN_EMAIL') ?: ('admin@' . $siteHost);
    $pdo->prepare("INSERT OR IGNORE INTO users (display_name, email, password_hash, role, email_verified, active)
        VALUES ('Admin', ?, ?, 'admin', 1, 1)")->execute([$seedEmail, $hash]);

    // Seed site settings
    $settings = [
        ['site_name',        $siteName],
        ['site_tagline',     'Cooperative Software for Nepal'],
        ['contact_email',    ''],
        ['contact_phone',    ''],
        ['address',          ''],
        ['logo_url',         ''],
        ['iso_logo_url',     ''],
        ['social_links',     '{"facebook":"","twitter":"","linkedin":"","youtube":""}'],
        ['whatsapp_number',  ''],
        ['whatsapp_enabled', '1'],
        ['stat_1_value',     '120+'],
        ['stat_1_label',     'Cooperatives Served'],
        ['stat_2_value',     '8 yrs'],
        ['stat_2_label',     'Nepal Experience'],
        ['stat_3_value',     '<2 hr'],
        ['stat_3_label',     'Avg Support Response'],
        ['stat_4_value',     '99.9%'],
        ['stat_4_label',     'Platform Uptime'],
        ['hero_title',       'Cooperative Software Built for Nepal'],
        ['hero_subtitle',    'IT Solutions & Software Services — purpose-built for Nepal. Reliable, locally supported, and tailored to your business.'],
        ['hero_cta_primary',  'Book a Free Demo'],
        ['hero_cta_secondary','See Pricing'],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO site_settings (setting_key, setting_val) VALUES (?, ?)");
    foreach ($settings as [$k, $v]) $stmt->execute([$k, $v]);

    // Seed sample clients
    $clients = [
        ['Sajha Cooperative', 'active', 'Kathmandu', 'Bagmati', 'CLN-001', 'CBS, Mobile App', 'John Doe', 'john@sajha.coop', '9800000001'],
        ['Nagrik Cooperative', 'active', 'Lalitpur', 'Bagmati', 'CLN-002', 'CBS', 'Jane Smith', 'jane@nagrik.coop', '9800000002'],
        ['Seti Cooperative', 'active', 'Dhangadhi', 'Sudurpashchim', 'CLN-003', 'CBS, HR & Payroll', 'Bob Sharma', 'bob@seti.coop', '9800000003'],
        ['Karnali Cooperative', 'pending', 'Surkhet', 'Karnali', 'CLN-004', 'CBS', 'Alice RC', 'alice@karnali.coop', '9800000004'],
        ['Janata Cooperative', 'active', 'Pokhara', 'Gandaki', 'CLN-005', 'CBS, Mobile App, DMS', 'Charlie B', 'charlie@janata.coop', '9800000005'],
        ['Mahalaxmi Cooperative', 'inactive', 'Bhaktapur', 'Bagmati', 'CLN-006', 'CBS', 'Diana P', 'diana@mahalaxmi.coop', '9800000006'],
        ['Sahara Cooperative', 'active', 'Nepalgunj', 'Lumbini', 'CLN-007', 'CBS, HR & Payroll', 'Evan M', 'evan@sahara.coop', '9800000007'],
        ['Pashupati Cooperative', 'renewal_due', 'Kathmandu', 'Bagmati', 'CLN-008', 'CBS, Mobile App', 'Fiona K', 'fiona@pashupati.coop', '9800000008'],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO clients (org_name, status, district, province, client_code, product, contact_name, contact_email, contact_phone) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($clients as $c) $stmt->execute($c);

    // Seed testimonials
    $testimonials = [
        ['Aarav Shrestha',  'Manager',    'Himalayan Saving Co-op',  'हाम्रो सहकारीको दैनिक काम एकदम सजिलो भयो। Support team ले समयमै reply दिन्छन्।',          5],
        ['Maya Tamang',     'IT Lead',    'Yatra Cooperative',       'CBS system ले हाम्रो NRB reporting काम एकदम सजिलो भयो। Highly recommended!',               5],
        ['Rohan Karki',     'Founder',    'Sajilo Sahakari',         'Website redesign पछि नयाँ सदस्य join गर्ने rate बढ्यो। Excellent work!',                    5],
        ['Sunita Gurung',   'President',  'Butwal Business Co-op', 'सेवा एकदम राम्रो छ — सधैं समयमा support पाउँछौं।',     5],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO testimonials (author_name, author_role, author_org, quote, rating, active, position) VALUES (?,?,?,?,?,1,?)");
    foreach ($testimonials as $i => $t) $stmt->execute(array_merge($t, [$i+1]));

    // Seed partners (client logos for marquee)
    $partners = [
        ['Himalayan Saving Co-op',           'client',  'Kathmandu', null],
        ['Yatra Cooperative',                'client',  'Lalitpur',  null],
        ['Sajilo Sahakari',                  'client',  'Bhaktapur', null],
        ['Butwal Business Co-op',          'client',  'Kaski',     null],
        ['Janahit Bachat Sahakari',          'client',  'Chitwan',   null],
        ['Annapurna Multipurpose Co-op',     'client',  'Kaski',     null],
        ['Seti Gandaki Saving Co-op',        'client',  'Surkhet',   null],
        ['Lumbini Cooperative Society',      'client',  'Rupandehi', null],
        ['Everest Community Finance',        'client',  'Solukhumbu',null],
        ['Bagmati Bachat Tatha Rin',         'client',  'Kathmandu', null],
        ['Janasewa Sahakari Sanstha',        'client',  'Chitwan',   null],
        ['Mechi Cooperative Bank',           'client',  'Jhapa',     null],
        ['Koshi Development Co-op',          'client',  'Morang',    null],
        ['Rapti Saving Society',             'client',  'Dang',      null],
        ['Bishal Multipurpose Co-op',        'client',  'Banke',     null],
        ['Nepal Rastra Bank',                'partner', 'Kathmandu', 'https://www.nrb.org.np'],
        ['Connectips',                       'partner', 'Kathmandu', null],
        ['eSewa',                            'partner', 'Kathmandu', 'https://esewa.com.np'],
        ['Khalti',                           'partner', 'Kathmandu', 'https://khalti.com'],
        ['Nepal Clearing House',             'partner', 'Kathmandu', null],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO partners (name, type, district, url, active, position) VALUES (?,?,?,?,1,?)");
    foreach ($partners as $i => $p) $stmt->execute(array_merge($p, [$i+1]));

    // Seed team members
    $team = [
        ['Rajesh Shrestha', 'Founder & CEO',    'Building cooperative software for Nepal since 2017. 15 years in fintech.',       1, 1],
        ['Sita Tamang',     'CTO',              'Leads product architecture and engineering. Ex-NIC Asia digital team.',           1, 2],
        ['Bikash Karki',    'Head of Sales',    'Former cooperative manager turned tech advocate. 10+ yrs in सहकारी sector.',     1, 3],
        ['Anita Rai',       'Lead Developer',   'PHP & MySQL specialist. Core of the CBS engine team.',                           0, 4],
        ['Prakash Thapa',   'Mobile Dev Lead',  'React Native / Flutter apps. Built 20+ banking apps in Nepal.',                  0, 5],
        ['Manisha Gurung',  'Support Lead',     'Ensures every ticket gets a response. Speaks Nepali, Hindi, English.',           0, 6],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO team_members (name, role, bio, is_leadership, active, position) VALUES (?,?,?,?,1,?)");
    foreach ($team as $t) $stmt->execute($t);

    // Seed services (IT infrastructure & support services)
    $services = [
        // title, slug, tagline, summary, icon, lucide_icon, icon_color, badge, price_from, highlights, features, position
        ['Cloud Services',       'cloud',    'Managed cloud for businesses across Nepal',               'Scalable, secure cloud infrastructure — managed servers, auto backups, 99.9% uptime SLA and 24×7 NOC monitoring.',    'cloud',          'cloud',          'blue',   'Popular',   null, '["Managed Servers","Auto Backups","99.9% Uptime SLA","24×7 NOC Monitor"]',   '["Managed Hosting","Auto Backups","99.9% SLA","NOC Monitoring","SSL Included"]', 1],
        ['Domain & Hosting',     'domain',   '.com.np, .org.np and international domains',              'Register domains with local support. Blazing-fast SSD hosting, free SSL, email hosting and Nepal-based control panel.',  'globe',          'globe',          'teal',   'Essential',  null, '["Domain Registration",".com.np & .np","Free SSL","SSD Hosting","Email Hosting"]', '["Domain Reg","cPanel Hosting","Email Hosting","Free SSL","DNS Management"]', 2],
        ['Bulk SMS Services',    'sms',      'High-delivery SMS for all Nepal telecom networks',        'Send transaction alerts, reminders, OTPs and promotional messages instantly across Ncell and NTC networks.',            'message-square', 'message-square', 'amber',  'Add-on',    null, '["Ncell & NTC Gateway","OTP / 2FA","Transaction Alerts","Delivery Reports"]',  '["OTP & 2FA","Transactional SMS","Promotional SMS","Delivery Reports","API Access"]', 3],
        ['Security Audit',       'security', 'End-to-end cybersecurity audit & penetration testing',   'Identify vulnerabilities before attackers do — penetration testing, vulnerability scan, source code review and compliance audit.', 'shield-check',  'shield-check', 'rose',   'Audit',     null, '["Penetration Testing","Vulnerability Scan","IT Compliance","Audit Report PDF"]', '["Pen Testing","Vuln Scan","Code Review","IT Compliance","Audit Report"]', 4],
        ['Website Development',  'website',  'SEO-optimised cooperative & business websites',           'Modern, fast, multilingual websites built for cooperatives and SMEs — with CMS, notice board and loan calculator.', 'globe',           'monitor',        'purple', '',          null, '["SEO Optimised","Multilingual CMS","Notice Board","Loan Calculator"]',       '["Custom Design","SEO","CMS","Mobile-friendly","Multilingual"]', 5],
        ['IT Consultancy',       'it-consultancy', 'Digital transformation for cooperatives & SMEs',   'Strategic IT guidance, system selection, staff training and hands-on implementation support for your organisation.',    'briefcase',      'briefcase',      'indigo', '',          null, '["Needs Assessment","System Selection","Staff Training","Implementation","Ongoing Advisory"]', '["Needs Assessment","System Selection","Staff Training","Implementation"]', 6],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO services (title, slug, tagline, summary, icon, lucide_icon, icon_color, badge, price_from, highlights, features, active, position) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?)");
    foreach ($services as $s) $stmt->execute([$s[0],$s[1],$s[2],$s[3],$s[4],$s[5],$s[6],$s[7],$s[8],$s[9],$s[10],$s[11]]);

    // Seed products (software products)
    // cols: name, slug, tagline, summary, icon, lucide_icon, icon_color, badge, category, highlights, features, price_from, position
    $products = [
        ['Core Banking System',  'cbs',    'Full-featured CBS for Nepal cooperatives', 'Member KYC, savings, loans, share capital, 100+ NRB reports. BS calendar native. Multi-branch ready.',         'monitor',    'monitor',    'blue',   'Flagship', 'Banking',   '["Member & KYC","Savings & FD","Loan Lifecycle","Share Capital","NRB Reports","Multi-branch","BS Calendar"]',   '["Member KYC","Savings","FD","Loans","Share Capital","NRB Reports","Multi-branch","BS Calendar","Audit Trail"]', null, 1],
        ['Mobile Banking App',   'mobile', 'Branded Android & iOS banking app',        'Branded mobile app — balance, transfers, QR payments, EMI calculator, push alerts, biometric login.',          'smartphone', 'smartphone', 'teal',   'Popular',  'Banking',   '["Android & iOS","QR Payments","Push Alerts","Biometric Login"]',                                                  '["Android","iOS","QR Payments","Push Notifications","Biometric Login","EMI Calc","Mini Statement"]', null, 2],
        ['Document Management',  'dms',    'Paperless KYC & loan file management',     'OCR indexing of KYC and loan files, version history, role-based access and full audit trail.',                  'file-text',  'file-text',  'purple', '',         'Documents', '["OCR Indexing","Version History","Role-based Access","Audit Trail"]',                                              '["OCR Indexing","Version History","Role-based Access","Audit Trail","Cloud Storage","Bulk Upload"]', null, 3],
        ['HR & Payroll',         'hr',     'Complete HR management for cooperatives',  'Full HR, payroll, TDS, SSF and CIT ready. Includes employee self-service portal and leave management.',        'users',      'users',      'green',  '',         'HR',        '["Payroll & TDS","SSF & CIT","ESS Portal","Leave Management"]',                                                    '["Payroll","TDS","SSF","CIT","Leave Mgmt","ESS Portal","Attendance","Payslip PDF"]', null, 4],
        ['Cooperative Website',  'website','SEO-optimised website with built-in CMS',  'Modern, fast cooperative website with self-service CMS, notice board, loan calculator and multilingual support.','globe',     'globe',      'orange', 'Add-on',   'Web',       '["SEO Optimised","Multilingual CMS","Notice Board","Loan Calculator"]',                                             '["Custom Design","SEO","CMS","Mobile-friendly","Nepali & English","Contact Forms"]', null, 5],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO products (name, slug, tagline, summary, icon, lucide_icon, icon_color, badge, category, highlights, features, price_from, active, position) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?)");
    foreach ($products as $p) $stmt->execute([$p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],$p[7],$p[8],$p[9],$p[10],$p[11],$p[12]]);

    // Seed pricing plans
    $plans = [
        ['Starter', 'Single-branch cooperatives',   'NPR 4,999', '/ month', 'Get started', 0, '["Up to 500 members","Core Banking (CBS)","Web portal + notices","Email & ticket support","Monthly backups"]',             1],
        ['Growth',  'Multi-branch — most popular',  'NPR 12,999','/ month', 'Book a demo', 1, '["Up to 5,000 members","CBS + Mobile Banking App","DMS + role-based access","Priority <2 hr support","Quarterly training"]', 2],
        ['Enterprise','Large & regulated cooperatives','Custom',  '',        'Contact us',  0, '["Unlimited members & branches","All modules included","Dedicated success manager","24×7 critical SLA","On-premise option"]', 3],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO pricing_plans (name, tag, price_label, period, cta_label, is_popular, features, active, position) VALUES (?,?,?,?,?,?,?,1,?)");
    foreach ($plans as $p) $stmt->execute($p);

    // Seed FAQs
    $faqs = [
        ['General',   'What is this company?',                   'We are a software company providing IT solutions and software services.', 1],
        ['General',   'How many cooperatives use your software?', 'Over 120 cooperatives across Nepal — from small single-branch savings cooperatives to large multi-province financial cooperatives.',            2],
        ['Products',  'Is your CBS NRB-compliant?',               'Yes. Our CBS includes 100+ pre-built NRB report templates and is updated with every regulatory change at no extra cost.',                       3],
        ['Products',  'Does it support Nepali calendar (BS)?',    'Yes. Every module — savings, loans, reports, payslips — is fully Bikram Sambat native.',                                                       4],
        ['Pricing',   'Are there hidden fees?',                   'No. We provide a full itemized quote before any commitment — including setup, data migration and training costs.',                              5],
        ['Support',   'What is your support response time?',      'Growth and Enterprise clients: < 2 hr SLA for P1 issues. Starter: next-business-day. All clients have the 24×7 ticket portal.',               6],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO faqs (category, question, answer, active, position) VALUES (?,?,?,1,?)");
    foreach ($faqs as $f) $stmt->execute($f);

    // Seed news articles
    $news = [
        ['CBS v4.0 Released with NRB 2081 Compliance Updates', 'cbs-v4-nrb-2081',
         'Our latest CBS release includes all NRB 2081 circular updates, improved loan lifecycle management and faster report generation.',
         'News', 1, 1, date('Y-m-d H:i:s', strtotime('-7 days'))],
        ['New Mobile Banking App Live for 15 Cooperatives', 'mobile-banking-live-15',
         'We are thrilled to announce that our new branded mobile banking app is now live for 15 cooperative clients across 6 districts of Nepal.',
         'Updates', 1, 1, date('Y-m-d H:i:s', strtotime('-21 days'))],
        ['Company Expands to New Region', 'madhesh-expansion',
         'We opened our regional office in Birgunj, making it easier to serve cooperatives across Madhesh Province with on-site support.',
         'Company', 1, 1, date('Y-m-d H:i:s', strtotime('-45 days'))],
        ['Introducing DMS 2.0: Faster Search and E-Signature Support', 'dms-2-esignature',
         'Document Management System 2.0 ships with full-text search, drag-and-drop upload, and legally compliant e-signature workflows for Nepali institutions.',
         'Product', 0, 1, date('Y-m-d H:i:s', strtotime('-60 days'))],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO news (title, slug, excerpt, category, featured, published, published_at, active) VALUES (?,?,?,?,?,?,?,1)");
    foreach ($news as $n) $stmt->execute($n);

    // Seed job listings
    $jobs = [
        ['Senior PHP Engineer', 'senior-php-engineer', 'Engineering', 'full-time', '3+ years', 'NPR 80,000–130,000', 'Build and scale our Core Banking platform used by 120+ cooperatives.', 1],
        ['QA Engineer',         'qa-engineer',         'Quality',     'full-time', '1+ years', 'NPR 45,000–70,000',  'Own quality for products trusted with members\' money.',                1],
        ['UI/UX Designer',      'ui-ux-designer',      'Design',      'full-time', '2+ years', 'NPR 50,000–90,000',  'Design simple, fast interfaces for cooperative staff and members.',    1],
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO job_listings (title, slug, department, type, experience, salary_range, short_desc, active) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($jobs as $j) $stmt->execute($j);
}
