<?php
/**
 * Balíkovna API Client
 *
 * @package WC_Balikovna
 */

defined('ABSPATH') || exit;

/**
 * Class WC_Balikovna_API
 */
class WC_Balikovna_API {
    
    /**
     * API Base URL
     *
     * @var string
     */
    private $api_base_url = 'https://api.balikovna.cz/v1';
    
    /**
     * API Token
     *
     * @var string
     */
    private $api_token;
    
    /**
     * Private Key
     *
     * @var string
     */
    private $private_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_token = get_option('wc_balikovna_api_token', '');
        $this->private_key = get_option('wc_balikovna_private_key', '');
    }
    
    /**
     * Get list of branches
     *
     * @param string $query Search query
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array|WP_Error
     */
    public function get_branches($query = '', $limit = 100, $offset = 0) {
        $params = array(
            'limit' => $limit,
            'offset' => $offset
        );
        
        if (!empty($query)) {
            $params['search'] = $query;
        }
        
        $url = add_query_arg($params, $this->api_base_url . '/branches');
        
        return $this->make_request('GET', $url);
    }
    
    /**
     * Create a shipment
     *
     * @param array $order_data Order data
     * @return array|WP_Error
     */
    public function create_shipment($order_data) {
        $url = $this->api_base_url . '/shipments';
        
        return $this->make_request('POST', $url, $order_data);
    }
    
    /**
     * Get shipment details
     *
     * @param string $shipment_id Shipment ID
     * @return array|WP_Error
     */
    public function get_shipment($shipment_id) {
        $url = $this->api_base_url . '/shipments/' . $shipment_id;
        
        return $this->make_request('GET', $url);
    }
    
    /**
     * Get shipment label
     *
     * @param string $shipment_id Shipment ID
     * @return string|WP_Error PDF content or error
     */
    public function get_label($shipment_id) {
        $url = $this->api_base_url . '/labels/' . $shipment_id;
        
        return $this->make_request('GET', $url, null, true);
    }
    
    /**
     * Cancel a shipment
     *
     * @param string $shipment_id Shipment ID
     * @return array|WP_Error
     */
    public function cancel_shipment($shipment_id) {
        $url = $this->api_base_url . '/shipments/' . $shipment_id;
        
        return $this->make_request('DELETE', $url);
    }
    
    /**
     * Make API request
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array|null $data Request data
     * @param bool $raw_response Return raw response
     * @return array|string|WP_Error
     */
    private function make_request($method, $url, $data = null, $raw_response = false) {
        $args = array(
            'method' => $method,
            'headers' => $this->get_headers(),
            'timeout' => 30,
            'sslverify' => true
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($data);
        }
        
        $this->log('API Request: ' . $method . ' ' . $url, $data);
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log('API Error: ' . $response->get_error_message(), null, 'error');
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $this->log('API Response: ' . $status_code, $body);
        
        if ($raw_response) {
            return $body;
        }
        
        $result = json_decode($body, true);
        
        if ($status_code >= 400) {
            $error_message = isset($result['message']) ? $result['message'] : __('API request failed', 'wc-balikovna');
            $this->log('API Error Response: ' . $status_code, $result, 'error');
            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }
        
        return $result;
    }
    
    /**
     * Get request headers
     *
     * @return array
     */
    private function get_headers() {
        return array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_token,
            'X-Private-Key' => $this->private_key
        );
    }
    
    /**
     * Log API activity
     *
     * @param string $message Log message
     * @param mixed $data Additional data
     * @param string $level Log level
     */
    private function log($message, $data = null, $level = 'info') {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $logger = wc_get_logger();
        $context = array('source' => 'wc-balikovna-api');
        
        if ($data !== null) {
            $message .= ' | Data: ' . print_r($data, true);
        }
        
        $logger->log($level, $message, $context);
    }
}
