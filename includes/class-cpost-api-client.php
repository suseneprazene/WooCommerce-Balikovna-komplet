<?php
/**
 * Česká pošta B2B API klient pro WooCommerce Balíkovna.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPost_API_Client
{
    private $api_url;
    private $api_token;
    private $secret_key;

    public function __construct($api_url, $api_token, $secret_key)
    {
        $this->api_url    = rtrim($api_url, '/') . '/';
        $this->api_token  = $api_token;
        $this->secret_key = $secret_key;
    }

    public function call($endpoint, $data)
    {
        $json     = json_encode($data, JSON_UNESCAPED_UNICODE);
        $timestamp = time();
        $nonce    = $this->gen_uuid_v4();
        $sha256   = hash('sha256', $json);
        $to_sign  = $sha256 . ';' . $timestamp . ';' . $nonce;
        $signature = base64_encode(hash_hmac('sha256', $to_sign, $this->secret_key, true));
        $headers  = array(
            'Api-Token: ' . $this->api_token,
            'Authorization-Timestamp: ' . $timestamp,
            'Authorization-Content-SHA256: ' . $sha256,
            'Authorization: CP-HMAC-SHA256 nonce="' . $nonce . '" signature="' . $signature . '"',
            'Content-Type: application/json;charset=UTF-8',
        );

        $response = wp_remote_post($this->api_url . ltrim($endpoint, '/'), array(
            'headers' => $headers,
            'body'    => $json,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $result = json_decode($body, true);

        if ($code == 200 && is_array($result)) {
            return array('success' => true, 'body' => $result);
        }

        return array('success' => false, 'error' => $body ? $body : 'HTTP error: ' . $code);
    }

    /**
     * Generuje UUIDv4 (standard pro nonce)
     */
    private function gen_uuid_v4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}