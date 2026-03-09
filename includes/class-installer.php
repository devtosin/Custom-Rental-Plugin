<?php
defined('ABSPATH') || exit;

class LendoCare_Installer {
    public static function install() {
        // Create tables if needed in future versions
        // For now, register the product type with WooCommerce
        self::maybe_create_terms();
        flush_rewrite_rules();
    }

    private static function maybe_create_terms() {
        // Ensure the 'rental' product type term exists in WC's product_type taxonomy
        if (!term_exists('rental', 'product_type')) {
            wp_insert_term('rental', 'product_type');
        }
    }
}
