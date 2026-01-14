# Hướng dẫn cài đặt mPDF

Plugin đã được chuyển từ TCPDF sang mPDF để hỗ trợ CSS tốt hơn và dễ debug hơn.

## Cài đặt mPDF

### Cách 1: Sử dụng Composer (Khuyến nghị)

1. Đảm bảo bạn đã cài Composer
2. Chạy lệnh sau trong thư mục plugin:

```bash
cd wp-content/plugins/delivery-order-system
composer install
```

Hoặc nếu chưa có `composer.json`, chạy:

```bash
composer require mpdf/mpdf
```

### Cách 2: Cài đặt thủ công

1. Tải mPDF từ: https://github.com/mpdf/mpdf/releases
2. Giải nén vào thư mục `wp-content/plugins/delivery-order-system/vendor/mpdf/`
3. Đảm bảo file `vendor/autoload.php` tồn tại

## Kiểm tra cài đặt

Sau khi cài đặt, kiểm tra bằng cách:
1. Vào trang quản trị WordPress
2. Tạo hoặc chỉnh sửa một đơn hàng vận chuyển
3. Thử generate PDF bill
4. Nếu thành công, mPDF đã được cài đặt đúng

## Lợi ích của mPDF so với TCPDF

- ✅ Hỗ trợ CSS tốt hơn (flexbox, grid, position, etc.)
- ✅ Render HTML/CSS chính xác hơn
- ✅ Dễ debug và preview
- ✅ Performance tốt hơn
- ✅ Hỗ trợ nhiều tính năng CSS hiện đại

## Gỡ bỏ TCPDF (Tùy chọn)

Nếu bạn muốn gỡ bỏ TCPDF để tiết kiệm dung lượng:

```bash
composer remove tecnickcom/tcpdf
```

## Troubleshooting

### Lỗi: "mPDF not available"

1. Kiểm tra file `vendor/autoload.php` có tồn tại không
2. Kiểm tra quyền truy cập thư mục `vendor/`
3. Chạy lại `composer install`

### Lỗi: "Class 'Mpdf\Mpdf' not found"

1. Đảm bảo đã chạy `composer install`
2. Kiểm tra file `composer.json` có chứa `"mpdf/mpdf": "^8.0"` không
3. Xóa thư mục `vendor/` và chạy lại `composer install`

