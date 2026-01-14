# Postman Collection Summary

## 📋 Collection Overview

**Collection Name**: Okhub JWT Auth API  
**Version**: 1.0.0  
**Total Endpoints**: 16  
**Categories**: 5

## 📁 Collection Structure

### 🔐 Authentication (5 endpoints)

-   **Login** - User login with email/username and password
-   **Register** - User registration with username, email, password
-   **Refresh Token** - Refresh access token using refresh token
-   **Logout** - Logout current session
-   **Logout All Devices** - Logout from all devices

### 👤 User Management (4 endpoints)

-   **Get Current User** - Get current authenticated user information
-   **Update Profile** - Update user profile information
-   **Change Password** - Change user password
-   **Get User Sessions** - Get user active sessions

### 🔑 Password Reset (3 endpoints)

-   **Forgot Password** - Request password reset email
-   **Reset Password** - Reset password with token
-   **Validate Reset Token** - Validate password reset token

### 🔐 OTP Password Reset (3 endpoints)

-   **Request OTP** - Request OTP for password reset
-   **Reset Password with OTP** - Reset password with OTP code
-   **Verify OTP** - Verify OTP code only

### 🌐 Social Login (3 endpoints)

-   **Google Login (ID Token)** - Google login using ID token (recommended)
-   **Google Login (Access Token)** - Google login using access token
-   **Google Login (Fallback)** - Google login using fallback data (development only)

## 🔧 Environment Variables

| Variable        | Type    | Description          | Example                  |
| --------------- | ------- | -------------------- | ------------------------ |
| `base_url`      | Default | WordPress site URL   | `https://yourdomain.com` |
| `access_token`  | Secret  | JWT access token     | Auto-filled after login  |
| `refresh_token` | Secret  | JWT refresh token    | Auto-filled after login  |
| `reset_token`   | Default | Password reset token | From email               |
| `user_id`       | Default | Current user ID      | Auto-filled after login  |
| `user_email`    | Default | Current user email   | Auto-filled after login  |
| `username`      | Default | Current username     | Auto-filled after login  |

## 🎯 Testing Scenarios

### Scenario 1: Complete User Flow

1. **Register** → **Login** → **Get Profile** → **Update Profile** → **Change Password** → **Logout**

### Scenario 2: Password Reset Flow

1. **Forgot Password** → **Check Email** → **Reset Password** → **Login with New Password**

### Scenario 3: OTP Reset Flow

1. **Request OTP** → **Check Email** → **Reset with OTP** → **Login with New Password**

### Scenario 4: Social Login Flow

1. **Get Google Token** → **Social Login** → **Access Protected Endpoints** → **Logout**

### Scenario 5: Token Management

1. **Login** → **Use Access Token** → **Refresh Token** → **Logout All Devices**

## 📊 Endpoint Statistics

### HTTP Methods

-   **POST**: 10 endpoints (62.5%)
-   **GET**: 3 endpoints (18.75%)
-   **PUT**: 3 endpoints (18.75%)

### Authentication Required

-   **No Auth**: 10 endpoints (62.5%)
-   **Auth Required**: 6 endpoints (37.5%)

### Categories

-   **Authentication**: 5 endpoints (31.25%)
-   **User Management**: 4 endpoints (25%)
-   **Password Reset**: 3 endpoints (18.75%)
-   **OTP Reset**: 3 endpoints (18.75%)
-   **Social Login**: 3 endpoints (18.75%)

## 🚀 Quick Start Guide

### 1. Import Collection

```bash
# Download the collection file
wget https://yourdomain.com/wp-content/plugins/okhub-jwt-auth/docs/okhub-jwt-auth-postman.json

# Import into Postman
# File → Import → Select JSON file
```

### 2. Setup Environment

```bash
# Download environment file
wget https://yourdomain.com/wp-content/plugins/okhub-jwt-auth/docs/postman-environment.json

# Import into Postman
# File → Import → Select JSON file
```

### 3. Configure Variables

-   Set `base_url` to your WordPress site URL
-   Other variables will be auto-filled during testing

### 4. Start Testing

-   Begin with **Authentication** → **Register**
-   Then **Authentication** → **Login**
-   Copy tokens to environment variables
-   Test protected endpoints

## 📝 Request Examples

### Login Request

```json
POST /auth/login
{
    "email": "user@example.com",
    "password": "password123"
}
```

### Registration Request

```json
POST /auth/register
{
    "username": "user123",
    "email": "user@example.com",
    "password": "password123",
    "first_name": "John"
}
```

### Profile Update Request

```json
PUT /users/me/profile
Authorization: Bearer {{access_token}}
{
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+1234567890"
}
```

### OTP Reset Request

```json
POST /auth/password/otp/reset
{
    "email": "user@example.com",
    "otp_code": "123456",
    "new_password": "newpassword123"
}
```

### Google Login Request

```json
POST /auth/social/login
{
    "provider": "google",
    "idToken": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

## 🔍 Response Examples

### Successful Login Response

```json
{
    "success": true,
    "message": "Đăng nhập thành công",
    "data": {
        "id": 123,
        "email": "user@example.com",
        "username": "user123",
        "display_name": "John Doe"
    },
    "token": {
        "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "accessPayload": {
            "user_id": 123,
            "iat": "2024-01-01T00:00:00+00:00",
            "exp": "2024-01-01T02:00:00+00:00"
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
    "code": "invalid_credentials",
    "message": "Invalid email or password",
    "status": 401
}
```

## 🛠️ Troubleshooting

### Common Issues

1. **401 Unauthorized** - Check token validity
2. **403 Forbidden** - Check feature settings
3. **404 Not Found** - Verify endpoint URL
4. **500 Internal Server Error** - Check server logs

### Environment Variables

-   Ensure environment is selected
-   Check variable names match exactly
-   Update variables after login
-   Use double curly braces: `{{variable_name}}`

## 📚 Additional Resources

-   [Postman Usage Guide](postman-usage-guide.md)
-   [RESTful Endpoints Guide](restful-endpoints.md)
-   [Google OAuth Setup](google-oauth-setup.md)
-   [API Documentation](README.md)

---

**Happy Testing! 🚀**
