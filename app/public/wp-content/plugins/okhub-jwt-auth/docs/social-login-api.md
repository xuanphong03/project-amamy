# 📌 Social Login API (Google) – WordPress Integration

## 1. Giới thiệu

Tài liệu này mô tả cách triển khai **một endpoint duy nhất** trong WordPress để xử lý **Google Login** từ NextAuth.  
Endpoint sẽ tự động xác định 3 trường hợp:

1. **User mới** → đăng ký
2. **User đã có tài khoản Google** → đăng nhập
3. **User có local account** → merge Google account

Sau khi xử lý, endpoint trả về **JWT token** và thông tin user.

## 2. Endpoint

### URL

```
POST /wp-json/okhub-jwt/v1/social-login
```

### Request Body

```json
{
    "provider": "google",
    "email": "user@gmail.com",
    "googleId": "1234567890",
    "name": "John Doe",
    "picture": "https://example.com/avatar.jpg"
}
```

### Response (Success)

```json
{
    "success": true,
    "message": "Đăng nhập Google thành công",
    "data": {
        "id": 123,
        "username": "user",
        "email": "user@gmail.com",
        "first_name": "John",
        "last_name": "Doe",
        "display_name": "John Doe",
        "email_verified": true,
        "is_google_user": true,
        "google_picture": "https://example.com/avatar.jpg"
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

### Response (Error)

```json
{
    "success": false,
    "code": "missing_fields",
    "message": "Field 'email' is required",
    "status": 400
}
```

## 3. Luồng xử lý trong backend

### 3.1. Check user theo email

#### Nếu không tồn tại → register

-   Tạo user với:
    -   `user_login` = email (unique)
    -   `user_email` = email
    -   `display_name` = name
    -   Random password (không dùng cho Google login)
-   Lưu usermeta:
    -   `google_id` = googleId
    -   `provider` = "google"
    -   `is_google_user` = true
    -   `google_picture` = picture (optional)
    -   `google_name` = name (optional)
-   Auto verify email
-   Trả về JWT

#### Nếu tồn tại + Google user (is_google_user=true và google_id match) → login

-   Verify google_id trùng khớp
-   Check user không bị block
-   Trả về JWT

#### Nếu tồn tại + local user (is_google_user=false) → merge Google account

-   Update usermeta: `google_id`, `provider`="google", `is_google_user`=true
-   Update `display_name` nếu chưa có
-   Trả về JWT

## 4. User Meta Fields

### Google User Meta

-   `google_id`: Google ID của user
-   `provider`: "google"
-   `is_google_user`: true
-   `google_picture`: URL avatar từ Google
-   `google_name`: Tên từ Google
-   `email_verified`: true (auto verify cho Google users)
-   `email_verified_at`: timestamp khi verify

### Local User Meta (sau khi merge)

-   `google_id`: Google ID được thêm vào
-   `provider`: "google" (được update)
-   `is_google_user`: true (được update)
-   `google_picture`: URL avatar từ Google
-   `google_name`: Tên từ Google

## 5. WordPress Hooks

### Actions

-   `okhub_jwt_google_user_registered`: Khi user mới đăng ký qua Google
-   `okhub_jwt_google_user_login`: Khi user Google đăng nhập
-   `okhub_jwt_google_account_merged`: Khi merge Google account với local account

### Filters

-   `okhub_jwt_pre_google_user_registration`: Modify user data trước khi tạo user mới

## 6. Error Codes

| Code                  | Status | Description                          |
| --------------------- | ------ | ------------------------------------ |
| `missing_fields`      | 400    | Thiếu required fields                |
| `invalid_data`        | 400    | Dữ liệu không hợp lệ                 |
| `bad_request`         | 400    | Request không hợp lệ                 |
| `account_blocked`     | 403    | Tài khoản bị khóa                    |
| `account_conflict`    | 409    | Google account không khớp            |
| `service_unavailable` | 503    | Social login service không available |

## 7. Security Features

### Validation

-   Email format validation
-   Provider validation (chỉ hỗ trợ "google")
-   Google ID validation
-   Required fields validation

### Security Checks

-   Google ID mismatch protection
-   User block status check
-   Email verification auto-enable
-   Random password generation (không dùng cho Google login)

### Data Sanitization

-   Email: `sanitize_email()`
-   Google ID: `sanitize_text_field()`
-   Name: `sanitize_text_field()`
-   Picture: `esc_url_raw()`

## 8. Integration với NextAuth

### NextAuth Configuration

```javascript
// next-auth.config.js
import GoogleProvider from "next-auth/providers/google";

export default {
    providers: [
        GoogleProvider({
            clientId: process.env.GOOGLE_CLIENT_ID,
            clientSecret: process.env.GOOGLE_CLIENT_SECRET,
        }),
    ],
    callbacks: {
        async signIn({ user, account, profile }) {
            if (account.provider === "google") {
                // Call WordPress API
                const response = await fetch(
                    "/wp-json/okhub-jwt/v1/social-login",
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({
                            provider: "google",
                            email: user.email,
                            googleId: account.providerAccountId,
                            name: user.name,
                            picture: user.image,
                        }),
                    }
                );

                const data = await response.json();
                if (data.success) {
                    // Store JWT token
                    localStorage.setItem("jwt_token", data.token.accessToken);
                    return true;
                }
            }
            return false;
        },
    },
};
```

## 9. Testing

### Test Cases

#### 1. New User Registration

```bash
curl -X POST /wp-json/okhub-jwt/v1/social-login \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "google",
    "email": "newuser@gmail.com",
    "googleId": "1234567890",
    "name": "New User",
    "picture": "https://example.com/avatar.jpg"
  }'
```

#### 2. Existing Google User Login

```bash
curl -X POST /wp-json/okhub-jwt/v1/social-login \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "google",
    "email": "existing@gmail.com",
    "googleId": "1234567890",
    "name": "Existing User"
  }'
```

#### 3. Local User Merge

```bash
curl -X POST /wp-json/okhub-jwt/v1/social-login \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "google",
    "email": "localuser@example.com",
    "googleId": "9876543210",
    "name": "Local User"
  }'
```

## 10. Database Schema

### wp_users table

-   Standard WordPress user fields
-   `user_login`: Generated from email
-   `user_email`: From Google
-   `display_name`: From Google name

### wp_usermeta table

-   `google_id`: Google account ID
-   `provider`: "google"
-   `is_google_user`: true
-   `google_picture`: Avatar URL
-   `google_name`: Google display name
-   `email_verified`: true
-   `email_verified_at`: Verification timestamp

## 11. Performance Considerations

### Optimization

-   Single endpoint cho tất cả scenarios
-   Efficient user lookup by email
-   Minimal database queries
-   JWT token generation optimization
-   Session management integration

### Caching

-   User meta caching
-   Token validation caching
-   Session data caching

## 12. Monitoring & Logging

### Log Events

-   Google user registration
-   Google user login
-   Account merging
-   Security violations (Google ID mismatch)
-   Service errors

### Metrics

-   Registration success rate
-   Login success rate
-   Merge success rate
-   Error rates by type
-   Response times
