<?php
$filePath = '../index.php';
$content = file_get_contents($filePath);

$searchMarker = '                <button class="compact-menu-btn dropdown-toggle">
                    <span class="menu-icon"><i class="fas fa-calendar-alt" style="color: #ff9800;"></i></span>
                    <span class="menu-text">Booking</span>
                    <span class="dropdown-arrow">▼</span>
                </button>';

$bottomMarker = '<div class="hero-overlay"></div>';

$replacement = $searchMarker . '
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
';

// Find the target block using a very specific context
$targetStart = strpos($content, $searchMarker);
$targetEnd = strpos($content, $bottomMarker);

if ($targetStart !== false && $targetEnd !== false) {
    $newContent = substr($content, 0, $targetStart) . $replacement . substr($content, $targetEnd);
    file_put_contents($filePath, $newContent);
    echo "Successfully recovered index.php\n";
} else {
    echo "Could not find markers in index.php\n";
    if ($targetStart === false) echo "Start marker missing\n";
    if ($targetEnd === false) echo "End marker missing\n";
}
?>
