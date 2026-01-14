<?php

namespace DeliveryOrderSystem\Payment;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handle OnePay IPN (Instant Payment Notification)
 */
class IPN_Handler
{
    /**
     * Build log file path inside plugin and ensure directory exists.
     *
     * @return string
     */
    private static function get_log_file_path()
    {
        $plugin_dir = dirname(__DIR__, 2); // plugin root
        $logs_dir = $plugin_dir . '/logs';

        if (! is_dir($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }

        return $logs_dir . '/ipn.log';
    }

    /**
     * Write structured log entry to plugin log file.
     *
     * @param string $message Short message
     * @param array  $context Additional data to json encode
     */
    private static function log_message($message, $context = array())
    {
        $log_file = self::get_log_file_path();
        $log_entry = array_merge(array(
            'timestamp' => current_time('mysql'),
            'message' => $message,
        ), $context);

        $entry = wp_json_encode($log_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        error_log($entry, 3, $log_file);
    }

    /**
     * Standalone signature verification helper for debugging
     * This can be used to test OnePay signature verification independently
     *
     * @param string $method HTTP method (PUT)
     * @param string $uri Canonical URI
     * @param array $queryParams Query parameters array
     * @param array $headers Signed headers array
     * @param string $body Raw request body
     * @param string $date X-OP-Date header
     * @param int $expires X-OP-Expires header
     * @param string $receivedSignature Received X-OP-Authorization header
     * @return array Verification result with debug info
     */
    public static function debug_verify_signature($method, $uri, $queryParams, $headers, $body, $date, $expires, $receivedSignature)
    {
        try {
            // Parse date
            $dateObj = Util::parseIsoStringToDateObject($date);
            if (!$dateObj) {
                return array('valid' => false, 'error' => 'Invalid date format');
            }

            // Create auth object
            $auth = new Authorization(
                \PARTNER_PC,
                \PARTNER_SECRET_KEY_PC,
                'onepay',
                'paycollect',
                $method,
                $uri,
                $queryParams,
                $headers,
                $body,
                $dateObj,
                $expires
            );

            $expectedSignature = $auth->sign();

            return array(
                'valid' => hash_equals($expectedSignature, $receivedSignature),
                'expected' => $expectedSignature,
                'received' => $receivedSignature,
                'canonical_components' => array(
                    'method' => $method,
                    'uri' => $uri,
                    'query_string' => self::build_query_string($queryParams),
                    'headers' => $headers,
                    'body_hash' => hash('sha256', $body)
                )
            );
        } catch (\Exception $e) {
            return array('valid' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Build canonical query string
     */
    private static function build_query_string($params)
    {
        if (empty($params)) {
            return '';
        }

        ksort($params);
        $pairs = array();
        foreach ($params as $key => $value) {
            $pairs[] = AuthorizationHelper::uriEncode($key, true) . '=' . AuthorizationHelper::uriEncode(strval($value), true);
        }
        return implode('&', $pairs);
    }


    /**
     * Get header value handling WordPress header name conversion
     * WordPress converts: x-op-date -> x_op_date (dash to underscore)
     */
    private static function get_header_value($name)
    {
        $key1 = strtolower($name);
        $key2 = strtolower(str_replace('-', '_', $name));

        $headers = array_change_key_case(getallheaders(), CASE_LOWER);

        return $headers[$key1] ?? $headers[$key2] ?? '';
    }

    /**
     * Register REST API endpoint for IPN
     */
    public static function register_rest_route()
    {
        register_rest_route(
            'delivery-order-system/v1',
            '/ipn',
            array(
                'methods' => 'PUT',
                'callback' => array(__CLASS__, 'handle_ipn'),
                'permission_callback' => '__return_true', // Public endpoint, verify signature instead
            )
        );
    }

    /**
     * Handle IPN request from OnePay
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_ipn($request)
    {
        // Get fund_transfer_id from query parameter
        $fund_transfer_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

        // Get RAW request body (OWS1 requires exact bytes, not WordPress processed)
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        // Debug: Compare raw vs WordPress processed body and OnePay-style trimming
        $wp_body = $request->get_body();
        $trimmed_body = rtrim($body, "\r\n");
        self::log_message('OWS1 Payload Compare', array(
            'raw_body_length' => strlen($body),
            'raw_body_hash' => hash('sha256', $body),
            'trimmed_body_length' => strlen($trimmed_body),
            'trimmed_body_hash' => hash('sha256', $trimmed_body),
            'wp_body_length' => strlen($wp_body),
            'wp_body_hash' => hash('sha256', $wp_body),
            'bodies_different' => $body !== $wp_body,
            'raw_vs_trimmed_different' => $body !== $trimmed_body,
        ));

        self::log_message('IPN request received', array(
            'method' => $request->get_method(),
            'fund_transfer_id' => $fund_transfer_id,
            'headers' => array(
                'x_op_date' => $request->get_header('x_op_date'),
                'x_op_authorization' => $request->get_header('x_op_authorization'),
                'x_op_expires' => $request->get_header('x_op_expires'),
            ),
            'body_length' => strlen($body),
            'body_hash' => hash('sha256', $body),
            'payload_hash_method' => 'UNSIGNED-PAYLOAD',
        ));

        if (! $data || ! is_array($data)) {
            self::log_message('Invalid IPN request body', array('body' => $body));
            return new \WP_Error('invalid_data', 'Invalid request data', array('status' => 400));
        }

        // Verify signature
        $signature_valid = self::verify_signature($request, $body);
        if (! $signature_valid) {
            self::log_message('IPN signature verification failed', array('fund_transfer_id' => $fund_transfer_id));
            return new \WP_Error('invalid_signature', 'Invalid signature', array('status' => 401));
        }

        // Log IPN received
        self::log_message('IPN verified', array(
            'fund_transfer_id' => $fund_transfer_id,
            'data' => $data,
        ));

        // Process IPN data
        $processed = self::process_ipn($fund_transfer_id, $data);

        if ($processed) {
            // Return success response
            self::log_message('IPN processed successfully', array('fund_transfer_id' => $fund_transfer_id));
            return new \WP_REST_Response(
                array(
                    'error_code' => '0',
                    'message' => 'Success',
                ),
                200
            );
        } else {
            // Still return 200 to prevent retry, but log error
            self::log_message('IPN processing failed but returning 200', array('fund_transfer_id' => $fund_transfer_id));
            return new \WP_REST_Response(
                array(
                    'error_code' => '1',
                    'message' => 'Processing failed',
                ),
                200
            );
        }
    }

    /**
     * Verify OnePay signature
     *
     * @param WP_REST_Request $request REST request object
     * @param string $body Request body
     * @return bool True if signature is valid, false otherwise
     */
    private static function verify_signature($request, $body)
    {
        // Get headers
        $headers = $request->get_headers();
        $requestUrl = site_url($_SERVER['REQUEST_URI']);
        $x_op_date = isset($headers['x_op_date']) ? $headers['x_op_date'][0] : '';
        $x_op_authorization = isset($headers['x_op_authorization']) ? $headers['x_op_authorization'][0] : '';
        $x_op_expires = isset($headers['x_op_expires']) ? $headers['x_op_expires'][0] : '';

        if (empty($x_op_date) || empty($x_op_authorization)) {
            error_log('[Delivery Order System] Missing required headers for signature verification');
            return false;
        }

        // Parse authorization header to get timestamp
        // Format: OWS1-HMAC-SHA256 Credential=...,SignedHeaders=...,Signature=...
        // We need to reconstruct the signature to verify

        // Parse date from header
        try {
            $date = Util::parseIsoStringToDateObject($x_op_date);
            if (! $date) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        // Follow OnePay sample pcVerifyOWSIpn() exactly
        $queryParameter = array(); // Empty query params like sample
        $timeStamp = $date; // Use parsed date object

        // Parse SignedHeaders from actual Authorization header
        // OnePay tells us exactly which headers it signed - we MUST use those only
        $signedHeadersList = array();
        if (preg_match('/SignedHeaders=([^,]+)/', $x_op_authorization, $matches)) {
            $signedHeadersList = explode(';', $matches[1]);
        }

        // Build headers array using ONLY headers OnePay signed
        $headersSign = array();
        foreach ($signedHeadersList as $headerName) {
            $value = self::get_header_value($headerName);
            if ($value !== '') {
                $headersSign[$headerName] = $value;
            }
        }

        $auth = new Authorization(
            \PARTNER_PC,
            \PARTNER_SECRET_KEY_PC,
            'onepay',
            'paycollect',
            'PUT',
            $requestUrl, // Use full URI like sample $requestUrl
            $queryParameter, // Empty array like sample
            $headersSign,
            $body, // Use actual body content like sample $requestContent
            $timeStamp,
            intval($x_op_expires ?: 3600)
        );

        $expected_signature = $auth->sign(); // Use sign() method like sample

        // Extract signature from Authorization header
        // Format: OWS1-HMAC-SHA256 Credential=...,SignedHeaders=...,Signature=...
        $received_signature = '';
        if (preg_match('/Signature=([^,]+)/', $x_op_authorization, $matches)) {
            $received_signature = $matches[1];
        }

        // Extract signature from expected Authorization header
        $expected_sig_only = '';
        if (preg_match('/Signature=([^,]+)/', $expected_signature, $matches)) {
            $expected_sig_only = $matches[1];
        }

        // Verify signature
        return hash_equals($expected_sig_only, $received_signature);
    }

    /**
     * Process IPN data
     *
     * @param string $fund_transfer_id Fund transfer ID from query parameter
     * @param array $data IPN data
     * @return bool True if processed successfully, false otherwise
     */
    private static function process_ipn($fund_transfer_id, $data)
    {
        try {
            // Extract data
            $bank_txn_ref = isset($data['bank_txn_ref']) ? $data['bank_txn_ref'] : '';
            $invoice_id = isset($data['invoice']['id']) ? $data['invoice']['id'] : '';
            $user_id = isset($data['user']['id']) ? $data['user']['id'] : '';
            $user_reference = isset($data['user']['reference']) ? $data['user']['reference'] : '';
            $state = isset($data['state']) ? $data['state'] : '';
            $amount = isset($data['amount']) ? $data['amount'] : '';
            $currency = isset($data['currency']) ? $data['currency'] : 'VND';
            $create_time = isset($data['create_time']) ? $data['create_time'] : '';
            $account_number = isset($data['account']['account_number']) ? $data['account']['account_number'] : '';
            $bank_name = isset($data['account']['bank_name']) ? $data['account']['bank_name'] : '';
            $merchant_id = isset($data['merchant_id']) ? $data['merchant_id'] : '';

            // Log IPN details
            self::log_message('Processing IPN data', array(
                'fund_transfer_id' => $fund_transfer_id,
                'bank_txn_ref' => $bank_txn_ref,
                'invoice_id' => $invoice_id,
                'user_reference' => $user_reference,
                'state' => $state,
                'amount' => $amount,
                'currency' => $currency,
            ));

            // Parse user reference to get delivery_post_id and ma_van_don_id
            // Format: DELIVERY_{delivery_post_id}_{ma_van_don_id}_{timestamp}
            // Support both old format (without timestamp) and new format (with timestamp)
            if (preg_match('/^DELIVERY_(\d+)_(\d+)(?:_(\d+))?$/', $user_reference, $matches)) {
                $delivery_post_id = intval($matches[1]);
                $ma_van_don_id = intval($matches[2]);

                // Update payment status based on state
                if ($state === 'success' || $state === 'completed') {
                    // Payment successful - update order status
                    // You can add custom logic here to update order status, send email, etc.
                    self::handle_payment_success($delivery_post_id, $ma_van_don_id, $data);
                } elseif ($state === 'failed' || $state === 'cancelled') {
                    // Payment failed - update order status
                    self::handle_payment_failed($delivery_post_id, $ma_van_don_id, $data);
                }

                // Save IPN data to post meta for reference
                $ipn_data = array(
                    'fund_transfer_id' => $fund_transfer_id,
                    'bank_txn_ref' => $bank_txn_ref,
                    'invoice_id' => $invoice_id,
                    'user_id' => $user_id,
                    'user_reference' => $user_reference,
                    'state' => $state,
                    'amount' => $amount,
                    'currency' => $currency,
                    'create_time' => $create_time,
                    'account_number' => $account_number,
                    'bank_name' => $bank_name,
                    'merchant_id' => $merchant_id,
                    'ipn_received_at' => current_time('mysql'),
                );

                // Save to delivery post meta
                // update_post_meta($delivery_post_id, 'onepay_ipn_data', $ipn_data);
                // update_post_meta($delivery_post_id, 'onepay_last_ipn_time', current_time('mysql'));

                // Also save to ma_van_don post meta
                update_post_meta($ma_van_don_id, 'onepay_ipn_data', $ipn_data);
                update_post_meta($ma_van_don_id, 'onepay_last_ipn_time', current_time('mysql'));

                return true;
            } else {
                self::log_message('Invalid user reference format', array('user_reference' => $user_reference));
                return false;
            }
        } catch (\Exception $e) {
            self::log_message('Error processing IPN', array('message' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Handle successful payment
     *
     * @param int $delivery_post_id Delivery post ID
     * @param int $ma_van_don_id Ma van don post ID
     * @param array $data IPN data
     */
    private static function handle_payment_success($delivery_post_id, $ma_van_don_id, $data)
    {
        // Update payment status
        // update_post_meta($delivery_post_id, 'payment_status', 'paid');
        update_post_meta($ma_van_don_id, 'payment_status', 'paid');
        update_post_meta($ma_van_don_id, 'payment_date', current_time('mysql'));

        // Log payment success
        self::log_message('Payment successful', array(
            'delivery_post_id' => $delivery_post_id,
            'ma_van_don_id' => $ma_van_don_id,
        ));

        // You can add additional logic here:
        // - Send confirmation email
        // - Update order status
        // - Trigger webhook
        // etc.
    }

    /**
     * Handle failed payment
     *
     * @param int $delivery_post_id Delivery post ID
     * @param int $ma_van_don_id Ma van don post ID
     * @param array $data IPN data
     */
    private static function handle_payment_failed($delivery_post_id, $ma_van_don_id, $data)
    {
        // Update payment status
        // update_post_meta($delivery_post_id, 'payment_status', 'failed');
        update_post_meta($ma_van_don_id, 'payment_status', 'failed');

        // Log payment failure
        self::log_message('Payment failed', array(
            'delivery_post_id' => $delivery_post_id,
            'ma_van_don_id' => $ma_van_don_id,
        ));

        // You can add additional logic here:
        // - Send notification email
        // - Update order status
        // etc.
    }
}
