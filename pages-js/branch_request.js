$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let branchRequestModalElement = document.getElementById('branchRequestModal');
    let branchRequestModal = branchRequestModalElement ? new bootstrap.Modal(branchRequestModalElement) : null;

    loadBranchRequests();

    $('#addBranchRequestBtn').on('click', function () {
        resetBranchRequestForm();
        if (branchRequestModal) {
            branchRequestModal.show();
        }
    });

    $(document).on('change', '#business_id', function () {
        loadMainBranches($(this).val());
    });

    $('#branchRequestForm').on('submit', function (e) {
        e.preventDefault();

        if ($.trim($('#branch_name').val()) === '') {
            showAppToast('warning', 'Please enter branch name.');
            $('#branch_name').focus();
            return;
        }

        setButtonLoading('saveBranchRequestBtn', 'Submitting...');

        $.ajax({
            url: window.BASE_URL + 'api/branch_request.php',
            type: 'POST',
            dataType: 'json',
            data: $('#branchRequestForm').serialize() + '&action=save_request',
            success: function (response) {
                if (response.status === true) {
                    showAppToast('success', response.message || 'Branch request submitted.');
                    if (branchRequestModal) { branchRequestModal.hide(); }
                    loadBranchRequests();
                } else {
                    handleApiError(response);
                }
                resetButtonLoading('saveBranchRequestBtn');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showAppToast('error', 'Server error. Please try again.');
                resetButtonLoading('saveBranchRequestBtn');
            }
        });
    });

    function loadBusinesses() {
        $.ajax({
            url: window.BASE_URL + 'api/branch_request.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_businesses' },
            success: function (response) {
                let html = '<option value="">Select Business</option>';

                if (response.status === true && response.data.businesses.length > 0) {
                    $.each(response.data.businesses, function (index, business) {
                        html += `<option value="${business.id}">${escapeHtml(business.business_name)}</option>`;
                    });
                }

                $('#business_id').html(html);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function loadMainBranches(businessId) {
        let html = '<option value="">Select Main Branch</option>';
        $('#parent_branch_id').html(html);

        if (!businessId) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/branch_request.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_main_branches',
                business_id: businessId
            },
            success: function (response) {
                if (response.status === true && response.data.branches.length > 0) {
                    $.each(response.data.branches, function (index, branch) {
                        html += `<option value="${branch.id}">${escapeHtml(branch.branch_name)}</option>`;
                    });
                }

                $('#parent_branch_id').html(html);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function loadBranchRequests() {
        $.ajax({
            url: window.BASE_URL + 'api/branch_request.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'list_requests' },
            success: function (response) {
                if (response.status === true) {
                    renderBranchRequests(response.data.requests);
                    updateCounts(response.data.requests);
                } else {
                    $('#branchRequestTableBody').html(`<tr><td colspan="7" class="text-center text-muted">${response.message}</td></tr>`);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#branchRequestTableBody').html('<tr><td colspan="7" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderBranchRequests(requests) {
        if (!requests || requests.length === 0) {
            $('#branchRequestTableBody').html('<tr><td colspan="7" class="text-center text-muted">No branch requests found.</td></tr>');
            return;
        }

        let html = '';

        $.each(requests, function (index, request) {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(request.business_name || '')}</td>
                    <td>${escapeHtml(request.main_branch_name || '')}</td>
                    <td>
                        <strong>${escapeHtml(request.branch_name || '')}</strong><br>
                        <small class="text-muted">${escapeHtml(request.branch_code || '')}</small>
                    </td>
                    <td>
                        ${escapeHtml(request.mobile || '')}<br>
                        <small>${escapeHtml(request.email || '')}</small>
                    </td>
                    <td>${statusBadge(request.approval_status)}</td>
                    <td>${formatDate(request.requested_at)}</td>
                </tr>
            `;
        });

        $('#branchRequestTableBody').html(html);
    }

    function updateCounts(requests) {
        let total = requests ? requests.length : 0;
        let pending = 0;
        let approved = 0;

        $.each(requests || [], function (index, request) {
            if (parseInt(request.approval_status) === 0) pending++;
            if (parseInt(request.approval_status) === 1) approved++;
        });

        $('#totalRequestsCount').text(total);
        $('#pendingRequestsCount').text(pending);
        $('#approvedRequestsCount').text(approved);
    }

    function statusBadge(status) {
        status = parseInt(status);
        if (status === 1) return '<span class="badge bg-success">Approved</span>';
        if (status === 2) return '<span class="badge bg-danger">Rejected</span>';
        return '<span class="badge bg-warning">Pending</span>';
    }

    function resetBranchRequestForm() {
        $('#branchRequestForm')[0].reset();
        if (window.USER_TYPE === 'platform_owner') {
            $('#parent_branch_id').html('<option value="">Select Main Branch</option>');
        }
    }
});

function escapeHtml(text) {
    return $('<div>').text(text || '').html();
}

function formatDate(dateValue) {
    if (!dateValue) return '';
    let date = new Date(dateValue.replace(' ', 'T'));
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
