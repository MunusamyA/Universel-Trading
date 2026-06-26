$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let customerModalElement = document.getElementById('customerRegisterModal');
    let branchListModalElement = document.getElementById('branchListModal');

    if (!customerModalElement) {
        console.error('customerRegisterModal not found. Wrong JS file loaded.');
        return;
    }

    let customerRegisterModal = new bootstrap.Modal(customerModalElement);
    let branchListModal = branchListModalElement ? new bootstrap.Modal(branchListModalElement) : null;

    let registrationRows = [];

    loadPackageRoles();
    loadRegistrations();
    loadExistingBusinesses();

    $('#addCustomerRegisterBtn').on('click', function () {
        resetCustomerForm();
        $('#customerRegisterModalTitle').text('Add Customer Registration');
        $('#customerRegisterBtn').html('Save Registration');
        customerRegisterModal.show();
    });

    $(document).on('click', '.edit-registration-btn', function () {
        let businessId = $(this).data('business-id');
        let branchId = $(this).data('branch-id');
        editRegistration(businessId, branchId);
    });

    $('#refreshCustomerRegisterBtn').on('click', function () {
        loadRegistrations();
    });

    $('#registration_mode').on('change', function () {
        toggleRegistrationMode();
    });

    $('#existing_business_id').on('change', function () {
        let businessId = $(this).val();

        if (isEditMode()) {
            loadBusinessBranches(businessId);
        } else {
            loadMainBranches(businessId);
        }
    });

    $('#parent_branch_id').on('change', function () {
        if (!isEditMode()) {
            return;
        }

        let businessId = $('#existing_business_id').val();
        let branchId = $(this).val();

        if (businessId && branchId) {
            loadRegistrationForBranch(businessId, branchId);
        }
    });

    $('#togglePassword').on('click', function () {
        togglePasswordField('password', $(this));
    });

    $('#toggleConfirmPassword').on('click', function () {
        togglePasswordField('confirm_password', $(this));
    });

    $(document).on('click', '.view-child-branches-btn', function () {
        let businessId = $(this).data('business-id');
        let branchId = $(this).data('branch-id');
        let title = $(this).data('title') || 'Branches Under Main Branch';

        $('#branchListModalTitle').text(title);
        loadChildBranches(businessId, branchId);

        if (branchListModal) {
            branchListModal.show();
        }
    });

    $('#customerRegisterForm').on('submit', function (e) {
        e.preventDefault();

        let mode = $('#registration_mode').val();

        if (mode === 'new_business') {
            if ($.trim($('#business_name').val()) === '') {
                showAppToast('warning', 'Please enter business name.');
                $('#business_name').focus();
                return;
            }
        } else {
            if ($('#existing_business_id').val() === '') {
                showAppToast('warning', 'Please select existing business.');
                $('#existing_business_id').focus();
                return;
            }
            if ($('#parent_branch_id').val() === '') {
                showAppToast('warning', 'Please select main branch.');
                $('#parent_branch_id').focus();
                return;
            }
            if ($.trim($('#branch_name').val()) === '') {
                showAppToast('warning', 'Please enter branch name.');
                $('#branch_name').focus();
                return;
            }
        }

        if ($.trim($('#owner_name').val()) === '') {
            showAppToast('warning', 'Please enter login / owner name.');
            $('#owner_name').focus();
            return;
        }

        if (!/^[0-9]{10}$/.test($.trim($('#mobile').val()))) {
            showAppToast('warning', 'Please enter valid 10 digit mobile number.');
            $('#mobile').focus();
            return;
        }

        if ($.trim($('#username').val()).length < 4) {
            showAppToast('warning', 'Username must be at least 4 characters.');
            $('#username').focus();
            return;
        }

        let isEdit = parseInt($('#registration_id').val() || 0) > 0;
        let passwordValue = $.trim($('#password').val());

        if (!isEdit || passwordValue !== '') {
            if (passwordValue.length < 6) {
                showAppToast('warning', 'Password must be at least 6 characters.');
                $('#password').focus();
                return;
            }

            if ($('#password').val() !== $('#confirm_password').val()) {
                showAppToast('warning', 'Password and confirm password do not match.');
                $('#confirm_password').focus();
                return;
            }
        }

        if ($('#package_role_id').val() === '') {
            showAppToast('warning', 'Please select package role.');
            $('#package_role_id').focus();
            return;
        }

        setButtonLoading('customerRegisterBtn', 'Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'POST',
            dataType: 'json',
            data: $('#customerRegisterForm').serialize() + '&action=save_registration',
            success: function (response) {
                if (response.status === true) {
                    showAppToast('success', response.message || 'Registration saved.');
                    customerRegisterModal.hide();
                    loadRegistrations();
                    loadExistingBusinesses();
                } else {
                    handleApiError(response);
                }
                resetButtonLoading('customerRegisterBtn');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error. Please try again.');
                resetButtonLoading('customerRegisterBtn');
            }
        });
    });

    function loadPackageRoles() {
        let preloaded = window.PRELOADED_PACKAGE_ROLES || [];
        renderPackageRoles(preloaded);

        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_package_roles' },
            success: function (response) {
                if (response.status === true) {
                    renderPackageRoles(response.data.package_roles || response.data.roles || []);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function renderPackageRoles(roles) {
        let html = '<option value="">Select Package Role</option>';

        $.each(roles || [], function (index, role) {
            html += `<option value="${role.id}">${escapeHtml(role.role_name)}</option>`;
        });

        $('#package_role_id').html(html);
    }

    function loadExistingBusinesses() {
        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_existing_businesses' },
            success: function (response) {
                let html = '<option value="">Select Existing Business</option>';

                if (response.status === true && response.data.businesses.length > 0) {
                    $.each(response.data.businesses, function (index, business) {
                        html += `<option value="${business.id}">${escapeHtml(business.business_name)}</option>`;
                    });
                }

                $('#existing_business_id').html(html);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function loadMainBranches(businessId) {
        let html = '<option value="">Select Main Branch</option>';
        $('#parent_branch_id').html(html);

        if (!businessId) return;

        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_main_branches',
                business_id: businessId
            },
            success: function (response) {
                if (response.status === true && response.data.branches.length > 0) {
                    $.each(response.data.branches, function (index, branch) {
                        html += `<option value="${branch.id}">${escapeHtml(branch.branch_name)} (${escapeHtml(branch.branch_code || '')})</option>`;
                    });
                }

                $('#parent_branch_id').html(html);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }


    function loadBusinessBranches(businessId, selectedBranchId) {
        let html = '<option value="">Select Branch</option>';
        $('#parent_branch_id').html(html);

        if (!businessId) return;

        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_business_branches',
                business_id: businessId
            },
            success: function (response) {
                if (response.status === true && response.data.branches.length > 0) {
                    $.each(response.data.branches, function (index, branch) {
                        let branchType = (!branch.parent_branch_id || parseInt(branch.parent_branch_id) === 0) ? 'Main' : 'Child';
                        let selected = parseInt(branch.id) === parseInt(selectedBranchId || 0) ? 'selected' : '';
                        html += `<option value="${branch.id}" ${selected}>${escapeHtml(branch.branch_name)} (${escapeHtml(branch.branch_code || '')}) - ${branchType}</option>`;
                    });
                }

                $('#parent_branch_id').html(html);

                if (selectedBranchId) {
                    $('#edit_branch_id').val(selectedBranchId);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function loadRegistrations() {
        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'list_registrations' },
            success: function (response) {
                if (response.status === true) {
                    registrationRows = response.data.registrations || response.data.customers || [];
                    renderRegistrations(registrationRows);
                    updateStats(response.data.stats || {});
                } else {
                    $('#customerRegisterTableBody').html(`<tr><td colspan="10" class="text-center text-muted">${escapeHtml(response.message)}</td></tr>`);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#customerRegisterTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderRegistrations(rows) {
        if (!rows || rows.length === 0) {
            $('#customerRegisterTableBody').html('<tr><td colspan="10" class="text-center text-muted">No registrations found.</td></tr>');
            return;
        }

        let html = '';

        $.each(rows, function (index, row) {
            let childCount = parseInt(row.child_branch_count || 0);
            let branchButton = `
                <button type="button"
                        class="btn btn-sm btn-outline-primary view-child-branches-btn"
                        data-business-id="${row.business_id}"
                        data-branch-id="${row.branch_id}"
                        data-title="${escapeHtml(row.business_name || '')} - Branches">
                    <i class="mdi mdi-source-branch"></i> ${childCount}
                </button>
            `;

            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <strong>${escapeHtml(row.business_name || '-')}</strong><br>
                        <small class="text-muted">${escapeHtml(row.business_code || '')}</small>
                    </td>
                    <td>${escapeHtml(row.owner_name || '-')}</td>
                    <td>${escapeHtml(row.mobile || '-')}<br><small>${escapeHtml(row.email || '')}</small></td>
                    <td>${escapeHtml(row.branch_name || '-')}<br><small>${escapeHtml(row.branch_code || '')}</small></td>
                    <td>${escapeHtml(row.username || '-')}</td>
                    <td>${escapeHtml(row.package_role_name || '-')}</td>
                    <td>${branchButton}</td>
                    <td>${statusBadge(row.business_status)}</td>
                    <td>${formatDate(row.created_at)}</td>
                    <td>
                        <button type="button"
                                class="btn btn-sm btn-primary edit-registration-btn"
                                data-business-id="${row.business_id}"
                                data-branch-id="${row.branch_id}">
                            Edit
                        </button>
                    </td>
                </tr>
            `;
        });

        $('#customerRegisterTableBody').html(html);
    }


    function editRegistration(businessId, branchId) {
        if (!businessId) {
            showAppToast('error', 'Invalid registration.');
            return;
        }

        resetCustomerForm();
        $('#customerRegisterModalTitle').text('Edit Customer / Branch Registration');
        $('#customerRegisterBtn').html('Update Registration');
        $('#registration_id').val(businessId || '');
        $('#edit_branch_id').val(branchId || '');
        $('#registration_mode').val('existing_business').prop('disabled', true);
        $('#existing_business_id').val(businessId).prop('disabled', true);
        toggleRegistrationMode();
        $('#parent_branch_label').html('Select Main Branch <span class="text-danger">*</span>');
        $('#branchDetailsTitle').text('Selected Branch Details');
        $('#password').attr('placeholder', 'Leave empty to keep old password');
        $('#confirm_password').attr('placeholder', 'Leave empty to keep old password');

        loadBusinessBranches(businessId, branchId);
        loadRegistrationForBranch(businessId, branchId);
        customerRegisterModal.show();
    }

    function loadRegistrationForBranch(businessId, branchId) {
        if (!businessId) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_registration',
                business_id: businessId,
                branch_id: branchId || ''
            },
            success: function (response) {
                if (response.status === true) {
                    let r = response.data.registration;

                    $('#registration_id').val(r.business_id || '');
                    $('#edit_branch_id').val(r.branch_id || '');
                    $('#existing_business_id').val(r.business_id || '');
                    $('#parent_branch_id').val(r.branch_id || '');

                    $('#business_name').val(r.business_name || '');
                    $('#owner_name').val(r.user_name || r.owner_name || '');
                    $('#mobile').val(r.user_mobile || r.branch_mobile || r.business_mobile || '');
                    $('#email').val(r.user_email || r.branch_email || r.business_email || '');
                    $('#gst_number').val(r.gst_number || '');
                    $('#address').val(r.branch_address || r.business_address || '');
                    $('#city').val(r.branch_city || r.business_city || '');
                    $('#state').val(r.branch_state || r.business_state || 'Tamil Nadu');
                    $('#pincode').val(r.branch_pincode || r.business_pincode || '');
                    $('#branch_name').val(r.branch_name || '');
                    $('#branch_code').val(r.branch_code || '');
                    $('#username').val(r.username || '');
                    $('#password').val('');
                    $('#confirm_password').val('');
                    $('#package_role_id').val(r.package_role_id || '');
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

    function loadChildBranches(businessId, parentBranchId) {
        $('#branchListTableBody').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/customer_register.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_child_branches',
                business_id: businessId,
                parent_branch_id: parentBranchId
            },
            success: function (response) {
                if (response.status === true) {
                    renderChildBranches(response.data.branches || []);
                } else {
                    $('#branchListTableBody').html(`<tr><td colspan="8" class="text-center text-muted">${escapeHtml(response.message)}</td></tr>`);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#branchListTableBody').html('<tr><td colspan="8" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderChildBranches(branches) {
        if (!branches || branches.length === 0) {
            $('#branchListTableBody').html('<tr><td colspan="8" class="text-center text-muted">No child branches found under this main branch.</td></tr>');
            return;
        }

        let html = '';

        $.each(branches, function (index, branch) {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(branch.branch_name || '-')}</td>
                    <td>${escapeHtml(branch.branch_code || '-')}</td>
                    <td>${escapeHtml(branch.mobile || '-')}<br><small>${escapeHtml(branch.email || '')}</small></td>
                    <td>${escapeHtml(branch.city || '-')}<br><small>${escapeHtml(branch.state || '')} ${escapeHtml(branch.pincode || '')}</small></td>
                    <td>${escapeHtml(branch.username || '-')}</td>
                    <td>${escapeHtml(branch.package_role_name || '-')}</td>
                    <td>${statusBadge(branch.status)}</td>
                </tr>
            `;
        });

        $('#branchListTableBody').html(html);
    }

    function toggleRegistrationMode() {
        let mode = $('#registration_mode').val();

        if (mode === 'existing_business') {
            $('#existingBusinessSection').show();
            $('#newBusinessSection').hide();
            $('.existing-only').show();

            if (isEditMode()) {
                $('#parent_branch_label').html('Select Main Branch <span class="text-danger">*</span>');
                $('#branchDetailsTitle').text('Selected Branch Details');
            } else {
                $('#parent_branch_label').html('Select Main Branch <span class="text-danger">*</span>');
                $('#branchDetailsTitle').text('New Branch Details');
            }
        } else {
            $('#existingBusinessSection').hide();
            $('#newBusinessSection').show();
            $('.existing-only').hide();
            $('#parent_branch_label').html('Select Main Branch <span class="text-danger">*</span>');
            $('#branchDetailsTitle').text('Main Branch Details');
        }
    }

    function isEditMode() {
        return parseInt($('#registration_id').val() || 0) > 0;
    }

    function updateStats(stats) {
        $('#totalBusinessCount').text(stats.total_businesses || 0);
        $('#activeBusinessCount').text(stats.active_businesses || 0);
        $('#approvedBranchCount').text(stats.approved_branches || 0);
        $('#businessUserCount').text(stats.business_users || 0);
    }

    function statusBadge(status) {
        status = parseInt(status);
        if (status === 1) return '<span class="badge bg-success">Active</span>';
        if (status === 2) return '<span class="badge bg-danger">Inactive</span>';
        return '<span class="badge bg-warning">Pending</span>';
    }

    function resetCustomerForm() {
        $('#customerRegisterForm')[0].reset();
        $('#registration_id').val('');
        $('#edit_branch_id').val('');
        $('#registration_mode').prop('disabled', false);
        $('#existing_business_id').prop('disabled', false);
        $('#registration_mode').val('new_business');
        $('#state').val('Tamil Nadu');
        $('#parent_branch_id').html('<option value="">Select Main Branch</option>');
        $('#parent_branch_label').html('Select Main Branch <span class="text-danger">*</span>');
        $('#password').attr('placeholder', 'Enter password');
        $('#confirm_password').attr('placeholder', 'Confirm password');
        $('#customerRegisterBtn').html('Save Registration');
        toggleRegistrationMode();
        loadPackageRoles();
    }

    function togglePasswordField(fieldId, btn) {
        let input = $('#' + fieldId);
        let type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        btn.find('i').toggleClass('mdi-eye-outline mdi-eye-off-outline');
    }

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function formatDate(dateValue) {
        if (!dateValue) return '-';
        let date = new Date(String(dateValue).replace(' ', 'T'));
        if (isNaN(date.getTime())) return dateValue;
        return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function showAppToast(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 4000);
        } else {
            alert(message);
        }
    }

    function handleApiError(response) {
        if (response && response.redirect) {
            window.location.href = response.redirect;
            return;
        }
        showAppToast('error', (response && response.message) ? response.message : 'Something went wrong.');
    }

    function setButtonLoading(buttonId, text) {
        let btn = $('#' + buttonId);
        btn.data('original-text', btn.html());
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>' + text);
    }

    function resetButtonLoading(buttonId) {
        let btn = $('#' + buttonId);
        btn.prop('disabled', false).html(btn.data('original-text'));
    }
});
