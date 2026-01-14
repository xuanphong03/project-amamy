# Email Hooks Documentation

## Overview

The Okhub JWT Auth plugin provides comprehensive hooks for customizing email functionality. These hooks allow developers to modify email templates, subjects, headers, and hook into email events.

## Filter Hooks

### 1. Email Template Filters

#### `okhub_jwt_reset_password_email_template`

Customize the password reset email template.

**Parameters:**

-   `$template` (string): The HTML email template
-   `$data` (array): Template data including:
    -   `reset_url`: Password reset URL
    -   `site_name`: Website name
    -   `site_url`: Website URL

**Example:**

```php
add_filter('okhub_jwt_reset_password_email_template', function($template, $data) {
    // Customize the template
    $template = str_replace('Đặt lại mật khẩu', 'Reset Password', $template);
    return $template;
}, 10, 2);
```

#### `okhub_jwt_password_changed_email_template`

Customize the password changed notification email template.

**Parameters:**

-   `$template` (string): The HTML email template
-   `$data` (array): Template data including:
    -   `site_name`: Website name
    -   `site_url`: Website URL
    -   `login_url`: Login page URL

**Example:**

```php
add_filter('okhub_jwt_password_changed_email_template', function($template, $data) {
    // Add custom branding
    $template = str_replace('</body>', '<div>Custom Footer</div></body>', $template);
    return $template;
}, 10, 2);
```

#### `okhub_jwt_welcome_email_template`

Customize the welcome email template for new users.

**Parameters:**

-   `$template` (string): The HTML email template
-   `$data` (array): Template data including:
    -   `username`: New user's username
    -   `site_name`: Website name
    -   `site_url`: Website URL
    -   `login_url`: Login page URL

**Example:**

```php
add_filter('okhub_jwt_welcome_email_template', function($template, $data) {
    // Add personalized welcome message
    $welcome_msg = "Welcome to our community, {$data['username']}!";
    $template = str_replace('Xin chào', $welcome_msg, $template);
    return $template;
}, 10, 2);
```

### 2. Email Subject Filters

#### `okhub_jwt_reset_password_email_subject`

Customize the password reset email subject.

**Parameters:**

-   `$subject` (string): The email subject
-   `$data` (array): Subject data including:
    -   `email`: Recipient email
    -   `reset_url`: Password reset token
    -   `site_name`: Website name

**Example:**

```php
add_filter('okhub_jwt_reset_password_email_subject', function($subject, $data) {
    return "Password Reset Request - {$data['site_name']}";
}, 10, 2);
```

#### `okhub_jwt_password_changed_email_subject`

Customize the password changed email subject.

**Parameters:**

-   `$subject` (string): The email subject
-   `$data` (array): Subject data including:
    -   `email`: Recipient email
    -   `site_name`: Website name

**Example:**

```php
add_filter('okhub_jwt_password_changed_email_subject', function($subject, $data) {
    return "Security Alert: Password Changed - {$data['site_name']}";
}, 10, 2);
```

#### `okhub_jwt_welcome_email_subject`

Customize the welcome email subject.

**Parameters:**

-   `$subject` (string): The email subject
-   `$data` (array): Subject data including:
    -   `email`: Recipient email
    -   `username`: New user's username
    -   `site_name`: Website name

**Example:**

```php
add_filter('okhub_jwt_welcome_email_subject', function($subject, $data) {
    return "Welcome {$data['username']} to {$data['site_name']}!";
}, 10, 2);
```

### 3. Email Headers Filters

#### `okhub_jwt_reset_password_email_headers`

Customize the password reset email headers.

**Parameters:**

-   `$headers` (array): The email headers
-   `$data` (array): Header data including:
    -   `email`: Recipient email
    -   `reset_url`: Password reset token

**Example:**

```php
add_filter('okhub_jwt_reset_password_email_headers', function($headers, $data) {
    $headers[] = 'X-Priority: 1 (Highest)';
    $headers[] = 'X-MSMail-Priority: High';
    return $headers;
}, 10, 2);
```

#### `okhub_jwt_password_changed_email_headers`

Customize the password changed email headers.

**Parameters:**

-   `$headers` (array): The email headers
-   `$data` (array): Header data including:
    -   `email`: Recipient email

**Example:**

```php
add_filter('okhub_jwt_password_changed_email_headers', function($headers, $data) {
    $headers[] = 'X-Category: Security';
    return $headers;
}, 10, 2);
```

#### `okhub_jwt_welcome_email_headers`

Customize the welcome email headers.

**Parameters:**

-   `$headers` (array): The email headers
-   `$data` (array): Header data including:
    -   `email`: Recipient email
    -   `username`: New user's username

**Example:**

```php
add_filter('okhub_jwt_welcome_email_headers', function($headers, $data) {
    $headers[] = 'X-Category: Welcome';
    $headers[] = 'X-User-Type: New';
    return $headers;
}, 10, 2);
```

## Action Hooks

### 1. Before Email Hooks

#### `okhub_jwt_before_reset_password_email`

Fired before sending password reset email.

**Parameters:**

-   `$email` (string): Recipient email
-   `$resetToken` (string): Password reset token
-   `$subject` (string): Email subject
-   `$message` (string): Email message
-   `$headers` (array): Email headers

**Example:**

```php
add_action('okhub_jwt_before_reset_password_email', function($email, $resetToken, $subject, $message, $headers) {
    // Log email attempt
    error_log("Sending password reset email to: {$email}");

    // Add to analytics
    do_action('analytics_track', 'password_reset_email_sent', $email);
}, 10, 5);
```

#### `okhub_jwt_before_password_changed_email`

Fired before sending password changed email.

**Parameters:**

-   `$email` (string): Recipient email
-   `$subject` (string): Email subject
-   `$message` (string): Email message
-   `$headers` (array): Email headers

**Example:**

```php
add_action('okhub_jwt_before_password_changed_email', function($email, $subject, $message, $headers) {
    // Send SMS notification
    do_action('send_sms_notification', $email, 'Password changed');
}, 10, 4);
```

#### `okhub_jwt_before_welcome_email`

Fired before sending welcome email.

**Parameters:**

-   `$email` (string): Recipient email
-   `$username` (string): New user's username
-   `$subject` (string): Email subject
-   `$message` (string): Email message
-   `$headers` (array): Email headers

**Example:**

```php
add_action('okhub_jwt_before_welcome_email', function($email, $username, $subject, $message, $headers) {
    // Add user to mailing list
    do_action('add_to_mailing_list', $email, 'welcome_series');

    // Send welcome gift
    do_action('send_welcome_gift', $email, $username);
}, 10, 5);
```

### 2. After Email Hooks

#### `okhub_jwt_reset_password_email_sent`

Fired after sending password reset email.

**Parameters:**

-   `$email` (string): Recipient email
-   `$resetToken` (string): Password reset token
-   `$result` (bool): Email send result

**Example:**

```php
add_action('okhub_jwt_reset_password_email_sent', function($email, $resetToken, $result) {
    if ($result) {
        // Log successful send
        error_log("Password reset email sent successfully to: {$email}");

        // Update user meta
        $user = get_user_by('email', $email);
        if ($user) {
            update_user_meta($user->ID, 'last_password_reset_email', current_time('mysql'));
        }
    } else {
        // Log failed send
        error_log("Failed to send password reset email to: {$email}");
    }
}, 10, 3);
```

#### `okhub_jwt_password_changed_email_sent`

Fired after sending password changed email.

**Parameters:**

-   `$email` (string): Recipient email
-   `$result` (bool): Email send result

**Example:**

```php
add_action('okhub_jwt_password_changed_email_sent', function($email, $result) {
    if ($result) {
        // Log security event
        do_action('security_log', 'password_changed', $email);

        // Send admin notification
        do_action('notify_admin', 'password_changed', $email);
    }
}, 10, 2);
```

#### `okhub_jwt_welcome_email_sent`

Fired after sending welcome email.

**Parameters:**

-   `$email` (string): Recipient email
-   `$username` (string): New user's username
-   `$result` (bool): Email send result

**Example:**

```php
add_action('okhub_jwt_welcome_email_sent', function($email, $username, $result) {
    if ($result) {
        // Update user onboarding status
        $user = get_user_by('email', $email);
        if ($user) {
            update_user_meta($user->ID, 'welcome_email_sent', current_time('mysql'));
            update_user_meta($user->ID, 'onboarding_step', 'welcome_email_sent');
        }

        // Start onboarding sequence
        do_action('start_onboarding_sequence', $email, $username);
    }
}, 10, 3);
```

## Complete Example

Here's a complete example of how to customize all aspects of the welcome email:

```php
// Customize welcome email template
add_filter('okhub_jwt_welcome_email_template', function($template, $data) {
    // Replace Vietnamese text with English
    $template = str_replace('Xin chào', 'Hello', $template);
    $template = str_replace('Chào mừng bạn đến với', 'Welcome to', $template);
    $template = str_replace('Cảm ơn bạn đã đăng ký', 'Thank you for registering', $template);

    // Add custom CSS
    $custom_css = '<style>
        .welcome-header { background: linear-gradient(45deg, #667eea 0%, #764ba2 100%); }
        .welcome-button { background: #28a745 !important; }
    </style>';

    $template = str_replace('<head>', '<head>' . $custom_css, $template);

    return $template;
}, 10, 2);

// Customize welcome email subject
add_filter('okhub_jwt_welcome_email_subject', function($subject, $data) {
    return "🎉 Welcome {$data['username']} to {$data['site_name']}!";
}, 10, 2);

// Customize welcome email headers
add_filter('okhub_jwt_welcome_email_headers', function($headers, $data) {
    $headers[] = 'X-Category: Welcome';
    $headers[] = 'X-User-Type: New';
    $headers[] = 'X-Priority: 1';
    return $headers;
}, 10, 2);

// Hook before sending welcome email
add_action('okhub_jwt_before_welcome_email', function($email, $username, $subject, $message, $headers) {
    // Add user to welcome series
    do_action('add_to_mailing_list', $email, 'welcome_series');

    // Send welcome SMS
    do_action('send_sms', $email, "Welcome {$username}!");
}, 10, 5);

// Hook after sending welcome email
add_action('okhub_jwt_welcome_email_sent', function($email, $username, $result) {
    if ($result) {
        // Update user meta
        $user = get_user_by('email', $email);
        if ($user) {
            update_user_meta($user->ID, 'welcome_email_sent', current_time('mysql'));
        }

        // Start onboarding sequence
        wp_schedule_single_event(time() + 3600, 'send_onboarding_email_1', [$email]);
    }
}, 10, 3);
```

## Best Practices

1. **Always check return values** from action hooks to ensure proper error handling
2. **Use nonces** when modifying sensitive data
3. **Log important events** for debugging and monitoring
4. **Keep modifications lightweight** to avoid performance issues
5. **Test thoroughly** in development environment before production
6. **Document your customizations** for future maintenance

## Available Hooks Summary

| Hook Type  | Hook Name                    | Purpose                       |
| ---------- | ---------------------------- | ----------------------------- |
| **Filter** | `okhub_jwt_*_email_template` | Customize email HTML content  |
| **Filter** | `okhub_jwt_*_email_subject`  | Customize email subject lines |
| **Filter** | `okhub_jwt_*_email_headers`  | Customize email headers       |
| **Action** | `okhub_jwt_before_*_email`   | Hook before sending email     |
| **Action** | `okhub_jwt_*_email_sent`     | Hook after sending email      |

_Replace `_`with:`reset_password`, `password_changed`, or `welcome`\*
