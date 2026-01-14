# Delivery Order System

Plugin WordPress để quản lý vận chuyển đơn hàng với tích hợp OnePay API và tạo PDF hóa đơn.

## Cấu trúc thư mục

```
delivery-order-system/
├── delivery-order-system.php    # Main plugin file
├── composer.json                 # Composer dependencies
├── README.md                     # Documentation
│
├── admin/                        # Admin interface
│   ├── class-admin.php
│   ├── css/
│   ├── js/
│   └── views/
│
├── public/                       # Public interface
│   ├── class-public.php
│   ├── css/
│   └── js/
│
├── includes/                     # Core functionality
│   ├── class-activator.php
│   ├── class-deactivator.php
│   ├── class-loader.php
│   ├── class-plugin.php
│   ├── class-delivery-post-type.php
│   ├── class-pdf-bill.php
│   ├── class-send-mail.php
│   ├── helpers.php
│   │
│   ├── Payment/                  # PSR-4: DeliveryOrderSystem\Payment
│   │   ├── Authorization.php
│   │   ├── IPN_Handler.php
│   │   ├── PayCollect.php
│   │   └── Util.php
│   │
│   ├── PDF/                      # PSR-4: DeliveryOrderSystem\PDF
│   │   ├── Data_Collector.php
│   │   ├── Generator.php
│   │   ├── QR_Handler.php
│   │   └── Template_Renderer.php
│   │
│   └── templates/
│       ├── pdf-bill-template.php
│       └── pdf-bill.css
│
├── config/                       # Configuration
│   └── constants.php             # All plugin constants
│
├── preview/                      # Preview utilities
│   └── preview-bill.php
│
├── tests/                        # Test files (development only)
│   ├── test-qr-code.php
│   └── test-update-user-state.php
│
├── docs/                         # Documentation
│   └── MPDF_INSTALL.md
│
└── vendor/                       # Composer dependencies
```

## Cài đặt

1. Cài đặt dependencies:
```bash
composer install
```

2. Cấu hình constants trong `config/constants.php` hoặc override trong `wp-config.php`

## Tính năng

- Quản lý đơn hàng vận chuyển
- Tạo PDF hóa đơn tự động
- Tích hợp OnePay API cho thanh toán QR code
- IPN (Instant Payment Notification) handler
- Email tự động gửi hóa đơn

## Development

Test files nằm trong `tests/` và không được commit vào production (đã ignore trong `.gitignore`).

## License

GPL-2.0+

