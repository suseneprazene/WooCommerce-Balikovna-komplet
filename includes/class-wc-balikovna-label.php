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
        'name'    => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
        'street'  => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
        'city'    => $order->get_shipping_city() ?: $order->get_billing_city(),
        'zipCode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
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
        $template_path = WC_BALIKOVNA_PLUGIN_DIR . 'assets/template.pdf';
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
        $pdf->SetXY( 12, 23 );
        $pdf->MultiCell( $maxWidth, 5, wp_strip_all_tags( $data['sender']['name'] ), 0, 'L' );
        $pdf->SetXY( 12, $pdf->GetY() + 2 );
        $pdf->MultiCell( $maxWidth, 5, 'Objednávka č.: ' . wp_strip_all_tags( $data['sender']['order_number'] ), 0, 'L' );

        $pdf->SetXY( 12, 45 );
        $pdf->MultiCell( $maxWidth, 5, wp_strip_all_tags( $data['recipient']['name'] ), 0, 'L' );
        $pdf->SetXY( 12, $pdf->GetY() + 2 );
        $pdf->MultiCell( $maxWidth, 5, wp_strip_all_tags( $data['recipient']['street'] ), 0, 'L' );
        $pdf->SetXY( 12, $pdf->GetY() + 2 );
        $pdf->MultiCell( $maxWidth, 5, wp_strip_all_tags( $data['recipient']['city'] . ' ' . $data['recipient']['zipCode'] ), 0, 'L' );

        $pdf->SetXY( 12, $pdf->GetY() + 8 );
        $pdf->MultiCell( $maxWidth, 5, 'Hmotnost zásilky: ' . $data['weight'] . ' kg', 0, 'L' );

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
