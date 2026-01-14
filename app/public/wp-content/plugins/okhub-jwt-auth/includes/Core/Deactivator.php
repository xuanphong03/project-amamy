<?php

namespace OkhubJwtAuth\Core;

/**
 * Plugin deactivation handler
 */
class Deactivator
{
    /**
     * Run deactivation tasks
     */
    public static function deactivate()
    {
        // Clear scheduled events
        wp_clear_scheduled_hook('okhub_jwt_cleanup_expired_tokens');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
