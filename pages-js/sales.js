$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    function updatePosCurrentTime() {
        let now = new Date();
        let timeText = now.toLocaleTimeString('en-IN', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
        $('#posCurrentTime').text(timeText);
    }

    updatePosCurrentTime();
    setInterval(updatePosCurrentTime, 1000);

    $(document).on('click', '#posExitBtn', function (e) {
        if (salesItems && salesItems.length > 0) {
            if (!confirm('Items are added in current sale. Exit POS?')) {
                e.preventDefault();
            }
        }
    });



    let searchCustomerTimer = null;
    let searchProductTimer = null;
    let selectedCustomer = null;
    let selectedProduct = null;
    let selectedBatches = [];
    let salesItems = [];
    let salesPayments = [];
    let paymentModes = [];
    let editingProductId = null;
    let holdBillsModal = null;
    let customerModal = null;
    let productEntryModal = null;

    const DRAFT_KEY = 'universal_erp_sales_draft';
    const HOLD_KEY = 'universal_erp_hold_bills';

    if (document.getElementById('holdBillsModal')) {
        holdBillsModal = new bootstrap.Modal(document.getElementById('holdBillsModal'));
    }

    if (document.getElementById('customerModal')) {
        customerModal = new bootstrap.Modal(document.getElementById('customerModal'));
    }

    if (document.getElementById('productEntryModal')) {
        productEntryModal = new bootstrap.Modal(document.getElementById('productEntryModal'));
    }

    loadPaymentModes();
    loadCustomerZones();
    bindEvents();
    loadSalesDocumentPermissions();



    function loadSalesDocumentPermissions() {
        $.ajax({
            url: window.BASE_URL + 'api/sales.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_document_permissions'
            },
            success: function (response) {
                if (response.status === true) {
                    let config = getSalesPageConfig();
                    config.document_types = response.data.document_types || {};
                    config.allowed_document_types = response.data.allowed_document_types || [];
                    config.permissions = response.data.permissions || {};
                    config.can_view = response.data.can_view || false;
                    config.can_list = response.data.can_list || false;
                    config.can_add = response.data.can_add || false;
                    config.can_edit = response.data.can_edit || false;
                    config.can_quick_add_customer = response.data.can_quick_add_customer || false;
                    config.can_view_customers = response.data.can_view_customers || false;
                    config.can_hold_bill = response.data.can_hold_bill || false;
                    config.can_clear_draft = response.data.can_clear_draft || false;
                    window.SALES_PAGE_CONFIG = config;

                    renderDocumentTypeDropdown();
                    applySalesPagePermissionControls();
                    initSalesPageMode();
                } else {
                    $('#documentType').html('<option value="">No Permission</option>');
                    showAppToast('error', response.message || 'Unable to load document permissions.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#documentType').html('<option value="">Permission Error</option>');
                showAppToast('error', 'Server error while loading document permissions.');
            }
        });
    }

    function renderDocumentTypeDropdown(selectedType) {
        let config = getSalesPageConfig();
        let types = config.document_types || {};
        let allowed = (config.allowed_document_types || []).map(function (v) {
            return parseInt(v);
        });
        let mode = config.mode || 'new';
        let selected = parseInt(selectedType || config.target_type || 0);
        let html = '';

        /*
         * Split permission:
         * New document dropdown = Add permission only.
         * Convert/generate mode = show target only when SOURCE document generate permission exists.
         */
        if (mode === 'convert' && selected > 0 && canGenerateCurrentTargetDocument(selected) && $.inArray(selected, allowed) === -1) {
            allowed.push(selected);
        }

        $.each(allowed, function (_, typeId) {
            let info = types[typeId] || types[String(typeId)] || {};
            html += `<option value="${typeId}">${escapeHtml(info.label || ('Document ' + typeId))}</option>`;
        });

        if (html === '') {
            html = '<option value="">No document type permission</option>';
        }

        $('#documentType').html(html);

        if (selected > 0) {
            $('#documentType').val(String(selected));
        }

        if (!$('#documentType').val() && allowed.length > 0) {
            $('#documentType').val(String(allowed[0]));
        }

        syncSwitchControls();
        applySalesPagePermissionControls();
    }

    function getSalesPageConfig() {
        return window.SALES_PAGE_CONFIG || {
            mode: 'new',
            id: 0,
            source_id: 0,
            source_type: 0,
            target_type: 0,
            document_types: {},
            permissions: {}
        };
    }

    function getGlobalPermission(key) {
        let config = getSalesPageConfig();
        let value = config[key];

        return value === true || value === 1 || value === '1';
    }

    function hasAnyDocPermission(action) {
        for (let typeId = 1; typeId <= 5; typeId++) {
            if (docPermission(typeId, action)) {
                return true;
            }
        }

        return false;
    }

    function hasAnySalesCreateOrGeneratePermission() {
        return hasAnyDocPermission('add')
            || hasAnyDocPermission('generate_invoice')
            || hasAnyDocPermission('generate_proforma_bill')
            || hasAnyDocPermission('generate_sales_bill');
    }

    function generateActionKeysForTarget(typeId) {
        typeId = parseInt(typeId || 0);

        if (typeId === 2) {
            return ['generate_proforma_bill'];
        }

        if (typeId === 3) {
            return ['generate_sales_bill'];
        }

        if (typeId === 5) {
            return ['generate_invoice'];
        }

        return [];
    }

    function allowedGenerateTargetsForSource(sourceType) {
        sourceType = parseInt(sourceType || 0);

        if (sourceType === 1) {
            return [2, 3, 5];
        }

        if (sourceType === 2) {
            return [3, 5];
        }

        if (sourceType === 3) {
            return [5];
        }

        return [];
    }

    function canGenerateToTarget(sourceType, targetType, targetGenerateActions) {
        /*
         * Final source-row based generate rule.
         */
        sourceType = parseInt(sourceType || 0);
        targetType = parseInt(targetType || 0);
        targetGenerateActions = targetGenerateActions || generateActionKeysForTarget(targetType);

        if (sourceType <= 0 || targetType <= 0 || sourceType === targetType) {
            return false;
        }

        if ($.inArray(targetType, allowedGenerateTargetsForSource(sourceType)) === -1) {
            return false;
        }

        for (let i = 0; i < targetGenerateActions.length; i++) {
            if (docPermission(sourceType, targetGenerateActions[i])) {
                return true;
            }
        }

        return false;
    }

    function canGenerateCurrentTargetDocument(typeId) {
        let config = getSalesPageConfig();
        let sourceType = parseInt(config.source_type || 0);
        typeId = parseInt(typeId || 0);

        return canGenerateToTarget(sourceType, typeId, generateActionKeysForTarget(typeId));
    }

    function canSaveCurrentDocument() {
        let config = getSalesPageConfig();
        let mode = config.mode || 'new';
        let docType = parseInt($('#documentType').val() || config.target_type || 0);

        if (docType <= 0) {
            return false;
        }

        if (mode === 'edit') {
            return docPermission(docType, 'edit');
        }

        if (mode === 'convert') {
            return canGenerateCurrentTargetDocument(docType);
        }

        return docPermission(docType, 'add');
    }

    function applySalesPagePermissionControls() {
        let config = getSalesPageConfig();
        let mode = config.mode || 'new';
        let docType = parseInt($('#documentType').val() || config.target_type || 0);
        let hasCreate = docType > 0 && docPermission(docType, 'add');
        let hasAnyAccess = hasAnySalesCreateOrGeneratePermission();
        let hasList = hasAnyDocPermission('list') || hasAnyDocPermission('view') || getGlobalPermission('can_list') || getGlobalPermission('can_view');
        let canQuickAddCustomer = getGlobalPermission('can_quick_add_customer');

        if (hasList) {
            $('#salesListNavBtn').removeClass('d-none');
        } else {
            $('#salesListNavBtn').addClass('d-none');
        }

        if (hasCreate) {
            $('#holdBillBtn, #holdBillsListBtn, #clearDraftBtn').removeClass('d-none');
        } else {
            $('#holdBillBtn, #holdBillsListBtn, #clearDraftBtn').addClass('d-none');
        }

        if (canQuickAddCustomer) {
            $('#addCustomerBtn, #saveCustomerBtn').removeClass('d-none');
        } else {
            $('#addCustomerBtn, #saveCustomerBtn').addClass('d-none');
        }

        if (canSaveCurrentDocument()) {
            $('#saveSaleBtn').removeClass('d-none').prop('disabled', false);
            $('#salesPermissionAlert').addClass('d-none');
        } else {
            $('#saveSaleBtn').addClass('d-none').prop('disabled', true);

            if (!hasAnyAccess && mode !== 'edit') {
                $('#salesPermissionAlert').removeClass('d-none');
            } else {
                $('#salesPermissionAlert').addClass('d-none');
            }
        }

        if (docType > 0 && (getGlobalPermission('can_receive_payment') || docPermission(docType, 'receive_payment'))) {
            $('#addPaymentBtn').removeClass('d-none');
        } else {
            $('#addPaymentBtn').addClass('d-none');
        }
    }


    function documentLabel(typeId) {
        let config = getSalesPageConfig();
        let info = config.document_types && config.document_types[typeId] ? config.document_types[typeId] : null;
        return info && info.label ? info.label : 'Document';
    }

    function docPermission(typeId, action) {
        let config = getSalesPageConfig();
        return !!(config.permissions && config.permissions[typeId] && config.permissions[typeId][action]);
    }


    function initSalesPageMode() {
        let config = getSalesPageConfig();

        if (config.mode === 'convert' && parseInt(config.source_id || 0) > 0) {
            clearDraftStorage();
            loadSaleFromApi(parseInt(config.source_id), 'convert');
            return;
        }

        if ((config.mode === 'edit' || parseInt(config.id || 0) > 0) && parseInt(config.id || 0) > 0) {
            clearDraftStorage();
            loadSaleFromApi(parseInt(config.id), 'edit');
            return;
        }

        loadDraftFromStorage();
        renderSalesActionButtons();
    }

    function loadSaleFromApi(id, mode) {
        $.ajax({
            url: window.BASE_URL + 'api/sales.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_sale',
                id: id
            },
            success: function (response) {
                if (response.status === true) {
                    applySaleResponseToScreen(response.data || {}, mode);
                } else {
                    handleApiError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error while loading sale.');
            }
        });
    }

    function applySaleResponseToScreen(data, mode) {
        let sale = data.sale || {};
        let config = getSalesPageConfig();
        let items = Array.isArray(data.items) ? data.items : [];
        let payments = Array.isArray(data.payments) ? data.payments : [];

        let targetDocumentType = mode === 'convert'
            ? parseInt(config.target_type || sale.document_type || 1)
            : parseInt(sale.document_type || 1);

        let payload = {
            id: mode === 'convert' ? 0 : parseInt(sale.id || 0),
            customer_id: parseInt(sale.customer_id || 0),
            customer_display: sale.customer_name || '',
            customer_info: ((sale.customer_mobile || '') + ' ' + (sale.gst_number || '')).trim(),
            customer_zone_filter: sale.zone_id || '',
            customer_object: {
                id: parseInt(sale.customer_id || 0),
                customer_name: sale.customer_name || '',
                mobile: sale.customer_mobile || '',
                gst_number: sale.gst_number || '',
                zone_id: sale.zone_id || ''
            },
            document_type: targetDocumentType,
            invoice_type: parseInt(sale.invoice_type || 1),
            sales_date: currentDate(),
            due_date: sale.due_date || '',
            notes: mode === 'convert'
                ? ('Generated from ' + documentLabel(parseInt(sale.document_type || 0)) + ' ' + (sale.sales_no || ''))
                : (sale.notes || ''),
            delivery_address: sale.delivery_address || sale.address || '',
            shipping_charges: sale.shipping_charges || 0,
            discount_type: sale.discount_type || 1,
            discount_value: sale.discount_value || 0,
            round_off: sale.round_off || 0,
            items: items.map(function (item) {
                return {
                    group_id: item.group_id || ('LOAD_' + item.product_id),
                    product_id: parseInt(item.product_id || 0),
                    purchase_id: parseInt(item.purchase_id || 0),
                    purchase_item_id: parseInt(item.purchase_item_id || 0),
                    purchase_batch_no: item.purchase_batch_no || '',
                    purchase_bill_no: item.purchase_bill_no || '',
                    product_code: item.product_code || '',
                    product_name: item.product_name || '',
                    unit_qty: parseFloat(item.unit_qty || 0),
                    qty_per_unit: parseFloat(item.qty_per_unit || 1),
                    loose_qty: parseFloat(item.loose_qty || 0),
                    qty: parseFloat(item.qty || 0),
                    entered_total_qty: parseFloat(item.qty || 0),
                    price_type: parseInt(item.price_type || 1),
                    markup_type: parseInt(item.markup_type || 1),
                    markup_value: parseFloat(item.markup_value || 0),
                    selling_rate: parseFloat(item.selling_rate || 0),
                    discount_type: parseInt(item.discount_type || 1),
                    discount_value: parseFloat(item.discount_value || 0),
                    gst_percentage: parseFloat(item.gst_percentage || 0)
                };
            }),
            payments: mode === 'convert' ? [] : payments.map(function (payment) {
                return {
                    payment_mode_id: parseInt(payment.payment_mode_id || 0),
                    payment_amount: parseFloat(payment.payment_amount || 0),
                    reference_no: payment.reference_no || '',
                    payment_date: payment.payment_date || currentDate(),
                    checked: parseFloat(payment.payment_amount || 0) > 0 ? 1 : 0
                };
            })
        };

        renderDocumentTypeDropdown(targetDocumentType);
        applyPayloadToScreen(payload, false);

        if (mode === 'convert') {
            $('#saleId').val('0');
            $('#documentType').val(String(targetDocumentType)).prop('disabled', true);
            $('#documentModeText').text('Generate mode: ' + documentLabel(targetDocumentType));
            $('#saveSaleBtn').html('<i class="mdi mdi-receipt-text-check-outline me-1"></i> Generate ' + documentLabel(targetDocumentType));
        } else {
            $('#documentType').prop('disabled', true);
            $('#documentModeText').text('Edit mode: document type locked');
            $('#saveSaleBtn').html('<i class="mdi mdi-content-save me-1"></i> Update');
        }

        renderSalesActionButtons(sale);
        calculateSummary();
    }

    function renderSalesActionButtons(currentSale) {
        let saleId = parseInt($('#saleId').val() || 0);
        let docType = parseInt($('#documentType').val() || 0);
        let config = getSalesPageConfig();
        let mode = config.mode || 'new';

        currentSale = currentSale || {};

        /*
         * In convert mode, save button already generates the selected target document.
         * So hide any extra convert/generate button pointing to the same target type.
         * Example:
         * mode=convert&source_type=1&target_type=2
         * Save button = Generate Proforma Bill
         * Hide extra Generate Proforma Bill button.
         */
        let currentTargetType = parseInt(config.target_type || 0);
        let isConvertMode = (mode === 'convert');

        /*
         * In convert mode, the form is a new unsaved target document,
         * so #saleId is intentionally 0.
         * For bottom buttons like Print / Generate Invoice / Convert,
         * use the original source document id and source document type.
         */
        let actionSaleId = saleId;
        let actionDocType = docType;

        if (mode === 'convert') {
            actionSaleId = parseInt(config.source_id || currentSale.id || 0);
            actionDocType = parseInt(config.source_type || currentSale.document_type || docType || 0);
        }

        $('.sales-convert-btn, #printSaleBtn').addClass('d-none');
        $('#saveSaleBtn').addClass('d-none').prop('disabled', true);

        if (mode === 'convert') {
            $('#saveSaleBtn').html(
                '<i class="mdi mdi-receipt-text-check-outline me-1"></i> Generate ' +
                documentLabel(parseInt(config.target_type || docType))
            );
        } else if (saleId <= 0) {
            $('#saveSaleBtn').html('<i class="mdi mdi-content-save me-1"></i> Save');
            applySalesPagePermissionControls();
            return;
        } else {
            $('#saveSaleBtn').html('<i class="mdi mdi-content-save me-1"></i> Update');
        }

        applySalesPagePermissionControls();

        if (actionSaleId <= 0 || actionDocType <= 0) {
            return;
        }

        /*
         * Same-row generate flow:
         * conversion_status = 1 only means this row was generated from another type.
         * It should NOT hide next generate buttons.
         * Hide buttons only if converted_to_sale_id points to another different row.
         */
        let currentRowId = parseInt(currentSale.id || actionSaleId || 0);
        let convertedToSaleId = parseInt(currentSale.converted_to_sale_id || 0);
        let converted = convertedToSaleId > 0 && convertedToSaleId !== currentRowId;

        if (converted && mode !== 'convert') {
            $('#documentModeText').text('Already generated to another document');
        }

        if (docPermission(actionDocType, 'print')) {
            $('#printSaleBtn')
                .removeClass('d-none')
                .attr('data-print-id', actionSaleId);
        }

        if (converted) {
            return;
        }

        let targetButtons = [
            {
                target_type: 2,
                selector: '#convertProformaBtn',
                actions: ['generate_proforma_bill']
            },
            {
                target_type: 3,
                selector: '#convertSalesBillBtn',
                actions: ['generate_sales_bill']
            },
            {
                target_type: 5,
                selector: '#generateInvoiceBtn',
                actions: ['generate_invoice']
            }
        ];

        $.each(targetButtons, function (_, btn) {
            if (btn.target_type === actionDocType) {
                return;
            }

            if (isConvertMode && currentTargetType === btn.target_type) {
                return;
            }

            if (canGenerateToTarget(actionDocType, btn.target_type, btn.actions)) {
                $(btn.selector)
                    .removeClass('d-none')
                    .attr('data-source-id', actionSaleId)
                    .attr('data-source-type', actionDocType);
            }
        });
    }

    function bindEvents() {
        $('#customerSearch').on('focus click', function () {
            searchCustomers();
        });

        $('#customerSearch').on('keyup', function () {
            clearTimeout(searchCustomerTimer);
            searchCustomerTimer = setTimeout(searchCustomers, 300);
        });

        $('#customerZoneFilter').on('change', function () {
            $('#customerId').val('');
            selectedCustomer = null;
            $('#customerSearch').val('');
            $('#customerInfo').text('');
            searchCustomers();
            saveDraftToStorage();
        });

        $('#productSearch').on('focus click', function () {
            searchProducts();
        });

        $('#productSearch').on('keyup', function () {
            clearTimeout(searchProductTimer);
            searchProductTimer = setTimeout(searchProducts, 300);
        });

        $('#unitQty, #qtyPerUnit, #looseQty').on('input', calculateTotalQty);

        $('#invoiceTypeSwitch').on('change', function () {
            $('#invoiceType').val($(this).is(':checked') ? '1' : '2');
            $('#invoiceTypeLabel').text($(this).is(':checked') ? 'GST Invoice' : 'Non-GST Invoice');

            if (!$(this).is(':checked')) {
                $('#gstPercentage').val('0');
            } else {
                applyPriceType();
            }

            renderSalesItems();
            calculateSummary();
            saveDraftToStorage();
        });

        $('#priceTypeSwitch').on('change', function () {
            $('#priceType').val($(this).is(':checked') ? '2' : '1');
            $('#priceTypeLabel').text($(this).is(':checked') ? 'Wholesale' : 'Retail');

            $('#selectedBatchDetailsBody .selected-batch-detail-row').each(function () { updateSelectedBatchRowPrice($(this)); });
            applyPriceType();
            renderSalesItems();
            calculateSummary();
            saveDraftToStorage();
        });

        $('#markupType, #markupValue').on('change keyup', applyPriceType);
        $('#headerDiscountType, #headerDiscountValue, #roundOff').on('change keyup', function () {
            renderSalesItems();
            calculateSummary();
            saveDraftToStorage();
        });

        $('#addItemBtn').on('click', addOrUpdateSalesItem);
        $('#cancelEditItemBtn').on('click', cancelItemEdit);
        $('#addPaymentBtn').on('click', function () {
            let docType = parseInt($('#documentType').val() || 0);

            if (docType > 0 && !(getGlobalPermission('can_receive_payment') || docPermission(docType, 'receive_payment'))) {
                showAppToast('error', 'You do not have permission to add payment for this document.');
                return;
            }

            addPaymentRow();
        });
        $('#saveSaleBtn').on('click', saveSale);

        $(document).on('click', '.sales-convert-btn', function () {
            let config = getSalesPageConfig();
            let mode = config.mode || 'new';

            let saleId = parseInt($(this).attr('data-source-id') || $('#saleId').val() || 0);
            let sourceType = parseInt($(this).attr('data-source-type') || $('#documentType').val() || 0);
            let targetType = parseInt($(this).data('target-type') || 0);

            /*
             * When current page is opened like:
             * sales.php?mode=convert&source_id=6&source_type=2&target_type=3
             * #saleId is 0 because the new target document is not saved yet.
             * Use the original source id/type for conversion buttons.
             */
            if (mode === 'convert') {
                saleId = parseInt(config.source_id || saleId || 0);
                sourceType = parseInt(config.source_type || sourceType || 0);
            }

            if (saleId <= 0) {
                showAppToast('warning', 'Please save the document before generate.');
                return;
            }

            /*
             * Correct behavior:
             * On edit page, Generate must save current changes and generate in one click.
             * No separate convert page needed.
             */
            generateFromCurrentEdit(saleId, sourceType, targetType);
        });

        $('#printSaleBtn').on('click', function () {
            let config = getSalesPageConfig();
            let mode = config.mode || 'new';

            let saleId = parseInt($(this).attr('data-print-id') || $('#saleId').val() || 0);

            /*
             * In convert mode print the original source document.
             * The converted target document can be printed only after it is generated/saved.
             */
            if (mode === 'convert') {
                saleId = parseInt(config.source_id || saleId || 0);
            }

            if (saleId <= 0) {
                showAppToast('warning', 'Please save before print.');
                return;
            }

            window.open(window.BASE_URL + 'pages/sales-print.php?id=' + saleId + '&print=1', '_blank');
        });

        $('#addCustomerBtn').on('click', function () {
            if (!getGlobalPermission('can_quick_add_customer')) {
                showAppToast('error', 'You do not have permission to add customer.');
                return;
            }

            resetCustomerModalForm();
            $('#customerModalTitle').text('Add Customer');
            loadCustomerZones();
            if (customerModal) {
                customerModal.show();
            }
        });

        $('#customerForm').on('submit', saveCustomerFromSales);

        $('#shippingCharges, #deliveryAddress').on('change keyup', function () {
            calculateSummary();
            saveDraftToStorage();
        });

        $('#holdBillBtn').on('click', holdCurrentBill);
        $('#holdBillsListBtn').on('click', openHoldBillsModal);

        $('#clearDraftBtn').on('click', function () {
            if (!confirm('Are you sure you want to clear this draft?')) {
                return;
            }
            clearCurrentScreen();
            clearDraftStorage();
            showAppToast('success', 'Draft cleared.');
        });

        $(document).on('click', '.customer-suggestion-item', function () {
            let customer = $(this).data('customer');
            selectedCustomer = customer;
            $('#customerId').val(customer.id);
            if (customer.zone_id) {
                $('#customerZoneFilter').val(customer.zone_id);
            }
            $('#customerSearch').val(customer.customer_name);
            $('#customerInfo').text((customer.mobile || '') + ' ' + (customer.gst_number || ''));
            $('#deliveryAddress').val(formatCustomerAddress(customer));
            $('#customerSuggestions').addClass('d-none').html('');
            saveDraftToStorage();
        });

        $(document).on('click', '.product-suggestion-item', function () {
            selectedProduct = $(this).data('product');
            selectedBatches = [];
            editingProductId = null;
            $('#productSearch').val((selectedProduct.product_code || '') + ' - ' + (selectedProduct.product_name || ''));
            $('#productSuggestions').addClass('d-none').html('');
            $('#productEntryModalTitle').text('Add Product');
            $('#productEntryModalSubTitle').text((selectedProduct.product_code || '') + ' - ' + (selectedProduct.product_name || ''));
            loadProductBatches(selectedProduct.id, function () {
                if (productEntryModal) {
                    productEntryModal.show();
                }
            });
        });

        
        $(document).on('input', '.batch-unit-qty, .batch-loose-qty', function () {
            calculateSelectedBatchRow($(this).closest('.selected-batch-detail-row'));
        });

        $(document).on('change', '.batch-price-type', function () {
            let row = $(this).closest('.selected-batch-detail-row');
            updateSelectedBatchRowPrice(row);
            let markupType = parseInt(row.find('.batch-markup-type').val() || 1);
            let markupValue = parseFloat(row.find('.batch-markup-value').val() || 0);
            row.find('.batch-markup-label').text((markupType === 2 ? '₹ ' : '% ') + markupValue.toFixed(2));
        });

$(document).on('click', '.remove-item-btn', function () {
            let productId = parseInt($(this).data('product-id'));
            salesItems = salesItems.filter(item => parseInt(item.product_id) !== productId);
            renderSalesItems();
            calculateSummary();
            saveDraftToStorage();
            showAppToast('success', 'Product removed.');
        });

        $(document).on('click', '.edit-item-btn', function () {
            let productId = parseInt($(this).data('product-id'));
            editProductItem(productId);
        });

        $(document).on('click', '.remove-payment-btn', function () {
            let index = parseInt($(this).data('index'));
            salesPayments.splice(index, 1);
            renderPayments();
            calculateSummary();
            saveDraftToStorage();
            showAppToast('success', 'Payment row removed.');
        });

        $(document).on('change keyup', '.payment-input', function () {
            if ($(this).hasClass('payment-check') && !$(this).is(':checked')) {
                let row = $(this).closest('.payment-row');
                row.find('.payment-amount').val('0.00');
                row.find('.payment-reference').val('');
            }

            if ($(this).hasClass('payment-amount') && parseFloat($(this).val() || 0) > 0) {
                $(this).closest('.payment-row').find('.payment-check').prop('checked', true);
            }

            syncPaymentsFromDom();
            calculateSummary();
            saveDraftToStorage();
        });

        $('#dueDate').on('change', function () {
            syncPaymentsFromDom();
            saveDraftToStorage();
        });

        $(document).on('click', '.load-hold-bill-btn', function () {
            let holdId = $(this).data('id');
            loadHoldBill(holdId);
        });

        $(document).on('click', '.delete-hold-bill-btn', function () {
            let holdId = $(this).data('id');
            deleteHoldBill(holdId);
        });

        syncSwitchControls();

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#customerSearch, #customerSuggestions').length) {
                $('#customerSuggestions').addClass('d-none');
            }
            if (!$(e.target).closest('#productSearch, #productSuggestions').length) {
                $('#productSuggestions').addClass('d-none');
            }
        });
    }


    function syncSwitchControls() {
        let invoiceType = parseInt($('#invoiceType').val() || 1);
        $('#invoiceTypeSwitch').prop('checked', invoiceType === 1);
        $('#invoiceTypeLabel').text(invoiceType === 1 ? 'GST Invoice' : 'Non-GST Invoice');

        let priceType = parseInt($('#priceType').val() || 1);
        if (priceType !== 2) {
            priceType = 1;
            $('#priceType').val('1');
        }
        $('#priceTypeSwitch').prop('checked', priceType === 2);
        $('#priceTypeLabel').text(priceType === 2 ? 'Wholesale' : 'Retail');
    }

    function searchCustomers() {
        let search = $.trim($('#customerSearch').val());

        $.ajax({
            url: window.BASE_URL + 'api/sales.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'search_customers',
                q: search,
                zone_id: $('#customerZoneFilter').val() || ''
            },
            success: function (response) {
                if (response.status === true) {
                    renderCustomerSuggestions(response.data.rows || []);
                } else {
                    handleApiError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error while searching customers.');
            }
        });
    }

    function renderCustomerSuggestions(customers) {
        if (!customers || customers.length === 0) {
            $('#customerSuggestions').html('<div class="list-group-item text-muted">No customers found.</div>').removeClass('d-none');
            return;
        }

        let html = '';
        $.each(customers, function (_, customer) {
            html += `
                <button type="button" class="list-group-item list-group-item-action customer-suggestion-item">
                    <strong>${escapeHtml(customer.customer_name || '')}</strong><br>
                    <small>${escapeHtml(customer.mobile || '')} ${escapeHtml(customer.gst_number || '')}</small>
                </button>
            `;
        });

        $('#customerSuggestions').html(html).removeClass('d-none');

        $('.customer-suggestion-item').each(function (index) {
            $(this).data('customer', customers[index]);
        });
    }

    function searchProducts() {
        let search = $.trim($('#productSearch').val());

        $.ajax({
            url: window.BASE_URL + 'api/sales.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'search_products',
                q: search
            },
            success: function (response) {
                if (response.status === true) {
                    renderProductSuggestions(response.data.rows || []);
                } else {
                    handleApiError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error while searching products.');
            }
        });
    }

    function renderProductSuggestions(products) {
        if (!products || products.length === 0) {
            $('#productSuggestions').html('<div class="list-group-item text-muted">No products found.</div>').removeClass('d-none');
            return;
        }

        let html = '';
        $.each(products, function (_, product) {
            html += `
                <button type="button" class="list-group-item list-group-item-action product-suggestion-item">
                    <strong>${escapeHtml(product.product_name || '')}</strong>
                    <span class="badge bg-light text-dark">${escapeHtml(product.product_code || '')}</span><br>
                    <small>Available: ${parseFloat(product.available_qty || 0).toFixed(4)}</small>
                </button>
            `;
        });

        $('#productSuggestions').html(html).removeClass('d-none');

        $('.product-suggestion-item').each(function (index) {
            $(this).data('product', products[index]);
        });
    }

    function loadProductBatches(productId, callback) {
        $('#selectedBatchDetailsBody').html('<tr><td colspan="12" class="text-center text-muted">Loading batches...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/sales.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_product_batches',
                product_id: productId
            },
            success: function (response) {
                if (response.status === true) {
                    renderProductBatches(response.data.rows || []);
                    if (typeof callback === 'function') {
                        callback(response.data.rows || []);
                    }
                } else {
                    handleApiError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#selectedBatchDetailsBody').html('<tr><td colspan="12" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderProductBatches(batches) {
        if (!batches || batches.length === 0) {
            selectedBatches = [];
            $('#selectedBatchDetailsBody').html('<tr><td colspan="12" class="text-center text-muted">No available batch stock.</td></tr>');
            $('#selectedBatchBox').addClass('d-none').html('');
            return;
        }

        selectedBatches = batches;
        renderSelectedBatchesInfo();
        renderSelectedBatchDetails();
    }

    function renderSelectedBatchesInfo() {
        if (!selectedBatches || selectedBatches.length === 0) {
            $('#selectedBatchBox').addClass('d-none').html('');
            return;
        }

        let totalAvailable = selectedBatches.reduce((sum, b) => sum + parseFloat(b.available_qty || 0), 0);
        $('#selectedBatchBox')
            .removeClass('d-none')
            .html(`<strong>Available Batches:</strong> ${selectedBatches.length} | <strong>Total Available:</strong> ${totalAvailable.toFixed(4)}`);
    }

    function calculateTotalQty() {
        let unitQty = parseFloat($('#unitQty').val() || 0);
        let qtyPerUnit = parseFloat($('#qtyPerUnit').val() || 0);
        let looseQty = parseFloat($('#looseQty').val() || 0);
        let totalQty = (unitQty * qtyPerUnit) + looseQty;

        $('#totalQty').val(totalQty.toFixed(4));
    }

    function applyPriceType() {
        if (!selectedProduct) {
            return;
        }

        let batch = selectedBatches.length > 0 ? selectedBatches[0] : null;
        if (!batch) {
            return;
        }

        syncSwitchControls();

        let conversion = parseFloat(batch.unit_conversion || batch.secondary_unit_value || selectedProduct.secondary_unit_value || 1);
        if (conversion <= 0) {
            conversion = 1;
        }

        $('#qtyPerUnit').val(conversion.toFixed(4));
        calculateTotalQty();

        /*
         * Base Rate = purchase_items.purchase_price after scheme discount.
         * Price Type switch changes markup source:
         * Retail    => retail markup / retail price based markup
         * Wholesale => wholesale markup / wholesale price based markup
         */
        let baseRate = parseFloat(batch.purchase_price || 0);
        if (baseRate <= 0) {
            baseRate = parseFloat(batch.retail_price || selectedProduct.retail_price || selectedProduct.final_mrp || 0);
        }

        let priceType = parseInt($('#priceType').val() || 1);
        let markupType = 1;
        let markupValue = 0;
        let targetSaleRate = 0;

        if (priceType === 2) {
            markupType = parseInt(batch.wholesale_markup_type || selectedProduct.wholesale_markup_type || 1);
            markupValue = parseFloat(batch.wholesale_markup_value || selectedProduct.wholesale_markup_value || 0);
            targetSaleRate = parseFloat(batch.wholesale_price || selectedProduct.wholesale_price || 0);

            if (!(markupValue > 0) && targetSaleRate > baseRate) {
                markupType = 1;
                markupValue = ((targetSaleRate - baseRate) / baseRate) * 100;
            }
        } else {
            markupType = parseInt(batch.retail_markup_type || selectedProduct.retail_markup_type || 1);
            markupValue = parseFloat(batch.retail_markup_value || selectedProduct.retail_markup_value || 0);
            targetSaleRate = parseFloat(batch.retail_price || selectedProduct.retail_price || 0);

            if (!(markupValue > 0) && targetSaleRate > baseRate) {
                markupType = 1;
                markupValue = ((targetSaleRate - baseRate) / baseRate) * 100;
            }
        }

        $('#markupType').val(markupType === 2 ? '2' : '1');
        $('#markupValue').val(markupValue.toFixed(2));

        let markupAmount = markupType === 2
            ? markupValue
            : (baseRate * markupValue / 100);

        let sellingRate = baseRate + markupAmount;
        $('#sellingRate').val(sellingRate.toFixed(2));

        let gstPercentage = parseFloat(batch.igst_percentage || 0) > 0
            ? parseFloat(batch.igst_percentage || 0)
            : parseFloat(batch.cgst_percentage || 0) + parseFloat(batch.sgst_percentage || 0);

        $('#gstPercentage').val(parseInt($('#invoiceType').val()) === 1 ? gstPercentage.toFixed(2) : '0.00');
    }

    
    
    function formatInput2(value, blankZero) {
        let number = parseFloat(value || 0);
        if (blankZero && Math.abs(number) < 0.000001) {
            return '';
        }
        return number.toFixed(2);
    }

function getBatchKey(batch) {
        return parseInt(batch.purchase_item_id || 0);
    }

    function getBatchDefaultPriceInfo(batch) {
        let baseRate = parseFloat(batch.purchase_price || 0);
        if (baseRate <= 0) {
            baseRate = parseFloat(batch.retail_price || selectedProduct?.retail_price || selectedProduct?.final_mrp || 0);
        }

        let priceType = parseInt(batch._price_type || 1);
        let markupType = 1;
        let markupValue = 0;
        let targetSaleRate = 0;

        if (priceType === 2) {
            markupType = parseInt(batch.wholesale_markup_type || selectedProduct?.wholesale_markup_type || 1);
            markupValue = parseFloat(batch.wholesale_markup_value || selectedProduct?.wholesale_markup_value || 0);
            targetSaleRate = parseFloat(batch.wholesale_price || selectedProduct?.wholesale_price || 0);

            if (!(markupValue > 0) && targetSaleRate > baseRate) {
                markupType = 1;
                markupValue = ((targetSaleRate - baseRate) / baseRate) * 100;
            }
        } else {
            markupType = parseInt(batch.retail_markup_type || selectedProduct?.retail_markup_type || 1);
            markupValue = parseFloat(batch.retail_markup_value || selectedProduct?.retail_markup_value || 0);
            targetSaleRate = parseFloat(batch.retail_price || selectedProduct?.retail_price || 0);

            if (!(markupValue > 0) && targetSaleRate > baseRate) {
                markupType = 1;
                markupValue = ((targetSaleRate - baseRate) / baseRate) * 100;
            }
        }

        let markupAmount = markupType === 2 ? markupValue : (baseRate * markupValue / 100);
        let sellingRate = baseRate + markupAmount;

        let gstPercentage = parseFloat(batch.igst_percentage || 0) > 0
            ? parseFloat(batch.igst_percentage || 0)
            : parseFloat(batch.cgst_percentage || 0) + parseFloat(batch.sgst_percentage || 0);

        return {
            base_rate: baseRate,
            markup_type: markupType,
            markup_value: markupValue,
            selling_rate: sellingRate,
            gst_percentage: parseInt($('#invoiceType').val()) === 1 ? gstPercentage : 0
        };
    }

    function calculateSelectedBatchRow(row) {
        let unitQty = parseFloat(row.find('.batch-unit-qty').val() || 0);
        let qtyPerUnit = parseFloat(row.find('.batch-qty-per-unit').val() || 0);
        let looseQty = parseFloat(row.find('.batch-loose-qty').val() || 0);
        let totalQty = (unitQty * qtyPerUnit) + looseQty;
        row.find('.batch-total-qty').val(totalQty > 0 ? totalQty.toFixed(2) : '');
    }

    function updateSelectedBatchRowPrice(row) {
        let purchaseItemId = parseInt(row.data('purchase-item-id') || 0);
        let batch = selectedBatches.find(b => parseInt(b.purchase_item_id) === purchaseItemId);
        if (!batch) {
            return;
        }

        batch._price_type = parseInt(row.find('.batch-price-type').val() || 1);
        let info = getBatchDefaultPriceInfo(batch);

        row.find('.batch-markup-type').val(info.markup_type);
        row.find('.batch-markup-value').val(info.markup_value.toFixed(2));
        row.find('.batch-selling-rate').val(info.selling_rate.toFixed(2));

        if (parseInt($('#invoiceType').val()) === 1) {
            row.find('.batch-gst-percentage').val(info.gst_percentage.toFixed(2));
        } else {
            row.find('.batch-gst-percentage').val('0.00');
        }
    }

    function renderSelectedBatchDetails(existingRows) {
        existingRows = existingRows || [];

        if (!selectedBatches || selectedBatches.length === 0) {
            $('#selectedBatchDetailsBody').html('<tr><td colspan="12" class="text-center text-muted">Search and select product to load batch-wise inputs.</td></tr>');
            return;
        }

        let html = '';
        $.each(selectedBatches, function (_, batch) {
            let old = existingRows.find(r => parseInt(r.purchase_item_id) === parseInt(batch.purchase_item_id)) || {};
            let conversion = parseFloat(batch.unit_conversion || batch.secondary_unit_value || selectedProduct?.secondary_unit_value || old.qty_per_unit || 1);
            if (conversion <= 0) conversion = 1;

            batch._price_type = parseInt(old.price_type || batch._price_type || 1);
            let info = getBatchDefaultPriceInfo(batch);

            let unitQty = parseFloat(old.unit_qty || 0);
            let looseQty = parseFloat(old.loose_qty || 0);
            let totalQty = parseFloat(old.qty || 0);

            if (totalQty > 0 && unitQty <= 0 && looseQty <= 0) {
                unitQty = Math.floor(totalQty / conversion);
                looseQty = totalQty - (unitQty * conversion);
            }

            let discountType = parseInt(old.discount_type || 1);
            let discountValue = parseFloat(old.discount_value || 0);
            let gstPercentage = old.gst_percentage !== undefined ? parseFloat(old.gst_percentage || 0) : info.gst_percentage;
            let sellingRate = old.selling_rate !== undefined ? parseFloat(old.selling_rate || 0) : info.selling_rate;
            let markupType = old.markup_type !== undefined ? parseInt(old.markup_type || 1) : info.markup_type;
            let markupValue = old.markup_value !== undefined ? parseFloat(old.markup_value || 0) : info.markup_value;

            html += `
                <tr class="selected-batch-detail-row" data-purchase-item-id="${parseInt(batch.purchase_item_id)}">
                    <td>
                        <div class="batch-title">${escapeHtml(batch.batch_no || '-')}</div>
                        <div class="text-muted batch-sub">Bill: ${escapeHtml(batch.bill_no || '-')} | ${escapeHtml(batch.purchase_date || '-')}</div>
                    </td>
                    <td class="text-end">${parseFloat(batch.available_qty || 0).toFixed(2)}</td>
                    <td class="text-end">${formatCurrency(batch.purchase_price || 0)}</td>
                    <td><input type="number" step="0.01" min="0" class="form-control batch-unit-qty text-end" value="${formatInput2(unitQty, true)}"></td>
                    <td><input type="number" step="0.01" min="0" class="form-control batch-qty-per-unit text-end" value="${formatInput2(conversion, false)}" readonly></td>
                    <td><input type="number" step="0.01" min="0" class="form-control batch-loose-qty text-end" value="${formatInput2(looseQty, true)}"></td>
                    <td><input type="number" step="0.01" class="form-control batch-total-qty text-end" value="${formatInput2(totalQty, true)}" readonly></td>
                    <td>
                        <select class="form-select batch-price-type">
                            <option value="1" ${parseInt(batch._price_type) === 1 ? 'selected' : ''}>Retail</option>
                            <option value="2" ${parseInt(batch._price_type) === 2 ? 'selected' : ''}>Wholesale</option>
                        </select>
                        <input type="hidden" class="batch-markup-type" value="${markupType}">
                        <input type="hidden" class="batch-markup-value" value="${markupValue.toFixed(2)}">
                        <small class="text-muted batch-markup-label">${markupType === 2 ? '₹' : '%'} ${markupValue.toFixed(2)}</small>
                    </td>
                    <td><input type="number" step="0.01" min="0" class="form-control batch-selling-rate text-end" value="${sellingRate.toFixed(2)}" readonly></td>
                    <td>
                        <select class="form-select batch-discount-type">
                            <option value="1" ${discountType === 1 ? 'selected' : ''}>%</option>
                            <option value="2" ${discountType === 2 ? 'selected' : ''}>₹</option>
                        </select>
                    </td>
                    <td><input type="number" step="0.01" min="0" class="form-control batch-discount-value text-end" value="${formatInput2(discountValue, true)}"></td>
                    <td><input type="number" step="0.01" min="0" class="form-control batch-gst-percentage text-end" value="${gstPercentage.toFixed(2)}"></td>
                </tr>
            `;
        });

        $('#selectedBatchDetailsBody').html(html);
        $('#selectedBatchDetailsBody .selected-batch-detail-row').each(function () {
            calculateSelectedBatchRow($(this));
        });
    }

function addOrUpdateSalesItem() {
        if (!selectedProduct) {
            showAppToast('warning', 'Please select product.');
            return;
        }

        if (!selectedBatches || selectedBatches.length === 0) {
            showAppToast('warning', 'Please select at least one batch.');
            return;
        }

        let productId = parseInt(selectedProduct.id);

        if (editingProductId === null && salesItems.some(item => parseInt(item.product_id) === productId)) {
            showAppToast('warning', 'Product already added. Click edit icon to update this product.');
            return;
        }

        let rows = $('#selectedBatchDetailsBody .selected-batch-detail-row');
        if (!rows.length) {
            showAppToast('warning', 'Please enter selected batch details.');
            return;
        }

        let newRows = [];
        let groupId = Date.now() + '_' + productId;

        rows.each(function () {
            let row = $(this);
            calculateSelectedBatchRow(row);

            let purchaseItemId = parseInt(row.data('purchase-item-id') || 0);
            let batch = selectedBatches.find(b => parseInt(b.purchase_item_id) === purchaseItemId);
            if (!batch) {
                return true;
            }

            let unitQty = parseFloat(row.find('.batch-unit-qty').val() || 0);
            let qtyPerUnit = parseFloat(row.find('.batch-qty-per-unit').val() || 0);
            let looseQty = parseFloat(row.find('.batch-loose-qty').val() || 0);
            let totalQty = parseFloat(row.find('.batch-total-qty').val() || 0);
            let availableQty = parseFloat(batch.available_qty || 0);

            if (totalQty <= 0) {
                return true;
            }

            if (totalQty > availableQty) {
                showAppToast('warning', 'Qty cannot be greater than available qty for batch ' + (batch.batch_no || '-'));
                row.find('.batch-unit-qty').focus();
                newRows = null;
                return false;
            }

            let sellingRate = parseFloat(row.find('.batch-selling-rate').val() || 0);
            if (sellingRate <= 0) {
                showAppToast('warning', 'Selling rate required for batch ' + (batch.batch_no || '-'));
                newRows = null;
                return false;
            }

            newRows.push({
                group_id: groupId,
                product_id: productId,
                purchase_id: parseInt(batch.purchase_id),
                purchase_item_id: parseInt(batch.purchase_item_id),
                purchase_batch_no: batch.batch_no || '',
                purchase_bill_no: batch.bill_no || '',
                product_code: batch.product_code || selectedProduct.product_code || '',
                product_name: batch.product_name || selectedProduct.product_name || '',
                unit_qty: unitQty,
                qty_per_unit: qtyPerUnit,
                loose_qty: looseQty,
                qty: totalQty,
                entered_total_qty: totalQty,
                price_type: parseInt(row.find('.batch-price-type').val() || 1),
                markup_type: parseInt(row.find('.batch-markup-type').val() || 1),
                markup_value: parseFloat(row.find('.batch-markup-value').val() || 0),
                selling_rate: sellingRate,
                discount_type: parseInt(row.find('.batch-discount-type').val() || 1),
                discount_value: parseFloat(row.find('.batch-discount-value').val() || 0),
                gst_percentage: parseInt($('#invoiceType').val()) === 1 ? parseFloat(row.find('.batch-gst-percentage').val() || 0) : 0
            });
        });

        if (newRows === null) {
            return;
        }

        if (!newRows.length) {
            showAppToast('warning', 'Please enter quantity for at least one selected batch.');
            return;
        }

        if (editingProductId !== null) {
            salesItems = salesItems.filter(item => parseInt(item.product_id) !== parseInt(editingProductId));
        }

        salesItems = salesItems.concat(newRows);

        resetProductEntry();
        if (productEntryModal) {
            productEntryModal.hide();
        }
        renderSalesItems();
        calculateSummary();
        saveDraftToStorage();

        showAppToast('success', editingProductId === null ? 'Item added successfully.' : 'Item updated successfully.');

        editingProductId = null;
        $('#addItemBtn').html('<i class="mdi mdi-plus me-1"></i> Add Item');
        $('#cancelEditItemBtn').addClass('d-none');
    }

    function editProductItem(productId) {
        let productRows = salesItems.filter(item => parseInt(item.product_id) === parseInt(productId));
        if (!productRows.length) {
            return;
        }

        let first = productRows[0];
        editingProductId = parseInt(productId);

        selectedProduct = {
            id: first.product_id,
            product_code: first.product_code,
            product_name: first.product_name
        };

        $('#productSearch').val((first.product_code || '') + ' - ' + (first.product_name || ''));
        $('#productEntryModalTitle').text('Edit Product');
        $('#productEntryModalSubTitle').text((first.product_code || '') + ' - ' + (first.product_name || ''));

        loadProductBatches(productId, function (batches) {
            selectedBatches = batches.filter(b => productRows.some(r => parseInt(r.purchase_item_id) === parseInt(b.purchase_item_id)));

            // If selected batch stock is now zero, keep current saved rows in edit screen.
            productRows.forEach(function (r) {
                if (!selectedBatches.some(b => parseInt(b.purchase_item_id) === parseInt(r.purchase_item_id))) {
                    selectedBatches.push({
                        purchase_item_id: r.purchase_item_id,
                        purchase_id: r.purchase_id,
                        batch_no: r.purchase_batch_no,
                        bill_no: r.purchase_bill_no,
                        product_id: r.product_id,
                        product_code: r.product_code,
                        product_name: r.product_name,
                        available_qty: r.qty,
                        purchase_price: r.selling_rate,
                        unit_conversion: r.qty_per_unit,
                        cgst_percentage: 0,
                        sgst_percentage: r.gst_percentage,
                        igst_percentage: 0
                    });
                }
            });

            renderProductBatches(batches);
            renderSelectedBatchDetails(productRows);

            if (productEntryModal) {
                productEntryModal.show();
            }
        });

        $('#addItemBtn').html('<i class="mdi mdi-content-save me-1"></i> Update Item');
        $('#cancelEditItemBtn').removeClass('d-none');
    }

    function cancelItemEdit() {
        editingProductId = null;
        resetProductEntry();
        $('#addItemBtn').html('<i class="mdi mdi-plus me-1"></i> Add Item');
        $('#cancelEditItemBtn').addClass('d-none');
        if (productEntryModal) {
            productEntryModal.hide();
        }
    }

    function resetProductEntry() {
        selectedProduct = null;
        selectedBatches = [];
        $('#productSearch').val('');
        $('#selectedBatchBox').addClass('d-none').html('');
        $('#selectedBatchDetailsBody').html('<tr><td colspan="12" class="text-center text-muted">Search and select product to load batch-wise inputs.</td></tr>');
        $('#unitQty').val('0');
        $('#qtyPerUnit').val('1');
        $('#looseQty').val('0');
        $('#totalQty').val('0');
        $('#sellingRate').val('0');
        $('#markupValue').val('0');
        $('#discountValue').val('0');
        $('#gstPercentage').val('0');
        $('#productEntryModalTitle').text('Add Product');
        $('#productEntryModalSubTitle').text('Select batch and enter item details');
    }

    function renderSalesItems() {
        if (!salesItems || salesItems.length === 0) {
            $('#itemsTableBody').html('<tr><td colspan="10" class="text-center text-muted">No items added.</td></tr>');
            return;
        }

        let grouped = {};
        $.each(salesItems, function (_, item) {
            let key = item.product_id;
            if (!grouped[key]) {
                grouped[key] = [];
            }
            grouped[key].push(item);
        });

        let html = '';

        $.each(grouped, function (productId, rows) {
            let first = rows[0];

            let totalUnit = 0;
            let totalLoose = 0;
            let totalQty = 0;
            let lineGross = 0;
            let lineTax = 0;
            let lineTotal = 0;

            let qtyPerUnitValues = [];
            let sellingRateValues = [];
            let batchNames = rows.map(r => {
                let rowQty = parseFloat(r.qty || 0);
                return escapeHtml(r.purchase_batch_no || '-') + ' (' + rowQty.toFixed(2) + ')';
            }).join('<br>');

            $.each(rows, function (_, row) {
                let line = calculateItemLine(row);
                let rowQty = parseFloat(row.qty || 0);
                let rowUnit = parseFloat(row.unit_qty || 0);
                let rowLoose = parseFloat(row.loose_qty || 0);
                let rowQtyPerUnit = parseFloat(row.qty_per_unit || 0);

                totalUnit += rowUnit;
                totalLoose += rowLoose;
                totalQty += rowQty;
                lineGross += line.gross;
                lineTax += line.tax;
                lineTotal += line.total;

                if (rowQtyPerUnit > 0 && !qtyPerUnitValues.some(v => Math.abs(v - rowQtyPerUnit) < 0.0001)) {
                    qtyPerUnitValues.push(rowQtyPerUnit);
                }

                let rowSellingRate = parseFloat(row.selling_rate || 0);
                if (rowSellingRate > 0 && !sellingRateValues.some(v => Math.abs(v - rowSellingRate) < 0.01)) {
                    sellingRateValues.push(rowSellingRate);
                }
            });

            let displayQtyPerUnit = qtyPerUnitValues.length === 1
                ? qtyPerUnitValues[0].toFixed(2)
                : 'Mixed';

            let displayRate = sellingRateValues.length === 1
                ? formatCurrency(sellingRateValues[0])
                : '<span class="text-muted">Mixed</span>';

            html += `
                <tr>
                    <td>
                        <h6 class="mb-0">${escapeHtml(first.product_name || '')}</h6>
                        <small class="text-muted">${escapeHtml(first.product_code || '')}</small>
                    </td>
                    <td>
                        <div>${batchNames}</div>
                        <small class="text-muted">${rows.length} batch${rows.length > 1 ? 'es' : ''}</small>
                    </td>
                    <td class="text-end">${totalUnit.toFixed(2)}</td>
                    <td class="text-end">${displayQtyPerUnit}</td>
                    <td class="text-end">${totalLoose.toFixed(2)}</td>
                    <td class="text-end"><strong>${totalQty.toFixed(2)}</strong></td>
                    <td class="text-end">${displayRate}</td>
                    <td class="text-end">${formatCurrency(lineTax)}</td>
                    <td class="text-end"><strong>${formatCurrency(lineTotal)}</strong></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary edit-item-btn" data-product-id="${productId}" title="Edit">
                                <i class="mdi mdi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger remove-item-btn" data-product-id="${productId}" title="Delete">
                                <i class="mdi mdi-delete"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#itemsTableBody').html(html);
    }

    function calculateItemLine(item) {
        let gross = parseFloat(item.qty || 0) * parseFloat(item.selling_rate || 0);
        let discount = parseInt(item.discount_type) === 1
            ? gross * parseFloat(item.discount_value || 0) / 100
            : parseFloat(item.discount_value || 0);

        if (discount > gross) {
            discount = gross;
        }

        let taxable = Math.max(gross - discount, 0);
        let tax = parseInt($('#invoiceType').val()) === 1
            ? taxable * parseFloat(item.gst_percentage || 0) / 100
            : 0;

        return {
            gross: gross,
            discount: discount,
            taxable: taxable,
            tax: tax,
            total: taxable + tax
        };
    }

    function loadPaymentModes() {
        $.ajax({
            url: window.BASE_URL + 'api/sales.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_payment_modes'
            },
            success: function (response) {
                if (response.status === true) {
                    paymentModes = response.data.rows || [];
                    if (!paymentModes.length) {
                        paymentModes = [
                            {id: 1, mode_name: 'Cash'},
                            {id: 2, mode_name: 'UPI'},
                            {id: 3, mode_name: 'Bank'},
                            {id: 4, mode_name: 'Cheque'}
                        ];
                    }
                    renderPayments();
                } else {
                    handleApiError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error while loading payment modes.');
            }
        });
    }

    function addPaymentRow() {
        renderPayments();

        if ($('#paymentsBox .payment-row').length === 0) {
            salesPayments = [];
            $.each(paymentModes, function (_, mode) {
                salesPayments.push({
                    payment_mode_id: mode.id,
                    payment_amount: 0,
                    reference_no: '',
                    payment_date: currentDate(),
                    checked: 0
                });
            });
            renderPayments();
        }

        showAppToast('success', 'Select payment mode checkbox and enter amount.');
    }

    function renderPayments() {
        if (!paymentModes || paymentModes.length === 0) {
            $('#paymentsBox').html('<div class="text-muted">Payment modes not found.</div>');
            return;
        }

        if (!salesPayments || salesPayments.length === 0) {
            salesPayments = [];
            $.each(paymentModes, function (_, mode) {
                salesPayments.push({
                    payment_mode_id: mode.id,
                    payment_amount: 0,
                    reference_no: '',
                    payment_date: currentDate(),
                    checked: 0
                });
            });
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-sm table-centered table-nowrap mb-0">
                    <thead>
                        <tr>
                            <th width="70">Select</th>
                            <th>Mode</th>
                            <th width="180">Amount</th>
                            <th>Reference No</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        $.each(paymentModes, function (_, mode) {
            let payment = salesPayments.find(p => parseInt(p.payment_mode_id) === parseInt(mode.id)) || {
                payment_mode_id: mode.id,
                payment_amount: 0,
                reference_no: '',
                payment_date: currentDate(),
                checked: 0
            };

            let checked = parseInt(payment.checked || 0) === 1 || parseFloat(payment.payment_amount || 0) > 0 ? 'checked' : '';

            html += `
                <tr class="payment-row" data-mode-id="${mode.id}">
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input payment-input payment-check" ${checked}>
                    </td>
                    <td><strong>${escapeHtml(mode.mode_name || '')}</strong></td>
                    <td>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm payment-input payment-amount" value="${parseFloat(payment.payment_amount || 0).toFixed(2)}">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm payment-input payment-reference" value="${escapeAttr(payment.reference_no || '')}" placeholder="Reference / Cheque No">
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
            <small class="text-muted">Use checkbox for Cash / UPI / Bank / Cheque. Common due date is in document details.</small>
        `;

        $('#paymentsBox').html(html);
    }

    function paymentModeOptions(selectedId) {
        let html = '';

        if (!paymentModes || paymentModes.length === 0) {
            return '<option value="">No Payment Mode</option>';
        }

        $.each(paymentModes, function (_, mode) {
            let selected = parseInt(selectedId) === parseInt(mode.id) ? 'selected' : '';
            html += `<option value="${mode.id}" ${selected}>${escapeHtml(mode.mode_name || '')}</option>`;
        });

        return html;
    }

    function syncPaymentsFromDom() {
        let rows = [];

        $('.payment-row').each(function () {
            let modeId = parseInt($(this).data('mode-id') || 0);
            let isChecked = $(this).find('.payment-check').is(':checked') ? 1 : 0;
            let amount = parseFloat($(this).find('.payment-amount').val() || 0);
            let referenceNo = $(this).find('.payment-reference').val() || '';

            if (isChecked || amount > 0) {
                rows.push({
                    payment_mode_id: modeId,
                    payment_amount: amount,
                    reference_no: referenceNo,
                    payment_date: $('#dueDate').val() || currentDate(),
                    checked: isChecked
                });
            }
        });

        salesPayments = rows;
    }

    function calculateSummary() {
        let subTotal = 0;
        let itemDiscount = 0;
        let taxAmount = 0;
        let lineTotal = 0;

        $.each(salesItems, function (_, item) {
            let line = calculateItemLine(item);
            subTotal += line.gross;
            itemDiscount += line.discount;
            taxAmount += line.tax;
            lineTotal += line.total;
        });

        let headerDiscountType = parseInt($('#headerDiscountType').val());
        let headerDiscountValue = parseFloat($('#headerDiscountValue').val() || 0);
        let headerDiscount = headerDiscountValue > 0
            ? (headerDiscountType === 1 ? lineTotal * headerDiscountValue / 100 : headerDiscountValue)
            : 0;

        let shippingCharges = parseFloat($('#shippingCharges').val() || 0);
        let roundOff = parseFloat($('#roundOff').val() || 0);
        let grandTotal = Math.max(lineTotal - headerDiscount + shippingCharges + roundOff, 0);

        syncPaymentsFromDom();

        let paidAmount = 0;
        $.each(salesPayments, function (_, payment) {
            paidAmount += parseFloat(payment.payment_amount || 0);
        });

        let dueAmount = Math.max(grandTotal - paidAmount, 0);

        $('#summarySubTotal').text(formatCurrency(subTotal));
        $('#summaryDiscount').text(formatCurrency(itemDiscount + headerDiscount));
        $('#summaryTax').text(formatCurrency(taxAmount));
        $('#summaryGrandTotal').text(formatCurrency(grandTotal));
        $('#summaryPaid').text(formatCurrency(paidAmount));
        $('#summaryDue').text(formatCurrency(dueAmount));
    }

    function validateSaleBeforeSubmit() {
        if (parseInt($('#customerId').val() || 0) <= 0) {
            if ($.trim($('#customerSearch').val()) === '') {
                $('#customerSearch').val('Walk-in Customer');
            }

            if ($.trim($('#deliveryAddress').val()) === '') {
                showAppToast('warning', 'Please enter delivery address for walk-in customer.');
                $('#deliveryAddress').focus();
                return false;
            }
        } else if ($.trim($('#deliveryAddress').val()) === '') {
            showAppToast('warning', 'Delivery address is required.');
            $('#deliveryAddress').focus();
            return false;
        }

        if (!salesItems || salesItems.length === 0) {
            showAppToast('warning', 'Please add at least one product.');
            return false;
        }

        let invalidQty = salesItems.some(p => parseFloat(p.qty || 0) <= 0);
        if (invalidQty) {
            showAppToast('warning', 'Product quantity must be greater than zero.');
            return false;
        }

        syncPaymentsFromDom();

        let invalidPayment = salesPayments.some(p => parseInt(p.checked || 0) === 1 && parseFloat(p.payment_amount || 0) <= 0);
        if (invalidPayment) {
            showAppToast('warning', 'Please enter amount for selected payment mode.');
            return false;
        }

        return true;
    }

    function generateFromCurrentEdit(sourceSaleId, sourceType, targetType) {
        sourceSaleId = parseInt(sourceSaleId || 0);
        sourceType = parseInt(sourceType || 0);
        targetType = parseInt(targetType || 0);

        if (sourceSaleId <= 0 || sourceType <= 0 || targetType <= 0) {
            showAppToast('warning', 'Invalid generate request.');
            return;
        }

        if (sourceType === targetType) {
            showAppToast('warning', 'This document is already in selected type.');
            return;
        }

        if (!validateSaleBeforeSubmit()) {
            return;
        }

        let payload = collectPayload();

        /*
         * Important:
         * Generate should update same sales.id using current screen values.
         * Example:
         * pages/sales.php?id=15&mode=edit
         * User changes item/rate/customer and clicks Generate Final Invoice.
         * This payload updates id=15 and changes document_type to 5.
         */
        payload.id = sourceSaleId;
        payload.mode = 'convert';
        payload.source_id = sourceSaleId;
        payload.source_type = sourceType;
        payload.target_type = targetType;
        payload.document_type = targetType;

        setButtonLoading('saveSaleBtn', 'Generating...');

        $.ajax({
            url: window.BASE_URL + 'api/sales.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'save_sale',
                csrf_token: $('input[name="csrf_token"]').first().val(),
                payload: JSON.stringify(payload)
            },
            success: function (response) {
                if (response.status === true) {
                    showAppToast('success', response.message || 'Document generated successfully.');

                    let savedId = response.data && response.data.id ? parseInt(response.data.id) : sourceSaleId;

                    clearSalesSessionData();

                    /*
                     * After generate, automatically reload edit page.
                     * Final Invoice can be updated after reload.
                     */
                    setTimeout(function () {
                        window.location.href = window.BASE_URL + 'pages/sales.php?id=' + savedId + '&mode=edit';
                    }, 600);
                } else {
                    handleApiError(response);
                    resetButtonLoading('saveSaleBtn', $('#saveSaleBtn').html());
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error. Please try again.');
                resetButtonLoading('saveSaleBtn', $('#saveSaleBtn').html());
            }
        });
    }

    function saveSale() {
        if (!canSaveCurrentDocument()) {
            showAppToast('error', 'You do not have permission to save/generate this document type.');
            return;
        }

        if (parseInt($('#customerId').val() || 0) <= 0) {
            if ($.trim($('#customerSearch').val()) === '') {
                $('#customerSearch').val('Walk-in Customer');
            }

            if ($.trim($('#deliveryAddress').val()) === '') {
                showAppToast('warning', 'Please enter delivery address for walk-in customer.');
                $('#deliveryAddress').focus();
                return;
            }
        } else if ($.trim($('#deliveryAddress').val()) === '') {
            showAppToast('warning', 'Delivery address is required.');
            $('#deliveryAddress').focus();
            return;
        }

        if (!salesItems || salesItems.length === 0) {
            showAppToast('warning', 'Please add at least one product.');
            return;
        }

        syncPaymentsFromDom();

        let grandTotal = parseCurrencyText($('#summaryGrandTotal').text());
        let paidAmount = 0;
        $.each(salesPayments, function (_, payment) {
            paidAmount += parseFloat(payment.payment_amount || 0);
        });

        if (paidAmount > grandTotal) {
            showAppToast('warning', 'Paid amount cannot be greater than grand total.');
            return;
        }

        let invalidPayment = salesPayments.some(p => parseInt(p.checked || 0) === 1 && parseFloat(p.payment_amount || 0) <= 0);
        if (invalidPayment) {
            showAppToast('warning', 'Please enter amount for selected payment mode.');
            return;
        }

        let payload = collectPayload();

        setButtonLoading('saveSaleBtn', 'Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/sales.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'save_sale',
                csrf_token: $('input[name="csrf_token"]').first().val(),
                payload: JSON.stringify(payload)
            },
            success: function (response) {
                if (response.status === true) {
                    showAppToast('success', response.message || 'Sale saved successfully.');

                    let savedId = response.data && response.data.id ? parseInt(response.data.id) : 0;

                    // Clear only current sales draft/session items after save/generate/final invoice.
                    // Hold bills are stored in HOLD_KEY and are not removed here.
                    clearSalesSessionData();

                    let payloadMode = payload.mode || getSalesPageConfig().mode || 'new';
                    let savedDocType = response.data && response.data.sale
                        ? parseInt(response.data.sale.document_type || 0)
                        : parseInt(payload.document_type || 0);

                    /*
                     * After generate, reload the same document in edit mode.
                     * This allows:
                     * - Proforma -> continue Generate Sales Bill
                     * - Sales Bill -> continue Generate Final Invoice
                     * - Final Invoice -> Update / Print / Payment only
                     */
                    if (savedId > 0 && (payloadMode === 'convert' || parseInt(payload.id || 0) > 0 || savedDocType === 5)) {
                        $('#documentModeText').text('Saved: ' + (response.data.sales_no || ''));
                        setTimeout(function () {
                            window.location.href = window.BASE_URL + 'pages/sales.php?id=' + savedId + '&mode=edit';
                        }, 600);
                        return;
                    }

                    clearCurrentScreen();
                    renderSalesActionButtons({});

                    if (savedId > 0) {
                        $('#documentModeText').text('Saved: ' + (response.data.sales_no || ''));
                    }
                } else {
                    handleApiError(response);
                }

                resetButtonLoading('saveSaleBtn', $('#saveSaleBtn').html());
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error. Please try again.');
                resetButtonLoading('saveSaleBtn', $('#saveSaleBtn').html());
            }
        });
    }

    function normalizeSalesItemForPayload(item) {
        item = Object.assign({}, item || {});

        let unitQty = parseFloat(item.unit_qty || 0);
        let qtyPerUnit = parseFloat(item.qty_per_unit || 1);
        if (qtyPerUnit <= 0) {
            qtyPerUnit = 1;
        }
        let looseQty = parseFloat(item.loose_qty || 0);

        /*
         * IMPORTANT FIX:
         * Screen total is already calculated in item.qty.
         * Keep item.qty as final qty so API will not multiply again.
         */
        let qty = parseFloat(item.qty || item.entered_total_qty || 0);
        if (qty <= 0) {
            qty = (unitQty * qtyPerUnit) + looseQty;
        }

        item.unit_qty = unitQty;
        item.qty_per_unit = qtyPerUnit;
        item.loose_qty = looseQty;
        item.qty = qty;
        item.entered_total_qty = qty;

        return item;
    }

    function collectPayload() {
        syncPaymentsFromDom();

        return {
            id: parseInt($('#saleId').val() || 0),
            mode: getSalesPageConfig().mode || 'new',
            source_id: parseInt(getSalesPageConfig().source_id || 0),
            source_type: parseInt(getSalesPageConfig().source_type || 0),
            target_type: parseInt(getSalesPageConfig().target_type || 0),
            customer_id: parseInt($('#customerId').val() || 0),
            document_type: parseInt($('#documentType').val()),
            invoice_type: parseInt($('#invoiceType').val()),
            sales_date: $('#salesDate').val(),
            due_date: $('#dueDate').val(),
            notes: $('#notes').val(),
            discount_type: parseInt($('#headerDiscountType').val()),
            discount_value: parseFloat($('#headerDiscountValue').val() || 0),
            round_off: parseFloat($('#roundOff').val() || 0),
            shipping_charges: parseFloat($('#shippingCharges').val() || 0),
            delivery_address: $('#deliveryAddress').val() || '',
            customer_display: $('#customerSearch').val(),
            customer_info: $('#customerInfo').text(),
            customer_zone_filter: $('#customerZoneFilter').val() || '',
            customer_object: selectedCustomer,
            items: salesItems.map(normalizeSalesItemForPayload),
            payments: salesPayments
        };
    }

    function holdCurrentBill() {
        if (!salesItems || salesItems.length === 0) {
            showAppToast('warning', 'Add products before hold bill.');
            return;
        }

        let holds = getHoldBills();
        let payload = collectPayload();
        let holdId = 'HOLD_' + Date.now();

        holds.push({
            id: holdId,
            created_at: new Date().toLocaleString('en-IN'),
            customer_name: $('#customerSearch').val() || 'Walk-in Customer',
            amount: parseCurrencyText($('#summaryGrandTotal').text()),
            payload: payload
        });

        localStorage.setItem(HOLD_KEY, JSON.stringify(holds));
        clearCurrentScreen();
        clearDraftStorage();

        showAppToast('success', 'Bill held successfully.');
    }

    function openHoldBillsModal() {
        renderHoldBills();
        if (holdBillsModal) {
            holdBillsModal.show();
        }
    }

    function getHoldBills() {
        try {
            return JSON.parse(localStorage.getItem(HOLD_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function renderHoldBills() {
        let holds = getHoldBills();

        if (!holds.length) {
            $('#holdBillsTableBody').html('<tr><td colspan="5" class="text-center text-muted">No hold bills.</td></tr>');
            return;
        }

        let html = '';
        $.each(holds, function (index, hold) {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(hold.customer_name || '-')}</td>
                    <td>${escapeHtml(hold.created_at || '-')}</td>
                    <td class="text-end">${formatCurrency(hold.amount || 0)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary load-hold-bill-btn" data-id="${hold.id}">
                                Load
                            </button>
                            <button type="button" class="btn btn-outline-danger delete-hold-bill-btn" data-id="${hold.id}">
                                <i class="mdi mdi-delete"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#holdBillsTableBody').html(html);
    }

    function loadHoldBill(holdId) {
        let holds = getHoldBills();
        let hold = holds.find(h => h.id === holdId);

        if (!hold) {
            showAppToast('error', 'Hold bill not found.');
            return;
        }

        applyPayloadToScreen(hold.payload);

        holds = holds.filter(h => h.id !== holdId);
        localStorage.setItem(HOLD_KEY, JSON.stringify(holds));

        if (holdBillsModal) {
            holdBillsModal.hide();
        }

        showAppToast('success', 'Hold bill loaded.');
    }

    function deleteHoldBill(holdId) {
        if (!confirm('Delete this hold bill?')) {
            return;
        }

        let holds = getHoldBills().filter(h => h.id !== holdId);
        localStorage.setItem(HOLD_KEY, JSON.stringify(holds));
        renderHoldBills();
        showAppToast('success', 'Hold bill deleted.');
    }

    function saveDraftToStorage() {
        let config = getSalesPageConfig();
        let mode = config.mode || 'new';
        let currentSaleId = parseInt($('#saleId').val() || 0);

        /*
         * Do not store edit / generate pages as draft.
         * This prevents old session data from loading again after update/refresh.
         */
        if (mode === 'edit' || mode === 'convert' || currentSaleId > 0) {
            return;
        }

        localStorage.setItem(DRAFT_KEY, JSON.stringify(collectPayload()));
    }

    function loadDraftFromStorage() {
        let raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) {
            return;
        }

        try {
            let draft = JSON.parse(raw);
            applyPayloadToScreen(draft, false);
        } catch (e) {
            clearDraftStorage();
        }
    }

    function applyPayloadToScreen(payload, allowDraftSave) {
        allowDraftSave = (allowDraftSave !== false);
        selectedCustomer = payload.customer_object || null;
        $('#saleId').val(payload.id || 0);
        $('#customerId').val(payload.customer_id || '');
        $('#customerSearch').val(payload.customer_display || '');
        $('#customerInfo').text(payload.customer_info || '');
        $('#customerZoneFilter').val(payload.customer_zone_filter || (selectedCustomer && selectedCustomer.zone_id ? selectedCustomer.zone_id : ''));
        if (payload.document_type && $('#documentType option[value="' + payload.document_type + '"]').length === 0) {
            renderDocumentTypeDropdown(payload.document_type);
        }
        $('#documentType').val(payload.document_type || $('#documentType').val() || '');
        $('#invoiceType').val(payload.invoice_type || '1');
        syncSwitchControls();
        $('#salesDate').val(payload.sales_date || currentDate());
        $('#dueDate').val(payload.due_date || payload.validity_date || '');
        $('#notes').val(payload.notes || '');
        $('#deliveryAddress').val(payload.delivery_address || '');
        $('#shippingCharges').val(payload.shipping_charges || '0');
        $('#headerDiscountType').val(payload.discount_type || '1');
        $('#headerDiscountValue').val(payload.discount_value || '0');
        $('#roundOff').val(payload.round_off || '0');

        salesItems = Array.isArray(payload.items) ? payload.items : [];
        salesPayments = Array.isArray(payload.payments) ? payload.payments : [];

        renderSalesItems();
        renderPayments();
        calculateSummary();
        renderSalesActionButtons();

        if (allowDraftSave === true) {
            saveDraftToStorage();
        }
    }

    function clearCurrentScreen() {
        $('#saleId').val('0');
        $('#customerId').val('');
        $('#customerSearch').val('');
        $('#customerInfo').text('');
        $('#customerZoneFilter').val('');
        let firstDocType = $('#documentType option:first').val() || '';
        $('#documentType').val(firstDocType).prop('disabled', false);
        $('#invoiceType').val('1');
        syncSwitchControls();
        $('#salesDate').val(currentDate());
        $('#dueDate').val('');
        $('#notes').val('');
        $('#deliveryAddress').val('');
        $('#shippingCharges').val('0');
        $('#headerDiscountType').val('1');
        $('#headerDiscountValue').val('0');
        $('#roundOff').val('0');

        selectedCustomer = null;
        selectedProduct = null;
        selectedBatches = [];
        salesItems = [];
        salesPayments = [];
        editingProductId = null;

        resetProductEntry();
        renderSalesItems();
        renderPayments();
        calculateSummary();
    }

    function clearSalesSessionData() {
        /*
         * Clear every temporary sales draft/session data after save/update/generate.
         * HOLD_KEY is preserved because hold bills are separate.
         */
        let protectedKeys = [HOLD_KEY];

        function shouldRemoveSalesKey(key) {
            if (!key || protectedKeys.indexOf(key) !== -1) {
                return false;
            }

            let k = String(key).toLowerCase();

            return (
                k.indexOf('sales') !== -1 ||
                k.indexOf('sale_') !== -1 ||
                k.indexOf('universal_erp_sales') !== -1 ||
                k.indexOf('draft') !== -1
            ) && (
                k.indexOf('draft') !== -1 ||
                k.indexOf('temp') !== -1 ||
                k.indexOf('session') !== -1 ||
                k.indexOf('current') !== -1 ||
                k.indexOf('selected') !== -1
            );
        }

        localStorage.removeItem(DRAFT_KEY);
        sessionStorage.removeItem(DRAFT_KEY);

        try {
            Object.keys(localStorage).forEach(function (key) {
                if (shouldRemoveSalesKey(key)) {
                    localStorage.removeItem(key);
                }
            });
        } catch (e) {}

        try {
            Object.keys(sessionStorage).forEach(function (key) {
                if (shouldRemoveSalesKey(key)) {
                    sessionStorage.removeItem(key);
                }
            });
        } catch (e) {}

        selectedCustomer = null;
        selectedProduct = null;
        selectedBatches = [];
        editingProductId = null;
    }

    function clearDraftStorage() {
        clearSalesSessionData();
    }


    function formatCustomerAddress(customer) {
        let parts = [];

        if (customer.address) parts.push(customer.address);
        if (customer.city) parts.push(customer.city);
        if (customer.state) parts.push(customer.state);
        if (customer.pincode) parts.push(customer.pincode);

        return parts.join(', ');
    }

    function loadCustomerZones(selectedZoneId) {
        $.ajax({
            url: window.BASE_URL + 'api/customers.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_zones'
            },
            success: function (response) {
                if (response.status === true) {
                    let zones = response.data.zones || [];
                    let html = '<option value="">Select Zone</option>';
                    let filterHtml = '<option value="">All Zones</option>';

                    $.each(zones, function (_, zone) {
                        let label = zone.zone_name + (zone.zone_code ? ' - ' + zone.zone_code : '');
                        let selected = parseInt(zone.id) === parseInt(selectedZoneId || 0) ? 'selected' : '';
                        html += `<option value="${zone.id}" ${selected}>${escapeHtml(label)}</option>`;
                        filterHtml += `<option value="${zone.id}">${escapeHtml(label)}</option>`;
                    });

                    $('#zone_id').html(html);
                    let currentFilterZone = $('#customerZoneFilter').val();
                    $('#customerZoneFilter').html(filterHtml);
                    if (currentFilterZone) {
                        $('#customerZoneFilter').val(currentFilterZone);
                    }
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function resetCustomerModalForm() {
        if ($('#customerForm').length) {
            $('#customerForm')[0].reset();
        }

        $('#customer_id').val('');
        $('#state').val('Tamil Nadu');
        $('#opening_outstanding').val('0.00');
        $('#status').val('1');
        $('#saveCustomerBtn').prop('disabled', false).html('Save Customer');
        loadCustomerZones();
    }

    function saveCustomerFromSales(e) {
        e.preventDefault();
        if (!getGlobalPermission('can_quick_add_customer')) {
            showAppToast('error', 'You do not have permission to save customer.');
            return;
        }


        if ($.trim($('#customer_name').val()) === '') {
            showAppToast('warning', 'Please enter customer name.');
            $('#customer_name').focus();
            return;
        }

        if ($('#zone_id').val() === '') {
            showAppToast('warning', 'Please select zone.');
            $('#zone_id').focus();
            return;
        }

        let mobile = $.trim($('#mobile').val());
        if (mobile !== '' && !/^[0-9]{10}$/.test(mobile)) {
            showAppToast('warning', 'Please enter valid 10 digit mobile number.');
            $('#mobile').focus();
            return;
        }

        let email = $.trim($('#email').val());
        if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showAppToast('warning', 'Please enter valid email address.');
            $('#email').focus();
            return;
        }

        let pincode = $.trim($('#pincode').val());
        if (pincode !== '' && !/^[0-9]{6}$/.test(pincode)) {
            showAppToast('warning', 'Please enter valid 6 digit pincode.');
            $('#pincode').focus();
            return;
        }

        $('#saveCustomerBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/customers.php',
            type: 'POST',
            dataType: 'json',
            data: $('#customerForm').serialize() + '&action=save_customer',
            success: function (response) {
                if (response.status === true) {
                    showAppToast('success', response.message || 'Customer saved.');

                    let customerId = response.data.customer_id || response.data.id || 0;
                    let customerName = $('#customer_name').val();
                    let customerAddress = [
                        $('#address').val(),
                        $('#city').val(),
                        $('#state').val(),
                        $('#pincode').val()
                    ].filter(Boolean).join(', ');

                    selectedCustomer = {
                        id: customerId,
                        customer_name: customerName,
                        mobile: $('#mobile').val(),
                        gst_number: $('#gst_number').val(),
                        address: $('#address').val(),
                        city: $('#city').val(),
                        state: $('#state').val(),
                        pincode: $('#pincode').val()
                    };

                    $('#customerId').val(customerId);
                    $('#customerSearch').val(customerName);
                    $('#customerInfo').text(($('#mobile').val() || '') + ' ' + ($('#gst_number').val() || ''));
                    $('#deliveryAddress').val(customerAddress);

                    if (customerModal) {
                        customerModal.hide();
                    }

                    saveDraftToStorage();
                } else {
                    handleApiError(response);
                }

                $('#saveCustomerBtn').prop('disabled', false).html('Save Customer');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error.');
                $('#saveCustomerBtn').prop('disabled', false).html('Save Customer');
            }
        });
    }

    function currentDate() {
        return new Date().toISOString().slice(0, 10);
    }

    function formatCurrency(value) {
        let numberValue = parseFloat(value || 0);
        return '₹' + numberValue.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function parseCurrencyText(value) {
        return parseFloat(String(value || '0').replace(/[₹,]/g, '')) || 0;
    }

    function setButtonLoading(buttonId, text) {
        $('#' + buttonId).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>' + text);
    }

    function resetButtonLoading(buttonId, html) {
        $('#' + buttonId).prop('disabled', false).html(html);
    }

    function showAppToast(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        alert(message);
    }

    function handleApiError(response) {
        if (response && response.redirect) {
            window.location.href = response.redirect;
            return;
        }

        showAppToast('error', (response && response.message) ? response.message : 'Something went wrong.');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function escapeAttr(value) {
        return String(value || '').replace(/'/g, '&#039;').replace(/"/g, '&quot;');
    }
});
