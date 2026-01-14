/**
 * Modal JavaScript for Delivery Order System
 */
(function ($) {
    "use strict";

    var currentPage = 1;
    var currentChieuVanChuyenId = 0;
    var searchTimeout = null;

    $(document).ready(function () {
        var modal = $("#delivery_order_system_modal");
        var openBtn = $("#them_don_btn");
        var closeBtns = $(".delivery-order-system-modal-close");
        var overlay = $(".delivery-order-system-modal-overlay");
        var chieuVanChuyenSelect = $("#chieu_van_chuyen_id");
        var searchInput = $("#delivery_order_system_search");
        var tableBody = $("#delivery_order_system_table_body");
        var selectAllCheckbox = $("#cb-select-all");

        // Enable/disable button based on select value
        function toggleButton() {
            var selectedValue = chieuVanChuyenSelect.val();
            if (selectedValue && selectedValue !== "") {
                openBtn.prop("disabled", false);
                currentChieuVanChuyenId = parseInt(selectedValue);
            } else {
                openBtn.prop("disabled", true);
                currentChieuVanChuyenId = 0;
            }
        }

        // Initial state
        toggleButton();

        // Watch for select changes
        chieuVanChuyenSelect.on("change", function () {
            toggleButton();
            if (modal.is(":visible")) {
                loadData();
            }
        });

        // Open modal
        openBtn.on("click", function () {
            if (!$(this).prop("disabled")) {
                currentPage = 1;

                // Save chieu_van_chuyen_id to ACF/meta before opening modal
                $.ajax({
                    url: deliveryOrderSystemModal.ajaxUrl,
                    type: "POST",
                    data: {
                        action: "delivery_order_system_save_chieu_van_chuyen",
                        nonce: deliveryOrderSystemModal.saveChieuNonce,
                        post_id: deliveryOrderSystemModal.postId,
                        chieu_van_chuyen_id: currentChieuVanChuyenId,
                    },
                    success: function (response) {
                        if (!response.success) {
                            alert(
                                response.data && response.data.message
                                    ? response.data.message
                                    : deliveryOrderSystemModal.strings
                                          .saveChieuError
                            );
                            return;
                        }
                        loadData();
                        modal.fadeIn(200);
                    },
                    error: function () {
                        alert(deliveryOrderSystemModal.strings.saveChieuError);
                    },
                });
            }
        });

        // Close modal
        function closeModal() {
            modal.fadeOut(200);
            searchInput.val("");
            currentPage = 1;
        }

        closeBtns.on("click", closeModal);
        overlay.on("click", closeModal);

        // Close on ESC key
        $(document).on("keydown", function (e) {
            if (e.keyCode === 27 && modal.is(":visible")) {
                closeModal();
            }
        });

        // Search functionality
        searchInput.on("input", function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                currentPage = 1;
                loadData();
            }, 500);
        });

        // Select all checkbox
        selectAllCheckbox.on("change", function () {
            var isChecked = $(this).prop("checked");
            tableBody.find('input[type="checkbox"]').prop("checked", isChecked);
        });

        // Render skeleton loading
        function renderSkeleton() {
            var html = "";
            for (var i = 0; i < 10; i++) {
                html += '<tr class="delivery-order-system-skeleton-row">';
                html +=
                    '<td class="check-column"><div class="delivery-order-system-skeleton skeleton-checkbox"></div></td>';
                // html += '<td><div class="delivery-order-system-skeleton skeleton-id"></div></td>';
                html +=
                    '<td><div class="delivery-order-system-skeleton skeleton-text"></div></td>';
                html +=
                    '<td><div class="delivery-order-system-skeleton skeleton-text-long"></div></td>';
                html +=
                    '<td><div class="delivery-order-system-skeleton skeleton-text"></div></td>';
                html +=
                    '<td><div class="delivery-order-system-skeleton skeleton-text"></div></td>';
                html +=
                    '<td><div class="delivery-order-system-skeleton skeleton-text-short"></div></td>';
                html +=
                    '<td><div class="delivery-order-system-skeleton skeleton-text"></div></td>';
                html +=
                    '<td><div class="delivery-order-system-skeleton skeleton-text"></div></td>';
                html +=
                    '<td><div class="delivery-order-system-skeleton skeleton-text"></div></td>';
                html +=
                    '<td><div class="delivery-order-system-skeleton skeleton-text-long"></div></td>';
                html +=
                    '<td><div class="delivery-order-system-skeleton skeleton-text-short"></div></td>';
                //html += '<td><div class="delivery-order-system-skeleton skeleton-text-short"></div></td>';
                html += "</tr>";
            }
            return html;
        }

        // Load data via AJAX
        function loadData() {
            if (!currentChieuVanChuyenId) {
                tableBody.html(
                    '<tr><td colspan="13" style="text-align: center; padding: 20px;">' +
                        deliveryOrderSystemModal.strings
                            .pleaseSelectChieuVanChuyen +
                        "</td></tr>"
                );
                return;
            }

            // Show skeleton loading
            tableBody.html(renderSkeleton());

            $.ajax({
                url: deliveryOrderSystemModal.ajaxUrl,
                type: "POST",
                data: {
                    action: "delivery_order_system_get_ma_van_don",
                    nonce: deliveryOrderSystemModal.nonce,
                    chieu_van_chuyen_id: currentChieuVanChuyenId,
                    search: searchInput.val(),
                    page: currentPage,
                },
                success: function (response) {
                    if (response.success) {
                        renderTable(response.data);
                        renderPagination(response.data);
                    } else {
                        tableBody.html(
                            '<tr><td colspan="13" style="text-align: center; padding: 20px; color: #d63638;">' +
                                response.data.message +
                                "</td></tr>"
                        );
                    }
                },
                error: function () {
                    tableBody.html(
                        '<tr><td colspan="13" style="text-align: center; padding: 20px; color: #d63638;">' +
                            deliveryOrderSystemModal.strings.errorLoading +
                            "</td></tr>"
                    );
                },
            });
        }

        // Render table
        function renderTable(data) {
            if (!data.posts || data.posts.length === 0) {
                tableBody.html(
                    '<tr><td colspan="13" style="text-align: center; padding: 20px;">' +
                        deliveryOrderSystemModal.strings.noResults +
                        "</td></tr>"
                );
                return;
            }

            var html = "";
            $.each(data.posts, function (index, post) {
                html += "<tr>";
                html += '<th scope="row" class="check-column">';
                html +=
                    '<input type="checkbox" name="ma_van_don_ids[]" value="' +
                    post.ID +
                    '">';
                html += "</th>";
                //html += "<td>" + (post.ID || "") + "</td>";
                html += "<td>" + (post.ma_khach_hang || "") + "</td>";
                html += "<td>" + (post.name_facebook || "") + "</td>";
                html += "<td>" + (post.ten_nguoi_gui || "") + "</td>";
                html += "<td>" + (post.email || "") + "</td>";
                html += "<td>" + (post.phone || "") + "</td>";
                html += "<td>" + (post.ten_nguoi_nhan || "") + "</td>";
                html += "<td>" + (post.nation || "") + "</td>";
                html += "<td>" + (post.tinh_thanh_nguoi_nhan || "") + "</td>";
                html += "<td>" + (post.dia_chi_nguoi_nhan || "") + "</td>";
                html += "<td>" + (post.loai_tien_te || "") + "</td>";
                if (post.in_delivery_manager_title) {
                    html +=
                        "<td><a href='" +
                        post.in_delivery_manager_url +
                        "' target='_blank'>" +
                        post.in_delivery_manager_title +
                        "</a></td>";
                } else {
                    html += "<td></td>";
                }
                html += "</tr>";
            });
            tableBody.html(html);
            selectAllCheckbox.prop("checked", false);
        }

        // Render pagination
        function renderPagination(data) {
            var total = data.total || 0;
            var totalPages = data.total_pages || 0;
            var current = data.current_page || 1;

            var displayingNum = $(".displaying-num");
            var paginationLinks = $(".pagination-links");

            if (total === 0) {
                displayingNum.text("");
                paginationLinks.html("");
                return;
            }

            var start = (current - 1) * data.per_page + 1;
            var end = Math.min(current * data.per_page, total);
            displayingNum.text(
                "Hiển thị " + start + "–" + end + " trong tổng số " + total
            );

            var linksHtml = "";
            if (current > 1) {
                linksHtml +=
                    '<a class="button" href="#" data-page="' +
                    (current - 1) +
                    '">&laquo;</a> ';
            } else {
                linksHtml += '<span class="button disabled">&laquo;</span> ';
            }

            linksHtml += '<span class="paging-input">';
            linksHtml += '<span class="tablenav-paging-text">';
            linksHtml +=
                '<span class="paging-input-text">Trang <input type="number" class="current-page" value="' +
                current +
                '" min="1" max="' +
                totalPages +
                '"> / <span class="total-pages">' +
                totalPages +
                "</span></span>";
            linksHtml += "</span>";
            linksHtml += "</span> ";

            if (current < totalPages) {
                linksHtml +=
                    '<a class="button" href="#" data-page="' +
                    (current + 1) +
                    '">&raquo;</a>';
            } else {
                linksHtml += '<span class="button disabled">&raquo;</span>';
            }

            paginationLinks.html(linksHtml);

            // Pagination click handlers
            paginationLinks.find("a[data-page]").on("click", function (e) {
                e.preventDefault();
                currentPage = parseInt($(this).data("page"));
                loadData();
            });

            paginationLinks.find(".current-page").on("change", function () {
                var page = parseInt($(this).val());
                if (page >= 1 && page <= totalPages) {
                    currentPage = page;
                    loadData();
                } else {
                    $(this).val(currentPage);
                }
            });
        }

        // Save button handler
        $("#delivery_order_system_modal_save").on("click", function () {
            var selectedIds = [];
            tableBody.find('input[type="checkbox"]:checked').each(function () {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                alert(deliveryOrderSystemModal.strings.pleaseSelectAtLeastOne);
                return;
            }

            if (!deliveryOrderSystemModal.postId) {
                alert(deliveryOrderSystemModal.strings.saveError);
                return;
            }

            $.ajax({
                url: deliveryOrderSystemModal.ajaxUrl,
                type: "POST",
                data: {
                    action: "delivery_order_system_save_ma_van_don",
                    nonce: deliveryOrderSystemModal.saveNonce,
                    post_id: deliveryOrderSystemModal.postId,
                    selected_ids: selectedIds,
                },
                success: function (response) {
                    if (response.success) {
                        alert(deliveryOrderSystemModal.strings.saveSuccess);
                        if (deliveryOrderSystemModal.editUrl) {
                            window.location.href =
                                deliveryOrderSystemModal.editUrl;
                        } else {
                            // Reload if edit URL is not available (e.g., new post)
                            window.location.reload();
                        }
                    } else {
                        alert(
                            response.data && response.data.message
                                ? response.data.message
                                : deliveryOrderSystemModal.strings.saveError
                        );
                    }
                },
                error: function () {
                    alert(deliveryOrderSystemModal.strings.saveError);
                },
            });
        });

        // Download PDF for individual order
        $(document).on("click", ".delivery-manager-download-pdf", function (e) {
            e.preventDefault();
            var $btn = $(this);
            var maVanDonId = $btn.data("ma-van-don");

            console.log('PDF button clicked, maVanDonId:', maVanDonId, 'postId:', deliveryOrderSystemModal.postId);

            if (!maVanDonId || !deliveryOrderSystemModal.postId) {
                alert("Thiếu thông tin đơn hàng");
                return;
            }

            $btn.prop('disabled', true);
            $btn.find('.dashicons').addClass('dashicons-update spin');

            $.ajax({
                url: deliveryOrderSystemModal.ajaxUrl,
                type: "POST",
                data: {
                    action: 'delivery_order_system_generate_single_pdf',
                    nonce: deliveryOrderSystemModal.nonce,
                    post_id: deliveryOrderSystemModal.postId,
                    ma_van_don_id: maVanDonId
                },
                success: function (response) {
                    console.log('PDF AJAX response:', response);
                    if (response.success && response.data && response.data.pdf_url) {
                        // Open PDF in new tab
                        window.open(response.data.pdf_url, '_blank');
                    } else {
                        alert(response.data && response.data.message ? response.data.message : 'Không thể tải PDF');
                    }
                },
                error: function (xhr, status, error) {
                    console.log('PDF AJAX error:', xhr, status, error);
                    alert('Có lỗi xảy ra khi tải PDF');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $btn.find('.dashicons').removeClass('dashicons-update spin');
                }
            });
        });

        // Send mail for individual order
        $(document).on("click", ".delivery-manager-send-mail", function (e) {
            e.preventDefault();
            var $btn = $(this);
            var maVanDonId = $btn.data("ma-van-don");

            if (!maVanDonId || !deliveryOrderSystemModal.postId) {
                alert("Thiếu thông tin đơn hàng");
                return;
            }

            if (!confirm("Bạn có chắc muốn gửi mail cho đơn hàng này?")) {
                return;
            }

            $btn.prop('disabled', true);
            $btn.find('.dashicons').addClass('dashicons-update spin');

            $.ajax({
                url: deliveryOrderSystemModal.ajaxUrl,
                type: "POST",
                data: {
                    action: "delivery_order_system_send_single_mail",
                    nonce: deliveryOrderSystemModal.nonce,
                    post_id: deliveryOrderSystemModal.postId,
                    ma_van_don_id: maVanDonId,
                },
                success: function (response) {
                    if (response.success) {
                        alert("Email đã được gửi thành công!");
                    } else {
                        alert(
                            response.data && response.data.message
                                ? response.data.message
                                : "Không thể gửi email"
                        );
                    }
                },
                error: function () {
                    alert("Có lỗi xảy ra khi gửi email");
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $btn.find('.dashicons').removeClass('dashicons-update spin');
                }
            });
        });

        // Delete row in metabox (persist via AJAX)
        $(document).on("click", ".delivery-manager-remove-row", function (e) {
            e.preventDefault();
            var $row = $(this).closest("tr");
            var maVanDon = $row
                .find('input[type="hidden"][name*="[ma_van_don]"]')
                .val();

            if (!maVanDon) {
                $row.remove();
                return;
            }

            if (!confirm("Bạn có chắc muốn xoá dòng này?")) {
                return;
            }

            $.ajax({
                url: deliveryOrderSystemModal.ajaxUrl,
                type: "POST",
                data: {
                    action: "delivery_order_system_remove_row",
                    nonce: deliveryOrderSystemModal.removeNonce,
                    post_id: deliveryOrderSystemModal.postId,
                    ma_van_don_id: maVanDon,
                },
                success: function (response) {
                    if (response.success) {
                        $row.remove();
                        alert(deliveryOrderSystemModal.strings.removeSuccess);
                    } else {
                        alert(
                            response.data && response.data.message
                                ? response.data.message
                                : deliveryOrderSystemModal.strings.removeError
                        );
                    }
                },
                error: function () {
                    alert(deliveryOrderSystemModal.strings.removeError);
                },
            });
        });
    });
})(jQuery);
