<?php
defined('ABSPATH') || exit;

class LendoCare_Cart_Handler {

    public function init() {
        // Store rental data in cart item
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_rental_data_to_cart'], 10, 2);

        // Recalculate rental price in cart
        add_action('woocommerce_before_calculate_totals', [$this, 'set_cart_item_price'], 20);

        // Display rental details in cart
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);

        // Validate rental data before checkout
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_before_add_to_cart'], 10, 2);

        // Block direct add-to-cart for rental products (must come through booking form)
        add_filter('woocommerce_add_to_cart_validation', [$this, 'require_booking_data'], 10, 2);
    }

    /**
     * Attach booking meta to the cart item.
     * Called via AJAX after the booking form is submitted.
     */
    public function add_rental_data_to_cart(array $cart_item_data, int $product_id): array {
        // Data is set in the AJAX handler and passed via session/post; we pick it up here.
        $rental_data = WC()->session->get('lendocare_pending_booking_' . $product_id);
        if ($rental_data) {
            $cart_item_data['lendocare_rental'] = $rental_data;
            WC()->session->__unset('lendocare_pending_booking_' . $product_id);
        }
        return $cart_item_data;
    }

    /**
     * Override the cart item price with the calculated rental price.
     */
    public function set_cart_item_price(\WC_Cart $cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['lendocare_rental'])) {
                $data  = $cart_item['lendocare_rental'];
                $price = (float) $data['rental_price'];

                // Add delivery fee to price
                if (isset($data['delivery_fee'])) {
                    $price += (float) $data['delivery_fee'];
                }

                $cart_item['data']->set_price($price);
            }
        }
    }

    /**
     * Show rental details in cart & checkout page.
     */
    public function display_cart_item_data(array $item_data, array $cart_item): array {
        if (!isset($cart_item['lendocare_rental'])) return $item_data;

        $d = $cart_item['lendocare_rental'];

        $item_data[] = [
            'key'   => __('Pickup date', 'lendocare-rental'),
            'value' => esc_html(date_i18n(get_option('date_format'), strtotime($d['pickup_date']))),
        ];
        $item_data[] = [
            'key'   => __('Return date', 'lendocare-rental'),
            'value' => esc_html(date_i18n(get_option('date_format'), strtotime($d['dropoff_date']))),
        ];
        $item_data[] = [
            'key'   => __('Duration', 'lendocare-rental'),
            'value' => esc_html($d['billing_unit']),
        ];
        if (!empty($d['delivery_address'])) {
            $item_data[] = [
                'key'   => __('Delivery address', 'lendocare-rental'),
                'value' => esc_html($d['delivery_address']),
            ];
        }
        $item_data[] = [
            'key'   => __('Delivery postcode', 'lendocare-rental'),
            'value' => esc_html($d['postcode']),
        ];
        if (!empty($d['collection_address'])) {
            $same = !empty($d['same_address']) && $d['same_address'] === '1';
            $item_data[] = [
                'key'   => __('Collection address', 'lendocare-rental'),
                'value' => $same ? esc_html__('Same as delivery', 'lendocare-rental') : esc_html($d['collection_address']),
            ];
        }
        if (!empty($d['delivery_time'])) {
            $time_label = date('g:ia', strtotime($d['delivery_time']));
            $type_label = ($d['delivery_type'] ?? 'standard') === 'ooh' ? ' (out-of-hours)' : '';
            $item_data[] = [
                'key'   => __('Preferred delivery time', 'lendocare-rental'),
                'value' => esc_html($time_label . $type_label),
            ];
        }
        $item_data[] = [
            'key'   => __('Delivery fee', 'lendocare-rental'),
            'value' => wc_price($d['delivery_fee']),
        ];
        if (!empty($d['deposit'])) {
            $item_data[] = [
                'key'   => __('Security deposit (refundable)', 'lendocare-rental'),
                'value' => wc_price($d['deposit']),
            ];
        }

        return $item_data;
    }

    /**
     * Block adding rental products to cart without valid booking data.
     */
    public function require_booking_data(bool $valid, int $product_id): bool {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'rental') return $valid;

        $pending = WC()->session->get('lendocare_pending_booking_' . $product_id);
        if (!$pending) {
            wc_add_notice(__('Please complete the booking form to add this rental to your cart.', 'lendocare-rental'), 'error');
            return false;
        }

        return $valid;
    }

    public function validate_before_add_to_cart(bool $valid, int $product_id): bool {
        return $valid;
    }
}
