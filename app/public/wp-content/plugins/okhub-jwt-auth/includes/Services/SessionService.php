<?php

namespace OkhubJwtAuth\Services;

/**
 * Session management service for multi-device support
 */
class SessionService
{
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'okhub_jwt_sessions';
    }

    /**
     * Create new session for device
     */
    public function createSession($userId, $sessionId, $deviceInfo, $tokens)
    {
        global $wpdb;



        $deviceInfo = $this->sanitizeDeviceInfo($deviceInfo);
        $accessTokenHash = hash('sha256', $tokens['access_token']);
        $refreshTokenHash = hash('sha256', $tokens['refresh_token']);

        // Calculate expiration based on refresh token expiration time
        // Get refresh token expiration from tokens array
        $refreshExpireSeconds = $tokens['refresh_expires_in'] ?? 604800; // Default 7 days if not provided

        $currentWordPressTime = \current_time('mysql');
        $expiresAt = date('Y-m-d H:i:s', strtotime($currentWordPressTime) + $refreshExpireSeconds);

        // Debug: Log timezone calculations
        error_log("Okhub JWT Auth: Session timezone debug - Current WordPress time: $currentWordPressTime, Refresh expire seconds: $refreshExpireSeconds, Expires at: $expiresAt");

        $insertData = [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'device_info' => $deviceInfo,
            'access_token_hash' => $accessTokenHash,
            'refresh_token_hash' => $refreshTokenHash,
            'access_token' => $tokens['access_token'], // Store actual token for revocation
            'refresh_token' => $tokens['refresh_token'], // Store actual token for revocation
            'expires_at' => $expiresAt,
            'created_at' => \current_time('mysql'),
            'last_used' => \current_time('mysql'),
            'is_active' => 1
        ];

        $result = $wpdb->insert(
            $this->table,
            $insertData,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        return $result !== false;
    }

    /**
     * Get active sessions for user
     */
    public function getUserSessions($userId)
    {
        global $wpdb;

        $currentTime = \current_time('mysql');
        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT session_id, device_info, created_at, last_used, expires_at 
                 FROM {$this->table} 
                 WHERE user_id = %d AND is_active = 1 AND expires_at > %s
                 ORDER BY last_used DESC",
                $userId,
                $currentTime
            )
        );

        return $sessions;
    }

    /**
     * Get all sessions for user (including inactive ones)
     */
    public function getAllUserSessions($userId)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY last_used DESC",
            $userId
        );

        $sessions = $wpdb->get_results($query);

        return $sessions;
    }

    /**
     * Update session last used time
     */
    public function updateSessionActivity($sessionId)
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            ['last_used' => \current_time('mysql')],
            ['session_id' => $sessionId],
            ['%s'],
            ['%s']
        );
    }

    /**
     * Update session tokens after refresh
     */
    public function updateSessionTokens($sessionId, $newTokens)
    {
        global $wpdb;

        $accessTokenHash = hash('sha256', $newTokens['access_token']);
        $refreshTokenHash = hash('sha256', $newTokens['refresh_token']);

        // Recalculate expiration based on new refresh token
        $refreshExpireSeconds = $newTokens['refresh_expires_in'] ?? 604800;
        $currentWordPressTime = \current_time('mysql');
        $expiresAt = date('Y-m-d H:i:s', strtotime($currentWordPressTime) + $refreshExpireSeconds);

        $result = $wpdb->update(
            $this->table,
            [
                'access_token_hash' => $accessTokenHash,
                'refresh_token_hash' => $refreshTokenHash,
                'access_token' => $newTokens['access_token'],
                'refresh_token' => $newTokens['refresh_token'],
                'expires_at' => $expiresAt,
                'last_used' => \current_time('mysql')
            ],
            ['session_id' => $sessionId],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%s']
        );

        if ($result !== false) {
            error_log("Okhub JWT Auth: Updated session $sessionId with new tokens and expiration $expiresAt");
        }

        return $result !== false;
    }

    /**
     * Deactivate specific session
     */
    public function deactivateSession($sessionId, $userId = null)
    {
        global $wpdb;

        $where = ['session_id' => $sessionId];
        if ($userId) {
            $where['user_id'] = $userId;
        }

        return $wpdb->update(
            $this->table,
            ['is_active' => 0],
            $where,
            ['%d'],
            ['%s', '%d']
        );
    }

    /**
     * Delete specific session completely
     */
    public function deleteSession($sessionId, $userId = null)
    {
        global $wpdb;

        $where = ['session_id' => $sessionId];
        if ($userId) {
            $where['user_id'] = $userId;
        }

        return $wpdb->delete(
            $this->table,
            $where,
            ['%s', '%d']
        );
    }

    /**
     * Deactivate all sessions for user (force logout all devices)
     */
    public function deactivateAllUserSessions($userId)
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            ['is_active' => 0],
            ['user_id' => $userId],
            ['%d'],
            ['%d']
        );
    }

    /**
     * Delete all sessions for user completely
     */
    public function deleteAllUserSessions($userId)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table,
            ['user_id' => $userId],
            ['%d']
        );
    }

    /**
     * Validate session
     */
    public function validateSession($sessionId, $userId)
    {
        global $wpdb;

        $currentTime = \current_time('mysql');
        $session = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE session_id = %s AND user_id = %d AND is_active = 1 AND expires_at > %s",
                $sessionId,
                $userId,
                $currentTime
            )
        );

        if ($session) {
            // Update last used time
            $this->updateSessionActivity($sessionId);
            return $session;
        }

        return false;
    }

    /**
     * Get session by ID (without validation)
     */
    public function getSessionById($sessionId)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE session_id = %s",
                $sessionId
            )
        );
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions()
    {
        global $wpdb;

        $batch_size = 1000;
        $deleted = 0;

        do {
            $currentTime = \current_time('mysql');
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$this->table} WHERE expires_at < %s LIMIT %d",
                    $currentTime,
                    $batch_size
                )
            );

            if ($result !== false) {
                $deleted += $result;
            }

            if ($result === $batch_size) {
                usleep(10000); // 10ms delay
            }
        } while ($result === $batch_size);

        return $deleted;
    }

    /**
     * Clean up expired sessions for specific user
     */
    public function cleanupExpiredSessionsForUser($userId)
    {
        global $wpdb;

        $currentTime = \current_time('mysql');
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table} WHERE user_id = %d AND expires_at < %s",
                $userId,
                $currentTime
            )
        );

        return $result !== false ? $result : 0;
    }

    /**
     * Clean up orphaned sessions (sessions without valid tokens)
     */
    public function cleanupOrphanedSessions()
    {
        global $wpdb;

        // Find sessions where tokens are expired or blacklisted
        $batch_size = 1000;
        $deleted = 0;

        do {
            $currentTime = \current_time('mysql');
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE s FROM {$this->table} s 
                     LEFT JOIN {$wpdb->prefix}okhub_jwt_blacklist b ON s.access_token_hash = b.token_hash 
                     WHERE s.expires_at < %s OR b.token_hash IS NOT NULL 
                     LIMIT %d",
                    $currentTime,
                    $batch_size
                )
            );

            if ($result !== false) {
                $deleted += $result;
            }

            if ($result === $batch_size) {
                usleep(10000); // 10ms delay
            }
        } while ($result === $batch_size);

        return $deleted;
    }

    /**
     * Clean up orphaned sessions for specific user (but not current session)
     */
    public function cleanupOrphanedSessionsForUser($userId, $currentSessionId = null)
    {
        global $wpdb;

        // Build WHERE clause to exclude current session
        $currentTime = \current_time('mysql');
        $whereClause = "s.user_id = %d AND (s.expires_at < %s OR b.token_hash IS NOT NULL)";
        $params = [$userId, $currentTime];

        if ($currentSessionId) {
            $whereClause .= " AND s.session_id != %s";
            $params[] = $currentSessionId;
        }

        // Find and delete orphaned sessions for specific user (except current session)
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE s FROM {$this->table} s 
                 LEFT JOIN {$wpdb->prefix}okhub_jwt_blacklist b ON s.access_token_hash = b.token_hash 
                 WHERE $whereClause",
                ...$params
            )
        );

        return $result !== false ? $result : 0;
    }

    /**
     * Get device info from request with enhanced fingerprinting
     */
    public function getDeviceInfo()
    {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ip = $this->getClientIP();

        $deviceInfo = [
            'user_agent' => substr($userAgent, 0, 255),
            'ip_address' => $ip,
            'device_type' => $this->detectDeviceType($userAgent),
            'platform' => $this->detectPlatform($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'fingerprint' => $this->generateDeviceFingerprint($userAgent, $ip),
            'screen_resolution' => $this->getScreenResolution(),
            'timezone' => $this->getClientTimezone(),
            'language' => $this->getClientLanguage()
        ];

        return json_encode($deviceInfo);
    }

    /**
     * Generate unique device fingerprint
     */
    private function generateDeviceFingerprint($userAgent, $ip)
    {
        $components = [
            $userAgent,
            $ip,
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $_SERVER['HTTP_ACCEPT'] ?? ''
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Get screen resolution from request headers
     */
    private function getScreenResolution()
    {
        $width = $_SERVER['HTTP_X_SCREEN_WIDTH'] ?? $_SERVER['HTTP_X_DEVICE_PIXEL_RATIO'] ?? 'unknown';
        $height = $_SERVER['HTTP_X_SCREEN_HEIGHT'] ?? 'unknown';

        return "{$width}x{$height}";
    }

    /**
     * Get client timezone
     */
    private function getClientTimezone()
    {
        return $_SERVER['HTTP_X_TIMEZONE'] ?? 'unknown';
    }

    /**
     * Get client language
     */
    private function getClientLanguage()
    {
        return $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown';
    }

    /**
     * Generate unique session ID
     */
    public function generateSessionId()
    {
        return wp_generate_password(32, false);
    }

    /**
     * Sanitize device info
     */
    private function sanitizeDeviceInfo($deviceInfo)
    {
        if (is_array($deviceInfo)) {
            $deviceInfo = json_encode($deviceInfo);
        }

        return \sanitize_text_field($deviceInfo);
    }

    /**
     * Get client IP address
     */
    private function getClientIP()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Detect device type
     */
    private function detectDeviceType($userAgent)
    {
        if (preg_match('/Mobile|Android|iPhone|iPad|Windows Phone/i', $userAgent)) {
            return 'mobile';
        } elseif (preg_match('/Tablet|iPad/i', $userAgent)) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }

    /**
     * Detect platform
     */
    private function detectPlatform($userAgent)
    {
        if (preg_match('/Windows/i', $userAgent)) {
            return 'Windows';
        } elseif (preg_match('/Mac/i', $userAgent)) {
            return 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            return 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            return 'Android';
        } elseif (preg_match('/iOS/i', $userAgent)) {
            return 'iOS';
        } else {
            return 'Unknown';
        }
    }

    /**
     * Detect browser
     */
    private function detectBrowser($userAgent)
    {
        if (preg_match('/Chrome/i', $userAgent)) {
            return 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            return 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            return 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            return 'Edge';
        } elseif (preg_match('/Opera/i', $userAgent)) {
            return 'Opera';
        } else {
            return 'Unknown';
        }
    }
}