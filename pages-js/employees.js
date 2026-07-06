$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let searchTimer = null;

    let pageContext = {
        can_view: false,
        can_list: false,
        can_add: false,
        can_edit: false,
        can_delete: false,
        page_title: 'Employees',
        page_note: '',
        add_button_label: 'Add Employee',
        form_url: ''
    };

    loadPageContext();

    $('#refreshEmployeesBtn').on('click', loadEmployees);
    $('#employeeStatusFilter, #roleFilter').on('change', loadEmployees);

    $('#employeeSearch').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadEmployees, 400);
    });

    $(document).on('click', '.delete-employee-btn', function () {
        if (!pageContext.can_delete) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        let employeeId = $(this).data('id');

        if (!confirm('Are you sure you want to delete this employee?')) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_employee',
                employee_id: employeeId,
                csrf_token: $('input[name="csrf_token"]').first().val()
            },
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Employee deleted.');
                    loadEmployees();
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
                    loadRoles();
                    loadEmployees();
                } else {
                    $('#employeeTableBody').html('<tr><td colspan="10" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#addEmployeeBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#employeeTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>');
                $('#addEmployeeBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageTitleText').text(pageContext.page_title || 'Employees');
        $('#pageNoteText').text(pageContext.page_note || '');
        $('#addEmployeeBtnText').text(pageContext.add_button_label || 'Add Employee');

        if (pageContext.form_url) {
            $('#addEmployeeBtn').attr('href', pageContext.form_url);
        }

        if (pageContext.can_add) {
            $('#addEmployeeBtn').removeClass('d-none');
        } else {
            $('#addEmployeeBtn').addClass('d-none');
        }
    }

    function loadEmployees() {
        if (!pageContext.can_view && !pageContext.can_list) {
            $('#employeeTableBody').html('<tr><td colspan="10" class="text-center text-danger">Permission denied.</td></tr>');
            return;
        }

        $('#employeeTableBody').html('<tr><td colspan="10" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_employees',
                search: $('#employeeSearch').val(),
                status: $('#employeeStatusFilter').val(),
                role_id: $('#roleFilter').val()
            },
            success: function (response) {
                if (response.status === true) {
                    renderEmployeeRows(response.data.employees || []);
                    renderStats(response.data.stats || {});
                } else {
                    $('#employeeTableBody').html('<tr><td colspan="10" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load employees.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#employeeTableBody').html('<tr><td colspan="10" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderEmployeeRows(employees) {
        if (!employees || employees.length === 0) {
            $('#employeeTableBody').html('<tr><td colspan="10" class="text-center text-muted">No employees found.</td></tr>');
            return;
        }

        let html = '';

        $.each(employees, function (index, employee) {
            let actionHtml = '';

            if (employee.can_edit) {
                actionHtml += '<a href="' + window.BASE_URL + 'pages/employee-form.php?id=' + employee.id + '" class="btn btn-outline-primary btn-sm" title="Edit"><i class="mdi mdi-pencil"></i></a>';
            }

            if (employee.can_delete) {
                actionHtml += '<button type="button" class="btn btn-outline-danger btn-sm delete-employee-btn ms-1" data-id="' + employee.id + '" title="Delete"><i class="mdi mdi-delete"></i></button>';
            }

            if (actionHtml === '') {
                actionHtml = '<span class="text-muted">No access</span>';
            }

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(employee.employee_code || '-') + '</strong></td>';
            html += '<td>' + escapeHtml(employee.employee_name || '') + '</td>';
            html += '<td>' + escapeHtml(employee.username || '') + '</td>';
            html += '<td><div>' + escapeHtml(employee.mobile || '-') + '</div><small class="text-muted">' + escapeHtml(employee.email || '') + '</small></td>';
            html += '<td>' + escapeHtml(employee.designation || '-') + '</td>';
            html += '<td>' + escapeHtml(employee.role_name || '-') + '</td>';
            html += '<td>' + currency(employee.salary) + '</td>';
            html += '<td>' + statusBadge(employee.status) + '</td>';
            html += '<td><div class="btn-group btn-group-sm">' + actionHtml + '</div></td>';
            html += '</tr>';
        });

        $('#employeeTableBody').html(html);
    }

    function loadRoles() {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_roles'
            },
            success: function (response) {
                let html = '<option value="0">All Roles</option>';

                if (response.status === true) {
                    $.each(response.data.roles || [], function (_, role) {
                        html += '<option value="' + role.id + '">' + escapeHtml(role.role_name) + '</option>';
                    });
                }

                $('#roleFilter').html(html);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
            }
        });
    }

    function renderStats(stats) {
        $('#totalEmployeesCount').text(stats.total_employees || 0);
        $('#activeEmployeesCount').text(stats.active_employees || 0);
        $('#inactiveEmployeesCount').text(stats.inactive_employees || 0);
    }

    function statusBadge(status) {
        return parseInt(status) === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Inactive</span>';
    }

    function currency(value) {
        let numberValue = parseFloat(value);

        if (isNaN(numberValue)) {
            numberValue = 0;
        }

        return '₹' + numberValue.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
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

        showToastSafe('error', response && response.message ? response.message : 'Something went wrong.');
    }

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

});
