<?php
defined('ABSPATH') || exit;

class LendoCare_Rental_Product extends WC_Product {

    public function __construct($product) {
        $this->product_type = 'rental';
        parent::__construct($product);
    }

    public function get_type(): string {
        return 'rental';
    }

    // ── Rental-specific meta getters ──────────────────────────────────────────

    public function get_pricing_mode(): string {
        return $this->get_meta('_rental_pricing_mode') ?: 'per_day';
    }

    public function get_price_per_day(): float {
        return (float) ($this->get_meta('_rental_price_per_day') ?: 0);
    }

    public function get_price_per_week(): float {
        return (float) ($this->get_meta('_rental_price_per_week') ?: 0);
    }

    public function get_deposit_amount(): float {
        return (float) ($this->get_meta('_rental_deposit') ?: 0);
    }

    public function get_min_rental_days(): int {
        return (int) ($this->get_meta('_rental_min_days') ?: 1);
    }

    public function get_max_rental_days(): int {
        return (int) ($this->get_meta('_rental_max_days') ?: 90);
    }

    public function get_delivery_fee(): float {
        return (float) ($this->get_meta('_rental_delivery_fee') ?: 0);
    }

    public function get_blocked_dates(): array {
        $raw = $this->get_meta('_rental_blocked_dates');
        return $raw ? json_decode($raw, true) : [];
    }

    // ── Price display ─────────────────────────────────────────────────────────

    public function get_price_html($deprecated = ''): string {
        $mode = $this->get_pricing_mode();

        if ($mode === 'per_day') {
            $price = wc_price($this->get_price_per_day());
            return sprintf(__('%s <span class="per-unit">/ day</span>', 'lendocare-rental'), $price);
        } else {
            $price = wc_price($this->get_price_per_week());
            return sprintf(__('%s <span class="per-unit">/ week</span>', 'lendocare-rental'), $price);
        }
    }

    /**
     * Rentals are not purchasable through the normal add-to-cart flow.
     * The booking form handles the cart addition via AJAX.
     */
    public function is_purchasable(): bool {
        return true;
    }

    public function is_in_stock(): bool {
        return true;
    }

    public function add_to_cart_url(): string {
        return get_permalink($this->get_id());
    }

    public function add_to_cart_text(): string {
        return __('Book Now', 'lendocare-rental');
    }

    public function single_add_to_cart_text(): string {
        return __('Book Now', 'lendocare-rental');
    }
}
