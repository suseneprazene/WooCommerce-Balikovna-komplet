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
        error_log('=== WC Balíkovna: Starting label generation for order #' . $order->get_id() . ' ===');
        
        // Get delivery type
        $delivery_type = $order->get_meta('_wc_balikovna_delivery_type');

        if (empty($delivery_type)) {
            error_log('WC Balíkovna Label ERROR: No delivery type found for order #' . $order->get_id());
            return array(
                'success' => false,
                'message' => __('Tato objednávka není objednávka Balíkovny (chybí delivery_type)', 'wc-balikovna-komplet')
            );
        }

        error_log('WC Balíkovna Label: Delivery type: ' . $delivery_type);

        // Check if API credentials are set
        $api_token = get_option('wc_balikovna_api_token', '');
        $api_private_key = get_option('wc_balikovna_api_private_key', '');
        
        if (empty($api_token)) {
            error_log('WC Balíkovna Label ERROR: API token not configured');
            return array(
                'success' => false,
                'message' => __('API token není nastaven. Nastavte jej v Nastavení → Balíkovna.', 'wc-balikovna-komplet')
            );
        }
        
        if (empty($api_private_key)) {
            error_log('WC Balíkovna Label ERROR: API private key not configured');
            return array(
                'success' => false,
                'message' => __('Privátní klíč API není nastaven. Nastavte jej v Nastavení → Balíkovna.', 'wc-balikovna-komplet')
            );
        }

        // Validate sender information
        $missing_sender_fields = array();
        $sender_fields = array(
            'wc_balikovna_sender_name' => 'Jméno odesílatele',
            'wc_balikovna_sender_street' => 'Ulice odesílatele',
            'wc_balikovna_sender_city' => 'Město odesílatele',
            'wc_balikovna_sender_zip' => 'PSČ odesílatele',
            'wc_balikovna_sender_phone' => 'Telefon odesílatele',
            'wc_balikovna_sender_email' => 'Email odesílatele',
        );
        
        foreach ($sender_fields as $field => $label) {
            if (empty(get_option($field))) {
                $missing_sender_fields[] = $label;
            }
        }
        
        if (!empty($missing_sender_fields)) {
            error_log('WC Balíkovna Label ERROR: Missing sender fields: ' . implode(', ', $missing_sender_fields));
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Chybí údaje odesílatele: %s. Doplňte je v Nastavení → Balíkovna.', 'wc-balikovna-komplet'),
                    implode(', ', $missing_sender_fields)
                )
            );
        }

        // Generate label based on delivery type
        if ($delivery_type === 'box') {
            $result = $this->generate_box_label($order);
        } else {
            $result = $this->generate_address_label($order);
        }

        error_log('WC Balíkovna: Label generation result: ' . ($result['success'] ? 'SUCCESS' : 'FAILED'));
        if (!$result['success']) {
            error_log('WC Balíkovna Label ERROR: ' . $result['message']);
        }
        error_log('=== WC Balíkovna: Label generation finished ===');

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
        
        error_log('WC Balíkovna Label: Branch ID: ' . $branch_id);
        error_log('WC Balíkovna Label: Branch Name: ' . $branch_name);

        if (empty($branch_id)) {
            error_log('WC Balíkovna Label ERROR: Branch ID is empty for order #' . $order->get_id());
            return array(
                'success' => false,
                'message' => __('Pobočka nebyla vybrána při objednávce. ID pobočky chybí.', 'wc-balikovna-komplet')
            );
        }

        // Validate required order data
        $validation = $this->validate_order_data($order);
        if (!$validation['valid']) {
            error_log('WC Balíkovna Label ERROR: Missing order fields: ' . implode(', ', $validation['missing_fields']));
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Chybí údaje objednávky: %s', 'wc-balikovna-komplet'),
                    implode(', ', $validation['missing_fields'])
                )
            );
        }

        // Prepare data for API
        $data = array(
            'deliveryType' => 'box',
            'branchId' => $branch_id,
            'branchName' => $branch_name,
            'orderNumber' => $order->get_order_number(),
            'customerName' => $this->get_customer_name($order),
            'customerEmail' => $order->get_billing_email(),
            'customerPhone' => $order->get_billing_phone(),
            'weight' => $this->calculate_order_weight($order),
            'codAmount' => $order->get_payment_method() === 'cod' ? $order->get_total() : 0,
        );
        
        error_log('WC Balíkovna Label: Prepared data: ' . json_encode($data));

        // Call API
        $api_result = $this->call_api('label/generate', $data, $order);

        if ($api_result['success']) {
            // Save label info to order
            $order->update_meta_data('_wc_balikovna_label_generated', 'yes');
            $order->update_meta_data('_wc_balikovna_label_url', $api_result['label_url']);
            $order->update_meta_data('_wc_balikovna_label_date', current_time('mysql'));
            $order->save();
            
            error_log('WC Balíkovna Label: Label saved to order meta');

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
        
        error_log('WC Balíkovna Label: Address: ' . $address . ', ' . $city . ', ' . $postcode);

        if (empty($address) || empty($city) || empty($postcode)) {
            error_log('WC Balíkovna Label ERROR: Incomplete delivery address for order #' . $order->get_id());
            $missing = array();
            if (empty($address)) $missing[] = 'Ulice';
            if (empty($city)) $missing[] = 'Město';
            if (empty($postcode)) $missing[] = 'PSČ';
            
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Dodací adresa není kompletní. Chybí: %s', 'wc-balikovna-komplet'),
                    implode(', ', $missing)
                )
            );
        }
        
        // Validate required order data
        $validation = $this->validate_order_data($order);
        if (!$validation['valid']) {
            error_log('WC Balíkovna Label ERROR: Missing order fields: ' . implode(', ', $validation['missing_fields']));
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Chybí údaje objednávky: %s', 'wc-balikovna-komplet'),
                    implode(', ', $validation['missing_fields'])
                )
            );
        }

        // Prepare data for API
        $data = array(
            'deliveryType' => 'address',
            'address' => $address,
            'city' => $city,
            'postcode' => $postcode,
            'orderNumber' => $order->get_order_number(),
            'customerName' => $this->get_customer_name($order),
            'customerEmail' => $order->get_billing_email(),
            'customerPhone' => $order->get_billing_phone(),
            'weight' => $this->calculate_order_weight($order),
            'codAmount' => $order->get_payment_method() === 'cod' ? $order->get_total() : 0,
        );
        
        error_log('WC Balíkovna Label: Prepared data: ' . json_encode($data));

        // Call API
        $api_result = $this->call_api('label/generate', $data, $order);

        if ($api_result['success']) {
            // Save label info to order
            $order->update_meta_data('_wc_balikovna_label_generated', 'yes');
            $order->update_meta_data('_wc_balikovna_label_url', $api_result['label_url']);
            $order->update_meta_data('_wc_balikovna_label_date', current_time('mysql'));
            $order->save();
            
            error_log('WC Balíkovna Label: Label saved to order meta');

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
        // Get API credentials from settings
        $api_token = get_option('wc_balikovna_api_token', '');
        
        error_log('WC Balíkovna API: Calling endpoint: ' . $endpoint);
        error_log('WC Balíkovna API: Request data: ' . json_encode($data));
        
        // For now, this is a mock implementation
        // In production, this would make an actual HTTP request to the Czech Post API
        // The actual API endpoint and structure would need to be determined based on Czech Post documentation
        
        // Mock response for testing
        $mock_label_url = WC_BALIKOVNA_PLUGIN_URL . 'assets/template.pdf';
        
        error_log('WC Balíkovna API: Mock response - returning success with URL: ' . $mock_label_url);
        
        return array(
            'success' => true,
            'label_url' => $mock_label_url,
            'message' => __('Štítek byl úspěšně vygenerován (MOCK - implementace skutečného API volání čeká na dokumentaci API České pošty)', 'wc-balikovna-komplet')
        );

        /*
        // Real API implementation would look like this:
        $url = $this->api_url . $endpoint;
        
        $request_body = json_encode($data);
        
        error_log('WC Balíkovna API: Request URL: ' . $url);
        error_log('WC Balíkovna API: Request body: ' . $request_body);
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_token,
            ),
            'body' => $request_body,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('WC Balíkovna API ERROR: wp_remote_post failed: ' . $error_message);
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Chyba při komunikaci s API: %s', 'wc-balikovna-komplet'),
                    $error_message
                )
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('WC Balíkovna API: Response HTTP code: ' . $http_code);
        error_log('WC Balíkovna API: Response body: ' . $body);

        if ($http_code !== 200 && $http_code !== 201) {
            error_log('WC Balíkovna API ERROR: Non-success HTTP code: ' . $http_code);
            return array(
                'success' => false,
                'message' => sprintf(
                    __('API vrátilo chybu HTTP %d. Odpověď: %s', 'wc-balikovna-komplet'),
                    $http_code,
                    substr($body, 0, 200)
                )
            );
        }
        
        $result = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('WC Balíkovna API ERROR: Failed to parse JSON response: ' . json_last_error_msg());
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Chyba při parsování odpovědi API: %s', 'wc-balikovna-komplet'),
                    json_last_error_msg()
                )
            );
        }

        if (isset($result['error'])) {
            error_log('WC Balíkovna API ERROR: API returned error: ' . $result['error']);
            return array(
                'success' => false,
                'message' => sprintf(
                    __('API chyba: %s', 'wc-balikovna-komplet'),
                    $result['error']
                )
            );
        }
        
        if (!isset($result['label_url'])) {
            error_log('WC Balíkovna API ERROR: Missing label_url in API response');
            return array(
                'success' => false,
                'message' => __('API nevrátilo URL štítku', 'wc-balikovna-komplet')
            );
        }

        error_log('WC Balíkovna API: Success - label URL: ' . $result['label_url']);
        
        return array(
            'success' => true,
            'label_url' => $result['label_url'],
            'message' => __('Štítek byl úspěšně vygenerován', 'wc-balikovna-komplet')
        );
        */
    }

    /**
     * Validate required order data
     *
     * @param WC_Order $order
     * @return array Array with 'valid' (bool) and 'missing_fields' (array)
     */
    private function validate_order_data($order)
    {
        $missing_fields = array();
        
        if (empty($order->get_billing_first_name()) && empty($order->get_billing_last_name())) {
            $missing_fields[] = 'Jméno zákazníka';
        }
        if (empty($order->get_billing_email())) {
            $missing_fields[] = 'Email zákazníka';
        }
        if (empty($order->get_billing_phone())) {
            $missing_fields[] = 'Telefon zákazníka';
        }
        
        return array(
            'valid' => empty($missing_fields),
            'missing_fields' => $missing_fields
        );
    }

    /**
     * Get formatted customer name from order
     *
     * @param WC_Order $order
     * @return string
     */
    private function get_customer_name($order)
    {
        return trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
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
