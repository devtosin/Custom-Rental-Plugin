<?php
defined('ABSPATH') || exit;

class LendoCare_Ajax {

    public function init() {
        add_action('wp_ajax_lendocare_validate_postcode',    [$this, 'validate_postcode']);
        add_action('wp_ajax_nopriv_lendocare_validate_postcode', [$this, 'validate_postcode']);

        add_action('wp_ajax_lendocare_calculate_price',    [$this, 'calculate_price']);
        add_action('wp_ajax_nopriv_lendocare_calculate_price', [$this, 'calculate_price']);

        add_action('wp_ajax_lendocare_add_to_cart',    [$this, 'add_to_cart']);
        add_action('wp_ajax_nopriv_lendocare_add_to_cart', [$this, 'add_to_cart']);
    }

    private function verify_nonce() {
        if (!check_ajax_referer('lendocare_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'lendocare-rental')], 403);
        }
    }

    public function validate_postcode() {
        $this->verify_nonce();

        $postcode = sanitize_text_field($_POST['postcode'] ?? '');
        if (empty($postcode)) {
            wp_send_json_error(['message' => __('Please enter a postcode.', 'lendocare-rental')]);
        }

        $result = LendoCare_Delivery_Zone::validate_postcode($postcode);

        if ($result['valid']) {
            wp_send_json_success([
                'postcode'  => $result['postcode'],
                'area_name' => $result['area_name'],
                'message'   => sprintf(
                    __('Great! We deliver to %s.', 'lendocare-rental'),
                    $result['area_name'] ?: $result['postcode']
                ),
            ]);
        } else {
            wp_send_json_error([
                'postcode' => $result['postcode'],
                'message'  => $result['error'] ?: __('Sorry, we currently only deliver within Greater London. Please check your postcode.', 'lendocare-rental'),
            ]);
        }
    }

    public function calculate_price() {
        $this->verify_nonce();

        $product_id   = (int) ($_POST['product_id'] ?? 0);
        $pickup_date  = sanitize_text_field($_POST['pickup_date'] ?? '');
        $dropoff_date = sanitize_text_field($_POST['dropoff_date'] ?? '');

        if (!$product_id || !$pickup_date || !$dropoff_date) {
            wp_send_json_error(['message' => __('Missing required fields.', 'lendocare-rental')]);
        }

        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'rental') {
            wp_send_json_error(['message' => __('Invalid product.', 'lendocare-rental')]);
        }

        // Validate dates
        $pickup  = DateTime::createFromFormat('Y-m-d', $pickup_date);
        $dropoff = DateTime::createFromFormat('Y-m-d', $dropoff_date);

        if (!$pickup || !$dropoff || $dropoff <= $pickup) {
            wp_send_json_error(['message' => __('Return date must be after pickup date.', 'lendocare-rental')]);
        }

        $days = LendoCare_Booking_Calculator::get_days($pickup_date, $dropoff_date);

        // Check min/max
        if ($days < $product->get_min_rental_days()) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Minimum rental period is %d day(s).', 'lendocare-rental'),
                    $product->get_min_rental_days()
                ),
            ]);
        }
        if ($days > $product->get_max_rental_days()) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Maximum rental period is %d days.', 'lendocare-rental'),
                    $product->get_max_rental_days()
                ),
            ]);
        }

        // Frontend calculates delivery fee based on time/day/bank holiday
        $delivery_fee = isset($_POST['delivery_fee']) && is_numeric($_POST['delivery_fee'])
            ? (float) $_POST['delivery_fee']
            : $product->get_delivery_fee();
        $breakdown    = LendoCare_Booking_Calculator::full_breakdown($product, $days, $delivery_fee);

        wp_send_json_success([
            'days'          => $days,
            'weeks'         => $breakdown['weeks'],
            'billing_unit'  => $breakdown['billing_unit'],
            'mode'          => $breakdown['mode'],
            'rental_price'  => $breakdown['rental_price'],
            'rental_price_f'=> wc_price($breakdown['rental_price']),
            'delivery_fee'  => $breakdown['delivery_fee'],
            'delivery_fee_f'=> wc_price($breakdown['delivery_fee']),
            'deposit'       => $breakdown['deposit'],
            'deposit_f'     => wc_price($breakdown['deposit']),
            'total'         => $breakdown['total'],
            'total_f'       => wc_price($breakdown['total']),
        ]);
    }

    public function add_to_cart() {
        $this->verify_nonce();

        $product_id       = (int) ($_POST['product_id'] ?? 0);
        $pickup_date      = sanitize_text_field($_POST['pickup_date'] ?? '');
        $dropoff_date     = sanitize_text_field($_POST['dropoff_date'] ?? '');
        $postcode         = sanitize_text_field($_POST['postcode'] ?? '');
        $delivery_address    = sanitize_textarea_field($_POST['delivery_address'] ?? '');
        $collection_address  = sanitize_textarea_field($_POST['collection_address'] ?? '');
        $same_address        = sanitize_text_field($_POST['same_address'] ?? '1');
        $delivery_time       = sanitize_text_field($_POST['delivery_time'] ?? '');
        $delivery_type    = sanitize_text_field($_POST['delivery_type'] ?? 'standard');

        // Validate core fields
        if (!$product_id || !$pickup_date || !$dropoff_date || !$postcode) {
            wp_send_json_error(['message' => __('Please complete all booking fields.', 'lendocare-rental')]);
        }

        // Validate postcode — airport bookings bypass zone check
        $is_airport_booking = ($postcode === 'AIRPORT' || !empty($_POST['delivery_airport']) && $_POST['delivery_airport'] !== 'none');
        if ($is_airport_booking) {
            $zone_check = ['valid' => true, 'postcode' => $postcode];
        } else {
            $zone_check = LendoCare_Delivery_Zone::validate_postcode($postcode);
            if (!$zone_check['valid']) {
                wp_send_json_error(['message' => __('Your postcode is outside our delivery zone.', 'lendocare-rental')]);
            }
        }

        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'rental') {
            wp_send_json_error(['message' => __('Invalid product.', 'lendocare-rental')]);
        }

        $days = LendoCare_Booking_Calculator::get_days($pickup_date, $dropoff_date);

        // Use frontend-calculated delivery fee (accounts for time/day/bank holiday)
        $delivery_fee = isset($_POST['delivery_fee']) && is_numeric($_POST['delivery_fee'])
            ? (float) $_POST['delivery_fee']
            : ($delivery_type === 'ooh' ? 150.0 : 100.0);

        $breakdown = LendoCare_Booking_Calculator::full_breakdown($product, $days, $delivery_fee);

        // Check blocked dates
        $blocked = $product->get_blocked_dates();
        $pickup_obj  = new DateTime($pickup_date);
        $dropoff_obj = new DateTime($dropoff_date);
        $interval    = new DateInterval('P1D');
        $period      = new DatePeriod($pickup_obj, $interval, $dropoff_obj);
        foreach ($period as $date) {
            if (in_array($date->format('Y-m-d'), $blocked, true)) {
                wp_send_json_error(['message' => __('Your selected dates include unavailable dates. Please choose different dates.', 'lendocare-rental')]);
            }
        }

        // Store booking data in session for cart handler to pick up
        $booking_data = [
            'product_id'         => $product_id,
            'pickup_date'        => $pickup_date,
            'dropoff_date'       => $dropoff_date,
            'postcode'           => $zone_check['postcode'],
            'delivery_address'   => $delivery_address,
            'collection_address' => $collection_address,
            'same_address'       => $same_address,
            'delivery_time'      => $delivery_time,
            'collection_time'    => $collection_time,
            'delivery_airport'   => $delivery_airport,
            'collection_airport' => $collection_airport,
            'delivery_ooh'       => $delivery_ooh,
            'collection_ooh'     => $collection_ooh,
            'delivery_type'      => $delivery_type,
            'days'               => $breakdown['days'],
            'weeks'              => $breakdown['weeks'],
            'billing_unit'       => $breakdown['billing_unit'],
            'mode'               => $breakdown['mode'],
            'rental_price'       => $breakdown['rental_price'],
            'delivery_fee'       => $breakdown['delivery_fee'],
            'deposit'            => $breakdown['deposit'],
            'total'              => $breakdown['total'],
        ];

        WC()->session->set('lendocare_pending_booking_' . $product_id, $booking_data);

        // Add to cart
        $cart_item_key = WC()->cart->add_to_cart($product_id, 1);

        if ($cart_item_key) {
            wp_send_json_success([
                'message'       => __('Booking added to cart!', 'lendocare-rental'),
                'cart_url'      => wc_get_cart_url(),
                'checkout_url'  => wc_get_checkout_url(),
            ]);
        } else {
            wp_send_json_error(['message' => __('Could not add booking to cart. Please try again.', 'lendocare-rental')]);
        }
    }
}
