<?php
// API load more article Stephen 
// API load more article Stephen 
// API load more article Stephen 
// API load more article Stephen 

function post_stephen_api_endpoint()

{
	register_rest_route('api/v1', '/post-detail-slug/(?P<slug>[a-zA-Z0-9-]+)', [
		'methods' => 'GET',
		'callback' => 'get_post_detail',
		'permission_callback' => '__return_true',
	]);
	register_rest_route('uppromote/v1', '/posts-stephen', array(
		'methods'  => 'GET',
		'callback' => 'get_posts_stephen',
	));
}
add_action('rest_api_init', 'post_stephen_api_endpoint');

function get_posts_stephen($data)
{
	// Làm sạch các giá trị page và per_page
	$page = isset($data['page']) ? absint($data['page']) : 1;
	$per_page = isset($data['per_page']) ? absint($data['per_page']) : 6;
	$offset = ($page - 1) * $per_page;

	// Truy vấn bài viết
	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $per_page * $page,
		'paged'          => $page,
		'offset'         => 0,
		'orderby'        => 'id',
		'order'          => 'DESC',
	);

	// Lọc theo field author1 nếu được cung cấp
	if (isset($data['author'])) {
		$author1_value = sanitize_text_field($data['author']);
		$args['meta_query'] = array(
			array(
				'key'     => 'author1',
				'value'   => $author1_value,
				'compare' => 'LIKE',
			)
		);
	}

	$query = new WP_Query($args);

	// Kiểm tra có bài viết không
	if (!$query->have_posts()) {
		return new WP_Error('no_post_found', 'Không có bài viết hợp lệ', array('status' => 404));
	}

	$posts = $query->posts;
	$total_posts = $query->found_posts;
	$total_pages = ceil($total_posts / $args['posts_per_page']);
	$response = array(
		'total_posts'  => $total_posts,
		'current_page' => $args['paged'],
		'total_pages'  => $total_pages,
		'posts'        => array(),
	);

	// Vòng lặp để tạo phản hồi JSON cho mỗi bài viết
	foreach ($posts as $post) {
		$title = $post->post_title;
		$slug = get_the_permalink($post->ID);
		$feature_image = get_the_post_thumbnail_url($post->ID);
		$post_date = date_i18n('M j, Y', strtotime($post->post_date)); // Định dạng ngày thành Aug 21, 2024
		$excerpt = get_the_excerpt($post->ID);

		// Lấy danh sách danh mục của bài viết
		$categories = get_the_category($post->ID);
		$category_names = wp_list_pluck($categories, 'name'); // Chỉ lấy tên danh mục

		$response['posts'][] = array(
			'title'         => $title,
			'slug'          => $slug,
			'feature_image' => $feature_image,
			'excerpt'       => $excerpt,
			'date'          => $post_date,
			'categories'    => $category_names,
		);
	}

	return rest_ensure_response($response);
}



// API load more customer success
// API load more customer success
// API load more customer success
// API load more customer success

function post_customer_success_api_endpoint()
{
	register_rest_route('uppromote/v1', '/posts-customer-success', array(
		'methods'  => 'GET',
		'callback' => 'get_posts_customer_success',
	));
}
add_action('rest_api_init', 'post_customer_success_api_endpoint');

function get_posts_customer_success($data)
{
	// Làm sạch các giá trị page và per_page
	$page = isset($data['page']) ? absint($data['page']) : 1;
	$per_page = isset($data['per_page']) ? absint($data['per_page']) : 9;
	$offset = ($page - 1) * $per_page;

	// Truy vấn bài viết
	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $per_page * $page,
		'paged'          => $page,
		'offset'         => 0,
		'orderby'        => 'id',
		'order'          => 'DESC',
	);
	$query = new WP_Query($args);

	// Kiểm tra có bài viết không
	if (!$query->have_posts()) {
		return new WP_Error('no_post_found', 'Không có bài viết hợp lệ', array('status' => 404));
	}

	$posts = $query->posts;
	$total_posts = $query->found_posts;
	$total_pages = ceil($total_posts / $args['posts_per_page']);

	$response = array(
		'total_posts'  => $total_posts,
		'current_page' => $args['paged'],
		'total_pages'  => $total_pages,
		'posts'        => array(),
	);

	// Vòng lặp để tạo phản hồi JSON cho mỗi bài viết
	foreach ($posts as $post) {
		$title = $post->post_title;
		$slug = get_the_permalink($post->ID);
		$feature_image = get_the_post_thumbnail_url($post->ID);
		$brand_name = get_field('brand_name', $post->ID);
		$logo = get_field('brand_logo', $post->ID);
		// Lấy danh sách danh mục của bài viết
		$categories = get_the_category($post->ID);
		$first_category_name = isset($categories[0]) ? $categories[0]->name : '';


		$response['posts'][] = array(
			'title'         => $title,
			'slug'          => $slug,
			'feature_image' => $feature_image,
			'categories'    => $first_category_name,
			'brand_name'    => $brand_name,
			'logo'            => $logo,
		);
	}

	return rest_ensure_response($response);
}
//Api lọc integration theo taxonomy
add_action('rest_api_init', function () {
	register_rest_route('uppromote/v1', '/get-integrations', [
		'methods' => 'POST',
		'callback' => 'get_integration_posts',
		'permission_callback' => '__return_true', // Public API (có thể thay bằng kiểm tra quyền nếu cần)
	]);
});

function get_integration_posts($request)
{
	// Lấy danh sách ID của taxonomy từ request
	$taxonomy_ids = $request->get_param('taxonomy_ids');

	// Nếu taxonomy_ids là rỗng, set nó thành null để không lọc theo taxonomy
	$tax_query = [];

	if (!empty($taxonomy_ids) && is_array($taxonomy_ids)) {
		// Chỉ thêm tax_query nếu taxonomy_ids không rỗng
		$tax_query = [
			[
				'taxonomy' => 'integration-categories',
				'field' => 'term_id',
				'terms' => $taxonomy_ids,
				'operator' => 'IN',
			],
		];
	}

	// Query bài viết với hoặc không có taxonomy
	$query_args = [
		'post_type' => 'integration',
		'posts_per_page' => -1,
		'post_status' => 'publish',
		'tax_query' => $tax_query,
		'orderby' => 'id',
		'order' => 'DESC',
	];

	$query = new WP_Query($query_args);

	if (!$query->have_posts()) {
		return rest_ensure_response([
			'success' => false,
			'message' => 'Không tìm thấy bài viết nào.',
		]);
	}

	// Lấy dữ liệu bài viết
	$posts = [];
	while ($query->have_posts()) {
		$query->the_post();

		$terms = get_the_terms(get_the_ID(), 'integration-categories');
		$term_names = $terms ? wp_list_pluck($terms, 'name') : [];

		// Lấy URL của ảnh từ ID (nếu có)
		$thumbnail_image_id = get_field('thumbnail_image'); // ID ảnh
		$thumbnail_image_url = $thumbnail_image_id ? wp_get_attachment_url($thumbnail_image_id) : '';

		$posts[] = [
			'id' => get_the_ID(),
			'title' => get_the_title(),
			'excerpt' => get_the_excerpt(),
			'thumbnail_image' => $thumbnail_image_url, // Trả về URL của ảnh thay vì ID
			'terms' => $term_names,
		];
	}
	wp_reset_postdata();

	return rest_ensure_response([
		'success' => true,
		'posts' => $posts,
	]);
}
// lấy chi tiết bài post
add_action('rest_api_init', function () {
	register_rest_route('uppromote/v1', '/get-post-details', [
		'methods' => 'POST',
		'callback' => 'get_custom_post_details',
		'permission_callback' => '__return_true', // Public API (có thể thay đổi thành kiểm tra quyền nếu cần)
	]);
});

function get_custom_post_details($request)
{
	// Lấy post ID từ request
	$post_id = $request->get_param('post_id');

	if (empty($post_id) || !is_numeric($post_id)) {
		return new WP_Error('invalid_param', 'post_id phải là một số hợp lệ.', ['status' => 400]);
	}

	// Kiểm tra xem bài viết có tồn tại và đã xuất bản không
	$post = get_post($post_id);
	if (!$post || $post->post_status !== 'publish') {
		return new WP_Error('not_found', 'Không tìm thấy bài viết hoặc bài viết không được xuất bản.', ['status' => 404]);
	}

	// Lấy URL của ảnh từ custom field
	$image_id = get_field('image', $post_id); // 'image' là tên custom field
	$image_url = $image_id ? wp_get_attachment_url($image_id) : '';

	// Lấy custom field title
	$custom_title = get_field('title', $post_id);

	// Lấy danh sách thông tin từ custom field
	$list_information_post = get_field('list_information_post', $post_id); // Thay tên meta key nếu cần

	// Trả về dữ liệu bài viết
	$response = [
		'success' => true,
		'post' => [
			'id' => $post_id,
			'title' => $custom_title, // Custom field 'title'
			'image' => $image_url,    // URL của ảnh từ custom field 'image'
			'list_information_post' => $list_information_post, // Custom field
		],
	];

	return rest_ensure_response($response);
}
//search Affiliate Program Directory

add_action('rest_api_init', function () {
	register_rest_route('custom-api/v1', '/filter-posts', [
		'methods'  => 'POST',
		'callback' => 'filter_directory_posts',
		'permission_callback' => '__return_true', // Cho phép truy cập công khai
	]);
});

function filter_directory_posts($data)
{
	// Lấy tham số từ request
	$search_name = sanitize_text_field($data->get_param('name')); // Tìm kiếm theo tên
	$taxonomy_filters = $data->get_param('taxonomy_filters'); // Bộ lọc taxonomy
	$price_from = floatval($data->get_param('price_from')); // Giá thấp nhất
	$price_to = floatval($data->get_param('price_to'));     // Giá cao nhất
	$cookie_from = intval($data->get_param('cookie_from')); // Cookie thấp nhất
	$cookie_to = intval($data->get_param('cookie_to'));     // Cookie cao nhất
	$commission_rate = floatval($data->get_param('commission_rate')); // Tỷ lệ hoa hồng
	$current_page = max(1, intval($data->get_param('page'))); // Trang hiện tại, mặc định là 1
	$posts_per_page = 12; // Số bài viết mỗi trang

	// Kiểm tra điều kiện không hợp lệ
	if (($price_to > 0 && $price_to < $price_from) || ($cookie_to > 0 && $cookie_to < $cookie_from)) {
		return new WP_REST_Response(['ok' => false, 'message' => 'No posts found'], 404);
	}

	// Query posts
	$query_args = [
		'post_type'      => 'directory',
		'posts_per_page' => $posts_per_page,
		'post_status'    => 'publish',
		'orderby'        => 'id',
		'order'          => 'DESC',
		'paged'          => $current_page, // Thêm thông số phân trang
	];

	// Thêm điều kiện tìm kiếm theo tên (nếu có)
	if (!empty($search_name)) {
		$query_args['s'] = $search_name;
	}

	// Thêm điều kiện tax_query nếu có bộ lọc taxonomy
	if (!empty($taxonomy_filters) && is_array($taxonomy_filters)) {
		$tax_query = [];

		foreach ($taxonomy_filters as $filter) {
			if (!empty($filter['taxonomy']) && !empty($filter['terms'])) {
				// Chuyển đổi tên taxonomy nếu cần
				$filter['taxonomy'] = str_replace('_', '-', $filter['taxonomy']);
				$tax_query[] = [
					'taxonomy' => sanitize_text_field($filter['taxonomy']),
					'field'    => 'term_id',
					'terms'    => array_map('intval', $filter['terms']),
					'operator' => 'IN',
				];
			}
		}

		// Thêm tax_query vào query_args nếu có bộ lọc taxonomy
		if (!empty($tax_query)) {
			$query_args['tax_query'] = $tax_query;
		}
	}

	// Thêm điều kiện meta_query để filter theo giá, cookie và commission_rate
	$meta_query = [];

	if ($price_from > 0) {
		$meta_query[] = [
			'key'     => 'commissions',
			'value'   => $price_from,
			'type'    => 'NUMERIC',
			'compare' => '>=',
		];
	}

	if ($price_to > 0) {
		$meta_query[] = [
			'key'     => 'commissions',
			'value'   => $price_to,
			'type'    => 'NUMERIC',
			'compare' => '<=',
		];
	}

	if ($cookie_from > 0) {
		$meta_query[] = [
			'key'     => 'cookies',
			'value'   => $cookie_from,
			'type'    => 'NUMERIC',
			'compare' => '>=',
		];
	}

	if ($cookie_to > 0) {
		$meta_query[] = [
			'key'     => 'cookies',
			'value'   => $cookie_to,
			'type'    => 'NUMERIC',
			'compare' => '<=',
		];
	}

	// Thêm điều kiện meta_query cho commission_rate
	if ($commission_rate > 0) {
		$meta_query[] = [
			'key'     => 'commissions_rate',
			'value'   => $commission_rate,
			'type'    => 'NUMERIC',
			'compare' => '<',
		];
	}

	if (!empty($meta_query)) {
		$query_args['meta_query'] = $meta_query;
	}

	// Thực hiện truy vấn WP_Query
	$query = new WP_Query($query_args);

	// Chuẩn bị dữ liệu trả về
	if ($query->have_posts()) {
		$posts = [];
		while ($query->have_posts()) {
			$query->the_post();

			$posts[] = [
				'id'            => get_the_ID(),
				'title'         => remove_affiliate_program(get_the_title()),
				'thumbnail'     => has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'full') : get_template_directory_uri() . '/assets/icons/Rectangle-34626452.webp',
				'cookies'       => get_field('cookies') . ' days',
				'commission'    => get_commission(), // Gọi hàm ở đây thay vì để hàm vô danh
				'niche_tags'    => wp_list_pluck(get_the_terms(get_the_ID(), 'niche'), 'name'),
				'commission_check' => get_field('commission_checkbox'),
				'commission_rate' => get_field('commissions_rate'),
				'commission_rate_text' => '$' . get_field('commissions_rate_text') . ' per sale',
				'permalink'     => get_permalink(),
			];
		}
		wp_reset_postdata();

		// Tổng số bài viết và số trang
		$total = $query->found_posts;
		$total_pages = $query->max_num_pages;

		return new WP_REST_Response([
			'ok'           => true,
			'posts'        => $posts,
			'total'        => $total,
			'totalPages'   => $total_pages,
			'currentPage'  => $current_page,
		], 200);
	} else {
		return new WP_REST_Response(['ok' => false, 'message' => 'No posts found'], 404);
	}
}

// Hàm get_commission() phải được khai báo ngoài vòng lặp while
function get_commission()
{
	$commissions = get_field('commissions');
	$commissions_max = get_field('commissions_max');
	if ($commissions_max) {
		return '$' . $commissions . ' - $' . $commissions_max . ' per sale';
	} else {
		return '$' . $commissions . ' per sale';
	}
}
// hàm cắt chuỗi Affiliate Program
function remove_affiliate_program($title)
{
	return str_replace('Affiliate Program', '', $title);
}

add_action('rest_api_init', function () {
	register_rest_route('api/v1', '/pages/(?P<id>\d+)', array(
		'methods' => 'GET',
		'callback' => 'get_post_fields',
		'permission_callback' => '__return_true',
	));
	register_rest_route('api/v1', '/(?P<post_type>[a-zA-Z0-9_-]+)/(?P<slug>[^/]+)', array(
		'methods'  => 'GET',
		'callback' => 'get_post_fields_by_slug',
		'permission_callback' => '__return_true',
	));
});

function get_post_fields_by_slug($request)
{
	$post_type = $request['post_type'];
	$slug = $request['slug'];
	$fields = isset($request['_fields']) ? explode(',', $request['_fields']) : [];
	$sub_fields = isset($request['_sub_fields']) ? explode(',', $request['_sub_fields']) : [];
	$limit = isset($request['limit']) ? (int)$request['limit'] : 10;

	// Kiểm tra post type có hợp lệ không
	$allowed_post_types = get_post_types(['public' => true], 'names');
	if (!in_array($post_type, $allowed_post_types, true)) {
		return new WP_Error('invalid_post_type', 'Invalid post type', ['status' => 400]);
	}

	// Truy vấn post theo slug trong post type được chỉ định
	$args = [
		'name'           => $slug,
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => 1
	];
	$posts = get_posts($args);

	if (empty($posts)) {
		return new WP_Error('not_found', 'Post not found', ['status' => 404]);
	}

	$post = $posts[0]; // Lấy bài viết đầu tiên tìm được
	$data = ['title' => get_the_title($post)];

	if (!empty($fields)) {
		foreach ($fields as $field) {
			$field_data = get_field($field, $post->ID);
			$data[$field] = get_post_format_data($field_data, $limit, $sub_fields);
		}
	} else {
		$data = array_merge($data, get_post_format_post($post, $limit, $sub_fields));
	}

	return $data;
}

function get_post_fields($request)
{
	$id = $request['id'];
	$fields = isset($request['_fields']) ? explode(',', $request['_fields']) : [];
	$sub_fields = isset($request['_sub_fields']) ? explode(',', $request['_sub_fields']) : [];
	$limit = isset($request['limit']) ? (int)$request['limit'] : 10;
	$post = get_post($id);

	if (!$post) {
		return new WP_Error('not_found', 'Post not found', ['status' => 404]);
	}

	$data = ['title' => get_the_title($post)];

	if (!empty($fields)) {
		foreach ($fields as $field) {
			$field_data = get_field($field, $post->ID);
			$data[$field] = get_post_format_data($field_data, $limit, $sub_fields);
		}
	} else {
		$data = array_merge($data, get_post_format_post($post, $limit, $sub_fields));
	}

	return $data;
}

function get_post_format_post(WP_Post $post, $limit = 10, $sub_fields = [])
{
	$post_data = wp_post_to_array($post);
	$acf_fields = get_fields($post->ID) ?: [];

	foreach ($acf_fields as $field => $value) {
		if (!empty($sub_fields) && !in_array($field, $sub_fields)) {
			continue;
		}
		$post_data[$field] = get_post_format_data($value, $limit, $sub_fields);
	}

	return $post_data;
}

function get_post_format_term(WP_Term $term, $limit)
{
	return [
		'id'          => $term->term_id,
		'name'        => $term->name,
		'slug'        => $term->slug,
		'description' => $term->description,
		'count'       => $term->count,
		'icon'        => get_field('icon', $term),
		'posts'       => get_term_posts($term, $limit),
	];
}

function get_term_posts(WP_Term $term, $limit)
{
	$args = [
		'post_type'      => 'post',
		'posts_per_page' => $limit,
		'tax_query'      => [
			[
				'taxonomy' => $term->taxonomy,
				'field'    => 'slug',
				'terms'    => [$term->slug],
			],
		],
	];
	$query = new WP_Query($args);
	$posts = [];

	while ($query->have_posts()) {
		$query->the_post();
		$posts[] = wp_post_to_array(get_post());
	}
	wp_reset_postdata();

	return $posts;
}

function get_post_format_data($data, $limit, $sub_fields)
{
	if (is_a($data, 'WP_Post')) {
		return get_post_format_post($data, $limit, $sub_fields);
	}

	if (is_a($data, 'WP_Term')) {
		return get_post_format_term($data, $limit);
	}

	if (is_array($data)) {
		return array_map(fn($item) => get_post_format_data($item, $limit, $sub_fields), $data);
	}

	return $data;
}

function wp_post_to_array(WP_Post $post)
{
	return [
		'ID'         => $post->ID,
		'title'      => get_the_title($post),
		'slug'       => $post->post_name,
		'content'    => apply_filters('the_content', $post->post_content),
		'excerpt'    => get_the_excerpt($post),
		'status'     => $post->post_status,
		'type'       => $post->post_type,
		'author'     => get_the_author_meta('display_name', $post->post_author),
		'date'       => get_the_date('c', $post),
		'modified'   => get_the_modified_date('c', $post),
		'categories' => wp_get_post_terms($post->ID, 'category', ['fields' => 'names']),
		'tags'       => wp_get_post_terms($post->ID, 'post_tag', ['fields' => 'names']),
		'thumbnail'  => get_the_post_thumbnail_url($post, 'full'),
		//         'meta'       => get_post_meta($post->ID),
	];
}

// api get blogs
add_action('rest_api_init', function () {
	register_rest_route('api/v1', '/blogs', [
		'methods' => 'GET',
		'callback' => 'get_blogs',
		'permission_callback' => '__return_true',
	]);
});

function get_blogs($request)
{
	$category_names = $request->get_param('categories'); // Danh sách danh mục (chuỗi hoặc mảng)
	$limit = $request->get_param('limit') ?: 10;
	$paged = $request->get_param('page') ?: 1;

	$args = [
		'post_type'      => 'post',
		'posts_per_page' => $limit,
		'paged'          => $paged,
		'orderby'        => 'ID',
		'order'          => 'DESC',
	];

	// Nếu có danh mục, thêm điều kiện lọc
	if (!empty($category_names)) {
		if (is_string($category_names)) {
			$category_names = explode(',', $category_names); // Chuyển chuỗi thành mảng
		}

		$args['tax_query'] = [
			[
				'taxonomy' => 'category',
				'field'    => 'slug', // Có thể dùng 'id' nếu truyền ID
				'terms'    => $category_names,
			]
		];
	}

	$query = new WP_Query($args);
	$total_items = $query->found_posts; // Tổng số bài viết
	$total_paged = ceil($total_items / $limit); // Tổng số trang

	$posts = [];

	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();

			// Lấy thông tin ảnh đại diện đầy đủ
			$image_id = get_post_thumbnail_id(get_the_ID());
			$image = $image_id ? wp_get_attachment_image_src($image_id, 'full') : null;
			$image_data = [
				'id'     => $image_id,
				'url'    => $image[0] ?? null,
				'width'  => $image[1] ?? null,
				'height' => $image[2] ?? null,
				'alt'    => get_post_meta($image_id, '_wp_attachment_image_alt', true),
			];

			$posts[] = [
				'title'   => get_the_title(),
				'date'    => get_the_date(),
				'slug' => get_post_field('post_name', get_the_ID()),
				'image'   => $image_data,
				'categories' => wp_list_pluck(get_the_category(), 'name')[0] ?? '',
			];
		}
	}

	wp_reset_postdata();

	return rest_ensure_response([
		'total_items'   => $total_items,
		'current_paged' => (int) $paged,
		'total_paged'   => (int) $total_paged,
		'posts'         => $posts,
	]);
}
/// api get fields in option paged
add_action('rest_api_init', function () {
	register_rest_route('api/v1', '/options', [
		'methods' => 'GET',
		'callback' => 'get_global',
		'permission_callback' => '__return_true',
	]);
});
function get_global($request)
{
	$fields = $request->get_param('fields'); // Lấy fields từ request
	$page   = $request->get_param('page') ?: 'global'; // Mặc định là 'global' nếu không truyền page

	if (empty($fields)) {
		return rest_ensure_response([
			'data'    => null,
			'message' => 'Vui lòng cung cấp fields',
			'status'  => 400
		]);
	}

	// Lấy dữ liệu từ options page (ACF hoặc get_option)
	if ($page === 'global') {
		$data = [];
		$field_list = explode(',', $fields); // Chuyển fields thành mảng

		foreach ($field_list as $field) {
			$field = trim($field);
			$value = get_field($field, 'option'); // Lấy dữ liệu từ ACF options page

			// Nếu field là 'you_might_like_it' hoặc 'you_might_like_it_details' và nó là post object, xử lý riêng
			if (in_array($field, ['you_might_like_it', 'you_might_like_it_details']) && !empty($value)) {
				$posts = [];

				if (is_array($value)) { // Nếu là danh sách bài viết
					foreach ($value as $post) {
						$posts[] = format_post_data($post);
					}
				} else { // Nếu chỉ có một bài viết
					$posts[] = format_post_data($value);
				}

				$data[$field] = $posts;
			} else {
				$data[$field] = $value;
			}
		}

		return rest_ensure_response([
			'data'    => $data,
			'message' => 'Lấy dữ liệu thành công',
			'status'  => 200
		]);
	}

	return rest_ensure_response([
		'data'    => null,
		'message' => 'Page không hợp lệ',
		'status'  => 400
	]);
}


// Hàm format bài viết
function format_post_data($post)
{
	$categories = get_the_terms($post->ID, 'category');
	$category_name = $categories && !is_wp_error($categories) ? $categories[0]->name : null; // Chỉ lấy tên của category đầu tiên
	return [
		'title'      => get_the_title($post->ID),
		'slug'       => get_post_field('post_name', $post->ID),
		'category'   => $category_name, // Chỉ lấy tên của category đầu tiên

	];
}
// api search post
add_action('rest_api_init', function () {
	register_rest_route('api/v1', '/search', [
		'methods' => 'GET',
		'callback' => 'search_posts',
		'permission_callback' => '__return_true',
	]);
});

function search_posts()
{
	$keywords = isset($_GET['keywords']) ? sanitize_text_field($_GET['keywords']) : '';
	$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 9;
	$paged = isset($_GET['page']) ? intval($_GET['page']) : 1;
	$categories = isset($_GET['categories']) ? sanitize_text_field($_GET['categories']) : '';

	$args = [
		'post_type'      => 'post',
		'posts_per_page' => $limit,
		'paged'          => $paged,
		's'              => $keywords,
	];

	if (!empty($categories)) {
		$args['category_name'] = $categories;
	}

	$query = new WP_Query($args);

	$posts = [];
	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();
			$image_id = get_post_thumbnail_id(get_the_ID());
			$image = $image_id ? wp_get_attachment_image_src($image_id, 'full') : null;
			$image_data = [
				'id'     => $image_id,
				'url'    => $image[0] ?? null,
				'width'  => $image[1] ?? null,
				'height' => $image[2] ?? null,
				'alt'    => get_post_meta($image_id, '_wp_attachment_image_alt', true),
			];
			$posts[] = [
				'title'      => get_the_title(),
				'image'      => $image_data,
				'slug'       => get_post_field('post_name', get_the_ID()),
				'categories' => wp_list_pluck(get_the_category(), 'name'),
				'date'       => get_the_date(),
			];
		}
		wp_reset_postdata();
	}

	return rest_ensure_response([
		'status'        => 200,
		'message'       => 'Success',
		'data'          => $posts,
		'total'         => $query->found_posts,
		'total_pages'   => $query->max_num_pages,
		'current_page'  => $paged,
	]);
}

function get_post_detail(WP_REST_Request $request) {
	$slug = sanitize_text_field($request->get_param('slug'));

	// Truy vấn bài viết chính xác hơn
	$args = [
		'name'           => $slug,
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 1
	];
	$query = new WP_Query($args);
	$post  = $query->have_posts() ? $query->post : null;

	if (!$post) {
		return new WP_Error('no_post', 'Không tìm thấy bài viết', ['status' => 404]);
	}

	// Lấy danh mục của bài viết
	$categories = get_the_category($post->ID);
	$category_ids = wp_list_pluck($categories, 'term_id');
	$category_names = wp_list_pluck($categories, 'name');

	// Lấy ảnh đại diện
	$image_id = get_post_thumbnail_id($post->ID);
	$image_data = null;

	if ($image_id) {
		$image = wp_get_attachment_image_src($image_id, 'full');
		$image_data = [
			'id'     => $image_id,
			'url'    => $image[0] ?? null,
			'width'  => $image[1] ?? null,
			'height' => $image[2] ?? null,
			'alt'    => get_post_meta($image_id, '_wp_attachment_image_alt', true),
		];
	}

	// Dữ liệu bài viết
	$post_data = [
		'id'         => $post->ID,
		'title'      => get_the_title($post->ID),
		'content'    => apply_filters('the_content', $post->post_content),
		'slug'       => $post->post_name,
		'excerpt'    => get_the_excerpt($post->ID),
		'image'      => $image_data,
		'categories' => $category_names,
		'date'       => get_the_date('', $post->ID),
	];

	// Lấy bài viết liên quan (cùng danh mục, trừ bài hiện tại)
	$related_posts = [];

	if (!empty($category_ids)) {
		$related_args = [
			'post_type'      => 'post',
			'posts_per_page' => 5,
			'post__not_in'   => [$post->ID],
			'category__in'   => $category_ids,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$related_query = new WP_Query($related_args);

		if ($related_query->have_posts()) {
			while ($related_query->have_posts()) {
				$related_query->the_post();
				$related_image_id = get_post_thumbnail_id(get_the_ID());
				$related_image = $related_image_id ? wp_get_attachment_image_src($related_image_id, 'full') : null;
				$related_image_data = [
					'id'     => $related_image_id,
					'url'    => $related_image[0] ?? null,
					'width'  => $related_image[1] ?? null,
					'height' => $related_image[2] ?? null,
					'alt'    => get_post_meta($related_image_id, '_wp_attachment_image_alt', true),
				];
				$related_posts[] = [
					'id'         => get_the_ID(),
					'title'      => get_the_title(),
					'slug'       => get_post_field('post_name', get_the_ID()),
					'excerpt'    => get_the_excerpt(),
					'image'      => $related_image_data,
					'categories' => wp_list_pluck(get_the_category(), 'name'),
					'date'       => get_the_date(),
				];
			}
			wp_reset_postdata();
		}
	}

	return rest_ensure_response([
		'status'         => 200,
		'message'        => 'Success',
		'post'           => $post_data,
		'related_posts'  => $related_posts,
	]);
}

//get categories name and slug in post
add_action('rest_api_init', function () {
	register_rest_route('api/v1', '/categories', [
		'methods' => 'GET',
		'callback' => 'get_categories_post',
		'permission_callback' => '__return_true',
	]);
});
function get_categories_post()
{
	$categories = get_categories(['hide_empty' => false]);

	$data = [];
	foreach ($categories as $category) {
		$data[] = [
			'id'   => $category->term_id,
			'name' => $category->name,
			'slug' => $category->slug,
		];
	}

	return rest_ensure_response([
		'status'     => 200,
		'message'    => 'Success',
		'categories' => $data,
	]);
}
//chieu van chuyen 
function custom_api_get_chieu_van_chuyen_posts() {
	// Kiểm tra nếu function get_field() tồn tại (để tránh lỗi khi ACF chưa kích hoạt)
	if (!function_exists('get_field')) {
		return new WP_Error('acf_missing', 'Advanced Custom Fields plugin is required.', array('status' => 500));
	}

	$args = array(
		'post_type'      => 'chieu-van-chuyen',
		'posts_per_page' => -1, // Lấy tất cả các bài post
	);

	$posts = get_posts($args);
	$formatted_posts = array();

	foreach ($posts as $post) {
		setup_postdata($post); // Đảm bảo dữ liệu post được đồng bộ đúng cách
		$thumbnail = get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: ''; // Kiểm tra nếu không có ảnh
		$title = get_the_title($post->ID);
		$type = get_field('type_order', $post->ID);
		$european = get_field('european_afternoon', $post->ID);
		$time = get_field('vn_duc_time', $post->ID) ?: '';
		$note = get_field('note_more', $post->ID) ?: '';
		$instruct = get_field('Instruct', $post->ID) ?: '';
		$insurance = get_field('insurance', $post->ID) ?: '';
		$return_shipping_cost = get_field('return_shipping_cost', $post->ID) ?: '';
		$important_note = get_field('important_note', $post->ID) ?: '';
		$payment_method = get_field('payment_method', $post->ID) ?: '';
		$package = get_field('package', $post->ID) ?: '';
		$note_page_huong_dan = get_field('note_page_huong_dan', $post->ID) ?: '';
		$hidden_shipping = get_field('hidden_shipping', $post->ID) ?: '';
		$nation = get_field('nation', $post->ID) ?: '';

		$formatted_posts[] = array(
			'id' => $post->ID,
			'thumbnail' => $thumbnail,
			'title'     => $title,
			'type'		=> $type,
			'european'		=> $european,
			'note_page_huong_dan' => $note_page_huong_dan,
			'nation' => $nation,
			'information' => array(
				'time' => $time,
				'note' => $note,
				'instruct' => $instruct,
				'insurance' => $insurance,
				'shipping_cost' => $return_shipping_cost,
				'important_note' => $important_note,
				'payment_method' => $payment_method,
				'package' => $package,
				'hidden_shipping' => $hidden_shipping,
			),
		);
	}
	// 	$data = array(
	// 		'data_tab' => '', 
	// 		'data' => $formatted_posts,
	// 	);
	wp_reset_postdata(); // Đặt lại dữ liệu bài viết để tránh lỗi đồng bộ

	return $formatted_posts;
}

add_action('rest_api_init', function () {
	register_rest_route('api/v1', '/chieu-van-chuyen-posts', array(
		'methods'  => 'GET',
		'callback' => 'custom_api_get_chieu_van_chuyen_posts',
	));
});

//chieu van chuyen header
function custom_api_get_chieu_van_chuyen_posts_header() {
	// Kiểm tra nếu function get_field() tồn tại (để tránh lỗi khi ACF chưa kích hoạt)
	if (!function_exists('get_field')) {
		return new WP_Error('acf_missing', 'Advanced Custom Fields plugin is required.', array('status' => 500));
	}

	$args = array(
		'post_type'      => 'chieu-van-chuyen',
		'posts_per_page' => -1, // Lấy tất cả các bài post
	);

	$posts = get_posts($args);
	$formatted_posts = array();

	foreach ($posts as $post) {
		setup_postdata($post); // Đảm bảo dữ liệu post được đồng bộ đúng cách

		$thumbnail = get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: ''; // Kiểm tra nếu không có ảnh
		$title = get_the_title($post->ID);
		$type = get_field('type_order', $post->ID);
		$slug = get_post_field('post_name', $post->ID);
		$formatted_posts[] = array(
			'id' => $post->ID,
			'thumbnail' => $thumbnail,
			'title'     => $title,
			'type'		=> $type,
			'slug'      => $slug,
		);
	}

	wp_reset_postdata(); // Đặt lại dữ liệu bài viết để tráadd_actionnh lỗi đồng bộ

	return $formatted_posts;
}

add_action('rest_api_init', function () {
	register_rest_route('api/v1', '/chieu-van-chuyen-header', array(
		'methods'  => 'GET',
		'callback' => 'custom_api_get_chieu_van_chuyen_posts_header',
	));
});


add_action('rest_api_init', function () {
	register_rest_route('api/v1', '/all-slug-transport', [
		'methods' => 'GET',
		'callback' => 'get_all_slugs_transport',
		'permission_callback' => '__return_true',
	]);

	register_rest_route('api/v1', '/all-slug-post', [
		'methods' => 'GET',
		'callback' => 'get_all_post',
		'permission_callback' => '__return_true',
	]);
});

// Hàm get all slug transport
function get_all_slugs_transport()
{
	$args = [
		'post_type' => 'chieu-van-chuyen',
		'posts_per_page' => -1,
		'post_status' => 'publish',
	];
	$query = new WP_Query($args);
	$posts = [];
	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();
			$posts[] = [
				'slug' => get_post_field('post_name', get_the_id()),
			];
		}
		wp_reset_postdata();
	}
	return rest_ensure_response($posts);
}

// Hàm get all slug post
function get_all_post()
{
	$args = [
		'post_type' => 'post',
		'posts_per_page' => -1,
		'post_status' => 'publish',
	];
	$query = new WP_Query($args);
	$posts = [];
	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();
			$thumbnail_id = get_post_thumbnail_id();
			$posts[] = [
				'slug' => get_post_field('post_name', get_the_id()),
			];
		}
		wp_reset_postdata();
	}
	return rest_ensure_response($posts);
}

// API: /wp-json/api/v1/chieu-van-chuyen
add_action('rest_api_init', function () {
    register_rest_route('api/v1', '/chieu-van-chuyen', array(
        'methods' => 'GET',
        'callback' => 'get_delivery_direction',
        'permission_callback' => '__return_true', // Cho phép public
    ));
});

function get_delivery_direction(WP_REST_Request $request) {
    // Lấy tất cả bài viết của post type 'chieu-van-chuyen'
    $args = array(
        'post_type'      => 'chieu-van-chuyen',
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Lấy tất cả
    );

    $query = new WP_Query($args);

    $data = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $post_id = get_the_ID();

            $acf_data = array();
            if (function_exists('get_field')) {
                $acf_data = array(
                    'delivery_direction' => get_field('delivery_direction', $post_id),
                    'weight_unit'        => get_field('weight_unit', $post_id),
                    'currency'           => get_field('currency', $post_id),
                    'delivery_price_list'=> get_field('delivery_price_list', $post_id),
                    'domestic_delivery'  => get_field('domestic_delivery', $post_id),
                    'note'  => get_field('note', $post_id),
                    'delivery_cost_calculation_type'  => get_field('delivery_cost_calculation_type', $post_id),
                    'depends_on_product_type' => get_field('depends_on_product_type', $post_id),
                    'depends_on_city' => get_field('depends_on_city', $post_id),
                );
            }

            $data[] = array(
                'id'    => $post_id,
                'title' => get_the_title(),
                'slug'  => get_post_field('post_name', $post_id),
                'featured_image' => get_the_post_thumbnail_url($post_id, 'full'),
                'acf'   => $acf_data
            );
        }
        wp_reset_postdata();
    }

    return array(
        'data' => $data
    );
}
