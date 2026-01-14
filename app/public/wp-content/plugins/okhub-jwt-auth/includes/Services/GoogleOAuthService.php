<?php

namespace OkhubJwtAuth\Services;

// Google API Client classes will be available after composer install
// use Google\Client;
// use Google\Service\Oauth2;

/**
 * Google OAuth Service for token verification
 */
class GoogleOAuthService
{
    private $client;
    private $clientId;
    private $clientSecret;

    public function __construct()
    {
        $this->clientId = get_option('okhub_jwt_google_client_id', '');
        $this->clientSecret = get_option('okhub_jwt_google_client_secret', '');

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new \Exception('Google OAuth credentials not configured');
        }

        $this->initializeClient();
    }

    /**
     * Initialize Google Client
     */
    private function initializeClient()
    {
        // Check if Google API Client is available
        if (!class_exists('Google\Client')) {
            throw new \Exception('Google API Client library not installed. Run: composer install');
        }

        $this->client = new \Google\Client();
        $this->client->setClientId($this->clientId);
        $this->client->setClientSecret($this->clientSecret);
        $this->client->setRedirectUri(home_url('/wp-json/okhub-jwt/v1/auth/social/login'));
    }

    /**
     * Verify Google ID token
     * 
     * @param string $idToken
     * @return array|false
     */
    public function verifyIdToken($idToken)
    {
        try {
            // Verify the ID token
            $payload = $this->client->verifyIdToken($idToken);

            if ($payload) {
                return [
                    'user_id' => $payload['sub'],
                    'email' => $payload['email'],
                    'email_verified' => $payload['email_verified'] ?? false,
                    'name' => $payload['name'] ?? '',
                    'picture' => $payload['picture'] ?? '',
                    'given_name' => $payload['given_name'] ?? '',
                    'family_name' => $payload['family_name'] ?? '',
                    'aud' => $payload['aud'],
                    'iss' => $payload['iss'],
                    'exp' => $payload['exp'],
                    'iat' => $payload['iat']
                ];
            }
        } catch (\Exception $e) {
            error_log('Google OAuth verification failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Verify Google access token
     * 
     * @param string $accessToken
     * @return array|false
     */
    public function verifyAccessToken($accessToken)
    {
        try {
            // Set the access token
            $this->client->setAccessToken($accessToken);

            // Create OAuth2 service
            $oauth2 = new \Google\Service\Oauth2($this->client);

            // Get user info
            $userInfo = $oauth2->userinfo->get();

            return [
                'user_id' => $userInfo->getId(),
                'email' => $userInfo->getEmail(),
                'email_verified' => $userInfo->getVerifiedEmail(),
                'name' => $userInfo->getName(),
                'picture' => $userInfo->getPicture(),
                'given_name' => $userInfo->getGivenName(),
                'family_name' => $userInfo->getFamilyName()
            ];
        } catch (\Exception $e) {
            error_log('Google access token verification failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Verify Google authorization code
     * 
     * @param string $code
     * @return array|false
     */
    public function verifyAuthorizationCode($code)
    {
        try {
            // Exchange authorization code for access token
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);

            if (isset($accessToken['error'])) {
                error_log('Google OAuth error: ' . $accessToken['error_description']);
                return false;
            }

            // Set the access token
            $this->client->setAccessToken($accessToken);

            // Create OAuth2 service
            $oauth2 = new \Google\Service\Oauth2($this->client);

            // Get user info
            $userInfo = $oauth2->userinfo->get();

            return [
                'user_id' => $userInfo->getId(),
                'email' => $userInfo->getEmail(),
                'email_verified' => $userInfo->getVerifiedEmail(),
                'name' => $userInfo->getName(),
                'picture' => $userInfo->getPicture(),
                'given_name' => $userInfo->getGivenName(),
                'family_name' => $userInfo->getFamilyName(),
                'access_token' => $accessToken
            ];
        } catch (\Exception $e) {
            error_log('Google authorization code verification failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Get Google OAuth URL for authorization
     * 
     * @param array $scopes
     * @return string
     */
    public function getAuthorizationUrl($scopes = ['openid', 'email', 'profile'])
    {
        $this->client->setScopes($scopes);
        return $this->client->createAuthUrl();
    }

    /**
     * Check if Google credentials are configured
     * 
     * @return bool
     */
    public static function isConfigured()
    {
        $clientId = get_option('okhub_jwt_google_client_id', '');
        $clientSecret = get_option('okhub_jwt_google_client_secret', '');

        return !empty($clientId) && !empty($clientSecret);
    }

    /**
     * Get Google Client ID
     * 
     * @return string
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * Get Google Client Secret
     * 
     * @return string
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }
}
