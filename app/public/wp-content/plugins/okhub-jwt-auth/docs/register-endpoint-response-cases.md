# Register Endpoint Response Cases

## Endpoint: `/wp-json/okhub-jwt/v1/auth/register`

### Method: POST

## Request Parameters

| Parameter       | Type   | Required | Description                          |
| --------------- | ------ | -------- | ------------------------------------ |
| `username`      | string | Yes      | Username (min 3 characters)          |
| `email`         | string | Yes      | Valid email address                  |
| `password`      | string | Yes      | Password (min 6 characters)          |
| `first_name`    | string | No       | User's first name                    |
| `customer_code` | string | No       | Customer code (must exist in system) |

## Response Cases

### 1. Success Cases

#### 1.1 Registration Success - Email Verification Disabled

**Status Code:** `201 Created`

```json
{
    "success": true,
    "message": "Đăng ký thành công",
    "data": {
        "id": 123,
        "username": "user123",
        "email": "user@example.com",
        "first_name": "John",
        "last_name": "",
        "display_name": "John",
        "customer_code": "CUST001",
        "registered": "2024-01-01 10:00:00",
        "roles": ["subscriber"],
        "capabilities": ["read"],
        "email_verified": true
    },
    "token": {
        "accessToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refreshToken": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refreshPayload": {
            "user_id": 123,
            "iat": "2024-01-01T10:00:00+00:00",
            "exp": "2024-01-08T10:00:00+00:00"
        },
        "accessPayload": {
            "user_id": 123,
            "iat": "2024-01-01T10:00:00+00:00",
            "exp": "2024-01-01T11:00:00+00:00"
        }
    }
}
```

#### 1.2 Registration Success - Email Verification Enabled

**Status Code:** `201 Created`

```json
{
    "success": true,
    "message": "Đăng ký thành công. Vui lòng kiểm tra email để xác thực tài khoản.",
    "data": {
        "id": 123,
        "username": "user123",
        "email": "user@example.com",
        "first_name": "John",
        "last_name": "",
        "display_name": "John",
        "customer_code": "CUST001",
        "registered": "2024-01-01 10:00:00",
        "roles": ["subscriber"],
        "capabilities": ["read"],
        "email_verified": false,
        "requires_verification": true
    }
}
```

### 2. Error Cases

#### 2.1 Missing Required Fields

**Status Code:** `400 Bad Request`

```json
{
    "code": "missing_fields",
    "message": "Username, email and password are required",
    "data": {
        "status": 400
    }
}
```

#### 2.2 Username Already Exists

**Status Code:** `400 Bad Request`

```json
{
    "code": "registration_failed",
    "message": "Username đã được sử dụng",
    "data": {
        "status": 400
    }
}
```

**Note:** This error comes from AuthService with status 422, but gets wrapped in WP_Error with status 400 in RestApi.

#### 2.3 Email Already Exists

**Status Code:** `400 Bad Request`

```json
{
    "code": "registration_failed",
    "message": "Email đã được sử dụng",
    "data": {
        "status": 400
    }
}
```

**Note:** This error comes from AuthService with status 422, but gets wrapped in WP_Error with status 400 in RestApi.

#### 2.4 Invalid Customer Code

**Status Code:** `400 Bad Request`

```json
{
    "code": "customer_code_exists",
    "message": "Customer code not found",
    "data": {
        "status": 400
    }
}
```

#### 2.5 Username Required When Username Login Enabled

**Status Code:** `400 Bad Request`

```json
{
    "code": "missing_username",
    "message": "Username is required when username login is enabled",
    "data": {
        "status": 400
    }
}
```

#### 2.6 WordPress User Creation Error (General)

**Status Code:** `400 Bad Request`

```json
{
    "code": "registration_failed",
    "message": "WordPress error message from wp_create_user()",
    "data": {
        "status": 400
    }
}
```

#### 2.7 OTP Generation Failed

**Status Code:** `500 Internal Server Error`

```json
{
    "code": "registration_failed",
    "message": "Không thể tạo mã OTP xác thực",
    "data": {
        "status": 500
    }
}
```

**Note:** This error comes from AuthService with status 500, but gets wrapped in WP_Error with status 400 in RestApi.

#### 2.8 Email Sending Failed

**Status Code:** `500 Internal Server Error`

```json
{
    "code": "registration_failed",
    "message": "Không thể gửi email xác thực",
    "data": {
        "status": 500
    }
}
```

**Note:** This error comes from AuthService with status 500, but gets wrapped in WP_Error with status 400 in RestApi.

#### 2.9 WordPress User Creation Error (wp_create_user failed)

**Status Code:** `400 Bad Request`

```json
{
    "code": "registration_failed",
    "message": "WordPress error message from wp_create_user()",
    "data": {
        "status": 400
    }
}
```

**Possible WordPress errors:**

-   `existing_user_login` - Username already exists
-   `existing_user_email` - Email already exists
-   `empty_user_login` - Username is empty
-   `empty_user_email` - Email is empty
-   `invalid_email` - Invalid email format
-   `user_login_too_long` - Username too long
-   `user_email_too_long` - Email too long
-   `invalid_username` - Invalid username characters

#### 2.10 User Profile Update Error (wp_update_user failed)

**Status Code:** `400 Bad Request`

```json
{
    "code": "registration_failed",
    "message": "WordPress error message from wp_update_user()",
    "data": {
        "status": 400
    }
}
```

**Possible WordPress errors:**

-   `empty_user_name` - Display name is empty
-   `user_name_too_long` - Display name too long
-   `invalid_user_id` - Invalid user ID

### 3. Validation Errors

#### 3.1 Invalid Email Format

**Status Code:** `400 Bad Request`

```json
{
    "code": "rest_invalid_param",
    "message": "Invalid parameter(s): email",
    "data": {
        "status": 400,
        "params": {
            "email": "Invalid email format"
        }
    }
}
```

#### 3.2 Username Too Short

**Status Code:** `400 Bad Request`

```json
{
    "code": "rest_invalid_param",
    "message": "Invalid parameter(s): username",
    "data": {
        "status": 400,
        "params": {
            "username": "Username must be at least 3 characters long"
        }
    }
}
```

#### 3.3 Password Too Short

**Status Code:** `400 Bad Request`

```json
{
    "code": "rest_invalid_param",
    "message": "Invalid parameter(s): password",
    "data": {
        "status": 400,
        "params": {
            "password": "Password must be at least 6 characters long"
        }
    }
}
```

## Business Logic Flow

### 1. Email Verification Disabled

1. Validate required fields
2. Check username uniqueness
3. Check email uniqueness
4. Validate customer code (if provided)
5. Create WordPress user
6. Generate JWT tokens
7. Send welcome email (if enabled)
8. Return success with tokens

### 2. Email Verification Enabled

1. Validate required fields
2. Check username uniqueness
3. Check email uniqueness
4. Validate customer code (if provided)
5. Create WordPress user (unverified)
6. Generate OTP
7. Send OTP verification email
8. Return success without tokens

## Configuration Options

| Option                                | Default | Description                       |
| ------------------------------------- | ------- | --------------------------------- |
| `okhub_jwt_enable_email_verification` | `true`  | Enable/disable email verification |
| `okhub_jwt_enable_username_login`     | `false` | Enable/disable username login     |
| `okhub_jwt_enable_welcome_email`      | `true`  | Enable/disable welcome email      |

## WordPress Hooks

### Actions

-   `okhub_jwt_user_registered` - Fired after user registration
-   `okhub_jwt_email_verified` - Fired after email verification

### Filters

-   `okhub_jwt_pre_user_registration` - Modify user data before registration
-   `okhub_jwt_post_user_registration` - Modify user object after registration

## Notes

1. **Customer Code Validation**: If `customer_code` is provided, it must exist as a term in the `customer_code` taxonomy
2. **Email Verification**: When enabled, users must verify their email before receiving JWT tokens
3. **Username Generation**: If username login is disabled, username is still required for WordPress compatibility
4. **Password Security**: Passwords are hashed using WordPress's built-in password hashing
5. **Error Messages**: All error messages are in Vietnamese as per plugin configuration
6. **Status Code Mapping**: Some errors from AuthService (422, 500) get wrapped as 400 in RestApi layer
7. **WordPress Error Handling**: All WordPress errors from `wp_create_user()` and `wp_update_user()` are passed through as-is
8. **Duplicate Validation**: Both plugin-level and WordPress-level validation check for existing username/email
