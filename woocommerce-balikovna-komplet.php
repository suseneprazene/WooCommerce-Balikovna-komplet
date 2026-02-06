<?php
/**
 * Plugin Name: WooCommerce Balíkovna Komplet
 * Plugin URI: https://github.com/suseneprazene/WooCommerce-Balikovna-komplet
 * Description: Integrace Balíkoven České pošty do WooCommerce s podporou výběru pobočky při checkoutu
 * Version: 1.0.2
 * Author: suseneprazene
 * Author URI: https://github.com/suseneprazene
 * Text Domain: wc-balikovna-komplet
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.5
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * 
 * GitHub Plugin URI: suseneprazene/WooCommerce-Balikovna-komplet
 * GitHub Branch: main
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WC_BALIKOVNA_VERSION', '1.0.2');
define('WC_BALIKOVNA_PLUGIN_FILE', __FILE__);
define('WC_BALIKOVNA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_BALIKOVNA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_BALIKOVNA_API_URL', 'http://napostu.ceskaposta.cz/vystupy/balikovny.xml');

/**
 * Check if WooCommerce is active
 */
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

/**
 * Main plugin class
 */
class WC_Balikovna_Komplet
{
    /**
     * The single instance of the class
     *
     * @var WC_Balikovna_Komplet
     */
    protected static $_instance = null;

    /**
     * Main WC_Balikovna_Komplet Instance
     *
     * Ensures only one instance of WC_Balikovna_Komplet is loaded or can be loaded
     *
     * @return WC_Balikovna_Komplet - Main instance
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * WC_Balikovna_Komplet Constructor
     */
    public function __construct()
    {
        // Declare compatibility BEFORE WooCommerce initializes
        add_action('before_woocommerce_init', array($this, 'declare_compatibility'));
        
        // Hook into plugins_loaded to ensure WooCommerce is loaded first
        add_action('plugins_loaded', array($this, 'check_woocommerce'), 10);
    }

    /**
     * Declare HPOS and other WooCommerce compatibility features
     */
    public function declare_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare High-Performance Order Storage (HPOS) compatibility
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            // Declare Cart & Checkout Blocks compatibility (not yet supported)
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
        }
    }

    /**
     * Check if WooCommerce is active and initialize plugin
     */
    public function check_woocommerce()
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Include required files
        $this->includes();

        // Initialize the plugin
        $this->init_hooks();
        
        // Initialize plugin components immediately
        $this->init();
    }

    /**
     * Show notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice()
    {
        echo '<div class="error"><p><strong>' . esc_html__('WooCommerce Balíkovna Komplet', 'wc-balikovna-komplet') . '</strong> ' . esc_html__('vyžaduje aktivní WooCommerce plugin.', 'wc-balikovna-komplet') . '</p></div>';
    }

    /**
     * Include required files
     */
    private function includes()
    {
        // Core classes
        require_once WC_BALIKOVNA_PLUGIN_DIR . 'includes/class-wc-balikovna-install.php';
        require_once WC_BALIKOVNA_PLUGIN_DIR . 'includes/class-wc-balikovna-api.php';
        require_once WC_BALIKOVNA_PLUGIN_DIR . 'includes/class-wc-balikovna-shipping.php';
        require_once WC_BALIKOVNA_PLUGIN_DIR . 'includes/class-wc-balikovna-checkout.php';
        require_once WC_BALIKOVNA_PLUGIN_DIR . 'includes/class-wc-balikovna-admin.php';
        require_once WC_BALIKOVNA_PLUGIN_DIR . 'includes/class-wc-balikovna-order.php';
        require_once WC_BALIKOVNA_PLUGIN_DIR . 'includes/class-wc-balikovna-label.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Register activation hook
        register_activation_hook(__FILE__, array('WC_Balikovna_Install', 'install'));

        // Register deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Add shipping method
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
    }

    /**
     * Initialize plugin components
     */
    public function init()
    {
        // Initialize API endpoints
        WC_Balikovna_API::instance();

        // Initialize checkout functionality
        WC_Balikovna_Checkout::instance();

        // Initialize admin functionality
        if (is_admin()) {
            WC_Balikovna_Admin::instance();
            WC_Balikovna_Label::instance();
        }

        // Initialize order functionality
        WC_Balikovna_Order::instance();
    }

    /**
     * Add Balikovna shipping method to WooCommerce
     *
     * @param array $methods
     * @return array
     */
    public function add_shipping_method($methods)
    {
        $methods['balikovna'] = 'WC_Balikovna_Shipping_Method';
        return $methods;
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clear cached data
        delete_transient('wc_balikovna_branches_cache');
        delete_transient('wc_balikovna_branches_timestamp');
    }
}

/**
 * Returns the main instance of WC_Balikovna_Komplet
 *
 * @return WC_Balikovna_Komplet
 */
function WC_Balikovna()
{
    return WC_Balikovna_Komplet::instance();
}

// Initialize the plugin
WC_Balikovna();
