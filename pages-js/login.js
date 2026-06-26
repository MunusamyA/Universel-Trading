$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    $('#togglePassword').on('click', function () {
        let passwordInput = $('#password');
        let icon = $(this).find('i');

        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('mdi-eye-outline').addClass('mdi-eye-off-outline');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('mdi-eye-off-outline').addClass('mdi-eye-outline');
        }
    });

    $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        let username = $.trim($('#username').val());
        let password = $.trim($('#password').val());
        let rememberMe = $('#remember_me').is(':checked') ? 1 : 0;
        let csrfToken = $('#csrf_token').val();

        if (username === '') {
            showToast('warning', 'Please enter username, email or mobile.', 5000);
            $('#username').focus();
            return;
        }

        if (password === '') {
            showToast('warning', 'Please enter password.', 5000);
            $('#password').focus();
            return;
        }

        if (csrfToken === '') {
            showToast('error', 'Security token missing. Please refresh the page.', 5000);
            return;
        }

        setButtonLoading('loginBtn', 'Logging in...');

        $.ajax({
            url: 'api/auth.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'login',
                username: username,
                password: password,
                remember_me: rememberMe,
                csrf_token: csrfToken
            },
            success: function (response) {
                if (response.status === true) {
                    showToast('success', response.message || 'Login successful.', 3000);

                    setTimeout(function () {
                        window.location.href = response.redirect || 'dashboard.php';
                    }, 700);
                } else {
                    handleApiError(response);
                    resetButtonLoading('loginBtn');
                }
            },
            error: function () {
                showToast('error', 'Server error. Please try again.', 5000);
                resetButtonLoading('loginBtn');
            }
        });
    });

});