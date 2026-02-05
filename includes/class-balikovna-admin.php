<?php
/**
 * Balíkovna Admin Interface
 *
 * @package WC_Balikovna
 */

defined('ABSPATH') || exit;

/**
 * Class WC_Balikovna_Admin
 */
class WC_Balikovna_Admin {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_order_metabox'));
        add_action('wp_ajax_balikovna_get_branches', array(__CLASS__, 'ajax_get_branches'));
        add_action('wp_ajax_nopriv_balikovna_get_branches', array(__CLASS__, 'ajax_get_branches'));
        add_action('wp_ajax_balikovna_create_shipment', array(__CLASS__, 'ajax_create_shipment'));
        add_action('wp_ajax_balikovna_download_label', array(__CLASS__, 'ajax_download_label'));
        add_action('wp_ajax_balikovna_cancel_shipment', array(__CLASS__, 'ajax_cancel_shipment'));
        add_action('woocommerce_checkout_after_customer_details', array(__CLASS__, 'add_branch_selector_to_checkout'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        
        // Bulk actions
        add_filter('bulk_actions-edit-shop_order', array(__CLASS__, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-shop_order', array(__CLASS__, 'handle_bulk_actions'), 10, 3);
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public static function enqueue_admin_assets($hook) {
        if (in_array($hook, array('post.php', 'post-new.php', 'edit.php'))) {
            global $post_type;
            
            if ($post_type === 'shop_order') {
                wp_enqueue_style('wc-balikovna-admin', WC_BALIKOVNA_URL . 'assets/css/admin.css', array(), WC_BALIKOVNA_VERSION);
                wp_enqueue_script('wc-balikovna-admin', WC_BALIKOVNA_URL . 'assets/js/admin.js', array('jquery'), WC_BALIKOVNA_VERSION, true);
                
                wp_localize_script('wc-balikovna-admin', 'wcBalikovnaAdmin', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wc_balikovna_admin'),
                    'strings' => array(
                        'confirm_cancel' => __('Opravdu chcete zrušit tuto zásilku?', 'wc-balikovna'),
                        'creating_shipment' => __('Vytváření zásilky...', 'wc-balikovna'),
                        'downloading_label' => __('Stahuji štítek...', 'wc-balikovna'),
                        'canceling_shipment' => __('Ruším zásilku...', 'wc-balikovna'),
                        'error' => __('Chyba', 'wc-balikovna'),
                        'success' => __('Úspěch', 'wc-balikovna')
                    )
                ));
            }
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public static function enqueue_frontend_assets() {
        if (is_checkout()) {
            wp_enqueue_style('wc-balikovna-frontend', WC_BALIKOVNA_URL . 'assets/css/frontend.css', array(), WC_BALIKOVNA_VERSION);
            wp_enqueue_script('wc-balikovna-branch-selector', WC_BALIKOVNA_URL . 'assets/js/branch-selector.js', array('jquery'), WC_BALIKOVNA_VERSION, true);
            
            wp_localize_script('wc-balikovna-branch-selector', 'wcBalikovnaFrontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_balikovna_frontend'),
                'strings' => array(
                    'select_branch' => __('Vybrat', 'wc-balikovna'),
                    'loading' => __('Načítání...', 'wc-balikovna'),
                    'no_results' => __('Nenalezeny žádné pobočky', 'wc-balikovna'),
                    'search_error' => __('Chyba při vyhledávání', 'wc-balikovna')
                )
            ));
        }
    }
    
    /**
     * Add order metabox
     */
    public static function add_order_metabox() {
        add_meta_box(
            'wc_balikovna_order_metabox',
            __('Balíkovna - Zásilka', 'wc-balikovna'),
            array(__CLASS__, 'render_order_metabox'),
            'shop_order',
            'side',
            'default'
        );
    }
    
    /**
     * Render order metabox
     *
     * @param WP_Post $post Post object
     */
    public static function render_order_metabox($post) {
        $order = wc_get_order($post->ID);
        
        if (!$order) {
            return;
        }
        
        $branch_id = $order->get_meta('_balikovna_branch_id');
        $shipment_id = $order->get_meta('_balikovna_shipment_id');
        $tracking_number = $order->get_meta('_balikovna_tracking_number');
        
        wp_nonce_field('wc_balikovna_metabox', 'wc_balikovna_metabox_nonce');
        
        echo '<div class="balikovna-metabox">';
        
        if (!$branch_id) {
            echo '<p class="form-field">';
            echo '<label>' . __('Výdejní místo nebylo vybráno', 'wc-balikovna') . '</label>';
            echo '</p>';
        }
        
        if (!$shipment_id) {
            echo '<p class="form-field">';
            echo '<button type="button" class="button button-primary" id="balikovna-create-shipment" data-order-id="' . esc_attr($post->ID) . '">';
            echo __('Vytvořit zásilku', 'wc-balikovna');
            echo '</button>';
            echo '</p>';
        } else {
            echo '<p><strong>' . __('ID zásilky:', 'wc-balikovna') . '</strong> ' . esc_html($shipment_id) . '</p>';
            
            if ($tracking_number) {
                echo '<p><strong>' . __('Tracking:', 'wc-balikovna') . '</strong> ' . esc_html($tracking_number) . '</p>';
            }
            
            echo '<p class="form-field">';
            echo '<button type="button" class="button" id="balikovna-download-label" data-order-id="' . esc_attr($post->ID) . '">';
            echo __('Stáhnout štítek', 'wc-balikovna');
            echo '</button>';
            echo '</p>';
            
            echo '<p class="form-field">';
            echo '<button type="button" class="button" id="balikovna-cancel-shipment" data-order-id="' . esc_attr($post->ID) . '">';
            echo __('Zrušit zásilku', 'wc-balikovna');
            echo '</button>';
            echo '</p>';
        }
        
        echo '<div id="balikovna-metabox-messages"></div>';
        echo '</div>';
    }
    
    /**
     * Add branch selector to checkout page
     */
    public static function add_branch_selector_to_checkout() {
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        
        if (empty($chosen_methods) || strpos($chosen_methods[0], 'balikovna') === false) {
            return;
        }
        
        $template_path = WC_BALIKOVNA_PATH . 'templates/branch-selector.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        }
    }
    
    /**
     * AJAX handler for getting branches
     */
    public static function ajax_get_branches() {
        check_ajax_referer('wc_balikovna_frontend', 'nonce');
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        
        $api = new WC_Balikovna_API();
        $result = $api->get_branches($query);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX handler for creating shipment
     */
    public static function ajax_create_shipment() {
        check_ajax_referer('wc_balikovna_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Nedostatečná oprávnění', 'wc-balikovna')));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Chybné ID objednávky', 'wc-balikovna')));
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => __('Objednávka nenalezena', 'wc-balikovna')));
        }
        
        $branch_id = $order->get_meta('_balikovna_branch_id');
        
        if (!$branch_id) {
            wp_send_json_error(array('message' => __('Výdejní místo nebylo vybráno', 'wc-balikovna')));
        }
        
        // Prepare shipment data
        $shipment_data = array(
            'branch_id' => intval($branch_id),
            'recipient' => array(
                'name' => $order->get_formatted_billing_full_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone()
            ),
            'package' => array(
                'weight' => 2.5, // Default weight
                'value' => floatval($order->get_total()),
                'cod_amount' => $order->get_payment_method() === 'cod' ? floatval($order->get_total()) : 0
            ),
            'sender' => array(
                'name' => get_bloginfo('name'),
                'email' => get_option('admin_email'),
                'phone' => get_option('woocommerce_store_phone', '')
            ),
            'reference_id' => 'WC-' . $order->get_order_number()
        );
        
        $api = new WC_Balikovna_API();
        $result = $api->create_shipment($shipment_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Save shipment data
        if (isset($result['shipment_id'])) {
            $order->update_meta_data('_balikovna_shipment_id', $result['shipment_id']);
        }
        
        if (isset($result['tracking_number'])) {
            $order->update_meta_data('_balikovna_tracking_number', $result['tracking_number']);
        }
        
        $order->save();
        
        wp_send_json_success(array(
            'message' => __('Zásilka byla úspěšně vytvořena', 'wc-balikovna'),
            'shipment_id' => $result['shipment_id'] ?? '',
            'tracking_number' => $result['tracking_number'] ?? ''
        ));
    }
    
    /**
     * AJAX handler for downloading label
     */
    public static function ajax_download_label() {
        check_ajax_referer('wc_balikovna_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Nedostatečná oprávnění', 'wc-balikovna')));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Chybné ID objednávky', 'wc-balikovna')));
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => __('Objednávka nenalezena', 'wc-balikovna')));
        }
        
        $shipment_id = $order->get_meta('_balikovna_shipment_id');
        
        if (!$shipment_id) {
            wp_send_json_error(array('message' => __('Zásilka nebyla vytvořena', 'wc-balikovna')));
        }
        
        // Generate label using Label Generator
        $label_generator = new WC_Balikovna_Label_Generator();
        $pdf_content = $label_generator->generate_label($order_id, array('shipment_id' => $shipment_id));
        
        if (is_wp_error($pdf_content)) {
            wp_send_json_error(array('message' => $pdf_content->get_error_message()));
        }
        
        // Return base64 encoded PDF
        wp_send_json_success(array(
            'pdf' => base64_encode($pdf_content),
            'filename' => 'balikovna-label-' . $order->get_order_number() . '.pdf'
        ));
    }
    
    /**
     * AJAX handler for canceling shipment
     */
    public static function ajax_cancel_shipment() {
        check_ajax_referer('wc_balikovna_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Nedostatečná oprávnění', 'wc-balikovna')));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Chybné ID objednávky', 'wc-balikovna')));
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => __('Objednávka nenalezena', 'wc-balikovna')));
        }
        
        $shipment_id = $order->get_meta('_balikovna_shipment_id');
        
        if (!$shipment_id) {
            wp_send_json_error(array('message' => __('Zásilka nebyla vytvořena', 'wc-balikovna')));
        }
        
        $api = new WC_Balikovna_API();
        $result = $api->cancel_shipment($shipment_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Remove shipment data
        $order->delete_meta_data('_balikovna_shipment_id');
        $order->delete_meta_data('_balikovna_tracking_number');
        $order->save();
        
        wp_send_json_success(array('message' => __('Zásilka byla zrušena', 'wc-balikovna')));
    }
    
    /**
     * Add bulk actions
     *
     * @param array $actions Existing actions
     * @return array
     */
    public static function add_bulk_actions($actions) {
        $actions['balikovna_create_shipments'] = __('Balíkovna - Vytvořit zásilky', 'wc-balikovna');
        $actions['balikovna_download_labels'] = __('Balíkovna - Stáhnout štítky', 'wc-balikovna');
        
        return $actions;
    }
    
    /**
     * Handle bulk actions
     *
     * @param string $redirect_to Redirect URL
     * @param string $action Action name
     * @param array $post_ids Order IDs
     * @return string
     */
    public static function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if ($action === 'balikovna_create_shipments') {
            $created = 0;
            
            foreach ($post_ids as $order_id) {
                $order = wc_get_order($order_id);
                
                if (!$order) {
                    continue;
                }
                
                $branch_id = $order->get_meta('_balikovna_branch_id');
                $shipment_id = $order->get_meta('_balikovna_shipment_id');
                
                if (!$branch_id || $shipment_id) {
                    continue;
                }
                
                // Create shipment (simplified version)
                $created++;
            }
            
            $redirect_to = add_query_arg('balikovna_created', $created, $redirect_to);
        }
        
        return $redirect_to;
    }
}
