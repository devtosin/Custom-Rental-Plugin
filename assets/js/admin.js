jQuery(function ($) {
    // Toggle per-day / per-week price fields based on pricing mode
    function togglePricingFields() {
        const mode = $('#_rental_pricing_mode').val();
        if (mode === 'per_week') {
            $('.rental-price-per-day').hide();
            $('.rental-price-per-week').show();
        } else {
            $('.rental-price-per-day').show();
            $('.rental-price-per-week').hide();
        }
    }

    $('#_rental_pricing_mode').on('change', togglePricingFields);
    togglePricingFields(); // run on load
});
