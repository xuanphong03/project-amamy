# OTP Verification và Reset Password Fix

## Vấn đề

API `/auth/password/otp/verify` sau khi verify OTP thành công đã đánh dấu OTP là đã sử dụng (`used = 1`). Điều này khiến OTP không thể sử dụng được nữa trong API `/auth/password/otp/reset`.

## Luồng hoạt động đúng

1. User gọi `/auth/password/otp/request` để yêu cầu OTP
2. User gọi `/auth/password/otp/verify` để verify OTP (chỉ kiểm tra, không đánh dấu used)
3. User gọi `/auth/password/otp/reset` để reset password với OTP đã verify (mới đánh dấu used)

## Giải pháp

Đã sửa method `verifyOtp()` trong `AuthService.php`:

-   Loại bỏ việc gọi `markOtpAsUsed()` trong method `verifyOtp()`
-   Chỉ đánh dấu OTP là đã sử dụng khi thực sự reset password thành công trong method `resetPasswordWithOtp()`

## Code thay đổi

```php
// Trước (SAI):
public function verifyOtp($email, $otpCode)
{
    // ... validation logic ...

    // Mark OTP as used - SAI!
    $this->otpService->markOtpAsUsed($email, $otpCode);

    return $result;
}

// Sau (ĐÚNG):
public function verifyOtp($email, $otpCode)
{
    // ... validation logic ...

    // Don't mark OTP as used here - let it be used for password reset
    // Only mark as used when password is actually reset successfully

    return $result;
}
```

## Test

Chạy file `test-otp-flow.php` để kiểm tra luồng hoạt động:

```bash
php test-otp-flow.php
```

## Kết quả mong đợi

1. OTP được tạo thành công
2. OTP verification thành công và OTP vẫn còn valid (used = 0)
3. Password reset thành công với cùng OTP
4. OTP được đánh dấu used sau khi reset password thành công
5. OTP không thể sử dụng lại sau khi đã reset password
