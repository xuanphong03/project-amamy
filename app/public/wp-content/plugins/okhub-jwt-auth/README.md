# 🔐 Okhub JWT Auth Plugin

Plugin xác thực JWT (JSON Web Token) mạnh mẽ cho WordPress với hỗ trợ đa thiết bị, bảo mật cao và quản lý session thông minh.

## ✨ Tính năng chính

### 🔑 Xác thực JWT

-   **Login/Register** với email và password
-   **Token-based authentication** thay thế session WordPress
-   **Access Token** (2 giờ) và **Refresh Token** (7 ngày)
-   **Token rotation** - Tự động tạo refresh token mới mỗi lần refresh

### 📱 Multi-Device Support

-   **Đăng nhập đa thiết bị** - Mỗi thiết bị có session riêng biệt
-   **Device tracking** - Theo dõi thông tin thiết bị (OS, Browser, IP)
-   **Selective logout** - Đăng xuất từng thiết bị hoặc tất cả
-   **Session management** - Quản lý và giám sát tất cả sessions

### 🛡️ Bảo mật nâng cao

-   **Token blacklisting** - Vô hiệu hóa tokens khi logout
-   **Automatic cleanup** - Tự động xóa expired tokens
-   **IP tracking** - Theo dõi địa chỉ IP của từng session
-   **Session validation** - Kiểm tra tính hợp lệ của session

### 📧 Email Services

-   **Password reset** - Gửi email reset mật khẩu
-   **Welcome email** - Email chào mừng khi đăng ký
-   **Customizable templates** - Tùy chỉnh nội dung email

### 🔐 Email Verification & OTP

-   **Email Verification** - Có thể bật/tắt xác thực email khi đăng ký
-   **Registration OTP** - Xác thực email bằng OTP khi đăng ký (nếu bật)
-   **Password reset OTP** - Đặt lại mật khẩu bằng OTP (tùy chọn)
-   **Flexible Settings** - Cấu hình linh hoạt cho từng tính năng
-   **Resend OTP** - Gửi lại mã OTP nếu cần

### 🌐 Social Login

-   **Google Login** - Đăng nhập/đăng ký qua Google
-   **Auto account merge** - Tự động liên kết với tài khoản local
-   **Email verification** - Tự động verify email cho Google users
-   **Unified JWT response** - Trả về JWT token giống login thường

## 🚀 Cài đặt

### Yêu cầu hệ thống

-   WordPress 5.0+
-   PHP 7.4+
-   MySQL 5.6+ hoặc MariaDB 10.1+
-   Composer (để cài đặt dependencies)

### Bước 1: Cài đặt plugin

```bash
# Clone hoặc download plugin vào thư mục wp-content/plugins/
cd wp-content/plugins/
git clone [repository-url] okhub-jwt-auth
cd okhub-jwt-auth

# Cài đặt dependencies
composer install
```

### Bước 2: Kích hoạt plugin

1. Vào **WordPress Admin** → **Plugins**
2. Tìm **Okhub JWT Auth** và click **Activate**
3. Plugin sẽ tự động tạo database tables cần thiết

### Bước 3: Cấu hình (tùy chọn)

#### Google OAuth Setup (nếu sử dụng Social Login)

1. **Google Cloud Console:**

    - Tạo project mới hoặc chọn project hiện có
    - Enable Google+ API hoặc Google Identity API
    - Tạo OAuth 2.0 credentials
    - Set redirect URI: `https://yourdomain.com/wp-json/okhub-jwt/v1/social-login`

2. **WordPress Admin:**

    - Vào **Okhub JWT Auth Settings**
    - Enable **Social Login**
    - Paste **Google Client ID** và **Client Secret**
    - Configure các options khác

3. Vào **WordPress Admin** → **Settings** → **JWT Auth**
4. Tùy chỉnh:
    - JWT Secret Key
    - Token expiration times
    - Email settings
    - Security options

## 🧪 Testing với Postman

### Import Postman Collection

1. **Download** `docs/okhub-jwt-auth-postman.json`
2. **Import** vào Postman
3. **Import** environment từ `docs/postman-environment.json`
4. **Set** `base_url` variable
5. **Start testing** các endpoints

### ✨ Auto-Save Tokens Feature

Collection bao gồm **script tự động lưu tokens**:

-   **Auto-save tokens** sau khi login/register/social login thành công
-   **Auto-update tokens** sau khi refresh
-   **Save user info** (ID, email, username) vào environment variables
-   **Console logs** để debug

**Endpoints hỗ trợ auto-save:**

-   ✅ Login
-   ✅ Register
-   ✅ Refresh Token
-   ✅ Google Login (tất cả methods)

### Quick Test Flow

1. **Register** user mới → ✅ Tokens tự động lưu
2. **Login** để lấy JWT tokens → ✅ Tokens tự động lưu
3. **Test** protected endpoints → Sử dụng `{{access_token}}`
4. **Try** password reset flows
5. **Test** social login (nếu configured) → ✅ Tokens tự động lưu

Xem chi tiết trong [Postman Usage Guide](docs/postman-usage-guide.md)

## 📚 API Endpoints

### Base URL

```
/wp-json/okhub-jwt/v1/
```

### 🔐 Authentication APIs

#### 1. Đăng ký tài khoản

```http
POST /auth/register
Content-Type: application/json

{
    "username": "johndoe",
    "email": "john@example.com",
    "password": "securepassword123",
    "first_name": "John"
}
```

**Response (Registration - requires OTP verification):**

```json
{
    "success": true,
    "message": "Đăng ký thành công. Vui lòng kiểm tra email để xác thực tài khoản.",
    "data": {
        "id": 123,
        "username": "johndoe",
        "email": "john@example.com",
        "first_name": "John",
        "display_name": "John",
        "registered": "2025-01-19 10:00:00",
        "roles": ["subscriber"],
        "capabilities": ["read"],
        "email_verified": false,
        "requires_verification": true
    }
}
```

#### 2. Xác thực OTP đăng ký

```http
POST /auth/register/verify
Content-Type: application/json

{
    "email": "john@example.com",
    "otp_code": "123456"
}
```

**Response (After OTP verification):**

```json
{
    "success": true,
    "message": "Xác thực email thành công",
    "data": {
        "id": 123,
        "username": "johndoe",
        "email": "john@example.com",
        "first_name": "John",
        "display_name": "John",
        "registered": "2025-01-19 10:00:00",
        "roles": ["subscriber"],
        "capabilities": ["read"],
        "email_verified": true
    },
    "token": {
        "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refreshPayload": {
            "iss": "https://yoursite.com",
            "user_id": 123,
            "type": "refresh",
            "session_id": "abc123def456ghi789",
            "iat": "2025-01-19T08:00:00+00:00",
            "exp": "2025-01-26T08:00:00+00:00"
        },
        "accessPayload": {
            "iss": "https://yoursite.com",
            "user_id": 123,
            "type": "access",
            "session_id": "abc123def456ghi789",
            "iat": "2025-01-19T08:00:00+00:00",
            "exp": "2025-01-19T10:00:00+00:00"
        }
    }
}
```

#### 3. Gửi lại OTP đăng ký

```http
POST /auth/register/resend-otp
Content-Type: application/json

{
    "email": "john@example.com"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Mã OTP mới đã được gửi đến email của bạn"
}
```

#### 4. Đăng nhập

```http
POST /auth/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "securepassword123"
}
```

**Response:** Tương tự như register, nhưng không có `data` user info.

**Lưu ý:** User phải đã xác thực email trước khi có thể đăng nhập.

#### 3. Refresh Token

```http
POST /refresh-token
Content-Type: application/json

{
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

**Response:**

```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "token": {
        "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "refreshPayload": {...},
        "accessPayload": {...}
    }
}
```

#### 4. Đăng xuất

```http
POST /logout
Authorization: Bearer {accessToken}
```

**Response:**

```json
{
    "success": true,
    "message": "Đăng xuất thành công"
}
```

#### 5. Đăng xuất tất cả thiết bị

```http
POST /logout-all
Authorization: Bearer {accessToken}
```

**Response:**

```json
{
    "success": true,
    "message": "Đã đăng xuất tất cả thiết bị"
}
```

### 👤 User Management APIs

#### 6. Lấy thông tin user hiện tại

```http
GET /me
Authorization: Bearer {accessToken}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 123,
        "username": "johndoe",
        "email": "john@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "display_name": "John Doe",
        "roles": ["subscriber"],
        "capabilities": ["read", "level_0", "subscriber"]
    }
}
```

#### 7. Xem tất cả thiết bị đang đăng nhập

```http
GET /sessions
Authorization: Bearer {accessToken}
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "session_id": "abc123def456ghi789",
            "device_info": {
                "device_type": "mobile",
                "platform": "iOS",
                "browser": "Safari",
                "ip_address": "192.168.1.100"
            },
            "created_at": "2025-01-19 08:00:00",
            "last_used": "2025-01-19 10:30:00",
            "expires_at": "2025-01-26 08:00:00",
            "is_current": true
        },
        {
            "session_id": "xyz789abc123def456",
            "device_info": {
                "device_type": "desktop",
                "platform": "Windows",
                "browser": "Chrome",
                "ip_address": "192.168.1.101"
            },
            "created_at": "2025-01-19 09:00:00",
            "last_used": "2025-01-19 11:15:00",
            "expires_at": "2025-01-26 09:00:00",
            "is_current": false
        }
    ]
}
```

### 🔒 Password Management APIs

#### 8. Quên mật khẩu

```http
POST /forgot-password
Content-Type: application/json

{
    "email": "john@example.com"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Email reset mật khẩu đã được gửi"
}
```

#### 9. Reset mật khẩu

```http
POST /reset-password
Content-Type: application/json

{
    "token": "reset_token_here",
    "password": "newpassword123"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Mật khẩu đã được thay đổi thành công"
}
```

#### 10. Thay đổi mật khẩu

```http
POST /change-password
Authorization: Bearer {accessToken}
Content-Type: application/json

{
    "current_password": "oldpassword123",
    "new_password": "newpassword123"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Mật khẩu đã được thay đổi thành công"
}
```

#### 11. Cập nhật profile

```http
POST /update-profile
Authorization: Bearer {accessToken}
Content-Type: application/json

{
    "first_name": "John Updated",
    "last_name": "Doe Updated",
    "display_name": "John Doe Updated"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Profile đã được cập nhật thành công",
    "data": {
        "id": 123,
        "username": "johndoe",
        "email": "john@example.com",
        "first_name": "John Updated",
        "last_name": "Doe Updated",
        "display_name": "John Doe Updated"
    }
}
```

### 🌐 Social Login APIs

#### 12. Google Login

**Token-based Authentication (Recommended):**

```http
POST /auth/social/login
Content-Type: application/json

{
    "provider": "google",
    "idToken": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Access Token Authentication:**

```http
POST /auth/social/login
Content-Type: application/json

{
    "provider": "google",
    "accessToken": "ya29.a0AfH6SMC..."
}
```

**Fallback Authentication (Less Secure):**

```http
POST /auth/social/login
Content-Type: application/json

{
    "provider": "google",
    "email": "user@gmail.com",
    "googleId": "1234567890",
    "name": "John Doe",
    "picture": "https://example.com/avatar.jpg"
}
```

**Response:**

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
        "accessPayload": {...},
        "refreshPayload": {...}
    }
}
```

**Luồng xử lý:**

1. **User mới** → Tự động đăng ký với Google info + `email_verified: true`
2. **User Google đã có** → Đăng nhập trực tiếp + `email_verified: true`
3. **User local** → Merge với Google account + `email_verified: true`

**Lưu ý:** Tất cả Google users đều có `email_verified: true` tự động (nếu setting "Auto Verify Google Email" được bật).

**Security Features:**

-   **Token Verification** - Verify Google tokens với Google API Client
-   **Multiple Auth Methods** - ID Token, Access Token, Authorization Code
-   **Fallback Mode** - Cho development/testing (không khuyến khích production)
-   **Account Merging** - Tự động merge Google với local account
-   **Email Verification** - Auto verify email cho Google users

## 🔧 Cấu hình

### WordPress Admin Settings

Truy cập **WordPress Admin → Settings → JWT Auth** để cấu hình:

#### 🔑 Core JWT Settings

-   **JWT Secret Key** - Khóa bí mật để ký tokens
-   **Access Token Expiry** - Thời hạn access token (mặc định: 7200 giây)
-   **Refresh Token Expiry** - Thời hạn refresh token (mặc định: 604800 giây)
-   **Enable Refresh Tokens** - Bật/tắt refresh tokens
-   **Enable Username Login** - Cho phép đăng nhập bằng username

#### 📧 Registration & Email Verification

-   **Enable Email Verification** - Bắt buộc xác thực email khi đăng ký
-   **Enable Welcome Email** - Gửi email chào mừng sau đăng ký

#### 🔐 Password Reset Settings

-   **Enable Password Reset** - Bật/tắt chức năng reset mật khẩu
-   **Password Reset Token Expiry** - Thời hạn token reset (mặc định: 3600 giây)
-   **Enable URL-based Password Reset Email** - Gửi email với link reset
-   **Enable OTP-based Password Reset** - Reset mật khẩu bằng OTP
-   **OTP Expiry** - Thời hạn OTP (mặc định: 300 giây)
-   **OTP Max Attempts** - Số lần thử tối đa (mặc định: 3)

#### 📬 Email Notifications

-   **Enable Password Changed Email** - Thông báo khi đổi mật khẩu

#### 🌐 Social Login Settings

-   **Enable Social Login** - Bật/tắt đăng nhập qua Google
-   **Google Client ID** - ID ứng dụng Google
-   **Google Client Secret** - Secret key Google
-   **Auto Verify Google Email** - Tự động verify email cho Google users
-   **Allow Account Merge** - Cho phép liên kết tài khoản

### Programmatic Configuration

```php
// Core JWT Settings
update_option('okhub_jwt_secret', 'your-super-secret-key-here');
update_option('okhub_jwt_expire', 7200);        // 2 giờ
update_option('okhub_jwt_refresh_expire', 604800); // 7 ngày
update_option('okhub_jwt_enable_refresh_tokens', true);
update_option('okhub_jwt_enable_username_login', false);

// Registration & Email Verification
update_option('okhub_jwt_enable_email_verification', true);
update_option('okhub_jwt_enable_welcome_email', true);

// Password Reset
update_option('okhub_jwt_enable_password_reset', true);
update_option('okhub_jwt_password_reset_expire', 3600);
update_option('okhub_jwt_enable_password_reset_email', true);
update_option('okhub_jwt_enable_otp_reset', false);
update_option('okhub_jwt_otp_expire', 300);
update_option('okhub_jwt_otp_max_attempts', 3);

// Email Notifications
update_option('okhub_jwt_enable_password_changed_email', true);

// Social Login
update_option('okhub_jwt_enable_social_login', true);
update_option('okhub_jwt_google_client_id', 'your-client-id');
update_option('okhub_jwt_google_client_secret', 'your-client-secret');
update_option('okhub_jwt_auto_verify_google_email', true);
update_option('okhub_jwt_allow_account_merge', true);
```

### OTP Settings

```php
// WordPress Admin → Okhub JWT Auth Settings
update_option('okhub_jwt_enable_otp_reset', true);
update_option('okhub_jwt_otp_expire', 300);        // 5 phút
update_option('okhub_jwt_otp_max_attempts', 3);    // 3 lần thử
```

### Email Settings

```php
// Tùy chỉnh email templates
add_filter('okhub_jwt_auth_reset_password_email_subject', function($subject) {
    return 'Reset mật khẩu - ' . get_bloginfo('name');
});

add_filter('okhub_jwt_auth_welcome_email_subject', function($subject) {
    return 'Chào mừng bạn đến với ' . get_bloginfo('name');
});
```

## 🛡️ Bảo mật

### Token Security

-   **JWT Secret Key** - Sử dụng key mạnh, ít nhất 32 ký tự
-   **Token Expiration** - Access token ngắn hạn, refresh token dài hạn
-   **Token Rotation** - Refresh token mới mỗi lần sử dụng
-   **Blacklisting** - Vô hiệu hóa tokens khi logout

### Session Security

-   **Device Tracking** - Theo dõi thông tin thiết bị
-   **IP Validation** - Kiểm tra địa chỉ IP
-   **Session Expiration** - Tự động hết hạn sau 7 ngày
-   **Multi-device Control** - Quản lý từng session riêng biệt

### Database Security

-   **Token Hashing** - Lưu hash thay vì plain text
-   **SQL Injection Protection** - Sử dụng prepared statements
-   **Automatic Cleanup** - Xóa expired tokens định kỳ

## 📊 Database Tables

### 1. `wp_okhub_jwt_blacklist`

```sql
CREATE TABLE wp_okhub_jwt_blacklist (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    token_hash varchar(255) NOT NULL,
    user_id bigint(20) NOT NULL,
    expires_at datetime NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY token_hash (token_hash),
    KEY user_id (user_id),
    KEY expires_at (expires_at)
);
```

### 2. `wp_okhub_jwt_sessions`

```sql
CREATE TABLE wp_okhub_jwt_sessions (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    session_id varchar(64) NOT NULL,
    device_info text,
    access_token_hash varchar(255),
    refresh_token_hash varchar(255),
    access_token text,
    refresh_token text,
    expires_at datetime NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    last_used datetime DEFAULT CURRENT_TIMESTAMP,
    is_active tinyint(1) DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY session_id (session_id),
    KEY user_id (user_id),
    KEY expires_at (expires_at),
    KEY is_active (is_active)
);
```

### 3. `wp_okhub_jwt_reset_tokens`

```sql
CREATE TABLE wp_okhub_jwt_reset_tokens (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    token varchar(255) NOT NULL,
    expires_at datetime NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY token (token),
    KEY user_id (user_id),
    KEY expires_at (expires_at)
);
```

## 🚀 Sử dụng với Frontend

### JavaScript Example

```javascript
class JWTClient {
    constructor() {
        this.baseUrl = "/wp-json/okhub-jwt/v1";
        this.accessToken = localStorage.getItem("accessToken");
        this.refreshToken = localStorage.getItem("refreshToken");
    }

    async login(email, password) {
        try {
            const response = await fetch(`${this.baseUrl}/login`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ email, password }),
            });

            const data = await response.json();

            if (data.success) {
                localStorage.setItem("accessToken", data.token.accessToken);
                localStorage.setItem("refreshToken", data.token.refreshToken);
                return data;
            }
        } catch (error) {
            console.error("Login failed:", error);
        }
    }

    async getProfile() {
        try {
            const response = await fetch(`${this.baseUrl}/me`, {
                headers: { Authorization: `Bearer ${this.accessToken}` },
            });

            return await response.json();
        } catch (error) {
            console.error("Get profile failed:", error);
        }
    }

    async logout() {
        try {
            await fetch(`${this.baseUrl}/logout`, {
                method: "POST",
                headers: { Authorization: `Bearer ${this.accessToken}` },
            });

            localStorage.removeItem("accessToken");
            localStorage.removeItem("refreshToken");
        } catch (error) {
            console.error("Logout failed:", error);
        }
    }
}

// Sử dụng
const client = new JWTClient();
client.login("user@example.com", "password123");
```

### React Hook Example

```javascript
import { useState, useEffect } from "react";

const useJWT = () => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(false);

    const login = async (email, password) => {
        setLoading(true);
        try {
            const response = await fetch("/wp-json/okhub-jwt/v1/login", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ email, password }),
            });

            const data = await response.json();

            if (data.success) {
                localStorage.setItem("accessToken", data.token.accessToken);
                localStorage.setItem("refreshToken", data.token.refreshToken);
                setUser(data.data);
                return data;
            }
        } catch (error) {
            console.error("Login failed:", error);
        } finally {
            setLoading(false);
        }
    };

    const logout = async () => {
        try {
            const token = localStorage.getItem("accessToken");
            if (token) {
                await fetch("/wp-json/okhub-jwt/v1/logout", {
                    method: "POST",
                    headers: { Authorization: `Bearer ${token}` },
                });
            }
        } catch (error) {
            console.error("Logout failed:", error);
        } finally {
            localStorage.removeItem("accessToken");
            localStorage.removeItem("refreshToken");
            setUser(null);
        }
    };

    return { user, loading, login, logout };
};

export default useJWT;
```

## 🔍 Troubleshooting

### Common Issues

#### 1. Plugin không kích hoạt được

-   Kiểm tra PHP version (yêu cầu 7.4+)
-   Kiểm tra Composer dependencies đã cài đặt
-   Xem error log trong WordPress

#### 2. JWT tokens không hoạt động

-   Kiểm tra JWT_SECRET_KEY đã được set
-   Kiểm tra database tables đã được tạo
-   Xem WordPress debug log

#### 3. Email không gửi được

-   Kiểm tra WordPress email settings
-   Kiểm tra SMTP configuration
-   Xem email log

#### 4. Performance issues

-   Kiểm tra database indexes
-   Tối ưu cron job cleanup
-   Monitor memory usage

### Debug Mode

```php
// Bật debug mode
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// JWT debug
define('JWT_DEBUG', true);
```

## 📈 Performance Optimization

### Database Optimization

-   **Indexes** - Đã được tạo tự động cho các trường quan trọng
-   **Batch Processing** - Cleanup tokens theo batch để tránh timeout
-   **Connection Pooling** - Sử dụng WordPress database connection

### Memory Management

-   **Token Cleanup** - Tự động xóa expired tokens
-   **Session Cleanup** - Tự động xóa expired sessions
-   **Batch Operations** - Xử lý theo batch để tiết kiệm memory

### Cron Job Optimization

-   **Daily Cleanup** - Chạy vào 00:00 mỗi ngày
-   **Batch Size** - Xử lý 1000 records mỗi lần
-   **Sleep Delay** - 10ms delay giữa các batch

## 🤝 Contributing

1. Fork repository
2. Tạo feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Tạo Pull Request

## 📄 License

Plugin này được phát hành dưới GPL v2 hoặc mới hơn.

## 🆘 Support

-   **Documentation**: [Wiki](link-to-wiki)
-   **Issues**: [GitHub Issues](link-to-issues)
-   **Email**: support@okhub.com
-   **Community**: [Forum](link-to-forum)

## 🔄 Changelog

### Version 1.0.0

-   ✅ JWT Authentication
-   ✅ Multi-device support
-   ✅ Session management
-   ✅ Password reset
-   ✅ Email services
-   ✅ Security features
-   ✅ Database optimization
    optuj- ✅ **Google Social Login** - Đăng nhập/đăng ký qua Google với token verification
-   ✅ **Account merging** - Tự động merge Google với local account
-   ✅ **Unified API** - Một endpoint xử lý tất cả scenarios
-   ✅ **Token Verification** - Verify Google tokens với Google API Client
-   ✅ **Multiple Auth Methods** - ID Token, Access Token, Authorization Code

---

**Made with ❤️ by Okhub Team**
