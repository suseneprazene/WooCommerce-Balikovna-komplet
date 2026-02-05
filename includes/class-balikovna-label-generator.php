<?php
/**
 * Balíkovna Label Generator
 *
 * @package WC_Balikovna
 */

defined('ABSPATH') || exit;

// Include TCPDF and FPDI libraries
require_once WC_BALIKOVNA_PATH . 'tcpdf/tcpdf.php';
require_once WC_BALIKOVNA_PATH . 'fpdi/src/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Class WC_Balikovna_Label_Generator
 */
class WC_Balikovna_Label_Generator {
    
    /**
     * Generate label for order
     *
     * @param int $order_id Order ID
     * @param array $shipment_data Shipment data
     * @return string|WP_Error PDF content or error
     */
    public function generate_label($order_id, $shipment_data) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', __('Objednávka nenalezena', 'wc-balikovna'));
        }
        
        $branch_info = WC_Balikovna_Order::get_branch_info($order_id);
        
        if (!$branch_info) {
            return new WP_Error('no_branch', __('Výdejní místo nebylo vybráno', 'wc-balikovna'));
        }
        
        try {
            // Create new PDF document
            $pdf = new Fpdi();
            
            // Set document information
            $pdf->SetCreator('WooCommerce Balíkovna');
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle('Balíkovna Label - ' . $order->get_order_number());
            
            // Add a page
            $pdf->AddPage();
            
            // Check if template exists
            $template_path = WC_BALIKOVNA_PATH . 'assets/template.pdf';
            
            if (file_exists($template_path)) {
                // Import template
                $pageCount = $pdf->setSourceFile($template_path);
                $tplIdx = $pdf->importPage(1);
                $pdf->useTemplate($tplIdx, 0, 0, 210); // A4 width
            }
            
            // Set font
            $pdf->SetFont('helvetica', '', 10);
            
            // Add tracking number / barcode
            if (isset($shipment_data['shipment_id'])) {
                $pdf->SetXY(15, 20);
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->Cell(0, 10, $shipment_data['shipment_id'], 0, 1);
                
                // Add 1D barcode
                $pdf->write1DBarcode($shipment_data['shipment_id'], 'C128', 15, 35, 80, 15, 0.4, array('border' => false));
            }
            
            // Sender information
            $pdf->SetXY(15, 60);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 5, __('Odesílatel:', 'wc-balikovna'), 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetX(15);
            $pdf->Cell(0, 5, get_bloginfo('name'), 0, 1);
            
            $store_address = get_option('woocommerce_store_address', '');
            $store_city = get_option('woocommerce_store_city', '');
            $store_postcode = get_option('woocommerce_store_postcode', '');
            
            if ($store_address) {
                $pdf->SetX(15);
                $pdf->Cell(0, 5, $store_address, 0, 1);
            }
            
            if ($store_city && $store_postcode) {
                $pdf->SetX(15);
                $pdf->Cell(0, 5, $store_postcode . ' ' . $store_city, 0, 1);
            }
            
            // Recipient information
            $pdf->SetXY(15, 100);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 5, __('Příjemce:', 'wc-balikovna'), 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetX(15);
            $pdf->Cell(0, 5, $order->get_formatted_billing_full_name(), 0, 1);
            $pdf->SetX(15);
            $pdf->Cell(0, 5, $order->get_billing_email(), 0, 1);
            $pdf->SetX(15);
            $pdf->Cell(0, 5, $order->get_billing_phone(), 0, 1);
            
            // Branch information
            $pdf->SetXY(15, 140);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 5, __('Výdejní místo:', 'wc-balikovna'), 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetX(15);
            $pdf->Cell(0, 5, $branch_info['branch_name'], 0, 1);
            $pdf->SetX(15);
            $pdf->Cell(0, 5, $branch_info['branch_address'], 0, 1);
            
            // Order information
            $pdf->SetXY(15, 170);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 5, __('Objednávka:', 'wc-balikovna'), 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetX(15);
            $pdf->Cell(0, 5, __('Číslo:', 'wc-balikovna') . ' ' . $order->get_order_number(), 0, 1);
            $pdf->SetX(15);
            $pdf->Cell(0, 5, __('Hodnota:', 'wc-balikovna') . ' ' . $order->get_formatted_order_total(), 0, 1);
            
            // COD information
            if ($order->get_payment_method() === 'cod') {
                $pdf->SetX(15);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(0, 5, __('Dobírka:', 'wc-balikovna') . ' ' . $order->get_formatted_order_total(), 0, 1);
            }
            
            // QR Code with tracking info
            if (isset($branch_info['tracking_number']) && !empty($branch_info['tracking_number'])) {
                $qr_data = $branch_info['tracking_number'];
                $pdf->write2DBarcode($qr_data, 'QRCODE,H', 150, 20, 40, 40, array('border' => false));
            }
            
            // Return PDF as string
            return $pdf->Output('', 'S');
            
        } catch (Exception $e) {
            return new WP_Error('pdf_generation_error', $e->getMessage());
        }
    }
    
    /**
     * Generate and save label to file
     *
     * @param int $order_id Order ID
     * @param array $shipment_data Shipment data
     * @param string $filepath File path to save
     * @return bool|WP_Error
     */
    public function generate_and_save_label($order_id, $shipment_data, $filepath) {
        $pdf_content = $this->generate_label($order_id, $shipment_data);
        
        if (is_wp_error($pdf_content)) {
            return $pdf_content;
        }
        
        $result = file_put_contents($filepath, $pdf_content);
        
        if ($result === false) {
            return new WP_Error('save_error', __('Nepodařilo se uložit štítek', 'wc-balikovna'));
        }
        
        return true;
    }
    
    /**
     * Generate labels for multiple orders
     *
     * @param array $order_ids Array of order IDs
     * @return string|WP_Error Combined PDF or error
     */
    public function generate_bulk_labels($order_ids) {
        if (empty($order_ids)) {
            return new WP_Error('no_orders', __('Žádné objednávky', 'wc-balikovna'));
        }
        
        try {
            $pdf = new Fpdi();
            
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                
                if (!$order) {
                    continue;
                }
                
                $branch_info = WC_Balikovna_Order::get_branch_info($order_id);
                
                if (!$branch_info || empty($branch_info['shipment_id'])) {
                    continue;
                }
                
                // Generate single label
                $single_label = $this->generate_label($order_id, array('shipment_id' => $branch_info['shipment_id']));
                
                if (is_wp_error($single_label)) {
                    continue;
                }
                
                // Import the generated PDF
                $pageCount = $pdf->setSourceFile('data://text/plain;base64,' . base64_encode($single_label));
                
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $pdf->AddPage();
                    $tplIdx = $pdf->importPage($pageNo);
                    $pdf->useTemplate($tplIdx);
                }
            }
            
            return $pdf->Output('', 'S');
            
        } catch (Exception $e) {
            return new WP_Error('bulk_generation_error', $e->getMessage());
        }
    }
}
