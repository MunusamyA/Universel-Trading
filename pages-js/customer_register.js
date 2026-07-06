$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let customerModalElement = document.getElementById('customerRegisterModal');
    let customerRegisterModal = customerModalElement ? new bootstrap.Modal(customerModalElement) : null;

    loadRegistrations();
    loadExistingBusinesses();

    $('#addCustomerRegisterBtn').on('click', function () {
        resetForm();
        $('#customerRegisterModalTitle').text('Add Customer Registration');
        $('#customerRegisterBtn').html('Save Registration');
        if (customerRegisterModal) {
            customerRegisterModal.show();
        }
    });

    $('#refreshCustomerRegisterBtn').on('click', function () {
        loadRegistrations();
    });

    $('#registration_mode').on('change', function () {
        toggleRegistrationMode();
    });

    $('#existing_business_id').on('change', function () {
        loadMainBranches($(this).val());
    });

    $('#togglePassword').on('click', function () {
        togglePasswordField('password', $(this));
    });

    $('#toggleConfirmPassword').on('click', function () {
        togglePasswordField('confirm_password', $(this));
    });

    $('#customerRegisterForm').on('submit', function (e) {
        e.preventDefault();

        let mode = $('#registration_mode').val();

        if (mode === 'new_business' && $.trim($('#business_name').val()) === '') {
            notify('warning', 'Please enter business name.');
            $('#business_name').focus();
            return;
        }

        if (mode === 'new_branch') {
            if ($('#existing_business_id').val() === '') {
                notify('warning', 'Please select existing business.');
                $('#existing_business_id').focus();
                return;
            }

            if ($('#parent_branch_id').val() === '') {
                notify('warning', 'Please select main branch.');
                $('#parent_branch_id').focus();
                return;
            }
        }

        if ($.trim($('#owner_name').val()) === '') {
            notify('warning', 'Please enter owner / login name.');
            $('#owner_name').focus();
            return;
        }

        if (!/^[0-9]{10}$/.test($.trim($('#mobile').val()))) {
            notify('warning', 'Please enter valid 10 digit mobile number.');
            $('#mobile').focus();
            return;
        }

        if ($.trim($('#branch_name').val()) === '') {
            notify('warning', 'Please enter branch name.');
            $('#branch_name').focus();
            return;
        }

        if ($.trim($('#username').val()).length < 4) {
            notify('warning', 'Username must be at least 4 characters.');
            $('#username').focus();
            return;
        }

        if ($.trim($('#password').val()).length < 6) {
            notify('warning', 'Password must be at least 6 characters.');
            $('#password').focus();
            return;
        }

        if ($('#password').val() !== $('#confirm_password').val()) {
            notify('warning', 'Password and confirm password do not match.');
            $('#confirm_password').focus();
            return;
        }

        if ($('#package_role_id').val() === '') {
            notify('warning', 'Please select package role.');
            $('#package_role_id').focus();
            return;
        }

        setLoading('customerRegisterBtn', 'Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'POST',
            dataType: 'json',
            data: $('#customerRegisterForm').serialize() + '&action=save_registration',
            success: function (response) {
                if (response.status === true) {
                    notify('success', response.message || 'Registration saved successfully.');
                    if (customerRegisterModal) {
                        customerRegisterModal.hide();
                    }
                    loadRegistrations();
                    loadExistingBusinesses();
                } else {
                    notify('error', response.message || 'Unable to save registration.');
                }

                resetLoading('customerRegisterBtn', 'Save Registration');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                notify('error', 'Server error. Please try again.');
                resetLoading('customerRegisterBtn', 'Save Registration');
            }
        });
    });

    function loadRegistrations() {
        $('#customerRegisterTableBody').html(
            '<tr><td colspan="10" class="text-center text-muted">Loading...</td></tr>'
        );

        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'list_registrations' },
            success: function (response) {
                if (response.status === true) {
                    renderRegistrations(response.data.registrations || []);
                    renderStats(response.data.stats || {});
                } else {
                    $('#customerRegisterTableBody').html(
                        '<tr><td colspan="10" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load data.') + '</td></tr>'
                    );
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#customerRegisterTableBody').html(
                    '<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>'
                );
            }
        });
    }

    function renderRegistrations(rows) {
        if (!rows.length) {
            $('#customerRegisterTableBody').html(
                '<tr><td colspan="10" class="text-center text-muted">No registrations found.</td></tr>'
            );
            return;
        }

        let html = '';

        $.each(rows, function (index, row) {
            let statusBadge = parseInt(row.business_status || 0) === 1
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-danger">Inactive</span>';

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(row.business_name || '-') + '</strong><br><small class="text-muted">' + escapeHtml(row.business_code || '') + '</small></td>';
            html += '<td>' + escapeHtml(row.owner_name || '-') + '</td>';
            html += '<td>' + escapeHtml(row.mobile || '-') + '</td>';
            html += '<td>' + escapeHtml(row.branch_name || '-') + '<br><small class="text-muted">' + escapeHtml(row.branch_code || '') + '</small></td>';
            html += '<td>' + escapeHtml(row.username || '-') + '</td>';
            html += '<td>' + escapeHtml(row.package_role_name || row.role_name || '-') + '</td>';
            html += '<td><span class="badge bg-info">' + parseInt(row.child_branch_count || 0) + '</span></td>';
            html += '<td>' + statusBadge + '</td>';
            html += '<td>' + formatDate(row.created_at) + '</td>';
            html += '</tr>';
        });

        $('#customerRegisterTableBody').html(html);
    }

    function renderStats(stats) {
        $('#totalBusinessCount').text(stats.total_businesses || 0);
        $('#activeBusinessCount').text(stats.active_businesses || 0);
        $('#approvedBranchCount').text(stats.approved_branches || 0);
        $('#businessUserCount').text(stats.business_users || 0);
    }

    function loadExistingBusinesses() {
        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_existing_businesses' },
            success: function (response) {
                let html = '<option value="">Select Business</option>';

                if (response.status === true) {
                    $.each(response.data.businesses || [], function (_, row) {
                        html += '<option value="' + row.id + '">' + escapeHtml(row.business_name || '-') + '</option>';
                    });
                }

                $('#existing_business_id').html(html);
            }
        });
    }

    function loadMainBranches(businessId) {
        $('#parent_branch_id').html('<option value="">Select Main Branch</option>');

        if (!businessId) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_main_branches',
                business_id: businessId
            },
            success: function (response) {
                let html = '<option value="">Select Main Branch</option>';

                if (response.status === true) {
                    $.each(response.data.branches || [], function (_, row) {
                        html += '<option value="' + row.id + '">' + escapeHtml(row.branch_name || '-') + ' (' + escapeHtml(row.branch_code || '') + ')</option>';
                    });
                }

                $('#parent_branch_id').html(html);
            }
        });
    }

    function toggleRegistrationMode() {
        let mode = $('#registration_mode').val();

        if (mode === 'new_business') {
            $('.new-business-area').removeClass('d-none');
            $('.existing-business-area').addClass('d-none');
            $('#branch_name').val('Main Branch');
            $('#existing_business_id').val('');
            $('#parent_branch_id').html('<option value="">Select Main Branch</option>');
        } else {
            $('.new-business-area').addClass('d-none');
            $('.existing-business-area').removeClass('d-none');
            $('#branch_name').val('');
        }
    }

    function resetForm() {
        $('#customerRegisterForm')[0].reset();
        $('#registration_mode').val('new_business');
        $('#branch_name').val('Main Branch');
        $('#state').val('Tamil Nadu');
        toggleRegistrationMode();
    }

    function togglePasswordField(inputId, button) {
        let input = $('#' + inputId);
        let icon = button.find('i');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('mdi-eye-outline').addClass('mdi-eye-off-outline');
        } else {
            input.attr('type', 'password');
            icon.removeClass('mdi-eye-off-outline').addClass('mdi-eye-outline');
        }
    }

    function setLoading(buttonId, text) {
        let btn = $('#' + buttonId);
        btn.data('old-html', btn.html());
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>' + text);
    }

    function resetLoading(buttonId, defaultText) {
        let btn = $('#' + buttonId);
        btn.prop('disabled', false).html(btn.data('old-html') || defaultText);
    }

    function notify(type, message) {
        if (typeof showAppToast === 'function') {
            showAppToast(type, message);
            return;
        }

        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        console.log(type + ': ' + message);
    }

    function escapeHtml(value) {
        value = value === null || value === undefined ? '' : String(value);

        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }

        let date = new Date(value.replace(' ', 'T'));

        if (isNaN(date.getTime())) {
            return escapeHtml(value);
        }

        return date.toLocaleDateString('en-IN');
    }

});
