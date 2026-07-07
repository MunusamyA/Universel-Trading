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

    $(document).on('click', '.view-product-btn', function () {
        openProductView($(this).data('id'));
    });

    $(document).on('click', '.stock-product-btn', function () {
        openProductStockDetails($(this).data('id'));
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
                    $('#productTableBody').html('<tr><td colspan="14" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#addProductBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#productTableBody').html('<tr><td colspan="14" class="text-center text-danger">Server error.</td></tr>');
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
            $('#productTableBody').html('<tr><td colspan="14" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $('#productTableBody').html('<tr><td colspan="14" class="text-center text-muted">Loading...</td></tr>');

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
                    $('#productTableBody').html('<tr><td colspan="14" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load products.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#productTableBody').html('<tr><td colspan="14" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderProductRows(products) {
        if (!products || products.length === 0) {
            $('#productTableBody').html('<tr><td colspan="14" class="text-center text-muted">No products found.</td></tr>');
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

            if (pageContext.can_view || pageContext.can_list) {
                actionHtml += '<button type="button" class="btn btn-outline-info btn-sm view-product-btn" data-id="' + product.id + '" title="View"><i class="mdi mdi-eye"></i></button>';
                actionHtml += '<button type="button" class="btn btn-outline-warning btn-sm stock-product-btn ms-1" data-id="' + product.id + '" title="Stock Details"><i class="mdi mdi-package-variant-closed"></i></button>';
            }

            if (product.can_edit) {
                actionHtml += '<a href="' + window.BASE_URL + 'pages/product-form.php?id=' + product.id + '" class="btn btn-outline-primary btn-sm ms-1" title="Edit"><i class="mdi mdi-pencil"></i></a>';
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
            html += '<td>' + stockBadge(product.available_stock, product.minimum_stock) + '</td>';
            html += '<td><span class="badge bg-light text-dark border">' + escapeHtml(product.base_unit || '-') + '</span></td>';
            html += '<td><div>₹' + num(product.retail_price) + '</div><small class="text-muted">Profit: ' + num(retailProfit.percentage) + '%</small></td>';
            html += '<td><div>₹' + num(product.wholesale_price) + '</div><small class="text-muted">Profit: ' + num(wholesaleProfit.percentage) + '%</small></td>';
            html += '<td><div>₹' + num(save.amount) + '</div><small class="text-muted">' + num(save.percentage) + '%</small></td>';
            html += '<td>' + statusBadge(product.status) + '</td>';
            html += '<td><div class="btn-group btn-group-sm">' + actionHtml + '</div></td>';
            html += '</tr>';
        });

        $('#productTableBody').html(html);
    }

    function openProductView(productId) {
        productId = parseInt(productId || 0);
        if (productId <= 0) {
            showToastSafe('error', 'Invalid product.');
            return;
        }

        $('#productViewBody').html('<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-1"></span>Loading...</div>');
        showBootstrapModal('productViewModal');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_product',
                product_id: productId
            },
            success: function (response) {
                if (response.status === true) {
                    renderProductView(response.data.product || {});
                } else {
                    $('#productViewBody').html('<div class="alert alert-danger mb-0">' + escapeHtml(response.message || 'Unable to load product.') + '</div>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#productViewBody').html('<div class="alert alert-danger mb-0">Server error.</div>');
            }
        });
    }

    function renderProductView(product) {
        let imageHtml = product.product_image
            ? '<img src="' + window.BASE_URL + escapeHtml(product.product_image) + '" class="rounded border" style="width:110px;height:110px;object-fit:cover;">'
            : '<div class="rounded border bg-light d-flex align-items-center justify-content-center" style="width:110px;height:110px;"><i class="mdi mdi-image-outline font-size-24 text-muted"></i></div>';

        let gst = gstPercent(product);
        let html = '';
        html += '<div class="row g-3">';
        html += '<div class="col-md-3 text-center">' + imageHtml + '</div>';
        html += '<div class="col-md-9">';
        html += '<h5 class="mb-1">' + escapeHtml(product.product_name || '-') + '</h5>';
        html += '<div class="text-muted mb-2">Code: ' + escapeHtml(product.product_code || '-') + '</div>';
        html += '<div class="row g-2">';
        html += viewInfoBox('Category', escapeHtml(product.category_name || '-') + '<br><small>' + escapeHtml(product.sub_category_name || '') + '</small>');
        html += viewInfoBox('HSN / GST', escapeHtml(product.hsn_code || '-') + '<br><small>GST: ' + gst + '%</small>');
        html += viewInfoBox('Status', statusBadge(product.status));
        html += '</div></div></div>';

        html += '<hr>';
        html += '<div class="row g-3">';
        html += viewInfoBox('MRP', '₹' + num(product.enter_mrp));
        html += viewInfoBox('Final MRP', '₹' + num(product.final_mrp));
        html += viewInfoBox('Purchase / Stock Price', '₹' + num(product.cost_price));
        html += viewInfoBox('Retail Price', '₹' + num(product.retail_price));
        html += viewInfoBox('Wholesale Price', '₹' + num(product.wholesale_price));
        html += viewInfoBox('Minimum Stock', num(product.minimum_stock));
        html += viewInfoBox('Base Unit', escapeHtml(product.base_unit || 'Piece'));
        html += viewInfoBox('Secondary Conversion', num(product.secondary_unit_value || 1));
        html += '</div>';

        $('#productViewBody').html(html);
    }

    function openProductStockDetails(productId) {
        productId = parseInt(productId || 0);
        if (productId <= 0) {
            showToastSafe('error', 'Invalid product.');
            return;
        }

        $('#productStockBody').html('<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-1"></span>Loading...</div>');
        showBootstrapModal('productStockModal');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_product_stock_details',
                product_id: productId
            },
            success: function (response) {
                if (response.status === true) {
                    renderProductStockDetails(response.data || {});
                } else {
                    $('#productStockBody').html('<div class="alert alert-danger mb-0">' + escapeHtml(response.message || 'Unable to load stock details.') + '</div>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#productStockBody').html('<div class="alert alert-danger mb-0">Server error.</div>');
            }
        });
    }

    function renderProductStockDetails(data) {
        let product = data.product || {};
        let summary = data.summary || {};
        let batches = data.batches || [];

        let html = '';
        html += '<div class="d-flex align-items-start justify-content-between mb-3">';
        html += '<div><h5 class="mb-1">' + escapeHtml(product.product_name || '-') + '</h5><div class="text-muted">' + escapeHtml(product.product_code || '-') + ' | ' + escapeHtml(product.category_name || '-') + '</div></div>';
        html += '<span class="badge bg-primary font-size-13">Available: ' + num(summary.total_available || 0) + '</span>';
        html += '</div>';

        html += '<div class="row g-3 mb-3">';
        html += viewInfoBox('Total Purchased Stock', num(summary.total_stock || 0));
        html += viewInfoBox('Sold Stock', num(summary.total_sold || 0));
        html += viewInfoBox('Available Stock', num(summary.total_available || 0));
        html += viewInfoBox('Available Stock Value', '₹' + num(summary.stock_value || 0));
        html += '</div>';

        html += '<h6 class="mb-2">Batchwise Available Stock</h6>';

        if (batches.length === 0) {
            html += '<div class="alert alert-warning mb-0">No available stock found for this product.</div>';
            $('#productStockBody').html(html);
            return;
        }

        html += '<div class="table-responsive">';
        html += '<table class="table table-sm table-bordered table-centered mb-0">';
        html += '<thead class="table-light"><tr>';
        html += '<th>#</th><th>Purchase</th><th>Date</th><th>Supplier</th><th>Batch</th><th>Stock</th><th>Sold</th><th>Available</th><th>Rate</th><th>Expiry</th>';
        html += '</tr></thead><tbody>';

        $.each(batches, function (index, row) {
            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(row.bill_no || '-') + '</strong><br><small class="text-muted">#' + parseInt(row.purchase_id || 0) + '</small></td>';
            html += '<td>' + escapeHtml(row.purchase_date || '-') + '</td>';
            html += '<td>' + escapeHtml(row.supplier_name || '-') + '</td>';
            html += '<td>' + escapeHtml(row.batch_no || '-') + '</td>';
            html += '<td>' + num(row.stock_qty) + '</td>';
            html += '<td>' + num(row.sold_qty) + '</td>';
            html += '<td><span class="badge bg-success">' + num(row.available_qty) + '</span></td>';
            html += '<td>₹' + num(row.purchase_price) + '</td>';
            html += '<td>' + escapeHtml(row.expiry_date || '-') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        $('#productStockBody').html(html);
    }

    function viewInfoBox(label, value) {
        return '<div class="col-md-3 col-sm-6"><div class="border rounded p-2 h-100"><small class="text-muted d-block">' + escapeHtml(label) + '</small><div class="fw-semibold">' + value + '</div></div></div>';
    }

    function showBootstrapModal(id) {
        let el = document.getElementById(id);
        if (!el || !window.bootstrap || !bootstrap.Modal) {
            return;
        }
        bootstrap.Modal.getOrCreateInstance(el).show();
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

    function stockBadge(availableStock, minimumStock) {
        let available = parseFloat(availableStock || 0);
        let minimum = parseFloat(minimumStock || 0);
        let badgeClass = 'bg-success';

        if (available <= 0) {
            badgeClass = 'bg-danger';
        } else if (minimum > 0 && available <= minimum) {
            badgeClass = 'bg-warning text-dark';
        }

        return '<span class="badge ' + badgeClass + ' font-size-12">' + num(available) + '</span>';
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
