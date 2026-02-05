<?php
/**
 * Label generation and shipment handling for Czech Post API
 *
 * Requires TCPDF library to be installed at: {plugin_dir}/tcpdf/tcpdf.php
 * The library is bundled with the plugin and should be automatically available.
 * 
 * @package WC_Balikovna_Komplet
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Balikovna_Label Class
 * Handles shipment creation and PDF label generation
 */
class WC_Balikovna_Label
{
    /**
     * Default weight for parcels in grams
     * Can be overridden via filter: apply_filters('wc_balikovna_default_weight', 1000, $order_id)
     */
    const DEFAULT_PARCEL_WEIGHT = 1000;
    
    /**
     * API endpoint for shipment operations
     */
    const SHIPMENT_ENDPOINT = 'https://online.postservis.cz/api/v1/shipments';

    /**
     * Generate shipping label for order
     *
     * @param int $order_id Order identifier
     * @return array|WP_Error Result with PDF path or error
     */
    public function generate_label($order_id)
    {
        $order_obj = wc_get_order($order_id);
        
        if (!$order_obj) {
            return new WP_Error('invalid_order', __('Objednávka nebyla nalezena', 'wc-balikovna-komplet'));
        }

        // Determine shipment type
        $delivery_to_box = $this->is_box_delivery($order_obj);
        
        // Validate required branch data for box delivery
        if ($delivery_to_box && !$this->has_valid_branch_data($order_obj)) {
            return new WP_Error('missing_branch', __('Chybí informace o výdejním místě', 'wc-balikovna-komplet'));
        }

        // Create shipment via API
        $shipment_result = $this->create_shipment($order_obj, $delivery_to_box);
        
        if (is_wp_error($shipment_result)) {
            return $shipment_result;
        }

        // Store tracking information
        $tracking_code = $shipment_result['tracking_number'];
        $order_obj->update_meta_data('_balikovna_tracking_number', sanitize_text_field($tracking_code));
        $order_obj->update_meta_data('_balikovna_label_created', current_time('mysql'));
        $order_obj->update_meta_data('_balikovna_shipment_type', $delivery_to_box ? 'box' : 'address');
        $order_obj->save();

        // Generate PDF label
        $pdf_result = $this->generate_pdf_label($order_obj, $tracking_code, $delivery_to_box);
        
        return $pdf_result;
    }

    /**
     * Check if order uses box delivery method
     *
     * @param WC_Order $order_obj Order object
     * @return bool True if box delivery
     */
    private function is_box_delivery($order_obj)
    {
        $shipping_items = $order_obj->get_items('shipping');
        
        foreach ($shipping_items as $item) {
            $method_id = $item->get_method_id();
            
            // Check if it's balikovna and NOT address type
            if (strpos($method_id, 'balikovna') !== false) {
                if (strpos($method_id, 'address') !== false) {
                    return false;
                }
                
                // Check if branch ID exists
                $branch_id = $order_obj->get_meta('_wc_balikovna_branch_id');
                if (empty($branch_id)) {
                    // Check legacy format
                    $branch_id = $order_obj->get_meta('_balikovna_point');
                }
                
                return !empty($branch_id);
            }
        }
        
        return false;
    }

    /**
     * Validate branch data exists for order
     *
     * @param WC_Order $order_obj Order object
     * @return bool True if valid
     */
    private function has_valid_branch_data($order_obj)
    {
        $branch_id = $order_obj->get_meta('_wc_balikovna_branch_id');
        
        if (empty($branch_id)) {
            $branch_id = $order_obj->get_meta('_balikovna_point');
        }
        
        return !empty($branch_id);
    }

    /**
     * Create shipment through Czech Post API
     *
     * @param WC_Order $order_obj Order object
     * @param bool $is_box_delivery Box delivery flag
     * @return array|WP_Error Shipment data or error
     */
    private function create_shipment($order_obj, $is_box_delivery)
    {
        $api_token = get_option('wc_balikovna_api_token');
        $private_key = get_option('wc_balikovna_api_private_key');

        if (empty($api_token) || empty($private_key)) {
            return new WP_Error('missing_credentials', __('API přihlašovací údaje nejsou nastaveny', 'wc-balikovna-komplet'));
        }

        $shipment_payload = $this->prepare_shipment_data($order_obj, $is_box_delivery);
        
        $current_timestamp = time();
        $auth_signature = $this->create_signature($private_key, $current_timestamp);
        
        if (is_wp_error($auth_signature)) {
            return $auth_signature;
        }

        $request_headers = array(
            'Authorization' => 'Bearer ' . $api_token,
            'X-Timestamp' => $current_timestamp,
            'X-Signature' => $auth_signature,
            'Content-Type' => 'application/json',
        );

        error_log('WC Balíkovna: Creating shipment for order #' . $order_obj->get_id());
        error_log('WC Balíkovna: Payload: ' . wp_json_encode($shipment_payload));

        $api_response = wp_remote_post(self::SHIPMENT_ENDPOINT, array(
            'headers' => $request_headers,
            'body' => wp_json_encode($shipment_payload),
            'timeout' => 30,
        ));

        if (is_wp_error($api_response)) {
            error_log('WC Balíkovna: API request failed: ' . $api_response->get_error_message());
            return $api_response;
        }

        $response_code = wp_remote_retrieve_response_code($api_response);
        $response_body = wp_remote_retrieve_body($api_response);
        
        error_log('WC Balíkovna: API response code: ' . $response_code);
        error_log('WC Balíkovna: API response: ' . $response_body);

        if ($response_code !== 200 && $response_code !== 201) {
            return new WP_Error('api_error', sprintf(
                __('API vrátilo chybu: %s', 'wc-balikovna-komplet'),
                $response_body
            ));
        }

        $parsed_response = json_decode($response_body, true);
        
        if (empty($parsed_response['tracking_number'])) {
            return new WP_Error('missing_tracking', __('API nevrátilo tracking číslo', 'wc-balikovna-komplet'));
        }

        error_log('WC Balíkovna: Shipment created successfully with tracking: ' . $parsed_response['tracking_number']);

        return $parsed_response;
    }

    /**
     * Prepare shipment data payload for API
     *
     * @param WC_Order $order_obj Order object
     * @param bool $is_box_delivery Box delivery flag
     * @return array Shipment payload
     */
    private function prepare_shipment_data($order_obj, $is_box_delivery)
    {
        $sender_data = array(
            'name' => get_option('wc_balikovna_sender_name', ''),
            'street' => get_option('wc_balikovna_sender_street', ''),
            'city' => get_option('wc_balikovna_sender_city', ''),
            'zip' => get_option('wc_balikovna_sender_zip', ''),
            'phone' => get_option('wc_balikovna_sender_phone', ''),
            'email' => get_option('wc_balikovna_sender_email', ''),
        );

        $recipient_data = array(
            'name' => $order_obj->get_formatted_billing_full_name(),
            'phone' => $order_obj->get_billing_phone(),
            'email' => $order_obj->get_billing_email(),
        );

        // Add address for non-box delivery
        if (!$is_box_delivery) {
            $recipient_data['street'] = $order_obj->get_shipping_address_1();
            $recipient_data['city'] = $order_obj->get_shipping_city();
            $recipient_data['zip'] = $order_obj->get_shipping_postcode();
        }

        $parcel_info = array(
            'reference_number' => $order_obj->get_order_number(),
            'weight' => apply_filters('wc_balikovna_parcel_weight', self::DEFAULT_PARCEL_WEIGHT, $order_obj->get_id()),
            'value' => intval($order_obj->get_total()),
            'cash_on_delivery' => $this->get_cod_amount($order_obj),
        );

        $payload_structure = array(
            'sender' => $sender_data,
            'recipient' => $recipient_data,
            'delivery_type' => $is_box_delivery ? 'PARCEL_SHOP' : 'ADDRESS',
            'parcel' => $parcel_info,
        );

        // Add branch ID for box delivery
        if ($is_box_delivery) {
            $branch_id = $order_obj->get_meta('_wc_balikovna_branch_id');
            if (empty($branch_id)) {
                $branch_id = $order_obj->get_meta('_balikovna_point');
            }
            $payload_structure['parcel_shop_id'] = $branch_id;
        }

        return $payload_structure;
    }

    /**
     * Create HMAC signature for API authentication
     *
     * @param string $private_key Private key
     * @param int $timestamp Current timestamp
     * @return string|WP_Error Base64 encoded signature or error
     */
    private function create_signature($private_key, $timestamp)
    {
        $decoded_key = base64_decode($private_key, true);
        
        if ($decoded_key === false || empty($decoded_key)) {
            return new WP_Error('invalid_key', __('Privátní klíč má neplatný formát', 'wc-balikovna-komplet'));
        }
        
        $message_to_sign = $timestamp . ':shipments';
        $hmac_hash = hash_hmac('sha256', $message_to_sign, $decoded_key, true);
        
        return base64_encode($hmac_hash);
    }

    /**
     * Get COD amount from order
     *
     * @param WC_Order $order_obj Order object
     * @return int COD amount or 0
     */
    private function get_cod_amount($order_obj)
    {
        $payment_method = $order_obj->get_payment_method();
        
        if ($payment_method === 'cod' || strpos($payment_method, 'cod') !== false) {
            return intval($order_obj->get_total());
        }
        
        return 0;
    }

    /**
     * Generate PDF label for shipment
     *
     * @param WC_Order $order_obj Order object
     * @param string $tracking_code Tracking number
     * @param bool $is_box_delivery Box delivery flag
     * @return array|WP_Error PDF file info or error
     */
    private function generate_pdf_label($order_obj, $tracking_code, $is_box_delivery)
    {
        $tcpdf_path = WC_BALIKOVNA_PLUGIN_DIR . 'tcpdf/tcpdf.php';
        
        if (!file_exists($tcpdf_path)) {
            return new WP_Error('missing_tcpdf', __('TCPDF knihovna nebyla nalezena. Zkontrolujte instalaci pluginu.', 'wc-balikovna-komplet'));
        }
        
        require_once $tcpdf_path;

        $pdf_generator = new TCPDF('P', 'mm', 'A6', true, 'UTF-8', false);
        
        // Configure PDF document
        $pdf_generator->SetCreator('WC Balikovna Komplet');
        $pdf_generator->SetAuthor(get_bloginfo('name'));
        $pdf_generator->SetTitle(__('Štítek Balíkovny', 'wc-balikovna-komplet') . ' #' . $order_obj->get_order_number());
        
        // Remove default header/footer
        $pdf_generator->setPrintHeader(false);
        $pdf_generator->setPrintFooter(false);
        
        // Set margins
        $pdf_generator->SetMargins(5, 5, 5);
        $pdf_generator->SetAutoPageBreak(false, 5);
        
        // Add page
        $pdf_generator->AddPage();
        
        // Generate label content based on type
        if ($is_box_delivery) {
            $this->generate_box_label_content($pdf_generator, $order_obj, $tracking_code);
        } else {
            $this->generate_address_label_content($pdf_generator, $order_obj, $tracking_code);
        }

        // Save PDF to temporary location
        $upload_dir = wp_upload_dir();
        $label_dir = $upload_dir['basedir'] . '/balikovna-labels';
        
        if (!file_exists($label_dir)) {
            wp_mkdir_p($label_dir);
        }

        // Generate unique filename
        $safe_tracking = sanitize_file_name($tracking_code);
        $pdf_filename = sprintf(
            'label-%d-%s-%s.pdf',
            $order_obj->get_id(),
            $safe_tracking,
            wp_generate_password(8, false)
        );
        $pdf_filepath = $label_dir . '/' . $pdf_filename;
        
        $pdf_generator->Output($pdf_filepath, 'F');

        return array(
            'file' => $pdf_filepath,
            'url' => $upload_dir['baseurl'] . '/balikovna-labels/' . $pdf_filename,
            'tracking' => $tracking_code,
        );
    }

    /**
     * Generate label content for box delivery
     *
     * @param TCPDF $pdf_generator PDF object
     * @param WC_Order $order_obj Order object
     * @param string $tracking_code Tracking number
     */
    private function generate_box_label_content($pdf_generator, $order_obj, $tracking_code)
    {
        $vertical_position = 10;
        
        // Sender section
        $pdf_generator->SetFont('helvetica', 'B', 10);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->Cell(0, 5, __('Odesílatel:', 'wc-balikovna-komplet'), 0, 1);
        
        $vertical_position += 6;
        $pdf_generator->SetFont('helvetica', '', 9);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->MultiCell(0, 4, 
            get_option('wc_balikovna_sender_name', '') . "\n" .
            get_option('wc_balikovna_sender_street', '') . "\n" .
            get_option('wc_balikovna_sender_city', '') . ' ' . get_option('wc_balikovna_sender_zip', ''),
            0, 'L'
        );

        $vertical_position += 18;
        
        // Recipient section
        $pdf_generator->SetFont('helvetica', 'B', 10);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->Cell(0, 5, __('Příjemce:', 'wc-balikovna-komplet'), 0, 1);
        
        $vertical_position += 6;
        $pdf_generator->SetFont('helvetica', '', 9);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->MultiCell(0, 4,
            $order_obj->get_formatted_billing_full_name() . "\n" .
            $order_obj->get_billing_phone() . "\n" .
            $order_obj->get_billing_email(),
            0, 'L'
        );

        $vertical_position += 18;
        
        // Branch information
        $pdf_generator->SetFont('helvetica', 'B', 10);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->Cell(0, 5, __('Výdejní místo:', 'wc-balikovna-komplet'), 0, 1);
        
        $vertical_position += 6;
        $branch_name = $order_obj->get_meta('_wc_balikovna_branch_name');
        $branch_address = $order_obj->get_meta('_wc_balikovna_branch_address');
        $branch_city = $order_obj->get_meta('_wc_balikovna_branch_city');
        $branch_zip = $order_obj->get_meta('_wc_balikovna_branch_zip');
        
        $pdf_generator->SetFont('helvetica', '', 9);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->MultiCell(0, 4,
            $branch_name . "\n" .
            $branch_address . "\n" .
            $branch_city . ' ' . $branch_zip,
            0, 'L'
        );

        $vertical_position += 20;
        
        // COD highlight if applicable
        $cod_amount = $this->get_cod_amount($order_obj);
        if ($cod_amount > 0) {
            $pdf_generator->SetFillColor(255, 255, 0);
            $pdf_generator->SetFont('helvetica', 'B', 12);
            $pdf_generator->SetXY(10, $vertical_position);
            $pdf_generator->Cell(0, 8, __('DOBÍRKA:', 'wc-balikovna-komplet') . ' ' . number_format($cod_amount, 0, ',', ' ') . ' Kč', 1, 1, 'C', true);
            $vertical_position += 10;
        }

        // Barcode
        $pdf_generator->SetFont('helvetica', '', 8);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->write1DBarcode($tracking_code, 'C128', 10, $vertical_position, 85, 20, 0.4, array('stretch' => true));
        
        $vertical_position += 22;
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->Cell(0, 5, $tracking_code, 0, 1, 'C');
    }

    /**
     * Generate label content for address delivery
     *
     * @param TCPDF $pdf_generator PDF object
     * @param WC_Order $order_obj Order object
     * @param string $tracking_code Tracking number
     */
    private function generate_address_label_content($pdf_generator, $order_obj, $tracking_code)
    {
        $vertical_position = 10;
        
        // Sender section
        $pdf_generator->SetFont('helvetica', 'B', 10);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->Cell(0, 5, __('Odesílatel:', 'wc-balikovna-komplet'), 0, 1);
        
        $vertical_position += 6;
        $pdf_generator->SetFont('helvetica', '', 9);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->MultiCell(0, 4,
            get_option('wc_balikovna_sender_name', '') . "\n" .
            get_option('wc_balikovna_sender_street', '') . "\n" .
            get_option('wc_balikovna_sender_city', '') . ' ' . get_option('wc_balikovna_sender_zip', ''),
            0, 'L'
        );

        $vertical_position += 18;
        
        // Recipient section with full address
        $pdf_generator->SetFont('helvetica', 'B', 10);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->Cell(0, 5, __('Příjemce:', 'wc-balikovna-komplet'), 0, 1);
        
        $vertical_position += 6;
        $pdf_generator->SetFont('helvetica', '', 9);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->MultiCell(0, 4,
            $order_obj->get_formatted_billing_full_name() . "\n" .
            $order_obj->get_shipping_address_1() . "\n" .
            $order_obj->get_shipping_city() . ' ' . $order_obj->get_shipping_postcode() . "\n" .
            $order_obj->get_billing_phone(),
            0, 'L'
        );

        $vertical_position += 25;
        
        // COD highlight if applicable
        $cod_amount = $this->get_cod_amount($order_obj);
        if ($cod_amount > 0) {
            $pdf_generator->SetFillColor(255, 255, 0);
            $pdf_generator->SetFont('helvetica', 'B', 12);
            $pdf_generator->SetXY(10, $vertical_position);
            $pdf_generator->Cell(0, 8, __('DOBÍRKA:', 'wc-balikovna-komplet') . ' ' . number_format($cod_amount, 0, ',', ' ') . ' Kč', 1, 1, 'C', true);
            $vertical_position += 10;
        }

        // Barcode
        $pdf_generator->SetFont('helvetica', '', 8);
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->write1DBarcode($tracking_code, 'C128', 10, $vertical_position, 85, 20, 0.4, array('stretch' => true));
        
        $vertical_position += 22;
        $pdf_generator->SetXY(10, $vertical_position);
        $pdf_generator->Cell(0, 5, $tracking_code, 0, 1, 'C');
    }
}
