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

    <div class="wc-balikovna-label-actions">
        <?php if ($label_generated === 'yes' && !empty($label_url)) : ?>
            <p class="wc-balikovna-download">
                <strong><?php echo esc_html__('Štítek byl vygenerován:', 'wc-balikovna-komplet'); ?></strong><br>
                <a href="<?php echo esc_url($label_url); ?>" target="_blank" class="button wc-balikovna-download-link">
                    <?php echo esc_html__('Stáhnout štítek', 'wc-balikovna-komplet'); ?>
                </a>
            </p>
            <p>
                <button type="button" class="button button-secondary wc-balikovna-regenerate-label" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                    <?php echo esc_html__('Regenerovat štítek', 'wc-balikovna-komplet'); ?>
                </button>
            </p>
        <?php else : ?>
            <p>
                <button type="button" class="button button-primary wc-balikovna-generate-label" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                    <?php echo esc_html__('Generovat štítek', 'wc-balikovna-komplet'); ?>
                </button>
            </p>
        <?php endif; ?>
        <div class="wc-balikovna-label-message" style="display: none; margin-top: 10px;"></div>
    </div>
</div>

<script>
jQuery(function($) {
    function runLabelAjax(orderId, $button, $messageDiv) {
        $button.prop('disabled', true);
        $messageDiv.hide().removeClass('notice-success notice-error');

        $.post(ajaxurl, {
            action: 'wc_balikovna_generate_label',
            order_id: orderId,
            nonce: '<?php echo wp_create_nonce('wc_balikovna_label_nonce'); ?>'
        }, function(response) {
            if (response && response.success) {
                $messageDiv.addClass('notice notice-success').html('<p>' + (response.data.message || '<?php echo esc_js(__('Štítek vygenerován', 'wc-balikovna-komplet')); ?>') + '</p>').show();

                if (response.data && response.data.label_url) {
                    var $dl = $('.wc-balikovna-download-link');
                    if ($dl.length) {
                        $dl.attr('href', response.data.label_url);
                    } else {
                        var $html = '<p class="wc-balikovna-download"><strong><?php echo esc_js(__('Štítek byl vygenerován:', 'wc-balikovna-komplet')); ?></strong><br>' +
                                    '<a href="' + response.data.label_url + '" target="_blank" class="button wc-balikovna-download-link"><?php echo esc_js(__('Stáhnout štítek', 'wc-balikovna-komplet')); ?></a></p>';
                        $('.wc-balikovna-label-actions').prepend($html);
                    }
                }

                setTimeout(function(){
                    location.reload();
                }, 1200);
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : (response && response.message ? response.message : '<?php echo esc_js(__('Chyba při generování štítku', 'wc-balikovna-komplet')); ?>');
                $messageDiv.addClass('notice notice-error').html('<p>' + msg + '</p>').show();
                $button.prop('disabled', false);
            }
        }, 'json').fail(function(jqXHR, textStatus) {
            $messageDiv.addClass('notice notice-error').html('<p><?php echo esc_js(__('Došlo k chybě při komunikaci se serverem', 'wc-balikovna-komplet')); ?></p>').show();
            $button.prop('disabled', false);
        });
    }

    // generovat poprvé
    $(document).on('click', '.wc-balikovna-generate-label', function(e) {
        e.preventDefault();
        var button = $(this), orderId = button.data('order-id'), messageDiv = $('.wc-balikovna-label-message');
        runLabelAjax(orderId, button, messageDiv);
    });

    // regenerovat
    $(document).on('click', '.wc-balikovna-regenerate-label', function(e) {
        e.preventDefault();
        var button = $(this), orderId = button.data('order-id'), messageDiv = $('.wc-balikovna-label-message');
        if (!confirm('<?php echo esc_js(__('Opravdu chcete vygenerovat nový štítek? Starý bude nahrazen.', 'wc-balikovna-komplet')); ?>')) {
            return;
        }
        runLabelAjax(orderId, button, messageDiv);
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
    $resp = array('message' => $result['message']);
    if (isset($result['label_url'])) {
        $resp['label_url'] = $result['label_url'];
    } else {
        // pokusíme se načíst uloženou meta (fallback)
        $label_url = $order->get_meta('_wc_balikovna_label_url');
        if (!empty($label_url)) {
            $resp['label_url'] = $label_url;
        }
    }
    wp_send_json_success($resp);
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

// --- Nahraď tento blok (místo volání API a zpracování výsledku) ---
$prepared = $this->prepare_label_data( $order );
if ( is_wp_error( $prepared ) ) {
    return array(
        'success' => false,
        'message' => $prepared->get_error_message()
    );
}

$pdf_result = $this->build_pdf_from_template( $prepared );
if ( is_wp_error( $pdf_result ) ) {
    return array(
        'success' => false,
        'message' => $pdf_result->get_error_message()
    );
}

if ( isset( $pdf_result['success'] ) && $pdf_result['success'] && ! empty( $pdf_result['url'] ) ) {
    // Save label info to order
    $order->update_meta_data('_wc_balikovna_label_generated', 'yes');
    $order->update_meta_data('_wc_balikovna_label_url', $pdf_result['url']);
    $order->update_meta_data('_wc_balikovna_label_date', current_time('mysql'));
    $order->save();

    error_log('WC Balíkovna Label: Label saved to order meta (generated PDF)');

    return array(
        'success' => true,
        'label_url' => $pdf_result['url'],
        'message' => __('Štítek byl úspěšně vygenerován', 'wc-balikovna-komplet')
    );
} else {
    return array(
        'success' => false,
        'message' => __('Chyba při generování PDF štítku', 'wc-balikovna-komplet')
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

      // --- Nahraď tento blok (místo volání API a zpracování výsledku) ---
$prepared = $this->prepare_label_data( $order );
if ( is_wp_error( $prepared ) ) {
    return array(
        'success' => false,
        'message' => $prepared->get_error_message()
    );
}

$pdf_result = $this->build_pdf_from_template( $prepared );
if ( is_wp_error( $pdf_result ) ) {
    return array(
        'success' => false,
        'message' => $pdf_result->get_error_message()
    );
}

if ( isset( $pdf_result['success'] ) && $pdf_result['success'] && ! empty( $pdf_result['url'] ) ) {
    // Save label info to order
    $order->update_meta_data('_wc_balikovna_label_generated', 'yes');
    $order->update_meta_data('_wc_balikovna_label_url', $pdf_result['url']);
    $order->update_meta_data('_wc_balikovna_label_date', current_time('mysql'));
    $order->save();

    error_log('WC Balíkovna Label: Label saved to order meta (generated PDF)');

    return array(
        'success' => true,
        'label_url' => $pdf_result['url'],
        'message' => __('Štítek byl úspěšně vygenerován', 'wc-balikovna-komplet')
    );
} else {
    return array(
        'success' => false,
        'message' => __('Chyba při generování PDF štítku', 'wc-balikovna-komplet')
    );
	} 
}

// --- Přidat: pomocné metody pro přípravu dat a generování PDF (vložit PŘED metodou call_api) ---

/**
 * Připraví data pro štítek z objednávky.
 *
 * @param int|WC_Order $order_or_id
 * @return array|WP_Error
 */
private function prepare_label_data( $order_or_id ) {
    $order = is_object( $order_or_id ) ? $order_or_id : wc_get_order( intval( $order_or_id ) );

    if ( ! $order ) {
        return new WP_Error( 'order_not_found', 'Objednávka nenalezena' );
    }

    // Odesílatel (možno upravit z nastavení pluginu)
    $sender = array(
        'name'         => get_option( 'wc_balikovna_sender_name', 'SU~PR sušené | pražené' ),
        'order_number' => $order->get_order_number() ?: $order->get_id(),
    );

// Příjemce (ze shipping, fallback na billing)
$recipient = array(
    'name'     => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
    'street'   => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
    'address_2'=> $order->get_shipping_address_2() ?: $order->get_meta('shipping_address_2') ?: '',
    'city'     => $order->get_shipping_city() ?: $order->get_billing_city(),
    'zipCode'  => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
);

    if ( empty( $recipient['name'] ) || empty( $recipient['street'] ) || empty( $recipient['city'] ) || empty( $recipient['zipCode'] ) ) {
        return new WP_Error( 'missing_recipient', 'Chybí některé povinné údaje příjemce' );
    }

    // Spočítat hmotnost (WooCommerce: obvykle v kg)
    $weight = 0.0;
    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        $qty     = $item->get_quantity();
        if ( $product ) {
            $prod_weight = $product->get_weight();
            $prod_weight = $prod_weight ? floatval( $prod_weight ) : 0.0;
            $weight += $prod_weight * $qty;
        }
    }
    if ( $weight <= 0 ) {
        $weight = 1.0;
    }

    return array(
        'sender'    => $sender,
        'recipient' => $recipient,
        'weight'    => number_format( $weight, 3, '.', '' ),
    );
}

/**
 * Vytvoří PDF štítek z template (FPDI + TCPDF nebo FPDI+FPDF fallback).
 *
 * @param array $data Výstup z prepare_label_data()
 * @return array|WP_Error ['success'=>true,'url'=>'...'] nebo WP_Error
 */
private function build_pdf_from_template( $data ) {
	// --- TEST: jednoduché rychlé PDF (izolace zápisu) ---
try {
    // pokusíme se použít TCPDF pokud existuje
    if ( class_exists('\TCPDF') ) {
        $upload_dir = wp_upload_dir();
        $dir = trailingslashit( $upload_dir['basedir'] ) . 'wc-balikovna-labels';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            error_log('WC Balíkovna DEBUG: Created labels dir: ' . $dir);
        }
        $test_file = trailingslashit( $dir ) . 'test_simple_' . time() . '.pdf';
        try {
            $testPdf = new \TCPDF('P','mm','A4');
            $testPdf->SetCreator('WC Balikovna Test');
            $testPdf->SetAuthor(get_bloginfo('name'));
            $testPdf->SetTitle('Test PDF');
            $testPdf->SetPrintHeader(false);
            $testPdf->SetPrintFooter(false);
            $testPdf->AddPage();
            // pro test nepoužívej diakritiku aby se vyloučil font problém
            $testPdf->SetFont('helvetica','',12);
            $testPdf->Write(0, 'TEST - tento text se má zobrazit v PDF. order:' . ($data['sender']['order_number'] ?? 'unknown'));
            $testPdf->Output($test_file, 'F');
            error_log('WC Balíkovna DEBUG: Test PDF generated: ' . $test_file);
            // vrátíme úspěch, aby se proces zastavil a bylo jasné, že zápis funguje
            return array('success' => true, 'url' => trailingslashit($upload_dir['baseurl']) . 'wc-balikovna-labels/' . basename($test_file), 'message' => 'Test PDF generated');
        } catch (Exception $e) {
            error_log('WC Balíkovna DEBUG: Test PDF exception: ' . $e->getMessage());
            // pokud padne, pokračujeme do normálního flow
        }
    } else {
        error_log('WC Balíkovna DEBUG: TCPDF class not found (class_exists returned false).');
    }
} catch (Exception $e) {
    error_log('WC Balíkovna DEBUG: Unexpected test exception: ' . $e->getMessage());
}
    $autoload = WC_BALIKOVNA_PLUGIN_DIR . 'vendor/autoload.php';
    $tcpdf_in_plugin = WC_BALIKOVNA_PLUGIN_DIR . 'tcpdf/tcpdf.php';
    $fpdi_in_plugin = WC_BALIKOVNA_PLUGIN_DIR . 'fpdi/src/autoload.php';

    if ( file_exists( $autoload ) ) {
        require_once $autoload;
    } else {
        if ( file_exists( $fpdi_in_plugin ) ) {
            require_once $fpdi_in_plugin;
        }
        if ( file_exists( $tcpdf_in_plugin ) ) {
            require_once $tcpdf_in_plugin;
        }
    }

    if ( ! class_exists( '\setasign\Fpdi\Tcpdf\Fpdi' ) && ! class_exists( '\setasign\Fpdi\Fpdi' ) ) {
        return new WP_Error( 'missing_lib', 'FPDI/TCPDF knihovny nejsou dostupné. Nainstalujte závislosti (composer require setasign/fpdi-tcpdf) nebo přidejte knihovny do pluginu.' );
    }

    try {
        $template_path = WC_BALIKOVNA_PLUGIN_DIR . 'assets/BAL_stitek_HD_balikovna.pdf';
        if ( ! file_exists( $template_path ) ) {
            return new WP_Error( 'template_missing', 'Šablona PDF nebyla nalezena: assets/template.pdf' );
        }

        // Instantiate appropriate FPDI class
        if ( class_exists( '\setasign\Fpdi\Tcpdf\Fpdi' ) ) {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        } elseif ( class_exists( '\setasign\Fpdi\Fpdi' ) ) {
            $pdf = new \setasign\Fpdi\Fpdi();
        } else {
            return new WP_Error( 'no_pdf_engine', 'Nevhodná PDF knihovna' );
        }

        // Metadata (pokud dostupné)
        if ( method_exists( $pdf, 'SetCreator' ) ) {
            $pdf->SetCreator( 'WooCommerce Balíkovna' );
            $pdf->SetAuthor( get_bloginfo( 'name' ) );
            $pdf->SetTitle( 'Balíkovna - Adresní štítek' );
        }
        if ( method_exists( $pdf, 'SetPrintHeader' ) ) {
            $pdf->SetPrintHeader( false );
            $pdf->SetPrintFooter( false );
        }

        // Načteme template a vykreslíme
        $pageCount = $pdf->setSourceFile( $template_path );
        $tplId = $pdf->importPage( 1 );

        $pdf->AddPage();
        $pdf->useTemplate( $tplId, 0, 0 );

        // Font (TCPDF podporuje UTF-8)
        if ( method_exists( $pdf, 'SetFont' ) ) {
            $pdf->SetFont( 'dejavusans', '', 10 );
        }

        $maxWidth = 85; // uprav dle šablony

        // --- Vykreslení polí (souřadnice uprav podle vaší šablony) ---
   // --- START: Výpis odesílatele — 4 řádky, co největším písmem do šířky $maxWidth ---
$senderX = 12; // uprav X pozici podle potřeby (mm)
$senderY = 17; // uprav Y pozici podle potřeby (mm)

// První řádek: Obj. č. {číslo} z SU~PR sušené | pražené
$order_number_display = '';
if ( isset( $data['sender']['order_number'] ) && ! empty( $data['sender']['order_number'] ) ) {
    $order_number_display = $data['sender']['order_number'];
} elseif ( isset( $order ) && is_object( $order ) ) {
    $order_number_display = $order->get_id();
}

$sender_lines = array(
    'Obj. č. ' . $order_number_display . ' z SU~PR sušené | pražené',
    'Petrašovice 61',
    'Bílá - Petrašovice',
    '463 42',
);

// Font a počáteční velikost (mm jednotky používá TCPDF v bodech fontSize)
$font_family = 'dejavusans';
$font_style = '';
$fontSize = 10; // počáteční velikost — bude snižována pokud se nevejde
$minFontSize = 3; // minimální velikost které dovolíme
$lineGap = 1; // mezera mezi řádky v mm (násobek fontSize použijeme níže)

// Zajistíme, že GetStringWidth použije stejný font a velikost při měření
// Pokud GetStringWidth nepodporuje čtvrtý parametr v tvé sestavě TCPDF, použij jen $pdf->GetStringWidth($text) a uprav logiku.
$fits = false;
while ( $fontSize >= $minFontSize ) {
    $tooWide = false;
    foreach ( $sender_lines as $line ) {
        // GetStringWidth(text, font, style, fontsize) - vrací šířku v aktuálních jednotkách (mm)
        $w = $pdf->GetStringWidth( $line, $font_family, $font_style, $fontSize );
        if ( $w > $maxWidth ) {
            $tooWide = true;
            break;
        }
    }
    if ( ! $tooWide ) {
        $fits = true;
        break;
    }
    $fontSize -= 1; // snižujeme o 1 pt dokud se to nevejde
}

// Pokud se nic nevejde i při minFontSize, ořízneme text (truncation) nebo zmenšíme ještě víc
if ( ! $fits ) {
    // fallback: použij minFontSize a případně zkrátit první řádek pokud je potřeba
    $fontSize = $minFontSize;
    // volitelně: můžeme zkrátit první řádek, aby se vešel
    $first = $sender_lines[0];
    while ( $pdf->GetStringWidth( $first, $font_family, $font_style, $fontSize ) > $maxWidth && mb_strlen($first) > 3 ) {
        $first = mb_substr( $first, 0, mb_strlen($first) - 1 );
    }
    if ( mb_strlen($first) < mb_strlen($sender_lines[0]) ) {
        $first = rtrim( $first ) . '…';
        $sender_lines[0] = $first;
    }
}

// Nastavíme font a vykreslíme řádky
$pdf->SetFont( $font_family, $font_style, $fontSize );
$pdf->SetTextColor( 0, 0, 0 );

// spočítáme výšku jednoho řádku (přibližně)
// TCPDF používá font size v bodech; přepočet na mm pro řádkování: použijeme jednoduchý faktor
$lineHeight = max(  ( $fontSize * 0.35 ), 4 ); // 0.35 ~ převod na mm přijatelný, 4mm min výška řádku

$curY = $senderY;
foreach ( $sender_lines as $line ) {
    $pdf->SetXY( $senderX, $curY );
    // Použijeme Cell přesně do šířky $maxWidth a zarovnání vlevo
    $pdf->Cell( $maxWidth, $lineHeight, $line, 0, 1, 'L', 0, '', 0 );
    $curY += $lineHeight + $lineGap;
}

// Posuň kurzor pod tuto sekci, aby další obsah nezačínal přes odesílatele
$pdf->SetY( $curY + 1 ); // 1 mm mezera navíc
// --- END: Výpis odesílatele ---

// --- START: Sekce "Adresát" (1: jméno TUČNĚ; 2: shipping_address_2 nebo branch; 3: ulice+č.p.; 4: PSČ + město TUČNĚ) ---
$recipientX = 12;
$recipientY = 45; // původní pozice

// Primární zdroj dat z $data
$recipient_name = !empty($data['recipient']['name']) ? wp_strip_all_tags($data['recipient']['name']) : '';
$address1 = !empty($data['recipient']['street']) ? wp_strip_all_tags($data['recipient']['street']) : '';
$address2 = !empty($data['recipient']['address_2']) ? wp_strip_all_tags($data['recipient']['address_2']) : '';
$city = !empty($data['recipient']['city']) ? wp_strip_all_tags($data['recipient']['city']) : '';
$zip  = !empty($data['recipient']['zipCode']) ? wp_strip_all_tags($data['recipient']['zipCode']) : '';

// Fallbacky: pokud chybí některé údaje, zkusíme objednávku podle sender.order_number
$branch_id = !empty($data['recipient']['branch_id']) ? wp_strip_all_tags($data['recipient']['branch_id']) : '';
$branch_name = !empty($data['recipient']['branch_name']) ? wp_strip_all_tags($data['recipient']['branch_name']) : '';

if ( empty($address2) || empty($address1) || empty($city) || empty($zip) || empty($recipient_name) || ( empty($branch_id) && empty($branch_name) ) ) {
    $order_candidate = null;
    if ( ! empty( $data['sender']['order_number'] ) ) {
        $on = $data['sender']['order_number'];
        if ( is_numeric( $on ) ) {
            $order_candidate = wc_get_order( intval( $on ) );
        } else {
            // někdy prepare_label_data může vrátit order_number jako text — zkus nejdřív ID, pak order number
            $order_candidate = wc_get_order( intval( $on ) );
        }
    }
    if ( $order_candidate ) {
        if ( empty($recipient_name) ) {
            $recipient_name = trim( $order_candidate->get_shipping_first_name() . ' ' . $order_candidate->get_shipping_last_name() );
            if ( empty($recipient_name) ) {
                $recipient_name = trim( $order_candidate->get_billing_first_name() . ' ' . $order_candidate->get_billing_last_name() );
            }
        }
        if ( empty($address1) ) {
            $address1 = $order_candidate->get_shipping_address_1() ?: $order_candidate->get_billing_address_1();
        }
        if ( empty($address2) ) {
            // TADY: konkrétně bereme shipping_address_2 z objednávky (pole, které jsi chtěl)
            $address2 = $order_candidate->get_shipping_address_2() ?: $order_candidate->get_meta('shipping_address_2');
        }
        if ( empty($city) ) {
            $city = $order_candidate->get_shipping_city() ?: $order_candidate->get_billing_city();
        }
        if ( empty($zip) ) {
            $zip = $order_candidate->get_shipping_postcode() ?: $order_candidate->get_billing_postcode();
        }
        if ( empty($branch_id) ) {
            $branch_id = $order_candidate->get_meta('_wc_balikovna_branch_id');
        }
        if ( empty($branch_name) ) {
            $branch_name = $order_candidate->get_meta('_wc_balikovna_branch_name');
        }
    }
}

// Druhý řádek: preferuj address2 (shipping_address_2), pokud prázdné, použij branch_name nebo "Balíkovna"
$line1 = $recipient_name;
$line2 = '';
if ( ! empty( $address2 ) ) {
    $line2 = $address2;
} else {
    if ( ! empty( $branch_name ) ) {
        $line2 = $branch_name;
    } elseif ( ! empty( $branch_id ) ) {
        $line2 = 'Balíkovna ID: ' . $branch_id;
    } else {
        // pokud opravdu nic není, nechte prázdné (nebude vykresleno)
        $line2 = '';
    }
}
$line3 = $address1;
$line4 = trim( $zip . ' ' . $city );

// Vytvoříme pole řádků (max 4), přeskočíme prázdné
$recipient_lines = array();
if ( $line1 !== '' ) $recipient_lines[] = $line1;
if ( $line2 !== '' ) $recipient_lines[] = $line2;
if ( $line3 !== '' ) $recipient_lines[] = $line3;
if ( $line4 !== '' ) $recipient_lines[] = $line4;

// Fonty / velikosti
$font_family = 'dejavusans';
$fontSizeBold = 12;
$fontSizeNormal = 11;
$minFontSize = 7;

// Helper pro vykreslení jedné linky, vrací použité lineHeight v mm
$drawLine = function( $text, $x, $y, $width, $fontFamily, $fontStyle, $fontSize ) use ( $pdf, $minFontSize ) {
    $fs = $fontSize;
    while ( $fs >= $minFontSize ) {
        $w = $pdf->GetStringWidth( $text, $fontFamily, $fontStyle, $fs );
        if ( $w <= $width ) break;
        $fs -= 1;
    }
    if ( $fs < $minFontSize ) $fs = $minFontSize;
    $pdf->SetFont( $fontFamily, $fontStyle, $fs );
    $pdf->SetXY( $x, $y );
    $pdf->Cell( $width, 0, $text, 0, 1, 'L', 0, '', 0 );
    return max( ( $fs * 0.35 ), 4 );
};

// Vykreslíme řádky s požadovanými styly (1. tučně; 2. normálně; 3. normálně; 4. tučně)
$curY = $recipientY;
$totalLines = count( $recipient_lines );
foreach ( $recipient_lines as $idx => $line ) {
    // 1. řádek je vždy první (idx==0) -> bold
    if ( $idx === 0 ) {
        $lineHeight = $drawLine( $line, $recipientX, $curY, $maxWidth, $font_family, 'B', $fontSizeBold );
    } elseif ( $idx === 3 ) {
        // pokud máme skutečně 4 řádky, 4. má být tučný (PSČ + město)
        $lineHeight = $drawLine( $line, $recipientX, $curY, $maxWidth, $font_family, 'B', $fontSizeBold );
    } else {
        // ostatní řádky normální
        $lineHeight = $drawLine( $line, $recipientX, $curY, $maxWidth, $font_family, '', $fontSizeNormal );
    }
    $curY += $lineHeight + 1; // 1 mm gap
}

// Nastavíme kurzor pod adresátem
$pdf->SetY( $curY + 1 );
// --- END: Sekce "Adresát" ---

// Vložíme piktogram a do něj vykreslíme hmotnost (formát x,yy bez jednotky)
$img_path = WC_BALIKOVNA_PLUGIN_DIR . 'assets/18_hmotnost_hodnota_20_10.jpg';
$x = 80;
$y = $pdf->GetY() + 5;
$img_w = 20; // šířka v mm (uprav dle potřeby)
$img_h = 10; // výška v mm (uprav dle potřeby)

// Formát hmotnosti pro zobrazení: 2 desetinná místa + čárka jako oddělovač
$weight_display = is_numeric( $data['weight'] ) ? number_format( (float) $data['weight'], 2, ',', '' ) : $data['weight'];

if ( file_exists( $img_path ) ) {
    // Vložíme obrázek
    $pdf->Image( $img_path, $x, $y, $img_w, $img_h, 'JPG' );

    // Font pro text uvnitř obrázku
    if ( method_exists( $pdf, 'SetFont' ) ) {
        $pdf->SetFont( 'dejavusans', 'B', 9 );
    } else {
        $pdf->SetFont( 'Helvetica', 'B', 9 ); // fallback
    }
    $pdf->SetTextColor(0, 0, 0);

    // Připravíme text (bez jednotky)
    $text = $weight_display;

    // Zjistíme šířku textu (metoda vrací šířku v aktuálních jednotkách)
    $textWidth = $pdf->GetStringWidth( $text );

    // Vypočítáme pozici pro centrovaný text uvnitř obrázku
    $textX = $x + max(0, ($img_w - $textWidth) / 2);
    $textY = $y + ($img_h / 2) - 2; // ladicí posun; uprav podle potřeby

    // --- POSUN TEXTU DOPRAVA: uprav hodnotu $offset_right_mm (v mm) ---
    $offset_right_mm = 3; // <--- změň na 0/1/2/... podle potřeby
    $textX += $offset_right_mm;

    // Vykreslíme text pomocí přesné X pozice (zarovnáno vlevo, protože jsme spočítali přesný X)
    $pdf->SetXY( $textX, $textY );
    $pdf->Cell( $textWidth, 0, $text, 0, 1, 'L', 0, '', 0 );

    // Po vykreslení obrázku zajistíme, že další obsah nezačne přes něj
    $pdf->SetY( $y + $img_h + 1 ); // 1 mm mezera pod obrázkem, uprav dle potřeby

} else {
    // Fallback: pokud obrázek chybí, vypíšeme jen číslo bez jednotky
    $pdf->SetXY( 75, $pdf->GetY() + 10 );
    $pdf->SetFont( 'dejavusans', '', 10 );
    $pdf->MultiCell( $maxWidth, 5, $weight_display, 0, 'L' );
    error_log( 'WC Balíkovna DEBUG: weight icon not found: ' . $img_path );
}

        // Uložit do uploads
        $upload_dir = wp_upload_dir();
        $dir = trailingslashit( $upload_dir['basedir'] ) . 'wc-balikovna-labels';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $filename = 'balikovna_label_' . sanitize_file_name( (string) $data['sender']['order_number'] ) . '_' . time() . '.pdf';
        $filepath = trailingslashit( $dir ) . $filename;

        $pdf->Output( $filepath, 'F' );

        $url = trailingslashit( $upload_dir['baseurl'] ) . 'wc-balikovna-labels/' . $filename;
        return array( 'success' => true, 'url' => $url );

    } catch ( Exception $e ) {
        return new WP_Error( 'pdf_error', 'Chyba při generování PDF: ' . $e->getMessage() );
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
        $mock_label_url = WC_BALIKOVNA_PLUGIN_URL . 'assets/BAL_stitek_HD_balikovna.pdf';
        
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
