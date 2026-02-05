<?php
/**
 * WooCommerce Balikovna Shipping Method
 *
 * @package WC_Balikovna_Komplet
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Balikovna_Shipping_Method Class
 */
class WC_Balikovna_Shipping_Method extends WC_Shipping_Method
{
    /**
     * Constructor
     */
    public function __construct($instance_id = 0)
    {
        $this->id = 'balikovna';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Balíkovna České pošty', 'wc-balikovna-komplet');
        $this->method_description = __('Doručení na pobočku Balíkovny České pošty', 'wc-balikovna-komplet');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init();
    }

    /**
     * Initialize shipping method
     */
    public function init()
    {
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        $this->cost = $this->get_option('cost');
        $this->free_shipping_threshold = $this->get_option('free_shipping_threshold');

        // Save settings
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields()
    {
        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Název metody', 'wc-balikovna-komplet'),
                'type' => 'text',
                'description' => __('Název dopravy zobrazený zákazníkovi', 'wc-balikovna-komplet'),
                'default' => __('Balíkovna České pošty', 'wc-balikovna-komplet'),
                'desc_tip' => true,
            ),
            'cost' => array(
                'title' => __('Cena dopravy', 'wc-balikovna-komplet'),
                'type' => 'text',
                'description' => __('Cena dopravy v Kč', 'wc-balikovna-komplet'),
                'default' => '49',
                'desc_tip' => true,
                'placeholder' => '49',
            ),
            'free_shipping_threshold' => array(
                'title' => __('Doprava zdarma od', 'wc-balikovna-komplet'),
                'type' => 'text',
                'description' => __('Minimální částka objednávky pro dopravu zdarma v Kč. Nechte prázdné pro vypnutí.', 'wc-balikovna-komplet'),
                'default' => '',
                'desc_tip' => true,
                'placeholder' => '1000',
            ),
        );
    }

    /**
     * Calculate shipping cost
     *
     * @param array $package
     */
    public function calculate_shipping($package = array())
    {
        $cost = floatval($this->cost);
        $free_threshold = floatval($this->free_shipping_threshold);

        // Check if free shipping threshold is met
        if ($free_threshold > 0) {
            $cart_total = WC()->cart->get_subtotal();
            if ($cart_total >= $free_threshold) {
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
}
