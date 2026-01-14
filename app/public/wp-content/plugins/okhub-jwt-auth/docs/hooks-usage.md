# Okhub JWT Auth - WordPress Hooks Usage Guide

## Overview

Okhub JWT Auth plugin provides WordPress hooks and actions that allow developers to extend and customize the authentication flow using standard WordPress hook system.

## Available WordPress Hooks

### 1. User Registration Hooks

#### `okhub_jwt_pre_user_registration` (Filter)

**Triggered:** Before user creation
**Data:** User data array (username, email, password, first_name)
**Purpose:** Modify user data, add custom fields, validate data
**Return:** Modified user data array

```php
add_filter('okhub_jwt_pre_user_registration', function($userData) {
    // Add custom fields
    $userData['phone'] = $_POST['phone'] ?? '';
    $userData['company'] = $_POST['company'] ?? '';

    // Validate custom fields
    if (empty($userData['phone'])) {
        throw new Exception('Phone number is required');
    }

    return $userData;
});
```

#### `okhub_jwt_post_user_registration` (Filter)

**Triggered:** After user creation, before token generation
**Data:** WP_User object
**Purpose:** Save custom fields, assign roles, create profiles
**Return:** Modified WP_User object

```php
add_filter('okhub_jwt_post_user_registration', function($user) {
    // Save custom fields to user meta
    if (isset($_POST['phone'])) {
        update_user_meta($user->ID, 'phone', sanitize_text_field($_POST['phone']));
    }

    // Assign custom role
    $user->set_role('customer');

    return $user;
});
```

#### `okhub_jwt_user_registered` (Action)

**Triggered:** After user creation
**Data:** WP_User object
**Purpose:** Execute actions after user creation
**Return:** None

```php
add_action('okhub_jwt_user_registered', function($user) {
    // Send admin notification
    sendAdminNotification($user);

    // Create user profile
    createUserProfile($user->ID);
});
```

#### `okhub_jwt_pre_welcome_email` (Filter)

**Triggered:** Before sending welcome email
**Data:** Email data array (email, username, user)
**Purpose:** Customize email data, add activation links
**Return:** Modified email data array

```php
add_filter('okhub_jwt_pre_welcome_email', function($emailData) {
    // Add custom email data
    $emailData['activation_link'] = generateActivationLink($emailData['user']->ID);
    $emailData['custom_message'] = 'Welcome to our platform!';

    return $emailData;
});
```

#### `okhub_jwt_welcome_email_sent` (Action)

**Triggered:** After welcome email is sent
**Data:** Email data array (email, username, user)
**Purpose:** Track email metrics, execute post-email actions
**Return:** None

```php
add_action('okhub_jwt_welcome_email_sent', function($emailData) {
    // Track email metrics
    trackEmailMetrics($emailData);

    // Send follow-up notification
    sendFollowUpNotification($emailData['user']);
});
```

#### `okhub_jwt_post_registration_complete` (Filter)

**Triggered:** After registration is completely finished
**Data:** Response data array (success, message, data, token)
**Purpose:** Add custom response data, welcome bonuses
**Return:** Modified response data array

```php
add_filter('okhub_jwt_post_registration_complete', function($responseData) {
    // Add custom response data
    $responseData['welcome_bonus'] = 'Get 10% off your first order!';
    $responseData['next_steps'] = [
        'Complete your profile',
        'Verify your email',
        'Browse our products'
    ];

    return $responseData;
});
```

#### `okhub_jwt_registration_complete` (Action)

**Triggered:** After registration is completely finished
**Data:** Response data array, WP_User object
**Purpose:** Execute actions after registration completion
**Return:** None

```php
add_action('okhub_jwt_registration_complete', function($responseData, $user) {
    // Create welcome campaign
    createWelcomeCampaign($user);

    // Send onboarding emails
    sendOnboardingEmails($user);
});
```

### 2. User Login Hooks

#### `okhub_jwt_pre_session_creation` (Filter)

**Triggered:** Before creating user session
**Data:** Session data array (user_id, session_id, device_info, tokens)
**Purpose:** Modify session data, add device info, validate device
**Return:** Modified session data array

```php
add_filter('okhub_jwt_pre_session_creation', function($sessionData) {
    // Add custom device info
    $sessionData['device_info']['app_version'] = $_POST['app_version'] ?? '1.0.0';
    $sessionData['device_info']['device_id'] = $_POST['device_id'] ?? '';

    // Validate device
    if (empty($sessionData['device_info']['device_id'])) {
        throw new Exception('Device ID is required');
    }

    return $sessionData;
});
```

#### `okhub_jwt_session_created` (Action)

**Triggered:** After session creation
**Data:** Session data array
**Purpose:** Track device login, execute post-session actions
**Return:** None

```php
add_action('okhub_jwt_session_created', function($sessionData) {
    // Track device login
    trackDeviceLogin($sessionData);

    // Send device notification
    sendDeviceNotification($sessionData);
});
```

#### `okhub_jwt_post_login_success` (Filter)

**Triggered:** After successful login
**Data:** Response data array (success, message, data, token)
**Purpose:** Add user preferences, track activity, customize response
**Return:** Modified response data array

```php
add_filter('okhub_jwt_post_login_success', function($responseData) {
    // Add user preferences
    $user = wp_get_current_user();
    $responseData['user_preferences'] = [
        'theme' => get_user_meta($user->ID, 'theme', true) ?: 'light',
        'language' => get_user_meta($user->ID, 'language', true) ?: 'en'
    ];

    // Track login activity
    trackLoginActivity($user->ID);

    return $responseData;
});
```

#### `okhub_jwt_login_success` (Action)

**Triggered:** After successful login
**Data:** Response data array, WP_User object
**Purpose:** Execute actions after successful login
**Return:** None

```php
add_action('okhub_jwt_login_success', function($responseData, $user) {
    // Update last login time
    update_user_meta($user->ID, 'last_login', current_time('mysql'));

    // Send login notification
    sendLoginNotification($user);

    // Track login analytics
    trackLoginAnalytics($user);
});
```

## WordPress Hook Priority

WordPress hooks support priority-based execution (lower numbers = higher priority):

```php
// High priority (executes first)
add_filter('okhub_jwt_pre_user_registration', $callback, 5);

// Default priority
add_filter('okhub_jwt_pre_user_registration', $callback, 10);

// Low priority (executes last)
add_filter('okhub_jwt_pre_user_registration', $callback, 20);
```

## Error Handling

Hooks can throw exceptions to stop the process:

```php
add_filter('okhub_jwt_pre_user_registration', function($userData) {
    if (empty($_POST['phone'])) {
        throw new Exception('Phone number is required');
    }

    $userData['phone'] = $_POST['phone'];
    return $userData;
});
```

## Best Practices

### 1. Data Validation

Always validate data in hooks before processing:

```php
add_filter('okhub_jwt_pre_user_registration', function($userData) {
    // Validate required fields
    if (empty($_POST['phone'])) {
        throw new Exception('Phone number is required');
    }

    // Sanitize data
    $userData['phone'] = sanitize_text_field($_POST['phone']);

    return $userData;
});
```

### 2. Error Handling

Use try-catch blocks when calling hooks:

```php
try {
    $userData = apply_filters('okhub_jwt_pre_user_registration', $userData);
} catch (Exception $e) {
    return $this->errorResponse($e->getMessage(), 422);
}
```

### 3. Performance Considerations

Keep hooks lightweight and efficient:

```php
// Good: Simple data modification
add_filter('okhub_jwt_post_login_success', function($responseData) {
    $responseData['timestamp'] = current_time('mysql');
    return $responseData;
});

// Avoid: Heavy operations in hooks
add_filter('okhub_jwt_post_login_success', function($responseData) {
    // Don't do heavy operations here
    // $this->heavyDatabaseQuery(); // ❌ Bad
    return $responseData;
});
```

### 4. Hook Organization

Organize hooks logically in your theme or plugin:

```php
// In your theme's functions.php
add_action('init', function() {
    // Registration hooks
    add_filter('okhub_jwt_pre_user_registration', 'my_pre_registration_hook');
    add_filter('okhub_jwt_post_user_registration', 'my_post_registration_hook');

    // Login hooks
    add_filter('okhub_jwt_pre_session_creation', 'my_pre_session_hook');
    add_filter('okhub_jwt_post_login_success', 'my_post_login_hook');
});
```

## Complete Example

```php
// In your theme's functions.php or custom plugin
add_action('init', function() {
    // Hook 1: Add custom fields
    add_filter('okhub_jwt_pre_user_registration', function($userData) {
        $userData['phone'] = $_POST['phone'] ?? '';
        $userData['company'] = $_POST['company'] ?? '';
        return $userData;
    });

    // Hook 2: Save custom fields
    add_filter('okhub_jwt_post_user_registration', function($user) {
        if (isset($_POST['phone'])) {
            update_user_meta($user->ID, 'phone', sanitize_text_field($_POST['phone']));
        }
        return $user;
    });

    // Hook 3: Customize response
    add_filter('okhub_jwt_post_registration_complete', function($responseData) {
        $responseData['welcome_message'] = 'Welcome to our platform!';
        return $responseData;
    });

    // Action: Track registration
    add_action('okhub_jwt_user_registered', function($user) {
        trackUserRegistration($user);
    });
});
```

## Hook Management

### List All Hooks

```php
// WordPress automatically manages hooks
// No need for custom hook management
```

### Remove Hooks

```php
// Remove specific hook
remove_filter('okhub_jwt_pre_user_registration', $callback);

// Remove all hooks for an action
remove_all_filters('okhub_jwt_pre_user_registration');
remove_all_actions('okhub_jwt_user_registered');
```

## Troubleshooting

### Hook Not Executing

1. Check if hook is registered correctly
2. Verify hook name spelling
3. Ensure hook is registered before the event occurs
4. Check WordPress debug log for errors

### Data Not Modified

1. Check if hook returns modified data
2. Verify hook priority (lower numbers execute first)
3. Ensure no exceptions are thrown
4. Check if filter is applied correctly

### Performance Issues

1. Keep hooks lightweight
2. Avoid heavy database operations
3. Use appropriate hook priorities
4. Monitor execution time

## WordPress Integration Benefits

### 1. Standard WordPress Pattern

-   ✅ **Familiar syntax** - Uses `add_filter()` and `add_action()`
-   ✅ **WordPress ecosystem** - Compatible with all WordPress plugins
-   ✅ **Debugging tools** - WordPress debug bar and logging

### 2. Performance

-   ✅ **WordPress optimized** - Built-in performance optimizations
-   ✅ **Memory efficient** - WordPress handles hook execution
-   ✅ **Caching friendly** - Works with object caching

### 3. Developer Experience

-   ✅ **IDE support** - Autocomplete and type hints
-   ✅ **Documentation** - WordPress hook documentation
-   ✅ **Community** - Large WordPress developer community

## Support

For questions or issues with WordPress hooks, please refer to:

-   WordPress Codex: [Plugin API](https://codex.wordpress.org/Plugin_API)
-   WordPress Developer Handbook: [Hooks](https://developer.wordpress.org/plugins/hooks/)
-   Plugin support channels
