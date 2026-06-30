<?php
// File: admin/marketing_migration.php
require_once __DIR__ . '/../config/database.php';

try {
    // For a clean start in this development phase, drop if exists
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DROP TABLE IF EXISTS marketing_templates");
    $pdo->exec("DROP TABLE IF EXISTS marketing_campaigns");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Create marketing_templates table
    $pdo->exec("
        CREATE TABLE marketing_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            subject VARCHAR(200),
            hero_title VARCHAR(200),
            body TEXT,
            hero_image VARCHAR(500),
            cta_buttons TEXT, -- JSON
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create marketing_campaigns table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketing_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_id INT,
            subject VARCHAR(200),
            body TEXT,
            sent_count INT DEFAULT 0,
            open_count INT DEFAULT 0,
            click_count INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'sent', -- 'draft', 'scheduled', 'sent'
            scheduled_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Add some default templates
    $templates = [
        [
            'name' => 'Flash Sale Promo',
            'subject' => 'Flash Sale! Up to 50% Off Your Dream Destination!',
            'hero_title' => 'Limited Time Flash Sale',
            'body' => json_encode([
                ['type' => 'header', 'text' => 'Limited Time Flash Sale', 'align' => 'center', 'color' => '#ffffff', 'bg' => '#003580'],
                ['type' => 'text', 'text' => 'Don\'t miss out on our biggest sale of the year. Book your next adventure now!', 'align' => 'center', 'size' => '16', 'color' => '#64748b'],
                ['type' => 'button', 'text' => 'Book Now', 'link' => 'https://heydreamtravel.com/flash-deals', 'align' => 'center', 'bg' => '#003580', 'color' => '#ffffff', 'size' => '16', 'padding' => '12', 'width' => 'auto']
            ]),
            'cta_buttons' => json_encode([['text' => 'Book Now', 'url' => 'https://heydreamtravel.com/flash-deals']])
        ],
        [
            'name' => 'Welcome Email',
            'subject' => 'Welcome to HeyDream Travel!',
            'hero_title' => 'Ready for Your Next Journey?',
            'body' => json_encode([
                ['type' => 'header', 'text' => 'Ready for Your Next Journey?', 'align' => 'center', 'color' => '#ffffff', 'bg' => '#003580'],
                ['type' => 'text', 'text' => 'Thanks for choosing HeyDream. We\'re excited to help you plan your dream vacation.', 'align' => 'center', 'size' => '16', 'color' => '#64748b'],
                ['type' => 'button', 'text' => 'Browse Packages', 'link' => 'https://heydreamtravel.com/packages', 'align' => 'center', 'bg' => '#003580', 'color' => '#ffffff', 'size' => '16', 'padding' => '12', 'width' => 'auto']
            ]),
            'cta_buttons' => json_encode([['text' => 'Browse Packages', 'url' => 'https://heydreamtravel.com/packages']])
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO marketing_templates (name, subject, hero_title, body, cta_buttons) VALUES (?, ?, ?, ?, ?)");
    foreach ($templates as $t) {
        $check = $pdo->prepare("SELECT id FROM marketing_templates WHERE name = ?");
        $check->execute([$t['name']]);
        if (!$check->fetch()) {
            $stmt->execute([$t['name'], $t['subject'], $t['hero_title'], $t['body'], $t['cta_buttons']]);
        }
    }

    echo "Marketing tables created and seeded successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
