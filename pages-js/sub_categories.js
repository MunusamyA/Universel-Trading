$(document).ready(function () {
    $('#preloader').fadeOut('slow');
    let modal = new bootstrap.Modal(document.getElementById('recordModal'));
    let timer = null;

    loadRecords();
    loadCategories();

    $('#addBtn').on('click', function() {
        resetForm();
        $('#modalTitle').text('Add');
        loadCategories();
        modal.show();
    });

    $('#refreshBtn, #statusFilter').on('click change', loadRecords);
    $('#searchInput').on('keyup', function() { clearTimeout(timer); timer = setTimeout(loadRecords, 400); });

    $('#recordForm').on('submit', function(e) {
        e.preventDefault();
        if ($('#category_id').val() === '') { showMsg('Please select category.'); return; } if ($.trim($('#sub_category_name').val()) === '') { showMsg('Please enter sub category name.'); return; }
        $('#saveBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: $('#recordForm').serialize() + '&action=save',
            success: function(response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Saved.');
                    modal.hide();
                    loadRecords();
                } else {
                    handleError(response);
                }
                $('#saveBtn').prop('disabled', false).html('Save');
            },
            error: function(xhr) { console.log(xhr.responseText); showToastSafe('error', 'Server error.'); $('#saveBtn').prop('disabled', false).html('Save'); }
        });
    });

    $(document).on('click', '.edit-btn', function() {
        $.getJSON(window.BASE_URL + 'api/' + window.MASTER_FILE + '.php', {action:'get', id:$(this).data('id')}, function(response) {
            if (response.status === true) {
                let row = response.data.record;
                resetForm();
                $('#modalTitle').text('Edit');
                $('#id').val(row.id);
                $('#category_id').val(row.category_id); $('#sub_category_name').val(row.sub_category_name); $('#description').val(row.description);
                $('#status').val(row.status);
                loadCategories(row.category_id);
                modal.show();
            } else handleError(response);
        });
    });

    $(document).on('click', '.delete-btn', function() {
        if (!confirm('Are you sure?')) return;
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: { action:'delete', id:$(this).data('id'), csrf_token:$('input[name="csrf_token"]').first().val() },
            success: function(response) { if (response.status === true) { showToastSafe('success', response.message); loadRecords(); } else handleError(response); },
            error: function(xhr) { console.log(xhr.responseText); showToastSafe('error', 'Server error.'); }
        });
    });

    function loadRecords() {
        $('#tableBody').html('<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>');
        $.getJSON(window.BASE_URL + 'api/' + window.MASTER_FILE + '.php', {action:'list', search:$('#searchInput').val(), status:$('#statusFilter').val()}, function(response) {
            if (response.status === true) {
                renderRows(response.data.records || []);
                renderStats(response.data.stats || {});
            } else $('#tableBody').html(`<tr><td colspan="8" class="text-center text-danger">${escapeHtml(response.message)}</td></tr>`);
        });
    }

    function renderRows(rows) {
        if (!rows.length) { $('#tableBody').html('<tr><td colspan="8" class="text-center text-muted">No records found.</td></tr>'); return; }
        let html = '';
        $.each(rows, function(i, row) {
            html += `<tr><td>${i+1}</td><td>${escapeHtml(row.category_name || '-')}</td><td>${escapeHtml(row.sub_category_name || '')}</td><td>${escapeHtml(row.description || '')}</td><td>${statusBadge(row.status)}</td><td><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary edit-btn" data-id="${row.id}"><i class="mdi mdi-pencil"></i></button><button class="btn btn-outline-danger delete-btn" data-id="${row.id}"><i class="mdi mdi-delete"></i></button></div></td></tr>`;
        });
        $('#tableBody').html(html);
    }

    function renderStats(stats) {
        $('#totalCount').text(stats.total || 0);
        $('#activeCount').text(stats.active || 0);
        $('#inactiveCount').text(stats.inactive || 0);
    }

    function resetForm() { $('#recordForm')[0].reset(); $('#id').val(''); $('#status').val('1'); }
    function statusBadge(s) { return parseInt(s) === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; }
    function percent(v) { return parseFloat(v || 0).toFixed(2) + '%'; }
    function escapeHtml(v) { return $('<div>').text(v === null || v === undefined ? '' : v).html(); }
    function showToastSafe(type, msg) { if (typeof showToast === 'function') showToast(type, msg, 5000); else alert(msg); }
    function handleError(response) { if (response && response.redirect) window.location.href = response.redirect; else showToastSafe('error', response.message || 'Something went wrong.'); }

    function loadCategories(selectedId) {
        $.getJSON(window.BASE_URL + 'api/sub_categories.php', {action:'categories'}, function(response) {
            let html = '<option value="">Select Category</option>';
            if (response.status === true) {
                $.each(response.data.categories || [], function(_, c) {
                    let selected = parseInt(c.id) === parseInt(selectedId || 0) ? 'selected' : '';
                    html += `<option value="${c.id}" ${selected}>${escapeHtml(c.category_name)}</option>`;
                });
            }
            $('#category_id').html(html);
        });
    }

});