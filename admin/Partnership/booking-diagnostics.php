<?php
/**
 * Booking Diagnostics - Partner Dashboard
 * Helps partners verify that their bookings are properly linked
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/partner-booking-tracker.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['partner_id']) || empty($_SESSION['partner_id'])) {
    header('Location: partner-login.php');
    exit;
}

$partnerId = (int)$_SESSION['partner_id'];
$stmt = $pdo->prepare("SELECT * FROM partner_applications WHERE id = ? LIMIT 1");
$stmt->execute([$partnerId]);
$partner = $stmt->fetch();

if (!$partner || $partner['status'] !== 'approved') {
    header('Location: partner-login.php');
    exit;
}

ensurePartnerBookingTracking($pdo);

// Diagnostic queries
$diagnostics = [];

// 1. Check partner_package_uploads for this partner
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM partner_package_uploads WHERE partner_id = ?");
$stmt->execute([$partnerId]);
$diagnostics['partner_packages'] = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Check bookings with direct partner_id assignment
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE partner_id = ? AND deleted_at IS NULL");
$stmt->execute([$partnerId]);
$diagnostics['direct_partner_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. Check bookings from partner_package_uploads
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM bookings 
    WHERE partner_source = 'partner_package_upload' 
    AND partner_id IS NULL 
    AND partner_package_id IS NOT NULL 
    AND deleted_at IS NULL
");
$stmt->execute();
$diagnostics['partner_upload_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC);

// 4. Get recent bookings for this partner (both types)
$stmt = $pdo->prepare("
    SELECT 
        b.id, 
        b.booking_number, 
        b.full_name, 
        b.destination_name, 
        b.package_name, 
        b.partner_package_name,
        b.travel_date, 
        b.booking_status, 
        b.payment_status,
        b.partner_id,
        b.partner_source,
        b.partner_package_id,
        b.partner_approved,
        b.created_at
    FROM bookings b
    WHERE (b.partner_id = ? OR (b.partner_id IS NULL AND b.partner_source = 'partner_package_upload' AND b.partner_package_id IS NOT NULL))
    AND b.deleted_at IS NULL
    ORDER BY b.created_at DESC
    LIMIT 20
");
$stmt->execute([$partnerId]);
$diagnostics['recent_bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Check partner_package_uploads with approved packages
$stmt = $pdo->prepare("
    SELECT 
        id, package_name, destination_name, duration, price, upload_status, created_at
    FROM partner_package_uploads 
    WHERE partner_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$partnerId]);
$diagnostics['packages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Diagnostics - Partner</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7fb;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #0f4c81; margin-bottom: 30px; }
        h2 { color: #0f4c81; margin-top: 30px; margin-bottom: 15px; font-size: 1.3rem; }
        .diagnostic-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #003580 0%, #1a4b8c 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card .number { font-size: 2rem; font-weight: 700; }
        .stat-card .label { font-size: 0.9rem; opacity: 0.9; margin-top: 8px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background: #0f4c81;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #ddd;
        }
        td {
            padding: 12px;
            border: 1px solid #ddd;
            font-size: 0.95rem;
        }
        tr:nth-child(even) { background: #f9f9f9; }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .status-pending { background: #fef2f2; color: #dc2626; }
        .status-confirmed { background: #ecfdf5; color: #059669; }
        .status-paid { background: #eff6ff; color: #0284c7; }
        .info-box {
            background: #e8f2ff;
            border-left: 4px solid #003580;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #003580;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .back-btn:hover { background: #0f4c81; }
    </style>
</head>
<body>
    <div class="container">
        <a href="partner-dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h1>📊 Booking Diagnostics</h1>
        
        <div class="info-box">
            <strong>ℹ️ This page shows diagnostics for your bookings.</strong> If your bookings are not appearing in the main Bookings tab, check the information below.
        </div>

        <h2>Summary Statistics</h2>
        <div class="diagnostic-box">
            <div class="stat-row">
                <div class="stat-card">
                    <div class="number"><?= $diagnostics['partner_packages']['count'] ?? 0 ?></div>
                    <div class="label">Packages Uploaded</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?= $diagnostics['direct_partner_bookings']['count'] ?? 0 ?></div>
                    <div class="label">Bookings (Direct)</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?= $diagnostics['partner_upload_bookings']['count'] ?? 0 ?></div>
                    <div class="label">Bookings (From Uploads)</div>
                </div>
            </div>
            <p style="color: #666; font-size: 0.9rem;">
                <strong>Total Bookings:</strong> <?= ($diagnostics['direct_partner_bookings']['count'] ?? 0) + ($diagnostics['partner_upload_bookings']['count'] ?? 0) ?>
            </p>
        </div>

        <h2>Your Uploaded Packages</h2>
        <div class="diagnostic-box">
            <?php if (empty($diagnostics['packages'])): ?>
                <p style="color: #666;">You haven't uploaded any packages yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Package Name</th>
                            <th>Destination</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diagnostics['packages'] as $pkg): ?>
                        <tr>
                            <td><?= htmlspecialchars($pkg['package_name']) ?></td>
                            <td><?= htmlspecialchars($pkg['destination_name']) ?></td>
                            <td><?= htmlspecialchars($pkg['duration']) ?></td>
                            <td><?= htmlspecialchars($pkg['price']) ?></td>
                            <td><span class="status-badge status-<?= strtolower($pkg['upload_status']) ?>"><?= ucfirst($pkg['upload_status']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($pkg['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <h2>Recent Bookings</h2>
        <div class="diagnostic-box">
            <?php if (empty($diagnostics['recent_bookings'])): ?>
                <p style="color: #666;">No bookings found yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking #</th>
                            <th>Customer</th>
                            <th>Package</th>
                            <th>Travel Date</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Approved</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diagnostics['recent_bookings'] as $booking): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($booking['booking_number']) ?></strong></td>
                            <td><?= htmlspecialchars($booking['full_name']) ?></td>
                            <td><?= htmlspecialchars($booking['partner_package_name'] ?: $booking['destination_name']) ?></td>
                            <td><?= date('M d, Y', strtotime($booking['travel_date'])) ?></td>
                            <td><span class="status-badge status-<?= strtolower($booking['booking_status']) ?>"><?= ucfirst($booking['booking_status']) ?></span></td>
                            <td><span class="status-badge status-<?= strtolower($booking['payment_status']) ?>"><?= ucfirst($booking['payment_status']) ?></span></td>
                            <td><?= $booking['partner_approved'] ? '✅ Yes' : '⏳ Pending' ?></td>
                            <td><?= date('M d, Y', strtotime($booking['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="info-box" style="background: #f0fdf4; border-left-color: #059669; margin-top: 30px;">
            <strong>✅ Troubleshooting:</strong>
            <ul style="margin-top: 10px; margin-left: 20px;">
                <li><strong>No bookings showing?</strong> Check if you've uploaded packages and if they have been approved by admin.</li>
                <li><strong>Bookings in "Pending"?</strong> You need to confirm/approve them from the Bookings tab to make them visible to admin.</li>
                <li><strong>Still having issues?</strong> Contact admin support with your partner ID: <strong><?= $partnerId ?></strong></li>
            </ul>
        </div>
    </div>
</body>
</html>
