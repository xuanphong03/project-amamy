<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handle sending emails for Delivery posts
 */
class Delivery_Order_System_Send_Mail
{
    /**
     * Register hooks
     */
    public static function init()
    {
        // Handle admin-post?action=delivery_send_mail for logged-in users
        add_action('admin_post_delivery_send_mail', array(__CLASS__, 'handle_send_mail'));
        // AJAX endpoint
        add_action('wp_ajax_delivery_order_system_send_mail', array(__CLASS__, 'handle_send_mail_ajax'));
        // Configure SMTP
        add_action('phpmailer_init', array(__CLASS__, 'configure_smtp'));
    }

    /**
     * Handle send mail request
     */
    public static function handle_send_mail()
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('You are not allowed to send this email.', 'delivery-order-system'));
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if (! $post_id || get_post_type($post_id) !== 'delivery') {
            wp_die(esc_html__('Invalid delivery post.', 'delivery-order-system'));
        }

        $result = self::send_mail($post_id);
        if (! $result['success']) {
            wp_die(esc_html($result['message']));
        }

        // Add success admin notice
        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Email sent successfully.', 'delivery-order-system') . '</p></div>';
        });

        $redirect_url = add_query_arg(
            array(
                'post'          => $post_id,
                'action'        => 'edit',
                'delivery_mail' => 'sent',
            ),
            admin_url('post.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Handle AJAX send mail
     */
    public static function handle_send_mail_ajax()
    {
        if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'delivery_order_system_send_mail')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'delivery-order-system')));
        }

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'delivery-order-system')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (! $post_id || get_post_type($post_id) !== 'delivery') {
            wp_send_json_error(array('message' => __('Invalid delivery post.', 'delivery-order-system')));
        }

        $result = self::send_mail($post_id);
        if (! $result['success']) {
            wp_send_json_error(array('message' => $result['message']));
        }

        wp_send_json_success(array('message' => $result['message']));
    }

    /**
     * Send email for a single order
     *
     * @param int $delivery_post_id
     * @param int $ma_van_don_id
     * @return bool
     */
    public static function send_single_order_email($delivery_post_id, $ma_van_don_id)
    {
        if (!function_exists('get_field')) {
            return false;
        }

        $delivery_post = get_post($delivery_post_id);
        if (!$delivery_post) {
            return false;
        }

        $ma_van_don_post = get_post($ma_van_don_id);
        if (!$ma_van_don_post || $ma_van_don_post->post_type !== 'ma_van_don') {
            return false;
        }

        // Check SMTP configuration
        $smtp_config = self::get_smtp_config();
        if (empty($smtp_config['host']) || empty($smtp_config['username']) || empty($smtp_config['password'])) {
            return false;
        }

        // Get fee data for this specific order
        $rows = get_field('delivery_manager', $delivery_post_id);
        $fee_data = null;

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (isset($row['ma_van_don']) && absint($row['ma_van_don']) === $ma_van_don_id) {
                    $fee_data = \Delivery_Order_System_Delivery_Post_Type::create_fee_data_from_row($row);
                    break;
                }
            }
        }

        if (!$fee_data) {
            return false;
        }

        // Get email (hardcoded for now)
        $email = 'leetaam.okhub@gmail.com';

        if (empty($email) || !is_email($email)) {
            return false;
        }

        // Get order details
        $ma_khach_hang = get_post_meta($ma_van_don_id, 'ma_khach_hang', true);
        $ten_nguoi_nhan = get_post_meta($ma_van_don_id, 'ten_nguoi_nhan', true);
        $ten_nguoi_gui = get_post_meta($ma_van_don_id, 'ten_nguoi_gui', true);
        $phone = get_post_meta($ma_van_don_id, 'sdt', true);
        $dia_chi_nguoi_nhan = get_post_meta($ma_van_don_id, 'dia_chi_nguoi_nhan', true);
        $tinh_thanh_nguoi_nhan = get_post_meta($ma_van_don_id, 'tinh_thanh_nguoi_nhan', true);
        $nation = get_post_meta($ma_van_don_id, 'nation', true);
        $loai_tien_te = get_post_meta($ma_van_don_id, 'loai_tien_te', true);

        // Generate PDF for this order
        if (!class_exists('Delivery_Order_System_PDF_Bill')) {
            require_once plugin_dir_path(__FILE__) . '../class-pdf-bill.php';
        }

        $pdf_path = Delivery_Order_System_PDF_Bill::generate_pdf($delivery_post_id, $ma_van_don_id, $fee_data);
        if (!$pdf_path) {
            return false;
        }

        // Build email subject (same as bulk email)
        $subject = sprintf(__('Thông báo đóng hàng hoàn tất - Mã vận đơn #%d', 'delivery-order-system'), $ma_van_don_id);

        // Build email message using same template as bulk email
        $message = self::build_email_template(array(
            'ma_van_don_id' => $ma_van_don_id,
            'ma_khach_hang' => $ma_khach_hang,
            'ten_nguoi_nhan' => $ten_nguoi_nhan,
            'ten_nguoi_gui' => $ten_nguoi_gui,
            'email' => $email,
            'phone' => $phone,
            'dia_chi_nguoi_nhan' => $dia_chi_nguoi_nhan,
            'tinh_thanh_nguoi_nhan' => $tinh_thanh_nguoi_nhan,
            'nation' => $nation,
            'loai_tien_te' => $loai_tien_te,
            'phi_van_chuyen' => $fee_data['phi_van_chuyen'],
            'phu_thu_gia_co_dong_goi' => $fee_data['phu_thu_gia_co_dong_goi'],
            'phu_thu_ruou_pin_nuoc_hoa' => $fee_data['phu_thu_ruou_pin_nuoc_hoa'],
            'phu_thu_tem_dhl_hoac_pick_up' => $fee_data['phu_thu_tem_dhl_hoac_pick_up'],
            'phu_thu_bao_hiem_hai_quan_do_vo' => $fee_data['phu_thu_bao_hiem_hai_quan_do_vo'],
            'bao_hiem_hang_hoa' => $fee_data['bao_hiem_hang_hoa'],
            'phi_quay_video_can_nang_kho' => $fee_data['phi_quay_video_can_nang_kho'],
            'phi_giao_hang_noi_dia_tai_vn' => $fee_data['phi_giao_hang_noi_dia_tai_vn'],
            'uu_dai' => $fee_data['uu_dai'],
            'so_can' => $fee_data['so_can'],
            'delivery_title' => $delivery_post->post_title,
        ));

        // Email headers (same as bulk email)
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $smtp_config['from_name'] . ' <' . $smtp_config['from_email'] . '>',
        );

        $cc_emails = self::get_cc_recipients();
        if (! empty($cc_emails)) {
            $headers[] = 'Cc: ' . implode(', ', $cc_emails);
        }

        // Attach PDF
        $attachments = array();
        if ($pdf_path && file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }

        // Send email with attachment
        error_log('Delivery Order System: Sending single email to ' . $email . ' with subject: ' . $subject);
        error_log('Delivery Order System: PDF path: ' . $pdf_path);

        $result = wp_mail($email, $subject, $message, $headers, $attachments);

        error_log('Delivery Order System: Single email send result: ' . ($result ? 'SUCCESS' : 'FAILED'));

        return $result;
    }

    /**
     * Get SMTP configuration
     *
     * @return array SMTP settings
     */
    private static function get_smtp_config()
    {
        // You can customize these values or use WordPress options
        // Option 1: Use constants (define in wp-config.php)
        // Option 2: Use WordPress options (get_option)
        // Option 3: Hardcode here (not recommended for production)

        // Get values from constants or options
        $host = '';
        $port = 587;
        $encryption = 'tls';
        $username = '';
        $password = '';
        $from_email = get_option('admin_email');
        $from_name = get_bloginfo('name');

        if (defined('DELIVERY_SMTP_HOST')) {
            $host = constant('DELIVERY_SMTP_HOST');
        } else {
            $host = get_option('delivery_smtp_host', 'smtp.gmail.com');
        }

        if (defined('DELIVERY_SMTP_PORT')) {
            $port = constant('DELIVERY_SMTP_PORT');
        } else {
            $port = get_option('delivery_smtp_port', 587);
        }

        if (defined('DELIVERY_SMTP_ENCRYPTION')) {
            $encryption = constant('DELIVERY_SMTP_ENCRYPTION');
        } else {
            $encryption = get_option('delivery_smtp_encryption', 'tls');
        }

        if (defined('DELIVERY_SMTP_USERNAME')) {
            $username = constant('DELIVERY_SMTP_USERNAME');
        } else {
            $username = get_option('delivery_smtp_username', '');
        }

        if (defined('DELIVERY_SMTP_PASSWORD')) {
            $password = constant('DELIVERY_SMTP_PASSWORD');
        } else {
            $password = get_option('delivery_smtp_password', '');
        }

        if (defined('DELIVERY_SMTP_FROM_EMAIL')) {
            $from_email = constant('DELIVERY_SMTP_FROM_EMAIL');
        } else {
            $from_email = get_option('delivery_smtp_from_email', get_option('admin_email'));
        }

        if (defined('DELIVERY_SMTP_FROM_NAME')) {
            $from_name = constant('DELIVERY_SMTP_FROM_NAME');
        } else {
            $from_name = get_option('delivery_smtp_from_name', get_bloginfo('name'));
        }

        $config = array(
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'from_email' => $from_email,
            'from_name' => $from_name,
        );

        return $config;
    }

    /**
     * Configure PHPMailer for SMTP
     *
     * @param PHPMailer $phpmailer PHPMailer instance
     */
    public static function configure_smtp($phpmailer)
    {
        $config = self::get_smtp_config();

        // Skip if SMTP is not configured
        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            return;
        }

        // Set SMTP settings
        $phpmailer->isSMTP();
        $phpmailer->Host = $config['host'];
        $phpmailer->Port = absint($config['port']);
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $config['username'];
        $phpmailer->Password = $config['password'];

        // Set encryption
        if ($config['encryption'] === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($config['encryption'] === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        }

        // Set from email and name
        $phpmailer->setFrom($config['from_email'], $config['from_name']);

        // Optional: Enable debug (set to 0, 1, 2, or 3)
        // 0 = off, 1 = client messages, 2 = client and server messages, 3 = verbose debug
        // $phpmailer->SMTPDebug = 0;
    }

    /**
     * Core mail sending logic
     *
     * @param int $post_id
     * @return array ['success'=>bool,'message'=>string]
     */
    private static function send_mail($post_id)
    {
        if (! function_exists('get_field')) {
            return array('success' => false, 'message' => __('ACF is not active.', 'delivery-order-system'));
        }

        $delivery_post = get_post($post_id);
        if (! $delivery_post) {
            return array('success' => false, 'message' => __('Delivery post not found.', 'delivery-order-system'));
        }

        // Check SMTP configuration
        $smtp_config = self::get_smtp_config();
        if (empty($smtp_config['host']) || empty($smtp_config['username']) || empty($smtp_config['password'])) {
            return array(
                'success' => false,
                'message' => __('SMTP chưa được cấu hình. Vui lòng cấu hình SMTP trong wp-config.php hoặc WordPress options.', 'delivery-order-system'),
            );
        }

        // Get ACF repeater rows
        $rows = get_field('delivery_manager', $post_id);
        if (! is_array($rows) || empty($rows)) {
            return array('success' => false, 'message' => __('No delivery items found.', 'delivery-order-system'));
        }

        $sent_count = 0;
        $failed_count = 0;
        $errors = array();

        // Send email for each ma_van_don
        foreach ($rows as $row) {
            $ma_van_don_id = isset($row['ma_van_don']) ? absint($row['ma_van_don']) : 0;
            if (! $ma_van_don_id) {
                $failed_count++;
                continue;
            }

            $ma_van_don_post = get_post($ma_van_don_id);
            if (! $ma_van_don_post || $ma_van_don_post->post_type !== 'ma_van_don') {
                $failed_count++;
                continue;
            }

            // Get email from meta
            // $email = get_post_meta($ma_van_don_id, 'user', true);
            $email = 'leetaam.okhub@gmail.com';
            if (empty($email) || ! is_email($email)) {
                $failed_count++;
                $errors[] = sprintf(__('Invalid email for ma_van_don #%d', 'delivery-order-system'), $ma_van_don_id);
                continue;
            }

            // Get ma_van_don details
            $ma_khach_hang = get_post_meta($ma_van_don_id, 'ma_khach_hang', true);
            $ten_nguoi_nhan = get_post_meta($ma_van_don_id, 'ten_nguoi_nhan', true);
            $ten_nguoi_gui = get_post_meta($ma_van_don_id, 'ten_nguoi_gui', true);
            $phone = get_post_meta($ma_van_don_id, 'sdt', true);
            $dia_chi_nguoi_nhan = get_post_meta($ma_van_don_id, 'dia_chi_nguoi_nhan', true);
            $tinh_thanh_nguoi_nhan = get_post_meta($ma_van_don_id, 'tinh_thanh_nguoi_nhan', true);
            $nation = get_post_meta($ma_van_don_id, 'nation', true);
            $loai_tien_te = get_post_meta($ma_van_don_id, 'loai_tien_te', true);

            // Prepare fee data for PDF using helper method
            $fee_data = \Delivery_Order_System_Delivery_Post_Type::create_fee_data_from_row($row);

            // Generate PDF bill
            $pdf_filepath = Delivery_Order_System_PDF_Bill::generate_pdf($post_id, $ma_van_don_id, $fee_data);

            // Build email subject
            $subject = sprintf(__('Thông báo đóng hàng hoàn tất - Mã vận đơn #%d', 'delivery-order-system'), $ma_van_don_id);

            // Build email message (HTML)
            $message = self::build_email_template(array_merge(array(
                'ma_van_don_id' => $ma_van_don_id,
                'ma_khach_hang' => $ma_khach_hang,
                'ten_nguoi_nhan' => $ten_nguoi_nhan,
                'ten_nguoi_gui' => $ten_nguoi_gui,
                'email' => $email,
                'phone' => $phone,
                'dia_chi_nguoi_nhan' => $dia_chi_nguoi_nhan,
                'tinh_thanh_nguoi_nhan' => $tinh_thanh_nguoi_nhan,
                'nation' => $nation,
                'loai_tien_te' => $loai_tien_te,
                'delivery_title' => $delivery_post->post_title,
            ), $fee_data));

            // Email headers
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $smtp_config['from_name'] . ' <' . $smtp_config['from_email'] . '>',
            );

            $cc_emails = self::get_cc_recipients();
            if (! empty($cc_emails)) {
                $headers[] = 'Cc: ' . implode(', ', $cc_emails);
            }

            // Attach PDF if generated successfully
            $attachments = array();
            if ($pdf_filepath && file_exists($pdf_filepath)) {
                $attachments[] = $pdf_filepath;
            }

            // Send email
            $sent = wp_mail($email, $subject, $message, $headers, $attachments);

            // Clean up PDF file after sending (optional - you may want to keep it)
            if ($pdf_filepath && file_exists($pdf_filepath)) {
                // Uncomment the line below if you want to delete PDF immediately after sending
                // @unlink($pdf_filepath);
            }

            // Get error details from PHPMailer if available
            $error_message = '';
            if (! $sent) {
                global $phpmailer;
                if (isset($phpmailer) && is_object($phpmailer) && ! empty($phpmailer->ErrorInfo)) {
                    $error_message = $phpmailer->ErrorInfo;
                    // Log error to debug.log
                    error_log(sprintf(
                        '[Delivery Order System] Failed to send email to %s (ma_van_don #%d): %s',
                        $email,
                        $ma_van_don_id,
                        $error_message
                    ));
                } else {
                    // Log generic error if PHPMailer error not available
                    error_log(sprintf(
                        '[Delivery Order System] Failed to send email to %s (ma_van_don #%d): wp_mail returned false',
                        $email,
                        $ma_van_don_id
                    ));
                }
            }

            if ($sent) {
                $sent_count++;
            } else {
                $failed_count++;
                $error_text = sprintf(__('Failed to send email to %s (ma_van_don #%d)', 'delivery-order-system'), $email, $ma_van_don_id);
                if (! empty($error_message)) {
                    $error_text .= ': ' . esc_html($error_message);
                }
                $errors[] = $error_text;
            }
        }

        // Build result message
        if ($sent_count > 0 && $failed_count === 0) {
            $message = sprintf(_n('Email sent successfully to %d recipient.', 'Emails sent successfully to %d recipients.', $sent_count, 'delivery-order-system'), $sent_count);
            return array('success' => true, 'message' => $message);
        } elseif ($sent_count > 0 && $failed_count > 0) {
            $message = sprintf(__('Sent %d email(s) successfully, %d failed.', 'delivery-order-system'), $sent_count, $failed_count);
            if (! empty($errors)) {
                $message .= ' ' . implode(', ', array_slice($errors, 0, 3));
            }
            return array('success' => true, 'message' => $message);
        } else {
            $message = __('Failed to send all emails.', 'delivery-order-system');
            if (! empty($errors)) {
                $message .= ' ' . implode(', ', array_slice($errors, 0, 3));
            }
            return array('success' => false, 'message' => $message);
        }
    }

    /**
     * Build email template
     *
     * @param array $data
     * @return string HTML email content
     */
    private static function build_email_template($data)
    {
        // Get customer name from sender or receiver
        $customer_name = !empty($data['ten_nguoi_gui']) ? $data['ten_nguoi_gui'] : $data['ten_nguoi_nhan'];

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0; padding: 0; }
        .content { padding: 0; }
        .highlight { font-weight: bold; color: #0073aa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <p>Kính chào</p>

            <p>Amamy xin thông báo: đơn hàng của <span class="highlight">' . esc_html($customer_name) . '</span> đã được đóng hàng hoàn tất.</p>

            <p>👉 Amamy gửi bạn hóa đơn thanh toán đính kèm email này để kiểm tra và xác nhận trước khi tiến hành thanh toán.</p>

            <p><strong>Thông tin đơn hàng:</strong></p>
            <p>Tên khách hàng: ' . esc_html($customer_name) . '</p>
            <p>Mã khách hàng: ' . esc_html($data['ma_khach_hang']) . '</p>

            <p>📌 <strong>Lưu ý:</strong></p>
            <p>Trường hợp anh/chị cần điều chỉnh thông tin hóa đơn hoặc có thắc mắc, vui lòng phản hồi email này hoặc liên hệ trực tiếp với Amamy.</p>

            <p>Amamy chân thành cảm ơn anh/chị đã tin tưởng sử dụng dịch vụ.</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Retrieve CC recipients defined in options/constants.
     *
     * @return array
     */
    private static function get_cc_recipients()
    {
        $cc_value = '';

        if (defined('DELIVERY_ORDER_SYSTEM_CC_EMAILS')) {
            $cc_value = constant('DELIVERY_ORDER_SYSTEM_CC_EMAILS');
        } else {
            $cc_value = get_option('delivery_order_system_cc_emails', '');
        }

        if (empty($cc_value)) {
            return array();
        }

        $emails = array_map('trim', explode(',', $cc_value));
        $valid = array();
        foreach ($emails as $email) {
            if (is_email($email)) {
                $valid[] = sanitize_email($email);
            }
        }

        return $valid;
    }
}

Delivery_Order_System_Send_Mail::init();
