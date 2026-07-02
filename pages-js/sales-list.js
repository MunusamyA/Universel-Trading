$(document).ready(function () {
    let listConfig = window.SALES_LIST_CONFIG || {};
    let documentType = parseInt(listConfig.document_type || 0);
    let documentTypes = listConfig.document_types || {};
    let permissions = listConfig.permissions || {};

    initSalesListPage();
    bindSalesListEvents();

    function initSalesListPage() {
        loadDocumentPermissions(function () {
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

        $('#searchText').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadSalesList();
            }
        });

        $(document).on('click', '.convert-doc-btn', function () {
            let id = parseInt($(this).attr('data-id') || $(this).data('id') || 0);

            /*
             * Source type must be the CURRENT document_type of this row.
             * Do not use old source_document_type from previously generated rows.
             * Otherwise api/sales.php throws:
             * "Source document type mismatch. Please reload and try again."
             */
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
                    if (response.data.document_types) {
                        documentTypes = response.data.document_types;
                    }

                    if (response.data.permissions) {
                        permissions = response.data.permissions;
                    }
                }

                if (typeof callback === 'function') {
                    callback();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);

                if (typeof callback === 'function') {
                    callback();
                }
            }
        });
    }

    function loadSalesList() {
        $('#salesListBody').html(
            '<tr><td colspan="11" class="text-center text-muted">Loading...</td></tr>'
        );

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
                    if (response.data && response.data.document_types) {
                        documentTypes = response.data.document_types;
                    }

                    if (response.data && response.data.permissions) {
                        permissions = response.data.permissions;
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
                $('#salesListBody').html(
                    '<tr><td colspan="11" class="text-center text-danger">Server error.</td></tr>'
                );
            }
        });
    }

    function renderSalesList(rows) {
        updateCards(rows);

        if (!rows || rows.length === 0) {
            $('#salesListBody').html(
                '<tr><td colspan="11" class="text-center text-muted">No records found.</td></tr>'
            );
            return;
        }

        let html = '';

        $.each(rows, function (index, row) {
            let docType = currentDocumentType(row);
            let converted = isConvertedToAnotherRow(row, docType);

            html += `
                <tr>
                    <td>${index + 1}</td>

                    <td>
                        <strong>${escapeHtml(row.sales_no || '')}</strong><br>
                        <span class="badge bg-soft-primary text-primary">${escapeHtml(documentLabel(docType))}</span>
                    </td>

                    <td>${formatDate(row.sales_date)}</td>

                    <td>
                        <strong>${escapeHtml(row.customer_name || '')}</strong><br>
                        <small class="text-muted">${escapeHtml(row.customer_mobile || '')}</small>
                    </td>

                    <td class="text-end">${formatCurrency(row.sub_total || 0)}</td>
                    <td class="text-end">${formatCurrency(row.tax_amount || 0)}</td>
                    <td class="text-end"><strong>${formatCurrency(row.grand_total || 0)}</strong></td>
                    <td class="text-end">${formatCurrency(row.paid_amount || 0)}</td>
                    <td class="text-end">${formatCurrency(row.due_amount || 0)}</td>
                    <td>${statusBadge(row.status, converted)}</td>
                    <td class="text-end">${actionButtons(row, converted)}</td>
                </tr>
            `;
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

        let documentType = parseInt(row.document_type || 0);
        let convertedToDocumentType = parseInt(row.converted_to_document_type || 0);
        let convertedToSaleId = parseInt(row.converted_to_sale_id || 0);

        /*
         * Same-row generate flow:
         * Your API updates the same sales.id and changes document_type.
         * In some responses, converted_to_document_type may be newer than
         * document_type. Use it only when this row was not converted into
         * another sales row.
         */
        if (convertedToSaleId <= 0 && convertedToDocumentType > 0) {
            documentType = convertedToDocumentType;
        }

        return documentType;
    }

    function isGeneratedToAnotherRow(row, currentType) {
        return isConvertedToAnotherRow(row, currentType);
    }

    function isConvertedToAnotherRow(row, currentType) {
        row = row || {};

        let id = parseInt(row.id || 0);
        let convertedToSaleId = parseInt(row.converted_to_sale_id || 0);
        let convertedToDocumentType = parseInt(row.converted_to_document_type || 0);

        if (convertedToSaleId <= 0) {
            return false;
        }

        /*
         * If API updates same row, converted_to_sale_id may be empty or same id.
         * Only treat as generated/locked when it points to another document row.
         */
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

        let html = `<div class="btn-group btn-group-sm">`;

        if (docPermission(docType, 'view') || docPermission(docType, 'edit')) {
            html += `
                <a class="btn btn-outline-primary"
                   href="${window.BASE_URL}pages/sales.php?id=${id}&mode=edit"
                   title="Edit / View">
                    <i class="mdi mdi-pencil"></i>
                </a>
            `;
        }

        if (parseFloat(row.due_amount || 0) > 0) {
            html += `
                <a class="btn btn-outline-success"
                   href="${window.BASE_URL}pages/customer-payments.php?sales_id=${id}"
                   title="Receive Payment">
                    <i class="mdi mdi-cash-plus"></i>
                </a>
            `;
        }

        if (docPermission(docType, 'print')) {
            html += `
                <a class="btn btn-outline-danger"
                   target="_blank"
                   href="${window.BASE_URL}pages/sales-print.php?id=${id}&print=1"
                   title="Print PDF">
                    <i class="mdi mdi-file-pdf-box"></i>
                </a>
            `;
        }

        if (!converted) {
            if (docType === 1) {
                if (docPermission(1, 'convert') && docPermission(2, 'add')) {
                    html += `
                        <button type="button"
                                class="btn btn-outline-info convert-doc-btn"
                                data-id="${id}"
                                data-source-type="${docType}"
                                data-target-type="2"
                                title="Generate Proforma Bill">
                            <i class="mdi mdi-file-document-plus-outline"></i>
                        </button>
                    `;
                }

                if (docPermission(1, 'convert') && docPermission(3, 'add')) {
                    html += `
                        <button type="button"
                                class="btn btn-outline-success convert-doc-btn"
                                data-id="${id}"
                                data-source-type="${docType}"
                                data-target-type="3"
                                title="Generate Sales Bill">
                            <i class="mdi mdi-receipt-text-plus-outline"></i>
                        </button>
                    `;
                }

                if (docPermission(1, 'generate_invoice') && docPermission(5, 'add')) {
                    html += `
                        <button type="button"
                                class="btn btn-warning convert-doc-btn"
                                data-id="${id}"
                                data-source-type="${docType}"
                                data-target-type="5"
                                title="Generate Final Invoice">
                            <i class="mdi mdi-receipt-text-check-outline"></i>
                        </button>
                    `;
                }
            } else if (docType === 2) {
                if (docPermission(2, 'convert') && docPermission(3, 'add')) {
                    html += `
                        <button type="button"
                                class="btn btn-outline-success convert-doc-btn"
                                data-id="${id}"
                                data-source-type="${docType}"
                                data-target-type="3"
                                title="Generate Sales Bill">
                            <i class="mdi mdi-receipt-text-plus-outline"></i>
                        </button>
                    `;
                }

                if (docPermission(2, 'generate_invoice') && docPermission(5, 'add')) {
                    html += `
                        <button type="button"
                                class="btn btn-warning convert-doc-btn"
                                data-id="${id}"
                                data-source-type="${docType}"
                                data-target-type="5"
                                title="Generate Final Invoice">
                            <i class="mdi mdi-receipt-text-check-outline"></i>
                        </button>
                    `;
                }
            } else if (docType === 3) {
                if (docPermission(3, 'generate_invoice') && docPermission(5, 'add')) {
                    html += `
                        <button type="button"
                                class="btn btn-warning convert-doc-btn"
                                data-id="${id}"
                                data-source-type="${docType}"
                                data-target-type="5"
                                title="Generate Final Invoice">
                            <i class="mdi mdi-receipt-text-check-outline"></i>
                        </button>
                    `;
                }
            }
        }

        html += `</div>`;
        return html;
    }

    function docPermission(typeId, action) {
        typeId = parseInt(typeId || 0);

        /*
         * Flexible permission checker.
         * Supports API payload keys:
         *   permissions[1].convert
         *   permissions[1].can_convert
         *   permissions.sales_quotation.can_convert
         *   permissions.sales_proforma_bill.can_generate_invoice
         *
         * If the permission payload is incomplete, show the button.
         * Backend still validates on save/generate.
         */
        if (!permissions || Object.keys(permissions).length === 0) {
            return true;
        }

        let menuKeys = {
            1: ['1', 1, 'sales_quotation', 'quotation', 'quotation_list'],
            2: ['2', 2, 'sales_proforma_bill', 'proforma_bill', 'proforma_bill_list'],
            3: ['3', 3, 'sales_bill', 'sale_order', 'sales_bill_list'],
            4: ['4', 4, 'sales_direct_sale', 'direct_sale', 'direct_sale_list'],
            5: ['5', 5, 'sales_final_invoice', 'sales_invoice', 'final_invoice_list']
        };

        let actionKeys = [action, 'can_' + action];

        if (action === 'generate_invoice') {
            actionKeys.push('generate');
            actionKeys.push('can_generate');
            actionKeys.push('invoice');
            actionKeys.push('can_invoice');
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

        return true;
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

        if (status === 1) return '<span class="badge bg-success">Active</span>';
        if (status === 2) return '<span class="badge bg-primary">Final</span>';
        if (status === 3) return '<span class="badge bg-danger">Deleted</span>';
        if (status === 4) return '<span class="badge bg-warning text-dark">Cancelled</span>';

        return '<span class="badge bg-secondary">Unknown</span>';
    }

    function formatCurrency(value) {
        return '₹' + parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(date) {
        if (!date) return '-';

        let parts = String(date).split('-');

        if (parts.length !== 3) {
            return escapeHtml(date);
        }

        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }
});
