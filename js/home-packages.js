// ========================================
// FILE: js/home-packages.js
// DESCRIPTION: Homepage Local Packages Popup Functionality
// Completely independent - ONLY handles local destinations
// Updated design to match foreign-packages.js
// Added improved payment method design
// ========================================

// Hotel Selection State
window.homeSelectedHotelSurcharge = 0;
window.toggleHomeHotelSelection = function () {
    const dropdown = document.getElementById('homeHotelDropdown');
    if (dropdown) dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
};
window.selectHomeHotel = function (index, name, stars, price) {
    const basePrice = window.currentHomeDest ? window.currentHomeDest.price : 0;
    const nameEl = document.getElementById('homeSelectedHotelName');
    const starHtml = stars ? ` <span style="font-size:0.8rem;">${'⭐'.repeat(stars)}</span>` : '';
    if (nameEl) nameEl.innerHTML = name + starHtml + ' <i class="fas fa-chevron-down" style="font-size:0.7rem; margin-left:5px;"></i>';
    window.homeSelectedHotelSurcharge = price;
    if (typeof updateHomeTotalPrice === 'function') updateHomeTotalPrice(basePrice);
    const dropdown = document.getElementById('homeHotelDropdown');
    if (dropdown) dropdown.style.display = 'none';
    document.querySelectorAll('#homeHotelDropdown .hotel-option').forEach((el) => {
        el.style.background = Number(el.dataset.hotelIndex) === index ? '#e3f2fd' : 'white';
    });
};

// Global variable to store ONLY local destinations
let homePackageData = {};
// Cache for local destinations loaded via search (by ID)
let localPackageDataCache = {};

// Format number with commas
function formatNumber(num) {
    if (!num) return '0';
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Formats a date range for Travel Validity
 */
function formatValidityDate(start, end, fallback) {
    if (!start && !end) return fallback || 'Year Round';

    const options = { day: '2-digit', month: 'long', year: 'numeric' };

    try {
        if (start && end) {
            const startDate = new Date(start).toLocaleDateString('en-GB', options);
            const endDate = new Date(end).toLocaleDateString('en-GB', options);
            return `${startDate} - ${endDate}`;
        }
        if (start) {
            return `From ${new Date(start).toLocaleDateString('en-GB', options)}`;
        }
        if (end) {
            return `Until ${new Date(end).toLocaleDateString('en-GB', options)}`;
        }
    } catch (e) {
        return fallback || 'Year Round';
    }

    return fallback || 'Year Round';
}

// Function to initialize with data from PHP (local destinations only)
function initHomePackages(destinationsData) {
    // Clear existing data
    homePackageData = {};

    console.log('=== HOME PACKAGES INITIALIZED ===');
    console.log('Raw local destinations data:', destinationsData);

    if (destinationsData && destinationsData.length > 0) {
        // Convert array to object keyed by destination key with 'local_' prefix
        destinationsData.forEach(dest => {
            // Create a unique key with prefix to avoid conflicts with foreign destinations
            const key = 'local_' + dest.name.toLowerCase().replace(/ /g, '_');

            console.log('Processing local destination:', dest.name, 'with key:', key);

            homePackageData[key] = {
                id: dest.id,
                name: dest.name,
                location: dest.location_name || dest.city || 'Philippines',
                price: parseFloat(dest.price) || 0,
                currency: dest.currency || '₱',
                duration: dest.duration || '3D/2N',
                activities: parseInt(dest.activities_count) || 0,
                groupSize: dest.group_size || '2-15 pax',
                bestSeason: dest.best_season || 'Year Round',
                description: dest.description || 'Experience the beauty of this amazing destination.',
                itinerary: Array.isArray(dest.itinerary) ? dest.itinerary : [],
                inclusions: Array.isArray(dest.inclusions) ? dest.inclusions : [],
                exclusions: Array.isArray(dest.exclusions) ? dest.exclusions : [],
                hotels: Array.isArray(dest.hotels) ? dest.hotels : [],
                image_path: dest.image_path || null,
                badge: dest.badge_text || (dest.activities_count + ' activities'),
                remarks: dest.remarks || '',
                promo_start: dest.promo_start,
                promo_end: dest.promo_end,
                blocked_months: dest.blocked_months,
                highlight_duration: parseInt(dest.highlight_duration || 1),
                partner_id: dest.partner_id ?? null,
                partner_company: dest.partner_company || null,
                partner_source: dest.partner_source || null
            };
        });
    }

    console.log('Home LOCAL destinations initialized:', Object.keys(homePackageData).length);
    console.log('Available local keys:', Object.keys(homePackageData));

    // RENDER THE LOCAL DESTINATIONS GRID
    renderLocalDestinationsGrid();
}

// FUNCTION TO RENDER LOCAL DESTINATIONS TO THE GRID - UPDATED DESIGN
function renderLocalDestinationsGrid() {
    const localGrid = document.getElementById('localDestinationsGrid');

    if (!localGrid) {
        console.error('localDestinationsGrid not found!');
        return;
    }

    // Clear loading spinner
    localGrid.innerHTML = '';

    const destinationsArray = Object.values(homePackageData);

    if (destinationsArray.length === 0) {
        localGrid.innerHTML = '<div class="loading-spinner" style="grid-column: 1/-1; text-align: center; padding: 40px;"><i class="fas fa-info-circle" style="font-size: 2rem; color: #003580;"></i><p style="margin-top: 10px;">No local destinations available yet. Check back soon!</p></div>';
        return;
    }

    // Render each local destination with modern card design
    destinationsArray.forEach(dest => {
        const destKey = 'local_' + dest.name.toLowerCase().replace(/ /g, '_');

        // Handle image path
        let imagePath = 'https://via.placeholder.com/400x250?text=' + encodeURIComponent(dest.name);
        if (dest.image_path) {
            if (dest.image_path.startsWith('http')) {
                imagePath = dest.image_path;
            } else if (dest.image_path.startsWith('uploads/')) {
                imagePath = dest.image_path;
            } else {
                imagePath = 'uploads/' + dest.image_path;
            }
        }

        const card = document.createElement('div');
        card.className = 'home-destination-card';
        card.setAttribute('data-destination', destKey);
        card.onclick = () => showLocalPackagePopup(destKey);

        const badgeHtml = dest.badge ? `<div class="card-badge">${escapeHtml(dest.badge)}</div>` : '';

        card.innerHTML = `
            <div class="home-card-image">
                <img src="${imagePath}"
                     alt="${escapeHtml(dest.name)}"
                     onerror="this.src='https://via.placeholder.com/400x250?text=' + encodeURIComponent('${escapeHtml(dest.name)}')">
                <h3 class="home-card-name">${escapeHtml(dest.name)}</h3>
            </div>
            <div class="home-card-content">
                ${badgeHtml}
                <div class="home-card-location">
                    <i class="fas fa-map-marker-alt"></i> ${escapeHtml(dest.location)}
                </div>
                <p class="home-card-desc">${escapeHtml(dest.description)}</p>
                ${dest.partner_id ? `<div style="font-size: 0.8rem; color: #64748b; margin-top: 5px; margin-bottom: 5px;">Provided by: <a href="view-partner-profile.php?id=${dest.partner_id}" style="color: #003580; font-weight: 500; text-decoration: none;" onclick="event.stopPropagation();">${escapeHtml(dest.partner_company || 'Partner')}</a></div>` : ''}
                <div class="home-card-footer">
                    <div class="home-card-price">
                        From ${dest.currency}${formatNumber(dest.price)}
                        <small>/ person</small>
                    </div>
                    <button class="home-card-btn" onclick="event.stopPropagation(); showLocalPackagePopup('${destKey}')">
                        View Tour Details →
                    </button>
                </div>
            </div>
        `;


        localGrid.appendChild(card);
    });
}

// Load LOCAL destination data from database API
async function loadLocalDestinationFromDatabase(id) {
    // Check cache first
    if (localPackageDataCache[id]) {
        return localPackageDataCache[id];
    }

    try {
        const response = await fetch(`api/get-local-destinations.php?id=${id}`);
        const data = await response.json();

        if (data.success && data.destination) {
            const dest = data.destination;

            // Parse JSON strings if needed (database returns strings)
            ['itinerary', 'inclusions', 'exclusions', 'hotels'].forEach(field => {
                if (typeof dest[field] === 'string') {
                    try {
                        dest[field] = JSON.parse(dest[field]);
                    } catch (e) { dest[field] = []; }
                }
                if (!dest[field]) dest[field] = [];
            });

            // Set default values matching initHomePackages
            dest.location = dest.location_name || dest.city || 'Philippines';
            dest.price = parseFloat(dest.price) || 0;
            dest.currency = dest.currency || '₱';
            dest.duration = dest.duration || '3D/2N';
            dest.activities = parseInt(dest.activities_count) || 0;
            dest.groupSize = dest.group_size || '2-15 pax';
            dest.bestSeason = dest.best_season || 'Year Round';
            dest.description = dest.description || 'Experience the beauty of this amazing destination.';
            dest.badge = dest.badge_text || (dest.activities_count + ' activities');
            dest.highlight_duration = parseInt(dest.highlight_duration || 1);

            localPackageDataCache[id] = dest;
            return dest;
        }
        return null;
    } catch (error) {
        console.error('Error loading local destination from database:', error);
        return null;
    }
}

window.switchHomeTab = function (event, tabId) {
    const tabs = event.target.parentElement.querySelectorAll('.home-tab');
    tabs.forEach(t => {
        t.classList.remove('active');
        t.style.color = '#666';
        t.style.borderBottomColor = 'transparent';
    });
    event.target.classList.add('active');
    event.target.style.color = '#003580';
    event.target.style.borderBottomColor = '#ff9800';

    const modalBody = event.target.closest('.package-modal-body') || document.getElementById('homeDestinationModalBody');
    if (modalBody) {
        modalBody.querySelectorAll('.home-pane').forEach(p => p.style.display = 'none');
        const targetPane = modalBody.querySelector('#home-pane-' + tabId);
        if (targetPane) targetPane.style.display = 'block';
    }
};

// "View Tour Details" now navigates to the full package-details.php page.
// The original modal-building logic is kept as showLocalPackagePopupModal —
// used by package-details.php's "Book This Deal" button (via resumeHomeBooking)
// to open the booking flow directly, without rebuilding it.
window.showLocalPackagePopup = function (identifier) {
    window.location.href = `package-details.php?type=local&id=${encodeURIComponent(identifier)}`;
};

// Show destination details directly - ONLY for local packages - UPDATED MODAL DESIGN
window.showLocalPackagePopupModal = async function (identifier) {
    console.log('=== showLocalPackagePopupModal called ===');
    console.log('Identifier:', identifier);

    let destination = null;

    // 1. Try to find in homePackageData (if it's a key like 'local_siargao')
    if (typeof identifier === 'string' && homePackageData[identifier]) {
        destination = homePackageData[identifier];
    }
    // 2. Try to find by ID in homePackageData
    else {
        destination = Object.values(homePackageData).find(d => d.id == identifier);
    }

    // 3. If still not found, fetch from database (crucial for saved.php)
    if (!destination && identifier) {
        // Show universal modal with loading state first
        ensureLocalModalExists();
        const modal = document.getElementById('homeDestinationModal');
        if (modal) {
            modal.classList.add('active');
            // Show loading placeholder content
            const titleEl = document.getElementById('homeModalDestName');
            const locEl = document.getElementById('homeModalDestLocation');
            const bodyEl = document.getElementById('homeDestinationModalBody');

            if (titleEl) titleEl.textContent = 'Loading...';
            if (locEl) locEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching details...';
            if (bodyEl) bodyEl.innerHTML = '<div style="text-align:center; padding:50px; color:#003580;"><i class="fas fa-spinner fa-spin" style="font-size:2.5rem;"></i><p style="margin-top:15px; font-weight:600;">Connecting to HeyDream systems...</p></div>';
        }

        destination = await loadLocalDestinationFromDatabase(identifier);
    }

    if (!destination) {
        console.error('Local destination not found for:', identifier);
        alert('Package details coming soon!');
        return;
    }

    console.log('Found local destination:', destination.name);
    window.currentHomeDestCurrency = destination.currency || '₱';

    // Create modal if it doesn't exist
    let modal = document.getElementById('homeDestinationModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'homeDestinationModal';
        modal.className = 'package-modal';
        modal.innerHTML = `
            <div class="package-modal-content" style="position:relative;">
                <div class="close-modal-circle" onclick="closeHomeDestinationModal()" style="position:absolute; top:15px; right:15px; width:35px; height:35px; background:rgba(0,0,0,0.5); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.4rem; cursor:pointer; z-index:2000; backdrop-filter:blur(4px); transition:all 0.3s; border: 1px solid rgba(255,255,255,0.2);">&times;</div>
                <div class="package-modal-header" style="display:none;">
                    <span class="close-modal" onclick="closeHomeDestinationModal()">&times;</span>
                    <h2 id="homeModalDestName">Loading...</h2>
                    <p id="homeModalDestLocation"><i class="fas fa-map-marker-alt"></i> Loading...</p>
                </div>
                <div class="package-modal-body" id="homeDestinationModalBody">
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
                        <p>Loading tour details...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeHomeDestinationModal();
            }
        });
    }

    // Update modal content
    window.currentHomeDest = destination; // Store for booking
    window.homeSelectedHotelSurcharge = 0; // Initialize surcharge
    document.getElementById('homeModalDestName').textContent = destination.name;
    document.getElementById('homeModalDestLocation').innerHTML = `<i class="fas fa-map-marker-alt"></i> ${destination.location}, Philippines`;
    const modalBody = document.getElementById('homeDestinationModalBody');

    // Build itinerary HTML
    let itineraryHtml = `<div class="itinerary-section"><h3><i class="fas fa-list-ol"></i> Tour Itinerary</h3>`;

    if (destination.itinerary && destination.itinerary.length > 0) {
        destination.itinerary.forEach(day => {
            const dayTitle = day.title || `Day ${day.day}`;
            itineraryHtml += `
                <div class="itinerary-day">
                    <h4>
                        <span class="day-badge" style="background: #ff9800; color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; margin-right: 8px; display: inline-block;">
                            ${escapeHtml(dayTitle.split(':')[0])}
                        </span>
                        ${escapeHtml(dayTitle.split(':')[1] || dayTitle)}
                    </h4>
                    <ul>
            `;
            if (day.activities && day.activities.length > 0) {
                day.activities.forEach(activity => itineraryHtml += `<li>${escapeHtml(activity)}</li>`);
            } else {
                itineraryHtml += `<li>Details will be provided upon booking</li>`;
            }
            itineraryHtml += `</ul></div>`;
        });
    } else {
        itineraryHtml += `<p>Itinerary details will be provided upon booking.</p>`;
    }
    itineraryHtml += `</div>`;

    // Build remarks HTML
    let remarksHtml = '';
    if (destination.remarks && destination.remarks.trim().length > 0) {
        remarksHtml = `
            <div class="remarks-section" style="margin-top: 20px; margin-bottom: 20px; padding: 15px; background: #fffde7; border-left: 4px solid #fbc02d; border-radius: 8px;">
                <h3 style="margin-top: 0; margin-bottom: 10px; color: #f57f17; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-info-circle"></i> Remarks and Note
                </h3>
                <div style="white-space: pre-wrap; color: #5d4037; font-size: 0.95rem; line-height: 1.5;">${escapeHtml(destination.remarks)}</div>
            </div>
        `;
    }


    // Build inclusions HTML
    let inclusionsHtml = `<div class="inclusions-section"><h3><i class="fas fa-check-circle"></i> Package Inclusions</h3><ul class="inclusions-list">`;
    if (destination.inclusions && destination.inclusions.length > 0) {
        destination.inclusions.forEach(item => inclusionsHtml += `<li>${escapeHtml(item)}</li>`);
    } else {
        inclusionsHtml += `<li>Details will be provided upon booking</li>`;
    }
    inclusionsHtml += `</ul></div>`;

    // Build exclusions HTML
    let exclusionsHtml = '';
    if (destination.exclusions && destination.exclusions.length > 0) {
        exclusionsHtml = `<div class="exclusions-section"><h3><i class="fas fa-times-circle"></i> Package Exclusions</h3><ul class="exclusions-list">`;
        destination.exclusions.forEach(item => exclusionsHtml += `<li>${escapeHtml(item)}</li>`);
        exclusionsHtml += `</ul></div>`;
    }

    // Build the full modal content with modern design and improved payment methods
    let html = `
        <span class="close-modal" onclick="closeHomeDestinationModal()" style="position:absolute; top:15px; right:20px; color:white; font-size:1.8rem; cursor:pointer; z-index:10; text-shadow:0 2px 4px rgba(0,0,0,0.5);">&times;</span>
        
        <div id="homeDetailsView">
            <div style="position:relative; height:250px; margin:-20px -20px 20px -20px; border-radius:24px 24px 0 0; overflow:hidden; background:#f0f0f0;">
                ${(() => {
            const images = destination.images || [];
            if (images.length === 0) {
                if (destination.image_path) images.push(destination.image_path);
                if (destination.image2_path) images.push(destination.image2_path);
                if (destination.image3_path) images.push(destination.image3_path);
            }

            if (images.length > 1) {
                return `
                            <div class="flash-slider-container" style="position:relative; width:100%; height:100%; overflow:hidden;">
                                ${images.map((img, i) => `
                                    <div class="flash-slide ${i === 0 ? 'active' : ''}" style="background-image:url('${escapeHtml(img)}');"></div>
                                `).join('')}
                                
                                <div class="flash-slider-dots">
                                    ${images.map((_, i) => `
                                        <div class="flash-slide-dot ${i === 0 ? 'active' : ''}" data-index="${i}"></div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
            } else {
                return `<img src="${escapeHtml(images[0] || 'images/placeholder-dest.jpg')}" alt="${escapeHtml(destination.name)}" style="width:100%; height:100%; object-fit:cover;">`;
            }
        })()}
                <div style="position:absolute; bottom:0; left:0; right:0; padding:40px 20px 15px; background:linear-gradient(to top, rgba(0,0,0,0.9), transparent); color:white; z-index:10;">
                    <h2 style="margin:0; font-size:1.6rem; text-shadow:0 2px 10px rgba(0,0,0,0.9), 0 1px 4px rgba(0,0,0,0.7); color: #ffffff !important; font-weight: 800;">${escapeHtml(destination.name)}</h2>
                    <p style="margin:5px 0 0; font-size:0.85rem; text-shadow:0 1px 4px rgba(0,0,0,0.7); color: #ffffff !important; font-weight: 600;"><i class="fas fa-map-marker-alt" style="color:#ff9800;"></i> ${escapeHtml(destination.location)}, Philippines | <i class="fas fa-clock" style="color:#ff9800;"></i> ${escapeHtml(destination.duration)}</p>
                </div>
            </div>
            
            <div style="display:flex; overflow-x:auto; border-bottom:1px solid #ddd; margin-bottom:20px;">
                <div class="home-tab active" onclick="switchHomeTab(event, 'info')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#003580; border-bottom:3px solid #ff9800; white-space:nowrap;">Overview</div>
                <div class="home-tab" onclick="switchHomeTab(event, 'itinerary')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Itinerary</div>
                <div class="home-tab" onclick="switchHomeTab(event, 'inclusions')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Inclusions</div>
                ${destination.partner_id ? `<div class="home-tab" onclick="switchHomeTab(event, 'partner')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Partner Profile</div>` : ''}
            </div>

            <div id="home-pane-info" class="home-pane" style="display:block;">
                <div class="package-details-grid" style="margin-bottom:15px;">
                    <div class="detail-item"><i class="fas fa-clock"></i><div class="detail-label">TRAVEL VALIDITY</div><div class="detail-value">${escapeHtml(formatValidityDate(destination.promo_start, destination.promo_end, destination.bestSeason))}</div></div>
                    <div class="detail-item"><i class="fas fa-users"></i><div class="detail-label">GROUP SIZE</div><div class="detail-value">${escapeHtml(destination.groupSize)}</div></div>
                    <div class="detail-item"><i class="fas fa-calendar-alt"></i><div class="detail-label">DURATION</div><div class="detail-value">${escapeHtml(destination.duration)}</div></div>
                </div>
            </div>

            <div id="home-pane-itinerary" class="home-pane" style="display:none;">
                ${itineraryHtml}
                ${remarksHtml}
            </div>
            
            <div id="home-pane-inclusions" class="home-pane" style="display:none;">
                ${inclusionsHtml}
                ${exclusionsHtml}
            </div>
            ${destination.partner_id ? `
            <div id="home-pane-partner" class="home-pane" style="display:none;">
                <div style="text-align: center; padding: 30px 15px;">
                    <i class="fas fa-handshake" style="font-size: 3rem; color: #ff9800; margin-bottom: 15px;"></i>
                    <h3 style="color: #003580; margin-bottom: 15px; font-size: 1.4rem;">${escapeHtml(destination.partner_company || 'Partner Provider')}</h3>
                    <p style="color: #666; margin-bottom: 25px; line-height: 1.6;">This package is exclusively provided by one of our trusted partners. View their full profile to learn more about them and discover other amazing packages they offer.</p>
                    <a href="view-partner-profile.php?id=${destination.partner_id}" style="display: inline-block; background: #003580; color: white; padding: 12px 25px; border-radius: 25px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                        View Partner Profile <i class="fas fa-external-link-alt" style="margin-left: 8px;"></i>
                    </a>
                </div>
            </div>` : ''}

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                <div>
                    <span style="font-size:0.8rem; color:#666;">Price starting from</span><br>
                    <span style="font-size:1.4rem; font-weight:800; color:#ff9800;">${destination.currency || '₱'}${formatNumber(destination.price)}</span>
                </div>
                <button onclick="document.getElementById('homeDetailsView').style.display='none'; document.getElementById('homeBookingView').style.display='block';" style="background:linear-gradient(135deg, #ff9800, #f57c00); color:white; border:none; padding:10px 25px; border-radius:30px; font-weight:bold; cursor:pointer;">
                    Book This Deal <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <div id="homeBookingView" style="display:none;">
            <div class="package-price-card">
                <div class="price">${destination.currency || '₱'}${formatNumber(destination.price)}</div>
                <small>/ person</small>
                <div style="margin-top: 8px;">${escapeHtml(destination.duration)} tour package</div>
            </div>
        
        <!-- Booking Steps -->
        <div class="booking-steps">
            <div class="step active" id="homeStep1">
                <div class="step-number">1</div>
                <div class="step-label">Date</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="homeStep2">
                <div class="step-number">2</div>
                <div class="step-label">Info</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="homeStep3">
                <div class="step-number">3</div>
                <div class="step-label">Review</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="homeStep4">
                <div class="step-number">4</div>
                <div class="step-label">Payment</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="homeStep5">
                <div class="step-number">5</div>
                <div class="step-label">Confirm</div>
            </div>
        </div>
        
        <!-- Step 1: Travel Date -->
        <div id="homeStep1Content" class="step-content active">
            <div class="booking-form-modal">
                <h3><i class="fas fa-calendar-alt"></i> Select Travel Date</h3>
                <p style="color: #666; margin-bottom: 20px;">Please pick your preferred travel date to get started.</p>
                
                <div class="form-group">
                    <label>Travel Date *</label>
                    <input type="text" id="homeTravelDate" placeholder="Select your travel date" readonly style="cursor:pointer; background:#fff;">
                </div>
                <div id="homeDateRangeInfo" style="display:none; margin-top:8px; padding:10px 14px; background:linear-gradient(135deg,#e3f2fd,#e8f5e9); border-left:4px solid #2196F3; border-radius:6px; font-size:0.9em; color:#0d47a1; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <i class="fas fa-info-circle"></i> <span id="homeDateRangeText"></span>
                            <div id="homePromoEndingWarning" style="display:none; margin-top:5px; color:#b71c1c; font-weight:bold; font-size:0.85em;">
                                <i class="fas fa-exclamation-triangle"></i> <span id="homePromoEndingWarningText"></span>
                            </div>
                            <div id="homeBlockedDateConflict" style="display:none; margin-top:5px; color:#b71c1c; font-weight:bold; font-size:0.85em;">
                                <i class="fas fa-ban"></i> <span id="homeBlockedDateConflictText"></span>
                            </div>
                        </div>
                        <button type="button" id="homeClearDateBtn" onclick="clearHomeDate()" style="background: rgba(0,0,0,0.05); border: none; border-radius: 4px; padding: 2px 6px; cursor: pointer; color: #0d47a1; font-size: 0.8rem; font-weight: 600; margin-left: 10px; white-space: nowrap;" title="Clear Selection">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>

                ${destination.hotels && destination.hotels.length > 0 ? `
                <div class="form-group" style="margin-top:20px;">
                    <label>Hotel <span style="font-weight:400; color:#94a3b8;">(optional)</span></label>
                    <div class="hotel-selection-item" onclick="toggleHomeHotelSelection()" style="cursor:pointer; border:1px solid #ddd; border-radius:8px; padding:12px 15px; display:flex; align-items:center; justify-content:space-between;">
                        <span><i class="fas fa-hotel" style="color:#ff9800; margin-right:8px;"></i><span id="homeSelectedHotelName" style="font-weight:600;">No hotel selected</span></span>
                        <i class="fas fa-chevron-down" style="font-size:0.8rem; color:#666;"></i>
                    </div>
                    <div id="homeHotelDropdown" class="hotel-dropdown" style="display:none; margin-top:8px; background: white; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <div class="hotel-option active" data-hotel-index="-1" onclick="selectHomeHotel(-1, 'No hotel selected', 0, 0)" style="padding: 12px 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s; background:#e3f2fd;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-weight:500; color:#64748b;">No hotel <span style="font-size:0.8rem;">(I'll arrange my own)</span></span>
                            </div>
                        </div>
                        ${destination.hotels.map((h, i) => `
                            <div class="hotel-option" data-hotel-index="${i}" onclick="selectHomeHotel(${i}, '${escapeHtml(h.name).replace(/'/g, "\\'")}', ${h.stars || 0}, ${h.price})" style="padding: 12px 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-weight:500;">${escapeHtml(h.name)} <span style="font-size:0.8rem;">${h.stars ? '⭐'.repeat(h.stars) : ''}</span></span>
                                    <span style="color:#4caf50; font-size:0.9rem;">${h.price > 0 ? `+${destination.currency || '₱'}${formatNumber(h.price)}` : 'Included'}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}

                <div id="homeStep1Errors" class="error-message" style="display: none;"></div>
                
                <div class="action-buttons">
                    <button type="button" class="btn-next" onclick="validateHomeStep1(${destination.id}, ${destination.price}, '${escapeHtml(destination.name).replace(/'/g, "\\'")}', '${escapeHtml(destination.duration)}')">Next: Passenger Info <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Step 2: Passenger Information -->
        <div id="homeStep2Content" class="step-content">
            <div class="booking-form-modal">
                <h3><i class="fas fa-user"></i> Passenger Information</h3>
                <div class="booking-summary" style="margin-bottom: 20px; background: #f9f9f9;">
                    <div class="summary-item"><span>Price per Person:</span><span>${destination.currency || '₱'}${formatNumber(destination.price)}</span></div>
                    <div class="summary-item"><span>Travelers:</span><span id="homeSummaryTravelers">1</span></div>
                    <div class="summary-item discounted" id="homeSummaryDiscountedRow" style="display:none;"><span>Discounted:</span><span id="homeSummaryDiscounted">₱0</span></div>
                    <div class="summary-item total"><span>Total:</span><span id="homeSummaryTotal">${destination.currency || '₱'}${formatNumber(destination.price)}</span></div>
                </div>

                <div class="form-row">
                    <div class="form-group"><label>Full Name *</label><input type="text" id="homeFullName" autocomplete="name" placeholder="Enter your full name" value="${window.currentFullName || ''}"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Phone *</label><input type="tel" id="homePhone" autocomplete="tel" placeholder="+63 912 345 6789"></div>
                    <div class="form-group"><label>Travelers *</label><input type="number" id="homeTravelersCount" min="1" value="1" onchange="updateHomeTotalPrice(${destination.price})"></div>
                </div>
                <!-- Voucher Section (Step 2) -->
                <div id="homeStep2VoucherArea" style="margin-bottom:18px;"></div>
                <div class="form-group"><label>Special Requests</label><textarea id="homeSpecialRequests" rows="2" placeholder="Any special requirements, dietary restrictions, etc."></textarea></div>
                
                <div id="homeStep2Errors" class="error-message" style="display: none;"></div>
                
                <div class="action-buttons">
                    <button type="button" class="btn-prev" onclick="goToHomeStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="button" class="btn-next" onclick="validateHomeStep2(${destination.id}, ${destination.price}, '${escapeHtml(destination.name).replace(/'/g, "\\'")}', '${escapeHtml(destination.duration)}')">Review Booking <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Step 3: Review -->
        <div id="homeStep3Content" class="step-content">
            <div class="review-details">
                <div class="review-section"><h4>Passenger Information</h4>
                    <div class="review-row"><div class="review-label">Full Name:</div><div class="review-value" id="homeReviewName">-</div></div>
                    <div class="review-row"><div class="review-label">Email:</div><div class="review-value" id="homeReviewEmail">-</div></div>
                    <div class="review-row"><div class="review-label">Phone:</div><div class="review-value" id="homeReviewPhone">-</div></div>
                </div>
                <div class="review-section"><h4>Travel Details</h4>
                    <div class="review-row"><div class="review-label">Destination:</div><div class="review-value">${escapeHtml(destination.name)}</div></div>
                    <div class="review-row"><div class="review-label">Duration:</div><div class="review-value">${escapeHtml(destination.duration)}</div></div>
                    <div class="review-row"><div class="review-label">Travel Date:</div><div class="review-value" id="homeReviewDate">-</div></div>
                    <div class="review-row"><div class="review-label">Travelers:</div><div class="review-value" id="homeReviewTravelers">-</div></div>
                    <div class="review-row"><div class="review-label">Special Requests:</div><div class="review-value" id="homeReviewRequests">-</div></div>
                </div>
                <div class="review-section"><h4>Price Summary</h4>
                    <div class="review-row"><div class="review-label">Price per Person:</div><div class="review-value">${destination.currency || '₱'}${formatNumber(destination.price)}</div></div>
                    <div class="review-row total"><div class="review-label">Total:</div><div class="review-value" id="homeReviewTotal">${destination.currency || '₱'}${formatNumber(destination.price)}</div></div>
                </div>
            </div>
            <div class="action-buttons">
                <button type="button" class="btn-prev" onclick="goToHomeStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="button" class="btn-next" onclick="goToHomeStep(4)">Proceed to Payment <i class="fas fa-credit-card"></i></button>
            </div>
        </div>
        
        <!-- Step 4: Payment Methods - IMPROVED DESIGN -->
        <div id="homeStep4Content" class="step-content">
            <div class="booking-form-modal">
                <h3><i class="fas fa-credit-card"></i> Select Payment Method</h3>
                <div class="payment-methods">
                    <div class="payment-method" onclick="selectHomePaymentMethod('gcash', event)">
                        <input type="radio" name="home_payment" value="gcash" id="homeGcashRadio">
                        <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">GCash</div>
                            <div class="payment-desc">Scan QR code to pay</div>
                        </div>
                    </div>
                    <div class="payment-method" onclick="selectHomePaymentMethod('paymaya', event)">
                        <input type="radio" name="home_payment" value="paymaya" id="homePaymayaRadio">
                        <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">PayMaya</div>
                            <div class="payment-desc">Scan QR code to pay</div>
                        </div>
                    </div>
                    <div class="payment-method disabled" onclick="alert('Credit/Debit Card payment is coming soon! Please use other payment methods for now.')" style="opacity: 0.6; cursor: not-allowed; position: relative;">
                        <input type="radio" name="home_payment" value="card" id="homeCardRadio" disabled>
                        <div class="payment-icon"><i class="fas fa-credit-card"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">Credit / Debit Card <span style="color: #ef4444; font-size: 0.65rem; font-weight: 800; margin-left: 5px;">(NOT AVAILABLE)</span></div>
                            <div class="payment-desc">Coming Soon</div>
                        </div>
                    </div>
                    <div class="payment-method disabled" onclick="alert('Bank Transfer is currently unavailable. Please use GCash or PayMaya for now.')" style="opacity: 0.6; cursor: not-allowed; position: relative;">
                        <input type="radio" name="home_payment" value="bank" id="homeBankRadio" disabled>
                        <div class="payment-icon"><i class="fas fa-university"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">Bank Transfer <span style="color: #ef4444; font-size: 0.65rem; font-weight: 800; margin-left: 5px;">(NOT AVAILABLE)</span></div>
                            <div class="payment-desc">Coming Soon</div>
                        </div>
                    </div>
                </div>
                
                <div id="homeGcashDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>GCash Payment</h4></div>
                        <div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>GCash QR Code</p><p>0945 776 4140</p></div>
                        <div class="account-details">
                            <p><strong>GCash Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboardHome('0945 776 4140')">Copy</button></p>
                            <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                            <p><strong>Amount:</strong> <span style="color:#ff9800;">${destination.currency || '₱'}<span id="homeGcashAmount">${formatNumber(destination.price)}</span></span></p>
                        </div>
                        <div class="form-group"><label>Reference Number *</label><input type="text" id="homePaymentRefGcash" placeholder="Enter GCash reference number"></div>
                        <div class="file-upload" onclick="document.getElementById('homeProofGcash').click()" style="margin-top:15px; padding:15px; border:2px dashed #ff9800; border-radius:12px; text-align:center; cursor:pointer; background:#fffaf2;">
                            <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem; color:#ff9800;"></i>
                            <p style="font-size:0.8rem; margin:5px 0;">Upload payment receipt</p>
                            <p class="file-name" id="home-file-name-gcash" style="font-size:0.7rem; color:#666;">No file selected</p>
                            <div id="home-preview-gcash" class="upload-preview" style="margin-top:10px;"></div>
                            <input type="file" id="homeProofGcash" accept="image/*" style="display:none" onchange="handleHomeFileUpload(event, 'gcash')">
                        </div>
                        <div class="instruction-note"><i class="fas fa-info-circle"></i> Enter the reference number and upload your receipt</div>
                    </div>
                </div>
                
                <div id="homePaymayaDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>PayMaya Payment</h4></div>
                        <div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>PayMaya QR Code</p><p>0945 776 4140</p></div>
                        <div class="account-details">
                            <p><strong>PayMaya Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboardHome('0945 776 4140')">Copy</button></p>
                            <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                            <p><strong>Amount:</strong> <span style="color:#ff9800;">${destination.currency || '₱'}<span id="homePaymayaAmount">${formatNumber(destination.price)}</span></span></p>
                        </div>
                        <div class="form-group"><label>Reference Number *</label><input type="text" id="homePaymentRefPaymaya" placeholder="Enter PayMaya reference number"></div>
                        <div class="file-upload" onclick="document.getElementById('homeProofPaymaya').click()" style="margin-top:15px; padding:15px; border:2px dashed #ff9800; border-radius:12px; text-align:center; cursor:pointer; background:#fffaf2;">
                            <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem; color:#ff9800;"></i>
                            <p style="font-size:0.8rem; margin:5px 0;">Upload payment receipt</p>
                            <p class="file-name" id="home-file-name-paymaya" style="font-size:0.7rem; color:#666;">No file selected</p>
                            <div id="home-preview-paymaya" class="upload-preview" style="margin-top:10px;"></div>
                            <input type="file" id="homeProofPaymaya" accept="image/*" style="display:none" onchange="handleHomeFileUpload(event, 'paymaya')">
                        </div>
                        <div class="instruction-note"><i class="fas fa-info-circle"></i> Enter the reference number and upload your receipt</div>
                    </div>
                </div>
                
                <div id="homeCardDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-credit-card"></i><h4>Card Payment</h4></div>
                        <div class="form-group"><label>Card Number *</label><input type="text" id="homeCardNumber" placeholder="1234 5678 9012 3456"></div>
                        <div class="card-row">
                            <div class="form-group"><label>Expiry *</label><input type="text" id="homeExpiryDate" placeholder="MM/YY"></div>
                            <div class="form-group"><label>CVV *</label><input type="text" id="homeCvv" placeholder="123"></div>
                        </div>
                        <div class="form-group"><label>Cardholder Name *</label><input type="text" id="homeCardName" placeholder="Name on card"></div>
                    </div>
                </div>
                
                <div id="homeBankDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-university"></i><h4>Bank Transfer</h4></div>
                        <div class="account-details">
                            <p><strong>BPI:</strong> 1234 5678 90 <button class="copy-btn" onclick="copyToClipboardHome('1234 5678 90')">Copy</button></p>
                            <p><strong>BDO:</strong> 5678 1234 56 <button class="copy-btn" onclick="copyToClipboardHome('5678 1234 56')">Copy</button></p>
                            <p><strong>Metrobank:</strong> 9012 3456 78 <button class="copy-btn" onclick="copyToClipboardHome('9012 3456 78')">Copy</button></p>
                            <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                            <p><strong>Amount:</strong> <span style="color:#ff9800;">${destination.currency || '₱'}<span id="homeBankAmount">${formatNumber(destination.price)}</span></span></p>
                        </div>
                        <div class="form-group"><label>Reference Number *</label><input type="text" id="homeBankRef" placeholder="Enter bank reference number"></div>
                        <div class="file-upload" onclick="document.getElementById('homeProofBank').click()" style="margin-top:15px; padding:15px; border:2px dashed #ff9800; border-radius:12px; text-align:center; cursor:pointer; background:#fffaf2;">
                            <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem; color:#ff9800;"></i>
                            <p style="font-size:0.8rem; margin:5px 0;">Upload payment receipt</p>
                            <p class="file-name" id="home-file-name-bank" style="font-size:0.7rem; color:#666;">No file selected</p>
                            <div id="home-preview-bank" class="upload-preview" style="margin-top:10px;"></div>
                            <input type="file" id="homeProofBank" accept="image/*" style="display:none" onchange="handleHomeFileUpload(event, 'bank')">
                        </div>
                        <div class="instruction-note"><i class="fas fa-info-circle"></i> Enter the reference number and upload your receipt</div>
                    </div>
                </div>
                
                <div id="homeStep4Errors" class="error-message" style="display: none;"></div>
                <div class="action-buttons">
                    <button type="button" class="btn-prev" onclick="goToHomeStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="button" class="btn-next" onclick="validateHomePayment()">Complete Payment <i class="fas fa-check-circle"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Step 5: Confirmation -->
        <div id="homeStep5Content" class="step-content">
            <div class="success-message">
                <i class="fas fa-clock"></i>
                <h2>⏳ Booking Received!</h2>
                <p>Your booking has been submitted and is pending payment verification.</p>
                <div class="booking-number" id="homeBookingNumber">Booking: Processing...</div>
                <div class="details-card">
                    <h4>📋 Booking Details:</h4>
                    <p><strong>Destination:</strong> ${escapeHtml(destination.name)}</p>
                    <p><strong>Duration:</strong> ${escapeHtml(destination.duration)}</p>
                    <p><strong>Travel Date:</strong> <span id="homeConfirmDate">-</span></p>
                    <p><strong>Travelers:</strong> <span id="homeConfirmTravelers">-</span></p>
                    <p><strong>Total Amount:</strong> <span style="color:#ff9800;" id="homeConfirmTotal">${destination.currency || '₱'}${formatNumber(destination.price)}</span></p>
                    <p><strong>Payment Method:</strong> <span id="homeConfirmPayment">-</span></p>
                    <p><strong>Payment Status:</strong> <span style="color:#ff9800;">Pending Verification</span></p>
                    <p><strong>Booked By:</strong> <span id="homeConfirmName">-</span></p>
                </div>
                <div class="payment-status-pending"><i class="fas fa-info-circle"></i> Your payment is pending verification. Our team will contact you shortly.</div>
                <div class="action-buttons">
                    <button class="book-now-btn" onclick="closeHomeDestinationModal()" style="background: #ff9800; width: auto;">Close</button>
                </div>
            </div>
                </div>
            </div>
        </div>
    </div>`;

    modalBody.innerHTML = html;
    modal.classList.add('active');

    // ── Flatpickr calendar for travel date ──────────────────────────────────
    const blockedDates = (destination.blocked_months_specific || destination.blocked_dates || '')
        .split(',')
        .map(d => d.trim())
        .filter(Boolean);

    // Parse blocked months (CSV string "1,2,3" -> array of numbers [0,1,2] for JS)
    const blockedMonths = (destination.blocked_months || '')
        .split(',')
        .map(m => parseInt(m.trim()) - 1)
        .filter(m => !isNaN(m));

    // highlight_duration defaults to 1 in the DB for almost every row, so
    // relying on it alone always highlighted a single day on the calendar no
    // matter what the duration text said. Only trust highlight_duration as a
    // deliberate override once it's > 1; otherwise derive the trip length
    // from the human-readable duration text (e.g. "3D/2N" -> 3), which is
    // what admins actually edit.
    const parsedDuration = parseInt(destination.duration) || 1;
    const highlightDuration = parseInt(destination.highlight_duration) > 1 ? parseInt(destination.highlight_duration) : parsedDuration;

    flatpickr('#homeTravelDate', {
        minDate: destination.promo_start && new Date(destination.promo_start) > new Date() ? destination.promo_start : 'today',
        // A promo_end in the past (an expired promo an admin never updated) must
        // not be used as maxDate -- that would put maxDate before minDate and
        // flatpickr disables every date on the calendar with no visible error.
        maxDate: destination.promo_end && new Date(destination.promo_end) > new Date() ? destination.promo_end : null,
        disableMobile: true,
        disable: [
            ...blockedDates,
            function (date) {
                // Disable based on blocked months
                return blockedMonths.includes(date.getMonth());
            }
        ],
        onDayCreate: function (dObj, dStr, fp, dayElem) {
            const date = dayElem.dateObj;
            const selectedDate = fp.selectedDates[0];

            if (selectedDate) {
                const startTime = new Date(selectedDate).setHours(0, 0, 0, 0);
                const endTime = new Date(selectedDate);
                endTime.setDate(endTime.getDate() + highlightDuration - 1);
                const endTimeTime = endTime.setHours(0, 0, 0, 0);
                const currentTime = date.setHours(0, 0, 0, 0);

                if (currentTime >= startTime && currentTime <= endTimeTime) {
                    dayElem.classList.add('promo-range-red');
                } else {
                    dayElem.classList.add('promo-pale');
                }
            }
        },
        onChange: function (selectedDates, dateStr, instance) {
            if (!selectedDates.length) return;

            const start = selectedDates[0];
            const end = new Date(start);
            end.setDate(end.getDate() + highlightDuration - 1);

            // Redraw to apply classes
            instance.redraw();

            // Show range info banner
            const rangeInfo = document.getElementById('homeDateRangeInfo');
            const rangeText = document.getElementById('homeDateRangeText');
            const warningBox = document.getElementById('homePromoEndingWarning');
            const warningText = document.getElementById('homePromoEndingWarningText');

            if (rangeInfo && rangeText) {
                const opts = { month: 'short', day: 'numeric', year: 'numeric' };
                const durText = highlightDuration > 1 ? `${highlightDuration} Days` : '1 Day';
                rangeText.textContent = `Your trip: ${start.toLocaleDateString(undefined, opts)} → ${end.toLocaleDateString(undefined, opts)} (${durText})`;
                rangeInfo.style.display = 'block';

                // CHECK PROMO END VALIDATION
                let promoExpired = false;
                if (destination.promo_end) {
                    const promoEnd = new Date(destination.promo_end);
                    promoEnd.setHours(23, 59, 59, 999);

                    if (end > promoEnd) {
                        promoExpired = true;
                        warningText.textContent = `Trip Unavailable: Your ${durText} trip extends beyond the promo period (ends ${promoEnd.toLocaleDateString(undefined, opts)}).`;
                        warningBox.style.display = 'block';
                    } else {
                        warningBox.style.display = 'none';
                    }
                }

                // CHECK BLOCKED DATE CONFLICTS (Range Check)
                const conflictBox = document.getElementById('homeBlockedDateConflict');
                const conflictText = document.getElementById('homeBlockedDateConflictText');
                const blockedDatesArr = (destination.blocked_dates || '').split(',').map(d => d.trim()).filter(Boolean);

                let foundConflict = null;
                const checkDate = new Date(start);

                for (let i = 0; i < highlightDuration; i++) {
                    const yyyy = checkDate.getFullYear();
                    const mm = String(checkDate.getMonth() + 1).padStart(2, '0');
                    const dd = String(checkDate.getDate()).padStart(2, '0');
                    const dateStr = `${yyyy}-${mm}-${dd}`;
                    const month = checkDate.getMonth();

                    if (blockedDatesArr.includes(dateStr)) {
                        foundConflict = `${checkDate.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} is a blocked date.`;
                        break;
                    }
                    if (blockedMonths.includes(month)) {
                        foundConflict = `${checkDate.toLocaleDateString(undefined, { month: 'long' })} is a blocked month.`;
                        break;
                    }
                    checkDate.setDate(checkDate.getDate() + 1);
                }

                const clearBtn = document.getElementById('homeClearDateBtn');

                if (foundConflict || promoExpired) {
                    window.homeDateConflict = true;
                    if (foundConflict) {
                        conflictText.textContent = `Trip Unavailable: ${foundConflict} Please choose another start date.`;
                        conflictBox.style.display = 'block';
                    } else {
                        conflictBox.style.display = 'none';
                    }

                    // Set Red Style (Error)
                    rangeInfo.style.background = 'linear-gradient(135deg,#ffebee,#ffcdd2)';
                    rangeInfo.style.borderLeft = '4px solid #f44336';
                    rangeInfo.style.color = '#b71c1c';
                    if (clearBtn) clearBtn.style.color = '#b71c1c';
                } else {
                    window.homeDateConflict = false;
                    conflictBox.style.display = 'none';

                    // Set Blue Style (Normal)
                    rangeInfo.style.background = 'linear-gradient(135deg,#e3f2fd,#e8f5e9)';
                    rangeInfo.style.borderLeft = '4px solid #2196F3';
                    rangeInfo.style.color = '#0d47a1';
                    if (clearBtn) clearBtn.style.color = '#0d47a1';
                }
            }
        }
    });

    // --- PHOTO SLIDER INITIALIZATION ---
    const slides = document.querySelectorAll('.flash-slide');
    if (slides.length > 1) {
        const dots = document.querySelectorAll('.flash-slide-dot');
        let current = 0;

        function showSlide(index) {
            slides.forEach((s, i) => {
                if (i === index) s.classList.add('active');
                else s.classList.remove('active');
            });
            dots.forEach((d, i) => {
                if (i === index) d.classList.add('active');
                else d.classList.remove('active');
            });
            current = index;
        }

        if (window.homeSliderInterval) clearInterval(window.homeSliderInterval);
        window.homeSliderInterval = setInterval(() => {
            showSlide((current + 1) % slides.length);
        }, 3500);

        dots.forEach(dot => {
            dot.onclick = (e) => {
                clearInterval(window.homeSliderInterval);
                showSlide(parseInt(e.target.dataset.index));
                window.homeSliderInterval = setInterval(() => {
                    showSlide((current + 1) % slides.length);
                }, 3500);
            };
        });
    }

    window.homeDateConflict = false; // Reset state

    window.clearHomeDate = function () {
        const fp = document.querySelector('#homeTravelDate')._flatpickr;
        if (fp) {
            fp.clear();
            fp.redraw();
        }
        const rangeInfo = document.getElementById('homeDateRangeInfo');
        if (rangeInfo) rangeInfo.style.display = 'none';
        document.getElementById('homeTravelDate').value = '';
    };
    // ──────────────────────────────────────────────────────────────────────────

    // Initialize total update
    document.getElementById('homeTravelersCount').addEventListener('change', function () {
        updateHomeTotalPrice(destination.price);
    });
}

// Helper to ensure modal exists
function ensureLocalModalExists() {
    let modal = document.getElementById('homeDestinationModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'homeDestinationModal';
        modal.className = 'home-package-modal';
        modal.innerHTML = `
            <div class="home-package-modal-content">
                <div class="home-package-modal-header" style="background: #003580; padding: 20px; border-radius: 24px 24px 0 0;">
                    <span class="close-modal" onclick="closeHomeDestinationModal()" style="color: white;">&times;</span>
                    <h2 id="homeModalDestName" style="color: white; margin: 0;">Loading...</h2>
                    <p id="homeModalDestLocation" style="color: rgba(255,255,255,0.8); margin: 5px 0 0;"><i class="fas fa-map-marker-alt"></i> Loading...</p>
                </div>
                <div class="home-package-modal-body" id="homeDestinationModalBody">
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
                        <p>Loading tour details...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeHomeDestinationModal();
            }
        });
    }
}

// Payment method variables
let homeSelectedPayment = null;
let homeBookingData = null;

function copyToClipboardHome(text) {
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = 'Copied!';
        btn.style.background = '#28a745';
        btn.style.color = 'white';
        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.background = '#e0e0e0';
            btn.style.color = '';
        }, 1500);
    });
}

function updateHomeTotalPrice(price) {
    const travelersCount = document.getElementById('homeTravelersCount');
    if (!travelersCount) return;

    const travelers = parseInt(travelersCount.value) || 1;
    const hotelSurcharge = window.homeSelectedHotelSurcharge || 0;
    const total = (travelers * price) + hotelSurcharge;
    const currency = window.currentHomeDestCurrency || '₱';

    document.getElementById('homeSummaryTravelers').textContent = travelers;
    document.getElementById('homeSummaryTotal').textContent = currency + formatNumber(total);
    if (document.getElementById('homeGcashAmount')) document.getElementById('homeGcashAmount').textContent = formatNumber(total);
    if (document.getElementById('homePaymayaAmount')) document.getElementById('homePaymayaAmount').textContent = formatNumber(total);
    if (document.getElementById('homeBankAmount')) document.getElementById('homeBankAmount').textContent = formatNumber(total);

    if (typeof updateVoucherTotalInline === 'function') {
        updateVoucherTotalInline('home', total);
    }
}

function goToHomeStep(step) {
    for (let i = 1; i <= 5; i++) {
        const stepDiv = document.getElementById(`homeStep${i}`);
        const contentDiv = document.getElementById(`homeStep${i}Content`);
        if (stepDiv) {
            if (i < step) {
                stepDiv.classList.add('completed');
                stepDiv.classList.remove('active');
            } else if (i === step) {
                stepDiv.classList.add('active');
                stepDiv.classList.remove('completed');
            } else {
                stepDiv.classList.remove('active', 'completed');
            }
        }
        if (contentDiv) {
            if (i === step) {
                contentDiv.classList.add('active');
            } else {
                contentDiv.classList.remove('active');
            }
        }
    }
    // Init voucher widget on Step 2 (Passenger Info)
    if (step === 2 && typeof initVoucherCheckoutInline === 'function') {
        const price = window.currentHomeDest ? window.currentHomeDest.price : 0;
        const travelers = parseInt(document.getElementById('homeTravelersCount')?.value) || 1;
        const hotelSurcharge = window.homeSelectedHotelSurcharge || 0;
        const total = (price * travelers) + hotelSurcharge;
        initVoucherCheckoutInline(
            'home',
            total,
            'local_destinations',
            'homeStep2VoucherArea',
            'homeSummaryTotal',
            () => window.currentHomeDestCurrency || '₱',
            window.currentHomeDest ? window.currentHomeDest.id : 0
        );
    }
    if (step === 4 && homeBookingData) {
        const total = homeBookingData.totalAmount;
        if (document.getElementById('homeGcashAmount')) document.getElementById('homeGcashAmount').textContent = formatNumber(total);
        if (document.getElementById('homePaymayaAmount')) document.getElementById('homePaymayaAmount').textContent = formatNumber(total);
        if (document.getElementById('homeBankAmount')) document.getElementById('homeBankAmount').textContent = formatNumber(total);
    }
}

function validateHomeStep1(destinationId, price, destinationName, duration) {
    const errors = [];
    const travelDate = document.getElementById('homeTravelDate').value;

    if (!travelDate) errors.push('Travel Date is required');
    if (window.homeDateConflict) errors.push('Selected range includes blocked dates. Please pick another date.');

    if (errors.length > 0) {
        const errorDiv = document.getElementById('homeStep1Errors');
        errorDiv.style.display = 'flex';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    // Require login before proceeding to passenger info
    if (window.currentHomeDest) {
        requireLogin('resumeHomeBooking', window.currentHomeDest.key, 2);
    } else {
        requireLogin(() => {
            goToHomeStep(2);
        });
    }

    return true;
}

window.resumeHomeBooking = async function (destKey, step) {
    const modal = document.getElementById('homeDestinationModal');
    if (modal && modal.classList.contains('active')) {
        // Modal is already open. resumeHomeBooking is only ever called to enter
        // or resume the booking flow (never to show the details view), so always
        // switch to it -- package-details.php already shows the full details.
        const detailsView = document.getElementById('homeDetailsView');
        const bookingView = document.getElementById('homeBookingView');
        if (detailsView) detailsView.style.display = 'none';
        if (bookingView) bookingView.style.display = 'block';
        goToHomeStep(step);
    } else {
        // Modal not open (likely after login redirect, or arriving fresh from
        // package-details.php). showLocalPackagePopupModal does a real network
        // fetch + builds the modal (including the flatpickr date picker) --
        // await it fully instead of guessing a fixed delay, otherwise
        // goToHomeStep can run before window.currentHomeDest is set and
        // before flatpickr is attached, leaving a dead, unstyled date input
        // and a "Next" button that silently throws on click.
        if (typeof showLocalPackagePopupModal === 'function') {
            await showLocalPackagePopupModal(destKey);

            // Always switch straight to the booking view -- skip the
            // redundant details view since package-details.php already shows it.
            const detailsView = document.getElementById('homeDetailsView');
            const bookingView = document.getElementById('homeBookingView');
            if (detailsView) detailsView.style.display = 'none';
            if (bookingView) bookingView.style.display = 'block';

            // Pre-fill user info if available
            if (window.currentFullName) {
                const nameField = document.getElementById('homeFullName');
                if (nameField) nameField.value = window.currentFullName;
            }
            goToHomeStep(step);
        }
    }
};

function validateHomeStep2(destinationId, price, destinationName, duration) {
    const errors = [];
    const fullName = document.getElementById('homeFullName').value.trim();
    const phone = document.getElementById('homePhone').value.trim();
    const travelDate = document.getElementById('homeTravelDate').value;
    const travelers = document.getElementById('homeTravelersCount').value;

    if (!fullName) errors.push('Full Name is required');

    // Use auto-detected email
    const email = window.currentUserEmail || '';
    if (!email) errors.push('Your account email could not be detected. Please log in again.');
    if (!phone) errors.push('Phone number is required');
    if (!travelers || travelers < 1) errors.push('At least 1 traveler is required');

    if (errors.length > 0) {
        const errorDiv = document.getElementById('homeStep2Errors');
        errorDiv.style.display = 'flex';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    const appliedVoucher = window._appliedVoucher && window._appliedVoucher['home'];
    const rawTotal = (price * parseInt(travelers)) + (window.homeSelectedHotelSurcharge || 0);
    const finalAmount = appliedVoucher ? appliedVoucher.finalTotal : rawTotal;

    homeBookingData = {
        destinationId: destinationId,
        destinationName: destinationName,
        duration: duration,
        price: price,
        fullName: fullName,
        email: email,
        phone: phone,
        travelDate: travelDate,
        travelers: parseInt(travelers),
        specialRequests: document.getElementById('homeSpecialRequests').value.trim() || '',
        totalAmount: finalAmount
    };

    document.getElementById('homeReviewName').textContent = fullName;
    document.getElementById('homeReviewEmail').textContent = email;
    document.getElementById('homeReviewPhone').textContent = phone;
    document.getElementById('homeReviewDate').textContent = new Date(travelDate).toLocaleDateString();
    document.getElementById('homeReviewTravelers').textContent = travelers;
    document.getElementById('homeReviewRequests').textContent = homeBookingData.specialRequests || 'None';
    
    const reviewTotalEl = document.getElementById('homeReviewTotal');
    if (reviewTotalEl) {
        if (appliedVoucher) {
            reviewTotalEl.innerHTML = `${window.currentHomeDestCurrency || '₱'}${formatNumber(finalAmount)} <span style="text-decoration:line-through;color:#94a3b8;font-size:0.8em;">${window.currentHomeDestCurrency || '₱'}${formatNumber(rawTotal)}</span>`;
        } else {
            reviewTotalEl.textContent = (window.currentHomeDestCurrency || '₱') + formatNumber(rawTotal);
        }
    }

    goToHomeStep(3);
    return true;
}

// Select payment method - FIXED (added event parameter)
function selectHomePaymentMethod(method, event) {
    homeSelectedPayment = method;
    const targetElement = event.currentTarget;
    document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
    targetElement.classList.add('selected');
    document.querySelectorAll('input[name="home_payment"]').forEach(radio => radio.checked = false);
    const radio = document.getElementById(`home${method.charAt(0).toUpperCase() + method.slice(1)}Radio`);
    if (radio) radio.checked = true;

    // Hide all payment details boxes
    document.querySelectorAll('.payment-details-box').forEach(box => {
        box.classList.remove('show');
    });

    // Show selected payment details
    const detailsBox = document.getElementById(`home${method.charAt(0).toUpperCase() + method.slice(1)}Details`);
    if (detailsBox) detailsBox.classList.add('show');
}

function validateHomePayment() {
    const errors = [];
    const totalAmount = homeBookingData ? homeBookingData.totalAmount : 0;

    if (!homeSelectedPayment) errors.push('Please select a payment method');

    if (homeSelectedPayment === 'gcash') {
        const ref = document.getElementById('homePaymentRefGcash')?.value.trim();
        if (!ref) errors.push('Please enter the GCash reference number');
    }
    if (homeSelectedPayment === 'paymaya') {
        const ref = document.getElementById('homePaymentRefPaymaya')?.value.trim();
        if (!ref) errors.push('Please enter the PayMaya reference number');
    }
    if (homeSelectedPayment === 'card') {
        if (!document.getElementById('homeCardNumber')?.value.trim()) errors.push('Card Number is required');
        if (!document.getElementById('homeExpiryDate')?.value.trim()) errors.push('Expiry Date is required');
        if (!document.getElementById('homeCvv')?.value.trim()) errors.push('CVV is required');
        if (!document.getElementById('homeCardName')?.value.trim()) errors.push('Cardholder Name is required');
    }
    if (homeSelectedPayment === 'bank') {
        const ref = document.getElementById('homeBankRef')?.value.trim();
        if (!ref) errors.push('Reference Number is required');
    }

    if (errors.length > 0) {
        const errorDiv = document.getElementById('homeStep4Errors');
        errorDiv.style.display = 'flex';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    let paymentRef = '';
    if (homeSelectedPayment === 'gcash') paymentRef = document.getElementById('homePaymentRefGcash')?.value.trim();
    else if (homeSelectedPayment === 'paymaya') paymentRef = document.getElementById('homePaymentRefPaymaya')?.value.trim();
    else if (homeSelectedPayment === 'card') paymentRef = 'Card ending in ' + document.getElementById('homeCardNumber')?.value.slice(-4);
    else if (homeSelectedPayment === 'bank') paymentRef = document.getElementById('homeBankRef')?.value.trim();

    // Show processing state
    const btn = document.querySelector('#homeStep4Content .btn-next');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    let paymentMethodName = '';
    if (homeSelectedPayment === 'gcash') paymentMethodName = 'GCash';
    else if (homeSelectedPayment === 'paymaya') paymentMethodName = 'PayMaya';
    else if (homeSelectedPayment === 'card') paymentMethodName = 'Credit/Debit Card';
    else if (homeSelectedPayment === 'bank') paymentMethodName = 'Bank Transfer';

    // Send actual booking to server
    sendHomeBookingToServer(btn, originalText, paymentMethodName, paymentRef);
    return true;
}

// Global function to handle home file upload
window.handleHomeFileUpload = function (event, paymentMethod) {
    const file = event.target.files[0];
    if (file) {
        if (!file.type.match('image.*')) {
            alert('Please upload an image file (PNG, JPG, JPEG)');
            event.target.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5MB.');
            event.target.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function (e) {
            const previewDiv = document.getElementById(`home-preview-${paymentMethod}`);
            if (previewDiv) {
                previewDiv.innerHTML = `<img src="${e.target.result}" alt="Payment Proof" style="max-width:100%; max-height:100px; border-radius:8px;">`;
            }
        };
        reader.readAsDataURL(file);
        const fileNameSpan = document.getElementById(`home-file-name-${paymentMethod}`);
        if (fileNameSpan) {
            fileNameSpan.textContent = file.name;
        }
    }
};

function sendHomeBookingToServer(btn, originalText, paymentMethodName, paymentRef) {
    if (!homeBookingData) return;

    // Apply voucher discount if one was applied
    const appliedVoucher = window._appliedVoucher && window._appliedVoucher['home'];
    const finalAmount = appliedVoucher ? appliedVoucher.finalTotal : homeBookingData.totalAmount;

    const formData = new FormData();
    formData.append('service_type', 'Local Package');
    formData.append('package_name', homeBookingData.destinationName);
    formData.append('package_duration', homeBookingData.duration);
    formData.append('price_per_person', homeBookingData.price);
    formData.append('full_name', homeBookingData.fullName);
    formData.append('email', homeBookingData.email);
    formData.append('phone', homeBookingData.phone);
    formData.append('travel_date', homeBookingData.travelDate);
    formData.append('number_of_travelers', homeBookingData.travelers);
    formData.append('special_requests', homeBookingData.specialRequests);
    formData.append('total_amount', finalAmount);
    formData.append('currency', window.currentHomeDestCurrency || '₱');
    if (window.currentHomeDest?.partner_id) formData.append('partner_id', window.currentHomeDest.partner_id);
    if (window.currentHomeDest?.partner_company) formData.append('partner_company', window.currentHomeDest.partner_company);
    if (window.currentHomeDest?.partner_source) formData.append('partner_source', window.currentHomeDest.partner_source);
    if (window.currentHomeDest?.name) formData.append('partner_package_name', window.currentHomeDest.name);
    formData.append('payment_method', homeSelectedPayment);
    if (paymentRef) formData.append('payment_reference', paymentRef);
    if (appliedVoucher) {
        formData.append('voucher_id', appliedVoucher.id);
        formData.append('voucher_discount', appliedVoucher.discountAmount);
    }

    // Support file uploads for payment proof
    const fileInput = document.getElementById(`homeProof${homeSelectedPayment.charAt(0).toUpperCase() + homeSelectedPayment.slice(1)}`);
    if (fileInput && fileInput.files[0]) {
        formData.append('payment_proof', fileInput.files[0]);
    }

    fetch('api/save-service-booking.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if (data.success) {
                document.getElementById('homeBookingNumber').innerHTML = 'Booking: ' + data.booking_number;
                document.getElementById('homeConfirmDate').textContent = new Date(homeBookingData.travelDate).toLocaleDateString();
                document.getElementById('homeConfirmTravelers').textContent = homeBookingData.travelers;
                const currency = window.currentHomeDestCurrency || '₱';
                document.getElementById('homeConfirmTotal').innerHTML = currency + formatNumber(homeBookingData.totalAmount);
                document.getElementById('homeConfirmPayment').textContent = paymentMethodName;
                document.getElementById('homeConfirmName').textContent = homeBookingData.fullName;

                goToHomeStep(5);
            } else {
                const errorDiv = document.getElementById('homeStep4Errors');
                if (errorDiv) {
                    errorDiv.style.display = 'flex';
                    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${data.message}`;
                    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    alert('Error: ' + data.message);
                }
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            console.error('Error saving home booking:', error);
            alert('Connection Error. Please try again.');
        });
}

// Update total (legacy function for backward compatibility)
function updateHomeTotal(price) {
    updateHomeTotalPrice(price);
}

// Handle booking (legacy function for backward compatibility)
function handleHomeBooking(event, destinationId, price, destinationName, duration) {
    event.preventDefault();

    // Check login first
    fetch('api/check-auth.php')
        .then(response => response.json())
        .then(data => {
            if (!data.logged_in) {
                showLoginRequiredPopup();
                return;
            }

            // Continue with existing booking logic
            const form = event.target;
            // ... rest of your existing code ...
        });


    // Validation
    if (!fullName) {
        showHomeNotification('Please enter your full name', 'error');
        return;
    }
    if (!email) {
        showHomeNotification('Please enter your email address', 'error');
        return;
    }
    if (!email.match(/^[^\s@]+@([^\s@]+\.)+[^\s@]+$/)) {
        showHomeNotification('Please enter a valid email address', 'error');
        return;
    }
    if (!phone) {
        showHomeNotification('Please enter your phone number', 'error');
        return;
    }
    if (!travelDate) {
        showHomeNotification('Please select a travel date', 'error');
        return;
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    // Send booking to server
    fetch('api/save-local-booking.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            destination_id: destinationId,
            destination_name: destinationName,
            package_duration: duration,
            price_per_person: price,
            full_name: fullName,
            email: email,
            phone: phone,
            travel_date: travelDate,
            number_of_travelers: travelers,
            special_requests: specialRequests,
            total_amount: totalAmount,
            currency: window.currentHomeDestCurrency || '₱',
            partner_id: window.currentHomeDest?.partner_id ?? null,
            partner_company: window.currentHomeDest?.partner_company ?? null,
            partner_package_name: window.currentHomeDest?.name ?? null,
            partner_source: window.currentHomeDest?.partner_source ?? null
        })
    })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;

            if (data.success) {
                const bookingNumber = data.booking_number || 'LOC-' + Math.random().toString(36).substr(2, 8).toUpperCase();

                const modalBody = document.getElementById('homeDestinationModalBody');
                modalBody.innerHTML = `
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h2>🎉 Booking Confirmed!</h2>
                    <p>Your booking for <strong>${escapeHtml(destinationName)}</strong> has been submitted successfully.</p>
                    <div class="booking-number">Booking: ${bookingNumber}</div>
                    <div class="details-card">
                        <p><strong>📋 Booking Details:</strong></p>
                        <p><strong>📍 Destination:</strong> ${escapeHtml(destinationName)}</p>
                        <p><strong>📅 Duration:</strong> ${escapeHtml(duration)}</p>
                        <p><strong>📅 Travel Date:</strong> ${new Date(travelDate).toLocaleDateString()}</p>
                        <p><strong>👥 Travelers:</strong> ${travelers} person(s)</p>
                        <p><strong>💰 Total Amount:</strong> <span style="color: #ff9800; font-size: 1.1rem;">${window.currentHomeDestCurrency || '₱'}${formatNumber(totalAmount)}</span></p>
                        <p><strong>👤 Booked By:</strong> ${escapeHtml(fullName)}</p>
                        <p><strong>📧 Email:</strong> ${escapeHtml(email)}</p>
                        <p><strong>📞 Phone:</strong> ${escapeHtml(phone)}</p>
                        ${specialRequests ? `<p><strong>📝 Special Requests:</strong> ${escapeHtml(specialRequests)}</p>` : ''}
                    </div>
                    <p>A confirmation has been recorded in our system. Our team will contact you shortly.</p>
                    <button class="book-now-btn" onclick="closeHomeDestinationModal()" style="width: auto; margin-top: 20px;">Close</button>
                </div>`;

                document.dispatchEvent(new CustomEvent('bookingUpdated'));

            } else {
                showHomeNotification(data.message || 'Booking failed. Please try again.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            showHomeNotification('Network error. Please check your connection and try again.', 'error');
        });
}

// Close modal function
function closeHomeDestinationModal() {
    const modal = document.getElementById('homeDestinationModal');
    if (modal) modal.classList.remove('active');

    // Clear slider interval
    if (window.homeSliderInterval) {
        clearInterval(window.homeSliderInterval);
        window.homeSliderInterval = null;
    }

    homeSelectedPayment = null;
    homeBookingData = null;
}

// Helper function to show notifications
function showHomeNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `home-saved-notification ${type}`;
    notification.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.add('show');
    }, 10);

    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Add styles for home destination modal - MODERN DESIGN MATCHING FOREIGN-PACKAGES
function addHomePackageModalStyles() {
    if (!document.querySelector('#homeDestinationModalStyles')) {
        const style = document.createElement('style');
        style.id = 'homeDestinationModalStyles';
        style.textContent = `
            /* Home Destination Card Styles - Modern Grid Cards */
            #localDestinationsGrid {
                display: flex;
                gap: 20px;
                margin-bottom: 0;
                min-width: min-content;
            }
            
            @media (max-width: 768px) {
                #localDestinationsGrid {
                    gap: 15px;
                }
            }
            
            @media (max-width: 480px) {
                #localDestinationsGrid {
                    gap: 12px;
                }
            }
            
            .home-destination-card {
                background: white;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 5px 20px rgba(0,0,0,0.05);
                transition: all 0.3s ease;
                cursor: pointer;
                position: relative;
                animation: fadeInUp 0.4s ease;
                display: flex;
                flex-direction: column;
                height: 430px;
                min-width: 280px;
                width: 280px;
            }
            
            .home-destination-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 15px 35px rgba(0,53,128,0.15);
            }
            
            .home-card-image {
                position: relative;
                height: 210px;
                overflow: hidden;
                flex-shrink: 0;
            }
            
            .home-card-image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.4s ease;
            }
            
            .home-destination-card:hover .home-card-image img {
                transform: scale(1.05);
            }
            
            .home-card-badge {
                position: absolute;
                top: 10px;
                right: 10px;
                background: #ff9800;
                color: white;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 0.7rem;
                font-weight: 600;
                z-index: 2;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }
            
            .home-card-content {
                padding: 12px;
                flex: 1;
                display: flex;
                flex-direction: column;
            }
            
            .home-card-name {
                font-size: 1rem;
                font-weight: 700;
                color: #003580;
                margin-bottom: 2px;
                font-family: 'Poppins', sans-serif;
                display: -webkit-box;
                -webkit-line-clamp: 1;
                line-clamp: 1;
                -webkit-box-orient: vertical;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .home-card-location {
                color: #666;
                font-size: 0.7rem;
                margin-bottom: 6px;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            
            .home-card-location i {
                color: #ff9800;
                font-size: 0.65rem;
            }
            
            .home-card-desc {
                color: #555;
                font-size: 0.75rem;
                line-height: 1.4;
                margin-bottom: 8px;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            
            .home-card-price {
                font-size: 1rem;
                font-weight: 700;
                color: #ff9800;
                margin-bottom: 6px;
                margin-top: auto;
            }
            
            .home-card-price small {
                font-size: 0.65rem;
                color: #888;
                font-weight: normal;
            }
            
            .home-card-btn {
                background: #003580;
                color: white;
                border: none;
                padding: 9px 14px;
                border-radius: 25px;
                font-size: 0.75rem;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.3s ease;
                width: 100%;
                text-align: center;
                margin-top: 4px;
            }
            
            .home-card-btn:hover {
                background: #ff9800;
                transform: scale(1.02);
            }
            
            /* Scrollable container styles */
            .scrollable-container {
                overflow-x: auto;
                overflow-y: visible;
                margin-bottom: 20px;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                scrollbar-color: #ff9800 #e0e0e0;
            }
            
            .scrollable-container::-webkit-scrollbar {
                height: 8px;
            }
            
            .scrollable-container::-webkit-scrollbar-track {
                background: #e0e0e0;
                border-radius: 10px;
            }
            
            .scrollable-container::-webkit-scrollbar-thumb {
                background: #ff9800;
                border-radius: 10px;
            }
            
            .scrollable-container::-webkit-scrollbar-thumb:hover {
                background: #003580;
            }
            
            /* Home Package Modal Styles - Matches Foreign Modal */
            .home-package-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                z-index: 99999999 !important;
                align-items: center;
                justify-content: center;
                backdrop-filter: blur(5px);
                padding: 40px 0;
            }
            
            .home-package-modal.active {
                display: flex;
            }
            
            .home-package-modal-content {
                background: white;
                border-radius: 24px;
                max-width: 900px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                animation: modalSlideIn 0.3s ease;
            }
            
            .home-package-modal-content::-webkit-scrollbar {
                width: 8px;
            }
            
            .home-package-modal-content::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }
            
            .home-package-modal-content::-webkit-scrollbar-thumb {
                background: #003580;
                border-radius: 10px;
            }
            
            .home-package-modal-header {
                background: linear-gradient(135deg, #003580, #1a4b8c);
                color: white;
                padding: 20px 25px;
                border-radius: 24px 24px 0 0;
                position: relative;
            }
            
            .home-package-modal-header .close-modal {
                position: absolute;
                top: 15px;
                right: 20px;
                font-size: 1.8rem;
                cursor: pointer;
                transition: all 0.3s ease;
                color: white;
            }
            
            .home-package-modal-header .close-modal:hover {
                transform: rotate(90deg);
                color: #ff9800;
            }
            
            .home-package-modal-header h2 {
                font-size: 1.5rem;
                margin-bottom: 8px;
                font-family: 'Poppins', sans-serif;
                color: #ffffff;
                text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            }
            
            .home-package-modal-header p {
                opacity: 0.9;
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 0.85rem;
            }
            
            .home-package-modal-body {
                padding: 20px;
            }
            
            /* Shared styles with foreign modal */
            .package-details-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                margin-bottom: 20px;
                background: #f8f9fa;
                padding: 15px;
                border-radius: 16px;
            }
            
            .detail-item {
                text-align: center;
            }
            
            .detail-item i {
                font-size: 1.3rem;
                color: #ff9800;
                margin-bottom: 6px;
                display: block;
            }
            
            .detail-label {
                font-size: 0.7rem;
                color: #666;
                text-transform: uppercase;
            }
            
            .detail-value {
                font-size: 0.95rem;
                font-weight: 700;
                color: #003580;
            }
            
            .itinerary-section {
                margin-bottom: 20px;
            }
            
            .itinerary-section h3 {
                color: #003580;
                margin-bottom: 15px;
                font-size: 1.1rem;
            }
            
            .itinerary-day {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 12px;
                margin-bottom: 10px;
                border-left: 4px solid #ff9800;
            }
            
            .itinerary-day h4 {
                color: #003580;
                margin-bottom: 8px;
                font-size: 0.9rem;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .itinerary-day ul {
                list-style: none;
                padding-left: 0;
            }
            
            .itinerary-day li {
                padding: 4px 0;
                padding-left: 20px;
                position: relative;
                color: #555;
                font-size: 0.75rem;
            }
            
            .itinerary-day li:before {
                content: "✓";
                position: absolute;
                left: 0;
                color: #ff9800;
            }
            
            .inclusions-section {
                background: #e8f0fe;
                padding: 15px;
                border-radius: 16px;
                margin-bottom: 15px;
                border-left: 4px solid #003580;
            }
            
            .inclusions-section h3 {
                color: #003580;
                margin-bottom: 12px;
                font-size: 1rem;
            }
            
            .inclusions-list {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 6px;
                list-style: none;
            }
            
            .inclusions-list li {
                padding-left: 20px;
                position: relative;
                color: #555;
                font-size: 0.75rem;
            }
            
            .inclusions-list li:before {
                content: "✓";
                position: absolute;
                left: 0;
                color: #28a745;
            }
            
            .exclusions-section {
                background: #fff3e0;
                padding: 15px;
                border-radius: 16px;
                margin-bottom: 20px;
                border-left: 4px solid #ff9800;
            }
            
            .exclusions-section h3 {
                color: #ff9800;
                margin-bottom: 12px;
                font-size: 1rem;
            }
            
            .exclusions-list {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 6px;
                list-style: none;
                padding-left: 0;
            }
            
            .exclusions-list li {
                position: relative;
                padding-left: 20px;
                margin-bottom: 8px;
                color: #555;
                font-size: 0.75rem;
            }
            
            .exclusions-list li:before {
                content: "✗";
                position: absolute;
                left: 0;
                color: #dc3545;
            }
            
            .package-price-card {
                background: linear-gradient(135deg, #fff, #f8f9fa);
                border-radius: 16px;
                padding: 20px;
                text-align: center;
                margin-bottom: 20px;
                border: 2px solid #ff9800;
            }
            
            .package-price-card .price {
                font-size: 1.8rem;
                font-weight: 800;
                color: #ff9800;
            }
            
            .book-now-btn {
                background: #ff9800;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 50px;
                font-size: 0.85rem;
                font-weight: 600;
                cursor: pointer;
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: all 0.3s ease;
            }
            
            .book-now-btn:hover:not(:disabled) {
                background: #f57c00;
                transform: translateY(-2px);
            }
            
            .book-now-btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
            
            .booking-form-modal {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 2px solid #e9ecef;
            }
            
            .booking-form-modal h3 {
                color: #003580;
                margin-bottom: 15px;
                font-size: 1rem;
            }
            
            .booking-form-modal .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                margin-bottom: 12px;
            }
            
            .booking-form-modal input,
            .booking-form-modal textarea {
                width: 100%;
                padding: 8px 10px;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 0.8rem;
                font-family: inherit;
            }
            
            .booking-form-modal input:focus,
            .booking-form-modal textarea:focus {
                outline: none;
                border-color: #003580;
                box-shadow: 0 0 0 3px rgba(0,53,128,0.1);
            }
            
            .booking-form-modal label {
                display: block;
                font-weight: 600;
                margin-bottom: 4px;
                font-size: 0.75rem;
            }
            
            .booking-summary {
                background: #f8f9fa;
                padding: 12px;
                border-radius: 12px;
                margin-bottom: 15px;
            }
            
            .booking-summary h4 {
                color: #003580;
                margin-bottom: 12px;
                font-size: 0.95rem;
            }
            
            .summary-item {
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
                border-bottom: 1px dashed #e0e0e0;
                font-size: 0.75rem;
            }
            
            .summary-item.total {
                font-weight: bold;
                color: #ff9800;
                font-size: 0.85rem;
            }
            
            .success-message {
                text-align: center;
                padding: 30px;
            }
            
            .success-message i {
                font-size: 2.5rem;
                color: #28a745;
                margin-bottom: 12px;
            }
            
            .booking-number {
                background: #e8f0fe;
                padding: 6px 12px;
                border-radius: 8px;
                font-size: 0.8rem;
                margin: 10px 0;
                display: inline-block;
            }
            
            .details-card {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 12px;
                margin: 15px 0;
                text-align: left;
            }
            
            .home-saved-notification {
                position: fixed;
                top: 80px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                z-index: 2200;
                transform: translateX(400px);
                transition: transform 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 0.85rem;
                font-weight: 500;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            }
            
            .home-saved-notification.show {
                transform: translateX(0);
            }
            
            .home-saved-notification.success {
                background: #28a745;
                color: white;
            }
            
            .home-saved-notification.error {
                background: #dc3545;
                color: white;
            }
            
            /* Payment Method Styles - IMPROVED */
            .payment-methods {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin: 20px 0;
            }
            
            .payment-method {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 15px;
                border: 2px solid #e8e8e8;
                border-radius: 16px;
                cursor: pointer;
                transition: all 0.3s ease;
                background: white;
            }
            
            .payment-method:hover {
                border-color: #ff9800;
                background: #fffaf2;
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(255,152,0,0.1);
            }
            
            .payment-method.selected {
                border-color: #ff9800;
                background: #fffaf2;
                box-shadow: 0 3px 10px rgba(255,152,0,0.15);
            }
            
            .payment-method input[type="radio"] {
                width: 20px;
                height: 20px;
                cursor: pointer;
                accent-color: #ff9800;
                margin: 0;
                flex-shrink: 0;
            }
            
            .payment-icon {
                width: 50px;
                height: 50px;
                background: #f8f9fa;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            
            .payment-icon i {
                font-size: 1.6rem;
                color: #ff9800;
            }
            
            .payment-info {
                flex: 1;
            }
            
            .payment-name {
                font-weight: 700;
                font-size: 1rem;
                color: #003580;
                margin-bottom: 4px;
            }
            
            .payment-desc {
                font-size: 0.7rem;
                color: #888;
            }
            
            .payment-details-box {
                display: none;
                margin-top: 25px;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 16px;
                animation: fadeIn 0.3s ease;
            }
            
            .payment-details-box.show {
                display: block;
            }
            
            .payment-instructions {
                background: white;
                border-radius: 14px;
                padding: 20px;
            }
            
            .instruction-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 20px;
                padding-bottom: 12px;
                border-bottom: 2px solid #ff9800;
            }
            
            .instruction-header i {
                font-size: 1.6rem;
                color: #ff9800;
            }
            
            .instruction-header h4 {
                color: #003580;
                margin: 0;
                font-size: 1.1rem;
            }
            
            .qr-placeholder {
                width: 180px;
                height: 180px;
                background: linear-gradient(135deg, #f5f7fa, #e9eef5);
                border-radius: 12px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                margin: 0 auto;
                gap: 10px;
            }
            
            .qr-placeholder i {
                font-size: 3rem;
                color: #ff9800;
            }
            
            .qr-placeholder p {
                font-size: 0.7rem;
                color: #666;
                margin: 0;
            }
            
            .account-details {
                background: #fff9e6;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            
            .account-number {
                font-weight: bold;
                color: #003580;
                background: #e8f0fe;
                padding: 4px 8px;
                border-radius: 6px;
                font-family: monospace;
                font-size: 1rem;
            }
            
            .copy-btn {
                background: #e0e0e0;
                border: none;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 0.7rem;
                cursor: pointer;
                margin-left: 8px;
                transition: all 0.2s ease;
            }
            
            .copy-btn:hover {
                background: #ff9800;
                color: white;
            }
            
            .instruction-note {
                background: #e8f0fe;
                padding: 10px;
                border-radius: 8px;
                font-size: 0.7rem;
                color: #003580;
                margin-top: 10px;
                text-align: center;
            }
            
            .card-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                margin-bottom: 12px;
            }
            
            .booking-steps {
                display: flex;
                margin: 20px 0 25px;
                position: relative;
                padding: 0 10px;
            }
            
            .step {
                flex: 1;
                text-align: center;
                position: relative;
            }
            
            .step-number {
                width: 35px;
                height: 35px;
                background: #e0e0e0;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 8px;
                font-weight: bold;
                font-size: 0.9rem;
                color: #666;
                transition: all 0.3s ease;
            }
            
            .step.active .step-number {
                background: #ff9800;
                color: white;
                box-shadow: 0 0 0 3px rgba(255,152,0,0.3);
            }
            
            .step.completed .step-number {
                background: #28a745;
                color: white;
            }
            
            .step-label {
                font-size: 0.7rem;
                color: #666;
                font-weight: 500;
            }
            
            .step.active .step-label {
                color: #ff9800;
                font-weight: 600;
            }
            
            .step.completed .step-label {
                color: #28a745;
            }
            
            .step-line {
                position: absolute;
                top: 17px;
                left: 50%;
                width: 100%;
                height: 2px;
                background: #e0e0e0;
                z-index: 0;
            }
            
            .step:last-child .step-line {
                display: none;
            }
            
            .step-content {
                display: none;
                animation: fadeIn 0.3s ease;
            }
            
            .step-content.active {
                display: block;
            }
            
            .action-buttons {
                display: flex;
                gap: 15px;
                justify-content: space-between;
                align-items: center;
                margin-top: 20px;
            }

            /* Ghost/outline style, deliberately quieter than .btn-next so the
               two don't read as a matched pair of equally-weighted actions. */
            .btn-prev {
                background: transparent;
                color: #64748b;
                border: 1.5px solid #e2e8f0;
                padding: 9px 22px;
                border-radius: 40px;
                font-size: 0.85rem;
                font-weight: 600;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s ease;
            }

            .btn-prev:hover {
                background: #f1f5f9;
                color: #334155;
                border-color: #cbd5e1;
            }

            .btn-next {
                flex: 1;
                justify-content: center;
                background: linear-gradient(135deg, #ff9800, #f57c00);
                color: white;
                border: none;
                padding: 10px 25px;
                border-radius: 40px;
                font-size: 0.85rem;
                font-weight: 600;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
            }
            
            .btn-next:hover {
                background: #f57c00;
                transform: translateY(-2px);
            }
            
            .review-details {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .review-section {
                margin-bottom: 15px;
            }
            
            .review-section h4 {
                color: #003580;
                margin-bottom: 10px;
                font-size: 0.85rem;
                border-left: 3px solid #ff9800;
                padding-left: 10px;
            }
            
            .review-row {
                display: flex;
                padding: 6px 0;
                border-bottom: 1px solid #e9ecef;
                font-size: 0.8rem;
            }
            
            .review-label {
                width: 120px;
                font-weight: 600;
                color: #666;
            }
            
            .review-value {
                flex: 1;
                color: #333;
            }
            
            .error-message {
                background: #f8d7da;
                color: #721c24;
                padding: 10px 12px;
                border-radius: 8px;
                margin-bottom: 15px;
                font-size: 0.75rem;
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .payment-status-pending {
                background: #fff3cd;
                color: #856404;
                padding: 10px;
                border-radius: 8px;
                text-align: center;
                font-size: 0.8rem;
                margin-top: 10px;
            }
            
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @media (max-width: 768px) {
                .package-details-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                .booking-form-modal .form-row {
                    grid-template-columns: 1fr;
                    gap: 12px;
                }
                .home-destination-card {
                    min-width: 260px;
                }
                .payment-methods {
                    grid-template-columns: 1fr;
                    gap: 10px;
                }
                .card-row {
                    grid-template-columns: 1fr;
                }
                .action-buttons {
                    flex-direction: column-reverse;
                    gap: 14px;
                }
                .btn-prev, .btn-next {
                    width: 100%;
                    justify-content: center;
                }
                .review-row {
                    flex-direction: column;
                }
                .review-label {
                    width: 100%;
                    margin-bottom: 3px;
                }
            }
            
            @media (max-width: 480px) {
                .home-destination-card {
                    min-width: 240px;
                }
                .step-number {
                    width: 30px;
                    height: 30px;
                    font-size: 0.8rem;
                }
                .step-label {
                    font-size: 0.6rem;
                }
                .step-line {
                    top: 15px;
                }
            }
            
            @keyframes modalSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(-50px) scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// ========== FIXED INITIALIZATION ==========
// Force render local destinations when window is fully loaded
function forceRenderLocalDestinations() {
    if (typeof window.homeLocalDestinationsData !== 'undefined' && window.homeLocalDestinationsData.length > 0) {
        console.log('Forcing local destinations render with:', window.homeLocalDestinationsData.length, 'destinations');
        initHomePackages(window.homeLocalDestinationsData);
    } else {
        console.log('No local destinations data found, retrying in 500ms...');
        setTimeout(forceRenderLocalDestinations, 500);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    addHomePackageModalStyles();

    // Wait a bit to ensure PHP data is loaded
    setTimeout(forceRenderLocalDestinations, 100);
});
