<?php

namespace DeliveryOrderSystem\PDF;

if (! defined('ABSPATH')) {
    exit;
}

use DeliveryOrderSystem\Payment\PayCollect;

/**
 * Handle QR code generation from OnePay API
 */
class QR_Handler
{
    /**
     * Get QR code from OnePay API
     *
     * @param array $data Collected data from Data_Collector
     * @return array QR code data (qr_image_base64, account_number, bank_name)
     */
    public static function get_qr_code($data)
    {
        $result = array(
            'qr_image_base64' => null,
            'account_number' => $data['bank_account'],
            'bank_name' => isset($data['bank_name']) ? $data['bank_name'] : 'Techcombank',
        );

        // Prepare user data for OnePay API - Use merchant/account owner info instead of customer info
        // Use account owner name (bank_account_name) as the user name for QR code
        // Generate unique references with the SAME timestamp to avoid duplicates and ensure consistency
        // Format: DELIVERY_{delivery_post_id}_{ma_van_don_id}_{timestamp}
        // Format: INV_{delivery_post_id}_{ma_van_don_id}_{timestamp}
        // IMPORTANT: Both user_reference and invoice_reference MUST use the same timestamp
        $timestamp = time(); // Unix timestamp for uniqueness - used for BOTH references
        
        $user_data = array(
            'reference' => 'DELIVERY_' . $data['delivery_post']->ID . '_' . $data['ma_van_don_id'] . '_' . $timestamp,
            'name' => isset($data['bank_account_name']) ? $data['bank_account_name'] : (isset($data['company_name']) ? $data['company_name'] : 'Merchant Account'),
            'email' => isset($data['merchant_email']) ? $data['merchant_email'] : '',
            'mobile_number' => isset($data['merchant_phone']) ? $data['merchant_phone'] : '',
            'address' => isset($data['merchant_address']) ? $data['merchant_address'] : '',
            'description' => sprintf(__('Delivery order #%d - Mã vận đơn #%d', 'delivery-order-system'), $data['delivery_post']->ID, $data['ma_van_don_id']),
        );

        // Prepare invoice data - using the SAME timestamp as user_reference
        $invoice_data = array(
            'reference' => 'INV_' . $data['delivery_post']->ID . '_' . $data['ma_van_don_id'] . '_' . $timestamp,
            'amount' => strval($data['total_vnd']),
            'description' => sprintf(__('Payment for delivery order #%d', 'delivery-order-system'), $data['delivery_post']->ID),
        );

        // Call OnePay API to create VA and get QR code
        $paycollect_response = PayCollect::create_va_with_qr($user_data, $invoice_data);

        if ($paycollect_response && isset($paycollect_response['success']) && $paycollect_response['success']) {
            $result['qr_image_base64'] = isset($paycollect_response['qr_image_base64']) ? $paycollect_response['qr_image_base64'] : null;
            $result['account_number'] = isset($paycollect_response['account_number']) ? $paycollect_response['account_number'] : $data['bank_account'];
            // Use bank_name from API if available, otherwise keep the one from Data_Collector
            $result['bank_name'] = isset($paycollect_response['bank_name']) && !empty($paycollect_response['bank_name']) 
                ? $paycollect_response['bank_name'] 
                : (isset($data['bank_name']) ? $data['bank_name'] : 'Techcombank');
        } else {
            error_log('[Delivery Order System] Failed to create VA and QR code from OnePay API');
            // Keep bank_name from Data_Collector if API fails
            $result['bank_name'] = isset($data['bank_name']) ? $data['bank_name'] : 'Techcombank';
        }

        return $result;
    }
}

