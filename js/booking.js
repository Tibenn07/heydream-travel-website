// ========================================
// FILE: js/booking.js
// DESCRIPTION: Flight & Hotel pricing tables (Dynamic from Database)
// ========================================

let flightData = {};
let hotelData = {};

// Fetch flight data from database
function fetchFlightData() {
    fetch('admin/api/get-flight-data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                flightData = data.data;
                updateFlightPrices('baguio');
            }
        })
        .catch(error => console.error('Error fetching flight data:', error));
}

// Fetch hotel data from database
function fetchHotelData() {
    fetch('admin/api/get-hotel-data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hotelData = data.data;
                updateHotelPrices('baguio');
            }
        })
        .catch(error => console.error('Error fetching hotel data:', error));
}

function formatPrice(price) {
    return '₱' + price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function updateFlightPrices(destinationKey) {
    const data = flightData[destinationKey];
    const container = document.getElementById('flightPrices');
    const destinationTitle = document.getElementById('selectedDestination');
    
    if (!container || !destinationTitle || !data) return;
    
    destinationTitle.textContent = `Round Trip to ${data.name}`;
    container.innerHTML = '';
    
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    
    months.forEach(month => {
        const monthKey = month.toLowerCase();
        const monthData = data[`month_${monthKey}`];
        
        if (monthData) {
            const priceRow = document.createElement('div');
            priceRow.className = 'price-row';
            priceRow.style.cursor = 'pointer';
            
            priceRow.innerHTML = `
                <span class="month-name">${month}</span>
                <span class="price-range">
                    <span class="price-low">${formatPrice(monthData.low)}</span> - 
                    <span class="price-high">${formatPrice(monthData.high)}</span>
                    <span class="airline-tag">${monthData.airline || ''}</span>
                </span>
            `;
            
            priceRow.addEventListener('click', function(e) {
                e.stopPropagation();
                this.style.backgroundColor = '#e8f0fe';
                setTimeout(() => {
                    this.style.backgroundColor = '';
                }, 200);
            });
            
            container.appendChild(priceRow);
        }
    });
}

function updateHotelPrices(destinationKey) {
    const data = hotelData[destinationKey];
    const container = document.getElementById('hotelPrices');
    const destinationTitle = document.getElementById('selectedHotelDestination');
    
    if (!container || !destinationTitle || !data) return;
    
    destinationTitle.textContent = `Hotel Rates in ${data.name}`;
    container.innerHTML = '';
    
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    
    months.forEach(month => {
        const monthKey = month.toLowerCase();
        const monthData = data[`month_${monthKey}`];
        
        if (monthData) {
            const priceRow = document.createElement('div');
            priceRow.className = 'price-row';
            priceRow.style.cursor = 'pointer';
            
            priceRow.innerHTML = `
                <span class="month-name">${month}</span>
                <span class="price-range">
                    <span class="price-low">${formatPrice(monthData.low)}</span> - 
                    <span class="price-high">${formatPrice(monthData.high)}</span>
                    <span class="hotel-tag">${monthData.hotel || ''}</span>
                </span>
            `;
            
            priceRow.addEventListener('click', function(e) {
                e.stopPropagation();
                this.style.backgroundColor = '#e8f0fe';
                setTimeout(() => {
                    this.style.backgroundColor = '';
                }, 200);
            });
            
            container.appendChild(priceRow);
        }
    });
}

// Initialize booking functionality
document.addEventListener('DOMContentLoaded', function() {
    fetchFlightData();
    fetchHotelData();
    
    // Destination selection for flights
    const flightDestinations = document.querySelectorAll('#destinationList .destination-item');
    flightDestinations.forEach(item => {
        item.addEventListener('click', function() {
            flightDestinations.forEach(d => d.classList.remove('active'));
            this.classList.add('active');
            const destKey = this.getAttribute('data-destination');
            if (destKey && flightData[destKey]) updateFlightPrices(destKey);
        });
    });
    
    // Destination selection for hotels
    const hotelDestinations = document.querySelectorAll('#hotelDestinationList .destination-item');
    hotelDestinations.forEach(item => {
        item.addEventListener('click', function() {
            hotelDestinations.forEach(d => d.classList.remove('active'));
            this.classList.add('active');
            const destKey = this.getAttribute('data-hotel-destination');
            if (destKey && hotelData[destKey]) updateHotelPrices(destKey);
        });
    });
});