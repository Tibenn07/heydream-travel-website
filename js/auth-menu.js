// ========================================
// FILE: js/auth-menu.js
// DESCRIPTION: Dynamic auth menu in side panel
// ========================================

function getBasePath() {
    const path = window.location.pathname;
    return (path.includes('/buttons/') || path.includes('/api/') || path.includes('/User%20Account/') || path.includes('/User Account/')) ? '../' : '';
}

// Function to update auth section based on login status
function updateAuthSection() {
    // Get new sidebar elements
    const userRoleEl = document.getElementById('sidebarUserRole');
    const userNameEl = document.getElementById('sidebarUserName');
    const avatarEl = document.getElementById('sidebarAvatar');
    const loginBtn = document.getElementById('sidebarLoginBtn');
    const logoutBtn = document.getElementById('sidebarLogoutBtn');
    const navAccountToggle = document.getElementById('nav-account-toggle');
    const accountDropdown = document.getElementById('accountDropdown');
    const navMyBooking = document.getElementById('nav-my-booking');
    const navChangePassword = document.getElementById('nav-change-password');

    // Check if user is logged in via session
    fetch(getBasePath() + 'api/check-auth.php')
        .then(response => response.json())
        .then(data => {
            if (data.logged_in) {
                // Set global user variables for booking forms
                window.currentUserEmail = data.user.email;
                window.currentFullName = data.user.full_name;

                // Update Profile Header
                if (userRoleEl) userRoleEl.textContent = 'Member';
                if (userNameEl) userNameEl.textContent = data.user.full_name;
                if (avatarEl) {
                    if (data.user.profile_pic) {
                        avatarEl.innerHTML = '<img src="' + getBasePath() + data.user.profile_pic + '" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
                    } else {
                        avatarEl.textContent = data.user.initials;
                    }
                }

                // Toggle login/logout buttons
                if (loginBtn) loginBtn.style.display = 'none';
                if (logoutBtn) logoutBtn.style.display = 'flex';

                // Show My Account, My Booking & Change Password sections
                if (navAccountToggle) navAccountToggle.style.display = 'flex';
                if (navMyBooking) navMyBooking.style.display = 'flex';
                if (navChangePassword) navChangePassword.style.display = 'block';

                // Update saved count if available
                updateSavedCount();

                // Fetch booking count
                fetchBookingCount();

            } else {
                // Reset Profile Header for guests
                if (userRoleEl) userRoleEl.textContent = 'Guest';
                if (userNameEl) userNameEl.textContent = 'Welcome!';
                if (avatarEl) avatarEl.innerHTML = '<i class="fas fa-user"></i>';

                // Toggle login/logout buttons
                if (loginBtn) loginBtn.style.display = 'flex';
                if (logoutBtn) logoutBtn.style.display = 'none';

                // Hide My Account, My Booking & Change Password sections
                if (navAccountToggle) navAccountToggle.style.display = 'none';
                if (accountDropdown) accountDropdown.classList.remove('show');
                if (navMyBooking) navMyBooking.style.display = 'none';
                if (navChangePassword) navChangePassword.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error checking auth status:', error);
            // Fallback for guests
            if (userRoleEl) userRoleEl.textContent = 'Guest';
            if (userNameEl) userNameEl.textContent = 'Welcome!';
            if (avatarEl) avatarEl.innerHTML = '<i class="fas fa-user"></i>';
            if (loginBtn) loginBtn.style.display = 'flex';
            if (logoutBtn) logoutBtn.style.display = 'none';
            if (navAccountToggle) navAccountToggle.style.display = 'none';
            if (accountDropdown) accountDropdown.classList.remove('show');
            if (navMyBooking) navMyBooking.style.display = 'none';
            if (navChangePassword) navChangePassword.style.display = 'none';
        });
}


// Function to update saved count
function updateSavedCount() {
    const savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];
    const savedCountElements = document.querySelectorAll('#savedCount, #savedCountMenu');
    savedCountElements.forEach(el => {
        if (el) el.textContent = savedItems.length;
    });
}

// Function to fetch booking count
function fetchBookingCount() {
    if (!isLoggedIn()) return;

    fetch(getBasePath() + 'api/get-booking-count.php')
        .then(response => response.json())
        .then(data => {
            const bookingCountElem = document.getElementById('bookingCount');
            if (bookingCountElem && data.count !== undefined) {
                bookingCountElem.textContent = data.count;
                if (data.count > 0) {
                    bookingCountElem.style.display = 'inline-block';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching booking count:', error);
        });
}

// Function to check if user is logged in (from session)
function isLoggedIn() {
    // This will be updated by the check-auth API
    return document.cookie.includes('PHPSESSID');
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Function to initialize the side panel with auth section
function initSidePanelWithAuth() {
    // Update auth section when page loads
    updateAuthSection();

    // Listen for storage events (for saved items)
    window.addEventListener('storage', function (e) {
        if (e.key === 'savedItems') {
            updateSavedCount();
        }
    });

    // Listen for custom events
    document.addEventListener('savedItemsUpdated', updateSavedCount);
    document.addEventListener('bookingUpdated', fetchBookingCount);
}

// ============================================
// ADDED: Login check function for bookings
// ============================================

/**
 * Shows a beautiful custom popup for login requirements
 * @param {string} loginPath Path to the login page
 * @param {string} registerPath Path to the registration page
 */
function showLoginRequiredPopup(loginPath, registerPath) {
    // Determine default paths if not provided
    if (!loginPath) {
        const isSubdir = window.location.pathname.includes('/buttons/') || window.location.pathname.includes('/api/');
        loginPath = isSubdir ? '../User Account/login.php' : 'User Account/login.php';
    }
    if (!registerPath) {
        const isSubdir = window.location.pathname.includes('/buttons/') || window.location.pathname.includes('/api/');
        registerPath = isSubdir ? '../User Account/register.php' : 'User Account/register.php';
    }

    // Create modal if it doesn't exist
    let modal = document.getElementById('loginRequiredModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'loginRequiredModal';
        modal.className = 'login-required-modal';
        modal.style.zIndex = '2147483647';
        modal.style.pointerEvents = 'auto';
        modal.innerHTML = `
            <div class="login-required-modal-content">
                <div class="login-required-header">
                    <span class="close-login-modal" onclick="closeLoginRequiredModal()">&times;</span>
                    <div class="icon-container">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h2>Login Required</h2>
                </div>
                <div class="login-required-body">
                    <h3>Continue Your Booking</h3>
                    <p>Please sign in to your HeyDream account to provide passenger information and secure your reservation. Don't have an account yet? Join us today!</p>
                    <div class="login-required-buttons">
                        <a href="${loginPath}?redirect=${encodeURIComponent(window.location.href)}" class="login-btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Sign In to Continue
                        </a>
                        <a href="${registerPath}?redirect=${encodeURIComponent(window.location.href)}" class="login-btn-secondary">
                            <i class="fas fa-user-plus"></i> Create Account
                        </a>
                        <button class="login-btn-text" onclick="closeLoginRequiredModal()">Maybe later</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Close on background click
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeLoginRequiredModal();
            }
        });
    }

    // Show modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent scroll
}

/**
 * Closes the login required modal
 */
function closeLoginRequiredModal() {
    const modal = document.getElementById('loginRequiredModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = ''; // Restore scroll
    }
}

// ============================================
// LOGOUT CONFIRMATION MODAL
// ============================================

/**
 * Shows a beautiful custom popup for logout confirmation
 */
function showLogoutConfirmPopup() {
    const isSubdir = window.location.pathname.includes('/buttons/') || window.location.pathname.includes('/api/');
    const logoutProcessPath = isSubdir ? '../User Account/process-logout.php' : 'User Account/process-logout.php';

    // Create modal if it doesn't exist
    let modal = document.getElementById('logoutConfirmModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'logoutConfirmModal';
        modal.className = 'logout-confirm-modal';

        const userName = window.currentFullName ? escapeHtml(window.currentFullName) : '';
        const greetingHtml = userName ? `<div class="logout-user-greeting" style="font-weight: 700; color: #003580; font-size: 1.1rem; margin-bottom: 8px;">${userName}</div>` : '';

        modal.innerHTML = `
            <div class="logout-confirm-content">
                <div class="logout-header">
                    <div class="icon-container">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <h2>Secure Sign Out</h2>
                </div>
                <div class="logout-body">
                    ${greetingHtml}
                    <p style="color: #555; font-size: 0.95rem; line-height: 1.5; margin-bottom: 20px;">You are about to sign out of your HeyDream Travel account. For your security, please confirm if you wish to proceed.</p>
                    <div class="logout-buttons">
                        <button class="logout-btn-cancel" onclick="closeLogoutConfirmModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button class="logout-btn-confirm" id="confirmLogoutBtn" onclick="executeLogout('${logoutProcessPath}')">
                            <i class="fas fa-check"></i> Confirm Sign Out
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Close on background click
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeLogoutConfirmModal();
            }
        });
    }

    // Show modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent scroll
}

/**
 * Closes the logout confirm modal
 */
function closeLogoutConfirmModal() {
    const modal = document.getElementById('logoutConfirmModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = ''; // Restore scroll
    }
}

/**
 * Executes the actual logout by adding a loading state and redirecting
 */
function executeLogout(logoutProcessPath) {
    const btn = document.getElementById('confirmLogoutBtn');
    if (btn) {
        btn.innerHTML = '<span class="loading-spinner-logout"></span> Signing out...';
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.8';
    }
    window.location.href = logoutProcessPath;
}

/**
 * Main function to enforce login before actions
 */
function requireLogin(callback, ...args) {
    const isSubdir = window.location.pathname.includes('/buttons/') || window.location.pathname.includes('/api/') || window.location.pathname.includes('/User Account/') || window.location.pathname.includes('/User%20Account/');
    const loginPath = isSubdir ? '../User Account/login.php' : 'User Account/login.php';
    const registerPath = isSubdir ? '../User Account/register.php' : 'User Account/register.php';
    const checkAuthPath = isSubdir ? '../api/check-auth.php' : 'api/check-auth.php';

    fetch(checkAuthPath)
        .then(response => response.json())
        .then(data => {
            if (data.logged_in) {
                if (typeof callback === 'function') {
                    callback(...args);
                } else if (typeof window[callback] === 'function') {
                    window[callback](...args);
                }
            } else {
                // Store pending action to resume after login
                const callbackName = typeof callback === 'function' ? callback.name : callback;
                if (callbackName) {
                    sessionStorage.setItem('pending_action', JSON.stringify({ name: callbackName, args }));
                }

                // Use the new custom designed popup
                showLoginRequiredPopup(loginPath, registerPath);
            }
        })
        .catch(error => {
            console.error('Error checking auth status:', error);
            // Fallback: If auth check fails, try to proceed anyway to avoid blocking the user experience
            if (typeof callback === 'function') {
                callback(...args);
            } else if (typeof window[callback] === 'function') {
                window[callback](...args);
            }
        });
}

/**
 * Checks for and executes any pending action stored in sessionStorage
 */
function handlePendingAction() {
    const pendingActionStr = sessionStorage.getItem('pending_action');
    if (!pendingActionStr) return;

    // Check if user is logged in before executing
    fetch(getBasePath() + 'api/check-auth.php')
        .then(res => res.json())
        .then(data => {
            if (data.logged_in) {
                const action = JSON.parse(pendingActionStr);
                sessionStorage.removeItem('pending_action');

                if (typeof window[action.name] === 'function') {
                    console.log('Executing pending action:', action.name);
                    window[action.name](...action.args);
                }
            }
        })
        .catch(err => console.error('Error handling pending action:', err));
}

// Navigation helpers
function goToSaved() { window.location.href = getBasePath() + 'saved.php'; }
function goToProfile() { window.location.href = getBasePath() + 'User Account/profile.php'; }

// The sidebar/nav "Sign In" links are static HTML with no redirect target,
// so login always dropped the user back on index.php regardless of which
// page they clicked "Sign In" from. Stamp the current page onto every such
// link so login.php's ?redirect= sends them back to where they actually were.
function fixLoginLinksRedirect() {
    const here = encodeURIComponent(window.location.href);
    document.querySelectorAll('a[href*="login.php"]').forEach(function (a) {
        const href = a.getAttribute('href');
        if (!href || href.indexOf('redirect=') !== -1 || href.indexOf('login.php?logout') !== -1) return;
        const sep = href.indexOf('?') === -1 ? '?' : '&';
        a.setAttribute('href', href + sep + 'redirect=' + here);
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    initSidePanelWithAuth();
    handlePendingAction();
    fixLoginLinksRedirect();

    // If there's an existing menu toggle, make sure auth updates when menu opens
    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            // Small delay to ensure panel is open
            setTimeout(updateAuthSection, 100);
        });
    }
});

// Also update when panel opens via other methods
const sidePanel = document.getElementById('sidePanel');
if (sidePanel) {
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.attributeName === 'class' && sidePanel.classList.contains('open')) {
                updateAuthSection();
            }
        });
    });
    observer.observe(sidePanel, { attributes: true });
}