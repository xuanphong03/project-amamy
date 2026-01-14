<?php

namespace OkhubJwtAuth\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWT Token service
 */
class TokenService
{
    private $secret;
    private $algorithm;
    private $expire;
    private $refreshExpire;
    private $sessionService;

    public function __construct(?SessionService $sessionService = null)
    {
        // Use JWT_SECRET_KEY constant if defined, otherwise fallback to option
        $this->secret = defined('JWT_SECRET_KEY') ? constant('JWT_SECRET_KEY') : \get_option('okhub_jwt_secret', 'your-secret-key');
        $this->algorithm = 'HS256';
        $this->expire = \get_option('okhub_jwt_expire', 7200);
        $this->refreshExpire = \get_option('okhub_jwt_refresh_expire', 604800);
        $this->sessionService = $sessionService;
    }

    /**
     * Generate access and refresh tokens
     */
    public function generateTokens($userId, $sessionId = null)
    {
        // JWT standard requires UTC time
        $now = time();

        // Access token payload
        $accessPayload = [
            'iss' => \get_site_url(),
            'user_id' => $userId,
            'type' => 'access',
            'iat' => $now,
            'exp' => $now + $this->expire
        ];

        // Add session_id to access token if provided
        if ($sessionId) {
            $accessPayload['session_id'] = $sessionId;
        }

        // Refresh token payload
        $refreshPayload = [
            'iss' => \get_site_url(),
            'user_id' => $userId,
            'type' => 'refresh',
            'iat' => $now,
            'exp' => $now + $this->refreshExpire
        ];

        // Add session_id to refresh token if provided
        if ($sessionId) {
            $refreshPayload['session_id'] = $sessionId;
        }

        $accessToken = JWT::encode($accessPayload, $this->secret, $this->algorithm);
        $refreshToken = JWT::encode($refreshPayload, $this->secret, $this->algorithm);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->expire,
            'refresh_expires_in' => $this->refreshExpire,
            'accessPayload' => $this->formatPayloadForFrontend($accessPayload),
            'refreshPayload' => $this->formatPayloadForFrontend($refreshPayload)
        ];
    }

    /**
     * Format JWT payload for frontend (convert timestamps to ISO 8601)
     */
    private function formatPayloadForFrontend($payload)
    {
        $formatted = [];
        foreach ($payload as $key => $value) {
            if ($key === 'iat' || $key === 'exp') {
                // Convert Unix timestamp to ISO 8601 format
                $formatted[$key] = gmdate('Y-m-d\TH:i:s.000\Z', $value);
            } else {
                $formatted[$key] = $value;
            }
        }
        return $formatted;
    }

    /**
     * Validate access token
     */
    public function validateAccessToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

            if ($decoded->type !== 'access') {
                return false;
            }

            $result = [
                'user_id' => $decoded->user_id,
                'exp' => $decoded->exp,
                'iat' => $decoded->iat
            ];

            // Add session_id if present
            if (isset($decoded->session_id)) {
                $result['session_id'] = $decoded->session_id;
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate refresh token
     */
    public function validateRefreshToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

            if ($decoded->type !== 'refresh') {
                return false;
            }

            $result = [
                'user_id' => $decoded->user_id,
                'exp' => $decoded->exp,
                'iat' => $decoded->iat
            ];

            // Add session_id if present
            if (isset($decoded->session_id)) {
                $result['session_id'] = $decoded->session_id;

                // Check if session is still active
                if ($this->sessionService) {
                    $session = $this->sessionService->getSessionById($decoded->session_id);
                    if (!$session || !$session->is_active) {
                        return false; // Session is not active
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken($refreshToken)
    {
        $decoded = $this->validateRefreshToken($refreshToken);
        if (!$decoded) {
            return false;
        }

        $now = time();

        // Create new access token
        $accessPayload = [
            'iss' => \get_site_url(),
            'user_id' => $decoded['user_id'],
            'type' => 'access',
            'iat' => $now,
            'exp' => $now + $this->expire
        ];

        // Add session_id if present in original token
        if (isset($decoded['session_id'])) {
            $accessPayload['session_id'] = $decoded['session_id'];
        }

        // Create new refresh token
        $refreshPayload = [
            'iss' => \get_site_url(),
            'user_id' => $decoded['user_id'],
            'type' => 'refresh',
            'iat' => $now,
            'exp' => $now + $this->refreshExpire
        ];

        // Add session_id if present in original token
        if (isset($decoded['session_id'])) {
            $refreshPayload['session_id'] = $decoded['session_id'];
        }

        $accessToken = JWT::encode($accessPayload, $this->secret, $this->algorithm);
        $newRefreshToken = JWT::encode($refreshPayload, $this->secret, $this->algorithm);

        $newTokens = [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => $this->expire,
            'refresh_expires_in' => $this->refreshExpire,
            'accessPayload' => $this->formatPayloadForFrontend($accessPayload),
            'refreshPayload' => $this->formatPayloadForFrontend($refreshPayload)
        ];

        // Update session with new tokens if session_id is present
        if (isset($decoded['session_id']) && $this->sessionService) {
            $this->sessionService->updateSessionTokens($decoded['session_id'], $newTokens);
        }

        return $newTokens;
    }

    /**
     * Blacklist token (for logout)
     */
    public function blacklistToken($token, $userId)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_jwt_blacklist';
        $tokenHash = hash('sha256', $token);

        // Get token expiration from decoded token
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            // Convert UTC timestamp to WordPress timezone for database
            $expiresAt = \current_time('mysql', true, $decoded->exp);
        } catch (\Exception $e) {
            // Fallback: use current WordPress time + expire
            $expiresAt = \current_time('mysql', true, time() + $this->expire);
        }

        $result = $wpdb->insert(
            $table,
            [
                'token_hash' => $tokenHash,
                'user_id' => $userId,
                'expires_at' => $expiresAt
            ],
            ['%s', '%d', '%s']
        );

        return $result !== false;
    }

    /**
     * Check if token is blacklisted
     */
    public function isTokenBlacklisted($token)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_jwt_blacklist';
        $tokenHash = hash('sha256', $token);

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE token_hash = %s",
                $tokenHash
            )
        );

        return $result !== null;
    }

    /**
     * Clean up expired tokens with performance optimization
     */
    public function cleanupExpiredTokens()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_jwt_blacklist';
        // Use UTC time for comparison to match JWT token expiration
        $now = \current_time('mysql', true);

        // Use LIMIT to avoid long-running queries
        // Process in batches of 1000 records
        $batch_size = 1000;
        $deleted = 0;

        do {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $table WHERE expires_at < %s LIMIT %d",
                    $now,
                    $batch_size
                )
            );

            if ($result !== false) {
                $deleted += $result;
            }

            // Small delay to prevent blocking
            if ($result === $batch_size) {
                usleep(10000); // 10ms delay
            }
        } while ($result === $batch_size);

        // Log cleanup results for monitoring
        if ($deleted > 0) {
            error_log("Okhub JWT Auth: Cleaned up $deleted expired tokens");
        }

        return $deleted;
    }

    /**
     * Batch blacklist multiple tokens for better performance
     */
    public function batchBlacklistTokens($tokens, $userId)
    {
        global $wpdb;

        if (empty($tokens)) {
            return 0;
        }

        $table = $wpdb->prefix . 'okhub_jwt_blacklist';
        $inserted = 0;

        // Process in batches to avoid memory issues
        $batch_size = 100;
        $batches = array_chunk($tokens, $batch_size);

        foreach ($batches as $batch) {
            $values = [];
            $placeholders = [];

            foreach ($batch as $token) {
                try {
                    $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
                    $expiresAt = \current_time('mysql', true, $decoded->exp);
                } catch (\Exception $e) {
                    // Fallback: use current WordPress time + expire
                    $expiresAt = \current_time('mysql', true, time() + $this->expire);
                }

                $values[] = hash('sha256', $token);
                $values[] = $userId;
                $values[] = $expiresAt;
                $placeholders[] = "(%s, %d, %s)";
            }

            if (!empty($values)) {
                $sql = "INSERT INTO $table (token_hash, user_id, expires_at) VALUES " . implode(', ', $placeholders);
                $result = $wpdb->query($wpdb->prepare($sql, $values));

                if ($result !== false) {
                    $inserted += $result;
                }
            }
        }

        return $inserted;
    }
}
