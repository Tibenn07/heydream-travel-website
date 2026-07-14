// ========================================
// FILE: js/saved.js
// DESCRIPTION: Saved page specific functionality
// ========================================

// Display saved items in the main grid
function displaySavedItems() {
    const savedGrid = document.getElementById('saved-items-grid');
    const emptyState = document.getElementById('empty-saved-state');
    const savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];
    
    if (!savedGrid) return;
    
    if (savedItems.length === 0) {
        savedGrid.style.display = 'none';
        if (emptyState) emptyState.style.display = 'block';
        return;
    }
    
    savedGrid.style.display = 'grid';
    if (emptyState) emptyState.style.display = 'none';
    
    savedGrid.innerHTML = '';
    
    savedItems.forEach(item => {
        const card = document.createElement('div');
        card.className = 'saved-item-card';
        card.innerHTML = `
            <button class="remove-saved-btn" onclick="removeSavedItem('${item.id}', event)">
                <i class="fas fa-times"></i>
            </button>
            <div class="saved-item-image">
                <img src="${item.image || 'https://via.placeholder.com/300x200?text=No+Image'}" alt="${item.name}" onerror="this.onerror=null;this.src='https://via.placeholder.com/300x200?text=No+Image'">
            </div>
            <div class="saved-item-content">
                <div class="saved-item-category">${item.category}</div>
                <div class="saved-item-location">
                    <i class="fas fa-map-marker-alt"></i> ${item.location || 'Various locations'}
                </div>
                <h3 class="saved-item-title">${item.name}</h3>
                <div class="saved-item-price">
                    <span class="price-label">From</span>
                    <span class="price-value">${item.price || 'Contact for price'}</span>
                </div>
                <div class="saved-item-footer">
                    <span class="saved-date">Saved ${new Date(item.dateSaved).toLocaleDateString()}</span>
                    <button class="view-btn" onclick="viewItem('${item.id}', '${item.type || ''}')">View Details</button>
                </div>
            </div>
        `;
        savedGrid.appendChild(card);
    });
}

// Update saved count in the menu button and hero badge
function updatePanelSavedItems() {
    const savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];
    const count = savedItems.length;

    const savedCount = document.getElementById('savedCount');
    if (savedCount) savedCount.textContent = count;

    const heroCount = document.getElementById('heroSavedCount');
    if (heroCount) heroCount.textContent = count;

    const chipCount = document.getElementById('savedChipCount');
    if (chipCount) chipCount.textContent = count;
}

// View item details
window.viewItem = function(id, type) {
    if (!type) {
        // Fallback for older saved items: heuristic detection
        const savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];
        const item = savedItems.find(i => i.id === id);
        if (item) {
            type = item.type;
            if (!type) {
                // Heuristic: Foreign keys are usually strings with hyphens, Local/Flash are numeric
                if (typeof id === 'string' && (id.includes('-') || id.length > 5) && isNaN(id)) {
                    type = 'foreign';
                } else if (item.category && item.category.toUpperCase().includes('FLASH')) {
                    type = 'flash';
                } else {
                    // Default to local/flash check
                    type = 'local';
                }
            }
        }
    }

    console.log('Viewing item:', id, 'Type:', type);

    if (type === 'foreign' && typeof showForeignPackagePopup === 'function') {
        showForeignPackagePopup(id);
    } else if (type === 'flash' && typeof showFlashDealPopup === 'function') {
        showFlashDealPopup(id);
    } else if ((type === 'local' || !type) && typeof showLocalPackagePopup === 'function') {
        showLocalPackagePopup(id);
    } else {
        // If specific function not found, try to alert or find alternative
        alert('Opening details for ' + id + '...');
        // Fallback: many pages might not have all scripts loaded
        // We could redirect to index with an auto-open parameter if needed
    }
};

// Go back to home page
window.goBack = function() {
    window.location.href = 'index.php';
};

// ========================================
// GLOBAL SAVE BUTTON INJECTION LOGIC
// ========================================

// Function to dynamically inject Save buttons onto all package cards on the page
function injectSaveButtons() {
    // Selectors for different package card types across the site
    const cardSelectors = [
        '.foreign-card', 
        '.flash-deal-card', 
        '.popular-card', 
        '.home-destination-card',
        '.destination-card',
        '.destination-card-local',
        '.destination-card-foreign',
        '.deal-card'
    ];

    const allCards = document.querySelectorAll(cardSelectors.join(', '));
    const savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];
    
    allCards.forEach(card => {
        // Skip if button already exists
        if (card.querySelector('.save-btn')) return;

        // Try to identify the image container to prepend the button to
        const imgContainer = card.querySelector('.foreign-card-image, .flash-deal-image, .deal-image, .card-image, .destination-image, .home-card-image, .destination-card-image') || card;
        
        // ── Detect type first from class ──
        let type = 'local';
        if (card.classList.contains('foreign-card') || card.classList.contains('destination-card-foreign') || card.classList.contains('popular-card')) {
            type = 'foreign';
        } else if (card.classList.contains('flash-deal-card') || card.classList.contains('deal-card')) {
            type = 'flash';
        }

        // ── Extract ID robustly from onclick or data attributes ──
        let id = card.getAttribute('data-id') || card.getAttribute('data-key');

        if (!id) {
            const onclick = card.getAttribute('onclick') || '';
            // Match: showFlashDealPopup(5) or showLocalPackagePopup(12) or showForeignPackagePopup('bali_4d3n')
            const numMatch = onclick.match(/\(\s*(\d+)\s*\)/);
            const strMatch = onclick.match(/\(\s*['"]([^'"]+)['"]\s*\)/);
            if (numMatch) {
                id = numMatch[1];  // numeric id as string e.g. "5"
            } else if (strMatch) {
                id = strMatch[1];  // string key e.g. "bali_4d3n"
            }
            // Also detect type from onclick function name
            if (onclick.includes('showFlashDealPopup')) type = 'flash';
            else if (onclick.includes('showForeignPackagePopup')) type = 'foreign';
            else if (onclick.includes('showLocalPackagePopup')) type = 'local';
        }

        // If still no id, try children's onclick
        if (!id) {
            const clickableChild = card.querySelector('[onclick]');
            if (clickableChild) {
                const onclick = clickableChild.getAttribute('onclick') || '';
                const numMatch = onclick.match(/\(\s*(\d+)\s*\)/);
                const strMatch = onclick.match(/\(\s*['"]([^'"]+)['"]\s*\)/);
                if (numMatch) id = numMatch[1];
                else if (strMatch) id = strMatch[1];
                if (onclick.includes('showFlashDealPopup')) type = 'flash';
                else if (onclick.includes('showForeignPackagePopup')) type = 'foreign';
                else if (onclick.includes('showLocalPackagePopup')) type = 'local';
            }
        }

        // Last resort: use a random id (card won't be fetchable, but at least doesn't crash)
        if (!id) {
            id = Math.random().toString(36).substr(2, 9);
        }

        const name = card.querySelector('h3, .foreign-card-name, .flash-deal-title, .deal-title, .destination-name, .home-card-name, .destination-name-popular, .card-title')?.textContent?.trim() || 'Unknown Package';
        const image = card.querySelector('img')?.src || '';
        const priceText = card.querySelector('[class*="price"], .price-value')?.textContent?.trim() || '';
        const locationText = card.querySelector('[class*="location"], .destination-location')?.textContent?.trim() || '';
        const categoryText = card.querySelector('[class*="category"]')?.textContent?.trim() || 'Package';
        
        const isSaved = savedItems.some(item => item.id === id);
        
        const btn = document.createElement('button');
        btn.className = `save-btn ${isSaved ? 'saved' : ''}`;
        btn.innerHTML = `<i class="${isSaved ? 'fas' : 'far'} fa-heart"></i>`;
        
        // Prepare the payload for saving
        const itemData = {
            id: id,
            type: type,
            name: name,
            image: image,
            price: priceText,
            location: locationText,
            category: categoryText,
            dateSaved: new Date().toISOString()
        };
        
        // Bind the onclick event, demanding requireLogin!
        btn.onclick = function(e) {
            e.stopPropagation(); // prevent card click
            e.preventDefault();
            
            if (typeof window.requireLogin === 'function') {
                // Call global processSaveItem via requireLogin check
                window.temporarySaveItemData = { item: itemData, btn: this };
                window.requireLogin('processSaveItem');
            } else {
                alert('Please log in to save items.');
            }
        };
        
        // Assuming position absolute inside the image container
        imgContainer.style.position = 'relative';
        imgContainer.appendChild(btn);
    });
}

// Global scope execution of the save
window.processSaveItem = function() {
    const data = window.temporarySaveItemData;
    if (!data) return;
    
    const item = data.item;
    const btnElement = data.btn;
    
    let savedItems = JSON.parse(localStorage.getItem('savedItems')) || [];
    const index = savedItems.findIndex(saved => saved.id === item.id);
    
    // Toggling logic
    if (index === -1) {
        savedItems.push(item);
        btnElement.classList.add('saved');
        btnElement.querySelector('i').classList.replace('far', 'fas');
    } else {
        savedItems.splice(index, 1);
        btnElement.classList.remove('saved');
        btnElement.querySelector('i').classList.replace('fas', 'far');
    }
    
    localStorage.setItem('savedItems', JSON.stringify(savedItems));
    
    // Dispatch custom event to notify other scripts
    document.dispatchEvent(new Event('savedItemsUpdated'));
    
    if (typeof updatePanelSavedItems === 'function') updatePanelSavedItems();
    if (typeof updateSavedCount === 'function') updateSavedCount();
    
    const savedGrid = document.getElementById('saved-items-grid');
    if (savedGrid && savedGrid.style.display !== 'none') {
        displaySavedItems(); 
    }
};

// Use MutationObserver to inject buttons into JS-rendered cards dynamically
const observer = new MutationObserver((mutations) => {
    let shouldInject = false;
    for (let mutation of mutations) {
        if (mutation.addedNodes.length > 0) {
            shouldInject = true;
            break;
        }
    }
    if (shouldInject) injectSaveButtons();
});

// Initialize saved page
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerIcon = document.querySelector('.hamburger-icon');
    if (hamburgerIcon) {
        hamburgerIcon.classList.add('dark');
    }
    
    displaySavedItems();
    updatePanelSavedItems();
    
    // Inject save buttons initially
    injectSaveButtons();
    
    // Observe body for dynamically loaded cards
    observer.observe(document.body, { childList: true, subtree: true });
});