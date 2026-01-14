<?php

namespace DeliveryOrderSystem\Payment;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * OnePay PayCollect API Integration
 * Handle creating virtual account and invoice with QR code
 */
class PayCollect
{
    /**
     * Create virtual account and invoice with QR code
     *
     * @param array $user_data User information
     * @param array $invoice_data Invoice information
     * @return array|false Response data with QR code on success, false on failure
     */
    public static function create_va_with_qr($user_data, $invoice_data)
    {
        $date = new \DateTime();
        $isoTime = Util::formatTimeToYYYYMMDDTHHMMSSZ($date);
        $microtime = microtime(true);

        // Generate unique reference
        $user_reference = isset($user_data['reference']) ? $user_data['reference'] : 'USER' . round($microtime * 1000);
        $invoice_reference = isset($invoice_data['reference']) ? $invoice_data['reference'] : 'INV' . round($microtime * 1000);

        // Prepare headers for signature
        $headersSign = array(
            \X_OP_DATE_HEADER => $isoTime,
            \X_OP_EXPIRES_HEADER => '3600',
        );

        // Validate and prepare user body
        $user_name = isset($user_data['name']) ? trim($user_data['name']) : '';

        // Name is required and must not be empty
        if (empty($user_name)) {
            error_log('[Delivery Order System] User name is required but empty');
            return false;
        }

        // Clean name: remove extra spaces
        $user_name = preg_replace('/\s+/', ' ', $user_name);

        // Name must have at least 2 characters and max 50 characters (per API spec)
        if (strlen($user_name) < 2) {
            error_log('[Delivery Order System] User name must be at least 2 characters');
            return false;
        }

        if (strlen($user_name) > 50) {
            error_log('[Delivery Order System] User name exceeds 50 characters limit');
            $user_name = mb_substr($user_name, 0, 50, 'UTF-8'); // Truncate to 50 chars
        }

        // Prepare user body with field length validation per API spec
        // Only include non-empty optional fields
        $userBody = array(
            'name' => $user_name, // R, String, max 50 - Required
        );

        // Add gender if provided (default to 'male' if not specified)
        if (isset($user_data['gender']) && in_array($user_data['gender'], array('male', 'female'))) {
            $userBody['gender'] = $user_data['gender'];
        } else {
            $userBody['gender'] = 'male'; // Default value
        }

        // Add optional fields only if they have values
        if (isset($user_data['address']) && ! empty(trim($user_data['address']))) {
            $userBody['address'] = mb_substr(trim($user_data['address']), 0, 200);
        }

        if (isset($user_data['mobile_number']) && ! empty(trim($user_data['mobile_number']))) {
            $userBody['mobile_number'] = mb_substr(trim($user_data['mobile_number']), 0, 50);
        }

        if (isset($user_data['email']) && ! empty(trim($user_data['email']))) {
            $userBody['email'] = mb_substr(trim($user_data['email']), 0, 50);
        }

        if (isset($user_data['id_card']) && ! empty(trim($user_data['id_card']))) {
            $userBody['id_card'] = mb_substr(trim($user_data['id_card']), 0, 50);
        }

        if (isset($user_data['issue_date']) && ! empty(trim($user_data['issue_date']))) {
            $userBody['issue_date'] = mb_substr(trim($user_data['issue_date']), 0, 50);
        }

        if (isset($user_data['issue_by']) && ! empty(trim($user_data['issue_by']))) {
            $userBody['issue_by'] = mb_substr(trim($user_data['issue_by']), 0, 50);
        }

        if (isset($user_data['description']) && ! empty(trim($user_data['description']))) {
            $userBody['description'] = mb_substr(trim($user_data['description']), 0, 200);
        }

        // Add expired_time if provided
        if (isset($user_data['expired_time']) && ! empty($user_data['expired_time'])) {
            $userBody['expired_time'] = $user_data['expired_time'];
        }

        // Prepare invoice body with validation per API spec
        $amount = isset($invoice_data['amount']) ? strval($invoice_data['amount']) : '0';
        if (empty($amount) || ! is_numeric($amount) || floatval($amount) <= 0) {
            error_log('[Delivery Order System] Invoice amount must be a positive number');
            return false;
        }

        $invoiceBody = array(
            'amount' => $amount, // R, String, max 20 - Required
        );

        // Add description only if provided
        if (isset($invoice_data['description']) && ! empty(trim($invoice_data['description']))) {
            $invoiceBody['description'] = mb_substr(trim($invoice_data['description']), 0, 200);
        }

        // Build batch request
        $contentUser = array(
            'name' => 'user',
            'do_on' => 'always',
            'method' => 'PUT',
            'href' => '/partners/' . \PARTNER_PC . '/users/' . $user_reference,
            'body' => $userBody,
        );

        $contentInvoice = array(
            'name' => 'invoice',
            'do_on' => '$user.response.status == 201 || $user.response.status == 200',
            'method' => 'PUT',
            'href' => '/partners/' . \PARTNER_PC . '/users/' . $user_reference . '/invoices/' . $invoice_reference,
            'body' => $invoiceBody,
        );

        $bodyRequest = array($contentUser, $contentInvoice);
        $jsonData = json_encode($bodyRequest, JSON_UNESCAPED_UNICODE);

        // Log request for debugging (only first 500 chars to avoid huge logs)
        $log_data = array(
            'user_name' => $user_name,
            'user_reference' => $user_reference,
            'invoice_reference' => $invoice_reference,
            'request_preview' => $jsonData,
        );
        error_log('[Delivery Order System] PayCollect request: ' . print_r($log_data, true));

        // Prepare URI and query parameters
        $uri = \URL_PREFIX_PC . '/batchs';
        $queryParameter = array();

        // Create authorization signature
        $auth = new Authorization(
            \PARTNER_PC,
            \PARTNER_SECRET_KEY_PC,
            'onepay',
            'paycollect',
            'POST',
            $uri,
            $queryParameter,
            $headersSign,
            $jsonData,
            $date,
            3600
        );
        $stringAuth = $auth->sign();

        // Prepare request URL
        $requestURL = \DOMAIN_PREFIX . $uri;

        // Prepare request headers
        $contentLength = strlen($jsonData);
        $headerRequest = array(
            'Accept: application/json',
            'Accept-Language: vi',
            'Content-Type: application/json',
            'X-OP-Date: ' . $isoTime,
            'X-OP-Authorization: ' . $stringAuth,
            'X-OP-Expires: 3600',
            'Content-Length: ' . $contentLength,
        );

        // Make API call
        $response = self::call_post_request($requestURL, $jsonData, $headerRequest);

        if ($response === false) {
            error_log('[Delivery Order System] PayCollect API call failed');
            return false;
        }

        // Parse response
        $responseData = json_decode($response, true);

        if (! $responseData || ! is_array($responseData)) {
            error_log('[Delivery Order System] Invalid PayCollect API response: ' . $response);
            return false;
        }

        // Check if invoice was created successfully (user might already exist)
        $userResponse = null;
        $invoiceResponse = null;
        $userExists = false;

        foreach ($responseData as $item) {
            $itemStatus = isset($item['status']) ? intval($item['status']) : 0;
            $itemName = isset($item['name']) ? $item['name'] : '';

            // Check for user response (200 = exists, 201 = created)
            if ($itemName === 'user' && ($itemStatus === 201 || $itemStatus === 200)) {
                $userResponse = isset($item['body']) ? $item['body'] : null;
                if ($itemStatus === 200) {
                    $userExists = true; // User already existed
                }
            }

            // Check for invoice response (most important - we need this)
            if ($itemName === 'invoice' && ($itemStatus === 201 || $itemStatus === 200)) {
                $invoiceResponse = isset($item['body']) ? $item['body'] : null;
            }

            // Fallback identification by body content if name is missing
            if (!$userResponse && isset($item['body']['accounts']) && ($itemStatus === 201 || $itemStatus === 200)) {
                $userResponse = $item['body'];
                if ($itemStatus === 200) {
                    $userExists = true;
                }
            }
            if (!$invoiceResponse && (isset($item['body']['qr']) || isset($item['body']['amount'])) && ($itemStatus === 201 || $itemStatus === 200)) {
                $invoiceResponse = $item['body'];
            }
        }

        // Invoice is required, user response is optional (user might already exist)
        if (! $invoiceResponse) {
            // Check if batch failed because user already exists
            $errorMessage = '';

            // Check different possible error response structures
            if (isset($responseData['message'])) {
                // Direct error response: {"state":"failed","message":"User already exists",...}
                $errorMessage = $responseData['message'];
            } elseif (isset($responseData[0]['body']['message'])) {
                // Batch response with error in first item
                $errorMessage = $responseData[0]['body']['message'];
            } elseif (isset($responseData[0]['message'])) {
                // Batch response with error at top level
                $errorMessage = $responseData[0]['message'];
            }

            // If user already exists, try to get user info and create invoice separately
            if (
                stripos($errorMessage, 'User already exists') !== false ||
                stripos($errorMessage, 'already exists') !== false ||
                (isset($responseData['name']) && $responseData['name'] === 'PARAMETER_INVALID')
            ) {

                error_log('[Delivery Order System] User already exists, attempting to get user info and create invoice separately');

                // Get user info first
                $userInfo = self::get_user($user_reference);
                if ($userInfo && isset($userInfo['accounts'])) {
                    $userResponse = $userInfo;

                    // Create invoice separately
                    $invoiceResult = self::create_invoice($user_reference, $invoice_reference, $invoiceBody);
                    if ($invoiceResult && isset($invoiceResult['qr']['image'])) {
                        $invoiceResponse = $invoiceResult;
                    } elseif ($invoiceResult) {
                        // Invoice created but might not have QR in response, use invoice data anyway
                        $invoiceResponse = $invoiceResult;
                    }
                } else {
                    error_log('[Delivery Order System] Failed to get user info for existing user: ' . $user_reference);
                }
            }

            // If still no invoice, log and return false
            if (! $invoiceResponse) {
                error_log('[Delivery Order System] Failed to create invoice. User may already exist. Response: ' . $response);
                return false;
            }
        }

        // If user already exists but we don't have userResponse, try to get account info from invoice
        if (! $userResponse && $invoiceResponse) {
            // Try to extract user info from invoice response if available
            if (isset($invoiceResponse['accounts'])) {
                $userResponse = array('accounts' => $invoiceResponse['accounts']);
            }
        }

        // Extract QR code from invoice response or user accounts
        $qr_image_base64 = null;
        if (isset($invoiceResponse['qr']['image'])) {
            $qr_image_base64 = $invoiceResponse['qr']['image'];
        } elseif (isset($userResponse['accounts']['qr']['image'])) {
            $qr_image_base64 = $userResponse['accounts']['qr']['image'];
        }

        // Return response data
        return array(
            'success' => true,
            'user' => $userResponse,
            'invoice' => $invoiceResponse,
            'qr_image_base64' => $qr_image_base64,
            'account_number' => isset($userResponse['accounts']['account_number']) ? $userResponse['accounts']['account_number'] : '',
            'bank_name' => isset($userResponse['accounts']['bank_name']) ? $userResponse['accounts']['bank_name'] : '',
        );
    }

    /**
     * Call POST request to OnePay API
     *
     * @param string $url API URL
     * @param string $content Request body
     * @param array $headers Request headers
     * @return string|false Response body on success, false on failure
     */
    private static function call_post_request($url, $content, $headers)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log('[Delivery Order System] cURL error: ' . $error);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // For batch requests, even HTTP 400 might contain partial success (e.g., invoice created)
        // So we parse the response anyway and let the caller decide
        if ($httpCode !== 200 && $httpCode !== 201) {
            $error_message = '[Delivery Order System] PayCollect API returned HTTP ' . $httpCode;
            $error_message .= ' | URL: ' . $url;

            // Log response body (truncated if too long)
            $response_preview = strlen($response) > 500 ? substr($response, 0, 500) . '...' : $response;
            $error_message .= ' | Response: ' . $response_preview;

            error_log($error_message);

            // Still return response for HTTP 400 to check if invoice was created
            // Return false only for other error codes
            if ($httpCode === 400) {
                return $response; // Allow parsing of 400 response
            }
            return false;
        }

        return $response;
    }

    /**
     * Get user information by reference
     *
     * @param string $user_reference User reference
     * @return array|false User data on success, false on failure
     */
    public static function get_user($user_reference)
    {

        $date = new \DateTime();
        $isoTime = Util::formatTimeToYYYYMMDDTHHMMSSZ($date);

        // Prepare headers for signature
        $headersSign = array(
            \X_OP_DATE_HEADER => $isoTime,
            \X_OP_EXPIRES_HEADER => '3600',
        );

        // Prepare URI
        $uri = URL_PREFIX_PC . '/partners/' . PARTNER_PC . '/users/' . $user_reference;
        $queryParameter = array();

        // Create authorization signature
        $auth = new Authorization(
            \PARTNER_PC,
            \PARTNER_SECRET_KEY_PC,
            'onepay',
            'paycollect',
            'GET',
            $uri,
            $queryParameter,
            $headersSign,
            null, // No body for GET request
            $date,
            3600
        );
        $stringAuth = $auth->sign();

        // Prepare request URL
        $requestURL = \DOMAIN_PREFIX . $uri;

        // Prepare request headers
        $headerRequest = array(
            'Accept: application/json',
            'Accept-Language: vi',
            'X-OP-Date: ' . $isoTime,
            'X-OP-Authorization: ' . $stringAuth,
            'X-OP-Expires: 3600',
        );

        // Make API call
        $response = self::call_get_request($requestURL, $headerRequest);

        if ($response === false) {
            error_log('[Delivery Order System] Failed to get user info for reference: ' . $user_reference);
            return false;
        }

        $responseData = json_decode($response, true);

        if (! $responseData || ! is_array($responseData)) {
            error_log('[Delivery Order System] Invalid user info response: ' . $response);
            return false;
        }

        return $responseData;
    }

    /**
     * Create invoice for existing user
     *
     * @param string $user_reference User reference
     * @param string $invoice_reference Invoice reference
     * @param array $invoiceBody Invoice body data
     * @return array|false Invoice data on success, false on failure
     */
    public static function create_invoice($user_reference, $invoice_reference, $invoiceBody)
    {

        $date = new \DateTime();
        $isoTime = Util::formatTimeToYYYYMMDDTHHMMSSZ($date);

        // Prepare headers for signature
        $headersSign = array(
            \X_OP_DATE_HEADER => $isoTime,
            \X_OP_EXPIRES_HEADER => '3600',
        );

        // Prepare URI
        $uri = \URL_PREFIX_PC . '/partners/' . \PARTNER_PC . '/users/' . $user_reference . '/invoices/' . $invoice_reference;
        $queryParameter = array();

        $jsonData = json_encode($invoiceBody, JSON_UNESCAPED_UNICODE);

        // Create authorization signature
        $auth = new Authorization(
            \PARTNER_PC,
            \PARTNER_SECRET_KEY_PC,
            'onepay',
            'paycollect',
            'PUT',
            $uri,
            $queryParameter,
            $headersSign,
            $jsonData,
            $date,
            3600
        );
        $stringAuth = $auth->sign();

        // Prepare request URL
        $requestURL = \DOMAIN_PREFIX . $uri;

        // Prepare request headers
        $contentLength = strlen($jsonData);
        $headerRequest = array(
            'Accept: application/json',
            'Accept-Language: vi',
            'Content-Type: application/json',
            'X-OP-Date: ' . $isoTime,
            'X-OP-Authorization: ' . $stringAuth,
            'X-OP-Expires: 3600',
            'Content-Length: ' . $contentLength,
        );

        // Make API call
        $response = self::call_put_request($requestURL, $jsonData, $headerRequest);

        if ($response === false) {
            error_log('[Delivery Order System] Failed to create invoice for user: ' . $user_reference);
            return false;
        }

        $responseData = json_decode($response, true);

        if (! $responseData || ! is_array($responseData)) {
            error_log('[Delivery Order System] Invalid invoice response: ' . $response);
            return false;
        }

        return $responseData;
    }

    /**
     * Call GET request to OnePay API
     *
     * @param string $url API URL
     * @param array $headers Request headers
     * @return string|false Response body on success, false on failure
     */
    private static function call_get_request($url, $headers)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log('[Delivery Order System] cURL error: ' . $error);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $error_message = '[Delivery Order System] PayCollect GET API returned HTTP ' . $httpCode;
            $error_message .= ' | URL: ' . $url;

            $response_preview = strlen($response) > 500 ? substr($response, 0, 500) . '...' : $response;
            $error_message .= ' | Response: ' . $response_preview;

            error_log($error_message);
            return false;
        }

        return $response;
    }

    /**
     * Call PUT request to OnePay API
     *
     * @param string $url API URL
     * @param string $content Request body
     * @param array $headers Request headers
     * @return string|false Response body on success, false on failure
     */
    private static function call_put_request($url, $content, $headers)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log('[Delivery Order System] cURL error: ' . $error);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $error_message = '[Delivery Order System] PayCollect PUT API returned HTTP ' . $httpCode;
            $error_message .= ' | URL: ' . $url;

            $response_preview = strlen($response) > 500 ? substr($response, 0, 500) . '...' : $response;
            $error_message .= ' | Response: ' . $response_preview;

            error_log($error_message);
            return false;
        }

        return $response;
    }

    /**
     * Update user state (active/inactive)
     *
     * @param string $user_reference User reference
     * @param string $state User state: 'active' or 'inactive'
     * @return array|false User data on success, false on failure
     */
    public static function update_user_state($user_reference, $state)
    {

        // Validate state
        if (!in_array($state, array('active', 'inactive'))) {
            error_log('[Delivery Order System] Invalid user state. Must be "active" or "inactive"');
            return false;
        }

        $date = new \DateTime();
        $isoTime = Util::formatTimeToYYYYMMDDTHHMMSSZ($date);

        // Prepare headers for signature
        $headersSign = array(
            \X_OP_DATE_HEADER => $isoTime,
            \X_OP_EXPIRES_HEADER => '3600',
        );

        // Prepare URI
        $uri = URL_PREFIX_PC . '/partners/' . PARTNER_PC . '/users/' . $user_reference;
        $queryParameter = array();

        // Prepare body
        $bodyData = array(
            'state' => $state,
        );
        $jsonData = json_encode($bodyData, JSON_UNESCAPED_UNICODE);

        // Create authorization signature
        $auth = new Authorization(
            \PARTNER_PC,
            \PARTNER_SECRET_KEY_PC,
            'onepay',
            'paycollect',
            'PATCH',
            $uri,
            $queryParameter,
            $headersSign,
            $jsonData,
            $date,
            3600
        );
        $stringAuth = $auth->sign();

        // Prepare request URL
        $requestURL = \DOMAIN_PREFIX . $uri;

        // Prepare request headers
        $contentLength = strlen($jsonData);
        $headerRequest = array(
            'Accept: application/json',
            'Accept-Language: vi',
            'Content-Type: application/json',
            'X-OP-Date: ' . $isoTime,
            'X-OP-Authorization: ' . $stringAuth,
            'X-OP-Expires: 3600',
            'Content-Length: ' . $contentLength,
        );

        // Make API call
        $response = self::call_patch_request($requestURL, $jsonData, $headerRequest);

        if ($response === false) {
            error_log('[Delivery Order System] Failed to update user state for: ' . $user_reference);
            return false;
        }

        $responseData = json_decode($response, true);

        if (! $responseData || ! is_array($responseData)) {
            error_log('[Delivery Order System] Invalid update user state response: ' . $response);
            return false;
        }

        return $responseData;
    }

    /**
     * Call PATCH request to OnePay API
     *
     * @param string $url API URL
     * @param string $content Request body
     * @param array $headers Request headers
     * @return string|false Response body on success, false on failure
     */
    private static function call_patch_request($url, $content, $headers)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log('[Delivery Order System] cURL error: ' . $error);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $error_message = '[Delivery Order System] PayCollect PATCH API returned HTTP ' . $httpCode;
            $error_message .= ' | URL: ' . $url;

            $response_preview = strlen($response) > 500 ? substr($response, 0, 500) . '...' : $response;
            $error_message .= ' | Response: ' . $response_preview;

            error_log($error_message);
            return false;
        }

        return $response;
    }
}
