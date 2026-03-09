<?php
defined('ABSPATH') || exit;

class LendoCare_Frontend {

    public function init() {
        // Replace the standard single-product add-to-cart template for rental products
        add_action('woocommerce_rental_add_to_cart', [$this, 'rental_add_to_cart_template']);

        // Remove default add-to-cart button for rental products on archives
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'archive_book_button'], 10, 2);
    }

    public function rental_add_to_cart_template() {
        global $product;
        if (!$product || $product->get_type() !== 'rental') return;

        $min_days = $product->get_min_rental_days();
        $max_days = $product->get_max_rental_days();
        $mode     = $product->get_pricing_mode();
        $blocked  = json_encode($product->get_blocked_dates());

        include LENDOCARE_PATH . 'templates/single-rental-form.php';
    }

    public function archive_book_button(string $link, $product): string {
        if ($product->get_type() !== 'rental') return $link;
        $url = get_permalink($product->get_id());
        return sprintf(
            '<a href="%s" class="button lendocare-book-btn">%s</a>',
            esc_url($url),
            esc_html__('Book Now', 'lendocare-rental')
        );
    }
}
