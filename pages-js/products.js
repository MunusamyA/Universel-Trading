$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let productModal = new bootstrap.Modal(document.getElementById('productModal'));
    let searchTimer = null;

    loadCategories();
    loadHsnCodes();
    loadProducts();

    $('#addProductBtn').on('click', function () {
        resetProductForm();
        $('#productModalTitle').text('Add Product');
        loadCategories();
        loadHsnCodes();
        $('#sub_category_id').html('<option value="">Select Sub Category</option>');
        productModal.show();
    });

    $('#refreshProductsBtn').on('click', function () {
        loadProducts();
    });

    $('#productStatusFilter, #categoryFilter').on('change', function () {
        loadProducts();
    });

    $('#productSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            loadProducts();
        }, 400);
    });

    $('#category_id').on('change', function () {
        loadSubCategories($(this).val());
    });

    $(document).on('input', '.text-uppercase', function () {
        $(this).val($(this).val().toUpperCase());
    });

    $('#productForm').on('submit', function (e) {
        e.preventDefault();

        if ($('#category_id').val() === '') {
            showToastSafe('warning', 'Please select category.');
            $('#category_id').focus();
            return;
        }

        if ($('#sub_category_id').val() === '') {
            showToastSafe('warning', 'Please select sub category.');
            $('#sub_category_id').focus();
            return;
        }

        if ($('#hsn_id').val() === '') {
            showToastSafe('warning', 'Please select HSN.');
            $('#hsn_id').focus();
            return;
        }

        if ($.trim($('#product_name').val()) === '') {
            showToastSafe('warning', 'Please enter product name.');
            $('#product_name').focus();
            return;
        }

        let piecesPerBox = parseFloat($('#default_pieces_per_box').val() || 0);
        if (piecesPerBox <= 0) {
            showToastSafe('warning', 'Pieces per box must be greater than zero.');
            $('#default_pieces_per_box').focus();
            return;
        }

        let markup = parseFloat($('#markup_percentage').val() || 0);
        let minimumStock = parseFloat($('#minimum_stock').val() || 0);

        if (markup < 0 || minimumStock < 0) {
            showToastSafe('warning', 'Markup and minimum stock cannot be negative.');
            return;
        }

        $('#saveProductBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/products.php',
            type: 'POST',
            dataType: 'json',
            data: $('#productForm').serialize() + '&action=save_product',
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Product saved.');
                    productModal.hide();
                    loadProducts();
                } else {
                    handleError(response);
                }

                $('#saveProductBtn').prop('disabled', false).html('Save Product');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#saveProductBtn').prop('disabled', false).html('Save Product');
            }
        });
    });

    $(document).on('click', '.edit-product-btn', function () {
        let productId = $(this).data('id');
        loadProductForEdit(productId);
    });

    $(document).on('click', '.delete-product-btn', function () {
        let productId = $(this).data('id');

        if (!confirm('Are you sure you want to delete this product?')) {
            return;
        }

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

    function loadProducts() {
        $('#productTableBody').html('<tr><td colspan="10" class="text-center text-muted">Loading...</td></tr>');

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
                    $('#productTableBody').html(`<tr><td colspan="10" class="text-center text-danger">${escapeHtml(response.message || 'Unable to load products.')}</td></tr>`);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#productTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderProductRows(products) {
        if (!products || products.length === 0) {
            $('#productTableBody').html('<tr><td colspan="10" class="text-center text-muted">No products found.</td></tr>');
            return;
        }

        let html = '';

        $.each(products, function (index, product) {
            html += `
                <tr>
                    <td>${index + 1}</td>
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
                    <td>${escapeHtml(product.base_unit || '-')}</td>
                    <td>${numberFormat(product.default_pieces_per_box)} / ${escapeHtml(product.box_label || 'Box')}</td>
                    <td>${numberFormat(product.markup_percentage)}%</td>
                    <td>${numberFormat(product.minimum_stock)}</td>
                    <td>${statusBadge(product.status)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary edit-product-btn" data-id="${product.id}" title="Edit">
                                <i class="mdi mdi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger delete-product-btn" data-id="${product.id}" title="Delete">
                                <i class="mdi mdi-delete"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#productTableBody').html(html);
    }

    function loadProductForEdit(productId) {
        $.ajax({
            url: window.BASE_URL + 'api/products.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_product',
                product_id: productId
            },
            success: function (response) {
                if (response.status === true) {
                    let product = response.data.product;

                    resetProductForm();
                    $('#productModalTitle').text('Edit Product');

                    $('#product_id').val(product.id);
                    $('#product_code').val(product.product_code);
                    $('#product_name').val(product.product_name);
                    setSelectValueWithFallback('#base_unit', product.base_unit || 'Piece');
                    setSelectValueWithFallback('#box_label', product.box_label || 'Box');
                    $('#default_pieces_per_box').val(parseFloat(product.default_pieces_per_box || 1).toFixed(2));
                    $('#markup_percentage').val(parseFloat(product.markup_percentage || 0).toFixed(2));
                    $('#minimum_stock').val(parseFloat(product.minimum_stock || 0).toFixed(2));
                    $('#status').val(product.status);

                    loadCategories(product.category_id);
                    loadSubCategories(product.category_id, product.sub_category_id);
                    loadHsnCodes(product.hsn_id);

                    productModal.show();
                } else {
                    handleError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
            }
        });
    }

    function loadCategories(selectedCategoryId) {
        $.ajax({
            url: window.BASE_URL + 'api/products.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_categories'
            },
            success: function (response) {
                if (response.status === true) {
                    let categories = response.data.categories || [];
                    let modalHtml = '<option value="">Select Category</option>';
                    let filterHtml = '<option value="0">All Categories</option>';

                    $.each(categories, function (_, category) {
                        let selected = parseInt(category.id) === parseInt(selectedCategoryId || 0) ? 'selected' : '';
                        modalHtml += `<option value="${category.id}" ${selected}>${escapeHtml(category.category_name)}</option>`;
                        filterHtml += `<option value="${category.id}">${escapeHtml(category.category_name)}</option>`;
                    });

                    $('#category_id').html(modalHtml);
                    $('#categoryFilter').html(filterHtml);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function loadSubCategories(categoryId, selectedSubCategoryId) {
        $('#sub_category_id').html('<option value="">Loading...</option>');

        if (!categoryId) {
            $('#sub_category_id').html('<option value="">Select Sub Category</option>');
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/products.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_sub_categories',
                category_id: categoryId
            },
            success: function (response) {
                let html = '<option value="">Select Sub Category</option>';

                if (response.status === true) {
                    $.each(response.data.sub_categories || [], function (_, subCategory) {
                        let selected = parseInt(subCategory.id) === parseInt(selectedSubCategoryId || 0) ? 'selected' : '';
                        html += `<option value="${subCategory.id}" ${selected}>${escapeHtml(subCategory.sub_category_name)}</option>`;
                    });
                }

                $('#sub_category_id').html(html);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#sub_category_id').html('<option value="">Select Sub Category</option>');
            }
        });
    }

    function loadHsnCodes(selectedHsnId) {
        $.ajax({
            url: window.BASE_URL + 'api/products.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_hsn_codes'
            },
            success: function (response) {
                let html = '<option value="">Select HSN</option>';

                if (response.status === true) {
                    $.each(response.data.hsn_codes || [], function (_, hsn) {
                        let selected = parseInt(hsn.id) === parseInt(selectedHsnId || 0) ? 'selected' : '';
                        let label = hsn.hsn_code + ' - GST ' + numberFormat(hsn.igst_percentage) + '%';
                        html += `<option value="${hsn.id}" ${selected}>${escapeHtml(label)}</option>`;
                    });
                }

                $('#hsn_id').html(html);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function resetProductForm() {
        $('#productForm')[0].reset();
        $('#product_id').val('');
        $('#base_unit').val('Piece');
        $('#box_label').val('Box');
        $('#default_pieces_per_box').val('1.00');
        $('#markup_percentage').val('0.00');
        $('#minimum_stock').val('0.00');
        $('#status').val('1');
        $('#saveProductBtn').prop('disabled', false).html('Save Product');
    }

    function renderStats(stats) {
        $('#totalProductsCount').text(stats.total_products || 0);
        $('#activeProductsCount').text(stats.active_products || 0);
        $('#inactiveProductsCount').text(stats.inactive_products || 0);
        $('#minimumStockTotal').text(numberFormat(stats.minimum_stock_total || 0));
    }

    function gstPercent(product) {
        let cgst = parseFloat(product.cgst_percentage || 0);
        let sgst = parseFloat(product.sgst_percentage || 0);
        let igst = parseFloat(product.igst_percentage || 0);

        if (igst > 0) {
            return numberFormat(igst);
        }

        return numberFormat(cgst + sgst);
    }

    function numberFormat(value) {
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

        showToastSafe('error', (response && response.message) ? response.message : 'Something went wrong.');
    }


    function setSelectValueWithFallback(selector, value) {
        if (!value) {
            return;
        }

        let exists = $(selector + ' option[value="' + value + '"]').length > 0;

        if (!exists) {
            $(selector).append('<option value="' + escapeHtml(value) + '">' + escapeHtml(value) + '</option>');
        }

        $(selector).val(value);
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }
});
