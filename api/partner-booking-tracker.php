<?php
/**
 * Self-healing fix for a legacy schema bug: on some deployments, `bookings.id`
 * was created without PRIMARY KEY / AUTO_INCREMENT, so any INSERT that didn't
 * supply an id got id = 0. Multiple bookings sharing id = 0 (or, from an old
 * duplicate-submit race, sharing any other id) made `WHERE id = ?` ambiguous,
 * so edits/deletes could silently hit the wrong row. This checks once (cheap)
 * and repairs it once if needed, without touching any other data.
 */
function ensureBookingsPrimaryKey($pdo)
{
    try {
        $hasPrimaryKey = $pdo->query("SHOW KEYS FROM bookings WHERE Key_name = 'PRIMARY'")->fetch();
        if ($hasPrimaryKey) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }

    try {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN tmp_pk_fix_rowid INT AUTO_INCREMENT UNIQUE FIRST");

        $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM bookings")->fetchColumn();
        $pdo->exec("SET @tmpfix_newid := {$maxId}");

        // Renumber every id = 0 row, plus every row that isn't the first
        // occurrence of its (non-zero) id, so all ids become unique.
        $pdo->exec("
            UPDATE bookings b
            SET b.id = (@tmpfix_newid := @tmpfix_newid + 1)
            WHERE b.id = 0
               OR b.tmp_pk_fix_rowid NOT IN (
                    SELECT first_rowid FROM (
                        SELECT MIN(tmp_pk_fix_rowid) AS first_rowid
                        FROM bookings
                        WHERE id <> 0
                        GROUP BY id
                    ) firsts
               )
            ORDER BY b.tmp_pk_fix_rowid ASC
        ");

        $pdo->exec("ALTER TABLE bookings DROP COLUMN tmp_pk_fix_rowid");
        $pdo->exec("ALTER TABLE bookings MODIFY id INT(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)");
    } catch (Throwable $e) {
        // Best effort — if this environment can't run the migration (e.g. limited
        // privileges), leave the table as-is rather than fail the request.
        try { $pdo->exec("ALTER TABLE bookings DROP COLUMN tmp_pk_fix_rowid"); } catch (Throwable $e2) {}
    }
}

function ensurePartnerBookingTracking($pdo)
{
    ensureBookingsPrimaryKey($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_package_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_id INT NOT NULL,
        partner_company VARCHAR(255) NOT NULL,
        uploaded_by_name VARCHAR(255) NOT NULL,
        uploaded_by_email VARCHAR(255) NOT NULL,
        package_name VARCHAR(255) NOT NULL,
        destination_name VARCHAR(255) DEFAULT '',
        duration VARCHAR(80) DEFAULT '',
        price DECIMAL(10,2) DEFAULT 0,
        description TEXT,
        upload_status VARCHAR(30) DEFAULT 'pending',
        image_path VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_partner_uploads_partner (partner_id),
        INDEX idx_partner_uploads_status (upload_status)
    )");

    $trackingColumns = [
        ['partner_id', 'INT DEFAULT NULL'],
        ['partner_company', 'VARCHAR(255) DEFAULT NULL'],
        ['partner_package_id', 'INT DEFAULT NULL'],
        ['partner_package_name', 'VARCHAR(255) DEFAULT NULL'],
        ['partner_source', 'VARCHAR(50) DEFAULT NULL'],
        // Columns the admin and partner booking-edit/view screens depend on that
        // aren't guaranteed to exist on every legacy copy of this table's schema.
        ['address', 'TEXT DEFAULT NULL'],
        ['package_duration', 'VARCHAR(50) DEFAULT NULL'],
        ['price_per_person', 'DECIMAL(10,2) DEFAULT NULL'],
        ['special_requests', 'TEXT DEFAULT NULL'],
        ['flight_details', 'TEXT DEFAULT NULL'],
        ['admin_notes', 'TEXT DEFAULT NULL'],
        ['payment_reference', 'VARCHAR(100) DEFAULT NULL'],
        ['payment_proof', 'VARCHAR(255) DEFAULT NULL'],
        ['payment_processed', 'TINYINT(1) DEFAULT 0'],
        ['travel_documents', 'TINYINT(1) DEFAULT 0'],
        ['ready_for_travel', 'TINYINT(1) DEFAULT 0'],
        ['reminder_sent', 'TINYINT(1) DEFAULT 0'],
        ['visa_status', "VARCHAR(50) DEFAULT 'PENDING'"],
        ['marketing_consent', 'TINYINT(1) DEFAULT 0'],
    ];

    foreach ($trackingColumns as [$column, $definition]) {
        try {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN {$column} {$definition}");
        } catch (Throwable $e) {
            // Ignore if column already exists
        }
    }
}

/**
 * Self-healing setup for the shared `reported_issues` table (also used by
 * support.php and admin/marketing.php's "Reported Issues" tab), plus a
 * one-time backfill of any legacy rows from the old partner-only
 * `partner_support_reports` table so nothing submitted before this change
 * gets lost.
 */
function ensurePartnerReportedIssues($pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS reported_issues (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        contact VARCHAR(255) DEFAULT NULL,
        category VARCHAR(100) NOT NULL,
        severity VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        subject VARCHAR(255) DEFAULT NULL,
        screenshot_path VARCHAR(500) DEFAULT NULL,
        partner_id INT DEFAULT NULL
    )");

    $reportColumns = [
        ['subject', 'VARCHAR(255) DEFAULT NULL'],
        ['screenshot_path', 'VARCHAR(500) DEFAULT NULL'],
        ['partner_id', 'INT DEFAULT NULL'],
    ];
    foreach ($reportColumns as [$column, $definition]) {
        try {
            $pdo->exec("ALTER TABLE reported_issues ADD COLUMN {$column} {$definition}");
        } catch (Throwable $e) {
            // Ignore if column already exists
        }
    }

    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'partner_support_reports'")->fetch();
        if (!$tableCheck) {
            return;
        }

        try {
            $pdo->exec("ALTER TABLE partner_support_reports ADD COLUMN migrated TINYINT(1) DEFAULT 0");
        } catch (Throwable $e) {
            // Ignore if column already exists
        }

        $legacyRows = $pdo->query("SELECT * FROM partner_support_reports WHERE migrated = 0 OR migrated IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        if (!$legacyRows) {
            return;
        }

        $partnerStmt = $pdo->prepare("SELECT contact_person, company_name, email, phone FROM partner_applications WHERE id = ? LIMIT 1");
        $insertStmt = $pdo->prepare("
            INSERT INTO reported_issues (name, email, contact, category, severity, description, status, subject, partner_id)
            VALUES (?, ?, ?, 'Other', ?, ?, ?, ?, ?)
        ");
        $markMigratedStmt = $pdo->prepare("UPDATE partner_support_reports SET migrated = 1 WHERE id = ?");

        foreach ($legacyRows as $row) {
            $partnerStmt->execute([$row['partner_id']]);
            $partnerInfo = $partnerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$partnerInfo) {
                $markMigratedStmt->execute([$row['id']]);
                continue;
            }

            $severity = (strtolower($row['priority'] ?? '') === 'high') ? 'Critical' : 'Medium';
            $status = (strtolower($row['status'] ?? '') === 'open') ? 'Pending' : ($row['status'] ?: 'Pending');

            $insertStmt->execute([
                $partnerInfo['contact_person'] ?: $partnerInfo['company_name'],
                $partnerInfo['email'],
                $partnerInfo['phone'],
                $severity,
                $row['message'],
                $status,
                $row['subject'],
                $row['partner_id'],
            ]);
            $markMigratedStmt->execute([$row['id']]);
        }
    } catch (Throwable $e) {
        // Best effort — legacy backfill failing should never block the request.
    }
}

/**
 * Resolve the partner who owns the package being booked.
 *
 * Priority:
 *  1. If the booking request already carries partner_id / partner_company from JS, use it directly.
 *  2. Otherwise search all content tables (destinations, flash_deals, foreign_destinations,
 *     site_services, cruises, visas, partner_package_uploads) by package/destination name.
 *     Only packages with a non-null partner_id count.
 */
function resolvePartnerBookingMeta($pdo, array $input)
{
    $partnerId        = isset($input['partner_id'])         ? (int) $input['partner_id']         : 0;
    $partnerCompany   = trim((string) ($input['partner_company']      ?? ''));
    $partnerPackageId = !empty($input['partner_package_id']) ? (int) $input['partner_package_id'] : 0;
    $partnerPackageName = trim((string) ($input['partner_package_name'] ?? ''));
    $partnerSource    = trim((string) ($input['partner_source']        ?? ''));

    // If the front-end already resolved partner metadata, trust it.
    if ($partnerId > 0) {
        return [
            'partner_id'           => $partnerId,
            'partner_company'      => $partnerCompany   !== '' ? $partnerCompany   : null,
            'partner_package_id'   => $partnerPackageId > 0   ? $partnerPackageId : null,
            'partner_package_name' => $partnerPackageName !== '' ? $partnerPackageName : null,
            'partner_source'       => $partnerSource    !== '' ? $partnerSource    : 'direct',
        ];
    }

    // Build a list of candidate names to search for.
    $searchTerms = array_values(array_unique(array_filter([
        trim((string) ($input['destination_name']      ?? '')),
        trim((string) ($input['package_name']          ?? '')),
        trim((string) ($input['partner_package_name']  ?? '')),
        trim((string) ($input['deal_title']            ?? '')),
        trim((string) ($input['service_name']          ?? '')),
    ], static fn($v) => $v !== '')));

    if (empty($searchTerms)) {
        return ['partner_id' => null, 'partner_company' => null, 'partner_package_id' => null, 'partner_package_name' => null, 'partner_source' => null];
    }

    // Tables to search: [table => [name_column, partner_id_column, source_label]]
    // All content tables created by partner-content-manager.php store partner_id directly.
    $tables = [
        'destinations'         => ['name', 'partner_id', 'partner_company', 'local_destination'],
        'foreign_destinations' => ['name', 'partner_id', 'partner_company', 'foreign_destination'],
        'flash_deals'          => ['title', 'partner_id', 'partner_company', 'flash_deal'],
        'site_services'        => ['title', 'partner_id', 'partner_company', 'site_service'],
        'cruises'              => ['title', 'partner_id', 'partner_company', 'cruise'],
        'visas'                => ['title', 'partner_id', 'partner_company', 'visa'],
        'partner_package_uploads' => ['package_name', 'partner_id', 'partner_company', 'partner_package_upload'],
    ];

    foreach ($searchTerms as $term) {
        foreach ($tables as $table => [$nameCol, $pidCol, $pcoCol, $sourceLabel]) {
            try {
                $extra = ($table === 'partner_package_uploads') ? " AND upload_status = 'approved'" : '';
                $sql   = "SELECT id, {$pidCol} AS partner_id, {$pcoCol} AS partner_company, {$nameCol} AS pkg_name
                          FROM {$table}
                          WHERE {$pidCol} IS NOT NULL AND LOWER({$nameCol}) = LOWER(?){$extra}
                          LIMIT 1";
                $stmt  = $pdo->prepare($sql);
                $stmt->execute([$term]);
                $row   = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['partner_id'])) {
                    return [
                        'partner_id'           => (int) $row['partner_id'],
                        'partner_company'      => $row['partner_company'] ?? null,
                        'partner_package_id'   => (int) $row['id'],
                        'partner_package_name' => $row['pkg_name'] ?? null,
                        'partner_source'       => $sourceLabel,
                    ];
                }
            } catch (Throwable $e) {
                // Table may not exist yet; continue searching
            }
        }
    }

    return [
        'partner_id'           => null,
        'partner_company'      => null,
        'partner_package_id'   => null,
        'partner_package_name' => null,
        'partner_source'       => null,
    ];
}
