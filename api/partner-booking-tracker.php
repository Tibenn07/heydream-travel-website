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

function resolvePartnerBookingMeta($pdo, array $input)
{
    $partnerId = isset($input['partner_id']) ? (int) $input['partner_id'] : 0;
    $partnerCompany = trim((string) ($input['partner_company'] ?? ''));
    $partnerPackageId = !empty($input['partner_package_id']) ? (int) $input['partner_package_id'] : 0;
    $partnerPackageName = trim((string) ($input['partner_package_name'] ?? ''));
    $partnerSource = trim((string) ($input['partner_source'] ?? ''));

    if ($partnerId > 0 || $partnerCompany !== '' || $partnerPackageId > 0 || $partnerPackageName !== '') {
        return [
            'partner_id' => $partnerId > 0 ? $partnerId : null,
            'partner_company' => $partnerCompany !== '' ? $partnerCompany : null,
            'partner_package_id' => $partnerPackageId > 0 ? $partnerPackageId : null,
            'partner_package_name' => $partnerPackageName !== '' ? $partnerPackageName : null,
            'partner_source' => $partnerSource !== '' ? $partnerSource : 'direct',
        ];
    }

    $searchTerms = array_values(array_filter([
        trim((string) ($input['package_name'] ?? '')),
        trim((string) ($input['destination_name'] ?? '')),
        trim((string) ($input['deal_title'] ?? '')),
        trim((string) ($input['service_name'] ?? '')),
    ], static function ($value) {
        return $value !== '';
    }));

    if (!empty($searchTerms)) {
        $whereClauses = [];
        $params = [];
        foreach ($searchTerms as $term) {
            $whereClauses[] = '(LOWER(package_name) = LOWER(?) OR LOWER(destination_name) = LOWER(?))';
            $params[] = $term;
            $params[] = $term;
        }

        $sql = "SELECT id, partner_id, partner_company, package_name FROM partner_package_uploads WHERE upload_status = 'approved' AND (" . implode(' OR ', $whereClauses) . ") ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($match) {
            return [
                'partner_id' => !empty($match['partner_id']) ? (int) $match['partner_id'] : null,
                'partner_company' => $match['partner_company'] ?? null,
                'partner_package_id' => !empty($match['id']) ? (int) $match['id'] : null,
                'partner_package_name' => $match['package_name'] ?? null,
                'partner_source' => 'partner_package_upload',
            ];
        }
    }

    return [
        'partner_id' => null,
        'partner_company' => null,
        'partner_package_id' => null,
        'partner_package_name' => null,
        'partner_source' => null,
    ];
}
