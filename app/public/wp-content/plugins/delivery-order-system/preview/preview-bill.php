<?php

/**
 * Preview HTML for PDF Bill
 * Path: wp-content/plugins/delivery-order-system/preview/preview-bill.php
 */

// Load WordPress environment only if accessed directly
if (!defined('ABSPATH')) {
    $wp_load_path = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
    } else {
        $wp_load_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
        if (file_exists($wp_load_path)) {
            require_once($wp_load_path);
        } else {
            die('Could not find wp-load.php. Please check the file path.');
        }
    }
}

// Check access (Only admin or editors)
if (!current_user_can('edit_posts')) {
    wp_die(__('Bạn không có quyền truy cập trang này.', 'delivery-order-system'));
}

// Get parameters
$post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
$ma_van_don_id = isset($_GET['ma_van_don_id']) ? absint($_GET['ma_van_don_id']) : 0;

if (!$post_id || !$ma_van_don_id) {
    wp_die(__('Thiếu tham số post_id hoặc ma_van_don_id.', 'delivery-order-system'));
}

// Load Composer autoloader for PSR-4
if (file_exists(DELIVERY_ORDER_SYSTEM_PATH . 'vendor/autoload.php')) {
    require_once DELIVERY_ORDER_SYSTEM_PATH . 'vendor/autoload.php';
}

use DeliveryOrderSystem\PDF\Data_Collector;
use DeliveryOrderSystem\PDF\QR_Handler;
use DeliveryOrderSystem\PDF\Template_Renderer;

// Check ACF
if (!function_exists('get_field')) {
    wp_die(__('Plugin ACF chưa được kích hoạt.', 'delivery-order-system'));
}

$rows = get_field('delivery_manager', $post_id);
$fee_data = null;

if (is_array($rows)) {
    foreach ($rows as $row) {
        if (isset($row['ma_van_don']) && absint($row['ma_van_don']) === $ma_van_don_id) {
            // Include the delivery post type class to use its helper method
            if (!class_exists('Delivery_Order_System_Delivery_Post_Type')) {
                require_once plugin_dir_path(__FILE__) . '../includes/class-delivery-post-type.php';
            }

            $fee_data = \Delivery_Order_System_Delivery_Post_Type::create_fee_data_from_row($row);
            break;
        }
    }
}

if (!$fee_data) {
    wp_die(__('Không tìm thấy dữ liệu phí cho mã vận đơn này trong đơn hàng.', 'delivery-order-system'));
}

// Collect data
$data = Data_Collector::collect($post_id, $ma_van_don_id, $fee_data);
if (!$data) {
    wp_die(__('Lỗi khi thu thập dữ liệu.', 'delivery-order-system'));
}

// Get QR
$qr_result = QR_Handler::get_qr_code($data);
$data['qr_image_base64'] = $qr_result['qr_image_base64'];
$data['bank_account'] = $qr_result['account_number'];
$data['bank_name'] = $qr_result['bank_name'];
$data['date'] = current_time('d/m/Y H:i');

// Render
$html = Template_Renderer::render($data);

if (!$html) {
    wp_die(__('Lỗi khi render template.', 'delivery-order-system'));
}

// Inject QR image
if (!empty($data['qr_image_base64'])) {
    $qr_img_tag = '<img src="data:image/png;base64,' . $data['qr_image_base64'] . '" style="width: 40mm; height: 40mm; display: block; margin: 2mm auto;">';
    $placeholder = 'QR code sẽ được hiển thị ở đây (QR code will be displayed here)';

    // Replace placeholder text with QR image
    if (strpos($html, $placeholder) !== false) {
        $html = str_replace($placeholder, $qr_img_tag, $html);
    } else {
        // Fallback: try to find and replace the qr-placeholder div
        $html = preg_replace(
            '/<div class="qr-placeholder">.*?QR code sẽ được hiển thị ở đây.*?<\/div>/s',
            '<div class="qr-placeholder">' . $qr_img_tag . '</div>',
            $html
        );
    }
}

$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Hóa Đơn - Mã vận đơn #<?php echo esc_html($ma_van_don_id); ?></title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            background: #f0f0f1;
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }

        .preview-controls {
            background: #fff;
            padding: 15px 20px;
            margin: 0 auto 20px auto;
            max-width: 800px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .preview-controls-left {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .preview-controls-right {
            display: flex;
            gap: 10px;
        }

        .preview-page {
            background: #fff;
            width: 210mm;
            margin: 0 auto;
            min-height: 297mm;
            padding: 15mm;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .btn {
            background: #2271b1;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }

        .btn:hover {
            background: #135e96;
        }

        .btn-secondary {
            background: #646970;
        }

        .btn-secondary:hover {
            background: #50575e;
        }

        .btn.active {
            background: #00a32a;
        }

        .debug-info {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 10000;
            max-width: 300px;
            display: none;
        }

        .debug-info.active {
            display: block;
        }

        .debug-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image:
                linear-gradient(to right, rgba(255, 0, 0, 0.1) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 0, 0, 0.1) 1px, transparent 1px);
            background-size: 10mm 10mm;
            pointer-events: none;
            display: none;
        }

        .debug-mode .debug-grid {
            display: block;
        }

        .debug-mode .preview-page>* {
            outline: 1px dashed rgba(0, 0, 255, 0.3);
        }

        .debug-mode .preview-page>*:hover {
            outline: 2px solid rgba(255, 0, 0, 0.5);
            background-color: rgba(255, 255, 0, 0.1) !important;
        }

        .size-badge {
            position: absolute;
            top: -20px;
            left: 0;
            background: rgba(255, 0, 0, 0.8);
            color: #fff;
            padding: 2px 6px;
            font-size: 10px;
            border-radius: 2px;
            display: none;
            white-space: nowrap;
            z-index: 1000;
        }

        .debug-mode .preview-page>*:hover .size-badge {
            display: block;
        }

        @media print {
            .preview-controls {
                display: none;
            }

            .debug-grid {
                display: none;
            }

            .debug-info {
                display: none;
            }

            body {
                background: #fff;
                padding: 0;
            }

            .preview-page {
                box-shadow: none;
                width: 100%;
                margin: 0;
            }
        }
    </style>
</head>

<body class="<?php echo $debug_mode ? 'debug-mode' : ''; ?>">
    <div class="preview-controls">
        <div class="preview-controls-left">
            <div>
                <strong>Xem trước hóa đơn:</strong> Mã vận đơn #<?php echo esc_html($ma_van_don_id); ?>
            </div>
            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                <input type="checkbox" id="debug-toggle" <?php echo $debug_mode ? 'checked' : ''; ?>
                    onchange="toggleDebug()">
                <span>Debug Mode</span>
            </label>
        </div>
        <div class="preview-controls-right">
            <button onclick="window.print()" class="btn">In ngay (Print PDF)</button>
            <a href="?post_id=<?php echo esc_attr($post_id); ?>&ma_van_don_id=<?php echo esc_attr($ma_van_don_id); ?>&debug=<?php echo $debug_mode ? '0' : '1'; ?>"
                class="btn btn-secondary">
                <?php echo $debug_mode ? 'Tắt Debug' : 'Bật Debug'; ?>
            </a>
        </div>
    </div>
    <div class="preview-page" id="preview-page">
        <div class="debug-grid"></div>
        <?php echo $html; ?>
    </div>
    <div class="debug-info" id="debug-info">
        <div><strong>Debug Info</strong></div>
        <div id="debug-content">Hover vào element để xem thông tin</div>
    </div>
    <script>
        function toggleDebug() {
            document.body.classList.toggle('debug-mode');
            const debugInfo = document.getElementById('debug-info');
            if (document.body.classList.contains('debug-mode')) {
                debugInfo.classList.add('active');
            } else {
                debugInfo.classList.remove('active');
            }
        }

        // Convert CSS value to mm
        function cssToMm(value) {
            if (!value || value === 'auto' || value === 'none') return value;
            const num = parseFloat(value);
            if (isNaN(num)) return value;

            // If already in mm, return as is
            if (value.includes('mm')) return value;
            // If in pt, convert (1pt = 0.352778mm)
            if (value.includes('pt')) return (num * 0.352778).toFixed(2) + 'mm';
            // If in px, convert (1px = 0.264583mm at 96dpi)
            if (value.includes('px') || !value.match(/[a-z]+/)) {
                return (num * 0.264583).toFixed(2) + 'mm';
            }
            return value;
        }

        // Show element info on hover (debug mode only)
        function initDebugMode() {
            const previewPage = document.getElementById('preview-page');
            const debugInfo = document.getElementById('debug-content');

            previewPage.addEventListener('mouseover', function(e) {
                if (e.target === previewPage || e.target.classList.contains('debug-grid')) return;

                const el = e.target;
                const rect = el.getBoundingClientRect();
                const pageRect = previewPage.getBoundingClientRect();
                const styles = window.getComputedStyle(el);

                // Calculate relative position within page
                const relX = rect.left - pageRect.left;
                const relY = rect.top - pageRect.top;

                const info = `
                    <div style="margin-bottom: 8px;"><strong style="color: #00ff00;">${el.tagName.toLowerCase()}</strong>${el.className ? ' <span style="color: #ffaa00;">.' + el.className.split(' ').join(' .') + '</span>' : ''}</div>
                    <div><strong>Kích thước:</strong> ${cssToMm(rect.width + 'px')} × ${cssToMm(rect.height + 'px')}</div>
                    <div><strong>Vị trí (trong trang):</strong> ${cssToMm(relX + 'px')}, ${cssToMm(relY + 'px')}</div>
                    <div><strong>Margin:</strong> ${cssToMm(styles.marginTop)} ${cssToMm(styles.marginRight)} ${cssToMm(styles.marginBottom)} ${cssToMm(styles.marginLeft)}</div>
                    <div><strong>Padding:</strong> ${cssToMm(styles.paddingTop)} ${cssToMm(styles.paddingRight)} ${cssToMm(styles.paddingBottom)} ${cssToMm(styles.paddingLeft)}</div>
                    <div><strong>Font:</strong> ${styles.fontSize} / ${styles.fontWeight}</div>
                    <div><strong>Text-align:</strong> ${styles.textAlign}</div>
                    <div><strong>Color:</strong> <span style="display: inline-block; width: 20px; height: 12px; background: ${styles.color}; border: 1px solid #ccc; vertical-align: middle;"></span> ${styles.color}</div>
                    <div><strong>Background:</strong> <span style="display: inline-block; width: 20px; height: 12px; background: ${styles.backgroundColor}; border: 1px solid #ccc; vertical-align: middle;"></span> ${styles.backgroundColor}</div>
                    ${styles.borderWidth !== '0px' ? `<div><strong>Border:</strong> ${styles.borderWidth} ${styles.borderStyle} ${styles.borderColor}</div>` : ''}
                `;

                debugInfo.innerHTML = info;
            }, true);
        }

        // Initialize debug mode if active
        if (document.body.classList.contains('debug-mode')) {
            initDebugMode();
        }

        // Re-initialize when toggling
        const debugToggle = document.getElementById('debug-toggle');
        if (debugToggle) {
            debugToggle.addEventListener('change', function() {
                setTimeout(function() {
                    if (document.body.classList.contains('debug-mode')) {
                        initDebugMode();
                    }
                }, 100);
            });
        }
    </script>
</body>

</html>