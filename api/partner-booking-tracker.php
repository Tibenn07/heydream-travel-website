<?php
function ensurePartnerBookingTracking($pdo)
{
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
