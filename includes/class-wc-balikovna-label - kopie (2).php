<?php
/**
 * Label generation for Balikovna
 *
 * @package WC_Balikovna_Komplet
 */
// doÄŤasnÄ› vloĹľ do WC_Balikovna_Komplet::init() na zaÄŤĂˇtek

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

    // Handle AJAX request for label generation (admin)
    add_action('wp_ajax_wc_balikovna_generate_label', array($this, 'handle_label_generation'));

    // Save selected branch on checkout (store branch meta into order)
    add_action('woocommerce_checkout_update_order_meta', array($this, 'save_branch_meta_on_checkout'), 10, 2);
    // AJAX endpoint to set branch into WC()->session (frontend)
    add_action('wp_ajax_wc_balikovna_set_branch', array($this, 'ajax_set_branch'));
    add_action('wp_ajax_nopriv_wc_balikovna_set_branch', array($this, 'ajax_set_branch'));
}

    /**
     * Add label generation button to order admin page
     *
     * @param WC_Order $order
     */
public function add_label_button($order)
{
// --- START: korektnĂ­ naÄŤtenĂ­ delivery_type + branch pro admin UI (add_label_button) ---
$delivery_type = $order->get_meta('_wc_balikovna_delivery_type');
$branch_name   = $order->get_meta('_wc_balikovna_branch_name');
$branch_type   = $order->get_meta('_wc_balikovna_branch_type');
$branch_icon   = $order->get_meta('_wc_balikovna_branch_icon');

// Pokud meta chybĂ­, zkusĂ­me detekovat pomocĂ­ prepare_label_data() â€” pouze pro zobrazenĂ­ v adminu.
// NepĹ™episujeme objednĂˇvku a nevracĂ­me pole, jen doplnĂ­me hodnoty pro UI.
if ( empty( $delivery_type ) ) {
    $detected = $this->prepare_label_data( $order );
    if ( ! is_wp_error( $detected ) ) {
        if ( ! empty( $detected['deliveryType'] ) ) {
            $delivery_type = $detected['deliveryType'];
        }
        if ( ! empty( $detected['recipient'] ) && is_array( $detected['recipient'] ) ) {
            if ( empty( $branch_name ) && ! empty( $detected['recipient']['branch_name'] ) ) {
                $branch_name = $detected['recipient']['branch_name'];
            }
            if ( empty( $branch_type ) && ! empty( $detected['recipient']['branch_type'] ) ) {
                $branch_type = $detected['recipient']['branch_type'];
            }
            if ( empty( $branch_icon ) && ! empty( $detected['recipient']['branch_icon'] ) ) {
                $branch_icon = $detected['recipient']['branch_icon'];
            }
        }
    } else {
        // debug only â€” nezastavujeme UI
        error_log( 'WC BalĂ­kovna DEBUG (admin display): prepare_label_data failed: ' . $detected->get_error_message() );
    }
}
// --- END: korektnĂ­ naÄŤtenĂ­ delivery_type + branch pro admin UI ---
    // Pokud objednĂˇvka vĹŻbec nemĂˇ BalĂ­kovna shipping (ani meta, ani shipping item), nic nepĹ™idĂˇme
    $has_balikovna = ( $order->get_meta('_wc_balikovna_branch_id') || $order->get_meta('_wc_balikovna_branch_name') );
    if ( ! $has_balikovna ) {
        // zkusĂ­me kontrolu pĹ™es shipping items: pokud existuje poloĹľka s method_id=balikovna, povaĹľujeme to za BalĂ­kovnu
        foreach ( $order->get_items( 'shipping' ) as $si ) {
            if ( method_exists( $si, 'get_method_id' ) && $si->get_method_id() === 'balikovna' ) {
                $has_balikovna = true;
                break;
            }
        }
    }
    if ( ! $has_balikovna ) {
        return;
    }

    // Normalizovat hodnotu pro zobrazenĂ­
    if ( empty( $delivery_type ) ) {
        $delivery_type_label = __( 'unknown', 'wc-balikovna-komplet' );
    } else {
        $delivery_type_label = $delivery_type === 'address' ? __( 'Na adresu', 'wc-balikovna-komplet' ) : __( 'Do boxu / poboÄŤky', 'wc-balikovna-komplet' );
    }

    // pĹ™ipravit ikonku (pokud mĂˇme filename)
    $branch_icon_html = '';
    if ( ! empty( $branch_icon ) ) {
        $icon_path = $this->get_asset_path( $branch_icon );
        if ( $icon_path && file_exists( $icon_path ) ) {
            $icon_url = str_replace( WP_CONTENT_DIR, content_url(), $icon_path ); // relativnĂ­ URL fallback
            // lepĹˇĂ­ variantu mĹŻĹľeĹˇ udÄ›lat pĹ™es upload_url nebo WP funkce; tady jen jednoduchĂ© zobrazenĂ­
            $branch_icon_html = '<img src="' . esc_url( $icon_url ) . '" style="height:24px;margin-right:6px;vertical-align:middle;" alt="" />';
        }
    }

    // render sekce v adminu (zachovĂˇme pĹŻvodnĂ­ tlaÄŤĂ­tka)
    ?>
    <div class="wc-balikovna-label-section" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
        <h3><?php echo esc_html__( 'BalĂ­kovna', 'wc-balikovna-komplet' ); ?></h3>

        <div style="margin-bottom:10px;">
            <strong><?php echo esc_html__( 'Typ doruÄŤenĂ­:', 'wc-balikovna-komplet' ); ?></strong>
            <span style="margin-left:8px;"><?php echo esc_html( $delivery_type_label ); ?></span>
        </div>

        <?php if ( ! empty( $branch_name ) ) : ?>
            <div style="margin-bottom:8px;">
                <strong><?php echo esc_html__( 'PoboÄŤka:', 'wc-balikovna-komplet' ); ?></strong>
                <span style="margin-left:8px;"><?php echo esc_html( $branch_name ); ?></span>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $branch_type ) ) : ?>
            <div style="margin-bottom:8px;">
                <strong><?php echo esc_html__( 'Druh poboÄŤky:', 'wc-balikovna-komplet' ); ?></strong>
                <span style="margin-left:8px;"><?php echo esc_html( $branch_type ); ?></span>
            </div>
        <?php endif; ?>

        <?php if ( $branch_icon_html ) : ?>
            <div style="margin-bottom:12px;">
                <strong><?php echo esc_html__( 'Ikona poboÄŤky:', 'wc-balikovna-komplet' ); ?></strong>
                <span style="margin-left:8px;"><?php echo $branch_icon_html; ?></span>
            </div>
        <?php endif; ?>

        <div class="wc-balikovna-label-actions">
            <?php
            $label_generated = $order->get_meta('_wc_balikovna_label_generated');
            $label_url = $order->get_meta('_wc_balikovna_label_url');
            if ( $label_generated === 'yes' && ! empty( $label_url ) ) :
            ?>
                <p class="wc-balikovna-download">
                    <strong><?php echo esc_html__( 'Ĺ tĂ­tek byl vygenerovĂˇn:', 'wc-balikovna-komplet' ); ?></strong><br>
                    <a href="<?php echo esc_url( $label_url ); ?>" target="_blank" class="button wc-balikovna-download-link">
                        <?php echo esc_html__( 'StĂˇhnout ĹˇtĂ­tek', 'wc-balikovna-komplet' ); ?>
                    </a>
                </p>
                <p>
                    <button type="button" class="button button-secondary wc-balikovna-regenerate-label" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                        <?php echo esc_html__( 'Regenerovat ĹˇtĂ­tek', 'wc-balikovna-komplet' ); ?>
                    </button>
                </p>
            <?php else : ?>
                <p>
                    <button type="button" class="button button-primary wc-balikovna-generate-label" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                        <?php echo esc_html__( 'Generovat ĹˇtĂ­tek', 'wc-balikovna-komplet' ); ?>
                    </button>
                </p>
            <?php endif; ?>
            <div class="wc-balikovna-label-message" style="display: none; margin-top: 10px;"></div>
        </div>
    </div>
	
	<script>
jQuery(function($){
    // zabezpeÄŤenĂ˝ nonce vloĹľenĂ˝ PHP do JS
    var nonce = '<?php echo wp_create_nonce("wc_balikovna_label_nonce"); ?>';

    function runLabelAjax(orderId, $button, $messageDiv) {
        $button.prop('disabled', true);
        $messageDiv.hide().removeClass('notice-success notice-error');

        $.post(ajaxurl, {
            action: 'wc_balikovna_generate_label',
            order_id: orderId,
            nonce: nonce
        }, function(response) {
            if (response && response.success) {
                $messageDiv.addClass('notice notice-success').html('<p>' + (response.data.message || 'Ĺ tĂ­tek vygenerovĂˇn') + '</p>').show();

                if (response.data && response.data.label_url) {
                    var $dl = $('.wc-balikovna-download-link');
                    if ($dl.length) {
                        $dl.attr('href', response.data.label_url);
                    } else {
                        var $html = '<p class="wc-balikovna-download"><strong>Ĺ tĂ­tek byl vygenerovĂˇn:</strong><br>' +
                                    '<a href="' + response.data.label_url + '" target="_blank" class="button wc-balikovna-download-link">StĂˇhnout ĹˇtĂ­tek</a></p>';
                        $('.wc-balikovna-label-actions').prepend($html);
                    }
                }

                // refresh page lightly to show updated meta / url (nebo jen reinstrumentovat UI)
                setTimeout(function(){
                    location.reload();
                }, 900);
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : (response && response.message ? response.message : 'Chyba pĹ™i generovĂˇnĂ­ ĹˇtĂ­tku');
                $messageDiv.addClass('notice notice-error').html('<p>' + msg + '</p>').show();
                $button.prop('disabled', false);
            }
        }, 'json').fail(function(jqXHR, textStatus, err) {
            $messageDiv.addClass('notice notice-error').html('<p>DoĹˇlo k chybÄ› pĹ™i komunikaci se serverem: ' + textStatus + '</p>').show();
            $button.prop('disabled', false);
            console.error('WC Balikovna AJAX fail', textStatus, err, jqXHR);
        });
    }

    // DelegovanĂ© click handlery (funguje i po AJAX reloadu)
    $(document).on('click', '.wc-balikovna-generate-label', function(e){
        e.preventDefault();
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        var $msg = $btn.closest('.wc-balikovna-label-section').find('.wc-balikovna-label-message');
        runLabelAjax(orderId, $btn, $msg);
    });

    $(document).on('click', '.wc-balikovna-regenerate-label', function(e){
        e.preventDefault();
        if (!confirm('Opravdu chcete vygenerovat novĂ˝ ĹˇtĂ­tek? StarĂ˝ bude nahrazen.')) return;
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        var $msg = $btn.closest('.wc-balikovna-label-section').find('.wc-balikovna-label-message');
        runLabelAjax(orderId, $btn, $msg);
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
            wp_send_json_error(array('message' => __('NeplatnĂ˝ bezpeÄŤnostnĂ­ token', 'wc-balikovna-komplet')));
        }

        // Check user permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('NemĂˇte oprĂˇvnÄ›nĂ­ provĂ©st tuto akci', 'wc-balikovna-komplet')));
        }

        // Get order ID
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('NeplatnĂ© ID objednĂˇvky', 'wc-balikovna-komplet')));
        }

        // Get order
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => __('ObjednĂˇvka nebyla nalezena', 'wc-balikovna-komplet')));
        }

        // Generate label
        $result = $this->generate_label($order);

        if ($result['success']) {
            $resp = array('message' => $result['message']);
            if (isset($result['label_url'])) {
                $resp['label_url'] = $result['label_url'];
            } else {
                // pokusĂ­me se naÄŤĂ­st uloĹľenou meta (fallback)
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
        error_log('=== WC BalĂ­kovna: Starting label generation for order #' . $order->get_id() . ' ===');
        
// RobustnĂ­ detekce delivery type (meta -> fallback na prepare_label_data)
$delivery_type = $order->get_meta('_wc_balikovna_delivery_type');

if ( empty( $delivery_type ) ) {
    error_log( 'WC BalĂ­kovna Label: delivery_type meta empty for order #' . $order->get_id() . ' â€” trying prepare_label_data()' );

    $detection = $this->prepare_label_data( $order );
    if ( is_wp_error( $detection ) ) {
        // pokud detekce selhala, napiĹˇ chybovou hlĂˇĹˇku a vraĹĄ se s uĹľiteÄŤnou chybou
        error_log( 'WC BalĂ­kovna Label ERROR: prepare_label_data failed for order #' . $order->get_id() . ' - ' . $detection->get_error_message() );
        return array(
            'success' => false,
            'message' => __( 'Nelze urÄŤit typ dopravy (box/adresa) pro tuto objednĂˇvku. Zkontrolujte metadata BalĂ­kovny.', 'wc-balikovna-komplet' )
        );
    }

    if ( isset( $detection['deliveryType'] ) && ! empty( $detection['deliveryType'] ) ) {
        $delivery_type = $detection['deliveryType'];
        error_log( 'WC BalĂ­kovna Label: delivery_type detected by prepare_label_data: ' . $delivery_type . ' for order #' . $order->get_id() );

        // VolitelnÄ›: persist resolved delivery type so admin + future calls use it.
        // UloĹľĂ­me to jen kdyĹľ meta byla pĹŻvodnÄ› prĂˇzdnĂˇ (tj. detekovali jsme ji)
        try {
            $order->update_meta_data('_wc_balikovna_delivery_type', $delivery_type);
            $order->save();
            error_log( 'WC BalĂ­kovna DEBUG: persisted delivery_type "' . $delivery_type . '" to order #' . $order->get_id() );
        } catch ( Exception $e ) {
            error_log( 'WC BalĂ­kovna DEBUG: could not persist delivery_type: ' . $e->getMessage() );
        }
    }
}

// KoneÄŤnĂˇ kontrola
if ( empty( $delivery_type ) ) {
    error_log( 'WC BalĂ­kovna Label ERROR: No delivery type resolved for order #' . $order->get_id() );
    return array(
        'success' => false,
        'message' => __( 'Tato objednĂˇvka nemĂˇ urÄŤen typ dodĂˇnĂ­ (box vs adresa).', 'wc-balikovna-komplet' )
    );
}



        // Check if API credentials are set
        $api_token = get_option('wc_balikovna_api_token', '');
        $api_private_key = get_option('wc_balikovna_api_private_key', '');
        
        if (empty($api_token)) {
            error_log('WC BalĂ­kovna Label ERROR: API token not configured');
            return array(
                'success' => false,
                'message' => __('API token nenĂ­ nastaven. Nastavte jej v NastavenĂ­ â†’ BalĂ­kovna.', 'wc-balikovna-komplet')
            );
        }
        
        if (empty($api_private_key)) {
            error_log('WC BalĂ­kovna Label ERROR: API private key not configured');
            return array(
                'success' => false,
                'message' => __('PrivĂˇtnĂ­ klĂ­ÄŤ API nenĂ­ nastaven. Nastavte jej v NastavenĂ­ â†’ BalĂ­kovna.', 'wc-balikovna-komplet')
            );
        }

        // Validate sender information
        $missing_sender_fields = array();
        $sender_fields = array(
            'wc_balikovna_sender_name' => 'JmĂ©no odesĂ­latele',
            'wc_balikovna_sender_street' => 'Ulice odesĂ­latele',
            'wc_balikovna_sender_city' => 'MÄ›sto odesĂ­latele',
            'wc_balikovna_sender_zip' => 'PSÄŚ odesĂ­latele',
            'wc_balikovna_sender_phone' => 'Telefon odesĂ­latele',
            'wc_balikovna_sender_email' => 'Email odesĂ­latele',
        );
        
        foreach ($sender_fields as $field => $label) {
            if (empty(get_option($field))) {
                $missing_sender_fields[] = $label;
            }
        }
        
        if (!empty($missing_sender_fields)) {
            error_log('WC BalĂ­kovna Label ERROR: Missing sender fields: ' . implode(', ', $missing_sender_fields));
            return array(
                'success' => false,
                'message' => sprintf(
                    __('ChybĂ­ Ăşdaje odesĂ­latele: %s. DoplĹte je v NastavenĂ­ â†’ BalĂ­kovna.', 'wc-balikovna-komplet'),
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

        error_log('WC BalĂ­kovna: Label generation result: ' . ($result['success'] ? 'SUCCESS' : 'FAILED'));
        if (!$result['success']) {
            error_log('WC BalĂ­kovna Label ERROR: ' . $result['message']);
        }
        error_log('=== WC BalĂ­kovna: Label generation finished ===');

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
        
        error_log('WC BalĂ­kovna Label: Branch ID: ' . $branch_id);
        error_log('WC BalĂ­kovna Label: Branch Name: ' . $branch_name);

        if (empty($branch_id)) {
            error_log('WC BalĂ­kovna Label ERROR: Branch ID is empty for order #' . $order->get_id());
            return array(
                'success' => false,
                'message' => __('PoboÄŤka nebyla vybrĂˇna pĹ™i objednĂˇvce. ID poboÄŤky chybĂ­.', 'wc-balikovna-komplet')
            );
        }

        // Validate required order data
        $validation = $this->validate_order_data($order);
        if (!$validation['valid']) {
            error_log('WC BalĂ­kovna Label ERROR: Missing order fields: ' . implode(', ', $validation['missing_fields']));
            return array(
                'success' => false,
                'message' => sprintf(
                    __('ChybĂ­ Ăşdaje objednĂˇvky: %s', 'wc-balikovna-komplet'),
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
        
        error_log('WC BalĂ­kovna Label: Prepared data: ' . json_encode($data));

 // PĹ™ipravĂ­me data pro PDF (pouze jednou) a vynutĂ­me deliveryType = 'box'
$prepared = $this->prepare_label_data( $order );
if ( is_wp_error( $prepared ) ) {
    return array(
        'success' => false,
        'message' => $prepared->get_error_message()
    );
}

// FORCE deliveryType = 'box' (aby build_pdf pouĹľil balĂ­kovna Ĺˇablonu)
$prepared['deliveryType'] = 'box';

// VytvoĹ™Ă­me PDF
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

            error_log('WC BalĂ­kovna Label: Label saved to order meta (generated PDF)');

            return array(
                'success' => true,
                'label_url' => $pdf_result['url'],
                'message' => __('Ĺ tĂ­tek byl ĂşspÄ›ĹˇnÄ› vygenerovĂˇn', 'wc-balikovna-komplet')
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Chyba pĹ™i generovĂˇnĂ­ PDF ĹˇtĂ­tku', 'wc-balikovna-komplet')
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
        
        error_log('WC BalĂ­kovna Label: Address: ' . $address . ', ' . $city . ', ' . $postcode);

        if (empty($address) || empty($city) || empty($postcode)) {
            error_log('WC BalĂ­kovna Label ERROR: Incomplete delivery address for order #' . $order->get_id());
            $missing = array();
            if (empty($address)) $missing[] = 'Ulice';
            if (empty($city)) $missing[] = 'MÄ›sto';
            if (empty($postcode)) $missing[] = 'PSÄŚ';
            
            return array(
                'success' => false,
                'message' => sprintf(
                    __('DodacĂ­ adresa nenĂ­ kompletnĂ­. ChybĂ­: %s', 'wc-balikovna-komplet'),
                    implode(', ', $missing)
                )
            );
        }
        
        // Validate required order data
        $validation = $this->validate_order_data($order);
        if (!$validation['valid']) {
            error_log('WC BalĂ­kovna Label ERROR: Missing order fields: ' . implode(', ', $validation['missing_fields']));
            return array(
                'success' => false,
                'message' => sprintf(
                    __('ChybĂ­ Ăşdaje objednĂˇvky: %s', 'wc-balikovna-komplet'),
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
        
        error_log('WC BalĂ­kovna Label: Prepared data: ' . json_encode($data));

        // --- NahraÄŹ tento blok (mĂ­sto volĂˇnĂ­ API a zpracovĂˇnĂ­ vĂ˝sledku) ---
    $prepared = $this->prepare_label_data( $order );
if ( is_wp_error( $prepared ) ) {
    return array(
        'success' => false,
        'message' => $prepared->get_error_message()
    );
}

// FORCE deliveryType = 'address' (aby build_pdf pouĹľil adresnĂ­ Ĺˇablonu)
$prepared['deliveryType'] = 'address';

$pdf_result = $this->build_pdf_from_template( $prepared );

        if ( isset( $pdf_result['success'] ) && $pdf_result['success'] && ! empty( $pdf_result['url'] ) ) {
            // Save label info to order
            $order->update_meta_data('_wc_balikovna_label_generated', 'yes');
            $order->update_meta_data('_wc_balikovna_label_url', $pdf_result['url']);
            $order->update_meta_data('_wc_balikovna_label_date', current_time('mysql'));
            $order->save();

            error_log('WC BalĂ­kovna Label: Label saved to order meta (generated PDF)');

            return array(
                'success' => true,
                'label_url' => $pdf_result['url'],
                'message' => __('Ĺ tĂ­tek byl ĂşspÄ›ĹˇnÄ› vygenerovĂˇn', 'wc-balikovna-komplet')
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Chyba pĹ™i generovĂˇnĂ­ PDF ĹˇtĂ­tku', 'wc-balikovna-komplet')
            );
        } 
    }

    // --- PĹ™idat: pomocnĂ© metody pro pĹ™Ă­pravu dat a generovĂˇnĂ­ PDF (vloĹľit PĹED metodou call_api) ---

    /**
     * PĹ™ipravĂ­ data pro ĹˇtĂ­tek z objednĂˇvky.
     *
     * @param int|WC_Order $order_or_id
     * @return array|WP_Error
     */
    private function prepare_label_data( $order_or_id ) {
        $order = is_object( $order_or_id ) ? $order_or_id : wc_get_order( intval( $order_or_id ) );

        if ( ! $order ) {
            return new WP_Error( 'order_not_found', 'ObjednĂˇvka nenalezena' );
        }

        // OdesĂ­latel (moĹľno upravit z nastavenĂ­ pluginu)
        $sender = array(
            'name'         => get_option( 'wc_balikovna_sender_name', 'SU~PR suĹˇenĂ© | praĹľenĂ©' ),
            'order_number' => $order->get_order_number() ?: $order->get_id(),
        );

        // PĹ™Ă­jemce (ze shipping, fallback na billing)
        $recipient = array(
            'name'     => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
            'street'   => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
            'address_2'=> $order->get_shipping_address_2() ?: $order->get_meta('shipping_address_2') ?: '',
            'city'     => $order->get_shipping_city() ?: $order->get_billing_city(),
            'zipCode'  => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
        );
// AFTER you set $recipient (name/street/city/zip), pĹ™idej:
$branch_id = $order->get_meta('_wc_balikovna_branch_id') ?: '';
$branch_name = $order->get_meta('_wc_balikovna_branch_name') ?: '';
$branch_type = $order->get_meta('_wc_balikovna_branch_type') ?: '';
$branch_icon = $order->get_meta('_wc_balikovna_branch_icon') ?: '';

// pokud icon chybĂ­, pokusĂ­me se ji zjistit z typu
if ( empty( $branch_icon ) && ! empty( $branch_type ) ) {
    $branch_icon = $this->get_piktogram_for_branch_type( $branch_type );
}


// uloĹľ do $recipient tak, aby build_pdf_from_template mohl pouĹľĂ­t
if ( $branch_id )   $recipient['branch_id']   = $branch_id;
if ( $branch_name ) $recipient['branch_name'] = $branch_name;
if ( $branch_type ) $recipient['branch_type'] = $branch_type;
if ( $branch_icon ) $recipient['branch_icon'] = $branch_icon;

        if ( empty( $recipient['name'] ) || empty( $recipient['street'] ) || empty( $recipient['city'] ) || empty( $recipient['zipCode'] ) ) {
            return new WP_Error( 'missing_recipient', 'ChybĂ­ nÄ›kterĂ© povinnĂ© Ăşdaje pĹ™Ă­jemce' );
        }

        // SpoÄŤĂ­tat hmotnost (pĹ™evod podle nastavenĂ­ WooCommerce na kg)
        $weight_kg = $this->calculate_order_weight( $order );

// --- START: robustnĂ­ urÄŤenĂ­ deliveryType (nahraÄŹ pĹŻvodnĂ­ blok) ---
$delivery_type = $order->get_meta('_wc_balikovna_delivery_type');

if ( empty( $delivery_type ) ) {
    // debug
    error_log( 'prepare_label_data - delivery_type meta empty for order #' . $order->get_id() );

    $shipping_items = $order->get_items( 'shipping' );
    foreach ( $shipping_items as $ship_item ) {
        $method_id    = method_exists( $ship_item, 'get_method_id' ) ? $ship_item->get_method_id() : ( $ship_item['method_id'] ?? '' );
        $instance_id  = method_exists( $ship_item, 'get_instance_id' ) ? $ship_item->get_instance_id() : ( $ship_item['instance_id'] ?? '' );
        $method_title = method_exists( $ship_item, 'get_method_title' ) ? $ship_item->get_method_title() : ( $ship_item['method_title'] ?? '' );

        error_log( 'prepare_label_data - shipping item: method_id=' . var_export( $method_id, true ) . ' instance_id=' . var_export( $instance_id, true ) . ' method_title=' . var_export( $method_title, true ) );

        if ( $method_id && strpos( $method_id, 'balikovna' ) !== false ) {
            // extrahovat instance_id z method_id pokud je ve formĂˇtu 'balikovna:3'
            if ( strpos( $method_id, ':' ) !== false ) {
                $parts = explode( ':', $method_id );
                if ( isset( $parts[1] ) && is_numeric( $parts[1] ) ) {
                    $instance_id = intval( $parts[1] );
                    error_log( 'prepare_label_data - extracted instance_id from method_id: ' . $instance_id );
                }
            }

            // pokud mĂˇme instance id, zkusĂ­me naÄŤĂ­st option z shipping method instance
            if ( ! empty( $instance_id ) && class_exists( 'WC_Balikovna_Shipping_Method' ) ) {
                try {
                    $mi = new \WC_Balikovna_Shipping_Method( $instance_id );
                    // upravte nĂˇzev option pokud mĂˇ vaĹˇe metoda odliĹˇnĂ˝ nĂˇzev
                    $opt = $mi->get_option( 'delivery_type', '' );
                    error_log( 'prepare_label_data - method instance option delivery_type=' . var_export( $opt, true ) . ' for instance ' . $instance_id );
                    if ( ! empty( $opt ) ) {
                        $delivery_type = $opt;
                        break;
                    }
                } catch ( Exception $e ) {
                    error_log( 'prepare_label_data - instance lookup failed: ' . $e->getMessage() );
                }
            }

            // heuristika podle nĂˇzvu metody (method_title)
            if ( $method_title ) {
                if ( stripos( $method_title, 'box' ) !== false || stripos( $method_title, 'pobo' ) !== false ) {
                    $delivery_type = 'box';
                    error_log( 'prepare_label_data - delivery_type heuristically set to box based on method_title: ' . $method_title );
                    break;
                }
                if ( stripos( $method_title, 'adresa' ) !== false || stripos( $method_title, 'na adresu' ) !== false ) {
                    $delivery_type = 'address';
                    error_log( 'prepare_label_data - delivery_type heuristically set to address based on method_title: ' . $method_title );
                    break;
                }
            }

            // fallback na order meta (pokud pĹ™eci jen nÄ›kde existuje)
            $maybe_meta = $order->get_meta('_wc_balikovna_delivery_type');
            if ( ! empty( $maybe_meta ) ) {
                $delivery_type = $maybe_meta;
                error_log( 'prepare_label_data - delivery_type taken from order meta fallback: ' . $delivery_type );
                break;
            }
        }
    }
}

// poslednĂ­ fallback: 'box'
if ( empty( $delivery_type ) ) {
    $delivery_type = 'box';
    error_log( 'prepare_label_data - delivery_type fallback to box for order #' . $order->get_id() );
}
// --- END: robustnĂ­ urÄŤenĂ­ deliveryType ---

return array(
    'sender'       => $sender,
    'recipient'    => $recipient,
    'weight'       => $weight_kg,
    'deliveryType' => $delivery_type,
);
    }

    /**
     * VytvoĹ™Ă­ PDF ĹˇtĂ­tek z template (FPDI + TCPDF nebo FPDI+FPDF fallback).
     *
     * @param array $data VĂ˝stup z prepare_label_data()
     * @return array|WP_Error ['success'=>true,'url'=>'...'] nebo WP_Error
     */
private function build_pdf_from_template( $data ) {
    // Load autoloads / bundled libs
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
        return new WP_Error( 'missing_lib', 'FPDI/TCPDF knihovny nejsou dostupnĂ©. Nainstalujte zĂˇvislosti (composer require setasign/fpdi-tcpdf) nebo pĹ™idejte knihovny do pluginu.' );
    }

    try {
// --- DEBUG LOGGER: uklĂˇdĂˇ do sloĹľky pluginu /logs/debug.log ---
$plugin_dir = defined('WC_BALIKOVNA_PLUGIN_DIR') ? WC_BALIKOVNA_PLUGIN_DIR : plugin_dir_path( __FILE__ );
$log_dir = trailingslashit( $plugin_dir ) . 'logs';
if ( ! file_exists( $log_dir ) ) {
    @wp_mkdir_p( $log_dir );
}
$log_file = trailingslashit( $log_dir ) . 'debug.log';
$log = function( $message ) use ( $log_file ) {
    $time = date( 'Y-m-d H:i:s' );
    @file_put_contents( $log_file, "[$time] " . $message . PHP_EOL, FILE_APPEND | LOCK_EX );
};

// Log raw incoming deliveryType
$rawDelivery = isset( $data['deliveryType'] ) ? $data['deliveryType'] : 'NULL';
$log( 'build_pdf_from_template - raw deliveryType: ' . var_export( $rawDelivery, true ) );

// Normalizace hodnoty (trim + lowercase)
$deliveryTypeNormalized = is_string( $rawDelivery ) ? strtolower( trim( $rawDelivery ) ) : '';
if ( $deliveryTypeNormalized === '' ) {
    $deliveryTypeNormalized = 'box';
}
$log( 'build_pdf_from_template - normalized deliveryType: ' . $deliveryTypeNormalized );

// Vybereme Ĺˇablonu podle delivery type (default = box)
$template_name = 'BAL_stitek_HD_balikovna.pdf'; // default pro box
if ( $deliveryTypeNormalized === 'address' ) {
    $template_name = 'HAS_Balikovna_plus_cistopis_podavatele.pdf';
    $log( 'build_pdf_from_template - selected address template: ' . $template_name );
} else {
    $log( 'build_pdf_from_template - selected box/default template: ' . $template_name );
}

// sestavĂ­me cestu k souboru a zkontrolujeme existenci (fallback na default)
$template_path = trailingslashit( $plugin_dir ) . 'assets/' . $template_name;
if ( ! file_exists( $template_path ) ) {
    $log( 'build_pdf_from_template - template not found: ' . $template_path . ' â€” trying fallback.' );
    $fallback = trailingslashit( $plugin_dir ) . 'assets/BAL_stitek_HD_balikovna.pdf';
    if ( file_exists( $fallback ) ) {
        $template_path = $fallback;
        $log( 'build_pdf_from_template - using fallback template: ' . $template_path );
    } else {
        $log( 'build_pdf_from_template - ERROR: no template found (tried ' . $template_name . ' and fallback).' );
        return new WP_Error( 'template_missing', 'Ĺ ablona PDF nebyla nalezena: ' . $template_name );
    }
} else {
    $log( 'build_pdf_from_template - template file exists: ' . $template_path );
}
        // Instantiate appropriate FPDI class
        if ( class_exists( '\setasign\Fpdi\Tcpdf\Fpdi' ) ) {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        } elseif ( class_exists( '\setasign\Fpdi\Fpdi' ) ) {
            $pdf = new \setasign\Fpdi\Fpdi();
        } else {
            return new WP_Error( 'no_pdf_engine', 'NevhodnĂˇ PDF knihovna' );
        }

        // Metadata
        if ( method_exists( $pdf, 'SetCreator' ) ) {
            $pdf->SetCreator( 'WooCommerce BalĂ­kovna' );
            $pdf->SetAuthor( get_bloginfo( 'name' ) );
            $pdf->SetTitle( 'BalĂ­kovna - Ĺ tĂ­tek' );
        }
        if ( method_exists( $pdf, 'SetPrintHeader' ) ) {
            $pdf->SetPrintHeader( false );
            $pdf->SetPrintFooter( false );
        }

        // Load template
        $pageCount = $pdf->setSourceFile( $template_path );
        $tplId = $pdf->importPage( 1 );

        $pdf->AddPage();
        $pdf->useTemplate( $tplId, 0, 0 );

        // Font (TCPDF supports UTF-8)
        if ( method_exists( $pdf, 'SetFont' ) ) {
            $pdf->SetFont( 'dejavusans', '', 10 );
        }

        $maxWidth = 85; // uprav dle Ĺˇablony

        // --- OdesĂ­latel ---
        $senderX = 12;
        $senderY = 17;

        $order_number_display = isset( $data['sender']['order_number'] ) && ! empty( $data['sender']['order_number'] ) ? $data['sender']['order_number'] : 'unknown';
        $sender_lines = array(
            'Obj. ÄŤ. ' . $order_number_display . ' z SU~PR suĹˇenĂ© | praĹľenĂ©',
            'PetraĹˇovice 61',
            'BĂ­lĂˇ - PetraĹˇovice',
            '463 42',
        );

        $font_family = 'dejavusans';
        $font_style = '';
        $fontSize = 10;
        $minFontSize = 3;
        $lineGap = 1;

        $fits = false;
        while ( $fontSize >= $minFontSize ) {
            $tooWide = false;
            foreach ( $sender_lines as $line ) {
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
            $fontSize -= 1;
        }

        if ( ! $fits ) {
            $fontSize = $minFontSize;
            $first = $sender_lines[0];
            while ( $pdf->GetStringWidth( $first, $font_family, $font_style, $fontSize ) > $maxWidth && mb_strlen($first) > 3 ) {
                $first = mb_substr( $first, 0, mb_strlen($first) - 1 );
            }
            if ( mb_strlen($first) < mb_strlen($sender_lines[0]) ) {
                $first = rtrim( $first ) . 'â€¦';
                $sender_lines[0] = $first;
            }
        }

        $pdf->SetFont( $font_family, $font_style, $fontSize );
        $pdf->SetTextColor( 0, 0, 0 );

        $lineHeight = max( ( $fontSize * 0.35 ), 4 );

        $curY = $senderY;
        foreach ( $sender_lines as $line ) {
            $pdf->SetXY( $senderX, $curY );
            $pdf->Cell( $maxWidth, $lineHeight, $line, 0, 1, 'L', 0, '', 0 );
            $curY += $lineHeight + $lineGap;
        }
        $pdf->SetY( $curY + 1 );

        // --- AdresĂˇt ---
        $recipientX = 12;
        $recipientY = 45;

        $recipient_name = !empty($data['recipient']['name']) ? wp_strip_all_tags($data['recipient']['name']) : '';
        $address1 = !empty($data['recipient']['street']) ? wp_strip_all_tags($data['recipient']['street']) : '';
        $address2 = !empty($data['recipient']['address_2']) ? wp_strip_all_tags($data['recipient']['address_2']) : '';
        $city = !empty($data['recipient']['city']) ? wp_strip_all_tags($data['recipient']['city']) : '';
        $zip  = !empty($data['recipient']['zipCode']) ? wp_strip_all_tags($data['recipient']['zipCode']) : '';

        $branch_id = !empty($data['recipient']['branch_id']) ? wp_strip_all_tags($data['recipient']['branch_id']) : '';
        $branch_name = !empty($data['recipient']['branch_name']) ? wp_strip_all_tags($data['recipient']['branch_name']) : '';

        if ( empty($address2) || empty($address1) || empty($city) || empty($zip) || empty($recipient_name) || ( empty($branch_id) && empty($branch_name) ) ) {
            $order_candidate = null;
            if ( ! empty( $data['sender']['order_number'] ) && is_numeric( $data['sender']['order_number'] ) ) {
                $order_candidate = wc_get_order( intval( $data['sender']['order_number'] ) );
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

        $line1 = $recipient_name;
        $line2 = '';
        if ( ! empty( $address2 ) ) {
            $line2 = $address2;
        } else {
            if ( ! empty( $branch_name ) ) {
                $line2 = $branch_name;
            } elseif ( ! empty( $branch_id ) ) {
                $line2 = 'BalĂ­kovna ID: ' . $branch_id;
            } else {
                $line2 = '';
            }
        }
        $line3 = $address1;
        $line4 = trim( $zip . ' ' . $city );

        $recipient_lines = array();
        if ( $line1 !== '' ) $recipient_lines[] = $line1;
        if ( $line2 !== '' ) $recipient_lines[] = $line2;
        if ( $line3 !== '' ) $recipient_lines[] = $line3;
        if ( $line4 !== '' ) $recipient_lines[] = $line4;

        $font_family = 'dejavusans';
        $fontSizeBold = 12;
        $fontSizeNormal = 11;
        $minFontSize = 7;

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

$curY = $recipientY;
foreach ( $recipient_lines as $idx => $line ) {
    if ( $idx === 0 ) {
        $lineHeight = $drawLine( $line, $recipientX, $curY, $maxWidth, $font_family, 'B', $fontSizeBold );
    } elseif ( $idx === 3 ) {
        $lineHeight = $drawLine( $line, $recipientX, $curY, $maxWidth, $font_family, 'B', $fontSizeBold );
    } else {
        $lineHeight = $drawLine( $line, $recipientX, $curY, $maxWidth, $font_family, '', $fontSizeNormal );
    }
    $curY += $lineHeight + 1;
}

// NastavĂ­me kurzor pod adresĂˇtem
$pdf->SetY( $curY + 1 );

// --- Ikony (zarovnĂˇnĂ­ podle zaÄŤĂˇtku adresy a doladÄ›nĂ­ hmotnosti) ---
// PouĹľĂ­vĂˇme $recipientX a $recipientY (adresĂˇt) jako referenci pro vertikĂˇlnĂ­ zarovnĂˇnĂ­.
$icons_reference_y = isset($recipientY) ? $recipientY : $pdf->GetY(); // pokud nenĂ­ recipientY, fallback
// Parametry ladÄ›nĂ­ (mm) - uprav podle vĂ˝sledku
$leftX = 12; // X pozice piktogramu vzhledem k levĂ©mu okraji (mĹŻĹľeĹˇ upravit)
$leftY = 120;   // doleva/nahoru doladÄ›nĂ­ pro levĂ˝ piktogram (doplĹ; kladnĂ© posouvĂˇ dolĹŻ)
$weight_vertical_adjust_mm = 5.0; // kolik mm posunout hmotnost dolĹŻ oproti adresnĂ­ lince (zvĂ˝Ĺˇit = vĂ­ce dolĹŻ)
$weight_text_vertical_extra = 2.0; // dalĹˇĂ­ jemnĂ© doladÄ›nĂ­ pro text uvnitĹ™ hmotnostnĂ­ho piktogramu

// LevĂ˝ piktogram - oÄŤekĂˇvanĂ© jmĂ©no; kĂłd zkusĂ­ tolerantnĂ­ varianty
$left_candidate = '04_balikovna na adresu_10_10.jpg';
$left_img_path = $this->get_asset_path( $left_candidate );
if ( empty( $left_img_path ) ) {
    $alt = str_replace( ' ', '_', $left_candidate );
    $left_img_path = $this->get_asset_path( $alt );
}
// dalĹˇĂ­ tolerantnĂ­ hledĂˇnĂ­
if ( empty( $left_img_path ) ) {
    $assets = glob( trailingslashit( WC_BALIKOVNA_PLUGIN_DIR ) . 'assets/*.{jpg,png}', GLOB_BRACE );
    $wanted_norm = strtolower( preg_replace( '/[^a-z0-9]+/i', '', pathinfo( $left_candidate, PATHINFO_FILENAME ) ) );
    foreach ( (array) $assets as $f ) {
        $bn = pathinfo( $f, PATHINFO_FILENAME );
        if ( strtolower( preg_replace( '/[^a-z0-9]+/i', '', $bn ) ) === $wanted_norm ) {
            $left_img_path = $f;
            break;
        }
    }
}

// PravĂ˝ (hmotnost) piktogram - pĹŻvodnĂ­ nĂˇzev
$right_img_path = $this->get_asset_path( '18_hmotnost_hodnota_20_10.jpg' );
if ( empty( $right_img_path ) ) {
    $legacy = trailingslashit( WC_BALIKOVNA_PLUGIN_DIR ) . 'assets/18_hmotnost_hodnota_20_10.jpg';
    if ( file_exists( $legacy ) ) $right_img_path = $legacy;
}

// RozmÄ›ry (mm) - doladĂ­Ĺˇ podle vĂ˝sledku
$leftW  = 10; $leftH  = 10;
$rightW = 20; $rightH = 10;

// --- Ikony: posun obou ikon nĂ­Ĺľ + posun textu hmotnosti doprava ---
// reference jako dĹ™Ă­v
$iconsY_mm = $pdf->GetY() + 5;

// rozmÄ›ry (nezmÄ›nÄ›no)
$leftW  = 10; $leftH  = 10;
$rightW = 20; $rightH = 10;

// vĂ˝chozĂ­ X souĹ™adnice (ponech stejnĂ© jako u box verze)
$leftX  = 12;
$rightX = 70;

// VĹ EOBECNĂť VERTIKĂLNĂŤ POSUN (zmÄ›Ĺ jen toto ÄŤĂ­slo pro obÄ› ikony)
$vertical_shift_mm = 5.5; // zvĂ˝Ĺˇit = ikony vĂ­ce dolĹŻ, snĂ­Ĺľit = nahoru

// pevnĂ© Y pozice (posunutĂ© dolĹŻ)
$leftY  = $iconsY_mm + $vertical_shift_mm;
$rightY = $iconsY_mm + $vertical_shift_mm;

// POSUN TEXTU HMOTNOSTI (vodorovnÄ› / svisle)
$weight_text_horizontal_shift = 4.0; // pozitivnĂ­ = posun doprava (nastavit podle potĹ™eby)
$weight_text_vertical_extra   = 2.0; // jiĹľ pouĹľĂ­vanĂ© drobnĂ© doladÄ›nĂ­ (ponechat/ladit)



// VykreslĂ­me levou ikonu


if ( ! empty( $left_img_path ) && file_exists( $left_img_path ) ) {
    try {
        $pdf->Image( $left_img_path, $leftX, $leftY, $leftW, $leftH, '', '', '', false, 300 );
    } catch ( Exception $e ) {
        error_log( 'WC BalĂ­kovna DEBUG: left icon render failed: ' . $e->getMessage() );
    }
} else {
    error_log( 'WC BalĂ­kovna DEBUG: left icon not found: ' . $left_candidate );
}

// VykreslĂ­me pravou ikonu (grafika pro hmotnost)

$drawWeightInside = false;
if ( ! empty( $right_img_path ) && file_exists( $right_img_path ) ) {
    try {
        $pdf->Image( $right_img_path, $rightX, $rightY, $rightW, $rightH, '', '', '', false, 300 );
        $drawWeightInside = true;
    } catch ( Exception $e ) {
        error_log( 'WC BalĂ­kovna DEBUG: right icon render failed: ' . $e->getMessage() );
        $drawWeightInside = false;
    }
} else {
    error_log( 'WC BalĂ­kovna DEBUG: weight icon not found: ' . $right_img_path );
    $drawWeightInside = false;
}

// VykreslenĂ­ textu hmotnosti uvnitĹ™ pravĂ©ho piktogramu (robustnĂ­ centrovĂˇnĂ­ + posun + clamping)
if ( $drawWeightInside && isset( $data['weight'] ) && $data['weight'] !== '' ) {
    $font_family = ( method_exists( $pdf, 'SetFont' ) ? 'dejavusans' : 'Helvetica' );
    $font_size   = 9;
    $text = (string) $data['weight'];

    $textWidth = $pdf->GetStringWidth( $text, $font_family, '', $font_size );

    // zĂˇkladnĂ­ centrovĂˇnĂ­ uvnitĹ™ piktogramu
    $baseX = $rightX + max( 0, ( $rightW - $textWidth ) / 2 );

    // horizontĂˇlnĂ­ posun (volitelnĂ˝)
    $shift = isset( $weight_text_horizontal_shift ) ? floatval( $weight_text_horizontal_shift ) : 0.0;

    // vĂ˝slednĂ© X s posunem
    $textX = $baseX + $shift;

    // clamping: zajistit, aby text nezaÄŤĂ­nal vlevo od piktogramu ani nepĹ™etekl vpravo
    $minTextX = $rightX;
    $maxTextX = $rightX + $rightW - $textWidth;

    if ( $textWidth > $rightW ) {
        // text je ĹˇirĹˇĂ­ neĹľ piktogram -> zarovnat vlevo uvnitĹ™ piktogramu
        $textX = $rightX;
    } else {
        if ( $textX < $minTextX ) {
            $textX = $minTextX;
        }
        if ( $textX > $maxTextX ) {
            $textX = $maxTextX;
        }
    }

    $textY = $rightY + ( $rightH / 2 ) - ( $font_size * 0.35 ) + ( isset( $weight_text_vertical_extra ) ? floatval( $weight_text_vertical_extra ) : 0.0 );

    $pdf->SetFont( $font_family, '', $font_size );
    $pdf->SetTextColor( 0, 0, 0 );
    $pdf->SetXY( $textX, $textY );
    $pdf->Cell( $textWidth, 0, $text, 0, 1, 'L', 0, '', 0 );
}

// Posun kurzoru pod ikony, aby dalĹˇĂ­ obsah nezaÄŤĂ­nal pĹ™es nÄ›
$afterIconsY = max( $leftY + $leftH, $rightY + $rightH );
$pdf->SetY( $afterIconsY + 2 );
// --- konec ikon ---

// UloĹľit do uploads
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
    return new WP_Error( 'pdf_error', 'Chyba pĹ™i generovĂˇnĂ­ PDF: ' . $e->getMessage() );
}
}


/**
 * Resolve an asset filename to a real existing file path inside plugin (or common fallbacks).
 *
 * @param string $filename
 * @return string empty if not found, otherwise full path to file
 */
private function get_asset_path( $filename ) {
    if ( empty( $filename ) ) {
        return '';
    }
    // Normalize filename (in case someone passed full path already)
    $filename = ltrim( str_replace( '\\', '/', $filename ), '/' );

    $candidates = array(
        // primary plugin assets folder (preferred)
        trailingslashit( WC_BALIKOVNA_PLUGIN_DIR ) . 'assets/' . $filename,
        // fallback: plugin folder guessed by slug (in case constant differs)
        WP_PLUGIN_DIR . '/woocommerce-balikovna-komplet/assets/' . $filename,
        // relative fallback (same directory structure)
        dirname( __FILE__ ) . '/../assets/' . $filename,
    );

    foreach ( $candidates as $p ) {
        if ( file_exists( $p ) ) {
            return $p;
        }
    }
    return '';
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
        
        error_log('WC BalĂ­kovna API: Calling endpoint: ' . $endpoint);
        error_log('WC BalĂ­kovna API: Request data: ' . json_encode($data));
        
        // For now, this is a mock implementation
        // In production, this would make an actual HTTP request to the Czech Post API
        // The actual API endpoint and structure would need to be determined based on Czech Post documentation
        
        // Mock response for testing
        $mock_label_url = WC_BALIKOVNA_PLUGIN_URL . 'assets/BAL_stitek_HD_balikovna.pdf';
        
        error_log('WC BalĂ­kovna API: Mock response - returning success with URL: ' . $mock_label_url);
        
        return array(
            'success' => true,
            'label_url' => $mock_label_url,
            'message' => __('Ĺ tĂ­tek byl ĂşspÄ›ĹˇnÄ› vygenerovĂˇn (MOCK - implementace skuteÄŤnĂ©ho API volĂˇnĂ­ ÄŤekĂˇ na dokumentaci API ÄŚeskĂ© poĹˇty)', 'wc-balikovna-komplet')
        );
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
            $missing_fields[] = 'JmĂ©no zĂˇkaznĂ­ka';
        }
        if (empty($order->get_billing_email())) {
            $missing_fields[] = 'Email zĂˇkaznĂ­ka';
        }
        if (empty($order->get_billing_phone())) {
            $missing_fields[] = 'Telefon zĂˇkaznĂ­ka';
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
     * Calculate order weight and return it in kilograms (float)
     *
     * @param WC_Order $order
     * @return float Weight in kilograms
     */
    private function calculate_order_weight($order)
    {
        // Get store weight unit (kg, g, lbs, oz)
        $unit = get_option('woocommerce_weight_unit', 'kg');

        $total = 0.0;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $qty     = $item->get_quantity();
            if ($product) {
                $prod_weight = $product->get_weight();
                $prod_weight = $prod_weight ? floatval($prod_weight) : 0.0;
                $total += $prod_weight * $qty;
            }
        }

        // Default weight if none set
        if ($total <= 0) {
            $total = 1.0;
        }

        // Convert total to kilograms depending on store unit
        switch ( strtolower( $unit ) ) {
            case 'g':
            case 'gram':
            case 'grams':
                $weight_kg = $total / 1000.0;
                break;
            case 'kg':
            case 'kilogram':
            case 'kilograms':
                $weight_kg = $total;
                break;
            case 'lbs':
            case 'lb':
            case 'pound':
            case 'pounds':
                $weight_kg = $total * 0.45359237;
                break;
            case 'oz':
            case 'ounce':
            case 'ounces':
                $weight_kg = $total * 0.0283495231;
                break;
            default:
                // unknown unit â€” assume already kg
                $weight_kg = $total;
                break;
        }

        // ensure reasonable precision
        return round( (float) $weight_kg, 6 );
    }
}
