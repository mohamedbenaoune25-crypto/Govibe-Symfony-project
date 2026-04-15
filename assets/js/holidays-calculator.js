// Calcul du prix avec supplément de jours fériés
function initializeHolidayCalculation(holidays, supplementPercentage) {
    var estimatedTotalEl = document.getElementById('reservation-estimated-total');
    var priceBreakdownEl = document.getElementById('reservation-price-breakdown');
    var basePriceEl = document.getElementById('reservation-base-price');
    var holidayNightsEl = document.getElementById('reservation-holiday-nights');
    var supplementEl = document.getElementById('reservation-supplement');
    var holidayInfoEl = document.getElementById('reservation-holiday-info');
    var supplementInfoEl = document.getElementById('reservation-supplement-info');
    var dateDebutInput = document.getElementById('reservation_form_dateDebut');
    var dateFinInput = document.getElementById('reservation_form_dateFin');
    var chambreSelect = document.getElementById('reservation_form_chambre');

    function dateToString(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function parseDate(value) {
        if (!value) return null;
        var date = new Date(value + 'T00:00:00');
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function countHolidayNights(start, end) {
        var count = 0;
        var current = new Date(start);
        while (current < end) {
            if (holidays.indexOf(dateToString(current)) !== -1) {
                count++;
            }
            current.setDate(current.getDate() + 1);
        }
        return count;
    }

    function getUnitPrice(option) {
        var standard = parseFloat(option.getAttribute('data-prix-standard') || '0');
        var high = parseFloat(option.getAttribute('data-prix-haute') || '0');
        var low = parseFloat(option.getAttribute('data-prix-basse') || '0');
        return standard > 0 ? standard : (high > 0 ? high : (low > 0 ? low : 0));
    }

    function updateTotal() {
        if (!estimatedTotalEl || !chambreSelect) {
            if (priceBreakdownEl) priceBreakdownEl.style.display = 'none';
            return;
        }

        var option = chambreSelect.selectedOptions.length ? chambreSelect.selectedOptions[0] : null;
        if (!option || option.value === '') {
            if (estimatedTotalEl) estimatedTotalEl.textContent = '-';
            if (priceBreakdownEl) priceBreakdownEl.style.display = 'none';
            return;
        }

        var start = parseDate(dateDebutInput ? dateDebutInput.value : '');
        var end = parseDate(dateFinInput ? dateFinInput.value : '');
        var unit = getUnitPrice(option);

        if (!start || !end || end <= start || unit <= 0) {
            if (estimatedTotalEl) estimatedTotalEl.textContent = '-';
            if (priceBreakdownEl) priceBreakdownEl.style.display = 'none';
            return;
        }

        var nights = Math.round((end.getTime() - start.getTime()) / (24 * 60 * 60 * 1000));
        if (nights <= 0) {
            if (estimatedTotalEl) estimatedTotalEl.textContent = '-';
            if (priceBreakdownEl) priceBreakdownEl.style.display = 'none';
            return;
        }

        var basePrice = unit * nights;
        var holidayNights = countHolidayNights(start, end);
        var supplementAmount = holidayNights > 0 ? (unit * (supplementPercentage / 100)) * holidayNights : 0;
        var totalPrice = basePrice + supplementAmount;

        if (basePriceEl) basePriceEl.textContent = basePrice.toFixed(2) + ' €';
        if (estimatedTotalEl) estimatedTotalEl.textContent = totalPrice.toFixed(2) + ' €';

        if (holidayNights > 0) {
            if (holidayNightsEl) holidayNightsEl.textContent = holidayNights + ' nuit' + (holidayNights > 1 ? 's' : '');
            if (supplementEl) supplementEl.textContent = '+ ' + supplementAmount.toFixed(2) + ' €';
            if (holidayInfoEl) holidayInfoEl.style.display = 'flex';
            if (supplementInfoEl) supplementInfoEl.style.display = 'flex';
        } else {
            if (holidayInfoEl) holidayInfoEl.style.display = 'none';
            if (supplementInfoEl) supplementInfoEl.style.display = 'none';
        }

        if (priceBreakdownEl) priceBreakdownEl.style.display = 'block';
    }

    // Attacher les event listeners
    if (chambreSelect) chambreSelect.addEventListener('change', updateTotal);
    if (dateDebutInput) dateDebutInput.addEventListener('change', updateTotal);
    if (dateFinInput) dateFinInput.addEventListener('change', updateTotal);

    updateTotal();
}
