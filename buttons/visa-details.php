<?php
// ========================================
// FILE: buttons/visa-details.php
// DESCRIPTION: Full-page detail view for a visa service (mirrors package-details.php)
// ========================================
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$identifier = isset($_GET['id']) ? $_GET['id'] : '';
$isNumeric = is_numeric($identifier);

$visa = null;

// Self-healing migration for missing columns
try {
    $pdo->exec("ALTER TABLE visas ADD COLUMN partner_id INT NULL");
} catch (PDOException $e) { /* Column exists or table doesn't exist yet */ }
try {
    $pdo->exec("ALTER TABLE visas ADD COLUMN partner_company VARCHAR(255) NULL");
} catch (PDOException $e) { /* Column exists */ }


try {
    $sql = "SELECT v.*, COALESCE(pr.business_display_name, p.company_name, v.partner_company) AS partner_company
            FROM visas v
            LEFT JOIN partner_applications p ON v.partner_id = p.id
            LEFT JOIN partner_profiles pr ON pr.partner_id = v.partner_id
            WHERE ";
    if ($isNumeric) {
        $stmt = $pdo->prepare($sql . "v.id = :id");
        $stmt->execute(['id' => intval($identifier)]);
    } else {
        $stmt = $pdo->prepare($sql . "(v.title = :name OR REPLACE(LOWER(v.title), ' ', '_') = :name OR v.title LIKE :name_like)");
        $stmt->execute(['name' => $identifier, 'name_like' => '%' . $identifier . '%']);
    }
    $visa = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $visa = null;
}

$requirements = [];
if ($visa) {
    $decoded = json_decode($visa['requirements'] ?? '', true);
    if (is_array($decoded)) $requirements = $decoded;
}

$isFree = $visa && strtolower($visa['visa_status'] ?? '') === 'free';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $visa ? htmlspecialchars($visa['title']) . ' Visa - HeyDream Travel' : 'Visa Not Found - HeyDream Travel' ?></title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { background: #f4f6f9; font-family: 'Poppins', sans-serif; }

        .pkgdet-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px 60px; }

        .pkgdet-top-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }

        .pkgdet-breadcrumb { font-size: 0.85rem; color: #64748b; }
        .pkgdet-breadcrumb a { color: #003580; text-decoration: none; font-weight: 600; }
        .pkgdet-breadcrumb a:hover { text-decoration: underline; }

        .pkgdet-back-btn { display: inline-flex; align-items: center; gap: 8px; background: #fff; color: #003580; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; text-decoration: none; box-shadow: 0 1px 3px rgba(15,23,42,0.06); cursor: pointer; transition: background 0.2s, box-shadow 0.2s; flex-shrink: 0; }
        .pkgdet-back-btn:hover { background: #f1f5f9; box-shadow: 0 2px 6px rgba(15,23,42,0.1); }

        .visa-hero { background: linear-gradient(135deg, #003580, #0055c8); border-radius: 20px; padding: 32px; display: flex; align-items: center; gap: 22px; margin-bottom: 28px; color: #fff; }
        .visa-hero-flag { width: 84px; height: 84px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; box-shadow: 0 6px 16px rgba(0,0,0,0.2); }
        .visa-hero-flag img { width: 60px; height: 60px; object-fit: contain; }
        .visa-hero h1 { font-size: 1.8rem; font-weight: 800; margin: 0 0 6px; }
        .visa-hero-meta { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .visa-status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 20px; font-weight: 700; font-size: 0.82rem; }
        .visa-status-pill.free { background: #dcfce7; color: #15803d; }
        .visa-status-pill.required { background: #fff7ed; color: #c2410c; }

        .pkgdet-body { display: grid; grid-template-columns: 1fr 340px; gap: 28px; align-items: start; }
        @media (max-width: 900px) {
            .pkgdet-body { grid-template-columns: 1fr; }
            .pkgdet-sticky { order: -1; margin-bottom: 20px; position: static !important; }
        }

        .pkgdet-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .pkgdet-card h2 { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0 0 14px; display: flex; align-items: center; gap: 8px; }
        .pkgdet-card h2 i { color: #ff9800; }

        .pkgdet-chips { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .pkgdet-chip { background: #f0f7ff; border: 1px solid #cce5ff; color: #003580; padding: 8px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .pkgdet-chip i { color: #ff9800; }

        .pkgdet-desc { color: #334155; line-height: 1.7; font-size: 0.95rem; white-space: pre-line; }

        .pkgdet-line-row { display: flex; align-items: flex-start; gap: 10px; font-size: 0.88rem; line-height: 1.5; margin-bottom: 10px; color: #334155; }
        .pkgdet-line-row:last-child { margin-bottom: 0; }
        .pkgdet-line-row .dot { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.68rem; flex-shrink: 0; margin-top: 1px; background: #dcfce7; color: #15803d; }

        .visa-disclaimer { padding: 16px 18px; background: #fff5f5; border-left: 4px solid #ef4444; border-radius: 10px; color: #7f1d1d; font-size: 0.88rem; line-height: 1.6; }
        .visa-notes { padding: 16px 18px; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 10px; color: #78350f; font-size: 0.88rem; line-height: 1.6; }

        .pkgdet-partner-box { display: flex; align-items: center; gap: 14px; }
        .pkgdet-partner-box .icon { width: 48px; height: 48px; border-radius: 50%; background: #fff7ed; color: #ff9800; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .pkgdet-partner-box a { color: #003580; font-weight: 700; text-decoration: none; }
        .pkgdet-partner-box a:hover { text-decoration: underline; }

        .pkgdet-sticky { position: sticky; top: 20px; }
        .pkgdet-price-card { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; }
        .pkgdet-price-now { font-size: 1.9rem; font-weight: 900; color: #ff9800; }
        .pkgdet-price-per { color: #64748b; font-size: 0.85rem; }
        .pkgdet-book-btn { width: 100%; margin-top: 16px; background: linear-gradient(135deg, #ff9800, #f57c00); color: #fff; border: none; padding: 14px; border-radius: 12px; font-weight: 800; font-size: 1rem; cursor: pointer; box-shadow: 0 6px 14px rgba(255,152,0,0.3); }
        .pkgdet-book-btn:hover { filter: brightness(1.05); }
        .pkgdet-price-meta { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; font-size: 0.85rem; color: #475569; }
        .pkgdet-price-meta div { display: flex; justify-content: space-between; }

        .pkgdet-notfound { text-align: center; min-height: 56vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; }
        .pkgdet-notfound .pkgdet-notfound-icon { width: 88px; height: 88px; border-radius: 50%; background: #eef2ff; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        .pkgdet-notfound .pkgdet-notfound-icon i { font-size: 2.2rem; color: #003580; }
        .pkgdet-notfound h2 { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .pkgdet-notfound p { color: #64748b; margin-bottom: 28px; }
        .pkgdet-notfound-actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
        .pkgdet-notfound-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 26px; border-radius: 24px; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: transform 0.15s, box-shadow 0.15s; }
        .pkgdet-notfound-btn.primary { background: #003580; color: #fff; box-shadow: 0 4px 12px rgba(0,53,128,0.25); }
        .pkgdet-notfound-btn.primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,53,128,0.32); }

        /* ---- Booking modal (ported from visa.php so "Apply Now" opens in place) ---- */
        .booking-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
        .booking-modal.active { display: flex; }
        .booking-modal-content { background: white; border-radius: 20px; max-width: 600px; width: 90%; max-height: 85vh; overflow-y: auto; animation: modalSlideIn 0.3s ease; }
        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        .booking-modal-header { background: linear-gradient(135deg, #6c5ce7, #8a7cff); color: white; padding: 20px 25px; border-radius: 20px 20px 0 0; position: relative; }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 1.8rem; cursor: pointer; color: white; }
        .close-modal:hover { transform: rotate(90deg); color: #ff9800; }
        .booking-modal-header h2 { font-size: 1.3rem; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        .booking-modal-header p { font-size: 0.75rem; opacity: 0.8; }
        .booking-steps { display: flex; margin: 20px 0 25px; position: relative; padding: 0 10px; }
        .step { flex: 1; text-align: center; position: relative; }
        .step-number { width: 32px; height: 32px; background: #e0e0e0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 6px; font-weight: bold; font-size: 0.8rem; color: #666; }
        .step.active .step-number { background: #ff9800; color: white; }
        .step.completed .step-number { background: #28a745; color: white; }
        .step-label { font-size: 0.65rem; color: #666; }
        .step.active .step-label { color: #ff9800; font-weight: 600; }
        .step-line { position: absolute; top: 15px; left: 50%; width: 100%; height: 2px; background: #e0e0e0; z-index: 0; }
        .step:last-child .step-line { display: none; }
        .step-content { display: none; animation: fadeIn 0.3s ease; padding: 0 5px; }
        .step-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .booking-service-summary { background: #f8f9fa; border-radius: 12px; padding: 15px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; border: 1px solid #e0e0e0; }
        .service-icon-large { width: 50px; height: 50px; background: linear-gradient(135deg, #6c5ce7, #8a7cff); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .service-icon-large i { font-size: 1.4rem; color: white; }
        .service-info h3 { color: #003580; margin-bottom: 3px; font-size: 1rem; }
        .service-price { font-size: 1.2rem; font-weight: 700; color: #ff9800; }
        .service-duration { color: #666; font-size: 0.7rem; }
        .form-section { margin-bottom: 20px; }
        .form-section h4 { color: #003580; margin-bottom: 12px; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; border-left: 3px solid #ff9800; padding-left: 10px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.75rem; color: #333; }
        .form-group label .required { color: #ff4444; margin-left: 2px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.8rem; background: #fff; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #ff9800; box-shadow: 0 0 0 2px rgba(255, 152, 0, 0.1); }
        .form-group input.error, .form-group select.error { border-color: #ff4444; background: #fff5f5; }
        .error-message { background: #fff5f5; border-left: 3px solid #ff4444; padding: 10px 12px; margin-bottom: 15px; border-radius: 8px; font-size: 0.75rem; color: #ff4444; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .error-list { list-style: none; margin: 0; padding-left: 20px; }
        .review-details { background: #f8f9fa; border-radius: 12px; padding: 15px; margin-bottom: 20px; }
        .review-section { margin-bottom: 15px; }
        .review-section h4 { color: #003580; margin-bottom: 10px; font-size: 0.85rem; border-left: 3px solid #ff9800; padding-left: 10px; }
        .review-row { display: flex; padding: 6px 0; border-bottom: 1px solid #e9ecef; font-size: 0.8rem; }
        .review-label { width: 120px; font-weight: 600; color: #666; }
        .review-value { flex: 1; color: #333; }
        .action-buttons { display: flex; gap: 12px; justify-content: center; margin-top: 20px; }
        .btn-prev { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 40px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-next { background: linear-gradient(135deg, #6c5ce7, #8a7cff); color: white; border: none; padding: 10px 20px; border-radius: 40px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-next:hover { background: #ff9800; transform: translateY(-2px); }
        .submit-booking-btn { background: linear-gradient(135deg, #ff9800, #f57c00); color: white; border: none; padding: 12px 20px; border-radius: 40px; font-size: 0.9rem; font-weight: 600; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .file-upload { border: 2px dashed #e0e0e0; border-radius: 12px; padding: 15px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: #f8f9fa; margin-top: 15px; }
        .file-upload:hover { border-color: #ff9800; background: #fff9e6; }
        .file-upload i { font-size: 1.5rem; color: #ff9800; margin-bottom: 5px; }
        .file-upload p { font-size: 0.7rem; color: #666; margin: 0; }
        .file-upload .file-name { font-size: 0.65rem; color: #003580; margin-top: 5px; font-weight: 500; }
        .success-message { text-align: center; padding: 25px; }
        .success-message i { font-size: 2.5rem; color: #28a745; margin-bottom: 12px; }
        .booking-number { background: #e8f0fe; padding: 8px 16px; border-radius: 8px; font-size: 0.8rem; margin: 12px 0; display: inline-block; }
        .details-card { background: #f8f9fa; padding: 15px; border-radius: 12px; margin: 15px 0; text-align: left; font-size: 0.8rem; }
        .payment-status-pending { background: #fff3cd; color: #856404; padding: 10px; border-radius: 8px; text-align: center; font-size: 0.8rem; margin-top: 10px; }
        .btn-primary {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            width: auto;
            padding: 12px 26px;
            box-shadow: 0 4px 14px rgba(22,163,74,0.3);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(22,163,74,0.4); }
        .btn-secondary {
            background: #fff;
            color: #475569;
            border: 1.5px solid #cbd5e1;
            width: auto;
            padding: 12px 26px;
            box-shadow: none;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        .btn-secondary:hover { background: #f1f5f9; border-color: #94a3b8; color: #334155; }
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
                    <p>This visa listing may have been removed or the link is incorrect.</p>
                    <div class="pkgdet-notfound-actions">
                        <a href="visa.php" class="pkgdet-notfound-btn primary"><i class="fas fa-arrow-left"></i> Back to Visas</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="pkgdet-top-row">
                    <a href="../index.php" class="pkgdet-back-btn"><i class="fas fa-home"></i> Back to Home</a>
                    <div class="pkgdet-breadcrumb">
                        <a href="../index.php">Home</a> /
                        <a href="visa.php">Visa</a> /
                        <?= htmlspecialchars($visa['title']) ?>
                    </div>
                </div>

                <div class="visa-hero">
                    <div class="visa-hero-flag">
                        <?php if ($visa['icon_type'] === 'image' && $visa['icon_value']): ?>
                            <img src="<?= htmlspecialchars($visa['icon_value']) ?>" alt="<?= htmlspecialchars($visa['title']) ?> flag" onerror="this.style.display='none'">
                        <?php else: ?>
                            <i class="fas fa-passport" style="font-size:2rem; color:#003580;"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1><?= htmlspecialchars($visa['title']) ?> Visa</h1>
                        <div class="visa-hero-meta">
                            <span class="visa-status-pill <?= $isFree ? 'free' : 'required' ?>"><i class="fas <?= $isFree ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i> <?= $isFree ? 'Visa-Free' : 'Visa Required' ?></span>
                            <?php if ($visa['processing_time']): ?><span style="opacity:0.9; font-size:0.88rem;"><i class="fas fa-clock"></i> <?= htmlspecialchars($visa['processing_time']) ?></span><?php endif; ?>
                            <?php if ($visa['category']): ?><span style="opacity:0.9; font-size:0.88rem;"><i class="fas fa-globe-asia"></i> <?= htmlspecialchars($visa['category']) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="pkgdet-body">
                    <div class="pkgdet-main">
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-info-circle"></i> Overview</h2>
                            <p class="pkgdet-desc"><?= nl2br(htmlspecialchars($visa['description'] ?: 'Details about this visa application will be provided upon booking.')) ?></p>
                        </div>

                        <?php if ($requirements): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-clipboard-list"></i> Required Documents</h2>
                            <?php foreach ($requirements as $r): ?><div class="pkgdet-line-row"><span class="dot"><i class="fas fa-check"></i></span><span><?= htmlspecialchars($r) ?></span></div><?php endforeach; ?>
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

                        <?php if ($visa['partner_id']): ?>
                        <div class="pkgdet-card">
                            <h2><i class="fas fa-handshake"></i> Provided By</h2>
                            <div class="pkgdet-partner-box">
                                <div class="icon"><i class="fas fa-store"></i></div>
                                <div>
                                    <a href="../view-partner-profile.php?id=<?= intval($visa['partner_id']) ?>&from_type=visa&from_id=<?= urlencode($identifier) ?>"><?= htmlspecialchars($visa['partner_company'] ?: 'Partner Provider') ?></a>
                                    <p style="margin:2px 0 0; color:#64748b; font-size:0.82rem;">This visa service is offered by one of our trusted partners.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="pkgdet-sticky">
                        <div class="pkgdet-price-card">
                            <span class="pkgdet-price-now"><?= htmlspecialchars($visa['currency'] ?: '₱') ?><?= number_format($visa['price']) ?></span>
                            <div class="pkgdet-price-per">per applicant</div>
                            <button class="pkgdet-book-btn" onclick="applyForThisVisa()"><i class="fas fa-bolt"></i> Apply Now</button>
                            <div class="pkgdet-price-meta">
                                <div><span>Processing Time</span><strong><?= htmlspecialchars($visa['processing_time'] ?: 'Varies') ?></strong></div>
                                <div><span>Status</span><strong><?= $isFree ? 'Visa-Free' : 'Visa Required' ?></strong></div>
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
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="../career.php">Career</a></li>
                        <li><a href="../privacy.php">Data Privacy Policy</a></li>
                        <li><a href="../terms.php">Terms & Conditions</a></li>
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
            <p>© 2026 HeyDream Travel & Tours. All rights reserved.</p>
        </div>
    </footer>

    <script>
        window.currentUserEmail = '<?php $curr = $auth->getCurrentUser(); echo ($curr && isset($curr['email'])) ? $curr['email'] : ''; ?>';
        window.currentFullName = '<?php echo ($curr && isset($curr['full_name'])) ? htmlspecialchars($curr['full_name']) : ''; ?>';

        // "Apply Now" opens the exact same visa application wizard used on
        // visa.php, right here on the details page (skipping the gate popup,
        // since this page already shows the description/requirements/disclaimer).
        function applyForThisVisa() {
            requireLogin('showVisaBooking', <?= json_encode($visa['title'] ?? '') ?>, <?= json_encode(floatval($visa['price'] ?? 0)) ?>, <?= json_encode($visa['processing_time'] ?? '') ?>, <?= json_encode($requirements) ?>);
        }
    </script>
    <script>
        let currentVisa = null, visaBookingData = null, selectedPayment = null, visaDocumentFiles = [];
        function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); }
        function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => { const btn = event.target; const orig = btn.textContent; btn.textContent = 'Copied!'; btn.style.background = '#28a745'; btn.style.color = 'white'; setTimeout(() => { btn.textContent = orig; btn.style.background = '#e0e0e0'; btn.style.color = ''; }, 1500); }); }
        function handleFileUpload(event, paymentMethod) { const file = event.target.files[0]; if (file) { if (!file.type.match('image.*')) { alert('Please upload an image file.'); event.target.value = ''; return; } if (file.size > 5 * 1024 * 1024) { alert('File too large. Max 5MB.'); event.target.value = ''; return; } const reader = new FileReader(); reader.onload = e => { const p = document.getElementById(`preview-${paymentMethod}`); if (p) p.innerHTML = `<img src="${e.target.result}" alt="Payment Proof">`; }; reader.readAsDataURL(file); const fn = document.getElementById(`file-name-${paymentMethod}`); if (fn) fn.textContent = file.name; } }

        function updateVisaLiveTotal(val) {
            const num = parseInt(val) || 0;
            const price = currentVisa.price || 0;
            const display = document.getElementById('visa-live-total-val');
            if (display) {
                display.innerText = '₱' + (price * num).toLocaleString();
            }
        }

        function showVisaBooking(title, price, duration, requirements) {
            currentVisa = { title, price, duration, requirements: requirements || [] };
            visaDocumentFiles = [];
            let modal = document.getElementById('visaBookingModal');
            if (!modal) {
                modal = document.createElement('div'); modal.id = 'visaBookingModal'; modal.className = 'booking-modal';
                modal.innerHTML = `<div class="booking-modal-content"><div class="booking-modal-header"><span class="close-modal" onclick="closeVisaBookingModal()">&times;</span><h2><i class="fas fa-passport"></i> Visa Application</h2><p>Complete your application</p></div><div class="booking-modal-body"><div class="booking-steps"><div class="step active" id="step1Indicator"><div class="step-number">1</div><div class="step-label">Details</div><div class="step-line" style="width: 100%;"></div></div><div class="step" id="step2Indicator"><div class="step-number">2</div><div class="step-label">Documents</div><div class="step-line" style="width: 100%;"></div></div><div class="step" id="step3Indicator"><div class="step-number">3</div><div class="step-label">Review</div><div class="step-line" style="width: 100%;"></div></div><div class="step" id="step4Indicator"><div class="step-number">4</div><div class="step-label">Confirmation</div></div></div><div id="step1Content" class="step-content active"></div><div id="step2Content" class="step-content"></div><div id="step3Content" class="step-content"></div><div id="step4Content" class="step-content"></div></div></div>`;
                document.body.appendChild(modal);
                modal.addEventListener('click', e => { if (e.target === modal) closeVisaBookingModal(); });
            }
            renderVisaStep1(); modal.classList.add('active');
        }

        function renderVisaStep1() {
            document.getElementById('step1Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-passport"></i></div><div class="service-info"><h3>${currentVisa.title}</h3><p class="service-price">₱${formatNumber(currentVisa.price)}</p><p class="service-duration">${currentVisa.duration}</p></div></div>
                <form id="visaForm" onsubmit="return false;">
                    <div class="form-section"><h4><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></h4>
                        <div class="form-group"><input type="email" id="applicationEmail" value="${window.currentUserEmail || ''}" placeholder="Your email address" required></div>
                        <p style="font-size: 0.75rem; color: #666; margin-top: -10px;">The agents will contact you at this email address to confirm your application.</p>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-user"></i> Applicant Information</h4>
                        <div class="form-group"><label>Full Name <span class="required">*</span></label><input type="text" id="fullName" value="${window.currentFullName || ''}" placeholder="As in passport"></div>
                        <div class="form-group"><label>Phone <span class="required">*</span></label><input type="tel" id="phone" placeholder="+63 912 345 6789"></div>
                        <div class="form-row"><div class="form-group"><label>Date of Birth <span class="required">*</span></label><input type="date" id="dob" max="${new Date().toISOString().split('T')[0]}"></div>
                        <div class="form-group"><label>Passport Number <span class="required">*</span></label><input type="text" id="passportNum" placeholder="Passport number"></div></div>
                        <div class="form-row"><div class="form-group"><label>Passport Expiry <span class="required">*</span></label><input type="date" id="passportExpiry"></div>
                        <div class="form-group"><label>Address <span class="required">*</span></label><input type="text" id="address" placeholder="Complete address"></div></div>
                        <div class="form-group">
                            <label>Number of Applicants <span class="required">*</span></label>
                            <input type="number" id="applicants" value="1" min="1" max="50" oninput="updateVisaLiveTotal(this.value)">
                        </div>
                        <div id="visa-live-total-display" style="margin-top:15px; padding:15px; background:#f0f9ff; border-radius:12px; border:1px solid #bae6fd; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight:700; color:#1e3a8a; font-size:0.85rem;">Estimated Total Fee:</span>
                            <span id="visa-live-total-val" style="font-size:1.1rem; font-weight:900; color:#006ce4;">₱${formatNumber(currentVisa.price)}</span>
                        </div>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-map-marked-alt"></i> Travel Details</h4>
                        <div class="form-row"><div class="form-group"><label>Destination <span class="required">*</span></label><input type="text" id="destination" placeholder="Country name"></div>
                        <div class="form-group"><label>Embassy/Consulate</label><select id="embassy"><option value="manila">Manila</option><option value="cebu">Cebu</option><option value="davao">Davao</option></select></div></div>
                        <div class="form-row"><div class="form-group"><label>Target Travel Date (Optional)</label><input type="date" id="travelDate" min="${new Date().toISOString().split('T')[0]}"></div>
                        <div class="form-group"><label>Processing</label><select id="processing"><option value="regular">Regular (10-15 days)</option><option value="urgent">Urgent (3-5 days, +₱3,000)</option><option value="express">Express (24h, +₱5,000)</option></select></div></div>
                    </div>
                    <div class="form-section"><h4><i class="fas fa-file-alt"></i> Additional Information</h4>
                        <div class="form-group"><label>Occupation</label><input type="text" id="occupation" placeholder="Your job title"></div>
                        <div class="form-group"><label>Travel History</label><textarea id="travelHistory" rows="2" placeholder="Countries visited in last 5 years"></textarea></div>
                    </div>
                    <div id="step1Errors" class="error-message" style="display:none;"></div>
                    <div class="action-buttons"><button type="button" class="btn-next" onclick="validateAndGoToStep2()">Review Application <i class="fas fa-arrow-right"></i></button></div>
                </form>`;
        }

        function validateAndGoToStep2() {
            const errors = [], fn = v => document.getElementById(v)?.value.trim();
            const fullName = fn('fullName'), phone = fn('phone'), dob = fn('dob'), passportNum = fn('passportNum'), passportExpiry = fn('passportExpiry'), address = fn('address'), destination = fn('destination'), travelDate = fn('travelDate') || '', email = fn('applicationEmail'), applicants = fn('applicants');
            if (!email) errors.push('Email is required');
            if (!fullName) errors.push('Full Name is required');
            if (!phone) errors.push('Phone number is required');
            if (!dob) errors.push('Date of Birth is required');
            if (!passportNum) errors.push('Passport Number is required');
            if (!passportExpiry) errors.push('Passport Expiry Date is required');
            if (!address) errors.push('Address is required');
            if (!destination) errors.push('Destination is required');
            if (!applicants || parseInt(applicants) < 1) errors.push('Please enter at least 1 applicant');

            document.querySelectorAll('.form-group input,.form-group select').forEach(f => f.classList.remove('error'));
            ['applicationEmail', 'fullName', 'phone', 'dob', 'passportNum', 'passportExpiry', 'address', 'destination'].forEach(id => { if (!fn(id)) document.getElementById(id)?.classList.add('error'); });
            if (errors.length > 0) { const e = document.getElementById('step1Errors'); e.style.display = 'flex'; e.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul class="error-list">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`; e.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
            renderVisaStep2Documents();
        }

        function handleVisaDocSelect(event, index) {
            const file = event.target.files[0];
            const nameEl = document.getElementById(`visa-doc-name-${index}`);
            if (!file) { if (nameEl) nameEl.textContent = 'No file selected'; return; }
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please upload a PDF or image file (JPG, PNG, WEBP).');
                event.target.value = '';
                if (nameEl) nameEl.textContent = 'No file selected';
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                alert('File too large. Max 10MB.');
                event.target.value = '';
                if (nameEl) nameEl.textContent = 'No file selected';
                return;
            }
            if (nameEl) nameEl.textContent = file.name;
        }

        function renderVisaStep2Documents() {
            const reqs = (currentVisa.requirements && currentVisa.requirements.length > 0)
                ? currentVisa.requirements
                : ['Supporting Documents (optional)'];

            const uploadBlocks = reqs.map((label, index) => `
                <div class="form-group">
                    <label>${escapeHtml(label)}</label>
                    <div class="file-upload" onclick="document.getElementById('visaDoc${index}').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload (PDF or image)</p>
                        <p class="file-name" id="visa-doc-name-${index}">No file selected</p>
                        <input type="file" id="visaDoc${index}" accept="application/pdf,image/*" style="display:none" onchange="handleVisaDocSelect(event, ${index})">
                    </div>
                </div>
            `).join('');

            document.getElementById('step2Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-passport"></i></div><div class="service-info"><h3>${currentVisa.title}</h3><p class="service-price">${escapeHtml(currentVisa.duration || '')}</p></div></div>
                <div style="background: #e8f0fe; padding: 12px; border-radius: 10px; font-size: 0.8rem; color: #003580; margin-bottom: 15px;">
                    <i class="fas fa-info-circle"></i> Uploading now is optional and helps speed up review — you can also add or update these later from My Bookings.
                </div>
                <form onsubmit="return false;">
                    ${uploadBlocks}
                </form>
                <div class="action-buttons"><button type="button" class="btn-prev" onclick="goToVisaStep1()"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="btn-next" onclick="goToVisaStep3()">Continue to Review <i class="fas fa-arrow-right"></i></button></div>`;
            updateVisaSteps(2);
        }

        function goToVisaStep3() {
            const reqs = (currentVisa.requirements && currentVisa.requirements.length > 0)
                ? currentVisa.requirements
                : ['Supporting Documents (optional)'];
            visaDocumentFiles = [];
            reqs.forEach((label, index) => {
                const input = document.getElementById(`visaDoc${index}`);
                const file = input?.files?.[0];
                if (file) visaDocumentFiles.push({ file, label });
            });

            const gv = id => document.getElementById(id)?.value;
            const fullName = gv('fullName'), email = gv('applicationEmail'), phone = gv('phone'), dob = gv('dob'), passportNum = gv('passportNum'), passportExpiry = gv('passportExpiry'), address = gv('address'), destination = gv('destination'), embassy = gv('embassy'), travelDate = gv('travelDate'), processing = gv('processing'), occupation = gv('occupation'), travelHistory = gv('travelHistory'), applicants = parseInt(gv('applicants')) || 1;
            let addAmountPerPerson = 0, processingLabel = 'Regular';
            if (processing === 'urgent') { addAmountPerPerson = 3000; processingLabel = 'Urgent (3-5 days)'; } else if (processing === 'express') { addAmountPerPerson = 5000; processingLabel = 'Express (24h)'; }
            const total = (currentVisa.price + addAmountPerPerson) * applicants;
            visaBookingData = { fullName, email, phone, dob, passportNum, passportExpiry, address, destination, embassy, travelDate, processingLabel, occupation, travelHistory, total, applicants, addAmountPerPerson };
            document.getElementById('step3Content').innerHTML = `
                <div class="booking-service-summary"><div class="service-icon-large"><i class="fas fa-passport"></i></div><div class="service-info"><h3>${currentVisa.title}</h3><p class="service-price">₱${formatNumber(total)}</p></div></div>
                <div class="review-details"><div class="review-section"><h4>Applicant Info</h4><div class="review-row"><div class="review-label">Name:</div><div class="review-value">${escapeHtml(fullName)}</div></div><div class="review-row"><div class="review-label">Applicants:</div><div class="review-value">${applicants} Person${applicants > 1 ? 's' : ''}</div></div><div class="review-row"><div class="review-label">Passport:</div><div class="review-value">${escapeHtml(passportNum)} (Exp: ${new Date(passportExpiry).toLocaleDateString()})</div></div><div class="review-row"><div class="review-label">Email:</div><div class="review-value">${escapeHtml(email)}</div></div><div class="review-row"><div class="review-label">Phone:</div><div class="review-value">${escapeHtml(phone)}</div></div></div>
                <div class="review-section"><h4>Travel Details</h4><div class="review-row"><div class="review-label">Destination:</div><div class="review-value">${escapeHtml(destination)}</div></div><div class="review-row"><div class="review-label">Embassy:</div><div class="review-value">${embassy === 'manila' ? 'Manila' : embassy === 'cebu' ? 'Cebu' : 'Davao'}</div></div><div class="review-row"><div class="review-label">Travel Date:</div><div class="review-value">${travelDate ? new Date(travelDate).toLocaleDateString() : 'To be determined'}</div></div><div class="review-row"><div class="review-label">Processing:</div><div class="review-value">${processingLabel}</div></div></div>
                <div class="review-section"><h4>Documents</h4><div class="review-row"><div class="review-label">Uploaded:</div><div class="review-value">${visaDocumentFiles.length} of ${reqs.length} document${reqs.length > 1 ? 's' : ''}</div></div></div>
                <div class="review-section"><h4>Fee Summary</h4><div class="review-row"><div class="review-label">Visa Fee:</div><div class="review-value">₱${formatNumber(currentVisa.price)} x ${applicants}</div></div>${visaBookingData.addAmountPerPerson > 0 ? `<div class="review-row"><div class="review-label">Processing:</div><div class="review-value">+₱${formatNumber(visaBookingData.addAmountPerPerson)} x ${applicants}</div></div>` : ''}<div class="review-row"><div class="review-label">Total to Pay:</div><div class="review-value" style="color:#ff9800; font-weight:800;">₱${formatNumber(total)}</div></div></div>
                <div style="background: #e8f0fe; padding: 12px; border-radius: 10px; font-size: 0.75rem; color: #003580; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> After submitting, an agent will review your application and contact you for the payment process and remaining document collection.
                </div>
                </div>
                <div class="action-buttons"><button type="button" class="btn-prev" onclick="updateVisaSteps(2)"><i class="fas fa-arrow-left"></i> Back</button><button type="button" class="submit-booking-btn" onclick="submitVisaApplication()"><i class="fas fa-paper-plane"></i> Submit Application</button></div>`;
            updateVisaSteps(3);
        }

        function goToVisaStep1() { updateVisaSteps(1); setTimeout(() => { if (visaBookingData) { ['fullName', 'phone', 'passportNum', 'destination', 'travelDate', 'applicationEmail'].forEach(id => { const el = document.getElementById(id); if (el) el.value = visaBookingData[id] || ''; }); } }, 50); }

        function uploadVisaDocuments(bookingNumber) {
            const uploads = visaDocumentFiles.map(({ file }) => {
                const fd = new FormData();
                fd.append('action', 'upload');
                fd.append('booking_number', bookingNumber);
                fd.append('document', file);
                return fetch('../User Account/api/upload-api.php', { method: 'POST', body: fd })
                    .catch(err => { console.error('Document upload failed:', err); });
            });
            return Promise.all(uploads);
        }

        function submitVisaApplication() {
            if (!currentVisa || !visaBookingData) {
                alert('Your application session has expired or was reset. Please close this window and start over.');
                return;
            }

            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            try {
                fetch('../api/save-service-booking.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service_type: 'Visa Assistance', package_name: currentVisa.title,
                        package_duration: visaBookingData.processingLabel, price_per_person: currentVisa.price,
                        full_name: visaBookingData.fullName, email: visaBookingData.email, phone: visaBookingData.phone,
                        travelers: visaBookingData.applicants,
                        travel_date: visaBookingData.travelDate,
                        special_requests: `Applicants: ${visaBookingData.applicants}, Passport: ${visaBookingData.passportNum}, DOB: ${visaBookingData.dob}, Address: ${visaBookingData.address}, Destination: ${visaBookingData.destination}, Embassy: ${visaBookingData.embassy}, Occupation: ${visaBookingData.occupation}, Travel History: ${visaBookingData.travelHistory}`,
                        total_amount: visaBookingData.total, payment_method: 'Manual Agent Approval',
                        payment_reference: 'PENDING_AGENT'
                    })
                }).then(r => r.json()).then(async data => {
                    if (data.success) {
                        if (visaDocumentFiles.length > 0) {
                            await uploadVisaDocuments(data.booking_number);
                        }
                        document.getElementById('step4Content').innerHTML = `<div class="success-message"><i class="fas fa-clock" style="color:#ff9800;"></i><h2>📄 Application Received!</h2><p>Your application is now being reviewed by our agents.</p><div class="booking-number">Application Reference: ${data.booking_number}</div><div class="details-card"><h4>📋 Next Steps:</h4><p>1. Our expert agents will review your details manually.</p><p>2. You will receive an email at <strong>${visaBookingData.email}</strong> once approved.</p><p>3. Upon approval, we will guide you through the document collection and final payment.</p></div><div class="payment-status-pending" style="background: #e8f4fd; color: #004085;"><i class="fas fa-user-tie"></i> Please wait for the confirmation of our agents to approve your application.</div><div class="action-buttons"><button class="submit-booking-btn btn-primary" onclick="window.location.href='../User Account/profile.php?track=' + encodeURIComponent('${data.booking_number}')"><i class="fas fa-file-upload"></i> View My Application</button><button class="submit-booking-btn btn-secondary" onclick="closeVisaBookingModal();location.reload();"><i class="fas fa-check"></i> Understood</button></div></div>`;
                        updateVisaSteps(4);
                    } else { btn.disabled = false; btn.innerHTML = originalHtml; alert('Error: ' + data.message); }
                }).catch(() => { btn.disabled = false; btn.innerHTML = originalHtml; alert('Connection error. Please try again.'); });
            } catch (err) {
                btn.disabled = false; btn.innerHTML = originalHtml;
                console.error('Application submission error:', err);
                alert('Something went wrong while submitting your application: ' + err.message + '. Please try again.');
            }
        }

        function updateVisaSteps(step) { for (let i = 1; i <= 4; i++) { const ind = document.getElementById(`step${i}Indicator`), cont = document.getElementById(`step${i}Content`); if (i < step) { ind.classList.add('completed'); ind.classList.remove('active'); } else if (i === step) { ind.classList.add('active'); ind.classList.remove('completed'); } else { ind.classList.remove('active', 'completed'); } if (i === step) cont.classList.add('active'); else cont.classList.remove('active'); } }
        function closeVisaBookingModal() { const m = document.getElementById('visaBookingModal'); if (m) m.classList.remove('active'); visaBookingData = null; selectedPayment = null; visaDocumentFiles = []; }
    </script>
    <script src="../js/main.js?v=2"></script>
    <script src="../js/auth-menu.js?v=2"></script>
</body>

</html>
