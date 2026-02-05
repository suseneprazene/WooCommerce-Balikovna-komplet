<?php
/**
 * Balíkovna Shipping Method
 *
 * @package WC_Balikovna
 */

defined('ABSPATH') || exit;

/**
 * Class WC_Balikovna_Shipping
 */
class WC_Balikovna_Shipping extends WC_Shipping_Method {
    
    /**
     * Constructor
     *
     * @param int $instance_id Instance ID
     */
    public function __construct($instance_id = 0) {
        $this->id = 'balikovna';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Balíkovna', 'wc-balikovna');
        $this->method_description = __('Doprava přes výdejní místa Balíkovny', 'wc-balikovna');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        
        $this->init();
    }
    
    /**
     * Initialize settings
     */
    private function init() {
        $this->init_form_fields();
        $this->init_settings();
        
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->cost = $this->get_option('cost');
        $this->free_shipping_threshold = $this->get_option('free_shipping_threshold');
        $this->enable_cod = $this->get_option('enable_cod');
        
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'enabled' => array(
                'title' => __('Zapnout/vypnout', 'wc-balikovna'),
                'type' => 'checkbox',
                'label' => __('Zapnout metodu dopravy Balíkovna', 'wc-balikovna'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Název', 'wc-balikovna'),
                'type' => 'text',
                'description' => __('Název, který se zobrazí zákazníkovi při výběru dopravy', 'wc-balikovna'),
                'default' => __('Balíkovna - Výdejní místo', 'wc-balikovna'),
                'desc_tip' => true
            ),
            'cost' => array(
                'title' => __('Cena dopravy', 'wc-balikovna'),
                'type' => 'price',
                'description' => __('Cena dopravy v Kč', 'wc-balikovna'),
                'default' => '59',
                'desc_tip' => true
            ),
            'free_shipping_threshold' => array(
                'title' => __('Doprava zdarma od', 'wc-balikovna'),
                'type' => 'price',
                'description' => __('Částka, od které je doprava zdarma. Nechte prázdné pro vypnutí.', 'wc-balikovna'),
                'default' => '1000',
                'desc_tip' => true
            ),
            'enable_cod' => array(
                'title' => __('Povolit dobírku', 'wc-balikovna'),
                'type' => 'checkbox',
                'label' => __('Povolit platbu na dobírku pro tuto metodu dopravy', 'wc-balikovna'),
                'default' => 'yes'
            ),
            'api_settings_title' => array(
                'title' => __('API Nastavení', 'wc-balikovna'),
                'type' => 'title',
                'description' => __('Nastavení pro API Balíkovny', 'wc-balikovna'),
            ),
            'api_token' => array(
                'title' => __('API Token', 'wc-balikovna'),
                'type' => 'text',
                'description' => __('API token pro přístup k Balíkovna API', 'wc-balikovna'),
                'default' => get_option('wc_balikovna_api_token', ''),
                'desc_tip' => true
            ),
            'private_key' => array(
                'title' => __('Private Key', 'wc-balikovna'),
                'type' => 'textarea',
                'description' => __('Soukromý klíč pro API autentizaci', 'wc-balikovna'),
                'default' => get_option('wc_balikovna_private_key', ''),
                'desc_tip' => true
            )
        );
    }
    
    /**
     * Calculate shipping cost
     *
     * @param array $package Package data
     */
    public function calculate_shipping($package = array()) {
        $cost = floatval($this->cost);
        
        // Check for free shipping threshold
        if (!empty($this->free_shipping_threshold)) {
            $threshold = floatval($this->free_shipping_threshold);
            $cart_total = WC()->cart->get_subtotal();
            
            if ($cart_total >= $threshold) {
                $cost = 0;
            }
        }
        
        $rate = array(
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $cost,
            'package' => $package,
        );
        
        $this->add_rate($rate);
    }
    
    /**
     * Process admin options
     *
     * @return bool
     */
    public function process_admin_options() {
        $result = parent::process_admin_options();
        
        // Update global API options
        if (isset($_POST['woocommerce_balikovna_api_token'])) {
            update_option('wc_balikovna_api_token', sanitize_text_field($_POST['woocommerce_balikovna_api_token']));
        }
        
        if (isset($_POST['woocommerce_balikovna_private_key'])) {
            update_option('wc_balikovna_private_key', sanitize_textarea_field($_POST['woocommerce_balikovna_private_key']));
        }
        
        return $result;
    }
}
