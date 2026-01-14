<?php

if (! defined('ABSPATH')) {
    exit;
}

use DeliveryOrderSystem\PDF\Data_Collector;
use DeliveryOrderSystem\PDF\QR_Handler;
use DeliveryOrderSystem\PDF\Template_Renderer;
use DeliveryOrderSystem\PDF\Generator;

/**
 * Generate PDF bill for delivery orders
 * Main orchestrator class - delegates to specialized modules
 */
class Delivery_Order_System_PDF_Bill
{
    /**
     * Generate PDF bill for a delivery order
     *
     * @param int $delivery_post_id Delivery post ID
     * @param int $ma_van_don_id Mã vận đơn post ID
     * @param array $fee_data Fee data from repeater row
     * @return string|false PDF file path on success, false on failure
     */
    public static function generate_pdf($delivery_post_id, $ma_van_don_id, $fee_data)
    {
        try {
            // Step 1: Collect data
            $data = Data_Collector::collect($delivery_post_id, $ma_van_don_id, $fee_data);
            if (! $data) {
                return false;
            }

            // Step 2: Get QR code from OnePay API
            $qr_result = QR_Handler::get_qr_code($data);
            $data['qr_image_base64'] = $qr_result['qr_image_base64'];
            $data['bank_account'] = $qr_result['account_number'];
            // Only override bank_name if QR_Handler returns a non-empty value
            if (!empty($qr_result['bank_name'])) {
                $data['bank_name'] = $qr_result['bank_name'];
            }
            // Otherwise keep the bank_name from Data_Collector (already set)
            $data['date'] = current_time('d/m/Y H:i');

            // Step 3: Create PDF instance
            $pdf = Generator::create_instance($ma_van_don_id);
            if (! $pdf) {
                return false;
            }

            // Step 4: Render template HTML (QR code will be injected in template)
            $html = Template_Renderer::render($data);
            if (! $html) {
                return false;
            }

            // Step 5: Add HTML content to PDF (mPDF)
            $pdf->WriteHTML($html);

            // Step 7: Save PDF to file
            $filepath = Generator::save($pdf, $delivery_post_id, $ma_van_don_id);

            return $filepath;
        } catch (\Exception $e) {
            error_log('[Delivery Order System] PDF generation error: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Clean up old PDF files (older than specified days)
     *
     * @param int $days Number of days to keep files (0 = delete all)
     * @return int Number of files deleted
     */
    public static function cleanup_old_pdfs($days = 7)
    {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/delivery-bills';

        if (! file_exists($pdf_dir)) {
            return 0;
        }

        $files = glob($pdf_dir . '/*.pdf');
        $cutoff_time = $days > 0 ? time() - ($days * DAY_IN_SECONDS) : time(); // If days = 0, delete all
        $deleted_count = 0;

        foreach ($files as $file) {
            if (file_exists($file) && filemtime($file) < $cutoff_time) {
                if (@unlink($file)) {
                    $deleted_count++;
                }
            }
        }

        return $deleted_count;
    }
}