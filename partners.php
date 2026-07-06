<?php
require_once __DIR__ . '/config/database.php';

// Get all approved partners with their profiles
$stmt = $pdo->prepare("
    SELECT 
        pa.id,
        pa.company_name,
        pa.contact_person,
        pa.email,
        pa.phone,
        pa.business_type,
        pa.website,
        pa.created_at,
        pa.approved_at,
        pp.business_display_name,
        pp.bio,
        pp.city,
        pp.country,
        pp.specialties
    FROM partner_applications pa
    LEFT JOIN partner_profiles pp ON pa.id = pp.partner_id
    WHERE pa.status = 'approved'
    ORDER BY pa.approved_at DESC
");
$stmt->execute();
$partners = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Partners - HeyDream Travel</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f4c81;
            --primary-soft: #e8f2ff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --success: #10b981;
            --bg: #f4f7fb;
            --accent: #f59e0b;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #eef6ff 0%, #f8fafc 100%);
            color: var(--text);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 48px;
            padding: 40px 20px;
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: var(--primary);
            margin: 0 0 12px 0;
        }

        .page-header p {
            font-size: 1.1rem;
            color: var(--muted);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .filters {
            display: flex;
            gap: 12px;
            margin-bottom: 32px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }

        .partners-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .partner-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }

        .partner-card:hover {
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.16);
            transform: translateY(-4px);
        }

        .card-header {
            background: linear-gradient(135deg, #e8f2ff 0%, #d8e8ff 100%);
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }

        .card-header h3 {
            margin: 0 0 8px 0;
            font-size: 1.1rem;
            color: var(--primary);
        }

        .business-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--primary);
            color: white;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 8px;
        }

        .card-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .card-bio {
            color: var(--muted);
            line-height: 1.6;
            font-size: 0.9rem;
            margin-bottom: 12px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-location {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 12px;
        }

        .card-specialties {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .specialty-badge {
            background: var(--primary-soft);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .card-footer {
            padding-top: 12px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
        }

        .contact-link {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            background: var(--primary-soft);
            color: var(--primary);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
        }

        .contact-link:hover {
            background: var(--primary);
            color: white;
        }

        .view-profile-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
        }

        .view-profile-btn:hover {
            background: #0d3a60;
        }

        .no-partners {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
        }

        .no-partners i {
            font-size: 3rem;
            color: var(--muted);
            margin-bottom: 16px;
            display: block;
        }

        .no-partners h2 {
            color: var(--text);
            margin: 0 0 8px 0;
        }

        .no-partners p {
            color: var(--muted);
            margin: 0;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .filters {
                flex-direction: column;
            }

            .search-box {
                min-width: 100%;
            }

            .partners-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-handshake"></i> Our Partners</h1>
            <p>Discover our network of trusted travel partners offering unique experiences and services worldwide.</p>
        </div>

        <div class="filters">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchPartners" placeholder="Search by company name, location, or specialty...">
            </div>
            <a href="admin/Partnership/partner-index.php" class="view-profile-btn" style="min-width: 220px;">
                <i class="fas fa-handshake"></i>
                <span>Browse Partner Packages</span>
            </a>
        </div>

        <div class="partners-grid" id="partners-container">
            <?php if (empty($partners)): ?>
                <div class="no-partners">
                    <i class="fas fa-users"></i>
                    <h2>No Partners Yet</h2>
                    <p>We're currently onboarding our partner network. Check back soon!</p>
                </div>
            <?php else: ?>
                <?php foreach ($partners as $partner): ?>
                    <div class="partner-card">
                        <div class="card-header">
                            <h3><?= htmlspecialchars($partner['business_display_name'] ?? $partner['company_name']) ?></h3>
                            <span class="business-type"><i class="fas fa-building"></i> <?= htmlspecialchars($partner['business_type']) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($partner['bio'])): ?>
                                <p class="card-bio"><?= htmlspecialchars($partner['bio']) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($partner['city']) || !empty($partner['country'])): ?>
                                <div class="card-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($partner['city'] ?? 'City') ?>, <?= htmlspecialchars($partner['country'] ?? 'Country') ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($partner['specialties'])): ?>
                                <div class="card-specialties">
                                    <?php foreach (array_slice(array_map('trim', explode(',', $partner['specialties'])), 0, 2) as $specialty): ?>
                                        <span class="specialty-badge"><?= htmlspecialchars($specialty) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <?php if (!empty($partner['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($partner['email']) ?>" class="contact-link" title="Email">
                                    <i class="fas fa-envelope"></i>
                                    <span>Email</span>
                                </a>
                            <?php endif; ?>
                            <a href="view-partner-profile.php?id=<?= htmlspecialchars($partner['id']) ?>" class="view-profile-btn">
                                <i class="fas fa-arrow-right"></i>
                                <span>View Profile</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchPartners');
        const partnersContainer = document.getElementById('partners-container');
        const partnerCards = document.querySelectorAll('.partner-card');

        searchInput?.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            let visibleCount = 0;

            partnerCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                const noResults = document.createElement('div');
                noResults.className = 'no-partners';
                noResults.innerHTML = `
                    <i class="fas fa-search"></i>
                    <h2>No Partners Found</h2>
                    <p>Try adjusting your search criteria.</p>
                `;
                partnersContainer.innerHTML = '';
                partnersContainer.appendChild(noResults);
            }
        });
    </script>
</body>
</html>
