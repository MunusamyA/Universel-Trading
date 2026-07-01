$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let listConfig = window.SALES_LIST_CONFIG || {};
    let documentType = parseInt(listConfig.document_type || 0);
    let documentTypes = listConfig.document_types || {};
    let permissions = listConfig.permissions || {};

    loadSalesList();
    bindSalesListEvents();

    function bindSalesListEvents() {
        $('#filterBtn').on('click', loadSalesList);

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
            let id = parseInt($(this).data('id') || 0);
            let sourceType = parseInt($(this).data('source-type') || 0);
            let targetType = parseInt($(this).data('target-type') || 0);
            if (id <= 0 || sourceType <= 0 || targetType <= 0) {
                return;
            }

            window.location.href = window.BASE_URL + 'pages/sales.php?mode=convert&source_id=' + id + '&source_type=' + sourceType + '&target_type=' + targetType;
        });
    }

    function loadSalesList() {
        $('#salesListBody').html('<tr><td colspan="11" class="text-center text-muted ">Loading...</td></tr>');

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
                    if (response.data.document_types) {
                        documentTypes = response.data.document_types;
                    }
                    if (response.data.permissions) {
                        permissions = response.data.permissions;
                    }
                    renderSalesList(response.data.rows || []);
                } else {
                    $('#salesListBody').html('<tr><td colspan="11" class="text-center text-danger ">' + escapeHtml(response.message || 'Unable to load list') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#salesListBody').html('<tr><td colspan="11" class="text-center text-danger ">Server error.</td></tr>');
            }
        });
    }

    function renderSalesList(rows) {
        updateCards(rows);

        if (!rows || rows.length === 0) {
            $('#salesListBody').html('<tr><td colspan="11" class="text-center text-muted ">No records found.</td></tr>');
            return;
        }

        let html = '';
        $.each(rows, function (index, row) {
            let docType = parseInt(row.document_type || 0);
            let converted = parseInt(row.conversion_status || 0) === 1 || parseInt(row.converted_to_sale_id || 0) > 0;

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

    function actionButtons(row, converted) {
        let id = parseInt(row.id || 0);
        let docType = parseInt(row.document_type || 0);
        let html = `<div class="btn-group btn-group-sm">
            <a class="btn btn-outline-primary" href="${window.BASE_URL}pages/sales.php?id=${id}&mode=edit" title="Edit / View"><i class="mdi mdi-pencil"></i></a>`;

        if (docPermission(docType, 'print')) {
            html += `<a class="btn btn-outline-secondary" target="_blank" href="${window.BASE_URL}pages/sales-print.php?id=${id}" title="Print"><i class="mdi mdi-printer"></i></a>`;
        }

        if (!converted) {
            if (docType === 1) {
                if (docPermission(1, 'convert') && docPermission(2, 'add')) {
                    html += `<button type="button" class="btn btn-outline-info convert-doc-btn" data-id="${id}" data-source-type="1" data-target-type="2" title="Convert to Proforma"><i class="mdi mdi-file-earmark-text"></i></button>`;
                }
                if (docPermission(1, 'convert') && docPermission(3, 'add')) {
                    html += `<button type="button" class="btn btn-outline-success convert-doc-btn" data-id="${id}" data-source-type="1" data-target-type="3" title="Convert to Sales Bill"><i class="mdi mdi-receipt"></i></button>`;
                }
                if (docPermission(1, 'generate_invoice') && docPermission(5, 'add')) {
                    html += `<button type="button" class="btn btn-warning convert-doc-btn" data-id="${id}" data-source-type="1" data-target-type="5" title="Generate Invoice"><i class="mdi mdi-receipt-cutoff"></i></button>`;
                }
            } else if (docType === 2) {
                if (docPermission(2, 'convert') && docPermission(3, 'add')) {
                    html += `<button type="button" class="btn btn-outline-success convert-doc-btn" data-id="${id}" data-source-type="2" data-target-type="3" title="Convert to Sales Bill"><i class="mdi mdi-receipt"></i></button>`;
                }
                if (docPermission(2, 'generate_invoice') && docPermission(5, 'add')) {
                    html += `<button type="button" class="btn btn-warning convert-doc-btn" data-id="${id}" data-source-type="2" data-target-type="5" title="Generate Invoice"><i class="mdi mdi-receipt-cutoff"></i></button>`;
                }
            } else if (docType === 3) {
                if (docPermission(3, 'generate_invoice') && docPermission(5, 'add')) {
                    html += `<button type="button" class="btn btn-warning convert-doc-btn" data-id="${id}" data-source-type="3" data-target-type="5" title="Generate Invoice"><i class="mdi mdi-receipt-cutoff"></i></button>`;
                }
            }
        }

        html += `</div>`;
        return html;
    }

    function docPermission(typeId, action) {
        return !!(permissions[typeId] && permissions[typeId][action]);
    }

    function documentLabel(typeId) {
        return documentTypes[typeId] && documentTypes[typeId].label ? documentTypes[typeId].label : 'Document';
    }

    function statusBadge(status, converted) {
        if (converted) {
            return '<span class="badge bg-info">Converted</span>';
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
        if (parts.length !== 3) return escapeHtml(date);
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }
});
