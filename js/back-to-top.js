// ========================================
// FILE: js/back-to-top.js
// DESCRIPTION: Back to Top Button Functionality
// ========================================

// Back to Top Button - Show/hide on scroll
window.addEventListener('scroll', function() {
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        if (window.scrollY > 300) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }
    }
});