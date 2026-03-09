<?php
/**
 * Plugin Name: Custom Rental - Tosin
 * Plugin URI: https://adaptahome.co.uk
 * Description: WooCommerce rental booking system with per-day/per-week pricing, Greater London delivery zone validation, and date-based pricing.
 * Version: 1.0.0
 * Author: Tosin
 * Text Domain: lendocare-rental
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

define('LENDOCARE_VERSION', '1.0.0');
define('LENDOCARE_PATH', plugin_dir_path(__FILE__));
define('LENDOCARE_URL', plugin_dir_url(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'LendoCare\\';
    $base_dir = LENDOCARE_PATH . 'includes/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

class LendoCare_Rental_Plugin {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                echo '<div class="error"><p><strong>Custom Rental – Tosin</strong> requires WooCommerce to be installed and active.</p></div>';
            });
            return;
        }

        $this->load_includes();
        $this->init_hooks();
    }

    private function load_includes() {
        require_once LENDOCARE_PATH . 'includes/class-product-type.php';
        require_once LENDOCARE_PATH . 'includes/class-rental-product.php';
        require_once LENDOCARE_PATH . 'includes/class-product-admin.php';
        require_once LENDOCARE_PATH . 'includes/class-delivery-zone.php';
        require_once LENDOCARE_PATH . 'includes/class-booking-calculator.php';
        require_once LENDOCARE_PATH . 'includes/class-cart-handler.php';
        require_once LENDOCARE_PATH . 'includes/class-checkout-handler.php';
        require_once LENDOCARE_PATH . 'includes/class-order-handler.php';
        require_once LENDOCARE_PATH . 'includes/class-frontend.php';
        require_once LENDOCARE_PATH . 'includes/class-ajax.php';
        require_once LENDOCARE_PATH . 'includes/class-admin-settings.php';
    }

    private function init_hooks() {
        // Register product type
        add_filter('product_type_selector', ['LendoCare_Product_Type', 'add_product_type']);
        add_filter('woocommerce_product_class', ['LendoCare_Product_Type', 'product_class'], 10, 2);

        // Admin
        $admin = new LendoCare_Product_Admin();
        $admin->init();

        $settings = new LendoCare_Admin_Settings();
        $settings->init();

        // Frontend
        $frontend = new LendoCare_Frontend();
        $frontend->init();

        // Cart / Checkout
        $cart = new LendoCare_Cart_Handler();
        $cart->init();

        $checkout = new LendoCare_Checkout_Handler();
        $checkout->init();

        $orders = new LendoCare_Order_Handler();
        $orders->init();

        // AJAX
        $ajax = new LendoCare_Ajax();
        $ajax->init();

        // Scripts & styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('lendocare-frontend', LENDOCARE_URL . 'assets/css/frontend.css', [], LENDOCARE_VERSION);
        wp_enqueue_style('lendocare-datepicker-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], null);
        wp_enqueue_script('lendocare-datepicker', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], null, true);
        wp_enqueue_script('lendocare-frontend', LENDOCARE_URL . 'assets/js/frontend.js', ['jquery', 'lendocare-datepicker'], LENDOCARE_VERSION, true);

        wp_localize_script('lendocare-frontend', 'lendocareData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('lendocare_nonce'),
            'currency' => get_woocommerce_currency_symbol(),
        ]);
    }

    public function enqueue_admin_scripts($hook) {
        global $post;
        if (($hook === 'post.php' || $hook === 'post-new.php') && isset($post) && $post->post_type === 'product') {
            wp_enqueue_style('lendocare-admin', LENDOCARE_URL . 'assets/css/admin.css', [], LENDOCARE_VERSION);
            wp_enqueue_script('lendocare-admin', LENDOCARE_URL . 'assets/js/admin.js', ['jquery'], LENDOCARE_VERSION, true);
        }
    }
}

// Activation hook
register_activation_hook(__FILE__, function () {
    require_once LENDOCARE_PATH . 'includes/class-installer.php';
    LendoCare_Installer::install();
});

LendoCare_Rental_Plugin::instance();
