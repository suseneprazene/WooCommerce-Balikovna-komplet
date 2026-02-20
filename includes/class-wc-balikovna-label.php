<?php
/**
 * Label generation for Balikovna
 *
 * @package WC_Balikovna_Komplet
 */
// dočasně vlož do WC_Balikovna_Komplet::init() na začátek

error_log('BALIKOVNA: končím funkci generate_label()');

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-cpost-api-client.php';

/**
 * WC_Balikovna_Label Class
 */
 
 if ( class_exists( 'WC_Balikovna_Label' ) ) {
    // Třída už je definovaná (např. kvůli záloze) — nic více nedělej.
    return;
}
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
    private $api_url = 'https://b2b-test.postaonline.cz:444/restservices/ZSKService/v1/';


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
    // zjistíme, jestli má objednávka nějakou Balíkovna dopravu (meta nebo shipping položka)
    $delivery_type = $order->get_meta('_wc_balikovna_delivery_type');
    $branch_name   = $order->get_meta('_wc_balikovna_branch_name');
    $branch_type   = $order->get_meta('_wc_balikovna_branch_type');
    $branch_icon   = $order->get_meta('_wc_balikovna_branch_icon');

    // Pokud nemáme uložený delivery_type, zkusíme fallback přes shipping položky a instance nastavení
    if ( empty( $delivery_type ) ) {
        $shipping_items = $order->get_items( 'shipping' );
        foreach ( $shipping_items as $ship_item ) {
            $method_id   = method_exists( $ship_item, 'get_method_id' ) ? $ship_item->get_method_id() : ( $ship_item['method_id'] ?? '' );
            $instance_id = method_exists( $ship_item, 'get_instance_id' ) ? $ship_item->get_instance_id() : ( $ship_item['instance_id'] ?? '' );
            if ( $method_id === 'balikovna' ) {
                // pokud máme instance_id, zkusíme načíst option z WC_Balikovna_Shipping_Method
                if ( ! empty( $instance_id ) && class_exists( 'WC_Balikovna_Shipping_Method' ) ) {
                    try {
                        $mi = new \WC_Balikovna_Shipping_Method( $instance_id );
                        $delivery_type = $mi->get_option( 'delivery_type', 'box' );
                    } catch ( Exception $e ) {
                        // ignoruj - ponecháme delivery_type prázdné a pokračujeme
                    }
                }
                // pokud branch meta chybí, můžeme si vzít alespoň název z order shipping fields (method_title)
                if ( empty( $branch_name ) ) {
                    $branch_name = method_exists( $ship_item, 'get_method_title' ) ? $ship_item->get_method_title() : '';
                }
                break;
            }
        }
    }

    // Pokud objednávka vůbec nemá Balíkovna shipping (ani meta, ani shipping item), nic nepřidáme
    $has_balikovna = ( $order->get_meta('_wc_balikovna_branch_id') || $order->get_meta('_wc_balikovna_branch_name') );
    if ( ! $has_balikovna ) {
        // zkusíme kontrolu přes shipping items: pokud existuje položka s method_id=balikovna, považujeme to za Balíkovnu
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

    // Normalizovat hodnotu pro zobrazení
    if ( empty( $delivery_type ) ) {
        $delivery_type_label = __( 'unknown', 'wc-balikovna-komplet' );
    } else {
        $delivery_type_label = $delivery_type === 'address' ? __( 'Na adresu', 'wc-balikovna-komplet' ) : __( 'Do boxu / pobočky', 'wc-balikovna-komplet' );
    }


// --- START: korektní načtení delivery_type + branch pro admin UI (add_label_button) ---
$delivery_type = $order->get_meta('_wc_balikovna_delivery_type');
$branch_name   = $order->get_meta('_wc_balikovna_branch_name');
$branch_type   = $order->get_meta('_wc_balikovna_branch_type');
$branch_icon   = $order->get_meta('_wc_balikovna_branch_icon');

// Pokud meta chybí, zkusíme detekovat pomocí prepare_label_data() — pouze pro zobrazení v adminu.
// Nepřepisujeme objednávku a nevracíme pole, jen doplníme hodnoty pro UI.
if ( empty( $delivery_type ) ) {
    $detected = $this->prepare_label_data( $order );
    if ( ! is_wp_error( $detected ) && !empty( $detected['deliveryType'] ) ) {
        $delivery_type = $detected['deliveryType'];
    }
    if ( ! is_wp_error( $detected ) && !empty( $detected['recipient'] ) && is_array( $detected['recipient'] ) ) {
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
} else if ( empty( $branch_type ) ) {
    // Oprav branch_type pokud meta existuje, ale branch_type je prázdné
    $detected = $this->prepare_label_data( $order );
    if ( ! is_wp_error( $detected ) && !empty( $detected['recipient']['branch_type'] ) ) {
        $branch_type = $detected['recipient']['branch_type'];
    }
}

// Výstup pro admin UI — vždy správný typ doručení
if ( empty( $delivery_type ) ) {
    $delivery_type_label = __( 'unknown', 'wc-balikovna-komplet' );
} elseif ( $delivery_type === 'address' ) {
    $delivery_type_label = __( 'Na adresu', 'wc-balikovna-komplet' );
} else {
    $delivery_type_label = __( 'Do boxu / pobočky', 'wc-balikovna-komplet' );
}

// --- END: korektní načtení ---
// --- END: korektní načtení delivery_type + branch pro admin UI ---
    // Pokud objednávka vůbec nemá Balíkovna shipping (ani meta, ani shipping item), nic nepřidáme
    $has_balikovna = ( $order->get_meta('_wc_balikovna_branch_id') || $order->get_meta('_wc_balikovna_branch_name') );
    if ( ! $has_balikovna ) {
        // zkusíme kontrolu přes shipping items: pokud existuje položka s method_id=balikovna, považujeme to za Balíkovnu
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

    // Normalizovat hodnotu pro zobrazení
    if ( empty( $delivery_type ) ) {
        $delivery_type_label = __( 'unknown', 'wc-balikovna-komplet' );
    } else {
        $delivery_type_label = $delivery_type === 'address' ? __( 'Na adresu', 'wc-balikovna-komplet' ) : __( 'Do boxu / pobočky', 'wc-balikovna-komplet' );
    }

    // připravit ikonku (pokud máme filename)
    $branch_icon_html = '';
    if ( ! empty( $branch_icon ) ) {
        $icon_path = $this->get_asset_path( $branch_icon );
        if ( $icon_path && file_exists( $icon_path ) ) {
            $icon_url = str_replace( WP_CONTENT_DIR, content_url(), $icon_path ); // relativní URL fallback
            // lepší variantu můžeš udělat přes upload_url nebo WP funkce; tady jen jednoduché zobrazení
            $branch_icon_html = '<img src="' . esc_url( $icon_url ) . '" style="height:24px;margin-right:6px;vertical-align:middle;" alt="" />';
        }
    }



    // render sekce v adminu (zachováme původní tlačítka)
    ?>
    <div class="wc-balikovna-label-section" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
        <h3><?php echo esc_html__( 'Balíkovna', 'wc-balikovna-komplet' ); ?></h3>

        <div style="margin-bottom:10px;">
            <strong><?php echo esc_html__( 'Typ doručení:', 'wc-balikovna-komplet' ); ?></strong>
            <span style="margin-left:8px;"><?php echo esc_html( $delivery_type_label ); ?></span>
        </div>

        <?php if ( ! empty( $branch_name ) ) : ?>
            <div style="margin-bottom:8px;">
                <strong><?php echo esc_html__( 'Pobočka:', 'wc-balikovna-komplet' ); ?></strong>
                <span style="margin-left:8px;"><?php echo esc_html( $branch_name ); ?></span>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $branch_type ) ) : ?>
            <div style="margin-bottom:8px;">
                <strong><?php echo esc_html__( 'Druh pobočky:', 'wc-balikovna-komplet' ); ?></strong>
                <span style="margin-left:8px;"><?php echo esc_html( $branch_type ); ?></span>
            </div>
        <?php endif; ?>
<?php if ( ! empty( $branch_type ) ) : ?>
    <div style="margin-bottom:8px;">
        <strong><?php echo esc_html__( 'Druh pobočky:', 'wc-balikovna-komplet' ); ?></strong>
        <span style="margin-left:8px;">
            <?php
            if ($branch_type === 'box') {
                echo esc_html__( 'Box', 'wc-balikovna-komplet' );
            } elseif ($branch_type === 'branch' || $branch_type === 'balikovna') {
                echo esc_html__( 'Balíkovna (pobočka)', 'wc-balikovna-komplet' );
            } else {
                echo esc_html( $branch_type );
            }
            ?>
        </span>
    </div>
<?php endif; ?>

        <?php if ( $branch_icon_html ) : ?>
            <div style="margin-bottom:12px;">
                <strong><?php echo esc_html__( 'Ikona pobočky:', 'wc-balikovna-komplet' ); ?></strong>
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
                    <strong><?php echo esc_html__( 'Štítek byl vygenerován:', 'wc-balikovna-komplet' ); ?></strong><br>
                    <a href="<?php echo esc_url( $label_url ); ?>" target="_blank" class="button wc-balikovna-download-link">
                        <?php echo esc_html__( 'Stáhnout štítek', 'wc-balikovna-komplet' ); ?>
                    </a>
                </p>
                <p>
                    <button type="button" class="button button-secondary wc-balikovna-regenerate-label" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                        <?php echo esc_html__( 'Regenerovat štítek', 'wc-balikovna-komplet' ); ?>
                    </button>
                </p>
            <?php else : ?>
                <p>
                    <button type="button" class="button button-primary wc-balikovna-generate-label" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                        <?php echo esc_html__( 'Generovat štítek', 'wc-balikovna-komplet' ); ?>
                    </button>
                </p>
            <?php endif; ?>
            <div class="wc-balikovna-label-message" style="display: none; margin-top: 10px;"></div>
        </div>
    </div>
	
	<script>
jQuery(function($){
    // zabezpečený nonce vložený PHP do JS
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
                $messageDiv.addClass('notice notice-success').html('<p>' + (response.data.message || 'Štítek vygenerován') + '</p>').show();

                if (response.data && response.data.label_url) {
                    var $dl = $('.wc-balikovna-download-link');
                    if ($dl.length) {
                        $dl.attr('href', response.data.label_url);
                    } else {
                        var $html = '<p class="wc-balikovna-download"><strong>Štítek byl vygenerován:</strong><br>' +
                                    '<a href="' + response.data.label_url + '" target="_blank" class="button wc-balikovna-download-link">Stáhnout štítek</a></p>';
                        $('.wc-balikovna-label-actions').prepend($html);
                    }
                }

                // refresh page lightly to show updated meta / url (nebo jen reinstrumentovat UI)
                setTimeout(function(){
                    location.reload();
                }, 900);
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : (response && response.message ? response.message : 'Chyba při generování štítku');
                $messageDiv.addClass('notice notice-error').html('<p>' + msg + '</p>').show();
                $button.prop('disabled', false);
            }
        }, 'json').fail(function(jqXHR, textStatus, err) {
            $messageDiv.addClass('notice notice-error').html('<p>Došlo k chybě při komunikaci se serverem: ' + textStatus + '</p>').show();
            $button.prop('disabled', false);
            console.error('WC Balikovna AJAX fail', textStatus, err, jqXHR);
        });
    }

    // Delegované click handlery (funguje i po AJAX reloadu)
    $(document).on('click', '.wc-balikovna-generate-label', function(e){
        e.preventDefault();
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        var $msg = $btn.closest('.wc-balikovna-label-section').find('.wc-balikovna-label-message');
        runLabelAjax(orderId, $btn, $msg);
    });

    $(document).on('click', '.wc-balikovna-regenerate-label', function(e){
        e.preventDefault();
        if (!confirm('Opravdu chcete vygenerovat nový štítek? Starý bude nahrazen.')) return;
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
    /**
     * Debug log to /balikovna-debug.log in plugin root
     */
    private function balikovna_debug_log($message) {
        $plugin_dir = dirname(__FILE__, 2);
        $log_file = $plugin_dir . '/balikovna-debug.log';
        $time = date('Y-m-d H:i:s');
        if (is_array($message) || is_object($message)) {
            $msg = $time . " | " . print_r($message, true) . "\n";
        } else {
            $msg = $time . " | " . $message . "\n";
        }
        @file_put_contents($log_file, $msg, FILE_APPEND | LOCK_EX);
    }

    /**
     * Generate label for order (Opravená verze - žádné duplikace a žádný kód mimo třídu!)
     *
     * @param WC_Order $order
     * @return array
     */
    public function generate_label($order)
    {
        $this->balikovna_debug_log('--- === Spouštím generate_label pro objednávku ID: ' . (method_exists($order, 'get_id') ? $order->get_id() : '[neznámý]') . ' === ---');

        // Robustní detekce delivery_type
        $delivery_type = $order->get_meta('_wc_balikovna_delivery_type');
        $detected_type = '';

        foreach ($order->get_items('shipping') as $ship_item) {
            $method_id    = method_exists($ship_item, 'get_method_id') ? $ship_item->get_method_id() : ($ship_item['method_id'] ?? '');
            $instance_id  = method_exists($ship_item, 'get_instance_id') ? $ship_item->get_instance_id() : ($ship_item['instance_id'] ?? '');
            $method_title = method_exists($ship_item, 'get_method_title') ? $ship_item->get_method_title() : ($ship_item['method_title'] ?? '');
            if (!$method_id) continue;
            if (stripos($method_id, 'balikovna') === false) continue;

            if (empty($instance_id) && strpos($method_id, ':') !== false) {
                $parts = explode(':', $method_id);
                if (isset($parts[1])) {
                    $maybe = preg_replace('/[^0-9]/', '', $parts[1]);
                    if ($maybe !== '') {
                        $instance_id = intval($maybe);
                    }
                }
            }
            if (!empty($instance_id) && class_exists('WC_Balikovna_Shipping_Method')) {
                try {
                    $mi = new \WC_Balikovna_Shipping_Method(intval($instance_id));
                    $opt = $mi->get_option('delivery_type', '');
                    if (!empty($opt)) {
                        $detected_type = $opt;
                        break;
                    }
                } catch (\Exception $e) {}
            }
            if ($method_title) {
                if (stripos($method_title, 'box') !== false || stripos($method_title, 'pobo') !== false) {
                    $detected_type = 'box';
                    break;
                }
                if (stripos($method_title, 'adresa') !== false || stripos($method_title, 'na adresu') !== false) {
                    $detected_type = 'address';
                    break;
                }
            }
        }

        if (empty($delivery_type) && !empty($detected_type)) {
            $delivery_type = $detected_type;
            try {
                $order->update_meta_data('_wc_balikovna_delivery_type', $delivery_type);
                $order->save();
            } catch (\Exception $e) {}
        } elseif (!empty($detected_type) && $detected_type !== $delivery_type) {
            $delivery_type = $detected_type;
            try {
                $order->update_meta_data('_wc_balikovna_delivery_type', $delivery_type);
                $order->save();
            } catch (\Exception $e) {}
        }
        if (empty($delivery_type)) {
            $this->balikovna_debug_log('ERROR: No delivery type resolved');
            return array(
                'success' => false,
                'message' => __('Tato objednávka nemá určen typ dodání (box vs adresa).', 'wc-balikovna-komplet')
            );
        }

        // Validate API auth
        $api_token = get_option('wc_balikovna_api_token', '');
        $api_private_key = get_option('wc_balikovna_api_private_key', '');
        if (empty($api_token) || empty($api_private_key)) {
            return array(
                'success' => false,
                'message' => __('API token nebo privátní klíč nejsou nastaveny. Nastavte je v administraci pluginu.', 'wc-balikovna-komplet')
            );
        }
        // Sender check
        $required = [
            'wc_balikovna_sender_name','wc_balikovna_sender_street','wc_balikovna_sender_city',
            'wc_balikovna_sender_zip','wc_balikovna_sender_phone','wc_balikovna_sender_email'
        ];
        $missing = [];
        foreach ($required as $f) if (empty(get_option($f))) $missing[]=$f;
        if ($missing) {
            return array(
                'success' => false,
                'message' => 'Chybí údaje odesílatele: '.implode(', ',$missing)
            );
        }

        // API build
        $api_url   = 'https://b2b.postaonline.cz:444/restservices/ZSKService/v1/';
        $secret_key = get_option('wc_balikovna_api_private_key', '');
        $prefix = ($delivery_type === 'box' || $delivery_type === 'balikovna') ? 'NB' : 'DR';
        $data = array(
            'parcelServiceHeader' => array(
                'parcelServiceHeaderCom' => array(
                    'transmissionDate' => date('Y-m-d'),
                    'customerID'      => get_option('wc_balikovna_customer_id', ''),
                    'postCode'        => get_option('wc_balikovna_postcode', ''),
                ),
                'printParams' => array(
                    'idForm' => 'FORM_ID', // nastav dle ČP
                    'shiftHorizontal' => 0,
                    'shiftVertical' => 0,
                ),
                'position' => 1
            ),
            'parcelServiceData' => array(
                'parcelParams' => array(
                    'prefixParcelCode' => $prefix,
                    'weight'           => round($this->calculate_order_weight($order) * 100),
                    'insuredValue'     => $order->get_total(),
                    'amount'           => $order->get_payment_method() === 'cod' ? $order->get_total() : 0,
                    'notePrint'        => 'Obj. ' . $order->get_order_number(),
                ),
                'parcelAddress' => array(
                    'firstName'   => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
                    'surname'     => $order->get_shipping_last_name() ?: $order->get_billing_last_name(),
                    'address' => array(
                        'street'   => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
                        'city'     => $order->get_shipping_city() ?: $order->get_billing_city(),
                        'zipCode'  => ($delivery_type == 'box')
                            ? ($order->get_meta('_wc_balikovna_branch_zip') ?: $order->get_shipping_postcode() ?: $order->get_billing_postcode())
                            : ($order->get_shipping_postcode() ?: $order->get_billing_postcode()),
                    ),
                    'emailAddress' => $order->get_billing_email(),
                    'phoneNumber'  => $order->get_billing_phone(),
                ),
            ),
        );

        $api = new CPost_API_Client($api_url, $api_token, $secret_key);
        $this->balikovna_debug_log(['payload před API call' => $data]);

        try {
            $response = $api->call('parcelService', $data);
            $this->balikovna_debug_log(['API call odpověď' => $response]);
        } catch (\Throwable $e) {
            $this->balikovna_debug_log([
                'FATAL ERROR v $api->call!' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return array('success' => false, 'message' => 'Fatální chyba v komunikaci s API: ' . $e->getMessage());
        }

        if (isset($response['success']) && $response['success']) {
            $res_body = isset($response['body']) ? $response['body'] : [];
            if (isset($res_body['responseHeader']['resultParcelData']['parcelCode'])) {
                $parcelCode = $res_body['responseHeader']['resultParcelData']['parcelCode'];
                $order->update_meta_data('_wc_balikovna_parcel_code', $parcelCode);
                if (!empty($res_body['printingDataResult'])) {
                    $pdf_content = base64_decode($res_body['printingDataResult']);
                    $upload_dir = wp_upload_dir();
                    $filename = 'balikovna_label_' . sanitize_file_name($order->get_order_number()) . '_' . $parcelCode . '.pdf';
                    $filepath = trailingslashit($upload_dir['basedir']) . 'wc-balikovna-labels/' . $filename;
                    if (!file_exists(dirname($filepath))) {
                        wp_mkdir_p(dirname($filepath));
                    }
                    file_put_contents($filepath, $pdf_content);
                    $label_url = trailingslashit($upload_dir['baseurl']) . 'wc-balikovna-labels/' . $filename;
                    $order->update_meta_data('_wc_balikovna_label_url', $label_url);
                    $order->update_meta_data('_wc_balikovna_label_generated', 'yes');
                    $order->save();
                    return array(
                        'success' => true,
                        'label_url' => $label_url,
                        'message' => __('Štítek úspěšně vygenerován z API ČP.', 'wc-balikovna-komplet')
                    );
                }
                return array('success' => false, 'message' => 'API nevrátilo štítek (printingDataResult).');
            }
            return array('success' => false, 'message' => 'API nevrátilo parcelCode: ' . print_r($res_body, true));
        } else {
            $msg = isset($response['error']) ? $response['error'] : __('Neznámá chyba API.', 'wc-balikovna-komplet');
            return array('success' => false, 'message' => $msg);
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
// AFTER you set $recipient (name/street/city/zip), přidej:
$branch_id = $order->get_meta('_wc_balikovna_branch_id') ?: '';
$branch_name = $order->get_meta('_wc_balikovna_branch_name') ?: '';
$branch_type = $order->get_meta('_wc_balikovna_branch_type') ?: '';
$branch_icon = $order->get_meta('_wc_balikovna_branch_icon') ?: '';

// pokud icon chybí, pokusíme se ji zjistit z typu
if ( empty( $branch_icon ) && ! empty( $branch_type ) ) {
    $branch_icon = $this->get_piktogram_for_branch_type( $branch_type );
}


// ulož do $recipient tak, aby build_pdf_from_template mohl použít
if ( $branch_id )   $recipient['branch_id']   = $branch_id;
if ( $branch_name ) $recipient['branch_name'] = $branch_name;
if ( $branch_type ) $recipient['branch_type'] = $branch_type;
if ( $branch_icon ) $recipient['branch_icon'] = $branch_icon;

        if ( empty( $recipient['name'] ) || empty( $recipient['street'] ) || empty( $recipient['city'] ) || empty( $recipient['zipCode'] ) ) {
            return new WP_Error( 'missing_recipient', 'Chybí některé povinné údaje příjemce' );
        }

        // Spočítat hmotnost (převod podle nastavení WooCommerce na kg)
        $weight_kg = $this->calculate_order_weight( $order );


// --- START: robustní určení deliveryType (nahraď původní blok) ---
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
            // extrahovat instance_id z method_id pokud je ve formátu 'balikovna:3'
            if ( strpos( $method_id, ':' ) !== false ) {
                $parts = explode( ':', $method_id );
                if ( isset( $parts[1] ) && is_numeric( $parts[1] ) ) {
                    $instance_id = intval( $parts[1] );
                    error_log( 'prepare_label_data - extracted instance_id from method_id: ' . $instance_id );
                }
            }

            // pokud máme instance id, zkusíme načíst option z shipping method instance
            if ( ! empty( $instance_id ) && class_exists( 'WC_Balikovna_Shipping_Method' ) ) {
                try {
                    $mi = new \WC_Balikovna_Shipping_Method( $instance_id );
                    // upravte název option pokud má vaše metoda odlišný název
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

            // heuristika podle názvu metody (method_title)
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

            // fallback na order meta (pokud přeci jen někde existuje)
            $maybe_meta = $order->get_meta('_wc_balikovna_delivery_type');
            if ( ! empty( $maybe_meta ) ) {
                $delivery_type = $maybe_meta;
                error_log( 'prepare_label_data - delivery_type taken from order meta fallback: ' . $delivery_type );

                break;
            }
        }
    }
}


// poslední fallback
if ( empty( $delivery_type ) ) {
    $delivery_type = 'box';

}
// --- END: robustní určení deliveryType ---
return array(
    'sender'       => $sender,
    'recipient'    => $recipient,
    'weight'       => $weight_kg,
    'deliveryType' => $delivery_type,
);
} // <-- Tato závorka musí být, aby jsi ukončil funkci prepare_label_data!

/**
 * Vrátí layout/positioning ...
 */
private function get_template_layout_settings( $template_key ) {
    // default settings (bezpečný fallback)
    $defaults = array(
        'recipientX' => 12,
        'recipientY' => 45,
        'senderY'    => 17,
        'icons_offset_default' => 5,
        'icons_offset_box_down' => 12,
        'icons_offset' => 5,
        'maxWidth'    => 85,
        'font_family' => 'dejavusans',
        'font_size_normal' => 11,
        'font_size_bold' => 12,
    );

    // per-template overrides
    $templates = array(
        'box' => array(
            'recipientY'   => 52,
            'senderY'      => 13,
            'icons_offset' => 0,
            'maxWidth'     => 85,
            'font_size_bold' => 12,
            'font_size_normal' => 11,
        ),
        'address' => array(
            'recipientY'   => 45,
            'senderY'      => 17,
            'icons_offset' => 5,
            'maxWidth'     => 85,
            'font_size_bold' => 12,
            'font_size_normal' => 11,
        ),
    );

    $overrides = isset( $templates[ $template_key ] ) ? $templates[ $template_key ] : array();

    return array_merge( $defaults, $overrides );
}

private function get_piktogram_for_branch($branch_type, $delivery_type) {
    $filename = '';
    // Při doručení NA ADRESU
    if ($delivery_type === 'address') {
        $filename = '04_balikovna na adresu_10_10.jpg';
    }
    // Při doručení do BOXU (zásilkový box, automat)
    else if ($delivery_type === 'box' || $branch_type === 'box') {
        $filename = '07_BOX_10_10.jpg';
    }
    // Při doručení na POBOČKU BALÍKOVNY
    else if ($delivery_type === 'balikovna' || $branch_type === 'branch') {
        $filename = '05_ukladana_zasilka_10_10.jpg';
    }
    // Možné fallbacky (pro jistotu, pokud typ není jednoznačný)
    else {
        $filename = '05_ukladana_zasilka_10_10.jpg';
    }
    return $this->get_asset_path($filename);
}


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
        return new WP_Error( 'missing_lib', 'FPDI/TCPDF knihovny nejsou dostupné. Nainstalujte závislosti (composer require setasign/fpdi-tcpdf) nebo přidejte knihovny do pluginu.' );
    }

    try {
// --- DEBUG LOGGER: ukládá do složky pluginu /logs/debug.log ---
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



// --- START: normalized deliveryType, layout selection, template load & PDF init (replace duplicated block) ---

// Normalizace hodnoty (trim + lowercase)
$deliveryTypeNormalized = is_string( $rawDelivery ) ? strtolower( trim( $rawDelivery ) ) : '';
if ( $deliveryTypeNormalized === '' ) {
    $deliveryTypeNormalized = 'box';
}
$log( 'build_pdf_from_template - normalized deliveryType: ' . $deliveryTypeNormalized );



// získej layout nastavení podle template (delivery type) — jen jednou
$layout = $this->get_template_layout_settings( $deliveryTypeNormalized );


// Vybereme šablonu podle delivery type (default = box)
$template_name = 'BAL_stitek_HD_balikovna.pdf'; // default pro box
if ( $deliveryTypeNormalized === 'address' ) {
    $template_name = 'HAS_Balikovna_plus_cistopis_podavatele.pdf';
    $log( 'build_pdf_from_template - selected address template: ' . $template_name );
} else {
    $log( 'build_pdf_from_template - selected box/default template: ' . $template_name );
}

// sestavíme cestu k souboru a zkontrolujeme existenci (fallback na default)
$template_path = trailingslashit( $plugin_dir ) . 'assets/' . $template_name;
if ( ! file_exists( $template_path ) ) {
    $log( 'build_pdf_from_template - template not found: ' . $template_path . ' — trying fallback.' );
    $fallback = trailingslashit( $plugin_dir ) . 'assets/BAL_stitek_HD_balikovna.pdf';
    if ( file_exists( $fallback ) ) {
        $template_path = $fallback;
        $log( 'build_pdf_from_template - using fallback template: ' . $template_path );
    } else {
        $log( 'build_pdf_from_template - ERROR: no template found (tried ' . $template_name . ' and fallback).' );
        return new WP_Error( 'template_missing', 'Šablona PDF nebyla nalezena: ' . $template_name );
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
            return new WP_Error( 'no_pdf_engine', 'Nevhodná PDF knihovna' );
        }

        // Metadata
        if ( method_exists( $pdf, 'SetCreator' ) ) {
            $pdf->SetCreator( 'WooCommerce Balíkovna' );
            $pdf->SetAuthor( get_bloginfo( 'name' ) );
            $pdf->SetTitle( 'Balíkovna - Štítek' );
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

        $maxWidth = 85; // uprav dle šablony


// Instantiate appropriate FPDI class (jen tady)
if ( class_exists( '\setasign\Fpdi\Tcpdf\Fpdi' ) ) {
    $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
} elseif ( class_exists( '\setasign\Fpdi\Fpdi' ) ) {
    $pdf = new \setasign\Fpdi\Fpdi();
} else {
    return new WP_Error( 'no_pdf_engine', 'Nevhodná PDF knihovna' );
}

// Metadata / header/footer
if ( method_exists( $pdf, 'SetCreator' ) ) {
    $pdf->SetCreator( 'WooCommerce Balíkovna' );
    $pdf->SetAuthor( get_bloginfo( 'name' ) );
    $pdf->SetTitle( 'Balíkovna - Štítek' );
}
if ( method_exists( $pdf, 'SetPrintHeader' ) ) {
    $pdf->SetPrintHeader( false );
    $pdf->SetPrintFooter( false );
}

// Load template into FPDI
$pageCount = $pdf->setSourceFile( $template_path );
$tplId = $pdf->importPage( 1 );
$pdf->AddPage();
$pdf->useTemplate( $tplId, 0, 0 );

// Font (TCPDF supports UTF-8)
if ( method_exists( $pdf, 'SetFont' ) ) {
    $pdf->SetFont( 'dejavusans', '', 10 );
}

// mapuj layout proměnné (jednou)
$maxWidth       = isset( $layout['maxWidth'] ) ? $layout['maxWidth'] : 85;
$recipientX     = isset( $layout['recipientX'] ) ? $layout['recipientX'] : 12;
$recipientY     = isset( $layout['recipientY'] ) ? $layout['recipientY'] : 45;
$senderY        = isset( $layout['senderY'] ) ? $layout['senderY'] : 17;
$font_family    = isset( $layout['font_family'] ) ? $layout['font_family'] : 'dejavusans';
$fontSizeBold   = isset( $layout['font_size_bold'] ) ? $layout['font_size_bold'] : 12;
$fontSizeNormal = isset( $layout['font_size_normal'] ) ? $layout['font_size_normal'] : 11;
$icons_offset   = isset( $layout['icons_offset'] ) ? $layout['icons_offset'] : ( isset( $layout['icons_offset_box_down'] ) && $deliveryTypeNormalized === 'box' ? $layout['icons_offset_box_down'] : ( isset( $layout['icons_offset_default'] ) ? $layout['icons_offset_default'] : 5 ) );

// Poznámka: $iconsY_mm počítej až až - tam kde máš smysl zavolat $pdf->GetY() (po vykreslení adresy/odesílatele).
// --- END replacement ---


        // --- Odesílatel ---
        $senderX = 12;
        $senderY = 17;

        $order_number_display = isset( $data['sender']['order_number'] ) && ! empty( $data['sender']['order_number'] ) ? $data['sender']['order_number'] : 'unknown';
        $sender_lines = array(
            'Obj. č. ' . $order_number_display . ' z SU~PR sušené | pražené',
            'Petrašovice 61',
            'Bílá - Petrašovice',
            '463 42',
        );


        $font_family = 'dejavusans';
        $font_style = '';
        $fontSize = 10;
        $minFontSize = 3;
        $lineGap = 1;

// respektuj hodnoty z $layout pokud už byly nastaveny, jinak použij bezpečné defaulty
$font_family = isset( $font_family ) && $font_family ? $font_family : ( isset( $layout['font_family'] ) ? $layout['font_family'] : 'dejavusans' );
$font_style  = isset( $font_style ) ? $font_style : '';
$fontSize    = isset( $fontSize ) ? $fontSize : 10;
$minFontSize = isset( $minFontSize ) ? $minFontSize : 3;
$lineGap     = isset( $lineGap ) ? $lineGap : 1;

$fontSizeBold   = isset( $fontSizeBold ) && $fontSizeBold ? $fontSizeBold : ( isset( $layout['font_size_bold'] ) ? $layout['font_size_bold'] : 12 );
$fontSizeNormal = isset( $fontSizeNormal ) && $fontSizeNormal ? $fontSizeNormal : ( isset( $layout['font_size_normal'] ) ? $layout['font_size_normal'] : 11 );


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
                $first = rtrim( $first ) . '…';
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


        // --- Adresát ---
        $recipientX = 12;
        $recipientY = 45;

// --- Adresát ---

// Pokud jde o box šablonu, posuň adresát níže (doladit hodnotu podle potřeby)
if ( isset( $deliveryTypeNormalized ) && $deliveryTypeNormalized === 'box' ) {
    // Zvýšení hodnoty posune adresát níže (větší = níže)
    $recipientY = 45; // posun dolů oproti předchozí hodnotě (experimentálně +16 mm)
    // pokud je definováno $senderY (nastaveno výše), můžeme ho nechat nebo posunout taky pokud je potřeba
    if ( isset( $senderY ) ) {
        $senderY = $senderY; // ponechat odesílatele -- nebo uprav např. $senderY += 0;
    }
}


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
                $line2 = 'Balíkovna ID: ' . $branch_id;
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

// Nastavíme kurzor pod adresátem
$pdf->SetY( $curY + 1 );

// --- Ikony (zarovnání podle začátku adresy a doladění hmotnosti) ---
// Používáme $recipientX a $recipientY (adresát) jako referenci pro vertikální zarovnání.
$icons_reference_y = isset($recipientY) ? $recipientY : $pdf->GetY(); // pokud není recipientY, fallback
// Parametry ladění (mm) - uprav podle výsledku
$leftX = 12; // X pozice piktogramu vzhledem k levému okraji (můžeš upravit)
$leftY = 120;   // doleva/nahoru doladění pro levý piktogram (doplň; kladné posouvá dolů)
$weight_vertical_adjust_mm = 5.0; // kolik mm posunout hmotnost dolů oproti adresní lince (zvýšit = více dolů)
$weight_text_vertical_extra = 2.0; // další jemné doladění pro text uvnitř hmotnostního piktogramu


// Levý piktogram - očekávané jméno; kód zkusí tolerantní varianty
$left_candidate = '04_balikovna na adresu_10_10.jpg';
$left_img_path = $this->get_asset_path( $left_candidate );
if ( empty( $left_img_path ) ) {
    $alt = str_replace( ' ', '_', $left_candidate );
    $left_img_path = $this->get_asset_path( $alt );
}

// Levý piktogram - výběr podle typu; NEPOUŽÍVEJ $left_candidate, pouze funkci!
$left_img_path = $this->get_piktogram_for_branch(
    isset($data['recipient']['branch_type']) ? $data['recipient']['branch_type'] : '',
    isset($data['deliveryType']) ? $data['deliveryType'] : ''
);

// další tolerantní hledání
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

// Pravý (hmotnost) piktogram - původní název
$right_img_path = $this->get_asset_path( '18_hmotnost_hodnota_20_10.jpg' );
if ( empty( $right_img_path ) ) {
    $legacy = trailingslashit( WC_BALIKOVNA_PLUGIN_DIR ) . 'assets/18_hmotnost_hodnota_20_10.jpg';
    if ( file_exists( $legacy ) ) $right_img_path = $legacy;
}

// Rozměry (mm) - doladíš podle výsledku
$leftW  = 10; $leftH  = 10;
$rightW = 20; $rightH = 10;

// --- Ikony: posun obou ikon níž + posun textu hmotnosti doprava ---

// reference jako dřív
$iconsY_mm = $pdf->GetY() + 5;

// vypočítat offset ikon podle layoutu (fallback 5 mm)
$icons_offset = isset( $layout['icons_offset'] )
    ? $layout['icons_offset']
    : ( isset( $layout['icons_offset_box_down'] ) && $deliveryTypeNormalized === 'box' ? $layout['icons_offset_box_down'] : ( isset( $layout['icons_offset_default'] ) ? $layout['icons_offset_default'] : 5 ) );
$iconsY_mm = $pdf->GetY() + $icons_offset;


// rozměry (nezměněno)
$leftW  = 10; $leftH  = 10;
$rightW = 20; $rightH = 10;

// výchozí X souřadnice (ponech stejné jako u box verze)
$leftX  = 12;
$rightX = 70;

// VŠEOBECNÝ VERTIKÁLNÍ POSUN (změň jen toto číslo pro obě ikony)
$vertical_shift_mm = 5.5; // zvýšit = ikony více dolů, snížit = nahoru

// pevné Y pozice (posunuté dolů)
$leftY  = $iconsY_mm + $vertical_shift_mm;
$rightY = $iconsY_mm + $vertical_shift_mm;

// POSUN TEXTU HMOTNOSTI (vodorovně / svisle)

$weight_text_horizontal_shift = 4.0; // pozitivní = posun doprava (nastavit podle potřeby)

$weight_text_horizontal_shift = 3.5; // pozitivní = posun doprava (nastavit podle potřeby)

$weight_text_vertical_extra   = 2.0; // již používané drobné doladění (ponechat/ladit)



// Vykreslíme levou ikonu


if ( ! empty( $left_img_path ) && file_exists( $left_img_path ) ) {
    try {
        $pdf->Image( $left_img_path, $leftX, $leftY, $leftW, $leftH, '', '', '', false, 300 );
    } catch ( Exception $e ) {
        error_log( 'WC Balíkovna DEBUG: left icon render failed: ' . $e->getMessage() );
    }
} else {
    error_log( 'WC Balíkovna DEBUG: left icon not found: ' . $left_candidate );
}

// Vykreslíme pravou ikonu (grafika pro hmotnost)

$drawWeightInside = false;
if ( ! empty( $right_img_path ) && file_exists( $right_img_path ) ) {
    try {
        $pdf->Image( $right_img_path, $rightX, $rightY, $rightW, $rightH, '', '', '', false, 300 );
        $drawWeightInside = true;
    } catch ( Exception $e ) {
        error_log( 'WC Balíkovna DEBUG: right icon render failed: ' . $e->getMessage() );
        $drawWeightInside = false;
    }
} else {
    error_log( 'WC Balíkovna DEBUG: weight icon not found: ' . $right_img_path );
    $drawWeightInside = false;
}

// Vykreslení textu hmotnosti uvnitř pravého piktogramu (robustní centrování + posun + clamping)
if ( $drawWeightInside && isset( $data['weight'] ) && $data['weight'] !== '' ) {
    $font_family = ( method_exists( $pdf, 'SetFont' ) ? 'dejavusans' : 'Helvetica' );
    $font_size   = 9;
    $text = (string) $data['weight'];

    $textWidth = $pdf->GetStringWidth( $text, $font_family, '', $font_size );

    // základní centrování uvnitř piktogramu
    $baseX = $rightX + max( 0, ( $rightW - $textWidth ) / 2 );

    // horizontální posun (volitelný)
    $shift = isset( $weight_text_horizontal_shift ) ? floatval( $weight_text_horizontal_shift ) : 0.0;

    // výsledné X s posunem
    $textX = $baseX + $shift;

    // clamping: zajistit, aby text nezačínal vlevo od piktogramu ani nepřetekl vpravo
    $minTextX = $rightX;
    $maxTextX = $rightX + $rightW - $textWidth;

    if ( $textWidth > $rightW ) {
        // text je širší než piktogram -> zarovnat vlevo uvnitř piktogramu
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

// Posun kurzoru pod ikony, aby další obsah nezačínal přes ně
$afterIconsY = max( $leftY + $leftH, $rightY + $rightH );
$pdf->SetY( $afterIconsY + 2 );
// --- konec ikon ---

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
                // unknown unit — assume already kg
                $weight_kg = $total;
                break;
        }

        // ensure reasonable precision
        return round( (float) $weight_kg, 6 );
    }
	
	public function generate_box_label($order)
{
    // Správný prefix a delivery type pro box:
    $prefix = 'NB';
    $deliveryType = 'box';

    // Payload pro API
    $data = array(
        'parcelServiceHeader' => array(
            'parcelServiceHeaderCom' => array(
                'transmissionDate' => date('Y-m-d'),
                'customerID'      => get_option('wc_balikovna_customer_id', ''),
                'postCode'        => get_option('wc_balikovna_postcode', ''),
            ),
            'printParams' => array(
                'idForm' => 'FORM_ID', // ZMĚŇ! - podle ČP dokumentace
                'shiftHorizontal' => 0,
                'shiftVertical' => 0,
            ),
            'position' => 1
        ),
        'parcelServiceData' => array(
            'parcelParams' => array(
                'prefixParcelCode' => $prefix,
                'weight'           => round($this->calculate_order_weight($order) * 100),
                'insuredValue'     => $order->get_total(),
                'amount'           => $order->get_payment_method() === 'cod' ? $order->get_total() : 0,
                'notePrint'        => 'Obj. ' . $order->get_order_number(),
            ),
            'parcelAddress' => array(
                'firstName'   => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
                'surname'     => $order->get_shipping_last_name() ?: $order->get_billing_last_name(),
                'address' => array(
                    'street'   => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
                    'city'     => $order->get_shipping_city() ?: $order->get_billing_city(),
                    'zipCode'  => $order->get_meta('_wc_balikovna_branch_zip') ?: $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
                ),
                'emailAddress' => $order->get_billing_email(),
                'phoneNumber'  => $order->get_billing_phone(),
            ),
            // případně další pole podle potřeby
        ),
    );

    // DEBUG - vlož do logu aktuální payload
  $this->balikovna_debug_log(['payload před API call' => $data]);

    $api_url     = 'https://b2b.postaonline.cz:444/restservices/ZSKService/v1/';
    $api_token   = get_option('wc_balikovna_api_token', '');
    $secret_key  = get_option('wc_balikovna_api_private_key', '');

    $api = new CPost_API_Client($api_url, $api_token, $secret_key);

    // Volání API obalené try/catch
    try {
        $response = $api->call('parcelService', $data);
        $this->balikovna_debug_log(['API call odpověď' => $response]);
    } catch (\Throwable $e) {
        $this->balikovna_debug_log([
            'FATAL ERROR v $api->call!' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        return array('success' => false, 'message' => 'Fatální chyba v komunikaci s API: ' . $e->getMessage());
    }

    // Zpracování výsledku
    if (isset($response['success']) && $response['success']) {
        // ...další zpracování...
        return array('success' => true, 'message' => 'Štítek úspěšně vygenerován!', 'response' => $response);
    } else {
        $msg = isset($response['error']) ? $response['error'] : 'Neznámá chyba API při generování štítku (box)';
        return array('success' => false, 'message' => $msg);
    }
}
}