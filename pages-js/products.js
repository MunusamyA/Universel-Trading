$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let searchTimer = null;
    loadCategories();
    loadProducts();

    $('#refreshProductsBtn').on('click', loadProducts);
    $('#productStatusFilter, #categoryFilter').on('change', loadProducts);
    $('#productSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadProducts, 400);
    });

    $(document).on('click', '.delete-product-btn', function () {
        let productId = $(this).data('id');
        if (!confirm('Are you sure you want to delete this product?')) return;

        $.ajax({
            url: window.BASE_URL + 'api/products.php',
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
                } else handleError(response);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
            }
        });
    });

    function loadProducts() {
        $('#productTableBody').html('<tr><td colspan="12" class="text-center text-muted">Loading...</td></tr>');
        $.ajax({
            url: window.BASE_URL + 'api/products.php',
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
                    $('#productTableBody').html(`<tr><td colspan="12" class="text-center text-danger">${escapeHtml(response.message || 'Unable to load products.')}</td></tr>`);
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
                ? `<img src="${window.BASE_URL}${escapeHtml(product.product_image)}" class="rounded border" style="width:45px;height:45px;object-fit:cover;">`
                : `<div class="rounded border bg-light d-flex align-items-center justify-content-center" style="width:45px;height:45px;"><i class="mdi mdi-image text-muted"></i></div>`;

            let retailProfit = profit(product.cost_price, product.retail_price);
            let wholesaleProfit = profit(product.cost_price, product.wholesale_price);
            let save = youSave(product.final_mrp, product.retail_price);

            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${img}</td>
                    <td>
                        <h6 class="mb-0">${escapeHtml(product.product_name || '')}</h6>
                        <small class="text-muted">${escapeHtml(product.product_code || '')}</small>
                    </td>
                    <td>
                        <div>${escapeHtml(product.category_name || '-')}</div>
                        <small class="text-muted">${escapeHtml(product.sub_category_name || '')}</small>
                    </td>
                    <td>
                        <div>${escapeHtml(product.hsn_code || '-')}</div>
                        <small class="text-muted">GST: ${gstPercent(product)}%</small>
                    </td>
                    <td>
                        <div>MRP: ₹${num(product.enter_mrp)}</div>
                        <small class="text-muted">Final: ₹${num(product.final_mrp)}</small>
                    </td>
                    <td>₹${num(product.cost_price)}</td>
                    <td>
                        <div>₹${num(product.retail_price)}</div>
                        <small class="text-muted">Profit: ${num(retailProfit.percentage)}%</small>
                    </td>
                    <td>
                        <div>₹${num(product.wholesale_price)}</div>
                        <small class="text-muted">Profit: ${num(wholesaleProfit.percentage)}%</small>
                    </td>
                    <td>
                        <div>₹${num(save.amount)}</div>
                        <small class="text-muted">${num(save.percentage)}%</small>
                    </td>
                    <td>${statusBadge(product.status)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="${window.BASE_URL}pages/product-form.php?id=${product.id}" class="btn btn-outline-primary"><i class="mdi mdi-pencil"></i></a>
                            <button type="button" class="btn btn-outline-danger delete-product-btn" data-id="${product.id}"><i class="mdi mdi-delete"></i></button>
                        </div>
                    </td>
                </tr>
            `;
        });
        $('#productTableBody').html(html);
    }

    function loadCategories() {
        $.ajax({
            url: window.BASE_URL + 'api/products.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_categories' },
            success: function (response) {
                if (response.status === true) {
                    let html = '<option value="0">All Categories</option>';
                    $.each(response.data.categories || [], function (_, c) {
                        html += `<option value="${c.id}">${escapeHtml(c.category_name)}</option>`;
                    });
                    $('#categoryFilter').html(html);
                }
            }
        });
    }

    function renderStats(s) {
        $('#totalProductsCount').text(s.total_products || 0);
        $('#activeProductsCount').text(s.active_products || 0);
        $('#inactiveProductsCount').text(s.inactive_products || 0);
        $('#minimumStockTotal').text(num(s.minimum_stock_total || 0));
    }

    function gstPercent(p) {
        let igst = parseFloat(p.igst_percentage || 0);
        let cgst = parseFloat(p.cgst_percentage || 0);
        let sgst = parseFloat(p.sgst_percentage || 0);
        return num(igst > 0 ? igst : cgst + sgst);
    }

    function profit(cost, price) {
        cost = parseFloat(cost || 0);
        price = parseFloat(price || 0);
        let amount = price - cost;
        let percentage = cost > 0 ? (amount / cost) * 100 : 0;
        return { amount, percentage };
    }

    function youSave(mrp, retail) {
        mrp = parseFloat(mrp || 0);
        retail = parseFloat(retail || 0);
        let amount = mrp - retail;
        if (amount < 0) amount = 0;
        let percentage = mrp > 0 ? (amount / mrp) * 100 : 0;
        return { amount, percentage };
    }

    function num(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function statusBadge(status) {
        return parseInt(status) === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
    }

    function showToastSafe(type, message) {
        if (typeof showToast === 'function') showToast(type, message, 5000);
        else alert(message);
    }

    function handleError(response) {
        if (response && response.redirect) { window.location.href = response.redirect; return; }
        showToastSafe('error', response && response.message ? response.message : 'Something went wrong.');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }
});
