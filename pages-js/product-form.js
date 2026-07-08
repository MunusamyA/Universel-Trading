$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let productId = parseInt(window.PRODUCT_ID || 0);
    let quickMasterModal = null;
    const quickMasterModalEl = document.getElementById('quickMasterModal');
    if (quickMasterModalEl && window.bootstrap && bootstrap.Modal) {
        quickMasterModal = bootstrap.Modal.getOrCreateInstance(quickMasterModalEl);
    }
    let hsnList = [];

    let pageContext = {
        can_add: false,
        can_edit: false,
        add_form_title: 'Add Product',
        edit_form_title: 'Edit Product',
        list_url: ''
    };

    loadPageContext();

    $('#category_id').on('change', function () {
        loadSubCategories($(this).val());
    });

    $('#enable_secondary_unit').on('change', function () {
        toggleSecondaryConversion();
    });

    $('#secondary_unit_label').on('change', function () {
        toggleSecondaryConversion();
    });

    $(document).on('input', '.text-uppercase', function () {
        $(this).val($(this).val().toUpperCase());
    });

    $(document).on('input change', '.price-calc', calculateProductPrice);

    $('#product_image').on('change', function () {
        previewSelectedImage(this);
    });

    $(document).on('click', '#removeImageBtn', function () {
        $('#remove_image').val('1');
        $('#product_image').val('');
        $('#currentImagePreview').html('<span class="text-muted">Image removed. Save product to confirm.</span>');
    });

    $('.quick-master-btn').on('click', function () {
        if (!pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        openQuickMaster($(this).data('master'));
    });

    $('#quickMasterForm').on('submit', function (e) {
        e.preventDefault();
        saveQuickMaster();
    });

    $('#productForm').on('submit', function (e) {
        e.preventDefault();

        let currentProductId = parseInt($('#product_id').val() || 0);

        if (currentProductId > 0 && !pageContext.can_edit) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if (currentProductId <= 0 && !pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if ($('#category_id').val() === '') return warn('Please select category.', '#category_id');
        if ($.trim($('#product_name').val()) === '') return warn('Please enter product name.', '#product_name');

        calculateProductPrice();

        let enterMrp = parseFloat($('#enter_mrp').val() || 0);
        let stockPrice = parseFloat($('#cost_price').val() || 0);
        let retailPrice = parseFloat($('#retail_price').val() || 0);
        let wholesalePrice = parseFloat($('#wholesale_price').val() || 0);
        let secondaryValue = parseFloat($('#secondary_unit_value').val() || 0);
        let initialStock = parseFloat($('#initial_stock').val() || 0);

        if (enterMrp <= 0) return warn('Please enter MRP.', '#enter_mrp');
        if (stockPrice <= 0) return warn('Please enter purchase / stock price.', '#cost_price');
        if (retailPrice <= stockPrice) return warn('Sale / retail price must be greater than stock price.', '#retail_price');
        if (wholesalePrice < stockPrice) return warn('Wholesale price must be greater than or equal to stock price.', '#wholesale_price');
        if ($('#base_unit').val() === '') return warn('Please select base unit.', '#base_unit');
        let secondaryEnabled = $('#enable_secondary_unit').is(':checked');
        let secondaryLabel = secondaryEnabled ? ($('#secondary_unit_label').val() || '') : '';
        if (secondaryEnabled && secondaryLabel === '') return warn('Please select secondary unit label.', '#secondary_unit_label');
        if (secondaryEnabled && secondaryValue <= 0) return warn('Please enter secondary conversion value.', '#secondary_unit_value');
        if (!secondaryEnabled) {
            $('#secondary_unit_label').val('');
            $('#secondary_unit_value').val('');
        }
        if (initialStock < 0) return warn('Initial stock cannot be negative.', '#initial_stock');

        $('#box_label').val('');
        $('#default_pieces_per_box').val('1.0000');

        let formData = new FormData($('#productForm')[0]);
        formData.append('action', 'save_product');

        $('#saveProductBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Product saved.');
                    setTimeout(function () {
                        window.location.href = response.data && response.data.redirect ? response.data.redirect : window.BASE_URL + 'pages/products.php';
                    }, 600);
                } else {
                    handleError(response);
                    $('#saveProductBtn').prop('disabled', false).html('Save Product');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#saveProductBtn').prop('disabled', false).html('Save Product');
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
                    loadHsnCodes();
                    toggleSecondaryConversion();

                    if (productId > 0) {
                        loadProductForEdit(productId);
                    }
                } else {
                    showToastSafe('error', response.message || 'Permission denied.');
                    disableProductForm();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                disableProductForm();
            }
        });
    }

    function applyPageContext() {
        if (pageContext.list_url) {
            $('#backProductsBtn').attr('href', pageContext.list_url);
        }

        if (productId > 0) {
            $('#productPageTitle').text(pageContext.edit_form_title || 'Edit Product');

            if (!pageContext.can_edit) {
                showToastSafe('error', 'Permission denied.');
                disableProductForm();
            }
        } else {
            $('#productPageTitle').text(pageContext.add_form_title || 'Add Product');

            if (!pageContext.can_add) {
                showToastSafe('error', 'Permission denied.');
                disableProductForm();
            }
        }

        if (pageContext.can_add) {
            $('.quick-master-btn').removeClass('d-none');
        } else {
            $('.quick-master-btn').addClass('d-none');
        }
    }

    function disableProductForm() {
        $('#productForm :input').prop('disabled', true);
        $('#saveProductBtn').prop('disabled', true);
        $('.quick-master-btn').addClass('d-none');
    }

    function calculateProductPrice() {
        let enterMrp = parseFloat($('#enter_mrp').val()) || 0;
        let gstType = parseInt($('#gst_type').val()) || 1;
        let gstPercentage = selectedGstPercentage();

        let stockPrice = parseFloat($('#cost_price').val()) || 0;

        let retailMarkupType = parseInt($('#retail_markup_type').val()) || 1;
        let retailMarkupValue = parseFloat($('#retail_markup_value').val()) || 0;

        let wholesaleMarkupType = parseInt($('#wholesale_markup_type').val()) || 1;
        let wholesaleMarkupValue = parseFloat($('#wholesale_markup_value').val()) || 0;

        let gstAmount = 0;
        let finalMrp = 0;

        if (gstType === 1) {
            finalMrp = enterMrp;
            gstAmount = gstPercentage > 0 ? (enterMrp * gstPercentage) / (100 + gstPercentage) : 0;
            $('#gstTypeInfo').text('MRP includes GST ₹' + gstAmount.toFixed(2));
        } else {
            gstAmount = (enterMrp * gstPercentage) / 100;
            finalMrp = enterMrp + gstAmount;
            $('#gstTypeInfo').text('GST added ₹' + gstAmount.toFixed(2));
        }

        $('#gstAmountText').text('GST amount: ₹' + gstAmount.toFixed(2));
        $('#final_mrp').val(finalMrp.toFixed(2));
        $('#stockPriceInfo').text('Manual purchase/stock price');

        let retailMarkupAmount = retailMarkupType === 1 ? (stockPrice * retailMarkupValue) / 100 : retailMarkupValue;
        let wholesaleMarkupAmount = wholesaleMarkupType === 1 ? (stockPrice * wholesaleMarkupValue) / 100 : wholesaleMarkupValue;

        $('.retail-markup-symbol').text(retailMarkupType === 1 ? '%' : '₹');
        $('.wholesale-markup-symbol').text(wholesaleMarkupType === 1 ? '%' : '₹');

        $('#retailMarkupInfo').text('Markup: ₹' + retailMarkupAmount.toFixed(2));
        $('#wholesaleMarkupInfo').text('Markup: ₹' + wholesaleMarkupAmount.toFixed(2));

        let activeId = $(document.activeElement).attr('id');

        if ((parseFloat($('#retail_price').val()) || 0) <= 0 || activeId === 'retail_markup_value' || activeId === 'retail_markup_type' || activeId === 'cost_price') {
            $('#retail_price').val((stockPrice + retailMarkupAmount).toFixed(2));
        }

        if ((parseFloat($('#wholesale_price').val()) || 0) <= 0 || activeId === 'wholesale_markup_value' || activeId === 'wholesale_markup_type' || activeId === 'cost_price') {
            $('#wholesale_price').val((stockPrice + wholesaleMarkupAmount).toFixed(2));
        }

        let retailPrice = parseFloat($('#retail_price').val()) || 0;
        let wholesalePrice = parseFloat($('#wholesale_price').val()) || 0;

        let retailProfit = retailPrice - stockPrice;
        let wholesaleProfit = wholesalePrice - stockPrice;
        let retailProfitPercentage = stockPrice > 0 ? (retailProfit / stockPrice) * 100 : 0;
        let wholesaleProfitPercentage = stockPrice > 0 ? (wholesaleProfit / stockPrice) * 100 : 0;

        $('#retail_profit_display').val(retailProfitPercentage.toFixed(2) + '% / ₹' + retailProfit.toFixed(2));
        $('#wholesale_profit_display').val(wholesaleProfitPercentage.toFixed(2) + '% / ₹' + wholesaleProfit.toFixed(2));

        retailPrice <= stockPrice && stockPrice > 0 ? $('#retailPriceError').removeClass('d-none') : $('#retailPriceError').addClass('d-none');

        if (wholesalePrice < stockPrice && stockPrice > 0) {
            $('#wholesalePriceError').removeClass('d-none');
            $('#wholesalePriceInfo').addClass('d-none');
        } else {
            $('#wholesalePriceError').addClass('d-none');
            $('#wholesalePriceInfo').removeClass('d-none').text(wholesalePrice === stockPrice && stockPrice > 0 ? 'Same as stock price (no markup)' : 'Wholesale price includes markup');
        }

        $('#box_label').val('');
        $('#default_pieces_per_box').val('1.0000');
    }

    function loadProductForEdit(productId) {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_product', product_id: productId },
            success: function (response) {
                if (response.status === true) {
                    let p = response.data.product;
                    $('#product_id').val(p.id);
                    $('#product_code').val(p.product_code);
                    $('#product_name').val(p.product_name);
                    $('#enter_mrp').val(parseFloat(p.enter_mrp || 0).toFixed(2));
                    $('#gst_type').val(p.gst_type || 1);
                    $('#final_mrp').val(parseFloat(p.final_mrp || 0).toFixed(2));
                    $('#discount_type').val('1');
                    $('#discount_value').val('0.00');
                    $('#cost_price').val(parseFloat(p.cost_price || 0).toFixed(2));
                    $('#retail_markup_type').val(p.retail_markup_type || 1);
                    $('#retail_markup_value').val(parseFloat(p.retail_markup_value || 0).toFixed(2));
                    $('#retail_price').val(parseFloat(p.retail_price || 0).toFixed(2));
                    $('#wholesale_markup_type').val(p.wholesale_markup_type || 1);
                    $('#wholesale_markup_value').val(parseFloat(p.wholesale_markup_value || 0).toFixed(2));
                    $('#wholesale_price').val(parseFloat(p.wholesale_price || 0).toFixed(2));
                    selectFallback('#base_unit', p.base_unit || 'Piece');
                    $('#box_label').val('');
                    let hasSecondaryUnit = !!(p.secondary_unit_label && String(p.secondary_unit_label).trim() !== '');
                    $('#enable_secondary_unit').prop('checked', hasSecondaryUnit);
                    selectFallback('#secondary_unit_label', hasSecondaryUnit ? p.secondary_unit_label : '');
                    $('#secondary_unit_value').val((hasSecondaryUnit && p.secondary_unit_value !== null && p.secondary_unit_value !== '') ? parseFloat(p.secondary_unit_value).toFixed(4) : '');
                    toggleSecondaryConversion();
                    $('#initial_stock').val(parseFloat(p.initial_stock || 0).toFixed(4));
                    $('#initial_stock_expiry_date').val(p.initial_stock_expiry_date || '');
                    $('#minimum_stock').val(parseFloat(p.minimum_stock || 0).toFixed(2));
                    $('#status1').val(p.status);
                    renderCurrentImage(p.product_image);
                    loadCategories(p.category_id);
                    loadSubCategories(p.category_id, p.sub_category_id);
                    loadHsnCodes(p.hsn_id);
                } else handleError(response);
            },
            error: function (xhr) { console.log(xhr.responseText); showToastSafe('error', 'Server error.'); }
        });
    }

    function loadCategories(selectedId) {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_categories' },
            success: function (response) {
                let html = '<option value="">Select Category</option>';
                if (response.status === true) {
                    $.each(response.data.categories || [], function (_, c) {
                        html += `<option value="${c.id}" ${parseInt(c.id) === parseInt(selectedId || 0) ? 'selected' : ''}>${escapeHtml(c.category_name)}</option>`;
                    });
                }
                $('#category_id').html(html);
            }
        });
    }

    function loadSubCategories(categoryId, selectedId) {
        if (!categoryId) { $('#sub_category_id').html('<option value="">Select Sub Category (Optional)</option>'); return; }
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_sub_categories', category_id: categoryId },
            success: function (response) {
                let html = '<option value="">Select Sub Category (Optional)</option>';
                if (response.status === true) {
                    $.each(response.data.sub_categories || [], function (_, s) {
                        html += `<option value="${s.id}" ${parseInt(s.id) === parseInt(selectedId || 0) ? 'selected' : ''}>${escapeHtml(s.sub_category_name)}</option>`;
                    });
                }
                $('#sub_category_id').html(html);
            }
        });
    }

    function loadHsnCodes(selectedId) {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_hsn_codes' },
            success: function (response) {
                let html = '<option value="">Select HSN (Optional)</option>';
                if (response.status === true) {
                    hsnList = response.data.hsn_codes || [];
                    $.each(hsnList, function (_, h) {
                        let gst = h.igst_percentage > 0 ? h.igst_percentage : (parseFloat(h.cgst_percentage || 0) + parseFloat(h.sgst_percentage || 0));
                        html += `<option value="${h.id}" ${parseInt(h.id) === parseInt(selectedId || 0) ? 'selected' : ''}>${escapeHtml(h.hsn_code + ' - GST ' + parseFloat(gst).toFixed(2) + '%')}</option>`;
                    });
                }
                $('#hsn_id').html(html);
                calculateProductPrice();
            }
        });
    }


    function toggleSecondaryConversion() {
        let enabled = $('#enable_secondary_unit').is(':checked');

        $('.secondary-unit-field').toggleClass('d-none', !enabled);
        $('#secondary_unit_label').prop('disabled', !enabled);
        $('#secondary_unit_value').prop('disabled', !enabled);

        if (!enabled) {
            $('#secondary_unit_label').val('');
            $('#secondary_unit_value').val('');
        }
    }

    function selectedGstPercentage() {
        let id = parseInt($('#hsn_id').val()) || 0;
        let row = hsnList.find(h => parseInt(h.id) === id);
        if (!row) return 0;
        let igst = parseFloat(row.igst_percentage || 0);
        return igst > 0 ? igst : parseFloat(row.cgst_percentage || 0) + parseFloat(row.sgst_percentage || 0);
    }

    function openQuickMaster(master) {
        $('#quickMasterForm')[0].reset();
        $('#quick_master_type').val(master);
        if (master === 'category') {
            $('#quickMasterTitle').text('Add Category');
            $('#quickMasterBody').html('<div class="mb-3"><label class="form-label">Category Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="category_name" id="quick_category_name"></div><div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>');
        }
        if (master === 'sub_category') {
            $('#quickMasterTitle').text('Add Sub Category');
            $('#quickMasterBody').html('<div class="mb-3"><label class="form-label">Category <span class="text-danger">*</span></label><select class="form-select" name="category_id" id="quick_sub_category_category_id">' + $('#category_id').html() + '</select></div><div class="mb-3"><label class="form-label">Sub Category Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="sub_category_name" id="quick_sub_category_name"></div><div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>');
            $('#quick_sub_category_category_id').val($('#category_id').val());
        }
        if (master === 'hsn') {
            $('#quickMasterTitle').text('Add HSN');
            $('#quickMasterBody').html('<div class="row"><div class="col-md-6"><label class="form-label">HSN Code <span class="text-danger">*</span></label><input type="text" class="form-control" name="hsn_code" id="quick_hsn_code"></div><div class="col-md-6"><label class="form-label">Description</label><input type="text" class="form-control" name="hsn_description"></div><div class="col-md-4 mt-3"><label class="form-label">CGST %</label><input type="number" step="0.01" min="0" class="form-control" name="cgst_percentage" value="0.00"></div><div class="col-md-4 mt-3"><label class="form-label">SGST %</label><input type="number" step="0.01" min="0" class="form-control" name="sgst_percentage" value="0.00"></div><div class="col-md-4 mt-3"><label class="form-label">IGST %</label><input type="number" step="0.01" min="0" class="form-control" name="igst_percentage" value="0.00"></div></div>');
        }
        if (!quickMasterModal) {
            showToastSafe('error', 'Quick master modal not found on this page.');
            return;
        }
        quickMasterModal.show();
    }

    function saveQuickMaster() {
        if (!pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        let master = $('#quick_master_type').val(), action = '';
        if (master === 'category') { if ($.trim($('#quick_category_name').val()) === '') return warn('Please enter category name.', '#quick_category_name'); action = 'quick_add_category'; }
        if (master === 'sub_category') { if ($('#quick_sub_category_category_id').val() === '') return warn('Please select category.', '#quick_sub_category_category_id'); if ($.trim($('#quick_sub_category_name').val()) === '') return warn('Please enter sub category name.', '#quick_sub_category_name'); action = 'quick_add_sub_category'; }
        if (master === 'hsn') { if ($.trim($('#quick_hsn_code').val()) === '') return warn('Please enter HSN code.', '#quick_hsn_code'); action = 'quick_add_hsn'; }
        $('#saveQuickMasterBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php', type: 'POST', dataType: 'json',
            data: $('#quickMasterForm').serialize() + '&action=' + action,
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Saved.');
                    if (quickMasterModal) quickMasterModal.hide();
                    if (master === 'category') loadCategories(response.data.id);
                    if (master === 'sub_category') { $('#category_id').val(response.data.category_id); loadSubCategories(response.data.category_id, response.data.id); }
                    if (master === 'hsn') loadHsnCodes(response.data.id);
                } else handleError(response);
                $('#saveQuickMasterBtn').prop('disabled', false).html('Save');
            },
            error: function (xhr) { console.log(xhr.responseText); showToastSafe('error', 'Server error.'); $('#saveQuickMasterBtn').prop('disabled', false).html('Save'); }
        });
    }

    function previewSelectedImage(input) {
        if (!input.files || !input.files[0]) return;
        let reader = new FileReader();
        reader.onload = function (e) {
            $('#remove_image').val('0');
            $('#currentImagePreview').html(`<div class="d-flex align-items-center gap-2"><img src="${e.target.result}" class="rounded border" style="width:70px;height:70px;object-fit:cover;"><span class="text-muted">New image selected</span></div>`);
        };
        reader.readAsDataURL(input.files[0]);
    }

    function renderCurrentImage(path) {
        if (!path) { $('#currentImagePreview').html('<span class="text-muted">No image uploaded</span>'); return; }
        $('#currentImagePreview').html(`<div class="d-flex align-items-center gap-2"><img src="${window.BASE_URL}${escapeHtml(path)}" class="rounded border" style="width:70px;height:70px;object-fit:cover;"><button type="button" class="btn btn-sm btn-outline-danger" id="removeImageBtn">Remove</button></div>`);
    }

    function warn(msg, selector) { showToastSafe('warning', msg); $(selector).focus(); return false; }
    function selectFallback(selector, value) { if (!value) return; if ($(selector + ' option[value="' + value + '"]').length === 0) $(selector).append('<option value="' + escapeHtml(value) + '">' + escapeHtml(value) + '</option>'); $(selector).val(value); }
    function showToastSafe(type, message) { if (typeof showToast === 'function') showToast(type, message, 5000); else alert(message); }
    function handleError(response) { if (response && response.redirect) { window.location.href = response.redirect; return; } showToastSafe('error', response && response.message ? response.message : 'Something went wrong.'); }
    function escapeHtml(value) { return $('<div>').text(value === null || value === undefined ? '' : value).html(); }
});

