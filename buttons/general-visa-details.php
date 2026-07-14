<?php
// ========================================
// FILE: buttons/general-visa-details.php
// DESCRIPTION: Details page for the 6 general visa types (DB-driven)
// ========================================
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$mode = (strpos($type, 'Renewal') !== false) ? 'Renew' : 'Apply';

$visa = null;
$requirements = [];

// Try to load visa from database first
if ($pdo && !empty($type)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM visas WHERE title = ? LIMIT 1");
        $stmt->execute([$type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $visa = [
                'title'           => $row['title'],
                'description'     => $row['description'] ?? '',
                'price'           => floatval($row['price'] ?? 0),
                'processing_time' => $row['processing_time'] ?? 'Standard Processing',
                'category'        => $row['category'] ?? 'Global',
                'important_notes' => $row['important_notes'] ?? '',
                'disclaimer'      => $row['disclaimer'] ?? '',
                'icon_value'      => ($row['icon_type'] === 'icon' || $row['icon_type'] === '') ? ($row['icon_value'] ?? 'fas fa-passport') : 'fas fa-passport',
            ];
            $req_raw = $row['requirements'] ?? '[]';
            $decoded = json_decode($req_raw, true);
            $requirements = is_array($decoded) ? $decoded : [];
        }
    } catch (PDOException $e) {
        // Fallback to hardcoded data below
    }
}

// Fallback: hardcoded data if DB lookup failed
if (!$visa) {
    $defaults = [
        'description'     => '',
        'price'           => 0,
        'processing_time' => 'Standard Processing',
        'category'        => 'Global',
        'important_notes' => 'Please ensure all submitted documents are clear, legible, and valid. Processing times may vary depending on embassy workload.',
        'disclaimer'      => 'Visa approval is solely at the discretion of the respective embassy or consulate. HeyDream Travel acts only as an application facilitator. Application service fees are non-refundable once processing has commenced.',
        'icon_value'      => 'fas fa-passport',
    ];
    $baseReqs = [
        'Valid Passport (at least 6 months validity)',
        'Completed Application Form',
        '2x2 Passport-size Photo (white background)',
        'Proof of Financial Capacity (Bank Statement)',
        'Travel Itinerary (Flight & Hotel Booking)',
    ];
    switch ($type) {
        case 'Tourist Visa':
            $visa = array_merge($defaults, ['title' => 'Tourist Visa', 'description' => "Apply for a short-term travel permit for leisure, sightseeing, and recreation. Our team streamlines the documentation and submission process so you can focus on planning your adventure.", 'icon_value' => 'fas fa-umbrella-beach']);
            $requirements = $baseReqs;
            break;
        case 'Resident Visa':
            $visa = array_merge($defaults, ['title' => 'Resident Visa', 'description' => "Secure a long-term residency permit for those planning to live abroad. We guide you through the complex documentation and legal requirements to make your international move smooth and stress-free.", 'icon_value' => 'fas fa-home']);
            $requirements = array_merge($baseReqs, ['Police Clearance / NBI Clearance', 'Medical Certificate', 'Proof of Means of Livelihood']);
            break;
        case 'Work Visa':
            $visa = array_merge($defaults, ['title' => 'Work Visa', 'description' => "Obtain a permit for employment or business activities in a foreign country. We coordinate with your employer and ensure all paperwork is compliant with the destination country's requirements.", 'icon_value' => 'fas fa-briefcase']);
            $requirements = array_merge($baseReqs, ['Employment Contract / Job Offer Letter', 'Company Invitation Letter', 'Educational Certificates & Credentials']);
            break;
        case 'Tourist Visa Renewal':
            $visa = array_merge($defaults, ['title' => 'Tourist Visa Renewal', 'description' => "Extend your tourist stay and continue exploring your destination. We handle the extension paperwork so you don't have to cut your trip short.", 'icon_value' => 'fas fa-umbrella-beach']);
            $requirements = ['Current Visa Copy', 'Valid Passport', 'Proof of Accommodation', 'Letter of Intent for Extension', 'Financial Proof'];
            break;
        case 'Resident Visa Renewal':
            $visa = array_merge($defaults, ['title' => 'Resident Visa Renewal', 'description' => "Renew your residency permit before it expires to maintain your legal status abroad without interruption.", 'icon_value' => 'fas fa-home']);
            $requirements = ['Current Resident Visa', 'Valid Passport', 'Proof of Address', 'Proof of Continued Livelihood', 'Recent ID Photos'];
            break;
        case 'Work Visa Renewal':
            $visa = array_merge($defaults, ['title' => 'Work Visa Renewal', 'description' => "Keep your career abroad running smoothly. Renew your work permit in time and avoid employment disruptions.", 'icon_value' => 'fas fa-briefcase']);
            $requirements = ['Current Work Visa', 'Valid Passport', 'Updated Employment Certificate', 'Employer Endorsement Letter'];
            break;
        default:
            $visa = null;
    }
}

// Auth context
if (class_exists('Auth')) {
    $auth = new Auth($pdo);
    $curr = $auth->getCurrentUser();
} else {
    $curr = null;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $visa ? htmlspecialchars($visa['title']) . ' - HeyDream Travel' : 'Visa Not Found - HeyDream Travel' ?></title>
    <meta name="description" content="<?= $visa ? htmlspecialchars(substr($visa['description'], 0, 160)) : 'Visa service details' ?>">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { background: #f4f6f9; font-family: 'Poppins', sans-serif; }

        .pkgdet-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px 60px; }
        .pkgdet-top-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .pkgdet-breadcrumb { font-size: 0.85rem; color: #64748b; }
        .pkgdet-breadcrumb a { color: #003580; text-decoration: none; font-weight: 600; }
        .pkgdet-breadcrumb a:hover { text-decoration: underline; }
        .pkgdet-back-btn { display: inline-flex; align-items: center; gap: 8px; background: #fff; color: #003580; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; text-decoration: none; box-shadow: 0 1px 3px rgba(15,23,42,0.06); cursor: pointer; transition: background 0.2s; flex-shrink: 0; }
        .pkgdet-back-btn:hover { background: #f1f5f9; }

        .visa-hero { background: linear-gradient(135deg, #003580 0%, #0055c8 60%, #6c5ce7 100%); border-radius: 20px; padding: 36px 32px; display: flex; align-items: center; gap: 24px; margin-bottom: 28px; color: #fff; box-shadow: 0 8px 32px rgba(0,53,128,0.25); }
        .visa-hero-flag { width: 90px; height: 90px; border-radius: 50%; background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 6px 16px rgba(0,0,0,0.2); border: 2px solid rgba(255,255,255,0.3); }
        .visa-hero-flag i { font-size: 2.4rem; color: white; }
        .visa-hero h1 { font-size: 2rem; font-weight: 800; margin: 0 0 8px; }
        .visa-hero-meta { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .visa-status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 20px; font-weight: 700; font-size: 0.82rem; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); }
        @media (max-width: 600px) { .visa-hero { flex-direction: column; text-align: center; } .visa-hero h1 { font-size: 1.5rem; } }

        .pkgdet-body { display: grid; grid-template-columns: 1fr 340px; gap: 28px; align-items: start; }
        @media (max-width: 900px) { .pkgdet-body { grid-template-columns: 1fr; } }

        .pkgdet-card { background: #fff; border-radius: 16px; padding: 26px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .pkgdet-card h2 { font-size: 1.1rem; color: #003580; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .pkgdet-card h2 i { color: #ff9800; }
        .pkgdet-desc { color: #475569; line-height: 1.8; font-size: 0.95rem; }
        .pkgdet-line-row { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.93rem; color: #334155; }
        .pkgdet-line-row:last-child { border-bottom: none; }
        .dot { color: #22c55e; font-size: 0.9rem; margin-top: 2px; flex-shrink: 0; }
        .visa-notes { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 8px; color: #92400e; font-size: 0.9rem; line-height: 1.7; }
        .visa-disclaimer { background: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 16px; border-radius: 8px; color: #991b1b; font-size: 0.88rem; line-height: 1.7; }

        .pkgdet-sticky { position: sticky; top: 80px; }
        .pkgdet-price-card { background: #fff; border-radius: 16px; padding: 26px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
        .pkgdet-price-now { display: block; font-size: 1.5rem; font-weight: 800; color: #003580; margin-bottom: 4px; }
        .pkgdet-price-per { font-size: 0.82rem; color: #64748b; margin-bottom: 20px; }
        .pkgdet-book-btn { width: 100%; background: linear-gradient(135deg, #ff9800, #f57c00); color: white; border: none; padding: 15px; border-radius: 12px; font-weight: 800; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 12px rgba(255,152,0,0.35); }
        .pkgdet-book-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(255,152,0,0.45); }
        .pkgdet-price-meta { margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 16px; display: flex; flex-direction: column; gap: 10px; }
        .pkgdet-price-meta div { display: flex; justify-content: space-between; font-size: 0.85rem; }
        .pkgdet-price-meta span { color: #64748b; }
        .pkgdet-price-meta strong { color: #0f172a; font-weight: 700; }

        .pkgdet-notfound { text-align: center; padding: 80px 20px; }
        .pkgdet-notfound-icon { font-size: 4rem; color: #cbd5e1; margin-bottom: 24px; }
        .pkgdet-notfound h2 { color: #334155; margin-bottom: 12px; }
        .pkgdet-notfound p { color: #64748b; margin-bottom: 28px; }
        .pkgdet-notfound-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 12px; font-weight: 700; text-decoration: none; }
        .pkgdet-notfound-btn.primary { background: #003580; color: white; }

        /* ── Visa Drawer ── */
        .visa-drawer-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.45); backdrop-filter:blur(4px); z-index:2000; opacity:0; visibility:hidden; transition:all 0.3s ease; }
        .visa-drawer-overlay.active { opacity:1; visibility:visible; }
        .visa-drawer { position:fixed; top:50%; left:50%; width:90%; max-width:600px; height:85vh; max-height:800px; transform:translate(-50%, -50%) scale(0.95); opacity:0; visibility:hidden; background:rgba(255,255,255,1); border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.2); z-index:2001; transition:all 0.3s cubic-bezier(0.4,0,0.2,1); display:flex; flex-direction:column; overflow:hidden; }
        @media(max-width:600px){ .visa-drawer{ width:100%; height:100vh; max-height:none; border-radius:0; transform:translate(-50%, -50%) scale(1); } }
        .visa-drawer.active { transform:translate(-50%, -50%) scale(1); opacity:1; visibility:visible; }
        .visa-drawer-header { background:linear-gradient(135deg,#6c5ce7,#8a7cff); color:white; padding:24px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 4px 15px rgba(108,92,231,0.3); }
        .visa-drawer-header h2 { margin:0; font-size:1.3rem; font-weight:800; display:flex; align-items:center; gap:10px; }
        .visa-drawer-close { background:rgba(255,255,255,0.2); border:none; color:white; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; cursor:pointer; transition:all 0.2s; flex-shrink:0; }
        .visa-drawer-close:hover { background:rgba(255,255,255,0.4); transform:rotate(90deg); }
        .visa-drawer-body { flex:1; overflow-y:auto; padding:24px; }
        .visa-drawer-footer { padding:20px 24px; background:#fff; border-top:1px solid #f1f5f9; display:flex; gap:12px; }
        .visa-drawer-btn { flex:1; padding:14px; border-radius:12px; font-weight:700; font-size:1rem; border:none; cursor:pointer; transition:all 0.2s; }
        .visa-drawer-btn.cancel { background:#f1f5f9; color:#475569; }
        .visa-drawer-btn.cancel:hover { background:#e2e8f0; }
        .visa-drawer-btn.submit { background:linear-gradient(135deg,#003580,#0055c8); color:white; box-shadow:0 4px 12px rgba(0,53,128,0.3); }
        .visa-drawer-btn.submit:hover { transform:translateY(-2px); }
        .visa-drawer-btn:disabled { opacity:0.7; cursor:not-allowed; transform:none !important; }
        .vd-section { margin-bottom:22px; }
        .vd-section h4 { color:#003580; margin-bottom:14px; font-size:1rem; display:flex; align-items:center; gap:8px; border-left:4px solid #ff9800; padding-left:12px; }
        .vd-form-group { margin-bottom:14px; }
        .vd-form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        @media(max-width:480px){ .vd-form-row{ grid-template-columns:1fr; } }
        .vd-form-group label { display:block; font-weight:600; margin-bottom:6px; font-size:0.83rem; color:#334155; }
        .vd-form-group label .req { color:#ef4444; }
        .vd-form-group input, .vd-form-group select, .vd-form-group textarea { width:100%; padding:11px; border:1px solid #cbd5e1; border-radius:10px; font-size:0.93rem; font-family:inherit; background:#fff; transition:border-color 0.2s,box-shadow 0.2s; box-sizing:border-box; }
        .vd-form-group input:focus, .vd-form-group select:focus, .vd-form-group textarea:focus { outline:none; border-color:#6c5ce7; box-shadow:0 0 0 3px rgba(108,92,231,0.1); }
        .vd-form-group input.vd-err, .vd-form-group select.vd-err { border-color:#ef4444; background:#fef2f2; }
        .vd-file-upload { border:2px dashed #cbd5e1; border-radius:12px; padding:18px; text-align:center; cursor:pointer; transition:all 0.2s; background:#f8fafc; }
        .vd-file-upload:hover { border-color:#6c5ce7; background:#f0f7ff; }
        .vd-file-upload i { font-size:1.6rem; color:#94a3b8; display:block; margin-bottom:6px; }
        .vd-file-upload p { font-size:0.82rem; color:#64748b; margin:0; }
        .vd-file-name { font-size:0.78rem; color:#003580; font-weight:600; margin-top:6px !important; }
        .vd-error-msg { background:#fef2f2; border-left:4px solid #ef4444; padding:12px 16px; border-radius:8px; color:#b91c1c; font-size:0.88rem; margin-bottom:18px; display:none; }
        .vd-success-view { display:none; text-align:center; padding:40px 20px; }
        .vd-success-view .fa-check-circle { font-size:4rem; color:#22c55e; margin-bottom:20px; }
        .vd-success-view h3 { font-size:1.5rem; color:#0f172a; margin-bottom:10px; }
        .vd-success-view p { color:#475569; line-height:1.6; margin-bottom:24px; }
        .vd-success-view .ref-box { background:#f1f5f9; padding:16px; border-radius:12px; font-family:monospace; font-size:1.1rem; font-weight:bold; color:#003580; letter-spacing:1px; }
    </style>
</head>

<body>
    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="logo" onclick="window.location.href='../index.php'">
            <div class="company-name">
                <span class="line1">HeyDream Travel</span>
                <span class="line2">and Tours</span>
            </div>
        </div>
    </header>

    <section class="main-page-section">
        <div class="pkgdet-wrap">
            <?php if (!$visa): ?>
                <div class="pkgdet-notfound">
                    <div class="pkgdet-notfound-icon"><i class="fas fa-passport"></i></div>
                    <h2>Visa Not Found</h2>
                    <p>The visa type you're looking for doesn't exist. Please go back and try again.</p>
                    <div>
                        <a href="visa.php" class="pkgdet-notfound-btn primary"><i class="fas fa-arrow-left"></i> Back to Visas</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="pkgdet-top-row">
                    <a href="visa.php" class="pkgdet-back-btn"><i class="fas fa-arrow-left"></i> Back to Visas</a>
                    <div class="pkgdet-breadcrumb">
                        <a href="../index.php">Home</a> /
                        <a href="visa.php">Visa</a> /
                        <?= htmlspecialchars($visa['title']) ?>
                    </div>
                </div>

                <div class="visa-hero">
                    <div class="visa-hero-flag">
                        <i class="<?= htmlspecialchars($visa['icon_value']) ?>"></i>
                    </div>
                    <div>
                        <h1><?= htmlspecialchars($visa['title']) ?></h1>
                        <div class="visa-hero-meta">
                            <span class="visa-status-pill"><i class="fas fa-exclamation-circle"></i> Visa Required</span>
                            <?php if ($visa['processing_time']): ?><span class="visa-status-pill"><i class="fas fa-clock"></i> <?= htmlspecialchars($visa['processing_time']) ?></span><?php endif; ?>
                            <?php if ($visa['category']): ?><span class="visa-status-pill"><i class="fas fa-globe-asia"></i> <?= htmlspecialchars($visa['category']) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="pkgdet-body">
                    <div class="pkgdet-main">
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-info-circle"></i> Overview</h2>
                            <p class="pkgdet-desc"><?= nl2br(htmlspecialchars($visa['description'])) ?></p>
                        </div>

                        <?php if ($requirements): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-clipboard-list"></i> Required Documents</h2>
                            <?php foreach ($requirements as $r): ?>
                                <div class="pkgdet-line-row"><span class="dot"><i class="fas fa-check"></i></span><span><?= htmlspecialchars($r) ?></span></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($visa['important_notes']): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-info-circle"></i> Important Notes</h2>
                            <div class="visa-notes"><?= nl2br(htmlspecialchars($visa['important_notes'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($visa['disclaimer']): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-exclamation-triangle"></i> Disclaimer</h2>
                            <div class="visa-disclaimer"><?= nl2br(htmlspecialchars($visa['disclaimer'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pkgdet-sticky">
                        <div class="pkgdet-price-card">
                            <?php if ($visa['price'] > 0): ?>
                                <span class="pkgdet-price-now">₱<?= number_format($visa['price']) ?></span>
                                <div class="pkgdet-price-per">Application Fee</div>
                            <?php else: ?>
                                <span class="pkgdet-price-now">Assessment Required</span>
                                <div class="pkgdet-price-per">Fee varies by destination & embassy</div>
                            <?php endif; ?>
                            <button class="pkgdet-book-btn" onclick="openVisaDrawer('<?= htmlspecialchars($visa['title']) ?>', '<?= $mode ?>')">
                                <i class="fas fa-bolt"></i>
                                <?= $mode === 'Renew' ? 'Renew Now' : 'Apply Now' ?>
                            </button>
                            <div class="pkgdet-price-meta">
                                <div><span>Processing Time</span><strong><?= htmlspecialchars($visa['processing_time'] ?: 'Standard') ?></strong></div>
                                <div><span>Application Type</span><strong><?= $mode === 'Renew' ? 'Renewal' : 'New Application' ?></strong></div>
                                <div><span>Coverage</span><strong><?= htmlspecialchars($visa['category']) ?></strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-logo-section">
                <div class="footer-logo">
                    <img src="../images/Heydream Logo.png" alt="HeyDream Logo" class="footer-logo-img">
                    <span class="footer-brand">HeyDream</span>
                </div>
                <div class="footer-country"><i class="fas fa-globe"></i> Philippines (Pilipinas)</div>
            </div>
            <div class="footer-links-grid">
                <div class="footer-column">
                    <h4>Contact Us</h4>
                    <ul class="contact-list">
                        <li><i class="fas fa-map-marker-alt"></i> 3104 Tektite East Tower, Philippine Stock Exchange, Ortigas</li>
                        <li><i class="fas fa-phone-alt"></i> 0945 776 4140</li>
                        <li><i class="fas fa-envelope"></i> heydreamtravelandtours@gmail.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Fri: 9AM-6PM<br>Sat: 9AM-1PM</li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="../career.php">Career</a></li>
                        <li><a href="../privacy.php">Data Privacy Policy</a></li>
                        <li><a href="../terms.php">Terms &amp; Conditions</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-social">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="https://x.com/HeyDreamTravel?s=20" target="_blank"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 HeyDream Travel and Tours. All rights reserved.</p>
        </div>
    </footer>

    <!-- ── Visa Application Drawer ── -->
    <div class="visa-drawer-overlay" id="vdOverlay" onclick="closeVisaDrawer()"></div>
    <div class="visa-drawer" id="vdDrawer">
        <div class="visa-drawer-header">
            <h2 id="vdHeaderTitle"><i class="fas fa-file-signature"></i> Visa Application</h2>
            <button class="visa-drawer-close" onclick="closeVisaDrawer()"><i class="fas fa-times"></i></button>
        </div>
        <div class="visa-drawer-body" id="vdBody">
            <div class="vd-error-msg" id="vdError"></div>
            <form id="vdForm" onsubmit="return false;">
                <input type="hidden" id="vdVisaType">
                <div class="vd-section">
                    <h4><i class="fas fa-user"></i> Personal Information</h4>
                    <div class="vd-form-group"><label>Email Address <span class="req">*</span></label><input type="email" id="vdEmail" placeholder="your@email.com"></div>
                    <div class="vd-form-group"><label>Full Name <span class="req">*</span></label><input type="text" id="vdName" placeholder="As written in passport"></div>
                    <div class="vd-form-row">
                        <div class="vd-form-group"><label>Date of Birth <span class="req">*</span></label><input type="date" id="vdDob"></div>
                        <div class="vd-form-group"><label>Nationality <span class="req">*</span></label><input type="text" id="vdNationality" placeholder="e.g. Filipino"></div>
                    </div>
                    <div class="vd-form-group"><label>Phone Number <span class="req">*</span></label><input type="tel" id="vdPhone" placeholder="+63 912 345 6789"></div>
                </div>
                <div class="vd-section">
                    <h4><i class="fas fa-passport"></i> Passport Details</h4>
                    <div class="vd-form-group"><label>Passport Number <span class="req">*</span></label><input type="text" id="vdPassportNum"></div>
                    <div class="vd-form-row">
                        <div class="vd-form-group"><label>Expiry Date <span class="req">*</span></label><input type="date" id="vdPassportExpiry"></div>
                        <div class="vd-form-group"><label>Place of Issue <span class="req">*</span></label><input type="text" id="vdPassportIssue"></div>
                    </div>
                </div>
                <div class="vd-section" id="vdRenewalSection" style="display:none;">
                    <h4><i class="fas fa-sync-alt"></i> Renewal Information</h4>
                    <div class="vd-form-group"><label>Current Visa Number <span class="req">*</span></label><input type="text" id="vdCurrentVisaNum"></div>
                    <div class="vd-form-group"><label>Current Visa Expiry Date <span class="req">*</span></label><input type="date" id="vdCurrentVisaExpiry"></div>
                    <div class="vd-form-group"><label>Reason for Renewal</label><textarea id="vdRenewalReason" rows="2" placeholder="Brief explanation"></textarea></div>
                </div>
                <div class="vd-section">
                    <h4><i class="fas fa-plane-departure"></i> Travel Information</h4>
                    <div class="vd-form-group"><label>Destination Country <span class="req">*</span></label><input type="text" id="vdDestination" placeholder="e.g. Japan, Canada"></div>
                    <div class="vd-form-row">
                        <div class="vd-form-group"><label>Intended Arrival <span class="req">*</span></label><input type="date" id="vdArrival"></div>
                        <div class="vd-form-group"><label>Intended Departure <span class="req">*</span></label><input type="date" id="vdDeparture"></div>
                    </div>
                    <div class="vd-form-group"><label>Purpose of Visit <span class="req">*</span></label><input type="text" id="vdPurpose" placeholder="e.g. Tourism, Work, Study"></div>
                </div>
                <div class="vd-section">
                    <h4><i class="fas fa-file-upload"></i> Documents <span style="font-size:0.75rem;font-weight:400;color:#ef4444;">(Required, max 10MB)</span></h4>
                    <div class="vd-form-group">
                        <label>Passport Data Page</label>
                        <div class="vd-file-upload" onclick="document.getElementById('vdDocPassport').click()">
                            <i class="fas fa-cloud-upload-alt"></i><p>Click to upload (PDF or image)</p>
                            <div class="vd-file-name" id="vdNamePassport">No file selected</div>
                            <input type="file" id="vdDocPassport" style="display:none" accept="image/*,application/pdf" onchange="vdHandleFile(event,'vdNamePassport')">
                        </div>
                    </div>
                    <div class="vd-form-group">
                        <label>Passport-size Photo</label>
                        <div class="vd-file-upload" onclick="document.getElementById('vdDocPhoto').click()">
                            <i class="fas fa-cloud-upload-alt"></i><p>Click to upload (PDF or image)</p>
                            <div class="vd-file-name" id="vdNamePhoto">No file selected</div>
                            <input type="file" id="vdDocPhoto" style="display:none" accept="image/*,application/pdf" onchange="vdHandleFile(event,'vdNamePhoto')">
                        </div>
                    </div>
                    <div class="vd-form-group">
                        <label>Supporting Documents</label>
                        <div class="vd-file-upload" onclick="document.getElementById('vdDocSupport').click()">
                            <i class="fas fa-cloud-upload-alt"></i><p>Click to upload (PDF or image)</p>
                            <div class="vd-file-name" id="vdNameSupport">No file selected</div>
                            <input type="file" id="vdDocSupport" style="display:none" accept="image/*,application/pdf" onchange="vdHandleFile(event,'vdNameSupport')">
                        </div>
                    </div>
                    <div class="vd-form-group">
                        <label>Additional Documents</label>
                        <div class="vd-file-upload" onclick="document.getElementById('vdDocAdditional').click()">
                            <i class="fas fa-cloud-upload-alt"></i><p>Click to upload (PDF or image)</p>
                            <div class="vd-file-name" id="vdNameAdditional">No file selected</div>
                            <input type="file" id="vdDocAdditional" style="display:none" accept="image/*,application/pdf" onchange="vdHandleFile(event,'vdNameAdditional')">
                    </div>
                </div>
                <div class="vd-section">
                    <h4><i class="fas fa-credit-card"></i> Payment Method</h4>
                    <p style="font-size:0.85rem; color:#64748b; margin-bottom:12px;">Select how you wish to handle the payment.</p>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px;">
                        <label style="flex:1; border:1px solid #cbd5e1; border-radius:10px; padding:12px; cursor:pointer; display:flex; align-items:center; gap:8px;" onclick="document.getElementById('vdGcashDetails').style.display='block';">
                            <input type="radio" name="vdPaymentMethod" value="GCash" checked>
                            <i class="fas fa-mobile-alt" style="color:#003580;"></i> GCash
                        </label>
                        <label style="flex:1; border:1px solid #cbd5e1; border-radius:10px; padding:12px; cursor:pointer; display:flex; align-items:center; gap:8px;" onclick="document.getElementById('vdGcashDetails').style.display='none';">
                            <input type="radio" name="vdPaymentMethod" value="Manual Agent Approval">
                            <i class="fas fa-user-tie" style="color:#003580;"></i> Pay Later / Agent
                        </label>
                    </div>

                    <div id="vdGcashDetails" class="vd-pay-details" style="display:block; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:15px; margin-bottom:15px;">
                        <div style="text-align:center; margin-bottom:15px;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=09457764140" alt="GCash QR Code" style="border:1px solid #ccc; border-radius:8px; padding:5px; background:white; width:120px; height:120px; margin-bottom:10px;">
                            <p style="margin:0; font-weight:700; color:#0f172a;">HeyDream Travel & Tours</p>
                            <p style="margin:0; font-size:0.9rem; color:#64748b;">0945 776 4140</p>
                        </div>
                        <div class="vd-form-group">
                            <label>GCash Reference Number <span class="req">*</span></label>
                            <input type="text" id="vdPaymentRef" placeholder="e.g. 1234567890123">
                        </div>
                        <div class="vd-form-group" style="margin-bottom:0;">
                            <label>Upload Payment Proof <span class="req">*</span></label>
                            <div class="vd-file-upload" onclick="document.getElementById('vdDocPayment').click()">
                                <i class="fas fa-receipt"></i><p>Click to upload receipt</p>
                                <div class="vd-file-name" id="vdNamePayment">No file selected</div>
                                <input type="file" id="vdDocPayment" style="display:none" accept="image/*,application/pdf" onchange="vdHandleFile(event,'vdNamePayment')">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <div class="vd-success-view" id="vdSuccess">
                <i class="fas fa-check-circle"></i>
                <h3>Application Submitted!</h3>
                <p>We've received your <span id="vdSuccessType">Visa</span> application. Our agents will review your details and contact you shortly at the email address provided.</p>
                <div class="ref-box" id="vdSuccessRef"></div>
            </div>
        </div>
        <div class="visa-drawer-footer" id="vdFooter">
            <button type="button" class="visa-drawer-btn cancel" onclick="closeVisaDrawer()">Cancel</button>
            <button type="button" class="visa-drawer-btn submit" id="vdSubmitBtn" onclick="submitVisaDrawer()">Submit Application <i class="fas fa-paper-plane" style="margin-left:6px;"></i></button>
        </div>
    </div>

    <script>
        window.currentUserEmail = '<?php echo ($curr && isset($curr['email'])) ? $curr['email'] : ''; ?>';
        window.currentFullName  = '<?php echo ($curr && isset($curr['full_name'])) ? htmlspecialchars($curr['full_name']) : ''; ?>';

        function openVisaDrawer(visaType, mode) {
            document.getElementById('vdEmail').value = window.currentUserEmail || '';
            document.getElementById('vdName').value  = window.currentFullName  || '';
            document.getElementById('vdVisaType').value = visaType;
            document.getElementById('vdHeaderTitle').innerHTML = mode === 'Renew'
                ? `<i class="fas fa-sync-alt"></i> ${visaType}`
                : `<i class="fas fa-file-signature"></i> ${visaType}`;
            document.getElementById('vdRenewalSection').style.display = mode === 'Renew' ? 'block' : 'none';
            document.getElementById('vdForm').style.display    = 'block';
            document.getElementById('vdSuccess').style.display = 'none';
            document.getElementById('vdFooter').style.display  = 'flex';
            document.getElementById('vdError').style.display   = 'none';
            document.getElementById('vdSubmitBtn').disabled    = false;
            document.getElementById('vdSubmitBtn').innerHTML   = 'Submit Application <i class="fas fa-paper-plane" style="margin-left:6px;"></i>';
            document.getElementById('vdOverlay').classList.add('active');
            document.getElementById('vdDrawer').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeVisaDrawer() {
            document.getElementById('vdOverlay').classList.remove('active');
            document.getElementById('vdDrawer').classList.remove('active');
            document.body.style.overflow = '';
            ['vdDocPassport','vdDocPhoto','vdDocSupport','vdDocAdditional','vdDocPayment'].forEach(id => { const e=document.getElementById(id); if(e) e.value=''; });
            ['vdNamePassport','vdNamePhoto','vdNameSupport','vdNameAdditional','vdNamePayment'].forEach(id => { const e=document.getElementById(id); if(e) e.innerText='No file selected'; });
            const pRef = document.getElementById('vdPaymentRef'); if(pRef) pRef.value = '';
            document.querySelector('input[name="vdPaymentMethod"][value="GCash"]').checked = true;
            document.getElementById('vdGcashDetails').style.display = 'block';
        }

        function vdHandleFile(e, labelId) {
            const file = e.target.files[0], label = document.getElementById(labelId);
            if (file) {
                if (file.size > 10*1024*1024) { alert('File too large. Max 10MB.'); e.target.value=''; label.innerText='No file selected'; }
                else { label.innerText = file.name; }
            } else { label.innerText = 'No file selected'; }
        }

        async function submitVisaDrawer() {
            const btn = document.getElementById('vdSubmitBtn'), errDiv = document.getElementById('vdError');
            errDiv.style.display = 'none';
            document.querySelectorAll('.vd-form-group input,.vd-form-group select').forEach(el => el.classList.remove('vd-err'));
            const reqIds = ['vdEmail','vdName','vdDob','vdNationality','vdPhone','vdPassportNum','vdPassportExpiry','vdPassportIssue','vdDestination','vdArrival','vdDeparture','vdPurpose'];
            const isRenew = document.getElementById('vdRenewalSection').style.display === 'block';
            if (isRenew) reqIds.push('vdCurrentVisaNum','vdCurrentVisaExpiry');
            let selectedPayment = document.querySelector('input[name="vdPaymentMethod"]:checked').value;
            let hasError = false;
            reqIds.forEach(id => { const el=document.getElementById(id); if (!el || !el.value.trim()) { if(el) el.classList.add('vd-err'); hasError=true; } });
            
            if (selectedPayment === 'GCash') {
                const refEl = document.getElementById('vdPaymentRef');
                const proofEl = document.getElementById('vdDocPayment');
                if (!refEl.value.trim()) { refEl.classList.add('vd-err'); hasError = true; }
                if (!proofEl.files.length) { errDiv.innerHTML='<i class="fas fa-exclamation-triangle"></i> Please upload payment proof.'; errDiv.style.display='block'; return; }
            }
            if (hasError) { errDiv.innerHTML='<i class="fas fa-exclamation-triangle"></i> Please fill in all required fields.'; errDiv.style.display='block'; document.getElementById('vdBody').scrollTop=0; return; }
            btn.disabled = true; const orig = btn.innerHTML; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Submitting...';
            let special = `Nationality: ${document.getElementById('vdNationality').value}, DOB: ${document.getElementById('vdDob').value}, Passport: ${document.getElementById('vdPassportNum').value} (Exp: ${document.getElementById('vdPassportExpiry').value}, Issued: ${document.getElementById('vdPassportIssue').value}), Dest: ${document.getElementById('vdDestination').value}, Travel: ${document.getElementById('vdArrival').value} to ${document.getElementById('vdDeparture').value}, Purpose: ${document.getElementById('vdPurpose').value}`;
            if (isRenew) special += ` | RENEWAL - Current Visa: ${document.getElementById('vdCurrentVisaNum').value} (Exp: ${document.getElementById('vdCurrentVisaExpiry').value}), Reason: ${document.getElementById('vdRenewalReason').value}`;
            const payload = { service_type: 'Visa Assistance', package_name: document.getElementById('vdVisaType').value, full_name: document.getElementById('vdName').value, email: document.getElementById('vdEmail').value, phone: document.getElementById('vdPhone').value, total_amount: '<?= $visa['price'] > 0 ? $visa['price'] : "0.00" ?>', travel_date: document.getElementById('vdArrival').value, special_requests: special, payment_method: selectedPayment, payment_reference: selectedPayment === 'GCash' ? document.getElementById('vdPaymentRef').value : 'PENDING_AGENT' };
            try {
                const data = await fetch('../api/save-service-booking.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) }).then(r => r.json());
                if (data.success) {
                    const ref = data.booking_number, uploads = [];
                    [['vdDocPassport','Passport Copy'],['vdDocPhoto','Passport Photo'],['vdDocSupport','Supporting Docs'],['vdDocAdditional','Additional Docs'],['vdDocPayment','Payment Proof']].forEach(([id, title]) => {
                        const el = document.getElementById(id);
                        if (el && el.files.length > 0) {
                            const fd = new FormData(); fd.append('action','upload'); fd.append('booking_number',ref); fd.append('document',el.files[0]);
                            uploads.push(fetch('../User Account/api/upload-api.php', {method:'POST', body:fd}).catch(e=>console.error(e)));
                        }
                    });
                    if (uploads.length > 0) { btn.innerHTML='<i class="fas fa-cloud-upload-alt fa-fade"></i> Uploading...'; await Promise.all(uploads); }
                    document.getElementById('vdForm').style.display = 'none';
                    document.getElementById('vdFooter').style.display = 'none';
                    document.getElementById('vdSuccessType').innerText = document.getElementById('vdVisaType').value;
                    document.getElementById('vdSuccessRef').innerText  = ref;
                    document.getElementById('vdSuccess').style.display = 'block';
                } else { errDiv.innerHTML='<i class="fas fa-exclamation-triangle"></i> Error: '+data.message; errDiv.style.display='block'; btn.disabled=false; btn.innerHTML=orig; }
            } catch(err) { errDiv.innerHTML='<i class="fas fa-exclamation-triangle"></i> Network error. Please try again.'; errDiv.style.display='block'; btn.disabled=false; btn.innerHTML=orig; }
        }
    </script>
    <script src="../js/main.js?v=2"></script>
    <script src="../js/auth-menu.js?v=2"></script>
    <?php include_once __DIR__ . '/../chatbot_widget.php'; ?>
</body>

</html>
