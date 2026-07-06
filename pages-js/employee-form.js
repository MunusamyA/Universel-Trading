$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let employeeId = parseInt(window.EMPLOYEE_ID || $('#employee_id').val() || 0);

    let pageContext = {
        can_add: false,
        can_edit: false,
        add_form_title: 'Add Employee',
        edit_form_title: 'Edit Employee',
        list_url: ''
    };

    loadPageContext();

    $('#employeeForm').on('submit', function (e) {
        e.preventDefault();

        let currentEmployeeId = parseInt($('#employee_id').val() || 0);

        if (currentEmployeeId > 0 && !pageContext.can_edit) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if (currentEmployeeId <= 0 && !pageContext.can_add) {
            showToastSafe('error', 'Permission denied.');
            return;
        }

        if ($.trim($('#employee_name').val()) === '') {
            showToastSafe('warning', 'Please enter employee name.');
            $('#employee_name').focus();
            return;
        }

        if ($.trim($('#username').val()) === '') {
            showToastSafe('warning', 'Please enter username.');
            $('#username').focus();
            return;
        }

        let username = $.trim($('#username').val());

        if (!/^[A-Za-z0-9_.-]{3,100}$/.test(username)) {
            showToastSafe('warning', 'Username minimum 3 characters. Use letters, numbers, dot, hyphen, underscore only.');
            $('#username').focus();
            return;
        }

        let email = $.trim($('#email').val());

        if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showToastSafe('warning', 'Please enter valid email.');
            $('#email').focus();
            return;
        }

        let mobile = $.trim($('#mobile').val());

        if (mobile !== '' && !/^[0-9]{10}$/.test(mobile)) {
            showToastSafe('warning', 'Please enter valid 10 digit mobile.');
            $('#mobile').focus();
            return;
        }

        if (parseInt($('#role_id').val() || 0) <= 0) {
            showToastSafe('warning', 'Please select role.');
            $('#role_id').focus();
            return;
        }

        let password = $('#password').val() || '';
        let confirmPassword = $('#confirm_password').val() || '';

        if (currentEmployeeId <= 0 && $.trim(password) === '') {
            showToastSafe('warning', 'Please enter password.');
            $('#password').focus();
            return;
        }

        if ($.trim(password) !== '') {
            if (password.length < 6) {
                showToastSafe('warning', 'Password must be minimum 6 characters.');
                $('#password').focus();
                return;
            }

            if (password !== confirmPassword) {
                showToastSafe('warning', 'Password and confirm password do not match.');
                $('#confirm_password').focus();
                return;
            }
        }

        let salary = parseFloat($('#salary').val() || 0);

        if (salary < 0) {
            showToastSafe('warning', 'Salary cannot be negative.');
            $('#salary').focus();
            return;
        }

        setButtonLoading('saveEmployeeBtn', 'Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'POST',
            dataType: 'json',
            data: $('#employeeForm').serialize() + '&action=save_employee',
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Employee saved.');

                    setTimeout(function () {
                        window.location.href = response.data.redirect || (window.BASE_URL + 'pages/employees.php');
                    }, 500);
                } else {
                    handleError(response);
                    resetButtonLoading('saveEmployeeBtn');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                resetButtonLoading('saveEmployeeBtn');
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
                    loadRoles(function () {
                        if (employeeId > 0) {
                            loadEmployee(employeeId);
                        }
                    });
                } else {
                    showToastSafe('error', response.message || 'Permission denied.');
                    $('#employeeForm :input').prop('disabled', true);
                    $('#saveEmployeeBtn').prop('disabled', true);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#employeeForm :input').prop('disabled', true);
                $('#saveEmployeeBtn').prop('disabled', true);
            }
        });
    }

    function applyPageContext() {
        if (pageContext.list_url) {
            $('#backEmployeesBtn').attr('href', pageContext.list_url);
        }

        if (employeeId > 0) {
            $('#employeePageTitle').text(pageContext.edit_form_title || 'Edit Employee');
            $('.edit-password-note').removeClass('d-none');
            $('.new-password-required').addClass('d-none');

            if (!pageContext.can_edit) {
                showToastSafe('error', 'Permission denied.');
                $('#employeeForm :input').prop('disabled', true);
                $('#saveEmployeeBtn').prop('disabled', true);
            }
        } else {
            $('#employeePageTitle').text(pageContext.add_form_title || 'Add Employee');
            $('.edit-password-note').addClass('d-none');
            $('.new-password-required').removeClass('d-none');

            if (!pageContext.can_add) {
                showToastSafe('error', 'Permission denied.');
                $('#employeeForm :input').prop('disabled', true);
                $('#saveEmployeeBtn').prop('disabled', true);
            }
        }
    }

    function loadRoles(callback) {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_roles'
            },
            success: function (response) {
                let html = '<option value="">Select Role</option>';

                if (response.status === true) {
                    $.each(response.data.roles || [], function (_, role) {
                        html += '<option value="' + role.id + '">' + escapeHtml(role.role_name) + '</option>';
                    });
                }

                $('#role_id').html(html);

                if (typeof callback === 'function') {
                    callback();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);

                if (typeof callback === 'function') {
                    callback();
                }
            }
        });
    }

    function loadEmployee(id) {
        $.ajax({
            url: window.BASE_URL + 'api/' + window.MASTER_FILE + '.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_employee',
                employee_id: id
            },
            success: function (response) {
                if (response.status === true) {
                    let employee = response.data.employee || {};

                    $('#employee_id').val(employee.id || '');
                    $('#employee_code').val(employee.employee_code || '');
                    $('#employee_name').val(employee.employee_name || '');
                    $('#username').val(employee.username || '');
                    $('#role_id').val(employee.role_id || '');
                    $('#mobile').val(employee.mobile || '');
                    $('#email').val(employee.email || '');
                    $('#gender').val(employee.gender || 0);
                    $('#dob').val(employee.dob || '');
                    $('#joining_date').val(employee.joining_date || '');
                    $('#designation').val(employee.designation || '');
                    $('#salary').val(parseFloat(employee.salary || 0).toFixed(2));
                    $('#status1').val(employee.status || 1);
                    $('#address').val(employee.address || '');
                    $('#city').val(employee.city || '');
                    $('#state').val(employee.state || '');
                    $('#pincode').val(employee.pincode || '');
                } else {
                    handleError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
            }
        });
    }

    function setButtonLoading(buttonId, text) {
        $('#' + buttonId).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>' + text);
    }

    function resetButtonLoading(buttonId) {
        $('#' + buttonId).prop('disabled', false).html('Save Employee');
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
