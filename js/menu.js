// ========================================
// FILE: js/menu.js
// DESCRIPTION: Modern Collapsible Sidebar Logic
// ========================================

document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('menuToggle');
    const sidePanel = document.getElementById('sidePanel');
    const panelOverlay = document.getElementById('panelOverlay');
    const collapseBtn = document.getElementById('sidebarCollapseBtn');

    // Basic Open/Close for mobile overlay
    function openMenu() {
        if (sidePanel && panelOverlay) {
            sidePanel.classList.add('open');
            panelOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            if (menuToggle) menuToggle.classList.add('open');

        }
    }

    function closeMenu() {
        if (sidePanel && panelOverlay) {
            sidePanel.classList.remove('open');
            panelOverlay.classList.remove('active');
            document.body.style.overflow = '';
            if (menuToggle) menuToggle.classList.remove('open');


            // Minimize custom chatbot window when sidebar is closed if active
            const windowEl = document.getElementById('hdChatWindow');
            const fab = document.getElementById('hdChatFab');
            if (windowEl && windowEl.classList.contains('active')) {
                windowEl.classList.remove('active');
                if (fab) fab.classList.remove('active');
            }

            // On mobile close, reset collapse state for next open
            if (window.innerWidth <= 1024) {
                sidePanel.classList.remove('collapsed');
            }
        }
    }

    if (menuToggle) menuToggle.addEventListener('click', openMenu);
    if (panelOverlay) panelOverlay.addEventListener('click', closeMenu);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidePanel && sidePanel.classList.contains('open')) {
            closeMenu();
        }
    });

    // Sidebar Collapse Logic
    if (collapseBtn && sidePanel) {
        collapseBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            sidePanel.classList.toggle('collapsed');

            // Close any open dropdowns when collapsing
            if (sidePanel.classList.contains('collapsed')) {
                const openDropdowns = sidePanel.querySelectorAll('.sidebar-nav-item.dropdown-open');
                openDropdowns.forEach(btn => {
                    const dropdownId = btn.getAttribute('onclick').match(/'([^']+)'/)[1];
                    toggleSidebarDropdown(dropdownId, btn);
                });
            }
        });
    }

});

// Global function for sidebar dropdowns
window.toggleSidebarDropdown = function (dropdownId, btnElement) {
    const dropdown = document.getElementById(dropdownId);
    const sidePanel = document.getElementById('sidePanel');

    if (!dropdown) return;

    // If sidebar is collapsed, expand it first
    if (sidePanel && sidePanel.classList.contains('collapsed')) {
        sidePanel.classList.remove('collapsed');
        // Wait for expand animation before opening dropdown
        setTimeout(() => toggleSidebarDropdown(dropdownId, btnElement), 300);
        return;
    }

    const isOpen = dropdown.classList.contains('open');

    // Close all other dropdowns
    const allDropdowns = document.querySelectorAll('.sidebar-dropdown-content');
    const allBtns = document.querySelectorAll('.sidebar-nav-item');

    allDropdowns.forEach(d => {
        if (d.id !== dropdownId) d.classList.remove('open');
    });

    allBtns.forEach(b => {
        if (b !== btnElement) b.classList.remove('dropdown-open');
    });

    // Toggle target dropdown
    if (isOpen) {
        dropdown.classList.remove('open');
        btnElement.classList.remove('dropdown-open');
    } else {
        dropdown.classList.add('open');
        btnElement.classList.add('dropdown-open');

        // Ensure the dropdown is visible without manual scrolling
        setTimeout(() => {
            dropdown.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 300);
    }
};

// Global function to open support chat directly
window.openSupportChat = function() {
    if (typeof window.toggleHeyDreamChatbot === 'function') {
        window.toggleHeyDreamChatbot();
        
        // Close the sidebar menu if it's open (mobile)
        const sidePanel = document.getElementById('sidePanel');
        const panelOverlay = document.getElementById('panelOverlay');
        const menuToggle = document.getElementById('menuToggle');
        
        if (sidePanel && sidePanel.classList.contains('open')) {
            sidePanel.classList.remove('open');
            if (panelOverlay) panelOverlay.classList.remove('active');
            document.body.style.overflow = '';
            if (menuToggle) menuToggle.classList.remove('open');
        }
    }
};