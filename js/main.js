// ========================================
// FILE: js/main.js
// DESCRIPTION: Main shared functionality
// ========================================

// View package function (used across pages)
function viewPackage(destination) {
    alert(`Viewing package for ${destination}`);
}

// Show notification
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `saved-notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.add('show');
    }, 10);

    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 2000);
}

// Save item to localStorage
function saveItem(item) {
    let savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];

    if (!savedItems.some(saved => saved.id === item.id)) {
        savedItems.push(item);
        localStorage.setItem('savedItems', JSON.stringify(savedItems));
        showNotification('✓ Saved to your list!', 'success');
        return true;
    } else {
        showNotification('⚠️ Already in your saved items', 'error');
        return false;
    }
}

// Remove saved item
function removeSavedItem(id, event) {
    if (event) {
        event.stopPropagation();
    }

    const savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];
    const updatedItems = savedItems.filter(item => item.id !== id);
    localStorage.setItem('savedItems', JSON.stringify(updatedItems));

    if (typeof displaySavedItems === 'function') displaySavedItems();
    if (typeof updatePanelSavedItems === 'function') updatePanelSavedItems();

    showNotification('❌ Removed from saved', 'error');
}

// Update saved count badge
function updateSavedCount() {
    const savedCountElements = document.querySelectorAll('#savedCount');
    const savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];

    savedCountElements.forEach(element => {
        if (element) element.textContent = savedItems.length;
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    updateSavedCount();

    // Navbar scroll effect
    const nav = document.querySelector('.navbar');
    if (nav) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });
    }

    // User Menu Dropdown Toggle
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    const userWrapper = document.querySelector('.user-menu-wrapper');

    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
            userWrapper.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (userWrapper && !userWrapper.contains(e.target)) {
                userDropdown.classList.remove('show');
                userWrapper.classList.remove('active');
            }
        });
    }

    // --- Dynamic Localista Logo Application to All Pages ---
    const companyNames = document.querySelectorAll('.company-name');
    companyNames.forEach(container => {
        // Determine the correct relative path prefix by looking at the adjacent logo img
        let prefix = '';
        const prevSibling = container.previousElementSibling;
        if (prevSibling && prevSibling.tagName === 'IMG' && prevSibling.getAttribute('src')) {
            if (prevSibling.getAttribute('src').startsWith('../')) {
                prefix = '../';
            }
        }

        container.innerHTML = `
            <style>
                .company-logo-img { height: 80px; object-fit: contain; cursor: pointer; transition: transform 0.3s ease; }
                .company-logo-img:hover { transform: scale(1.1); }
                @media (max-width: 768px) { .company-logo-img { height: 30px; } }
            </style>
            <img src="${prefix}images/Localista (1).png" alt="HeyDream Travel and Tours" class="company-logo-img" onclick="window.location.href='${prefix}index.php'">
        `;
    });
});