<?php

namespace OkhubJwtAuth\Services;

/**
 * Email service for authentication operations
 */
class EmailService
{
    /**
     * Send password reset email
     */
    public function sendResetPasswordEmail($email, $resetToken)
    {
        // Check if password reset emails are enabled
        if (!\get_option('okhub_jwt_enable_password_reset_email', true)) {
            return false;
        }

        $resetUrl = \add_query_arg([
            'action' => 'reset_password',
            'token' => $resetToken
        ], \home_url('/wp-login.php'));

        $subject = sprintf('[%s] Đặt lại mật khẩu', \get_bloginfo('name'));

        // Apply filter to allow subject customization
        $subject = \apply_filters('okhub_jwt_reset_password_email_subject', $subject, [
            'email' => $email,
            'reset_url' => $resetToken,
            'site_name' => \get_bloginfo('name')
        ]);

        $message = $this->getResetPasswordEmailTemplate($resetUrl);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Apply filter to allow headers customization
        $headers = \apply_filters('okhub_jwt_reset_password_email_headers', $headers, [
            'email' => $email,
            'reset_url' => $resetToken
        ]);

        // Fire action hook before sending email
        \do_action('okhub_jwt_before_reset_password_email', $email, $resetToken, $subject, $message, $headers);

        $result = \wp_mail($email, $subject, $message, $headers);

        // Fire action hook after sending email
        \do_action('okhub_jwt_reset_password_email_sent', $email, $resetToken, $result);

        return $result;
    }

    /**
     * Send OTP email for password reset
     */
    public function sendOtpEmail($email, $otpCode, $type = 'password_reset')
    {
        // Check if emails are enabled
        if (!\get_option('okhub_jwt_enable_password_reset_email', true)) {
            return false;
        }

        // Set subject based on type
        if ($type === 'registration') {
            $subject = sprintf('[%s] Mã OTP xác thực đăng ký', \get_bloginfo('name'));
        } else {
            $subject = sprintf('[%s] Mã OTP đặt lại mật khẩu', \get_bloginfo('name'));
        }

        // Apply filter to allow subject customization
        $subject = \apply_filters('okhub_jwt_otp_email_subject', $subject, [
            'email' => $email,
            'otp_code' => $otpCode,
            'type' => $type,
            'site_name' => \get_bloginfo('name')
        ]);

        $message = $this->getOtpEmailTemplate($otpCode, $type);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Apply filter to allow headers customization
        $headers = \apply_filters('okhub_jwt_otp_email_headers', $headers, [
            'email' => $email,
            'otp_code' => $otpCode
        ]);

        // Fire action hook before sending email
        \do_action('okhub_jwt_before_otp_email', $email, $otpCode, $subject, $message, $headers);

        $result = \wp_mail($email, $subject, $message, $headers);

        // Fire action hook after sending email
        \do_action('okhub_jwt_otp_email_sent', $email, $otpCode, $result);

        return $result;
    }

    /**
     * Send password changed notification email
     */
    public function sendPasswordChangedEmail($email)
    {
        // Check if password changed emails are enabled
        if (!\get_option('okhub_jwt_enable_password_changed_email', true)) {
            return false;
        }

        $subject = sprintf('[%s] Mật khẩu đã được thay đổi', \get_bloginfo('name'));

        // Apply filter to allow subject customization
        $subject = \apply_filters('okhub_jwt_password_changed_email_subject', $subject, [
            'email' => $email,
            'site_name' => \get_bloginfo('name')
        ]);

        $message = $this->getPasswordChangedEmailTemplate();

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Apply filter to allow headers customization
        $headers = \apply_filters('okhub_jwt_password_changed_email_headers', $headers, [
            'email' => $email
        ]);

        // Fire action hook before sending email
        \do_action('okhub_jwt_before_password_changed_email', $email, $subject, $message, $headers);

        $result = \wp_mail($email, $subject, $message, $headers);

        // Fire action hook after sending email
        \do_action('okhub_jwt_password_changed_email_sent', $email, $result);

        return $result;
    }

    /**
     * Send welcome email for new users
     */
    public function sendWelcomeEmail($email, $username)
    {
        // Check if welcome emails are enabled
        if (!\get_option('okhub_jwt_enable_welcome_email', true)) {
            return false;
        }

        $subject = sprintf('[%s] Chào mừng bạn đến với %s', \get_bloginfo('name'), \get_bloginfo('name'));

        // Apply filter to allow subject customization
        $subject = \apply_filters('okhub_jwt_welcome_email_subject', $subject, [
            'email' => $email,
            'username' => $username,
            'site_name' => \get_bloginfo('name')
        ]);

        $message = $this->getWelcomeEmailTemplate($username);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Apply filter to allow headers customization
        $headers = \apply_filters('okhub_jwt_welcome_email_headers', $headers, [
            'email' => $email,
            'username' => $username
        ]);

        // Fire action hook before sending email
        \do_action('okhub_jwt_before_welcome_email', $email, $username, $subject, $message, $headers);

        $result = \wp_mail($email, $subject, $message, $headers);

        // Fire action hook after sending email
        \do_action('okhub_jwt_welcome_email_sent', $email, $username, $result);

        return $result;
    }

    /**
     * Get password reset email template
     */
    private function getResetPasswordEmailTemplate($resetUrl)
    {
        $siteName = \get_bloginfo('name');
        $siteUrl = \get_bloginfo('url');

        $template = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Đặt lại mật khẩu</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #f8f9fa; padding: 30px; border-radius: 10px;'>
                <h2 style='color: #007cba; margin-bottom: 20px;'>Đặt lại mật khẩu</h2>
                
                <p>Xin chào,</p>
                
                <p>Bạn đã yêu cầu đặt lại mật khẩu cho tài khoản tại <strong>{$siteName}</strong>.</p>
                
                <p>Để đặt lại mật khẩu, vui lòng nhấp vào nút bên dưới:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetUrl}' style='background-color: #007cba; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Đặt lại mật khẩu</a>
                </div>
                
                <p>Hoặc copy link này vào trình duyệt:</p>
                <p style='word-break: break-all; background-color: #e9ecef; padding: 10px; border-radius: 5px;'>{$resetUrl}</p>
                
                <p><strong>Lưu ý:</strong> Link này sẽ hết hạn sau 1 giờ. Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.</p>
                
                <p>Trân trọng,<br><strong>{$siteName}</strong></p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #dee2e6;'>
                <p style='font-size: 12px; color: #6c757d;'>Email này được gửi từ {$siteUrl}</p>
            </div>
        </body>
        </html>";

        // Apply filter to allow template customization
        return \apply_filters('okhub_jwt_reset_password_email_template', $template, [
            'reset_url' => $resetUrl,
            'site_name' => $siteName,
            'site_url' => $siteUrl
        ]);
    }

    /**
     * Get OTP email template
     */
    private function getOtpEmailTemplate($otpCode, $type = 'password_reset')
    {
        $siteName = \get_bloginfo('name');
        $siteUrl = \get_bloginfo('url');
        $expireTime = \get_option('okhub_jwt_otp_expire', 300); // 5 minutes default
        $expireMinutes = round($expireTime / 60);

        // Set title and header based on type
        if ($type === 'registration') {
            $title = 'Mã OTP xác thực đăng ký';
            $header = '🎉 Mã OTP xác thực đăng ký';
            $description = 'Bạn đã đăng ký tài khoản thành công. Vui lòng sử dụng mã OTP bên dưới để xác thực email và kích hoạt tài khoản.';
        } else {
            $title = 'Mã OTP đặt lại mật khẩu';
            $header = '🔐 Mã OTP đặt lại mật khẩu';
            $description = 'Bạn đã yêu cầu đặt lại mật khẩu. Vui lòng sử dụng mã OTP bên dưới để xác thực và đặt lại mật khẩu mới.';
        }

        $template = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #f8f9fa; padding: 30px; border-radius: 10px;'>
                <h2 style='color: #dc3545; margin-bottom: 20px;'>{$header}</h2>
                
                <p>Xin chào,</p>
                
                <p>{$description}</p>
                
                <p>Mã OTP của bạn là:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <div style='background-color: #fff; border: 2px solid #dc3545; border-radius: 10px; padding: 20px; display: inline-block;'>
                        <h1 style='color: #dc3545; margin: 0; font-size: 36px; letter-spacing: 5px; font-family: monospace;'>{$otpCode}</h1>
                    </div>
                </div>
                
                <p><strong>Lưu ý quan trọng:</strong></p>
                <ul style='color: #dc3545;'>
                    <li>Mã OTP này chỉ có hiệu lực trong <strong>{$expireMinutes} phút</strong></li>
                    <li>Mã OTP chỉ có thể sử dụng <strong>1 lần</strong></li>
                    <li>Bạn có tối đa <strong>3 lần</strong> nhập sai trước khi mã bị vô hiệu</li>
                    <li>Không chia sẻ mã OTP này với bất kỳ ai</li>
                </ul>
                
                <p>Nếu bạn không thực hiện hành động này, vui lòng bỏ qua email này và kiểm tra bảo mật tài khoản của bạn.</p>
                
                <p>Trân trọng,<br><strong>{$siteName}</strong></p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #dee2e6;'>
                <p style='font-size: 12px; color: #6c757d;'>Email này được gửi từ {$siteUrl}</p>
            </div>
        </body>
        </html>";

        // Apply filter to allow template customization
        return \apply_filters('okhub_jwt_otp_email_template', $template, [
            'otp_code' => $otpCode,
            'type' => $type,
            'site_name' => $siteName,
            'site_url' => $siteUrl,
            'expire_time' => $expireTime
        ]);
    }

    /**
     * Get password changed email template
     */
    private function getPasswordChangedEmailTemplate()
    {
        $siteName = \get_bloginfo('name');
        $siteUrl = \get_bloginfo('url');
        $loginUrl = \wp_login_url();

        $template = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Mật khẩu đã được thay đổi</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #f8f9fa; padding: 30px; border-radius: 10px;'>
                <h2 style='color: #28a745; margin-bottom: 20px;'>Mật khẩu đã được thay đổi</h2>
                
                <p>Xin chào,</p>
                
                <p>Mật khẩu cho tài khoản của bạn tại <strong>{$siteName}</strong> đã được thay đổi thành công.</p>
                
                <p>Nếu bạn đã thực hiện thay đổi này, bạn có thể bỏ qua email này.</p>
                
                <p>Nếu bạn không thực hiện thay đổi này, vui lòng liên hệ ngay với chúng tôi để được hỗ trợ.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$loginUrl}' style='background-color: #007cba; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Đăng nhập</a>
                </div>
                
                <p>Trân trọng,<br><strong>{$siteName}</strong></p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #dee2e6;'>
                <p style='font-size: 12px; color: #6c757d;'>Email này được gửi từ {$siteUrl}</p>
            </div>
        </body>
        </html>";

        // Apply filter to allow template customization
        return \apply_filters('okhub_jwt_password_changed_email_template', $template, [
            'site_name' => $siteName,
            'site_url' => $siteUrl,
            'login_url' => $loginUrl
        ]);
    }

    /**
     * Get welcome email template
     */
    private function getWelcomeEmailTemplate($username)
    {
        $siteName = \get_bloginfo('name');
        $siteUrl = \get_bloginfo('url');
        $loginUrl = \wp_login_url();

        $template = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Chào mừng bạn đến với {$siteName}</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #f8f9fa; padding: 30px; border-radius: 10px;'>
                <h2 style='color: #007cba; margin-bottom: 20px;'>Chào mừng bạn đến với {$siteName}!</h2>
                
                <p>Xin chào <strong>{$username}</strong>,</p>
                
                <p>Cảm ơn bạn đã đăng ký tài khoản tại <strong>{$siteName}</strong>. Tài khoản của bạn đã được tạo thành công!</p>
                
                <p>Bây giờ bạn có thể đăng nhập và sử dụng tất cả các tính năng của website.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$loginUrl}' style='background-color: #007cba; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Đăng nhập ngay</a>
                </div>
                
                <p>Nếu bạn có bất kỳ câu hỏi nào, đừng ngần ngại liên hệ với chúng tôi.</p>
                
                <p>Trân trọng,<br><strong>{$siteName}</strong></p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #dee2e6;'>
                <p style='font-size: 12px; color: #6c757d;'>Email này được gửi từ {$siteUrl}</p>
            </div>
        </body>
        </html>";

        // Apply filter to allow template customization
        return \apply_filters('okhub_jwt_welcome_email_template', $template, [
            'username' => $username,
            'site_name' => $siteName,
            'site_url' => $siteUrl,
            'login_url' => $loginUrl
        ]);
    }

    /**
     * Check if a specific email type is enabled
     */
    public function isEmailEnabled($emailType)
    {
        switch ($emailType) {
            case 'welcome':
                return \get_option('okhub_jwt_enable_welcome_email', true);
            case 'password_changed':
                return \get_option('okhub_jwt_enable_password_changed_email', true);
            case 'password_reset':
                return \get_option('okhub_jwt_enable_password_reset_email', true);
            default:
                return false;
        }
    }

    /**
     * Get all email settings status
     */
    public function getEmailSettings()
    {
        return [
            'welcome' => \get_option('okhub_jwt_enable_welcome_email', true),
            'password_changed' => \get_option('okhub_jwt_enable_password_changed_email', true),
            'password_reset' => \get_option('okhub_jwt_enable_password_reset_email', true)
        ];
    }
}
