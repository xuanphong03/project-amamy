<?php

namespace DeliveryOrderSystem\PDF;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Collect data for PDF bill generation
 */
class Data_Collector
{
    /**
     * Collect all data needed for PDF bill
     *
     * @param int $delivery_post_id
     * @param int $ma_van_don_id
     * @param array $fee_data
     * @return array|false Data array on success, false on failure
     */
    public static function collect($delivery_post_id, $ma_van_don_id, $fee_data)
    {
        // Get delivery post data
        $delivery_post = get_post($delivery_post_id);
        if (! $delivery_post) {
            return false;
        }

        // Get ma_van_don data
        $ma_van_don_post = get_post($ma_van_don_id);
        if (! $ma_van_don_post) {
            return false;
        }

        // Get delivery direction for currency unit and exchange rate
        $chieu_van_don_id = get_field('chieu_van_don', $ma_van_don_id);
        $calculated_pricing_currency_unit = '';
        $exchange_rate_to_vnd = '';
        if ($chieu_van_don_id) {
            $calculated_pricing_currency_unit = get_field('calculated_pricing_currency_unit', $chieu_van_don_id);
            $exchange_rate_to_vnd = get_field('exchange_rate_to_vnd', $chieu_van_don_id);
        }

        // Get meta fields
        $data = array(
            'delivery_post' => $delivery_post,
            'ma_van_don_post' => $ma_van_don_post,
            'delivery_title' => $delivery_post->post_title,
            'ma_van_don_id' => $ma_van_don_id,
            'ma_khach_hang' => get_post_meta($ma_van_don_id, 'ma_khach_hang', true),
            'ten_nguoi_nhan' => get_post_meta($ma_van_don_id, 'ten_nguoi_nhan', true),
            'ten_nguoi_gui' => get_post_meta($ma_van_don_id, 'ten_nguoi_gui', true),
            'email' => get_post_meta($ma_van_don_id, 'user', true),
            'phone' => get_post_meta($ma_van_don_id, 'sdt', true),
            'dia_chi_nguoi_nhan' => get_post_meta($ma_van_don_id, 'dia_chi_nguoi_nhan', true),
            'tinh_thanh_nguoi_nhan' => get_post_meta($ma_van_don_id, 'tinh_thanh_nguoi_nhan', true),
            'nation' => get_post_meta($ma_van_don_id, 'nation', true),
            'loai_tien_te' => !empty($calculated_pricing_currency_unit) ? $calculated_pricing_currency_unit : get_post_meta($ma_van_don_id, 'loai_tien_te', true),
            'name_facebook' => get_post_meta($ma_van_don_id, 'name_facebook', true),
            'can_nang' => self::get_weight($ma_van_don_id),
            'fee_data' => $fee_data,
        );

        // Get company info
        $data['company_name'] = defined('DELIVERY_COMPANY_NAME') ? constant('DELIVERY_COMPANY_NAME') : 'CÔNG TY TNHH THƯƠNG MẠI VÀ DỊCH VỤ AMAMY';
        $data['bank_account'] = defined('DELIVERY_BANK_ACCOUNT') ? constant('DELIVERY_BANK_ACCOUNT') : '19071901565010';
        $data['bank_account_name'] = defined('DELIVERY_BANK_ACCOUNT_NAME') ? constant('DELIVERY_BANK_ACCOUNT_NAME') : 'Hoang Van Long';
        $data['bank_name'] = defined('DELIVERY_BANK_NAME') ? constant('DELIVERY_BANK_NAME') : 'Techcombank';
        $data['exchange_rate'] = !empty($exchange_rate_to_vnd) ? floatval($exchange_rate_to_vnd) : (defined('DELIVERY_EXCHANGE_RATE') ? constant('DELIVERY_EXCHANGE_RATE') : get_option('delivery_exchange_rate', 31446));

        // Get merchant/account owner contact info (for QR code generation)
        $data['merchant_email'] = defined('DELIVERY_ACCOUNT_OWNER_EMAIL') ? constant('DELIVERY_ACCOUNT_OWNER_EMAIL') : '';
        $data['merchant_phone'] = defined('DELIVERY_ACCOUNT_OWNER_PHONE') ? constant('DELIVERY_ACCOUNT_OWNER_PHONE') : '';
        $data['merchant_address'] = defined('DELIVERY_COMPANY_ADDRESS') ? constant('DELIVERY_COMPANY_ADDRESS') : '';

        // Build full address
        $data['full_address'] = trim(implode(', ', array_filter(array(
            $data['dia_chi_nguoi_nhan'],
            $data['tinh_thanh_nguoi_nhan'],
            $data['nation']
        ))));

        // Get delivery direction ID for calculating shipping costs
        $chieu_van_chuyen_id = get_post_meta($delivery_post_id, 'chieu_van_chuyen_id', true);

        // Recalculate phi_van_chuyen and phi_giao_hang_noi_dia_tai_vn based on so_can from ACF if available
        $so_can = isset($fee_data['so_can']) ? floatval($fee_data['so_can']) : 0;
        if ($so_can > 0) {
            // Include the delivery post type class to use its calculation methods
            if (!class_exists('Delivery_Order_System_Delivery_Post_Type')) {
                require_once plugin_dir_path(__FILE__) . '../class-delivery-post-type.php';
            }

            // Create instance without namespace reference
            $delivery_instance = new \Delivery_Order_System_Delivery_Post_Type();

            // Recalculate phi_van_chuyen if chieu_van_chuyen_id is available
            if ($chieu_van_chuyen_id > 0) {
                $calculated_phi_van_chuyen = $delivery_instance->calculate_phi_van_chuyen($so_can, $chieu_van_chuyen_id);
                if ($calculated_phi_van_chuyen > 0) {
                    $fee_data['phi_van_chuyen'] = number_format($calculated_phi_van_chuyen, 2, '.', '');
                }
            }

            // Recalculate phi_giao_hang_noi_dia_tai_vn based on ma_van_don_id
            $calculated_domestic_cost = $delivery_instance->calculate_phi_giao_hang_noi_dia_tai_vn($so_can, $ma_van_don_id);
            if ($calculated_domestic_cost > 0) {
                $fee_data['phi_giao_hang_noi_dia_tai_vn'] = number_format($calculated_domestic_cost, 2, '.', '');
            }
        }

        // Calculate totals (exclude so_can and so_kien from calculation as they are just quantity info)
        $total_eur = 0;
        foreach ($fee_data as $key => $value) {
            if (! empty($value) && is_numeric($value) && !in_array($key, ['so_can', 'so_kien'])) {
                $calculated_value = floatval($value);
                // Ưu đãi cần được trừ đi (giảm giá)
                if ($key === 'uu_dai') {
                    $total_eur -= $calculated_value;
                } else {
                    $total_eur += $calculated_value;
                }
            }
        }
        $data['total_eur'] = $total_eur;
        $data['total_vnd'] = $total_eur * $data['exchange_rate'];

        return $data;
    }

    /**
     * Get weight from post meta (try different keys)
     *
     * @param int $ma_van_don_id
     * @return string
     */
    private static function get_weight($ma_van_don_id)
    {
        $can_nang = get_post_meta($ma_van_don_id, 'can_nang', true);
        if (empty($can_nang)) {
            $can_nang = get_post_meta($ma_van_don_id, 'khoi_luong', true);
        }
        if (empty($can_nang)) {
            $can_nang = get_post_meta($ma_van_don_id, 'weight', true);
        }
        return $can_nang;
    }
}
