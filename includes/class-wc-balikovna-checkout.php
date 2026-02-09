<?php
/**
 * Checkout integration for Balikovna
 *
 * @package WC_Balikovna_Komplet
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Balikovna_Checkout Class
 */
class WC_Balikovna_Checkout
{
    /**
     * The single instance of the class
     *
     * @var WC_Balikovna_Checkout
     */
    protected static $_instance = null;

    /**
     * Main WC_Balikovna_Checkout Instance
     *
     * @return WC_Balikovna_Checkout
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
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add branch selection field to checkout
        add_action('woocommerce_after_shipping_rate', array($this, 'add_branch_selection_field'), 10, 2);

        // Validate checkout fields
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_fields'));

        // Save checkout data to order
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_checkout_data'));
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts()
    {
        if (is_checkout()) {
            // Enqueue Select2
            wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
            wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);

            // Enqueue plugin styles
            wp_enqueue_style('wc-balikovna-checkout', WC_BALIKOVNA_PLUGIN_URL . 'assets/css/balikovna-checkout.css', array(), WC_BALIKOVNA_VERSION);

            // Enqueue plugin scripts
            wp_enqueue_script('wc-balikovna-checkout', WC_BALIKOVNA_PLUGIN_URL . 'assets/js/balikovna-checkout.js', array('jquery', 'select2'), WC_BALIKOVNA_VERSION, true);

            // Localize script
            wp_localize_script('wc-balikovna-checkout', 'wcBalikovnaData', array(
                'apiUrl' => rest_url('balikovna/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
                'selectPlaceholder' => __('Začněte psát název obce nebo PSČ...', 'wc-balikovna-komplet'),
                'validationError' => __('Vyberte prosím pobočku Balíkovny', 'wc-balikovna-komplet'),
                'loadingText' => __('Načítám...', 'wc-balikovna-komplet'),
                'openingHoursError' => __('Nepodařilo se načíst otevírací hodiny', 'wc-balikovna-komplet'),
                'openingHoursTitle' => __('Zobrazit otevírací hodiny', 'wc-balikovna-komplet'),
                'kindPosta' => __('pošta', 'wc-balikovna-komplet'),
                'kindBalikovna' => __('balíkovna', 'wc-balikovna-komplet'),
            ));
        }
    }

    /**
     * Add branch selection field or address notice to checkout
     *
     * @param WC_Shipping_Rate $method
     * @param int $index
     */
    public function add_branch_selection_field($method, $index)
    {
        if ($method->get_method_id() !== 'balikovna') {
            return;
        }

        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        $chosen_shipping = $chosen_methods[0];

        if (strpos($chosen_shipping, 'balikovna') === false) {
            return;
        }

        // Get delivery type from rate meta data
        $meta_data = $method->get_meta_data();
        $delivery_type = isset($meta_data['delivery_type']) ? $meta_data['delivery_type'] : 'box';

        // Save delivery type to session
        WC()->session->set('wc_balikovna_delivery_type', $delivery_type);

        // Render appropriate field based on delivery type
        if ($delivery_type === 'box') {
            $this->render_box_selection();
        } else {
            $this->render_address_notice();
        }
    }

    /**
     * Render box selection with iframe
     */
    private function render_box_selection()
    {
        ?>
        <div class="wc-balikovna-branch-selection" style="margin-top: 15px;">
            <label for="wc_balikovna_branch_iframe">
                <?php echo esc_html__('Vyberte pobočku Balíkovny', 'wc-balikovna-komplet'); ?>
                <span class="required">*</span>
            </label>
            <div id="wc-balikovna-iframe-container" style="margin-top: 10px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                <iframe 
                    id="wc_balikovna_branch_iframe" 
                    src="https://b2c.cpost.cz/locations/?type=BALIKOVNY" 
                    style="width: 100%; height: 500px; border: none;"
                    frameborder="0"
                ></iframe>
            </div>
            <input type="hidden" id="wc_balikovna_branch" name="wc_balikovna_branch" value="">
            <input type="hidden" name="wc_balikovna_delivery_type" value="box">
            <div id="wc-balikovna-selected-info" style="display: none; margin-top: 10px; padding: 10px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">
                <strong><?php echo esc_html__('Vybraná pobočka:', 'wc-balikovna-komplet'); ?></strong>
                <div id="wc-balikovna-selected-details"></div>
            </div>
        </div>
<script>
jQuery(function($) {
    // Listen for postMessage from iframe
    window.addEventListener('message', function(event) {
        // Verify origin
        if (event.origin !== 'https://b2c.cpost.cz') {
            return;
        }
        
        // Check if this is the picker result - API České pošty vrací "point", ne "branch"!
        if (event.data && 
            typeof event.data === 'object' && 
            event.data.message === 'pickerResult' &&
            event.data.point &&
            typeof event.data.point === 'object') {
            
            var point = event.data.point; // Změněno z event.data.branch na event.data.point
            
            if (point) {
                // Create branch data object
                var branchData = {
                    id: point.id || '',
                    name: point.name || '',
                    city: point.municipality_name || point.city || '',  // API vrací municipality_name
                    city_part: point.city_part || '',
                    address: point.address || '',
                    zip: point.zip || '',
                    kind: point.kind || ''
                };
                
                // Save to hidden field as JSON
                $('#wc_balikovna_branch').val(JSON.stringify(branchData));
                
                // Display selected branch info
                var infoHtml = '<p style="margin: 5px 0;">' +
                    '<strong>' + branchData.name + '</strong><br>' +
                    branchData.address + ', ' + branchData.zip + ' ' + branchData.city +
                    (branchData.city_part ? ' - ' + branchData.city_part : '') +
                    '</p>';
                $('#wc-balikovna-selected-details').html(infoHtml);
                $('#wc-balikovna-selected-info').show();
                
                // Oznámení WooCommerce, že se změnila adresa (jako ve starém pluginu)
                $(document.body).trigger('update_checkout');
            }
        }
    }, false);
});
</script>
        <?php
    }

    /**
     * Render address notice for home delivery
     */
    private function render_address_notice()
    {
        ?>
        <div class="wc-balikovna-address-notice" style="margin-top: 15px; padding: 15px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">
            <p style="margin: 0;">
                <strong><?php echo esc_html__('Doručení na adresu', 'wc-balikovna-komplet'); ?></strong><br>
                <?php echo esc_html__('Balík bude doručen na Tebou uvedenou dodací adresu, viz. níže.', 'wc-balikovna-komplet'); ?>
            </p>
            <input type="hidden" name="wc_balikovna_delivery_type" value="address">
        </div>
        <?php
    }

    /**
     * Validate checkout fields based on delivery type
     */
    public function validate_checkout_fields()
    {
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        
        if (empty($chosen_methods)) {
            return;
        }

        $chosen_shipping = $chosen_methods[0];

        if (strpos($chosen_shipping, 'balikovna') !== false) {
            $delivery_type = isset($_POST['wc_balikovna_delivery_type']) ? sanitize_text_field($_POST['wc_balikovna_delivery_type']) : 'box';
            
            if ($delivery_type === 'box') {
                // Validate branch selection for Box type
                if (empty($_POST['wc_balikovna_branch'])) {
                    wc_add_notice(__('Vyberte prosím pobočku Balíkovny', 'wc-balikovna-komplet'), 'error');
                }
            } else {
                // Validate address fields for Address type
                $this->validate_required_address_fields();
            }
        }
    }

    /**
     * Validate required address fields
     */
    private function validate_required_address_fields()
    {
        if (empty($_POST['billing_address_1']) && empty($_POST['shipping_address_1'])) {
            wc_add_notice(__('Vyplňte prosím dodací adresu', 'wc-balikovna-komplet'), 'error');
        }
        if (empty($_POST['billing_city']) && empty($_POST['shipping_city'])) {
            wc_add_notice(__('Vyplňte prosím město', 'wc-balikovna-komplet'), 'error');
        }
        if (empty($_POST['billing_postcode']) && empty($_POST['shipping_postcode'])) {
            wc_add_notice(__('Vyplňte prosím PSČ', 'wc-balikovna-komplet'), 'error');
        }
    }

    /**
     * Save checkout data to order
     *
     * @param int $order_id
     */
    public function save_checkout_data($order_id)
    {
        $delivery_type = isset($_POST['wc_balikovna_delivery_type']) ? sanitize_text_field($_POST['wc_balikovna_delivery_type']) : 'box';
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        // Save delivery type
        $order->update_meta_data('_wc_balikovna_delivery_type', $delivery_type);

        if ($delivery_type === 'box' && !empty($_POST['wc_balikovna_branch'])) {
            // Save branch data for Box type
            $branch_json = sanitize_text_field(wp_unslash($_POST['wc_balikovna_branch']));
            $branch_data = json_decode($branch_json, true);
            
            if ($branch_data && is_array($branch_data)) {
                $order->update_meta_data('_wc_balikovna_branch_id', sanitize_text_field($branch_data['id']));
                $order->update_meta_data('_wc_balikovna_branch_name', sanitize_text_field($branch_data['name']));
                $order->update_meta_data('_wc_balikovna_branch_city', sanitize_text_field($branch_data['city']));
                $order->update_meta_data('_wc_balikovna_branch_city_part', sanitize_text_field($branch_data['city_part'] ?? ''));
                $order->update_meta_data('_wc_balikovna_branch_address', sanitize_text_field($branch_data['address']));
                $order->update_meta_data('_wc_balikovna_branch_zip', sanitize_text_field($branch_data['zip']));
                $order->update_meta_data('_wc_balikovna_branch_kind', sanitize_text_field($branch_data['kind']));

                // Save to session for later use
                WC()->session->set('wc_balikovna_selected_branch', $branch_json);
            }
        } else {
            // Save flag for Address type
            $order->update_meta_data('_wc_balikovna_address_delivery', 'yes');
        }

        $order->save();
    }
}
