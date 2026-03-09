<?php
defined('ABSPATH') || exit;

class LendoCare_Product_Admin {

    public function init() {
        // Show rental tab in product data panel
        add_filter('woocommerce_product_data_tabs', [$this, 'add_rental_tab']);

        // Tab content
        add_action('woocommerce_product_data_panels', [$this, 'render_rental_panel']);

        // Show/hide standard panels (shipping, inventory etc.) for rental products
        add_filter('woocommerce_product_data_tabs', [$this, 'adjust_tabs']);

        // Save meta
        add_action('woocommerce_process_product_meta', [$this, 'save_rental_meta']);
    }

    public function add_rental_tab(array $tabs): array {
        $tabs['rental'] = [
            'label'    => __('Rental Settings', 'lendocare-rental'),
            'target'   => 'lendocare_rental_data',
            'class'    => ['show_if_rental'],
            'priority' => 60,
        ];
        return $tabs;
    }

    public function adjust_tabs(array $tabs): array {
        // Hide irrelevant tabs for rental products
        $hide = ['shipping'];
        foreach ($hide as $tab) {
            if (isset($tabs[$tab])) {
                $tabs[$tab]['class'][] = 'hide_if_rental';
            }
        }
        return $tabs;
    }

    public function render_rental_panel() {
        global $post;
        $product_id = $post->ID;

        $pricing_mode   = get_post_meta($product_id, '_rental_pricing_mode', true) ?: 'per_day';
        $price_per_day  = get_post_meta($product_id, '_rental_price_per_day', true);
        $price_per_week = get_post_meta($product_id, '_rental_price_per_week', true);
        $deposit        = get_post_meta($product_id, '_rental_deposit', true);
        $min_days       = get_post_meta($product_id, '_rental_min_days', true) ?: 1;
        $max_days       = get_post_meta($product_id, '_rental_max_days', true) ?: 90;
        $delivery_fee   = get_post_meta($product_id, '_rental_delivery_fee', true);
        $blocked_dates  = get_post_meta($product_id, '_rental_blocked_dates', true);
        ?>
        <div id="lendocare_rental_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <h4 style="padding-left:12px;"><?php _e('Pricing Mode', 'lendocare-rental'); ?></h4>

                <?php woocommerce_wp_select([
                    'id'      => '_rental_pricing_mode',
                    'label'   => __('Charge rental', 'lendocare-rental'),
                    'value'   => $pricing_mode,
                    'options' => [
                        'per_day'  => __('Per Day', 'lendocare-rental'),
                        'per_week' => __('Per Week (calculated from days)', 'lendocare-rental'),
                    ],
                    'desc_tip'    => true,
                    'description' => __('Per Week: price is charged per full week. Partial weeks count as a full week (e.g., 8 days = 2 weeks).', 'lendocare-rental'),
                ]); ?>

                <div class="rental-price-per-day" style="<?php echo $pricing_mode === 'per_week' ? 'display:none;' : ''; ?>">
                    <?php woocommerce_wp_text_input([
                        'id'                => '_rental_price_per_day',
                        'label'             => __('Price per day (' . get_woocommerce_currency_symbol() . ')', 'lendocare-rental'),
                        'type'              => 'number',
                        'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                        'value'             => $price_per_day,
                    ]); ?>
                </div>

                <div class="rental-price-per-week" style="<?php echo $pricing_mode === 'per_day' ? 'display:none;' : ''; ?>">
                    <?php woocommerce_wp_text_input([
                        'id'                => '_rental_price_per_week',
                        'label'             => __('Price per week (' . get_woocommerce_currency_symbol() . ')', 'lendocare-rental'),
                        'type'              => 'number',
                        'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                        'value'             => $price_per_week,
                        'desc_tip'          => true,
                        'description'       => __('This is charged per full week. Days are always rounded up to next week.', 'lendocare-rental'),
                    ]); ?>
                </div>
            </div>

            <div class="options_group">
                <h4 style="padding-left:12px;"><?php _e('Deposit & Delivery', 'lendocare-rental'); ?></h4>

                <?php woocommerce_wp_text_input([
                    'id'                => '_rental_deposit',
                    'label'             => __('Security deposit (' . get_woocommerce_currency_symbol() . ')', 'lendocare-rental'),
                    'type'              => 'number',
                    'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                    'value'             => $deposit,
                    'desc_tip'          => true,
                    'description'       => __('Optional refundable deposit added at checkout.', 'lendocare-rental'),
                ]); ?>

                <?php woocommerce_wp_text_input([
                    'id'                => '_rental_delivery_fee',
                    'label'             => __('Standard delivery fee (' . get_woocommerce_currency_symbol() . ')', 'lendocare-rental'),
                    'type'              => 'number',
                    'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                    'value'             => $delivery_fee,
                    'desc_tip'          => true,
                    'description'       => __('Base delivery/collection fee. Dynamic pricing (out-of-hours, weekends) can be configured in Rental Settings.', 'lendocare-rental'),
                ]); ?>
            </div>

            <div class="options_group">
                <h4 style="padding-left:12px;"><?php _e('Rental Duration Limits', 'lendocare-rental'); ?></h4>

                <?php woocommerce_wp_text_input([
                    'id'                => '_rental_min_days',
                    'label'             => __('Minimum rental (days)', 'lendocare-rental'),
                    'type'              => 'number',
                    'custom_attributes' => ['step' => '1', 'min' => '1'],
                    'value'             => $min_days,
                ]); ?>

                <?php woocommerce_wp_text_input([
                    'id'                => '_rental_max_days',
                    'label'             => __('Maximum rental (days)', 'lendocare-rental'),
                    'type'              => 'number',
                    'custom_attributes' => ['step' => '1', 'min' => '1'],
                    'value'             => $max_days,
                ]); ?>
            </div>

            <div class="options_group">
                <h4 style="padding-left:12px;"><?php _e('Blocked / Unavailable Dates', 'lendocare-rental'); ?></h4>
                <p class="form-field">
                    <label><?php _e('Blocked dates', 'lendocare-rental'); ?></label>
                    <textarea name="_rental_blocked_dates" id="_rental_blocked_dates" rows="4" style="width:70%;" placeholder='["2025-12-25","2025-12-26"]'><?php echo esc_textarea($blocked_dates); ?></textarea>
                    <span class="description"><?php _e('JSON array of YYYY-MM-DD dates to block (e.g. bank holidays).', 'lendocare-rental'); ?></span>
                </p>
            </div>
        </div>
        <?php
    }

    public function save_rental_meta(int $product_id) {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'rental') return;

        $fields = [
            '_rental_pricing_mode'   => 'sanitize_text_field',
            '_rental_price_per_day'  => 'floatval',
            '_rental_price_per_week' => 'floatval',
            '_rental_deposit'        => 'floatval',
            '_rental_min_days'       => 'intval',
            '_rental_max_days'       => 'intval',
            '_rental_delivery_fee'   => 'floatval',
            '_rental_blocked_dates'  => 'sanitize_textarea_field',
        ];

        foreach ($fields as $key => $sanitizer) {
            if (isset($_POST[$key])) {
                $value = call_user_func($sanitizer, $_POST[$key]);
                update_post_meta($product_id, $key, $value);
            }
        }

        // Set WC price meta so WooCommerce can display it in admin lists
        $pricing_mode = sanitize_text_field($_POST['_rental_pricing_mode'] ?? 'per_day');
        if ($pricing_mode === 'per_day') {
            update_post_meta($product_id, '_price', floatval($_POST['_rental_price_per_day'] ?? 0));
            update_post_meta($product_id, '_regular_price', floatval($_POST['_rental_price_per_day'] ?? 0));
        } else {
            update_post_meta($product_id, '_price', floatval($_POST['_rental_price_per_week'] ?? 0));
            update_post_meta($product_id, '_regular_price', floatval($_POST['_rental_price_per_week'] ?? 0));
        }
    }
}
