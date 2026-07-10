<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

if ($pdo === null) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Error - HeyDream Travel</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Poppins', sans-serif; background: #f4f7f6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
            h1 { color: #ef4444; margin-bottom: 20px; font-size: 24px; }
            p { color: #64748b; line-height: 1.6; margin-bottom: 30px; }
            .btn { background: #003580; color: white; padding: 12px 30px; text-decoration: none; border-radius: 10px; font-weight: 600; display: inline-block; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>⚠️ Database Connection Failed</h1>
            <p>We are unable to connect to the database. If this is the online hosting environment, please check and update your credentials in the <strong>config/database.php</strong> file.</p>
            <a href="login.php" class="btn">Return to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/../api/partner-booking-tracker.php';
ensurePartnerBookingTracking($pdo);

// Personal correction for User Request
$pdo->prepare("UPDATE bookings SET travel_date = '2026-05-20' WHERE booking_number IN ('FO-89118920260512', 'FO-FA655F20260513')")->execute();

// Get statistics
$totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pendingBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending'")->fetchColumn();
$confirmedBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'confirmed'")->fetchColumn();

// Calculate Revenue (Foreign bookings as USD, others as Peso)
$totalRevenueUSD = $pdo->query("SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND (booking_number LIKE 'FO-%' OR booking_number LIKE 'FOR-%')")->fetchColumn() ?: 0;
$totalRevenuePeso = $pdo->query("SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND (booking_number NOT LIKE 'FO-%' AND booking_number NOT LIKE 'FOR-%')")->fetchColumn() ?: 0;

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalDestinations = $pdo->query("SELECT COUNT(*) FROM destinations")->fetchColumn();

// Daily booking counts for a 14-day window (for the bookings trend chart)
// Window starts at $bookingsChartStartDate and covers that date + the next 13 days.
// The start date can never be later than "today - 13 days", so the window never reaches into the future.
$bookingsChartMaxStart = date('Y-m-d', strtotime('-13 days'));
$bookingsChartStartDate = $_GET['start_date'] ?? $bookingsChartMaxStart;
$validStart = DateTime::createFromFormat('Y-m-d', $bookingsChartStartDate);
if (!$validStart || $validStart->format('Y-m-d') !== $bookingsChartStartDate || $bookingsChartStartDate > $bookingsChartMaxStart) {
    $bookingsChartStartDate = $bookingsChartMaxStart;
}
$bookingsChartEndDate = date('Y-m-d', strtotime($bookingsChartStartDate . ' +13 days'));

$dailyBookingsRaw = $pdo->prepare("SELECT DATE(created_at) as booking_date, COUNT(*) as total
    FROM bookings
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)");
$dailyBookingsRaw->execute([$bookingsChartStartDate, $bookingsChartEndDate]);
$dailyBookingsRaw = $dailyBookingsRaw->fetchAll(PDO::FETCH_KEY_PAIR);

$dailyBookingsLast14 = [];
for ($i = 0; $i < 14; $i++) {
    $d = date('Y-m-d', strtotime($bookingsChartStartDate . " +{$i} days"));
    $dailyBookingsLast14[$d] = (int) ($dailyBookingsRaw[$d] ?? 0);
}
$bookingsChartLabels = array_map(fn($d) => date('M j', strtotime($d)), array_keys($dailyBookingsLast14));
$bookingsChartData = array_values($dailyBookingsLast14);
$bookingsChartTotal = array_sum($bookingsChartData);
$bookingsChartAvg = $bookingsChartTotal > 0 ? round($bookingsChartTotal / 14, 1) : 0;
$bookingsChartPeak = $bookingsChartData ? max($bookingsChartData) : 0;
$bookingsChartPeakDateLabel = null;
if ($bookingsChartPeak > 0) {
    $peakIndex = array_search($bookingsChartPeak, $bookingsChartData, true);
    $peakDateKey = array_keys($dailyBookingsLast14)[$peakIndex];
    $bookingsChartPeakDateLabel = date('M j, Y', strtotime($peakDateKey));
}
$bookingsChartRangeLabel = date('M j', strtotime($bookingsChartStartDate)) . ' – ' . date('M j, Y', strtotime($bookingsChartEndDate));

// Year range for the "Month" view's year selector
$bookingsChartCurrentYear = (int) date('Y');
$bookingsChartMinYearRow = $pdo->query("SELECT MIN(YEAR(created_at)) as minY FROM bookings")->fetch();
$bookingsChartMinYear = ($bookingsChartMinYearRow && $bookingsChartMinYearRow['minY']) ? (int) $bookingsChartMinYearRow['minY'] : $bookingsChartCurrentYear;

// Get pending requests count for badge
$pendingRequestsCount = 0;
if ($_SESSION['admin_role'] === 'super_admin') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_registration_requests WHERE status = 'pending'");
    $stmt->execute();
    $pendingRequestsCount = $stmt->fetchColumn();
}

// Get pending partner package approvals count for badge
$pendingPartnerPackagesCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM partner_package_uploads WHERE upload_status = 'pending'");
    $pendingPartnerPackagesCount = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    $pendingPartnerPackagesCount = 0;
}

// Get pending inquiries count for marketing badge
$stmtInqCount = $pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_method = 'Inquiry Only'");
$pendingInquiriesCount = $stmtInqCount->fetchColumn();

// Get active bookings count for sidebar badge — mirrors JS isFullyCompleted() logic exactly:
// Excludes cancelled, and excludes truly finished (status=completed + paid + docs=1 + ready=1)
$stmtBookCount = $pdo->query("
    SELECT COUNT(*) FROM bookings 
    WHERE booking_status != 'cancelled'
    AND NOT (
        booking_status = 'completed'
        AND payment_status = 'paid'
        AND travel_documents = 1
        AND ready_for_travel = 1
    )
");
$pendingActualBookingsCount = $stmtBookCount->fetchColumn();

$stmtMessagesCount = $pdo->query("
    SELECT COUNT(DISTINCT conversation_id) FROM customer_messages 
    WHERE is_read = 0 AND sender_type = 'Customer'
");
$unreadMessagesCount = $stmtMessagesCount ? $stmtMessagesCount->fetchColumn() : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - HeyDream Travel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <style>
        :root {
            --primary-hsl: 230, 60%, 50%;
            --primary: hsl(var(--primary-hsl));
            --primary-dark: hsl(230, 60%, 40%);
            --accent-hsl: 35, 100%, 55%;
            --accent: hsl(var(--accent-hsl));
            --accent-dark: hsl(35, 100%, 45%);
            --bg: hsl(220, 33%, 98%);
            --sidebar-bg: #0f172a;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --glass: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.3);
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            overflow-x: hidden;
            line-height: 1.5;
        }

        /* --- Sidebar & Drawer --- */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background-color: var(--sidebar-bg);
            color: white;
            transition: var(--transition);
            z-index: 1000;
            overflow-y: auto;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sidebar-header {
            padding: 40px 24px;
            text-align: center;
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.05), transparent);
        }

        .sidebar-logo-wrapper {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.1);
            transition: var(--transition);
        }

        .sidebar-logo-wrapper:hover {
            transform: scale(1.05) rotate(-3deg);
        }

        .sidebar-logo-wrapper img {
            width: 70%;
            height: auto;
        }

        .sidebar-header h2 {
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .sidebar-header p {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 4px;
        }

        .sidebar-menu {
            padding: 20px 12px;
        }

        .menu-item {
            padding: 12px 16px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
            cursor: pointer;
            font-weight: 500;
            position: relative;
        }

        .menu-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(4px);
        }

        .menu-item.active {
            color: white;
            background: linear-gradient(90deg, rgba(var(--primary-hsl), 0.2), transparent);
            box-shadow: inset 4px 0 0 var(--primary);
        }

        .menu-item i {
            width: 24px;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .menu-item:hover i {
            color: var(--accent);
        }

        .badge-count {
            background: var(--accent);
            color: var(--sidebar-bg);
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-left: auto;
            box-shadow: 0 0 15px rgba(255, 152, 0, 0.3);
        }

        /* --- Main Content Area --- */
        .main-content {
            margin-left: 280px;
            padding: 24px 32px;
            min-height: 100vh;
            width: calc(100% - 280px);
            transition: var(--transition);
        }

        /* --- Top Bar (Glassmorphism) --- */
        .top-bar {
            background: var(--glass);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            box-shadow: var(--shadow);
            position: sticky;
            top: 20px;
            z-index: 900;
        }

        .top-bar .left-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menu-toggle {
            display: none;
            background: var(--primary);
            color: white;
            border: none;
            width: 44px;
            height: 44px;
            min-width: 44px;
            min-height: 44px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 20px;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(var(--primary-hsl), 0.3);
            padding: 0;
            line-height: 1;
        }

        .menu-toggle i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0;
        }

        .menu-toggle:hover {
            transform: scale(1.05);
            background: var(--primary-dark);
        }

        .top-bar h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1.1;
            transform: translateY(1px);
            /* Visually corrects text descender baseline */
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .admin-info span {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: var(--text-muted);
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .admin-info span i {
            color: var(--primary);
        }

        .logout-btn {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
            padding: 8px 18px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }

        .app-link-btn {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .app-link-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.25);
        }

        .app-dashboard-btn {
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(20, 184, 166, 0.2);
        }

        .app-dashboard-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(20, 184, 166, 0.25);
        }

        /* --- Statistics Grid --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid #f1f5f9;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-dark);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
            opacity: 0;
            transition: var(--transition);
        }

        .stat-card:hover::after {
            opacity: 1;
        }

        .stat-info h3 {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2px;
            font-weight: 600;
        }

        .stat-number {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -1px;
        }

        .stat-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--bg), #f1f5f9);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .stat-card:hover .stat-icon {
            background: var(--primary);
            color: white;
            transform: rotate(-10deg) scale(1.1);
        }

        /* --- Data Tables --- */
        .data-table {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid #f1f5f9;
            margin-top: 24px;
            overflow: hidden;
        }

        .table-header {
            padding: 24px 30px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, #fafafa, #ffffff);
        }

        .table-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .table-header h2 i {
            color: var(--accent);
        }

        /* --- Booking Trends chart card --- */
        .chart-card-body {
            padding: 24px 30px;
        }

        .chart-mode-toggle {
            display: inline-flex;
            gap: 4px;
            background: var(--bg);
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 18px;
        }

        .chart-mode-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 9px;
            padding: 9px 18px;
            cursor: pointer;
            transition: var(--transition);
        }

        .chart-mode-btn:hover {
            color: var(--text-main);
        }

        .chart-mode-btn.is-active {
            background: var(--card-bg);
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .chart-filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
            margin-bottom: 22px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .chart-filter-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .chart-filter-field label {
            font-size: 0.72rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chart-filter-hint {
            font-size: 0.72rem;
            color: var(--text-muted);
            font-weight: 500;
            font-style: italic;
        }

        .chart-filter-field input[type="date"],
        .chart-filter-field select {
            font-family: 'Poppins', sans-serif;
            font-size: 0.88rem;
            color: var(--text-main);
            background: var(--card-bg);
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 9px 12px;
            transition: var(--transition);
            cursor: pointer;
        }

        .chart-filter-field input[type="date"]:focus,
        .chart-filter-field select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px hsla(var(--primary-hsl), 0.15);
        }

        .chart-filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            border-radius: 10px;
            padding: 10px 18px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .chart-filter-apply {
            background: var(--primary);
            color: white;
        }

        .chart-filter-apply:hover {
            background: var(--primary-dark);
        }

        .chart-filter-apply:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .chart-filter-reset {
            background: var(--bg);
            color: var(--text-muted);
            border: 1.5px solid #e2e8f0;
        }

        .chart-filter-reset:hover {
            background: #f1f5f9;
            color: var(--text-main);
        }

        .chart-range-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
            background: hsla(var(--primary-hsl), 0.08);
            border-radius: 999px;
            padding: 8px 14px;
            margin-left: auto;
        }

        .chart-filter-error {
            display: none;
            width: 100%;
            font-size: 0.78rem;
            color: #ef4444;
            font-weight: 600;
        }

        .chart-summary-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .chart-summary-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            background: var(--bg);
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 12px 20px;
            min-width: 140px;
        }

        .chart-summary-label {
            font-size: 0.72rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chart-summary-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .chart-summary-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .chart-canvas-wrap {
            position: relative;
            height: 280px;
            width: 100%;
        }

        @media (max-width: 640px) {
            .chart-card-body {
                padding: 20px;
            }

            .chart-filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .chart-filter-field input[type="date"] {
                width: 100%;
            }

            .chart-range-label {
                margin-left: 0;
                justify-content: center;
            }

            .chart-summary-item {
                flex: 1 1 auto;
                min-width: 100px;
                padding: 10px 14px;
            }

            .chart-summary-value {
                font-size: 1.2rem;
            }

            .chart-canvas-wrap {
                height: 220px;
            }
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            scrollbar-width: thin;
        }

        /* All Active Bookings table: no visible scrollbar, click-and-drag to pan left/right instead */
        #bookingsTableScroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
            cursor: grab;
        }

        #bookingsTableScroll::-webkit-scrollbar {
            display: none;
        }

        #bookingsTableScroll.is-dragging {
            cursor: grabbing;
            user-select: none;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 800px;
        }

        th {
            background: #f8fafc;
            padding: 16px 24px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
        }

        td {
            padding: 18px 24px;
            font-size: 0.9rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-main);
            transition: var(--transition);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr {
            transition: var(--transition);
        }

        tr:hover td {
            background-color: #f8fafc;
            color: var(--primary-dark);
        }

        /* --- Status Badges --- */
        .status-badge {
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .status-confirmed {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .status-cancelled {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .status-incomplete {
            background: #fafaf9;
            color: #44403c;
            border: 1px dashed #d6d3d1;
        }

        .status-completed {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        /* --- Icons & Buttons --- */
        /* Deliberately NOT display:flex — this class is applied directly to <td>
           elements across the admin tables. display:flex on a table cell makes
           browsers insert an anonymous table-cell to compensate, which breaks
           border alignment and row-hover highlighting between rows. Plain
           inline-flex buttons + margin spacing keeps proper table-cell layout. */
        .action-buttons {
            text-align: center;
            white-space: nowrap;
        }

        .action-buttons > * {
            margin: 0 4px;
            vertical-align: middle;
        }

        .action-buttons > *:first-child {
            margin-left: 0;
        }

        .action-buttons > *:last-child {
            margin-right: 0;
        }

        .edit-btn,
        .delete-btn,
        .view-btn,
        .approve-btn,
        .reject-btn,
        .incomplete-btn {
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Adjust icon-only buttons to be square */
        .edit-btn:empty,
        .delete-btn:empty,
        .view-btn:empty,
        .approve-btn:empty,
        .reject-btn:empty,
        .incomplete-btn:empty,
        .edit-btn i:only-child,
        .delete-btn i:only-child,
        .view-btn i:only-child,
        .approve-btn i:only-child,
        .reject-btn i:only-child,
        .incomplete-btn i:only-child {
            padding: 0;
            width: 36px;
        }

        .edit-btn {
            background: #fef3c7;
            color: #d97706;
        }

        .edit-btn:hover {
            background: #d97706;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.2);
        }

        .delete-btn {
            background: #fee2e2;
            color: #dc2626;
        }

        .delete-btn:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }

        .view-btn {
            background: #e0f2fe;
            color: #0284c7;
        }

        .view-btn:hover {
            background: #0284c7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.2);
        }

        .approve-btn {
            background: #dcfce7;
            color: #16a34a;
        }

        .approve-btn:hover {
            background: #16a34a;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
        }

        .reject-btn {
            background: #fee2e2;
            color: #dc2626;
        }

        .reject-btn:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
        }

        .incomplete-btn {
            background: #fff7ed;
            color: #ea580c;
        }

        .incomplete-btn:hover {
            background: #ea580c;
            color: white;
            transform: translateY(-2px);
        }

        /* Header Specific Buttons */
        .table-header .edit-btn,
        .table-header .btn-primary {
            background: var(--primary);
            color: white;
            padding: 0 20px;
            height: 42px;
            font-size: 0.9rem;
            box-shadow: 0 4px 12px rgba(var(--primary-hsl), 0.2);
        }

        .table-header .edit-btn:hover {
            background: var(--primary-dark);
            box-shadow: 0 6px 16px rgba(var(--primary-hsl), 0.3);
        }

        /* --- Modals & Overlays --- */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        /* Ensure SweetAlert2 is always on top of modals */
        .swal2-container {
            z-index: 3000 !important;
        }


        .modal-content {
            background: white;
            border-radius: 24px;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideUp 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        .modal-header {
            padding: 24px 32px;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-header h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .close-modal {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: white;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-modal:hover {
            background: #f1f5f9;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 32px;
        }

        /* --- Mobile Overlays --- */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.4);
            z-index: 950;
            display: none;
            backdrop-filter: blur(4px);
            transition: var(--transition);
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* --- Animations --- */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* --- Responsive Queries --- */
        @media (max-width: 1200px) {
            .main-content {
                padding-top: 20px;
            }

            /* Users Table Enhancements */
            #users-table tbody tr {
                cursor: pointer;
                transition: background 0.2s;
            }

            #users-table tbody tr:hover {
                background-color: #f1f5f9 !important;
            }

            .history-card {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 12px;
                transition: transform 0.2s;
            }

            .history-card:hover {
                transform: translateY(-2px);
                border-color: var(--primary);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                left: -280px;
                box-shadow: 20px 0 50px rgba(0, 0, 0, 0.1);
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 16px;
            }

            .menu-toggle {
                display: flex;
            }

            .top-bar {
                margin-bottom: 24px;
                padding: 12px 16px;
            }

            .top-bar h1 {
                font-size: 1.25rem;
            }

            .admin-info>span {
                display: none;
                /* Hide auxiliary info on tablet, but keep notification spans */
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .table-header button {
                width: 100%;
            }

            /* Stack top-bar: title on top, buttons below */
            .top-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                padding: 14px 16px;
            }

            .top-bar .left-section {
                justify-content: flex-start;
            }

            .admin-info {
                gap: 10px;
                justify-content: center;
                width: 100%;
            }

            /* Hide username and role on mobile */
            .admin-info>span {
                display: none;
            }

            /* Shrink notification button to fit */
            .notification-wrapper {
                min-width: unset !important;
                padding: 9px 14px !important;
                flex: 1;
                justify-content: center !important;
            }

            .logout-btn {
                padding: 9px 14px;
                font-size: 0.85rem;
                justify-content: center;
            }
        }

        #notifDropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            width: 340px;
            margin-top: 12px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid #f1f5f9;
            z-index: 1000;
            overflow: hidden;
        }

        @media (max-width: 992px) {
            .admin-info>span {
                display: none;
            }

            #notifDropdown {
                position: fixed !important;
                top: 85px !important;
                left: 16px !important;
                right: 16px !important;
                width: auto !important;
                max-width: none !important;
                z-index: 10000 !important;
                border-radius: 20px !important;
            }
        }

        /* --- Custom Helper Classes --- */
        .user-group-row {
            background-color: #f8fafc !important;
            cursor: pointer;
        }

        .user-group-row:hover td {
            background-color: #f1f5f9 !important;
        }

        .user-group-row td {
            font-weight: 700;
            color: var(--primary);
            border-left: 4px solid var(--primary);
        }

        .toggle-icon {
            display: inline-block;
            transition: var(--transition);
            margin-right: 12px;
        }

        .user-group-row.open .toggle-icon {
            transform: rotate(90deg);
        }

        .user-booking-row {
            display: none;
        }

        .user-booking-row.visible {
            display: table-row;
        }

        .booking-count-badge {
            background: var(--sidebar-bg);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            margin-left: 12px;
        }

        /* Forms */
        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            font-family: inherit;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(var(--primary-hsl), 0.1);
        }

        .save-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(var(--primary-hsl), 0.2);
        }

        .save-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(var(--primary-hsl), 0.3);
        }

        /* Role select in table */
        .role-select {
            padding: 6px 12px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: white;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Universal SweetAlert Modern Styling */
        body .swal2-container .swal2-popup {
            border-radius: 24px !important;
            padding: 32px 24px 24px !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
            font-family: inherit !important;
        }

        body .swal2-container .swal2-title {
            color: #1e293b !important;
            font-size: 1.6rem !important;
            font-weight: 800 !important;
        }

        body .swal2-container .swal2-html-container {
            color: #475569 !important;
            font-size: 1.05rem !important;
            margin-bottom: 20px !important;
        }

        body .swal2-container .swal2-actions {
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 12px !important;
            margin-top: 24px !important;
            width: 100% !important;
        }

        body .swal2-container .swal2-styled {
            margin: 0 !important;
            padding: 14px 24px !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
            width: 100% !important;
            max-width: 100% !important;
            font-weight: 700 !important;
            border-radius: 12px !important;
        }

        body .swal2-container .swal2-confirm {
            order: 1 !important;
        }

        body .swal2-container .swal2-deny {
            order: 2 !important;
        }

        body .swal2-container .swal2-cancel,
        body .swal2-container .swal-custom-cancel {
            order: 3 !important;
            color: #1e293b !important;
            background: #e2e8f0 !important;
            border: none !important;
        }

        body .swal2-container .swal2-cancel:hover,
        body .swal2-container .swal-custom-cancel:hover {
            background: #cbd5e1 !important;
            transform: translateY(-4px) !important;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1) !important;
        }

        body .swal2-container .swal2-confirm:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2) !important;
        }

        .swal-textarea-custom {
            border: 1px solid #dc2626 !important;
            border-radius: 12px !important;
            padding: 15px !important;
            font-family: inherit !important;
            font-size: 14px !important;
            min-height: 120px !important;
            box-shadow: none !important;
            resize: none !important;
            color: #64748b !important;
        }

        .swal-textarea-custom:focus {
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1) !important;
            outline: none !important;
        }

        .no-border-icon {
            border: none !important;
            margin: 20px auto 10px !important;
        }

        @keyframes flashSuccessIcon {
            0% {
                background-color: #166534;
                box-shadow: 0 0 0 8px rgba(34, 197, 94, 0.4);
            }

            50% {
                background-color: #22c55e;
                box-shadow: 0 0 0 14px rgba(34, 197, 94, 0.2);
            }

            100% {
                background-color: #166534;
                box-shadow: 0 0 0 8px rgba(34, 197, 94, 0.4);
            }
        }

        .custom-success-icon {
            width: 64px;
            height: 64px;
            background-color: #166534;
            border-radius: 50%;
            position: relative;
            box-shadow: 0 0 0 10px rgba(34, 197, 94, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: flashSuccessIcon 1.5s infinite ease-in-out;
            margin: 20px auto 10px;
        }

        .custom-success-icon::after {
            content: "✓";
            width: 28px;
            height: 28px;
            background-color: white;
            color: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
        }

        @keyframes flashApproveIcon {
            0% {
                background-color: #166534;
                box-shadow: 0 0 0 8px rgba(34, 197, 94, 0.4);
            }

            50% {
                background-color: #22c55e;
                box-shadow: 0 0 0 14px rgba(34, 197, 94, 0.2);
            }

            100% {
                background-color: #166534;
                box-shadow: 0 0 0 8px rgba(34, 197, 94, 0.4);
            }
        }

        .custom-approve-icon {
            width: 64px;
            height: 64px;
            background-color: #166534;
            border-radius: 50%;
            position: relative;
            box-shadow: 0 0 0 10px rgba(34, 197, 94, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: flashApproveIcon 1.5s infinite ease-in-out;
            margin: 20px auto 10px;
        }

        .custom-approve-icon::after {
            content: "?";
            width: 28px;
            height: 28px;
            background-color: white;
            color: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
        }

        @keyframes flashPaymentIcon {
            0% {
                background-color: #1e3a8a;
                box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.4);
            }

            50% {
                background-color: #3b82f6;
                box-shadow: 0 0 0 14px rgba(59, 130, 246, 0.2);
            }

            100% {
                background-color: #1e3a8a;
                box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.4);
            }
        }

        .custom-payment-icon {
            width: 64px;
            height: 64px;
            background-color: #1e3a8a;
            border-radius: 50%;
            position: relative;
            box-shadow: 0 0 0 10px rgba(59, 130, 246, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: flashPaymentIcon 1.5s infinite ease-in-out;
            margin: 20px auto 10px;
        }

        .custom-payment-icon::after {
            content: "?";
            width: 28px;
            height: 28px;
            background-color: white;
            color: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
        }

        @keyframes flashWarningIcon {
            0% {
                background-color: #991b1b;
                box-shadow: 0 0 0 8px rgba(248, 113, 113, 0.8);
            }

            50% {
                background-color: #dc2626;
                box-shadow: 0 0 0 14px rgba(239, 68, 68, 0.3);
            }

            100% {
                background-color: #991b1b;
                box-shadow: 0 0 0 8px rgba(248, 113, 113, 0.8);
            }
        }

        .custom-declined-icon {
            width: 64px;
            height: 64px;
            background-color: #991b1b;
            border-radius: 50%;
            position: relative;
            box-shadow: 0 0 0 10px rgba(248, 113, 113, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: flashWarningIcon 1.5s infinite ease-in-out;
            margin: 20px auto 10px;
        }

        .custom-declined-icon::after {
            content: "!";
            width: 28px;
            height: 28px;
            background-color: white;
            color: #dc2626;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
        }

        /* Enhanced Loading Modal Styles */
        .swal2-popup.swal2-loading {
            border-radius: 20px !important;
            padding: 40px 20px !important;
            background: linear-gradient(to bottom, #ffffff, #f8fafc) !important;
            border: 1px solid #e2e8f0;
        }

        .swal2-popup.swal2-loading .swal2-title {
            color: var(--primary) !important;
            font-size: 1.8rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.5px;
            margin-top: 10px !important;
            margin-bottom: 20px !important;
            animation: pulseText 2s infinite ease-in-out;
        }

        .swal2-popup.swal2-loading .swal2-html-container {
            color: var(--text-muted) !important;
            font-size: 1.1rem !important;
            font-weight: 500;
        }

        .swal2-popup.swal2-loading .swal2-loader {
            border-color: var(--primary) rgba(var(--primary-hsl), 0.2) var(--accent) rgba(var(--primary-hsl), 0.2) !important;
            border-width: 5px !important;
            width: 4.5rem !important;
            height: 4.5rem !important;
            margin: 20px auto 30px !important;
            animation: swal2-anim-custom-spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite !important;
        }

        @keyframes swal2-anim-custom-spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes pulseText {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.6;
            }
        }

        @media (max-width: 480px) {
            body .swal2-container .swal2-popup {
                padding: 24px 20px 20px !important;
                border-radius: 20px !important;
                width: 90% !important;
            }

            body .swal2-container .swal2-title {
                font-size: 1.35rem !important;
            }

            body .swal2-container .swal2-html-container {
                font-size: 0.95rem !important;
                margin-bottom: 15px !important;
            }

            .swal-textarea-custom {
                min-height: 90px !important;
                padding: 12px !important;
                font-size: 13px !important;
            }

            body .swal2-container .swal2-actions {
                margin-top: 15px !important;
                gap: 10px !important;
            }

            body .swal2-container .swal2-styled {
                padding: 12px 20px !important;
                font-size: 0.95rem !important;
            }

            .custom-success-icon,
            .custom-approve-icon,
            .custom-payment-icon,
            .custom-declined-icon {
                width: 52px !important;
                height: 52px !important;
                margin: 15px auto 10px !important;
            }

            .custom-success-icon::after,
            .custom-approve-icon::after,
            .custom-payment-icon::after,
            .custom-declined-icon::after {
                width: 24px !important;
                height: 24px !important;
                font-size: 15px !important;
            }
        }
        }

        /* Custom Dropdown Styles */
        .custom-dropdown {
            position: relative;
            width: 100%;
            font-family: inherit;
        }

        .custom-dropdown-selected {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1.5px solid #a5b4fc;
            /* Purplish-blue border */
            background: #ffffff;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .custom-dropdown-selected:hover {
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.15);
        }

        .custom-dropdown-selected .chevron {
            width: 16px;
            height: 16px;
            color: #94a3b8;
            transition: transform 0.2s ease;
        }

        .custom-dropdown.open .chevron {
            transform: rotate(180deg);
        }

        .custom-dropdown-options {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            z-index: 50;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .custom-dropdown.open .custom-dropdown-options {
            display: block;
            animation: fadeInDown 0.2s ease;
        }

        .custom-option {
            padding: 12px 14px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1d4ed8;
            /* Blue text */
            cursor: pointer;
            transition: background 0.2s;
        }

        .custom-option:hover {
            background-color: #f1f5f9;
        }

        .custom-option.active {
            background-color: #6b7280;
            /* Dark grey */
            color: #ffffff;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo-wrapper">
                <img src="../images/Heydream Logo.png" alt="HeyDream Logo">
            </div>
            <h2>HeyDream</h2>
            <p>Admin Elite</p>
        </div>
        <div class="sidebar-menu">
            <!-- Dashboard -->
            <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'sales'): ?>
                <a class="menu-item active" data-page="dashboard">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            <?php endif; ?>

            <!-- Bookings -->
            <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'sales'): ?>
                <a class="menu-item" data-page="bookings">
                    <i class="fas fa-calendar-check"></i>
                    <span>Bookings</span>
                    <span class="badge-count sidebar-badge" id="sidebarBookingCount"
                        style="background: #ff4757; box-shadow: 0 0 15px rgba(255, 71, 87, 0.4); display: <?= $pendingActualBookingsCount > 0 ? 'inline-flex' : 'none' ?>;">
                        <?= $pendingActualBookingsCount ?>
                    </span>
                </a>
            <?php endif; ?>



            <!-- Content Manager -->
            <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'editor'): ?>
                <a class="menu-item" onclick="window.location.href='content-manager.php'">
                    <i class="fas fa-layer-group"></i>
                    <span>Content Manager</span>
                </a>
            <?php endif; ?>

            <!-- Users -->
            <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'sales'): ?>
                <a class="menu-item" data-page="users">
                    <i class="fas fa-user-friends"></i>
                    <span>Customers</span>
                </a>
            <?php endif; ?>

            <!-- Marketing & Inquiries -->
            <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'sales'): ?>
                <a class="menu-item" onclick="window.location.href='marketing.php'">
                    <i class="fas fa-bullhorn"></i>
                    <span>Marketing Hub</span>
                    <?php if ($pendingInquiriesCount > 0): ?>
                        <span class="badge-count sidebar-badge" id="sidebarMarketingCount"
                            style="background: #ff4757; box-shadow: 0 0 15px rgba(255, 71, 87, 0.4);"><?= $pendingInquiriesCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <!-- Voucher Management -->
            <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'sales'): ?>
                <a class="menu-item" onclick="window.location.href='marketing.php#vouchers'">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Vouchers</span>
                </a>
            <?php endif; ?>

            <!-- Admins -->
            <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === 'editor'): ?>
                <a class="menu-item" data-page="admins">
                    <i class="fas fa-user-shield"></i>
                    <span>Staff Control</span>
                </a>
            <?php endif; ?>

            <!-- Partnership (Dropdown) -->
            <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin'): ?>
                <div class="menu-dropdown-wrapper">
                    <a class="menu-item dropdown-toggle" style="cursor: pointer;" onclick="const content = document.getElementById('partnershipDropdown'); const icon = this.querySelector('.fa-chevron-down'); if(content.style.display === 'none') { content.style.display = 'flex'; icon.style.transform = 'rotate(180deg)'; } else { content.style.display = 'none'; icon.style.transform = 'none'; }">
                        <i class="fas fa-handshake"></i>
                        <span>Partnership</span>
                        <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem; transition: transform 0.3s;"></i>
                    </a>
                    <div class="menu-dropdown-content" id="partnershipDropdown" style="display: none; padding-left: 20px; flex-direction: column;">
                        <a class="menu-item" data-page="partner-applications">
                            <i class="fas fa-file-contract" style="font-size: 0.9rem;"></i>
                            <span style="font-size: 0.9rem;">Pending Partners</span>
                        </a>
                        <a class="menu-item" data-page="approved-partners">
                            <i class="fas fa-star" style="font-size: 0.9rem;"></i>
                            <span style="font-size: 0.9rem;">Approved Partners</span>
                            <span class="badge-count sidebar-badge" id="menuApprovedPartnersCount"
                                style="background: #10b981; box-shadow: 0 0 15px rgba(16, 185, 129, 0.4); transform: scale(0.9);">0</span>
                        </a>
                        <a class="menu-item" data-page="packages-approval">
                            <i class="fas fa-box-open" style="font-size: 0.9rem;"></i>
                            <span style="font-size: 0.9rem;">Packages Approval</span>
                            <?php if ($pendingPartnerPackagesCount > 0): ?>
                                <span class="badge-count sidebar-badge" id="menuPartnerPackageApprovalCount"
                                    style="background: #f59e0b; box-shadow: 0 0 15px rgba(245, 158, 11, 0.35); transform: scale(0.9);"><?= $pendingPartnerPackagesCount ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                <a class="menu-item" data-page="pending-requests">
                    <i class="fas fa-user-plus"></i>
                    <span>New Requests</span>
                    <span class="badge-count sidebar-badge" id="menuPendingCount"
                        style="background: #ff4757; box-shadow: 0 0 15px rgba(255, 71, 87, 0.4);"><?= $pendingRequestsCount ?></span>
                </a>
                <a href="heydream_admin/admin_dashboard.php" class="menu-item" style="margin-top: 2px; background: linear-gradient(90deg, rgba(20,184,166,0.16), rgba(20,184,166,0.06)); color: #d1fae5; border: 1px solid rgba(20,184,166,0.25);">
                    <i class="fas fa-desktop"></i>
                    <span>HeyDream App Dashboard</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <div class="top-bar">
            <div class="left-section">
                <button class="menu-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 id="page-title">Command Center</h1>
            </div>
            <div class="admin-info">
                <div class="notification-wrapper" id="notificationBtn"
                    style="margin-right: 20px; cursor: pointer; position: relative; background: white; padding: 10px 18px; border-radius: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 12px; border: 1px solid #f1f5f9; transition: all 0.2s; min-width: 140px;"
                    onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 20px rgba(0,0,0,0.08)'"
                    onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.05)'">
                    <i class="fas fa-bell notification-bell" style="font-size: 1.2rem; color: #1e293b;"></i>
                    <span
                        style="font-weight: 700; font-size: 0.95rem; color: #1e293b; letter-spacing: -0.2px;">Upcoming</span>
                    <span id="notifBadge" class="badge-count"
                        style="display: none; background: #ef4444; color: white; border: none; box-shadow: 0 0 15px rgba(239, 68, 68, 0.4); font-weight: 800; font-size: 0.75rem; padding: 3px 9px; border-radius: 20px; position: static;">0</span>
                </div>

                <div id="notifDropdown">
                    <div
                        style="padding: 18px; border-bottom: 1px solid #f1f5f9; background: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-weight: 800; font-size: 0.95rem; color: #1e293b;">Departures</span>
                            <div id="notifCount" style="font-size: 0.75rem; color: var(--primary); font-weight: 600;">0
                                upcoming
                            </div>
                        </div>
                        <button onclick="showAllUpcoming(event)"
                            style="background: var(--primary); color: white; border: none; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.2s;"
                            onmouseover="this.style.transform='translateY(-1px)'"
                            onmouseout="this.style.transform='none'">
                            Show All
                        </button>
                    </div>
                    <div id="notifList" style="max-height: 380px; overflow-y: auto;">
                        <!-- Notifs injected here -->
                        <div
                            style="padding: 30px 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                            <i class="fas fa-calendar-check"
                                style="display: block; font-size: 1.5rem; margin-bottom: 10px; opacity: 0.3;"></i>
                            No upcoming travels detected
                        </div>
                    </div>
                </div>
                <span><i class="fas fa-id-badge"></i> <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                <span><i class="fas fa-shield-halved"></i>
                    <?= ucfirst(str_replace('_', ' ', $_SESSION['admin_role'] ?? 'admin')) ?></span>
                <a href="logout.php" class="logout-btn"><i class="fas fa-power-off"></i> Logout</a>
            </div>
        </div>

        <!-- Dashboard Page -->
        <div id="dashboard-page">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Bookings</h3>
                        <div class="stat-number" id="stat-total-bookings"><?= $totalBookings ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Pending Bookings</h3>
                        <div class="stat-number" id="stat-pending-bookings"><?= $pendingBookings ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Confirmed Bookings</h3>
                        <div class="stat-number" id="stat-confirmed-bookings"><?= $confirmedBookings ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-card" style="overflow: visible; z-index: 10;">
                    <div class="stat-info" style="width: 100%;">
                        <h3>Total Revenue</h3>
                        <div class="stat-number" id="stat-total-revenue">
                            ₱<?= number_format($totalRevenuePeso ?? 0, 2) ?></div>
                        <div class="custom-dropdown" id="revenueCurrencyDropdown" style="margin-top: 15px;">
                            <div class="custom-dropdown-selected" onclick="toggleDropdownOptions(event)">
                                <span id="customDropdownText">₱ Philippine Peso</span>
                                <svg class="chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                            <div class="custom-dropdown-options" id="customDropdownOptions">
                                <div class="custom-option active" data-value="peso"
                                    onclick="selectRevenueCurrency('peso', '₱ Philippine Peso', this)">
                                    ₱ Philippine Peso
                                </div>
                                <div class="custom-option" data-value="usd"
                                    onclick="selectRevenueCurrency('usd', '$ US Dollar', this)">
                                    $ US Dollar
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Users</h3>
                        <div class="stat-number"><?= $totalUsers ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Destinations</h3>
                        <div class="stat-number"><?= $totalDestinations ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                </div>
            </div>

            <div class="data-table">
                <div class="table-header">
                    <h2><i class="fas fa-chart-column"></i> Booking Trends</h2>
                    <button class="edit-btn" onclick="document.querySelector('[data-page=\'bookings\']').click()"><i
                            class="fas fa-eye"></i> View All</button>
                </div>
                <div class="chart-card-body">
                    <div class="chart-mode-toggle" role="tablist">
                        <button type="button" class="chart-mode-btn is-active" id="bookingsModeDayBtn" data-mode="day">
                            <i class="fas fa-calendar-day"></i> Day
                        </button>
                        <button type="button" class="chart-mode-btn" id="bookingsModeMonthBtn" data-mode="month">
                            <i class="fas fa-calendar"></i> Month
                        </button>
                    </div>
                    <div class="chart-filter-row">
                        <div class="chart-filter-field" id="bookingsDayFilterField">
                            <label for="bookingsStartDate">Start Date</label>
                            <input type="date" id="bookingsStartDate"
                                value="<?= htmlspecialchars($bookingsChartStartDate) ?>"
                                max="<?= htmlspecialchars($bookingsChartMaxStart) ?>">
                            <span class="chart-filter-hint">Shows this date + the next 13 days (14 days total)</span>
                        </div>
                        <div class="chart-filter-field" id="bookingsYearFilterField" style="display:none;">
                            <label for="bookingsYearSelect">Year</label>
                            <select id="bookingsYearSelect">
                                <?php for ($y = $bookingsChartCurrentYear; $y >= $bookingsChartMinYear; $y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="button" class="chart-filter-btn chart-filter-apply" id="bookingsFilterApply">
                            <i class="fas fa-check"></i> <span id="bookingsFilterApplyText">Click to Apply</span>
                        </button>
                        <button type="button" class="chart-filter-btn chart-filter-reset" id="bookingsFilterReset">
                            <i class="fas fa-rotate-left"></i> Reset
                        </button>
                        <span class="chart-range-label" id="bookingsRangeLabel">
                            <i class="fas fa-calendar-days"></i> <span id="bookingsRangeLabelText"><?= htmlspecialchars($bookingsChartRangeLabel) ?></span>
                        </span>
                        <span class="chart-filter-error" id="bookingsFilterError"></span>
                    </div>
                    <div class="chart-summary-row">
                        <div class="chart-summary-item">
                            <span class="chart-summary-label">Total in Range</span>
                            <span class="chart-summary-value" id="bookingsTotalValue"><?= $bookingsChartTotal ?></span>
                        </div>
                        <div class="chart-summary-item">
                            <span class="chart-summary-label" id="bookingsAvgLabel">Daily Average</span>
                            <span class="chart-summary-value" id="bookingsAvgValue"><?= $bookingsChartAvg ?></span>
                        </div>
                        <div class="chart-summary-item">
                            <span class="chart-summary-label" id="bookingsPeakLabel">Busiest Day</span>
                            <span class="chart-summary-value" id="bookingsPeakValue"><?= $bookingsChartPeak ?></span>
                            <span class="chart-summary-sub" id="bookingsPeakDateValue"><?= $bookingsChartPeakDateLabel ? htmlspecialchars($bookingsChartPeakDateLabel) : 'No bookings yet' ?></span>
                        </div>
                    </div>
                    <div class="chart-canvas-wrap">
                        <canvas id="bookingsTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="data-table">
                <div class="table-header">
                    <h2><i class="fas fa-clock"></i> Recent Bookings</h2>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Phone</th>
                                <th>Customer</th>
                                <th>Service #</th>
                                <th>Destination</th>
                                <th>Travel Date</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->prepare("SELECT * FROM bookings ORDER BY created_at DESC LIMIT 5");
                            $stmt->execute();
                            $recentBookings = $stmt->fetchAll();
                            foreach ($recentBookings as $booking):
                                $statusClass = '';
                                switch ($booking['booking_status']) {
                                    case 'pending':
                                        $statusClass = 'status-pending';
                                        break;
                                    case 'confirmed':
                                        $statusClass = 'status-confirmed';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'status-cancelled';
                                        break;
                                    case 'completed':
                                        $statusClass = 'status-completed';
                                        break;
                                }

                                $contactNum = (!empty($booking['contact_number']) && $booking['contact_number'] !== 'N/A')
                                    ? $booking['contact_number']
                                    : (!empty($booking['phone']) ? $booking['phone'] : 'N/A');
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($contactNum) ?></strong></td>
                                    <td onclick="viewUserHistory('<?= $booking['email'] ?>', '<?= htmlspecialchars($booking['full_name'], ENT_QUOTES) ?>')"
                                        style="cursor: pointer; color: var(--primary); font-weight: 600;"
                                        title="Click to view travel history">
                                        <?= htmlspecialchars($booking['full_name']) ?>
                                    </td>
                                    <td><strong><?= $booking['booking_number'] ?></strong></td>
                                    <td><?= htmlspecialchars($booking['destination_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($booking['travel_date'])) ?></td>
                                    <td><?= ((strpos($booking['booking_number'], 'FO-') === 0 || strpos($booking['booking_number'], 'FOR-') === 0) ? '$' : '₱') . number_format($booking['total_amount'], 2) ?>
                                    </td>
                                    <td><span
                                            class="status-badge <?= $statusClass ?>"><?= ucfirst($booking['booking_status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>


        <!-- Bookings Page (Enhanced) -->
        <div id="bookings-page" style="display: none;">
            <!-- Filters -->
            <div class="filter-section">
                <span class="filter-label">FILTERS:</span>
                <button class="filter-btn" onclick="toggleFilterGroup('service')" id="serviceGroupBtn">
                    SERVICE AVAILED <i class="fas fa-chevron-down" style="font-size: 0.7rem;"></i>
                </button>

                <button class="filter-btn" onclick="toggleFilterGroup('completed')" id="completedGroupBtn">
                    COMPLETED <i class="fas fa-chevron-down" style="font-size: 0.7rem;"></i>
                </button>

                <button class="filter-btn" onclick="setFilter('trashed', 'ALL')" id="trashFilterBtn">
                    <i class="fas fa-trash"></i> TRASH <span class="badge-count floating-badge">0</span>
                </button>

                <button class="filter-btn" onclick="setFilter('type', 'upcoming')" id="upcomingFilterBtn">
                    <i class="fas fa-clock"></i> UPCOMING <span class="badge-count floating-badge">0</span>
                </button>

                <button class="filter-btn" onclick="toggleSort('travel_date'); openCalendar();" id="sortDateBtn">
                    <i class="fas fa-calendar-alt"></i> CALENDAR
                </button>
            </div>

            <!-- New Sub-Filter Row (Appears above the table header) -->
            <div id="sub-filter-row" class="sub-filter-row" style="display: none;">
                <!-- Content injected via JS based on active toggle -->
                <div id="service-sub-options" style="display: none; gap: 12px; align-items: center;">
                    <button class="sub-filter-btn" onclick="setFilter('service_type', 'ALL')" id="serviceAllBtn">
                        <i class="fas fa-list"></i> ALL <span class="badge-count floating-badge">0</span>
                    </button>
                    <button class="sub-filter-btn" onclick="setFilter('service_type', 'TOUR')" id="serviceTourBtn">
                        <i class="fas fa-map-marked-alt"></i> TOUR PACKAGES <span
                            class="badge-count floating-badge">0</span>
                    </button>
                    <button class="sub-filter-btn" onclick="setFilter('service_type', 'VISA')" id="serviceVisaBtn">
                        <i class="fas fa-passport"></i> VISA ASSISTANCE <span
                            class="badge-count floating-badge">0</span>
                    </button>
                    <button class="sub-filter-btn" onclick="setFilter('service_type', 'INQUIRIES')"
                        id="serviceInquiriesBtn">
                        <i class="fas fa-question-circle"></i> INQUIRIES <span
                            class="badge-count floating-badge">0</span>
                    </button>
                </div>

                <div id="completed-sub-options" style="display: none; gap: 12px; align-items: center;">
                    <button class="sub-filter-btn" onclick="setFilter('completed_type', 'ALL')" id="completedAllBtn">
                        <i class="fas fa-list-ul"></i> ALL <span class="badge-count floating-badge">0</span>
                    </button>
                    <button class="sub-filter-btn" onclick="setFilter('completed_type', 'TOUR')" id="completedTourBtn">
                        <i class="fas fa-map-marked-alt"></i> TOUR PACKAGES <span
                            class="badge-count floating-badge">0</span>
                    </button>
                    <button class="sub-filter-btn" onclick="setFilter('completed_type', 'VISA')" id="completedVisaBtn">
                        <i class="fas fa-passport"></i> VISA ASSISTANCE <span
                            class="badge-count floating-badge">0</span>
                    </button>
                    <button class="sub-filter-btn" onclick="setFilter('completed_type', 'INQUIRIES')"
                        id="completedInquiriesBtn">
                        <i class="fas fa-question-circle"></i> INQUIRIES <span
                            class="badge-count floating-badge">0</span>
                    </button>
                </div>
            </div>

            <!-- Bookings Table Container -->
            <div class="data-table">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="filterSearchInput" class="search-input"
                        placeholder="SEARCH BY NAME, BOOKING #, OR CONTACT #..." oninput="handleSearchInput()"
                        autocomplete="new-password" autocorrect="off" autocapitalize="off" spellcheck="false">
                    <button id="searchClearBtn" class="search-clear-btn" onclick="clearSearch()"><i
                            class="fas fa-times"></i></button>
                </div>
                <div class="table-header">
                        <h2 id="bookingsTableTitle"><i id="tableTitleIcon" class="fas fa-list-ul"
                            style="color: var(--accent);"></i> <span id="tableTitleText">All Bookings</span> <span
                            id="bookingCountDisplay">(0)</span></h2>
                        <p id="trashNotice" class="trash-note" style="display:none;margin-top:8px;color:#6b7280;font-size:0.95rem;">
                        Note: Bookings in Trash will be permanently deleted after 30 days.
                        </p>
                    <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin'): ?>
                        <button class="edit-btn" onclick="exportBookings()"
                            style="background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <i class="fas fa-file-export"></i>
                            <span>Export CSV</span>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive" id="bookingsTableScroll">
                    <table>
                        <thead>
                            <tr>
                                <th>PHONE</th>
                                <th>CUSTOMER</th>
                                <th id="headerBookingNumber">SERVICE #</th>
                                <th id="headerAppliedOn">APPLIED ON</th>
                                <th>PACKAGE</th>
                                <th id="headerTravelDate">TRAVEL DATE</th>
                                <th id="headerTravelDocs" style="text-align: center;">DOCS</th>
                                <th id="headerVisa" style="text-align: center;">VISA</th>
                                <th id="headerPayment" style="text-align: center;">PAYMENT</th>
                                <th id="headerStatus" style="text-align: center;">STATUS</th>
                                <th id="headerActions" style="text-align: center;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="bookingsBody">
                            <!-- Injected by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <style>
            .filter-section {
                background: var(--card-bg);
                border-radius: var(--radius);
                padding: 20px;
                margin-bottom: 16px;
                box-shadow: var(--shadow-sm);
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                align-items: center;
                border: 1px solid #f1f5f9;
            }

            .sub-filter-row {
                background: white;
                border-radius: 16px;
                padding: 12px 20px;
                margin-bottom: 20px;
                border: 1px solid #e2e8f0;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 12px;
                animation: slideDown 0.3s ease-out;
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .filter-label {
                font-weight: 700;
                font-size: 0.85rem;
                color: var(--text-muted);
                margin-right: 8px;
                text-transform: uppercase;
            }

            .filter-btn {
                position: relative;
                padding: 12px 22px;
                border-radius: 14px;
                border: 1px solid #e2e8f0;
                background: white;
                color: var(--text-main);
                font-weight: 700;
                font-size: 0.9rem;
                cursor: pointer;
                transition: var(--transition);
                display: flex;
                align-items: center;
                gap: 8px;
                font-family: inherit;
                text-transform: uppercase;
            }

            .filter-btn:hover {
                border-color: var(--primary);
                color: var(--primary);
                background: #f8fafc;
            }

            .filter-btn.active {
                background: var(--primary) !important;
                color: white !important;
                border-color: var(--primary);
                box-shadow: 0 4px 12px rgba(var(--primary-hsl), 0.3);
            }

            .search-container {
                display: flex;
                align-items: center;
                background: #ffffff;
                border: 2px solid #f1f5f9;
                border-radius: 16px;
                padding: 6px 18px;
                margin: 10px 0 24px 0;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
                position: relative;
                width: 100%;
            }

            .search-container:hover {
                border-color: #e2e8f0;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            }

            .search-container:focus-within {
                border-color: var(--primary);
                background: white;
                box-shadow: 0 0 0 4px rgba(var(--primary-hsl), 0.15), 0 8px 20px rgba(0, 0, 0, 0.06);
                outline: none;
                /* Ensure no browser default outline interferes */
            }

            .search-input {
                border: none;
                outline: none;
                padding: 10px 12px;
                width: 100%;
                font-size: 0.9rem;
                font-family: 'Inter', sans-serif;
                font-weight: 500;
                color: var(--text-main);
                background: transparent;
                letter-spacing: 0.02em;
            }

            .search-input::placeholder {
                color: #94a3b8;
                font-weight: 400;
                text-transform: none;
                letter-spacing: normal;
            }

            .search-icon {
                color: #64748b;
                font-size: 1.1rem;
                transition: color 0.3s ease;
            }

            .search-container:focus-within .search-icon {
                color: var(--primary);
            }

            .search-clear-btn {
                background: #f1f5f9;
                border: none;
                color: #64748b;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: none;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s ease;
                font-size: 0.8rem;
            }

            .search-clear-btn:hover {
                background: #e2e8f0;
                color: var(--text-main);
            }

            .sub-filter-btn {
                position: relative;
                padding: 10px 20px;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                background: #f8fafc;
                color: var(--text-main);
                font-weight: 700;
                font-size: 0.9rem;
                cursor: pointer;
                transition: var(--transition);
                display: flex;
                align-items: center;
                gap: 8px;
                text-transform: uppercase;
            }

            .sub-filter-btn:hover {
                background: #edf2f7;
                color: var(--primary);
            }

            .sub-filter-btn.active {
                background: var(--primary) !important;
                color: white !important;
                border-color: var(--primary);
            }

            @media (max-width: 768px) {
                .filter-section {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 10px;
                    padding: 15px;
                }

                .sub-filter-row {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 10px;
                    padding: 12px 10px;
                }

                .filter-label {
                    grid-column: span 2;
                    text-align: center;
                    margin-bottom: 5px;
                }

                .filter-btn {
                    padding: 12px 10px;
                    font-size: 0.8rem;
                    width: 100%;
                    justify-content: center;
                }

                .sub-filter-btn {
                    padding: 12px 10px;
                    font-size: 0.8rem;
                    width: 100%;
                    justify-content: center;
                }

                /* Make the last sub-filter button full width if there are 3 */
                .sub-filter-btn:last-child:nth-child(odd) {
                    grid-column: span 2;
                }

                .floating-badge {
                    width: 22px;
                    height: 22px;
                    min-width: 22px;
                    font-size: 0.75rem;
                    top: -8px;
                    right: -8px;
                    border-width: 2px;
                }
            }

            .bookings-badge {
                background: #0f172a;
                color: white;
                padding: 2px 8px;
                border-radius: 20px;
                font-size: 0.7rem;
                width: fit-content;
                display: block;
                margin-top: 4px;
            }

            #calendarModal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(15, 23, 42, 0.4);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                z-index: 9999;
                align-items: center;
                justify-content: center;
                padding: 20px;
                /* Force side margins on mobile */
            }

            #calendarModal.active {
                display: flex;
            }

            #calendarModal .modal-content {
                max-width: 800px;
                width: 95%;
                padding: 30px;
                border-radius: 24px;
                background: #ffffff;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.2);
                position: relative;
            }

            .calendar-container {
                user-select: none;
                width: 100%;
            }

            .calendar-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                background: #f8fafc;
                padding: 12px;
                border-radius: 12px;
            }

            .cal-btn {
                width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                cursor: pointer;
                color: #1e293b;
                transition: all 0.2s;
            }

            .cal-btn:hover {
                background: #f1f5f9;
                color: var(--primary);
            }

            .calendar-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 6px;
                width: 100%;
            }

            .calendar-day,
            .day-label {
                aspect-ratio: 1 / 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                font-size: 0.85rem;
                width: 100%;
            }

            .day-label {
                font-size: 0.7rem;
                font-weight: 800;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                padding-bottom: 5px;
            }

            .calendar-day {
                transition: all 0.2s;
                border-radius: 10px;
                font-weight: 600;
                cursor: pointer;
                background: #f8fafc;
                position: relative;
            }

            .calendar-day:hover {
                background: #e2e8f0;
                transform: scale(1.05);
            }

            .calendar-day.past {
                opacity: 0.4;
                background: #f1f5f9;
                cursor: default;
            }

            .calendar-day.today {
                background: #fff7ed !important;
                border: 2px solid #f59e0b !important;
                color: #b45309;
            }

            .calendar-day.highlight {
                background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
                color: white !important;
                font-weight: 700;
                box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            }

            .calendar-day.past.highlight {
                background: #cbd5e1 !important;
                color: #64748b !important;
                box-shadow: none;
                opacity: 0.6;
            }

            .scheduler-item:hover {
                border-color: #6366f1 !important;
                background: #f5f3ff !important;
                transform: translateX(5px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            }

            @media (max-width: 768px) {
                #calendarModal .modal-content {
                    padding: 20px;
                    max-height: 90vh;
                    overflow-y: auto !important;
                }

                .calendar-layout {
                    flex-direction: column !important;
                }

                .calendar-scheduler {
                    border-left: none !important;
                    padding-left: 0 !important;
                    margin-top: 20px;
                    border-top: 1px solid #e2e8f0;
                    padding-top: 20px;
                }
            }

            /* Notification Icon Animation */
            @keyframes bell-ring {
                0% {
                    transform: rotate(0);
                }

                10% {
                    transform: rotate(15deg);
                }

                20% {
                    transform: rotate(-15deg);
                }

                30% {
                    transform: rotate(10deg);
                }

                40% {
                    transform: rotate(-10deg);
                }

                50% {
                    transform: rotate(5deg);
                }

                60% {
                    transform: rotate(-5deg);
                }

                100% {
                    transform: rotate(0);
                }
            }

            .notification-bell.pulse {
                animation: bell-ring 1.5s ease-in-out infinite;
                color: #d97706 !important;
                text-shadow: 0 0 10px rgba(217, 119, 6, 0.3);
            }

            /* Badge Counts */
            .badge-count {
                display: flex;
                align-items: center;
                justify-content: center;
                background: #ff4757;
                color: white;
                font-weight: 800;
                font-size: 0.75rem;
                padding: 2px 10px;
                border-radius: 20px;
                min-width: 22px;
            }

            /* Sidebar specific adjustment to override global floating behavior */
            .sidebar-badge {
                display: flex !important;
                position: static !important;
                margin-left: auto;
                min-width: 24px !important;
                height: 24px !important;
                padding: 0 8px !important;
                font-size: 0.85rem !important;
                border-radius: 12px !important;
                box-shadow: none;
            }

            .floating-badge {
                position: absolute;
                top: -10px;
                right: -10px;
                padding: 0 6px;
                height: 26px;
                min-width: 26px;
                font-size: 0.85rem;
                box-shadow: 0 4px 12px rgba(255, 71, 87, 0.5);
                border: 2.5px solid white;
                z-index: 10;
            }

            .sub-filter-btn.active .badge-count,
            .filter-btn.active .badge-count {
                background: #ff4757;
                color: white;
                border-color: white;
            }

            .upcoming-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                color: white;
                font-size: 0.7rem;
                font-weight: 700;
                background: #ef4444;
                padding: 3px 8px;
                border-radius: 6px;
                margin-bottom: 4px;
                box-shadow: 0 0 10px rgba(239, 68, 68, 0.3);
                animation: flash 1.5s ease-in-out infinite;
            }

            @keyframes flash {

                0%,
                100% {
                    opacity: 1;
                    transform: scale(1);
                }

                50% {
                    opacity: 0.7;
                    transform: scale(0.95);
                    box-shadow: 0 0 15px rgba(239, 68, 68, 0.6);
                }
            }

            @keyframes float {

                0%,
                100% {
                    transform: translateY(0);
                }

                50% {
                    transform: translateY(-2px);
                }
            }

            @keyframes bounce {

                0%,
                20%,
                50%,
                80%,
                100% {
                    transform: translateY(0);
                }

                40% {
                    transform: translateY(-6px);
                }

                60% {
                    transform: translateY(-3px);
                }
            }

            .viewed-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                background: linear-gradient(135deg, #6366f1, #4f46e5);
                color: white !important;
                font-size: 0.6rem;
                font-weight: 800;
                padding: 3px 8px;
                border-radius: 50px;
                margin-bottom: 5px;
                vertical-align: middle;
                box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
                letter-spacing: 0.6px;
                text-transform: uppercase;
                border: 1px solid rgba(255, 255, 255, 0.1);
                animation: fadeIn 0.4s ease, bounce 2s infinite;
            }

            .highlighted-cell {
                background: linear-gradient(90deg, rgba(79, 70, 229, 0.04), transparent) !important;
                position: relative;
                color: #10b981 !important;
            }

            .highlighted-cell span:not(.viewed-badge),
            .highlighted-cell strong {
                color: #10b981 !important;
            }

            td.highlighted-cell:first-child::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                background: #10b981;
                border-radius: 0 4px 4px 0;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: scale(0.95);
                }

                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
        </style>

        <!-- Calendar Modal -->
        <div id="calendarModal">
            <div class="modal-content">
                <div class="modal-header" style="padding: 0 0 25px; background: transparent; border: none;">
                    <h3 style="font-weight: 800; font-size: 1.4rem; letter-spacing: -0.5px;">Customer Travel Calendar
                    </h3>
                    <span class="close-modal" onclick="closeCalendar()"><i class="fas fa-times"></i></span>
                </div>
                <div class="calendar-container">
                    <div class="calendar-header">
                        <button class="cal-btn" onclick="prevMonth()"><i class="fas fa-chevron-left"></i></button>
                        <h4 id="currentMonthYearView"
                            style="margin: 0; font-weight: 800; font-size: 1.25rem; color: #1e293b; min-width: 180px; text-align: center;">
                            April 2026</h4>
                        <button class="cal-btn" onclick="nextMonth()"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="calendar-layout" style="display: flex; gap: 20px; margin-top: 15px;">
                        <div class="calendar-grid-container" style="flex: 1.5;">
                            <div class="calendar-grid" id="calendarGrid"></div>
                        </div>
                        <div class="calendar-scheduler"
                            style="flex: 1; border-left: 1px solid #e2e8f0; padding-left: 20px; display: flex; flex-direction: column;">
                            <h5
                                style="margin: 0 0 15px; color: #64748b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">
                                <i class="fas fa-plane-departure" style="color: #6366f1; margin-right: 8px;"></i> Flight
                                Scheduler
                            </h5>
                            <div id="schedulerList"
                                style="flex: 1; overflow-y: auto; max-height: 350px; padding-right: 5px;">
                                <!-- Scheduler items will be populated here -->
                                <div style="text-align: center; color: #94a3b8; padding-top: 40px; font-size: 0.9rem;">
                                    No departures this month
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 25px; text-align: right;">
                        <button class="reject-btn" onclick="closeCalendar()"
                            style="padding: 10px 24px; border-radius: 10px; font-weight: 700; font-size: 0.9rem;">Close</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- Users Page -->
        <div id="users-page" style="display: none;">
            <div class="data-table">
                <div class="table-header">
                    <h2><i class="fas fa-users"></i> HeyDream Users</h2>
                </div>
                <div class="table-responsive">
                    <table id="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Provider</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
                            $stmt->execute();
                            $users = $stmt->fetchAll();
                            foreach ($users as $user):
                                ?>
                                <tr
                                    onclick="viewUserHistory('<?= $user['email'] ?>', '<?= htmlspecialchars($user['full_name'], ENT_QUOTES) ?>')">
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= $user['phone'] ?? 'N/A' ?></td>
                                    <td><?= ucfirst($user['provider']) ?></td>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td class="action-buttons" style="white-space:nowrap;">
                                        <?php $isBanned = isset($user['is_banned']) && $user['is_banned'] ? 1 : 0; ?>
                                        <button class="delete-btn" onclick="event.stopPropagation(); deleteUser(<?= (int)$user['id'] ?>, '<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                            <span>Delete</span>
                                        </button>
                                        <button class="<?= isset($user['is_banned']) && $user['is_banned'] ? 'approve-btn' : 'incomplete-btn' ?>" onclick="event.stopPropagation(); promptBanUser(<?= (int)$user['id'] ?>, '<?= htmlspecialchars($user['full_name'], ENT_QUOTES) ?>', <?= $isBanned ?>)">
                                            <i class="fas fa-<?= $isBanned ? 'lock-open' : 'ban' ?>"></i>
                                            <span><?= $isBanned ? 'Unban' : 'Ban' ?></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>



        <!-- Admins Page -->
        <div id="admins-page" style="display: none;">
            <div class="data-table">
                <div class="table-header">
                    <h2><i class="fas fa-user-shield"></i> Admin Users</h2>
                    <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                        <button class="edit-btn" onclick="window.location.href='register.php'">
                            <i class="fas fa-user-plus"></i>
                            <span>Add New Admin</span>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table id="admins-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Only show active admins (is_active = 1)
                            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE is_active = 1 ORDER BY created_at DESC");
                            $stmt->execute();
                            $adminUsers = $stmt->fetchAll();
                            foreach ($adminUsers as $adminUser):
                                ?>
                                <tr>
                                    <td><?= $adminUser['id'] ?></td>
                                    <td><?= htmlspecialchars($adminUser['username']) ?></td>
                                    <td><?= htmlspecialchars($adminUser['full_name']) ?></td>
                                    <td><?= htmlspecialchars($adminUser['email']) ?></td>
                                    <td>
                                        <?php if ($_SESSION['admin_role'] === 'super_admin' && $adminUser['id'] != $_SESSION['admin_id']): ?>



                                            <select class="role-select" data-id="<?= $adminUser['id'] ?>"
                                                data-current="<?= $adminUser['role'] ?>">
                                                <option value="super_admin" <?= $adminUser['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                                <option value="admin" <?= $adminUser['role'] === 'admin' ? 'selected' : '' ?>>Admin
                                                </option>
                                                <option value="editor" <?= $adminUser['role'] === 'editor' ? 'selected' : '' ?>>
                                                    Editor</option>
                                                <option value="sales" <?= $adminUser['role'] === 'sales' ? 'selected' : '' ?>>Sales
                                                </option>
                                            </select>
                                        <?php else: ?>
                                            <span
                                                class="status-badge <?= $adminUser['role'] === 'super_admin' ? 'status-confirmed' : ($adminUser['role'] === 'admin' ? 'status-pending' : ($adminUser['role'] === 'sales' ? 'status-completed' : 'status-pending')) ?>">
                                                <?= ucfirst(str_replace('_', ' ', $adminUser['role'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $adminUser['last_login'] ? date('M d, Y H:i', strtotime($adminUser['last_login'])) : 'Never' ?>
                                    </td>
                                    <td><span
                                            class="status-badge <?= $adminUser['is_active'] ? 'status-confirmed' : 'status-cancelled' ?>"><?= $adminUser['is_active'] ? 'Active' : 'Inactive' ?></span>
                                    </td>
                                    <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                                        <td class="action-buttons">
                                            <?php if ($adminUser['id'] != $_SESSION['admin_id'] && $adminUser['role'] !== 'super_admin'): ?>
                                                <button class="delete-btn"
                                                    onclick="deleteAdminUser(<?= $adminUser['id'] ?>, '<?= htmlspecialchars($adminUser['username']) ?>')"
                                                    title="Delete Admin">
                                                    <i class="fas fa-trash"></i>
                                                    <span>Delete</span>
                                                </button>
                                            <?php elseif ($adminUser['role'] === 'super_admin' && $adminUser['id'] != $_SESSION['admin_id']): ?>
                                                <span class="status-badge status-pending"><i class="fas fa-info-circle"></i> Cannot
                                                    delete Super Admin</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pending Requests Page -->
        <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin'): ?>
            <div id="partner-applications-page" style="display: none;">
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-handshake"></i> Partner Applications</h2>
                        <div class="inline-flex">
                            <span class="status-badge status-confirmed" id="partnerApplicationCount">0 applications</span>
                            <button class="edit-btn" onclick="loadPartnerApplications()">
                                <i class="fas fa-rotate"></i>
                                <span>Refresh</span>
                            </button>
                        </div>
                    </div>
                    <!-- Status Filter Tabs -->
                    <div style="display:flex; gap:10px; padding: 16px 24px; border-bottom: 1px solid #f1f5f9; background:#fafafa;">
                        <button id="pa-tab-pending" onclick="filterPartnerApplications('pending')" style="padding:8px 20px; border-radius:999px; border:none; background:#003580; color:white; font-weight:700; cursor:pointer; font-family:inherit; font-size:0.85rem;">Pending</button>
                        <button id="pa-tab-approved" onclick="filterPartnerApplications('approved')" style="padding:8px 20px; border-radius:999px; border:1px solid #cbd5e1; background:white; color:#475569; font-weight:700; cursor:pointer; font-family:inherit; font-size:0.85rem;">Approved</button>
                        <button id="pa-tab-rejected" onclick="filterPartnerApplications('rejected')" style="padding:8px 20px; border-radius:999px; border:1px solid #cbd5e1; background:white; color:#475569; font-weight:700; cursor:pointer; font-family:inherit; font-size:0.85rem;">Rejected</button>
                        <button id="pa-tab-all" onclick="filterPartnerApplications('all')" style="padding:8px 20px; border-radius:999px; border:1px solid #cbd5e1; background:white; color:#475569; font-weight:700; cursor:pointer; font-family:inherit; font-size:0.85rem;">All</button>
                    </div>
                    <div class="table-responsive">
                        <table id="partner-applications-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Applied</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="partner-applications-list">
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading partner applications...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <!-- List of Partners Page -->
        <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin'): ?>
            <div id="approved-partners-page" style="display: none;">
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-users"></i> List of Partners</h2>
                        <div class="inline-flex">
                            <span class="status-badge status-confirmed" id="approvedPartnersCount">0 partners</span>
                            <button class="edit-btn" onclick="loadApprovedPartners()">
                                <i class="fas fa-rotate"></i>
                                <span>Refresh Data</span>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="approved-partners-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Business Type</th>
                                    <th>Website</th>
                                    <th>Approved Date</th>
                                    <th>Ban Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="approved-partners-list">
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading partners...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin'): ?>
            <div id="packages-approval-page" style="display: none;">
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-box-open"></i> Packages Approval</h2>
                        <div class="inline-flex">
                            <span class="status-badge status-pending" id="partnerPackageApprovalCount">0 pending</span>
                            <button class="edit-btn" onclick="loadPartnerPackagesApproval()">
                                <i class="fas fa-rotate"></i>
                                <span>Refresh Data</span>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="packages-approval-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Package</th>
                                    <th>Destination</th>
                                    <th>Partner</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="partner-packages-approval-list">
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;"><i
                                            class="fas fa-spinner fa-spin"></i> Loading package submissions...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
            <div id="pending-requests-page" style="display: none;">
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-hourglass-half"></i> Pending Admin Registration Requests</h2>
                        <span class="status-badge status-pending" id="pendingCountBadge"><?= $pendingRequestsCount ?>
                            pending</span>
                    </div>
                    <div style="padding: 0 0 16px 0;">
                        <a href="heydream_admin/admin_dashboard.php" class="app-dashboard-btn" style="display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-desktop"></i> HeyDream App Dashboard
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table id="pending-requests-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Requested Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pending-requests-list">
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;"><i
                                            class="fas fa-spinner fa-spin"></i> Loading requests...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Travel Documents Page -->
        <div id="visa-page" style="display: none;">
            <div class="data-table">
                <div class="table-header">
                    <h2><i class="fas fa-folder-open"></i> Travel Documents Management</h2>
                    <div class="inline-flex">
                        <span class="status-badge status-pending" id="visaPendingCount">0 pending</span>
                        <button class="edit-btn" onclick="loadVisaBookings()">
                            <i class="fas fa-rotate"></i>
                            <span>Refresh Data</span>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="visa-table">
                        <thead>
                            <tr>
                                <th>Ref #</th>
                                <th>Applicant</th>
                                <th>Visa Type</th>
                                <th>Destination</th>
                                <th>Travel Date</th>
                                <th>Processing</th>
                                <th>Total Fee</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="visa-list">
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px;"><i
                                        class="fas fa-spinner fa-spin"></i> Loading visa applications...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <h3 id="modal-title">Edit Item</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="history-container" style="display: none;">
                    <div id="history-list"></div>
                </div>
                <form id="editForm">
                    <input type="hidden" id="edit-id">
                    <input type="hidden" id="edit-type">
                    <input type="hidden" id="edit-booking-number">
                    <div id="form-fields"></div>
                    <button type="submit" class="save-btn">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const adminRole = '<?= $_SESSION['admin_role'] ?? 'admin' ?>';

        function viewUserHistory(email, name) {
            resetModal();
            document.getElementById('modal-title').innerHTML = `<i class="fas fa-history"></i> Travel History (Active & Completed): ${name}`;
            document.getElementById('form-fields').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Fetching history...</div>';
            document.getElementById('editModal').classList.add('active');
            document.querySelector('#editForm button').style.display = 'none';
            document.getElementById('history-container').style.display = 'block';

            fetch(`admin-api.php?action=get_user_history&email=${encodeURIComponent(email)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const bookings = data.data;
                        if (bookings.length === 0) {
                            document.getElementById('form-fields').innerHTML = `
                                <div style="text-align: center; padding: 30px; color: #64748b;">
                                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                                    <p>No booking or travel history found for this customer.</p>
                                </div>`;
                        } else {
                            let html = `<div style="margin-bottom: 15px; padding: 0 5px; font-weight: 700; color: var(--text-muted); font-size: 0.9rem;">
                                            <i class="fas fa-plane-departure" style="color: var(--primary);"></i> Total Trips Found: ${bookings.length}
                                        </div>`;
                            html += '<div style="max-height: 400px; overflow-y: auto; padding-right: 5px;">';
                            bookings.forEach(b => {
                                const availedDate = b.created_at ? new Date(b.created_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
                                const date = b.travel_date ? new Date(b.travel_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'Flexible';
                                const statusClass = b.booking_status === 'confirmed' ? 'status-confirmed' : (b.booking_status === 'cancelled' ? 'status-cancelled' : 'status-pending');

                                html += `
                                    <div class="history-card">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                            <div>
                                                <span style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">${b.booking_number}</span>
                                                <h4 style="margin: 2px 0; color: #1e293b; font-size: 1rem;">${escapeHtml(b.destination_name)}</h4>
                                            </div>
                                            <span class="status-badge ${statusClass}">${b.booking_status.toUpperCase()}</span>
                                        </div>
                                        <div style="border-top: 4px solid #cbd5e1; margin: 10px 0;"></div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.85rem; color: #475569;">
                                            <div><i class="fas fa-calendar-alt" style="color: #6366f1; width: 16px;"></i> Travel Date: <strong>${date}</strong></div>
                                            <div><i class="fas fa-history" style="color: #64748b; width: 16px;"></i> Availed On: <strong>${availedDate}</strong></div>
                                            <div><i class="fas fa-users" style="color: #6366f1; width: 16px;"></i> Travelers: <strong>${b.number_of_travelers}</strong></div>
                                            <div><i class="fas fa-money-bill-wave" style="color: #10b981; width: 16px;"></i> Total: <strong>₱${parseFloat(b.total_amount).toLocaleString()}</strong></div>
                                            <div><i class="fas fa-tag" style="color: #f59e0b; width: 16px;"></i> Package: <strong>${escapeHtml(b.package_name)}</strong></div>
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                            document.getElementById('form-fields').innerHTML = html;
                        }
                    } else {
                        document.getElementById('form-fields').innerHTML = `<div style="color: red; text-align: center;">Error: ${data.message}</div>`;
                    }
                })
                .catch(err => {
                    document.getElementById('form-fields').innerHTML = `<div style="color: red; text-align: center;">Connection Error.</div>`;
                });
        }

        // Custom Swal Interceptor to systematically replace specific configured icons
        const originalSwalFire = Swal.fire;
        Swal.fire = function (...args) {
            if (args.length > 0) {
                if (typeof args[0] === 'object') {
                    if (args[0].icon === 'success') {
                        args[0].icon = undefined;
                        args[0].iconHtml = '<div class="custom-success-icon"></div>';
                        args[0].customClass = args[0].customClass || {};
                        args[0].customClass.icon = (args[0].customClass.icon ? args[0].customClass.icon + ' ' : '') + 'no-border-icon';
                    } else if (args[0].icon === 'warning') {
                        args[0].icon = undefined;
                        args[0].iconHtml = '<div class="custom-declined-icon"></div>';
                        args[0].customClass = args[0].customClass || {};
                        args[0].customClass.icon = (args[0].customClass.icon ? args[0].customClass.icon + ' ' : '') + 'no-border-icon';
                        args[0].reverseButtons = true;
                    }
                } else if (typeof args[0] === 'string') {
                    if (args[2] === 'success') {
                        return originalSwalFire({
                            title: args[0],
                            text: args[1],
                            icon: undefined,
                            iconHtml: '<div class="custom-success-icon"></div>',
                            customClass: { icon: 'no-border-icon' }
                        });
                    } else if (args[2] === 'warning') {
                        return originalSwalFire({
                            title: args[0],
                            text: args[1],
                            icon: undefined,
                            iconHtml: '<div class="custom-declined-icon"></div>',
                            customClass: { icon: 'no-border-icon' },
                            reverseButtons: true
                        });
                    }
                }
            }
            return originalSwalFire.apply(this, args);
        };

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')}"></i> ${message}`;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // Centralized Page Switcher
        window.switchPage = function (pageId) {
            if (!pageId) return;

            // 1. Update Sidebar Active State
            document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
            const activeItem = document.querySelector(`.menu-item[data-page="${pageId}"]`);
            if (activeItem) activeItem.classList.add('active');

            // 2. Hide all pages
            const pages = ['dashboard-page', 'bookings-page', 'users-page', 'admins-page', 'partner-applications-page', 'approved-partners-page', 'packages-approval-page', 'pending-requests-page', 'visa-page'];
            pages.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });

            // 3. Show selected page
            const selectedPage = document.getElementById(pageId + '-page');
            if (selectedPage) {
                selectedPage.style.display = 'block';
            }

            // 4. Update Header Title
            let title = pageId.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
            if (pageId === 'dashboard') title = 'Command Center';
            if (pageId === 'users') title = 'Customer Database';
            if (pageId === 'admins') title = 'Staff Control';
            if (pageId === 'partner-applications') title = 'Partner Applications';
            if (pageId === 'approved-partners') title = 'Approved Partners';
            if (pageId === 'packages-approval') title = 'Packages Approval';

            const titleEl = document.getElementById('page-title');
            if (titleEl) titleEl.innerText = title;

            // 5. Trigger Page-Specific Updates
            if (pageId === 'bookings') {
                if (typeof resetFilters === 'function') resetFilters();
            } else if (pageId === 'pending-requests') {
                if (typeof loadPendingRequests === 'function') loadPendingRequests();
            } else if (pageId === 'partner-applications') {
                if (typeof loadPartnerApplications === 'function') loadPartnerApplications();
            } else if (pageId === 'approved-partners') {
                if (typeof loadApprovedPartners === 'function') loadApprovedPartners();
            } else if (pageId === 'packages-approval') {
                if (typeof loadPartnerPackagesApproval === 'function') loadPartnerPackagesApproval();
            } else if (pageId === 'visa') {
                if (typeof loadVisaBookings === 'function') loadVisaBookings();
            }
        };

        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function (e) {
                const pageId = this.dataset.page;
                if (!pageId) return; // For Content Manager which is a link

                switchPage(pageId);

                // Mobile: Close sidebar after click
                if (window.innerWidth <= 992) {
                    const sidebar = document.querySelector('.sidebar');
                    const overlay = document.getElementById('sidebarOverlay');
                    if (sidebar) sidebar.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                }
            });
        });

        function loadVisaBookings() {
            const tbody = document.getElementById('visa-list');
            if (tbody) tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading visa applications...</td></tr>';

            fetch('admin-api.php?action=get_visa_bookings')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const bookings = data.data;
                        const pendingCount = bookings.filter(b => b.booking_status === 'pending').length;
                        if (document.getElementById('visaPendingCount')) {
                            document.getElementById('visaPendingCount').textContent = `${pendingCount} pending`;
                            document.getElementById('visaPendingCount').className = `status-badge ${pendingCount > 0 ? 'status-pending' : 'status-confirmed'}`;
                        }

                        if (tbody) {
                            if (bookings.length === 0) {
                                tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px;"><i class="fas fa-check-circle"></i> No visa applications found.</td></tr>';
                            } else {
                                tbody.innerHTML = bookings.map(b => {
                                    let statusClass = 'status-pending';
                                    let statusText = 'Pending Review';

                                    if (b.booking_status === 'confirmed') {
                                        statusClass = 'status-confirmed';
                                        statusText = 'Approved';
                                    } else if (b.booking_status === 'cancelled') {
                                        statusClass = 'status-cancelled';
                                        statusText = 'Declined';
                                    } else if (b.admin_notes && b.admin_notes.trim() !== '') {
                                        statusClass = 'status-incomplete';
                                        statusText = 'Incomplete';
                                    }

                                    const travelDate = b.travel_date ? new Date(b.travel_date).toLocaleDateString() : 'N/A';

                                    return `
                                        <tr>
                                            <td><strong>${b.booking_number}</strong></td>
                                            <td>
                                                <div style="font-weight:600;">${escapeHtml(b.full_name)}</div>
                                                <div style="font-size:0.75rem; color:#666;">${escapeHtml(b.email)}</div>
                                            </td>
                                            <td>${escapeHtml(b.package_name)}</td>
                                            <td>${escapeHtml(b.package_duration)}</td>
                                            <td>${travelDate}</td>
                                            <td>${escapeHtml(b.special_requests.split(',')[4]?.split(':')[1]?.trim() || 'N/A')}</td>
                                            <td><strong style="color:#ff9800;">₱${parseFloat(b.total_amount).toLocaleString()}</strong></td>
                                            <td>
                                                <button class="status-badge ${b.payment_status === 'paid' ? 'status-confirmed' : 'status-pending'}" onclick="toggleVisaPayment(${b.id}, '${b.payment_status}')" style="cursor: pointer; border: none; font-weight: bold; padding: 5px 12px; border-radius: 50px;">
                                                    ${b.payment_status === 'paid' ? 'PAID' : 'UNPAID'}
                                                </button>
                                            </td>
                                            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                                            <td class="action-buttons">
                                                <button class="view-btn" onclick="viewVisaDetails(${b.id})" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                    <span>Details</span>
                                                </button>
                                                ${b.booking_status !== 'confirmed' ? `
                                                    <button class="approve-btn" onclick="updateVisaStatus(${b.id}, '${escapeHtml(b.full_name)}', 'approved')" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                        <span>Approve</span>
                                                    </button>
                                                ` : ''}
                                                ${b.booking_status === 'pending' ? `
                                                    <button class="incomplete-btn" onclick="updateVisaStatus(${b.id}, '${escapeHtml(b.full_name)}', 'incomplete')" title="Mark as Incomplete">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                        <span>Incomplete</span>
                                                    </button>
                                                ` : ''}
                                                ${b.booking_status !== 'cancelled' ? `
                                                    <button class="reject-btn" onclick="updateVisaStatus(${b.id}, '${escapeHtml(b.full_name)}', 'declined')" title="Decline">
                                                        <i class="fas fa-times"></i>
                                                        <span>Decline</span>
                                                    </button>
                                                ` : ''}
                                            </td>
                                        </tr>
                                    `;
                                }).join('');
                            }
                        }
                    } else {
                        showNotification('❌ Error: ' + data.message, 'error');
                    }
                })
                .catch(e => {
                    console.error('Error loading visa bookings:', e);
                    showNotification('❌ Connection error loading visa apps', 'error');
                });
        }

        function updateVisaStatus(id, name, status) {
            let title = '';
            let htmlContent = '';
            let inputType = 'none';
            let confirmButtonText = 'Yes';
            let confirmButtonColor = '#3085d6';
            let iconType = 'warning';

            if (status === 'approved') {
                title = 'Approve Application';
                htmlContent = `Are you sure you want to APPROVE the visa application for <strong>${name}</strong>?`;
                confirmButtonText = 'Yes, Approve';
                confirmButtonColor = '#28a745';
                iconType = 'question';
            } else if (status === 'incomplete') {
                title = 'Mark as Incomplete';
                htmlContent = `Please state what items or information are missing for <strong>${name}</strong>:`;
                inputType = 'textarea';
                confirmButtonText = 'Mark Incomplete';
                confirmButtonColor = '#ff9800';
            } else if (status === 'declined') {
                title = 'Decline Application';
                htmlContent = `Are you sure you want to decline the visa application for <strong>${name}</strong>?<br><br>Please provide a reason for declining:`;
                inputType = 'textarea';
                confirmButtonText = 'Submit';
                confirmButtonColor = '#d32f2f';
                iconType = 'warning';
            }

            let swalConfig = {
                title: title,
                html: htmlContent,
                icon: iconType,
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: confirmButtonText,
                customClass: {
                    popup: 'modern-modal-popup',
                    cancelButton: 'swal-custom-cancel',
                    input: 'swal-textarea-custom'
                }
            };

            if (status === 'declined') {
                swalConfig.icon = undefined;
                swalConfig.iconHtml = '<div class="custom-declined-icon"></div>';
                swalConfig.customClass.icon = 'no-border-icon';
                swalConfig.reverseButtons = true;
            } else if (status === 'approved') {
                swalConfig.icon = undefined;
                swalConfig.iconHtml = '<div class="custom-approve-icon"></div>';
                swalConfig.customClass.icon = 'no-border-icon';
            }

            if (inputType === 'textarea') {
                swalConfig.input = 'textarea';
                swalConfig.inputPlaceholder = 'Type your reason here...';
                swalConfig.inputAttributes = { 'aria-label': 'Type your reason here' };
                swalConfig.inputValidator = (value) => {
                    if (!value || value.trim() === '') {
                        return 'A reason is required!';
                    }
                };
            }

            Swal.fire(swalConfig).then((result) => {
                if (result.isConfirmed) {
                    const reason = result.value || '';

                    const formData = new URLSearchParams();
                    formData.append('action', 'update_visa_status');
                    formData.append('id', id);
                    formData.append('status', status);
                    formData.append('reason', reason);

                    // Show loading
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Please wait while the update is made.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                loadVisaBookings();
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        })
                        .catch(() => {
                            Swal.fire('Error', 'Connection error during update', 'error');
                        });
                }
            });
        }

        function toggleVisaPayment(id, currentStatus) {
            const newStatus = currentStatus === 'paid' ? 'unpaid' : 'paid';
            Swal.fire({
                title: 'Update Payment Status',
                html: `You are about to modify the payment status of this application to <strong style="color: #000;">${newStatus.toUpperCase()}</strong>.<br><br>The record will be updated instantly and a new confirmation email will be sent. Do you wish to proceed?`,
                iconHtml: '<div class="custom-payment-icon"></div>',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Change Status',
                customClass: {
                    icon: 'no-border-icon',
                    popup: 'modern-modal-popup',
                    cancelButton: 'swal-custom-cancel',
                    confirmButton: 'swal2-confirm'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                    const formData = new URLSearchParams();
                    formData.append('action', 'update_visa_payment_status');
                    formData.append('id', id);
                    formData.append('payment_status', newStatus);

                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Changed!', 'Payment status updated' + (data.email_sent ? ' and confirmation email sent.' : '.'), 'success');
                                loadVisaBookings(); // reload table
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        })
                        .catch(() => {
                            Swal.fire('Error', 'Connection error update payment status', 'error');
                        });
                }
            });
        }

        function viewVisaDetails(id) {
            resetModal();
            fetch(`admin-api.php?action=get_visa_booking&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const b = data.data;
                        let statusClass = 'status-pending';
                        let statusText = 'PENDING REVIEW';

                        if (b.booking_status === 'confirmed') {
                            statusClass = 'status-confirmed';
                            statusText = 'APPROVED';
                        } else if (b.booking_status === 'cancelled') {
                            statusClass = 'status-cancelled';
                            statusText = 'DECLINED';
                        } else if (b.admin_notes && b.admin_notes.trim() !== '') {
                            statusClass = 'status-incomplete';
                            statusText = 'INCOMPLETE';
                        }

                        let feedbackHtml = '';
                        if (b.admin_notes && b.admin_notes.trim() !== '') {
                            feedbackHtml = `
                                <div style="margin-top: 15px; padding: 16px; background: #fffcf0; border-radius: 12px; border: 1px dashed #ff9800;">
                                    <h5 style="margin: 0 0 8px; color: #856404; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-comment-dots" style="color: #eab308; margin-right: 6px;"></i> Agent Feedback / Reasons</h5>
                                    <div style="font-size: 0.9rem; font-style: italic; color: #333;">"${escapeHtml(b.admin_notes)}"</div>
                                </div>
                            `;
                        }

                        const fields = `
                            <div class="confirmation-details" style="font-family: inherit;">
                                <h4 style="color: #1e293b; font-size: 1.15rem; font-weight: 800; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 20px;">
                                    <i class="fas fa-passport" style="color: #0284c7; margin-right: 8px;"></i> Visa Application Details
                                </h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 25px; line-height: 1.6; font-size: 0.95rem;">
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Ref Number</strong> <span style="color: #0f172a; font-weight: 600;">${b.booking_number}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Applied On</strong> <span style="color: #0f172a; font-weight: 600;">${new Date(b.created_at).toLocaleDateString()}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Applicant</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(b.full_name)}</span></div>
                                    <div style="min-width: 0;"><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Email</strong> <span style="color: #0f172a; font-weight: 600; word-break: break-all;">${escapeHtml(b.email)}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Phone</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(b.phone)}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Visa Type</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(b.package_name)}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Processing</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(b.package_duration)}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Travel Date</strong> <span style="color: #0f172a; font-weight: 600;">${new Date(b.travel_date).toLocaleDateString()}</span></div>
                                    <div style="grid-column: 1 / -1; background: #f8fafc; padding: 12px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e2e8f0; margin-top: 5px;">
                                        <strong style="color: #1e293b; font-size: 1rem;">Total Fee</strong> 
                                        <span style="color: #0284c7; font-weight: 800; font-size: 1.1rem;">₱${parseFloat(b.total_amount).toLocaleString()}</span>
                                    </div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Status</strong> <span class="status-badge ${statusClass}" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">${statusText}</span></div>
                                </div>

                                ${feedbackHtml}

                                <div style="margin-top: 20px; padding: 16px; background: #eff6ff; border-radius: 12px; border: 1px solid #bfdbfe;">
                                    <h5 style="margin: 0 0 10px; color: #1e3a8a; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-id-card" style="color: #3b82f6; margin-right: 6px;"></i> Passport & Applicant Info</h5>
                                    <div style="font-size: 0.9rem; line-height: 1.6; color: #1e40af;">
                                        ${b.special_requests.split(',').map(item => `<div style="display: flex; align-items: flex-start; margin-bottom: 8px;"><span style="color: #60a5fa; margin-right: 10px; font-weight: bold;">•</span> <span style="flex: 1;">${escapeHtml(item.trim())}</span></div>`).join('')}
                                    </div>
                                </div>
                            </div>
                        `;
                        document.getElementById('modal-title').innerText = 'Visa Application details';
                        document.getElementById('form-fields').innerHTML = fields;
                        document.getElementById('editModal').classList.add('active');
                        document.querySelector('#editForm button').style.display = 'none';
                    } else {
                        showNotification('❌ ' + data.message, 'error');
                    }
                });
        }




        async function parseJsonResponse(response) {
            const text = await response.text();
            if (!text) {
                return { success: false, message: 'Empty response from server.' };
            }

            try {
                return JSON.parse(text);
            } catch (error) {
                return { success: false, message: 'Invalid server response.', raw: text };
            }
        }

        function loadPartnerPackagesApproval() {
            fetch('admin-api.php?action=get_partner_packages_for_approval')
                .then(async response => {
                    const data = await parseJsonResponse(response);
                    if (!data.success) {
                        console.error('Failed to load partner package approvals', data.message);
                        return;
                    }

                    const packages = data.data || [];
                    const pendingPackages = packages.filter(pkg => (pkg.upload_status || 'pending').toLowerCase() === 'pending');
                    const tbody = document.getElementById('partner-packages-approval-list');
                    const countBadge = document.getElementById('partnerPackageApprovalCount');
                    const menuBadge = document.getElementById('menuPartnerPackageApprovalCount');

                    if (countBadge) countBadge.textContent = `${pendingPackages.length} pending`;
                    if (menuBadge) menuBadge.textContent = pendingPackages.length;

                    if (tbody) {
                        if (pendingPackages.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px;"><i class="fas fa-check-circle"></i> No pending packages to review.</td></tr>';
                        } else {
                            tbody.innerHTML = pendingPackages.map(pkg => {
                                const packageName = escapeHtml(pkg.package_name || 'Untitled Package');
                                const destination = escapeHtml(pkg.destination_name || 'Unnamed Destination');
                                const partner = escapeHtml(pkg.partner_company || pkg.uploaded_by_name || 'Partner');
                                const submittedAt = pkg.created_at ? new Date(pkg.created_at).toLocaleString() : 'N/A';
                                const price = pkg.price !== null && pkg.price !== '' ? `₱${parseFloat(pkg.price).toLocaleString()}` : 'N/A';

                                return `
                                    <tr data-id="${pkg.id}">
                                        <td>${pkg.id}</td>
                                        <td>
                                            <strong>${packageName}</strong><br>
                                            <span style="font-size:0.8rem;color:#64748b;">${price}</span>
                                        </td>
                                        <td>${destination}</td>
                                        <td>${partner}</td>
                                        <td><span class="status-badge status-pending">PENDING</span></td>
                                        <td>${submittedAt}</td>
                                        <td class="action-buttons">
                                            <button class="approve-btn" onclick="approvePartnerPackageSubmission(${pkg.id}, '${escapeHtml(pkg.package_name || 'package')}')"><i class="fas fa-check"></i> Approve</button>
                                            <button class="reject-btn" onclick="rejectPartnerPackageSubmission(${pkg.id}, '${escapeHtml(pkg.package_name || 'package')}')"><i class="fas fa-times"></i> Reject</button>
                                        </td>
                                    </tr>
                                `;
                            }).join('');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading package approvals:', error);
                });
        }

        function approvePartnerPackageSubmission(id, packageName) {
            Swal.fire({
                title: 'Approve Package',
                html: `Approve <strong>${packageName}</strong> for public listing?`,
                iconHtml: '<div class="custom-approve-icon"></div>',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Approve',
                customClass: { icon: 'no-border-icon', popup: 'modern-modal-popup' }
            }).then(result => {
                if (result.isConfirmed) {
                    const formData = new URLSearchParams();
                    formData.append('action', 'approve_partner_package_submission');
                    formData.append('package_id', id);

                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    })
                        .then(async response => {
                            const data = await parseJsonResponse(response);
                            if (data.success) {
                                Swal.fire('Approved', data.message, 'success');
                                loadPartnerPackagesApproval();
                            } else {
                                Swal.fire('Error', data.message || 'Unable to approve package', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Approve package error:', error);
                            Swal.fire('Error', 'Unable to approve package right now. Please refresh and try again.', 'error');
                        });
                }
            });
        }

        function rejectPartnerPackageSubmission(id, packageName) {
            Swal.fire({
                title: 'Reject Package',
                html: `Provide a reason for rejecting <strong>${packageName}</strong>:`,
                input: 'textarea',
                inputPlaceholder: 'Enter rejection reason...',
                showCancelButton: true,
                confirmButtonText: 'Reject Package',
                cancelButtonText: 'Cancel',
                inputValidator: value => {
                    if (!value || !value.trim()) return 'A rejection reason is required.';
                }
            }).then(result => {
                if (result.isConfirmed) {
                    const formData = new URLSearchParams();
                    formData.append('action', 'reject_partner_package_submission');
                    formData.append('package_id', id);
                    formData.append('reason', result.value || '');

                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    })
                        .then(async response => {
                            const data = await parseJsonResponse(response);
                            if (data.success) {
                                Swal.fire('Rejected', data.message, 'success');
                                loadPartnerPackagesApproval();
                            } else {
                                Swal.fire('Error', data.message || 'Unable to reject package', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Reject package error:', error);
                            Swal.fire('Error', 'Unable to reject package right now. Please refresh and try again.', 'error');
                        });
                }
            });
        }

        function loadPendingRequests() {
            if (adminRole !== 'super_admin') return;

            fetch('admin-api.php?action=get_pending_requests')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const requests = data.data;
                        const tbody = document.getElementById('pending-requests-list');
                        const pendingCountBadge = document.getElementById('pendingCountBadge');
                        const menuPendingCount = document.getElementById('menuPendingCount');

                        if (pendingCountBadge) pendingCountBadge.textContent = requests.length + ' pending';
                        if (menuPendingCount) menuPendingCount.textContent = requests.length;

                        if (tbody) {
                            if (requests.length === 0) {
                                tbody.innerHTML = '<td colspan="7" style="text-align: center; padding: 40px;"><i class="fas fa-check-circle"></i> No pending requests</td> </tr>';
                            } else {
                                tbody.innerHTML = requests.map(req => {
                                    // Get role display text and badge class
                                    let roleDisplay = '';
                                    let roleClass = '';

                                    if (req.role === 'admin') {
                                        roleDisplay = 'ADMIN';
                                        roleClass = 'status-confirmed';
                                    } else if (req.role === 'editor') {
                                        roleDisplay = 'EDITOR';
                                        roleClass = 'status-pending';
                                    } else if (req.role === 'sales') {
                                        roleDisplay = 'SALES';
                                        roleClass = 'status-completed';
                                    } else {
                                        roleDisplay = req.role ? req.role.toUpperCase() : 'N/A';
                                        roleClass = 'status-pending';
                                    }

                                    return `
                                <tr data-id="${req.id}">
                                    <td>${req.id}</td>
                                    <td><strong>${escapeHtml(req.username)}</strong></td>
                                    <td>${escapeHtml(req.full_name)}</td>
                                    <td>${escapeHtml(req.email)}</td>
                                    <td><span class="status-badge ${roleClass}">${roleDisplay}</span></td>
                                    <td>${new Date(req.requested_at).toLocaleString()}</td>
                                    <td class="action-buttons">
                                        <button class="approve-btn" onclick="approveRequest(${req.id}, '${escapeHtml(req.username)}')"><i class="fas fa-check"></i> Approve</button>
                                        <button class="reject-btn" onclick="rejectRequest(${req.id}, '${escapeHtml(req.username)}')"><i class="fas fa-times"></i> Reject</button>
                                    </td>
                                </tr>
                            `;
                                }).join('');
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading requests:', error);
                });
        }

        let _allPartnerApplications = [];

        function filterPartnerApplications(status) {
            ['pending','approved','rejected','all'].forEach(s => {
                const btn = document.getElementById('pa-tab-' + s);
                if (!btn) return;
                btn.style.background = s === status ? '#003580' : 'white';
                btn.style.color = s === status ? 'white' : '#475569';
                btn.style.border = s === status ? 'none' : '1px solid #cbd5e1';
            });
            const filtered = status === 'all' ? _allPartnerApplications : _allPartnerApplications.filter(a => a.status === status);
            const tbody = document.getElementById('partner-applications-list');
            const countBadge = document.getElementById('partnerApplicationCount');
            if (countBadge) countBadge.textContent = `${filtered.length} applications`;
            if (!tbody) return;
            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;"><i class="fas fa-inbox"></i> No applications found.</td></tr>';
                return;
            }
            tbody.innerHTML = filtered.map(app => {
                const statusClass = app.status === 'approved' ? 'status-confirmed' : (app.status === 'rejected' ? 'status-cancelled' : 'status-pending');
                const appliedAt = app.created_at ? new Date(app.created_at).toLocaleString() : 'N/A';
                return `<tr data-id="${app.id}">
                    <td>${app.id}</td>
                    <td><strong>${escapeHtml(app.company_name)}</strong></td>
                    <td>${escapeHtml(app.contact_person)}<br><span style="font-size:0.8rem;color:#64748b;">${escapeHtml(app.phone)}</span></td>
                    <td>${escapeHtml(app.email)}</td>
                    <td>${escapeHtml(app.business_type)}</td>
                    <td><span class="status-badge ${statusClass}">${app.status.toUpperCase()}</span></td>
                    <td>${appliedAt}</td>
                    <td class="action-buttons">
                        ${app.status === 'pending' ? `
                            <button class="approve-btn" onclick="approvePartnerApplication(${app.id}, '${escapeHtml(app.company_name)}')"><i class="fas fa-check"></i> Approve</button>
                            <button class="reject-btn" onclick="promptRejectPartnerApplication(${app.id}, '${escapeHtml(app.company_name)}')"><i class="fas fa-times"></i> Reject</button>
                        ` : `<span style="color:#64748b;font-size:0.85rem;">No actions</span>`}
                    </td>
                </tr>`;
            }).join('');
        }

        function loadPartnerApplications() {
            fetch('admin-api.php?action=get_partner_applications')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        _allPartnerApplications = data.data;
                        filterPartnerApplications('pending');
                    } else {
                        console.error('Failed to load partner applications', data.message);
                    }
                })
                .catch(error => console.error('Error loading partner applications:', error));
        }

        function approvePartnerApplication(id, companyName) {
            Swal.fire({
                title: 'Approve Partner',
                html: `Approve partner application for <strong>${companyName}</strong>?`,
                iconHtml: '<div class="custom-approve-icon"></div>',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Approve',
                customClass: { icon: 'no-border-icon', popup: 'modern-modal-popup' }
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=approve_partner_application&request_id=${id}`
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            Swal.fire('Approved', data.message, 'success');
                            loadPartnerApplications();
                        } else {
                            Swal.fire('Error', data.message || 'Unable to approve', 'error');
                        }
                    });
                }
            });
        }

        function promptRejectPartnerApplication(id, companyName) {
            Swal.fire({
                title: 'Reject Partner Application',
                html: `Provide a rejection reason for <strong>${companyName}</strong>:`,
                input: 'textarea',
                inputPlaceholder: 'Enter rejection reason...',
                showCancelButton: true,
                confirmButtonText: 'Reject',
                inputValidator: value => { if (!value) return 'A rejection reason is required.'; }
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=reject_partner_application&request_id=${id}&reason=${encodeURIComponent(result.value)}`
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            Swal.fire('Rejected', data.message, 'success');
                            loadPartnerApplications();
                        } else {
                            Swal.fire('Error', data.message || 'Unable to reject', 'error');
                        }
                    });
                }
            });
        }

        function loadApprovedPartners() {
            fetch('admin-api.php?action=get_approved_partners')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const partners = data.data;
                        const tbody = document.getElementById('approved-partners-list');
                        const countBadge = document.getElementById('approvedPartnersCount');
                        const menuBadge = document.getElementById('menuApprovedPartnersCount');
                        if (countBadge) countBadge.textContent = `${partners.length} partner${partners.length !== 1 ? 's' : ''}`;
                        if (menuBadge) menuBadge.textContent = partners.length;
                        if (!tbody) return;
                        if (partners.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;"><i class="fas fa-check-circle"></i> No partners yet.</td></tr>';
                            return;
                        }
                        tbody.innerHTML = partners.map(partner => {
                            const approvedAt = partner.approved_at ? new Date(partner.approved_at).toLocaleString() : 'N/A';
                            const website = partner.website ? `<a href="${escapeHtml(partner.website)}" target="_blank" style="color:var(--primary);text-decoration:none;">${escapeHtml(partner.website)}</a>` : 'N/A';
                            let banHtml = '';
                            if (parseInt(partner.is_banned) === 1) {
                                if (partner.ban_until) {
                                    const banUntil = new Date(partner.ban_until);
                                    if (banUntil > new Date()) {
                                        const diffDays = Math.ceil((banUntil - new Date()) / (1000*60*60*24));
                                        banHtml = `<span class="status-badge status-cancelled"><i class="fas fa-ban"></i> Banned (${diffDays}d left)</span>`;
                                    } else {
                                        banHtml = `<span class="status-badge status-incomplete">Ban Expired</span>`;
                                    }
                                } else {
                                    banHtml = `<span class="status-badge status-cancelled"><i class="fas fa-ban"></i> Permanent Ban</span>`;
                                }
                            } else {
                                banHtml = `<span class="status-badge status-confirmed"><i class="fas fa-check-circle"></i> Active</span>`;
                            }
                            const isBanned = parseInt(partner.is_banned) === 1;
                            return `<tr data-id="${partner.id}">
                                <td>${partner.id}</td>
                                <td><strong>${escapeHtml(partner.company_name)}</strong></td>
                                <td>${escapeHtml(partner.contact_person)}<br><span style="font-size:0.8rem;color:#64748b;">${escapeHtml(partner.phone)}</span></td>
                                <td>${escapeHtml(partner.email)}</td>
                                <td>${escapeHtml(partner.business_type)}</td>
                                <td>${website}</td>
                                <td>${approvedAt}</td>
                                <td>${banHtml}</td>
                                <td class="action-buttons">
                                    <button class="view-btn" onclick="viewPartnerDetails(${partner.id})"><i class="fas fa-eye"></i></button>
                                    <button class="${isBanned ? 'approve-btn' : 'incomplete-btn'}" onclick="promptBanPartner(${partner.id}, '${escapeHtml(partner.company_name)}', ${isBanned ? 1 : 0})">
                                        <i class="fas fa-${isBanned ? 'lock-open' : 'ban'}"></i> ${isBanned ? 'Unban' : 'Ban'}
                                    </button>
                                    <button class="delete-btn" onclick="deletePartner(${partner.id}, '${escapeHtml(partner.company_name)}')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>`;
                        }).join('');
                    } else {
                        console.error('Failed to load approved partners', data.message);
                    }
                })
                .catch(error => console.error('Error loading approved partners:', error));
        }

        function promptBanPartner(partnerId, companyName, isBanned) {
            if (isBanned) {
                Swal.fire({
                    title: 'Unban Partner',
                    html: `Unban <strong>${companyName}</strong>? They will regain access immediately.`,
                    icon: 'question', showCancelButton: true,
                    confirmButtonColor: '#16a34a', confirmButtonText: 'Yes, Unban'
                }).then(result => {
                    if (result.isConfirmed) submitPartnerBan(partnerId, 'unban', null);
                });
            } else {
                Swal.fire({
                    title: 'Ban Partner',
                    html: `<p style="margin-bottom:12px;">Ban <strong>${companyName}</strong>?</p>
                        <select id="banTypeSelect" style="width:100%;padding:10px;border-radius:10px;border:1px solid #cbd5e1;font-family:inherit;">
                            <option value="1">1 Day</option>
                            <option value="7">7 Days</option>
                            <option value="30">30 Days</option>
                            <option value="90">90 Days</option>
                            <option value="permanent">Permanent</option>
                        </select>`,
                    icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#dc2626', confirmButtonText: 'Ban Partner',
                    preConfirm: () => document.getElementById('banTypeSelect').value
                }).then(result => {
                    if (result.isConfirmed) {
                        const days = result.value === 'permanent' ? null : parseInt(result.value);
                        submitPartnerBan(partnerId, 'ban', days);
                    }
                });
            }
        }

        function submitPartnerBan(partnerId, banAction, days) {
            const formData = new FormData();
            formData.append('action', 'ban_partner');
            formData.append('partner_id', partnerId);
            formData.append('ban_action', banAction);
            if (days !== null && days !== undefined) formData.append('ban_days', days);
            fetch('admin-api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Done!', text: data.message, timer: 2000, showConfirmButton: false });
                        loadApprovedPartners();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
        }

        function deletePartner(partnerId, companyName) {
            Swal.fire({
                title: 'Delete Partner Account',
                html: `Permanently delete <strong>${companyName}</strong>? This cannot be undone.`,
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#dc2626', confirmButtonText: 'Yes, Delete'
            }).then(result => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_partner');
                    formData.append('partner_id', partnerId);
                    fetch('admin-api.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({ icon: 'success', title: 'Deleted!', text: data.message, timer: 2000, showConfirmButton: false });
                                loadApprovedPartners();
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                }
            });
        }

        // --- User management: Delete & Ban ---
        function deleteUser(userId, email) {
            Swal.fire({
                title: 'Delete Customer',
                html: `Permanently delete customer <strong>${email}</strong>? This cannot be undone.`,
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#dc2626', confirmButtonText: 'Yes, Delete'
            }).then(result => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_user');
                    formData.append('user_id', userId);
                    fetch('admin-api.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({ icon: 'success', title: 'Deleted', text: data.message, timer: 1500, showConfirmButton: false });
                                // remove row or reload
                                setTimeout(() => location.reload(), 800);
                            } else {
                                Swal.fire('Error', data.message || 'Failed to delete user', 'error');
                            }
                        }).catch(() => Swal.fire('Error', 'Network error', 'error'));
                }
            });
        }

        function promptBanUser(userId, fullName, isBanned) {
            if (isBanned) {
                Swal.fire({
                    title: 'Unban Customer',
                    html: `Unban <strong>${fullName}</strong>? They will regain access immediately.`,
                    icon: 'question', showCancelButton: true,
                    confirmButtonColor: '#16a34a', confirmButtonText: 'Yes, Unban'
                }).then(result => {
                    if (result.isConfirmed) submitUserBan(userId, 'unban', null);
                });
            } else {
                Swal.fire({
                    title: 'Ban Customer',
                    html: `<p style="margin-bottom:12px;">Ban <strong>${fullName}</strong>?</p>
                        <select id="banUserTypeSelect" style="width:100%;padding:10px;border-radius:10px;border:1px solid #cbd5e1;font-family:inherit;">
                            <option value="1">1 Day</option>
                            <option value="7">7 Days</option>
                            <option value="30">30 Days</option>
                            <option value="90">90 Days</option>
                            <option value="permanent">Permanent</option>
                        </select>`,
                    icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#dc2626', confirmButtonText: 'Ban Customer',
                    preConfirm: () => document.getElementById('banUserTypeSelect').value
                }).then(result => {
                    if (result.isConfirmed) {
                        const days = result.value === 'permanent' ? null : parseInt(result.value);
                        submitUserBan(userId, 'ban', days);
                    }
                });
            }
        }

        function submitUserBan(userId, banAction, days) {
            const formData = new FormData();
            formData.append('action', 'ban_user');
            formData.append('user_id', userId);
            formData.append('ban_action', banAction);
            if (days !== null && days !== undefined) formData.append('ban_days', days);
            fetch('admin-api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Done', text: data.message, timer: 1500, showConfirmButton: false });
                        setTimeout(() => location.reload(), 700);
                    } else {
                        Swal.fire('Error', data.message || 'Failed to update ban status', 'error');
                    }
                }).catch(() => Swal.fire('Error', 'Network error', 'error'));
        }

        function viewPartnerDetails(partnerId) {
            window.open(`../view-partner-profile.php?id=${partnerId}`, '_blank');
        }

        function approveRequest(requestId, username) {
            Swal.fire({
                title: 'Approve Registration',
                html: `Are you sure you want to APPROVE the admin registration for <strong>${username}</strong>?`,
                iconHtml: '<div class="custom-approve-icon"></div>',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Approve',
                customClass: {
                    icon: 'no-border-icon',
                    popup: 'modern-modal-popup',
                    cancelButton: 'swal-custom-cancel',
                    confirmButton: 'swal2-confirm'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    fetch('admin-api.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=approve_admin_request&request_id=${requestId}`
                    })
                        .then(r => {
                            if (r.status === 401) throw new Error('Unauthorized');
                            return r.json();
                        })
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Approved!',
                                    html: 'Admin account approved successfully.',
                                    iconHtml: '<div class="custom-approve-icon"></div>',
                                    confirmButtonColor: '#28a745',
                                    confirmButtonText: 'OK',
                                    customClass: {
                                        icon: 'no-border-icon',
                                        popup: 'modern-modal-popup',
                                        confirmButton: 'swal2-confirm'
                                    }
                                }).then(() => location.reload());
                            } else {
                                Swal.fire('Error', data.message || 'An error occurred', 'error');
                            }
                        })
                        .catch(err => {
                            const msg = err.message === 'Unauthorized' ? 'Session expired or unauthorized. Please refresh and login again.' : (err.message || 'Network error');
                            Swal.fire('Error', msg, 'error');
                        });
                }
            });
        }

        function rejectRequest(requestId, username) {
            Swal.fire({
                title: 'Reject Registration',
                html: `You are about to reject the admin registration request for <strong>${username}</strong>.<br><br>This action cannot be undone.`,
                iconHtml: '<div class="custom-declined-icon"></div>',
                showCancelButton: true,
                confirmButtonColor: '#d32f2f',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Reject',
                reverseButtons: true,
                customClass: {
                    icon: 'no-border-icon',
                    popup: 'modern-modal-popup',
                    cancelButton: 'swal-custom-cancel',
                    confirmButton: 'swal2-confirm'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    fetch('admin-api.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=reject_admin_request&request_id=${requestId}`
                    })
                        .then(r => {
                            if (r.status === 401) throw new Error('Unauthorized');
                            return r.json();
                        })
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Rejected!',
                                    html: 'Request rejected successfully.',
                                    iconHtml: '<div class="custom-approve-icon"></div>',
                                    confirmButtonColor: '#28a745',
                                    confirmButtonText: 'OK',
                                    customClass: {
                                        icon: 'no-border-icon',
                                        popup: 'modern-modal-popup',
                                        confirmButton: 'swal2-confirm'
                                    }
                                }).then(() => {
                                    loadPendingRequests();
                                });
                            } else {
                                Swal.fire('Error', data.message || 'An error occurred', 'error');
                            }
                        })
                        .catch(err => {
                            const msg = err.message === 'Unauthorized' ? 'Session expired or unauthorized. Please refresh and login again.' : (err.message || 'Network error');
                            Swal.fire('Error', msg, 'error');
                        });
                }
            });
        }

        function deleteAdminUser(id, username) {
            if (adminRole !== 'super_admin') {
                showNotification('❌ Permission denied. Only Super Admin can delete admin users.', 'error');
                return;
            }
            Swal.fire({
                title: 'Delete Admin User',
                html: `You are about to permanently delete the admin account for <strong>${username}</strong>.<br><br>This action cannot be undone.`,
                iconHtml: '<div class="custom-declined-icon"></div>',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Delete Agent',
                reverseButtons: true,
                customClass: {
                    icon: 'no-border-icon',
                    popup: 'modern-modal-popup',
                    cancelButton: 'swal-custom-cancel',
                    confirmButton: 'swal2-confirm'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_admin_user&id=${id}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Deleted!',
                                    html: 'Admin user deleted successfully.',
                                    iconHtml: '<div class="custom-approve-icon"></div>',
                                    confirmButtonColor: '#28a745',
                                    confirmButtonText: 'OK',
                                    customClass: {
                                        icon: 'no-border-icon',
                                        popup: 'modern-modal-popup',
                                        confirmButton: 'swal2-confirm'
                                    }
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    html: data.message,
                                    iconHtml: '<div class="custom-declined-icon"></div>',
                                    confirmButtonColor: '#d32f2f',
                                    confirmButtonText: 'OK',
                                    customClass: {
                                        icon: 'no-border-icon',
                                        popup: 'modern-modal-popup',
                                        confirmButton: 'swal2-confirm'
                                    }
                                });
                            }
                        });
                }
            });
        }

        function showReceiptAlert(id) {
            const receiptNo = 'HD-REC-' + id.toString().padStart(6, '0');
            const now = new Date().toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            Swal.fire({
                title: '<span style="color: #059669;">Payment Verification</span>',
                html: `
                    <div style="margin-top: 10px;">
                        <i class="fas fa-check-circle" style="font-size: 3.5rem; color: #10b981; margin-bottom: 20px; display: block;"></i>
                        <p style="font-size: 1.05rem; color: #475569; margin-bottom: 24px; line-height: 1.5;">
                            This transaction has been successfully <br><strong>processed and verified</strong>.
                        </p>
                        
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 10px;">
                            <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 8px;">
                                Verification Reference ID
                            </span>
                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 1.25rem; font-weight: 800; color: #4f46e5; display: block; margin-bottom: 4px;">
                                ${receiptNo}
                            </span>
                            <span style="font-size: 0.7rem; color: #94a3b8;">
                                Verified on ${now}
                            </span>
                        </div>
                        
                        <div style="display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 15px; color: #10b981; font-size: 0.85rem; font-weight: 600;">
                            <i class="fas fa-shield-alt"></i> SECURED TRANSACTION
                        </div>
                    </div>
                `,
                showConfirmButton: true,
                confirmButtonColor: '#4f46e5',
                confirmButtonText: 'CLOSE RECEIPT',
                customClass: {
                    popup: 'glass-modal',
                    confirmButton: 'swal-custom-confirm'
                },
                showClass: {
                    popup: 'animate__animated animate__zoomIn animate__faster'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOut animate__faster'
                }
            });
        }

        async function viewBookingConfirmation(id, bookingNumber = '') {
            resetModal();
            try {
                const response = await fetch(`admin-api.php?action=get_booking&id=${id}&booking_number=${bookingNumber}`);
                const data = await response.json();

                if (data.success) {
                    const booking = data.data;

                    // Mark as viewed
                    const viewed = JSON.parse(localStorage.getItem('viewed_bookings') || '[]');
                    const trackId = booking.booking_number;
                    if (!viewed.includes(String(trackId))) {
                        viewed.push(String(trackId));
                        localStorage.setItem('viewed_bookings', JSON.stringify(viewed));
                        // Update UI immediately
                        applyFilters();
                    }

                    // Fetch documents for this specific booking
                    const docRes = await fetch(`../User%20Account/api/upload-api.php?action=list&booking_number=${booking.booking_number}`);
                    const docData = await docRes.json();
                    const bookingDocs = docData.success ? docData.documents : [];

                    // Fetch Package Details (Inclusions/Itinerary/Location)
                    let packageInfoHtml = '';
                    let packageLocation = null;
                    if (booking.package_name) {
                        try {
                            const pkgRes = await fetch(`admin-api.php?action=get_package_info&package_name=${encodeURIComponent(booking.package_name)}`);
                            const pkgData = await pkgRes.json();
                            if (pkgData.success) {
                                const info = pkgData.data;
                                packageLocation = info.location;
                                packageInfoHtml = `
                                    <div style="margin-top: 20px; padding: 16px; background: #f0f9ff; border-radius: 12px; border: 1px solid #bae6fd;">
                                        <h5 style="margin: 0 0 12px; color: #0369a1; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-info-circle" style="color: #0ea5e9; margin-right: 6px;"></i> Package Reference Details</h5>
                                        
                                        ${info.inclusions ? `
                                            <div style="margin-bottom: 12px;">
                                                <strong style="display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Inclusions:</strong>
                                                <div style="font-size: 0.85rem; color: #334155; line-height: 1.5; white-space: pre-wrap;">${info.inclusions}</div>
                                            </div>
                                        ` : ''}

                                        ${info.itinerary ? `
                                            <div>
                                                <strong style="display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Itinerary:</strong>
                                                <div style="font-size: 0.85rem; color: #334155; line-height: 1.5; white-space: pre-wrap;">${info.itinerary}</div>
                                            </div>
                                        ` : ''}
                                        
                                        ${!info.inclusions && !info.itinerary && info.description ? `
                                            <div>
                                                <strong style="display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Description:</strong>
                                                <div style="font-size: 0.85rem; color: #334155; line-height: 1.5;">${info.description}</div>
                                            </div>
                                        ` : ''}
                                    </div>
                                `;
                            }
                        } catch (e) { console.error("Error fetching package info:", e); }
                    }

                    let documentsHtml = '';
                    if (bookingDocs.length > 0) {
                        documentsHtml = `
                            <div style="margin-top: 20px; padding: 16px; background: #fdf4ff; border-radius: 12px; border: 1px solid #f5d0fe;">
                                <h5 style="margin: 0 0 12px; color: #a21caf; font-weight: 700; font-size: 0.95rem;">
                                    <i class="fas fa-folder-open" style="color: #c026d3; margin-right: 6px;"></i> Travel Documents
                                </h5>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    ${bookingDocs.map(doc => `
                                        <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 10px 14px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                            <span style="font-size: 0.9rem; color: #334155; font-weight: 500;"><i class="fas fa-file-alt" style="color: #64748b; margin-right: 8px;"></i> ${escapeHtml(doc.file_name)}</span>
                                            <a href="../${doc.file_path}" target="_blank" class="view-btn" style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }

                    const isInquiry = booking.payment_method === 'Inquiry Only';
                    const isVisa = booking.destination_name === 'Visa Assistance' || (booking.package_name && booking.package_name.toLowerCase().includes('visa'));
                    const displayDestination = packageLocation ? packageLocation : (booking.destination_name === 'Local Package' && booking.package_name ? booking.package_name : booking.destination_name);

                    let fields = '';
                    if (isInquiry) {
                        fields = `
                            <div class="confirmation-details" style="font-family: inherit;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 25px; line-height: 1.6; font-size: 0.95rem;">
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Inquiry Number</strong> <span style="color: #0f172a; font-weight: 600;">${booking.booking_number}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Status</strong> <span class="status-badge status-${booking.booking_status}" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">${booking.booking_status.toUpperCase()}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Applied Date</strong> <span style="color: #0f172a; font-weight: 600;">${new Date(booking.created_at).toLocaleDateString()}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Customer Name</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(booking.full_name)}</span></div>
                                    <div style="min-width: 0;"><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Email</strong> <span style="color: #0f172a; font-weight: 600; word-break: break-all;">${escapeHtml(booking.email)}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Phone</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(booking.phone)}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Travel Date</strong> <span style="color: #0f172a; font-weight: 600;">${booking.travel_date && booking.travel_date !== '0000-00-00' ? new Date(booking.travel_date).toLocaleDateString() : 'To be determined'}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Travelers</strong> <span style="color: #0f172a; font-weight: 600;">${booking.number_of_travelers}</span></div>
                                </div>

                                <div style="margin-top: 20px; padding: 16px; background: #eff6ff; border-radius: 12px; border: 1px solid #bfdbfe;">
                                    <h5 style="margin: 0 0 12px; color: #1e3a8a; font-weight: 700; font-size: 0.95rem;">
                                        <i class="fas fa-list-alt" style="color: #3b82f6; margin-right: 6px;"></i> Inquiry Options & Preferences
                                    </h5>
                                    <div style="font-size: 0.9rem; line-height: 1.6; color: #1e40af; white-space: pre-wrap;">${escapeHtml(booking.special_requests)}</div>
                                </div>

                                ${booking.admin_notes ? `
                                <div style="margin-top: 15px; padding: 16px; background: #fffcf0; border-radius: 12px; border: 1px dashed #ff9800;">
                                    <h5 style="margin: 0 0 8px; color: #856404; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-comment-dots" style="color: #eab308; margin-right: 6px;"></i> Admin Notes</h5>
                                    <div style="font-size: 0.9rem; font-style: italic; color: #333;">"${escapeHtml(booking.admin_notes)}"</div>
                                </div>` : ''}
                            </div>
                        `;
                    } else {
                        fields = `
                            <div class="confirmation-details" style="font-family: inherit;">
                                <h4 style="color: #1e293b; font-size: 1.15rem; font-weight: 800; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 20px;">
                                    <i class="fas ${isVisa ? 'fa-passport' : 'fa-ticket-alt'}" style="color: #0284c7; margin-right: 8px;"></i> ${isVisa ? 'Visa Assistance Details' : 'Booking and Customer Details'}
                                </h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 25px; line-height: 1.6; font-size: 0.95rem;">
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">${isVisa ? 'Assistance Number' : 'Booking Number'}</strong> <span style="color: #0f172a; font-weight: 600;">${booking.booking_number}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Receipt Number</strong> <span style="color: #6366f1; font-weight: 800; font-family: monospace;">HD-REC-${booking.id.toString().padStart(6, '0')}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">${isVisa ? 'Availed Date' : 'Booking Date'}</strong> <span style="color: #0f172a; font-weight: 600;">${new Date(booking.created_at).toLocaleDateString()}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Customer Name</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(booking.full_name)}</span></div>
                                    <div style="min-width: 0;"><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Email</strong> <span style="color: #0f172a; font-weight: 600; word-break: break-all;">${escapeHtml(booking.email)}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Phone</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(booking.phone)}</span></div>
                                    
                                    ${!isVisa ? `
                                        <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Address</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(booking.address) || 'N/A'}</span></div>
                                    ` : `
                                        <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Target Date</strong> <span style="color: #0f172a; font-weight: 600;">${booking.travel_date && booking.travel_date !== '0000-00-00' ? new Date(booking.travel_date).toLocaleDateString() : 'To be determined'}</span></div>
                                    `}
                                    
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">${isVisa ? 'Service Availed' : 'Location / Destination'}</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(displayDestination)}</span></div>
                                    
                                    ${!isVisa ? `
                                        <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Package</strong> <span style="color: #0f172a; font-weight: 600;">${escapeHtml(booking.package_name)}</span></div>
                                        <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Duration</strong> <span style="color: #0f172a; font-weight: 600;">${booking.package_duration}</span></div>
                                        <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Travel Date</strong> <span style="color: #0f172a; font-weight: 600;">${new Date(booking.travel_date).toLocaleDateString()}</span></div>
                                    ` : ''}

                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Travelers</strong> <span style="color: #0f172a; font-weight: 600;">${booking.number_of_travelers}</span></div>
                                    <div><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Price per Person</strong> <span style="color: #0f172a; font-weight: 600;">₱${parseFloat(booking.price_per_person).toLocaleString()}</span></div>
                                    <div style="grid-column: 1 / -1; background: #f8fafc; padding: 12px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e2e8f0; margin-top: 5px;">
                                        <strong style="color: #1e293b; font-size: 1rem;">Total Amount</strong> 
                                        <span style="color: #0284c7; font-weight: 800; font-size: 1.1rem;">₱${parseFloat(booking.total_amount).toLocaleString()}</span>
                                    </div>
                                    <div style="grid-column: 1 / -1; display: flex; justify-content: ${isVisa ? 'center' : 'space-between'}; gap: ${isVisa ? '40px' : '0'}; align-items: flex-start; flex-wrap: wrap; padding: 14px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                                        <div style="text-align: center; flex: ${isVisa ? '0 1 auto' : '1'};"><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Visa Status</strong> <span class="status-badge status-${(booking.visa_status || 'PENDING').toLowerCase() === 'approved' ? 'confirmed' : ((booking.visa_status || 'PENDING').toLowerCase() === 'declined' ? 'cancelled' : 'pending')}" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">${(booking.visa_status || 'PENDING').toUpperCase()}</span></div>
                                        ${isVisa ? '' : `<div style="text-align: center; flex: 1;"><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Booking Status</strong> <span class="status-badge status-${booking.booking_status}" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">${booking.booking_status.toUpperCase()}</span></div>`}
                                        <div style="text-align: center; flex: ${isVisa ? '0 1 auto' : '1'};"><strong style="color: #64748b; display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Payment Status</strong> <span class="status-badge ${booking.payment_status === 'paid' ? 'status-confirmed' : 'status-pending'}" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">${booking.payment_status.toUpperCase()}</span></div>
                                    </div>
                                </div>
                                
                                ${documentsHtml}
                                
                                ${(!isVisa && (booking.payment_method || booking.payment_reference || booking.payment_proof)) ? `
                                <div style="margin-top: 20px; padding: 16px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                                    <h5 style="margin: 0 0 12px; color: #15803d; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-credit-card" style="color: #16a34a; margin-right: 6px;"></i> Payment Information</h5>
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        ${booking.payment_method ? `<div style="display:flex;gap:8px;align-items:center;font-size:0.9rem;"><span style="color:#64748b;font-weight:600;min-width:110px;">Method:</span><span style="color:#0f172a;font-weight:700;text-transform:capitalize;">${escapeHtml(booking.payment_method)}</span></div>` : ''}
                                        ${booking.payment_reference ? `<div style="display:flex;gap:8px;align-items:center;font-size:0.9rem;"><span style="color:#64748b;font-weight:600;min-width:110px;">Reference #:</span><span style="color:#0f172a;font-weight:700;font-family:monospace;">${escapeHtml(booking.payment_reference)}</span></div>` : ''}
                                        ${booking.payment_proof ? `
                                        <div style="margin-top: 8px; text-align: center;">
                                            <span style="color:#64748b;font-weight:600;font-size:0.9rem;display:block;margin-bottom:8px;">Payment Proof:</span>
                                            ${/\.(jpg|jpeg|png|gif|webp)$/i.test(booking.payment_proof) ? `
                                            <a href="../${booking.payment_proof}" target="_blank" title="Click to view full image" style="display:inline-block;">
                                                <img src="../${booking.payment_proof}" alt="Payment Proof" style="max-width:100%;max-height:220px;border-radius:10px;border:2px solid #bbf7d0;box-shadow:0 4px 12px rgba(0,0,0,0.1);cursor:pointer;object-fit:cover;display:block;margin:0 auto;">
                                            </a>
                                            <p style="font-size:0.78rem;color:#64748b;margin-top:6px;text-align:center;"><i class="fas fa-search-plus" style="margin-right:4px;"></i>Click image to view full size</p>
                                            ` : `
                                            <a href="../${booking.payment_proof}" target="_blank" class="view-btn" style="padding:8px 16px;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;border-radius:8px;">
                                                <i class="fas fa-file-download"></i> Download / View Receipt
                                            </a>
                                            `}
                                        </div>` : ''}
                                    </div>
                                </div>` : ''}

                                ${!isInquiry ? `
                                <div style="margin-top: 20px; padding: 16px; background: #fdf4ff; border-radius: 12px; border: 1px solid #f0abfc;">
                                    <h5 style="margin: 0 0 12px; color: #a21caf; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-handshake" style="color: #c026d3; margin-right: 6px;"></i> Partner Details</h5>
                                    ${booking.partner_id ? `
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; line-height: 1.6; font-size: 0.92rem;">
                                        <div><strong style="color:#64748b;display:block;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.5px;">Name</strong> <span style="color:#0f172a;font-weight:600;">${escapeHtml(booking.partner_contact_person || booking.partner_business_name || booking.partner_company || 'Partner')}</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.5px;">Company</strong> <span style="color:#0f172a;font-weight:600;">${escapeHtml(booking.partner_business_name || booking.partner_company || 'N/A')}</span></div>
                                        <div style="min-width:0;"><strong style="color:#64748b;display:block;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.5px;">Email</strong> <span style="color:#0f172a;font-weight:600;word-break:break-all;">${escapeHtml(booking.partner_email || 'N/A')}</span></div>
                                        <div><strong style="color:#64748b;display:block;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.5px;">Number</strong> <span style="color:#0f172a;font-weight:600;">${escapeHtml(booking.partner_profile_phone || booking.partner_phone || 'N/A')}</span></div>
                                    </div>
                                    <a href="Partnership/partner-profile.php?id=${booking.partner_id}" target="_blank" class="view-btn" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                                        <i class="fas fa-arrow-up-right-from-square"></i> View Partner Profile
                                    </a>
                                    ` : `
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <i class="fas fa-star" style="color: #c026d3; font-size: 1.1rem;"></i>
                                        <div>
                                            <strong style="color: #0f172a; display: block;">Made by HeyDream</strong>
                                            <span style="font-size: 0.78rem; color: #64748b;">This package is offered directly by HeyDream Travel and Tours.</span>
                                        </div>
                                    </div>
                                    `}
                                </div>` : ''}

                                ${booking.special_requests ? `
                                <div style="margin-top: 20px; padding: 16px; background: #eff6ff; border-radius: 12px; border: 1px solid #bfdbfe;">
                                    <h5 style="margin: 0 0 8px; color: #1e3a8a; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-star" style="color: #3b82f6; margin-right: 6px;"></i> Special Requests</h5>
                                    <div style="font-size: 0.9rem; line-height: 1.6; color: #1e40af;">${escapeHtml(booking.special_requests)}</div>
                                </div>` : ''}

                                ${booking.flight_details ? `
                                <div style="margin-top: 20px; padding: 16px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                                    <h5 style="margin: 0 0 8px; color: #1e293b; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-plane-departure" style="color: #6366f1; margin-right: 6px;"></i> Flight Details</h5>
                                    <div style="font-size: 0.9rem; line-height: 1.6; color: #334155; white-space: pre-wrap;">${escapeHtml(booking.flight_details)}</div>
                                </div>` : ''}

                                ${booking.admin_notes ? `
                                <div style="margin-top: 15px; padding: 16px; background: #fffcf0; border-radius: 12px; border: 1px dashed #ff9800;">
                                    <h5 style="margin: 0 0 8px; color: #856404; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-comment-dots" style="color: #eab308; margin-right: 6px;"></i> Admin Notes</h5>
                                    <div style="font-size: 0.9rem; font-style: italic; color: #333;">"${escapeHtml(booking.admin_notes)}"</div>
                                </div>` : ''}
                            </div>
                        `;
                    }
                    document.getElementById('modal-title').innerText = isInquiry ? 'Customer Inquiry Details' : (isVisa ? 'Visa Assistance Details' : 'Booking and Customer Details');
                    document.getElementById('form-fields').innerHTML = fields;
                    document.getElementById('editModal').classList.add('active');
                    document.querySelector('#editForm button').style.display = 'none';
                }
            } catch (error) {
                console.error('Error viewing booking:', error);
                showNotification('Error loading booking details', 'error');
            }
        }


        async function editBooking(id, bookingNumber = '') {
            resetModal();

            // Remove from viewed if present
            let viewed = JSON.parse(localStorage.getItem('viewed_bookings') || '[]');
            const trackId = bookingNumber || bookings.find(b => b.id == id)?.booking_number;
            if (trackId && viewed.includes(String(trackId))) {
                viewed = viewed.filter(vId => vId !== String(trackId));
                localStorage.setItem('viewed_bookings', JSON.stringify(viewed));
                applyFilters(); // Update UI immediately
            }

            console.log('Fetching booking ID:', id, 'Booking Number:', bookingNumber); // Debug log

            try {
                const response = await fetch(`admin-api.php?action=get_booking&id=${id}&booking_number=${bookingNumber}`);
                console.log('Response status:', response.status);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();
                console.log('Response data:', data);

                if (data.success) {
                    const booking = data.data;

                    // Fetch uploaded documents for this specific booking
                    const docRes = await fetch(`../User%20Account/api/upload-api.php?action=list&booking_number=${booking.booking_number}`);
                    const docData = await docRes.json();
                    const bookingDocs = docData.success ? docData.documents : [];

                    // Fetch Package Details (Inclusions/Itinerary)
                    let packageInfoHtml = '';
                    if (booking.package_name) {
                        try {
                            const pkgRes = await fetch(`admin-api.php?action=get_package_info&package_name=${encodeURIComponent(booking.package_name)}`);
                            const pkgData = await pkgRes.json();
                            if (pkgData.success) {
                                const info = pkgData.data;
                                packageInfoHtml = `
                                    <div style="margin-bottom: 25px; padding: 16px; background: #f0f9ff; border-radius: 12px; border: 1px solid #bae6fd;">
                                        <h5 style="margin: 0 0 12px; color: #0369a1; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-info-circle" style="color: #0ea5e9; margin-right: 6px;"></i> Package Reference Details</h5>
                                        ${info.inclusions ? `
                                            <div style="margin-bottom: 12px;">
                                                <strong style="display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Inclusions:</strong>
                                                <div style="font-size: 0.85rem; color: #334155; line-height: 1.4; white-space: pre-wrap;">${info.inclusions}</div>
                                            </div>
                                        ` : ''}
                                        ${info.itinerary ? `
                                            <div>
                                                <strong style="display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Itinerary:</strong>
                                                <div style="font-size: 0.85rem; color: #334155; line-height: 1.4; white-space: pre-wrap;">${info.itinerary}</div>
                                            </div>
                                        ` : ''}
                                    </div>
                                `;
                            }
                        } catch (e) { }
                    }

                    let editDocsHtml = '';
                    if (bookingDocs.length > 0) {
                        editDocsHtml = `
                            <div style="margin-bottom: 25px; padding: 16px; background: #fdf4ff; border-radius: 12px; border: 1px solid #f5d0fe;">
                                <h5 style="margin: 0 0 12px; color: #a21caf; font-weight: 700; font-size: 0.95rem;">
                                    <i class="fas fa-folder-open" style="color: #c026d3; margin-right: 6px;"></i> Travel Documents
                                </h5>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    ${bookingDocs.map(doc => `
                                        <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 8px 12px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                            <span style="font-size: 0.85rem; color: #334155; font-weight: 500;">${escapeHtml(doc.file_name)}</span>
                                            <a href="../${doc.file_path}" target="_blank" style="color: #0284c7; font-size: 0.8rem; text-decoration: none;"><i class="fas fa-eye"></i> View</a>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }

                    const isVisa = booking.destination_name === 'Visa Assistance' || (booking.package_name && booking.package_name.toLowerCase().includes('visa'));
                    const isInquiry = booking.payment_method === 'Inquiry Only';

                    const inquiryDetailsHtml = isInquiry ? `
                        <div style="margin-bottom: 25px; padding: 16px; background: #eff6ff; border-radius: 12px; border: 1px solid #bfdbfe;">
                            <h5 style="margin: 0 0 12px; color: #1e3a8a; font-weight: 700; font-size: 0.95rem;">
                                <i class="fas fa-list-alt" style="color: #3b82f6; margin-right: 6px;"></i> Inquiry Options & Preferences
                            </h5>
                            <div style="font-size: 0.9rem; line-height: 1.6; color: #1e40af; white-space: pre-wrap;">${escapeHtml(booking.special_requests)}</div>
                        </div>
                    ` : '';

                    const fields = `
                    <div style="font-family: inherit;">
                        <div class="form-group" style="margin-bottom: 20px; display: ${isVisa ? 'none' : 'block'};">
                            <label style="color: #1e293b; font-weight: 700; margin-bottom: 8px;">Booking Status</label>
                            <select id="booking_status" class="form-control" style="background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                <option value="pending" ${booking.booking_status === 'pending' ? 'selected' : ''}>Pending</option>
                                ${isVisa ? `<option value="confirmed" ${booking.booking_status === 'confirmed' ? 'selected' : ''}>Confirmed (Checking & Send QR)</option>` : ''}
                                <option value="cancelled" ${booking.booking_status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                <option value="completed" ${booking.booking_status === 'completed' || (!isVisa && booking.booking_status === 'confirmed') ? 'selected' : ''}>Completed</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="color: #1e293b; font-weight: 700; margin-bottom: 8px;">Payment Status</label>
                            <select id="payment_status" class="form-control" style="background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                <option value="unpaid" ${booking.payment_status === 'unpaid' ? 'selected' : ''}>Unpaid</option>
                                <option value="paid" ${booking.payment_status === 'paid' ? 'selected' : ''}>Paid</option>
                                <option value="refunded" ${booking.payment_status === 'refunded' ? 'selected' : ''}>Refunded</option>
                            </select>
                        </div>
                        ${!isInquiry ? `
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="color: #1e293b; font-weight: 700; margin-bottom: 8px;">Visa Status</label>
                            <select id="visa_status" class="form-control" style="background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                ${isVisa ? `
                                <option value="FOR_RELEASING" ${booking.visa_status === 'FOR_RELEASING' ? 'selected' : ''}>For Releasing</option>
                                <option value="FOR_PICKUP" ${booking.visa_status === 'FOR_PICKUP' ? 'selected' : ''}>For Pick-up</option>
                                <option value="CLAIMED" ${booking.visa_status === 'CLAIMED' ? 'selected' : ''}>Claimed</option>
                                ` : `
                                <option value="PENDING" ${(!booking.visa_status || booking.visa_status === 'PENDING' || booking.visa_status === 'N/A') ? 'selected' : ''}>Pending</option>
                                <option value="APPROVED" ${booking.visa_status === 'APPROVED' ? 'selected' : ''}>Approved</option>
                                <option value="DECLINED" ${booking.visa_status === 'DECLINED' ? 'selected' : ''}>Declined</option>
                                `}
                            </select>
                        </div>
                        ` : ''}
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label style="color: #1e293b; font-weight: 700; margin-bottom: 8px;">Travelers</label>
                                <input type="number" id="number_of_travelers" class="form-control" value="${booking.number_of_travelers}" min="1" style="background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px;" oninput="updateEditTotal()">
                            </div>
                            <div class="form-group">
                                <label style="color: #1e293b; font-weight: 700; margin-bottom: 8px;">Price / Person</label>
                                <input type="number" id="price_per_person" class="form-control" value="${booking.price_per_person}" min="0" step="0.01" style="background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px;" oninput="updateEditTotal()">
                            </div>
                            <div class="form-group">
                                <label style="color: #1e293b; font-weight: 700; margin-bottom: 8px;">Total Amount</label>
                                <input type="number" id="total_amount" class="form-control" value="${booking.total_amount}" min="0" step="0.01" readonly style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; font-weight: 800; color: #0284c7; cursor: not-allowed;">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 25px;">
                            <label style="color: #1e293b; font-weight: 700; margin-bottom: 12px;"><i class="fas fa-tasks" style="color:#0284c7; margin-right: 6px;"></i> ${isVisa ? 'Visa Assistance Tracking Steps' : 'Booking Tracking Steps'}</label>
                            
                            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                                <label style="display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; cursor: pointer; transition: all 0.2s;">
                                    <input type="checkbox" id="travel_documents" ${booking.travel_documents == 1 ? 'checked' : ''} style="width: 20px; height: 20px; accent-color: #ff9800; margin: 0; cursor: pointer;">
                                    <span style="font-weight: 600; color: #334155; font-size: 0.95rem;"><i class="fas fa-file-alt" style="color:#ff9800; margin-right: 8px;"></i> ${isVisa ? 'Visa Documents Prepared' : 'Travel Documents Prepared'}</span>
                                </label>
                                
                                <label style="display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0; cursor: pointer; transition: all 0.2s;">
                                    <input type="checkbox" id="ready_for_travel" ${booking.ready_for_travel == 1 ? 'checked' : ''} style="width: 20px; height: 20px; accent-color: #22c55e; margin: 0; cursor: pointer;">
                                    <span style="font-weight: 600; color: #334155; font-size: 0.95rem;"><i class="fas fa-check-double" style="color:#22c55e; margin-right: 8px;"></i> Ready for Travel</span>
                                </label>
                            </div>

                            <div style="background: #eff6ff; padding: 14px 16px; border-radius: 12px; font-size: 0.85rem; color: #1d4ed8; display: flex; align-items: flex-start; gap: 10px; border: 1px solid #bfdbfe;">
                                <i class="fas fa-info-circle" style="margin-top: 2px;"></i>
                                <div>These steps are visible to the customer in their <strong>${isVisa ? 'Visa Assistance Tracking' : 'Booking Tracking'}</strong> view.</div>
                            </div>
                        </div>
                        ${editDocsHtml}

                        ${(!isVisa && (booking.payment_method || booking.payment_reference || booking.payment_proof)) ? `
                        <div style="margin-bottom: 25px; padding: 16px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                            <h5 style="margin: 0 0 12px; color: #15803d; font-weight: 700; font-size: 0.95rem;"><i class="fas fa-credit-card" style="color: #16a34a; margin-right: 6px;"></i> Payment Proof from Customer</h5>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                ${booking.payment_method ? `<div style="display:flex;gap:8px;align-items:center;font-size:0.9rem;"><span style="color:#64748b;font-weight:600;min-width:110px;">Method:</span><span style="color:#0f172a;font-weight:700;text-transform:capitalize;">${escapeHtml(booking.payment_method)}</span></div>` : ''}
                                ${booking.payment_reference ? `<div style="display:flex;gap:8px;align-items:center;font-size:0.9rem;"><span style="color:#64748b;font-weight:600;min-width:110px;">Reference #:</span><span style="color:#0f172a;font-weight:700;font-family:monospace;">${escapeHtml(booking.payment_reference)}</span></div>` : ''}
                                ${booking.payment_proof ? `
                                <div style="margin-top: 10px; text-align: center;">
                                    <span style="color:#64748b;font-weight:600;font-size:0.88rem;display:block;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;">Payment Screenshot / Receipt:</span>
                                    ${/\.(jpg|jpeg|png|gif|webp)$/i.test(booking.payment_proof) ? `
                                    <a href="../${booking.payment_proof}" target="_blank" title="Click to view full size" style="display:inline-block;">
                                        <img src="../${booking.payment_proof}" alt="Payment Proof"
                                            style="max-width:100%; max-height:260px; border-radius:12px; border:2px solid #bbf7d0;
                                                   box-shadow:0 4px 16px rgba(0,0,0,0.12); cursor:zoom-in; object-fit:contain; display:block; margin:0 auto; background:#f8fafc;">
                                    </a>
                                    <p style="font-size:0.78rem;color:#64748b;margin-top:8px;text-align:center;">
                                        <i class="fas fa-expand-alt" style="margin-right:4px;"></i>Click image to open full size in new tab
                                    </p>
                                    ` : `
                                    <a href="../${booking.payment_proof}" target="_blank"
                                       style="display:inline-flex;align-items:center;gap:8px;background:#16a34a;color:white;
                                              padding:10px 18px;border-radius:10px;font-size:0.88rem;font-weight:600;
                                              text-decoration:none;transition:all 0.2s;"
                                       onmouseover="this.style.background='#15803d'" onmouseout="this.style.background='#16a34a'">
                                        <i class="fas fa-file-download"></i> Open / Download Receipt
                                    </a>
                                    `}
                                </div>` : `
                                <div style="color:#64748b;font-size:0.88rem;padding:10px;background:#f8fafc;border-radius:8px;border:1px dashed #cbd5e1;text-align:center;">
                                    <i class="fas fa-image" style="margin-right:6px;opacity:0.5;"></i>No payment screenshot uploaded yet
                                </div>`}
                            </div>
                        </div>` : ''}

                        <div class="form-group" style="margin-bottom: 25px;">
                            <label style="color: #1e293b; font-weight: 700; margin-bottom: 8px;">Flight Details <span style="color: #64748b; font-weight: normal; font-size: 0.85rem;">(visible to customer in email)</span></label>
                            <textarea id="flight_details" rows="3" class="form-control" placeholder="Enter flight number, departure/arrival times, etc..." style="background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); resize: vertical;">${escapeHtml(booking.flight_details || '')}</textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 25px;">
                            <label style="color: #1e293b; font-weight: 700; margin-bottom: 8px;">Admin Notes <span style="color: #64748b; font-weight: normal; font-size: 0.85rem;">(${isVisa ? 'used for Documents Needed message' : 'will be included in email to customer'})</span></label>
                            <textarea id="admin_notes" rows="4" class="form-control" placeholder="${isVisa ? 'Enter required documents here...' : 'Add any notes about this booking...'}" style="background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); resize: vertical;">${escapeHtml(booking.admin_notes || '')}</textarea>
                        </div>
                        ${inquiryDetailsHtml}
                        <div style="background: #f1f5f9; padding: 14px 16px; border-radius: 12px; color: #334155; font-size: 0.85rem; display: flex; align-items: center; gap: 10px; border: 1px solid #e2e8f0;">
                            <i class="fas fa-envelope-circle-check" style="font-size: 1.1rem; color: #64748b;"></i>
                            <span>${isVisa ? 'Changing the assistance will send an email notification to the customer.' : 'Changing the booking status will send an email notification to the customer.'}</span>
                        </div>
                    </div>
                `;
                    document.getElementById('modal-title').innerText = isVisa ? 'Edit Visa Assistance' : 'Edit Booking';
                    document.getElementById('edit-id').value = id;
                    document.getElementById('edit-booking-number').value = booking.booking_number || bookingNumber || '';
                    document.getElementById('edit-type').value = 'booking';
                    document.getElementById('form-fields').innerHTML = fields;
                    document.getElementById('editModal').classList.add('active');
                    document.querySelector('#editForm button').style.display = 'block';
                } else {
                    showNotification(data.message || 'Error loading booking details', 'error');
                }
            } catch (error) {
                console.error('Error fetching booking:', error);
                showNotification('Network error. Please try again. Details: ' + error.message, 'error');
            }
        }

        // Add this function to refresh bookings list
        function refreshBookingsTable() {
            const bookingsPage = document.getElementById('bookings-page');
            if (bookingsPage && bookingsPage.style.display !== 'none') {
                location.reload();
            }
        }

        function deleteBooking(event, id, bookingNumber = '') {
            if (event && typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
            const bookingId = parseInt(id, 10);
            const bookingNumberValue = String(bookingNumber || '').trim();

            if ((isNaN(bookingId) || bookingId < 0) && !bookingNumberValue) {
                Swal.fire('Error', 'Invalid booking ID. Please refresh the page and try again.', 'error');
                return;
            }

            Swal.fire({
                title: 'Move Booking to Trash',
                html: `This booking will be moved to Trash. It can be restored later and will be permanently removed automatically after 30 days.`,
                iconHtml: '<div class="custom-declined-icon"></div>',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Move to Trash',
                reverseButtons: true,
                customClass: {
                    icon: 'no-border-icon',
                    popup: 'modern-modal-popup',
                    cancelButton: 'swal-custom-cancel',
                    confirmButton: 'swal2-confirm'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    const formData = new URLSearchParams();
                    formData.append('action', 'delete_booking');
                    if (bookingId > 0) {
                        formData.append('id', bookingId);
                    }
                    if (bookingNumberValue) {
                        formData.append('booking_number', bookingNumberValue);
                    }

                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    })
                        .then(async response => {
                            const text = await response.text();
                            let data = null;

                            if (text) {
                                try {
                                    data = JSON.parse(text);
                                } catch (e) {
                                    data = null;
                                }
                            }

                            console.debug('delete_booking raw response:', text, data);
                            if (response.ok && (!data || data.success !== false)) {
                                Swal.fire('Trashed!', (data && data.message) || 'Booking moved to Trash', 'success').then(() => location.reload());
                            } else {
                                const message = data && data.message ? data.message : (text || 'Unable to delete booking. Please try again.');
                                Swal.fire('Error', message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Delete booking error:', error);
                            Swal.fire('Error', 'Unable to delete booking. Please try again.', 'error');
                        });
                }
            });
        }

        function restoreBooking(event, id, bookingNumber = '') {
            if (event && typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
            const bookingId = parseInt(id, 10);
            const bookingNumberValue = String(bookingNumber || '').trim();
            if ((isNaN(bookingId) || bookingId < 0) && !bookingNumberValue) {
                Swal.fire('Error', 'Invalid booking ID. Please refresh the page and try again.', 'error');
                return;
            }

            Swal.fire({
                title: 'Restore Booking',
                html: 'This booking will be restored from Trash back into the active bookings list.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Restore Booking',
                reverseButtons: true,
                customClass: {
                    cancelButton: 'swal-custom-cancel',
                    confirmButton: 'swal2-confirm'
                }
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    const formData = new URLSearchParams();
                    formData.append('action', 'restore_booking');
                    if (bookingId > 0) {
                        formData.append('id', bookingId);
                    }
                    if (bookingNumberValue) {
                        formData.append('booking_number', bookingNumberValue);
                    }

                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    })
                        .then(async response => {
                            const text = await response.text();
                            let data = null;
                            if (text) {
                                try {
                                    data = JSON.parse(text);
                                } catch (e) {
                                    data = null;
                                }
                            }
                            console.debug('restore_booking raw response:', text, data);
                            if (response.ok && (!data || data.success !== false)) {
                                Swal.fire('Restored!', (data && data.message) || 'Booking restored successfully', 'success').then(() => location.reload());
                            } else {
                                const message = data && data.message ? data.message : (text || 'Unable to restore booking. Please try again.');
                                Swal.fire('Error', message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Restore booking error:', error);
                            Swal.fire('Error', 'Unable to restore booking. Please try again.', 'error');
                        });
                }
            });
        }

        function purgeBooking(event, id, bookingNumber = '') {
            if (event && typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
            const bookingId = parseInt(id, 10);
            const bookingNumberValue = String(bookingNumber || '').trim();
            if ((isNaN(bookingId) || bookingId < 0) && !bookingNumberValue) {
                Swal.fire('Error', 'Invalid booking ID. Please refresh the page and try again.', 'error');
                return;
            }

            Swal.fire({
                title: 'Permanently Delete Booking',
                html: 'This booking will be permanently deleted and cannot be restored.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Delete Permanently',
                reverseButtons: true,
                customClass: {
                    cancelButton: 'swal-custom-cancel',
                    confirmButton: 'swal2-confirm'
                }
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    const formData = new URLSearchParams();
                    formData.append('action', 'purge_booking');
                    if (bookingId > 0) {
                        formData.append('id', bookingId);
                    }
                    if (bookingNumberValue) {
                        formData.append('booking_number', bookingNumberValue);
                    }

                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    })
                        .then(async response => {
                            const text = await response.text();
                            let data = null;
                            if (text) {
                                try {
                                    data = JSON.parse(text);
                                } catch (e) {
                                    data = null;
                                }
                            }
                            console.debug('purge_booking raw response:', text, data);
                            if (response.ok && (!data || data.success !== false)) {
                                Swal.fire('Deleted!', (data && data.message) || 'Booking permanently deleted', 'success').then(() => location.reload());
                            } else {
                                const message = data && data.message ? data.message : (text || 'Unable to delete booking. Please try again.');
                                Swal.fire('Error', message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Purge booking error:', error);
                            Swal.fire('Error', 'Unable to delete booking. Please try again.', 'error');
                        });
                }
            });
        }

        function editDestination(id) {
            resetModal();
            fetch(`admin-api.php?action=get_destination&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const dest = data.data;
                        const fields = `
                            <div class="form-group"><label>Name</label><input type="text" id="name" value="${escapeHtml(dest.name)}" required></div>
                            <div class="form-group"><label>Country</label><input type="text" id="country" value="${escapeHtml(dest.country || '')}"></div>
                            <div class="form-group"><label>City</label><input type="text" id="city" value="${escapeHtml(dest.city || '')}"></div>
                            <div class="form-group"><label>Type</label><select id="type"><option value="foreign" ${dest.type === 'foreign' ? 'selected' : ''}>Foreign</option><option value="local" ${dest.type === 'local' ? 'selected' : ''}>Local</option></select></div>
                            <div class="form-group"><label>Description</label><textarea id="description" rows="3">${escapeHtml(dest.description || '')}</textarea></div>
                            <div class="form-group"><label>Active</label><select id="is_active"><option value="1" ${dest.is_active == 1 ? 'selected' : ''}>Active</option><option value="0" ${dest.is_active == 0 ? 'selected' : ''}>Inactive</option></select></div>
                        `;
                        document.getElementById('modal-title').innerText = 'Edit Destination';
                        document.getElementById('edit-id').value = id;
                        document.getElementById('edit-type').value = 'destination';
                        document.getElementById('form-fields').innerHTML = fields;
                        document.getElementById('editModal').classList.add('active');
                        document.querySelector('#editForm button').style.display = 'block';
                    }
                });
        }

        function addDestination() {
            resetModal();
            const fields = `
                <div class="form-group"><label>Name</label><input type="text" id="name" required></div>
                <div class="form-group"><label>Country</label><input type="text" id="country"></div>
                <div class="form-group"><label>City</label><input type="text" id="city"></div>
                <div class="form-group"><label>Type</label><select id="type"><option value="foreign">Foreign</option><option value="local">Local</option></select></div>
                <div class="form-group"><label>Description</label><textarea id="description" rows="3"></textarea></div>
                <div class="form-group"><label>Active</label><select id="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
            `;
            document.getElementById('modal-title').innerText = 'Add Destination';
            document.getElementById('edit-id').value = '';
            document.getElementById('edit-type').value = 'destination_new';
            document.getElementById('form-fields').innerHTML = fields;
            document.getElementById('editModal').classList.add('active');
            document.querySelector('#editForm button').style.display = 'block';
        }

        function deleteDestination(id) {
            Swal.fire({
                title: 'Delete Destination',
                html: `You are about to permanently delete this destination.<br><br><strong style="color: #dc2626;">Warning: All travel packages bound to this destination will also be erased.</strong><br>This action cannot be undone.`,
                iconHtml: '<div class="custom-declined-icon"></div>',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Delete Destination',
                reverseButtons: true,
                customClass: {
                    icon: 'no-border-icon',
                    popup: 'modern-modal-popup',
                    cancelButton: 'swal-custom-cancel',
                    confirmButton: 'swal2-confirm'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_destination&id=${id}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deleted!', 'Destination deleted successfully', 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                }
            });
        }

        function editPackage(id) {
            resetModal();
            fetch(`admin-api.php?action=get_package&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const pkg = data.data;
                        const fields = `
                            <div class="form-group"><label>Destination ID</label><input type="number" id="destination_id" value="${pkg.destination_id}" required></div>
                            <div class="form-group"><label>Package Name</label><input type="text" id="name" value="${escapeHtml(pkg.name)}" required></div>
                            <div class="form-group"><label>Duration (e.g., 5D/4N)</label><input type="text" id="duration" value="${escapeHtml(pkg.duration || '')}"></div>
                            <div class="form-group"><label>Price</label><input type="number" id="price" value="${pkg.price}" step="0.01" required></div>
                            <div class="form-group"><label>Activities Count</label><input type="number" id="activities_count" value="${pkg.activities_count || 0}"></div>
                            <div class="form-group"><label>Active</label><select id="is_active"><option value="1" ${pkg.is_active == 1 ? 'selected' : ''}>Active</option><option value="0" ${pkg.is_active == 0 ? 'selected' : ''}>Inactive</option></select></div>
                        `;
                        document.getElementById('modal-title').innerText = 'Edit Package';
                        document.getElementById('edit-id').value = id;
                        document.getElementById('edit-type').value = 'package';
                        document.getElementById('form-fields').innerHTML = fields;
                        document.getElementById('editModal').classList.add('active');
                        document.querySelector('#editForm button').style.display = 'block';
                    }
                });
        }

        function addPackage() {
            resetModal();
            fetch('admin-api.php?action=get_destinations_list')
                .then(response => response.json())
                .then(data => {
                    let destinationOptions = '<option value="">Select Destination</option>';
                    if (data.success && data.data) {
                        data.data.forEach(dest => {
                            destinationOptions += `<option value="${dest.id}">${escapeHtml(dest.name)}</option>`;
                        });
                    }
                    const fields = `
                        <div class="form-group"><label>Destination</label><select id="destination_id" required>${destinationOptions}</select></div>
                        <div class="form-group"><label>Package Name</label><input type="text" id="name" required></div>
                        <div class="form-group"><label>Duration (e.g., 5D/4N)</label><input type="text" id="duration"></div>
                        <div class="form-group"><label>Price</label><input type="number" id="price" step="0.01" required></div>
                        <div class="form-group"><label>Activities Count</label><input type="number" id="activities_count" value="0"></div>
                        <div class="form-group"><label>Active</label><select id="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                    `;
                    document.getElementById('modal-title').innerText = 'Add Package';
                    document.getElementById('edit-id').value = '';
                    document.getElementById('edit-type').value = 'package_new';
                    document.getElementById('form-fields').innerHTML = fields;
                    document.getElementById('editModal').classList.add('active');
                    document.querySelector('#editForm button').style.display = 'block';
                });
        }

        function deletePackage(id) {
            Swal.fire({
                title: 'Delete Package',
                html: `You are about to permanently delete this travel package.<br><br>This action cannot be undone.`,
                iconHtml: '<div class="custom-declined-icon"></div>',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#e2e8f0',
                confirmButtonText: 'Yes, Delete Package',
                reverseButtons: true,
                customClass: {
                    icon: 'no-border-icon',
                    popup: 'modern-modal-popup',
                    cancelButton: 'swal-custom-cancel',
                    confirmButton: 'swal2-confirm'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_package&id=${id}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deleted!', 'Package deleted successfully', 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        });
                }
            });
        }

        function exportBookings() {
            window.location.href = 'admin-api.php?action=export_bookings';
        }

        function resetModal() {
            document.getElementById('history-container').style.display = 'none';
            document.getElementById('form-fields').innerHTML = '';
            document.getElementById('edit-id').value = '';
            document.getElementById('edit-type').value = '';
            const saveBtn = document.querySelector('#editForm .save-btn');
            if (saveBtn) {
                saveBtn.style.display = 'block';
                saveBtn.disabled = false;
                saveBtn.innerHTML = 'Save Changes';
            }
        }
        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
            resetModal();
        }

        function escapeForJsString(text) {
            return String(text ?? '')
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'")
                .replace(/\r/g, '\\r')
                .replace(/\n/g, '\\n');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        function updateEditTotal() {
            const travelers = parseInt(document.getElementById('number_of_travelers')?.value || 1);
            const price = parseFloat(document.getElementById('price_per_person')?.value || 0);
            const totalInput = document.getElementById('total_amount');
            if (totalInput) {
                totalInput.value = (travelers * price).toFixed(2);
            }
        }
        document.getElementById('editModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });

        function exportUsers() {
            window.location.href = 'admin-api.php?action=export_users';
        }

        function toggleGroup(groupId, rowEl) {
            const rows = document.querySelectorAll(`.user-booking-row[data-group="${groupId}"]`);
            const isOpen = rowEl.classList.contains('open');
            rows.forEach(r => r.classList.toggle('visible', !isOpen));
            rowEl.classList.toggle('open', !isOpen);
        }

        if (adminRole === 'super_admin') {
            loadPendingRequests();
            setInterval(() => {
                const pendingPage = document.getElementById('pending-requests-page');
                if (pendingPage && pendingPage.style.display !== 'none') loadPendingRequests();
                const menuPendingCount = document.getElementById('menuPendingCount');
                if (menuPendingCount) {
                    fetch('admin-api.php?action=get_pending_requests')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) menuPendingCount.textContent = data.data.length;
                        });
                }
            }, 30000);
        }

        // Update admin role (only for super admin)
        function updateAdminRole(userId, newRole) {
            if (adminRole !== 'super_admin') {
                showNotification('❌ Permission denied. Only Super Admin can change roles.', 'error');
                return;
            }

            fetch('admin-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_admin_role&id=${userId}&role=${newRole}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Role Updated',
                            text: 'Admin role updated successfully!',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification('❌ Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('❌ Error updating role', 'error');
                });
        }

        // Add event listeners for role select dropdowns
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.role-select').forEach(select => {
                select.addEventListener('change', function () {
                    const userId = this.dataset.id;
                    const newRole = this.value;
                    const currentRole = this.dataset.current;

                    if (currentRole === newRole) return;

                    let confirmHtml = `You are about to modify the access level of this account.<br>This user will now operate with <strong style="color: #000;">${newRole.replace('_', ' ').toUpperCase()}</strong> privileges.`;
                    let iconType = 'warning';
                    let confirmButtonText = 'Yes, Change Role';

                    if (newRole === 'super_admin') {
                        confirmHtml = `⚠️ <strong>WARNING:</strong> You are about to grant <strong style="color: #0f172a;">SUPER ADMIN</strong> privileges to this user.<br><br>They will instantly gain unrestricted access to all aspects of the system. Do you wish to proceed?`;
                        iconType = 'error';
                        confirmButtonText = 'Yes, Grant Super Admin';
                    }

                    Swal.fire({
                        title: 'Confirm Role Change',
                        html: confirmHtml,
                        icon: iconType,
                        showCancelButton: true,
                        confirmButtonColor: newRole === 'super_admin' ? '#d33' : '#3085d6',
                        cancelButtonColor: '#e2e8f0',
                        confirmButtonText: confirmButtonText,
                        customClass: {
                            cancelButton: 'swal-custom-cancel',
                            confirmButton: 'swal2-confirm'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                            updateAdminRole(userId, newRole);
                        } else {
                            // Reset select to original value
                            select.value = currentRole;
                        }
                    });
                });
            });
        });
        // Sidebar Toggle
        // --- Advanced Bookings Dashboard Logic ---
        let bookings = <?php
        $stmt = $pdo->prepare("SELECT * FROM bookings ORDER BY created_at DESC");
        $stmt->execute();
        $rawBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rawBookings as &$b) {
            $b['is_trashed'] = !empty($b['deleted_at']) && $b['deleted_at'] !== '0000-00-00 00:00:00';

            // Identify if it's explicitly a Visa Service
            $b['is_visa_service'] = ($b['destination_name'] === 'Visa Assistance' || stripos($b['package_name'] ?? '', 'Visa') !== false);

            // Determine Visa Status - Show status if it's a visa service OR if a status has been manually set
            if ($b['is_visa_service'] || (!empty($b['visa_status']) && $b['visa_status'] !== 'N/A')) {
                $b['visa'] = !empty($b['visa_status']) ? strtoupper($b['visa_status']) : 'PENDING';
                if ($b['visa'] === 'PAID') {
                    $b['visa'] = 'APPROVED';
                }
            } else {
                $b['visa'] = 'N/A';
            }

            // Use real data from database consistently
            if (!isset($b['contact_number']) || !$b['contact_number'] || $b['contact_number'] === 'N/A' || $b['contact_number'] === '') {
                $b['contact_number'] = $b['phone'] ?? 'N/A';
            }
        }
        echo json_encode($rawBookings);
        ?>;



        let filteredBookings = [...bookings];
        let currentSort = { key: 'id', direction: 'desc' };
        let currentFilter = { key: null, value: null };
        let filterDate = null;

        function renderTable() {
            const body = document.getElementById('bookingsBody');
            if (!body) return;

            body.innerHTML = '';

            filteredBookings.forEach(booking => {
                const tr = document.createElement('tr');
                const viewedList = JSON.parse(localStorage.getItem('viewed_bookings') || '[]');
                const isViewed = viewedList.includes(String(booking.booking_number));

                // Identify if the booking is upcoming (within 3 days)
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const limit = new Date();
                limit.setDate(today.getDate() + 3);
                limit.setHours(23, 59, 59, 999);
                const tDate = new Date(booking.travel_date);
                const isUpcoming = tDate >= today && tDate <= limit && booking.booking_status === 'confirmed';
                const isInquiryView = (currentFilter.key === 'service_type' && currentFilter.value === 'INQUIRIES') || (currentFilter.key === 'completed_type' && currentFilter.value === 'INQUIRIES');

                tr.innerHTML = `
                    <td style="padding: 12px 15px; font-weight: 700; color: var(--text-main); vertical-align: top;" class="${isViewed ? 'highlighted-cell' : ''}">
                        <div style="display: flex; flex-direction: column; align-items: flex-start;">
                            ${isViewed ? `<span class="viewed-badge" ondblclick="unmarkViewed('${booking.booking_number}', event)" title="Double-click to dismiss" style="cursor:pointer;"><i class="fas fa-eye"></i> VIEWED</span>` : ''}
                            <span>${escapeHtml(booking.contact_number)}</span>
                        </div>
                    </td>
                    <td class="customer-info ${isViewed ? 'highlighted-cell' : ''}" style="padding: 12px 15px; vertical-align: top;">
                        <span style="font-weight:700; color: ${isViewed ? '#10b981' : '#4f46e5'}; display:block;">${escapeHtml(booking.full_name)}</span>
                    </td>
                    <td style="vertical-align: top;">
                        ${isUpcoming ? `<div class="upcoming-badge"><i class="fas fa-clock"></i> UPCOMING</div>` : ''}
                        <strong>${booking.booking_number}</strong>
                    </td>
                    <td style="vertical-align: top;">
                        <div style="line-height: 1.2;">
                            <div style="font-weight: 700; color: #1e293b;">${new Date(booking.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
                            <div style="font-size: 0.75rem; color: #64748b;">${new Date(booking.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}</div>
                        </div>
                    </td>
                    <td style="vertical-align: top;">${escapeHtml(booking.package_name || booking.destination_name || 'N/A')}</td>
                    <td style="vertical-align: top;">
                        <div style="font-weight: 600; color: #475569;">
                            ${new Date(booking.travel_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                        </div>
                    </td>
                    <td style="vertical-align: top; text-align: center; display: ${isInquiryView ? 'none' : 'table-cell'};">
                        <span class="status-badge ${booking.travel_documents == 1 ? 'status-confirmed' : 'status-pending'}">
                            <i class="fas ${booking.travel_documents == 1 ? 'fa-check-circle' : 'fa-clock'}"></i>
                            ${booking.travel_documents == 1 ? 'PREPARED' : 'PENDING'}
                        </span>
                    </td>
                    <td style="vertical-align: top; text-align: center; display: ${isInquiryView ? 'none' : 'table-cell'};">
                        <span class="status-badge ${booking.visa === 'APPROVED' ? 'status-confirmed' :
                        (booking.visa === 'DECLINED' ? 'status-cancelled' :
                            (booking.visa === 'PAID' ? 'status-paid-visa' : 'status-pending'))
                    }">${booking.visa}</span>
                    </td>
                    <td style="vertical-align: top; text-align: center; display: ${isInquiryView ? 'none' : 'table-cell'}; ${booking.payment_status === 'paid' ? 'cursor: pointer;' : ''}" ${booking.payment_status === 'paid' ? `onclick="showReceiptAlert(${booking.id})"` : ''}>
                        <span class="status-badge ${booking.payment_status === 'paid' ? 'status-confirmed' : 'status-pending'}">${booking.payment_status.toUpperCase()}</span>
                    </td>
                    <td style="vertical-align: top; text-align: center; display: ${isInquiryView ? 'none' : ((currentFilter.key === 'service_type' && currentFilter.value === 'VISA') || (currentFilter.key === 'completed_type' && currentFilter.value === 'VISA') ? 'none' : 'table-cell')};">
                        <span class="status-badge status-${booking.booking_status.toLowerCase()}">${booking.booking_status.toUpperCase()}</span>
                    </td>
                    <td class="action-buttons" style="text-align: center; vertical-align: top;">
                        <button class="view-btn" onclick="viewBookingConfirmation(${booking.id}, '${booking.booking_number}')" title="Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${booking.is_trashed ? `
                            <button class="edit-btn" onclick="restoreBooking(event, ${booking.id}, '${escapeForJsString(booking.booking_number || '')}')" title="Restore">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button type="button" class="delete-btn" style="background:#c92a2a;" onclick="purgeBooking(event, ${booking.id}, '${escapeForJsString(booking.booking_number || '')}')" title="Delete Permanently">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : (!isInquiryView ? `
                            <button class="edit-btn" onclick="editBooking(${booking.id}, '${booking.booking_number}')" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="delete-btn" onclick="deleteBooking(event, ${booking.id}, '${escapeForJsString(booking.booking_number || '')}')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : '')}
                        ${!booking.is_trashed && isFullyCompleted(booking) ? `
                            <div class="status-badge status-completed" style="background:#f0fdf4; color:#16a34a; border: 1px solid #bbf7d0; margin-top: 5px; font-size: 0.65rem; padding: 2px 6px;"><i class="fas fa-check-circle"></i> FINISHED</div>
                        ` : ''}
                    </td>
                `;
                body.appendChild(tr);
            });

            document.getElementById('bookingCountDisplay').innerText = `(${filteredBookings.length})`;
        }

        // Unmark a booking as viewed — removes badge when clicked
        function unmarkViewed(bookingNumber, event) {
            if (event) event.stopPropagation();
            let viewed = JSON.parse(localStorage.getItem('viewed_bookings') || '[]');
            viewed = viewed.filter(id => id !== String(bookingNumber));
            localStorage.setItem('viewed_bookings', JSON.stringify(viewed));
            renderTable();
        }

        function updateBookingStatus(id, status, isStatusAction = false) {
            const formData = new URLSearchParams();
            formData.append('action', 'update_booking');
            formData.append('id', id);
            formData.append('booking_status', status);

            const b = bookings.find(x => x.id == id);
            if (b) {
                formData.append('payment_status', b.payment_status);
                formData.append('admin_notes', b.admin_notes || '');
                formData.append('flight_details', b.flight_details || '');
                formData.append('travel_documents', b.travel_documents);
                formData.append('ready_for_travel', b.ready_for_travel);
            }

            // Visual feedback
            Swal.fire({
                title: 'Processing Status...',
                html: 'Securing administrative records',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                customClass: { popup: 'glass-modal' }
            });

            fetch('admin-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (isStatusAction) {
                            showStatusUpdateAlert(status);
                        } else {
                            showNotification('Status updated', 'success');
                            Swal.close();
                        }

                        const idx = bookings.findIndex(x => x.id == id);
                        if (idx !== -1) {
                            bookings[idx].booking_status = status;

                            // If it's a visa booking, update the display visa status too
                            if (bookings[idx].visa !== 'N/A') {
                                bookings[idx].visa = bookings[idx].visa_status ? bookings[idx].visa_status.toUpperCase() : 'PENDING';
                                if (bookings[idx].visa === 'PAID') bookings[idx].visa = 'APPROVED';
                            }

                            applyFilters();
                            if (typeof updateDashboardStats === 'function') updateDashboardStats();
                            if (typeof renderDashboardTables === 'function') renderDashboardTables();
                        }
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(() => {
                    Swal.fire('Network Error', 'Please verify your connection', 'error');
                });
        }

        function showStatusUpdateAlert(status) {
            let themeColor, icon, title, message;

            if (status === 'confirmed') {
                themeColor = '#10b981';
                icon = 'fa-check-circle';
                title = 'Booking Confirmed';
                message = 'The booking and visa status has been officially <strong>verified and approved</strong>.';
            } else if (status === 'cancelled') {
                themeColor = '#ef4444';
                icon = 'fa-times-circle';
                title = 'Booking Declined';
                message = 'The booking has been <strong>officially declined</strong> and marked as cancelled.';
            } else if (status === 'completed') {
                themeColor = '#6366f1';
                icon = 'fa-check-double';
                title = 'Booking Completed';
                message = 'The service has been <strong>marked as finished</strong>. Great job!';
            } else {
                showNotification('Status updated', 'success');
                Swal.close();
                return;
            }

            Swal.fire({
                title: `<span style="color: ${themeColor}; font-weight: 800;">${title}</span>`,
                html: `
                    <div style="margin-top: 10px;">
                        <i class="fas ${icon}" style="font-size: 4rem; color: ${themeColor}; margin-bottom: 25px; display: block; animation: zoomIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);"></i>
                        <p style="font-size: 1.1rem; color: #475569; margin-bottom: 20px; line-height: 1.5;">
                            ${message}
                        </p>
                        <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 15px;">
                            System records have been updated successfully.
                        </div>
                    </div>
                `,
                timer: 2500,
                timerProgressBar: true,
                showConfirmButton: false,
                customClass: { popup: 'glass-modal' }
            });
        }

        function updateDashboardStats() {
            if (!Array.isArray(bookings)) return;

            const total = bookings.length;
            const pending = bookings.filter(b => b.booking_status.toLowerCase() === 'pending').length;
            const confirmed = bookings.filter(b => b.booking_status.toLowerCase() === 'confirmed').length;
            const revenue = bookings
                .filter(b => b.payment_status.toLowerCase() === 'paid')
                .reduce((sum, b) => sum + parseFloat(b.total_amount || 0), 0);

            const map = {
                'stat-total-bookings': total,
                'stat-pending-bookings': pending,
                'stat-confirmed-bookings': confirmed,
                'stat-total-revenue': '₱' + revenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            };

            for (const [id, val] of Object.entries(map)) {
                const el = document.getElementById(id);
                if (el) el.innerText = val;
            }
        }

        function toggleSort(key) {
            // Set sorting per user request
            currentSort.key = key;
            currentSort.direction = 'asc';

            // Clear specific filters when sorting to ensure data "pops up" immediately
            currentFilter = { key: null, value: null };
            filterDate = null;

            document.querySelectorAll('.filter-btn, .sub-filter-btn').forEach(b => {
                b.classList.remove('active');
            });

            if (key === 'travel_date') {
                document.getElementById('sortDateBtn').classList.add('active');
            }

            // Reset calendar button label if it was showing a specific date
            const dateBtn = document.getElementById('sortDateBtn');
            if (dateBtn) dateBtn.innerHTML = '<i class="fas fa-calendar-alt"></i> Calendar';

            applyFilters();
        }

        function toggleFilterGroup(group) {
            const row = document.getElementById('sub-filter-row');
            const options = document.getElementById(group + '-sub-options');
            const btn = document.getElementById(group + 'GroupBtn');
            const icon = btn ? btn.querySelector('i') : null;

            // Clear all highlights from ALL buttons
            document.querySelectorAll('.filter-btn, .sub-filter-btn').forEach(b => b.classList.remove('active'));

            // Other groups to hide
            const allGroups = ['service', 'completed'];
            allGroups.filter(g => g !== group).forEach(g => {
                const otherOptions = document.getElementById(g + '-sub-options');
                const otherBtn = document.getElementById(g + 'GroupBtn');
                const otherIcon = otherBtn ? otherBtn.querySelector('i') : null;
                if (otherOptions) otherOptions.style.display = 'none';
                if (otherIcon) otherIcon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                if (otherBtn) otherBtn.classList.remove('active');
            });

            // Toggle current group
            if (options && options.style.display === 'flex') {
                options.style.display = 'none';
                if (row) row.style.display = 'none';
                if (icon) icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                if (btn) btn.classList.remove('active');

                // When closing a group, return to default "All Active" view
                resetFilters();
            } else {
                if (row) row.style.display = 'flex';
                if (options) options.style.display = 'flex';
                if (icon) icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                if (btn) btn.classList.add('active');

                // Automatically trigger "ALL" filter for this category
                if (group === 'service') {
                    setFilter('service_type', 'ALL');
                } else if (group === 'completed') {
                    setFilter('completed_type', 'ALL');
                }
            }

            applyFilters();
        }

        function setFilter(key, value) {
            if (currentFilter.key === key && currentFilter.value === value) {
                resetFilters();
                return;
            }

            currentFilter = { key, value };
            document.querySelectorAll('.filter-btn, .sub-filter-btn').forEach(b => b.classList.remove('active'));

            // Hide sub-filter row if we're not filtering by something that has sub-options, 
            // unless we want the sub-filter highlight to persist.
            // Actually, if we selected a sub-filter, we keep the row open but highlight the sub-btn.

            if (key === 'service_type') {
                const groupBtn = document.getElementById('serviceGroupBtn');
                if (groupBtn) {
                    groupBtn.classList.add('active');
                    const icon = groupBtn.querySelector('i');
                    if (icon) icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                }
                document.getElementById('sub-filter-row').style.display = 'flex';
                document.getElementById('service-sub-options').style.display = 'flex';

                if (value === 'ALL') document.getElementById('serviceAllBtn').classList.add('active');
                if (value === 'VISA') document.getElementById('serviceVisaBtn').classList.add('active');
                if (value === 'TOUR') document.getElementById('serviceTourBtn').classList.add('active');
                if (value === 'INQUIRIES') document.getElementById('serviceInquiriesBtn').classList.add('active');
            }

            if (key === 'trashed') {
                const trashBtn = document.getElementById('trashFilterBtn');
                if (trashBtn) {
                    trashBtn.classList.add('active');
                }
                document.getElementById('sub-filter-row').style.display = 'none';
                document.getElementById('service-sub-options').style.display = 'none';
                document.getElementById('completed-sub-options').style.display = 'none';
            }

            if (key === 'completed_type') {
                const groupBtn = document.getElementById('completedGroupBtn');
                if (groupBtn) {
                    groupBtn.classList.add('active');
                    const icon = groupBtn.querySelector('i.fa-chevron-down, i.fa-chevron-up');
                    if (icon) icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                }
                document.getElementById('sub-filter-row').style.display = 'flex';
                document.getElementById('completed-sub-options').style.display = 'flex';

                if (value === 'ALL') document.getElementById('completedAllBtn').classList.add('active');
                if (value === 'VISA') document.getElementById('completedVisaBtn').classList.add('active');
                if (value === 'TOUR') document.getElementById('completedTourBtn').classList.add('active');
                if (value === 'INQUIRIES') document.getElementById('completedInquiriesBtn').classList.add('active');
            }

            if (key === 'type' && value === 'upcoming') {
                const btn = document.getElementById('upcomingFilterBtn');
                if (btn) btn.classList.add('active');
            }

            if (key === 'booking_status') {
                if (value === 'COMPLETED') {
                    const btn = document.getElementById('statusDoneBtn');
                    if (btn) btn.classList.add('active');
                    document.getElementById('sub-filter-row').style.display = 'none';
                }
            }

            applyFilters();
        }

        function handleSearchInput() {
            const input = document.getElementById('filterSearchInput');
            const clearBtn = document.getElementById('searchClearBtn');
            if (input.value.length > 0) {
                clearBtn.style.display = 'flex';
            } else {
                clearBtn.style.display = 'none';
            }
            applyFilters();
        }

        function clearSearch() {
            const input = document.getElementById('filterSearchInput');
            input.value = '';
            document.getElementById('searchClearBtn').style.display = 'none';
            input.focus();
            applyFilters();
        }


        // Helper for case-insensitive search
        function stripos_js(haystack, needle) {
            if (!haystack || !needle) return false;
            return haystack.toLowerCase().indexOf(needle.toLowerCase()) !== -1;
        }

        // Logic Source of Truth: When is a booking truly "FINISHED"?
        function isFullyCompleted(b) {
            // 1. Status Check (Must be 'completed')
            const statusMatch = String(b.booking_status || '').toLowerCase() === 'completed';

            // 2. Payment Check (Must be 'paid')
            const paymentMatch = String(b.payment_status || '').toLowerCase() === 'paid';

            // 3. Tracking Check (Both steps must be checked)
            const trackingMatch = Number(b.travel_documents) === 1 && Number(b.ready_for_travel) === 1;

            // 4. Visa Check (Must be 'APPROVED' or 'N/A')
            // We check visa_status directly to catch cases where a tour package still has a pending visa entry
            const rawVisa = String(b.visa_status || b.visa || 'N/A').toUpperCase();
            const visaMatch = rawVisa === 'APPROVED' || rawVisa === 'N/A';

            return statusMatch && paymentMatch && trackingMatch && visaMatch;
        }

        function applyFilters() {
            const search = document.getElementById('filterSearchInput').value.toLowerCase();

            // 1. Initial Data Source
            filteredBookings = [...bookings];

            // Handle trashed booking view first
            if (currentFilter.key === 'trashed') {
                filteredBookings = filteredBookings.filter(b => b.is_trashed);
            } else {
                filteredBookings = filteredBookings.filter(b => !b.is_trashed);

                // 2. Determine Context (Completed Archive vs Active Work)
                const isCompletedContext =
                    (currentFilter.key === 'completed_type') ||
                    (currentFilter.key === 'booking_status' && currentFilter.value === 'COMPLETED');

                // 3. Apply the "Single Source of Truth" Filter
                if (isCompletedContext) {
                    // ARCHIVE: Strictly show only truly finished bookings
                    filteredBookings = filteredBookings.filter(b => isFullyCompleted(b));
                } else {
                    // ACTIVE QUEUES: Hide everything that is truly finished
                    filteredBookings = filteredBookings.filter(b => !isFullyCompleted(b));

                    // In the default ALL active view, keep inquiries out of the displayed list
                    if (!currentFilter.key || (currentFilter.key === 'service_type' && currentFilter.value === 'ALL')) {
                        filteredBookings = filteredBookings.filter(b => b.payment_method !== 'Inquiry Only');
                    }
                }

                // Apply secondary filters for active views
                if (currentFilter.key === 'service_type') {
                    if (currentFilter.value === 'VISA') {
                        filteredBookings = filteredBookings.filter(b => b.is_visa_service && b.payment_method !== 'Inquiry Only');
                    } else if (currentFilter.value === 'TOUR') {
                        filteredBookings = filteredBookings.filter(b => !b.is_visa_service && b.payment_method !== 'Inquiry Only');
                    } else if (currentFilter.value === 'INQUIRIES') {
                        filteredBookings = filteredBookings.filter(b => b.payment_method === 'Inquiry Only');
                    }
                } else if (currentFilter.key === 'booking_status' && currentFilter.value !== 'COMPLETED') {
                    filteredBookings = filteredBookings.filter(b => b.booking_status.toUpperCase() === currentFilter.value);
                } else if (currentFilter.type === 'upcoming') {
                    // Robust local date comparison
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const day = String(now.getDate()).padStart(2, '0');
                    const todayStr = `${year}-${month}-${day}`;

                    const limitDate = new Date();
                    limitDate.setDate(now.getDate() + 3);
                    const ly = limitDate.getFullYear();
                    const lm = String(limitDate.getMonth() + 1).padStart(2, '0');
                    const ld = String(limitDate.getDate()).padStart(2, '0');
                    const limitStr = `${ly}-${lm}-${ld}`;

                    filteredBookings = filteredBookings.filter(b => {
                        if (!b.travel_date) return false;
                        const bDateStr = b.travel_date.split(' ')[0];
                        const status = String(b.booking_status || '').toLowerCase();
                        const isDateInRange = bDateStr >= todayStr && bDateStr <= limitStr;
                        const isRightStatus = (status === 'confirmed' || status === 'completed');
                        return isDateInRange && isRightStatus;
                    });
                }
            }

            // Additional filtering for Completed sub-types
            if (currentFilter.key === 'completed_type') {
                if (currentFilter.value === 'VISA') {
                    filteredBookings = filteredBookings.filter(b => b.is_visa_service && b.payment_method !== 'Inquiry Only');
                } else if (currentFilter.value === 'TOUR') {
                    filteredBookings = filteredBookings.filter(b => !b.is_visa_service && b.payment_method !== 'Inquiry Only');
                } else if (currentFilter.value === 'INQUIRIES') {
                    filteredBookings = filteredBookings.filter(b => b.payment_method === 'Inquiry Only');
                }
            }

            // 4. Global Search Filter (applies on top of the above)
            if (search) {
                filteredBookings = filteredBookings.filter(b =>
                    stripos_js(b.full_name, search) ||
                    stripos_js(b.booking_number, search) ||
                    stripos_js(b.contact_number, search)
                );
            }

            // 4. UI Updates: Title, Icon, and Column Headers
            const tableTitleText = document.getElementById('tableTitleText');
            const tableTitleIcon = document.getElementById('tableTitleIcon');
            const trashNotice = document.getElementById('trashNotice');
            if (tableTitleText && tableTitleIcon) {
                // hide notice by default; show only when viewing trashed bookings
                if (trashNotice) trashNotice.style.display = 'none';
                if (currentFilter.key === 'trashed') {
                    tableTitleText.innerText = 'Trash: Deleted Bookings';
                    tableTitleIcon.className = 'fas fa-trash';
                    if (trashNotice) trashNotice.style.display = 'block';
                } else if (currentFilter.key === 'service_type') {
                    if (currentFilter.value === 'VISA') {
                        tableTitleText.innerText = 'Service Availed: Visa Assistance';
                        tableTitleIcon.className = 'fas fa-passport';
                    } else if (currentFilter.value === 'TOUR') {
                        tableTitleText.innerText = 'Service Availed: Tour Packages';
                        tableTitleIcon.className = 'fas fa-map-marked-alt';
                    } else if (currentFilter.value === 'INQUIRIES') {
                        tableTitleText.innerText = 'Service Availed: Inquiries Only';
                        tableTitleIcon.className = 'fas fa-question-circle';
                    } else {
                        tableTitleText.innerText = 'Service Availed: All';
                        tableTitleIcon.className = 'fas fa-list-ul';
                    }
                } else if (currentFilter.key === 'completed_type') {
                    if (currentFilter.value === 'VISA') {
                        tableTitleText.innerText = 'Completed: Visa Assistance';
                        tableTitleIcon.className = 'fas fa-check-double';
                    } else if (currentFilter.value === 'TOUR') {
                        tableTitleText.innerText = 'Completed: Tour Packages';
                        tableTitleIcon.className = 'fas fa-check-double';
                    } else if (currentFilter.value === 'INQUIRIES') {
                        tableTitleText.innerText = 'Completed: Inquiries Only';
                        tableTitleIcon.className = 'fas fa-check-double';
                    } else {
                        tableTitleText.innerText = 'Completed: All';
                        tableTitleIcon.className = 'fas fa-check-double';
                    }
                } else if (currentFilter.type === 'upcoming') {
                    tableTitleText.innerText = 'Upcoming Travels (Next 3 Days)';
                    tableTitleIcon.className = 'fas fa-clock';
                } else {
                    tableTitleText.innerText = 'All Active Bookings';
                    tableTitleIcon.className = 'fas fa-list-ul';
                }
            }

            // Header labels (Booking # vs Assistance #, etc.)
            const headerBookingNumber = document.getElementById('headerBookingNumber');
            const headerTravelDate = document.getElementById('headerTravelDate');
            const headerTravelDocs = document.getElementById('headerTravelDocs');
            const headerVisa = document.getElementById('headerVisa');
            const headerPayment = document.getElementById('headerPayment');
            const headerStatus = document.getElementById('headerStatus');
            const isVisaView = (currentFilter.key === 'service_type' && currentFilter.value === 'VISA') || (currentFilter.key === 'completed_type' && currentFilter.value === 'VISA');
            const isInquiryView = (currentFilter.key === 'service_type' && currentFilter.value === 'INQUIRIES') || (currentFilter.key === 'completed_type' && currentFilter.value === 'INQUIRIES');
            const showStatusHeader = !isVisaView && !isInquiryView;
            const showInfoColumns = !isInquiryView;

            if (headerTravelDocs) {
                headerTravelDocs.style.display = showInfoColumns ? 'table-cell' : 'none';
            }
            if (headerVisa) {
                headerVisa.style.display = showInfoColumns ? 'table-cell' : 'none';
            }
            if (headerPayment) {
                headerPayment.style.display = showInfoColumns ? 'table-cell' : 'none';
            }
            if (headerStatus) {
                headerStatus.style.display = showStatusHeader ? 'table-cell' : 'none';
            }

            if (headerBookingNumber && headerTravelDate && headerTravelDocs) {
                if (isVisaView) {
                    headerBookingNumber.innerText = 'ASSISTANCE #';
                    headerTravelDate.innerText = 'ASSISTANCE DATE';
                    headerTravelDocs.innerText = 'VISA DOCUMENTS';
                } else {
                    headerBookingNumber.innerText = 'SERVICE #';
                    headerTravelDate.innerText = 'TRAVEL DATE';
                    headerTravelDocs.innerText = 'TRAVEL DOCUMENTS';
                }
            }

            // Update ACTIONS header for inquiry view
            const headerActions = document.getElementById('headerActions');
            if (headerActions) {
                headerActions.innerText = isInquiryView ? 'ACTION' : 'ACTIONS';
            }

            // 5. Calendar Date Filter
            if (filterDate) {
                filteredBookings = filteredBookings.filter(b => b.travel_date.startsWith(filterDate));
            }

            // 6. Sorting
            const sortKey = currentSort.key || 'created_at';
            filteredBookings.sort((a, b) => {
                let valA, valB;
                if (sortKey === 'name') {
                    valA = (a.full_name || '').toLowerCase();
                    valB = (b.full_name || '').toLowerCase();
                } else if (sortKey === 'destination') {
                    valA = (a.destination_name || '').toLowerCase();
                    valB = (b.destination_name || '').toLowerCase();
                } else if (sortKey === 'id' || sortKey === 'created_at') {
                    valA = a.created_at ? new Date(a.created_at).getTime() : parseInt(a.id || 0);
                    valB = b.created_at ? new Date(b.created_at).getTime() : parseInt(b.id || 0);
                } else if (sortKey === 'travel_date') {
                    valA = new Date(a.travel_date).getTime();
                    valB = new Date(b.travel_date).getTime();
                } else {
                    valA = 0; valB = 0;
                }
                const result = valA < valB ? -1 : (valA > valB ? 1 : 0);
                return currentSort.direction === 'desc' ? -result : result;
            });

            renderTable();
            updateFilterBadges();
        }

        function updateFilterBadges() {
            const counts = {
                allActive: 0,
                tourActive: 0,
                visaActive: 0,
                inquiriesActive: 0,
                allCompleted: 0,
                tourCompleted: 0,
                visaCompleted: 0,
                inquiriesCompleted: 0,
                upcoming: 0,
                trashed: 0
            };

            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const todayStr = `${year}-${month}-${day}`;

            const limitDate = new Date();
            limitDate.setDate(now.getDate() + 3);
            const ly = limitDate.getFullYear();
            const lm = String(limitDate.getMonth() + 1).padStart(2, '0');
            const ld = String(limitDate.getDate()).padStart(2, '0');
            const limitStr = `${ly}-${lm}-${ld}`;
            const viewedList = JSON.parse(localStorage.getItem('viewed_bookings') || '[]');

            bookings.forEach(b => {
                const isCompleted = isFullyCompleted(b);
                const isVisa = b.is_visa_service;
                const isInquiry = b.payment_method === 'Inquiry Only';
                const isViewed = viewedList.includes(String(b.booking_number));
                const isTrashed = b.is_trashed;

                if (isTrashed) {
                    counts.trashed++;
                    return;
                }

                if (isCompleted) {
                    counts.allCompleted++;
                    if (isInquiry) counts.inquiriesCompleted++;
                    else if (isVisa) counts.visaCompleted++;
                    else counts.tourCompleted++;
                } else {
                    if (!isViewed) {
                        counts.allActive++;
                        if (isInquiry) counts.inquiriesActive++;
                        else if (isVisa) counts.visaActive++;
                        else counts.tourActive++;
                    }

                    // Upcoming check
                    if (b.travel_date) {
                        const bDateStr = b.travel_date.split(' ')[0];
                        const status = String(b.booking_status || '').toLowerCase();
                        if (bDateStr >= todayStr && bDateStr <= limitStr && (status === 'confirmed' || status === 'completed')) {
                            counts.upcoming++;
                        }
                    }
                }
            });

            // Update UI
            const updateCount = (id, count) => {
                const btn = document.getElementById(id);
                if (btn) {
                    const el = btn.querySelector('.badge-count');
                    if (el) {
                        el.innerText = count;
                        el.style.display = count > 0 ? 'inline-flex' : 'none';
                    }
                }
            };

            updateCount('serviceAllBtn', counts.allActive);
            updateCount('serviceTourBtn', counts.tourActive);
            updateCount('serviceVisaBtn', counts.visaActive);
            updateCount('serviceInquiriesBtn', counts.inquiriesActive);
            updateCount('completedAllBtn', counts.allCompleted);
            updateCount('completedTourBtn', counts.tourCompleted);
            updateCount('completedVisaBtn', counts.visaCompleted);
            updateCount('completedInquiriesBtn', counts.inquiriesCompleted);
            updateCount('trashFilterBtn', counts.trashed);
            updateCount('upcomingFilterBtn', counts.upcoming);

            // Update the main sidebar Bookings badge dynamically
            const sidebarBooking = document.getElementById('sidebarBookingCount');
            if (sidebarBooking) {
                sidebarBooking.innerText = counts.allActive;
                sidebarBooking.style.display = counts.allActive > 0 ? 'inline-flex' : 'none';
            }

            // Sync bookingCountDisplay with the active filter badge count
            // so the table title count ALWAYS matches the filter button badge
            const countDisplay = document.getElementById('bookingCountDisplay');
            if (countDisplay) {
                let activeCount = filteredBookings.length;

                // Map the active filter to its pre-computed count for perfect badge ↔ title sync
                if (currentFilter.key === 'service_type') {
                    if (currentFilter.value === 'TOUR') activeCount = counts.tourActive;
                    else if (currentFilter.value === 'VISA') activeCount = counts.visaActive;
                    else if (currentFilter.value === 'INQUIRIES') activeCount = counts.inquiriesActive;
                    else activeCount = counts.allActive;   // ALL
                } else if (currentFilter.key === 'completed_type') {
                    if (currentFilter.value === 'TOUR') activeCount = counts.tourCompleted;
                    else if (currentFilter.value === 'VISA') activeCount = counts.visaCompleted;
                    else if (currentFilter.value === 'INQUIRIES') activeCount = counts.inquiriesCompleted;
                    else activeCount = counts.allCompleted; // ALL completed
                } else if (currentFilter.type === 'upcoming') {
                    activeCount = counts.upcoming;
                } else if (currentFilter.key === 'trashed') {
                    activeCount = counts.trashed;
                } else {
                    activeCount = counts.allActive; // default: all active
                }

                countDisplay.innerText = `(${activeCount})`;
            }
        }

        function resetFilters() {
            currentSort = { key: 'id', direction: 'desc' };
            currentFilter = { key: null, value: null };
            filterDate = null;

            const searchInput = document.getElementById('filterSearchInput');
            if (searchInput) searchInput.value = '';

            document.querySelectorAll('.filter-btn, .sub-filter-btn').forEach(b => b.classList.remove('active'));

            const subFilterRow = document.getElementById('sub-filter-row');
            if (subFilterRow) subFilterRow.style.display = 'flex';

            ['service', 'visa', 'completed'].forEach(group => {
                const options = document.getElementById(group + '-sub-options');
                const btn = document.getElementById(group + 'GroupBtn');
                if (options) {
                    if (group === 'service') {
                        options.style.display = 'flex';
                    } else {
                        options.style.display = 'none';
                    }
                }
                if (btn) {
                    btn.classList.remove('active');
                    const icon = btn.querySelector('i');
                    if (icon) icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                }
            });

            const resetBtn = document.getElementById('resetFiltersBtn');
            if (resetBtn) resetBtn.classList.add('active');

            const dateBtn = document.getElementById('sortDateBtn');
            if (dateBtn) dateBtn.innerHTML = '<i class="fas fa-calendar-alt"></i> CALENDAR';

            applyFilters();
        }

        // Calendar Logic
        let viewDate = new Date();
        function openCalendar() { document.getElementById('calendarModal').classList.add('active'); renderCalendar(); }
        function closeCalendar() { document.getElementById('calendarModal').classList.remove('active'); }

        function renderCalendar() {
            const grid = document.getElementById('calendarGrid');
            const monthLabel = document.getElementById('currentMonthYearView');
            grid.innerHTML = '';

            // Add Day Labels to the same grid for perfect alignment
            const dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayLabels.forEach(day => {
                const dayEl = document.createElement('div');
                dayEl.className = 'day-label';
                dayEl.innerText = day;
                grid.appendChild(dayEl);
            });

            const month = viewDate.getMonth();
            const year = viewDate.getFullYear();
            monthLabel.innerText = viewDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            // Empty slots
            for (let i = 0; i < firstDay; i++) {
                grid.appendChild(document.createElement('div'));
            }

            // Days
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            for (let d = 1; d <= daysInMonth; d++) {
                const dayEl = document.createElement('div');
                dayEl.className = 'calendar-day';
                dayEl.innerText = d;

                const fullDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                const dateObj = new Date(fullDate);
                dateObj.setHours(0, 0, 0, 0);

                const daysFlights = bookings.filter(b => (b.travel_date || '').startsWith(fullDate));

                // 1. Identify date status
                if (dateObj < today) {
                    dayEl.classList.add('past');
                } else if (dateObj.getTime() === today.getTime()) {
                    dayEl.classList.add('today');
                }

                // 2. Identify flights
                if (daysFlights.length > 0) {
                    dayEl.classList.add('highlight');
                    dayEl.setAttribute('title', `${daysFlights.length} flight(s)`);

                    const dots = document.createElement('div');
                    dots.style.display = 'flex';
                    dots.style.gap = '2px';
                    dots.style.marginTop = '2px';
                    for (let i = 0; i < Math.min(daysFlights.length, 3); i++) {
                        const dot = document.createElement('span');
                        dot.style.width = '4px';
                        dot.style.height = '4px';
                        dot.style.background = dateObj < today ? '#94a3b8' : '#6366f1';
                        dot.style.borderRadius = '50%';
                        dots.appendChild(dot);
                    }
                    dayEl.appendChild(dots);
                }

                dayEl.onclick = () => {
                    filterDate = fullDate;
                    currentFilter = { key: null, value: null };
                    document.querySelectorAll('.filter-btn, .sub-filter-btn').forEach(b => {
                        if (b.id !== 'sortDateBtn') b.classList.remove('active');
                    });

                    document.getElementById('sortDateBtn').classList.add('active');
                    document.getElementById('sortDateBtn').innerHTML = `<i class="fas fa-calendar-day"></i> ${new Date(fullDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
                    closeCalendar();
                    applyFilters();
                };
                grid.appendChild(dayEl);
            }

            // Populate Scheduler List
            const schedulerList = document.getElementById('schedulerList');
            if (schedulerList) {
                // Filter for current month
                let currentMonthFlights = bookings.filter(b => {
                    const d = new Date(b.travel_date);
                    return d.getMonth() === month && d.getFullYear() === year;
                });

                // Prioritize: Today -> Future (Asc) -> Past (Desc)
                currentMonthFlights.sort((a, b) => {
                    const dateA = new Date(a.travel_date);
                    const dateB = new Date(b.travel_date);
                    dateA.setHours(0, 0, 0, 0);
                    dateB.setHours(0, 0, 0, 0);

                    const isPastA = dateA < today;
                    const isPastB = dateB < today;

                    if (isPastA !== isPastB) return isPastA ? 1 : -1; // Past items at the bottom

                    if (isPastA) {
                        return dateB - dateA; // Most recent past flights first among past
                    } else {
                        return dateA - dateB; // Soonest upcoming flights first
                    }
                });

                if (currentMonthFlights.length > 0) {
                    schedulerList.innerHTML = currentMonthFlights.map(b => {
                        const bDate = new Date(b.travel_date);
                        bDate.setHours(0, 0, 0, 0);
                        const isPast = bDate < today;
                        const isToday = bDate.getTime() === today.getTime();

                        let statusStyle = '';
                        let badgeStyle = 'background: #eef2ff; color: #6366f1;';
                        let itemBg = 'background: white;';

                        if (isPast) {
                            statusStyle = 'opacity: 0.6; filter: grayscale(1);';
                            badgeStyle = 'background: #f1f5f9; color: #94a3b8;';
                        } else if (isToday) {
                            itemBg = 'background: #fffbeb; border: 1.5px solid #f59e0b !important;';
                            badgeStyle = 'background: #f59e0b; color: white;';
                        }

                        return `
                            <div class="scheduler-item" onclick="focusBooking('${b.full_name.replace(/'/g, "\\'")}', '${b.booking_number}')" 
                                 style="padding: 12px; border-radius: 10px; border: 1px solid #f1f5f9; margin-bottom: 8px; cursor: pointer; transition: all 0.2s; ${itemBg} ${statusStyle}">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div style="font-weight: 700; font-size: 0.8rem; color: #1e293b;">${b.full_name} ${isToday ? '<span style="color: #f59e0b; font-size: 0.6rem; margin-left: 4px;">TODAY</span>' : ''}</div>
                                    <div style="font-size: 0.7rem; font-weight: 800; ${badgeStyle} padding: 2px 6px; border-radius: 4px;">${new Date(b.travel_date).getDate()}</div>
                                </div>
                                <div style="font-size: 0.7rem; color: #64748b; margin-top: 4px;">
                                    <i class="fas fa-map-marker-alt" style="margin-right: 4px;"></i> ${b.destination_name}
                                </div>
                                <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 2px; font-family: monospace;">
                                    ${b.booking_number}
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    schedulerList.innerHTML = `<div style="text-align: center; color: #94a3b8; padding-top: 40px; font-size: 0.9rem;">No departures this month</div>`;
                }
            }
        }

        async function checkNotifications() {
            try {
                const response = await fetch('admin-api.php?action=get_notifications&_=' + new Date().getTime());
                const result = await response.json();

                if (result.success && result.count > 0) {
                    const badge = document.getElementById('notifBadge');
                    const filterBadge = document.getElementById('filterNotifBadge');
                    const countEl = document.getElementById('notifCount');
                    const listEl = document.getElementById('notifList');

                    if (badge) {
                        badge.style.display = 'inline-flex';
                        badge.textContent = result.count;
                    }

                    const bell = document.querySelector('.notification-bell');
                    if (bell) bell.classList.add('pulse');

                    if (countEl) countEl.textContent = `${result.count} upcoming`;

                    if (listEl) {
                        listEl.innerHTML = result.travels.map(t => `
                            <div style="padding: 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.2s;" 
                                 onclick="focusBooking('${(t.full_name || '').replace(/'/g, "\\'")}', '${t.booking_number}')"
                                 onmouseover="this.style.background='#f8fafc'" 
                                 onmouseout="this.style.background='white'">
                                <div style="font-weight: 700; font-size: 0.85rem; color: #1e293b;">${t.full_name || 'Guest'}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                                    <i class="fas fa-plane-departure" style="margin-right: 4px;"></i> ${t.destination_name}
                                </div>
                                <div style="font-size: 0.75rem; color: #d97706; font-weight: 600; margin-top: 4px;">
                                    <i class="fas fa-calendar-alt" style="margin-right: 4px;"></i> ${new Date(t.travel_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                </div>
                            </div>
                        `).join('');
                    }

                    // Show a toast notification once per session
                    if (!sessionStorage.getItem('notif_toast_shown')) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Upcoming Travels Detected',
                            text: `You have ${result.count} customers traveling in the next 3 days!`,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 5000,
                            timerProgressBar: true,
                            iconColor: '#3b82f6'
                        });
                        sessionStorage.setItem('notif_toast_shown', 'true');
                    }
                }
            } catch (err) {
                console.error('Failed to fetch notifications:', err);
            }
        }

        // Focus on a specific booking from notification
        window.focusBooking = function (name, bookingNumber) {
            // 1. Switch to bookings page
            const bookingsTab = document.querySelector('.menu-item[data-page="bookings"]');
            if (bookingsTab) bookingsTab.click();

            // 2. Set search value to the specific booking number
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.value = bookingNumber;
            }

            // 3. Clear other active filters to ensure this one shows
            currentFilter = { key: null, value: null };
            filterDate = null;
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));

            // 4. Apply the search filter immediately
            applyFilters();

            // 5. Close notification dropdown
            if (notifDropdown) notifDropdown.style.display = 'none';
        };

        // Show all travelers departing in the next 3 days
        window.showAllUpcoming = function (e) {
            if (e) e.stopPropagation();

            // 1. Switch to bookings page
            const bookingsTab = document.querySelector('.menu-item[data-page="bookings"]');
            if (bookingsTab) bookingsTab.click();

            // 2. Set the special filter
            currentFilter = { type: 'upcoming', key: null, value: null };
            filterDate = null;

            // 3. Reset UI states
            const searchInput = document.getElementById('filterSearchInput');
            if (searchInput) searchInput.value = '';
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));

            // 4. Force ascending date sort
            currentSort = { key: 'travel_date', direction: 'asc' };

            // 5. Apply
            applyFilters();

            // 6. Close dropdown if open
            const dropdown = document.getElementById('notifDropdown');
            if (dropdown) dropdown.style.display = 'none';
        };

        function prevMonth() { viewDate.setMonth(viewDate.getMonth() - 1); renderCalendar(); }
        function nextMonth() { viewDate.setMonth(viewDate.getMonth() + 1); renderCalendar(); }

        // Initialize table on load if bookings page is active
        document.addEventListener('DOMContentLoaded', () => {
            // Handle URL parameters for page switching and notifications
            const urlParams = new URLSearchParams(window.location.search);
            const targetPage = urlParams.get('page');
            const isSaved = urlParams.get('saved');

            if (targetPage) {
                if (window.switchPage) window.switchPage(targetPage);
            } else {
                // Fallback to sessionStorage if no URL param
                const savedPage = sessionStorage.getItem('active_dashboard_page');
                if (savedPage) {
                    sessionStorage.removeItem('active_dashboard_page');
                    if (window.switchPage) window.switchPage(savedPage);
                }
            }

            if (isSaved === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Changes saved successfully',
                    timer: 3000,
                    showConfirmButton: false
                });
                // Clean up URL without refreshing
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // Restore sidebar click functionality
            document.querySelectorAll('.menu-item[data-page]').forEach(item => {
                item.addEventListener('click', () => {
                    const page = item.getAttribute('data-page');
                    switchPage(page);
                });
            });

            // Check for notifications
            checkNotifications();

            // Notification toggle logic
            const notificationBtn = document.getElementById('notificationBtn');
            const notifDropdown = document.getElementById('notifDropdown');
            if (notificationBtn && notifDropdown) {
                notificationBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isVisible = notifDropdown.style.display === 'block';
                    notifDropdown.style.display = isVisible ? 'none' : 'block';
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', () => {
                    notifDropdown.style.display = 'none';
                });
            }

            // Call initial page filter if starting on bookings
            const activePage = document.querySelector('.menu-item.active')?.dataset.page;
            if (activePage === 'bookings') {
                switchPage('bookings');
            }

            document.getElementById('bookings-page').addEventListener('click', function (e) {
                if (e.target === this || e.target.classList.contains('data-table')) {
                    resetFilters();
                }
            });

            // Re-bind edit form submit handler here
            const editForm = document.getElementById('editForm');
            if (editForm) {
                editForm.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const id = document.getElementById('edit-id').value;
                    const bookingNumber = document.getElementById('edit-booking-number')?.value || '';
                    const type = document.getElementById('edit-type').value;
                    let formData = new URLSearchParams();
                    formData.append('action', type === 'booking' ? 'update_booking' :
                        (type === 'destination' ? 'update_destination' :
                            (type === 'destination_new' ? 'add_destination' :
                                (type === 'package' ? 'update_package' : 'add_package'))));
                    if (id) formData.append('id', id);
                    if (bookingNumber) formData.append('booking_number', bookingNumber);

                    if (type === 'booking') {
                        formData.append('booking_status', document.getElementById('booking_status').value);
                        formData.append('visa_status', document.getElementById('visa_status')?.value || 'N/A');
                        formData.append('payment_status', document.getElementById('payment_status').value);
                        formData.append('admin_notes', document.getElementById('admin_notes')?.value || '');
                        formData.append('flight_details', document.getElementById('flight_details')?.value || '');
                        formData.append('travel_documents', document.getElementById('travel_documents')?.checked ? '1' : '0');
                        formData.append('ready_for_travel', document.getElementById('ready_for_travel')?.checked ? '1' : '0');
                        formData.append('number_of_travelers', document.getElementById('number_of_travelers')?.value || '1');
                        formData.append('price_per_person', document.getElementById('price_per_person')?.value || '0');
                        formData.append('total_amount', document.getElementById('total_amount')?.value || '0');
                    } else if (type === 'destination' || type === 'destination_new') {
                        formData.append('name', document.getElementById('name').value);
                        formData.append('country', document.getElementById('country').value);
                        formData.append('city', document.getElementById('city').value);
                        formData.append('type', document.getElementById('type').value);
                        formData.append('description', document.getElementById('description').value);
                        formData.append('is_active', document.getElementById('is_active').value);
                    } else if (type === 'package' || type === 'package_new') {
                        formData.append('destination_id', document.getElementById('destination_id').value);
                        formData.append('name', document.getElementById('name').value);
                        formData.append('duration', document.getElementById('duration').value);
                        formData.append('price', document.getElementById('price').value);
                        formData.append('activities_count', document.getElementById('activities_count').value);
                        formData.append('is_active', document.getElementById('is_active').value);
                    }

                    const submitBtn = editForm.querySelector('.save-btn');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                    fetch('admin-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;

                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: 'Changes saved successfully',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    closeModal();
                                    window.location.href = 'dashboard.php?page=bookings';
                                });
                            } else {
                                Swal.fire('Error', data.message || 'Save failed', 'error');
                            }
                        })
                        .catch(error => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                            Swal.fire('Connection Error', 'Details: ' + error.message, 'error');
                        });
                });
            }
        });

        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebar = document.querySelector('.sidebar');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
        }

        // Auto-close sidebar on window resize if larger than 992px
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
        });

        // Revenue Currency Switcher
        const totalRevenuePeso = <?= $totalRevenuePeso ?? 0 ?>;
        const totalRevenueUSD = <?= $totalRevenueUSD ?? 0 ?>;

        function toggleDropdownOptions(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('revenueCurrencyDropdown');
            dropdown.classList.toggle('open');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', () => {
            const dropdown = document.getElementById('revenueCurrencyDropdown');
            if (dropdown) dropdown.classList.remove('open');
        });

        function selectRevenueCurrency(currency, text, element) {
            // Update text
            document.getElementById('customDropdownText').innerText = text;

            // Update active styling
            document.querySelectorAll('.custom-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');

            // Close dropdown
            document.getElementById('revenueCurrencyDropdown').classList.remove('open');

            // Update revenue display
            const revEl = document.getElementById('stat-total-revenue');
            if (currency === 'usd') {
                revEl.innerHTML = '$' + totalRevenueUSD.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                revEl.innerHTML = '₱' + totalRevenuePeso.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }
        // Immediately update sidebar badge on every page load using accurate JS count
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof updateFilterBadges === 'function') {
                updateFilterBadges();
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const chartCanvas = document.getElementById('bookingsTrendChart');
            if (!chartCanvas || typeof Chart === 'undefined') return;

            const initialLabels = <?= json_encode($bookingsChartLabels) ?>;
            const initialData = <?= json_encode($bookingsChartData) ?>;
            const maxStartDate = <?= json_encode($bookingsChartMaxStart) ?>;

            // Matches the --primary / --accent values defined in :root above.
            // (Custom properties that reference other var()s can't be read back
            // resolved via getPropertyValue, so the theme colors are mirrored here.)
            const primary = 'hsl(230, 60%, 50%)';
            const accent = 'hsl(35, 100%, 55%)';

            const bookingsChart = new Chart(chartCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: initialLabels,
                    datasets: [{
                        label: 'Bookings',
                        data: initialData,
                        backgroundColor: primary,
                        hoverBackgroundColor: accent,
                        borderRadius: 8,
                        maxBarThickness: 36
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 400 },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleFont: { family: 'Poppins', weight: '600' },
                            bodyFont: { family: 'Poppins' },
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: function (ctx) {
                                    const n = ctx.parsed.y;
                                    return n + (n === 1 ? ' booking' : ' bookings');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, font: { family: 'Poppins' }, color: '#64748b' },
                            grid: { color: '#f1f5f9' }
                        },
                        x: {
                            ticks: { font: { family: 'Poppins' }, color: '#64748b' },
                            grid: { display: false }
                        }
                    }
                }
            });

            const startInput = document.getElementById('bookingsStartDate');
            const yearSelect = document.getElementById('bookingsYearSelect');
            const dayField = document.getElementById('bookingsDayFilterField');
            const yearField = document.getElementById('bookingsYearFilterField');
            const modeDayBtn = document.getElementById('bookingsModeDayBtn');
            const modeMonthBtn = document.getElementById('bookingsModeMonthBtn');
            const applyBtn = document.getElementById('bookingsFilterApply');
            const resetBtn = document.getElementById('bookingsFilterReset');
            const errorEl = document.getElementById('bookingsFilterError');
            const rangeLabelText = document.getElementById('bookingsRangeLabelText');
            const totalEl = document.getElementById('bookingsTotalValue');
            const avgEl = document.getElementById('bookingsAvgValue');
            const avgLabelEl = document.getElementById('bookingsAvgLabel');
            const peakEl = document.getElementById('bookingsPeakValue');
            const peakLabelEl = document.getElementById('bookingsPeakLabel');
            const peakDateEl = document.getElementById('bookingsPeakDateValue');

            if (!startInput || !yearSelect) return;
            startInput.max = maxStartDate;

            let currentMode = 'day';
            const defaultYear = yearSelect.value;

            function showFilterError(message) {
                errorEl.textContent = message;
                errorEl.style.display = message ? 'block' : 'none';
            }

            function setMode(mode) {
                currentMode = mode;
                showFilterError('');
                modeDayBtn.classList.toggle('is-active', mode === 'day');
                modeMonthBtn.classList.toggle('is-active', mode === 'month');
                dayField.style.display = mode === 'day' ? 'flex' : 'none';
                yearField.style.display = mode === 'month' ? 'flex' : 'none';
                avgLabelEl.textContent = mode === 'day' ? 'Daily Average' : 'Monthly Average';
                peakLabelEl.textContent = mode === 'day' ? 'Busiest Day' : 'Busiest Month';

                if (mode === 'day') {
                    loadBookingTrends({ period: 'day', startDate: startInput.value || maxStartDate });
                } else {
                    loadBookingTrends({ period: 'month', year: yearSelect.value || defaultYear });
                }
            }

            async function loadBookingTrends(params) {
                showFilterError('');
                applyBtn.disabled = true;
                try {
                    const qs = params.period === 'month'
                        ? `period=month&year=${encodeURIComponent(params.year)}`
                        : `start_date=${encodeURIComponent(params.startDate)}`;
                    const res = await fetch(`admin-api.php?action=get_booking_trends&${qs}`);
                    const result = await res.json();
                    if (!result.success) {
                        showFilterError(result.message || 'Unable to load that data.');
                        return;
                    }
                    bookingsChart.data.labels = result.labels;
                    bookingsChart.data.datasets[0].data = result.data;
                    bookingsChart.update();

                    totalEl.textContent = result.total;
                    avgEl.textContent = result.avg;
                    peakEl.textContent = result.peak;
                    peakDateEl.textContent = result.peakDateLabel || 'No bookings yet';
                    rangeLabelText.textContent = result.rangeLabel;
                    if (result.period === 'day') {
                        startInput.value = result.startDate;
                    } else {
                        yearSelect.value = result.year;
                    }
                } catch (e) {
                    showFilterError('Something went wrong loading that data. Please try again.');
                } finally {
                    applyBtn.disabled = false;
                }
            }

            modeDayBtn.addEventListener('click', () => setMode('day'));
            modeMonthBtn.addEventListener('click', () => setMode('month'));

            applyBtn.addEventListener('click', function () {
                if (currentMode === 'day') {
                    const chosen = startInput.value;
                    if (!chosen) {
                        showFilterError('Please choose a start date.');
                        return;
                    }
                    if (chosen > maxStartDate) {
                        showFilterError(`Start date can be at most ${maxStartDate} so the 14-day range doesn't go past today.`);
                        startInput.value = maxStartDate;
                        return;
                    }
                    loadBookingTrends({ period: 'day', startDate: chosen });
                } else {
                    loadBookingTrends({ period: 'month', year: yearSelect.value });
                }
            });

            resetBtn.addEventListener('click', function () {
                if (currentMode === 'day') {
                    startInput.value = maxStartDate;
                    loadBookingTrends({ period: 'day', startDate: maxStartDate });
                } else {
                    yearSelect.value = defaultYear;
                    loadBookingTrends({ period: 'month', year: defaultYear });
                }
            });

            startInput.addEventListener('change', function () {
                if (startInput.value > maxStartDate) {
                    showFilterError(`Start date can be at most ${maxStartDate} so the 14-day range doesn't go past today.`);
                    startInput.value = maxStartDate;
                } else {
                    showFilterError('');
                }
            });
        });
    </script>

    <script>
        // All Active Bookings table: click-and-drag horizontal panning (no visible scrollbar)
        document.addEventListener('DOMContentLoaded', function () {
            const scroller = document.getElementById('bookingsTableScroll');
            if (!scroller) return;

            let isDown = false;
            let startX = 0;
            let startScrollLeft = 0;
            let dragged = false;
            const DRAG_THRESHOLD = 5;

            scroller.addEventListener('mousedown', function (e) {
                isDown = true;
                dragged = false;
                startX = e.pageX;
                startScrollLeft = scroller.scrollLeft;
            });

            document.addEventListener('mousemove', function (e) {
                if (!isDown) return;
                const delta = e.pageX - startX;
                if (Math.abs(delta) > DRAG_THRESHOLD) {
                    dragged = true;
                    scroller.classList.add('is-dragging');
                }
                if (dragged) {
                    e.preventDefault();
                    scroller.scrollLeft = startScrollLeft - delta;
                }
            });

            document.addEventListener('mouseup', function () {
                isDown = false;
                scroller.classList.remove('is-dragging');
            });

            // Suppress the click (row/button activation) that follows an actual drag,
            // without affecting normal clicks/taps that never moved the mouse.
            scroller.addEventListener('click', function (e) {
                if (dragged) {
                    e.preventDefault();
                    e.stopPropagation();
                    dragged = false;
                }
            }, true);
        });
    </script>
</body>

</html>