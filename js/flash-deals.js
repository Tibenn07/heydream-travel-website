// ========================================
// FILE: js/flash-deals.js
// DESCRIPTION: Flash Deals Popup Functionality with Payment Methods
// Loads from database via API
// ========================================

// Cache for deal data from database
let flashDealDataCache = {};

// Format number with commas
// Hotel Selection State
window.flashSelectedHotelSurcharge = 0;
window.toggleFlashHotelSelection = function () {
    const dropdown = document.getElementById('flashHotelDropdown');
    if (dropdown) dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
};
window.selectFlashHotel = function (index, name, stars, price, skipPersist) {
    const basePrice = window.currentFlashDeal ? window.currentFlashDeal.price : 0;
    const nameEl = document.getElementById('flashSelectedHotelName');
    const starHtml = stars ? ` <span style="font-size:0.8rem;">${'⭐'.repeat(stars)}</span>` : '';
    if (nameEl) nameEl.innerHTML = name + starHtml + ' <i class="fas fa-chevron-down" style="font-size:0.7rem; margin-left:5px;"></i>';
    window.flashSelectedHotelSurcharge = price;
    if (typeof updateFlashStepTotal === 'function') updateFlashStepTotal(basePrice);
    const dropdown = document.getElementById('flashHotelDropdown');
    if (dropdown) dropdown.style.display = 'none';
    document.querySelectorAll('#flashHotelDropdown .hotel-option').forEach((el) => {
        el.style.background = Number(el.dataset.hotelIndex) === index ? '#fff3e0' : 'white';
    });
    // Persist the pick so it survives the modal being torn down and
    // rebuilt from scratch -- which happens when login interrupts the
    // booking flow (resumeFlashBooking rebuilds the whole modal, which
    // otherwise silently resets the hotel surcharge back to 0).
    if (!skipPersist && window.currentFlashDealId) {
        try {
            sessionStorage.setItem('flash_hotel_' + window.currentFlashDealId, JSON.stringify({ index, name, stars, price }));
        } catch (e) { /* storage unavailable, skip */ }
    }
};

function formatNumberFlash(num) {
    if (!num) return '0';
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Escape HTML to prevent XSS
function escapeHtmlFlash(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Formats a date range for Travel Validity
 */
function formatValidityDateFlash(start, end, fallback) {
    if (!start && !end) return fallback || 'Year Round';

    const options = { day: '2-digit', month: 'long', year: 'numeric' };

    try {
        if (start && end) {
            const startDate = new Date(start).toLocaleDateString('en-GB', options);
            const endDate = new Date(end).toLocaleDateString('en-GB', options);
            // Image mix: '07 April 2026 - 31 Aug 2026'. 
            // We'll use 'long' for month to match the 'April' part of the image.
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

// Load deal data from database API
async function loadFlashDealFromDatabase(dealId) {
    // Check cache first
    if (flashDealDataCache[dealId]) {
        return flashDealDataCache[dealId];
    }

    try {
        const response = await fetch(`api/get-flash-deals.php?id=${encodeURIComponent(dealId)}`);
        const data = await response.json();

        if (data.success && data.deal) {
            const deal = data.deal;
            flashDealDataCache[dealId] = deal;
            return deal;
        }
        return null;
    } catch (error) {
        console.error('Error loading flash deal from database:', error);
        return null;
    }
}

window.switchFlashTab = function (event, tabId) {
    const tabs = event.target.parentElement.querySelectorAll('.flash-tab');
    tabs.forEach(t => {
        t.classList.remove('active');
        t.style.color = '#666';
        t.style.borderBottomColor = 'transparent';
    });
    event.target.classList.add('active');
    event.target.style.color = '#003580';
    event.target.style.borderBottomColor = '#ff9800';

    const modalBody = event.target.closest('.flash-deal-modal-body');
    modalBody.querySelectorAll('.flash-pane').forEach(p => p.style.display = 'none');
    modalBody.querySelector('#flash-pane-' + tabId).style.display = 'block';
};

// "View Deal"/"View Tour Details" now navigates to the full package-details.php page.
// The original modal-building logic is kept as showFlashDealPopupModal —
// used by package-details.php's "Book This Deal" button (via resumeFlashBooking)
// to open the booking flow directly, without rebuilding it.
window.showFlashDealPopup = function (dealId) {
    window.location.href = `package-details.php?type=flash&id=${encodeURIComponent(dealId)}`;
};

// Global function to show flash deal popup
window.showFlashDealPopupModal = async function (dealId) {
    console.log('Showing flash deal for ID:', dealId);

    // Create modal if it doesn't exist
    let modal = document.getElementById('flashDealModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'flashDealModal';
        modal.className = 'flash-deal-modal';
        modal.innerHTML = `
            <div class="flash-deal-modal-content" style="position:relative;">
                <div class="close-modal-circle" onclick="closeFlashDealModal()" style="position:absolute; top:15px; right:15px; width:35px; height:35px; background:rgba(0,0,0,0.5); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.4rem; cursor:pointer; z-index:2000; backdrop-filter:blur(4px); transition:all 0.3s; border: 1px solid rgba(255,255,255,0.2);">&times;</div>
                <div class="flash-deal-modal-header" style="display:none;">
                    <span class="close-modal" onclick="closeFlashDealModal()">&times;</span>
                    <h2 id="flashModalDealTitle">Loading...</h2>
                    <p id="flashModalDealLocation"><i class="fas fa-map-marker-alt"></i> Loading...</p>
                </div>
                <div class="flash-deal-modal-body" id="flashDealModalBody">
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
                        <p>Loading deal details...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeFlashDealModal();
            }
        });
    } else {
        // Show loading state
        const modalBody = document.getElementById('flashDealModalBody');
        if (modalBody) {
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
                    <p>Loading deal details...</p>
                </div>
            `;
        }
    }

    modal.classList.add('active');

    // Load deal data from database
    const deal = await loadFlashDealFromDatabase(dealId);

    if (!deal) {
        const modalBody = document.getElementById('flashDealModalBody');
        if (modalBody) {
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 2rem; color: #dc3545;"></i>
                    <p>Deal details coming soon!</p>
                    <button class="book-now-btn" onclick="closeFlashDealModal()" style="width: auto; margin-top: 20px;">Close</button>
                </div>
            `;
        }
        return;
    }

    // Update modal header
    document.getElementById('flashModalDealTitle').textContent = deal.title;
    document.getElementById('flashModalDealLocation').innerHTML = `<i class="fas fa-map-marker-alt"></i> ${deal.location || 'Various Locations'}`;
    const header = document.querySelector('.flash-deal-modal-header');
    if (header) header.style.display = 'none';

    const modalBody = document.getElementById('flashDealModalBody');
    window.currentFlashDealCurrency = deal.currency || '₱';
    window.flashSelectedHotelSurcharge = 0; // Initialize surcharge

    // Build itinerary HTML
    let itineraryHtml = `<div class="itinerary-section"><h3><i class="fas fa-list-ol"></i> Tour Itinerary</h3>`;

    if (deal.itinerary && deal.itinerary.length > 0) {
        deal.itinerary.forEach(day => {
            const dayTitle = day.title || `Day ${day.day}`;
            itineraryHtml += `
                <div class="itinerary-day">
                    <h4>
                        <span class="day-badge" style="background: #ff9800; color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; margin-right: 8px; display: inline-block;">
                            ${escapeHtmlFlash(dayTitle.split(':')[0])}
                        </span>
                        ${escapeHtmlFlash(dayTitle.split(':')[1] || dayTitle)}
                    </h4>
                    <ul>
            `;
            if (day.activities && day.activities.length > 0) {
                day.activities.forEach(activity => itineraryHtml += `<li>${escapeHtmlFlash(activity)}</li>`);
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
    if (deal.remarks && deal.remarks.trim().length > 0) {
        remarksHtml = `
            <div class="remarks-section" style="margin-top: 20px; margin-bottom: 20px; padding: 15px; background: #fffde7; border-left: 4px solid #fbc02d; border-radius: 8px;">
                <h3 style="margin-top: 0; margin-bottom: 10px; color: #f57f17; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-info-circle"></i> Remarks and Note
                </h3>
                <div style="white-space: pre-wrap; color: #5d4037; font-size: 0.95rem; line-height: 1.5;">${escapeHtmlFlash(deal.remarks)}</div>
            </div>
        `;
    }


    // Build inclusions HTML
    let inclusionsHtml = `<div class="inclusions-section"><h3><i class="fas fa-check-circle"></i> Package Inclusions</h3><ul class="inclusions-list">`;
    if (deal.inclusions && deal.inclusions.length > 0) {
        deal.inclusions.forEach(item => inclusionsHtml += `<li>${escapeHtmlFlash(item)}</li>`);
    } else {
        inclusionsHtml += `<li>Details will be provided upon booking</li>`;
    }
    inclusionsHtml += `</ul></div>`;

    // Build exclusions HTML
    let exclusionsHtml = '';
    if (deal.exclusions && deal.exclusions.length > 0) {
        exclusionsHtml = `<div class="exclusions-section"><h3><i class="fas fa-times-circle"></i> Package Exclusions</h3><ul class="exclusions-list">`;
        deal.exclusions.forEach(item => exclusionsHtml += `<li>${escapeHtmlFlash(item)}</li>`);
        exclusionsHtml += `</ul></div>`;
    }

    const originalPriceHtml = deal.original_price > 0 ? `<span class="original-price">${deal.currency || '₱'}${formatNumberFlash(deal.original_price)}</span>` : '';
    const discountBadge = deal.discount_percent ? `<div style="margin-top: 5px; font-size: 0.7rem; color: #dc3545;">⚡ Save ${deal.discount_percent}%</div>` : '';

    // Store current deal for booking
    window.currentFlashDeal = deal;
    window.currentFlashDealId = dealId;

    let html = `
        <div id="flashDetailsView">
            <div style="position:relative; height:250px; margin:-20px -20px 20px -20px; border-radius:24px 24px 0 0; overflow:hidden; background:#f0f0f0;">
                ${(() => {
            const images = [];
            if (deal.image_path) images.push(deal.image_path);
            if (deal.image2_path) images.push(deal.image2_path);
            if (deal.image3_path) images.push(deal.image3_path);

            if (images.length > 1) {
                return `
                            <div class="flash-slider-container" style="position:relative; width:100%; height:100%; overflow:hidden;">
                                ${images.map((img, i) => `
                                    <div class="flash-slide ${i === 0 ? 'active' : ''}" style="background-image:url('${escapeHtmlFlash(img)}');"></div>
                                `).join('')}
                                
                                <div class="flash-slider-dots">
                                    ${images.map((_, i) => `
                                        <div class="flash-slide-dot ${i === 0 ? 'active' : ''}" data-index="${i}"></div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
            } else {
                return `<img src="${escapeHtmlFlash(images[0] || '../images/placeholder-deal.jpg')}" alt="${escapeHtmlFlash(deal.title)}" style="width:100%; height:100%; object-fit:cover;">`;
            }
        })()}
                <div style="position:absolute; bottom:0; left:0; right:0; padding:40px 20px 15px; background:linear-gradient(to top, rgba(0,0,0,0.9), transparent); color:white; z-index: 10;">
                    <h2 style="margin:0; font-size:1.6rem; text-shadow:0 2px 10px rgba(0,0,0,0.9), 0 1px 4px rgba(0,0,0,0.7); color: #ffffff !important; font-weight: 800;">${escapeHtmlFlash(deal.title)}</h2>
                    <p style="margin:5px 0 0; font-size:0.85rem; text-shadow:0 1px 4px rgba(0,0,0,0.7); color: #ffffff !important; font-weight: 600;"><i class="fas fa-map-marker-alt" style="color:#ff9800;"></i> ${escapeHtmlFlash(deal.location || 'Various Locations')} | <i class="fas fa-clock" style="color:#ff9800;"></i> ${escapeHtmlFlash(deal.duration || '3D/2N')}</p>
                </div>
            </div>
            
            <div style="display:flex; overflow-x:auto; border-bottom:1px solid #ddd; margin-bottom:20px;">
                <div class="flash-tab active" onclick="switchFlashTab(event, 'info')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#003580; border-bottom:3px solid #ff9800; white-space:nowrap;">Overview</div>
                <div class="flash-tab" onclick="switchFlashTab(event, 'itinerary')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Itinerary</div>
                <div class="flash-tab" onclick="switchFlashTab(event, 'inclusions')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Inclusions</div>
                ${deal.partner_id ? `<div class="flash-tab" onclick="switchFlashTab(event, 'partner')" style="padding:10px 15px; cursor:pointer; font-weight:600; color:#666; border-bottom:3px solid transparent; white-space:nowrap;">Partner Profile</div>` : ''}
            </div>

            <div id="flash-pane-info" class="flash-pane" style="display:block;">
                <div class="package-details-grid" style="margin-bottom:15px;">
                    <div class="detail-item"><i class="fas fa-clock"></i><div class="detail-label">TRAVEL VALIDITY</div><div class="detail-value">${escapeHtmlFlash(formatValidityDateFlash(deal.promo_start, deal.promo_end, deal.best_season))}</div></div>
                    <div class="detail-item"><i class="fas fa-users"></i><div class="detail-label">GROUP SIZE</div><div class="detail-value">${escapeHtmlFlash(deal.group_size || '2-15 pax')}</div></div>
                    <div class="detail-item"><i class="fas fa-calendar-alt"></i><div class="detail-label">DURATION</div><div class="detail-value">${escapeHtmlFlash(deal.duration || '3D/2N')}</div></div>
                </div>
            </div>

            <div id="flash-pane-itinerary" class="flash-pane" style="display:none;">
                ${itineraryHtml}
                ${remarksHtml}
            </div>
            
            <div id="flash-pane-inclusions" class="flash-pane" style="display:none;">
                ${inclusionsHtml}
                ${exclusionsHtml}
            </div>
            ${deal.partner_id ? `
            <div id="flash-pane-partner" class="flash-pane" style="display:none;">
                <div style="text-align: center; padding: 30px 15px;">
                    <i class="fas fa-handshake" style="font-size: 3rem; color: #ff9800; margin-bottom: 15px;"></i>
                    <h3 style="color: #003580; margin-bottom: 15px; font-size: 1.4rem;">${escapeHtmlFlash(deal.partner_company || 'Partner Provider')}</h3>
                    <p style="color: #666; margin-bottom: 25px; line-height: 1.6;">This package is exclusively provided by one of our trusted partners. View their full profile to learn more about them and discover other amazing packages they offer.</p>
                    <a href="view-partner-profile.php?id=${deal.partner_id}" style="display: inline-block; background: #003580; color: white; padding: 12px 25px; border-radius: 25px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease;">
                        View Partner Profile <i class="fas fa-external-link-alt" style="margin-left: 8px;"></i>
                    </a>
                </div>
            </div>` : ''}

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px; padding-top:15px; border-top:1px solid #eee;">
                <div>
                    <span style="font-size:0.8rem; color:#666;">Price starting from</span><br>
                    <span style="font-size:1.4rem; font-weight:800; color:#ff9800;">${deal.currency || '₱'}${formatNumberFlash(deal.price)}</span>
                </div>
                <button onclick="document.getElementById('flashDetailsView').style.display='none'; document.getElementById('flashBookingView').style.display='block';" style="background:linear-gradient(135deg, #ff9800, #f57c00); color:white; border:none; padding:10px 25px; border-radius:30px; font-weight:bold; cursor:pointer;">
                    Book This Deal <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <div id="flashBookingView" style="display:none;">
            <div class="deal-price-card">
                <div class="price">${deal.currency || '₱'}${formatNumberFlash(deal.price)} ${originalPriceHtml}</div>
                <small>/ person</small>
                ${discountBadge}
                <div style="margin-top: 8px;">${escapeHtmlFlash(deal.duration || '3D/2N')} tour package</div>
            </div>
        
        <!-- Booking Steps -->
        <div class="booking-steps">
            <div class="step active" id="flashStep1">
                <div class="step-number">1</div>
                <div class="step-label">Date</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="flashStep2">
                <div class="step-number">2</div>
                <div class="step-label">Info</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="flashStep3">
                <div class="step-number">3</div>
                <div class="step-label">Review</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="flashStep4">
                <div class="step-number">4</div>
                <div class="step-label">Payment</div>
                <div class="step-line"></div>
            </div>
            <div class="step" id="flashStep5">
                <div class="step-number">5</div>
                <div class="step-label">Confirm</div>
            </div>
        </div>
        
        <!-- Step 1: Travel Date -->
        <div id="flashStep1Content" class="step-content active">
            <div class="booking-form-modal">
                <h3><i class="fas fa-calendar-alt"></i> Select Travel Date</h3>
                <p style="color: #666; margin-bottom: 20px;">Please pick your preferred travel date to get started.</p>
                
                <div class="form-group">
                    <label>Travel Date *</label>
                    <input type="text" id="flashStepDate" placeholder="Select your travel date" readonly style="cursor:pointer; background:#fff;">
                </div>
                <div id="flashDateRangeInfo" style="display:none; margin-top:8px; padding:10px 14px; background:linear-gradient(135deg,#e3f2fd,#e8f5e9); border-left:4px solid #2196F3; border-radius:6px; font-size:0.9em; color:#0d47a1; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <i class="fas fa-info-circle"></i> <span id="flashDateRangeText"></span>
                            <div id="flashPromoEndingWarning" style="display:none; margin-top:5px; color:#b71c1c; font-weight:bold; font-size:0.85em;">
                                <i class="fas fa-exclamation-triangle"></i> <span id="flashPromoEndingWarningText"></span>
                            </div>
                            <div id="flashBlockedDateConflict" style="display:none; margin-top:5px; color:#b71c1c; font-weight:bold; font-size:0.85em;">
                                <i class="fas fa-ban"></i> <span id="flashBlockedDateConflictText"></span>
                            </div>
                        </div>
                        <button type="button" id="flashClearDateBtn" onclick="clearFlashDate()" style="background: rgba(0,0,0,0.05); border: none; border-radius: 4px; padding: 2px 6px; cursor: pointer; color: #0d47a1; font-size: 0.8rem; font-weight: 600; margin-left: 10px; white-space: nowrap;" title="Clear Selection">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>

                ${deal.hotels && deal.hotels.length > 0 ? `
                <div class="form-group" style="margin-top:20px;">
                    <label>Hotel <span style="font-weight:400; color:#94a3b8;">(optional)</span></label>
                    <div class="hotel-selection-item" onclick="toggleFlashHotelSelection()" style="cursor:pointer; border:1px solid #ddd; border-radius:8px; padding:12px 15px; display:flex; align-items:center; justify-content:space-between;">
                        <span><i class="fas fa-hotel" style="color:#ff9800; margin-right:8px;"></i><span id="flashSelectedHotelName" style="font-weight:600;">No hotel selected</span></span>
                        <i class="fas fa-chevron-down" style="font-size:0.8rem; color:#666;"></i>
                    </div>
                    <div id="flashHotelDropdown" class="hotel-dropdown" style="display:none; margin-top:8px; background: white; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <div class="hotel-option active" data-hotel-index="-1" onclick="selectFlashHotel(-1, 'No hotel selected', 0, 0)" style="padding: 12px 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s; background:#fff3e0;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-weight:500; color:#64748b;">No hotel <span style="font-size:0.8rem;">(I'll arrange my own)</span></span>
                            </div>
                        </div>
                        ${deal.hotels.map((h, i) => `
                            <div class="hotel-option" data-hotel-index="${i}" onclick="selectFlashHotel(${i}, '${escapeHtmlFlash(h.name).replace(/'/g, "\\'")}', ${h.stars || 0}, ${h.price})" style="padding: 12px 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-weight:500;">${escapeHtmlFlash(h.name)} <span style="font-size:0.8rem;">${h.stars ? '⭐'.repeat(h.stars) : ''}</span></span>
                                    <span style="color:#4caf50; font-size:0.9rem;">${h.price > 0 ? `+${deal.currency || '₱'}${formatNumberFlash(h.price)}` : 'Included'}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}

                <div id="flashStep1Errors" class="error-message" style="display: none;"></div>
                
                <div class="action-buttons">
                    <button type="button" class="btn-next" onclick="validateFlashStep1()">Next: Passenger Info <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>

        <!-- Step 2: Passenger Info -->
        <div id="flashStep2Content" class="step-content">
            <div class="booking-form-modal">
                <h3><i class="fas fa-user"></i> Passenger Details</h3>
                <div class="booking-summary">
                    <h4>Booking Summary</h4>
                    <div class="summary-item"><span>Deal:</span><span>${escapeHtmlFlash(deal.title)}</span></div>
                    <div class="summary-item"><span>Duration:</span><span>${escapeHtmlFlash(deal.duration || '3D/2N')}</span></div>
                    <div class="summary-item"><span>Price per Person:</span><span>${deal.currency || '₱'}${formatNumberFlash(deal.price)}</span></div>
                    <div class="summary-item"><span>Travelers:</span><span id="flashStepSummaryTravelers">1</span></div>
                    <div class="summary-item discounted" id="flashStepSummaryDiscountedRow" style="display:none;"><span>Discounted:</span><span id="flashStepSummaryDiscounted">₱0</span></div>
                    <div class="summary-item total"><span>Total:</span><span id="flashStepSummaryTotal">${deal.currency || '₱'}${formatNumberFlash(deal.price)}</span></div>
                </div>

                <!-- Voucher Section (Step 2) -->
                <div id="flashStep2VoucherArea" style="margin-bottom:18px;"></div>

                <div class="form-row">
                    <div class="form-group"><label>Full Name *</label><input type="text" id="flashStepFullName" autocomplete="name" placeholder="Enter your full name" value="${window.currentFullName || ''}"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Phone *</label><input type="tel" id="flashStepPhone" autocomplete="tel" placeholder="+63 912 345 6789"></div>
                    <div class="form-group"><label>Travelers *</label><input type="number" id="flashStepTravelers" min="1" value="1" onchange="updateFlashStepTotal(${deal.price})"></div>
                </div>
                <div id="flashStep2VoucherArea" style="margin-bottom:18px;"></div>
                <div class="form-group"><label>Special Requests</label><textarea id="flashStepRequests" rows="3" placeholder="Any special requirements, dietary restrictions, etc."></textarea></div>
                <div id="flashStep2Errors" class="error-message" style="display: none;"></div>
                <div class="action-buttons">
                    <button type="button" class="btn-prev" onclick="goToFlashStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="button" class="btn-next" onclick="validateFlashStep2()">Review Booking <i class="fas fa-arrow-right"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Step 3: Review -->
        <div id="flashStep3Content" class="step-content">
            <div class="review-details">
                <div class="review-section"><h4>Passenger Information</h4>
                    <div class="review-row"><div class="review-label">Full Name:</div><div class="review-value" id="flashReviewName">-</div></div>
                    <div class="review-row"><div class="review-label">Email:</div><div class="review-value" id="flashReviewEmail">-</div></div>
                    <div class="review-row"><div class="review-label">Phone:</div><div class="review-value" id="flashReviewPhone">-</div></div>
                </div>
                <div class="review-section"><h4>Travel Details</h4>
                    <div class="review-row"><div class="review-label">Deal:</div><div class="review-value">${escapeHtmlFlash(deal.title)}</div></div>
                    <div class="review-row"><div class="review-label">Duration:</div><div class="review-value">${escapeHtmlFlash(deal.duration || '3D/2N')}</div></div>
                    <div class="review-row"><div class="review-label">Travel Date:</div><div class="review-value" id="flashReviewDate">-</div></div>
                    <div class="review-row"><div class="review-label">Travelers:</div><div class="review-value" id="flashReviewTravelers">-</div></div>
                    <div class="review-row"><div class="review-label">Special Requests:</div><div class="review-value" id="flashReviewRequests">-</div></div>
                </div>
                <div class="review-section"><h4>Price Summary</h4>
                    <div class="review-row"><div class="review-label">Price per Person:</div><div class="review-value">${deal.currency || '₱'}${formatNumberFlash(deal.price)}</div></div>
                    <div class="review-row total"><div class="review-label">Total:</div><div class="review-value" id="flashReviewTotal">${deal.currency || '₱'}${formatNumberFlash(deal.price)}</div></div>
                </div>
            </div>
            <div class="action-buttons">
                <button type="button" class="btn-prev" onclick="goToFlashStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                <button type="button" class="btn-next" onclick="goToFlashStep(4)">Proceed to Payment <i class="fas fa-credit-card"></i></button>
            </div>
        </div>
        
        <!-- Step 4: Payment Methods - IMPROVED DESIGN -->
        <div id="flashStep4Content" class="step-content">
            <div class="booking-form-modal">
                <h3><i class="fas fa-credit-card"></i> Select Payment Method</h3>
                <div class="payment-methods">
                    <div class="payment-method" onclick="selectFlashPaymentMethod('gcash', event)">
                        <input type="radio" name="flash_payment" value="gcash" id="flashGcashRadio">
                        <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">GCash</div>
                            <div class="payment-desc">Scan QR code to pay</div>
                        </div>
                    </div>
                    <div class="payment-method" onclick="selectFlashPaymentMethod('paymaya', event)">
                        <input type="radio" name="flash_payment" value="paymaya" id="flashPaymayaRadio">
                        <div class="payment-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">PayMaya</div>
                            <div class="payment-desc">Scan QR code to pay</div>
                        </div>
                    </div>
                    <div class="payment-method disabled" onclick="alert('Credit/Debit Card payment is coming soon! Please use other payment methods for now.')" style="opacity: 0.6; cursor: not-allowed; position: relative;">
                        <input type="radio" name="flash_payment" value="card" id="flashCardRadio" disabled>
                        <div class="payment-icon"><i class="fas fa-credit-card"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">Credit / Debit Card <span style="color: #ef4444; font-size: 0.65rem; font-weight: 800; margin-left: 5px;">(NOT AVAILABLE)</span></div>
                            <div class="payment-desc">Coming Soon</div>
                        </div>
                    </div>
                    <div class="payment-method" onclick="selectFlashPaymentMethod('bank', event)">
                        <input type="radio" name="flash_payment" value="bank" id="flashBankRadio">
                        <div class="payment-icon"><i class="fas fa-university"></i></div>
                        <div class="payment-info">
                            <div class="payment-name">Bank Transfer</div>
                            <div class="payment-desc">BPI, BDO, Metrobank</div>
                        </div>
                    </div>
                </div>
                
                <div id="flashGcashDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>GCash Payment</h4></div>
                        <div class="qr-code"><div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>GCash QR Code</p><p>0945 776 4140</p></div></div>
                        <div class="account-details">
                            <p><strong>GCash Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboardFlash('0945 776 4140')">Copy</button></p>
                            <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                            <p><strong>Amount:</strong> <span style="color:#ff9800;">${deal.currency || '₱'}<span id="flashGcashAmount">${formatNumberFlash(deal.price)}</span></span></p>
                        </div>
                        <div class="form-group"><label>Reference Number *</label><input type="text" id="flashPaymentRefGcash" placeholder="Enter GCash reference number"></div>
                        <div class="file-upload" onclick="document.getElementById('flashProofGcash').click()">
                            <i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p>
                            <p class="file-name" id="flash-file-name-gcash">No file selected</p>
                            <div id="flash-preview-gcash" class="upload-preview"></div>
                            <input type="file" id="flashProofGcash" accept="image/*" style="display:none" onchange="handleFlashFileUpload(event, 'gcash')">
                        </div>
                        <div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of payment confirmation</div>
                    </div>
                </div>
                
                <div id="flashPaymayaDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-mobile-alt"></i><h4>PayMaya Payment</h4></div>
                        <div class="qr-code"><div class="qr-placeholder"><i class="fas fa-qrcode"></i><p>PayMaya QR Code</p><p>0945 776 4140</p></div></div>
                        <div class="account-details">
                            <p><strong>PayMaya Number:</strong> <span class="account-number">0945 776 4140</span> <button class="copy-btn" onclick="copyToClipboardFlash('0945 776 4140')">Copy</button></p>
                            <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                            <p><strong>Amount:</strong> <span style="color:#ff9800;">${deal.currency || '₱'}<span id="flashPaymayaAmount">${formatNumberFlash(deal.price)}</span></span></p>
                        </div>
                        <div class="form-group"><label>Reference Number *</label><input type="text" id="flashPaymentRefPaymaya" placeholder="Enter PayMaya reference number"></div>
                        <div class="file-upload" onclick="document.getElementById('flashProofPaymaya').click()">
                            <i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p>
                            <p class="file-name" id="flash-file-name-paymaya">No file selected</p>
                            <div id="flash-preview-paymaya" class="upload-preview"></div>
                            <input type="file" id="flashProofPaymaya" accept="image/*" style="display:none" onchange="handleFlashFileUpload(event, 'paymaya')">
                        </div>
                        <div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of payment confirmation</div>
                    </div>
                </div>
                
                <div id="flashCardDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-credit-card"></i><h4>Card Payment</h4></div>
                        <div class="form-group"><label>Card Number *</label><input type="text" id="flashCardNumber" placeholder="1234 5678 9012 3456"></div>
                        <div class="card-row">
                            <div class="form-group"><label>Expiry *</label><input type="text" id="flashExpiryDate" placeholder="MM/YY"></div>
                            <div class="form-group"><label>CVV *</label><input type="text" id="flashCvv" placeholder="123"></div>
                        </div>
                        <div class="form-group"><label>Cardholder Name *</label><input type="text" id="flashCardName" placeholder="Name on card"></div>
                    </div>
                </div>
                
                <div id="flashBankDetails" class="payment-details-box">
                    <div class="payment-instructions">
                        <div class="instruction-header"><i class="fas fa-university"></i><h4>Bank Transfer</h4></div>
                        <div class="account-details">
                            <p><strong>BPI:</strong> 1234 5678 90 <button class="copy-btn" onclick="copyToClipboardFlash('1234 5678 90')">Copy</button></p>
                            <p><strong>BDO:</strong> 5678 1234 56 <button class="copy-btn" onclick="copyToClipboardFlash('5678 1234 56')">Copy</button></p>
                            <p><strong>Metrobank:</strong> 9012 3456 78 <button class="copy-btn" onclick="copyToClipboardFlash('9012 3456 78')">Copy</button></p>
                            <p><strong>Account Name:</strong> HeyDream Travel & Tours</p>
                            <p><strong>Amount:</strong> <span style="color:#ff9800;">${deal.currency || '₱'}<span id="flashBankAmount">${formatNumberFlash(deal.price)}</span></span></p>
                        </div>
                        <div class="form-group"><label>Reference Number *</label><input type="text" id="flashBankRef" placeholder="Enter bank reference number"></div>
                        <div class="file-upload" onclick="document.getElementById('flashProofBank').click()">
                            <i class="fas fa-cloud-upload-alt"></i><p>Upload proof of payment</p>
                            <p class="file-name" id="flash-file-name-bank">No file selected</p>
                            <div id="flash-preview-bank" class="upload-preview"></div>
                            <input type="file" id="flashProofBank" accept="image/*" style="display:none" onchange="handleFlashFileUpload(event, 'bank')">
                        </div>
                        <div class="instruction-note"><i class="fas fa-info-circle"></i> Upload screenshot of bank transfer confirmation</div>
                    </div>
                </div>
                
                <div id="flashStep4Errors" class="error-message" style="display: none;"></div>
                <div class="action-buttons">
                    <button type="button" class="btn-prev" onclick="goToFlashStep(3)"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="button" class="btn-next" onclick="validateFlashPayment()">Complete Payment <i class="fas fa-check-circle"></i></button>
                </div>
            </div>
        </div>
        
        <!-- Step 5: Confirmation -->
        <div id="flashStep5Content" class="step-content">
            <div class="success-message">
                <i class="fas fa-clock"></i>
                <h2>⏳ Booking Received!</h2>
                <p>Your flash deal booking has been submitted and is pending payment verification.</p>
                <div class="booking-number" id="flashBookingNumber">Booking: Processing...</div>
                <div class="details-card">
                    <h4>📋 Booking Details:</h4>
                    <p><strong>⚡ Flash Deal:</strong> ${escapeHtmlFlash(deal.title)}</p>
                    <p><strong>📅 Duration:</strong> ${escapeHtmlFlash(deal.duration || '3D/2N')}</p>
                    <p><strong>📅 Travel Date:</strong> <span id="flashConfirmDate">-</span></p>
                    <p><strong>👥 Travelers:</strong> <span id="flashConfirmTravelers">-</span></p>
                    <p><strong>💰 Total Amount:</strong> <span style="color:#ff9800;" id="flashConfirmTotal">${deal.currency || '₱'}${formatNumberFlash(deal.price)}</span></p>
                    <p><strong>💳 Payment Method:</strong> <span id="flashConfirmPayment">-</span></p>
                    <p><strong>💵 Payment Status:</strong> <span style="color:#ff9800;">Pending Verification</span></p>
                    <p><strong>👤 Booked By:</strong> <span id="flashConfirmName">-</span></p>
                </div>
                <div class="payment-status-pending"><i class="fas fa-info-circle"></i> Your payment is pending verification. Our team will contact you shortly.</div>
                <div class="action-buttons">
                    <button class="book-now-btn" onclick="closeFlashDealModal()" style="background: #ff9800; width: auto;">Close</button>
                </div>
                </div>
            </div>
        </div>
    </div>
    `;

    modalBody.innerHTML = html;

    // Restore a previously selected hotel (e.g. the modal was just rebuilt
    // from scratch after a login interruption) so the surcharge and total
    // don't silently reset to base price.
    try {
        const savedHotel = sessionStorage.getItem('flash_hotel_' + dealId);
        if (savedHotel) {
            const h = JSON.parse(savedHotel);
            window.selectFlashHotel(h.index, h.name, h.stars, h.price, true);
        }
    } catch (e) { /* corrupt/unavailable, skip */ }

    // ── Flatpickr calendar for travel date ──────────────────────────────────
    // Parse blocked dates from deal data
    const blockedDates = (deal.blocked_dates || '')
        .split(',')
        .map(d => d.trim())
        .filter(Boolean);

    // Parse blocked months (CSV string "1,2,3" -> array of numbers [0,1,2] for JS)
    const blockedMonths = (deal.blocked_months || '')
        .split(',')
        .map(m => parseInt(m.trim()) - 1)
        .filter(m => !isNaN(m));

    const parsedDuration = parseInt(deal.duration) || 1;
    // Trip length shown on the calendar is controlled by the content
    // manager's "highlight duration" setting, not parsed from the
    // free-text duration label.
    const highlightDuration = parseInt(deal.highlight_duration) || parsedDuration;

    const flashDatePicker = flatpickr('#flashStepDate', {
        minDate: deal.promo_start && new Date(deal.promo_start) > new Date() ? deal.promo_start : 'today',
        // A promo_end in the past (an expired promo an admin never updated) must
        // not be used as maxDate -- that would put maxDate before minDate and
        // flatpickr disables every date on the calendar with no visible error.
        maxDate: deal.promo_end && new Date(deal.promo_end) > new Date() ? deal.promo_end : null,
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
            const rangeInfo = document.getElementById('flashDateRangeInfo');
            const rangeText = document.getElementById('flashDateRangeText');
            const warningBox = document.getElementById('flashPromoEndingWarning');
            const warningText = document.getElementById('flashPromoEndingWarningText');

            if (rangeInfo && rangeText) {
                const opts = { month: 'short', day: 'numeric', year: 'numeric' };
                const durText = highlightDuration > 1 ? `${highlightDuration} Days` : '1 Day';
                rangeText.textContent = `Your trip: ${start.toLocaleDateString(undefined, opts)} → ${end.toLocaleDateString(undefined, opts)} (${durText})`;
                rangeInfo.style.display = 'block';

                // CHECK PROMO END VALIDATION
                let promoExpired = false;
                if (deal.promo_end) {
                    const promoEnd = new Date(deal.promo_end);
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
                const conflictBox = document.getElementById('flashBlockedDateConflict');
                const conflictText = document.getElementById('flashBlockedDateConflictText');
                const blockedDatesArr = (deal.blocked_dates || '').split(',').map(d => d.trim()).filter(Boolean);

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

                const clearBtn = document.getElementById('flashClearDateBtn');

                if (foundConflict || promoExpired) {
                    window.flashDateConflict = true;
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
                    window.flashDateConflict = false;
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

    window.flashDateConflict = false; // Reset state

    window.clearFlashDate = function () {
        const fp = document.querySelector('#flashStepDate')._flatpickr;
        if (fp) {
            fp.clear();
            fp.redraw();
        }
        const rangeInfo = document.getElementById('flashDateRangeInfo');
        if (rangeInfo) rangeInfo.style.display = 'none';
        document.getElementById('flashStepDate').value = '';
    };
    // ──────────────────────────────────────────────────────────────────────────

    // Initialize total update
    const travelersInput = document.getElementById('flashStepTravelers');
    if (travelersInput) {
        travelersInput.addEventListener('change', function () {
            updateFlashStepTotal(deal.price);
        });
    }

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

        if (window.flashSliderInterval) clearInterval(window.flashSliderInterval);
        window.flashSliderInterval = setInterval(() => {
            showSlide((current + 1) % slides.length);
        }, 3500);

        dots.forEach(dot => {
            dot.onclick = (e) => {
                clearInterval(window.flashSliderInterval);
                showSlide(parseInt(e.target.dataset.index));
                window.flashSliderInterval = setInterval(() => {
                    showSlide((current + 1) % slides.length);
                }, 3500);
            };
        });
    }

    // Reset payment variables
    window.flashSelectedPayment = null;
    window.flashBookingData = null;
};

// Update flash step total
window.updateFlashStepTotal = function (price) {
    const travelers = parseInt(document.getElementById('flashStepTravelers').value) || 1;
    const hotelSurcharge = window.flashSelectedHotelSurcharge || 0;
    const total = (travelers * price) + hotelSurcharge;

    const summaryTravelers = document.getElementById('flashStepSummaryTravelers');
    const summaryTotal = document.getElementById('flashStepSummaryTotal');
    const gcashAmount = document.getElementById('flashGcashAmount');
    const paymayaAmount = document.getElementById('flashPaymayaAmount');
    const bankAmount = document.getElementById('flashBankAmount');

    if (summaryTravelers) summaryTravelers.textContent = travelers;
    if (summaryTotal) summaryTotal.textContent = (window.currentFlashDealCurrency || '₱') + formatNumberFlash(total);
    if (gcashAmount) gcashAmount.textContent = formatNumberFlash(total);
    if (paymayaAmount) paymayaAmount.textContent = formatNumberFlash(total);
    if (bankAmount) bankAmount.textContent = formatNumberFlash(total);

    if (typeof updateVoucherTotalInline === 'function') {
        updateVoucherTotalInline('flash', total);
    }
};

// Go to flash step
window.goToFlashStep = function (step) {
    for (let i = 1; i <= 5; i++) {
        const stepDiv = document.getElementById(`flashStep${i}`);
        const contentDiv = document.getElementById(`flashStep${i}Content`);
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
        const price = window.currentFlashDeal ? window.currentFlashDeal.price : 0;
        const travelers = parseInt(document.getElementById('flashStepTravelers')?.value) || 1;
        const hotelSurcharge = window.flashSelectedHotelSurcharge || 0;
        const total = (price * travelers) + hotelSurcharge;
        initVoucherCheckoutInline(
            'flash',
            total,
            'flash_deals',
            'flashStep2VoucherArea',
            'flashStepSummaryTotal',
            () => window.currentFlashDealCurrency || '₱',
            window.currentFlashDeal ? window.currentFlashDeal.id : 0
        );
    }
    if (step === 4 && window.flashBookingData) {
        const total = window.flashBookingData.totalAmount;
        if (document.getElementById('flashGcashAmount')) document.getElementById('flashGcashAmount').textContent = formatNumberFlash(total);
        if (document.getElementById('flashPaymayaAmount')) document.getElementById('flashPaymayaAmount').textContent = formatNumberFlash(total);
        if (document.getElementById('flashBankAmount')) document.getElementById('flashBankAmount').textContent = formatNumberFlash(total);
    }
};

// Validate flash step 1 (Date)
window.validateFlashStep1 = function () {
    const errors = [];
    const travelDate = document.getElementById('flashStepDate').value;

    if (!travelDate) errors.push('Travel Date is required');
    if (window.flashDateConflict) errors.push('Selected range includes blocked dates. Please pick another date.');

    if (errors.length > 0) {
        const errorDiv = document.getElementById('flashStep1Errors');
        errorDiv.style.display = 'flex';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    // Require login before proceeding to passenger info
    const currentDealId = window.currentFlashDeal ? window.currentFlashDeal.id : null;
    requireLogin('resumeFlashBooking', currentDealId, 2);

    return true;
};

window.resumeFlashBooking = async function (dealId, step) {
    const modal = document.getElementById('flashDealModal');
    if (modal && modal.classList.contains('active')) {
        // Modal is already open. resumeFlashBooking is only ever called to
        // enter or resume the booking flow (never to show the details view),
        // so always switch to it -- package-details.php already shows details.
        const detailsView = document.getElementById('flashDetailsView');
        const bookingView = document.getElementById('flashBookingView');
        if (detailsView) detailsView.style.display = 'none';
        if (bookingView) bookingView.style.display = 'block';
        goToFlashStep(step);
    } else {
        // Modal not open (likely after login redirect, or arriving fresh from
        // package-details.php). showFlashDealPopupModal does a real network
        // fetch + builds the modal -- await it fully instead of guessing a
        // fixed delay, otherwise goToFlashStep can run before the modal (and
        // its date picker) is actually ready.
        if (typeof showFlashDealPopupModal === 'function') {
            await showFlashDealPopupModal(dealId);

            // Always switch straight to the booking view -- skip the
            // redundant details view since package-details.php already shows it.
            const detailsView = document.getElementById('flashDetailsView');
            const bookingView = document.getElementById('flashBookingView');
            if (detailsView) detailsView.style.display = 'none';
            if (bookingView) bookingView.style.display = 'block';

            // Pre-fill user info if available
            if (window.currentFullName) {
                const nameField = document.getElementById('flashStepFullName');
                if (nameField) nameField.value = window.currentFullName;
            }
            goToFlashStep(step);
        }
    }
};

// Validate flash step 2 (Passenger Info)
window.validateFlashStep2 = function () {
    const errors = [];
    const fullName = document.getElementById('flashStepFullName').value.trim();
    const email = window.currentUserEmail || '';
    const phone = document.getElementById('flashStepPhone').value.trim();
    const travelDate = document.getElementById('flashStepDate').value;
    const travelers = document.getElementById('flashStepTravelers').value;

    if (!fullName) errors.push('Full Name is required');
    if (!email) errors.push('Your account email could not be detected. Please log in again.');
    if (!phone) errors.push('Phone number is required');
    if (!travelers || travelers < 1) errors.push('At least 1 traveler is required');

    if (errors.length > 0) {
        const errorDiv = document.getElementById('flashStep2Errors');
        errorDiv.style.display = 'flex';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    window.flashBookingData = {
        fullName, email, phone, travelDate, travelers: parseInt(travelers),
        specialRequests: document.getElementById('flashStepRequests').value.trim() || '',
        totalAmount: 0
    };

    document.getElementById('flashReviewName').textContent = fullName;
    document.getElementById('flashReviewEmail').textContent = email;
    document.getElementById('flashReviewPhone').textContent = phone;
    document.getElementById('flashReviewDate').textContent = new Date(travelDate).toLocaleDateString();
    document.getElementById('flashReviewTravelers').textContent = travelers;
    document.getElementById('flashReviewRequests').textContent = window.flashBookingData.specialRequests || 'None';

    const price = window.currentFlashDeal ? window.currentFlashDeal.price : 0;
    const hotelSurcharge = window.flashSelectedHotelSurcharge || 0;
    const rawTotal = (travelers * price) + hotelSurcharge;
    const appliedVoucher = window._appliedVoucher && window._appliedVoucher['flash'];
    const finalAmount = appliedVoucher ? appliedVoucher.finalTotal : rawTotal;

    window.flashBookingData.totalAmount = finalAmount;

    const reviewTotalEl = document.getElementById('flashReviewTotal');
    if (reviewTotalEl) {
        if (appliedVoucher) {
            reviewTotalEl.innerHTML = `${window.currentFlashDealCurrency || '₱'}${formatNumberFlash(finalAmount)} <span style="text-decoration:line-through;color:#94a3b8;font-size:0.8em;">${window.currentFlashDealCurrency || '₱'}${formatNumberFlash(rawTotal)}</span>`;
        } else {
            reviewTotalEl.textContent = (window.currentFlashDealCurrency || '₱') + formatNumberFlash(rawTotal);
        }
    }

    goToFlashStep(3);
    return true;
};

// Select flash payment method - FIXED
window.selectFlashPaymentMethod = function (method, event) {
    window.flashSelectedPayment = method;
    const targetElement = event.currentTarget;
    document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
    targetElement.classList.add('selected');
    document.querySelectorAll('input[name="flash_payment"]').forEach(radio => radio.checked = false);
    const radio = document.getElementById(`flash${method.charAt(0).toUpperCase() + method.slice(1)}Radio`);
    if (radio) radio.checked = true;

    // Hide all payment details boxes
    document.querySelectorAll('.payment-details-box').forEach(box => {
        box.classList.remove('show');
    });

    // Show selected payment details
    const detailsBox = document.getElementById(`flash${method.charAt(0).toUpperCase() + method.slice(1)}Details`);
    if (detailsBox) detailsBox.classList.add('show');
};

// Copy to clipboard for flash
window.copyToClipboardFlash = function (text) {
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
};

// Handle flash file upload
window.handleFlashFileUpload = function (event, paymentMethod) {
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
            const previewDiv = document.getElementById(`flash-preview-${paymentMethod}`);
            if (previewDiv) {
                previewDiv.innerHTML = `<img src="${e.target.result}" alt="Payment Proof" style="max-width:100%; max-height:100px; border-radius:8px;">`;
            }
        };
        reader.readAsDataURL(file);
        const fileNameSpan = document.getElementById(`flash-file-name-${paymentMethod}`);
        if (fileNameSpan) {
            fileNameSpan.textContent = file.name;
        }
    }
};

// Validate flash payment
window.validateFlashPayment = function () {
    const errors = [];
    const price = window.currentFlashDeal ? window.currentFlashDeal.price : 0;
    const travelers = window.flashBookingData ? window.flashBookingData.travelers : 1;
    // window.flashBookingData.totalAmount already includes the hotel
    // surcharge and any applied voucher -- recomputing price*travelers here
    // would silently drop both and undercharge the saved booking record.
    const totalAmount = (window.flashBookingData && window.flashBookingData.totalAmount) || (price * travelers);

    if (!window.flashSelectedPayment) errors.push('Please select a payment method');

    if (window.flashSelectedPayment === 'gcash') {
        const ref = document.getElementById('flashPaymentRefGcash')?.value.trim();
        if (!ref) errors.push('Please enter the GCash reference number');
        if (!document.getElementById('flashProofGcash')?.files[0]) errors.push('Please upload proof of payment');
    }
    if (window.flashSelectedPayment === 'paymaya') {
        const ref = document.getElementById('flashPaymentRefPaymaya')?.value.trim();
        if (!ref) errors.push('Please enter the PayMaya reference number');
        if (!document.getElementById('flashProofPaymaya')?.files[0]) errors.push('Please upload proof of payment');
    }
    if (window.flashSelectedPayment === 'card') {
        if (!document.getElementById('flashCardNumber')?.value.trim()) errors.push('Card Number is required');
        if (!document.getElementById('flashExpiryDate')?.value.trim()) errors.push('Expiry Date is required');
        if (!document.getElementById('flashCvv')?.value.trim()) errors.push('CVV is required');
        if (!document.getElementById('flashCardName')?.value.trim()) errors.push('Cardholder Name is required');
    }
    if (window.flashSelectedPayment === 'bank') {
        const ref = document.getElementById('flashBankRef')?.value.trim();
        if (!ref) errors.push('Reference Number is required');
        if (!document.getElementById('flashProofBank')?.files[0]) errors.push('Please upload proof of payment');
    }

    if (errors.length > 0) {
        const errorDiv = document.getElementById('flashStep4Errors');
        errorDiv.style.display = 'flex';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><ul style="margin:0; padding-left:20px;">${errors.map(e => `<li>✗ ${e}</li>`).join('')}</ul>`;
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    let paymentMethodName = '';
    let paymentRef = '';
    if (window.flashSelectedPayment === 'gcash') { paymentMethodName = 'GCash'; paymentRef = document.getElementById('flashPaymentRefGcash')?.value.trim(); }
    else if (window.flashSelectedPayment === 'paymaya') { paymentMethodName = 'PayMaya'; paymentRef = document.getElementById('flashPaymentRefPaymaya')?.value.trim(); }
    else if (window.flashSelectedPayment === 'card') { paymentMethodName = 'Credit/Debit Card'; paymentRef = 'Card ending in ' + document.getElementById('flashCardNumber')?.value.slice(-4); }
    else if (window.flashSelectedPayment === 'bank') { paymentMethodName = 'Bank Transfer'; paymentRef = document.getElementById('flashBankRef')?.value.trim(); }

    const btn = document.querySelector('#flashStep4Content .btn-next');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    // Apply voucher discount if one was applied
    const appliedVoucher = window._appliedVoucher && window._appliedVoucher['flash'];
    const finalAmount = appliedVoucher ? appliedVoucher.finalTotal : totalAmount;

    const formData = new FormData();
    formData.append('service_type', 'Flash Deal');
    formData.append('package_name', window.currentFlashDeal.title);
    formData.append('package_duration', window.currentFlashDeal.duration || '3D/2N');
    formData.append('price_per_person', window.currentFlashDeal.price);
    if (window.currentFlashDeal.id) {
        formData.append('package_source_id', window.currentFlashDeal.id);
        formData.append('package_source_type', 'flash');
    }
    // Attribute this booking to the partner who owns the flash deal (if any),
    // the same way home-packages.js / foreign-packages.js do for their types --
    // otherwise the booking never links back to the partner's dashboard table.
    if (window.currentFlashDeal.partner_id) {
        formData.append('partner_id', window.currentFlashDeal.partner_id);
        if (window.currentFlashDeal.partner_company) {
            formData.append('partner_company', window.currentFlashDeal.partner_company);
        }
        formData.append('partner_source', 'flash_deal');
        formData.append('partner_package_id', window.currentFlashDeal.id);
        formData.append('partner_package_name', window.currentFlashDeal.title);
    }
    formData.append('full_name', window.flashBookingData.fullName);
    formData.append('email', window.flashBookingData.email);
    formData.append('phone', window.flashBookingData.phone);
    formData.append('travelers', window.flashBookingData.travelers);
    formData.append('travel_date', window.flashBookingData.travelDate);
    formData.append('special_requests', window.flashBookingData.specialRequests);
    if (window.flashSelectedHotelSurcharge > 0) {
        const hotelNameEl = document.getElementById('flashSelectedHotelName');
        formData.append('hotel_name', hotelNameEl ? hotelNameEl.textContent.trim() : '');
        formData.append('hotel_price', window.flashSelectedHotelSurcharge);
    }
    formData.append('total_amount', finalAmount);
    formData.append('payment_method', window.flashSelectedPayment);
    if (paymentRef) formData.append('payment_reference', paymentRef);
    if (appliedVoucher) {
        formData.append('voucher_id', appliedVoucher.id);
        formData.append('voucher_discount', appliedVoucher.discountAmount);
    }

    // Support file uploads for payment proof
    const fileInput = document.getElementById(`flashProof${window.flashSelectedPayment.charAt(0).toUpperCase() + window.flashSelectedPayment.slice(1)}`);
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
                document.getElementById('flashBookingNumber').innerHTML = 'Booking: ' + data.booking_number;
                document.getElementById('flashConfirmDate').textContent = new Date(window.flashBookingData.travelDate).toLocaleDateString();
                document.getElementById('flashConfirmTravelers').textContent = window.flashBookingData.travelers;
                const currency = window.currentFlashDealCurrency || '₱';
                document.getElementById('flashConfirmTotal').innerHTML = currency + formatNumberFlash(totalAmount);
                document.getElementById('flashConfirmPayment').textContent = paymentMethodName;
                document.getElementById('flashConfirmName').textContent = window.flashBookingData.fullName;

                goToFlashStep(5);
            } else {
                const errorDiv = document.getElementById('flashStep4Errors');
                errorDiv.style.display = 'flex';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${data.message}`;
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            const errorDiv = document.getElementById('flashStep4Errors');
            errorDiv.style.display = 'flex';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> Connection Error. Please try again.`;
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            console.error('Error saving booking:', error);
        });

    return true;
};

// Close flash deal modal
window.closeFlashDealModal = function () {
    const modal = document.getElementById('flashDealModal');
    if (modal) modal.classList.remove('active');

    // Clear slider interval
    if (window.flashSliderInterval) {
        clearInterval(window.flashSliderInterval);
        window.flashSliderInterval = null;
    }

    window.flashSelectedPayment = null;
    window.flashBookingData = null;
    window.currentFlashDeal = null;
};

// Show notification
function showFlashNotification(message, type) {
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

// Helper function to render collage images for flash deals
function renderFlashDealCollage(deal) {
    const images = deal.images || [];
    const collageType = deal.collage_type || 'three';

    if (collageType === 'three' && images.length >= 3) {
        return `
            <div class="image-collage">
                <div class="collage-three">
                    <div class="collage-main">
                        <img src="${images[0] || ''}" alt="${escapeHtmlFlash(deal.title)}" onerror="this.onerror=null;this.src='https://via.placeholder.com/250x180?text=${deal.title}'">
                    </div>
                    <div class="collage-stack">
                        <img src="${images[1] || ''}" alt="${escapeHtmlFlash(deal.title)}" onerror="this.onerror=null;this.src='https://via.placeholder.com/150x90?text=${deal.title}'">
                        <img src="${images[2] || ''}" alt="${escapeHtmlFlash(deal.title)}" onerror="this.onerror=null;this.src='https://via.placeholder.com/150x90?text=${deal.title}'">
                    </div>
                </div>
            </div>
        `;
    } else if (collageType === 'half' && images.length >= 2) {
        return `
            <div class="image-collage">
                <div class="collage-half">
                    <img src="${images[0] || ''}" alt="${escapeHtmlFlash(deal.title)}" onerror="this.onerror=null;this.src='https://via.placeholder.com/200x180?text=${deal.title}'">
                    <img src="${images[1] || ''}" alt="${escapeHtmlFlash(deal.title)}" onerror="this.onerror=null;this.src='https://via.placeholder.com/200x180?text=${deal.title}'">
                </div>
            </div>
        `;
    } else {
        const imgSrc = images[0] || 'https://via.placeholder.com/400x250?text=' + encodeURIComponent(deal.title);
        return `
            <div class="image-collage">
                <img src="${imgSrc}" alt="${escapeHtmlFlash(deal.title)}" style="width:100%; height:100%; object-fit:cover;" onerror="this.onerror=null;this.src='https://via.placeholder.com/400x250?text=${encodeURIComponent(deal.title)}'">
            </div>
        `;
    }
}

// Load flash deals for homepage
async function loadFlashDealsForHome() {
    const container = document.getElementById('flashDealsGridHome');
    if (!container) return;

    // Show loading state
    container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Loading flash deals...</p></div>';

    try {
        const response = await fetch('api/get-flash-deals.php?limit=4');
        const data = await response.json();

        if (data.success && data.deals && data.deals.length > 0) {
            renderFlashDealsHome(data.deals);
        } else {
            container.innerHTML = '<div class="loading-spinner"><i class="fas fa-info-circle"></i><p>No flash deals available. Check back soon!</p></div>';
        }
    } catch (error) {
        console.error('Error loading flash deals:', error);
        container.innerHTML = '<div class="loading-spinner"><i class="fas fa-exclamation-circle"></i><p>Unable to load deals. Please try again later.</p></div>';
    }
}

// Render flash deals for homepage with collage support (DISCOUNT BADGE IN RED)
function renderFlashDealsHome(deals) {
    const container = document.getElementById('flashDealsGridHome');
    if (!container) return;

    container.innerHTML = '';

    deals.forEach(deal => {
        // Check if deal has discount - use red badge for discounted deals
        const hasDiscount = deal.discount_percent && deal.discount_percent > 0;
        const discountBadgeText = hasDiscount ? `⚡ ${deal.discount_percent}% OFF` : (deal.badge_text || 'Flash Deal');
        const badgeColorClass = hasDiscount ? 'flash-deal-badge-red' : 'flash-deal-badge';

        const currency = deal.currency || '₱';

        const images = [];
        if (deal.image_path) images.push(deal.image_path);
        if (deal.image2_path) images.push(deal.image2_path);
        if (deal.image3_path) images.push(deal.image3_path);
        if (deal.image4_path) images.push(deal.image4_path);

        const badgeText = deal.badge_text || deal.location || 'Special Offer';

        // Calculate countdown based on Travel Validity End Date (promo_end)
        let countdownHtml = '';
        if (deal.promo_end) {
            const endDate = new Date(deal.promo_end);
            endDate.setHours(23, 59, 59); // End of the day
            const now = new Date();
            const diffMs = endDate.getTime() - now.getTime();

            if (diffMs > 0) {
                const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const mins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
                countdownHtml = `
                    <div style="display: inline-flex; align-items: center; background: #fff1f1; color: #e53935; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; gap: 6px; font-weight: 500;">
                        <i class="far fa-clock"></i> Ends in ${days}d ${hours}h ${mins}m
                    </div>
                `;
            } else {
                countdownHtml = `
                    <div style="display: inline-flex; align-items: center; background: #f0f0f0; color: #888; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; gap: 6px; font-weight: 500;">
                        <i class="far fa-clock"></i> Expired
                    </div>
                `;
            }
        } else {
            // Fallback if no promo end date set
            const validText = deal.best_season ? 'Valid: ' + deal.best_season : 'Limited Time Offer';
            countdownHtml = `
                <div style="display: inline-flex; align-items: center; background: #fff1f1; color: #e53935; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; gap: 6px; font-weight: 500;">
                    <i class="far fa-clock"></i> ${escapeHtmlFlash(validText)}
                </div>
            `;
        }

        let collageHtml = '';
        if (images.length <= 1) {
            collageHtml = `<img src="${images[0] || 'images/placeholder-deal.jpg'}" alt="${escapeHtmlFlash(deal.title)}" style="width: 100%; height: 100%; object-fit: cover;">`;
        } else if (images.length === 2) {
            collageHtml = `
                <div class="image-collage collage-half">
                    <img src="${images[0]}" alt="${escapeHtmlFlash(deal.title)} 1">
                    <img src="${images[1]}" alt="${escapeHtmlFlash(deal.title)} 2">
                </div>`;
        } else {
            collageHtml = `
                <div class="image-collage collage-three">
                    <div class="collage-main">
                        <img src="${images[0]}" alt="${escapeHtmlFlash(deal.title)} 1">
                    </div>
                    <div class="collage-stack">
                        <img src="${images[1]}" alt="${escapeHtmlFlash(deal.title)} 2">
                        <img src="${images[2]}" alt="${escapeHtmlFlash(deal.title)} 3">
                    </div>
                </div>`;
        }

        container.innerHTML += `
            <div class="flash-deal-card" onclick="showFlashDealPopup(${deal.id})">
                <div class="flash-deal-image">
                    ${collageHtml}

                    <div style="position: absolute; bottom: 0; left: 0; right: 0; padding: 40px 15px 15px; background: linear-gradient(to top, rgba(10,30,60,0.95), transparent);">
                        <h3 style="color: white; margin: 0; font-size: 1.1rem; font-weight: 700; line-height: 1.3;">${escapeHtmlFlash(deal.title)}</h3>
                    </div>
                </div>

                <div class="flash-deal-content" style="padding: 15px; display: flex; flex-direction: column; flex-grow: 1;">
                    <div style="align-self: flex-start; background: #ff9800; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; margin-bottom: 10px;">
                        ${escapeHtmlFlash(badgeText).substring(0, 18)}${badgeText.length > 18 ? '...' : ''}
                    </div>
                    <div style="color: #888; font-size: 0.7rem; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; font-weight: 600;">
                        <i class="fas fa-map-marker-alt" style="color: #ff9800;"></i> ${escapeHtmlFlash(deal.location || 'Various Locations')}
                    </div>
                    
                    <p style="color: #666; font-size: 0.85rem; line-height: 1.4; margin-bottom: 15px; flex-grow: 1;">
                        ${escapeHtmlFlash(deal.description || 'Grab this amazing travel deal before it expires!')}
                    </p>
                    ${deal.partner_id ? `<div style="font-size: 0.8rem; color: #64748b; margin-top: 5px; margin-bottom: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;" title="${escapeHtmlFlash(deal.partner_company || 'Partner')}">Provided by: <a href="view-partner-profile.php?id=${deal.partner_id}" style="color: #003580; font-weight: 500; text-decoration: none;" onclick="event.stopPropagation();">${escapeHtmlFlash(deal.partner_company || 'Partner')}</a></div>` : ''}
                    
                    <div style="margin-bottom: 15px;">
                        <div style="font-size: 0.85rem; color: #888; margin-bottom: 8px;">
                            From <span style="color: #ff9800; font-weight: 800; font-size: 1.2rem;">${currency}${formatNumberFlash(deal.price)}</span> <span style="font-size: 0.75rem;">/ person</span>
                        </div>
                        ${countdownHtml}
                    </div>
                    
                    <button style="width: 100%; background: #002366; color: white; border: none; padding: 8px; border-radius: 20px; font-weight: 700; font-size: 0.75rem; cursor: pointer; transition: background 0.3s; text-transform: uppercase; letter-spacing: 0.5px;" onmouseover="this.style.background='#001540'" onmouseout="this.style.background='#002366'">
                        VIEW DEAL DETAILS &rarr;
                    </button>
                </div>
            </div>
        `;

    });
}

// Add modal styles for flash deals
function addFlashDealModalStyles() {
    if (!document.querySelector('#flashDealModalStyles')) {
        const style = document.createElement('style');
        style.id = 'flashDealModalStyles';
        style.textContent = `
            /* Flash Deal Card Styles for Homepage */
            #flashDealsGridHome {
                margin-bottom: 20px;
            }
            
            .flash-deal-card {
                background: white;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
                border: 1px solid #f0f0f0;
                transition: all 0.3s ease;
                cursor: pointer;
                position: relative;
                animation: fadeInUp 0.4s ease;
                display: flex;
                flex-direction: column;
                height: auto;
                min-height: 400px;
                width: 100%;
            }
            
            .flash-deal-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 15px 35px rgba(255,152,0,0.15);
            }
            
            .flash-deal-image {
                position: relative;
                height: 220px;
                border-radius: 16px 16px 0 0;
                overflow: hidden;
                flex-shrink: 0;
            }

            /* HORIZONTAL LAYOUT FOR DESKTOP (Like user requested screenshot) */
            @media (min-width: 992px) {
                .flash-deal-card {
                    flex-direction: row !important;
                    height: auto !important;
                    min-height: 230px !important;
                    width: 450px !important;
                    min-width: 450px !important;
                    flex: 0 0 450px !important;
                }
                .flash-deal-image {
                    width: 45%;
                    align-self: stretch;
                    height: auto !important;
                    min-height: 230px;
                    border-radius: 16px 0 0 16px !important;
                }
            }
            
            /* Image Collage Styles */
            .image-collage {
                position: relative;
                width: 100%;
                height: 100%;
                overflow: hidden;
            }
            
            .collage-three {
                display: flex;
                gap: 2px;
                height: 100%;
            }
            
            .collage-main {
                flex: 2;
                height: 100%;
            }
            
            .collage-main img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .collage-stack {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 2px;
                height: 100%;
            }
            
            .collage-stack img {
                width: 100%;
                height: calc(50% - 1px);
                object-fit: cover;
            }
            
            .collage-half {
                display: flex;
                gap: 2px;
                height: 100%;
            }
            
            .collage-half img {
                width: 50%;
                height: 100%;
                object-fit: cover;
            }
            
            .flash-deal-card:hover .flash-deal-image img {
                transform: scale(1.05);
            }
            
            .flash-deal-badge {
                position: absolute;
                top: 10px;
                left: 10px;
                background: #ff9800;
                color: white;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 0.7rem;
                font-weight: 600;
                z-index: 2;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }
            
            /* RED DISCOUNT BADGE */
            .flash-deal-badge-red {
                position: absolute;
                top: 10px;
                left: 10px;
                background: #dc3545;
                color: white;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 0.7rem;
                font-weight: 600;
                z-index: 2;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                animation: blinkRed 2s infinite;
            }
            
            @keyframes blinkRed {
                0% { background: #dc3545; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
                50% { background: #ff0000; box-shadow: 0 0 12px rgba(255,0,0,0.6); }
                100% { background: #dc3545; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
            }
            
            /* RED DISCOUNT BADGE */
            .flash-deal-badge-red {
                position: absolute;
                top: 10px;
                left: 10px;
                background: #dc3545;
                color: white;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 0.7rem;
                font-weight: 600;
                z-index: 2;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }
            
            @keyframes blinkRed {
                0% { background: #dc3545; opacity: 1; }
                50% { background: #ff0000; opacity: 0.85; box-shadow: 0 0 8px rgba(255,0,0,0.5); }
                100% { background: #dc3545; opacity: 1; }
            }
            
            .flash-deal-content {
                padding: 12px;
                flex: 1;
                display: flex;
                flex-direction: column;
            }
            
            .flash-deal-category {
                color: #ff9800;
                font-size: 0.7rem;
                font-weight: 600;
                text-transform: uppercase;
                margin-bottom: 4px;
            }
            
            .flash-deal-title {
                font-size: 1rem;
                font-weight: 700;
                color: #003580;
                margin-bottom: 2px;
                font-family: 'Poppins', sans-serif;
                line-height: 1.3;
                display: -webkit-box;
                -webkit-line-clamp: 1;
                line-clamp: 1;
                -webkit-box-orient: vertical;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .flash-deal-location {
                color: #666;
                font-size: 0.7rem;
                margin-bottom: 6px;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            
            .flash-deal-location i {
                color: #ff9800;
                font-size: 0.65rem;
            }
            
            .flash-deal-desc {
                color: #555;
                font-size: 0.75rem;
                line-height: 1.4;
                margin-bottom: 10px;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
                min-height: 50px;
            }
            
            .flash-deal-price-section {
                margin-bottom: 6px;
                margin-top: auto;
            }
            
            .flash-deal-current-price {
                font-size: 1rem;
                font-weight: 700;
                color: #ff9800;
            }
            
            .flash-deal-current-price small {
                font-size: 0.65rem;
                color: #888;
                font-weight: normal;
            }
            
            .flash-deal-btn {
                background: #003580;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 20px;
                font-size: 0.7rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
                width: 100%;
                text-align: center;
            }
            
            .flash-deal-btn:hover {
                background: #ff9800;
                transform: scale(1.02);
            }
            
            /* Flash Deal Modal Styles */
            .flash-deal-modal {
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
            
            .flash-deal-modal.active {
                display: flex;
            }
            
            .flash-deal-modal-content {
                background: white;
                border-radius: 24px;
                max-width: 900px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                animation: modalSlideIn 0.3s ease;
                position: relative;
            }
            
            .flash-deal-modal-content::-webkit-scrollbar {
                width: 8px;
            }
            
            .flash-deal-modal-content::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }
            
            .flash-deal-modal-content::-webkit-scrollbar-thumb {
                background: #ff9800;
                border-radius: 10px;
            }
            
            .flash-deal-modal-header {
                background: linear-gradient(135deg, #ff9800, #f57c00);
                color: white;
                padding: 20px 25px;
                border-radius: 24px 24px 0 0;
                position: relative;
            }
            
            .flash-deal-modal-header .close-modal {
                position: absolute;
                top: 15px;
                right: 20px;
                font-size: 1.8rem;
                cursor: pointer;
                transition: all 0.3s ease;
                color: white;
            }
            
            .flash-deal-modal-header .close-modal:hover {
                transform: rotate(90deg);
                color: #003580;
            }
            
            .flash-deal-modal-header h2 {
                font-size: 1.5rem;
                margin-bottom: 8px;
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

            .flash-deal-modal-body {
                padding: 20px;
            }
            
            .deal-price-card {
                background: linear-gradient(135deg, #fff, #f8f9fa);
                border-radius: 16px;
                padding: 20px;
                text-align: center;
                margin-bottom: 20px;
                border: 2px solid #ff9800;
            }
            
            .deal-price-card .price {
                font-size: 1.8rem;
                font-weight: 800;
                color: #ff9800;
            }
            
            .deal-price-card .original-price {
                font-size: 1rem;
                color: #999;
                text-decoration: line-through;
                margin-left: 10px;
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
            
            .upload-preview {
                margin-top: 10px;
                text-align: center;
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
                background: #fff5f5;
                border-left: 3px solid #ff4444;
                padding: 10px 12px;
                border-radius: 8px;
                margin-bottom: 15px;
                font-size: 0.75rem;
                color: #ff4444;
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
            
            .booking-form-modal label {
                display: block;
                font-weight: 600;
                margin-bottom: 4px;
                font-size: 0.75rem;
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
            
            @media (max-width: 768px) {
                .package-details-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                .payment-methods {
                    grid-template-columns: 1fr;
                }
                .card-row {
                    grid-template-columns: 1fr;
                }
                .booking-form-modal .form-row {
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
        `;
        document.head.appendChild(style);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    addFlashDealModalStyles();
    console.log('Flash deals JavaScript initialized!');

    function sendFlashDealBookingToServer(bookingNumber, totalAmount, paymentMethod) {
        // Check login first
        fetch('api/check-auth.php')
            .then(response => response.json())
            .then(data => {
                if (!data.logged_in) {
                    showLoginRequiredPopup();
                    return;
                }

                // Continue with existing booking
                if (!currentFlashDeal || !flashBookingData) return;

                fetch('api/save-flash-deal-booking.php', {
                    // ... existing code ...
                });
            });
    }
});