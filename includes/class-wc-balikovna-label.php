<?php
/**
 * Label generation for Balikovna
 *
 * @package WC_Balikovna_Komplet
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Balikovna_Label Class
 */
class WC_Balikovna_Label
{
    /**
     * The single instance of the class
     *
     * @var WC_Balikovna_Label
     */
    protected static $_instance = null;

    /**
     * API username
     *
     * @var string
     */
    private $api_username;

    /**
     * API password
     *
     * @var string
     */
    private $api_password;

    /**
     * API URL
     *
     * @var string
     */
    private $api_url = 'https://b2b.cpost.cz/services/';

    /**
     * Main WC_Balikovna_Label Instance
     *
     * @return WC_Balikovna_Label
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
        // Load API credentials from options
        $this->api_username = get_option('wc_balikovna_api_username', '');
        $this->api_password = get_option('wc_balikovna_api_password', '');

        // Add label button to order admin page
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'add_label_button'), 10, 1);

        // Handle AJAX request for label generation
        add_action('wp_ajax_wc_balikovna_generate_label', array($this, 'handle_label_generation'));
    }

    /**
     * Add label generation button to order admin page
     *
     * @param WC_Order $order
     */
    public function add_label_button($order)
    {
        // Check if this is a Balikovna order
        $delivery_type = $order->get_meta('_wc_balikovna_delivery_type');
        
        if (empty($delivery_type)) {
            return;
        }

        $label_generated = $order->get_meta('_wc_balikovna_label_generated');
        $label_url = $order->get_meta('_wc_balikovna_label_url');

        ?>
        <div class="wc-balikovna-label-section" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
            <h3><?php echo esc_html__('Štítek Balíkovny', 'wc-balikovna-komplet'); ?></h3>
            
            <?php if ($label_generated === 'yes' && !empty($label_url)) : ?>
                <p>
                    <strong><?php echo esc_html__('Štítek byl vygenerován:', 'wc-balikovna-komplet'); ?></strong><br>
                    <a href="<?php echo esc_url($label_url); ?>" target="_blank" class="button">
                        <?php echo esc_html__('Stáhnout štítek', 'wc-balikovna-komplet'); ?>
                    </a>
                </p>
            <?php else : ?>
                <p>
                    <button type="button" class="button button-primary wc-balikovna-generate-label" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                        <?php echo esc_html__('Generovat štítek', 'wc-balikovna-komplet'); ?>
                    </button>
                </p>
                <div class="wc-balikovna-label-message" style="display: none; margin-top: 10px;"></div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(function($) {
            $('.wc-balikovna-generate-label').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var orderId = button.data('order-id');
                var messageDiv = $('.wc-balikovna-label-message');
                
                button.prop('disabled', true).text('<?php echo esc_js(__('Generuji...', 'wc-balikovna-komplet')); ?>');
                messageDiv.hide().removeClass('notice-success notice-error');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wc_balikovna_generate_label',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce('wc_balikovna_label_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            messageDiv.addClass('notice notice-success')
                                .html('<p>' + response.data.message + '</p>')
                                .show();
                            // Reload page after 2 seconds to show the download button
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            messageDiv.addClass('notice notice-error')
                                .html('<p>' + response.data.message + '</p>')
                                .show();
                            button.prop('disabled', false).text('<?php echo esc_js(__('Generovat štítek', 'wc-balikovna-komplet')); ?>');
                        }
                    },
                    error: function() {
                        messageDiv.addClass('notice notice-error')
                            .html('<p><?php echo esc_js(__('Došlo k chybě při komunikaci se serverem', 'wc-balikovna-komplet')); ?></p>')
                            .show();
                        button.prop('disabled', false).text('<?php echo esc_js(__('Generovat štítek', 'wc-balikovna-komplet')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Handle AJAX request for label generation
     */
    public function handle_label_generation()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_balikovna_label_nonce')) {
            wp_send_json_error(array('message' => __('Neplatný bezpečnostní token', 'wc-balikovna-komplet')));
        }

        // Check user permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Nemáte oprávnění provést tuto akci', 'wc-balikovna-komplet')));
        }

        // Get order ID
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Neplatné ID objednávky', 'wc-balikovna-komplet')));
        }

        // Get order
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => __('Objednávka nebyla nalezena', 'wc-balikovna-komplet')));
        }

        // Generate label
        $result = $this->generate_label($order);

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Generate label for order
     *
     * @param WC_Order $order
     * @return array
     */
    public function generate_label($order)
    {
        // Get delivery type
        $delivery_type = $order->get_meta('_wc_balikovna_delivery_type');

        if (empty($delivery_type)) {
            return array(
                'success' => false,
                'message' => __('Tato objednávka není objednávka Balíkovny', 'wc-balikovna-komplet')
            );
        }

        // Check if API credentials are set
        if (empty($this->api_username) || empty($this->api_password)) {
            return array(
                'success' => false,
                'message' => __('API credentials nejsou nastaveny. Kontaktujte administrátora.', 'wc-balikovna-komplet')
            );
        }

        // Generate label based on delivery type
        if ($delivery_type === 'box') {
            $result = $this->generate_box_label($order);
        } else {
            $result = $this->generate_address_label($order);
        }

        return $result;
    }

    /**
     * Generate label for Box delivery type
     *
     * @param WC_Order $order
     * @return array
     */
    private function generate_box_label($order)
    {
        // Get branch data
        $branch_id = $order->get_meta('_wc_balikovna_branch_id');
        $branch_name = $order->get_meta('_wc_balikovna_branch_name');

        if (empty($branch_id)) {
            return array(
                'success' => false,
                'message' => __('Pobočka nebyla vybrána', 'wc-balikovna-komplet')
            );
        }

        // Prepare data for API
        $data = array(
            'deliveryType' => 'box',
            'branchId' => $branch_id,
            'branchName' => $branch_name,
            'orderNumber' => $order->get_order_number(),
            'customerName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customerEmail' => $order->get_billing_email(),
            'customerPhone' => $order->get_billing_phone(),
            'weight' => $this->calculate_order_weight($order),
            'codAmount' => $order->get_payment_method() === 'cod' ? $order->get_total() : 0,
        );

        // Call API (placeholder for actual API implementation)
        $api_result = $this->call_api('label/generate', $data, $order);

        if ($api_result['success']) {
            // Save label info to order
            $order->update_meta_data('_wc_balikovna_label_generated', 'yes');
            $order->update_meta_data('_wc_balikovna_label_url', $api_result['label_url']);
            $order->update_meta_data('_wc_balikovna_label_date', current_time('mysql'));
            $order->save();

            return array(
                'success' => true,
                'message' => __('Štítek byl úspěšně vygenerován', 'wc-balikovna-komplet')
            );
        } else {
            return array(
                'success' => false,
                'message' => $api_result['message']
            );
        }
    }

    /**
     * Generate label for Address delivery type
     *
     * @param WC_Order $order
     * @return array
     */
    private function generate_address_label($order)
    {
        // Get shipping address
        $address = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
        $city = $order->get_shipping_city() ?: $order->get_billing_city();
        $postcode = $order->get_shipping_postcode() ?: $order->get_billing_postcode();

        if (empty($address) || empty($city) || empty($postcode)) {
            return array(
                'success' => false,
                'message' => __('Dodací adresa není kompletní', 'wc-balikovna-komplet')
            );
        }

        // Prepare data for API
        $data = array(
            'deliveryType' => 'address',
            'address' => $address,
            'city' => $city,
            'postcode' => $postcode,
            'orderNumber' => $order->get_order_number(),
            'customerName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customerEmail' => $order->get_billing_email(),
            'customerPhone' => $order->get_billing_phone(),
            'weight' => $this->calculate_order_weight($order),
            'codAmount' => $order->get_payment_method() === 'cod' ? $order->get_total() : 0,
        );

        // Call API (placeholder for actual API implementation)
        $api_result = $this->call_api('label/generate', $data, $order);

        if ($api_result['success']) {
            // Save label info to order
            $order->update_meta_data('_wc_balikovna_label_generated', 'yes');
            $order->update_meta_data('_wc_balikovna_label_url', $api_result['label_url']);
            $order->update_meta_data('_wc_balikovna_label_date', current_time('mysql'));
            $order->save();

            return array(
                'success' => true,
                'message' => __('Štítek byl úspěšně vygenerován', 'wc-balikovna-komplet')
            );
        } else {
            return array(
                'success' => false,
                'message' => $api_result['message']
            );
        }
    }

    /**
     * Call API endpoint
     *
     * @param string $endpoint
     * @param array $data
     * @param WC_Order $order
     * @return array
     */
    private function call_api($endpoint, $data, $order)
    {
        // This is a placeholder for actual API implementation
        // In production, this would make an actual HTTP request to the Czech Post API
        
        // For now, we'll simulate a successful response
        // and generate a mock PDF URL
        
        $mock_label_url = WC_BALIKOVNA_PLUGIN_URL . 'assets/template.pdf';
        
        return array(
            'success' => true,
            'label_url' => $mock_label_url,
            'message' => __('API volání bylo úspěšné (MOCK)', 'wc-balikovna-komplet')
        );

        /*
        // Real API implementation would look like this:
        $url = $this->api_url . $endpoint;
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->api_username . ':' . $this->api_password)
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('Chyba při komunikaci s API: ', 'wc-balikovna-komplet') . $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['error'])) {
            return array(
                'success' => false,
                'message' => __('API chyba: ', 'wc-balikovna-komplet') . $result['error']
            );
        }

        return array(
            'success' => true,
            'label_url' => $result['label_url'],
            'message' => __('Štítek byl úspěšně vygenerován', 'wc-balikovna-komplet')
        );
        */
    }

    /**
     * Calculate order weight
     *
     * @param WC_Order $order
     * @return float
     */
    private function calculate_order_weight($order)
    {
        $weight = 0;

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_weight()) {
                $weight += floatval($product->get_weight()) * $item->get_quantity();
            }
        }

        // Default weight if no weight is set (in kg)
        if ($weight === 0) {
            $weight = 1.0;
        }

        return $weight;
    }
}
