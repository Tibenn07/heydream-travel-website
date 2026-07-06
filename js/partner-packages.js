// FILE: js/partner-packages.js
// Handle scrolling to the partner packages section if the page is loaded with the hash.

function scrollToPartnerPackagesSection() {
    const targetHash = '#partners-packages';
    if (window.location.hash === targetHash) {
        const section = document.querySelector(targetHash);
        if (section) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

function initializePartnerPackagesUI() {
    scrollToPartnerPackagesSection();

    document.querySelectorAll('[data-partner-package-link]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const targetId = link.getAttribute('data-partner-package-link');
            const target = document.getElementById(targetId);
            if (target) {
                event.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

window.addEventListener('DOMContentLoaded', initializePartnerPackagesUI);
window.initializePartnerPackagesUI = initializePartnerPackagesUI;
