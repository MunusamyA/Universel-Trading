$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let listConfig = window.SALES_LIST_CONFIG || {};
    let documentType = parseInt(listConfig.document_type || 0);
    let documentTypes = listConfig.document_types || {};
    let permissions = listConfig.permissions || {};
    let allowedDocumentTypes = [];
    let viewDocumentTypes = [];
    let listDocumentTypes = [];

    initSalesListPage();
    bindSalesListEvents();

    function initSalesListPage() {
        loadDocumentPermissions(function () {
            applyNavigationPermissions();
            loadSalesList();
        });
    }

    function bindSalesListEvents() {
        $('#filterBtn').on('click', function () {
            loadSalesList();
        });

        $('#resetFilterBtn').on('click', function () {
            $('#searchText').val('');
            $('#statusFilter').val('');
            $('#fromDate').val('');
            $('#toDate').val('');
            loadSalesList();
        });

        $('#searchText').on('keyup', function (event) {
            if (event.key === 'Enter') {
                loadSalesList();
            }
        });

        $(document).on('click', '.convert-doc-btn', function () {
            let id = parseInt($(this).attr('data-id') || $(this).data('id') || 0);
            let sourceType = parseInt($(this).attr('data-source-type') || $(this).data('source-type') || 0);
            let targetType = parseInt($(this).attr('data-target-type') || $(this).data('target-type') || 0);

            if (id <= 0 || sourceType <= 0 || targetType <= 0) {
                return;
            }

            window.location.href = window.BASE_URL +
                'pages/sales.php?mode=convert&source_id=' + id +
                '&source_type=' + sourceType +
                '&target_type=' + targetType;
        });

        $(document).on('click', '.delete-sale-btn', function () {
            let id = parseInt($(this).attr('data-id') || $(this).data('id') || 0);
            handleSaleCloseAction(id, 'delete_sale', 'Delete this document?', 'Delete reason');
        });

        $(document).on('click', '.cancel-sale-btn', function () {
            let id = parseInt($(this).attr('data-id') || $(this).data('id') || 0);
            handleSaleCloseAction(id, 'cancel_sale', 'Cancel this document?', 'Cancel reason');
        });
    }

    function loadDocumentPermissions(callback) {
        $.ajax({
            url: window.BASE_URL + 'api/sales.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_document_permissions'
            },
            success: function (response) {
                if (response.status === true && response.data) {
                    applyPermissionPayload(response.data);
                } else {
                    $('#salesListBody').html(
                        '<tr><td colspan="11" class="text-center text-danger">' +
                        escapeHtml(response.message || 'Permission denied.') +
                        '</td></tr>'
                    );
                }

                if (typeof callback === 'function') {
                    callback();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#salesListBody').html('<tr><td colspan="11" class="text-center text-danger">Server error.</td></tr>');

                if (typeof callback === 'function') {
                    callback();
                }
            }
        });
    }

    function applyPermissionPayload(data) {
        data = data || {};

        if (data.document_types) {
            documentTypes = data.document_types;
        }

        if (data.permissions) {
            permissions = data.permissions;
        }

        allowedDocumentTypes = data.allowed_document_types || [];
        viewDocumentTypes = data.view_document_types || [];
        listDocumentTypes = data.list_document_types || [];

        listConfig.can_add = data.can_add || false;
        listConfig.can_view = data.can_view || false;
        listConfig.can_list = data.can_list || false;
    }

    function applyNavigationPermissions() {
        toggleByPermission('#newSalesEntryBtn', listConfig.can_add === true || listConfig.can_add === 1 || listConfig.can_add === '1');

        toggleDocNav('#quotationListBtn', 1);
        toggleDocNav('#proformaListBtn', 2);
        toggleDocNav('#salesBillListBtn', 3);
        toggleDocNav('#directSaleListBtn', 4);
        toggleDocNav('#finalInvoiceListBtn', 5);

        toggleByPermission('#overallSalesListBtn', hasAnyDocumentPermission('view') || hasAnyDocumentPermission('list'));

        if (documentType > 0 && !docPermission(documentType, 'view') && !docPermission(documentType, 'list')) {
            $('#salesListBody').html('<tr><td colspan="11" class="text-center text-danger">Permission denied.</td></tr>');
        }
    }

    function toggleDocNav(selector, typeId) {
        toggleByPermission(selector, docPermission(typeId, 'view') || docPermission(typeId, 'list'));
    }

    function toggleByPermission(selector, allowed) {
        if (allowed) {
            $(selector).removeClass('d-none');
        } else {
            $(selector).addClass('d-none');
        }
    }

    function loadSalesList() {
        if (documentType > 0 && !docPermission(documentType, 'view') && !docPermission(documentType, 'list')) {
            $('#salesListBody').html('<tr><td colspan="11" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        if (documentType <= 0 && !hasAnyDocumentPermission('view') && !hasAnyDocumentPermission('list')) {
            $('#salesListBody').html('<tr><td colspan="11" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $('#salesListBody').html('<tr><td colspan="11" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/sales.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_sales',
                document_type: documentType,
                search: $('#searchText').val() || '',
                status: $('#statusFilter').val() || '',
                from_date: $('#fromDate').val() || '',
                to_date: $('#toDate').val() || ''
            },
            success: function (response) {
                if (response.status === true) {
                    if (response.data) {
                        applyPermissionPayload(response.data);
                        applyNavigationPermissions();
                    }

                    renderSalesList((response.data && response.data.rows) ? response.data.rows : []);
                } else {
                    $('#salesListBody').html(
                        '<tr><td colspan="11" class="text-center text-danger">' +
                        escapeHtml(response.message || 'Unable to load list') +
                        '</td></tr>'
                    );
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#salesListBody').html('<tr><td colspan="11" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderSalesList(rows) {
        updateCards(rows);

        if (!rows || rows.length === 0) {
            $('#salesListBody').html('<tr><td colspan="11" class="text-center text-muted">No records found.</td></tr>');
            return;
        }

        let html = '';

        $.each(rows, function (index, row) {
            let docType = currentDocumentType(row);
            let converted = isConvertedToAnotherRow(row, docType);

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(row.sales_no || '') + '</strong><br><span class="badge bg-soft-primary text-primary">' + escapeHtml(documentLabel(docType)) + '</span></td>';
            html += '<td>' + formatDate(row.sales_date) + '</td>';
            html += '<td><strong>' + escapeHtml(row.customer_name || '') + '</strong><br><small class="text-muted">' + escapeHtml(row.customer_mobile || '') + '</small></td>';
            html += '<td class="text-end">' + formatCurrency(row.sub_total || 0) + '</td>';
            html += '<td class="text-end">' + formatCurrency(row.tax_amount || 0) + '</td>';
            html += '<td class="text-end"><strong>' + formatCurrency(row.grand_total || 0) + '</strong></td>';
            html += '<td class="text-end">' + formatCurrency(row.paid_amount || 0) + '</td>';
            html += '<td class="text-end">' + formatCurrency(row.due_amount || 0) + '</td>';
            html += '<td>' + statusBadge(row.status, converted) + '</td>';
            html += '<td class="text-end">' + actionButtons(row, converted) + '</td>';
            html += '</tr>';
        });

        $('#salesListBody').html(html);
    }

    function updateCards(rows) {
        rows = rows || [];

        let totalAmount = 0;
        let dueAmount = 0;
        let paidAmount = 0;

        $.each(rows, function (_, row) {
            totalAmount += parseFloat(row.grand_total || 0);
            dueAmount += parseFloat(row.due_amount || 0);
            paidAmount += parseFloat(row.paid_amount || 0);
        });

        $('#countCard').text(rows.length);
        $('#totalCard').text(formatCurrency(totalAmount));
        $('#paidCard').text(formatCurrency(paidAmount));
        $('#dueCard').text(formatCurrency(dueAmount));
    }

    function currentDocumentType(row) {
        row = row || {};

        let type = parseInt(row.document_type || 0);
        let convertedToDocumentType = parseInt(row.converted_to_document_type || 0);
        let convertedToSaleId = parseInt(row.converted_to_sale_id || 0);

        if (convertedToSaleId <= 0 && convertedToDocumentType > 0) {
            type = convertedToDocumentType;
        }

        return type;
    }

    function isConvertedToAnotherRow(row, currentType) {
        row = row || {};

        let id = parseInt(row.id || 0);
        let convertedToSaleId = parseInt(row.converted_to_sale_id || 0);
        let convertedToDocumentType = parseInt(row.converted_to_document_type || 0);

        if (convertedToSaleId <= 0) {
            return false;
        }

        if (convertedToSaleId === id) {
            return false;
        }

        if (convertedToDocumentType > 0 && convertedToDocumentType === parseInt(currentType || 0)) {
            return false;
        }

        return true;
    }

    function actionButtons(row, converted) {
        let id = parseInt(row.id || 0);
        let docType = currentDocumentType(row);
        let dueAmount = parseFloat(row.due_amount || 0);
        let status = parseInt(row.status || 0);
        let closed = status === 3 || status === 4;

        let html = '<div class="btn-group btn-group-sm">';

        /*
         * Common row buttons are controlled by the row document type.
         */
        if (row.can_view || row.can_edit || docPermission(docType, 'view') || docPermission(docType, 'edit')) {
            html += '<a class="btn btn-outline-primary" href="' + window.BASE_URL + 'pages/sales.php?id=' + id + '&mode=edit" title="Edit / View"><i class="mdi mdi-pencil"></i></a>';
        }

        if (!closed && dueAmount > 0 && (row.can_receive_payment || docPermission(docType, 'receive_payment'))) {
            html += '<a class="btn btn-outline-success" href="' + window.BASE_URL + 'pages/customer-payments.php?sales_id=' + id + '" title="Receive Payment"><i class="mdi mdi-cash-plus"></i></a>';
        }

        if (row.can_print || docPermission(docType, 'print')) {
            html += '<a class="btn btn-outline-danger" target="_blank" href="' + window.BASE_URL + 'pages/sales-print.php?id=' + id + '&print=1" title="Print PDF"><i class="mdi mdi-file-pdf-box"></i></a>';
        }

        if (!closed && (row.can_cancel || docPermission(docType, 'cancel'))) {
            html += '<button type="button" class="btn btn-outline-warning cancel-sale-btn" data-id="' + id + '" title="Cancel"><i class="mdi mdi-cancel"></i></button>';
        }

        if (!closed && (row.can_delete || docPermission(docType, 'delete'))) {
            html += '<button type="button" class="btn btn-outline-danger delete-sale-btn" data-id="' + id + '" title="Delete"><i class="mdi mdi-delete"></i></button>';
        }

        if (!closed && !converted) {
            let targetButtons = [
                {
                    target_type: 2,
                    title: 'Generate Proforma Bill',
                    btn: 'btn-outline-info',
                    icon: 'mdi-file-document-plus-outline',
                    actions: ['generate_proforma_bill']
                },
                {
                    target_type: 3,
                    title: 'Generate Sales Bill',
                    btn: 'btn-outline-success',
                    icon: 'mdi-receipt-text-plus-outline',
                    actions: ['generate_sales_bill']
                },
                {
                    target_type: 5,
                    title: 'Generate Final Invoice',
                    btn: 'btn-warning',
                    icon: 'mdi-receipt-text-check-outline',
                    actions: ['generate_invoice']
                }
            ];

            $.each(targetButtons, function (_, target) {
                if (target.target_type === docType) {
                    return;
                }

                if (canGenerateToTarget(docType, target.target_type, target.actions)) {
                    html += convertButton(id, docType, target.target_type, target.btn, target.icon, target.title);
                }
            });
        }

        html += '</div>';

        if (html === '<div class="btn-group btn-group-sm"></div>') {
            return '<span class="text-muted">No access</span>';
        }

        return html;
    }

    function convertButton(id, sourceType, targetType, btnClass, iconClass, title) {
        return '<button type="button" class="btn ' + btnClass + ' convert-doc-btn" data-id="' + id + '" data-source-type="' + sourceType + '" data-target-type="' + targetType + '" title="' + title + '"><i class="mdi ' + iconClass + '"></i></button>';
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
         * Final source-row based generate rule:
         * Quotation  -> Proforma / Sales Bill / Final Invoice
         * Proforma   -> Sales Bill / Final Invoice
         * Sales Bill -> Final Invoice
         */
        sourceType = parseInt(sourceType || 0);
        targetType = parseInt(targetType || 0);
        targetGenerateActions = targetGenerateActions || [];

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

    function getSalesListCsrfToken() {
        return $('input[name="csrf_token"]').first().val()
            || $('meta[name="csrf-token"]').attr('content')
            || window.CSRF_TOKEN
            || '';
    }

    function handleSaleCloseAction(id, apiAction, confirmTitle, reasonLabel) {
        if (id <= 0) {
            return;
        }

        let runRequest = function (reason) {
            $.ajax({
                url: window.BASE_URL + 'api/sales.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: apiAction,
                    id: id,
                    reason: reason || '',
                    csrf_token: getSalesListCsrfToken()
                },
                success: function (response) {
                    if (response.status === true) {
                        showListToast('success', response.message || 'Updated successfully.');
                        loadSalesList();
                    } else {
                        showListToast('error', response.message || 'Unable to update.');
                    }
                },
                error: function (xhr) {
                    console.log(xhr.responseText);
                    showListToast('error', 'Server error.');
                }
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: confirmTitle,
                input: 'text',
                inputPlaceholder: reasonLabel,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No'
            }).then(function (result) {
                if (result.isConfirmed) {
                    runRequest(result.value || '');
                }
            });
            return;
        }

        if (confirm(confirmTitle)) {
            runRequest(prompt(reasonLabel, '') || '');
        }
    }

    function showListToast(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        alert(message);
    }

    function hasAnyDocumentPermission(action) {
        for (let typeId = 1; typeId <= 5; typeId++) {
            if (docPermission(typeId, action)) {
                return true;
            }
        }

        return false;
    }

    function docPermission(typeId, action) {
        typeId = parseInt(typeId || 0);

        if (!permissions || Object.keys(permissions).length === 0) {
            return false;
        }

        let menuKeys = {
            1: ['1', 1, 'sales_quotation', 'quotation', 'quotation_list', 'sales_quotation_list'],
            2: ['2', 2, 'sales_proforma_bill', 'proforma_bill', 'proforma_bill_list', 'sales_proforma_bill_list'],
            3: ['3', 3, 'sales_bill', 'sale_order', 'sales-bill', 'sales_bill_list'],
            4: ['4', 4, 'sales_direct_sale', 'direct_sale', 'direct_sale_list', 'sales_direct_sale_list'],
            5: ['5', 5, 'sales_final_invoice', 'sales_invoice', 'final_invoice_list', 'sales_final_invoice_list']
        };

        let actionKeys = [action, 'can_' + action];

        if (action === 'generate_invoice') {
            actionKeys.push('generate');
            actionKeys.push('can_generate');
            actionKeys.push('invoice');
            actionKeys.push('can_invoice');
        }

        if (action === 'generate_proforma_bill') {
            actionKeys.push('generate_proforma');
            actionKeys.push('can_generate_proforma');
            actionKeys.push('generate_proforma_bill');
            actionKeys.push('can_generate_proforma_bill');
        }

        if (action === 'generate_quotation') {
            actionKeys.push('generate_quotation');
            actionKeys.push('can_generate_quotation');
        }

        if (action === 'generate_sale_order') {
            actionKeys.push('generate_sale_order');
            actionKeys.push('can_generate_sale_order');
        }

        if (action === 'generate_sales_bill') {
            actionKeys.push('generate_sales_bill');
            actionKeys.push('can_generate_sales_bill');
        }

        let keys = menuKeys[typeId] || [String(typeId), typeId];

        for (let i = 0; i < keys.length; i++) {
            let permissionRow = permissions[keys[i]];

            if (permissionRow === true || permissionRow === 1 || permissionRow === '1') {
                return true;
            }

            if (!permissionRow || typeof permissionRow !== 'object') {
                continue;
            }

            for (let j = 0; j < actionKeys.length; j++) {
                let value = permissionRow[actionKeys[j]];

                if (value === true || value === 1 || value === '1') {
                    return true;
                }
            }
        }

        return false;
    }

    function documentLabel(typeId) {
        typeId = parseInt(typeId || 0);

        if (documentTypes[typeId] && documentTypes[typeId].label) {
            return documentTypes[typeId].label;
        }

        let fallback = {
            1: 'Quotation',
            2: 'Proforma Bill',
            3: 'Sales Bill',
            4: 'Direct Sale',
            5: 'Final Invoice'
        };

        return fallback[typeId] || 'Document';
    }

    function statusBadge(status, converted) {
        if (converted) {
            return '<span class="badge bg-info">Generated</span>';
        }

        status = parseInt(status || 0);

        if (status === 1) {
            return '<span class="badge bg-success">Active</span>';
        }

        if (status === 2) {
            return '<span class="badge bg-primary">Final</span>';
        }

        if (status === 3) {
            return '<span class="badge bg-danger">Deleted</span>';
        }

        if (status === 4) {
            return '<span class="badge bg-warning text-dark">Cancelled</span>';
        }

        return '<span class="badge bg-secondary">Unknown</span>';
    }

    function formatCurrency(value) {
        return '₹' + parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(value) {
        if (!value) {
            return '';
        }

        return escapeHtml(value);
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

});
