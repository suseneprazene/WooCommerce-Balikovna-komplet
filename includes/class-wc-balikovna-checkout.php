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

        // Validate branch selection
        add_action('woocommerce_checkout_process', array($this, 'validate_branch_selection'));

        // Save branch selection to order
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_branch_selection'));
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
                'selectedLabel' => __('Vybrané místo', 'wc-balikovna-komplet'),
            ));
        }
    }

    /**
     * Add branch selection field to checkout
     *
     * @param WC_Shipping_Rate $method
     * @param int $index
     */
    public function add_branch_selection_field($method, $index)
    {
        // Only show for balikovna method, but NOT for address variant
        if ($method->get_method_id() !== 'balikovna') {
            return;
        }

        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        $chosen_shipping = isset($chosen_methods[0]) ? $chosen_methods[0] : '';

        if (strpos($chosen_shipping, 'balikovna') === false) {
            return;
        }
        
        // Don't show picker for address delivery
        if (strpos($chosen_shipping, 'address') !== false) {
            return;
        }

        $selected_branch = WC()->session->get('wc_balikovna_selected_branch');
        $selected_info = '';
        
        if ($selected_branch) {
            $branch_data = json_decode($selected_branch, true);
            if ($branch_data && isset($branch_data['name'])) {
                $selected_info = sprintf(
                    '<strong>%s:</strong> %s, %s',
                    esc_html__('Vybrané místo', 'wc-balikovna-komplet'),
                    esc_html($branch_data['name']),
                    esc_html($branch_data['address'])
                );
            }
        }

        echo '<div id="balikovna_iframe_container" class="wc-balikovna-branch-selection" style="margin-top: 15px;">';
        echo '<label>' . esc_html__('Vyberte výdejní místo Balíkovny', 'wc-balikovna-komplet') . ' <span class="required">*</span></label>';
        echo '<iframe id="balikovna_picker" src="https://b2c.cpost.cz/locations/?type=BALIKOVNY" style="width:100%; height:500px; border:1px solid #ccc;" allow="geolocation"></iframe>';
        
        if ($selected_info) {
            echo '<div id="balikovna_selected_info" style="margin-top:10px; padding:10px; background:#f9f9f9; border:1px solid #ddd;">' . $selected_info . '</div>';
        } else {
            echo '<div id="balikovna_selected_info" style="margin-top:10px;"></div>';
        }
        
        echo '<input type="hidden" id="balikovna_branch_data" name="balikovna_branch_data" value="' . esc_attr($selected_branch) . '">';
        echo '</div>';
    }

    /**
     * Validate branch selection
     */
    public function validate_branch_selection()
    {
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        
        if (empty($chosen_methods)) {
            return;
        }

        $chosen_shipping = $chosen_methods[0];

        // Only validate for balikovna (not address variant)
        if (strpos($chosen_shipping, 'balikovna') !== false && strpos($chosen_shipping, 'address') === false) {
            if (empty($_POST['balikovna_branch_data'])) {
                wc_add_notice(__('Vyberte prosím pobočku Balíkovny', 'wc-balikovna-komplet'), 'error');
            }
        }
    }

    /**
     * Save branch selection to order
     *
     * @param int $order_id
     */
    public function save_branch_selection($order_id)
    {
        if (!empty($_POST['balikovna_branch_data'])) {
            // Sanitize JSON input
            $branch_json = sanitize_text_field(wp_unslash($_POST['balikovna_branch_data']));
            $branch_data = json_decode($branch_json, true);
            
            if ($branch_data && is_array($branch_data)) {
                $order = wc_get_order($order_id);
                
                if ($order) {
                    // Save branch data to order meta using HPOS-compatible method
                    $order->update_meta_data('_wc_balikovna_branch_id', sanitize_text_field($branch_data['id']));
                    $order->update_meta_data('_wc_balikovna_branch_name', sanitize_text_field($branch_data['name']));
                    $order->update_meta_data('_wc_balikovna_branch_address', sanitize_text_field($branch_data['address']));
                    $order->update_meta_data('_wc_balikovna_branch_city', sanitize_text_field($branch_data['city']));
                    $order->update_meta_data('_wc_balikovna_branch_zip', sanitize_text_field($branch_data['zip']));
                    $order->save();

                    // Save to session for later use
                    WC()->session->set('wc_balikovna_selected_branch', $branch_json);
                }
            }
        }
    }
}
