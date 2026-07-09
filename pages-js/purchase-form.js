$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let purchaseId = parseInt(window.PURCHASE_ID || 0);
    let items = [];
    let productList = [];
    let hsnList = [];
    let productMasters = { categories: [], sub_categories: [], hsn_codes: [] };
    let paymentModes = [];
    let purchasePaymentSplits = [];
    let autoRoundOffEnabled = false;

    let pageContext = {
        can_add: false,
        can_edit: false,
        add_form_title: 'Add Purchase',
        edit_form_title: 'Edit Purchase',
        list_url: ''
    };

    $('#quick_gst_type').val('2');
    $('#quick_discount_type').val('2');
    syncQuickSecondaryUnitFields();

    loadPageContext();
    updateRoundOffButtonState();

    $('#productSearchInput').on('focus click', function () {
        showProductSuggestions($(this).val());
    });

    $('#productSearchInput').on('input', function () {
        $('#productSelect').val('');
        showProductSuggestions($(this).val());
    });

    $('#clearProductSearchBtn').on('click', function () {
        clearProductSearch();
    });

    $(document).on('mousedown click', '.product-suggestion-item', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) {
            return;
        }
        let productId = parseInt($(this).data('id') || 0);
        selectProductFromInput(productId);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#productSearchInput, #productSuggestionBox').length) {
            $('#productSuggestionBox').addClass('d-none');
        }
    });

    $(document).on('change', '#pre_hsn_id', function () {
        applySelectedHsn($(this).val());
        calculatePreProduct();
    });

    $('#hsnForm').on('submit', function (e) {
        e.preventDefault();

        if (!pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        saveHsnCode();
    });

    $('#quickProductForm').on('submit', function (e) {
        e.preventDefault();

        if (!pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        saveQuickProduct();
    });

    $('#quick_category_id').on('change', function () {
        renderQuickSubCategories();
    });

    $(document).on('input change', '.quick-product-calc', function () {
        syncQuickSecondaryUnitFields();
        calculateQuickProductPrice();
    });

    $('#quickProductModal').on('shown.bs.modal', function () {
        syncQuickSecondaryUnitFields();
        calculateQuickProductPrice();
    });


    $(document).on('input change', '.pre-calc', function () {
        calculateMixedBoxPieceQty();
        if ($(this).attr('id') === 'pre_expiry_date') {
            $('#pre_expiry_days').val(calculateExpiryDaysFromDate($('#pre_expiry_date').val()));
        }
        calculatePreProduct();
    });

    $('#confirmAddProductBtn').on('click', function () {
        if ($.trim($('#batch_no').val()) === '') {
            $('#batch_no').val(generateHeaderBatchNo());
        }

        let selectedProductId = parseInt($('#pre_product_id').val() || 0);
        if (isProductAlreadyAdded(selectedProductId)) {
            showToastSafe('error', 'This product is already added. Please edit the existing row.');
            clearSelectedProductBox();
            $('#productSelect').val('');
        $('#productSearchInput').val('');
            return;
        }

        let item = getPreProductItem();

        if (!item) {
            return;
        }

        calculateItem(item);
        items.push(item);
        renderItems();
        clearSelectedProductBox();
        $('#productSelect').val('');
        $('#productSearchInput').val('');
    });

    $('#addItemBtn').on('click', function () {
        $('#productSearchInput').focus();
    });

    $(document).on('input', '.item-calc', function () {
        let row = $(this).closest('tr');
        let index = parseInt(row.data('index'));
        updateItemFromRow(index);
        updateItemRowAmounts(row, index);
        calculateTotals();
    });

    $(document).on('change blur', '.item-calc', function () {
        let row = $(this).closest('tr');
        let index = parseInt(row.data('index'));
        updateItemFromRow(index);
        updateItemRowAmounts(row, index);
        calculateTotals();
    });

    $(document).on('keydown', '.item-calc', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $(this).blur();
        }
    });

    $(document).on('click', '.remove-item-btn', function () {
        let index = parseInt($(this).closest('tr').data('index'));
        let removedProductId = parseInt(items[index] ? items[index].product_id : 0);

        items.splice(index, 1);

        renderItems();
        refreshProductSelectOptions();

        if (removedProductId > 0) {
            $('#productSelect').val('');
        $('#productSearchInput').val('');
            $('#selectedProductBox').addClass('d-none');
            showToastSafe('success', 'Product removed. You can add the same product again.');
        }
    });

    $('.calc-main').on('input change', function () {
        if ($(this).attr('id') === 'round_off') {
            autoRoundOffEnabled = false;
            updateRoundOffButtonState();
        }
        calculateTotals();
        syncPurchaseSplitsWithPaidAmount();
    });

    $('#roundOffToggleBtn').on('click', function () {
        autoRoundOffEnabled = !autoRoundOffEnabled;

        if (!autoRoundOffEnabled) {
            $('#round_off').val('0.00');
        }

        updateRoundOffButtonState();
        calculateTotals();
        syncPurchaseSplitsWithPaidAmount();
    });

    $('#addPurchasePaymentSplitBtn').on('click', function () {
        addPurchasePaymentSplit();
    });

    $(document).on('input change', '.purchase-payment-split-calc', function () {
        updatePurchaseSplitFromRow($(this).closest('tr').data('index'));
    });

    $(document).on('click', '.remove-purchase-split-btn', function () {
        let index = parseInt($(this).closest('tr').data('index'));
        purchasePaymentSplits.splice(index, 1);
        renderPurchasePaymentSplits();
    });

    $('#bill_no').on('input change', function () {
        $('#batch_no').val(generateHeaderBatchNo());
        $('#pre_purchase_batch_no').val($('#batch_no').val());
    });

    $('#purchase_date').on('change', function () {
        $('#batch_no').val(generateHeaderBatchNo());

        if ($('#selectedProductBox').hasClass('d-none') === false) {
            $('#pre_expiry_date').val(calculateExpiryDate(parseInt($('#pre_expiry_days').val() || 0)));
            $('#pre_purchase_batch_no').val($('#batch_no').val());
            calculatePreProduct();
        }
    });

    $('#purchaseForm').on('submit', function (e) {
        e.preventDefault();

        let currentPurchaseId = parseInt($('#purchase_id').val() || 0);

        if (currentPurchaseId > 0 && !pageContext.can_edit) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if (currentPurchaseId <= 0 && !pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if ($('#supplier_id').val() === '') {
            return warn('Please select supplier.', '#supplier_id');
        }

        if ($.trim($('#bill_no').val()) === '') {
            return warn('Please enter bill number.', '#bill_no');
        }

        if ($('#purchase_date').val() === '') {
            return warn('Please select purchase date.', '#purchase_date');
        }

        if (items.length === 0) {
            return warn('Please add at least one product.', '#productSelect');
        }

        for (let i = 0; i < items.length; i++) {
            let item = items[i];

            if (!item.product_id) {
                return warn('Invalid product in row ' + (i + 1), '#productSelect');
            }

            if (parseFloat(item.qty || 0) <= 0) {
                return warn('Quantity must be greater than zero in row ' + (i + 1), '#item_qty_' + i);
            }

            if (parseFloat(item.purchase_price || 0) <= 0) {
                return warn('Purchase price must be greater than zero in row ' + (i + 1), '#item_purchase_price_' + i);
            }

            if (parseFloat(item.unit_conversion || 0) <= 0) {
                return warn('Unit conversion must be greater than zero in row ' + (i + 1), '#item_unit_conversion_' + i);
            }
        }

        $('#items_json').val(JSON.stringify(items));

        if (!validatePurchasePaymentSplits()) {
            return;
        }

        $('#payment_splits_json').val(JSON.stringify(purchasePaymentSplits));

        $('#savePurchaseBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: $('#purchaseForm').serialize() + '&action=save_purchase',
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Purchase saved.');
                    setTimeout(function () {
                        window.location.href = response.data && response.data.redirect
                            ? response.data.redirect
                            : window.BASE_URL + 'pages/purchases.php';
                    }, 600);
                } else {
                    handleError(response);
                    $('#savePurchaseBtn').prop('disabled', false).html('Save Purchase');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#savePurchaseBtn').prop('disabled', false).html('Save Purchase');
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

                    loadSuppliers();
                    loadProducts();
                    loadHsnCodes();
                    loadProductMasters();
                    loadPurchasePaymentModes();

                    if (purchaseId > 0) {
                        loadPurchase(purchaseId);
                    } else {
                        $('#batch_no').val(generateHeaderBatchNo());
                    }
                } else {
                    showToastSafe('error', response.message || 'Permission denied.');
                    disablePurchaseForm();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                disablePurchaseForm();
            }
        });
    }

    function applyPageContext() {
        if (pageContext.list_url) {
            $('#backPurchasesBtn').attr('href', pageContext.list_url);
        }

        if (purchaseId > 0) {
            $('#purchasePageTitle').text(pageContext.edit_form_title || 'Edit Purchase');

            if (!pageContext.can_edit) {
                showToastSafe('error', 'Permission denied.');
                disablePurchaseForm();
            }
        } else {
            $('#purchasePageTitle').text(pageContext.add_form_title || 'Add Purchase');

            if (!pageContext.can_add) {
                showToastSafe('error', 'Permission denied.');
                disablePurchaseForm();
            }
        }

        if (pageContext.can_add) {
            $('#quickProductBtn').removeClass('d-none');
            $('.quick-hsn-btn').removeClass('d-none');
        } else {
            $('#quickProductBtn').addClass('d-none');
            $('.quick-hsn-btn').addClass('d-none');
        }
    }

    function disablePurchaseForm() {
        $('#purchaseForm :input').prop('disabled', true);
        $('#savePurchaseBtn').prop('disabled', true);
        $('#quickProductBtn').addClass('d-none');
        $('.quick-hsn-btn').addClass('d-none');
    }

    function loadPurchasePaymentModes() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_payment_modes' },
            success: function (response) {
                if (response.status === true) {
                    paymentModes = response.data.payment_modes || [];
                    renderPurchasePaymentSplits();
                }
            },
            error: function (xhr) { console.log(xhr.responseText); }
        });
    }

    function syncPurchaseSplitsWithPaidAmount() {
        let paidAmount = round2(parseFloat($('#paid_amount').val() || 0));

        if (paidAmount <= 0) {
            purchasePaymentSplits = [];
            renderPurchasePaymentSplits();
            return;
        }

        if (purchasePaymentSplits.length === 0) {
            purchasePaymentSplits.push({
                payment_mode_id: paymentModes.length ? parseInt(paymentModes[0].id) : 0,
                amount: paidAmount,
                reference_no: ''
            });
        } else if (purchasePaymentSplits.length === 1) {
            purchasePaymentSplits[0].amount = paidAmount;
        }

        renderPurchasePaymentSplits();
    }

    function addPurchasePaymentSplit() {
        let paidAmount = round2(parseFloat($('#paid_amount').val() || 0));
        if (paidAmount <= 0) return warn('Enter paid amount first.', '#paid_amount');

        let balance = round2(paidAmount - getPurchaseSplitTotal());
        if (balance < 0) balance = 0;

        purchasePaymentSplits.push({
            payment_mode_id: paymentModes.length ? parseInt(paymentModes[0].id) : 0,
            amount: balance,
            reference_no: ''
        });

        renderPurchasePaymentSplits();
    }

    function renderPurchasePaymentSplits() {
        let paidAmount = round2(parseFloat($('#paid_amount').val() || 0));

        if (paidAmount <= 0 || purchasePaymentSplits.length === 0) {
            $('#purchasePaymentSplitsBody').html('<tr><td colspan="4" class="text-center text-muted">Enter paid amount to add split.</td></tr>');
            $('#purchaseSplitTotal').text('0.00');
            $('#purchaseSplitBalance').text('0.00');
            return;
        }

        let html = '';
        $.each(purchasePaymentSplits, function (index, split) {
            html += `
                <tr data-index="${index}">
                    <td><select class="form-select form-select-sm purchase-payment-split-calc split-mode">${buildPaymentModeOptions(split.payment_mode_id)}</select></td>
                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end purchase-payment-split-calc split-amount" value="${parseFloat(split.amount || 0).toFixed(2)}"></td>
                    <td><input type="text" class="form-control form-control-sm purchase-payment-split-calc split-ref" value="${escapeHtml(split.reference_no || '')}"></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-purchase-split-btn"><i class="mdi mdi-delete"></i></button></td>
                </tr>`;
        });

        $('#purchasePaymentSplitsBody').html(html);
        renderPurchaseSplitTotals();
    }

    function buildPaymentModeOptions(selectedId) {
        let html = '<option value="">Select</option>';
        $.each(paymentModes || [], function (_, mode) {
            let selected = parseInt(mode.id) === parseInt(selectedId || 0) ? 'selected' : '';
            html += `<option value="${mode.id}" ${selected}>${escapeHtml(mode.mode_name || '')}</option>`;
        });
        return html;
    }

    function updatePurchaseSplitFromRow(index) {
        index = parseInt(index || 0);
        let row = $('#purchasePaymentSplitsBody tr[data-index="' + index + '"]');
        if (!purchasePaymentSplits[index] || row.length === 0) return;

        purchasePaymentSplits[index].payment_mode_id = parseInt(row.find('.split-mode').val() || 0);
        purchasePaymentSplits[index].amount = round2(parseFloat(row.find('.split-amount').val() || 0));
        purchasePaymentSplits[index].reference_no = row.find('.split-ref').val() || '';
        renderPurchaseSplitTotals();
    }

    function getPurchaseSplitTotal() {
        let total = 0;
        $.each(purchasePaymentSplits, function (_, split) {
            total += parseFloat(split.amount || 0);
        });
        return round2(total);
    }

    function renderPurchaseSplitTotals() {
        let paidAmount = round2(parseFloat($('#paid_amount').val() || 0));
        let splitTotal = getPurchaseSplitTotal();
        let balance = round2(paidAmount - splitTotal);
        $('#purchaseSplitTotal').text(splitTotal.toFixed(2));
        $('#purchaseSplitBalance').text(balance.toFixed(2));
    }

    function validatePurchasePaymentSplits() {
        let paidAmount = round2(parseFloat($('#paid_amount').val() || 0));
        if (paidAmount <= 0) {
            purchasePaymentSplits = [];
            return true;
        }

        if (purchasePaymentSplits.length === 0) {
            return warn('Payment split is required.', '#paid_amount');
        }

        for (let i = 0; i < purchasePaymentSplits.length; i++) {
            if (parseInt(purchasePaymentSplits[i].payment_mode_id || 0) <= 0) {
                return warn('Please select payment mode in split row ' + (i + 1), '#purchasePaymentSplitsBody');
            }
            if (parseFloat(purchasePaymentSplits[i].amount || 0) <= 0) {
                return warn('Please enter split amount in row ' + (i + 1), '#purchasePaymentSplitsBody');
            }
        }

        if (Math.abs(getPurchaseSplitTotal() - paidAmount) > 0.01) {
            return warn('Payment split total must match paid amount.', '#paid_amount');
        }

        return true;
    }

    function loadSuppliers(selectedId) {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_suppliers' },
            success: function (response) {
                let html = '<option value="">Select Supplier</option>';

                if (response.status === true) {
                    $.each(response.data.suppliers || [], function (_, supplier) {
                        let selected = parseInt(supplier.id) === parseInt(selectedId || 0) ? 'selected' : '';
                        html += `<option value="${supplier.id}" ${selected}>${escapeHtml(supplier.supplier_name)}</option>`;
                    });
                }

                $('#supplier_id').html(html);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function loadProducts(selectedProductId, callback) {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_products' },
            success: function (response) {
                if (response.status === true) {
                    productList = response.data.products || [];
                }

                if (typeof callback === 'function') {
                    callback(productList);
                }

                if (selectedProductId) {
                    selectProductFromInput(selectedProductId);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Unable to load products.');
            }
        });
    }


    function loadProductMasters() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_product_masters' },
            success: function (response) {
                if (response.status === true) {
                    productMasters = response.data || { categories: [], sub_categories: [], hsn_codes: [] };
                    renderQuickProductMasters();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function renderQuickProductMasters() {
        let categoryHtml = '<option value="">Select</option>';
        $.each(productMasters.categories || [], function (_, item) {
            categoryHtml += `<option value="${item.id}">${escapeHtml(item.category_name || '')}</option>`;
        });
        $('#quick_category_id').html(categoryHtml);

        let hsnHtml = '<option value="">Select</option>';
        $.each(productMasters.hsn_codes || [], function (_, item) {
            hsnHtml += `<option value="${item.id}">${escapeHtml(item.hsn_code || '')}</option>`;
        });
        $('#quick_hsn_id').html(hsnHtml);

        renderQuickSubCategories();
    }

    function renderQuickSubCategories() {
        let categoryId = parseInt($('#quick_category_id').val() || 0);
        let html = '<option value="">Select</option>';

        $.each(productMasters.sub_categories || [], function (_, item) {
            if (categoryId <= 0 || parseInt(item.category_id || 0) === categoryId) {
                html += `<option value="${item.id}">${escapeHtml(item.sub_category_name || '')}</option>`;
            }
        });

        $('#quick_sub_category_id').html(html);
    }


    function calculateQuickProductPrice() {
        syncQuickSecondaryUnitFields();

        let stockPrice = parseFloat($('#quick_cost_price').val() || 0);
        let mrp = parseFloat($('#quick_enter_mrp').val() || 0);
        let discountType = parseInt($('#quick_discount_type').val() || 2);
        let discountValue = parseFloat($('#quick_discount_value').val() || 0);

        let discountAmount = discountType === 1 ? (mrp * discountValue / 100) : discountValue;
        if (discountAmount > mrp) discountAmount = mrp;

        let finalMrp = mrp - discountAmount;
        $('#quick_final_mrp').val(finalMrp.toFixed(2));

        let retailMarkupType = parseInt($('#quick_retail_markup_type').val() || 1);
        let retailMarkupValue = parseFloat($('#quick_retail_markup_value').val() || 0);
        let retailMarkupAmount = retailMarkupType === 1 ? (stockPrice * retailMarkupValue / 100) : retailMarkupValue;
        let retailPrice = stockPrice + retailMarkupAmount;

        if (stockPrice > 0 && (parseFloat($('#quick_retail_price').val() || 0) <= 0 || $(document.activeElement).is('#quick_cost_price,#quick_retail_markup_type,#quick_retail_markup_value'))) {
            $('#quick_retail_price').val(retailPrice.toFixed(2));
        }

        $('#quick_product_calc_info').html(
            'Purchase Price: ₹' + stockPrice.toFixed(2) +
            ' | Final MRP: ₹' + finalMrp.toFixed(2) +
            ' | Retail Markup: ₹' + retailMarkupAmount.toFixed(2)
        );
    }

    function saveQuickProduct() {
        if (!pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        $('#saveQuickProductBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: $('#quickProductForm').serialize(),
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Product saved.');
                    $('#quickProductModal').modal('hide');
                    $('#quickProductForm')[0].reset();
                    $('#quick_base_unit').val('Piece');
                    $('#quick_gst_type').val('2');
                    $('#quick_discount_type').val('2');
                    $('#quick_enable_secondary_unit').prop('checked', false);
                    $('#quick_secondary_unit_label').val('');
                    $('#quick_secondary_unit_value').val('');
                    syncQuickSecondaryUnitFields();
                    $('#quick_expire_days').val('0');
                    $('#quick_status').val('1');
                    calculateQuickProductPrice();

                    let productId = response.data ? response.data.product_id : 0;
                    loadProducts(productId);
                } else {
                    handleError(response);
                }
                $('#saveQuickProductBtn').prop('disabled', false).html('Save Product Master');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#saveQuickProductBtn').prop('disabled', false).html('Save Product Master');
            }
        });
    }

    function productHasSecondaryUnit(product) {
        let value = parseFloat(product.secondary_unit_value || product.default_pieces_per_box || product.pieces_per_box || 0);
        return value > 1;
    }

    function getProductSecondaryLabel(product) {
        return $.trim(product.secondary_unit_label || product.box_label || 'Box');
    }

    function getProductSecondaryValue(product) {
        let value = parseFloat(product.secondary_unit_value || product.default_pieces_per_box || product.pieces_per_box || 1);
        return value > 0 ? value : 1;
    }

    function getProductPurchaseRate(product) {
        let rate = parseFloat(product.cost_price || 0);
        if (rate <= 0) {
            rate = parseFloat(product.purchase_price || 0);
        }
        return rate > 0 ? rate : 0;
    }

    function togglePreSecondaryUnit(enabled) {
        enabled = !!enabled;

        if (enabled) {
            $('.pre-secondary-unit-field').removeClass('d-none');
            $('#preLoosePieceQtyLabel').text('Loose Piece Qty');
            $('#pre_unit_conversion').prop('readonly', false);
        } else {
            $('.pre-secondary-unit-field').addClass('d-none');
            $('#preLoosePieceQtyLabel').text('Piece Qty');
            $('#pre_box_qty').val('0');
            $('#pre_unit_conversion').val('1.0000');
        }

        calculateMixedBoxPieceQty();
    }

    function renderPurchaseUnitOptions(product) {
        let baseUnit = product.base_unit || 'Piece';
        let hasSecondary = productHasSecondaryUnit(product);
        let secondaryLabel = getProductSecondaryLabel(product);
        let secondaryValue = getProductSecondaryValue(product);

        if (hasSecondary) {
            $('#pre_unit_label').val(secondaryLabel);
            $('#pre_box_label').val(secondaryLabel);
            $('#pre_pieces_per_box').val(secondaryValue.toFixed(4));
            $('#pre_unit_conversion').val(secondaryValue.toFixed(4));
        } else {
            $('#pre_unit_label').val(baseUnit);
            $('#pre_box_label').val('');
            $('#pre_pieces_per_box').val('1.0000');
            $('#pre_unit_conversion').val('1.0000');
        }

        togglePreSecondaryUnit(hasSecondary);
    }

    function calculateMixedBoxPieceQty() {
        let hasSecondary = !$('.pre-secondary-unit-field').first().hasClass('d-none');
        let boxQty = hasSecondary ? parseFloat($('#pre_box_qty').val() || 0) : 0;
        let loosePieceQty = parseFloat($('#pre_loose_piece_qty').val() || 0);
        let freeQty = parseFloat($('#pre_free_qty').val() || 0);
        let piecesPerBox = hasSecondary ? parseFloat($('#pre_unit_conversion').val() || 1) : 1;

        if (piecesPerBox <= 0) piecesPerBox = 1;

        let totalPieces = round4((boxQty * piecesPerBox) + loosePieceQty + freeQty);
        $('#pre_qty').val(totalPieces.toFixed(4));
        return totalPieces;
    }

    function clearProductSearch() {
        $('#productSelect').val('');
        $('#productSearchInput').val('');
        $('#productSuggestionBox').addClass('d-none').empty();
        clearSelectedProductBox();
        setTimeout(function () {
            $('#productSearchInput').focus();
        }, 50);
    }

    function syncQuickSecondaryUnitFields() {
        let enabled = $('#quick_enable_secondary_unit').is(':checked');

        if (enabled) {
            $('.quick-secondary-unit-fields').removeClass('d-none');
            if ($('#quick_secondary_unit_label').val() === '') {
                $('#quick_secondary_unit_label').val('Box');
            }
            if (parseFloat($('#quick_secondary_unit_value').val() || 0) <= 0) {
                $('#quick_secondary_unit_value').val('1.0000');
            }
            $('#quick_box_label').val($('#quick_secondary_unit_label').val() || 'Box');
            $('#quick_default_pieces_per_box').val(parseFloat($('#quick_secondary_unit_value').val() || 1).toFixed(4));
        } else {
            $('.quick-secondary-unit-fields').addClass('d-none');
            $('#quick_secondary_unit_label').val('');
            $('#quick_secondary_unit_value').val('');
            $('#quick_box_label').val('');
            $('#quick_default_pieces_per_box').val('1.0000');
        }
    }

    function showProductSuggestions(keyword) {
        if (productList.length === 0) {
            $('#productSuggestionBox').html('<div class="list-group-item text-muted">Loading products...</div>').removeClass('d-none');
            loadProducts(null, function () {
                renderProductSuggestions(keyword);
            });
            return;
        }

        renderProductSuggestions(keyword);
    }

    function renderProductSuggestions(keyword) {
        keyword = $.trim(keyword || '').toLowerCase();
        let filtered = productList.filter(function (p) {
            let name = (p.product_name || '').toLowerCase();
            let code = (p.product_code || '').toLowerCase();
            return keyword === '' || name.indexOf(keyword) >= 0 || code.indexOf(keyword) >= 0;
        }).slice(0, 10);

        let html = '';

        if (filtered.length === 0) {
            html = '<div class="list-group-item text-muted">No products found</div>';
        } else {
            $.each(filtered, function (_, product) {
                let disabled = isProductAlreadyAdded(parseInt(product.id || 0)) ? 'disabled text-muted' : '';
                html += `<button type="button" class="list-group-item list-group-item-action product-suggestion-item ${disabled}" data-id="${product.id}" ${disabled ? 'disabled' : ''}>
                    <div class="fw-semibold">${escapeHtml(product.product_name || '')}</div>
                    <small>${escapeHtml(product.product_code || '')} ${product.hsn_code ? ' | HSN: ' + escapeHtml(product.hsn_code) : ''}</small>
                </button>`;
            });
        }

        $('#productSuggestionBox').html(html).removeClass('d-none');
    }

    function selectProductFromInput(productId) {
        if (productId <= 0) {
            return warn('Please select product.', '#productSearchInput');
        }

        let product = productList.find(function (p) {
            return parseInt(p.id) === parseInt(productId);
        });

        if (!product) {
            return warn('Invalid product selected.', '#productSearchInput');
        }

        $('#productSelect').val(product.id);
        $('#productSearchInput').val((product.product_name || '') + (product.product_code ? ' - ' + product.product_code : ''));
        $('#productSuggestionBox').addClass('d-none');
        fillSelectedProduct(product);
    }


    function loadHsnCodes(selectedId) {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_hsn_codes' },
            success: function (response) {
                let html = '<option value="">Select HSN</option>';

                if (response.status === true) {
                    hsnList = response.data.hsn_codes || [];

                    $.each(hsnList, function (_, hsn) {
                        let selected = parseInt(hsn.id) === parseInt(selectedId || 0) ? 'selected' : '';
                        html += `<option value="${hsn.id}" ${selected}>${escapeHtml(hsn.hsn_code || '')}</option>`;
                    });
                }

                $('#pre_hsn_id').html(html);

                if (selectedId) {
                    $('#pre_hsn_id').val(selectedId);
                    applySelectedHsn(selectedId);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Unable to load HSN codes.');
            }
        });
    }

    function saveHsnCode() {
        if (!pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        $('#saveHsnBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: $('#hsnForm').serialize(),
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'HSN saved.');
                    $('#hsnModal').modal('hide');
                    $('#hsnForm')[0].reset();
                    loadHsnCodes(response.data ? response.data.hsn_id : 0);
                } else {
                    handleError(response);
                }
                $('#saveHsnBtn').prop('disabled', false).html('Save HSN');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#saveHsnBtn').prop('disabled', false).html('Save HSN');
            }
        });
    }

    function applySelectedHsn(hsnId) {
        hsnId = parseInt(hsnId || 0);
        let hsn = hsnList.find(function (h) {
            return parseInt(h.id) === hsnId;
        });

        if (!hsn) {
            $('#pre_hsn_code').val('');
            $('#pre_cgst_percentage').val('0.00');
            $('#pre_sgst_percentage').val('0.00');
            $('#pre_igst_percentage').val('0.00');
            return;
        }

        $('#pre_hsn_code').val(hsn.hsn_code || '');
        $('#pre_cgst_percentage').val(parseFloat(hsn.cgst_percentage || 0).toFixed(2));
        $('#pre_sgst_percentage').val(parseFloat(hsn.sgst_percentage || 0).toFixed(2));
        $('#pre_igst_percentage').val(parseFloat(hsn.igst_percentage || 0).toFixed(2));
    }



    function refreshProductSelectOptions() {
        // Product is input-search based now. Suggestions are refreshed dynamically.
        if (!$('#productSuggestionBox').hasClass('d-none')) {
            renderProductSuggestions($('#productSearchInput').val());
        }
    }

    function isProductAlreadyAdded(productId) {
        productId = parseInt(productId || 0);

        if (productId <= 0) {
            return false;
        }

        return items.some(function (item) {
            return parseInt(item.product_id || 0) === productId;
        });
    }

    function fillSelectedProduct(product) {
        let gstPercentage = parseFloat(product.igst_percentage || 0);

        if (gstPercentage <= 0) {
            gstPercentage = parseFloat(product.cgst_percentage || 0) + parseFloat(product.sgst_percentage || 0);
        }

        $('#pre_product_id').val(product.id);
        $('#pre_product_code').val(product.product_code || '');
        $('#pre_product_name').val(product.product_name || '');
        $('#pre_hsn_id').val(product.hsn_id || 0);
        applySelectedHsn(product.hsn_id || 0);
        $('#pre_base_unit').val(product.base_unit || 'Piece');
        $('#pre_box_label').val(product.box_label || 'Box');
        $('#pre_pieces_per_box').val(parseFloat(product.secondary_unit_value || product.default_pieces_per_box || 1));

        $('#selectedProductTitle').text((product.product_name || '') + (product.product_code ? ' - ' + product.product_code : ''));

        $('#pre_purchase_batch_no').val($('#batch_no').val());
        $('#pre_unit_label').val('');
        $('#pre_box_qty').val('0');
        $('#pre_loose_piece_qty').val('0');
        $('#pre_free_qty').val('0');
        $('#pre_qty').val('0');
        renderPurchaseUnitOptions(product);
        $('#pre_purchase_price').val(getProductPurchaseRate(product).toFixed(2));
        $('#pre_discount_type').val('2');
        $('#pre_discount_value').val('0.00');
        $('#pre_gst_type').val('2');
        
        $('#pre_mrp').val(parseFloat(product.final_mrp || product.enter_mrp || 0).toFixed(2));
        $('#pre_retail_price').val(parseFloat(product.retail_price || 0).toFixed(2));
        $('#pre_expiry_days').val(parseInt(product.expire_days || 0));
        $('#pre_expiry_date').val('');

        $('#selectedProductBox').removeClass('d-none');
        calculatePreProduct();
    }

    function getPreProductItem() {
        let productId = parseInt($('#pre_product_id').val() || 0);

        if (productId <= 0) {
            return warn('Please select product.', '#productSearchInput');
        }

        let boxQty = parseFloat($('#pre_box_qty').val() || 0);
        let loosePieceQty = parseFloat($('#pre_loose_piece_qty').val() || 0);
        let freeQty = parseFloat($('#pre_free_qty').val() || 0);
        let unitConversion = parseFloat($('#pre_unit_conversion').val() || 0);
        let qty = calculateMixedBoxPieceQty();
        let purchasePrice = parseFloat($('#pre_purchase_price').val() || 0);

        if (qty <= 0) {
            return warn('Total pieces must be greater than zero. Enter box qty or loose piece qty.', '#pre_box_qty');
        }

        if (purchasePrice <= 0) {
            return warn('Purchase price must be greater than zero.', '#pre_purchase_price');
        }

        if (unitConversion <= 0) {
            return warn('Conversion must be greater than zero.', '#pre_unit_conversion');
        }

        return {
            product_id: productId,
            product_code: $('#pre_product_code').val(),
            product_name: $('#pre_product_name').val(),
            hsn_id: parseInt($('#pre_hsn_id').val() || 0),
            hsn_code: $('#pre_hsn_code').val(),
            cgst_percentage: parseFloat($('#pre_cgst_percentage').val() || 0),
            sgst_percentage: parseFloat($('#pre_sgst_percentage').val() || 0),
            igst_percentage: parseFloat($('#pre_igst_percentage').val() || 0),
            base_unit: $('#pre_base_unit').val(),
            box_label: $('#pre_box_label').val(),
            pieces_per_box: parseFloat($('#pre_pieces_per_box').val() || 1),
            expiry_days: parseInt($('#pre_expiry_days').val() || 0),
            expiry_date: $('#pre_expiry_date').val(),
            unit_label: $('.pre-secondary-unit-field').first().hasClass('d-none') ? ($('#pre_base_unit').val() || 'Piece') : ($('#pre_unit_label').val() || 'Box'),
            box_qty: boxQty,
            loose_piece_qty: loosePieceQty,
            qty: qty,
            free_qty: parseFloat($('#pre_free_qty').val() || 0),
            unit_conversion: unitConversion,
            purchase_price: purchasePrice,
            discount_type: parseInt($('#pre_discount_type').val() || 2),
            discount_value: parseFloat($('#pre_discount_value').val() || 0),
            gst_type: parseInt($('#pre_gst_type').val() || 2),
            gst_percentage: getGstPercentage(),
            retail_price: 0,
            wholesale_price: 0,
            retail_scheme_discount_type: 1,
            retail_scheme_discount_value: 0,
            wholesale_scheme_discount_type: 1,
            wholesale_scheme_discount_value: 0,
            mrp: parseFloat($('#pre_mrp').val() || 0),
            retail_price: 0,
            wholesale_price: 0,
            retail_scheme_discount_type: 1,
            retail_scheme_discount_value: 0,
            wholesale_scheme_discount_type: 1,
            wholesale_scheme_discount_value: 0,
            line_total: 0
        };
    }

    function calculatePreProduct() {
        let item = getPreProductItemWithoutValidation();
        calculateItem(item);

        $('#pre_stock_qty').text(numberFormat4(item.stock_qty || 0));
        $('#pre_gst_amount').text(numberFormat(item.gst_amount || 0));
        $('#pre_scheme_total_amount').text(numberFormat(item.discount_amount || 0));
        $('#pre_scheme_per_piece').text(numberFormat(item.scheme_per_piece || 0));
        $('#pre_taxable_per_piece').text(numberFormat(item.taxable_per_piece || 0));
        $('#pre_gst_per_piece').text(numberFormat(item.gst_per_piece || 0));
        $('#pre_net_per_piece').text(numberFormat(item.net_per_piece || 0));
        $('#pre_line_total').text(numberFormat(item.line_total || 0));
        $('#pre_gst_type_info').text(parseInt(item.gst_type || 2) === 1 ? 'Inclusive: GST split from rate' : 'Exclusive: GST added after scheme');
    }

    function getPreProductItemWithoutValidation() {
        return {
            box_qty: parseFloat($('#pre_box_qty').val() || 0),
            loose_piece_qty: parseFloat($('#pre_loose_piece_qty').val() || 0),
            qty: calculateMixedBoxPieceQty(),
            free_qty: parseFloat($('#pre_free_qty').val() || 0),
            unit_conversion: parseFloat($('#pre_unit_conversion').val() || 1),
            purchase_price: parseFloat($('#pre_purchase_price').val() || 0),
            discount_type: parseInt($('#pre_discount_type').val() || 2),
            discount_value: parseFloat($('#pre_discount_value').val() || 0),
            gst_type: parseInt($('#pre_gst_type').val() || 2),
            gst_percentage: getGstPercentage(),
            retail_price: 0,
            wholesale_price: 0,
            retail_scheme_discount_type: 1,
            retail_scheme_discount_value: 0,
            wholesale_scheme_discount_type: 1,
            wholesale_scheme_discount_value: 0
        };
    }

    function clearSelectedProductBox() {
        $('#selectedProductBox').addClass('d-none');
        $('#selectedProductBox input').not('#pre_purchase_batch_no').val('');
        $('#pre_purchase_batch_no').val($('#batch_no').val());
        $('#pre_box_qty').val('0');
        $('#pre_loose_piece_qty').val('0');
        $('#pre_free_qty').val('0');
        $('#pre_qty').val('0');
        $('#pre_unit_conversion').val('1.0000');
        $('#pre_purchase_price').val('');
        $('#pre_discount_type').val('2');
        $('#pre_discount_value').val('0.00');
        $('#pre_gst_type').val('2');
        $('#pre_gst_percentage').val('0.00');
        $('#pre_mrp').val('0.00');
        $('#pre_retail_price').val('0.00');
        
        
        $('#pre_cgst_percentage').val('0.00');
        $('#pre_sgst_percentage').val('0.00');
        $('#pre_igst_percentage').val('0.00');
        $('#pre_expiry_days').val('0');
        $('#pre_expiry_date').val('');
        togglePreSecondaryUnit(false);
    }

    function renderItems() {
        if (!items || items.length === 0) {
            $('#itemsBody').html('<tr><td colspan="11" class="text-center text-muted">No products added.</td></tr>');
            calculateTotals();
            return;
        }

        let html = '';

        $.each(items, function (index, item) {
            calculateItem(item);

            html += `
                <tr data-index="${index}">
                    <td>
                        <input type="hidden" class="item-product-id" value="${item.product_id}">
                        <h6 class="mb-0">${escapeHtml(item.product_name)}</h6>
                        <small class="text-muted">${escapeHtml(item.product_code || '')} | HSN: ${escapeHtml(item.hsn_code || '-')}</small>
                        <br><small class="text-muted">Box: ${numberFormat4(item.box_qty || 0)} | Loose: ${numberFormat4(item.loose_piece_qty || 0)} | Free: ${numberFormat4(item.free_qty || 0)} | UPC: ${numberFormat4(item.unit_conversion || 1)}</small>
                    </td>
                    <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm item-calc" id="item_qty_${index}" data-field="qty" value="${item.qty}"></td>
                    <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm item-calc" data-field="free_qty" value="${item.free_qty}"></td>
                    <td>
                        <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm item-calc" id="item_unit_conversion_${index}" data-field="unit_conversion" value="${item.unit_conversion}">
                        <small class="text-muted">Stock: ${numberFormat4(item.stock_qty || 0)}</small>
                    </td>
                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm item-calc" id="item_purchase_price_${index}" data-field="purchase_price" value="${item.purchase_price}"></td>
                    <td class="scheme-disc-col">
                        <div class="input-group input-group-sm">
                            <select class="form-select item-calc" data-field="discount_type">
                                <option value="2" ${parseInt(item.discount_type) === 2 ? 'selected' : ''}>₹</option>
                                <option value="1" ${parseInt(item.discount_type) === 1 ? 'selected' : ''}>%</option>
                            </select>
                            <input type="number" step="0.01" min="0" class="form-control item-calc" data-field="discount_value" value="${item.discount_value}">
                        </div>
                    </td>
                    <td class="gst-col">
                        <div class="input-group input-group-sm">
                            <select class="form-select item-calc" data-field="gst_type">
                                <option value="2" ${parseInt(item.gst_type) === 2 ? 'selected' : ''}>Excl</option>
                                <option value="1" ${parseInt(item.gst_type) === 1 ? 'selected' : ''}>Incl</option>
                            </select>
                            <input type="number" step="0.01" min="0" class="form-control item-calc" data-field="gst_percentage" value="${item.gst_percentage}">
                        </div>
                        <small class="text-muted item-gst-amount">GST ₹${numberFormat(item.gst_amount || 0)}</small>
                    </td>
                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm item-calc" data-field="mrp" value="${item.mrp}"></td>
                    <td>
                        <input type="number" step="1" min="0" class="form-control form-control-sm item-calc mb-1" data-field="expiry_days" value="${item.expiry_days}">
                        <input type="date" class="form-control form-control-sm item-calc" data-field="expiry_date" value="${item.expiry_date || ''}">
                    </td>
                    <td>
                        <strong class="item-line-total">₹${numberFormat(item.line_total || 0)}</strong><br>
                        <small class="text-muted item-taxable-total">Taxable ₹${numberFormat(item.taxable_amount || 0)}</small>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn">
                            <i class="mdi mdi-delete"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        $('#itemsBody').html(html);
        calculateTotals();
        refreshProductSelectOptions();
    }

    function updateItemRowAmounts(row, index) {
        if (!items[index] || row.length === 0) {
            return;
        }

        let item = items[index];
        row.find('.item-gst-amount').text('GST ₹' + numberFormat(item.gst_amount || 0));
        row.find('.item-line-total').text('₹' + numberFormat(item.line_total || 0));
        row.find('.item-taxable-total').text('Taxable ₹' + numberFormat(item.taxable_amount || 0));

        if ($(document.activeElement).data('field') !== 'expiry_date') {
            row.find('[data-field="expiry_date"]').val(item.expiry_date || '');
        }

        row.find('small.text-muted').filter(function () {
            return $(this).text().indexOf('Stock:') === 0;
        }).text('Stock: ' + numberFormat4(item.stock_qty || 0));
    }

    function updateItemFromRow(index) {
        let row = $('#itemsBody tr[data-index="' + index + '"]');

        row.find('.item-calc').each(function () {
            let field = $(this).data('field');
            let value = $(this).val();

            if (!field) {
                return;
            }

            if ($.inArray(field, ['expiry_date']) >= 0) {
                items[index][field] = value;
            } else {
                items[index][field] = parseFloat(value || 0);
            }

            // If user edits table Qty directly, make API conversion calculation valid.
            if (field === 'qty') {
                items[index].box_qty = 0;
                items[index].loose_piece_qty = parseFloat(value || 0);
                items[index].unit_conversion = parseFloat(items[index].unit_conversion || 1);
            }
        });

        if ($(document.activeElement).data('field') === 'expiry_days') {
            items[index].expiry_date = calculateExpiryDate(parseInt(items[index].expiry_days || 0));
        }

        calculateItem(items[index]);
    }

    function calculateItem(item) {
        let qty = parseFloat(item.qty || 0); // total pieces
        let ratePerPiece = parseFloat(item.purchase_price || 0); // product rate per piece
        let grossAmount = qty * ratePerPiece;

        let schemeType = parseInt(item.discount_type || 1); // 1 = %, 2 = fixed amount
        let schemeValue = parseFloat(item.discount_value || 0);

        let schemeAmount = schemeType === 1
            ? grossAmount * schemeValue / 100
            : schemeValue;

        if (schemeAmount > grossAmount) {
            schemeAmount = grossAmount;
        }

        let schemePerPiece = qty > 0 ? schemeAmount / qty : 0;
        let afterSchemePerPiece = ratePerPiece - schemePerPiece;

        if (afterSchemePerPiece < 0) {
            afterSchemePerPiece = 0;
        }

        let gstPercentage = parseFloat(item.gst_percentage || 0);
        let gstType = parseInt(item.gst_type || 2); // 1=inclusive, 2=exclusive

        let taxableAmount = 0;
        let gstAmount = 0;
        let lineTotal = 0;
        let taxablePerPiece = 0;
        let gstPerPiece = 0;
        let netPerPiece = 0;

        if (gstType === 1) {
            // Inclusive: after scheme price already includes GST.
            // Example: rate 105 inclusive 5% => taxable 100, GST 5, total 105.
            let inclusiveAmount = afterSchemePerPiece * qty;
            gstAmount = gstPercentage > 0 ? (inclusiveAmount * gstPercentage) / (100 + gstPercentage) : 0;
            taxableAmount = inclusiveAmount - gstAmount;
            lineTotal = inclusiveAmount;
        } else {
            // Exclusive: GST is added after scheme discount.
            taxableAmount = afterSchemePerPiece * qty;
            gstAmount = taxableAmount * gstPercentage / 100;
            lineTotal = taxableAmount + gstAmount;
        }

        taxablePerPiece = qty > 0 ? taxableAmount / qty : 0;
        gstPerPiece = qty > 0 ? gstAmount / qty : 0;
        netPerPiece = qty > 0 ? lineTotal / qty : 0;

        item.gst_type = gstType;
        item.discount_amount = round2(schemeAmount);
        item.scheme_per_piece = round4(schemePerPiece);
        item.gross_amount = round2(grossAmount);
        item.taxable_amount = round2(taxableAmount);
        item.taxable_per_piece = round4(taxablePerPiece);
        item.gst_amount = round2(gstAmount);
        item.gst_per_piece = round4(gstPerPiece);
        item.net_per_piece = round4(netPerPiece);
        item.net_amount = round2(taxableAmount);
        item.line_total = round2(lineTotal);
        item.stock_qty = round4(qty);

        item.wholesale_price = parseFloat(item.retail_price || 0);
        item.retail_scheme_discount_type = 1;
        item.retail_scheme_discount_value = 0;
        item.retail_scheme_discount_amount = 0;
        item.retail_scheme_price = parseFloat(item.retail_price || 0);
        item.wholesale_scheme_discount_type = 1;
        item.wholesale_scheme_discount_value = 0;
        item.wholesale_scheme_discount_amount = 0;
        item.wholesale_scheme_price = parseFloat(item.retail_price || 0);

        return item;
    }

    function updateRoundOffButtonState() {
        updateRoundOffSign();

        if (autoRoundOffEnabled) {
            $('#roundOffToggleBtn').removeClass('btn-outline-secondary').addClass('btn-success').text('Rounded');
        } else {
            $('#roundOffToggleBtn').removeClass('btn-success').addClass('btn-outline-secondary').text('Round');
            $('#roundOffHelpText').text('Use + amount to add and - amount to reduce. Click Round to nearest rupee.');
        }
    }

    function updateRoundOffSign() {
        let roundOff = parseFloat($('#round_off').val() || 0);
        let signText = '±';

        $('#roundOffSignAddon').removeClass('text-success text-danger');

        if (roundOff > 0) {
            signText = '+';
            $('#roundOffSignAddon').addClass('text-success');
        } else if (roundOff < 0) {
            signText = '-';
            $('#roundOffSignAddon').addClass('text-danger');
        }

        $('#roundOffSignAddon').text(signText);
    }

    function calculateTotals() {
        let subTotal = 0; // taxable amount after item scheme discount
        let taxAmount = 0;

        $.each(items, function (_, item) {
            calculateItem(item);
            subTotal += parseFloat(item.taxable_amount || 0);
            taxAmount += parseFloat(item.gst_amount || 0);
        });

        subTotal = round2(subTotal);
        taxAmount = round2(taxAmount);

        let discountType = parseInt($('#discount_type').val() || 1);
        let discountValue = parseFloat($('#discount_value').val() || 0);
        let paidAmount = parseFloat($('#paid_amount').val() || 0);

        let billDiscount = discountType === 1
            ? subTotal * discountValue / 100
            : discountValue;

        billDiscount = round2(billDiscount);

        if (billDiscount > subTotal) {
            billDiscount = subTotal;
        }

        let taxableAfterBillDiscount = round2(subTotal - billDiscount);
        if (taxableAfterBillDiscount < 0) {
            taxableAfterBillDiscount = 0;
        }

        let grandBeforeRoundOff = round2(taxableAfterBillDiscount + taxAmount);
        let roundOff = parseFloat($('#round_off').val() || 0);

        if (autoRoundOffEnabled) {
            let roundedTotal = Math.round(grandBeforeRoundOff);
            roundOff = round2(roundedTotal - grandBeforeRoundOff);
            if (Math.abs(roundOff) < 0.005) {
                roundOff = 0;
            }
            $('#round_off').val(roundOff.toFixed(2));
        }

        updateRoundOffSign();

        let grandTotal = round2(grandBeforeRoundOff + roundOff);

        if (grandTotal < 0) {
            grandTotal = 0;
        }

        if (paidAmount > grandTotal) {
            paidAmount = grandTotal;
            $('#paid_amount').val(paidAmount.toFixed(2));
        }

        let dueAmount = round2(grandTotal - paidAmount);

        $('#sub_total').val(subTotal.toFixed(2));
        $('#tax_amount').val(taxAmount.toFixed(2));
        $('#grand_total').val(grandTotal.toFixed(2));
        $('#due_amount').val(dueAmount.toFixed(2));
    }

    function loadPurchase(purchaseId) {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_purchase',
                purchase_id: purchaseId
            },
            success: function (response) {
                if (response.status === true) {
                    let purchase = response.data.purchase;

                    loadSuppliers(purchase.supplier_id);

                    $('#purchase_id').val(purchase.id);
                    $('#bill_no').val(purchase.bill_no);
                    $('#batch_no').val(purchase.batch_no || '');
                    $('#purchase_date').val(purchase.purchase_date);
                    $('#due_date').val(purchase.due_date || '');
                    $('#discount_type').val(purchase.discount_type);
                    $('#discount_value').val(parseFloat(purchase.discount_value || 0).toFixed(2));
                    $('#round_off').val(parseFloat(purchase.round_off || 0).toFixed(2));
                    autoRoundOffEnabled = false;
                    updateRoundOffButtonState();
                    $('#paid_amount').val(parseFloat(purchase.paid_amount || 0).toFixed(2));
                    purchasePaymentSplits = [];
                    syncPurchaseSplitsWithPaidAmount();
                    $('#notes').val(purchase.notes || '');

                    items = [];

                    $.each(response.data.items || [], function (_, item) {
                        items.push({
                            product_id: parseInt(item.product_id),
                            product_code: item.product_code || '',
                            product_name: item.product_name || '',
                            hsn_id: parseInt(item.hsn_id || 0),
                            hsn_code: item.hsn_code || '',
                            base_unit: item.base_unit || 'Piece',
                            box_label: item.box_label || 'Box',
                            pieces_per_box: parseFloat(item.pieces_per_box || 1),
                            expiry_days: parseInt(item.expiry_days || 0),
                            expiry_date: item.expiry_date || '',
                            unit_label: item.unit_label || 'Piece',
                            qty: parseFloat(item.qty || 0),
                            free_qty: parseFloat(item.free_qty || 0),
                            unit_conversion: parseFloat(item.unit_conversion || 1),
                            stock_qty: parseFloat(item.stock_qty || 0),
                            // purchase_items.purchase_price stores after-scheme rate.
                            // For edit calculation, restore original entered rate from gross_amount / qty.
                            purchase_price: (parseFloat(item.gross_amount || 0) > 0 && parseFloat(item.qty || 0) > 0)
                                ? round2(parseFloat(item.gross_amount || 0) / parseFloat(item.qty || 0))
                                : parseFloat(item.purchase_price || 0),
                            gross_amount: parseFloat(item.gross_amount || 0),
                            discount_type: parseInt(item.discount_type || 2),
                            discount_value: parseFloat(item.discount_value || 0),
                            discount_amount: parseFloat(item.discount_amount || 0),
                            taxable_amount: parseFloat(item.taxable_amount || 0),
                            gst_type: parseInt(item.gst_type || 2),
                            gst_percentage: parseFloat(item.gst_percentage || 0),
                            cgst_percentage: parseFloat(item.cgst_percentage || 0),
                            sgst_percentage: parseFloat(item.sgst_percentage || 0),
                            igst_percentage: parseFloat(item.igst_percentage || 0),
                            gst_amount: parseFloat(item.gst_amount || 0),
                            net_amount: parseFloat(item.net_amount || 0),
                            line_total: parseFloat(item.line_total || 0),
                            mrp: parseFloat(item.mrp || 0),
                            retail_price: parseFloat(item.retail_price || 0),
                            wholesale_price: parseFloat(item.wholesale_price || 0),
                            retail_scheme_discount_type: parseInt(item.retail_scheme_discount_type || 1),
                            retail_scheme_discount_value: parseFloat(item.retail_scheme_discount_value || 0),
                            retail_scheme_discount_amount: parseFloat(item.retail_scheme_discount_amount || 0),
                            retail_scheme_price: parseFloat(item.retail_scheme_price || item.retail_price || 0),
                            wholesale_scheme_discount_type: parseInt(item.wholesale_scheme_discount_type || 1),
                            wholesale_scheme_discount_value: parseFloat(item.wholesale_scheme_discount_value || 0),
                            wholesale_scheme_discount_amount: parseFloat(item.wholesale_scheme_discount_amount || 0),
                            wholesale_scheme_price: parseFloat(item.wholesale_scheme_price || item.wholesale_price || 0)
                        });
                    });

                    renderItems();
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

    function getGstPercentage() {
        let igst = parseFloat($('#pre_igst_percentage').val() || 0);
        let cgst = parseFloat($('#pre_cgst_percentage').val() || 0);
        let sgst = parseFloat($('#pre_sgst_percentage').val() || 0);
        return igst > 0 ? igst : (cgst + sgst);
    }

    function generateHeaderBatchNo() {
        let dateText = ($('#purchase_date').val() || '').replaceAll('-', '');
        if (dateText === '') {
            dateText = new Date().toISOString().slice(0, 10).replaceAll('-', '');
        }

        let billNo = String($('#bill_no').val() || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        if (billNo === '') {
            billNo = 'PUR';
        }

        return 'BAT-' + dateText + '-' + billNo;
    }

    function calculateExpiryDate(expireDays) {
        expireDays = parseInt(expireDays || 0);

        if (expireDays <= 0 || $('#purchase_date').val() === '') {
            return '';
        }

        let date = new Date($('#purchase_date').val());
        date.setDate(date.getDate() + expireDays);

        return date.toISOString().slice(0, 10);
    }

    function round2(value) {
        return Math.round((parseFloat(value || 0) + Number.EPSILON) * 100) / 100;
    }

    function round4(value) {
        return Math.round((parseFloat(value || 0) + Number.EPSILON) * 10000) / 10000;
    }

    function numberFormat(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function numberFormat4(value) {
        return parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 4,
            maximumFractionDigits: 4
        });
    }

    function warn(message, selector) {
        showToastSafe('warning', message);
        $(selector).focus();
        return false;
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

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function escapeAttr(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/'/g, '&#039;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
});
