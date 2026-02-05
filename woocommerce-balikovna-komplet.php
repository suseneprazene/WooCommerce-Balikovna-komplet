<?php
/**
 * Plugin Name: WooCommerce-Balikovna-komplet
 * Plugin URI: https://github.com/suseneprazene/WooCommerce-Balikovna-komplet
 * Description: Kompletní integrace Balíkovny pro WooCommerce s výběrem výdejního místa a tiskem štítků
 * Version: 1.0.0
 * Author: suseneprazene
 * Text Domain: wc-balikovna
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

defined('ABSPATH') || exit;

// Definuj konstanty
define('WC_BALIKOVNA_VERSION', '1.0.0');
define('WC_BALIKOVNA_FILE', __FILE__);
define('WC_BALIKOVNA_PATH', plugin_dir_path(__FILE__));
define('WC_BALIKOVNA_URL', plugin_dir_url(__FILE__));

// Zkontroluj závislosti
add_action('plugins_loaded', 'wc_balikovna_init');

/**
 * Initialize the plugin
 */
function wc_balikovna_init() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . __('WooCommerce-Balikovna-komplet vyžaduje aktivní WooCommerce plugin.', 'wc-balikovna') . '</p></div>';
        });
        return;
    }
    
    // Načti třídy
    require_once WC_BALIKOVNA_PATH . 'includes/class-balikovna-api.php';
    require_once WC_BALIKOVNA_PATH . 'includes/class-balikovna-shipping.php';
    require_once WC_BALIKOVNA_PATH . 'includes/class-balikovna-admin.php';
    require_once WC_BALIKOVNA_PATH . 'includes/class-balikovna-order.php';
    require_once WC_BALIKOVNA_PATH . 'includes/class-balikovna-label-generator.php';
    
    // Inicializuj plugin
    WC_Balikovna_Admin::init();
    WC_Balikovna_Order::init();
    
    // Registruj shipping method
    add_filter('woocommerce_shipping_methods', 'wc_balikovna_register_shipping_method');
}

/**
 * Register shipping method
 *
 * @param array $methods
 * @return array
 */
function wc_balikovna_register_shipping_method($methods) {
    $methods['balikovna'] = 'WC_Balikovna_Shipping';
    return $methods;
}

// Aktivace pluginu
register_activation_hook(__FILE__, 'wc_balikovna_activate');

/**
 * Plugin activation callback
 */
function wc_balikovna_activate() {
    // Vytvoř potřebné DB tabulky nebo options
    // IMPORTANT: API credentials MUST be configured through WooCommerce > Settings > Shipping > Balíkovna
    // WARNING: Never commit real production API credentials to version control
    
    // Set default options if they don't exist
    if (get_option('wc_balikovna_api_token') === false) {
        add_option('wc_balikovna_api_token', '');
    }
    if (get_option('wc_balikovna_private_key') === false) {
        add_option('wc_balikovna_private_key', '');
    }
    if (get_option('wc_balikovna_default_weight') === false) {
        add_option('wc_balikovna_default_weight', '2.5');
    }
}

