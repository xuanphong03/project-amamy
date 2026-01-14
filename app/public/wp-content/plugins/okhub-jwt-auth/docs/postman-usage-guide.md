# Postman Collection Usage Guide

## 📋 Overview

This guide explains how to use the Okhub JWT Auth Postman collection to test all API endpoints.

## 🚀 Quick Start

### 1. Import Collection

1. **Download** the `okhub-jwt-auth-postman.json` file
2. **Open Postman**
3. **Click Import** button
4. **Select** the JSON file
5. **Import** the collection

### 2. Configure Environment Variables

Before testing, you need to set up environment variables:

| Variable        | Description             | Example Value             |
| --------------- | ----------------------- | ------------------------- |
| `base_url`      | Your WordPress site URL | `https://yourdomain.com`  |
| `access_token`  | JWT access token        | (auto-filled after login) |
| `refresh_token` | JWT refresh token       | (auto-filled after login) |
| `reset_token`   | Password reset token    | (from email)              |
| `user_id`       | Current user ID         | (auto-filled after login) |
| `user_email`    | Current user email      | (auto-filled after login) |
| `username`      | Current username        | (auto-filled after login) |

**To set variables:**

1. Click **Environment** tab in Postman
2. Create new environment or use existing one
3. Add variables with their values
4. Select the environment

### 3. Auto-Save Tokens Feature

The collection includes **automatic token saving** scripts that will:

-   **Auto-save tokens** after successful login/register/social login
-   **Auto-update tokens** after refresh
-   **Save user info** (ID, email, username) to environment variables
-   **Show console logs** for debugging

**Supported endpoints with auto-save:**

-   ✅ Login
-   ✅ Register
-   ✅ Refresh Token
-   ✅ Google Login (ID Token)
-   ✅ Google Login (Access Token)
-   ✅ Google Login (Fallback)

## 🔐 Testing Authentication Flow

### Step 1: Register New User

```
POST /auth/register
```

-   **Body**: Username, email, password, first_name
-   **Response**: User info + JWT tokens

### Step 2: Login

```
POST /auth/login
```

-   **Body**: Email/username + password
-   **Response**: User info + JWT tokens
-   **Action**: ✅ **Tokens automatically saved** to environment variables

### Step 3: Test Protected Endpoints

```
GET /users/me
```

-   **Header**: `Authorization: Bearer {{access_token}}`
-   **Response**: Current user information

## 👤 User Management Testing

### Get Current User

```
GET /users/me
```

-   **Auth Required**: Yes
-   **Headers**: Authorization Bearer token

### Update Profile

```
PUT /users/me/profile
```

-   **Auth Required**: Yes
-   **Body**: first_name, last_name, phone, gender, date_of_birth

### Change Password

```
PUT /users/me/password
```

-   **Auth Required**: Yes
-   **Body**: current_password, new_password

### Get Sessions

```
GET /users/me/sessions
```

-   **Auth Required**: Yes
-   **Response**: List of active sessions

## 🔑 Password Reset Testing

### Method 1: Email Token Reset

#### Step 1: Request Password Reset

```
POST /auth/password/forgot
```

-   **Body**: email
-   **Response**: Success message
-   **Action**: Check email for reset token

#### Step 2: Reset Password

```
POST /auth/password/reset
```

-   **Body**: token, new_password
-   **Response**: Success message

#### Step 3: Validate Token (Optional)

```
GET /auth/password/validate-token?token={{reset_token}}
```

-   **Query**: token parameter
-   **Response**: Token validity

### Method 2: OTP Reset

#### Step 1: Request OTP

```
POST /auth/password/otp/request
```

-   **Body**: email
-   **Response**: Success message
-   **Action**: Check email for OTP code

#### Step 2: Reset with OTP

```
POST /auth/password/otp/reset
```

-   **Body**: email, otp_code, new_password
-   **Response**: Success message

#### Step 3: Verify OTP (Optional)

```
POST /auth/password/otp/verify
```

-   **Body**: email, otp_code
-   **Response**: OTP validity

## 🌐 Social Login Testing

### Google Login Methods

#### Method 1: ID Token (Recommended)

```
POST /auth/social/login
```

-   **Body**: provider, idToken
-   **Response**: User info + JWT tokens

#### Method 2: Access Token

```
POST /auth/social/login
```

-   **Body**: provider, accessToken
-   **Response**: User info + JWT tokens

#### Method 3: Fallback (Development Only)

```
POST /auth/social/login
```

-   **Body**: provider, email, googleId, name, picture
-   **Response**: User info + JWT tokens

## 🔄 Token Management

### Refresh Token

```
POST /auth/refresh
```

-   **Body**: refresh_token
-   **Response**: New access_token and refresh_token
-   **Action**: ✅ **Tokens automatically updated** in environment variables

### Logout

```
POST /auth/logout
```

-   **Auth Required**: Yes
-   **Response**: Success message

### Logout All Devices

```
POST /auth/logout-all
```

-   **Auth Required**: Yes
-   **Response**: Success message

## 📝 Request Examples

### Login Request

```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

### Registration Request

```json
{
    "username": "user123",
    "email": "user@example.com",
    "password": "password123",
    "first_name": "John"
}
```

### Profile Update Request

```json
{
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+1234567890",
    "gender": "male",
    "date_of_birth": "1990-01-01"
}
```

### Password Change Request

```json
{
    "current_password": "oldpassword123",
    "new_password": "newpassword123"
}
```

### OTP Reset Request

```json
{
    "email": "user@example.com",
    "otp_code": "123456",
    "new_password": "newpassword123"
}
```

### Google Login Request

```json
{
    "provider": "google",
    "idToken": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

## 🎯 Testing Scenarios

### Scenario 1: Complete User Flow

1. **Register** new user
2. **Login** with credentials
3. **Get** user profile
4. **Update** profile information
5. **Change** password
6. **Logout**

### Scenario 2: Password Reset Flow

1. **Request** password reset
2. **Check** email for token
3. **Reset** password with token
4. **Login** with new password

### Scenario 3: OTP Reset Flow

1. **Request** OTP
2. **Check** email for OTP code
3. **Reset** password with OTP
4. **Login** with new password

### Scenario 4: Social Login Flow

1. **Get** Google ID token from frontend
2. **Login** with Google token
3. **Access** protected endpoints
4. **Logout**

## 🔧 Troubleshooting

### Common Issues

#### 1. 401 Unauthorized

-   **Cause**: Invalid or expired token
-   **Solution**: Refresh token or login again

#### 2. 403 Forbidden

-   **Cause**: Feature disabled in settings
-   **Solution**: Check WordPress admin settings

#### 3. 404 Not Found

-   **Cause**: Wrong endpoint URL
-   **Solution**: Verify base_url and endpoint path

#### 4. 500 Internal Server Error

-   **Cause**: Server configuration issue
-   **Solution**: Check WordPress error logs

### Environment Variables Not Working

1. **Check** environment is selected
2. **Verify** variable names match exactly
3. **Update** variables after login
4. **Use** double curly braces: `{{variable_name}}`

### Token Not Auto-Filling

1. **Check** if environment is selected
2. **Verify** auto-save scripts are enabled in collection
3. **Check** console logs for error messages
4. **Manually** copy tokens from login response if needed
5. **Paste** into environment variables
6. **Save** environment
7. **Re-run** requests

## 📚 Additional Resources

-   [Postman Documentation](https://learning.postman.com/)
-   [WordPress REST API](https://developer.wordpress.org/rest-api/)
-   [JWT Authentication](https://jwt.io/)
-   [Google OAuth](https://developers.google.com/identity/protocols/oauth2)

## 🎉 Success Indicators

### Successful Login Response

```json
{
    "success": true,
    "message": "Đăng nhập thành công",
    "data": {
        "id": 123,
        "email": "user@example.com",
        "username": "user123"
    },
    "token": {
        "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
    }
}
```

### Successful Profile Update

```json
{
    "success": true,
    "message": "Profile updated successfully",
    "data": {
        "id": 123,
        "first_name": "John",
        "last_name": "Doe"
    }
}
```

### Successful Password Reset

```json
{
    "success": true,
    "message": "Password reset successfully"
}
```

---

**Happy Testing! 🚀**
