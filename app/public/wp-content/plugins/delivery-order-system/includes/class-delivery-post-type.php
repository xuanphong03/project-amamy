<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handle Delivery Post Type and Metaboxes
 */
class Delivery_Order_System_Delivery_Post_Type
{
    /**
     * Initialize the class
     */
    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_delivery_order_system_get_ma_van_don', array($this, 'ajax_get_ma_van_don'));
        add_action('acf/include_fields', array($this, 'register_acf_fields'));
        add_action('wp_ajax_delivery_order_system_save_ma_van_don', array($this, 'ajax_save_ma_van_don'));
        add_action('wp_ajax_delivery_order_system_save_chieu_van_chuyen', array($this, 'ajax_save_chieu_van_chuyen'));
        add_action('wp_ajax_delivery_order_system_remove_row', array($this, 'ajax_remove_row'));
        add_action('wp_ajax_delivery_order_system_generate_single_pdf', array($this, 'ajax_generate_single_pdf'));
        add_action('wp_ajax_delivery_order_system_send_single_mail', array($this, 'ajax_send_single_mail'));
        add_action('wp_ajax_delivery_order_system_export_excel', array($this, 'ajax_export_excel'));
        add_action('post_submitbox_misc_actions', array($this, 'add_send_mail_button_submitbox'));
        add_filter('redirect_post_location', array($this, 'redirect_new_delivery_after_save'), 10, 2);
    }

    /**
     * Add meta boxes
     *
     * @param string $post_type Post type
     */
    public function add_meta_boxes($post_type)
    {
        if ($post_type === 'delivery') {
            add_meta_box(
                'delivery_order_system_tao_chuyen',
                __('Tạo chuyến', 'delivery-order-system'),
                array($this, 'render_tao_chuyen_metabox'),
                'delivery',
                'normal',
                'high'
            );
        }
    }

    /**
     * Render "Tạo chuyến" metabox
     *
     * @param WP_Post $post Post object
     */
    public function render_tao_chuyen_metabox($post)
    {
        // Add nonce for security
        wp_nonce_field('delivery_order_system_tao_chuyen_nonce', 'delivery_order_system_tao_chuyen_nonce');

        // Get saved value
        $chieu_van_chuyen_id = get_post_meta($post->ID, 'chieu_van_chuyen_id', true);

        // Get all posts from chieu-van-chuyen post type
        $chieu_van_chuyen_posts = get_posts(array(
            'post_type' => 'chieu-van-chuyen',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ));
?>
        <div style="margin-bottom: 15px;">
            <label for="chieu_van_chuyen_id" style="display: block; margin-bottom: 5px; font-weight: 600;">
                <?php _e('Chiều vận chuyển', 'delivery-order-system'); ?>
                <span style="color: #d63638;">*</span>
            </label>
            <div style="display: flex; align-items: center; gap: 10px;">
                <select name="chieu_van_chuyen_id" id="chieu_van_chuyen_id" required style="width: 100%; max-width: 400px;">
                    <option value=""><?php _e('-- Chọn chiều vận chuyển --', 'delivery-order-system'); ?></option>
                    <?php foreach ($chieu_van_chuyen_posts as $post_item) : ?>
                        <option value="<?php echo esc_attr($post_item->ID); ?>"
                            <?php selected($chieu_van_chuyen_id, $post_item->ID); ?>>
                            <?php echo esc_html($post_item->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="them_don_btn" class="button button-secondary" style="white-space: nowrap;" disabled>
                    <?php _e('Thêm đơn', 'delivery-order-system'); ?>
                </button>
            </div>
            <p class="description" style="margin-top: 5px; color: #646970;">
                <?php _e('Vui lòng chọn chiều vận chuyển cho chuyến này.', 'delivery-order-system'); ?>
            </p>
        </div>

        <!-- Modal Box -->
        <div id="delivery_order_system_modal" class="delivery-order-system-modal" style="display: none;">
            <div class="delivery-order-system-modal-overlay"></div>
            <div class="delivery-order-system-modal-content">
                <div class="delivery-order-system-modal-header">
                    <h2><?php _e('Thêm đơn', 'delivery-order-system'); ?></h2>
                    <button type="button" class="delivery-order-system-modal-close"
                        aria-label="<?php esc_attr_e('Đóng', 'delivery-order-system'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="delivery-order-system-modal-body">
                    <div class="delivery-order-system-modal-search" style="margin-bottom: 15px;">
                        <input type="search" id="delivery_order_system_search" class="regular-text"
                            placeholder="<?php esc_attr_e('Tìm kiếm...', 'delivery-order-system'); ?>"
                            style="width: 100%; max-width: 300px;">
                    </div>
                    <div class="delivery-order-system-modal-table-wrapper" style="overflow-x: auto;">
                        <table class="wp-list-table widefat fixed striped" id="delivery_order_system_table">
                            <thead>
                                <tr>
                                    <td class="manage-column column-cb check-column">
                                        <input type="checkbox" id="cb-select-all">
                                    </td>
                                    <th class="manage-column"><?php _e('Mã Khách hàng', 'delivery-order-system'); ?></th>
                                    <th class="manage-column"><?php _e('Tên facebook', 'delivery-order-system'); ?></th>
                                    <th class="manage-column"><?php _e('Người gửi', 'delivery-order-system'); ?></th>
                                    <th class="manage-column"><?php _e('Email', 'delivery-order-system'); ?></th>
                                    <th class="manage-column"><?php _e('Phone', 'delivery-order-system'); ?></th>
                                    <th class="manage-column"><?php _e('Người nhận', 'delivery-order-system'); ?></th>
                                    <th class="manage-column"><?php _e('Quốc gia', 'delivery-order-system'); ?></th>
                                    <th class="manage-column"><?php _e('Thành Phố', 'delivery-order-system'); ?></th>
                                    <th class="manage-column"><?php _e('Địa chỉ', 'delivery-order-system'); ?></th>
                                    <th class="manage-column"><?php _e('Tiền tệ', 'delivery-order-system'); ?></th>
                                    <th class="manage-column"><?php _e('Vận chuyển', 'delivery-order-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="delivery_order_system_table_body">
                                <tr>
                                    <td colspan="13" style="text-align: center; padding: 20px;">
                                        <?php _e('Vui lòng chọn chiều vận chuyển để xem danh sách mã vận đơn.', 'delivery-order-system'); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="delivery-order-system-modal-pagination" style="margin-top: 15px; text-align: right;">
                        <span class="displaying-num"></span>
                        <span class="pagination-links"></span>
                    </div>
                </div>
                <div class="delivery-order-system-modal-footer">
                    <button type="button" class="button button-secondary delivery-order-system-modal-close">
                        <?php _e('Hủy', 'delivery-order-system'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="delivery_order_system_modal_save">
                        <?php _e('Lưu', 'delivery-order-system'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php $this->render_acf_delivery_manager_table($post); ?>
    <?php
    }

    /**
     * Save meta box data
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @return int|void
     */
    public function save_meta_box($post_id, $post)
    {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // Check post type
        if ($post->post_type !== 'delivery') {
            return $post_id;
        }

        // Check user permissions
        if (! current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        // Verify nonce
        if (
            ! isset($_POST['delivery_order_system_tao_chuyen_nonce']) ||
            ! wp_verify_nonce($_POST['delivery_order_system_tao_chuyen_nonce'], 'delivery_order_system_tao_chuyen_nonce')
        ) {
            return $post_id;
        }

        // Validate and save chieu_van_chuyen_id
        if (isset($_POST['chieu_van_chuyen_id'])) {
            $chieu_van_chuyen_id = absint($_POST['chieu_van_chuyen_id']);

            // Validation: Check if value is required and valid
            if (empty($chieu_van_chuyen_id)) {
                // Remove meta if empty
                delete_post_meta($post_id, 'chieu_van_chuyen_id');
            } else {
                // Validate that the post exists and is from correct post type
                $post_exists = get_post($chieu_van_chuyen_id);
                if ($post_exists && $post_exists->post_type === 'chieu-van-chuyen') {
                    update_post_meta($post_id, 'chieu_van_chuyen_id', $chieu_van_chuyen_id);
                    if (function_exists('update_field')) {
                        update_field('chieu_van_chuyen_id', $chieu_van_chuyen_id, $post_id);
                    }
                } else {
                    // Invalid post ID, show error
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-error"><p>' . __('Chiều vận chuyển không hợp lệ.', 'delivery-order-system') . '</p></div>';
                    });
                }
            }
        }

        // Save delivery_manager repeater from metabox table
        if (isset($_POST['delivery_manager_rows']) && is_array($_POST['delivery_manager_rows']) && function_exists('update_field')) {
            $previous_rows = get_field('delivery_manager', $post_id);
            $previous_ids = array();
            if (is_array($previous_rows)) {
                foreach ($previous_rows as $previous_row) {
                    $previous_id = isset($previous_row['ma_van_don']) ? absint($previous_row['ma_van_don']) : 0;
                    if ($previous_id) {
                        $previous_ids[] = $previous_id;
                    }
                }
            }

            // Get delivery direction for calculating shipping costs
            $chieu_van_chuyen_id = get_post_meta($post_id, 'chieu_van_chuyen_id', true);
            $chieu_van_chuyen_id = $chieu_van_chuyen_id ? absint($chieu_van_chuyen_id) : 0;

            $rows = array();
            $new_ids = array();
            foreach ($_POST['delivery_manager_rows'] as $row) {
                $ma_van_don = isset($row['ma_van_don']) ? absint($row['ma_van_don']) : 0;
                if (! $ma_van_don) {
                    continue;
                }
                $new_ids[] = $ma_van_don;

                // Get weight for calculating shipping cost
                $so_can = isset($row['so_can']) ? floatval($row['so_can']) : 0;

                // Auto-calculate shipping cost if weight and delivery direction are available
                $calculated_phi_van_chuyen = '';
                if ($so_can > 0 && $chieu_van_chuyen_id > 0) {
                    $calculated_cost = $this->calculate_phi_van_chuyen($so_can, $chieu_van_chuyen_id);
                    if ($calculated_cost > 0) {
                        $calculated_phi_van_chuyen = number_format($calculated_cost, 2, '.', '');
                    }
                }

                // Auto-calculate domestic delivery cost if weight and ma_van_don are available
                $calculated_phi_giao_hang_noi_dia = '';
                if ($so_can > 0 && $ma_van_don > 0) {
                    $calculated_domestic_cost = $this->calculate_phi_giao_hang_noi_dia_tai_vn($so_can, $ma_van_don);
                    if ($calculated_domestic_cost > 0) {
                        $calculated_phi_giao_hang_noi_dia = number_format($calculated_domestic_cost, 2, '.', '');
                    }
                }

                // Auto-calculate bao_hiem_sua if ma_van_don is available
                $calculated_bao_hiem_sua = '';
                if ($ma_van_don > 0) {
                    $bao_hien_sua_label = get_field('loai_thoi_gian_giao', $ma_van_don);
                    if ($bao_hien_sua_label) {
                        $bao_hien_sua_result = $this->calculate_dynamic_field($bao_hien_sua_label, $chieu_van_chuyen_id);
                        if ($bao_hien_sua_result['value'] > 0) {
                            $so_kien_value = isset($row['so_kien']) ? absint($row['so_kien']) : 1;
                            $calculated_bao_hiem_sua = floatval($bao_hien_sua_result['value']) * $so_kien_value;
                        }
                    }
                }

                // Auto-calculate minh_bach_can_nang if ma_van_don is available
                $calculated_minh_bach_can_nang = '';
                if ($ma_van_don > 0) {
                    $minh_bach_can_nang_label = get_field('minh_bach_can_nang', $ma_van_don);
                    if ($minh_bach_can_nang_label) {
                        $minh_bach_can_nang_result = $this->calculate_dynamic_field($minh_bach_can_nang_label, $chieu_van_chuyen_id);
                        if ($minh_bach_can_nang_result['value'] > 0) {
                            $so_kien_value = isset($row['so_kien']) ? absint($row['so_kien']) : 1;
                            $calculated_minh_bach_can_nang = floatval($minh_bach_can_nang_result['value']) * $so_kien_value;
                        }
                    }
                }

                // Auto-calculate bao_hiem_den_bu if ma_van_don is available
                $calculated_bao_hiem_den_bu = '';
                if ($ma_van_don > 0) {
                    $bao_hiem_den_bu_label = get_field('loai_bao_hiem', $ma_van_don);
                    if ($bao_hiem_den_bu_label) {
                        $bao_hiem_den_bu_result = $this->calculate_dynamic_field($bao_hiem_den_bu_label, $chieu_van_chuyen_id);
                        if ($bao_hiem_den_bu_result['value'] > 0) {
                            $so_kien_value = isset($row['so_kien']) ? absint($row['so_kien']) : 1;
                            $calculated_bao_hiem_den_bu = floatval($bao_hiem_den_bu_result['value']) * $so_kien_value;
                        }
                    }
                }

                // Auto-calculate loai_dong_goi if ma_van_don is available
                $calculated_loai_dong_goi = '';
                if ($ma_van_don > 0) {
                    $loai_dong_goi_label = get_field('loai_dong_goi', $ma_van_don);
                    if ($loai_dong_goi_label) {
                        $loai_dong_goi_result = $this->calculate_dynamic_field($loai_dong_goi_label, $chieu_van_chuyen_id);
                        if ($loai_dong_goi_result['value'] > 0) {
                            $so_kien_value = isset($row['so_kien']) ? absint($row['so_kien']) : 1;
                            $calculated_loai_dong_goi = floatval($loai_dong_goi_result['value']) * $so_kien_value;
                        }
                    }
                }

                $rows[] = array(
                    'ma_van_don'                  => $ma_van_don,
                    'phi_van_chuyen'              => $calculated_phi_van_chuyen, // Auto-calculated
                    'phu_thu_ruou_pin_nuoc_hoa'   => isset($row['phu_thu_ruou_pin_nuoc_hoa']) ? sanitize_text_field($row['phu_thu_ruou_pin_nuoc_hoa']) : '',
                    'phu_thu_tem_dhl_hoac_pick_up' => isset($row['phu_thu_tem_dhl_hoac_pick_up']) ? sanitize_text_field($row['phu_thu_tem_dhl_hoac_pick_up']) : '',
                    'bao_hiem_hang_hoa'           => isset($row['bao_hiem_hang_hoa']) ? sanitize_text_field($row['bao_hiem_hang_hoa']) : '',
                    'phi_quay_video_can_nang_kho' => isset($row['phi_quay_video_can_nang_kho']) ? sanitize_text_field($row['phi_quay_video_can_nang_kho']) : '',
                    'phi_giao_hang_noi_dia_tai_vn' => $calculated_phi_giao_hang_noi_dia, // Auto-calculated
                    'uu_dai'                      => isset($row['uu_dai']) ? sanitize_text_field($row['uu_dai']) : '',
                    'so_can'                      => $so_can,
                    'so_kien'                     => isset($row['so_kien']) ? absint($row['so_kien']) : '',
                    'bao_hiem_sua'                => $calculated_bao_hiem_sua, // Auto-calculated
                    'minh_bach_can_nang'          => $calculated_minh_bach_can_nang, // Auto-calculated
                    'bao_hiem_den_bu'             => $calculated_bao_hiem_den_bu, // Auto-calculated
                    'loai_dong_goi'               => $calculated_loai_dong_goi, // Auto-calculated
                );
            }

            update_field('delivery_manager', $rows, $post_id);
            $this->sync_ma_van_don_assignments($post_id, $previous_ids, $new_ids);
        }
    }

    /**
     * Enqueue scripts and styles for admin
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts($hook)
    {
        global $post_type;

        // Only load on delivery post type edit screen
        if ($post_type === 'delivery' && ($hook === 'post.php' || $hook === 'post-new.php')) {
            $post_id = 0;
            if (isset($_GET['post'])) {
                $post_id = absint($_GET['post']);
            } elseif (isset($GLOBALS['post']->ID)) {
                $post_id = absint($GLOBALS['post']->ID);
            }

            // Enqueue modal CSS
            wp_enqueue_style(
                'delivery-order-system-modal',
                DELIVERY_ORDER_SYSTEM_URL . 'admin/css/modal.css',
                array(),
                DELIVERY_ORDER_SYSTEM_VERSION
            );

            // Enqueue delivery manager table CSS
            wp_enqueue_style(
                'delivery-order-system-table',
                DELIVERY_ORDER_SYSTEM_URL . 'admin/css/delivery-manager-table.css',
                array(),
                DELIVERY_ORDER_SYSTEM_VERSION
            );

            // Enqueue modal JavaScript
            wp_enqueue_script(
                'delivery-order-system-modal',
                DELIVERY_ORDER_SYSTEM_URL . 'admin/js/modal.js',
                array('jquery'),
                DELIVERY_ORDER_SYSTEM_VERSION,
                true
            );

            // Localize script
            wp_localize_script(
                'delivery-order-system-modal',
                'deliveryOrderSystemModal',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('delivery_order_system_ajax_nonce'),
                    'saveNonce' => wp_create_nonce('delivery_order_system_save_ma_van_don'),
                    'saveChieuNonce' => wp_create_nonce('delivery_order_system_save_chieu_van_chuyen'),
                    'removeNonce' => wp_create_nonce('delivery_order_system_remove_row'),
                    'postId' => $post_id,
                    'strings' => array(
                        'pleaseSelectChieuVanChuyen' => __('Vui lòng chọn chiều vận chuyển để xem danh sách mã vận đơn.', 'delivery-order-system'),
                        'loading' => __('Đang tải...', 'delivery-order-system'),
                        'errorLoading' => __('Có lỗi xảy ra khi tải dữ liệu.', 'delivery-order-system'),
                        'noResults' => __('Không tìm thấy mã vận đơn nào.', 'delivery-order-system'),
                        'pleaseSelectAtLeastOne' => __('Vui lòng chọn ít nhất một mã vận đơn.', 'delivery-order-system'),
                        'saveSuccess' => __('Đã lưu mã vận đơn vào trường Vận chuyển.', 'delivery-order-system'),
                        'saveError' => __('Không thể lưu dữ liệu.', 'delivery-order-system'),
                        'saveChieuError' => __('Không thể lưu chiều vận chuyển.', 'delivery-order-system'),
                        'removeError' => __('Không thể xoá dòng này.', 'delivery-order-system'),
                        'removeSuccess' => __('Đã xoá.', 'delivery-order-system'),
                    ),
                    'editUrl' => $post_id ? admin_url('post.php?post=' . $post_id . '&action=edit') : '',
                )
            );
        }
    }

    /**
     * AJAX handler to get ma_van_don posts
     */
    public function ajax_get_ma_van_don()
    {
        // Verify nonce
        if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'delivery_order_system_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'delivery-order-system')));
        }

        // Check user permissions
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'delivery-order-system')));
        }

        $chieu_van_chuyen_id = isset($_POST['chieu_van_chuyen_id']) ? absint($_POST['chieu_van_chuyen_id']) : 0;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        if (empty($chieu_van_chuyen_id)) {
            wp_send_json_error(array('message' => __('Chiều vận chuyển không hợp lệ.', 'delivery-order-system')));
        }

        // Build meta query
        $meta_query = array(
            'relation' => 'AND',
            array(
                'key' => 'chieu_van_don',
                'value' => $chieu_van_chuyen_id,
                'compare' => '=',
            ),
        );

        // Build search query in meta fields
        if (! empty($search)) {
            $search_meta_query = array(
                'relation' => 'OR',
                array(
                    'key' => 'ma_khach_hang',
                    'value' => $search,
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'name_facebook',
                    'value' => $search,
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'ten_nguoi_gui',
                    'value' => $search,
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'user',
                    'value' => $search,
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'sdt',
                    'value' => $search,
                    'compare' => 'LIKE',
                ),
            );
            $meta_query[] = $search_meta_query;
        }

        // Get posts
        $args = array(
            'post_type' => 'ma_van_don',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => $meta_query,
        );

        $query = new WP_Query($args);
        $total = $query->found_posts;
        $total_pages = ceil($total / $per_page);

        $posts = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $posts[] = array(
                    'ID' => $post_id,
                    'ma_khach_hang' => sanitize_text_field(get_post_meta($post_id, 'ma_khach_hang', true)),
                    'name_facebook' => sanitize_text_field(get_post_meta($post_id, 'name_facebook', true)),
                    'ten_nguoi_gui' => sanitize_text_field(get_post_meta($post_id, 'ten_nguoi_gui', true)),
                    'email' => sanitize_text_field(get_post_meta($post_id, 'user', true)),
                    'phone' => sanitize_text_field(get_post_meta($post_id, 'sdt', true)),
                    'ten_nguoi_nhan' => sanitize_text_field(get_post_meta($post_id, 'ten_nguoi_nhan', true)),
                    'nation' => sanitize_text_field(get_post_meta($post_id, 'nation', true)),
                    'tinh_thanh_nguoi_nhan' => sanitize_text_field(get_post_meta($post_id, 'tinh_thanh_nguoi_nhan', true)),
                    'dia_chi_nguoi_nhan' => sanitize_text_field(get_post_meta($post_id, 'dia_chi_nguoi_nhan', true)),
                    'loai_tien_te' => sanitize_text_field(get_post_meta($post_id, 'loai_tien_te', true)),
                    'date' => get_the_date('Y-m-d H:i:s'),
                    'date_formatted' => get_the_date('d/m/Y H:i'),
                    'in_delivery_manager_title' => get_post_meta($post_id, 'in_delivery_manager_id', true) ? get_the_title(get_post_meta($post_id, 'in_delivery_manager_id', true)) : '',
                    'in_delivery_manager_url' => get_post_meta($post_id, 'in_delivery_manager_id', true) ? site_url('wp-admin/post.php?post=' . get_post_meta($post_id, 'in_delivery_manager_id', true) . '&action=edit') : '',
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success(array(
            'posts' => $posts,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page,
        ));
    }

    /**
     * Register ACF fields for delivery post type
     */
    public function register_acf_fields()
    {
        if (! function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group(array(
            'key' => 'group_693fbea23213a',
            'title' => 'Vận chuyển',
            'fields' => array(
                array(
                    'key' => 'field_693fbea297b08',
                    'label' => 'Vận chuyển',
                    'name' => 'delivery_manager',
                    'type' => 'repeater',
                    'layout' => 'block',
                    'pagination' => 0,
                    'min' => 0,
                    'max' => 0,
                    'collapsed' => '',
                    'button_label' => 'Add Row',
                    'rows_per_page' => 20,
                    'sub_fields' => array(
                        array(
                            'key' => 'field_693fbedd97b0a',
                            'label' => 'Mã vận đơn',
                            'name' => 'ma_van_don',
                            'type' => 'post_object',
                            'post_type' => array('ma_van_don'),
                            'return_format' => 'id',
                            'multiple' => 0,
                            'allow_null' => 0,
                            'ui' => 1,
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_693fbf0c2ca3d',
                            'label' => 'Phí vận chuyển',
                            'name' => 'phi_van_chuyen',
                            'type' => 'text',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_693fbf572ca3f',
                            'label' => 'Phụ thu rượu, pin, nước hoa',
                            'name' => 'phu_thu_ruou_pin_nuoc_hoa',
                            'type' => 'text',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_693fbfb92ca40',
                            'label' => 'Phụ thu Tem DHL hoặc Pick Up',
                            'name' => 'phu_thu_tem_dhl_hoac_pick_up',
                            'type' => 'text',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_693fc08d2ca44',
                            'label' => 'Phí giao hàng nội địa',
                            'name' => 'phi_giao_hang_noi_dia_tai_vn',
                            'type' => 'text',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_693fc0aa2ca45',
                            'label' => 'Ưu đãi',
                            'name' => 'uu_dai',
                            'type' => 'text',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_so_can_001',
                            'label' => 'Số cân',
                            'name' => 'so_can',
                            'type' => 'number',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_bao_hiem_sua_001',
                            'label' => 'Bảo hiểm sữa',
                            'name' => 'bao_hiem_sua',
                            'type' => 'number',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_minh_bach_can_nang_001',
                            'label' => 'Minh bạch cân nặng',
                            'name' => 'minh_bach_can_nang',
                            'type' => 'number',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_bao_hiem_den_bu_001',
                            'label' => 'Bảo hiểm đền bù',
                            'name' => 'bao_hiem_den_bu',
                            'type' => 'number',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_loai_dong_goi_001',
                            'label' => 'Loại đóng gói',
                            'name' => 'loai_dong_goi',
                            'type' => 'text',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                        array(
                            'key' => 'field_so_kien_001',
                            'label' => 'Số kiện',
                            'name' => 'so_kien',
                            'type' => 'number',
                            'parent_repeater' => 'field_693fbea297b08',
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'delivery',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
        ));
    }

    /**
     * Save selected ma_van_don IDs into ACF repeater
     */
    public function ajax_save_ma_van_don()
    {
        if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'delivery_order_system_save_ma_van_don')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'delivery-order-system')));
        }

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'delivery-order-system')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $selected_ids = isset($_POST['selected_ids']) ? array_map('absint', (array) $_POST['selected_ids']) : array();

        if (! $post_id || empty($selected_ids)) {
            wp_send_json_error(array('message' => __('Missing data.', 'delivery-order-system')));
        }

        if (! function_exists('get_field')) {
            wp_send_json_error(array('message' => __('ACF is required.', 'delivery-order-system')));
        }

        $existing = get_field('delivery_manager', $post_id);
        if (! is_array($existing)) {
            $existing = array();
        }

        $existing_ids = array();
        foreach ($existing as $row) {
            if (isset($row['ma_van_don'])) {
                $existing_ids[] = absint($row['ma_van_don']);
            }
        }

        $skipped_ids = array();
        foreach ($selected_ids as $id) {
            if (in_array($id, $existing_ids, true)) {
                continue;
            }
            $assigned_to = $this->get_ma_van_don_delivery_assignment($id);
            if ($assigned_to && $assigned_to !== $post_id) {
                $skipped_ids[] = $id;
                continue;
            }
            $existing[] = array(
                'ma_van_don' => $id,
            );
            $existing_ids[] = $id;
            $this->set_ma_van_don_delivery_assignment($id, $post_id);
        }

        update_field('delivery_manager', $existing, $post_id);
        $message = __('Saved successfully.', 'delivery-order-system');
        if (! empty($skipped_ids)) {
            $message .= ' ' . sprintf(
                __('The following codes are assigned to another delivery: %s', 'delivery-order-system'),
                implode(', ', $skipped_ids)
            );
        }

        wp_send_json_success(array('message' => $message));
    }

    /**
     * Remove a delivery_manager row by ma_van_don ID
     */
    public function ajax_remove_row()
    {
        if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'delivery_order_system_remove_row')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'delivery-order-system')));
        }

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'delivery-order-system')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $ma_van_don_id = isset($_POST['ma_van_don_id']) ? absint($_POST['ma_van_don_id']) : 0;

        if (! $post_id || ! $ma_van_don_id) {
            wp_send_json_error(array('message' => __('Missing data.', 'delivery-order-system')));
        }

        if (! function_exists('get_field')) {
            wp_send_json_error(array('message' => __('ACF is required.', 'delivery-order-system')));
        }

        $existing = get_field('delivery_manager', $post_id);
        if (! is_array($existing)) {
            $existing = array();
        }

        $filtered = array();
        foreach ($existing as $row) {
            $row_id = isset($row['ma_van_don']) ? absint($row['ma_van_don']) : 0;
            if ($row_id && $row_id === $ma_van_don_id) {
                continue;
            }
            $filtered[] = $row;
        }

        update_field('delivery_manager', $filtered, $post_id);
        $this->set_ma_van_don_delivery_assignment($ma_van_don_id, 0);

        wp_send_json_success(array('message' => __('Deleted successfully.', 'delivery-order-system')));
    }

    /**
     * Save selected chieu_van_chuyen_id to ACF/meta via AJAX
     */
    public function ajax_save_chieu_van_chuyen()
    {
        if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'delivery_order_system_save_chieu_van_chuyen')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'delivery-order-system')));
        }

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'delivery-order-system')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $chieu_van_chuyen_id = isset($_POST['chieu_van_chuyen_id']) ? absint($_POST['chieu_van_chuyen_id']) : 0;

        if (! $post_id || ! $chieu_van_chuyen_id) {
            wp_send_json_error(array('message' => __('Missing data.', 'delivery-order-system')));
        }

        $post_exists = get_post($chieu_van_chuyen_id);
        if (! $post_exists || $post_exists->post_type !== 'chieu-van-chuyen') {
            wp_send_json_error(array('message' => __('Chiều vận chuyển không hợp lệ.', 'delivery-order-system')));
        }

        update_post_meta($post_id, 'chieu_van_chuyen_id', $chieu_van_chuyen_id);
        if (function_exists('update_field')) {
            update_field('chieu_van_chuyen_id', $chieu_van_chuyen_id, $post_id);
        }

        wp_send_json_success(array('message' => __('Saved successfully.', 'delivery-order-system')));
    }

    /**
     * Generate PDF for single order via AJAX
     */
    public function ajax_generate_single_pdf()
    {
        error_log('PDF Generation: ajax_generate_single_pdf called');

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'delivery_order_system_ajax_nonce')) {
            error_log('PDF Generation: nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed.', 'delivery-order-system')));
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            error_log('PDF Generation: Permission denied');
            wp_send_json_error(array('message' => __('Permission denied.', 'delivery-order-system')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $ma_van_don_id = isset($_POST['ma_van_don_id']) ? absint($_POST['ma_van_don_id']) : 0;

        error_log('PDF Generation: post_id=' . $post_id . ', ma_van_don_id=' . $ma_van_don_id);

        if (!$post_id || !$ma_van_don_id) {
            error_log('PDF Generation: Missing required parameters');
            wp_send_json_error(array('message' => __('Missing required parameters.', 'delivery-order-system')));
        }

        // Get fee data for this specific order
        $rows = get_field('delivery_manager', $post_id);
        $fee_data = null;

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (isset($row['ma_van_don']) && absint($row['ma_van_don']) === $ma_van_don_id) {
                    $fee_data = self::create_fee_data_from_row($row);
                    break;
                }
            }
        }

        if (!$fee_data) {
            wp_send_json_error(array('message' => __('Order data not found.', 'delivery-order-system')));
        }

        // Generate PDF using existing class
        if (!class_exists('Delivery_Order_System_PDF_Bill')) {
            require_once plugin_dir_path(__FILE__) . 'class-pdf-bill.php';
        }

        error_log('PDF Generation: About to generate PDF');
        $pdf_result = Delivery_Order_System_PDF_Bill::generate_pdf($post_id, $ma_van_don_id, $fee_data);
        error_log('PDF Generation: PDF result: ' . ($pdf_result ? $pdf_result : 'false'));

        if ($pdf_result) {
            // Return download URL
            $upload_dir = wp_upload_dir();
            $pdf_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $pdf_result);
            error_log('PDF Generation: PDF URL: ' . $pdf_url);
            wp_send_json_success(array('pdf_url' => $pdf_url));
        } else {
            error_log('PDF Generation: Failed to generate PDF');
            wp_send_json_error(array('message' => __('Failed to generate PDF.', 'delivery-order-system')));
        }
    }

    /**
     * Send mail for single order via AJAX
     */
    public function ajax_send_single_mail()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'delivery_order_system_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'delivery-order-system')));
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'delivery-order-system')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $ma_van_don_id = isset($_POST['ma_van_don_id']) ? absint($_POST['ma_van_don_id']) : 0;

        if (!$post_id || !$ma_van_don_id) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'delivery-order-system')));
        }

        // Use existing send mail functionality
        if (!class_exists('Delivery_Order_System_Send_Mail')) {
            require_once plugin_dir_path(__FILE__) . 'class-send-mail.php';
        }

        $mail_sender = new Delivery_Order_System_Send_Mail();
        $result = $mail_sender->send_single_order_email($post_id, $ma_van_don_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Email sent successfully.', 'delivery-order-system')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send email.', 'delivery-order-system')));
        }
    }

    /**
     * Export delivery manager data to Excel XLSX
     */
    public function ajax_export_excel()
    {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'delivery_order_system_export_excel')) {
            wp_die(__('Security check failed.', 'delivery-order-system'));
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_die(__('Permission denied.', 'delivery-order-system'));
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        if (!$post_id) {
            wp_die(__('Missing delivery post ID.', 'delivery-order-system'));
        }

        // Get delivery post
        $delivery_post = get_post($post_id);
        if (!$delivery_post || $delivery_post->post_type !== 'delivery') {
            wp_die(__('Invalid delivery post.', 'delivery-order-system'));
        }

        // Get delivery manager data
        if (!function_exists('get_field')) {
            wp_die(__('ACF is required.', 'delivery-order-system'));
        }

        $rows = get_field('delivery_manager', $post_id);
        if (!is_array($rows)) {
            $rows = array();
        }

        // Load PhpSpreadsheet classes
        require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = array(
            'Mã khách hàng',
            'Tên Facebook',
            'Người gửi',
            'Email',
            'SĐT',
            'Người nhận',
            'Quốc gia',
            'Tỉnh/Thành phố',
            'Địa chỉ',
            'Tiền tệ',
            'Phí vận chuyển (€)',
            'Phụ thu rượu, pin, nước hoa (€)',
            'Phụ thu Tem DHL/Pick Up (€)',
            'Bảo hiểm hàng hóa (€)',
            'Phí quay video cân nặng kho (€)',
            'Phí giao hàng nội địa (€)',
            'Ưu đãi (€)',
            'Số cân (kg)',
            'Số kiện',
            'Thành tiền EURO (€)',
            'Thành tiền VNĐ'
        );

        // Set header row
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Style header row
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '38B6FF'],
            ],
        ];
        $sheet->getStyle('A1:' . ($col === 'A' ? 'A' : chr(ord($col) - 1)) . '1')->applyFromArray($headerStyle);

        // Get exchange rate for VND calculation
        $exchange_rate = 0;
        $chieu_van_chuyen_id = get_post_meta($post_id, 'chieu_van_chuyen_id', true);
        if ($chieu_van_chuyen_id) {
            $exchange_rate = get_field('exchange_rate_to_vnd', $chieu_van_chuyen_id);
        }
        if (!$exchange_rate) {
            $exchange_rate = get_option('delivery_exchange_rate', 31446);
        }

        // Process each row
        $rowNum = 2; // Start from row 2 (after header)
        foreach ($rows as $row) {
            $ma_van_don_id = isset($row['ma_van_don']) ? absint($row['ma_van_don']) : 0;
            if (!$ma_van_don_id) {
                continue;
            }

            // Calculate totals
            $total_eur = 0;
            $fee_fields = $this->calculate_display_fee_fields($row);

            foreach ($fee_fields as $key => $value) {
                if (!empty($value) && is_numeric($value) && !in_array($key, ['so_can', 'so_kien'])) {
                    $calculated_value = floatval($value);
                    if ($key === 'uu_dai') {
                        $total_eur -= $calculated_value;
                    } else {
                        $total_eur += $calculated_value;
                    }
                }
            }
            $total_vnd = $total_eur * floatval($exchange_rate);

            // Prepare row data
            $row_data = array(
                get_post_meta($ma_van_don_id, 'ma_khach_hang', true),
                get_post_meta($ma_van_don_id, 'name_facebook', true),
                get_post_meta($ma_van_don_id, 'ten_nguoi_gui', true),
                get_post_meta($ma_van_don_id, 'user', true),
                get_post_meta($ma_van_don_id, 'sdt', true),
                get_post_meta($ma_van_don_id, 'ten_nguoi_nhan', true),
                get_post_meta($ma_van_don_id, 'nation', true),
                get_post_meta($ma_van_don_id, 'tinh_thanh_nguoi_nhan', true),
                get_post_meta($ma_van_don_id, 'dia_chi_nguoi_nhan', true),
                get_post_meta($ma_van_don_id, 'loai_tien_te', true),
                isset($fee_fields['phi_van_chuyen']) ? floatval($fee_fields['phi_van_chuyen']) : 0,
                isset($fee_fields['phu_thu_ruou_pin_nuoc_hoa']) ? floatval($fee_fields['phu_thu_ruou_pin_nuoc_hoa']) : 0,
                isset($fee_fields['phu_thu_tem_dhl_hoac_pick_up']) ? floatval($fee_fields['phu_thu_tem_dhl_hoac_pick_up']) : 0,
                isset($fee_fields['bao_hiem_hang_hoa']) ? floatval($fee_fields['bao_hiem_hang_hoa']) : 0,
                isset($fee_fields['phi_quay_video_can_nang_kho']) ? floatval($fee_fields['phi_quay_video_can_nang_kho']) : 0,
                isset($fee_fields['phi_giao_hang_noi_dia_tai_vn']) ? floatval($fee_fields['phi_giao_hang_noi_dia_tai_vn']) : 0,
                isset($fee_fields['uu_dai']) ? floatval($fee_fields['uu_dai']) : 0,
                isset($fee_fields['so_can']) ? floatval($fee_fields['so_can']) : 0,
                isset($fee_fields['so_kien']) ? intval($fee_fields['so_kien']) : 0,
                $total_eur,
                $total_vnd
            );

            // Set data in spreadsheet
            $col = 'A';
            foreach ($row_data as $cell_value) {
                $sheet->setCellValue($col . $rowNum, $cell_value);
                $col++;
            }

            $rowNum++;
        }

        // Format numeric columns
        $numericColumns = ['K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U']; // Columns with numeric data
        foreach ($numericColumns as $col) {
            $sheet->getStyle($col . '2:' . $col . ($rowNum - 1))
                ->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
        }

        // VND column format
        $sheet->getStyle('U2:U' . ($rowNum - 1))
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // Generate filename
        $filename = 'delivery-' . $delivery_post->post_name . '-' . date('Y-m-d-H-i-s') . '.xlsx';

        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // Create Excel writer and output
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');

        exit;
    }


    /**
     * Get list of services that should be multiplied by so_kien
     * @return array Array of service keys
     */
    public static function get_services_multiplied_by_so_kien()
    {
        return ['bao_hiem_sua', 'minh_bach_can_nang', 'bao_hiem_den_bu', 'loai_dong_goi'];
    }

    /**
     * Calculate total amount for a service considering so_kien
     * @param float $unit_price Unit price from ACF
     * @param int $so_kien Number of packages
     * @return float Total amount
     */
    public static function calculate_service_total($unit_price, $so_kien = 1)
    {
        return floatval($unit_price) * intval($so_kien);
    }

    /**
     * Check if a service key should be multiplied by so_kien
     * @param string $service_key Service key to check
     * @return bool True if should be multiplied
     */
    public static function should_multiply_by_so_kien($service_key)
    {
        return in_array($service_key, self::get_services_multiplied_by_so_kien());
    }

    /**
     * Get display fee fields from ACF row data
     * @param array $row ACF repeater row data
     * @return array Fee fields for display
     */
    private function calculate_display_fee_fields($row)
    {
        return array(
            'phi_van_chuyen' => isset($row['phi_van_chuyen']) ? $row['phi_van_chuyen'] : '',
            'phu_thu_ruou_pin_nuoc_hoa' => isset($row['phu_thu_ruou_pin_nuoc_hoa']) ? $row['phu_thu_ruou_pin_nuoc_hoa'] : '',
            'phu_thu_tem_dhl_hoac_pick_up' => isset($row['phu_thu_tem_dhl_hoac_pick_up']) ? $row['phu_thu_tem_dhl_hoac_pick_up'] : '',
            'bao_hiem_hang_hoa' => isset($row['bao_hiem_hang_hoa']) ? $row['bao_hiem_hang_hoa'] : '',
            'phi_quay_video_can_nang_kho' => isset($row['phi_quay_video_can_nang_kho']) ? $row['phi_quay_video_can_nang_kho'] : '',
            'phi_giao_hang_noi_dia_tai_vn' => isset($row['phi_giao_hang_noi_dia_tai_vn']) ? $row['phi_giao_hang_noi_dia_tai_vn'] : '',
            'uu_dai' => isset($row['uu_dai']) ? $row['uu_dai'] : '',
            'so_can' => isset($row['so_can']) ? $row['so_can'] : '',
            'so_kien' => isset($row['so_kien']) ? $row['so_kien'] : '',
            'bao_hiem_sua' => isset($row['bao_hiem_sua']) ? $row['bao_hiem_sua'] : '',
            'minh_bach_can_nang' => isset($row['minh_bach_can_nang']) ? $row['minh_bach_can_nang'] : '',
            'bao_hiem_den_bu' => isset($row['bao_hiem_den_bu']) ? $row['bao_hiem_den_bu'] : '',
            'loai_dong_goi' => isset($row['loai_dong_goi']) ? $row['loai_dong_goi'] : '',
        );
    }

    /**
     * Create fee data array from ACF repeater row
     * @param array $row ACF repeater row data
     * @return array Fee data array for PDF generation
     */
    public static function create_fee_data_from_row($row)
    {
        return array(
            'phi_van_chuyen' => isset($row['phi_van_chuyen']) ? $row['phi_van_chuyen'] : '',
            'phu_thu_gia_co_dong_goi' => isset($row['phu_thu_gia_co_dong_goi']) ? $row['phu_thu_gia_co_dong_goi'] : '',
            'phu_thu_ruou_pin_nuoc_hoa' => isset($row['phu_thu_ruou_pin_nuoc_hoa']) ? $row['phu_thu_ruou_pin_nuoc_hoa'] : '',
            'phu_thu_tem_dhl_hoac_pick_up' => isset($row['phu_thu_tem_dhl_hoac_pick_up']) ? $row['phu_thu_tem_dhl_hoac_pick_up'] : '',
            'phu_thu_bao_hiem_hai_quan_do_vo' => isset($row['phu_thu_bao_hiem_hai_quan_do_vo']) ? $row['phu_thu_bao_hiem_hai_quan_do_vo'] : '',
            'bao_hiem_hang_hoa' => isset($row['bao_hiem_hang_hoa']) ? $row['bao_hiem_hang_hoa'] : '',
            'phi_quay_video_can_nang_kho' => isset($row['phi_quay_video_can_nang_kho']) ? $row['phi_quay_video_can_nang_kho'] : '',
            'phi_giao_hang_noi_dia_tai_vn' => isset($row['phi_giao_hang_noi_dia_tai_vn']) ? $row['phi_giao_hang_noi_dia_tai_vn'] : '',
            'uu_dai' => isset($row['uu_dai']) ? $row['uu_dai'] : '',
            'so_can' => isset($row['so_can']) ? $row['so_can'] : '',
            'so_kien' => isset($row['so_kien']) && $row['so_kien'] !== '' ? $row['so_kien'] : '1',
            'bao_hiem_sua' => isset($row['bao_hiem_sua']) ? $row['bao_hiem_sua'] : '',
            'minh_bach_can_nang' => isset($row['minh_bach_can_nang']) ? $row['minh_bach_can_nang'] : '',
            'bao_hiem_den_bu' => isset($row['bao_hiem_den_bu']) ? $row['bao_hiem_den_bu'] : '',
            'loai_dong_goi' => isset($row['loai_dong_goi']) ? $row['loai_dong_goi'] : '',
        );
    }

    /**
     * Calculate phi_giao_hang_noi_dia_tai_vn based on weight and ma_van_don
     * @param float $so_can Weight in kg
     * @param int $ma_van_don_id Ma van don ID
     * @return float Calculated domestic delivery cost
     */
    public function calculate_phi_giao_hang_noi_dia_tai_vn($so_can, $ma_van_don_id)
    {
        // Validate input
        if ($so_can <= 0 || !$ma_van_don_id) {
            return 0;
        }

        // Validate ma_van_don post
        $ma_van_don = get_post($ma_van_don_id);
        if (!$ma_van_don || $ma_van_don->post_type !== 'ma_van_don') {
            return 0;
        }

        // Get chieu_van_don from ma_van_don
        $chieu_van_don_id = get_post_meta($ma_van_don_id, 'chieu_van_don', true);
        if (!$chieu_van_don_id) {
            return 0;
        }

        // Get domestic_delivery data from chieu_van_don
        $domestic_delivery = get_field('domestic_delivery', $chieu_van_don_id);
        if (!$domestic_delivery || !isset($domestic_delivery['paid_delivery'])) {
            return 0;
        }

        $paid_delivery = $domestic_delivery['paid_delivery'];
        $price = isset($paid_delivery['price']) ? floatval($paid_delivery['price']) : 0;

        // Calculate: price * weight
        return $price * $so_can;
    }
    /**
     * Calculate phi_van_chuyen based on weight and delivery direction
     * @param float $so_can Weight in kg
     * @param int $chieu_van_chuyen_id Delivery direction ID
     * @return float Calculated shipping cost
     */
    public function calculate_phi_van_chuyen($so_can, $chieu_van_chuyen_id)
    {
        // Validate input
        if ($so_can <= 0 || !$chieu_van_chuyen_id) {
            return 0;
        }

        // Validate delivery direction post
        $chieu_van_chuyen = get_post($chieu_van_chuyen_id);
        if (!$chieu_van_chuyen || $chieu_van_chuyen->post_type !== 'chieu-van-chuyen') {
            return 0;
        }

        // Get delivery price list from ACF field
        $delivery_price_list = get_field('delivery_price_list', $chieu_van_chuyen_id);
        if (!$delivery_price_list || !is_array($delivery_price_list) || empty($delivery_price_list)) {
            return 0;
        }

        // Get the first price list item (assuming single price list per direction)
        $delivery_price_item = $delivery_price_list[0];
        if (!isset($delivery_price_item['delivery_price']) || !is_array($delivery_price_item['delivery_price'])) {
            return 0;
        }

        $delivery_prices = $delivery_price_item['delivery_price'];

        // Find price based on weight range
        $delivery_price = 0;
        foreach ($delivery_prices as $item) {
            $min_weight = isset($item['min_weight']) ? floatval($item['min_weight']) : 0;
            $max_weight = isset($item['max_weight']) ? floatval($item['max_weight']) : PHP_FLOAT_MAX;
            $price = isset($item['price']) ? floatval($item['price']) : 0;

            if ($so_can >= $min_weight && $so_can <= $max_weight) {
                $delivery_price = $price;
                break;
            }
        }

        // Return calculated cost: weight * price per kg
        return $so_can * $delivery_price;
    }

    private function calculate_dynamic_field($key, $chieu_van_chuyen_id)
    {
        // Validate input
        if (!$key || !$chieu_van_chuyen_id) {
            return [
                'key' => '',
                'value' => 0,
            ];
        }

        // Validate delivery direction post
        $calculated_pricing_fields = get_field('calculated_pricing_fields', $chieu_van_chuyen_id);
        if (!$calculated_pricing_fields) {
            return [
                'key' => '',
                'value' => 0,
            ];
        }

        // Special case for "Standard -Miễn phí" (free standard packaging)
        $normalized_search_key = preg_replace('/\s+/', ' ', trim($key));
        if ($normalized_search_key === 'Standard -Miễn phí') {
            return [
                'key' => 'Standard -Miễn phí',
                'value' => 0, // Free
            ];
        }

        foreach ($calculated_pricing_fields as $item) {
            // Normalize whitespace for comparison
            $normalized_item_key = preg_replace('/\s+/', ' ', trim($item['key']));

            if ($normalized_item_key === $normalized_search_key) {
                return [
                    'key' => $item['key'],
                    'value' => $item['value'],
                ];
            }
        }

        return [
            'key' => '',
            'value' => 0,
        ];
    }

    /**
     * Render ACF delivery_manager table for manual editing
     *
     * @param WP_Post $post
     */
    private function render_acf_delivery_manager_table($post)
    {
        if (! function_exists('get_field')) {
            return;
        }

        $rows = get_field('delivery_manager', $post->ID);
        if (! is_array($rows)) {
            $rows = array();
        }

        // Fetch ma_van_don details
        $render_rows = array();
        foreach ($rows as $index => $row) {
            $ma_id = isset($row['ma_van_don']) ? absint($row['ma_van_don']) : 0;
            if (! $ma_id) {
                continue;
            }
            $ma_post = get_post($ma_id);
            if (! $ma_post || $ma_post->post_type !== 'ma_van_don') {
                continue;
            }

            $render_rows[] = array(
                'ma_van_don' => $ma_id,
                'ma_khach_hang' => get_post_meta($ma_id, 'ma_khach_hang', true),
                'name_facebook' => get_post_meta($ma_id, 'name_facebook', true),
                'ten_nguoi_gui' => get_post_meta($ma_id, 'ten_nguoi_gui', true),
                'email' => get_post_meta($ma_id, 'user', true),
                'phone' => get_post_meta($ma_id, 'sdt', true),
                'ten_nguoi_nhan' => get_post_meta($ma_id, 'ten_nguoi_nhan', true),
                'nation' => get_post_meta($ma_id, 'nation', true),
                'tinh_thanh_nguoi_nhan' => get_post_meta($ma_id, 'tinh_thanh_nguoi_nhan', true),
                'dia_chi_nguoi_nhan' => get_post_meta($ma_id, 'dia_chi_nguoi_nhan', true),
                'loai_tien_te' => get_post_meta($ma_id, 'loai_tien_te', true),
                'date' => get_the_date('d/m/Y H:i', $ma_post),
                'domestic_delivery' => get_field('domestic_delivery', get_field('chieu_van_don', $ma_id)),
                'fee_fields' => $this->calculate_display_fee_fields($row),
            );
        }

    ?>
        <div class="delivery-manager-table-container">
            <h3 class="delivery-manager-heading"><?php _e('Vận chuyển đã chọn', 'delivery-order-system'); ?></h3>

            <?php if (empty($render_rows)) : ?>
                <div class="delivery-manager-empty">
                    <?php _e('Chưa có mã vận đơn nào.', 'delivery-order-system'); ?>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped delivery-manager-table">
                    <thead>
                        <tr>
                            <th><?php _e('Facebook', 'delivery-order-system'); ?></th>
                            <th><?php _e('Mã KH', 'delivery-order-system'); ?></th>
                            <th><?php _e('Phone', 'delivery-order-system'); ?></th>
                            <th><?php _e('Người nhận', 'delivery-order-system'); ?></th>
                            <th><?php _e('Số cân', 'delivery-order-system'); ?></th>
                            <th><?php _e('Số kiện', 'delivery-order-system'); ?></th>
                            <th><?php _e('Phí vận chuyển', 'delivery-order-system'); ?></th>
                            <th><?php _e('Phí giao hàng nội địa', 'delivery-order-system'); ?></th>
                            <th><?php _e('Phụ thu rượu, pin, nước hoa', 'delivery-order-system'); ?></th>
                            <th><?php _e('Phụ thu Tem DHL/Pick Up', 'delivery-order-system'); ?></th>
                            <th><?php _e('Ưu đãi', 'delivery-order-system'); ?></th>
                            <th><?php _e('Thành tiền EURO', 'delivery-order-system'); ?></th>
                            <th><?php _e('Thành tiền VNĐ', 'delivery-order-system'); ?></th>
                            <th class="delivery-manager-actions-cell"><?php _e('Thao tác', 'delivery-order-system'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($render_rows as $i => $row) : ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="delivery_manager_rows[<?php echo esc_attr($i); ?>][ma_van_don]"
                                        value="<?php echo esc_attr($row['ma_van_don']); ?>">
                                    <div class="facebook-info">
                                        <div><?php echo esc_html($row['name_facebook']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($row['ma_khach_hang']); ?></td>
                                <td><?php echo esc_html($row['phone']); ?></td>
                                <td><?php echo esc_html($row['ten_nguoi_nhan']); ?></td>
                                <td>
                                    <input type="number" name="delivery_manager_rows[<?php echo esc_attr($i); ?>][so_can]"
                                        value="<?php echo esc_attr($row['fee_fields']['so_can']); ?>" class="regular-text" step="0.01"
                                        min="0">
                                </td>
                                <td>
                                    <input type="number" name="delivery_manager_rows[<?php echo esc_attr($i); ?>][so_kien]"
                                        value="<?php echo esc_attr($row['fee_fields']['so_kien']); ?>" class="regular-text" min="0">
                                </td>
                                <td>
                                    <input type="text" name="delivery_manager_rows[<?php echo esc_attr($i); ?>][phi_van_chuyen]"
                                        value="<?php echo esc_attr($row['fee_fields']['phi_van_chuyen']); ?>" class="regular-text"
                                        readonly>
                                </td>
                                <td>
                                    <?php
                                    $phi_giao_hang_noi_dia_tai_vn_label = esc_html($row['domestic_delivery']['paid_delivery']['price']) . ' ' . $row['domestic_delivery']['paid_delivery']['currency'] . '/' . $row['domestic_delivery']['paid_delivery']['unit_type'];
                                    $phi_giao_hang_noi_dia_tai_vn_value = esc_attr($row['fee_fields']['phi_giao_hang_noi_dia_tai_vn']);
                                    ?>
                                    <div title="<?= $phi_giao_hang_noi_dia_tai_vn_label; ?>">
                                        <input type="text"
                                            name="delivery_manager_rows[<?php echo esc_attr($i); ?>][phi_giao_hang_noi_dia_tai_vn]"
                                            value="<?= $phi_giao_hang_noi_dia_tai_vn_value; ?>" class="regular-text" readonly>
                                    </div>
                                </td>
                                <td>
                                    <input type="text"
                                        name="delivery_manager_rows[<?php echo esc_attr($i); ?>][phu_thu_ruou_pin_nuoc_hoa]"
                                        value="<?php echo esc_attr($row['fee_fields']['phu_thu_ruou_pin_nuoc_hoa']); ?>"
                                        class="regular-text">
                                </td>
                                <td>
                                    <input type="text"
                                        name="delivery_manager_rows[<?php echo esc_attr($i); ?>][phu_thu_tem_dhl_hoac_pick_up]"
                                        value="<?php echo esc_attr($row['fee_fields']['phu_thu_tem_dhl_hoac_pick_up']); ?>"
                                        class="regular-text">
                                </td>
                                <td>
                                    <input type="text" name="delivery_manager_rows[<?php echo esc_attr($i); ?>][uu_dai]"
                                        value="<?php echo esc_attr($row['fee_fields']['uu_dai']); ?>" class="regular-text">
                                </td>
                                <?php
                                // Calculate totals using same logic as PDF bill
                                $total_eur = 0;
                                $exchange_rate = 0;

                                // Get exchange rate from chieu_van_don
                                $chieu_van_don_id = get_field('chieu_van_don', $row['ma_van_don']);
                                if ($chieu_van_don_id) {
                                    $exchange_rate = get_field('exchange_rate_to_vnd', $chieu_van_don_id);
                                }
                                if (!$exchange_rate) {
                                    $exchange_rate = get_option('delivery_exchange_rate', 31446);
                                }

                                // Calculate total EUR (same logic as PDF Data_Collector)
                                foreach ($row['fee_fields'] as $key => $value) {
                                    if (!empty($value) && is_numeric($value) && !in_array($key, ['so_can', 'so_kien'])) {
                                        $calculated_value = floatval($value);
                                        // Ưu đãi cần được trừ đi (giảm giá)
                                        if ($key === 'uu_dai') {
                                            $total_eur -= $calculated_value;
                                        } else {
                                            $total_eur += $calculated_value;
                                        }
                                    }
                                }
                                $total_vnd = $total_eur * floatval($exchange_rate);
                                ?>
                                <td>
                                    <input type="text" value="<?php echo number_format($total_eur, 2, '.', ''); ?> €"
                                        class="regular-text" readonly>
                                </td>
                                <td>
                                    <input type="text" value="<?php echo number_format($total_vnd, 0, ',', '.'); ?> VNĐ"
                                        class="regular-text" readonly>
                                </td>
                                <td class="delivery-manager-actions-cell">
                                    <div class="delivery-manager-actions">
                                        <button type="button" class="button button-secondary delivery-manager-download-pdf"
                                            data-ma-van-don="<?php echo esc_attr($row['ma_van_don']); ?>"
                                            title="<?php esc_attr_e('Tải PDF', 'delivery-order-system'); ?>">
                                            <span class="dashicons dashicons-pdf"></span>
                                        </button>
                                        <button type="button" class="button button-secondary delivery-manager-send-mail"
                                            data-ma-van-don="<?php echo esc_attr($row['ma_van_don']); ?>"
                                            title="<?php esc_attr_e('Gửi mail', 'delivery-order-system'); ?>">
                                            <span class="dashicons dashicons-email"></span>
                                        </button>
                                        <button type="button" class="button button-link-delete delivery-manager-remove-row"
                                            data-row="<?php echo esc_attr($i); ?>"
                                            title="<?php esc_attr_e('Xoá', 'delivery-order-system'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Sync meta values on ma_van_don posts when delivery_manager rows change.
     *
     * @param int   $delivery_id Delivery post ID
     * @param array $previous_ids IDs before update
     * @param array $new_ids      IDs after update
     */
    private function sync_ma_van_don_assignments($delivery_id, array $previous_ids, array $new_ids)
    {
        $previous_ids = array_filter(array_map('absint', $previous_ids));
        $new_ids = array_filter(array_map('absint', $new_ids));

        $removed_ids = array_diff($previous_ids, $new_ids);
        foreach ($removed_ids as $removed_id) {
            $this->set_ma_van_don_delivery_assignment($removed_id, 0);
        }

        foreach (array_unique($new_ids) as $new_id) {
            $this->set_ma_van_don_delivery_assignment($new_id, $delivery_id);
        }
    }

    /**
     * Get the delivery ID assigned to a ma_van_don.
     *
     * @param int $ma_van_don_id
     * @return int
     */
    private function get_ma_van_don_delivery_assignment($ma_van_don_id)
    {
        if (! $ma_van_don_id) {
            return 0;
        }

        return absint(get_post_meta($ma_van_don_id, 'in_delivery_manager_id', true));
    }

    /**
     * Set or clear the delivery assignment on a ma_van_don post.
     *
     * @param int $ma_van_don_id
     * @param int $delivery_id
     * @return void
     */
    private function set_ma_van_don_delivery_assignment($ma_van_don_id, $delivery_id = 0)
    {
        if (! $ma_van_don_id) {
            return;
        }

        update_post_meta($ma_van_don_id, 'in_delivery_manager_id', $delivery_id ? absint($delivery_id) : 0);
    }

    /**
     * Add "Gửi mail" button inside Publish box on single delivery edit screen
     *
     * @param WP_Post $post
     */
    public function add_send_mail_button_submitbox($post)
    {
        if (! $post || $post->post_type !== 'delivery') {
            return;
        }

        $nonce = wp_create_nonce('delivery_order_system_send_mail');
        $export_nonce = wp_create_nonce('delivery_order_system_export_excel');
    ?>
        <div class="misc-pub-section delivery-send-mail-section">
            <button type="button" class="button button-secondary delivery-send-mail-btn"
                data-post-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
                <?php esc_html_e('Gửi mail', 'delivery-order-system'); ?>
            </button>
            <span class="delivery-send-mail-spinner spinner" style="float:none; margin-left:6px;"></span>
            <button type="button" class="button button-primary delivery-export-excel-btn"
                data-post-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr($export_nonce); ?>"
                style="margin-left: 10px;">
                <?php esc_html_e('Export Excel', 'delivery-order-system'); ?>
            </button>
            <span class="delivery-export-excel-spinner spinner" style="float:none; margin-left:6px;"></span>
        </div>
        <script>
            (function($) {
                $(document).ready(function() {
                    var btn = $('.delivery-send-mail-btn');
                    var spinner = $('.delivery-send-mail-spinner');
                    btn.on('click', function() {
                        var postId = $(this).data('post-id');
                        var nonce = $(this).data('nonce');
                        if (!postId || !nonce) return;
                        btn.prop('disabled', true);
                        spinner.addClass('is-active');
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'delivery_order_system_send_mail',
                                nonce: nonce,
                                post_id: postId
                            },
                            success: function(response) {
                                if (response && response.success) {
                                    alert(
                                        '<?php echo esc_js(__('Email sent successfully.', 'delivery-order-system')); ?>'
                                    );
                                } else {
                                    var msg = (response && response.data && response.data.message) ?
                                        response.data.message :
                                        '<?php echo esc_js(__('Send mail failed.', 'delivery-order-system')); ?>';
                                    alert(msg);
                                }
                            },
                            error: function() {
                                alert(
                                    '<?php echo esc_js(__('Send mail failed.', 'delivery-order-system')); ?>'
                                );
                            },
                            complete: function() {
                                btn.prop('disabled', false);
                                spinner.removeClass('is-active');
                            }
                        });
                    });

                    var exportBtn = $('.delivery-export-excel-btn');
                    var exportSpinner = $('.delivery-export-excel-spinner');
                    exportBtn.on('click', function() {
                        var postId = $(this).data('post-id');
                        var nonce = $(this).data('nonce');
                        if (!postId || !nonce) return;
                        exportBtn.prop('disabled', true);
                        exportSpinner.addClass('is-active');

                        // Create a temporary link to download the Excel file
                        var downloadUrl = ajaxurl + '?action=delivery_order_system_export_excel&post_id=' +
                            postId + '&nonce=' + nonce;
                        var link = document.createElement('a');
                        link.href = downloadUrl;
                        link.style.display = 'none';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        // Re-enable button after a short delay
                        setTimeout(function() {
                            exportBtn.prop('disabled', false);
                            exportSpinner.removeClass('is-active');
                        }, 2000);
                    });
                });
            })(jQuery);
        </script>
<?php
    }

    /**
     * Redirect newly created delivery posts to the edit screen after saving.
     *
     * @param string $location Redirect URL
     * @param int    $post_id  Post ID
     * @return string
     */
    public function redirect_new_delivery_after_save($location, $post_id)
    {
        if (get_post_type($post_id) !== 'delivery') {
            return $location;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return $location;
        }

        if (empty($_POST['original_publish'])) {
            return $location;
        }

        $redirect = admin_url('post.php?post=' . $post_id . '&action=edit');
        return add_query_arg('message', 6, $redirect);
    }
}
