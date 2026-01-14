# Google OAuth Setup Guide

## 📋 Prerequisites

1. **Google Cloud Console Project**
2. **Google API Client Library** (via Composer)
3. **WordPress Plugin Configuration**

## 🔧 Installation Steps

### 1. Install Google API Client Library

```bash
cd wp-content/plugins/okhub-jwt-auth
composer install
```

This will install the required dependencies:

-   `google/apiclient` - Google API Client Library
-   `firebase/php-jwt` - JWT token handling

### 2. Google Cloud Console Setup

1. **Go to [Google Cloud Console](https://console.cloud.google.com/)**
2. **Create a new project** or select existing one
3. **Enable APIs:**
    - Go to "APIs & Services" → "Library"
    - Search and enable "Google+ API" or "Google Identity API"
4. **Create OAuth 2.0 Credentials:**
    - Go to "APIs & Services" → "Credentials"
    - Click "Create Credentials" → "OAuth 2.0 Client IDs"
    - Set Application type to "Web application"
    - Add authorized redirect URIs:
        ```
        https://yourdomain.com/wp-json/okhub-jwt/v1/auth/social/login
        ```
5. **Copy Credentials:**
    - Copy Client ID and Client Secret

### 3. WordPress Plugin Configuration

1. **Go to WordPress Admin** → "Okhub JWT Auth Settings"
2. **Enable Social Login:**
    - Check "Enable Social Login"
3. **Configure Google OAuth:**
    - Paste Client ID in "Google Client ID" field
    - Paste Client Secret in "Google Client Secret" field
4. **Save Settings**

## 🔐 Authentication Methods

### Method 1: ID Token (Recommended)

```javascript
// Frontend (NextAuth/React)
const response = await fetch("/wp-json/okhub-jwt/v1/auth/social/login", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        provider: "google",
        idToken: "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    }),
});
```

### Method 2: Access Token

```javascript
const response = await fetch("/wp-json/okhub-jwt/v1/auth/social/login", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        provider: "google",
        accessToken: "ya29.a0AfH6SMC...",
    }),
});
```

### Method 3: Authorization Code

```javascript
const response = await fetch("/wp-json/okhub-jwt/v1/auth/social/login", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        provider: "google",
        code: "4/0AX4XfWh...",
    }),
});
```

### Method 4: Fallback (Less Secure)

```javascript
const response = await fetch("/wp-json/okhub-jwt/v1/auth/social/login", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        provider: "google",
        email: "user@gmail.com",
        googleId: "1234567890",
        name: "John Doe",
        picture: "https://example.com/avatar.jpg",
    }),
});
```

## 🛡️ Security Features

### Token Verification

-   **ID Token**: Verified with Google's public keys
-   **Access Token**: Verified with Google OAuth2 API
-   **Authorization Code**: Exchanged for access token and verified

### Fallback Mode

-   **Less Secure**: Only validates data format
-   **Use Case**: Development/testing only
-   **Warning**: Not recommended for production

## 📊 Response Format

### Success Response

```json
{
    "success": true,
    "message": "Đăng nhập Google thành công",
    "data": {
        "id": 123,
        "email": "user@gmail.com",
        "username": "user",
        "display_name": "John Doe",
        "first_name": "John",
        "last_name": "Doe"
    },
    "token": {
        "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "accessPayload": {
            "user_id": 123,
            "iat": "2024-01-01T00:00:00+00:00",
            "exp": "2024-01-01T01:00:00+00:00"
        },
        "refreshPayload": {
            "user_id": 123,
            "iat": "2024-01-01T00:00:00+00:00",
            "exp": "2024-01-08T00:00:00+00:00"
        }
    }
}
```

### Error Response

```json
{
    "success": false,
    "code": "invalid_token",
    "message": "Invalid Google token or verification failed",
    "status": 401
}
```

## 🧪 Testing

### Test File

Use the provided test file:

```
wp-content/plugins/okhub-jwt-auth/examples/google-oauth-test.html
```

### Test Scenarios

1. **New User Registration**
2. **Existing Google User Login**
3. **Local User Account Merge**

## ⚠️ Troubleshooting

### Common Issues

1. **"Google API Client library not installed"**

    ```bash
    cd wp-content/plugins/okhub-jwt-auth
    composer install
    ```

2. **"Google OAuth credentials not configured"**

    - Check Client ID and Secret in WordPress admin
    - Verify credentials in Google Cloud Console

3. **"Invalid Google token"**

    - Check token format and expiry
    - Verify redirect URI matches Google Console

4. **"Account merging is disabled"**
    - Enable "Allow Account Merge" in plugin settings

### Debug Mode

Enable WordPress debug mode to see detailed error logs:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🔄 Workflow

### 1. Frontend (NextAuth/React)

```javascript
// Get Google ID token
const { data: session } = useSession();
const idToken = session?.idToken;

// Send to WordPress
const response = await fetch("/wp-json/okhub-jwt/v1/auth/social/login", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
        provider: "google",
        idToken: idToken,
    }),
});
```

### 2. Backend (WordPress)

```php
// SocialLoginService processes the request
$googleUserInfo = $this->verifyGoogleToken($data);

// Verify with Google API
$userInfo = $this->googleOAuthService->verifyIdToken($idToken);

// Process login/register/merge
return $this->processGoogleLogin($googleUserInfo);
```

### 3. Google API Verification

```php
// GoogleOAuthService verifies token
$payload = $this->client->verifyIdToken($idToken);

// Returns verified user info
return [
    'user_id' => $payload['sub'],
    'email' => $payload['email'],
    'name' => $payload['name'],
    'picture' => $payload['picture']
];
```

## 📚 Additional Resources

-   [Google OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
-   [Google API Client Library](https://github.com/googleapis/google-api-php-client)
-   [NextAuth.js Google Provider](https://next-auth.js.org/providers/google)
-   [WordPress REST API](https://developer.wordpress.org/rest-api/)
