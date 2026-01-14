# RESTful API Endpoints

## 📋 Overview

All API endpoints have been updated to follow RESTful conventions with proper resource grouping and HTTP methods.

## 🔐 Authentication Endpoints

### Base URL: `/wp-json/okhub-jwt/v1/auth/`

| Method | Endpoint           | Description                                 | Auth Required |
| ------ | ------------------ | ------------------------------------------- | ------------- |
| `POST` | `/auth/login`      | User login with email/username and password | No            |
| `POST` | `/auth/register`   | User registration                           | No            |
| `POST` | `/auth/refresh`    | Refresh access token                        | No            |
| `POST` | `/auth/logout`     | Logout current session                      | Yes           |
| `POST` | `/auth/logout-all` | Logout from all devices                     | Yes           |

### Password Reset Endpoints

| Method | Endpoint                        | Description                   | Auth Required |
| ------ | ------------------------------- | ----------------------------- | ------------- |
| `POST` | `/auth/password/forgot`         | Request password reset email  | No            |
| `POST` | `/auth/password/reset`          | Reset password with token     | No            |
| `GET`  | `/auth/password/validate-token` | Validate password reset token | No            |

### OTP Password Reset Endpoints

| Method | Endpoint                     | Description                    | Auth Required |
| ------ | ---------------------------- | ------------------------------ | ------------- |
| `POST` | `/auth/password/otp/request` | Request OTP for password reset | No            |
| `POST` | `/auth/password/otp/reset`   | Reset password with OTP        | No            |
| `POST` | `/auth/password/otp/verify`  | Verify OTP only                | No            |

### Social Login Endpoints

| Method | Endpoint             | Description         | Auth Required |
| ------ | -------------------- | ------------------- | ------------- |
| `POST` | `/auth/social/login` | Google social login | No            |

## 👤 User Management Endpoints

### Base URL: `/wp-json/okhub-jwt/v1/users/me/`

| Method | Endpoint             | Description                  | Auth Required |
| ------ | -------------------- | ---------------------------- | ------------- |
| `GET`  | `/users/me`          | Get current user information | Yes           |
| `PUT`  | `/users/me/profile`  | Update user profile          | Yes           |
| `PUT`  | `/users/me/password` | Change user password         | Yes           |
| `GET`  | `/users/me/sessions` | Get user active sessions     | Yes           |

## 🔄 Migration from Old Endpoints

### Old → New Endpoint Mapping

| Old Endpoint            | New Endpoint                    | Notes                   |
| ----------------------- | ------------------------------- | ----------------------- |
| `/login`                | `/auth/login`                   | ✅ Updated              |
| `/register`             | `/auth/register`                | ✅ Updated              |
| `/refresh`              | `/auth/refresh`                 | ✅ Updated              |
| `/logout`               | `/auth/logout`                  | ✅ Updated              |
| `/logout-all`           | `/auth/logout-all`              | ✅ Updated              |
| `/me`                   | `/users/me`                     | ✅ Updated              |
| `/profile`              | `/users/me/profile`             | ✅ Updated (PUT method) |
| `/change-password`      | `/users/me/password`            | ✅ Updated (PUT method) |
| `/sessions`             | `/users/me/sessions`            | ✅ Updated              |
| `/forgot-password`      | `/auth/password/forgot`         | ✅ Updated              |
| `/reset-password`       | `/auth/password/reset`          | ✅ Updated              |
| `/validate-reset-token` | `/auth/password/validate-token` | ✅ Updated              |
| `/forgot-password-otp`  | `/auth/password/otp/request`    | ✅ Updated              |
| `/reset-password-otp`   | `/auth/password/otp/reset`      | ✅ Updated              |
| `/verify-otp`           | `/auth/password/otp/verify`     | ✅ Updated              |
| `/social-login`         | `/auth/social/login`            | ✅ Updated              |

## 🎯 RESTful Design Principles

### 1. Resource-Based URLs

-   **Before**: `/login`, `/register`, `/logout`
-   **After**: `/auth/login`, `/auth/register`, `/auth/logout`

### 2. HTTP Methods

-   **GET**: Retrieve data (user info, sessions)
-   **POST**: Create actions (login, register, password reset)
-   **PUT**: Update resources (profile, password)

### 3. Hierarchical Structure

```
/auth/                    # Authentication actions
  /login                  # User login
  /register              # User registration
  /refresh               # Token refresh
  /logout                # User logout
  /password/             # Password-related actions
    /forgot              # Request password reset
    /reset               # Reset password
    /validate-token      # Validate reset token
    /otp/                # OTP-related actions
      /request           # Request OTP
      /reset             # Reset with OTP
      /verify            # Verify OTP
  /social/               # Social login
    /login               # Social authentication

/users/me/               # Current user resources
  /                      # User information
  /profile               # User profile
  /password              # User password
  /sessions              # User sessions
```

### 4. Consistent Naming

-   **Actions**: Use verbs in URL (`/forgot`, `/reset`, `/verify`)
-   **Resources**: Use nouns (`/users`, `/sessions`, `/profile`)
-   **Hierarchy**: Use forward slashes for nesting (`/auth/password/otp`)

## 📝 Request Examples

### Authentication

```bash
# Login
curl -X POST /wp-json/okhub-jwt/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password123"}'

# Register
curl -X POST /wp-json/okhub-jwt/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username": "user123", "email": "user@example.com", "password": "password123"}'
```

### User Management

```bash
# Get current user
curl -X GET /wp-json/okhub-jwt/v1/users/me \
  -H "Authorization: Bearer {access_token}"

# Update profile
curl -X PUT /wp-json/okhub-jwt/v1/users/me/profile \
  -H "Authorization: Bearer {access_token}" \
  -H "Content-Type: application/json" \
  -d '{"first_name": "John", "last_name": "Doe"}'
```

### Password Reset

```bash
# Request password reset
curl -X POST /wp-json/okhub-jwt/v1/auth/password/forgot \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'

# Reset with OTP
curl -X POST /wp-json/okhub-jwt/v1/auth/password/otp/reset \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "otp_code": "123456", "new_password": "newpass123"}'
```

### Social Login

```bash
# Google login with ID token
curl -X POST /wp-json/okhub-jwt/v1/auth/social/login \
  -H "Content-Type: application/json" \
  -d '{"provider": "google", "idToken": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."}'
```

## 🔧 Backward Compatibility

**Note**: Old endpoints are no longer supported. All clients must update to use the new RESTful endpoints.

## 📚 Additional Resources

-   [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
-   [RESTful API Design Best Practices](https://restfulapi.net/)
-   [HTTP Status Codes](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status)
