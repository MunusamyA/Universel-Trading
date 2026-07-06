$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let profileContext = {
        can_view: false,
        can_edit: false,
        can_change_password: false
    };

    loadProfile();

    $('#profileForm').on('submit', function (e) {
        e.preventDefault();

        if (!profileContext.can_edit) {
            showToastSafe('error', 'Permission denied.');
            return false;
        }

        saveProfile();
    });

    $('#passwordForm').on('submit', function (e) {
        e.preventDefault();

        if (!profileContext.can_change_password) {
            showToastSafe('error', 'Permission denied.');
            return false;
        }

        changePassword();
    });

    function loadProfile() {
        $.ajax({
            url: window.BASE_URL + 'api/profile.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_profile' },
            success: function (response) {
                if (response.status === true) {
                    profileContext = response.data.context || profileContext;
                    renderProfile(response.data.user || {});
                    applyProfilePermissionContext();
                } else {
                    handleError(response);
                    disableProfileForms();
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error while loading profile.');
                disableProfileForms();
            }
        });
    }

    function applyProfilePermissionContext() {
        if (profileContext.can_edit) {
            $('#profileForm').find('input, select, textarea').not('[readonly]').prop('disabled', false);
            $('#saveProfileBtn').removeClass('d-none').prop('disabled', false);
        } else {
            $('#profileForm').find('input, select, textarea').not('[readonly]').prop('disabled', true);
            $('#saveProfileBtn').addClass('d-none').prop('disabled', true);
        }

        if (profileContext.can_change_password) {
            $('#passwordForm').find('input, button').prop('disabled', false);
            $('#changePasswordBtn').removeClass('d-none').prop('disabled', false);
        } else {
            $('#passwordForm').find('input, button').prop('disabled', true);
            $('#changePasswordBtn').addClass('d-none').prop('disabled', true);
        }
    }

    function disableProfileForms() {
        $('#profileForm, #passwordForm').find('input, select, textarea, button').prop('disabled', true);
        $('#saveProfileBtn, #changePasswordBtn').addClass('d-none');
    }

    function saveProfile() {
        if ($.trim($('#name').val()) === '') {
            return warn('Please enter name.', '#name');
        }

        let formData = new FormData($('#profileForm')[0]);
        formData.append('action', 'update_profile');

        $('#saveProfileBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/profile.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Profile updated.');
                    loadProfile();
                } else {
                    handleError(response);
                }

                $('#saveProfileBtn').prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Save Profile');
                applyProfilePermissionContext();
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error while saving profile.');
                $('#saveProfileBtn').prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Save Profile');
                applyProfilePermissionContext();
            }
        });

        return false;
    }

    function changePassword() {
        let currentPassword = $.trim($('#current_password').val());
        let newPassword = $.trim($('#new_password').val());
        let confirmPassword = $.trim($('#confirm_password').val());

        if (currentPassword === '') return warn('Please enter current password.', '#current_password');
        if (newPassword.length < 6) return warn('New password must be minimum 6 characters.', '#new_password');
        if (newPassword !== confirmPassword) return warn('Confirm password does not match.', '#confirm_password');

        let data = $('#passwordForm').serialize() + '&action=change_password';

        $('#changePasswordBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Updating...');

        $.ajax({
            url: window.BASE_URL + 'api/profile.php',
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Password changed.');
                    $('#passwordForm')[0].reset();
                } else {
                    handleError(response);
                }

                $('#changePasswordBtn').prop('disabled', false).html('<i class="mdi mdi-lock-reset me-1"></i> Change Password');
                applyProfilePermissionContext();
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error while changing password.');
                $('#changePasswordBtn').prop('disabled', false).html('<i class="mdi mdi-lock-reset me-1"></i> Change Password');
                applyProfilePermissionContext();
            }
        });

        return false;
    }

    function renderProfile(user) {
        $('#name').val(user.name || '');
        $('#username').val(user.username || '');
        $('#email').val(user.email || '');
        $('#mobile').val(user.mobile || '');

        $('#profileNameText').text(user.name || '-');
        $('#profileUsernameText').text(user.username ? '@' + user.username : '-');
        $('#profileEmailText').text(user.email || '-');
        $('#profileMobileText').text(user.mobile || '-');

        $('#profileRoleText').text(user.role_name || '-');
        $('#profileBusinessText').text(user.business_name || '-');
        $('#profileBranchText').text(user.branch_name || '-');

        $('#profileTypeText').text(formatUserType(user.user_type || 'business_user'));
        $('#profileStatusText').text(parseInt(user.status || 1) === 1 ? 'Active' : 'Inactive')
            .removeClass('bg-success bg-danger')
            .addClass(parseInt(user.status || 1) === 1 ? 'bg-success' : 'bg-danger');

        $('#profileInitials').text(makeInitials(user.name || user.username || 'U'));
    }

    function makeInitials(value) {
        value = $.trim(value || '');

        if (value === '') return 'U';

        let parts = value.split(/\s+/);

        if (parts.length === 1) {
            return parts[0].substring(0, 2).toUpperCase();
        }

        return (parts[0].substring(0, 1) + parts[1].substring(0, 1)).toUpperCase();
    }

    function formatUserType(type) {
        if (type === 'platform_owner') return 'Platform Owner';

        return 'Business User';
    }

    function warn(msg, selector) {
        showToastSafe('warning', msg);
        $(selector).focus();

        return false;
    }

    function showToastSafe(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
        } else if (typeof toastr !== 'undefined') {
            toastr[type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'error'](message);
        } else {
            alert(message);
        }
    }

    function handleError(response) {
        if (response && response.redirect) {
            window.location.href = response.redirect;
            return;
        }

        showToastSafe('error', response && response.message ? response.message : 'Something went wrong.');
    }
});
