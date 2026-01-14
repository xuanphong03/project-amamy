<?php

/**
 * Plugin Name: aseet
 */

require_once __DIR__  . '/vendor/autoload.php';

function ase_add_log($message)
{
    $log_file = __DIR__ . '/log.txt';
    $timestamp = date("Y-m-d H:i:s");
    $log_entry = "[{$timestamp}] " . print_r($message, true) . PHP_EOL;

    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function ase_clean_logs()
{
    $log_file = __DIR__ . '/log.txt';

    if (file_exists($log_file)) {
        file_put_contents($log_file, "");
    }
}
function ase_get_logs()
{
    $log_file = __DIR__ . '/log.txt';

    if (file_exists($log_file)) {
        return file_get_contents($log_file);
    }

    return "No logs available.";
}


add_action('wp_ajax_ase_get_logs', function () {
    echo ase_get_logs();
    wp_die();
});

add_action('wp_ajax_ase_clean_logs', function () {
    ase_clean_logs();
    echo "Logs cleared";
    wp_die();
});




add_action('toplevel_page_global', 'before_acf_options_page', 1);
function before_acf_options_page()
{
    ob_start();
}


add_action('toplevel_page_global', 'after_acf_options_page', 20);
function after_acf_options_page()
{
    $content = ob_get_clean();
    $count = 1;

    ob_start();
?>
<div id="acf-group_67e2baa63e22e1" class="postbox acf-postbox">
    <div class="postbox-header">
        <h2 class="hndle ui-sortable-handle">Nhật ký lỗi đồng bộ đơn hàng</h2>
    </div>
    <div class="inside acf-fields -top">
        <div class="acf-field">
            <button id="clear-logs" class="button button-primary">Xóa tất cả nhật ký</button>
            <pre id="log-content"
                style="background: #f5f5f5; padding: 10px; border-radius: 5px; white-space: pre-wrap;overflow:auto;max-height:380px;"></pre>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const logContent = document.getElementById("log-content");
    const clearLogsBtn = document.getElementById("clear-logs");

    function fetchLogs() {
        fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=ase_get_logs")
            .then(response => response.text())
            .then(data => {
                logContent.textContent = data || "Không có nhật ký lỗi.";
            });
    }

    fetchLogs();

    clearLogsBtn.addEventListener("click", function() {
        if (confirm("Bạn có chắc chắn muốn xóa tất cả nhật ký?")) {
            fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "action=ase_clean_logs"
            }).then(() => {
                logContent.textContent = "Nhật ký đã được xóa.";
            });
        }
    });
});
</script>
<?php
    $my_content = ob_get_contents();
    ob_clean();
    $content = str_replace('<div id="acf-group_67e2baa63e22e"', $my_content . '<div id="acf-group_67e2baa63e22e"', $content, $count);

    echo $content;
}

function get_shipping_directions()
{
    $shipping_direction_args = array(
        'post_type' => 'chieu-van-chuyen',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    );
    $query = new WP_Query($shipping_direction_args);
    $shipping_directions = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $shipping_directions[get_the_title()] = get_the_ID();
        }
    }

    wp_reset_postdata();
    return $shipping_directions;
}

function get_order_status()
{
    $order_statuses = [];
    $order_status_args = array(
        'post_type' => 'trang-thai-don-hang',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    );
    $query = new WP_Query($order_status_args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $order_statuses[get_the_title()] = get_the_ID();
        }
    }

    wp_reset_postdata();
    return $order_statuses;
}

function get_order_exists($titles)
{
    global $wpdb;
    $data = [];
    $placeholders = implode(',', array_fill(0, count($titles), '%s'));
    $query = "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_title IN ($placeholders)";

    $results = $wpdb->get_results($wpdb->prepare($query, 'ma_van_don', 'publish', ...$titles), ARRAY_A);

    foreach ($results as $row) {
        $data[$row['post_title']] = $row['ID'];
    }

    return $data;
}


add_action('ase_dong_bo_van_don_theo_chieu_van_chuyen_cron', 'ase_chieu_van_chuyen_main');
function ase_chieu_van_chuyen_main()
{
    $account_service_json = get_field('account_service', 'option');
    $shipping_direction_config = get_field('shipping_direction_config', 'option');

    try {
        $connection = ase_get_google_connection(json_decode($account_service_json, true));
        $account_service =  new Google_Service_Sheets($connection);
        $order_status = get_order_status();

        foreach ($shipping_direction_config as $config) {
            $rows = ase_get_rows($account_service, $config['id_trang_tinh'], $config['ten_trang_tinh']);
            $rows = ase_convert_rows($rows);

            $new_rows = [];

            foreach ($rows as $row) {
                if (empty($row) || !isset($row['title'])) {
                    continue;
                }
                $new_rows[] = $row;
            }

            $titles  = array_map(fn($o) => sanitize_text_field($o['title']), $new_rows);

            $order_exists = get_order_exists($titles);

            foreach ($new_rows as $row) {
                try {
                    $row['chieu_van_chuyen_id'] = $config['chieu_van_chuyen'];
                    ase_save_order_prss($row, $order_status, [], $order_exists);
                } catch (Exception $th) {
                    ase_add_log('ROW CVC' . $row['title'] . ' ERROR: ' .  $th->getMessage());
                }
            }
        }
    } catch (Exception $th) {
        ase_add_log('CONFIG CVC ERROR: ' .  $th->getMessage());
    }
}



function ase_save_order_prss($data, $order_status,  $shipping_directions = [], $order_exists = [])
{
    $update = true;
    $field_update = [];
    $post_id = $order_exists[$data['title']];
    $chieu_van_chuyen_id = isset($data['chieu_van_chuyen_id']) ? $data['chieu_van_chuyen_id'] : $shipping_directions[$data['chieu_don_hang']];

    if (!isset($post_id)) {
        $post_id = wp_insert_post(array(
            'post_type' => 'ma_van_don',
            'post_status' => 'publish',
            'post_title' => $data['title'],
        ));
        $update = false;
    }

    $prev_order_status = get_field('trang_thai_don_hang', $post_id);

    if ($prev_order_status->ID != $order_status[$data['trang_thai_giao_hang']]) {
        $field_update['trang_thai_don_hang'] = true;
    }
    if (array_key_exists('trang_thai_giao_hang', $data) && !empty($data['trang_thai_giao_hang'])) {
        update_field("trang_thai_don_hang", $order_status[$data['trang_thai_giao_hang']], $post_id);
    }
    if (isset($chieu_van_chuyen_id) && !empty($chieu_van_chuyen_id)) {
        update_field("chieu_van_don", $chieu_van_chuyen_id, $post_id);
    }
    if (array_key_exists('email', $data) && !empty($data['email'])) {
        update_field("user", $data['email'], $post_id);
    }
    if (array_key_exists('title', $data) && !empty($data['title'])) {
        update_field("ma_don", $data['title'], $post_id);
    }
    if (array_key_exists('ten_nguoi_gui', $data) && !empty($data['ten_nguoi_gui'])) {
        update_field("ten_nguoi_gui", $data['ten_nguoi_gui'], $post_id);
    }
    if (array_key_exists('ten_nguoi_nhan', $data) && !empty($data['ten_nguoi_nhan'])) {
        update_field("ten_nguoi_nhan", $data['ten_nguoi_nhan'], $post_id);
    }
    if (array_key_exists('text_tracking_thu_ba', $data) && !empty($data['text_tracking_thu_ba'])) {
        update_field("text_tracking_thu_ba", $data['text_tracking_thu_ba'], $post_id);
    }
    if (array_key_exists('link_tracking_thu_ba', $data) && !empty($data['link_tracking_thu_ba'])) {
        update_field("link_tracking_thu_ba", $data['link_tracking_thu_ba'], $post_id);
    }
    if (array_key_exists('ma_van_don_thu_ba', $data) && !empty($data['ma_van_don_thu_ba'])) {
        update_field("ma_van_don_thu_ba", $data['ma_van_don_thu_ba'], $post_id);
    }
    if (array_key_exists('Mã Kombini', $data) && !empty($data['Mã Kombini'])) {
        update_field("ma_kombini", $data['Mã Kombini'], $post_id);
    }
    if (array_key_exists('dia_chi_nguoi_nhan', $data) && !empty($data['dia_chi_nguoi_nhan'])) {
        update_field("tinh_thanh_nguoi_nhan", $data['dia_chi_nguoi_nhan'], $post_id);
    }
    if (array_key_exists('dia_chi_nguoi_nhan_chi_tiet', $data) && !empty($data['dia_chi_nguoi_nhan_chi_tiet'])) {
        update_field("dia_chi_nguoi_nhan_chi_tiet", $data['dia_chi_nguoi_nhan_chi_tiet'], $post_id);
    }
    if (array_key_exists('sdt', $data) && !empty($data['sdt'])) {
        update_field("sdt", $data['sdt'], $post_id);
        // 	update_field("tien_trinh_giao_hang", convertHtmlToArray($data['tien_trinh_giao_hang']), $post_id);
    }
    if (array_key_exists('tien_trinh_giao_hang', $data) && !empty($data['tien_trinh_giao_hang'])) {
        update_field("tien_trinh_giao_hang", $data['tien_trinh_giao_hang'], $post_id);
    }
    if (array_key_exists('dia_chi_nguoi_nhan', $data) && !empty($data['dia_chi_nguoi_nhan'])) {
        update_field("dia_chi_nguoi_nhan", $data['dia_chi_nguoi_nhan'], $post_id);
    }
    update_field("source_order", 'googlesheet', $post_id);

    do_action('ase/after_save_post', $post_id, $update, $field_update);
}




// add_action('ase_dong_bo_van_don_theo_chieu_van_chuyen_cron', 'ase_chieu_van_chuyen_main');
// function ase_chieu_van_chuyen_main()
// {
// 	$account_service_json = get_field('account_service', 'option');
// 	$shipping_direction_config = get_field('shipping_direction_config', 'option');

// 	try{
// 		$connection = ase_get_google_connection(json_decode($account_service_json, true));
// 		$account_service =  new Google_Service_Sheets($connection);

// 		// 		$shipping_directions = get_shipping_directions();
// 		$order_status = get_order_status();

// 		foreach($shipping_direction_config as $config){
// 			$rows = ase_get_rows($account_service, $config['id_trang_tinh'], $config['ten_trang_tinh']);
// 			$rows = ase_convert_rows($rows);

// 			foreach ($rows as $row) {
// 				try{
// 					if(empty($row) || !isset($row['title'])){
// 						continue;
// 					}

// 					$row['chieu_van_chuyen_id'] = $config['chieu_van_chuyen'];
// 					ase_save_order_prs($row, $order_status);
// 				}catch(Exception $th){
// 					ase_add_log('ROW CVC' . $row['title'] . ' ERROR: ' .  $th->getMessage());
// 				}
// 			}
// 		}
// 	}catch(Exception $th){
// 		ase_add_log('CONFIG CVC ERROR: '.  $th->getMessage());
// 	}
// }



function ase_save_order_prs($data, $order_status,  $shipping_directions = [])
{

    $update = true;
    $field_update = [];
    $query = new WP_Query([
        'post_type'      => 'ma_van_don',
        'title'          => $data['title'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    if (!empty($query->posts)) {
        $post_id = $query->posts[0];
    } else {
        $args = array(
            'post_type' => 'ma_van_don',
            'post_status' => 'publish',
            'post_title' => $data['title'],
        );

        $post_id = wp_insert_post($args);
        $update = false;
    }

    $prev_order_status = get_field('trang_thai_don_hang', $post_id);

    if ($prev_order_status->ID != $order_status[$data['trang_thai_giao_hang']]) {
        $field_update['trang_thai_don_hang'] = true;
    }

    if (array_key_exists('trang_thai_giao_hang', $data) && !empty($data['trang_thai_giao_hang'])) {
        update_field("trang_thai_don_hang", $order_status[$data['trang_thai_giao_hang']], $post_id);
    }


    if (isset($data['chieu_van_chuyen_id'])) {
        update_field("chieu_van_don", $data['chieu_van_chuyen_id'], $post_id);
    } else {
        if (isset($shipping_directions[$data['chieu_don_hang']])) {
            update_field("chieu_van_don", $shipping_directions[$data['chieu_don_hang']], $post_id);
        }
    }

    if (array_key_exists('email', $data) && !empty($data['email'])) {
        update_field("user", $data['email'], $post_id);
    }
    if (array_key_exists('title', $data) && !empty($data['title'])) {
        update_field("ma_don", $data['title'], $post_id);
    }
    if (array_key_exists('ten_nguoi_gui', $data) && !empty($data['ten_nguoi_gui'])) {
        update_field("ten_nguoi_gui", $data['ten_nguoi_gui'], $post_id);
    }
    if (array_key_exists('ten_nguoi_nhan', $data) && !empty($data['ten_nguoi_nhan'])) {
        update_field("ten_nguoi_nhan", $data['ten_nguoi_nhan'], $post_id);
    }
    if (array_key_exists('text_tracking_thu_ba', $data) && !empty($data['text_tracking_thu_ba'])) {
        update_field("text_tracking_thu_ba", $data['text_tracking_thu_ba'], $post_id);
    }
    if (array_key_exists('link_tracking_thu_ba', $data) && !empty($data['link_tracking_thu_ba'])) {
        update_field("link_tracking_thu_ba", $data['link_tracking_thu_ba'], $post_id);
    }
    if (array_key_exists('ma_van_don_thu_ba', $data) && !empty($data['ma_van_don_thu_ba'])) {
        update_field("ma_van_don_thu_ba", $data['ma_van_don_thu_ba'], $post_id);
    }
    if (array_key_exists('dia_chi_nguoi_nhan', $data) && !empty($data['dia_chi_nguoi_nhan'])) {
        update_field("tinh_thanh_nguoi_nhan", $data['dia_chi_nguoi_nhan'], $post_id);
    }
    if (array_key_exists('dia_chi_nguoi_nhan_chi_tiet', $data) && !empty($data['dia_chi_nguoi_nhan_chi_tiet'])) {
        update_field("dia_chi_nguoi_nhan_chi_tiet", $data['dia_chi_nguoi_nhan_chi_tiet'], $post_id);
    }
    if (array_key_exists('sdt', $data) && !empty($data['sdt'])) {
        update_field("sdt", $data['sdt'], $post_id);
    }
    if (array_key_exists('tien_trinh_giao_hang', $data) && !empty($data['tien_trinh_giao_hang'])) {
        // 	update_field("tien_trinh_giao_hang", convertHtmlToArray($data['tien_trinh_giao_hang']), $post_id);
        update_field("tien_trinh_giao_hang", $data['tien_trinh_giao_hang'], $post_id);
    }
    if (array_key_exists('dia_chi_nguoi_nhan', $data) && !empty($data['dia_chi_nguoi_nhan'])) {
        update_field("dia_chi_nguoi_nhan", $data['dia_chi_nguoi_nhan'], $post_id);
    }

    update_field("source_order", 'googlesheet', $post_id);


    do_action('ase/after_save_post', $post_id, $update, $field_update);
}

add_action('ase_dong_bo_van_don_cron', 'ase_dong_bo_van_don_cron_callback');

function ase_dong_bo_van_don_cron_callback()
{
    if (get_transient('cron_running_ase_dong_bo_van_don_aa_cron_action')) {
        error_log('CRON MESSAGE: Cronjob running.');
        ase_add_log('CRON MESSAGE: Cronjob running.');
        return;
    }

    // Increase execution time and memory limit
    @set_time_limit(0);
    @ini_set('max_execution_time', 0);
    @ini_set('memory_limit', '512M');

    // Set transient with longer timeout (10 minutes) for long-running sync
    set_transient('cron_running_ase_dong_bo_van_don_aa_cron_action', true, 10 * 60);
    ase_add_log('CRON MESSAGE: Cronjob starting.');

    try {
        ase_main();
        delete_transient('cron_running_ase_dong_bo_van_don_aa_cron_action');
        ase_add_log('CRON MESSAGE: Cronjob done.');
    } catch (Exception $e) {
        delete_transient('cron_running_ase_dong_bo_van_don_aa_cron_action');
        ase_add_log('CRON MESSAGE ERROR: ' . $e->getMessage());
        throw $e;
    }
}

// Manual sync via URL parameter ?dong-bo-van-don
add_action('init', 'ase_manual_sync_van_don');
function ase_manual_sync_van_don()
{
    // Check if parameter exists and user is admin
    if (isset($_GET['dong-bo-van-don']) && current_user_can('manage_options')) {
        // Prevent direct access without proper permissions
        if (!is_admin() && !current_user_can('manage_options')) {
            wp_die('Bạn không có quyền thực hiện thao tác này.');
        }

        // Increase execution time and memory limit to prevent timeout
        @set_time_limit(0);
        @ini_set('max_execution_time', 0);
        @ini_set('memory_limit', '512M');

        // Disable output buffering to prevent timeout
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Send immediate response to browser to prevent timeout
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(200);

        // Flush output buffer
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // Fallback for non-FastCGI
            ignore_user_abort(true);
            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();
        }

        ase_add_log('MANUAL SYNC: Đồng bộ thủ công được kích hoạt bởi ' . wp_get_current_user()->user_login);

        // Call the sync function in background
        try {
            ase_dong_bo_van_don_cron_callback();
            ase_add_log('MANUAL SYNC: Đồng bộ thủ công hoàn tất thành công.');
        } catch (Exception $e) {
            ase_add_log('MANUAL SYNC ERROR: ' . $e->getMessage());
        }

        // If still connected, show message
        if (!connection_aborted()) {
            wp_redirect(add_query_arg('dong-bo-sync', 'success', admin_url()));
            exit;
        }

        exit;
    }

    // Show success message after redirect
    if (isset($_GET['dong-bo-sync']) && $_GET['dong-bo-sync'] === 'success' && current_user_can('manage_options')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>Đồng bộ vận đơn đã được khởi động. Quá trình có thể mất vài phút. Vui lòng kiểm tra nhật ký để xem chi tiết.</p></div>';
        });
    }
}


function ase_main()
{
    $account_service_json = get_field('account_service', 'option');
    $sheet_id = get_field('sheet_id', 'option');
    $sheet_name = get_field('sheet_name', 'option');
    try {
        $connection = ase_get_google_connection(json_decode($account_service_json, true));
        $account_service =  new Google_Service_Sheets($connection);

        ase_add_log('SYNC: Đang lấy dữ liệu từ Google Sheets...');
        $rows = ase_get_rows($account_service, $sheet_id, $sheet_name);
        $rows = ase_convert_rows($rows);
        ase_add_log('SYNC: Đã lấy được ' . count($rows) . ' dòng dữ liệu.');

        $shipping_directions = get_shipping_directions();
        $order_status = get_order_status();

        $new_rows = [];

        foreach ($rows as $row) {
            if (empty($row) || !isset($row['title'])) {
                continue;
            }
            $new_rows[] = $row;
        }

        ase_add_log('SYNC: Có ' . count($new_rows) . ' dòng hợp lệ để xử lý.');

        $titles  = array_map(fn($o) => sanitize_text_field($o['title']), $new_rows);
        $order_exists = get_order_exists($titles);

        $processed = 0;
        $total = count($new_rows);

        foreach ($new_rows as $index => $row) {
            try {
                ase_save_order_prss($row, $order_status, $shipping_directions, $order_exists);
                $processed++;

                // Log progress every 10 items or at the end
                if (($index + 1) % 10 == 0 || ($index + 1) == $total) {
                    ase_add_log('SYNC: Đã xử lý ' . ($index + 1) . '/' . $total . ' đơn hàng.');
                }
            } catch (Exception $th) {
                ase_add_log('ROW ' . $row['title'] . ' ERROR: ' .  $th->getMessage());
            }
        }

        ase_add_log('SYNC: Hoàn tất xử lý ' . $processed . '/' . $total . ' đơn hàng.');
    } catch (Exception $th) {
        ase_add_log('CONFIG ERROR: ' .  $th->getMessage());
    }
}

// function ase_save_order($data){
// 	$update = true;
// 	$field_update = [];
// 	$query = new WP_Query([
// 		'post_type'      => 'ma_van_don',
// 		'title'          => $data['title'],
// 		'posts_per_page' => 1,
// 		'fields'         => 'ids',
// 	]);

// 	if (!empty($query->posts)) {
// 		$post_id = $query->posts[0];
// 	}else{
// 		$args = array(
// 			'post_type' => 'ma_van_don',
// 			'post_status' => 'publish',
// 			'post_title' => $data['title'],
// 		);

// 		$post_id = wp_insert_post($args);
// 		$update = false;
// 	}



// 	$order_status_args = array(
// 		'post_type' => 'trang-thai-don-hang',
// 		'title' => $data['trang_thai_giao_hang'],
// 		'fields'         => 'ids',
// 	); 
// 	$order_status_query = new WP_Query($order_status_args);


// 	if(!empty($order_status_query->posts)){
// 		$prev_order_status = get_field('trang_thai_don_hang', $post_id);

// 		if($prev_order_status->ID != $order_status_query->posts[0] ){
// 			$field_update['trang_thai_don_hang'] = true;
// 		}

// 		update_field("trang_thai_don_hang", $order_status_query->posts[0], $post_id);
// 	}


// 	if(isset($data['chieu_van_chuyen_id'])){
// 		update_field("chieu_van_don", $data['chieu_van_chuyen_id'], $post_id);

// 	}else{
// 		$shipping_direction_args = array(
// 			'post_type' => 'chieu-van-chuyen',
// 			'title' => $data['chieu_don_hang'],
// 			'fields'         => 'ids',
// 		); 
// 		$shipping_direction_query = new WP_Query($shipping_direction_args);
// 		if(!empty($shipping_direction_query->posts)){
// 			update_field("chieu_van_don", $shipping_direction_query->posts[0], $post_id);
// 		}

// 	}



// 	update_field("user", $data['email'], $post_id);
// 	update_field("ma_don", $data['title'], $post_id);
// 	update_field("ten_nguoi_gui", $data['ten_nguoi_gui'], $post_id);
// 	update_field("ten_nguoi_nhan", $data['ten_nguoi_nhan'], $post_id);
// 	update_field("text_tracking_thu_ba", $data['text_tracking_thu_ba'], $post_id);
// 	update_field("link_tracking_thu_ba", $data['link_tracking_thu_ba'], $post_id);
// 	update_field("ma_van_don_thu_ba", $data['ma_van_don_thu_ba'], $post_id);

// 	update_field("tinh_thanh_nguoi_nhan", $data['dia_chi_nguoi_nhan'], $post_id);
// 	update_field("dia_chi_nguoi_nhan_chi_tiet", $data['dia_chi_nguoi_nhan_chi_tiet'], $post_id);
// 	update_field("sdt", $data['sdt'], $post_id);
// 	update_field("tien_trinh_giao_hang", convertHtmlToArray($data['tien_trinh_giao_hang']), $post_id);
// 	update_field("dia_chi_nguoi_nhan", $data['dia_chi_nguoi_nhan'], $post_id);
// 	update_field("source_order", 'googlesheet', $post_id);


// 	do_action('ase/after_save_post', $post_id, $update, $field_update);
// 	// 	do_action('ase/save_post', $order_id, false);

// 	// 	update field
// 	// 	// // 		thong tin chieu nhan hang
// 	// update_field("ma_tinh_thanh_nguoi_nhan", $data['ma_tinh_thanh_nguoi_nhan'], $post_id);
// 	// update_field("quan_huyen_nguoi_nhan", $data['quan_huyen_nguoi_nhan'], $post_id);
// 	// update_field("phuong_xa_nguoi_nhan", $data['phuong_xa_nguoi_nhan'], $post_id);
// 	// update_field("so_nha_nguoi_nhan", $data['so_nha_nguoi_nhan'], $post_id);
// 	// update_field("ten_duong_nguoi_nhan", $data['ten_duong_nguoi_nhan'], $post_id);
// 	// update_field("id_hoac_cmt", $data['id_hoac_cmt'], $post_id);


// 	// // 		thong tin chieu gui hang
// 	// update_field("nguoi_gui_lien_he", $data['nguoi_gui_lien_he'], $post_id);


// 	// update_field("trang_thai_don_hang", $trang_thai_don_hang, $post_id);
// 	// // update_field("dia_chi_nguoi_gui", $data['dia_chi_nguoi_gui'], $post_id);
// 	// update_field("gia_don_hang", $data['gia_don_hang'], $post_id);
// 	// update_field("khoi_luong_don_hang", $data['khoi_luong_don_hang'], $post_id);
// 	// update_field("loai_tien_te", $data['loai_tien_te'], $post_id);
// 	// update_field("date", $data['date'], $post_id);
// 	// update_field("chieu_van_don", $data['chieu_van_don'], $post_id);
// 	// update_field("expected_date", $data['expected_date'], $post_id);
// }

add_action('ase/after_save_post', 'after_save_post_callback', 10, 3);
function after_save_post_callback($order_id, $post_update, $field_update)
{
    if ($post_update && isset($field_update['trang_thai_don_hang'])  && $field_update['trang_thai_don_hang']) {
        send_email_status($order_id);
        return;
    }
    if (!$post_update) {
        send_email_status($order_id);
        return;
    }
}


function ase_get_rows($account_service, $spreadsheet_id, $sheet_name)
{
    $result = $account_service->spreadsheets_values->batchGet($spreadsheet_id, array('ranges' => $sheet_name . "!A:Z"));
    $valueRanges = $result->getValueRanges();
    return $valueRanges[0]['values'];
}

function ase_convert_rows($sheets_values)
{
    $result = array();
    $labels = $sheets_values[0];
    for ($i = 1; $i < count($sheets_values); $i++) {
        $row = $sheets_values[$i];
        $rowData = array();
        for ($j = 0; $j < count($row); $j++) {
            $label = $labels[$j];
            $value = $row[$j];
            $rowData[$label] = $value;
        }
        $result[] = $rowData;
    }
    return $result;
}


function ase_get_google_connection($config_json)
{
    $client = new Google\Client();
    $client->setAuthConfig($config_json);
    $client->setApplicationName("Client_Library_Examples");
    $client->setScopes(['https://www.googleapis.com/auth/spreadsheets']);
    return $client;
}



function convertHtmlToArray($html)
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    $listItems = $dom->getElementsByTagName('li');
    $result = [];

    foreach ($listItems as $li) {
        $strong = $li->getElementsByTagName('strong')->item(0);
        if ($strong) {
            $day = trim($strong->nodeValue);
            $formattedDay = DateTime::createFromFormat('d/m/Y', $day)->format('Y-m-d');
            $title = trim(str_replace($day, '', $li->nodeValue));
            $result[] = ['day' => $formattedDay, 'title' => trim($title)];
        }
    }

    return $result;
}