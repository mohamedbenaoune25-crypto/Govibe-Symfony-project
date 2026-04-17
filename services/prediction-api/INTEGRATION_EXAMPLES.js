/**
 * AI Integration Examples for Vol Page (Flights)
 * 
 * How to integrate weather prediction into your flight listing page
 */

// ============================================================================
// VOL PAGE - WEATHER IMPACT PREDICTION
// ============================================================================

/**
 * Get weather impact score for a flight
 * Call this when displaying each flight/vol
 */
async function getWeatherImpact(temperature, humidity, pressure, windSpeed) {
    try {
        const response = await fetch('/api/ai/predict/weather', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                temperature: parseFloat(temperature),
                humidity: parseFloat(humidity),
                pressure: parseFloat(pressure),
                wind_speed: parseFloat(windSpeed)
            })
        });

        if (!response.ok) {
            console.error('Weather prediction failed:', response.status);
            return null;
        }

        const data = await response.json();
        
        if (data.success) {
            return {
                impact: data.data.weather_impact,
                safe: data.data.weather_impact < 0.5,
                warning: data.data.weather_impact >= 0.5 && data.data.weather_impact < 0.8,
                danger: data.data.weather_impact >= 0.8
            };
        }
    } catch (error) {
        console.error('Error fetching weather impact:', error);
    }
    
    return null;
}

/**
 * Display weather impact indicator on flight card
 * Usage: Call after flight card is rendered
 */
function displayWeatherRisk(flightElement, weatherData) {
    if (!weatherData) return;

    let badgeClass = 'badge-success';
    let badgeText = '✓ Safe';
    let badgeIcon = '☀️';

    if (weatherData.danger) {
        badgeClass = 'badge-danger';
        badgeText = '⚠ Risky';
        badgeIcon = '⛈️';
    } else if (weatherData.warning) {
        badgeClass = 'badge-warning';
        badgeText = '⚠ Caution';
        badgeIcon = '☁️';
    }

    const badge = document.createElement('span');
    badge.className = `badge ${badgeClass} weather-impact`;
    badge.textContent = `${badgeIcon} ${badgeText}`;
    badge.title = `Impact Score: ${(weatherData.impact * 100).toFixed(1)}%`;
    
    flightElement.querySelector('.weather-risk')?.appendChild(badge);
}

/**
 * Example: Loop through all flights and add weather impact
 */
async function updateAllFlightsWeatherImpact() {
    const flights = document.querySelectorAll('.flight-card');
    
    for (const flight of flights) {
        const temp = flight.dataset.temperature;
        const humidity = flight.dataset.humidity;
        const pressure = flight.dataset.pressure;
        const wind = flight.dataset.windSpeed;
        
        if (temp && humidity && pressure && wind) {
            const weatherData = await getWeatherImpact(temp, humidity, pressure, wind);
            displayWeatherRisk(flight, weatherData);
        }
    }
}

// ============================================================================
// CHECKOUT PAGE - BOOKING RISK ASSESSMENT
// ============================================================================

/**
 * Assess booking risk before checkout
 * Call this when user initiates checkout
 */
async function assessBookingRisk(userHistory, bookingAmount, departureDays) {
    try {
        const response = await fetch('/api/ai/predict/risk', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                user_history: userHistory,  // Array of 0/1 values
                booking_amount: parseFloat(bookingAmount),
                departure_days: parseInt(departureDays)
            })
        });

        if (!response.ok) {
            console.error('Risk assessment failed:', response.status);
            return null;
        }

        const data = await response.json();
        
        if (data.success) {
            return {
                riskLevel: data.data.risk_level,
                confidence: data.data.confidence,
                safe: data.data.risk_level < 0.3,
                warning: data.data.risk_level >= 0.3 && data.data.risk_level < 0.7,
                danger: data.data.risk_level >= 0.7
            };
        }
    } catch (error) {
        console.error('Error assessing booking risk:', error);
    }
    
    return null;
}

/**
 * Show risk warning modal before checkout confirmation
 */
function showRiskWarning(riskData) {
    if (!riskData || riskData.safe) return; // Don't show for safe bookings

    let title = '⚠️ Booking Alert';
    let message = 'Please review before proceeding.';
    let icon = '⚠️';

    if (riskData.danger) {
        title = '🚨 High Risk Booking';
        message = 'This booking has a high cancellation or fraud risk. Please verify details carefully.';
        icon = '🚨';
    } else if (riskData.warning) {
        title = '⚠️ Medium Risk Booking';
        message = 'This booking shows some risk factors. Please double-check your information.';
        icon = '⚠️';
    }

    const modal = document.createElement('div');
    modal.className = 'modal fade show risk-warning-modal';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">${icon} ${title}</h5>
                    <button type="button" class="btn-close"></button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                    <div class="risk-details">
                        <p><strong>Risk Score:</strong> ${(riskData.riskLevel * 100).toFixed(1)}%</p>
                        <p><strong>Confidence:</strong> ${(riskData.confidence * 100).toFixed(1)}%</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-risky-booking">Proceed Anyway</button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    new bootstrap.Modal(modal).show();
}

/**
 * Integration with checkout button
 */
function initCheckoutRiskAssessment() {
    const checkoutBtn = document.getElementById('checkout-btn');
    
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            // Get booking data
            const userHistory = JSON.parse(document.getElementById('user-history').value || '[1,0,1]');
            const bookingAmount = document.getElementById('total-amount').value;
            const departureDays = document.getElementById('departure-days').value;
            
            // Assess risk
            const riskData = await assessBookingRisk(userHistory, bookingAmount, departureDays);
            
            // Show warning if needed
            if (riskData && (riskData.warning || riskData.danger)) {
                showRiskWarning(riskData);
                
                // Only proceed if user confirms
                document.getElementById('confirm-risky-booking')?.addEventListener('click', () => {
                    submitCheckout();
                });
            } else {
                // Safe to proceed
                submitCheckout();
            }
        });
    }
}

/**
 * Submit checkout after risk assessment
 */
function submitCheckout() {
    // Your checkout submission logic here
    document.getElementById('checkout-form').submit();
}

// ============================================================================
// INITIALIZATION
// ============================================================================

/**
 * Initialize AI integrations when DOM is ready
 */
document.addEventListener('DOMContentLoaded', () => {
    // Vol page
    if (document.querySelector('.flight-list')) {
        updateAllFlightsWeatherImpact();
    }
    
    // Checkout page
    if (document.querySelector('.checkout-form')) {
        initCheckoutRiskAssessment();
    }
});

// ============================================================================
// HTMX Integration (if using HTMX)
// ============================================================================

/**
 * After HTMX loads flight cards, update weather impact
 */
document.addEventListener('htmx:afterSwap', (event) => {
    if (event.detail.xhr.responseURL.includes('/flights')) {
        updateAllFlightsWeatherImpact();
    }
});

// ============================================================================
// HEALTH CHECK (optional)
// ============================================================================

/**
 * Check if AI service is available on page load
 */
async function checkAiServiceHealth() {
    try {
        const response = await fetch('/api/ai/health');
        const data = await response.json();
        
        if (!data.success) {
            console.warn('AI service is not available');
            // Disable AI features or show warning
            document.body.classList.add('ai-service-unavailable');
        }
    } catch (error) {
        console.warn('Could not reach AI service:', error);
        document.body.classList.add('ai-service-unavailable');
    }
}

// Call on page load
checkAiServiceHealth();
