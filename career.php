<?php
// ========================================
// FILE: career.php
// DESCRIPTION: Careers at HeyDream Travel & Tours
// ========================================

require_once __DIR__ . '/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>HeyDream - Careers</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef5 100%);
            font-family: 'Poppins', sans-serif;
        }

        .career-hero {
            background: linear-gradient(135deg, #003580 0%, #1a4b8c 50%, #2c5a9e 100%);
            padding: 120px 5% 80px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .career-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .career-hero h1 {
            font-size: 3rem;
            margin-bottom: 15px;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }

        .career-hero p {
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.95;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        .career-content {
            padding: 60px 5%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .benefits-section {
            margin-bottom: 60px;
            text-align: center;
        }

        .benefits-section h2 {
            color: #003580;
            margin-bottom: 40px;
            font-size: 2rem;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .benefit-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }

        .benefit-card:hover {
            transform: translateY(-10px);
        }

        .benefit-card i {
            font-size: 2.5rem;
            color: #ff9800;
            margin-bottom: 20px;
        }

        .benefit-card h3 {
            color: #003580;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .benefit-card p {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .jobs-section h2 {
            color: #003580;
            margin-bottom: 30px;
            font-size: 2rem;
            text-align: center;
        }

        .job-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .job-card:hover {
            box-shadow: 0 15px 35px rgba(0, 53, 128, 0.1);
            transform: translateY(-3px);
            border-left: 5px solid #ff9800;
        }

        .job-info h3 {
            color: #003580;
            font-size: 1.4rem;
            margin-bottom: 10px;
        }

        .job-meta {
            display: flex;
            gap: 15px;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .job-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .job-meta i {
            color: #ff9800;
        }

        .job-desc {
            color: #555;
            line-height: 1.6;
            max-width: 800px;
        }

        .apply-btn {
            background: #ff9800;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }

        .apply-btn:hover {
            background: #003580;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .job-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .apply-btn {
                width: 100%;
                text-align: center;
            }
        }

        .custom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .custom-modal-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            position: relative;
            animation: modalFadeIn 0.3s ease;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .custom-modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.5rem;
            color: #666;
            cursor: pointer;
            transition: color 0.3s;
        }

        .custom-modal-close:hover {
            color: #dc3545;
        }

        .back-button-container {
            text-align: center;
            padding: 40px 15px;
            margin-top: 20px;
            background: #f8f9fa;
        }

        .back-button {
            background: linear-gradient(135deg, #003580, #1a4b8c);
            color: white;
            border: none;
            padding: 12px 35px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0, 53, 128, 0.2);
        }

        .back-button:hover {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            color: white;
        }

        .back-button i {
            font-size: 1rem;
        }
    </style>
</head>

<body>

    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="images/Heydream Logo.png" alt="HeyDream Logo" class="logo"
                onclick="window.location.href='index.php'">
            <div class="company-name">
                <span class="line1">HeyDream Travel</span>
                <span class="line2">and Tours</span>
            </div>
        </div>
        <div class="nav-links-desktop">
            <a href="index.php" class="nav-link-item">Home</a>
            <a href="local-destination.php" class="nav-link-item">Local Tours</a>
            <a href="foreign-destinations.php" class="nav-link-item">Foreign Tours</a>
            <a href="buttons/visa.php" class="nav-link-item">Visa Assistance</a>
            <a href="buttons/about.php" class="nav-link-item">About Us</a>
        </div>
        <div class="nav-container">
            <div class="nav-auth-desktop">
                <?php if ($auth->isLoggedIn()): ?>
                    <?php $currentUser = $auth->getCurrentUser(); ?>
                    <div class="user-menu-wrapper">
                        <button class="user-profile-btn" id="userMenuBtn">
                            <i class="fas fa-user-circle"></i>
                            <span class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-dropdown" id="userDropdown">
                            <a href="User Account/profile.php"><i class="fas fa-user-edit"></i> My Profile</a>
                            <a href="saved.php"><i class="fas fa-heart"></i> Saved Items</a>
                            <a href="User Account/vouchers.php"><i class="fas fa-ticket-alt"></i> Vouchers</a>
                            <div class="dropdown-divider"></div>
                            <a href="User Account/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i>
                                Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="User Account/login.php" class="nav-login-btn"><i class="fas fa-user-circle"></i> Sign In</a>
                <?php endif; ?>
            </div>
            <!-- Hamburger menu removed as per request -->
        </div>
    </header>

    <!-- Side panel and overlay removed as per request -->


    <section class="career-hero">
        <h1><i class="fas fa-briefcase"></i> Join Our Team</h1>
        <p>Build the future of travel with HeyDream. We're looking for passionate individuals who want to create
            unforgettable experiences.</p>
    </section>

    <div class="career-content">
        <div class="benefits-section">
            <h2>Why Work With Us?</h2>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <i class="fas fa-plane-departure"></i>
                    <h3>Travel Perks</h3>
                    <p>Enjoy exclusive discounts on flights, hotels, and tour packages for you and your family.</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-laptop-house"></i>
                    <h3>Flexible Work</h3>
                    <p>We support a hybrid work setup to ensure you have the best work-life balance possible.</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-heartbeat"></i>
                    <h3>Health & Wellness</h3>
                    <p>Comprehensive HMO coverage from day one, including free mental health consultations.</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Growth Opportunities</h3>
                    <p>Continuous learning programs, mentorship, and clear paths for career advancement.</p>
                </div>
            </div>
        </div>

        <div class="jobs-section">
            <h2>Open Positions</h2>

            <div class="job-card">
                <div class="job-info">
                    <h3>Senior Travel Consultant</h3>
                    <div class="job-meta">
                        <span><i class="fas fa-map-marker-alt"></i> Ortigas, Pasig City (Hybrid)</span>
                        <span><i class="fas fa-clock"></i> Full-time</span>
                    </div>
                    <p class="job-desc">We are looking for an experienced travel consultant to design customized
                        itineraries, handle international bookings, and provide premium customer service to our VIP
                        clients.</p>
                </div>
                <a href="#" class="apply-btn" onclick="openModal(event)">Apply Now</a>
            </div>

            <div class="job-card">
                <div class="job-info">
                    <h3>Frontend Web Developer</h3>
                    <div class="job-meta">
                        <span><i class="fas fa-map-marker-alt"></i> Remote</span>
                        <span><i class="fas fa-clock"></i> Full-time</span>
                    </div>
                    <p class="job-desc">Join our tech team to maintain and enhance the HeyDream booking platform. You
                        will work with HTML, CSS, JavaScript, and PHP to create seamless user experiences.</p>
                </div>
                <a href="#" class="apply-btn" onclick="openModal(event)">Apply Now</a>
            </div>

            <div class="job-card">
                <div class="job-info">
                    <h3>Marketing Coordinator</h3>
                    <div class="job-meta">
                        <span><i class="fas fa-map-marker-alt"></i> Ortigas, Pasig City (On-site)</span>
                        <span><i class="fas fa-clock"></i> Full-time</span>
                    </div>
                    <p class="job-desc">Help us grow the HeyDream brand by managing our social media campaigns,
                        partnering with influencers, and executing digital marketing strategies.</p>
                </div>
                <a href="#" class="apply-btn" onclick="openModal(event)">Apply Now</a>
            </div>
        </div>
    </div>

    <div class="back-button-container">
        <button class="back-button" onclick="window.location.href='index.php'">
            <i class="fas fa-arrow-left"></i> Back to Home
        </button>
    </div>

    <!-- Footer matching index.php -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-logo-section">
                <div class="footer-logo">
                    <img src="images/Heydream Logo.png" alt="HeyDream Logo" class="footer-logo-img">
                    <span class="footer-brand">HeyDream</span>
                </div>
                <div class="footer-country"><i class="fas fa-globe"></i> Philippines (Pilipinas)</div>
            </div>
            <div class="footer-links-grid">
                <div class="footer-column">
                    <h4>Contact Us</h4>
                    <ul class="contact-list">
                        <li><i class="fas fa-map-marker-alt"></i> 3104 Tektite East Tower, Philippine Stock Exchange,
                            Ortigas</li>
                        <li><i class="fas fa-phone-alt"></i> 0945 776 4140</li>
                        <li><i class="fas fa-envelope"></i> heydreamtravelandtours@gmail.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Fri: 9AM-6PM<br>Sat: 9AM-1PM</li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="buttons/about.php">About Us</a></li>
                        <li><a href="career.php">Career</a></li>
                        <li><a href="privacy.php">Data Privacy Policy</a></li>
                        <li><a href="terms.php">Terms & Conditions</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-social">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 HeyDream Travel & Tours. All rights reserved.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script src="js/menu.js"></script>
    <script src="js/saved.js"></script>
    <script src="js/auth-menu.js"></script>

    <!-- Floating AI Chatbot Script -->
    <script src="js/floating_chatbot.js"></script>

    <!-- No Slots Modal -->
    <div class="custom-modal" id="noSlotsModal">
        <div class="custom-modal-content">
            <span class="custom-modal-close" onclick="closeModal()">&times;</span>
            <i class="fas fa-calendar-times" style="font-size: 3.5rem; color: #ff9800; margin-bottom: 20px;"></i>
            <h2 style="color: #003580; margin-bottom: 15px; font-size: 1.8rem;">No Available Slots</h2>
            <p style="color: #666; margin-bottom: 25px; line-height: 1.6;">We are currently not accepting applications
                for this position. Please check back later!</p>
            <button onclick="closeModal()"
                style="background: #003580; color: white; border: none; padding: 12px 35px; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 1rem;">Got
                it</button>
        </div>
    </div>

    <script>
        function openModal(e) {
            if (e) e.preventDefault();
            document.getElementById('noSlotsModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('noSlotsModal').style.display = 'none';
        }
        // Close on outside click
        window.onclick = function (event) {
            const modal = document.getElementById('noSlotsModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>

</html>

