$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let modal = new bootstrap.Modal(document.getElementById('recordModal'));
    let timer = null;

    loadRecords();

    $('#addBtn').on('click', function () {
        resetForm();
        $('#modalTitle').text('Add Zone');
        modal.show();
    });

    $('#refreshBtn').on('click', function () {
        loadRecords();
    });

    $('#statusFilter').on('change', function () {
        loadRecords();
    });

    $('#searchInput').on('keyup', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            loadRecords();
        }, 400);
    });

    $(document).on('input', '.text-uppercase', function () {
        $(this).val($(this).val().toUpperCase());
    });

    $('#recordForm').on('submit', function (e) {
        e.preventDefault();

        if ($.trim($('#zone_name').val()) === '') {
            showToastSafe('warning', 'Please enter zone name.');
            $('#zone_name').focus();
            return;
        }

        $('#saveBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/zones.php',
            type: 'POST',
            dataType: 'json',
            data: $('#recordForm').serialize() + '&action=save',
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Saved.');
                    modal.hide();
                    loadRecords();
                } else {
                    handleError(response);
                }
                $('#saveBtn').prop('disabled', false).html('Save Zone');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#saveBtn').prop('disabled', false).html('Save Zone');
            }
        });
    });

    $(document).on('click', '.edit-btn', function () {
        let id = $(this).data('id');

        $.getJSON(window.BASE_URL + 'api/zones.php', {
            action: 'get',
            id: id
        }, function (response) {
            if (response.status === true) {
                let row = response.data.record;

                resetForm();
                $('#modalTitle').text('Edit Zone');
                $('#id').val(row.id);
                $('#zone_name').val(row.zone_name);
                $('#zone_code').val(row.zone_code);
                $('#description').val(row.description);
                $('#status').val(row.status);

                modal.show();
            } else {
                handleError(response);
            }
        });
    });

    $(document).on('click', '.delete-btn', function () {
        if (!confirm('Are you sure you want to delete this zone?')) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/zones.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete',
                id: $(this).data('id'),
                csrf_token: $('input[name="csrf_token"]').first().val()
            },
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Deleted.');
                    loadRecords();
                } else {
                    handleError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
            }
        });
    });

    function loadRecords() {
        $('#tableBody').html('<tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>');

        $.getJSON(window.BASE_URL + 'api/zones.php', {
            action: 'list',
            search: $('#searchInput').val(),
            status: $('#statusFilter').val()
        }, function (response) {
            if (response.status === true) {
                renderRows(response.data.records || []);
                renderStats(response.data.stats || {});
            } else {
                $('#tableBody').html(`<tr><td colspan="7" class="text-center text-danger">${escapeHtml(response.message || 'Unable to load zones.')}</td></tr>`);
            }
        });
    }

    function renderRows(rows) {
        if (!rows.length) {
            $('#tableBody').html('<tr><td colspan="7" class="text-center text-muted">No zones found.</td></tr>');
            return;
        }

        let html = '';

        $.each(rows, function (i, row) {
            html += `
                <tr>
                    <td>${i + 1}</td>
                    <td><strong>${escapeHtml(row.zone_name || '')}</strong></td>
                    <td>${escapeHtml(row.zone_code || '-')}</td>
                    <td>${escapeHtml(row.description || '-')}</td>
                    <td>${statusBadge(row.status)}</td>
                    <td>${formatDate(row.created_at)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary edit-btn" data-id="${row.id}" title="Edit">
                                <i class="mdi mdi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger delete-btn" data-id="${row.id}" title="Delete">
                                <i class="mdi mdi-delete"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#tableBody').html(html);
    }

    function renderStats(stats) {
        $('#totalCount').text(stats.total || 0);
        $('#activeCount').text(stats.active || 0);
        $('#inactiveCount').text(stats.inactive || 0);
    }

    function resetForm() {
        $('#recordForm')[0].reset();
        $('#id').val('');
        $('#status').val('1');
        $('#saveBtn').prop('disabled', false).html('Save Zone');
    }

    function statusBadge(status) {
        return parseInt(status) === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Inactive</span>';
    }

    function formatDate(dateValue) {
        if (!dateValue) return '-';

        let date = new Date(String(dateValue).replace(' ', 'T'));

        if (isNaN(date.getTime())) {
            return dateValue;
        }

        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function showToastSafe(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        alert(message);
    }

    function handleError(response) {
        if (response && response.redirect) {
            window.location.href = response.redirect;
            return;
        }

        showToastSafe('error', (response && response.message) ? response.message : 'Something went wrong.');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }
});
