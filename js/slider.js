// ========================================
// FILE: js/slider.js
// DESCRIPTION: Sliding images & rotating title
// ========================================

document.addEventListener('DOMContentLoaded', function () {
    // ===== SLIDING IMAGES FUNCTIONALITY =====
    const slides = document.querySelectorAll('.sliding-images-container img');

    if (slides.length > 1) {
        let currentSlide = 0;

        setInterval(function () {
            slides.forEach(slide => {
                slide.classList.remove('active');
            });

            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }, 5000);
    }

    // ===== ROTATING TITLE =====
    const rotatingTitle = document.getElementById('rotating-title');
    if (rotatingTitle) {
        const phrases = [
            'Turn your dreams into destinations',
            'Explore the world',
            'Discover new places',
            'Travel with ease',
            'Your journey begins here',
            'Escape the ordinary',
            'Dream it. Plan it. Travel it',
            'With your trusted travel partner',
            'HeyDream Travel & Tours'

        ];
        let phraseIndex = 0;

        setInterval(function () {
            phraseIndex = (phraseIndex + 1) % phrases.length;
            rotatingTitle.style.opacity = '0';
            setTimeout(function () {
                rotatingTitle.textContent = phrases[phraseIndex];
                rotatingTitle.style.opacity = '1';
            }, 300);
        }, 4000);
    }
});