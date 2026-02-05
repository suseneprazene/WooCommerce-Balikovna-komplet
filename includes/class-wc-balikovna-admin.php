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
        
        // Add print label action to order actions
        add_filter('woocommerce_admin_order_actions', array($this, 'add_print_label_action'), 10, 2);
        
        // Handle print label request
        add_action('admin_post_wc_balikovna_print_label', array($this, 'handle_print_label'));
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
    
    /**
     * Add print label action to order actions
     *
     * @param array $actions Current actions
     * @param WC_Order $order_obj Order object
     * @return array Modified actions
     */
    public function add_print_label_action($actions, $order_obj)
    {
        // Check if order uses Balikovna shipping
        $uses_balikovna = false;
        $shipping_items = $order_obj->get_items('shipping');
        
        foreach ($shipping_items as $item) {
            if (strpos($item->get_method_id(), 'balikovna') !== false) {
                $uses_balikovna = true;
                break;
            }
        }
        
        if (!$uses_balikovna) {
            return $actions;
        }
        
        // Check if label already exists
        $tracking_number = $order_obj->get_meta('_balikovna_tracking_number');
        
        $action_label = !empty($tracking_number) 
            ? __('Znovu vytisknout štítek', 'wc-balikovna-komplet')
            : __('Vytvořit štítek Balíkovny', 'wc-balikovna-komplet');
        
        $actions['balikovna_print_label'] = array(
            'url' => wp_nonce_url(
                admin_url('admin-post.php?action=wc_balikovna_print_label&order_id=' . $order_obj->get_id()),
                'wc_balikovna_print_label_' . $order_obj->get_id()
            ),
            'name' => $action_label,
            'action' => 'balikovna_label',
        );
        
        return $actions;
    }
    
    /**
     * Handle print label request
     */
    public function handle_print_label()
    {
        // Verify permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('Nemáte oprávnění k této akci', 'wc-balikovna-komplet'));
        }
        
        // Get order ID
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        
        if (!$order_id) {
            wp_die(__('Neplatné ID objednávky', 'wc-balikovna-komplet'));
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wc_balikovna_print_label_' . $order_id)) {
            wp_die(__('Bezpečnostní kontrola selhala', 'wc-balikovna-komplet'));
        }
        
        // Generate label
        $label_generator = new WC_Balikovna_Label();
        $result = $label_generator->generate_label($order_id);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        // Output PDF
        if (file_exists($result['file'])) {
            // Validate order ID is numeric
            $order_id = absint($order_id);
            if ($order_id === 0) {
                wp_die(__('Neplatné ID objednávky', 'wc-balikovna-komplet'));
            }
            
            $safe_filename = sanitize_file_name('balikovna-label-' . $order_id . '.pdf');
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $safe_filename . '"');
            header('Content-Length: ' . filesize($result['file']));
            readfile($result['file']);
            exit;
        }
        
        wp_die(__('Štítek se nepodařilo vytvořit', 'wc-balikovna-komplet'));
    }
}
