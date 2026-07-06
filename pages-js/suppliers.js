$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let supplierModal = new bootstrap.Modal(document.getElementById('supplierModal'));
    let searchTimer = null;

    let pageContext = {
        can_view: false,
        can_list: false,
        can_add: false,
        can_edit: false,
        can_delete: false,
        can_supplier_payment: false,
        page_title: 'Suppliers',
        page_note: '',
        add_button_label: 'Add Supplier',
        add_modal_title: 'Add Supplier',
        edit_modal_title: 'Edit Supplier',
        supplier_payment_url: ''
    };

    loadPageContext();

    $('#addSupplierBtn').on('click', function () {
        if (!pageContext.can_add) {
            showAppToast('error', 'Permission denied.');
            return;
        }

        resetSupplierForm();
        $('#supplierModalTitle').text(pageContext.add_modal_title || 'Add Supplier');
        supplierModal.show();
    });

    $('#refreshSuppliersBtn').on('click', function () {
        loadSuppliers();
    });

    $('#supplierStatusFilter').on('change', function () {
        loadSuppliers();
    });

    $('#supplierSearch').on('keyup', function () {
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function () {
            loadSuppliers();
        }, 400);
    });

    $(document).on('input', '.text-uppercase', function () {
        $(this).val($(this).val().toUpperCase());
    });

    $('#supplierForm').on('submit', function (e) {
        e.preventDefault();

        let supplierId = parseInt($('#supplier_id').val() || 0);

        if (supplierId > 0 && !pageContext.can_edit) {
            showAppToast('error', 'Permission denied.');
            return;
        }

        if (supplierId <= 0 && !pageContext.can_add) {
            showAppToast('error', 'Permission denied.');
            return;
        }

        if ($.trim($('#supplier_name').val()) === '') {
            showAppToast('warning', 'Please enter supplier name.');
            $('#supplier_name').focus();
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

        let ifsc = $.trim($('#bank_ifsc').val()).toUpperCase();

        if (ifsc !== '' && !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc)) {
            showAppToast('warning', 'Please enter valid IFSC code.');
            $('#bank_ifsc').focus();
            return;
        }

        let openingOutstanding = parseFloat($('#opening_outstanding').val() || 0);

        if (openingOutstanding < 0) {
            showAppToast('warning', 'Opening outstanding cannot be negative.');
            $('#opening_outstanding').focus();
            return;
        }

        setButtonLoading('saveSupplierBtn', 'Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: $('#supplierForm').serialize() + '&action=save_supplier',
            success: function (response) {
                if (response.status === true) {
                    showAppToast('success', response.message || 'Supplier saved.');
                    supplierModal.hide();
                    loadSuppliers();
                } else {
                    handleApiError(response);
                }

                resetButtonLoading('saveSupplierBtn');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error. Please try again.');
                resetButtonLoading('saveSupplierBtn');
            }
        });
    });

    $(document).on('click', '.edit-supplier-btn', function () {
        if (!pageContext.can_edit) {
            showAppToast('error', 'Permission denied.');
            return;
        }

        let supplierId = $(this).data('id');
        loadSupplierForEdit(supplierId);
    });

    $(document).on('click', '.delete-supplier-btn', function () {
        if (!pageContext.can_delete) {
            showAppToast('error', 'Permission denied.');
            return;
        }

        let supplierId = $(this).data('id');

        if (!confirm('Are you sure you want to delete this supplier?')) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_supplier',
                supplier_id: supplierId,
                csrf_token: $('input[name="csrf_token"]').first().val()
            },
            success: function (response) {
                if (response.status === true) {
                    showAppToast('success', response.message || 'Supplier deleted.');
                    loadSuppliers();
                } else {
                    handleApiError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error. Please try again.');
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
                } else {
                    $('#supplierTableBody').html('<tr><td colspan="9" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#addSupplierBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#supplierTableBody').html('<tr><td colspan="9" class="text-center text-danger">Server error.</td></tr>');
                $('#addSupplierBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Suppliers');
        $('#pageNoteText').text(pageContext.page_note || '');
        $('#addSupplierBtnText').text(pageContext.add_button_label || 'Add Supplier');

        if (pageContext.can_add) {
            $('#addSupplierBtn').removeClass('d-none');
        } else {
            $('#addSupplierBtn').addClass('d-none');
        }
    }

    function loadSuppliers() {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#supplierTableBody').html('<tr><td colspan="9" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $('#supplierTableBody').html('<tr><td colspan="9" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_suppliers',
                search: $('#supplierSearch').val(),
                status: $('#supplierStatusFilter').val()
            },
            success: function (response) {
                if (response.status === true) {
                    renderSupplierRows(response.data.suppliers || []);
                    renderStats(response.data.stats || {});
                } else {
                    $('#supplierTableBody').html('<tr><td colspan="9" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load suppliers.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#supplierTableBody').html('<tr><td colspan="9" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderSupplierRows(suppliers) {
        if (!suppliers || suppliers.length === 0) {
            $('#supplierTableBody').html('<tr><td colspan="9" class="text-center text-muted">No suppliers found.</td></tr>');
            return;
        }

        let html = '';

        $.each(suppliers, function (index, supplier) {
            let actionHtml = '';

            if (supplier.can_supplier_payment) {
                actionHtml += '<a class="btn btn-outline-success btn-sm" href="' + supplierPaymentUrl(supplier.id) + '" title="Supplier Payment"><i class="mdi mdi-cash-multiple"></i></a>';
            }

            if (supplier.can_edit) {
                actionHtml += '<button type="button" class="btn btn-outline-primary btn-sm edit-supplier-btn ms-1" data-id="' + supplier.id + '" title="Edit"><i class="mdi mdi-pencil"></i></button>';
            }

            if (supplier.can_delete) {
                actionHtml += '<button type="button" class="btn btn-outline-danger btn-sm delete-supplier-btn ms-1" data-id="' + supplier.id + '" title="Delete"><i class="mdi mdi-delete"></i></button>';
            }

            if (actionHtml === '') {
                actionHtml = '<span class="text-muted">No access</span>';
            }

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td>';
            html += '<h6 class="mb-0">' + escapeHtml(supplier.supplier_name || '') + '</h6>';
            html += '<small class="text-muted">' + escapeHtml(formatAddress(supplier)) + '</small>';
            html += '</td>';
            html += '<td>';
            html += '<div>' + escapeHtml(supplier.mobile || '-') + '</div>';
            html += '<small class="text-muted">' + escapeHtml(supplier.email || '') + '</small>';
            html += '</td>';
            html += '<td>';
            html += '<div>' + escapeHtml(supplier.gst_number || '-') + '</div>';
            html += '<small class="text-muted">PAN: ' + escapeHtml(supplier.pan_number || '-') + '</small>';
            html += '</td>';
            html += '<td>';
            html += '<div>DL: ' + escapeHtml(supplier.dl_number || '-') + '</div>';
            html += '<small class="text-muted">FL: ' + escapeHtml(supplier.fl_number || '-') + '</small>';
            html += '</td>';
            html += '<td>';
            html += '<div>' + escapeHtml(supplier.bank_name || '-') + '</div>';
            html += '<small class="text-muted">' + escapeHtml(supplier.bank_ifsc || '') + '</small>';
            html += '</td>';
            html += '<td><strong>' + formatCurrency(supplier.current_outstanding) + '</strong></td>';
            html += '<td>' + statusBadge(supplier.status) + '</td>';
            html += '<td>' + actionHtml + '</td>';
            html += '</tr>';
        });

        $('#supplierTableBody').html(html);
    }

    function supplierPaymentUrl(supplierId) {
        let baseUrl = pageContext.supplier_payment_url || (window.BASE_URL + 'pages/supplier-payments.php');
        return baseUrl + '?supplier_id=' + supplierId;
    }

    function loadSupplierForEdit(supplierId) {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_supplier',
                supplier_id: supplierId
            },
            success: function (response) {
                if (response.status === true) {
                    let supplier = response.data.supplier;

                    resetSupplierForm();

                    $('#supplierModalTitle').text(pageContext.edit_modal_title || 'Edit Supplier');

                    $('#supplier_id').val(supplier.id);
                    $('#supplier_name').val(supplier.supplier_name);
                    $('#mobile').val(supplier.mobile);
                    $('#email').val(supplier.email);
                    $('#gst_number').val(supplier.gst_number);
                    $('#pan_number').val(supplier.pan_number);
                    $('#dl_number').val(supplier.dl_number);
                    $('#fl_number').val(supplier.fl_number);

                    $('#address').val(supplier.address);
                    $('#city').val(supplier.city);
                    $('#state').val(supplier.state);
                    $('#pincode').val(supplier.pincode);

                    $('#bank_name').val(supplier.bank_name);
                    $('#bank_account_no').val(supplier.bank_account_no);
                    $('#bank_branch').val(supplier.bank_branch);
                    $('#bank_ifsc').val(supplier.bank_ifsc);
                    $('#upi_id').val(supplier.upi_id);

                    $('#opening_outstanding').val(parseFloat(supplier.opening_outstanding || 0).toFixed(2));
                    $('#status1').val(supplier.status);

                    supplierModal.show();
                } else {
                    handleApiError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error. Please try again.');
            }
        });
    }

    function resetSupplierForm() {
        $('#supplierForm')[0].reset();
        $('#supplier_id').val('');
        $('#state').val('Tamil Nadu');
        $('#opening_outstanding').val('0.00');
        $('#status1').val('1');
        $('#saveSupplierBtn').html('Save Supplier').prop('disabled', false);
    }

    function renderStats(stats) {
        $('#totalSuppliersCount').text(stats.total_suppliers || 0);
        $('#activeSuppliersCount').text(stats.active_suppliers || 0);
        $('#inactiveSuppliersCount').text(stats.inactive_suppliers || 0);
        $('#totalOutstandingAmount').text(formatCurrency(stats.total_outstanding || 0));
    }

    function formatAddress(supplier) {
        let parts = [];

        if (supplier.address) {
            parts.push(supplier.address);
        }

        if (supplier.city) {
            parts.push(supplier.city);
        }

        if (supplier.state) {
            parts.push(supplier.state);
        }

        if (supplier.pincode) {
            parts.push(supplier.pincode);
        }

        return parts.join(', ');
    }

    function statusBadge(status) {
        status = parseInt(status);

        if (status === 1) {
            return '<span class="badge bg-success">Active</span>';
        }

        if (status === 2) {
            return '<span class="badge bg-danger">Inactive</span>';
        }

        return '<span class="badge bg-warning">Unknown</span>';
    }

    function formatCurrency(value) {
        let numberValue = parseFloat(value || 0);

        return '₹' + numberValue.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function setButtonLoading(buttonId, text) {
        $('#' + buttonId).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>' + text);
    }

    function resetButtonLoading(buttonId) {
        $('#' + buttonId).prop('disabled', false).html('Save Supplier');
    }

    function showAppToast(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        if (typeof showToastSafe === 'function') {
            showToastSafe(type, message);
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

});
