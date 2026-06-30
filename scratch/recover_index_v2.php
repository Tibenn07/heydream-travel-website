<?php
$filePath = '../index.php';
$content = file_get_contents($filePath);

// Regex that finds the specific point where the code was cut off
// It looks for the end of the Booking button and the start of the hero-overlay div
$regex = '/<\/button>\s+<div class="hero-overlay">/s';

$replacement = '</button>
                <div class="dropdown-content">
                    <a href="foreign-destinations.php" class="dropdown-item"><span class="item-icon"><i class="fas fa-plane" style="color: #17a2b8;"></i></span> Foreign
                        Tours</a>
                    <a href="local-destination.php" class="dropdown-item"><span class="item-icon"><i class="fas fa-umbrella-beach" style="color: #ff9800;"></i></span> Local
                        Tours</a>
                    <a href="#" class="dropdown-item"><span class="item-icon"><i class="fas fa-hotel" style="color: #003580;"></i></span> Hotels</a>
                    <a href="#" class="dropdown-item"><span class="item-icon"><i class="fas fa-car" style="color: #dc3545;"></i></span> Car Rentals</a>
                </div>
            </div>
            <a href="tel:+639177220904" class="compact-menu-btn call-btn">
                <span class="menu-icon"><i class="fas fa-phone-alt" style="color: #003580;"></i></span>
                <span class="menu-text">Call Us</span>
                <span class="call-number">0917 722 0904</span>
            </a>
        </div>
    </div>
</div>

    <!-- HERO SECTION WITH SLIDING IMAGES -->
    <section class="hero" style="position: relative; overflow: visible;">
        <div class="sliding-images-container">
            <img src="images/siargao.jpg" alt="Siargao Island" class="active">
            <img src="images/boracay.jpg" alt="Boracay Beach">
            <img src="images/cebu.jpg" alt="Cebu">
            <img src="images/palawan.jpg" alt="Palawan">
            <img src="images/bohol.jpg" alt="Bohol">
            <img src="images/elnido.jpg" alt="El Nido">
        </div>
        <div class="hero-overlay">';

$newContent = preg_replace($regex, $replacement, $content);

if ($newContent !== null && $newContent !== $content) {
    file_put_contents($filePath, $newContent);
    echo "RECOVERY SUCCESSFUL";
} else {
    echo "RECOVERY FAILED: REGEX DID NOT MATCH OR ERROR";
}
?>
