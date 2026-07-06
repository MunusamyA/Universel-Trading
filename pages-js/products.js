$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let searchTimer = null;

    let pageContext = {
        can_view: false,
        can_list: false,
        can_add: false,
        can_edit: false,
        can_delete: false,
        page_title: 'Product Master',
        page_note: '',
        add_button_label: 'Add Product',
        form_url: ''
    };

    loadPageContext();

    $('#refreshProductsBtn').on('click', loadProducts);
    $('#productStatusFilter, #categoryFilter').on('change', loadProducts);

    $('#productSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadProducts, 400);
    });

    $(document).on('click', '.delete-product-btn', function () {
        if (!pageContext.can_delete) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        let productId = $(this).data('id');

        if (!confirm('Are you sure you want to delete this product?')) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_product',
                product_id: productId,
                csrf_token: $('input[name="csrf_token"]').first().val()
            },
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Product deleted.');
                    loadProducts();
                } else {
                    handleError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
            }
        });
    });

    function loadPageContext() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_page_context'
            },
            success: function (response) {
                if (response.status === true) {
                    pageContext = response.data.context || pageContext;
                    applyPageContext();
                    loadCategories();
                    loadProducts();
                } else {
                    $('#productTableBody').html('<tr><td colspan="12" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#addProductBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#productTableBody').html('<tr><td colspan="12" class="text-center text-danger">Server error.</td></tr>');
                $('#addProductBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Product Master');
        $('#pageNoteText').text(pageContext.page_note || '');
        $('#addProductBtnText').text(pageContext.add_button_label || 'Add Product');

        if (pageContext.form_url) {
            $('#addProductBtn').attr('href', pageContext.form_url);
        }

        if (pageContext.can_add) {
            $('#addProductBtn').removeClass('d-none');
        } else {
            $('#addProductBtn').addClass('d-none');
        }
    }

    function loadProducts() {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#productTableBody').html('<tr><td colspan="12" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $('#productTableBody').html('<tr><td colspan="12" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_products',
                search: $('#productSearch').val(),
                status: $('#productStatusFilter').val(),
                category_id: $('#categoryFilter').val()
            },
            success: function (response) {
                if (response.status === true) {
                    renderProductRows(response.data.products || []);
                    renderStats(response.data.stats || {});
                } else {
                    $('#productTableBody').html('<tr><td colspan="12" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load products.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#productTableBody').html('<tr><td colspan="12" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderProductRows(products) {
        if (!products || products.length === 0) {
            $('#productTableBody').html('<tr><td colspan="12" class="text-center text-muted">No products found.</td></tr>');
            return;
        }

        let html = '';

        $.each(products, function (index, product) {
            let img = product.product_image
                ? '<img src="' + window.BASE_URL + escapeHtml(product.product_image) + '" class="rounded border" style="width:45px;height:45px;object-fit:cover;">'
                : '<div class="rounded border bg-light d-flex align-items-center justify-content-center" style="width:45px;height:45px;"><i class="mdi mdi-image text-muted"></i></div>';

            let retailProfit = profit(product.cost_price, product.retail_price);
            let wholesaleProfit = profit(product.cost_price, product.wholesale_price);
            let save = youSave(product.final_mrp, product.retail_price);

            let actionHtml = '';

            if (product.can_edit) {
                actionHtml += '<a href="' + window.BASE_URL + 'pages/product-form.php?id=' + product.id + '" class="btn btn-outline-primary btn-sm" title="Edit"><i class="mdi mdi-pencil"></i></a>';
            }

            if (product.can_delete) {
                actionHtml += '<button type="button" class="btn btn-outline-danger btn-sm delete-product-btn ms-1" data-id="' + product.id + '" title="Delete"><i class="mdi mdi-delete"></i></button>';
            }

            if (actionHtml === '') {
                actionHtml = '<span class="text-muted">No access</span>';
            }

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td>' + img + '</td>';
            html += '<td><h6 class="mb-0">' + escapeHtml(product.product_name || '') + '</h6><small class="text-muted">' + escapeHtml(product.product_code || '') + '</small></td>';
            html += '<td><div>' + escapeHtml(product.category_name || '-') + '</div><small class="text-muted">' + escapeHtml(product.sub_category_name || '') + '</small></td>';
            html += '<td><div>' + escapeHtml(product.hsn_code || '-') + '</div><small class="text-muted">GST: ' + gstPercent(product) + '%</small></td>';
            html += '<td><div>MRP: ₹' + num(product.enter_mrp) + '</div><small class="text-muted">Final: ₹' + num(product.final_mrp) + '</small></td>';
            html += '<td>₹' + num(product.cost_price) + '</td>';
            html += '<td><div>₹' + num(product.retail_price) + '</div><small class="text-muted">Profit: ' + num(retailProfit.percentage) + '%</small></td>';
            html += '<td><div>₹' + num(product.wholesale_price) + '</div><small class="text-muted">Profit: ' + num(wholesaleProfit.percentage) + '%</small></td>';
            html += '<td><div>₹' + num(save.amount) + '</div><small class="text-muted">' + num(save.percentage) + '%</small></td>';
            html += '<td>' + statusBadge(product.status) + '</td>';
            html += '<td><div class="btn-group btn-group-sm">' + actionHtml + '</div></td>';
            html += '</tr>';
        });

        $('#productTableBody').html(html);
    }

    function loadCategories() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_categories'
            },
            success: function (response) {
                if (response.status === true) {
                    let html = '<option value="0">All Categories</option>';

                    $.each(response.data.categories || [], function (_, category) {
                        html += '<option value="' + category.id + '">' + escapeHtml(category.category_name) + '</option>';
                    });

                    $('#categoryFilter').html(html);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function renderStats(stats) {
        $('#totalProductsCount').text(stats.total_products || 0);
        $('#activeProductsCount').text(stats.active_products || 0);
        $('#inactiveProductsCount').text(stats.inactive_products || 0);
        $('#minimumStockTotal').text(num(stats.minimum_stock_total || 0));
    }

    function gstPercent(product) {
        let igst = parseFloat(product.igst_percentage || 0);
        let cgst = parseFloat(product.cgst_percentage || 0);
        let sgst = parseFloat(product.sgst_percentage || 0);

        return num(igst > 0 ? igst : cgst + sgst);
    }

    function profit(cost, price) {
        cost = parseFloat(cost || 0);
        price = parseFloat(price || 0);

        let amount = price - cost;
        let percentage = cost > 0 ? (amount / cost) * 100 : 0;

        return {
            amount: amount,
            percentage: percentage
        };
    }

    function youSave(mrp, retail) {
        mrp = parseFloat(mrp || 0);
        retail = parseFloat(retail || 0);

        let amount = mrp - retail;

        if (amount < 0) {
            amount = 0;
        }

        let percentage = mrp > 0 ? (amount / mrp) * 100 : 0;

        return {
            amount: amount,
            percentage: percentage
        };
    }

    function num(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function statusBadge(status) {
        return parseInt(status) === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Inactive</span>';
    }

    function showToastSafe(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        alert(message);
    }

    function handleError(response) {
        if (response && response.redirect) {
            window.location.href = response.redirect;
            return;
        }

        showToastSafe('error', response && response.message ? response.message : 'Something went wrong.');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

});
