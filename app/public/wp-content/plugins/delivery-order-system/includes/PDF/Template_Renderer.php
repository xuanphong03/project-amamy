<?php

namespace DeliveryOrderSystem\PDF;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Render PDF template HTML
 */
class Template_Renderer
{
    /**
     * Build PDF content HTML from template
     *
     * @param array $data Data for template
     * @return string HTML content
     */
    public static function render($data)
    {
        $fee_data = $data['fee_data'];
        // Use so_can from ACF row instead of can_nang from post meta
        $can_nang = isset($fee_data['so_can']) ? floatval($fee_data['so_can']) : (isset($data['can_nang']) ? floatval($data['can_nang']) : 0);
        $exchange_rate = isset($data['exchange_rate']) ? floatval($data['exchange_rate']) : 31446;
        $ma_van_don_id = isset($data['ma_van_don_id']) ? $data['ma_van_don_id'] : 0;

        // Build service details
        $service_details = self::build_service_details($fee_data, $can_nang, $data['loai_tien_te'], $ma_van_don_id);

        // Load CSS file
        $css_file = DELIVERY_ORDER_SYSTEM_PATH . 'includes/templates/pdf-bill.css';
        $css_content = '';
        if (file_exists($css_file)) {
            $css_content = file_get_contents($css_file);
        }

        // Prepare template variables
        $template_vars = array(
            'company_name' => $data['company_name'],
            'ten_nguoi_nhan' => $data['ten_nguoi_nhan'],
            'full_address' => $data['full_address'],
            'tinh_thanh_nguoi_nhan' => $data['tinh_thanh_nguoi_nhan'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'ma_khach_hang' => isset($data['ma_khach_hang']) ? $data['ma_khach_hang'] : '',
            'service_details' => $service_details,
            'total_eur' => $data['total_eur'],
            'exchange_rate' => $exchange_rate,
            'total_vnd' => $data['total_vnd'],
            'loai_tien_te' => $data['loai_tien_te'],
            'bank_account' => isset($data['bank_account']) ? $data['bank_account'] : '',
            'bank_account_name' => isset($data['bank_account_name']) ? $data['bank_account_name'] : '',
            'bank_name' => isset($data['bank_name']) ? $data['bank_name'] : '',
            'qr_image_base64' => isset($data['qr_image_base64']) ? $data['qr_image_base64'] : '',
        );

        // Load template file
        $template_file = DELIVERY_ORDER_SYSTEM_PATH . 'includes/templates/pdf-bill-template.php';
        if (! file_exists($template_file)) {
            error_log('[Delivery Order System] PDF template file not found: ' . $template_file);
            return false;
        }

        // Extract variables for template
        extract($template_vars);

        // Start output buffering
        ob_start();
        include $template_file;
        $template_output = ob_get_clean();

        // Combine CSS and template
        $html = '<style>' . $css_content . '</style>' . $template_output;

        return $html;
    }

    /**
     * Build service details array
     *
     * @param array $fee_data
     * @param float $can_nang
     * @param string $loai_tien_te
     * @param int $ma_van_don_id
     * @return array
     */
    public static function build_service_details($fee_data, $can_nang, $loai_tien_te, $ma_van_don_id)
    {
        $service_details = array();
        $stt_counter = 1;
        $so_kien = isset($fee_data['so_kien']) && $fee_data['so_kien'] !== '' ? intval($fee_data['so_kien']) : 1;

        // Phí vận chuyển
        $service_details[] = array(
            'stt' => $stt_counter++,
            'label' => __('Phí vận chuyển', 'delivery-order-system'),
            'weight' => $can_nang > 0 ? number_format($can_nang, 2, ',', '.') . ' KG' : '',
            'unit_price' => $can_nang > 0 && !empty($fee_data['phi_van_chuyen']) ? number_format(floatval($fee_data['phi_van_chuyen']) / $can_nang, 2, ',', '.') . ' ' . $loai_tien_te : '',
            'amount' => !empty($fee_data['phi_van_chuyen']) ? number_format(floatval($fee_data['phi_van_chuyen']), 2, ',', '.') . ' ' . $loai_tien_te : '',
        );

        // Phi giao hang noi dia - Always show
        {
            $domestic_label = '';
            if ($ma_van_don_id > 0) {
                $chieu_van_don_id = get_field('chieu_van_don', $ma_van_don_id);
                if ($chieu_van_don_id) {
                    $domestic_delivery = get_field('domestic_delivery', $chieu_van_don_id);
                    if ($domestic_delivery && isset($domestic_delivery['paid_delivery'])) {
                        $paid_delivery = $domestic_delivery['paid_delivery'];
                        $price = isset($paid_delivery['price']) ? floatval($paid_delivery['price']) : 0;
                        $currency = isset($paid_delivery['currency']) ? $paid_delivery['currency'] : 'EUR';
                        $unit_type = isset($paid_delivery['unit_type']) ? $paid_delivery['unit_type'] : 'kg';
                        if ($price > 0) {
                            $domestic_label = ' (' . number_format($price, 2, ',', '.') . ' ' . $currency . '/' . $unit_type . ')';
                        }
                    }
                }
            }
            $service_details[] = array(
                'stt' => $stt_counter++,
                'label' => __('Phí giao hàng nội địa', 'delivery-order-system') . $domestic_label,
                'weight' => $can_nang > 0 ? number_format($can_nang, 2, ',', '.') . ' KG' : '',
                'unit_price' => $can_nang > 0 ? number_format(floatval($fee_data['phi_giao_hang_noi_dia_tai_vn']) / $can_nang, 2, ',', '.') . ' ' . $loai_tien_te : '',
                'amount' => number_format(floatval($fee_data['phi_giao_hang_noi_dia_tai_vn']), 2, ',', '.') . ' ' . $loai_tien_te,
            );
        }

        // Bao hiem sua - Always show
        {
            $bao_hiem_sua_label = '';
            if ($ma_van_don_id > 0) {
                $service_label = get_field('loai_thoi_gian_giao', $ma_van_don_id);
                if ($service_label) {
                    $bao_hiem_sua_label = ' (' . $service_label . ')';
                }
            }
            $bao_hiem_sua_total = floatval($fee_data['bao_hiem_sua']);
            $bao_hiem_sua_unit_price = $so_kien > 0 ? $bao_hiem_sua_total / $so_kien : $bao_hiem_sua_total;
            $service_details[] = array(
                'stt' => $stt_counter++,
                'label' => __('Bảo hiểm sữa', 'delivery-order-system') . $bao_hiem_sua_label,
                'weight' => $so_kien > 0 ? $so_kien . ' kiện' : '1 kiện',
                'unit_price' => number_format($bao_hiem_sua_unit_price, 2, ',', '.') . ' ' . $loai_tien_te,
                'amount' => number_format($bao_hiem_sua_total, 2, ',', '.') . ' ' . $loai_tien_te,
            );
        }

        // Minh bach can nang - Always show
        {
            $minh_bach_can_nang_label = '';
            if ($ma_van_don_id > 0) {
                $service_label = get_field('minh_bach_can_nang', $ma_van_don_id);
                if ($service_label) {
                    $minh_bach_can_nang_label = ' (' . $service_label . ')';
                }
            }
            $minh_bach_can_nang_total = floatval($fee_data['minh_bach_can_nang']);
            $minh_bach_can_nang_unit_price = $so_kien > 0 ? $minh_bach_can_nang_total / $so_kien : $minh_bach_can_nang_total;
            $service_details[] = array(
                'stt' => $stt_counter++,
                'label' => __('Minh bạch cân nặng', 'delivery-order-system') . $minh_bach_can_nang_label,
                'weight' => $so_kien > 0 ? $so_kien . ' kiện' : '1 kiện',
                'unit_price' => number_format($minh_bach_can_nang_unit_price, 2, ',', '.') . ' ' . $loai_tien_te,
                'amount' => number_format($minh_bach_can_nang_total, 2, ',', '.') . ' ' . $loai_tien_te,
            );
        }

        // Bao hiem den bu - Always show
        {
            $bao_hiem_den_bu_label = '';
            if ($ma_van_don_id > 0) {
                $service_label = get_field('loai_bao_hiem', $ma_van_don_id);
                if ($service_label) {
                    $bao_hiem_den_bu_label = ' (' . $service_label . ')';
                }
            }
            $bao_hiem_den_bu_total = floatval($fee_data['bao_hiem_den_bu']);
            $bao_hiem_den_bu_unit_price = $so_kien > 0 ? $bao_hiem_den_bu_total / $so_kien : $bao_hiem_den_bu_total;
            $service_details[] = array(
                'stt' => $stt_counter++,
                'label' => __('Bảo hiểm đền bù', 'delivery-order-system') . $bao_hiem_den_bu_label,
                'weight' => $so_kien > 0 ? $so_kien . ' kiện' : '1 kiện',
                'unit_price' => number_format($bao_hiem_den_bu_unit_price, 2, ',', '.') . ' ' . $loai_tien_te,
                'amount' => number_format($bao_hiem_den_bu_total, 2, ',', '.') . ' ' . $loai_tien_te,
            );
        }

        // Loai dong goi - Always show
        {
            $loai_dong_goi_label = '';
            if ($ma_van_don_id > 0) {
                $service_label = get_field('loai_dong_goi', $ma_van_don_id);
                if ($service_label) {
                    $loai_dong_goi_label = ' (' . $service_label . ')';
                }
            }
            $loai_dong_goi_total = floatval($fee_data['loai_dong_goi']);
            $loai_dong_goi_unit_price = $so_kien > 0 ? $loai_dong_goi_total / $so_kien : $loai_dong_goi_total;
            $service_details[] = array(
                'stt' => $stt_counter++,
                'label' => __('Loại đóng gói', 'delivery-order-system') . $loai_dong_goi_label,
                'weight' => $so_kien > 0 ? $so_kien . ' kiện' : '1 kiện',
                'unit_price' => number_format($loai_dong_goi_unit_price, 2, ',', '.') . ' ' . $loai_tien_te,
                'amount' => number_format($loai_dong_goi_total, 2, ',', '.') . ' ' . $loai_tien_te,
            );
        }

        // Phụ thu rượu, pin, nước hoa
        if (!empty($fee_data['phu_thu_ruou_pin_nuoc_hoa'])) {
            $service_details[] = array(
                'stt' => $stt_counter++,
                'label' => __('Phụ thu rượu, pin, nước hoa, laptop, hàng điện tử, pin,...', 'delivery-order-system'),
                'weight' => '',
                'unit_price' => '',
                'amount' => number_format(floatval($fee_data['phu_thu_ruou_pin_nuoc_hoa']), 2, ',', '.') . ' ' . $loai_tien_te,
            );
        }

        // Phu thu Tem DHL hoac Pick Up
        if (!empty($fee_data['phu_thu_tem_dhl_hoac_pick_up'])) {
            $service_details[] = array(
                'stt' => $stt_counter++,
                'label' => __('Phụ thu Tem DHL hoặc Pick Up', 'delivery-order-system'),
                'weight' => '',
                'unit_price' => '',
                'amount' => number_format(floatval($fee_data['phu_thu_tem_dhl_hoac_pick_up']), 2, ',', '.') . ' ' . $loai_tien_te,
            );
        }

        // Uu dai
        if (!empty($fee_data['uu_dai'])) {
            $uu_dai_value = floatval($fee_data['uu_dai']);
            $uu_dai_display = ($uu_dai_value > 0 ? '-' : '') . number_format(abs($uu_dai_value), 2, ',', '.') . ' ' . $loai_tien_te;
            $service_details[] = array(
                'stt' => $stt_counter++,
                'label' => __('Ưu đãi', 'delivery-order-system'),
                'weight' => '',
                'unit_price' => '',
                'amount' => $uu_dai_display,
            );
        }

        // Bao hiem hang hoa
        if (!empty($fee_data['bao_hiem_hang_hoa'])) {
            $service_details[] = array(
                'stt' => $stt_counter++,
                'label' => __('Bảo hiểm hàng hoá', 'delivery-order-system'),
                'weight' => 'Standard',
                'unit_price' => '',
                'amount' => number_format(floatval($fee_data['bao_hiem_hang_hoa']), 2, ',', '.') . ' ' . $loai_tien_te,
            );
        }

        return $service_details;
    }
}
