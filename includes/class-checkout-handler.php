<?php
defined('ABSPATH') || exit;

class LendoCare_Checkout_Handler {
    public function init() {
        // Add deposit as separate fee at checkout
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_deposit_fee']);
    }

    public function add_deposit_fee(\WC_Cart $cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        $total_deposit = 0;
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['lendocare_rental']['deposit'])) {
                $total_deposit += (float) $cart_item['lendocare_rental']['deposit'];
            }
        }

        if ($total_deposit > 0) {
            $cart->add_fee(
                __('Security Deposit (refundable)', 'lendocare-rental'),
                $total_deposit,
                false // not taxable
            );
        }
    }
}

class LendoCare_Order_Handler {
    public function init() {
        // Save booking meta to order line items
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);

        // Display in admin order view
        add_action('woocommerce_after_order_itemmeta', [$this, 'display_order_item_meta'], 10, 3);
    }

    public function save_order_item_meta(\WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order) {
        if (!isset($values['lendocare_rental'])) return;

        $d = $values['lendocare_rental'];

        $item->add_meta_data(__('Pickup Date', 'lendocare-rental'), $d['pickup_date']);
        $item->add_meta_data(__('Return Date', 'lendocare-rental'), $d['dropoff_date']);
        $item->add_meta_data(__('Duration', 'lendocare-rental'), $d['billing_unit']);
        $item->add_meta_data(__('Delivery Postcode', 'lendocare-rental'), $d['postcode']);
        $item->add_meta_data(__('Rental Price', 'lendocare-rental'), wc_price($d['rental_price']));
        $item->add_meta_data(__('Delivery Fee', 'lendocare-rental'), wc_price($d['delivery_fee']));
        $item->add_meta_data(__('Security Deposit', 'lendocare-rental'), wc_price($d['deposit']));
        $item->add_meta_data('_lendocare_rental_raw', wp_json_encode($d)); // hidden raw for processing
    }

    public function display_order_item_meta(int $item_id, \WC_Order_Item $item, $product) {
        // WooCommerce will display the meta_data automatically in admin.
    }
}
