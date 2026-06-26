$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
    let rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
    let branchRows = [];

    loadBranchApprovals();

    $('#refreshBranchApprovalBtn').on('click', function () {
        loadBranchApprovals();
    });

    $(document).on('click', '.approve-branch-btn', function () {
        let branchId = parseInt($(this).data('id'));
        let branch = findBranch(branchId);

        if (!branch) {
            showToast('error', 'Branch data not found.', 5000);
            return;
        }

        $('#approveBranchForm')[0].reset();
        $('#approve_branch_id').val(branchId);

        $('#view_business_name').val(branch.business_name || '-');
        $('#view_main_branch_name').val(branch.main_branch_name || '-');
        $('#view_branch_name').val(branch.branch_name || '-');
        $('#view_branch_code').val(branch.branch_code || '-');
        $('#view_mobile').val(branch.mobile || '-');
        $('#view_email').val(branch.email || '-');
        $('#view_address').val(branch.address || '-');
        $('#view_city').val(branch.city || '-');
        $('#view_state').val(branch.state || '-');
        $('#view_pincode').val(branch.pincode || '-');

        $('#approve_name').val(branch.branch_name || '');
        $('#approve_username').val(makeUsername(branch.branch_name || ''));
        $('#approve_password').val('');
        $('#approve_remarks').val('');

        loadPackageRoles(branchId);
        approveModal.show();
    });

    $(document).on('click', '.reject-branch-btn', function () {
        $('#rejectBranchForm')[0].reset();
        $('#reject_branch_id').val($(this).data('id'));
        rejectModal.show();
    });

    $('#approveBranchForm').on('submit', function (e) {
        e.preventDefault();

        if ($.trim($('#approve_name').val()) === '') {
            showToast('warning', 'Please enter login name.', 5000);
            $('#approve_name').focus();
            return;
        }

        if ($.trim($('#approve_username').val()) === '') {
            showToast('warning', 'Please enter username.', 5000);
            $('#approve_username').focus();
            return;
        }

        if ($.trim($('#approve_password').val()).length < 6) {
            showToast('warning', 'Password must be at least 6 characters.', 5000);
            $('#approve_password').focus();
            return;
        }

        if ($('#approve_package_role_id').val() === '') {
            showToast('warning', 'Please select package role.', 5000);
            $('#approve_package_role_id').focus();
            return;
        }

        setButtonLoading('approveBranchBtn', 'Approving...');

        $.ajax({
            url: window.BASE_URL + 'api/branch_approvals.php',
            type: 'POST',
            dataType: 'json',
            data: $('#approveBranchForm').serialize() + '&action=approve_branch',
            success: function (response) {
                if (response.status === true) {
                    showToast('success', response.message || 'Branch approved and login created.', 4000);
                    approveModal.hide();
                    loadBranchApprovals();
                } else {
                    handleApiError(response);
                }

                resetButtonLoading('approveBranchBtn');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToast('error', 'Server error. Please try again.', 5000);
                resetButtonLoading('approveBranchBtn');
            }
        });
    });

    $('#rejectBranchForm').on('submit', function (e) {
        e.preventDefault();

        setButtonLoading('rejectBranchBtn', 'Rejecting...');

        $.ajax({
            url: window.BASE_URL + 'api/branch_approvals.php',
            type: 'POST',
            dataType: 'json',
            data: $('#rejectBranchForm').serialize() + '&action=reject_branch',
            success: function (response) {
                if (response.status === true) {
                    showToast('success', response.message || 'Branch rejected.', 4000);
                    rejectModal.hide();
                    loadBranchApprovals();
                } else {
                    handleApiError(response);
                }

                resetButtonLoading('rejectBranchBtn');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToast('error', 'Server error. Please try again.', 5000);
                resetButtonLoading('rejectBranchBtn');
            }
        });
    });

    function loadBranchApprovals() {
        $.ajax({
            url: window.BASE_URL + 'api/branch_approvals.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_requests'
            },
            success: function (response) {
                if (response.status === true) {
                    branchRows = response.data.branches || [];
                    renderBranchApprovals(branchRows);
                    updateCounts(branchRows);
                } else {
                    $('#branchApprovalTableBody').html(`
                        <tr>
                            <td colspan="9" class="text-center text-muted">${escapeHtml(response.message)}</td>
                        </tr>
                    `);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#branchApprovalTableBody').html(`
                    <tr>
                        <td colspan="9" class="text-center text-danger">Server error.</td>
                    </tr>
                `);
            }
        });
    }

    function loadPackageRoles(branchId) {
        $('#approve_package_role_id').html('<option value="">Loading package roles...</option>');

        $.ajax({
            url: window.BASE_URL + 'api/branch_approvals.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_package_roles',
                branch_id: branchId
            },
            success: function (response) {
                let html = '<option value="">Select Package Role</option>';

                if (response.status === true && response.data.roles.length > 0) {
                    $.each(response.data.roles, function (index, role) {
                        html += `<option value="${role.id}">${escapeHtml(role.role_name)}</option>`;
                    });
                }

                $('#approve_package_role_id').html(html);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#approve_package_role_id').html('<option value="">Package roles not loaded</option>');
            }
        });
    }

    function renderBranchApprovals(branches) {
        if (!branches || branches.length === 0) {
            $('#branchApprovalTableBody').html(`
                <tr>
                    <td colspan="9" class="text-center text-muted">No branch requests found.</td>
                </tr>
            `);
            return;
        }

        let html = '';

        $.each(branches, function (index, branch) {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(branch.business_name || '-')}</td>
                    <td>${escapeHtml(branch.main_branch_name || '-')}</td>
                    <td>${escapeHtml(branch.branch_name || '-')}</td>
                    <td>${escapeHtml(branch.branch_code || '-')}</td>
                    <td>${escapeHtml(branch.requested_by_name || '-')}</td>
                    <td>${escapeHtml(branch.mobile || '-')}<br><small>${escapeHtml(branch.email || '')}</small></td>
                    <td>${statusBadge(branch.approval_status)}</td>
                    <td>${statusActionButtons(branch)}</td>
                </tr>
            `;
        });

        $('#branchApprovalTableBody').html(html);
    }

    function statusActionButtons(branch) {
        if (parseInt(branch.approval_status) !== 0) {
            return `<small class="text-muted">${escapeHtml(branch.approval_remarks || '-')}</small>`;
        }

        return `
            <button type="button" class="btn btn-sm btn-success approve-branch-btn" data-id="${branch.id}">
                Approve
            </button>
            <button type="button" class="btn btn-sm btn-danger reject-branch-btn" data-id="${branch.id}">
                Reject
            </button>
        `;
    }

    function updateCounts(branches) {
        let total = branches ? branches.length : 0;
        let pending = 0;
        let approved = 0;
        let rejected = 0;

        $.each(branches || [], function (index, branch) {
            let status = parseInt(branch.approval_status);
            if (status === 0) pending++;
            if (status === 1) approved++;
            if (status === 2) rejected++;
        });

        $('#totalApprovalCount').text(total);
        $('#pendingApprovalCount').text(pending);
        $('#approvedApprovalCount').text(approved);
        $('#rejectedApprovalCount').text(rejected);
    }

    function findBranch(branchId) {
        return branchRows.find(function (branch) {
            return parseInt(branch.id) === parseInt(branchId);
        });
    }

    function statusBadge(status) {
        status = parseInt(status);
        if (status === 1) return '<span class="badge bg-success">Approved</span>';
        if (status === 2) return '<span class="badge bg-danger">Rejected</span>';
        return '<span class="badge bg-warning">Pending</span>';
    }

    function makeUsername(text) {
        let cleaned = (text || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        if (cleaned.length < 3) cleaned = 'branch';
        return cleaned + Math.floor(Math.random() * 900 + 100);
    }

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }
});
