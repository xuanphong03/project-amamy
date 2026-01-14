<?php

namespace DeliveryOrderSystem\PDF;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Generate PDF document using mPDF
 */
class Generator
{
    /**
     * Create mPDF instance and configure
     *
     * @param int $ma_van_don_id
     * @return \Mpdf\Mpdf|false mPDF instance on success, false on failure
     */
    public static function create_instance($ma_van_don_id)
    {
        // Check if mPDF is available
        if (! class_exists('\Mpdf\Mpdf')) {
            // Try to load mPDF from common locations
            $mpdf_paths = array(
                DELIVERY_ORDER_SYSTEM_PATH . 'vendor/autoload.php',
                ABSPATH . 'wp-content/plugins/mpdf/vendor/autoload.php',
                ABSPATH . 'vendor/autoload.php',
            );

            $mpdf_loaded = false;
            foreach ($mpdf_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    if (class_exists('\Mpdf\Mpdf')) {
                        $mpdf_loaded = true;
                        break;
                    }
                }
            }

            if (! $mpdf_loaded && ! class_exists('\Mpdf\Mpdf')) {
                error_log('[Delivery Order System] mPDF not available. Please install mPDF library via Composer: composer require mpdf/mpdf');
                return false;
            }
        }

        try {
            // mPDF configuration
            $config = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_header' => 0,
                'margin_footer' => 0,
                'tempDir' => sys_get_temp_dir(),
            );

            // Create mPDF instance
            $mpdf = new \Mpdf\Mpdf($config);

            // Set document information
            $mpdf->SetCreator(get_bloginfo('name'));
            $mpdf->SetAuthor(get_bloginfo('name'));
            $mpdf->SetTitle(sprintf(__('Hóa đơn vận chuyển - Mã vận đơn #%d', 'delivery-order-system'), $ma_van_don_id));
            $mpdf->SetSubject(__('Hóa đơn vận chuyển', 'delivery-order-system'));

            return $mpdf;
        } catch (\Exception $e) {
            error_log('[Delivery Order System] mPDF instance creation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add QR code image to PDF (no longer needed - QR is embedded in HTML template)
     * Kept for backward compatibility but does nothing
     *
     * @param \Mpdf\Mpdf $mpdf PDF instance
     * @param string $qr_image_base64 Base64 encoded QR image
     * @param int $delivery_post_id
     * @param int $ma_van_don_id
     */
    public static function add_qr_code($mpdf, $qr_image_base64, $delivery_post_id, $ma_van_don_id)
    {
        // QR code is now embedded directly in HTML template
        // This method is kept for backward compatibility
        return;
    }

    /**
     * Save PDF to file
     *
     * @param \Mpdf\Mpdf $mpdf PDF instance
     * @param int $delivery_post_id
     * @param int $ma_van_don_id
     * @return string|false File path on success, false on failure
     */
    public static function save($mpdf, $delivery_post_id, $ma_van_don_id)
    {
        try {
            // Generate unique filename
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/delivery-bills';
            if (! file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
            }

            $filename = sprintf('bill-%d-%d-%s.pdf', $delivery_post_id, $ma_van_don_id, time());
            $filepath = $pdf_dir . '/' . $filename;

            // Save PDF to file
            $mpdf->Output($filepath, 'F');

            return $filepath;
        } catch (\Exception $e) {
            error_log('[Delivery Order System] PDF save error: ' . $e->getMessage());
            return false;
        }
    }
}