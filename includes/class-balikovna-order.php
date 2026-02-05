<?php
/**
 * Balíkovna Order Handling
 *
 * @package WC_Balikovna
 */

defined('ABSPATH') || exit;

/**
 * Class WC_Balikovna_Order
 */
class WC_Balikovna_Order {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'save_branch_to_order'), 10, 1);
        add_action('woocommerce_admin_order_data_after_shipping_address', array(__CLASS__, 'display_branch_info'), 10, 1);
        add_action('woocommerce_email_after_order_table', array(__CLASS__, 'email_branch_info'), 10, 4);
        add_action('woocommerce_checkout_process', array(__CLASS__, 'validate_branch_selection'));
    }
    
    /**
     * Validate branch selection on checkout
     */
    public static function validate_branch_selection() {
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        
        if (!empty($chosen_methods) && strpos($chosen_methods[0], 'balikovna') !== false) {
            if (empty($_POST['balikovna_branch_id'])) {
                wc_add_notice(__('Prosím vyberte výdejní místo Balíkovny.', 'wc-balikovna'), 'error');
            }
        }
    }
    
    /**
     * Save selected branch to order meta
     *
     * @param int $order_id Order ID
     */
    public static function save_branch_to_order($order_id) {
        if (isset($_POST['balikovna_branch_id']) && !empty($_POST['balikovna_branch_id'])) {
            $branch_id = sanitize_text_field($_POST['balikovna_branch_id']);
            $branch_name = isset($_POST['balikovna_branch_name']) ? sanitize_text_field($_POST['balikovna_branch_name']) : '';
            $branch_address = isset($_POST['balikovna_branch_address']) ? sanitize_text_field($_POST['balikovna_branch_address']) : '';
            
            update_post_meta($order_id, '_balikovna_branch_id', $branch_id);
            update_post_meta($order_id, '_balikovna_branch_name', $branch_name);
            update_post_meta($order_id, '_balikovna_branch_address', $branch_address);
        }
    }
    
    /**
     * Display branch info in admin order details
     *
     * @param WC_Order $order Order object
     */
    public static function display_branch_info($order) {
        $branch_id = $order->get_meta('_balikovna_branch_id');
        
        if ($branch_id) {
            $branch_name = $order->get_meta('_balikovna_branch_name');
            $branch_address = $order->get_meta('_balikovna_branch_address');
            $shipment_id = $order->get_meta('_balikovna_shipment_id');
            $tracking_number = $order->get_meta('_balikovna_tracking_number');
            
            echo '<div class="balikovna-order-info">';
            echo '<h3>' . __('Balíkovna - Výdejní místo', 'wc-balikovna') . '</h3>';
            echo '<p><strong>' . __('ID pobočky:', 'wc-balikovna') . '</strong> ' . esc_html($branch_id) . '</p>';
            
            if ($branch_name) {
                echo '<p><strong>' . __('Název:', 'wc-balikovna') . '</strong> ' . esc_html($branch_name) . '</p>';
            }
            
            if ($branch_address) {
                echo '<p><strong>' . __('Adresa:', 'wc-balikovna') . '</strong> ' . esc_html($branch_address) . '</p>';
            }
            
            if ($shipment_id) {
                echo '<p><strong>' . __('ID zásilky:', 'wc-balikovna') . '</strong> ' . esc_html($shipment_id) . '</p>';
            }
            
            if ($tracking_number) {
                echo '<p><strong>' . __('Tracking číslo:', 'wc-balikovna') . '</strong> ' . esc_html($tracking_number) . '</p>';
            }
            
            echo '</div>';
        }
    }
    
    /**
     * Add branch info to order emails
     *
     * @param WC_Order $order Order object
     * @param bool $sent_to_admin Sent to admin flag
     * @param bool $plain_text Plain text flag
     * @param string $email Email object
     */
    public static function email_branch_info($order, $sent_to_admin, $plain_text, $email) {
        $branch_id = $order->get_meta('_balikovna_branch_id');
        
        if ($branch_id) {
            $branch_name = $order->get_meta('_balikovna_branch_name');
            $branch_address = $order->get_meta('_balikovna_branch_address');
            
            if ($plain_text) {
                echo "\n" . __('Výdejní místo Balíkovna:', 'wc-balikovna') . "\n";
                if ($branch_name) {
                    echo $branch_name . "\n";
                }
                if ($branch_address) {
                    echo $branch_address . "\n";
                }
            } else {
                echo '<h2>' . __('Výdejní místo Balíkovna', 'wc-balikovna') . '</h2>';
                echo '<p>';
                if ($branch_name) {
                    echo '<strong>' . esc_html($branch_name) . '</strong><br>';
                }
                if ($branch_address) {
                    echo esc_html($branch_address);
                }
                echo '</p>';
            }
        }
    }
    
    /**
     * Get branch info for order
     *
     * @param int $order_id Order ID
     * @return array|null
     */
    public static function get_branch_info($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return null;
        }
        
        $branch_id = $order->get_meta('_balikovna_branch_id');
        
        if (!$branch_id) {
            return null;
        }
        
        return array(
            'branch_id' => $branch_id,
            'branch_name' => $order->get_meta('_balikovna_branch_name'),
            'branch_address' => $order->get_meta('_balikovna_branch_address'),
            'shipment_id' => $order->get_meta('_balikovna_shipment_id'),
            'tracking_number' => $order->get_meta('_balikovna_tracking_number')
        );
    }
}
