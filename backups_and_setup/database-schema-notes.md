# Database Schema Notes — Self-Migrating Tables

This documents two tables whose full schema is **not** captured in any `.sql`
dump in this repo. Their extra columns are added automatically at runtime by
PHP code the first time a real form submission succeeds — not by a migration
file you run ahead of time.

**Why this matters:** if the database is ever restored from an older backup
or reset, these extra columns disappear silently. The site keeps working
(the next real submission re-adds them), but if you inspect the table
structure in phpMyAdmin *before* that happens, it'll look incomplete. This
file exists so that's not confusing, and so the columns can be re-applied
manually without waiting for a live submission.

---

## 1. `partner_applications`

### Base schema (always present, defined in `config/database.php`)
```
id, company_name, contact_person, email, phone, website, business_type,
message, password, status, rejection_reason, approved_at, created_at,
updated_at, is_banned, ban_until
```

### Auto-added columns
Added by `ensurePartnerColumns()` in `admin/Partnership/partner-register.php`,
called right before the INSERT on a successful submission.

| Column | Type |
|---|---|
| `address` | `TEXT DEFAULT NULL` |
| `latitude` | `VARCHAR(50) DEFAULT NULL` |
| `longitude` | `VARCHAR(50) DEFAULT NULL` |
| `business_id_filename` | `VARCHAR(255) DEFAULT NULL` |
| `business_id_path` | `VARCHAR(500) DEFAULT NULL` |
| `business_permit_filename` | `VARCHAR(255) DEFAULT NULL` |
| `business_permit_path` | `VARCHAR(500) DEFAULT NULL` |
| `dti_filename` | `VARCHAR(255) DEFAULT NULL` |
| `dti_path` | `VARCHAR(500) DEFAULT NULL` |
| `sec_filename` | `VARCHAR(255) DEFAULT NULL` |
| `sec_path` | `VARCHAR(500) DEFAULT NULL` |
| `dot_filename` | `VARCHAR(255) DEFAULT NULL` |
| `dot_path` | `VARCHAR(500) DEFAULT NULL` |
| `face_verification_filename` | `VARCHAR(255) DEFAULT NULL` |
| `face_verification_path` | `VARCHAR(500) DEFAULT NULL` |
| `confirm_valid_docs` | `TINYINT(1) DEFAULT 0` |
| `agree_terms` | `TINYINT(1) DEFAULT 0` |

### Manual re-apply (safe to run anytime — skips columns that already exist)
```sql
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS latitude VARCHAR(50) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS longitude VARCHAR(50) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS business_id_filename VARCHAR(255) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS business_id_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS business_permit_filename VARCHAR(255) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS business_permit_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS dti_filename VARCHAR(255) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS dti_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS sec_filename VARCHAR(255) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS sec_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS dot_filename VARCHAR(255) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS dot_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS face_verification_filename VARCHAR(255) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS face_verification_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS confirm_valid_docs TINYINT(1) DEFAULT 0;
ALTER TABLE partner_applications ADD COLUMN IF NOT EXISTS agree_terms TINYINT(1) DEFAULT 0;
```
*(Requires MySQL 8.0.29+ / MariaDB 10.0.2+ for `IF NOT EXISTS` on `ADD COLUMN`.
Both XAMPP's bundled MariaDB and Hostinger's MySQL support this. If your
version doesn't, run `SHOW COLUMNS FROM partner_applications` first and only
add what's missing.)*

Uploaded files for this table live in `admin/Partnership/uploads/applications/`
(self-created on first upload, `.htaccess`-protected against PHP execution).

---

## 2. `reported_issues`

### Base schema (always present, defined in `admin/ai_chat_admin.php` / original table)
```
id, name, email, contact, category, severity, description, status, created_at
```

### Auto-added columns
Added by `ensureReportColumns()` in `support.php`, called right before the
INSERT on a successful submission.

| Column | Type |
|---|---|
| `subject` | `VARCHAR(255) DEFAULT NULL` |
| `screenshot_path` | `VARCHAR(500) DEFAULT NULL` |

### Manual re-apply
```sql
ALTER TABLE reported_issues ADD COLUMN IF NOT EXISTS subject VARCHAR(255) DEFAULT NULL;
ALTER TABLE reported_issues ADD COLUMN IF NOT EXISTS screenshot_path VARCHAR(500) DEFAULT NULL;
```

Uploaded screenshots live in `uploads/reports/` at the site root
(self-created on first upload, `.htaccess`-protected against PHP execution).

---

## How the self-migration works (same pattern in both files)

```php
function ensureXxxColumns(PDO $pdo) {
    $existing = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM the_table");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['Field']] = true;
    }
    foreach ($columns as $column => $definition) {
        if (!isset($existing[$column])) {
            $pdo->exec("ALTER TABLE the_table ADD COLUMN `$column` $definition");
        }
    }
}
```

It only runs after validation passes, right before the `INSERT`, so a page
load (GET request) never triggers it — only an actual successful submission
does. This is why the columns can appear to "come and go": they exist from
the moment of the first real submission onward, until/unless the database
itself is reset from a backup that predates that submission.

## Last verified live (2026-07-08)
- `reported_issues`: both extra columns present.
- `partner_applications`: extra columns **not** present (will reappear on the
  next real partner application submission, or run the SQL above to add them
  now).
