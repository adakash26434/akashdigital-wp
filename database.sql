-- ══════════════════════════════════════════════════════════════════════════════
-- Butwal Project — Complete MySQL/MariaDB Database Schema
-- Compatible with: MySQL 5.7+ / MariaDB 10.3+
-- Last Updated: 2026-06-17
-- ══════════════════════════════════════════════════════════════════════════════
-- HOW TO IMPORT IN cPanel:
--   1. cPanel → MySQL Databases → Create Database & User → Grant ALL Privileges
--   2. phpMyAdmin → Select your database → Import → Choose this file → Go
--   OR via terminal: mysql -u username -p database_name < database.sql
-- ══════════════════════════════════════════════════════════════════════════════

-- ⚠️ MIGRATION: Run this for existing tables:
-- ALTER TABLE news ADD COLUMN source_url VARCHAR(500) DEFAULT NULL AFTER views;
-- ALTER TABLE partners ADD COLUMN show_on_contact TINYINT NOT NULL DEFAULT 0 AFTER position;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. USERS & AUTH
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  display_name  VARCHAR(255) NOT NULL DEFAULT '',
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(50)  NOT NULL DEFAULT 'client',
  avatar_url    VARCHAR(500),
  phone         VARCHAR(20),
  org_name      VARCHAR(255),
  client_code   VARCHAR(50),
  district      VARCHAR(100),
  bio           TEXT,
  email_verified TINYINT NOT NULL DEFAULT 0,
  active        TINYINT NOT NULL DEFAULT 1,
  theme_pref    VARCHAR(20) NOT NULL DEFAULT 'dark',
  totp_secret   VARCHAR(255) DEFAULT NULL,
  totp_enabled  TINYINT NOT NULL DEFAULT 0,
  totp_backup_code VARCHAR(20) DEFAULT NULL,
  require_2fa   TINYINT NOT NULL DEFAULT 0,
  last_login_at DATETIME,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email  (email),
  INDEX idx_role   (role),
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT  NOT NULL,
  token      VARCHAR(128) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token   (token),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL UNIQUE,
  token      VARCHAR(128) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  identifier     VARCHAR(255) NOT NULL UNIQUE,
  attempts       INT NOT NULL DEFAULT 1,
  last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_until   DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  event         VARCHAR(50) NOT NULL DEFAULT 'login',
  ip            VARCHAR(45),
  user_agent    TEXT,
  device        VARCHAR(100),
  session_token VARCHAR(128),
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. SITE SETTINGS & CONTENT
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS site_settings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_val LONGTEXT,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_pages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(100) NOT NULL UNIQUE,
  title      VARCHAR(255) NOT NULL,
  content    LONGTEXT,
  meta_desc  TEXT,
  active     TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(255) NOT NULL,
  body        TEXT,
  type        VARCHAR(30) NOT NULL DEFAULT 'info',
  scope       VARCHAR(30) NOT NULL DEFAULT 'banner',
  page_target VARCHAR(100),
  btn_text    VARCHAR(100),
  btn_url     VARCHAR(500),
  active      TINYINT NOT NULL DEFAULT 1,
  dismissible TINYINT NOT NULL DEFAULT 1,
  starts_at   DATETIME,
  ends_at     DATETIME,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banners (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  subtitle     TEXT,
  image_url    VARCHAR(500),
  link_url     VARCHAR(500),
  btn_text     VARCHAR(100),
  banner_style VARCHAR(50) DEFAULT 'default',
  page_target  VARCHAR(100) DEFAULT 'all',
  position     INT NOT NULL DEFAULT 10,
  active       TINYINT NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. PRODUCTS & SERVICES
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS products (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(255) NOT NULL,
  slug                VARCHAR(100) NOT NULL UNIQUE,
  tagline             VARCHAR(255),
  summary             TEXT,
  description         LONGTEXT,
  icon                VARCHAR(100) DEFAULT 'box',
  lucide_icon         VARCHAR(100) DEFAULT 'package',
  icon_color          VARCHAR(50)  DEFAULT 'blue',
  badge               VARCHAR(50),
  category            VARCHAR(100),
  highlights          JSON,
  features            JSON,
  price_from          DECIMAL(12,2),
  show_on_home        TINYINT NOT NULL DEFAULT 0,
  home_position       INT NOT NULL DEFAULT 0,
  home_card_wide      TINYINT NOT NULL DEFAULT 0,
  home_card_dark      TINYINT NOT NULL DEFAULT 0,
  home_bg_css         TEXT,
  demo_screenshot_url VARCHAR(500),
  tab_label           VARCHAR(100),
  active              TINYINT NOT NULL DEFAULT 1,
  position            INT NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_slug   (slug),
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(255) NOT NULL,
  slug            VARCHAR(100) NOT NULL UNIQUE,
  tagline         VARCHAR(255) DEFAULT '',
  summary         TEXT,
  description     LONGTEXT,
  icon            VARCHAR(100) DEFAULT 'settings',
  lucide_icon     VARCHAR(100) DEFAULT 'layers',
  icon_color      VARCHAR(50)  DEFAULT 'blue',
  badge           VARCHAR(50)  DEFAULT '',
  price_from      DECIMAL(12,2) DEFAULT NULL,
  highlights      JSON,
  features        JSON,
  screenshot_url  VARCHAR(500) DEFAULT NULL,
  active          TINYINT NOT NULL DEFAULT 1,
  position        INT NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_slug   (slug),
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- SAMPLE DATA: Products
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO products (name, slug, tagline, summary, lucide_icon, icon_color, badge, category, highlights, features, price_from, active, position, show_on_home, home_position, home_card_wide, home_card_dark, created_at, updated_at) VALUES
('Custom Software Development', 'custom-software-development', 'Tailored solutions for your unique needs', 'Bespoke software built exactly to your requirements, workflows, and business processes.', 'code', 'blue', NULL, 'Software Development', '["Business Analysis","UI/UX Design","Agile Development","QA Testing","Deployment","Support"]', '["Custom Features","API Integration","Database Design","Cloud Hosting","Mobile Responsive","Analytics Dashboard"]', 50000, 1, 1, 1, 1, 1, 0, NOW(), NOW()),
('Mobile App Development', 'mobile-app-development', 'Native & cross-platform mobile apps', 'Powerful mobile applications for iOS and Android that your users will love.', 'smartphone', 'green', 'Popular', 'Software Development', '["iOS Development","Android Development","React Native","Flutter","App Store Publishing","Push Notifications"]', '["Offline Support","Camera Integration","GPS Location","Social Login","Payment Gateway","Analytics"]', 80000, 1, 2, 1, 2, 1, 0, NOW(), NOW()),
('Document Management (DMS)', 'document-management-dms', 'Organize, store & manage documents', 'Centralized document management system with powerful search and version control.', 'file-text', 'purple', NULL, 'Enterprise Software', '["OCR Scanning","Version Control","Full-Text Search","Access Control","Audit Trail","E-Signature"]', '["Drag & Drop Upload","Folder Structure","Tagging System","Workflow Automation","Document Linking","Retention Policies"]', 35000, 1, 3, 1, 3, 1, 0, NOW(), NOW()),
('HR & Payroll Software', 'hr-payroll-software', 'Streamline HR & payroll operations', 'Complete HR and payroll management system for Nepal compliance.', 'users', 'orange', NULL, 'Enterprise Software', '["Employee Management","Leave Management","Attendance","Payroll Processing","Tax Calculation","Bank Transfer"]', '["PAN Processing","CIT Calculation","Salary Slips","Annual Reports","Mobile Attendance","Self Service Portal"]', 25000, 1, 4, 1, 4, 1, 0, NOW(), NOW()),
('Website Development', 'website-development', 'Modern, fast & SEO-friendly websites', 'Professional websites that load fast, rank well, and convert visitors to customers.', 'globe', 'blue', NULL, 'Software Development', '["Responsive Design","SEO Optimization","CMS Integration","Performance","Security","Analytics"]', '["WordPress Development","E-commerce","Custom Themes","Plugin Development","SSL Certificate","CDN Setup"]', 30000, 1, 5, 1, 5, 1, 0, NOW(), NOW()),
('Support & Ticket Desk', 'support-ticket-desk', 'Manage customer support efficiently', 'Help desk software to track, prioritize, and resolve customer issues fast.', 'headphones', 'red', 'New', 'Customer Service', '["Ticket Management","Knowledge Base","Live Chat","Email Integration","SLA Management","Reporting"]', '["Multi-Channel Support","Auto-Assignment","Canned Responses","Satisfaction Surveys","Team Collaboration","Mobile App"]', 20000, 1, 6, 1, 6, 1, 0, NOW(), NOW());

-- ─────────────────────────────────────────────────────────────────────────────
-- SAMPLE DATA: Services
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO services (title, slug, tagline, summary, description, lucide_icon, icon_color, badge, price_from, highlights, features, active, position, created_at, updated_at) VALUES
('Cloud Services', 'cloud-services', 'Managed cloud for businesses across Nepal', 'Scalable, secure cloud infrastructure — managed servers, auto backups, 99.9% uptime SLA and 24×7 NOC monitoring.', '<p>Scalable, secure cloud infrastructure — managed servers, auto backups, 99.9% uptime SLA and 24×7 NOC monitoring.</p>', 'cloud', 'blue', 'Popular', NULL, '["Managed Servers","Auto Backups","99.9% Uptime SLA","24×7 NOC Monitor"]', '["Server Management","Data Backup","Security Monitoring","Load Balancing","CDN Integration","SSL Management"]', 1, 1, NOW(), NOW()),
('Domain & Hosting', 'domain-hosting', '.com.np, .org.np and international domains', 'Register domains with local support. Blazing-fast SSD hosting, free SSL, email hosting and Nepal-based control panel.', '<p>Register domains with local support. Blazing-fast SSD hosting, free SSL, email hosting and Nepal-based control panel.</p>', 'globe', 'green', 'Essential', NULL, '["\\.com\\.np Registration","Free SSL","SSD Hosting","Email Hosting"]', '["Domain Registration","Web Hosting","Email Accounts","SSL Certificates","cPanel Access","99.9% Uptime"]', 1, 2, NOW(), NOW()),
('Bulk SMS Services', 'bulk-sms-services', 'High-delivery SMS for all Nepal telecom networks', 'Send transaction alerts, reminders, OTPs and promotional messages instantly across Ncell and NTC networks.', '<p>Send transaction alerts, reminders, OTPs and promotional messages instantly across Ncell and NTC networks.</p>', 'message-square', 'orange', 'Add-on', NULL, '["Ncell & NTC Gateway","OTP / 2FA","Transaction Alerts","Delivery Reports"]', '["SMS Gateway API","OTP Integration","Bulk Messaging","Scheduled Send","Delivery Reports","DLR Tracking"]', 1, 3, NOW(), NOW()),
('Security Audit Service', 'security-audit-service', 'End-to-end cybersecurity audit & penetration testing', 'Identify vulnerabilities before attackers do — penetration testing, vulnerability scan, source code review and compliance audit.', '<p>Identify vulnerabilities before attackers do — penetration testing, vulnerability scan, source code review and compliance audit.</p>', 'shield-check', 'red', 'Audit', NULL, '["Penetration Testing","Vulnerability Scan","IT Compliance","Audit Report PDF"]', '["Network Security","Web App Testing","Source Code Review","Compliance Audit","Security Report","Remediation Support"]', 1, 4, NOW(), NOW());

CREATE TABLE IF NOT EXISTS pricing_plans (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  tag         VARCHAR(100),
  price_label VARCHAR(100) NOT NULL DEFAULT 'Contact us',
  period      VARCHAR(50)  DEFAULT '/ month',
  cta_label   VARCHAR(100) DEFAULT 'Get started',
  cta_url     VARCHAR(500),
  is_popular  TINYINT NOT NULL DEFAULT 0,
  features    JSON,
  active      TINYINT NOT NULL DEFAULT 1,
  position    INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. CLIENTS & SUBSCRIPTIONS
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS clients (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  org_name        VARCHAR(255) NOT NULL,
  logo_url        VARCHAR(500),
  contact_name    VARCHAR(255),
  contact_email   VARCHAR(255),
  contact_phone   VARCHAR(30),
  billing_email   VARCHAR(255),
  client_code     VARCHAR(50) UNIQUE,
  user_id         INT DEFAULT NULL,
  claimed_at      DATETIME DEFAULT NULL,
  product         TEXT,
  cbs_use         TINYINT NOT NULL DEFAULT 1,
  integration     VARCHAR(255),
  integration_charge DECIMAL(12,2),
  district        VARCHAR(100),
  province        VARCHAR(100),
  local_govt      VARCHAR(100),
  ward_no         VARCHAR(10),
  address         TEXT,
  pan_no          VARCHAR(20),
  reg_no          VARCHAR(50),
  established_year SMALLINT,
  total_members   INT,
  total_branches  INT,
  website         VARCHAR(255),
  notes           TEXT,
  status          VARCHAR(30) NOT NULL DEFAULT 'active',
  head_office_amc DECIMAL(12,2) DEFAULT NULL,
  branch_office_amc DECIMAL(12,2) DEFAULT NULL,
  cloud_charge_ho DECIMAL(12,2) DEFAULT NULL,
  cloud_charge_branch DECIMAL(12,2) DEFAULT NULL,
  custom_charge_type VARCHAR(50) DEFAULT NULL,
  custom_charge_value DECIMAL(12,2) DEFAULT NULL,
  -- Service tracking
  agreement_date DATE DEFAULT NULL COMMENT 'Client agreement signing date',
  installation_date DATE DEFAULT NULL COMMENT 'Software installation date',
  num_branches INT NOT NULL DEFAULT 1 COMMENT 'Number of branches (HO + branches)',
  cloud_gb DECIMAL(10,2) DEFAULT NULL COMMENT 'Cloud storage in GB',
  -- Sale tracking
  sale_type       VARCHAR(30) NOT NULL DEFAULT 'office_sale' COMMENT 'office_sale, channel_partner',
  channel_partner_id INT DEFAULT NULL COMMENT 'Referral channel partner',
  sale_date       DATE DEFAULT NULL COMMENT 'When the sale was made',
  sale_by         INT DEFAULT NULL COMMENT 'Staff who made the sale',
  assigned_by     INT DEFAULT NULL COMMENT 'Admin who created this client',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_org    (org_name),
  INDEX idx_client_code (client_code),
  INDEX idx_channel_partner (channel_partner_id),
  INDEX idx_sale_type (sale_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branches (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  client_id  INT NOT NULL,
  code       VARCHAR(20),
  name       VARCHAR(255) NOT NULL,
  address    TEXT,
  district   VARCHAR(100),
  province   VARCHAR(100),
  phone      VARCHAR(30),
  manager    VARCHAR(255),
  is_head    TINYINT NOT NULL DEFAULT 0,
  active     TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_charge_history (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  client_id       INT NOT NULL,
  charge_type     VARCHAR(50) NOT NULL COMMENT 'amc_ho, amc_branch, cloud_ho, cloud_branch, custom',
  old_value       DECIMAL(12,2) DEFAULT NULL,
  new_value       DECIMAL(12,2) NOT NULL,
  effective_date  DATE NOT NULL,
  reason          VARCHAR(255) DEFAULT NULL,
  changed_by      INT DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client (client_id),
  INDEX idx_type (charge_type),
  INDEX idx_effective (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_termination (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  client_id       INT NOT NULL UNIQUE,
  terminated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason          VARCHAR(255) NOT NULL,
  remarks         TEXT,
  document_url    VARCHAR(500) DEFAULT NULL,
  terminated_by   INT DEFAULT NULL,
  INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_status_history (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  client_id       INT NOT NULL,
  old_status      VARCHAR(30) DEFAULT NULL,
  new_status      VARCHAR(30) NOT NULL,
  changed_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by      INT DEFAULT NULL,
  reason          VARCHAR(255) DEFAULT NULL,
  INDEX idx_client (client_id),
  INDEX idx_changed (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amc_renewal_config (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  client_id       INT NOT NULL UNIQUE,
  renewal_cycle   VARCHAR(20) NOT NULL DEFAULT '2years' COMMENT '1year, 2years, 3years, custom',
  cycle_months    INT DEFAULT 24,
  increment_type  VARCHAR(20) NOT NULL DEFAULT 'percentage' COMMENT 'percentage, fixed',
  increment_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  base_amc_ho     DECIMAL(12,2) DEFAULT NULL,
  base_amc_branch DECIMAL(12,2) DEFAULT NULL,
  next_renewal_date DATE DEFAULT NULL,
  last_renewal_date DATE DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client (client_id),
  INDEX idx_next_renewal (next_renewal_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_subscriptions (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  product_id       INT DEFAULT NULL,
  product_name     VARCHAR(255) NOT NULL,
  plan_name        VARCHAR(100) NOT NULL,
  license_key      VARCHAR(255) DEFAULT NULL,
  deployment_type  VARCHAR(50)  DEFAULT NULL,
  branches         INT NOT NULL DEFAULT 1,
  members_limit    INT DEFAULT NULL,
  amount           DECIMAL(12,2) DEFAULT NULL,
  billing_cycle    VARCHAR(30) DEFAULT 'monthly',
  status           VARCHAR(30) NOT NULL DEFAULT 'active',
  starts_at        DATE DEFAULT NULL,
  expires_at       DATE DEFAULT NULL,
  next_renewal     DATE DEFAULT NULL,
  notes            TEXT DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_id   (user_id),
  INDEX idx_status    (status),
  INDEX idx_product   (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_licenses (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  client_id         INT DEFAULT NULL,
  license_key       VARCHAR(255) UNIQUE,
  product           VARCHAR(255),
  activation_status VARCHAR(30) NOT NULL DEFAULT 'inactive',
  hardware_id       VARCHAR(255),
  activated_at      DATETIME,
  expires_at        DATETIME,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_agreements (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  client_id       INT NOT NULL,
  agreement_type  VARCHAR(50) NOT NULL DEFAULT 'contract' COMMENT 'contract, amendment, addendum, renewal, nda, sla',
  title           VARCHAR(255) NOT NULL,
  document_url    VARCHAR(500) DEFAULT NULL,
  document_name   VARCHAR(255) DEFAULT NULL,
  effective_date  DATE NOT NULL,
  expiry_date     DATE DEFAULT NULL,
  amount          DECIMAL(12,2) DEFAULT NULL COMMENT 'Agreement amount if applicable',
  currency        VARCHAR(3) DEFAULT 'NPR',
  status          VARCHAR(30) NOT NULL DEFAULT 'draft' COMMENT 'draft, pending_approval, active, expired, terminated',
  uploaded_by     VARCHAR(20) NOT NULL DEFAULT 'admin' COMMENT 'admin, client',
  approved_by     INT DEFAULT NULL,
  approved_at     DATETIME DEFAULT NULL,
  notes           TEXT DEFAULT NULL,
  signed_by       VARCHAR(255) DEFAULT NULL,
  signed_at       DATETIME DEFAULT NULL,
  created_by      INT DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client (client_id),
  INDEX idx_type   (agreement_type),
  INDEX idx_status (status),
  INDEX idx_expiry (expiry_date),
  INDEX idx_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_documents (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  client_id       INT NOT NULL,
  doc_type        VARCHAR(50) NOT NULL COMMENT 'registration, pan, vat, tax_clearance, bank_info, other',
  title           VARCHAR(255) NOT NULL,
  document_url    VARCHAR(500) DEFAULT NULL,
  document_name   VARCHAR(255) DEFAULT NULL,
  status          VARCHAR(30) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected',
  uploaded_by     VARCHAR(20) NOT NULL DEFAULT 'client' COMMENT 'admin, client',
  approved_by     INT DEFAULT NULL,
  approved_at     DATETIME DEFAULT NULL,
  rejection_reason TEXT DEFAULT NULL,
  notes           TEXT DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client (client_id),
  INDEX idx_type   (doc_type),
  INDEX idx_status (status),
  INDEX idx_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number      VARCHAR(50) NOT NULL UNIQUE COMMENT 'INV-YYYYMMDD-XXX format',
  client_id           INT NOT NULL,
  user_id             INT NOT NULL,
  billing_period_from DATE DEFAULT NULL COMMENT 'Billing period start',
  billing_period_to   DATE DEFAULT NULL COMMENT 'Billing period end',
  subtotal            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_rate            DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Tax percentage',
  tax_amount          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_amount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency            VARCHAR(3) DEFAULT 'NPR',
  amount_paid         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount_due          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  due_date            DATE NOT NULL,
  status              VARCHAR(30) NOT NULL DEFAULT 'draft' COMMENT 'draft, sent, paid, partial, overdue, cancelled',
  notes               TEXT DEFAULT NULL,
  terms               TEXT DEFAULT NULL COMMENT 'Payment terms',
  attachment_url      VARCHAR(500) DEFAULT NULL COMMENT 'Manual attachment (PDF bill, tax receipt etc)',
  attachment_name     VARCHAR(255) DEFAULT NULL,
  created_by          INT DEFAULT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client (client_id),
  INDEX idx_user   (user_id),
  INDEX idx_status (status),
  INDEX idx_due    (due_date),
  INDEX idx_number (invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id    INT NOT NULL,
  description   VARCHAR(500) NOT NULL,
  item_type     VARCHAR(50) NOT NULL COMMENT 'amc_ho, amc_branch, cloud_ho, cloud_branch, custom, support, other',
  quantity      DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_price   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  effective_date DATE DEFAULT NULL COMMENT 'Date this charge applies from',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agreement_templates (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(255) NOT NULL COMMENT 'Template name',
  template_type    VARCHAR(50) NOT NULL COMMENT 'contract, amendment, addendum, renewal, nda, sla',
  template_content LONGTEXT NOT NULL COMMENT 'Word template content with placeholders',
  word_file_path   VARCHAR(255) DEFAULT NULL COMMENT 'Path to Word docx template file',
  is_default      TINYINT NOT NULL DEFAULT 0 COMMENT 'Use as default for this type',
  created_by      INT DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_type (template_type),
  INDEX idx_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  type       VARCHAR(30) NOT NULL DEFAULT 'info',
  title      VARCHAR(255) NOT NULL,
  body       TEXT,
  link_url   VARCHAR(500),
  icon       VARCHAR(50) DEFAULT 'bell',
  seen_at    DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_seen_at (seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_services (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  name       VARCHAR(255) NOT NULL,
  status     VARCHAR(30) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS renewal_reminders (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  client_id       INT DEFAULT NULL,
  subscription_id INT DEFAULT NULL,
  remind_at       DATETIME NOT NULL,
  days_before     INT NOT NULL DEFAULT 30,
  sent            TINYINT NOT NULL DEFAULT 0,
  sent_at         DATETIME,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_remind_at (remind_at),
  INDEX idx_sent      (sent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 5. SUPPORT TICKETS
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS sla_policies (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(100) NOT NULL,
  description        TEXT,
  priority           VARCHAR(30) NOT NULL DEFAULT 'normal',
  response_minutes   INT NOT NULL DEFAULT 240,
  resolution_minutes INT NOT NULL DEFAULT 1440,
  active             TINYINT NOT NULL DEFAULT 1,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  user_id             INT NOT NULL,
  number              VARCHAR(30) NOT NULL UNIQUE,
  subject             VARCHAR(500) NOT NULL,
  body                LONGTEXT,
  category            VARCHAR(100) DEFAULT 'General',
  product             VARCHAR(255) DEFAULT NULL,
  priority            VARCHAR(20) NOT NULL DEFAULT 'normal',
  status              VARCHAR(30) NOT NULL DEFAULT 'open',
  assigned_to         INT DEFAULT NULL,
  sla_deadline        DATETIME DEFAULT NULL,
  sla_response_due    DATETIME DEFAULT NULL,
  sla_resolution_due  DATETIME DEFAULT NULL,
  first_response_at   DATETIME DEFAULT NULL,
  sla_breached        TINYINT NOT NULL DEFAULT 0,
  last_message_at     DATETIME DEFAULT NULL,
  closed_at           DATETIME DEFAULT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_id   (user_id),
  INDEX idx_status    (status),
  INDEX idx_priority  (priority),
  INDEX idx_number    (number),
  INDEX idx_assigned  (assigned_to),
  INDEX idx_created   (created_at),
  INDEX idx_status_priority (status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_replies (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id      INT NOT NULL,
  author_id      INT DEFAULT NULL,
  author_role    VARCHAR(20) NOT NULL DEFAULT 'client',
  body           LONGTEXT NOT NULL,
  attachment_url VARCHAR(500) DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_internal_notes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id  INT NOT NULL,
  author_id  INT DEFAULT NULL,
  body       LONGTEXT NOT NULL,
  note       TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_contacts (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  label       VARCHAR(100) NOT NULL,
  type        VARCHAR(30) NOT NULL DEFAULT 'phone',
  department  VARCHAR(100) DEFAULT NULL,
  value       VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  is_primary  TINYINT NOT NULL DEFAULT 0,
  active      TINYINT NOT NULL DEFAULT 1,
  position    INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_conversations (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  visitor_name    VARCHAR(255) NOT NULL DEFAULT 'Guest',
  visitor_email   VARCHAR(255) DEFAULT NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'open',
  last_message_at DATETIME DEFAULT NULL,
  unread_visitor  INT NOT NULL DEFAULT 0,
  unread_admin    INT NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender          VARCHAR(20) NOT NULL DEFAULT 'visitor',
  message         TEXT NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conv (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 6. CRM
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS crm_leads (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(255) NOT NULL,
  org_name         VARCHAR(255) DEFAULT NULL,
  email            VARCHAR(255) DEFAULT NULL,
  phone            VARCHAR(30)  DEFAULT NULL,
  district         VARCHAR(100) DEFAULT NULL,
  source           VARCHAR(50)  DEFAULT NULL,
  source_ref_id    INT DEFAULT NULL,
  products_interest TEXT DEFAULT NULL,
  stage            VARCHAR(50) NOT NULL DEFAULT 'prospect',
  stage_notes      TEXT DEFAULT NULL,
  deal_value       DECIMAL(14,2) DEFAULT NULL,
  next_followup    DATE DEFAULT NULL,
  last_contact_at  DATETIME DEFAULT NULL,
  assigned_to      INT DEFAULT NULL,
  won_at           DATETIME DEFAULT NULL,
  lost_reason      TEXT DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_stage     (stage),
  INDEX idx_assigned  (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_followups (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  lead_id       INT NOT NULL,
  user_id       INT DEFAULT NULL,
  type          VARCHAR(30) NOT NULL DEFAULT 'call',
  notes         TEXT DEFAULT NULL,
  outcome       VARCHAR(100) DEFAULT NULL,
  next_followup DATE DEFAULT NULL,
  followup_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_proposals (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  lead_id     INT NOT NULL,
  user_id     INT DEFAULT NULL,
  title       VARCHAR(255) NOT NULL,
  products    TEXT DEFAULT NULL,
  amount      DECIMAL(14,2) DEFAULT NULL,
  valid_until DATE DEFAULT NULL,
  status      VARCHAR(30) NOT NULL DEFAULT 'draft',
  notes       TEXT DEFAULT NULL,
  file_url    VARCHAR(500) DEFAULT NULL,
  sent_at     DATETIME DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 7. ORDERS & NOTIFICATIONS
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS orders (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT DEFAULT NULL,
  order_no       VARCHAR(50) NOT NULL UNIQUE,
  customer_email VARCHAR(255),
  product_name   VARCHAR(255),
  plan_name      VARCHAR(100),
  total          DECIMAL(12,2) DEFAULT 0,
  currency       VARCHAR(10) NOT NULL DEFAULT 'NPR',
  status         VARCHAR(30) NOT NULL DEFAULT 'pending',
  notes          TEXT,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS onboarding_progress (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL UNIQUE,
  current_step INT NOT NULL DEFAULT 1,
  total_steps  INT NOT NULL DEFAULT 5,
  completed    TINYINT NOT NULL DEFAULT 0,
  data         JSON,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 8. KNOWLEDGE BASE
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS kb_categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(255) NOT NULL,
  slug        VARCHAR(100) UNIQUE,
  description TEXT,
  icon        VARCHAR(50),
  position    INT NOT NULL DEFAULT 10,
  active      TINYINT NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kb_articles (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  category_id  INT DEFAULT NULL,
  author_id    INT DEFAULT NULL,
  title        VARCHAR(500) NOT NULL,
  slug         VARCHAR(255) UNIQUE,
  excerpt      TEXT,
  body         LONGTEXT,
  tags         TEXT,
  status       VARCHAR(20) NOT NULL DEFAULT 'draft',
  language     VARCHAR(10) NOT NULL DEFAULT 'en',
  views        INT NOT NULL DEFAULT 0,
  published_at DATETIME,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_category (category_id),
  INDEX idx_status   (status),
  INDEX idx_slug     (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 9. STATUS PAGE
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS status_components (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description TEXT,
  status      VARCHAR(30) NOT NULL DEFAULT 'operational',
  sort_order  INT NOT NULL DEFAULT 10,
  active      TINYINT NOT NULL DEFAULT 1,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS status_incidents (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  body         TEXT,
  severity     VARCHAR(30) NOT NULL DEFAULT 'investigating',
  impact       VARCHAR(30) NOT NULL DEFAULT 'minor',
  component_id INT DEFAULT NULL,
  started_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at  DATETIME DEFAULT NULL,
  INDEX idx_component (component_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS status_incident_updates (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  incident_id INT NOT NULL,
  status      VARCHAR(30) NOT NULL DEFAULT 'investigating',
  message     TEXT,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_incident (incident_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 10. API & AUDIT
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS api_tokens (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  token_prefix VARCHAR(20),
  token_hash   VARCHAR(255),
  client_id    INT DEFAULT NULL,
  scopes       TEXT,
  last_used_at DATETIME,
  revoked_at   DATETIME,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_request_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  token_id    INT DEFAULT NULL,
  path        VARCHAR(500),
  method      VARCHAR(10) DEFAULT 'GET',
  status_code SMALLINT,
  ip          VARCHAR(45),
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token_id  (token_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_rate_limits (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ip_hash      VARCHAR(100) NOT NULL UNIQUE,
  hits         INT NOT NULL DEFAULT 1,
  window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT DEFAULT NULL,
  action       VARCHAR(100) NOT NULL,
  target_table VARCHAR(100),
  target_id    INT,
  old_val      LONGTEXT,
  new_val      LONGTEXT,
  ip           VARCHAR(45),
  user_agent   TEXT,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id    (user_id),
  INDEX idx_action     (action),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT DEFAULT NULL,
  type       VARCHAR(50) NOT NULL,
  title      VARCHAR(255) NOT NULL,
  body       TEXT,
  link_url   VARCHAR(500),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cron_runs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  job         VARCHAR(100) NOT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'ok',
  output      TEXT,
  started_at  DATETIME,
  finished_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_job (job)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_intake_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  from_email VARCHAR(255),
  to_email   VARCHAR(255),
  subject    VARCHAR(500),
  body       LONGTEXT,
  processed  TINYINT NOT NULL DEFAULT 0,
  ticket_id  INT DEFAULT NULL,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 11. PUBLIC CONTENT
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS team_members (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(255) NOT NULL,
  role         VARCHAR(255) NOT NULL DEFAULT '',
  bio          TEXT,
  photo_url    VARCHAR(500),
  email        VARCHAR(255),
  linkedin_url VARCHAR(500),
  is_leadership TINYINT NOT NULL DEFAULT 0,
  category     VARCHAR(50) NOT NULL DEFAULT 'management',
  active       TINYINT NOT NULL DEFAULT 1,
  position     INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  author_name VARCHAR(255) NOT NULL,
  author_role VARCHAR(255),
  author_org  VARCHAR(255),
  photo_url   VARCHAR(500),
  quote       TEXT NOT NULL,
  rating      TINYINT NOT NULL DEFAULT 5,
  product_ref VARCHAR(100),
  active      TINYINT NOT NULL DEFAULT 1,
  position    INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(255),
  description TEXT,
  image_url   VARCHAR(500) NOT NULL,
  category    VARCHAR(100) DEFAULT 'General',
  active      TINYINT NOT NULL DEFAULT 1,
  position    INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partners (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(255) NOT NULL,
  logo_url   VARCHAR(500),
  url        VARCHAR(500),
  email      VARCHAR(255),
  phone      VARCHAR(50),
  address    TEXT,
  type       VARCHAR(30) NOT NULL DEFAULT 'client',
  district   VARCHAR(100),
  active     TINYINT NOT NULL DEFAULT 1,
  position   INT NOT NULL DEFAULT 0,
  show_on_contact TINYINT NOT NULL DEFAULT 0 COMMENT 'Show on contact page',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(500) NOT NULL,
  slug         VARCHAR(255) NOT NULL UNIQUE,
  excerpt      TEXT,
  content      LONGTEXT,
  image_url    VARCHAR(500),
  cover_url    VARCHAR(500),
  author_name  VARCHAR(100) DEFAULT 'Company',
  author_title VARCHAR(100) DEFAULT 'Team',
  read_time    INT,
  category     VARCHAR(100) DEFAULT 'News',
  tags         TEXT,
  featured     TINYINT NOT NULL DEFAULT 0,
  active       TINYINT NOT NULL DEFAULT 1,
  published    TINYINT NOT NULL DEFAULT 0,
  published_at DATETIME,
  views        INT NOT NULL DEFAULT 0,
  source_url   VARCHAR(500) DEFAULT NULL COMMENT 'External news link (e.g. onlinekhabar, nagariknews)',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_slug      (slug),
  INDEX idx_published (published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  category   VARCHAR(100) NOT NULL DEFAULT 'General',
  question   TEXT NOT NULL,
  answer     TEXT NOT NULL,
  active     TINYINT NOT NULL DEFAULT 1,
  position   INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(255) NOT NULL,
  slug          VARCHAR(100) NOT NULL UNIQUE,
  client_name   VARCHAR(255),
  category      VARCHAR(100),
  excerpt       TEXT,
  description   LONGTEXT,
  image_url     VARCHAR(500),
  result_metric VARCHAR(255),
  featured      TINYINT NOT NULL DEFAULT 0,
  active        TINYINT NOT NULL DEFAULT 1,
  position      INT NOT NULL DEFAULT 0,
  published_at  DATETIME,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_listings (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  slug         VARCHAR(100) NOT NULL UNIQUE,
  department   VARCHAR(100),
  location     VARCHAR(100) DEFAULT 'Kathmandu, Nepal',
  type         VARCHAR(30) NOT NULL DEFAULT 'full-time',
  experience   VARCHAR(100),
  salary_range VARCHAR(100),
  short_desc   TEXT,
  description  LONGTEXT,
  requirements LONGTEXT,
  perks        TEXT,
  deadline     DATE,
  active       TINYINT NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_applications (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  job_listing_id INT DEFAULT NULL,
  name           VARCHAR(255) NOT NULL,
  email          VARCHAR(255) NOT NULL,
  phone          VARCHAR(30),
  position       VARCHAR(255),
  resume_url     VARCHAR(500),
  cover_letter   LONGTEXT,
  status         VARCHAR(30) NOT NULL DEFAULT 'new',
  notes          TEXT,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_job_id (job_listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_submissions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(255) NOT NULL,
  email      VARCHAR(255) NOT NULL,
  phone      VARCHAR(30),
  org_name   VARCHAR(255),
  subject    VARCHAR(255) DEFAULT 'General Enquiry',
  message    TEXT NOT NULL,
  status     VARCHAR(30) NOT NULL DEFAULT 'new',
  notes      TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demo_requests (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  product      VARCHAR(255) NOT NULL,
  org_name     VARCHAR(255) NOT NULL,
  contact_name VARCHAR(255) NOT NULL,
  email        VARCHAR(255) NOT NULL,
  phone        VARCHAR(30),
  members      INT,
  message      TEXT,
  status       VARCHAR(30) NOT NULL DEFAULT 'new',
  notes        TEXT,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscribers (
  email        VARCHAR(255) NOT NULL PRIMARY KEY,
  name         VARCHAR(255),
  status       VARCHAR(20) NOT NULL DEFAULT 'active',
  source       VARCHAR(50),
  confirmed_at DATETIME,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tech_expertise (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  category    VARCHAR(100),
  icon_url    VARCHAR(500),
  proficiency INT NOT NULL DEFAULT 80,
  active      TINYINT NOT NULL DEFAULT 1,
  position    INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════════
-- DEFAULT DATA — Admin user + basic settings
-- ═══════════════════════════════════════════════════════════════════════════

-- Admin user (password: Admin@12345) — CHANGE THIS IMMEDIATELY AFTER LOGIN
INSERT IGNORE INTO users (display_name, email, password_hash, role, email_verified, active)
VALUES ('Admin', 'admin@yourdomain.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1);
-- NOTE: Default password is "password" via the hash above.
-- To set "Admin@12345", update the hash after importing using:
--   UPDATE users SET password_hash = PASSWORD_BCRYPT('Admin@12345') WHERE email='admin@yourdomain.com';
-- OR just login and change from the admin panel.

-- Basic site settings
INSERT IGNORE INTO site_settings (setting_key, setting_val) VALUES
('site_name',        'Your Company Name'),  -- ← Change this in Admin → Site Settings
('site_tagline',     'Software Solutions for Nepal'),
('contact_email',    ''),
('contact_phone',    ''),
('address',          'Butwal, Rupandehi, Nepal'),
('whatsapp_number',  ''),
('whatsapp_enabled', '1'),
('hero_title',       'Software Built for Nepal'),
('hero_subtitle',    'IT Solutions & Software Services — purpose-built for Nepal.');

-- Default SLA policies
INSERT IGNORE INTO sla_policies (name, description, priority, response_minutes, resolution_minutes) VALUES
('Critical',  'System down, data loss',     'critical', 60,   240),
('High',      'Major feature broken',       'high',     120,  480),
('Normal',    'Standard support request',   'normal',   240,  1440),
('Low',       'Questions & feature requests','low',     480,  2880);

-- Default status components
INSERT IGNORE INTO status_components (name, description, status, sort_order) VALUES
('Web Portal',     'Client portal & website',     'operational', 10),
('Core Banking',   'CBS platform',                'operational', 20),
('Mobile Banking', 'Android & iOS app',           'operational', 30),
('API Services',   'REST API & integrations',     'operational', 40),
('Email / SMS',    'Notification delivery',       'operational', 50);

-- Default KB categories
INSERT IGNORE INTO kb_categories (name, slug, icon, position) VALUES
('Getting Started', 'getting-started', 'rocket',      10),
('Core Banking',    'core-banking',    'landmark',     20),
('Mobile App',      'mobile-app',      'smartphone',   30),
('Billing',         'billing',         'receipt',      40),
('Account',         'account',         'user',         50);

