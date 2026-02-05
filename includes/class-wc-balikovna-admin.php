<?php
/**
 * Admin functionality for Balikovna
 *
 * @package WC_Balikovna_Komplet
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Balikovna_Admin Class
 */
class WC_Balikovna_Admin
{
    /**
     * The single instance of the class
     *
     * @var WC_Balikovna_Admin
     */
    protected static $_instance = null;

    /**
     * Main WC_Balikovna_Admin Instance
     *
     * @return WC_Balikovna_Admin
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add settings page
        add_filter('woocommerce_get_settings_pages', array($this, 'add_settings_page'));

        // Handle sync action
        add_action('admin_post_wc_balikovna_sync', array($this, 'handle_sync_action'));
    }

    /**
     * Add settings page to WooCommerce
     *
     * @param array $settings
     * @return array
     */
    public function add_settings_page($settings)
    {
        $settings[] = include WC_BALIKOVNA_PLUGIN_DIR . 'includes/class-wc-balikovna-settings.php';
        return $settings;
    }

    /**
     * Handle sync action
     */
    public function handle_sync_action()
    {
        // Check nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wc_balikovna_sync')) {
            wp_die(__('Neplatný požadavek', 'wc-balikovna-komplet'));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Nemáte oprávnění k této akci', 'wc-balikovna-komplet'));
        }

        // Perform sync
        $result = WC_Balikovna_Install::sync_data();

        if (is_wp_error($result)) {
            WC_Admin_Settings::add_error($result->get_error_message());
        } else {
            WC_Admin_Settings::add_message(
                sprintf(
                    __('Synchronizace dokončena. Importováno %d poboček a %d otevíracích hodin.', 'wc-balikovna-komplet'),
                    $result['branches'],
                    $result['hours']
                )
            );
        }

        // Redirect back
        wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=balikovna'));
        exit;
    }
}
