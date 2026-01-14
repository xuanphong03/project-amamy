<?php

/**
 * PDF Bill Template
 * 
 * Template variables available:
 * - $company_name: Company name
 * - $ten_nguoi_nhan: Recipient name
 * - $full_address: Full shipping address
 * - $tinh_thanh_nguoi_nhan: City
 * - $phone: Phone number
 * - $email: Email address
 * - $service_details: Array of service details
 * - $total_eur: Total amount in EUR
 * - $exchange_rate: Exchange rate
 * - $total_vnd: Total amount in VND
 * - $loai_tien_te: Currency type
 * - $bank_account: Bank account number
 * - $bank_account_name: Bank account name
 * - $bank_name: Bank name
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<div class="header">
    <h1><?php echo esc_html($company_name); ?></h1>
</div>

<div class="section">
    <div class="section-title">I. <?php esc_html_e('THÔNG TIN NHẬN HÀNG', 'delivery-order-system'); ?></div>
    <table class="info-table">
        <tr>
            <td class="info-label">
                <?php esc_html_e('Tên người nhận', 'delivery-order-system'); ?>:
            </td>
            <td class="info-value text-right">
                <?php echo esc_html($ten_nguoi_nhan); ?>
            </td>
        </tr>
        <tr>
            <td class="info-label">
                <?php esc_html_e('Địa chỉ nhận hàng', 'delivery-order-system'); ?>:
            </td>
            <td class="info-value text-right">
                <?php echo esc_html($full_address); ?>
            </td>
        </tr>
        <tr>
            <td class="info-label">
                <?php esc_html_e('Thành phố', 'delivery-order-system'); ?>:
            </td>
            <td class="info-value text-right">
                <?php echo esc_html($tinh_thanh_nguoi_nhan); ?>
            </td>
        </tr>
        <tr>
            <td class="info-label">
                <?php esc_html_e('Số điện thoại', 'delivery-order-system'); ?>:
            </td>
            <td class="info-value text-right">
                <?php echo esc_html($phone); ?>
            </td>
        </tr>
        <tr>
            <td class="info-label">
                <?php esc_html_e('Email nhận mã theo dõi đơn hàng', 'delivery-order-system'); ?>:
            </td>
            <td class="info-value text-right">
                <?php echo esc_html($email); ?>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <table class="service-table">
        <thead>
            <tr>
                <th class="col-stt"><?php esc_html_e('STT', 'delivery-order-system'); ?></th>
                <th class="col-detail"><?php esc_html_e('Chi Tiết', 'delivery-order-system'); ?></th>
                <th class="col-weight"><?php esc_html_e('Cân nặng /Số lượng', 'delivery-order-system'); ?></th>
                <th class="col-price"><?php esc_html_e('Đơn giá', 'delivery-order-system'); ?></th>
                <th class="col-amount"><?php esc_html_e('Thành tiền', 'delivery-order-system'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($service_details as $key => $service) :
            ?>
            <tr class="row-<?php echo esc_html($key + 1); ?>">
                <td class="col-stt"><?php echo esc_html($service['stt']); ?></td>
                <td class="col-detail"><?php echo esc_html($service['label']); ?></td>
                <td class="col-weight"><?php echo esc_html($service['weight']); ?></td>
                <td class="col-price"><?php echo esc_html($service['unit_price']); ?></td>
                <td class="col-amount"><?php echo esc_html($service['amount']); ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="total-row">
                <td class="col-stt total-label text-center">
                    <?php echo esc_html(count($service_details) + 1); ?>
                </td>
                <td class="col-detail">
                    <strong><?php esc_html_e('Tổng', 'delivery-order-system'); ?></strong>
                </td>
                <td></td>
                <td></td>
                <td class="col-amount">
                    <strong><?php echo esc_html(number_format($total_eur, 2, ',', '.')); ?>
                        <?php echo esc_html($loai_tien_te); ?></strong>
                </td>
            </tr>
            <tr class="exchange-rate-row">
                <td class="col-stt exchange-rate-label text-center">
                    <?php echo esc_html(count($service_details) + 2); ?>
                </td>
                <td class="col-detail">
                    <?php esc_html_e('Tỷ giá bán ra Vietcombank', 'delivery-order-system'); ?>
                </td>
                <td></td>
                <td></td>
                <td class="col-amount"><?php echo esc_html(number_format($exchange_rate, 0, ',', '.')); ?> VND</td>
            </tr>
            <tr class="total-row total-row-price">
                <td class="col-stt total-label">
                    <?php echo esc_html(count($service_details) + 3); ?>
                </td>
                <td class="col-detail">
                    <?php esc_html_e('Tổng số tiền cần thanh toán (VND)', 'delivery-order-system'); ?>
                </td>
                <td></td>
                <td></td>
                <td class="col-amount">
                    <strong><?php echo esc_html(number_format(floatval($total_vnd), 0, ',', '.')); ?> VND</strong>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">II. <?php esc_html_e('THÔNG TIN CHUYỂN KHOẢN', 'delivery-order-system'); ?></div>
    <div class="info-row qr-section">
        <div class="qr-info-row">
            <div style="display: block; text-align: center; margin:0; font-weight: bold">Quét mã QR để
                chuyển khoản
            </div>
            <?php if (!empty($qr_image_base64)) : ?>
            <img src="data:image/png;base64,<?php echo esc_attr($qr_image_base64); ?>"
                style="display: block; margin: 0 auto;" />
            <?php else : ?>
            <?php esc_html_e('QR code sẽ được hiển thị ở đây', 'delivery-order-system'); ?>
            <?php endif; ?>
        </div>
        <div class="qr-info-row">
            <div>
                <?php esc_html_e('Tên tài khoản', 'delivery-order-system'); ?>:
                <?php echo esc_html($bank_name); ?>
                - <?php echo esc_html($bank_account_name); ?>
            </div>
            <div>
                <?php esc_html_e('Nội dung chuyển khoản', 'delivery-order-system'); ?>:
                <?php esc_html_e('Mã khách hàng ', 'delivery-order-system'); ?>
                <?php echo esc_html($ma_khach_hang); ?>
            </div>
        </div>
    </div>
</div>

<div class="section terms-section">
    <div class="section-title">III. <?php esc_html_e('THÔNG TIN QUAN TRỌNG PHẢI ĐỌC', 'delivery-order-system'); ?>
    </div>
    <div class="terms-item">
        <span class="terms-number">1.</span>
        <?php esc_html_e('Bưu kiện của bạn sẽ không có bảo hiểm hàng hoá dễ vỡ và bảo hiểm hàng hoá thực phẩm hỏng. Amamy miễn trừ trách nhiệm đền bù đối với việc hàng hoá trong quá trình vận chuyển
        bị đổ vỡ, bị hỏng bên trong hoặc thực phẩm khô bị hỏng ôi thiu, trong bất kỳ trường hợp nào, kể cả lý do giao chậm hàng.', 'delivery-order-system'); ?>
    </div>
    <div class="terms-item">
        <span class="terms-number">2.</span>
        <?php esc_html_e('Sẽ có rủi ro thất lạc hàng hoá hoặc hải quan thu giữ, mặc dù rủi ro 2%. Nếu hàng hoá của bạn có giá trị lớn hơn 200.000 VNĐ/KG hãy cân nhắc mua bảo hiểm cho đơn hàng của
        mình. Trường hợp không có bảo hiểm, nếu có xảy ra thất lạc sẽ hoàn tiền vận chuyển và đền bù 10 EURO/KG. Nếu có bảo hiểm mua trước sẽ đền bù theo giá trị hàng hoá đã khai báo.', 'delivery-order-system'); ?>
    </div>
    <div class="terms-item">
        <span class="terms-number">3.</span>
        <?php esc_html_e('Thời gian giao hàng 10-15 ngày chỉ dự kiến trong trường hợp tốt nhất, có tính chất tham khảo. Một số trường hợp ngoại lệ có thể sẽ lâu hơn do delay, thời tiết xấu, hải quan kiểm tra
        đột xuất… Amamy không cam kết 100% thời điểm chính xác giao hàng cho khách hàng.', 'delivery-order-system'); ?>
    </div>
    <div class="terms-item">
        <span class="terms-number">4.</span>
        <?php esc_html_e('Khách hàng gửi hàng đồng nghĩa với việc đã đọc rõ các nội dung trên và đồng ý với các chính sách trên từ công ty. Amamy sẽ không chịu trách nhiệm cho việc rằng đã không đọc kỹ
        hay đọc thiếu thông tin trên.', 'delivery-order-system'); ?>
    </div>
</div>

<div class="confirmation-section">
    <table class="confirmation-wrapper">
        <tr>
            <td class="confirmation-left">
                <div class="confirmation-label text-center">
                    <?php esc_html_e('Xác nhận từ khách hàng', 'delivery-order-system'); ?>
                </div>
                <div class="confirmation-box"></div>
            </td>
            <td class="confirmation-right">
                <div class="confirmation-label text-center">
                    <?php esc_html_e('Xác nhận từ Amamy', 'delivery-order-system'); ?>
                </div>
                <div class="confirmation-box">
                    <img class="signature"
                        src="https://cms.amamy.net/wp-content/uploads/2025/12/z7342111302026_754c25370cbbe7d57b89b93b352e65c0.jpg"
                        alt="Signature" />
                </div>
            </td>
        </tr>
    </table>
</div>