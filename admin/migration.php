<?php
require_once __DIR__ . '/../config/database.php';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS visas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        category VARCHAR(50) DEFAULT 'international',
        description TEXT,
        price DECIMAL(10,2) DEFAULT 0,
        currency VARCHAR(10) DEFAULT '₱',
        processing_time VARCHAR(50),
        requirements TEXT,
        icon_type VARCHAR(50) DEFAULT 'image',
        icon_value VARCHAR(255),
        is_active TINYINT DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

$visas = [
    [
        'title' => 'Singapore',
        'category' => 'South East Asia',
        'description' => 'e-Arrival Card (SGAC) & complete entry document assistance.',
        'price' => 999,
        'currency' => '₱',
        'processing_time' => 'Visa-Free (30 Days)',
        'icon_type' => 'image',
        'icon_value' => 'https://flagcdn.com/w80/sg.png',
        'requirements' => json_encode([
            "Passport valid for 6 months", 
            "SG Arrival Card (SGAC)", 
            "Confirmed Round-trip Ticket", 
            "Confirmed Hotel Booking", 
            "Proof of Sufficient Funds"
        ]),
        'display_order' => 1
    ],
    [
        'title' => 'Malaysia',
        'category' => 'South East Asia',
        'description' => 'Digital Arrival Card (MDAC) and entry proof preparation.',
        'price' => 999,
        'currency' => '₱',
        'processing_time' => 'Visa-Free (30 Days)',
        'icon_type' => 'image',
        'icon_value' => 'https://flagcdn.com/w80/my.png',
        'requirements' => json_encode([
            "Passport valid for 6 months", 
            "Malaysia Digital Arrival Card (MDAC)", 
            "Confirmed Round-trip Ticket", 
            "Confirmed Hotel Booking"
        ]),
        'display_order' => 2
    ],
    [
        'title' => 'Thailand',
        'category' => 'South East Asia',
        'description' => 'Proof of funds documentation and entry assistance.',
        'price' => 999,
        'currency' => '₱',
        'processing_time' => 'Visa-Free (30 Days)',
        'icon_type' => 'image',
        'icon_value' => 'https://flagcdn.com/w80/th.png',
        'requirements' => json_encode([
            "Passport valid for 6 months", 
            "Confirmed Round-trip Ticket", 
            "Confirmed Hotel Booking", 
            "Proof of Funds (Min. 10,000 THB equivalent)"
        ]),
        'display_order' => 3
    ],
    [
        'title' => 'Indonesia',
        'category' => 'South East Asia',
        'description' => 'Electronic Customs Declaration (e-CD) & entry proofs.',
        'price' => 999,
        'currency' => '₱',
        'processing_time' => 'Visa-Free (30 Days)',
        'icon_type' => 'image',
        'icon_value' => 'https://flagcdn.com/w80/id.png',
        'requirements' => json_encode([
            "Passport valid for 6 months", 
            "e-Customs Declaration (e-CD)", 
            "Confirmed Round-trip Ticket", 
            "Confirmed Hotel Booking"
        ]),
        'display_order' => 4
    ],
    [
        'title' => 'Vietnam',
        'category' => 'South East Asia',
        'description' => 'Complete entry document preparation and proofs.',
        'price' => 999,
        'currency' => '₱',
        'processing_time' => 'Visa-Free (21 Days)',
        'icon_type' => 'image',
        'icon_value' => 'https://flagcdn.com/w80/vn.png',
        'requirements' => json_encode([
            "Passport valid for 6 months", 
            "Confirmed Round-trip Ticket", 
            "Confirmed Hotel/Tour Booking"
        ]),
        'display_order' => 5
    ],
    [
        'title' => 'Schengen Visa',
        'category' => 'International',
        'description' => 'Travel to 27 European countries with a single visa. Expert assistance with documentation.',
        'price' => 8999,
        'currency' => '₱',
        'processing_time' => 'Standard Processing',
        'icon_type' => 'image',
        'icon_value' => 'https://flagcdn.com/w80/eu.png',
        'requirements' => json_encode([
            "Passport valid for 6 months", 
            "2x2 photo with white background", 
            "Detailed Flight Itinerary", 
            "Confirmed Hotel Booking", 
            "Bank Certificate (3-6 Months)", 
            "Certificate of Employment", 
            "Travel Insurance"
        ]),
        'display_order' => 6
    ],
    [
        'title' => 'US Visa',
        'category' => 'International',
        'description' => 'B1/B2 tourist visa assistance. Interview preparation and DS-160 form assistance.',
        'price' => 12999,
        'currency' => '₱',
        'processing_time' => 'Regular Processing',
        'icon_type' => 'image',
        'icon_value' => 'https://flagcdn.com/w80/us.png',
        'requirements' => json_encode([
            "Passport valid for 6 months", 
            "2x2 photo (Recent, white background)", 
            "DS-160 Confirmation", 
            "Proof of strong ties to Philippines", 
            "Financial Documents", 
            "Employment Documents"
        ]),
        'display_order' => 7
    ]
];

$stmt = $pdo->prepare("INSERT INTO visas (title, category, description, price, currency, processing_time, icon_type, icon_value, requirements, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($visas as $visa) {
    // Check if exists
    $check = $pdo->prepare("SELECT id FROM visas WHERE title = ?");
    $check->execute([$visa['title']]);
    if (!$check->fetch()) {
        $stmt->execute([
            $visa['title'],
            $visa['category'],
            $visa['description'],
            $visa['price'],
            $visa['currency'],
            $visa['processing_time'],
            $visa['icon_type'],
            $visa['icon_value'],
            $visa['requirements'],
            $visa['display_order']
        ]);
        echo "Inserted {$visa['title']}\n";
    } else {
        echo "Skipped {$visa['title']} (already exists)\n";
    }
}
echo "Migration complete.\n";
