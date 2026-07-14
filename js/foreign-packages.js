// ========================================
// FILE: js/foreign-packages.js
// DESCRIPTION: Foreign Destinations Packages Popup Functionality
// Loads from database via API
// ========================================

// Cache for destination data from database
let foreignPackageDataCache = {};

// Hotel Selection State
window.foreignSelectedHotelSurcharge = 0;
window.toggleForeignHotelSelection = function () {
    const dropdown = document.getElementById('foreignHotelDropdown');
    if (dropdown) dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
};
window.selectForeignHotel = function (index, name, stars, price) {
    const basePrice = window.currentForeignDest ? window.currentForeignDest.price : 0;
    const nameEl = document.getElementById('foreignSelectedHotelName');
    const starHtml = stars ? ` <span style="font-size:0.8rem;">${'⭐'.repeat(stars)}</span>` : '';
    if (nameEl) nameEl.innerHTML = name + starHtml + ' <i class="fas fa-chevron-down" style="font-size:0.7rem; margin-left:5px;"></i>';
    window.foreignSelectedHotelSurcharge = price;
    if (typeof updateForeignTotalPrice === 'function') updateForeignTotalPrice(basePrice);
    const dropdown = document.getElementById('foreignHotelDropdown');
    if (dropdown) dropdown.style.display = 'none';
    document.querySelectorAll('#foreignHotelDropdown .hotel-option').forEach((el) => {
        el.style.background = Number(el.dataset.hotelIndex) === index ? '#e3f2fd' : 'white';
    });
};

// Format number with commas
function formatNumber(num) {
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

// Load destination data from database API
async function loadDestinationFromDatabase(destKey) {
    // Check cache first
    if (foreignPackageDataCache[destKey]) {
        return foreignPackageDataCache[destKey];
    }

    try {
        const response = await fetch(`api/get-foreign-destinations.php?key=${encodeURIComponent(destKey)}`);
        const data = await response.json();

        if (data.success && data.destination) {
            const dest = data.destination;

            // Parse JSON strings if needed
            if (typeof dest.itinerary === 'string') {
                try {
                    dest.itinerary = JSON.parse(dest.itinerary);
                } catch (e) { dest.itinerary = []; }
            }
            if (typeof dest.inclusions === 'string') {
                try {
                    dest.inclusions = JSON.parse(dest.inclusions);
                } catch (e) { dest.inclusions = []; }
            }
            if (typeof dest.exclusions === 'string') {
                try {
                    dest.exclusions = JSON.parse(dest.exclusions);
                } catch (e) { dest.exclusions = []; }
            }
            if (typeof dest.hotels === 'string') {
                try {
                    dest.hotels = JSON.parse(dest.hotels);
                } catch (e) { dest.hotels = []; }
            }

            // Set default values
            dest.duration = dest.duration || '3D/2N';
            dest.groupSize = dest.group_size || '2-15 pax';
            dest.bestSeason = dest.best_season || 'Year Round';
            dest.activities = dest.activities_count || 0;

            foreignPackageDataCache[destKey] = dest;
            return dest;
        }
        return null;
    } catch (error) {
        console.error('Error loading from database:', error);
        return null;
    }
}

window.switchForeignTab = function (event, tabId) {
    const tabs = event.target.parentElement.querySelectorAll('.foreign-tab');
    tabs.forEach(t => {
        t.classList.remove('active');
        t.style.color = '#666';
        t.style.borderBottomColor = 'transparent';
    });
    event.target.classList.add('active');
    event.target.style.color = '#003580';
    event.target.style.borderBottomColor = '#ff9800';

    const modalBody = event.target.closest('.package-modal-body');
    modalBody.querySelectorAll('.foreign-pane').forEach(p => p.style.display = 'none');
    modalBody.querySelector('#foreign-pane-' + tabId).style.display = 'block';
};

// "View Tour Details" now navigates to the full package-details.php page.
// The original modal-building logic is kept as showForeignPackagePopupModal —
// used by package-details.php's "Book This Deal" button (via resumeForeignBooking)
// to open the booking flow directly, without rebuilding it.
window.showForeignPackagePopup = function (destKey) {
    window.location.href = `package-details.php?type=foreign&id=${encodeURIComponent(destKey)}`;
};

// Global function to show foreign package popup
window.showForeignPackagePopupModal = async function (destKey) {
    console.log('Showing foreign package for:', destKey);

    // Create modal if it doesn't exist
    let modal = document.getElementById('foreignPackageModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'foreignPackageModal';
        modal.className = 'package-modal';
        modal.innerHTML = `
            <div class="package-modal-content" style="position:relative;">
                <div class="close-modal-circle" onclick="closeForeignPackageModal()" style="position:absolute; top:15px; right:15px; width:35px; height:35px; background:rgba(0,0,0,0.5); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.4rem; cursor:pointer; z-index:2000; backdrop-filter:blur(4px); transition:all 0.3s; border: 1px solid rgba(255,255,255,0.2);">&times;</div>
                <div class="package-modal-header" style="display:none;">
                    <span class="close-modal" onclick="closeForeignPackageModal()">&times;</span>
                    <h2 id="foreignModalDestName">Loading...</h2>
                    <p id="foreignModalDestLocation"><i class="fas fa-map-marker-alt"></i> Loading...</p>
                </div>
                <div class="package-modal-body" id="foreignPackageModalBody">
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
                closeForeignPackageModal();
            }
        });
    } else {
        // Show loading state
        const modalBody = document.getElementById('foreignPackageModalBody');
        if (modalBody) {
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
                    <p>Loading tour details...</p>
                </div>
            `;
        }
    }

    modal.classList.add('active');

    // Load destination data from database
    const destination = await loadDestinationFromDatabase(destKey);

    if (!destination) {
        const modalBody = document.getElementById('foreignPackageModalBody');
        if (modalBody) {
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 2rem; color: #dc3545;"></i>
                    <p>Package details coming soon!</p>
                    <button class="book-now-btn" onclick="closeForeignPackageModal()" style="width: auto; margin-top: 20px;">Close</button>
                </div>
            `;
        }
        return;
    }

    // Update modal header
    document.getElementById('foreignModalDestName').textContent = destination.name || destination.title;
    document.getElementById('foreignModalDestLocation').innerHTML = `<i class="fas fa-map-marker-alt"></i> ${destination.country || destination.location}`;
    const header = document.querySelector('.package-modal-header');
    if (header) header.style.display = 'none';
    window.currentForeignDestCurrency = destination.currency || '₱';
    window.foreignSelectedHotelSurcharge = 0; // Initialize surcharge
    window.currentForeignDest = destination;
    window.currentForeignDestKey = destKey;

    const modalBody = document.getElementById('foreignPackageModalBody');

    // Build itinerary HTML
    let itineraryHtml = `<div class="itinerary-section"><h3><i class="fas fa-list-ol"></i> Tour Itinerary</h3>`;

    if (destination.itinerary && destination.itinerary.length > 0) {
        destination.itinerary.forEach(day => {
            let dayNumber = day.title ? (day.title.split(':')[0] || `Day ${day.day}`) : `Day ${day.day || 1}`;
            let dayTitle = day.title ? (day.title.split(':')[1] || day.title) : (`Day ${day.day || 1}`);

            itineraryHtml += `
                <div class="itinerary-day">
                    <h4>
                        <span class="day-badge" style="background: #ff9800; color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; margin-right: 8px; display: inline-block;">
                            ${escapeHtml(dayNumber)}
                        </span>
                        ${escapeHtml(dayTitle)}
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

    // ========== Payment Methods HTML with 4-step booking ==========
    let html = `
        <span class="close-modal" onclick="closeForeignPackageModal()" style="position:absolute; top:15px; right:20px; color:white; font-size:1.8rem; cursor:pointer; z-index:10; text-shadow:0 2px 4px rgba(0,0,0,0.5);">&times;</span>
        
        <div id="foreignDetailsView">
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
                return `<img src="${escapeHtml(images[0] || 'images/placeholder-dest.jpg')}" alt="${escapeHtml(destination.name || destination.title)}" style="width:100%; height:100%; object-fit:cover;">`;
            }
        })()}
                <div style="position:absolute; bottom:0; left:0; right:0; padding:40px 20px 15px; background:linear-gradient(to top, rgba(0,0,0,0.9), transparent); color:white; z-index:10;">
                    <h2 style="margin:0; font-size:1.6rem; text-shadow:0 2px 10px rgba(0,0,0,0.9), 0 1px 4px rgba(0,0,0,0.7); color: #ffffff !important; font-weight: 800;">${escapeHtml(destination.name || destination.title)}</h2>
                    <p style="margin:5px 0 0; font-size:0.85rem; text-shadow:0 1px 4px rgba(0,0,0,0.7); color: #ffffff !important; font-weight: 600;"><i class="fas fa-map-marker-alt" style="color:#ff9800;"></i> ${escapeHtml(destination.country || destination.location)} | <i class="fas fa-clock" style="color:#ff9800;"></i> ${escapeHtml(destination.duration)}</p>
                </div>
            </div>
            
            <div style="display:flex; overflow-x:auto; border-bottom:1px solid #ddd; margin-bottom:20px;">
                <div class="foreign-tab active" onclick="switchForeignTab(event, 'info')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#003580; border-bottom:3px solid #ff9800; white-space:nowrap;">Overview</div>
                <div class="foreign-tab" onclick="switchForeignTab(event, 'itinerary')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Itinerary</div>
                <div class="foreign-tab" onclick="switchForeignTab(event, 'inclusions')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Inclusions</div>
                ${destination.partner_id ? `<div class="foreign-tab" onclick="switchForeignTab(event, 'partner')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Partner Profile</div>` : ''}
            </div>

            <div id="foreign-pane-info" class="foreign-pane" style="display:block;">
                <div class="package-details-grid" style="margin-bottom:15px;">
                    <div class="detail-item"><i class="fas fa-clock"></i><div class="detail-label">TRAVEL VALIDITY</div><div class="detail-value">${escapeHtml(formatValidityDate(destination.promo_start, destination.promo_end, destination.bestSeason))}</div></div>
                    <div class="detail-item"><i class="fas fa-users"></i><div class="detail-label">GROUP SIZE</div><div class="detail-value">${escapeHtml(destination.groupSize)}</div></div>
                    <div class="detail-item"><i class="fas fa-calendar-alt"></i><div class="detail-label">DURATION</div><div class="detail-value">${escapeHtml(destination.duration)}</div></div>
                </div>
            </div>

            <div id="foreign-pane-itinerary" class="foreign-pane" style="display:none;">
                ${itineraryHtml}
                ${remarksHtml}
            </div>
            
            <div id="foreign-pane-inclusions" class="foreign-pane" style="display:none;">
                ${inclusionsHtml}
                ${exclusionsHtml}
            </div>
            ${destination.partner_id ? `
            <div id="foreign-pane-partner" class="foreign-pane" style="display:none;">
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
                <button onclick="document.getElementById('foreignDetailsView').style.display='none'; document.getElementById('foreignBookingView').style.display='block';" style="background:linear-gradient(135deg, #ff9800, #f57c00); color:white; border:none; padding:10px 25px; border-radius:30px; font-weight:bold; cursor:pointer;">
                    Book This Deal <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <div id="foreignBookingView" style="display:none;">
            <div class="deal-price-card">
                <div class="price">${destination.currency || '₱'}${formatNumber(destination.price)}</div>
                <small>/ person</small>
                <div style="margin-top: 8px;">${escapeHtml(destination.duration)} tour package</div>
            </div>
        
        <!-- Booking Steps Navigation -->
        <div class="booking-steps">
            <div class="step active" id="foreignStep1">
                <div class="step-number">1</div>
                <div class="step-label">Date</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="foreignStep2">
                <div class="step-number">2</div>
                <div class="step-label">Info</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="foreignStep3">
                <div class="step-number">3</div>
                <div class="step-label">Review</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="foreignStep4">
                <div class="step-number">4</div>
                <div class="step-label">Payment</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="foreignStep5">
                <div class="step-number">5</div>
                <div class="step-label">Confirm</div>
            </div>
        </div>
        
        <!-- Step 1: Travel Date -->
        <div id="foreignStep1Content" class="step-content active">
            <div class="booking-form-modal">
                <h3><i class="fas fa-calendar-alt"></i> Select Travel Date</h3>
                <p style="color: #666; margin-bottom: 20px;">Please pick your preferred travel date to get started.</p>
                
                <div class="form-group">
                    <label>Travel Date *</label>
                    <input type="text" id="foreignStepDate" placeholder="Select your travel date" readonly style="cursor:pointer; background:#fff;">
                </div>
                <div id="foreignDateRangeInfo" style="display:none; margin-top:8px; padding:10px 14px; background:linear-gradient(135deg,#e3f2fd,#e8f5e9); border-left:4px solid #2196F3; border-radius:6px; font-size:0.9em; color:#0d47a1; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <i class="fas fa-info-circle"></i> <span id="foreignDateRangeText"></span>
                            <div id="foreignPromoEndingWarning" style="display:none; margin-top:5px; color:#b71c1c; font-weight:bold; font-size:0.85em;">
                                <i class="fas fa-exclamation-triangle"></i> <span id="foreignPromoEndingWarningText"></span>
                            </div>
                            <div id="foreignBlockedDateConflict" style="display:none; margin-top:5px; color:#b71c1c; font-weight:bold; font-size:0.85em;">
                                <i class="fas fa-ban"></i> <span id="foreignBlockedDateConflictText"></span>
                            </div>
                        </div>
                        <button type="button" id="foreignClearDateBtn" onclick="clearForeignDate()" style="background: rgba(0,0,0,0.05); border: none; border-radius: 4px; padding: 2px 6px; cursor: pointer; color: #0d47a1; font-size: 0.8rem; font-weight: 600; margin-left: 10px; white-space: nowrap;" title="Clear Selection">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>

                ${destination.hotels && destination.hotels.length > 0 ? `
                <div class="form-group" style="margin-top:20px;">
                    <label>Hotel <span style="font-weight:400; color:#94a3b8;">(optional)</span></label>
                    <div class="hotel-selection-item" onclick="toggleForeignHotelSelection()" style="cursor:pointer; border:1px solid #ddd; border-radius:8px; padding:12px 15px; display:flex; align-items:center; justify-content:space-between;">
                        <span><i class="fas fa-hotel" style="color:#ff9800; margin-right:8px;"></i><span id="foreignSelectedHotelName" style="font-weight:600;">No hotel selected</span></span>
                        <i class="fas fa-chevron-down" style="font-size:0.8rem; color:#666;"></i>
                    </div>
                    <div id="foreignHotelDropdown" class="hotel-dropdown" style="display:none; margin-top:8px; background: white; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <div class="hotel-option active" data-hotel-index="-1" onclick="selectForeignHotel(-1, 'No hotel selected', 0, 0)" style="padding: 12px 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s; background:#e3f2fd;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-weight:500; color:#64748b;">No hotel <span style="font-size:0.8rem;">(I'll arrange my own)</span></span>
                            </div>
                        </div>
                        ${destination.hotels.map((h, i) => `
                            <div class="hotel-option" data-hotel-index="${i}" onclick="selectForeignHotel(${i}, '${escapeHtml(h.name).replace(/'/g, "\\'")}', ${h.stars || 0}, ${h.price})" style="padding: 12px 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-weight:500;">${escapeHtml(h.name)} <span style="font-size:0.8rem;">${h.stars ? '⭐'.repeat(h.stars) : ''}</span></span>
                                    <span style="color:#4caf50; font-size:0.9rem;">${h.price > 0 ? `+${destination.currency || '₱'}${formatNumber(h.price)}` : 'Included'}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}

                <div id="foreignStep1Errors" class="error-message" style="display: none;"></div>
                
                <div class="action-buttons">
                    <button type="button" class="btn-next" onclick="validateForeignStep1()" style="width: 100%;">Next: Passenger Info <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Step 2: Passenger Information -->
        <div id="foreignStep2Content" class="step-content">
            <div class="booking-form-modal">
                <h3><i class="fas fa-user"></i> Passenger Information</h3>
                <div class="booking-summary" style="margin-bottom: 20px; background: #f9f9f9;">
                    <div class="summary-item"><span>Price per Person:</span><span>${destination.currency || '₱'}${formatNumber(destination.price)}</span></div>
                    <div class="summary-item"><span>Travelers:</span><span id="foreignStepSummaryTravelers">1</span></div>
                    <div class="summary-item discounted" id="foreignStepSummaryDiscountedRow" style="display:none;"><span>Discounted:</span><span id="foreignStepSummaryDiscounted">₱0</span></div>
                    <div class="summary-item total"><span>Total:</span><span id="foreignStepSummaryTotal">${destination.currency || '₱'}${formatNumber(destination.price)}</span></div>
                </div>

                <div class="form-row">
                    <div class="form-group"><label>Full Name *</label><input type="text" id="foreignStepFullName" autocomplete="name" placeholder="Enter your full name" value="${window.currentFullName || ''}"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email ${window.currentUserEmail ? '(Your Account Email)' : '*'}</label><input type="email" id="foreignStepEmail" autocomplete="email" placeholder="your.email@example.com" value="${window.currentUserEmail || ''}" ${window.currentUserEmail ? 'readonly style="background-color:#f0f0f0;cursor:not-allowed;"' : ''}></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Phone *</label><input type="tel" id="foreignStepPhone" autocomplete="tel" placeholder="+63 912 345 6789"></div>
                    <div class="form-group"><label>Travelers *</label><input type="number" id="foreignStepTravelers" min="1" value="1" onchange="updateForeignStepTotal(${destination.price})"></div>
                </div>
                <!-- Voucher Section (Step 2) -->
                <div id="foreignStep2VoucherArea" style="margin-bottom:18px;"></div>
                <div class="form-group"><label>Special Requests</label><textarea id="foreignStepRequests" rows="2" placeholder="Any special requirements, dietary restrictions, etc."></textarea></div>
                
                <div id="foreignStep2Errors" class="error-message" style="display: none;"></div>
                
                <div class="action-buttons">
                    <button type="button" class="btn-prev" onclick="goToForeignStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="button" class="btn-next" onclick="validateForeignStep2()">Review Booking <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Step 3: Review -->
        <div id="foreignStep3Content" class="step-content">
            <div class="review-details">
                <div class="review-section"><h4>Passenger Information</h4>
                    <div class="review-row"><div class="review-label">Full Name:</div><div class="review-value" id="foreignReviewName">-</div></div>
                    <div class="review-row"><div class="review-label">Email:</div><div class="review-value" id="foreignReviewEmail">-</div></div>
                    <div class="review-row"><div class="review-label">Phone:</div><div class="review-value" id="foreignReviewPhone">-</div></div>
                </div>
                <div class="review-section"><h4>Travel Details</h4>
                    <div class="review-row"><div class="review-label">Destination:</div><div class="review-value">${escapeHtml(destination.name)}</div></div>
                    <div class="review-row"><div class="review-label">Duration:</div><div class="review-value">${escapeHtml(destination.duration)}</div></div>
                    <div class="review-row"><div class="review-label">Travel Date:</div><div class="review-value" id="foreignReviewDate">-</div></div>
                    <div class="review-row"><div class="review-label">Travelers:</div><div class="review-value" id="foreignReviewTravelers">-</div></div>
                    <div class="review-row"><div class="review-label">Special Requests:</div><div class="review-value" id="foreignReviewRequests">-</div></div>
                </div>
                <div class="review-section"><h4>Price Summary</h4>
                    <div class="review-row"><div class="review-label">Price per Person:</div><div class="review-value">${destination.currency || '₱'}${formatNumber(destination.price)}</div></div>
                    <div class="review-row total"><div class="review-label">Total:</div><div class="review-value" id="foreignReviewTotal">${destination.currency || '₱'}${formatNumber(destination.price)}</div></div>
                </div>
            </div>
            <div class="action-buttons">
                <button type="button" class="btn-prev" onclick="goToForeignStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="button" class="btn-next" onclick="goToForeignStep(4)">Proceed to Payment <i class="fas fa-credit-card"></i></button>
            </div>
        </div>
        
        <!-- Step 4: Payment Methods - IMPROVED DESIGN -->
        <div id="foreignStep4Content" class="step-content">
            <div class="booking-form-modal">
                <h3><i class="fas fa-credit-card"></i> Select Payment Method</h3>
                <div class="payment-methods">
                    <div class="payment-method" onclick="selectForeignPaymentMethod('gcash', event)">
                        <input type="radio" name="foreign_payment" value="gcash" id="foreignGcashRadio">
                        <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">GCash</div>
                            <div class="payment-desc">Scan QR code to pay</div>
                        </div>
                    </div>
                    <div class="payment-method" onclick="selectForeignPaymentMethod('paymaya', event)">
                        <input type="radio" name="foreign_payment" value="paymaya" id="foreignPaymayaRadio">
                        <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">PayMaya</div>
                            <div class="payment-desc">Scan QR code to pay</div>
                        </div>
                    </div>
                    <div class="payment-method disabled" onclick="alert('Credit/Debit Card payment is coming soon! Please use other payment methods for now.')" style="opacity: 0.6; cursor: not-allowed; position: relative;">
                        <input type="radio" name="foreign_payment" value="card" id="foreignCardRadio" disabled>
                        <div class="payment-icon"><i class="fas fa-credit-card"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">Credit / Debit Card <span style="color: #ef4444; font-size: 0.65rem; font-weight: 800; margin-left: 5px;">(NOT AVAILABLE)</span></div>
                            <div class="payment-desc">Coming Soon</div>
                        </div>
                    </div>
                    <div class="payment-method disabled" onclick="alert('Bank Transfer is currently unavailable. Please use GCash or PayMaya for now.')" style="opacity: 0.6; cursor: not-allowed; position: relative;">
                        <input type="radio" name="foreign_payment" value="bank" id="foreignBankRadio" disabled>
                        <div class="payment-icon"><i class="fas fa-university"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">Bank Transfer <span style="color: #ef4444; font-size: 0.65rem; font-weight: 800; margin-left: 5px;">(NOT AVAILABLE)</span></div>
                            <div class="payment-desc">Coming Soon</div>
                        </div>
                    </div>
                </div>
                
                <div id="foreignGcashDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>GCash Payment</h4></div>
                        <div class="qr-code"><div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>GCash QR Code</p><p>0945 776 4140</p></div></div>
                        <div class="account-details">
                            <p><strong>GCash Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboardForeign('0945 776 4140')">Copy</button></p>
                            <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                            <p><strong>Amount:</strong> <span style="color:#ff9800;">${destination.currency || '₱'}<span id="foreignGcashAmount">${formatNumber(destination.price)}</span></span></p>
                        </div>
                        <div class="form-group"><label>Reference Number *</label><input type="text" id="foreignPaymentRefGcash" placeholder="Enter GCash reference number"></div>
                        <div class="file-upload" onclick="document.getElementById('foreignProofGcash').click()">
                            <i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p>
                            <p class="file-name" id="foreign-file-name-gcash">No file selected</p>
                            <div id="foreign-preview-gcash" class="upload-preview"></div>
                            <input type="file" id="foreignProofGcash" accept="image/*" style="display:none" onchange="handleForeignFileUpload(event, 'gcash')">
                        </div>
                        <div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of payment confirmation</div>
                    </div>
                </div>
                
                <div id="foreignPaymayaDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>PayMaya Payment</h4></div>
                        <div class="qr-code"><div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>PayMaya QR Code</p><p>0945 776 4140</p></div></div>
                        <div class="account-details">
                            <p><strong>PayMaya Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboardForeign('0945 776 4140')">Copy</button></p>
                            <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                            <p><strong>Amount:</strong> <span style="color:#ff9800;">${destination.currency || '₱'}<span id="foreignPaymayaAmount">${formatNumber(destination.price)}</span></span></p>
                        </div>
                        <div class="form-group"><label>Reference Number *</label><input type="text" id="foreignPaymentRefPaymaya" placeholder="Enter PayMaya reference number"></div>
                        <div class="file-upload" onclick="document.getElementById('foreignProofPaymaya').click()">
                            <i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p>
                            <p class="file-name" id="foreign-file-name-paymaya">No file selected</p>
                            <div id="foreign-preview-paymaya" class="upload-preview"></div>
                            <input type="file" id="foreignProofPaymaya" accept="image/*" style="display:none" onchange="handleForeignFileUpload(event, 'paymaya')">
                        </div>
                        <div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of payment confirmation</div>
                    </div>
                </div>
                
                <div id="foreignCardDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-credit-card"></i><h4>Card Payment</h4></div>
                        <div class="form-group"><label>Card Number *</label><input type="text" id="foreignCardNumber" placeholder="1234 5678 9012 3456"></div>
                        <div class="card-row">
                            <div class="form-group"><label>Expiry *</label><input type="text" id="foreignExpiryDate" placeholder="MM/YY"></div>
                            <div class="form-group"><label>CVV *</label><input type="text" id="foreignCvv" placeholder="123"></div>
                        </div>
                        <div class="form-group"><label>Cardholder Name *</label><input type="text" id="foreignCardName" placeholder="Name on card"></div>
                    </div>
                </div>
                
                <div id="foreignBankDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-university"></i><h4>Bank Transfer</h4></div>
                        <div class="account-details">
                            <p><strong>BPI:</strong> 1234 5678 90 <button class="copy-btn" onclick="copyToClipboardForeign('1234 5678 90')">Copy</button></p>
                            <p><strong>BDO:</strong> 5678 1234 56 <button class="copy-btn" onclick="copyToClipboardForeign('5678 1234 56')">Copy</button></p>
                            <p><strong>Metrobank:</strong> 9012 3456 78 <button class="copy-btn" onclick="copyToClipboardForeign('9012 3456 78')">Copy</button></p>
                            <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                            <p><strong>Amount:</strong> <span style="color:#ff9800;">${destination.currency || '₱'}<span id="foreignBankAmount">${formatNumber(destination.price)}</span></span></p>
                        </div>
                        <div class="form-group"><label>Reference Number *</label><input type="text" id="foreignBankRef" placeholder="Enter bank reference number"></div>
                        <div class="file-upload" onclick="document.getElementById('foreignProofBank').click()">
                            <i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p>
                            <p class="file-name" id="foreign-file-name-bank">No file selected</p>
                            <div id="foreign-preview-bank" class="upload-preview"></div>
                            <input type="file" id="foreignProofBank" accept="image/*" style="display:none" onchange="handleForeignFileUpload(event, 'bank')">
                        </div>
                        <div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of bank transfer confirmation</div>
                    </div>
                </div>
                
                <div id="foreignStep4Errors" class="error-message" style="display: none;"></div>
                <div class="action-buttons">
                    <button type="button" class="btn-prev" onclick="goToForeignStep(3)"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="button" class="btn-next" onclick="validateForeignPayment()">Complete Payment <i class="fas fa-check-circle"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Step 5: Confirmation -->
        <div id="foreignStep5Content" class="step-content">
            <div class="success-message">
                <i class="fas fa-clock"></i>
                <h2>⏳ Booking Received!</h2>
                <p>Your booking has been submitted and is pending payment verification.</p>
                <div class="booking-number" id="foreignBookingNumber">Booking: Processing...</div>
                <div class="details-card">
                    <h4>📋 Booking Details:</h4>
                    <p><strong>Destination:</strong> ${escapeHtml(destination.name)}</p>
                    <p><strong>Duration:</strong> ${escapeHtml(destination.duration)}</p>
                    <p><strong>Travel Date:</strong> <span id="foreignConfirmDate">-</span></p>
                    <p><strong>Travelers:</strong> <span id="foreignConfirmTravelers">-</span></p>
                    <p><strong>Total Amount:</strong> <span style="color:#ff9800;" id="foreignConfirmTotal">${destination.currency || '₱'}${formatNumber(destination.price)}</span></p>
                    <p><strong>Payment Method:</strong> <span id="foreignConfirmPayment">-</span></p>
                    <p><strong>Payment Status:</strong> <span style="color:#ff9800;">Pending Verification</span></p>
                    <p><strong>Booked By:</strong> <span id="foreignConfirmName">-</span></p>
                </div>
                <div class="payment-status-pending"><i class="fas fa-info-circle"></i> Your payment is pending verification. Our team will contact you shortly.</div>
                <div class="action-buttons">
                    <button class="book-now-btn" onclick="closeForeignPackageModal()" style="background: #ff9800; width: auto;">Close</button>
                </div>
                </div>
            </div>
        </div>
    </div>
    `;

    modalBody.innerHTML = html;

    // Store destination data for later use
    window.currentForeignDest = destination;

    // ── Flatpickr calendar for travel date ──────────────────────────────────
    const blockedDates = (destination.blocked_dates || '')
        .split(',')
        .map(d => d.trim())
        .filter(Boolean);

    // Parse blocked months (CSV string "1,2,3" -> array of numbers [0,1,2] for JS)
    const blockedMonths = (destination.blocked_months || '')
        .split(',')
        .map(m => parseInt(m.trim()) - 1)
        .filter(m => !isNaN(m));

    const parsedDuration = parseInt(destination.duration) || 1;
    // Trip length shown on the calendar is controlled by the content
    // manager's "highlight duration" setting, not parsed from the
    // free-text duration label.
    const highlightDuration = parseInt(destination.highlight_duration) || parsedDuration;

    flatpickr('#foreignStepDate', {
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
            const rangeInfo = document.getElementById('foreignDateRangeInfo');
            const rangeText = document.getElementById('foreignDateRangeText');
            const warningBox = document.getElementById('foreignPromoEndingWarning');
            const warningText = document.getElementById('foreignPromoEndingWarningText');

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
                const conflictBox = document.getElementById('foreignBlockedDateConflict');
                const conflictText = document.getElementById('foreignBlockedDateConflictText');
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

                const clearBtn = document.getElementById('foreignClearDateBtn');

                if (foundConflict || promoExpired) {
                    window.foreignDateConflict = true;
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
                    window.foreignDateConflict = false;
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

        if (window.foreignSliderInterval) clearInterval(window.foreignSliderInterval);
        window.foreignSliderInterval = setInterval(() => {
            showSlide((current + 1) % slides.length);
        }, 3500);

        dots.forEach(dot => {
            dot.onclick = (e) => {
                clearInterval(window.foreignSliderInterval);
                showSlide(parseInt(e.target.dataset.index));
                window.foreignSliderInterval = setInterval(() => {
                    showSlide((current + 1) % slides.length);
                }, 3500);
            };
        });
    }

    window.foreignDateConflict = false; // Reset state

    window.clearForeignDate = function () {
        const fp = document.querySelector('#foreignStepDate')._flatpickr;
        if (fp) {
            fp.clear();
            fp.redraw();
        }
        const rangeInfo = document.getElementById('foreignDateRangeInfo');
        if (rangeInfo) rangeInfo.style.display = 'none';
        document.getElementById('foreignStepDate').value = '';
    };
    // ──────────────────────────────────────────────────────────────────────────

    // Update total when travelers change
    document.getElementById('foreignStepTravelers').addEventListener('change', function () {
        updateForeignStepTotal(destination.price);
    });
};

// Helper function to copy to clipboard
function copyToClipboardForeign(text) {
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

// Update total for step 1
function updateForeignStepTotal(price) {
    const travelers = parseInt(document.getElementById('foreignStepTravelers').value) || 1;
    const hotelSurcharge = window.foreignSelectedHotelSurcharge || 0;
    const total = (travelers * price) + hotelSurcharge;
    document.getElementById('foreignStepSummaryTravelers').textContent = travelers;
    document.getElementById('foreignStepSummaryTotal').textContent = (window.currentForeignDestCurrency || '₱') + formatNumber(total);

    // Update payment amounts
    if (document.getElementById('foreignGcashAmount')) document.getElementById('foreignGcashAmount').textContent = formatNumber(total);
    if (document.getElementById('foreignPaymayaAmount')) document.getElementById('foreignPaymayaAmount').textContent = formatNumber(total);
    if (document.getElementById('foreignBankAmount')) document.getElementById('foreignBankAmount').textContent = formatNumber(total);

    if (typeof updateVoucherTotalInline === 'function') {
        updateVoucherTotalInline('foreign', total);
    }
}

// Go to specific step
function goToForeignStep(step) {
    for (let i = 1; i <= 5; i++) {
        const stepDiv = document.getElementById(`foreignStep${i}`);
        const contentDiv = document.getElementById(`foreignStep${i}Content`);
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
        const price = window.currentForeignDest ? window.currentForeignDest.price : 0;
        const travelers = parseInt(document.getElementById('foreignStepTravelers')?.value) || 1;
        const hotelSurcharge = window.foreignSelectedHotelSurcharge || 0;
        const total = (price * travelers) + hotelSurcharge;
        initVoucherCheckoutInline(
            'foreign',
            total,
            'foreign_destinations',
            'foreignStep2VoucherArea',
            'foreignStepSummaryTotal',
            () => window.currentForeignDestCurrency || '₱',
            window.currentForeignDest ? window.currentForeignDest.id : 0
        );
    }
    if (step === 4 && window.foreignBookingData) {
        const total = window.foreignBookingData.totalAmount;
        if (document.getElementById('foreignGcashAmount')) document.getElementById('foreignGcashAmount').textContent = formatNumber(total);
        if (document.getElementById('foreignPaymayaAmount')) document.getElementById('foreignPaymayaAmount').textContent = formatNumber(total);
        if (document.getElementById('foreignBankAmount')) document.getElementById('foreignBankAmount').textContent = formatNumber(total);
    }
}

// Validate step 1
function validateForeignStep1() {
    const errors = [];
    const travelDate = document.getElementById('foreignStepDate').value;

    if (!travelDate) errors.push('Travel Date is required');
    if (window.foreignDateConflict) errors.push('Selected range includes blocked dates. Please pick another date.');

    if (errors.length > 0) {
        const errorDiv = document.getElementById('foreignStep1Errors');
        errorDiv.style.display = 'block';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    // Require login before proceeding to passenger info
    if (window.currentForeignDest) {
        requireLogin('resumeForeignBooking', window.currentForeignDest.dest_key, 2);
    } else {
        requireLogin(() => {
            goToForeignStep(2);
        });
    }

    return true;
}

window.resumeForeignBooking = async function (destKey, step) {
    const modal = document.getElementById('foreignPackageModal');
    if (modal && modal.classList.contains('active')) {
        // Modal is already open. resumeForeignBooking is only ever called to
        // enter or resume the booking flow (never to show the details view),
        // so always switch to it -- package-details.php already shows details.
        const detailsView = document.getElementById('foreignDetailsView');
        const bookingView = document.getElementById('foreignBookingView');
        if (detailsView) detailsView.style.display = 'none';
        if (bookingView) bookingView.style.display = 'block';
        goToForeignStep(step);
    } else {
        // Modal not open (likely after login redirect, or arriving fresh from
        // package-details.php). showForeignPackagePopupModal does a real
        // network fetch + builds the modal -- await it fully instead of
        // guessing a fixed delay, otherwise goToForeignStep can run before
        // the modal (and its date picker) is actually ready.
        if (typeof showForeignPackagePopupModal === 'function') {
            await showForeignPackagePopupModal(destKey);

            // Always switch straight to the booking view -- skip the
            // redundant details view since package-details.php already shows it.
            const detailsView = document.getElementById('foreignDetailsView');
            const bookingView = document.getElementById('foreignBookingView');
            if (detailsView) detailsView.style.display = 'none';
            if (bookingView) bookingView.style.display = 'block';

            // Pre-fill user info if available
            if (window.currentFullName) {
                const nameField = document.getElementById('foreignStepFullName');
                if (nameField) nameField.value = window.currentFullName;
            }
            goToForeignStep(step);
        }
    }
};

function validateForeignStep2() {
    const errors = [];
    const fullName = document.getElementById('foreignStepFullName').value.trim();
    const email = document.getElementById('foreignStepEmail').value.trim();
    const phone = document.getElementById('foreignStepPhone').value.trim();
    const travelDate = document.getElementById('foreignStepDate').value;
    const travelers = document.getElementById('foreignStepTravelers').value;

    if (!fullName) errors.push('Full Name is required');

    // Validate email (either from logged-in user or form input)
    if (!email) errors.push('Email address is required');
    else if (!email.match(/^[^\s@]+@([^\s@]+\.)+[^\s@]+$/)) errors.push('Please enter a valid email address');
    
    if (!phone) errors.push('Phone number is required');
    if (!travelers || travelers < 1) errors.push('At least 1 traveler is required');

    if (errors.length > 0) {
        const errorDiv = document.getElementById('foreignStep2Errors');
        errorDiv.style.display = 'block';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    // Save data
    window.foreignBookingData = {
        fullName, email, phone, travelDate, travelers: parseInt(travelers),
        specialRequests: document.getElementById('foreignStepRequests').value.trim() || '',
        totalAmount: 0
    };

    // Update review section
    document.getElementById('foreignReviewName').textContent = fullName;
    document.getElementById('foreignReviewEmail').textContent = email;
    document.getElementById('foreignReviewPhone').textContent = phone;
    document.getElementById('foreignReviewDate').textContent = new Date(travelDate).toLocaleDateString();
    document.getElementById('foreignReviewTravelers').textContent = travelers;
    document.getElementById('foreignReviewRequests').textContent = window.foreignBookingData.specialRequests || 'None';

    const price = window.currentForeignDest ? window.currentForeignDest.price : 0;
    const hotelSurcharge = window.foreignSelectedHotelSurcharge || 0;
    const rawTotal = (travelers * price) + hotelSurcharge;
    const appliedVoucher = window._appliedVoucher && window._appliedVoucher['foreign'];
    const finalAmount = appliedVoucher ? appliedVoucher.finalTotal : rawTotal;

    window.foreignBookingData.totalAmount = finalAmount;

    const reviewTotalEl = document.getElementById('foreignReviewTotal');
    if (reviewTotalEl) {
        if (appliedVoucher) {
            reviewTotalEl.innerHTML = `${window.currentForeignDestCurrency || '₱'}${formatNumber(finalAmount)} <span style="text-decoration:line-through;color:#94a3b8;font-size:0.8em;">${window.currentForeignDestCurrency || '₱'}${formatNumber(rawTotal)}</span>`;
        } else {
            reviewTotalEl.textContent = (window.currentForeignDestCurrency || '₱') + formatNumber(rawTotal);
        }
    }

    goToForeignStep(3);
    return true;
}

// Select payment method - FIXED
function selectForeignPaymentMethod(method, event) {
    foreignSelectedPayment = method;
    const targetElement = event.currentTarget;
    document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
    targetElement.classList.add('selected');
    document.querySelectorAll('input[name="foreign_payment"]').forEach(radio => radio.checked = false);
    const radio = document.getElementById(`foreign${method.charAt(0).toUpperCase() + method.slice(1)}Radio`);
    if (radio) radio.checked = true;

    // Hide all payment details boxes
    document.querySelectorAll('.payment-details-box').forEach(box => {
        box.classList.remove('show');
    });

    // Show selected payment details
    const detailsBox = document.getElementById(`foreign${method.charAt(0).toUpperCase() + method.slice(1)}Details`);
    if (detailsBox) detailsBox.classList.add('show');
}

// Handle file upload
function handleForeignFileUpload(event, paymentMethod) {
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
            const previewDiv = document.getElementById(`foreign-preview-${paymentMethod}`);
            if (previewDiv) {
                previewDiv.innerHTML = `<img src="${e.target.result}" alt="Payment Proof" style="max-width:100%; max-height:100px; border-radius:8px;">`;
            }
        };
        reader.readAsDataURL(file);
        const fileNameSpan = document.getElementById(`foreign-file-name-${paymentMethod}`);
        if (fileNameSpan) {
            fileNameSpan.textContent = file.name;
        }
    }
}

// Validate payment
function validateForeignPayment() {
    const errors = [];
    const price = window.currentForeignDest ? window.currentForeignDest.price : 0;
    const travelers = window.foreignBookingData ? window.foreignBookingData.travelers : 1;
    const totalAmount = price * travelers;

    if (!foreignSelectedPayment) errors.push('Please select a payment method');

    if (foreignSelectedPayment === 'gcash') {
        const ref = document.getElementById('foreignPaymentRefGcash')?.value.trim();
        if (!ref) errors.push('Please enter the GCash reference number');
        if (!document.getElementById('foreignProofGcash')?.files[0]) errors.push('Please upload proof of payment');
    }
    if (foreignSelectedPayment === 'paymaya') {
        const ref = document.getElementById('foreignPaymentRefPaymaya')?.value.trim();
        if (!ref) errors.push('Please enter the PayMaya reference number');
        if (!document.getElementById('foreignProofPaymaya')?.files[0]) errors.push('Please upload proof of payment');
    }
    if (foreignSelectedPayment === 'card') {
        if (!document.getElementById('foreignCardNumber')?.value.trim()) errors.push('Card Number is required');
        if (!document.getElementById('foreignExpiryDate')?.value.trim()) errors.push('Expiry Date is required');
        if (!document.getElementById('foreignCvv')?.value.trim()) errors.push('CVV is required');
        if (!document.getElementById('foreignCardName')?.value.trim()) errors.push('Cardholder Name is required');
    }
    if (foreignSelectedPayment === 'bank') {
        const ref = document.getElementById('foreignBankRef')?.value.trim();
        if (!ref) errors.push('Reference Number is required');
        if (!document.getElementById('foreignProofBank')?.files[0]) errors.push('Please upload proof of payment');
    }

    if (errors.length > 0) {
        const errorDiv = document.getElementById('foreignStep4Errors');
        errorDiv.style.display = 'block';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    let paymentMethodName = '';
    let paymentRef = '';
    if (foreignSelectedPayment === 'gcash') { paymentMethodName = 'GCash'; paymentRef = document.getElementById('foreignPaymentRefGcash')?.value.trim(); }
    else if (foreignSelectedPayment === 'paymaya') { paymentMethodName = 'PayMaya'; paymentRef = document.getElementById('foreignPaymentRefPaymaya')?.value.trim(); }
    else if (foreignSelectedPayment === 'card') { paymentMethodName = 'Credit/Debit Card'; paymentRef = 'Card ending in ' + document.getElementById('foreignCardNumber')?.value.slice(-4); }
    else if (foreignSelectedPayment === 'bank') { paymentMethodName = 'Bank Transfer'; paymentRef = document.getElementById('foreignBankRef')?.value.trim(); }

    const btn = document.querySelector('#foreignStep4Content .btn-next');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    // Apply voucher discount if one was applied
    const appliedVoucher = window._appliedVoucher && window._appliedVoucher['foreign'];
    const finalAmount = appliedVoucher ? appliedVoucher.finalTotal : totalAmount;

    const formData = new FormData();
    formData.append('destination_key', window.currentForeignDestKey || '');
    formData.append('destination_name', window.currentForeignDest?.name || window.currentForeignDest?.title || 'Foreign Destination');
    formData.append('package_duration', window.currentForeignDest?.duration || 'N/A');
    formData.append('price_per_person', price);
    formData.append('full_name', window.foreignBookingData.fullName);
    formData.append('email', window.foreignBookingData.email);
    formData.append('phone', window.foreignBookingData.phone);
    formData.append('number_of_travelers', window.foreignBookingData.travelers);
    formData.append('travel_date', window.foreignBookingData.travelDate);
    formData.append('special_requests', window.foreignBookingData.specialRequests);
    formData.append('total_amount', finalAmount);
    formData.append('payment_method', foreignSelectedPayment);
    if (paymentRef) formData.append('payment_reference', paymentRef);
    formData.append('currency', window.currentForeignDestCurrency || '$');
    if (window.currentForeignDest?.partner_id) {
        formData.append('partner_id', window.currentForeignDest.partner_id);
        if (window.currentForeignDest.partner_company) formData.append('partner_company', window.currentForeignDest.partner_company);
        formData.append('partner_source', window.currentForeignDest?.partner_source ?? 'foreign_destination');
        formData.append('partner_package_name', window.currentForeignDest?.name ?? '');
    }
    if (appliedVoucher) {
        formData.append('voucher_id', appliedVoucher.id);
        formData.append('voucher_discount', appliedVoucher.discountAmount);
    }

    const proofInput = document.getElementById(`foreignProof${foreignSelectedPayment.charAt(0).toUpperCase()}${foreignSelectedPayment.slice(1)}`);
    if (proofInput && proofInput.files[0]) {
        formData.append('payment_proof', proofInput.files[0]);
    }

    fetch('api/save-foreign-booking.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if (data.success) {
                document.getElementById('foreignBookingNumber').innerHTML = 'Booking: ' + data.booking_number;
                document.getElementById('foreignConfirmDate').textContent = new Date(window.foreignBookingData.travelDate).toLocaleDateString();
                document.getElementById('foreignConfirmTravelers').textContent = window.foreignBookingData.travelers;
                const currency = window.currentForeignDestCurrency || '₱';
                document.getElementById('foreignConfirmTotal').innerHTML = currency + formatNumber(totalAmount);
                document.getElementById('foreignConfirmPayment').textContent = paymentMethodName;
                document.getElementById('foreignConfirmName').textContent = window.foreignBookingData.fullName;

                goToForeignStep(5);
            } else {
                const errorDiv = document.getElementById('foreignStep4Errors');
                if (errorDiv) {
                    errorDiv.style.display = 'block';
                    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${data.message}`;
                    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    alert('Error: ' + data.message);
                }
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Connection error. Please try again.');
        });

    return true;
}

// Close foreign modal
function closeForeignPackageModal() {
    const modal = document.getElementById('foreignPackageModal');
    if (modal) modal.classList.remove('active');

    // Clear slider interval
    if (window.foreignSliderInterval) {
        clearInterval(window.foreignSliderInterval);
        window.foreignSliderInterval = null;
    }

    foreignSelectedPayment = null;
    foreignBookingData = null;
}

// Add modal styles
function addForeignModalStyles() {
    if (!document.querySelector('#foreignModalStyles')) {
        const style = document.createElement('style');
        style.id = 'foreignModalStyles';
        style.textContent = `
            .package-modal {
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
            .package-modal.active {
                display: flex;
            }
            .package-modal-content {
                background: white;
                border-radius: 24px;
                max-width: 900px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                animation: modalSlideIn 0.3s ease;
            }
            .package-modal-content::-webkit-scrollbar {
                width: 8px;
            }
            .package-modal-content::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }
            .package-modal-content::-webkit-scrollbar-thumb {
                background: #003580;
                border-radius: 10px;
            }
            .package-modal-header {
                background: linear-gradient(135deg, #003580, #1a4b8c);
                color: white;
                padding: 20px 25px;
                border-radius: 24px 24px 0 0;
                position: relative;
            }
            .close-modal {
                position: absolute;
                top: 15px;
                right: 20px;
                font-size: 1.8rem;
                cursor: pointer;
                transition: all 0.3s ease;
                color: white;
            }
            .close-modal:hover {
                transform: rotate(90deg);
                color: #ff9800;
            }
            .package-modal-header h2 {
                font-size: 1.5rem;
                margin-bottom: 8px;
                color: #ffffff;
                text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            }

            /* --- PHOTO SLIDER STYLES --- */
            .flash-slider-container {
                position: relative;
                width: 100%;
                height: 100%;
                overflow: hidden;
            }
            .flash-slide {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-size: cover;
                background-position: center;
                opacity: 0;
                transition: opacity 0.8s ease;
                z-index: 1;
            }
            .flash-slide.active {
                opacity: 1;
                z-index: 2;
            }
            .flash-slider-dots {
                position: absolute;
                bottom: 60px;
                right: 20px;
                display: flex;
                gap: 8px;
                z-index: 10;
            }
            .flash-slide-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: rgba(255,255,255,0.5);
                cursor: pointer;
                transition: all 0.3s ease;
                border: 1px solid rgba(0,0,0,0.1);
            }
            .flash-slide-dot.active {
                background: #ff9800;
                transform: scale(1.3);
                box-shadow: 0 0 10px rgba(255,152,0,0.5);
            }

            .close-modal-circle:hover {
                background: rgba(255,152,0,0.8) !important;
                transform: scale(1.1) rotate(90deg);
            }

            .package-modal-body {
                padding: 20px;
            }
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
                margin-bottom: 20px;
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
            
            /* Payment Methods - IMPROVED */
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
            
            .qr-code {
                text-align: center;
                margin: 15px 0;
                padding: 15px;
                background: white;
                border-radius: 12px;
                border: 1px solid #e0e0e0;
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
            
            .file-upload {
                border: 2px dashed #e0e0e0;
                border-radius: 12px;
                padding: 15px;
                text-align: center;
                cursor: pointer;
                transition: all 0.3s ease;
                background: #f8f9fa;
                margin-top: 15px;
            }
            
            .file-upload:hover {
                border-color: #ff9800;
                background: #fff9e6;
            }
            
            .file-upload i {
                font-size: 1.5rem;
                color: #ff9800;
                margin-bottom: 5px;
            }
            
            .file-upload p {
                font-size: 0.7rem;
                color: #666;
                margin: 0;
            }
            
            .file-upload .file-name {
                font-size: 0.65rem;
                color: #003580;
                margin-top: 5px;
                font-weight: 500;
            }
            
            .upload-preview {
                margin-top: 10px;
                text-align: center;
            }
            
            .upload-preview img {
                max-width: 100%;
                max-height: 100px;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
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
            
            @media (max-width: 768px) {
                .package-details-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                .booking-form-modal .form-row {
                    grid-template-columns: 1fr;
                    gap: 12px;
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
        `;
        document.head.appendChild(style);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    addForeignModalStyles();
    console.log('Foreign packages initialized (Database connected)!');
});