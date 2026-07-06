$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let modal = new bootstrap.Modal(document.getElementById('recordModal'));
    let timer = null;

    let pageContext = {
        can_view: false,
        can_list: false,
        can_add: false,
        can_edit: false,
        can_delete: false,
        page_title: 'Sub Categories',
        page_note: '',
        add_button_label: 'Add Sub Category',
        add_modal_title: 'Add Sub Category',
        edit_modal_title: 'Edit Sub Category'
    };

    loadPageContext();

    $('#addBtn').on('click', function () {
        if (!pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        resetForm();
        $('#modalTitle').text(pageContext.add_modal_title || 'Add Sub Category');
        loadCategories();
        modal.show();
    });

    $('#refreshBtn, #statusFilter').on('click change', loadRecords);

    $('#searchInput').on('keyup', function () {
        clearTimeout(timer);
        timer = setTimeout(loadRecords, 400);
    });

    $('#recordForm').on('submit', function (e) {
        e.preventDefault();

        let recordId = parseInt($('#id').val() || 0);

        if (recordId > 0 && !pageContext.can_edit) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if (recordId <= 0 && !pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if ($('#category_id').val() === '') {
            showToastSafe('warning', 'Please select category.');
            $('#category_id').focus();
            return;
        }

        if ($.trim($('#sub_category_name').val()) === '') {
            showToastSafe('warning', 'Please enter sub category name.');
            $('#sub_category_name').focus();
            return;
        }

        $('#saveBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
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

                $('#saveBtn').prop('disabled', false).html('Save');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#saveBtn').prop('disabled', false).html('Save');
            }
        });
    });

    $(document).on('click', '.edit-btn', function () {
        if (!pageContext.can_edit) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        $.getJSON(
            window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            {
                action: 'get',
                id: $(this).data('id')
            },
            function (response) {
                if (response.status === true) {
                    let row = response.data.record;

                    resetForm();

                    $('#modalTitle').text(pageContext.edit_modal_title || 'Edit Sub Category');
                    $('#id').val(row.id);
                    $('#sub_category_name').val(row.sub_category_name || '');
                    $('#description').val(row.description || '');
                    $('#status1').val(row.status || 1);

                    loadCategories(row.category_id);

                    modal.show();
                } else {
                    handleError(response);
                }
            }
        );
    });

    $(document).on('click', '.delete-btn', function () {
        if (!pageContext.can_delete) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if (!confirm('Are you sure?')) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
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
                    loadRecords();
                } else {
                    $('#tableBody').html('<tr><td colspan="6" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#addBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#tableBody').html('<tr><td colspan="6" class="text-center text-danger">Server error.</td></tr>');
                $('#addBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Sub Categories');
        $('#pageNoteText').text(pageContext.page_note || '');
        $('#addBtnText').text(pageContext.add_button_label || 'Add Sub Category');

        if (pageContext.can_add) {
            $('#addBtn').removeClass('d-none');
        } else {
            $('#addBtn').addClass('d-none');
        }
    }

    function loadRecords() {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#tableBody').html('<tr><td colspan="6" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $('#tableBody').html('<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>');

        $.getJSON(
            window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            {
                action: 'list',
                search: $('#searchInput').val(),
                status: $('#statusFilter').val()
            },
            function (response) {
                if (response.status === true) {
                    renderRows(response.data.records || []);
                    renderStats(response.data.stats || {});
                } else {
                    $('#tableBody').html('<tr><td colspan="6" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load records.') + '</td></tr>');
                }
            }
        );
    }

    function renderRows(rows) {
        if (!rows.length) {
            $('#tableBody').html('<tr><td colspan="6" class="text-center text-muted">No records found.</td></tr>');
            return;
        }

        let html = '';

        $.each(rows, function (i, row) {
            let actionHtml = '';

            if (row.can_edit) {
                actionHtml += '<button class="btn btn-outline-primary btn-sm edit-btn" data-id="' + row.id + '"><i class="mdi mdi-pencil"></i></button>';
            }

            if (row.can_delete) {
                actionHtml += '<button class="btn btn-outline-danger btn-sm delete-btn ms-1" data-id="' + row.id + '"><i class="mdi mdi-delete"></i></button>';
            }

            if (actionHtml === '') {
                actionHtml = '<span class="text-muted">No access</span>';
            }

            html += '<tr>';
            html += '<td>' + (i + 1) + '</td>';
            html += '<td>' + escapeHtml(row.category_name || '-') + '</td>';
            html += '<td>' + escapeHtml(row.sub_category_name || '') + '</td>';
            html += '<td>' + escapeHtml(row.description || '') + '</td>';
            html += '<td>' + statusBadge(row.status) + '</td>';
            html += '<td>' + actionHtml + '</td>';
            html += '</tr>';
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
        $('#category_id').html('<option value="">Select Category</option>');
        $('#status1').val('1');
        $('#saveBtn').prop('disabled', false).html('Save');
    }

    function statusBadge(status) {
        return parseInt(status) === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Inactive</span>';
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function showToastSafe(type, message) {
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

    function handleError(response) {
        if (response && response.redirect) {
            window.location.href = response.redirect;
            return;
        }

        showToastSafe('error', response.message || 'Something went wrong.');
    }

    function loadCategories(selectedId) {
        $.getJSON(
            window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            {
                action: 'categories'
            },
            function (response) {
                let html = '<option value="">Select Category</option>';

                if (response.status === true) {
                    $.each(response.data.categories || [], function (_, category) {
                        let selected = parseInt(category.id) === parseInt(selectedId || 0) ? 'selected' : '';
                        html += '<option value="' + category.id + '" ' + selected + '>' + escapeHtml(category.category_name) + '</option>';
                    });
                }

                $('#category_id').html(html);
            }
        );
    }

});
