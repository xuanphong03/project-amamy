<?php

/**
 * Example: How to use Okhub JWT Auth WordPress Hooks
 * 
 * Place this code in your theme's functions.php or custom plugin
 */

// Hook vào pre_user_registration để thêm custom fields
add_action('init', function () {
    // Hook: pre_user_registration - Thêm custom fields trước khi tạo user
    add_filter('okhub_jwt_pre_user_registration', function ($userData) {
        // Thêm custom fields
        $userData['phone'] = $_POST['phone'] ?? '';
        $userData['company'] = $_POST['company'] ?? '';
        $userData['role'] = 'customer'; // Set default role

        // Validate custom fields
        if (empty($userData['phone'])) {
            throw new Exception('Phone number is required');
        }

        return $userData;
    });

    // Hook: post_user_registration - Xử lý sau khi tạo user
    add_filter('okhub_jwt_post_user_registration', function ($user) {
        // Lưu custom fields vào user meta
        if (isset($_POST['phone'])) {
            update_user_meta($user->ID, 'phone', sanitize_text_field($_POST['phone']));
        }

        if (isset($_POST['company'])) {
            update_user_meta($user->ID, 'company', sanitize_text_field($_POST['company']));
        }

        // Gán custom role
        $user->set_role('customer');

        // Tạo customer profile
        createCustomerProfile($user->ID);

        return $user;
    });

    // Hook: pre_welcome_email - Tùy chỉnh email data
    add_filter('okhub_jwt_pre_welcome_email', function ($emailData) {
        // Thêm custom data vào email
        $emailData['custom_message'] = 'Welcome to our platform!';
        $emailData['activation_link'] = generateActivationLink($emailData['user']->ID);

        return $emailData;
    });

    // Hook: post_registration_complete - Xử lý sau khi hoàn tất đăng ký
    add_filter('okhub_jwt_post_registration_complete', function ($responseData) {
        // Thêm custom data vào response
        $responseData['welcome_bonus'] = 'Get 10% off your first order!';
        $responseData['next_steps'] = [
            'Complete your profile',
            'Verify your email',
            'Browse our products'
        ];

        return $responseData;
    });

    // Hook: pre_session_creation - Tùy chỉnh session data
    add_filter('okhub_jwt_pre_session_creation', function ($sessionData) {
        // Thêm custom device info
        $sessionData['device_info']['app_version'] = $_POST['app_version'] ?? '1.0.0';
        $sessionData['device_info']['device_id'] = $_POST['device_id'] ?? '';

        // Validate device
        if (empty($sessionData['device_info']['device_id'])) {
            throw new Exception('Device ID is required');
        }

        return $sessionData;
    });

    // Hook: post_login_success - Xử lý sau khi login thành công
    add_filter('okhub_jwt_post_login_success', function ($responseData) {
        // Thêm user preferences
        $user = wp_get_current_user();
        $responseData['user_preferences'] = [
            'theme' => get_user_meta($user->ID, 'theme', true) ?: 'light',
            'language' => get_user_meta($user->ID, 'language', true) ?: 'en',
            'notifications' => get_user_meta($user->ID, 'notifications', true) ?: true
        ];

        // Track login activity
        trackLoginActivity($user->ID);

        return $responseData;
    });
});

// Action hooks - Xử lý events
add_action('okhub_jwt_user_registered', function ($user) {
    // User đã được tạo thành công
    error_log("User {$user->user_email} registered successfully");

    // Gửi notification cho admin
    sendAdminNotification($user);
});

add_action('okhub_jwt_welcome_email_sent', function ($emailData) {
    // Welcome email đã được gửi
    error_log("Welcome email sent to {$emailData['email']}");

    // Track email metrics
    trackEmailMetrics($emailData);
});

add_action('okhub_jwt_registration_complete', function ($responseData, $user) {
    // Registration hoàn tất
    error_log("Registration completed for user {$user->ID}");

    // Tạo welcome campaign
    createWelcomeCampaign($user);
});

add_action('okhub_jwt_session_created', function ($sessionData) {
    // Session đã được tạo
    error_log("Session created for user {$sessionData['user_id']}");

    // Track device login
    trackDeviceLogin($sessionData);
});

add_action('okhub_jwt_login_success', function ($responseData, $user) {
    // Login thành công
    error_log("User {$user->user_email} logged in successfully");

    // Update last login time
    update_user_meta($user->ID, 'last_login', current_time('mysql'));

    // Send login notification
    sendLoginNotification($user);
});

/**
 * Example helper functions
 */

// Tạo customer profile
function createCustomerProfile($userId)
{
    // Tạo custom table hoặc post type cho customer profile
    $profileData = [
        'user_id' => $userId,
        'created_at' => current_time('mysql'),
        'status' => 'active'
    ];

    // Insert vào custom table hoặc tạo post
    // ... implementation
}

// Tạo activation link
function generateActivationLink($userId)
{
    $token = wp_generate_password(32, false);
    $expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours

    // Lưu activation token
    update_user_meta($userId, 'activation_token', $token);
    update_user_meta($userId, 'activation_expires', $expires);

    return home_url("/activate?token={$token}&user={$userId}");
}

// Track login activity
function trackLoginActivity($userId)
{
    $loginData = [
        'user_id' => $userId,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'login_time' => current_time('mysql')
    ];

    // Lưu vào custom table hoặc log
    // ... implementation
}

// Send admin notification
function sendAdminNotification($user)
{
    $adminEmail = get_option('admin_email');
    $subject = 'New user registration';
    $message = "New user registered: {$user->user_email}";

    wp_mail($adminEmail, $subject, $message);
}

// Track email metrics
function trackEmailMetrics($emailData)
{
    // Track email open rates, click rates, etc.
    // ... implementation
}

// Create welcome campaign
function createWelcomeCampaign($user)
{
    // Tạo welcome email sequence
    // ... implementation
}

// Track device login
function trackDeviceLogin($sessionData)
{
    // Track device information for analytics
    // ... implementation
}

// Send login notification
function sendLoginNotification($user)
{
    // Gửi notification cho user về login mới
    // ... implementation
}

/**
 * Example: Custom registration form với WordPress hooks
 */
function custom_registration_form()
{
?>
    <form method="post" action="/wp-json/okhub-jwt/v1/register">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="text" name="first_name" placeholder="First Name">

        <!-- Custom fields -->
        <input type="tel" name="phone" placeholder="Phone Number" required>
        <input type="text" name="company" placeholder="Company">
        <input type="hidden" name="app_version" value="1.0.0">
        <input type="hidden" name="device_id" value="<?php echo uniqid(); ?>">

        <button type="submit">Register</button>
    </form>
<?php
}

/**
 * Example: Hook response sẽ như thế nào
 */
/*
Response sau khi apply WordPress hooks:

{
  "success": true,
  "message": "Register successfully",
  "data": {
    "id": 123,
    "username": "john_doe",
    "email": "john@example.com",
    "first_name": "John",
    "phone": "+1234567890",
    "company": "ACME Corp"
  },
  "token": {
    "accessToken": "...",
    "refreshToken": "...",
    "expires_in": 7200,
    "refresh_expires_in": 604800
  },
  "welcome_bonus": "Get 10% off your first order!",
  "next_steps": [
    "Complete your profile",
    "Verify your email", 
    "Browse our products"
  ]
}
*/
?>