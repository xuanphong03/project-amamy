<?php

namespace OkhubJwtAuth\Admin;

/**
 * Admin menu and settings
 */
class AdminMenu
{
    public function __construct()
    {
        \add_action('admin_menu', [$this, 'addAdminMenu']);
        \add_action('admin_init', [$this, 'initSettings']);
        \add_action('wp_ajax_test_okhub_email', [$this, 'handleTestEmail']);
        \add_action('wp_ajax_import_okhub_email_settings', [$this, 'handleImportEmailSettings']);
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu()
    {
        \add_options_page(
            'Okhub JWT Auth Settings',
            'JWT Auth',
            'manage_options',
            'okhub-jwt-auth',
            [$this, 'adminPage']
        );
    }

    /**
     * Initialize settings
     */
    public function initSettings()
    {
        // Core JWT Settings
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_secret');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_expire');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_refresh_expire');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_enable_refresh_tokens');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_enable_username_login');

        // Registration & Email Verification
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_enable_email_verification');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_enable_welcome_email');

        // Password Reset Settings
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_enable_password_reset');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_password_reset_expire');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_enable_password_reset_email');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_enable_otp_reset');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_otp_expire');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_otp_max_attempts');

        // Email Notifications
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_enable_password_changed_email');

        // Social Login Settings
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_enable_social_login');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_google_client_id');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_google_client_secret');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_auto_verify_google_email');
        \register_setting('okhub_jwt_auth_options', 'okhub_jwt_allow_account_merge');

        // Core JWT Settings Section
        \add_settings_section(
            'okhub_jwt_auth_core',
            'Core JWT Settings',
            [$this, 'coreSectionCallback'],
            'okhub-jwt-auth'
        );

        \add_settings_field(
            'okhub_jwt_secret',
            'JWT Secret Key',
            [$this, 'secretFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_core'
        );

        \add_settings_field(
            'okhub_jwt_expire',
            'Access Token Expiry (seconds)',
            [$this, 'expireFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_core'
        );

        \add_settings_field(
            'okhub_jwt_refresh_expire',
            'Refresh Token Expiry (seconds)',
            [$this, 'refreshExpireFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_core'
        );

        \add_settings_field(
            'okhub_jwt_enable_refresh_tokens',
            'Enable Refresh Tokens',
            [$this, 'enableRefreshFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_core'
        );

        \add_settings_field(
            'okhub_jwt_enable_username_login',
            'Enable Username Login',
            [$this, 'enableUsernameLoginFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_core'
        );

        // Registration & Email Verification Section
        \add_settings_section(
            'okhub_jwt_auth_registration',
            'Registration & Email Verification',
            [$this, 'registrationSectionCallback'],
            'okhub-jwt-auth'
        );

        \add_settings_field(
            'okhub_jwt_enable_email_verification',
            'Enable Email Verification',
            [$this, 'enableEmailVerificationFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_registration'
        );

        \add_settings_field(
            'okhub_jwt_enable_welcome_email',
            'Enable Welcome Email',
            [$this, 'enableWelcomeEmailFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_registration'
        );

        // Password Reset Section
        \add_settings_section(
            'okhub_jwt_auth_password_reset',
            'Password Reset Settings',
            [$this, 'passwordResetSectionCallback'],
            'okhub-jwt-auth'
        );

        \add_settings_field(
            'okhub_jwt_enable_password_reset',
            'Enable Password Reset',
            [$this, 'enablePasswordResetFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_password_reset'
        );

        \add_settings_field(
            'okhub_jwt_password_reset_expire',
            'Password Reset Token Expiry (seconds)',
            [$this, 'passwordResetExpireFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_password_reset'
        );

        \add_settings_field(
            'okhub_jwt_enable_password_reset_email',
            'Enable URL-based Password Reset Email',
            [$this, 'enablePasswordResetEmailFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_password_reset'
        );

        \add_settings_field(
            'okhub_jwt_enable_otp_reset',
            'Enable OTP-based Password Reset',
            [$this, 'enableOtpResetFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_password_reset'
        );

        \add_settings_field(
            'okhub_jwt_otp_expire',
            'OTP Expiry (seconds)',
            [$this, 'otpExpireFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_password_reset'
        );

        \add_settings_field(
            'okhub_jwt_otp_max_attempts',
            'OTP Max Attempts',
            [$this, 'otpMaxAttemptsFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_password_reset'
        );

        // Email Notifications Section
        \add_settings_section(
            'okhub_jwt_auth_email',
            'Email Notifications',
            [$this, 'emailSectionCallback'],
            'okhub-jwt-auth'
        );

        \add_settings_field(
            'okhub_jwt_enable_password_changed_email',
            'Enable Password Changed Email',
            [$this, 'enablePasswordChangedEmailFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_email'
        );

        // Social Login settings section
        \add_settings_section(
            'okhub_jwt_auth_social',
            'Social Login Settings',
            [$this, 'socialSectionCallback'],
            'okhub-jwt-auth'
        );

        \add_settings_field(
            'okhub_jwt_enable_social_login',
            'Enable Social Login',
            [$this, 'enableSocialLoginFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_social'
        );

        \add_settings_field(
            'okhub_jwt_google_client_id',
            'Google Client ID',
            [$this, 'googleClientIdFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_social'
        );

        \add_settings_field(
            'okhub_jwt_google_client_secret',
            'Google Client Secret',
            [$this, 'googleClientSecretFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_social'
        );

        \add_settings_field(
            'okhub_jwt_auto_verify_google_email',
            'Auto Verify Google Email',
            [$this, 'autoVerifyGoogleEmailFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_social'
        );

        \add_settings_field(
            'okhub_jwt_allow_account_merge',
            'Allow Account Merge',
            [$this, 'allowAccountMergeFieldCallback'],
            'okhub-jwt-auth',
            'okhub_jwt_auth_social'
        );
    }

    /**
     * Admin page content
     */
    public function adminPage()
    {
?>
        <div class="wrap">
            <h1>Okhub JWT Authentication Settings</h1>

            <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
                <h3 style="margin-top: 0; color: #155724;">🎯 Plugin Status: PRODUCTION READY</h3>
                <p style="margin: 0; color: #155724;">
                    <strong>JWT Authentication:</strong> ✅ Active |
                    <strong>Username Login:</strong> <?php echo get_option('okhub_jwt_enable_username_login', false) ? '✅ Enabled' : '❌ Disabled'; ?> |
                    <strong>Social Login:</strong> <?php echo get_option('okhub_jwt_enable_social_login', false) ? '✅ Enabled' : '❌ Disabled'; ?> |
                    <strong>Google OAuth:</strong> <?php
                                                    $clientId = get_option('okhub_jwt_google_client_id', '');
                                                    $clientSecret = get_option('okhub_jwt_google_client_secret', '');
                                                    echo (!empty($clientId) && !empty($clientSecret)) ? '✅ Configured' : '❌ Missing Credentials';
                                                    ?> |
                    <strong>OTP Reset:</strong> <?php echo get_option('okhub_jwt_enable_otp_reset', false) ? '✅ Enabled' : '❌ Disabled'; ?> |
                    <strong>Email System:</strong> <?php
                                                    $emailCount = 0;
                                                    if (get_option('okhub_jwt_enable_welcome_email', true)) $emailCount++;
                                                    if (get_option('okhub_jwt_enable_password_changed_email', true)) $emailCount++;
                                                    if (get_option('okhub_jwt_enable_password_reset_email', true)) $emailCount++;
                                                    echo $emailCount . '/3 Active';
                                                    ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('okhub_jwt_auth_options');
                do_settings_sections('okhub-jwt-auth');
                submit_button();
                ?>
            </form>

            <div class="card" style="max-width: 900px; margin-top: 20px;">
                <h2>📧 Email Settings</h2>
                <p>Configure which email notifications to send to users:</p>
                <ul>
                    <li><strong>Welcome Email:</strong> Sent to new users after successful registration</li>
                    <li><strong>Password Changed Email:</strong> Notification when user changes password</li>
                    <li><strong>Password Reset Email:</strong> Sent when user requests password reset</li>
                </ul>
                <p><em>Note: All email settings are enabled by default. Uncheck to disable specific email types.</em></p>

                <div style="margin: 15px 0;">
                    <button type="button" id="export_email_settings" class="button button-secondary">📤 Export Email Settings</button>
                    <button type="button" id="import_email_settings" class="button button-secondary" style="margin-left: 10px;">📥 Import Email Settings</button>
                    <input type="file" id="import_file" accept=".json" style="display: none;" />
                </div>

                <script>
                    jQuery(document).ready(function($) {
                        // Export email settings
                        $('#export_email_settings').on('click', function() {
                            var settings = {
                                welcome: <?php echo get_option('okhub_jwt_enable_welcome_email', true) ? 'true' : 'false'; ?>,
                                password_changed: <?php echo get_option('okhub_jwt_enable_password_changed_email', true) ? 'true' : 'false'; ?>,
                                password_reset: <?php echo get_option('okhub_jwt_enable_password_reset_email', true) ? 'true' : 'false'; ?>
                            };

                            var dataStr = JSON.stringify(settings, null, 2);
                            var dataBlob = new Blob([dataStr], {
                                type: 'application/json'
                            });

                            var link = document.createElement('a');
                            link.href = URL.createObjectURL(dataBlob);
                            link.download = 'okhub-jwt-email-settings.json';
                            link.click();
                        });

                        // Import email settings
                        $('#import_email_settings').on('click', function() {
                            $('#import_file').click();
                        });

                        $('#import_file').on('change', function(e) {
                            var file = e.target.files[0];
                            if (file) {
                                var reader = new FileReader();
                                reader.onload = function(e) {
                                    try {
                                        var settings = JSON.parse(e.target.result);
                                        if (confirm('Import these email settings?\n\nWelcome: ' + (settings.welcome ? 'Enabled' : 'Disabled') + '\nPassword Changed: ' + (settings.password_changed ? 'Enabled' : 'Disabled') + '\nPassword Reset: ' + (settings.password_reset ? 'Enabled' : 'Disabled'))) {
                                            // Send AJAX request to update settings
                                            $.post(ajaxurl, {
                                                    action: 'import_okhub_email_settings',
                                                    settings: settings,
                                                    nonce: '<?php echo wp_create_nonce('import_okhub_email_settings'); ?>'
                                                })
                                                .done(function(response) {
                                                    if (response.success) {
                                                        alert('Email settings imported successfully!');
                                                        location.reload();
                                                    } else {
                                                        alert('Failed to import settings: ' + (response.data || 'Unknown error'));
                                                    }
                                                })
                                                .fail(function() {
                                                    alert('Failed to import settings: Network error');
                                                });
                                        }
                                    } catch (e) {
                                        alert('Invalid JSON file');
                                    }
                                };
                                reader.readAsText(file);
                            }
                        });
                    });
                </script>

                <h3>📊 Email Statistics</h3>
                <p>Current email configuration and usage:</p>

                <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ffc107;">
                    <h4 style="margin-top: 0; color: #856404;">💡 Email Templates Information:</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><strong>Welcome Email:</strong> Professional HTML template with Vietnamese language, responsive design</li>
                        <li><strong>Password Changed:</strong> Security notification with login button and site branding</li>
                        <li><strong>Password Reset:</strong> Reset link with expiration notice and security warnings</li>
                    </ul>
                    <p style="margin: 10px 0 0 0; font-size: 13px; color: #856404;">
                        <em>All templates use WordPress wp_mail() function and support HTML formatting with inline CSS.</em>
                    </p>
                </div>

                <div style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4 style="margin-top: 0; color: #0066cc;">📈 Email Features Overview:</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <strong>Welcome Email:</strong><br>
                            <span style="color: <?php echo get_option('okhub_jwt_enable_welcome_email', true) ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo get_option('okhub_jwt_enable_welcome_email', true) ? '✅ Active' : '❌ Inactive'; ?>
                            </span><br>
                            <small>Sent to new users after registration</small>
                        </div>
                        <div>
                            <strong>Password Changed:</strong><br>
                            <span style="color: <?php echo get_option('okhub_jwt_enable_password_changed_email', true) ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo get_option('okhub_jwt_enable_password_changed_email', true) ? '✅ Active' : '❌ Inactive'; ?>
                            </span><br>
                            <small>Notification when password changes</small>
                        </div>
                        <div>
                            <strong>Password Reset:</strong><br>
                            <span style="color: <?php echo get_option('okhub_jwt_enable_password_reset_email', true) ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo get_option('okhub_jwt_enable_password_reset_email', true) ? '✅ Active' : '❌ Inactive'; ?>
                            </span><br>
                            <small>Reset link when requested</small>
                        </div>
                        <div>
                            <strong>Total Active:</strong><br>
                            <span style="color: #0066cc; font-weight: bold;">
                                <?php
                                $activeCount = 0;
                                if (get_option('okhub_jwt_enable_welcome_email', true)) $activeCount++;
                                if (get_option('okhub_jwt_enable_password_changed_email', true)) $activeCount++;
                                if (get_option('okhub_jwt_enable_password_reset_email', true)) $activeCount++;
                                echo $activeCount . '/3';
                                ?>
                            </span><br>
                            <small>Email types currently enabled</small>
                        </div>
                    </div>

                    <div class="card" style="max-width: 900px; margin-top: 20px;">
                        <h2>📚 Additional Resources</h2>
                        <p>Helpful information and next steps:</p>

                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
                            <h4 style="margin-top: 0; color: #495057;">🔗 Related Documentation:</h4>
                            <ul style="margin: 0; padding-left: 20px;">
                                <li><a href="https://developer.wordpress.org/rest-api/" target="_blank">WordPress REST API Documentation</a></li>
                                <li><a href="https://wordpress.org/support/article/using-smtp/" target="_blank">WordPress SMTP Configuration</a></li>
                                <li><a href="https://wordpress.org/support/article/emails/" target="_blank">WordPress Email Troubleshooting</a></li>
                            </ul>
                        </div>

                        <div style="background: #e2e3e5; padding: 15px; border-radius: 5px; margin: 15px 0;">
                            <h4 style="margin-top: 0; color: #495057;">🚀 Next Steps:</h4>
                            <ol style="margin: 0; padding-left: 20px;">
                                <li>Configure your email settings above</li>
                                <li>Test email functionality with the test button</li>
                                <li>Integrate JWT authentication in your frontend</li>
                                <li>Monitor email delivery and logs</li>
                                <li>Customize email templates if needed</li>
                            </ol>
                        </div>

                        <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 15px 0;">
                            <h4 style="margin-top: 0; color: #0c5460;">📞 Support:</h4>
                            <p style="margin: 0; color: #0c5460;">
                                If you encounter issues with email functionality, check the troubleshooting section above or review WordPress mail configuration.
                                The plugin uses standard WordPress <code>wp_mail()</code> function for compatibility.
                            </p>
                        </div>
                    </div>
                </div>

                <h3>🧪 Test Email Settings</h3>
                <p>Test your email configuration:</p>

                <div style="background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4 style="margin-top: 0; color: #495057;">📊 Current Email Settings Status:</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><strong>Welcome Email:</strong> <?php echo get_option('okhub_jwt_enable_welcome_email', true) ? '✅ Enabled' : '❌ Disabled'; ?></li>
                        <li><strong>Password Changed Email:</strong> <?php echo get_option('okhub_jwt_enable_password_changed_email', true) ? '✅ Enabled' : '❌ Disabled'; ?></li>
                        <li><strong>Password Reset Email:</strong> <?php echo get_option('okhub_jwt_enable_password_reset_email', true) ? '✅ Enabled' : '❌ Disabled'; ?></li>
                    </ul>
                </div>

                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;">
                    <label for="test_email">Test Email Address:</label>
                    <input type="email" id="test_email" name="test_email" value="<?php echo esc_attr(get_option('admin_email')); ?>" style="margin-left: 10px; padding: 5px; border: 1px solid #ddd; border-radius: 3px; width: 250px;" />
                    <button type="button" id="test_email_btn" class="button button-secondary" style="margin-left: 10px;">Send Test Email</button>
                    <div id="test_email_result" style="margin-top: 10px; padding: 10px; border-radius: 3px; display: none;"></div>
                </div>

                <script>
                    jQuery(document).ready(function($) {
                        $('#test_email_btn').on('click', function() {
                            var email = $('#test_email').val();
                            var btn = $(this);
                            var result = $('#test_email_result');

                            if (!email) {
                                alert('Please enter a valid email address');
                                return;
                            }

                            btn.prop('disabled', true).text('Sending...');
                            result.hide();

                            $.post(ajaxurl, {
                                    action: 'test_okhub_email',
                                    email: email,
                                    nonce: '<?php echo wp_create_nonce('test_okhub_email'); ?>'
                                })
                                .done(function(response) {
                                    if (response.success) {
                                        result.html('<div style="color: green; background: #d4edda; padding: 10px; border-radius: 3px;">✓ Test email sent successfully! Check your inbox.</div>');
                                    } else {
                                        result.html('<div style="color: red; background: #f8d7da; padding: 10px; border-radius: 3px;">✗ Failed to send test email: ' + (response.data || 'Unknown error') + '</div>');
                                    }
                                })
                                .fail(function() {
                                    result.html('<div style="color: red; background: #f8d7da; padding: 10px; border-radius: 3px;">✗ Failed to send test email: Network error</div>');
                                })
                                .always(function() {
                                    result.show();
                                    btn.prop('disabled', false).text('Send Test Email');
                                });
                        });
                    });
                </script>
            </div>

            <div class="card" style="max-width: 900px; margin-top: 20px;">
                <h2>🌐 Social Login Configuration</h2>
                <p>Configure Google Social Login integration with your WordPress site:</p>

                <div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 20px 0; border-radius: 5px;">
                    <h3 style="margin-top: 0; color: #0066cc;">📋 Google OAuth Setup Guide</h3>
                    <ol style="margin: 0; padding-left: 20px;">
                        <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                        <li>Create a new project or select existing one</li>
                        <li>Enable Google+ API</li>
                        <li>Go to "Credentials" → "Create Credentials" → "OAuth 2.0 Client IDs"</li>
                        <li>Set Application type to "Web application"</li>
                        <li>Add authorized redirect URIs: <code><?php echo home_url('/wp-json/okhub-jwt/v1/auth/social/login'); ?></code></li>
                        <li>Copy Client ID and Client Secret to settings above</li>
                    </ol>
                </div>

                <div style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4 style="margin-top: 0; color: #0066cc;">📈 Social Login Features:</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <strong>Google Login:</strong><br>
                            <span style="color: <?php echo get_option('okhub_jwt_enable_social_login', false) ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo get_option('okhub_jwt_enable_social_login', false) ? '✅ Active' : '❌ Inactive'; ?>
                            </span><br>
                            <small>Enable/disable Google login</small>
                        </div>
                        <div>
                            <strong>Auto Email Verify:</strong><br>
                            <span style="color: <?php echo get_option('okhub_jwt_auto_verify_google_email', true) ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo get_option('okhub_jwt_auto_verify_google_email', true) ? '✅ Active' : '❌ Inactive'; ?>
                            </span><br>
                            <small>Auto verify Google emails</small>
                        </div>
                        <div>
                            <strong>Account Merge:</strong><br>
                            <span style="color: <?php echo get_option('okhub_jwt_allow_account_merge', true) ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo get_option('okhub_jwt_allow_account_merge', true) ? '✅ Active' : '❌ Inactive'; ?>
                            </span><br>
                            <small>Merge with local accounts</small>
                        </div>
                        <div>
                            <strong>Google Credentials:</strong><br>
                            <span style="color: <?php echo (get_option('okhub_jwt_google_client_id') && get_option('okhub_jwt_google_client_secret')) ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo (get_option('okhub_jwt_google_client_id') && get_option('okhub_jwt_google_client_secret')) ? '✅ Configured' : '❌ Missing'; ?>
                            </span><br>
                            <small>Client ID & Secret set</small>
                        </div>
                    </div>
                </div>

                <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ffc107;">
                    <h4 style="margin-top: 0; color: #856404;">💡 Social Login Scenarios:</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><strong>New User:</strong> Automatically creates WordPress account with Google info</li>
                        <li><strong>Existing Google User:</strong> Direct login with Google credentials</li>
                        <li><strong>Local User Merge:</strong> Links Google account to existing WordPress account</li>
                    </ul>
                    <p style="margin: 10px 0 0 0; font-size: 13px; color: #856404;">
                        <em>All scenarios return standard JWT tokens for seamless integration.</em>
                    </p>
                </div>
            </div>

            <div class="card" style="max-width: 900px; margin-top: 20px;">
                <h2>API Endpoints</h2>
                <p>The following REST API endpoints are available for JWT authentication:</p>

                <h3>🔐 Authentication</h3>
                <ul>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/login</strong> - User login with email/username and password</li>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/register</strong> - User registration (username, email, password,
                        first_name) - Requires OTP verification if Email Verification is enabled</li>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/register/verify</strong> - Verify OTP for registration (email, otp_code)
                        <?php if (!get_option('okhub_jwt_enable_email_verification', true)): ?>
                            <span style="color: #dc3545; font-size: 12px;">[EMAIL VERIFICATION DISABLED]</span>
                        <?php endif; ?>
                    </li>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/register/resend-otp</strong> - Resend OTP for registration (email)
                        <?php if (!get_option('okhub_jwt_enable_email_verification', true)): ?>
                            <span style="color: #dc3545; font-size: 12px;">[EMAIL VERIFICATION DISABLED]</span>
                        <?php endif; ?>
                    </li>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/refresh</strong> - Refresh access token using refresh token</li>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/logout</strong> - Logout current session</li>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/logout-all</strong> - Logout from all devices</li>
                </ul>

                <h3>👤 User Management</h3>
                <ul>
                    <li><strong>GET /wp-json/okhub-jwt/v1/users/me</strong> - Get current authenticated user information
                        <br><small style="color: #666;">Returns user data including <code>email_verified</code> field when Email Verification is enabled</small>
                    </li>
                    <li><strong>PUT /wp-json/okhub-jwt/v1/users/me/profile</strong> - Update user profile (first_name, last_name, phone,
                        gender, date_of_birth)</li>
                    <li><strong>PUT /wp-json/okhub-jwt/v1/users/me/password</strong> - Change user password (current_password,
                        new_password)</li>
                    <li><strong>GET /wp-json/okhub-jwt/v1/users/me/sessions</strong> - Get user active sessions</li>
                </ul>

                <h3>🔑 Password Reset</h3>
                <ul>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/password/forgot</strong> - Request password reset (email)
                        <?php if (!get_option('okhub_jwt_enable_password_reset_email', true)): ?>
                            <span style="color: #dc3545; font-size: 12px;">[EMAILS DISABLED]</span>
                        <?php endif; ?>
                    </li>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/password/reset</strong> - Reset password with token (token,
                        new_password)</li>
                    <li><strong>GET /wp-json/okhub-jwt/v1/auth/password/validate-token</strong> - Validate password reset token (token)
                    </li>
                </ul>

                <h3>🔐 OTP Password Reset</h3>
                <ul>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/password/otp/request</strong> - Request OTP for password reset (email)
                        <?php if (!get_option('okhub_jwt_enable_otp_reset', false)): ?>
                            <span style="color: #dc3545; font-size: 12px;">[DISABLED]</span>
                        <?php endif; ?>
                    </li>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/password/otp/reset</strong> - Reset password with OTP (email, otp_code, new_password)
                        <?php if (!get_option('okhub_jwt_enable_otp_reset', false)): ?>
                            <span style="color: #dc3545; font-size: 12px;">[DISABLED]</span>
                        <?php endif; ?>
                    </li>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/password/otp/verify</strong> - Verify OTP only (email, otp_code)
                        <?php if (!get_option('okhub_jwt_enable_otp_reset', false)): ?>
                            <span style="color: #dc3545; font-size: 12px;">[DISABLED]</span>
                        <?php endif; ?>
                    </li>
                </ul>

                <h3>🌐 Social Login</h3>
                <ul>
                    <li><strong>POST /wp-json/okhub-jwt/v1/auth/social/login</strong> - Google Login (provider, email, googleId, name, picture)
                        <?php if (!get_option('okhub_jwt_enable_social_login', false)): ?>
                            <span style="color: #dc3545; font-size: 12px;">[DISABLED]</span>
                        <?php endif; ?>
                    </li>
                </ul>

                <h3>📧 Email Notifications</h3>
                <ul>
                    <li><strong>Welcome Email</strong> - Sent after registration
                        <?php if (!get_option('okhub_jwt_enable_welcome_email', true)): ?>
                            <span style="color: #dc3545; font-size: 12px;">[DISABLED]</span>
                        <?php endif; ?>
                    </li>
                    <li><strong>Password Changed Email</strong> - Sent when password changes
                        <?php if (!get_option('okhub_jwt_enable_password_changed_email', true)): ?>
                            <span style="color: #dc3545; font-size: 12px;">[DISABLED]</span>
                        <?php endif; ?>
                    </li>
                    <li><strong>Password Reset Email</strong> - Sent when password reset requested
                        <?php if (!get_option('okhub_jwt_enable_password_reset_email', true)): ?>
                            <span style="color: #dc3545; font-size: 12px;">[DISABLED]</span>
                        <?php endif; ?>
                    </li>
                </ul>

                <div style="background: #e2e3e5; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <p style="margin: 0; font-size: 13px; color: #495057;">
                        <strong>💡 Pro Tip:</strong> Disable specific email types if you want to handle notifications through other systems or reduce email volume.
                    </p>
                </div>

                <h3>📝 Request Parameters</h3>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                    <h4>Forgot Password Request:</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
POST /wp-json/okhub-jwt/v1/auth/password/forgot
Content-Type: application/json

{
    "email": "user@example.com"
}</pre>

                    <p style="margin: 10px 0; font-style: italic; color: #666;">
                        <strong>Note:</strong> Password reset email will be sent if enabled in email settings.
                    </p>

                    <h4>Login Request:</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
POST /wp-json/okhub-jwt/v1/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password123"
}

// Or with username (if username login is enabled):
{
    "username": "username123",
    "password": "password123"
}</pre>

                    <p style="margin: 10px 0; font-style: italic; color: #666;">
                        <strong>Note:</strong> Username login can be enabled/disabled in the settings above.
                        When enabled, users can login with either email or username.
                    </p>

                    <h4>Registration Response (Email Verification Enabled):</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
{
    "success": true,
    "message": "Đăng ký thành công. Vui lòng kiểm tra email để xác thực tài khoản.",
    "data": {
        "id": 123,
        "username": "username123",
        "email": "user@example.com",
        "first_name": "John",
        "display_name": "John",
        "registered": "2025-01-19 10:00:00",
        "roles": ["subscriber"],
        "capabilities": ["read"],
        "email_verified": false,
        "requires_verification": true
    }
}</pre>

                    <h4>Registration Response (Email Verification Disabled):</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
{
    "success": true,
    "message": "Đăng ký thành công",
    "data": {
        "id": 123,
        "username": "username123",
        "email": "user@example.com",
        "first_name": "John",
        "display_name": "John",
        "registered": "2025-01-19 10:00:00",
        "roles": ["subscriber"],
        "capabilities": ["read"]
    },
    "token": {
        "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
    }
}</pre>

                    <p style="margin: 10px 0; font-style: italic; color: #666;">
                        <strong>Note:</strong> When Email Verification is enabled, users must verify their email via OTP before they can login.
                        The <code>email_verified</code> field shows the verification status. Google users automatically have <code>email_verified: true</code>.
                    </p>

                    <h4>Registration Request:</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
POST /wp-json/okhub-jwt/v1/auth/register
Content-Type: application/json

{
    "username": "username123",
    "email": "user@example.com",
    "password": "password123",
    "first_name": "John"
}</pre>

                    <h4>Profile Update Request:</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
PUT /wp-json/okhub-jwt/v1/profile
Authorization: Bearer {access_token}
Content-Type: application/json

{
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+1234567890",
    "gender": "male",
    "date_of_birth": "1990-01-01"
}</pre>

                    <h4>Change Password Request:</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
PUT /wp-json/okhub-jwt/v1/users/me/password
Authorization: Bearer {access_token}
Content-Type: application/json

{
    "current_password": "oldpassword123",
    "new_password": "newpassword123"
}</pre>

                    <p style="margin: 10px 0; font-style: italic; color: #666;">
                        <strong>Note:</strong> Password changed notification email will be sent if enabled in email settings.
                    </p>

                    <h4>Social Login Request (Token-based - Recommended):</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
POST /wp-json/okhub-jwt/v1/auth/social/login
Content-Type: application/json

{
    "provider": "google",
    "idToken": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}</pre>

                    <h4>Social Login Request (Fallback - Less Secure):</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
POST /wp-json/okhub-jwt/v1/auth/social/login
Content-Type: application/json

{
    "provider": "google",
    "email": "user@gmail.com",
    "googleId": "1234567890",
    "name": "John Doe",
    "picture": "https://example.com/avatar.jpg"
}</pre>

                    <p style="margin: 10px 0; font-style: italic; color: #666;">
                        <strong>Note:</strong> Social login must be enabled in settings above. Supports 3 scenarios: new user registration, existing Google user login, and local account merging.
                    </p>

                    <h4>OTP Password Reset Request:</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
POST /wp-json/okhub-jwt/v1/auth/password/otp/request
Content-Type: application/json

{
    "email": "user@example.com"
}</pre>

                    <h4>Reset Password with OTP Request:</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
POST /wp-json/okhub-jwt/v1/auth/password/otp/reset
Content-Type: application/json

{
    "email": "user@example.com",
    "otp_code": "123456",
    "new_password": "newpassword123"
}</pre>

                    <h4>Verify OTP Request:</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
POST /wp-json/okhub-jwt/v1/auth/password/otp/verify
Content-Type: application/json

{
    "email": "user@example.com",
    "otp_code": "123456"
}</pre>

                    <p style="margin: 10px 0; font-style: italic; color: #666;">
                        <strong>Note:</strong> OTP reset must be enabled in settings above. OTP codes are 6 digits, expire in 5 minutes by default, and allow 3 attempts maximum.
                    </p>

                    <h4>Registration OTP Verification Request:</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
POST /wp-json/okhub-jwt/v1/auth/register/verify
Content-Type: application/json

{
    "email": "user@example.com",
    "otp_code": "123456"
}</pre>

                    <h4>Resend Registration OTP Request:</h4>
                    <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
POST /wp-json/okhub-jwt/v1/auth/register/resend-otp
Content-Type: application/json

{
    "email": "user@example.com"
}</pre>

                    <p style="margin: 10px 0; font-style: italic; color: #666;">
                        <strong>Note:</strong> Registration OTP verification must be enabled in settings above. After successful verification, users receive JWT tokens and can login normally.
                    </p>
                </div>

                <h3>🔒 Authentication Headers</h3>
                <p>For protected endpoints, include the JWT token in the Authorization header:</p>
                <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px;">
Authorization: Bearer {your_jwt_access_token}</pre>

                <h3>📊 Response Format</h3>
                <p>All endpoints return JSON responses with consistent format:</p>
                <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px;">
{
    "success": true/false,
    "message": "Response message",
    "data": { ... },
    "status": 200
}</pre>

                <h3>📧 Email Verification Field</h3>
                <p>When Email Verification is enabled, user responses include the <code>email_verified</code> field:</p>
                <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px;">
{
    "success": true,
    "data": {
        "id": 123,
        "username": "user123",
        "email": "user@example.com",
        "first_name": "John",
        "display_name": "John",
        "registered": "2025-01-19 10:00:00",
        "roles": ["subscriber"],
        "capabilities": ["read"],
        "email_verified": true,  // ⭐ Email verification status
        "is_google_user": false  // ⭐ Google user indicator
    }
}</pre>

                <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4 style="margin-top: 0; color: #0066cc;">💡 Email Verification Logic:</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><strong>Regular Users:</strong> <code>email_verified: false</code> until OTP verification</li>
                        <li><strong>Google Users:</strong> <code>email_verified: true</code> automatically</li>
                        <li><strong>Field Visibility:</strong> Only shown when Email Verification setting is enabled</li>
                        <li><strong>Login Restriction:</strong> Users with <code>email_verified: false</code> cannot login</li>
                    </ul>
                </div>

                <h3>📧 Email Response Examples</h3>
                <p>Email-related endpoints and their responses:</p>

                <h4>Forgot Password Response:</h4>
                <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
{
    "success": true,
    "message": "Link đặt lại mật khẩu đã được gửi đến email của bạn",
    "data": {
        "status": 200
    }
}</pre>

                <h4>Password Changed Response:</h4>
                <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
{
    "success": true,
    "message": "Mật khẩu đã được thay đổi thành công",
    "data": {
        "status": 200
    }
}</pre>

                <p style="margin: 10px 0; font-style: italic; color: #666;">
                    <strong>Note:</strong> Email responses depend on email settings configuration above.
                </p>

                <h3>⚠️ Error Handling</h3>
                <p>Common error scenarios and their responses:</p>

                <h4>Email Disabled Error:</h4>
                <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
{
    "success": false,
    "message": "Email notifications are currently disabled",
    "data": {
        "status": 400,
        "error_code": "emails_disabled"
    }
}</pre>

                <h4>Email Send Failure:</h4>
                <pre style="background: #f1f1f1; padding: 10px; border-radius: 3px; margin: 0;">
{
    "success": false,
    "message": "Failed to send email notification",
    "data": {
        "status": 500,
        "error_code": "email_send_failed"
    }
}</pre>

                <p style="margin: 10px 0; font-style: italic; color: #666;">
                    <strong>Note:</strong> Email errors are logged for debugging purposes.
                </p>

                <h3>🔧 Troubleshooting</h3>
                <p>Common issues and solutions for email functionality:</p>

                <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ffc107;">
                    <h4 style="margin-top: 0; color: #856404;">📧 Email Not Sending?</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Check if email settings are enabled above</li>
                        <li>Verify WordPress mail configuration (SMTP, etc.)</li>
                        <li>Check server logs for mail errors</li>
                        <li>Test with the "Send Test Email" button above</li>
                    </ul>
                </div>

                <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #17a2b8;">
                    <h4 style="margin-top: 0; color: #0c5460;">⚙️ Email Configuration Tips:</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Use SMTP plugin for reliable email delivery</li>
                        <li>Configure SPF and DKIM records for better deliverability</li>
                        <li>Test emails with different providers (Gmail, Outlook, etc.)</li>
                        <li>Monitor email logs for debugging</li>
                    </ul>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * General section callback
     */
    /**
     * Core section callback
     */
    public function coreSectionCallback()
    {
        echo '<p>Configure core JWT authentication settings:</p>';
    }

    /**
     * Registration section callback
     */
    public function registrationSectionCallback()
    {
        echo '<p>Configure user registration and email verification settings:</p>';
    }

    /**
     * Password reset section callback
     */
    public function passwordResetSectionCallback()
    {
        echo '<p>Configure password reset methods and settings:</p>';
    }

    /**
     * Email section callback
     */
    public function emailSectionCallback()
    {
        echo '<p>Configure email notification settings:</p>';
    }

    /**
     * Social section callback
     */
    public function socialSectionCallback()
    {
        echo '<p>Configure social login settings:</p>';
    }

    public function generalSectionCallback()
    {
        echo '<p>Configure JWT authentication settings below:</p>';
    }

    /**
     * Secret field callback
     */
    public function secretFieldCallback()
    {
        $value = \get_option('okhub_jwt_secret');
        echo '<input type="text" id="okhub_jwt_secret" name="okhub_jwt_secret" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Secret key used to sign JWT tokens. Keep this secure and unique.</p>';
    }

    /**
     * Expire field callback
     */
    public function expireFieldCallback()
    {
        $value = \get_option('okhub_jwt_expire', 3600);
        echo '<input type="number" id="okhub_jwt_expire" name="okhub_jwt_expire" value="' . esc_attr($value) . '" class="small-text" />';
        echo '<p class="description">How long access tokens are valid (in seconds). Default: 3600 (1 hour)</p>';
    }

    /**
     * Refresh expire field callback
     */
    public function refreshExpireFieldCallback()
    {
        $value = \get_option('okhub_jwt_refresh_expire', 604800);
        echo '<input type="number" id="okhub_jwt_refresh_expire" name="okhub_jwt_refresh_expire" value="' . esc_attr($value) . '" class="small-text" />';
        echo '<p class="description">How long refresh tokens are valid (in seconds). Default: 604800 (7 days)</p>';
    }

    /**
     * Enable refresh field callback
     */
    public function enableRefreshFieldCallback()
    {
        $value = \get_option('okhub_jwt_enable_refresh_tokens', true);
        echo '<input type="checkbox" id="okhub_jwt_enable_refresh_tokens" name="okhub_jwt_enable_refresh_tokens" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Enable refresh token functionality</p>';
    }

    /**
     * Enable password reset field callback
     */
    public function enablePasswordResetFieldCallback()
    {
        $value = \get_option('okhub_jwt_enable_password_reset', true);
        echo '<input type="checkbox" id="okhub_jwt_enable_password_reset" name="okhub_jwt_enable_password_reset" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Enable password reset functionality</p>';
    }

    /**
     * Password reset expire field callback
     */
    public function passwordResetExpireFieldCallback()
    {
        $value = \get_option('okhub_jwt_password_reset_expire', 3600);
        echo '<input type="number" id="okhub_jwt_password_reset_expire" name="okhub_jwt_password_reset_expire" value="' . esc_attr($value) . '" class="small-text" />';
        echo '<p class="description">How long password reset tokens are valid (in seconds). Default: 3600 (1 hour)</p>';
    }

    /**
     * Enable username login field callback
     */
    public function enableUsernameLoginFieldCallback()
    {
        $value = \get_option('okhub_jwt_enable_username_login', false);
        echo '<input type="checkbox" id="okhub_jwt_enable_username_login" name="okhub_jwt_enable_username_login" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Enable login with username instead of email. When enabled, registration will require both username and email.</p>';
    }

    /**
     * Enable email verification field callback
     */
    public function enableEmailVerificationFieldCallback()
    {
        $value = \get_option('okhub_jwt_enable_email_verification', true);
        echo '<input type="checkbox" id="okhub_jwt_enable_email_verification" name="okhub_jwt_enable_email_verification" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Require email verification via OTP before users can login. When enabled, users must verify their email after registration.</p>';
    }

    /**
     * Enable welcome email field callback
     */
    public function enableWelcomeEmailFieldCallback()
    {
        $value = \get_option('okhub_jwt_enable_welcome_email', true);
        echo '<input type="checkbox" id="okhub_jwt_enable_welcome_email" name="okhub_jwt_enable_welcome_email" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Send welcome email to users after successful registration.</p>';
    }



    /**
     * Enable OTP reset field callback
     */
    public function enableOtpResetFieldCallback()
    {
        $value = \get_option('okhub_jwt_enable_otp_reset', false);
        echo '<input type="checkbox" id="okhub_jwt_enable_otp_reset" name="okhub_jwt_enable_otp_reset" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Enable OTP-based password reset. Users will receive a 6-digit code via email to reset their password.</p>';
    }

    /**
     * OTP expire field callback
     */
    public function otpExpireFieldCallback()
    {
        $value = \get_option('okhub_jwt_otp_expire', 300);
        echo '<input type="number" id="okhub_jwt_otp_expire" name="okhub_jwt_otp_expire" value="' . esc_attr($value) . '" min="60" max="3600" />';
        echo '<p class="description">OTP expiry time in seconds (60-3600). Default: 300 seconds (5 minutes).</p>';
    }

    /**
     * OTP max attempts field callback
     */
    public function otpMaxAttemptsFieldCallback()
    {
        $value = \get_option('okhub_jwt_otp_max_attempts', 3);
        echo '<input type="number" id="okhub_jwt_otp_max_attempts" name="okhub_jwt_otp_max_attempts" value="' . esc_attr($value) . '" min="1" max="10" />';
        echo '<p class="description">Maximum number of OTP verification attempts before the OTP becomes invalid (1-10).</p>';
    }



    /**
     * Enable password changed email field callback
     */
    public function enablePasswordChangedEmailFieldCallback()
    {
        $value = \get_option('okhub_jwt_enable_password_changed_email', true);
        echo '<input type="checkbox" id="okhub_jwt_enable_password_changed_email" name="okhub_jwt_enable_password_changed_email" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Send notification email when user changes their password</p>';
    }

    /**
     * Enable password reset email field callback
     */
    public function enablePasswordResetEmailFieldCallback()
    {
        $value = \get_option('okhub_jwt_enable_password_reset_email', true);
        echo '<input type="checkbox" id="okhub_jwt_enable_password_reset_email" name="okhub_jwt_enable_password_reset_email" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Send password reset email when user requests password reset</p>';
    }

    /**
     * Handle test email AJAX request
     */
    public function handleTestEmail()
    {
        // Verify nonce
        if (!\wp_verify_nonce($_POST['nonce'], 'test_okhub_email')) {
            \wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error('Insufficient permissions');
        }

        $email = \sanitize_email($_POST['email']);
        if (!$email) {
            \wp_send_json_error('Invalid email address');
        }

        // Get email service
        $emailService = new \OkhubJwtAuth\Services\EmailService();

        // Send test email
        $subject = sprintf('[%s] Test Email - JWT Auth Plugin', \get_bloginfo('name'));
        $message = $this->getTestEmailTemplate();
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = \wp_mail($email, $subject, $message, $headers);

        if ($sent) {
            \wp_send_json_success('Test email sent successfully');
        } else {
            \wp_send_json_error('Failed to send test email. Check WordPress mail configuration.');
        }
    }

    /**
     * Get test email template
     */
    private function getTestEmailTemplate()
    {
        $siteName = \get_bloginfo('name');
        $siteUrl = \get_bloginfo('url');
        $currentTime = \current_time('Y-m-d H:i:s');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Test Email - JWT Auth Plugin</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #f8f9fa; padding: 30px; border-radius: 10px;'>
                <h2 style='color: #007cba; margin-bottom: 20px;'>🧪 Test Email - JWT Auth Plugin</h2>
                
                <p>Xin chào,</p>
                
                <p>Đây là email test để kiểm tra cấu hình email của plugin <strong>Okhub JWT Auth</strong>.</p>
                
                <div style='background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #495057;'>📧 Email Settings Status:</h3>
                    <ul style='margin: 0; padding-left: 20px;'>
                        <li><strong>Welcome Email:</strong> " . (\get_option('okhub_jwt_enable_welcome_email', true) ? '✅ Enabled' : '❌ Disabled') . "</li>
                        <li><strong>Password Changed Email:</strong> " . (\get_option('okhub_jwt_enable_password_changed_email', true) ? '✅ Enabled' : '❌ Disabled') . "</li>
                        <li><strong>Password Reset Email:</strong> " . (\get_option('okhub_jwt_enable_password_reset_email', true) ? '✅ Enabled' : '❌ Disabled') . "</li>
                    </ul>
                </div>
                
                <p><strong>Thông tin test:</strong></p>
                <ul>
                    <li><strong>Website:</strong> {$siteName}</li>
                    <li><strong>URL:</strong> {$siteUrl}</li>
                    <li><strong>Thời gian test:</strong> {$currentTime}</li>
                    <li><strong>Plugin:</strong> Okhub JWT Auth v1.0</li>
                </ul>
                
                <p>Nếu bạn nhận được email này, có nghĩa là cấu hình email của plugin đang hoạt động bình thường!</p>
                
                <p>Trân trọng,<br><strong>{$siteName}</strong></p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #dee2e6;'>
                <p style='font-size: 12px; color: #6c757d;'>Email test này được gửi từ {$siteUrl}</p>
            </div>
        </body>
        </html>";
    }

    /**
     * Handle import email settings AJAX request
     */
    public function handleImportEmailSettings()
    {
        // Verify nonce
        if (!\wp_verify_nonce($_POST['nonce'], 'import_okhub_email_settings')) {
            \wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error('Insufficient permissions');
        }

        $settings = $_POST['settings'];
        if (!is_array($settings)) {
            \wp_send_json_error('Invalid settings format');
        }

        // Validate and update settings
        $updated = 0;

        if (isset($settings['welcome'])) {
            \update_option('okhub_jwt_enable_welcome_email', (bool) $settings['welcome']);
            $updated++;
        }

        if (isset($settings['password_changed'])) {
            \update_option('okhub_jwt_enable_password_changed_email', (bool) $settings['password_changed']);
            $updated++;
        }

        if (isset($settings['password_reset'])) {
            \update_option('okhub_jwt_enable_password_reset_email', (bool) $settings['password_reset']);
            $updated++;
        }

        if ($updated > 0) {
            \wp_send_json_success("Successfully updated {$updated} email settings");
        } else {
            \wp_send_json_error('No valid settings to update');
        }
    }

    /**
     * Social section callback
     */


    /**
     * Enable social login field callback
     */
    public function enableSocialLoginFieldCallback()
    {
        $value = \get_option('okhub_jwt_enable_social_login', false);
        echo '<input type="checkbox" id="okhub_jwt_enable_social_login" name="okhub_jwt_enable_social_login" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Enable Google Social Login functionality</p>';
    }

    /**
     * Google Client ID field callback
     */
    public function googleClientIdFieldCallback()
    {
        $value = \get_option('okhub_jwt_google_client_id');
        echo '<input type="text" id="okhub_jwt_google_client_id" name="okhub_jwt_google_client_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Google OAuth Client ID from Google Cloud Console</p>';
    }

    /**
     * Google Client Secret field callback
     */
    public function googleClientSecretFieldCallback()
    {
        $value = \get_option('okhub_jwt_google_client_secret');
        echo '<input type="password" id="okhub_jwt_google_client_secret" name="okhub_jwt_google_client_secret" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Google OAuth Client Secret from Google Cloud Console</p>';
    }

    /**
     * Auto verify Google email field callback
     */
    public function autoVerifyGoogleEmailFieldCallback()
    {
        $value = \get_option('okhub_jwt_auto_verify_google_email', true);
        echo '<input type="checkbox" id="okhub_jwt_auto_verify_google_email" name="okhub_jwt_auto_verify_google_email" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Automatically verify email addresses for Google users</p>';
    }

    /**
     * Allow account merge field callback
     */
    public function allowAccountMergeFieldCallback()
    {
        $value = \get_option('okhub_jwt_allow_account_merge', true);
        echo '<input type="checkbox" id="okhub_jwt_allow_account_merge" name="okhub_jwt_allow_account_merge" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Allow merging Google accounts with existing local accounts</p>';
    }

    /**
     * OTP section callback
     */
    public function otpSectionCallback()
    {
        echo '<p>Configure OTP (One-Time Password) settings for password reset:</p>';
    }
}
