$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let paymentModal = null;
    let selectedCustomer = null;
    let selectedSale = null;
    let paymentModes = [];
    let customers = [];
    let overallCustomerSummary = {};
    let customerDueDocuments = [];

    /*
     * Direct page payment entry:
     * customer_id or sales_id in the URL pre-fills the same visible payment entry card.
     * No modal popup is used.
     */

    let pageContext = {
        can_view: false,
        can_list: false,
        can_receive_payment: false,
        can_edit: false,
        can_cancel: false,
        can_sales_list: false,
        page_title: 'Customer Payments',
        page_note: '',
        new_payment_label: 'New Payment',
        sales_list_url: ''
    };

    loadPageContext();

    $('#refreshPaymentListBtn').on('click', function () {
        loadPaymentsList();
    });

    $('#resetPaymentBtn').on('click', function () {
        openNewPaymentModal();
    });

    $('#paymentType').on('change', function () {
        $('#paymentTypeHidden').val($(this).val() || '');
        toggleIndividualDocumentBox();
        applyPaymentTypeAmount(true);
    });

    $('#paymentCustomerSearch').on('input keyup', function () {
        $('#paymentCustomerId').val('');
        renderCustomerOptions(0, true);
    });

    $('#paymentCustomerSearch').on('focus', function () {
        renderCustomerOptions(parseInt($('#paymentCustomerId').val() || getActiveCustomerId() || 0), true);
    });

    $('#clearCustomerSearchBtn').on('click', function () {
        clearCustomerSearch();
    });

    $(document).on('mousedown', '.payment-customer-option', function (e) {
        e.preventDefault();
        let customerId = parseInt($(this).data('id') || 0);
        selectCustomerForPayment(customerId, true);
    });

    $(document).on('click', function (e) {
        if ($(e.target).closest('#paymentCustomerBox').length === 0) {
            $('#paymentCustomerDropdown').addClass('d-none');
        }
    });

    $('#individualSalesSelect').on('change', function () {
        let saleId = parseInt($(this).val() || 0);
        $('#paymentSalesId').val(saleId || '');

        /*
         * When document changes, Total Split Amount must immediately change
         * to that selected document due amount.
         */
        applyPaymentTypeAmount(true);
    });

    $('#addSplitRowBtn').on('click', function () {
        addSplitRow('', '', '');
    });

    $(document).on('click', '.remove-split-row-btn', function () {
        $(this).closest('tr').remove();
        recalculateSplitTotal();
    });

    $(document).on('change keyup', '.split-mode, .split-amount, .split-reference', function () {
        recalculateSplitTotal();
    });

    $('#paymentSearch').on('keyup', function (e) {
        if (e.key === 'Enter') {
            loadPaymentsList();
        }
    });

    $('#newPaymentBtn').on('click', function () {
        if (!pageContext.can_receive_payment) {
            showPaymentToast('error', 'Permission denied.');
            return;
        }

        openNewPaymentModal();
    });

    $(document).on('click', '.edit-payment-btn', function () {
        if (!pageContext.can_edit) {
            showPaymentToast('error', 'Permission denied.');
            return;
        }

        loadPaymentForEdit(parseInt($(this).data('id') || 0));
    });

    $(document).on('click', '.cancel-payment-btn', function () {
        if (!pageContext.can_cancel) {
            showPaymentToast('error', 'Permission denied.');
            return;
        }

        let id = parseInt($(this).data('id') || 0);

        if (!id) {
            return;
        }

        let reason = prompt('Reason for cancel / reverse payment?');

        if (reason === null) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cancel_customer_payment',
                csrf_token: $('input[name="csrf_token"]').first().val(),
                id: id,
                reason: reason
            },
            success: function (response) {
                if (response.status === true) {
                    showPaymentToast('success', response.message || 'Payment cancelled.');
                    loadPaymentPage(false);
                } else {
                    showPaymentToast('error', response.message || 'Unable to cancel payment.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showPaymentToast('error', 'Server error.');
            }
        });
    });

    $('#paymentForm').on('submit', function (e) {
        e.preventDefault();

        let paymentId = parseInt($('#paymentId').val() || 0);

        if (paymentId > 0 && !pageContext.can_edit) {
            showPaymentToast('error', 'Permission denied.');
            return;
        }

        if (paymentId <= 0 && !pageContext.can_receive_payment) {
            showPaymentToast('error', 'Permission denied.');
            return;
        }

        let formCustomerId = parseInt($('#paymentCustomerId').val() || getActiveCustomerId() || 0);

        if (formCustomerId <= 0) {
            showPaymentToast('warning', 'Please select customer.');
            $('#paymentCustomerSearch').focus();
            return;
        }

        $('#paymentCustomerId').val(formCustomerId);

        if (parseInt($('#paymentType').val() || 0) === 1) {
            let selectedDocId = parseInt($('#individualSalesSelect').val() || $('#paymentSalesId').val() || 0);

            if (selectedDocId <= 0) {
                showPaymentToast('warning', 'Select particular quotation / proforma / sales bill / invoice.');
                $('#individualSalesSelect').focus();
                return;
            }

            $('#paymentSalesId').val(selectedDocId);
        }

        let splits = collectSplitRows();
        let amount = getSplitTotal();

        if (!splits.length || amount <= 0) {
            showPaymentToast('warning', 'Add at least one payment split.');
            return;
        }

        $('#splitPaymentsJson').val(JSON.stringify(splits));
        $('#paymentAmount').val(amount.toFixed(2));
        $('#paymentTypeHidden').val($('#paymentType').val() || '');

        setBtnLoading('#savePaymentBtn', 'Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: $('#paymentForm').serialize() + '&action=save_customer_payment',
            success: function (response) {
                resetBtnLoading('#savePaymentBtn', 'Save Payment');

                if (response.status === true) {
                    showPaymentToast('success', response.message || 'Payment saved.');
                    loadPaymentPage(false);
                } else {
                    showPaymentToast('error', response.message || 'Payment failed.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                resetBtnLoading('#savePaymentBtn', 'Save Payment');
                showPaymentToast('error', 'Server error.');
            }
        });
    });

    function loadPageContext() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'customer_payments_page_context'
            },
            success: function (response) {
                if (response.status === true) {
                    pageContext = response.data.context || pageContext;
                    applyPageContext();
                    loadPaymentPage(true);
                } else {
                    $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#newPaymentBtn').addClass('d-none');
                    $('#salesListBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>');
                $('#newPaymentBtn').addClass('d-none');
                $('#salesListBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Customer Payments');
        $('#pageNoteText').text(pageContext.page_note || '');
        $('#newPaymentBtnText').text(pageContext.new_payment_label || 'New Payment');

        if (pageContext.sales_list_url) {
            $('#salesListBtn').attr('href', pageContext.sales_list_url);
        }

        if (pageContext.can_sales_list) {
            $('#salesListBtn').removeClass('d-none');
        } else {
            $('#salesListBtn').addClass('d-none');
        }

        $('#newPaymentBtn').addClass('d-none');

        if (pageContext.can_receive_payment || pageContext.can_edit) {
            $('#paymentEntryCard').removeClass('d-none');
        } else {
            $('#paymentEntryCard').addClass('d-none');
        }
    }

    function loadPaymentPage(allowAutoOpen) {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'payment_page_init',
                customer_id: $('#pageCustomerId').val() || 0,
                sales_id: $('#pageSalesId').val() || 0
            },
            success: function (response) {
                if (response.status === true) {
                    paymentModes = response.data.payment_modes || [];
                    customers = response.data.customers || customers || [];
                    overallCustomerSummary = response.data.overall_summary || overallCustomerSummary || {};
                    selectedCustomer = response.data.selected_customer || null;
                    selectedSale = response.data.selected_sale || null;
                    syncSelectedIds();

                    renderPaymentModes();
                    renderCustomerOptions(getActiveCustomerId());
                    renderHeaderCards();
                    renderSelectedSale();

                    /*
                     * Initial page load should show latest payment list automatically.
                     * Earlier it showed only payment_page_init payments; when the page
                     * opened without customer_id, the list appeared only after Refresh.
                     */
                    if (pageContext.can_list) {
                        loadPaymentsList();
                    } else {
                        renderPayments(response.data.payments || []);
                    }

                    loadCustomerDueDocuments(function () {
                        if (pageContext.can_receive_payment && parseInt($('#paymentId').val() || 0) <= 0) {
                            openNewPaymentModal();
                        }
                    });
                } else {
                    showPaymentToast('error', response.message || 'Unable to load payment page.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showPaymentToast('error', 'Server error.');
            }
        });
    }

    function customerDisplayLabel(customer) {
        if (!customer) {
            return '';
        }

        let label = customer.customer_name || 'Customer';
        if (customer.mobile) {
            label += ' - ' + customer.mobile;
        }
        return label;
    }

    function findCustomerById(customerId) {
        customerId = parseInt(customerId || 0);
        return (customers || []).find(function (customer) {
            return parseInt(customer.id || 0) === customerId;
        }) || null;
    }

    function setCustomerInputText(customer) {
        $('#paymentCustomerSearch').val(customerDisplayLabel(customer));
    }

    function clearCustomerSearch() {
        selectCustomerForPayment(0, true);
        $('#paymentCustomerSearch').blur();
        $('#paymentCustomerDropdown').addClass('d-none').empty();
    }

    function renderCustomerOptions(selectedId, forceShow) {
        selectedId = parseInt(selectedId || 0);
        let searchText = ($('#paymentCustomerSearch').val() || '').toLowerCase().trim();
        let html = '';
        let shown = 0;

        $.each(customers || [], function (_, customer) {
            let customerId = parseInt(customer.id || 0);
            let rawLabel = customerDisplayLabel(customer);

            if (searchText !== '' && rawLabel.toLowerCase().indexOf(searchText) === -1 && customerId !== selectedId) {
                return;
            }

            if (shown >= 30 && customerId !== selectedId) {
                return;
            }

            html += '<button type="button" class="list-group-item list-group-item-action payment-customer-option" data-id="' + customerId + '">' +
                '<div class="fw-semibold">' + escapeHtml(customer.customer_name || 'Customer') + '</div>' +
                '<small class="text-muted">' + escapeHtml(customer.mobile || '') + '</small>' +
                '</button>';
            shown++;
        });

        if (html === '') {
            html = '<div class="list-group-item text-muted">No customer found.</div>';
        }

        $('#paymentCustomerDropdown').html(html);

        if (forceShow === true) {
            $('#paymentCustomerDropdown').removeClass('d-none');
        } else {
            $('#paymentCustomerDropdown').addClass('d-none');
        }
    }

    function selectCustomerForPayment(customerId, resetPayment) {
        customerId = parseInt(customerId || 0);

        selectedSale = null;
        $('#pageSalesId').val('0');
        $('#paymentSalesId').val('');
        renderSelectedSale();

        if (customerId <= 0) {
            selectedCustomer = null;
            $('#pageCustomerId').val('0');
            $('#paymentCustomerId').val('');
            $('#paymentCustomerSearch').val('');
            $('#paymentCustomerDropdown').addClass('d-none');
            customerDueDocuments = [];
            renderHeaderCards();
            renderIndividualDocumentOptions();
            $('#paymentInfoBox').html('Select customer to receive payment.');
            resetSplitRows('');
            applyPaymentTypeAmount(true);
            loadPaymentsList();
            return;
        }

        $('#pageCustomerId').val(customerId);
        $('#paymentCustomerId').val(customerId);

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'payment_page_init',
                customer_id: customerId,
                sales_id: 0
            },
            success: function (response) {
                if (response.status === true) {
                    customers = response.data.customers || customers || [];
                    paymentModes = response.data.payment_modes || paymentModes || [];
                    overallCustomerSummary = response.data.overall_summary || overallCustomerSummary || {};
                    selectedCustomer = response.data.selected_customer || null;
                    selectedSale = null;
                    setCustomerInputText(selectedCustomer || findCustomerById(customerId));
                    $('#paymentCustomerDropdown').addClass('d-none');
                    renderCustomerOptions(customerId, false);
                    renderPaymentModes();
                    renderHeaderCards();
                    renderSelectedSale();
                    loadPaymentsList();
                    loadCustomerDueDocuments(function () {
                        if (resetPayment === true) {
                            $('#paymentType').val('2').prop('disabled', false);
                            $('#paymentTypeHidden').val('2');
                            toggleIndividualDocumentBox();
                            applyPaymentTypeAmount(true);
                        }
                    });
                } else {
                    showPaymentToast('error', response.message || 'Unable to load customer.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showPaymentToast('error', 'Server error.');
            }
        });
    }

    function syncSelectedIds() {
        if (selectedCustomer && selectedCustomer.id) {
            $('#pageCustomerId').val(selectedCustomer.id);
        }

        if (selectedSale && selectedSale.id) {
            $('#pageSalesId').val(selectedSale.id);
        }
    }

    function getActiveCustomerId() {
        if (selectedCustomer && selectedCustomer.id) {
            return parseInt(selectedCustomer.id || 0);
        }

        if (selectedSale && selectedSale.customer_id) {
            return parseInt(selectedSale.customer_id || 0);
        }

        return parseInt($('#pageCustomerId').val() || 0);
    }

    function getActiveSalesId() {
        if (selectedSale && selectedSale.id) {
            return parseInt(selectedSale.id || 0);
        }

        return parseInt($('#pageSalesId').val() || 0);
    }

    function shouldAutoOpenSelectedSalePayment() {
        if (!pageContext.can_receive_payment) {
            return false;
        }

        let urlSalesId = parseInt($('#pageSalesId').val() || 0);
        let urlCustomerId = parseInt($('#pageCustomerId').val() || 0);

        return urlSalesId > 0 || urlCustomerId > 0;
    }

    function loadPaymentsList() {
        if (!pageContext.can_list) {
            $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_customer_payments',
                customer_id: getActiveCustomerId(),
                sales_id: getActiveSalesId(),
                search: $('#paymentSearch').val() || ''
            },
            success: function (response) {
                if (response.status === true) {
                    renderPayments(response.data.rows || []);
                } else {
                    $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function loadCustomerDueDocuments(callback) {
        let customerId = 0;

        if (selectedSale && selectedSale.customer_id) {
            customerId = parseInt(selectedSale.customer_id || 0);
        } else if (selectedCustomer && selectedCustomer.id) {
            customerId = parseInt(selectedCustomer.id || 0);
        } else {
            customerDueDocuments = [];
            renderIndividualDocumentOptions();

            if (callback) {
                callback();
            }

            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_customer_due_documents',
                customer_id: customerId,
                include_paid: getActiveSalesId() > 0 ? 1 : 0
            },
            success: function (response) {
                if (response.status === true) {
                    customerDueDocuments = response.data.rows || [];
                    renderIndividualDocumentOptions();
                }

                if (callback) {
                    callback();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                customerDueDocuments = [];
                renderIndividualDocumentOptions();

                if (callback) {
                    callback();
                }
            }
        });
    }

    function renderIndividualDocumentOptions(selectedId) {
        let html = '<option value="">Select quotation / proforma / sales bill / invoice</option>';

        $.each(customerDueDocuments || [], function (_, doc) {
            let selected = parseInt(doc.id) === parseInt(selectedId || 0) ? 'selected' : '';

            html += '<option value="' + doc.id + '" ' + selected + '>' +
                escapeHtml(doc.document_label || 'Document') + ' - ' +
                escapeHtml(doc.sales_no || '') + ' | Due: ' +
                formatCurrency(doc.due_amount || 0) +
                '</option>';
        });

        $('#individualSalesSelect').html(html);
    }

    function getSelectedPaymentDocument() {
        let saleId = parseInt($('#individualSalesSelect').val() || $('#paymentSalesId').val() || 0);

        if (saleId <= 0) {
            return null;
        }

        let doc = (customerDueDocuments || []).find(function (item) {
            return parseInt(item.id || 0) === saleId;
        });

        if (!doc && selectedSale && parseInt(selectedSale.id || 0) === saleId) {
            doc = selectedSale;
        }

        return doc || null;
    }

    function applyPaymentTypeAmount(resetRows) {
        let type = parseInt($('#paymentType').val() || 0);
        let amount = 0;

        /*
         * 1 = selected individual document due
         * 2 = sales bill due FIFO first + opening due last
         * 3 = opening due only
         */
        if (type === 1) {
            let doc = getSelectedPaymentDocument();

            if (doc) {
                amount = parseFloat(doc.due_amount || 0);

                $('#paymentSalesId').val(doc.id || '');
                $('#individualSalesSelect').val(String(doc.id || ''));
                $('#individualDocumentInfo').html(
                    escapeHtml(doc.document_label || 'Document') + ' ' +
                    escapeHtml(doc.sales_no || '') +
                    ' | Due: <strong>' + formatCurrency(amount) + '</strong>'
                );

                $('#paymentInfoBox').html(
                    '<strong>Individual Document Payment:</strong> ' +
                    escapeHtml(doc.document_label || 'Document') + ' ' +
                    escapeHtml(doc.sales_no || '') +
                    ' | Due: <strong>' + formatCurrency(amount) + '</strong>'
                );
            } else {
                $('#paymentSalesId').val('');
                $('#individualDocumentInfo').text('');
                $('#paymentInfoBox').html('Select particular document to receive payment.');
            }
        } else if (type === 2) {
            if (!selectedCustomer) {
                $('#paymentInfoBox').html('Select customer to receive payment.');
                amount = 0;
            }

            let salesDue = selectedCustomer ? parseFloat(selectedCustomer.sales_due || 0) : 0;
            let openingDue = selectedCustomer ? parseFloat(selectedCustomer.opening_due || 0) : 0;

            amount = selectedCustomer ? (salesDue + openingDue) : 0;

            $('#paymentSalesId').val('');
            $('#individualSalesSelect').val('');
            $('#individualDocumentInfo').text('');

            $('#paymentInfoBox').html(
                '<strong>Overall FIFO Payment:</strong> Bill Due ' +
                formatCurrency(salesDue) + ' + Opening Due ' + formatCurrency(openingDue) +
                ' = <strong>' + formatCurrency(amount) + '</strong><br>' +
                '<small>Allocation: oldest bill due first, opening due last.</small>'
            );
        } else if (type === 3) {
            if (!selectedCustomer) {
                $('#paymentInfoBox').html('Select customer to receive payment.');
            }

            amount = selectedCustomer ? parseFloat(selectedCustomer.opening_due || 0) : 0;

            $('#paymentSalesId').val('');
            $('#individualSalesSelect').val('');
            $('#individualDocumentInfo').text('');

            $('#paymentInfoBox').html(
                '<strong>Opening Balance Payment:</strong> Opening Due <strong>' +
                formatCurrency(amount) + '</strong>'
            );
        }

        amount = amount > 0 ? amount : 0;

        if (resetRows === true) {
            resetSplitRows(amount > 0 ? amount.toFixed(2) : '');
        } else {
            recalculateSplitTotal();
        }

        $('#paymentAmount').val(amount.toFixed(2));
        $('#splitTotalText').text(formatCurrency(amount));
    }

    function toggleIndividualDocumentBox() {
        let type = parseInt($('#paymentType').val() || 0);

        if (type === 1) {
            $('#individualDocumentBox').show();
        } else {
            $('#individualDocumentBox').hide();

            if (!selectedSale) {
                $('#paymentSalesId').val('');
                $('#individualSalesSelect').val('');
                $('#individualDocumentInfo').text('');
            }
        }
    }

    function renderHeaderCards() {
        let summary = selectedCustomer || overallCustomerSummary || {};
        let openingDue = parseFloat(summary.opening_due || 0);
        let billAmount = parseFloat(summary.sales_total || 0);
        let paidAmount = parseFloat(summary.sales_paid || 0);
        let dueAmount = parseFloat(summary.sales_due || 0);
        let totalPending = parseFloat(summary.total_pending || summary.total_receivable || (openingDue + dueAmount));

        $('#openingDueCard').text(formatCurrency(openingDue));
        $('#billAmountCard').text(formatCurrency(billAmount));
        $('#paidAmountCard').text(formatCurrency(paidAmount));
        $('#dueAmountCard').text(formatCurrency(dueAmount));
        $('#totalReceivableCard').text(formatCurrency(totalPending));

        if (!selectedCustomer) {
            $('#selectedCustomerInfo').addClass('d-none');
            $('#selectedCustomerName').text('-');
            return;
        }

        $('#selectedCustomerName').text(selectedCustomer.customer_name || '-');
        $('#selectedCustomerInfo').removeClass('d-none');
    }

    function renderSelectedSale() {
        if (!selectedSale) {
            $('#selectedSaleCard').hide();
            return;
        }

        $('#selectedSaleCard').show();
        $('#selectedSalesNo').text(selectedSale.sales_no || '-');
        $('#selectedSalesTotal').text(formatCurrency(selectedSale.grand_total || 0));
        $('#selectedSalesPaid').text(formatCurrency(selectedSale.paid_amount || 0));
        $('#selectedSalesDue').text(formatCurrency(selectedSale.due_amount || 0));
    }

    function renderPayments(rows) {
        if (!rows || rows.length === 0) {
            $('#paymentsTableBody').html('<tr><td colspan="10" class="text-center text-muted">No payments found.</td></tr>');
            return;
        }

        let html = '';

        $.each(rows, function (index, row) {
            let actionHtml = '';

            if (parseInt(row.status || 0) === 1 && pageContext.can_edit) {
                actionHtml += '<button type="button" class="btn btn-outline-primary btn-sm edit-payment-btn" data-id="' + row.id + '" title="Edit"><i class="mdi mdi-pencil"></i></button>';
            }

            if (parseInt(row.status || 0) === 1 && pageContext.can_cancel) {
                actionHtml += '<button type="button" class="btn btn-outline-danger btn-sm ms-1 cancel-payment-btn" data-id="' + row.id + '" title="Cancel / Reverse"><i class="mdi mdi-close-circle"></i></button>';
            }

            if (actionHtml === '') {
                actionHtml = '<span class="text-muted">No access</span>';
            }

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(row.payment_no || '') + '</strong></td>';
            html += '<td>' + formatDate(row.payment_date) + '</td>';
            html += '<td><strong>' + escapeHtml(row.customer_name || '') + '</strong><br><small class="text-muted">' + escapeHtml(row.customer_mobile || '') + '</small></td>';
            html += '<td>' + paymentTypeBadge(row.payment_type) + '</td>';
            html += '<td>' + escapeHtml(row.mode_name || '') + (row.split_summary ? '<br><small class="text-muted">' + escapeHtml(row.split_summary) + '</small>' : '') + '</td>';
            html += '<td>' + (row.sales_no ? '<strong>' + escapeHtml(row.sales_no) + '</strong><br><small class="text-muted">' + escapeHtml(row.document_label || '') + '</small>' : '-') + '</td>';
            html += '<td class="text-end">' + formatCurrency(row.amount || 0) + '</td>';
            html += '<td>' + paymentStatusBadge(row.status) + '</td>';
            html += '<td class="text-end">' + actionHtml + '</td>';
            html += '</tr>';
        });

        $('#paymentsTableBody').html(html);
    }

    function openNewPaymentModal() {
        if (!pageContext.can_receive_payment) {
            showPaymentToast('error', 'Permission denied.');
            return;
        }

        $('#paymentForm')[0].reset();
        $('#paymentId').val('');
        $('#paymentDate').val(currentDate());
        $('#paymentType').prop('disabled', false);
        $('#paymentTypeHidden').val($('#paymentType').val() || '');

        if (selectedSale) {
            renderCustomerOptions(selectedSale.customer_id, false);
            setCustomerInputText({customer_name: selectedSale.customer_name || selectedSale.customer_display_name || 'Customer', mobile: selectedSale.customer_mobile || ''});
            $('#paymentCustomerSearch').prop('disabled', true);
            $('#paymentCustomerId').val(selectedSale.customer_id);
            $('#paymentSalesId').val(selectedSale.id);
            $('#paymentType').val('1').prop('disabled', true);
            $('#paymentTypeHidden').val('1');
            $('#individualSalesSelect').val(String(selectedSale.id || ''));
            $('#paymentSalesId').val(selectedSale.id || '');
            $('#individualDocumentInfo').html(
                escapeHtml(selectedSale.document_label || 'Document') + ' ' +
                escapeHtml(selectedSale.sales_no || '') +
                ' | Due: <strong>' + formatCurrency(selectedSale.due_amount || 0) + '</strong>'
            );

            resetSplitRows(parseFloat(selectedSale.due_amount || 0).toFixed(2));

            $('#paymentInfoBox').html(
                '<strong>' + escapeHtml(selectedSale.customer_name || '') + '</strong> - ' +
                escapeHtml(selectedSale.document_label || 'Document') + ' ' +
                escapeHtml(selectedSale.sales_no || '') +
                ' | Due: <strong>' + formatCurrency(selectedSale.due_amount || 0) + '</strong>'
            );
        } else if (selectedCustomer) {
            renderCustomerOptions(selectedCustomer.id, false);
            setCustomerInputText(selectedCustomer);
            $('#paymentCustomerSearch').prop('disabled', false);
            $('#paymentCustomerId').val(selectedCustomer.id);
            $('#paymentSalesId').val('');
            $('#paymentType').val('2').prop('disabled', false);
            $('#paymentTypeHidden').val('2');

            let total = parseFloat(selectedCustomer.total_receivable || 0);
            if (total <= 0) {
                total = parseFloat(selectedCustomer.opening_due || 0) + parseFloat(selectedCustomer.sales_due || 0);
            }

            resetSplitRows(total.toFixed(2));

            $('#paymentInfoBox').html(
                '<strong>' + escapeHtml(selectedCustomer.customer_name || '') +
                '</strong> | Total Due: <strong>' + formatCurrency(total) + '</strong>'
            );
        } else {
            $('#paymentCustomerId').val('');
            $('#paymentSalesId').val('');
            $('#paymentCustomerSearch').prop('disabled', false).val('');
            $('#paymentCustomerDropdown').addClass('d-none');
            $('#paymentType').val('2').prop('disabled', false);
            $('#paymentTypeHidden').val('2');
            customerDueDocuments = [];
            renderIndividualDocumentOptions();
            resetSplitRows('');
            $('#paymentInfoBox').html('Select customer to receive payment.');
        }

        renderCustomerOptions(selectedSale ? selectedSale.customer_id : (selectedCustomer ? selectedCustomer.id : ''));
        $('#paymentCustomerSearch').prop('disabled', !!selectedSale);
        renderIndividualDocumentOptions(selectedSale ? selectedSale.id : '');
        toggleIndividualDocumentBox();
        recalculateSplitTotal();

        $('#paymentModalTitle').text('Payment Entry');
        $('#paymentEntryCard').removeClass('d-none');
    }

    function loadPaymentForEdit(id) {
        if (!id) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_customer_payment',
                id: id
            },
            success: function (response) {
                if (response.status === true) {
                    let payment = response.data.payment || {};
                    selectedCustomer = response.data.customer || selectedCustomer;

                    renderCustomerOptions(payment.customer_id || (selectedCustomer ? selectedCustomer.id : ''), false);
                    setCustomerInputText(selectedCustomer || {customer_name: payment.customer_name || '', mobile: payment.customer_mobile || ''});
                    $('#paymentCustomerSearch').prop('disabled', false);
                    renderHeaderCards();

                    $('#paymentForm')[0].reset();
                    renderCustomerOptions(payment.customer_id || (selectedCustomer ? selectedCustomer.id : ''), false);
                    setCustomerInputText(selectedCustomer || {customer_name: payment.customer_name || '', mobile: payment.customer_mobile || ''});
                    $('#paymentCustomerSearch').prop('disabled', false);
                    $('#paymentId').val(payment.id || '');
                    $('#paymentCustomerId').val(payment.customer_id || '');
                    $('#paymentSalesId').val(payment.sales_id || '');
                    $('#paymentType').val(payment.payment_type || 2).prop('disabled', false);
                    $('#paymentTypeHidden').val(payment.payment_type || 2);
                    $('#paymentDate').val(payment.payment_date || currentDate());
                    renderIndividualDocumentOptions(payment.sales_id || '');
                    $('#individualSalesSelect').val(payment.sales_id || '');
                    $('#paymentSalesId').val(payment.sales_id || '');
                    $('#paymentNotes').val(payment.notes || '');
                    $('#splitRowsBody').html('');

                    let splits = response.data.splits || [];

                    if (splits.length) {
                        $.each(splits, function (_, split) {
                            addSplitRow(split.payment_mode_id, split.amount, split.reference_no || '');
                        });
                    } else {
                        addSplitRow(payment.payment_mode_id || '', payment.amount || 0, payment.reference_no || '');
                    }

                    $('#paymentInfoBox').html('<strong>Edit:</strong> ' + escapeHtml(payment.payment_no || '') + '. Old allocation will reverse and recalculate.');
                    $('#paymentModalTitle').text('Edit Payment');

                    toggleIndividualDocumentBox();
                    recalculateSplitTotal();
                    $('#paymentEntryCard').removeClass('d-none');
                    $('html, body').animate({ scrollTop: $('#paymentEntryCard').offset().top - 80 }, 300);
                } else {
                    showPaymentToast('error', response.message || 'Unable to load payment.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showPaymentToast('error', 'Server error.');
            }
        });
    }

    function paymentModeOptions(selectedId) {
        let html = '<option value="">Select</option>';

        $.each(paymentModes || [], function (_, mode) {
            let selected = parseInt(mode.id) === parseInt(selectedId || 0) ? 'selected' : '';
            html += '<option value="' + mode.id + '" ' + selected + '>' + escapeHtml(mode.mode_name || '') + '</option>';
        });

        return html;
    }

    function renderPaymentModes() {
        // Split rows render their own payment mode dropdown.
    }

    function addSplitRow(modeId, amount, referenceNo) {
        let html = '';

        html += '<tr class="split-row">';
        html += '<td><select class="form-select form-select-sm split-mode">' + paymentModeOptions(modeId) + '</select></td>';
        html += '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm split-amount" value="' + (amount ? parseFloat(amount).toFixed(2) : '') + '"></td>';
        html += '<td><input type="text" class="form-control form-control-sm split-reference" value="' + escapeHtml(referenceNo || '') + '" placeholder="Ref no"></td>';
        html += '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-split-row-btn"><i class="mdi mdi-delete"></i></button></td>';
        html += '</tr>';

        $('#splitRowsBody').append(html);
        recalculateSplitTotal();
    }

    function resetSplitRows(amount) {
        $('#splitRowsBody').html('');
        addSplitRow('', amount || '', '');
    }

    function collectSplitRows() {
        let rows = [];

        $('#splitRowsBody .split-row').each(function () {
            let modeId = parseInt($(this).find('.split-mode').val() || 0);
            let amount = parseFloat($(this).find('.split-amount').val() || 0);
            let referenceNo = $(this).find('.split-reference').val() || '';

            if (modeId > 0 && amount > 0) {
                rows.push({
                    payment_mode_id: modeId,
                    amount: amount,
                    reference_no: referenceNo
                });
            }
        });

        return rows;
    }

    function getSplitTotal() {
        let total = 0;

        $('#splitRowsBody .split-amount').each(function () {
            total += parseFloat($(this).val() || 0);
        });

        return total;
    }

    function recalculateSplitTotal() {
        let total = getSplitTotal();

        $('#splitTotalText').text(formatCurrency(total));
        $('#paymentAmount').val(total.toFixed(2));
    }

    function paymentTypeBadge(type) {
        type = parseInt(type || 0);

        if (type === 1) {
            return '<span class="badge bg-primary">Individual Document</span>';
        }

        if (type === 2) {
            return '<span class="badge bg-info">Overall</span>';
        }

        if (type === 3) {
            return '<span class="badge bg-warning text-dark">Opening</span>';
        }

        return '<span class="badge bg-secondary">Unknown</span>';
    }

    function paymentStatusBadge(status) {
        status = parseInt(status || 0);

        if (status === 1) {
            return '<span class="badge bg-success">Active</span>';
        }

        if (status === 2) {
            return '<span class="badge bg-danger">Cancelled</span>';
        }

        return '<span class="badge bg-secondary">Unknown</span>';
    }

    function formatCurrency(value) {
        return '₹' + parseFloat(value || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(date) {
        if (!date) {
            return '-';
        }

        let parts = String(date).split('-');

        if (parts.length !== 3) {
            return escapeHtml(date);
        }

        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    function currentDate() {
        return new Date().toISOString().slice(0, 10);
    }

    function setBtnLoading(selector, text) {
        let btn = $(selector);
        btn.data('old-text', btn.html()).prop('disabled', true).html(text);
    }

    function resetBtnLoading(selector, text) {
        let btn = $(selector);
        btn.prop('disabled', false).html(btn.data('old-text') || text);
    }

    function showPaymentToast(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        if (typeof showAppToast === 'function') {
            showAppToast(type, message);
            return;
        }

        alert(message);
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

});
