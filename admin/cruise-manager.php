<?php
// File: admin/cruise-manager.php
require_once __DIR__ . '/../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// API Handlers (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];

    try {
        if ($action === 'save_cruise') {
            $id = intval($_POST['cruise_id'] ?? $_POST['id'] ?? 0);

            // Gallery image handling
            $gallery = [];
            if ($id > 0) {
                $old_gallery_str = $_POST['old_gallery'] ?? '[]';
                $gallery = json_decode($old_gallery_str, true);
                if (!is_array($gallery))
                    $gallery = [];
            }

            // Handle deleted gallery images
            if (isset($_POST['deleted_gallery_images']) && !empty($_POST['deleted_gallery_images'])) {
                $deleted_images = json_decode($_POST['deleted_gallery_images'], true);
                if (is_array($deleted_images)) {
                    $gallery = array_values(array_diff($gallery, $deleted_images));
                    foreach ($deleted_images as $del_img) {
                        $filepath = __DIR__ . '/../' . $del_img;
                        if (file_exists($filepath) && strpos($del_img, 'uploads/') === 0) {
                            @unlink($filepath);
                        }
                    }
                }
            }

            // Handle new uploaded gallery images
            if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
                for ($i = 0; $i < count($_FILES['gallery']['name']); $i++) {
                    if ($_FILES['gallery']['error'][$i] === 0) {
                        $ext = pathinfo($_FILES['gallery']['name'][$i], PATHINFO_EXTENSION);
                        $filename = 'cruise_gallery_' . time() . '_' . uniqid() . '.' . $ext;
                        if (move_uploaded_file($_FILES['gallery']['tmp_name'][$i], __DIR__ . '/../uploads/' . $filename)) {
                            $gallery[] = 'uploads/' . $filename;
                        }
                    }
                }
            }

            // Basic fields
            $data = [
                'cruise_code' => $_POST['cruise_code'] ?? '',
                'title' => $_POST['title'] ?? '',
                'short_description' => $_POST['short_description'] ?? '',
                'full_description' => $_POST['full_description'] ?? '',
                'duration' => $_POST['duration'] ?? '',
                'departure_port' => $_POST['departure_port'] ?? '',
                'destinations' => $_POST['destinations'] ?? '',
                'route' => $_POST['route'] ?? $_POST['destinations'] ?? '',
                'ship_name' => $_POST['ship_name'] ?? '',
                'cruise_line' => $_POST['cruise_line'] ?? '',
                'room_types' => $_POST['room_types'] ?? '[]',
                'amenities' => $_POST['amenities'] ?? '[]',
                'ship_description' => $_POST['ship_description'] ?? '',
                'base_price' => floatval($_POST['base_price'] ?? 0),
                'price_per_person' => floatval($_POST['price_per_person'] ?? 0),
                'promo_price' => floatval($_POST['promo_price'] ?? 0),
                'inclusions' => $_POST['inclusions'] ?? '[]',
                'exclusions' => $_POST['exclusions'] ?? '[]',
                'departure_date' => !empty($_POST['departure_date']) ? $_POST['departure_date'] : null,
                'return_date' => !empty($_POST['return_date']) ? $_POST['return_date'] : null,
                'booking_deadline' => !empty($_POST['booking_deadline']) ? $_POST['booking_deadline'] : null,
                'available_slots' => intval($_POST['available_slots'] ?? 0),
                'status' => $_POST['status'] ?? 'Available',
                'required_documents' => $_POST['required_documents'] ?? '',
                'travel_requirements' => $_POST['travel_requirements'] ?? '',
                'health_requirements' => $_POST['health_requirements'] ?? '',
                'cancellation_policy' => $_POST['cancellation_policy'] ?? '',
                'refund_policy' => $_POST['refund_policy'] ?? '',
                'terms_conditions' => $_POST['terms_conditions'] ?? '',
                'category' => $_POST['category'] ?? '',
                'destination_type' => $_POST['destination_type'] ?? 'International',
                'tags' => $_POST['tags'] ?? $_POST['destinations'] ?? '',
                'highlights' => $_POST['highlights'] ?? '',
                'promo_text' => $_POST['promo_text'] ?? '',
                'is_published' => isset($_POST['is_published']) ? 1 : 0,
                'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                'gallery' => json_encode($gallery)
            ];

            // Convert text fields to JSON arrays for columns with CHECK(json_valid)
            $json_fields = ['room_types', 'amenities', 'inclusions', 'exclusions', 'travel_requirements'];
            foreach ($json_fields as $json_field) {
                if (isset($data[$json_field]) && !empty($data[$json_field])) {
                    $val = trim($data[$json_field]);
                    // If it's not already a JSON array, convert it
                    if (strpos($val, '[') !== 0) {
                        $items = array_map('trim', preg_split('/[,\n\r]+/', $val));
                        $items = array_values(array_filter($items));
                        $data[$json_field] = json_encode($items);
                    }
                } else {
                    $data[$json_field] = '[]';
                }
            }

            // Image handling (Simplified for this script)
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
                $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                $filename = 'cruise_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], __DIR__ . '/../uploads/' . $filename)) {
                    $data['featured_image'] = 'uploads/' . $filename;
                }
            } else if ($id > 0) {
                $data['featured_image'] = $_POST['old_featured_image'] ?? '';
            }

            if ($id > 0) {
                // Update
                $sql = "UPDATE cruises SET ";
                $updates = [];
                foreach ($data as $key => $val) {
                    $updates[] = "$key = :$key";
                }
                $sql .= implode(', ', $updates) . " WHERE id = :id";
                $data['id'] = $id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                $cruise_id = $id;
            } else {
                // Insert
                $fields = array_keys($data);
                $placeholders = array_map(function ($f) {
                    return ":$f";
                }, $fields);
                $sql = "INSERT INTO cruises (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                $cruise_id = $pdo->lastInsertId();
            }

            // Handle Itinerary
            $pdo->prepare("DELETE FROM cruise_itinerary WHERE cruise_id = ?")->execute([$cruise_id]);
            if (isset($_POST['itinerary']) && is_array($_POST['itinerary'])) {
                $it_stmt = $pdo->prepare("INSERT INTO cruise_itinerary (cruise_id, day_number, title, description) VALUES (?, ?, ?, ?)");
                foreach ($_POST['itinerary'] as $day) {
                    $it_stmt->execute([$cruise_id, $day['day_number'], $day['title'], $day['description']]);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Cruise saved successfully!', 'id' => $cruise_id]);
        } elseif ($action === 'delete_cruise') {
            $id = intval($_POST['id']);
            $pdo->prepare("DELETE FROM cruises WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'get_cruise') {
            $id = intval($_POST['id']);
            $cruise = $pdo->prepare("SELECT * FROM cruises WHERE id = ?");
            $cruise->execute([$id]);
            $cruiseData = $cruise->fetch(PDO::FETCH_ASSOC);

            $itinerary = $pdo->prepare("SELECT * FROM cruise_itinerary WHERE cruise_id = ? ORDER BY day_number");
            $itinerary->execute([$id]);
            $cruiseData['itinerary'] = $itinerary->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $cruiseData]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch all cruises for display
$cruises = $pdo->query("SELECT * FROM cruises ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cruise Manager - HeyDream Travel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #003580;
            /* Blue */
            --accent: #ffd700;
            /* Yellow */
            --bg: #f4f7f6;
            --text: #333;
            --white: #ffffff;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Style matching your theme */
        .sidebar {
            width: 280px;
            background: #0f172a;
            color: white;
            padding: 30px 20px;
            position: fixed;
            height: 100vh;
        }

        .sidebar h2 {
            font-size: 1.5rem;
            margin-bottom: 40px;
            color: var(--accent);
            text-align: center;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: 0.3s;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-item i {
            margin-right: 15px;
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .add-btn {
            background: var(--accent);
            color: var(--primary);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: rgba(0, 53, 128, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.5rem;
        }

        .stat-info h3 {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
        }

        .stat-info p {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Table Style */
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            text-align: left;
            padding: 15px 20px;
            font-size: 0.85rem;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
        }

        .cruise-img {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-available {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-full {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-cancelled {
            background: #f1f5f9;
            color: #64748b;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }

        .edit-btn {
            background: #fef3c7;
            color: #d97706;
        }

        .delete-btn {
            background: #fee2e2;
            color: #dc2626;
        }

        .edit-btn:hover {
            background: #d97706;
            color: white;
        }

        .delete-btn:hover {
            background: #dc2626;
            color: white;
        }

        /* Modal Style */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            padding: 20px;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            width: 100%;
            max-width: 1000px;
            max-height: 90vh;
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--primary);
            color: white;
        }

        .modal-body {
            padding: 30px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #eee;
            text-align: right;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 25px;
            gap: 20px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .tab-btn {
            padding: 10px 5px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            position: relative;
            white-space: nowrap;
        }

        .tab-btn.active {
            color: var(--primary);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full {
            grid-column: span 2;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            color: #444;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.9rem;
        }

        input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 53, 128, 0.1);
        }

        /* Itinerary Builder */
        .itinerary-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
        }

        .remove-day {
            position: absolute;
            top: 15px;
            right: 15px;
            color: #dc2626;
            cursor: pointer;
        }

        .image-preview-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .img-preview {
            width: 120px;
            height: 120px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .save-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .cancel-btn {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 10px;
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--primary);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <div class="sidebar">
            <h2>HeyDream</h2>
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-th-large"></i> <span>Dashboard</span>
            </a>
            <a href="content-manager.php" class="nav-item">
                <i class="fas fa-layer-group"></i> <span>Content Manager</span>
            </a>
            <a href="cruise-manager.php" class="nav-item active">
                <i class="fas fa-ship"></i> <span>Cruise Inventory</span>
            </a>
            <a href="bookings.php" class="nav-item">
                <i class="fas fa-calendar-check"></i> <span>Bookings</span>
            </a>
            <a href="logout.php" class="nav-item" style="margin-top: 50px;">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </div>

        <div class="main-content">
            <div class="header">
                <h1>Cruise Inventory Management</h1>
                <button class="add-btn" onclick="openModal()">
                    <i class="fas fa-plus"></i> Add New Cruise
                </button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-ship"></i></div>
                    <div class="stat-info">
                        <h3>Total Cruises</h3>
                        <p><?= count($cruises) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #16a34a; background: rgba(22,163,74,0.1);"><i
                            class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3>Published</h3>
                        <p><?= count(array_filter($cruises, function ($c) {
                            return $c['is_published'];
                        })) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #d97706; background: rgba(217,119,6,0.1);"><i
                            class="fas fa-star"></i></div>
                    <div class="stat-info">
                        <h3>Featured</h3>
                        <p><?= count(array_filter($cruises, function ($c) {
                            return $c['is_featured'];
                        })) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #2563eb; background: rgba(37,99,235,0.1);"><i
                            class="fas fa-anchor"></i></div>
                    <div class="stat-info">
                        <h3>Total Slots</h3>
                        <p><?= array_sum(array_column($cruises, 'available_slots')) ?></p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Cruise Details</th>
                                <th>Schedule</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cruises as $cruise): ?>
                                <tr>
                                    <td>
                                        <img src="../<?= $cruise['featured_image'] ?: 'assets/img/placeholder.jpg' ?>"
                                            class="cruise-img">
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($cruise['title']) ?></strong><br>
                                        <small class="text-muted">Code: <?= htmlspecialchars($cruise['cruise_code']) ?> |
                                            <?= htmlspecialchars($cruise['ship_name']) ?></small>
                                    </td>
                                    <td>
                                        <i class="far fa-calendar"></i> <?= $cruise['departure_date'] ?: 'N/A' ?><br>
                                        <small><?= htmlspecialchars($cruise['duration']) ?></small>
                                    </td>
                                    <td>
                                        ₱<?= number_format($cruise['base_price'], 2) ?><br>
                                        <?php if ($cruise['promo_price'] > 0): ?>
                                            <small style="color: #dc2626;">Promo:
                                                ₱<?= number_format($cruise['promo_price'], 2) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($cruise['status']) ?>">
                                            <?= $cruise['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="action-btn edit-btn" onclick="editCruise(<?= $cruise['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn delete-btn"
                                                onclick="deleteCruise(<?= $cruise['id'] ?>, '<?= addslashes($cruise['title']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="cruiseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Cruise Package</h3>
                <span style="cursor: pointer;" onclick="closeModal()"><i class="fas fa-times"></i></span>
            </div>
            <div class="modal-body">
                <form id="cruiseForm">
                    <input type="hidden" name="id" id="cruise_id">
                    <input type="hidden" name="ajax_action" value="save_cruise">
                    <input type="hidden" name="old_featured_image" id="old_featured_image">

                    <div class="tabs">
                        <button type="button" class="tab-btn active" onclick="showTab('general')">General Info</button>
                        <button type="button" class="tab-btn" onclick="showTab('ship')">Ship & Route</button>
                        <button type="button" class="tab-btn" onclick="showTab('itinerary')">Itinerary</button>
                        <button type="button" class="tab-btn" onclick="showTab('pricing')">Pricing & Slots</button>
                        <button type="button" class="tab-btn" onclick="showTab('schedule')">Schedule</button>
                        <button type="button" class="tab-btn" onclick="showTab('policies')">Requirements &
                            Policies</button>
                        <button type="button" class="tab-btn" onclick="showTab('gallery')">Gallery</button>
                    </div>

                    <!-- General Tab -->
                    <div id="tab-general" class="tab-content active">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Cruise Title *</label>
                                <input type="text" name="title" id="title" required
                                    placeholder="e.g. 7-Day Mediterranean Dreams">
                            </div>
                            <div class="form-group">
                                <label>Cruise Code / ID *</label>
                                <input type="text" name="cruise_code" id="cruise_code" required
                                    placeholder="e.g. MED-2026-01">
                            </div>
                            <div class="form-group full">
                                <label>Short Description</label>
                                <input type="text" name="short_description" id="short_description"
                                    placeholder="Brief summary for list view">
                            </div>
                            <div class="form-group full">
                                <label>Full Description</label>
                                <textarea name="full_description" id="full_description" rows="5"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Duration</label>
                                <input type="text" name="duration" id="duration" placeholder="e.g. 7 Days / 6 Nights">
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category" id="category">
                                    <option value="Luxury">Luxury Cruises</option>
                                    <option value="Family">Family Friendly</option>
                                    <option value="Expedition">Expedition</option>
                                    <option value="River">River Cruises</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Status Controls</label>
                                <div style="display: flex; gap: 20px; align-items: center; margin-top: 10px;">
                                    <label style="display: flex; align-items: center; gap: 10px; margin: 0;">
                                        <input type="checkbox" name="is_published" id="is_published" checked
                                            style="width: auto;"> Published
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px; margin: 0;">
                                        <input type="checkbox" name="is_featured" id="is_featured" style="width: auto;">
                                        Featured
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ship Tab -->
                    <div id="tab-ship" class="tab-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Departure Port</label>
                                <input type="text" name="departure_port" id="departure_port">
                            </div>
                            <div class="form-group">
                                <label>Ship Name</label>
                                <input type="text" name="ship_name" id="ship_name">
                            </div>
                            <div class="form-group">
                                <label>Cruise Line</label>
                                <input type="text" name="cruise_line" id="cruise_line">
                            </div>
                            <div class="form-group">
                                <label>Room Types (One per line)</label>
                                <textarea name="room_types" id="room_types" rows="2"
                                    placeholder="Inside&#10;Oceanview&#10;Balcony&#10;Suite"></textarea>
                            </div>
                            <div class="form-group full">
                                <label>Amenities (One per line)</label>
                                <textarea name="amenities" id="amenities" rows="2"
                                    placeholder="Pool&#10;Spa&#10;Casino&#10;Gym&#10;Theater"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Destination Type</label>
                                <select name="destination_type" id="destination_type">
                                    <option value="International">International</option>
                                    <option value="Local">Local / Domestic</option>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label>Route / Destinations (Comma separated tags)</label>
                                <input type="text" name="destinations" id="destinations"
                                    placeholder="Greece, Italy, Spain, France">
                            </div>
                            <div class="form-group full">
                                <label>Ship Description</label>
                                <textarea name="ship_description" id="ship_description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Itinerary Tab -->
                    <div id="tab-itinerary" class="tab-content">
                        <div id="itinerary-container">
                            <!-- Dynamic Days Added Here -->
                        </div>
                        <button type="button" class="add-btn" style="background: #e2e8f0; margin-top: 10px;"
                            onclick="addItineraryDay()">
                            <i class="fas fa-plus"></i> Add Day to Itinerary
                        </button>
                    </div>

                    <!-- Pricing Tab -->
                    <div id="tab-pricing" class="tab-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Base Price (Display)</label>
                                <input type="number" name="base_price" id="base_price" step="0.01">
                            </div>
                            <div class="form-group">
                                <label>Promo Price (Optional)</label>
                                <input type="number" name="promo_price" id="promo_price" step="0.01">
                            </div>
                            <div class="form-group">
                                <label>Available Slots</label>
                                <input type="number" name="available_slots" id="available_slots">
                            </div>
                            <div class="form-group">
                                <label>Availability Status</label>
                                <select name="status" id="status">
                                    <option value="Available">Available</option>
                                    <option value="Full">Full / Fully Booked</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label>Inclusions (Bullet points or text)</label>
                                <textarea name="inclusions" id="inclusions" rows="4"></textarea>
                            </div>
                            <div class="form-group full">
                                <label>Exclusions</label>
                                <textarea name="exclusions" id="exclusions" rows="4"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Tab -->
                    <div id="tab-schedule" class="tab-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Departure Date</label>
                                <input type="date" name="departure_date" id="departure_date">
                            </div>
                            <div class="form-group">
                                <label>Return Date</label>
                                <input type="date" name="return_date" id="return_date">
                            </div>
                            <div class="form-group">
                                <label>Booking Deadline</label>
                                <input type="date" name="booking_deadline" id="booking_deadline">
                            </div>
                        </div>
                    </div>

                    <!-- Info & Policies Tab -->
                    <div id="tab-policies" class="tab-content">
                        <div class="form-grid">
                            <div class="form-group full">
                                <label>Highlights (One per line)</label>
                                <textarea name="highlights" id="highlights" rows="3"></textarea>
                            </div>
                            <div class="form-group full">
                                <label>Promo Text / Marketing Callout</label>
                                <input type="text" name="promo_text" id="promo_text"
                                    placeholder="e.g. Limited Time Offer - Book by Dec 31!">
                            </div>
                            <div class="form-group full">
                                <label>Travel Requirements</label>
                                <textarea name="travel_requirements" id="travel_requirements" rows="3"></textarea>
                            </div>
                            <div class="form-group full">
                                <label>Cancellation Policy</label>
                                <textarea name="cancellation_policy" id="cancellation_policy" rows="3"></textarea>
                            </div>
                            <div class="form-group full">
                                <label>Terms & Conditions</label>
                                <textarea name="terms_conditions" id="terms_conditions" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Gallery Tab -->
                    <div id="tab-gallery" class="tab-content">
                        <div class="form-grid">
                            <div class="form-group full" style="margin-bottom: 25px;">
                                <label
                                    style="font-weight: 700; color: #003580; margin-bottom: 5px; display: block; font-size: 1.1rem;">Featured
                                    Photo / Cover Image</label>
                                <span
                                    style="font-size: 0.85rem; color: #64748b; display: block; margin-bottom: 10px;">This
                                    is the main image that will be shown on cards, search results, and at the top of
                                    detail pages.</span>
                                <input type="file" name="featured_image"
                                    onchange="previewImage(this, 'featured_preview')">
                                <div id="featured_preview" class="image-preview-container"
                                    style="height: 180px; margin-top:10px; border-radius: 12px; border: 1px solid #cbd5e1; background-size: cover; background-position: center; display: block;">
                                </div>
                            </div>

                            <div class="form-group full" style="border-top: 1px solid #e2e8f0; padding-top: 25px;">
                                <label
                                    style="font-weight: 700; color: #003580; margin-bottom: 5px; display: block; font-size: 1.1rem;">Cruise
                                    Gallery Upload</label>
                                <span
                                    style="font-size: 0.85rem; color: #64748b; display: block; margin-bottom: 15px;">Add
                                    supplementary photos to showcase ship facilities, cabin classes, destinations, and
                                    activities.</span>

                                <input type="file" name="gallery[]" multiple id="gallery_files"
                                    onchange="previewGalleryImages(this)" accept="image/*" style="margin-bottom:15px;">

                                <input type="hidden" name="old_gallery" id="old_gallery" value="[]">
                                <input type="hidden" name="deleted_gallery_images" id="deleted_gallery_images"
                                    value="[]">

                                <div id="gallery_preview_container" class="image-preview-container"
                                    style="display:flex; gap:15px; flex-wrap:wrap; margin-top:10px;"></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                <button type="button" class="save-btn" onclick="saveCruise()">Save Cruise Package</button>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }

        function openModal() {
            document.getElementById('modalTitle').innerText = 'Add New Cruise Package';
            document.getElementById('cruiseForm').reset();
            document.getElementById('cruise_id').value = '';
            document.getElementById('itinerary-container').innerHTML = '';
            document.getElementById('featured_preview').innerHTML = '';
            document.getElementById('old_gallery').value = '[]';
            document.getElementById('deleted_gallery_images').value = '[]';
            document.getElementById('gallery_preview_container').innerHTML = '';
            const galleryFilesInput = document.getElementById('gallery_files');
            if (galleryFilesInput) galleryFilesInput.value = '';
            document.getElementById('cruiseModal').classList.add('active');
            showTab('general');
        }

        function closeModal() {
            document.getElementById('cruiseModal').classList.remove('active');
        }

        function addItineraryDay(data = null) {
            const container = document.getElementById('itinerary-container');
            const dayNum = container.children.length + 1;

            const div = document.createElement('div');
            div.className = 'itinerary-item';
            div.innerHTML = `
                <span class="remove-day" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></span>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Day Number</label>
                        <input type="number" name="itinerary_day[]" value="${data ? data.day_number : dayNum}" readonly>
                    </div>
                    <div class="form-group">
                        <label>Day Title</label>
                        <input type="text" name="itinerary_title[]" value="${data ? data.title : ''}" placeholder="e.g. Arrival at Port">
                    </div>
                    <div class="form-group full" style="margin:0;">
                        <label>Activities Description</label>
                        <textarea name="itinerary_desc[]" rows="2">${data ? data.description : ''}</textarea>
                    </div>
                </div>
            `;
            container.appendChild(div);
        }

        function previewImage(input, previewId) {
            const container = document.getElementById(previewId);
            container.innerHTML = '';
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-preview';
                    container.appendChild(img);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeGalleryImage(btn, path) {
            btn.parentElement.remove();
            const deletedInput = document.getElementById('deleted_gallery_images');
            const deleted = JSON.parse(deletedInput.value || '[]');
            deleted.push(path);
            deletedInput.value = JSON.stringify(deleted);
        }

        function previewGalleryImages(input) {
            const container = document.getElementById('gallery_preview_container');
            container.querySelectorAll('.local-preview').forEach(el => el.remove());

            if (input.files) {
                Array.from(input.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'local-preview';
                        wrapper.style.position = 'relative';
                        wrapper.style.width = '120px';
                        wrapper.style.height = '120px';

                        wrapper.innerHTML = `
                            <img src="${e.target.result}" class="img-preview" style="width:100%; height:100%; object-fit:cover; border-radius:10px; border:2px dashed #003580;">
                            <span style="position:absolute; bottom:5px; left:5px; background:rgba(0,53,128,0.85); color:white; padding:2px 6px; border-radius:4px; font-size:0.6rem; font-weight:700;">NEW</span>
                        `;
                        container.appendChild(wrapper);
                    }
                    reader.readAsDataURL(file);
                });
            }
        }

        async function saveCruise() {
            const form = document.getElementById('cruiseForm');
            const formData = new FormData(form);

            // Collect Itinerary Data
            const itineraryItems = document.querySelectorAll('.itinerary-item');
            itineraryItems.forEach((item, index) => {
                formData.append(`itinerary[${index}][day_number]`, item.querySelector('[name="itinerary_day[]"]').value);
                formData.append(`itinerary[${index}][title]`, item.querySelector('[name="itinerary_title[]"]').value);
                formData.append(`itinerary[${index}][description]`, item.querySelector('[name="itinerary_desc[]"]').value);
            });

            Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            try {
                const response = await fetch('cruise-manager.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire('Success', result.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', result.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Something went wrong', 'error');
            }
        }

        async function editCruise(id) {
            Swal.fire({ title: 'Loading...', didOpen: () => { Swal.showLoading(); } });

            try {
                const response = await fetch('cruise-manager.php', {
                    method: 'POST',
                    body: new URLSearchParams({ ajax_action: 'get_cruise', id: id })
                });
                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    openModal();
                    document.getElementById('modalTitle').innerText = 'Edit Cruise: ' + data.title;
                    document.getElementById('cruise_id').value = data.id;

                    // Populate basic fields
                    for (let key in data) {
                        const el = document.getElementById(key);
                        if (el) {
                            if (el.type === 'checkbox') {
                                el.checked = data[key] == 1;
                            } else {
                                let val = data[key] || '';
                                // Try to parse JSON for list fields
                                if (typeof val === 'string' && val.trim().startsWith('[') && val.trim().endsWith(']')) {
                                    try {
                                        const parsed = JSON.parse(val);
                                        if (Array.isArray(parsed)) {
                                            const separator = el.tagName === 'TEXTAREA' ? '\n' : ', ';
                                            val = parsed.join(separator);
                                        }
                                    } catch (e) { }
                                }
                                el.value = val;
                            }
                        }
                    }

                    document.getElementById('old_featured_image').value = data.featured_image;
                    if (data.featured_image) {
                        document.getElementById('featured_preview').innerHTML = `<img src="../${data.featured_image}" class="img-preview">`;
                    }

                    // Populate itinerary
                    if (data.itinerary) {
                        data.itinerary.forEach(day => addItineraryDay(day));
                    }

                    // Populate gallery
                    const galleryPreview = document.getElementById('gallery_preview_container');
                    galleryPreview.innerHTML = '';
                    document.getElementById('deleted_gallery_images').value = '[]';

                    let galleryList = [];
                    if (data.gallery) {
                        try {
                            galleryList = JSON.parse(data.gallery);
                        } catch (e) {
                            galleryList = [];
                        }
                    }
                    document.getElementById('old_gallery').value = JSON.stringify(galleryList);

                    if (Array.isArray(galleryList) && galleryList.length > 0) {
                        galleryList.forEach(img => {
                            const wrapper = document.createElement('div');
                            wrapper.style.position = 'relative';
                            wrapper.style.width = '120px';
                            wrapper.style.height = '120px';

                            wrapper.innerHTML = `
                                <img src="../${img}" class="img-preview" style="width:100%; height:100%; object-fit:cover; border-radius:10px; border:2px solid #e2e8f0;">
                                <span onclick="removeGalleryImage(this, '${img}')" style="position:absolute; top:5px; right:5px; background:rgba(220,38,38,0.85); color:white; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:0.75rem; transition:0.3s; z-index:10;"><i class="fas fa-times"></i></span>
                            `;
                            galleryPreview.appendChild(wrapper);
                        });
                    }

                    Swal.close();
                }
            } catch (error) {
                Swal.fire('Error', 'Failed to fetch data', 'error');
            }
        }

        function deleteCruise(id, title) {
            Swal.fire({
                title: 'Delete Cruise?',
                text: `Are you sure you want to delete "${title}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Yes, Delete it'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const response = await fetch('cruise-manager.php', {
                        method: 'POST',
                        body: new URLSearchParams({ ajax_action: 'delete_cruise', id: id })
                    });
                    const res = await response.json();
                    if (res.success) {
                        Swal.fire('Deleted', 'Cruise package has been removed.', 'success').then(() => {
                            window.location.reload();
                        });
                    }
                }
            });
        }
    </script>
</body>

</html>