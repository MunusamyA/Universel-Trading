$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let employeeId = parseInt(window.EMPLOYEE_ID || 0);
    loadRoles();

    if (employeeId > 0) {
        $('.edit-password-note').removeClass('d-none');
        $('.new-password-required').addClass('d-none');
        loadEmployee(employeeId);
    }

    $('#employeeForm').on('submit', function (e) {
        e.preventDefault();

        if ($.trim($('#employee_name').val()) === '') return warning('Please enter employee name.', '#employee_name');
        if ($.trim($('#username').val()) === '') return warning('Please enter username.', '#username');
        if ($('#role_id').val() === '') return warning('Please select branch role.', '#role_id');

        let mobile = $.trim($('#mobile').val());
        if (mobile !== '' && !/^[0-9]{10}$/.test(mobile)) return warning('Please enter valid 10 digit mobile number.', '#mobile');

        let email = $.trim($('#email').val());
        if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return warning('Please enter valid email address.', '#email');

        let password = $('#password').val();
        let confirmPassword = $('#confirm_password').val();

        if (employeeId <= 0 && password === '') return warning('Please enter password.', '#password');

        if (password !== '') {
            if (password.length < 6) return warning('Password must be minimum 6 characters.', '#password');
            if (password !== confirmPassword) return warning('Password and confirm password do not match.', '#confirm_password');
        }

        $('#saveEmployeeBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/employees.php',
            type: 'POST',
            dataType: 'json',
            data: $('#employeeForm').serialize() + '&action=save_employee',
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Employee saved.');
                    setTimeout(function () {
                        window.location.href = response.data && response.data.redirect ? response.data.redirect : window.BASE_URL + 'pages/employees.php';
                    }, 600);
                } else {
                    handleError(response);
                    $('#saveEmployeeBtn').prop('disabled', false).html('Save Employee');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error.');
                $('#saveEmployeeBtn').prop('disabled', false).html('Save Employee');
            }
        });
    });

    function loadRoles(selectedRoleId) {
        $.ajax({
            url: window.BASE_URL + 'api/employees.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_roles' },
            success: function (response) {
                let html = '<option value="">Select Role</option>';

                if (response.status === true) {
                    $.each(response.data.roles || [], function (_, role) {
                        let selected = parseInt(role.id) === parseInt(selectedRoleId || 0) ? 'selected' : '';
                        html += `<option value="${role.id}" ${selected}>${escapeHtml(role.role_name)}</option>`;
                    });
                }

                $('#role_id').html(html);
            },
            error: function (xhr) { console.log(xhr.responseText); }
        });
    }

    function loadEmployee(employeeId) {
        $.ajax({
            url: window.BASE_URL + 'api/employees.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_employee', employee_id: employeeId },
            success: function (response) {
                if (response.status === true) {
                    let employee = response.data.employee;
                    $('#employee_id').val(employee.id);
                    $('#employee_code').val(employee.employee_code);
                    $('#employee_name').val(employee.employee_name);
                    $('#username').val(employee.username);
                    $('#mobile').val(employee.mobile);
                    $('#email').val(employee.email);
                    $('#gender').val(employee.gender || 0);
                    $('#dob').val(employee.dob);
                    $('#joining_date').val(employee.joining_date);
                    $('#designation').val(employee.designation);
                    $('#salary').val(employee.salary || '0.00');
                    $('#status').val(employee.status);
                    $('#address').val(employee.address);
                    $('#city').val(employee.city);
                    $('#state').val(employee.state);
                    $('#pincode').val(employee.pincode);
                    loadRoles(employee.role_id);
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

    function warning(message, selector) { showToastSafe('warning', message); $(selector).focus(); return false; }
    function showToastSafe(type, message) { if (typeof showToast === 'function') showToast(type, message, 5000); else alert(message); }
    function handleError(response) { if (response && response.redirect) { window.location.href = response.redirect; return; } showToastSafe('error', response && response.message ? response.message : 'Something went wrong.'); }
    function escapeHtml(value) { return $('<div>').text(value === null || value === undefined ? '' : value).html(); }
});
