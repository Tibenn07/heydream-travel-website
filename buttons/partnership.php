<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Partner with HeyDream Travel</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(180deg, #f7fbff 0%, #eaf3ff 100%);
            font-family: 'Poppins', sans-serif;
            color: #1f2937;
        }

        .partnership-hero {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 70vh;
            padding: 80px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .partnership-hero::before {
            content: '';
            position: absolute;
            top: -40px;
            left: -40px;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle at center, rgba(59, 130, 246, 0.18), transparent 60%);
            filter: blur(24px);
        }

        .partnership-panel {
            background: white;
            border-radius: 32px;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.08);
            max-width: 980px;
            width: 100%;
            padding: 60px 40px;
            position: relative;
            z-index: 1;
        }

        .partnership-panel h1 {
            font-size: clamp(2.2rem, 3.5vw, 3.5rem);
            margin-bottom: 18px;
            color: #0f172a;
        }

        .partnership-panel p {
            font-size: 1rem;
            line-height: 1.8;
            color: #475569;
            max-width: 840px;
            margin: 0 auto 30px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 35px;
        }

        .feature-card {
            background: #f8fbff;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 24px;
            text-align: left;
            min-height: 150px;
        }

        .feature-card h3 {
            font-size: 1.05rem;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .feature-card p {
            color: #64748b;
            margin: 0;
        }

        .partnership-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 16px;
        }

        .cta-btn,
        .cta-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 28px;
            border-radius: 999px;
            font-weight: 700;
            border: none;
            text-decoration: none;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .cta-btn {
            background: #0f172a;
            color: white;
        }

        .cta-secondary {
            background: transparent;
            color: #0f172a;
            border: 2px solid #0f172a;
        }

        .cta-btn:hover,
        .cta-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
        }

        .partnership-footer {
            margin-top: 50px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            color: #334155;
        }

        .partnership-footer strong {
            color: #0f172a;
        }

        @media (max-width: 760px) {
            .partnership-panel {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="partnership-hero">
        <section class="partnership-panel">
            <h1><i class="fas fa-handshake"></i> Grow with HeyDream Travel</h1>
            <p>Partner with us and connect your travel business to a broader network of customers, packages, and curated travel
                experiences. Apply today to become a verified HeyDream Travel partner and access a dedicated partner portal
                once approved.</p>

            <div class="feature-grid">
                <div class="feature-card">
                    <h3><i class="fas fa-bullseye" style="color:#2563eb;margin-right:8px;"></i>Targeted Exposure</h3>
                    <p>Get featured in our partner marketplace, campaigns, and travel recommendation engine.</p>
                </div>
                <div class="feature-card">
                    <h3><i class="fas fa-chart-line" style="color:#10b981;margin-right:8px;"></i>Sales Support</h3>
                    <p>Receive operational support from our sales teams and access vetted booking leads.</p>
                </div>
                <div class="feature-card">
                    <h3><i class="fas fa-shield-alt" style="color:#f59e0b;margin-right:8px;"></i>Trusted Network</h3>
                    <p>Join an expanding network of hotels, airlines, tour operators, and corporate travel partners.</p>
                </div>
            </div>

            <div class="partnership-actions">
                <a class="cta-btn" href="../admin/Partnership/partner-register.php"><i class="fas fa-paper-plane"></i>Apply as Partner</a>
                <a class="cta-secondary" href="../admin/Partnership/partner-login.php"><i class="fas fa-sign-in-alt"></i>Partner Login</a>
            </div>

            <div class="partnership-footer">
                <p><strong>What we review:</strong> company profile, market fit, compliance, and partner readiness.</p>
                <p><strong>Approval timeline:</strong> responses are typically sent within 2 business days.</p>
            </div>
        </section>
    </div>
</body>

</html>
