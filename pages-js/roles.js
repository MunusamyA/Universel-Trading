$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    let roleModal = new bootstrap.Modal(document.getElementById('roleModal'));

    loadRoles();
    loadMenuCount();

    $(document).on('change', '#statusSwitch', function () {
        $('#status').val($(this).is(':checked') ? '1' : '2');
    });

    $('#addRoleBtn').on('click', function () {
        resetRoleForm();
        $('#roleModalTitle').text((window.USER_TYPE || '') === 'platform_owner' ? 'Add Package Role' : 'Add Role');
        loadPermissionMenus(0);
        roleModal.show();
    });

    $('#roleForm').on('submit', function (e) {
        e.preventDefault();

        let roleName = $.trim($('#role_name').val());
        $('#status').val($('#statusSwitch').is(':checked') ? '1' : '2');

        if (roleName === '') {
            safeShowToast('warning', 'Please enter role name.', 5000);
            $('#role_name').focus();
            return;
        }

        safeSetButtonLoading('saveRoleBtn', 'Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'POST',
            dataType: 'json',
            data: $('#roleForm').serialize() + '&action=save_role',
            success: function (response) {
                if (response.status === true) {
                    safeShowToast('success', response.message || 'Role saved successfully.', 4000);
                    roleModal.hide();
                    loadRoles();
                    loadMenuCount();
                } else {
                    safeHandleApiError(response);
                }
                safeResetButtonLoading('saveRoleBtn');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                safeShowToast('error', 'Server error. Please try again.', 5000);
                safeResetButtonLoading('saveRoleBtn');
            }
        });
    });

    $(document).on('click', '.edit-role-btn', function () {
        let roleId = $(this).data('id');
        resetRoleForm();
        $('#roleModalTitle').text('Edit Role');

        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_role', role_id: roleId },
            success: function (response) {
                if (response.status === true) {
                    let role = response.data.role;
                    $('#role_id').val(role.id);
                    $('#role_name').val(role.role_name);
                    $('#description').val(role.description);
                    $('#status').val(role.status);
                    $('#statusSwitch').prop('checked', role.status == 1);
                    loadPermissionMenus(role.id);
                    roleModal.show();
                } else {
                    safeHandleApiError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                safeShowToast('error', 'Server error. Please try again.', 5000);
            }
        });
    });

    $(document).on('click', '.delete-role-btn', function () {
        let roleId = $(this).data('id');
        if (!confirm('Are you sure you want to delete this role?')) return;

        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'delete_role', role_id: roleId, csrf_token: getCsrfToken() },
            success: function (response) {
                if (response.status === true) {
                    safeShowToast('success', response.message || 'Role deleted successfully.', 4000);
                    loadRoles();
                    loadMenuCount();
                } else {
                    safeHandleApiError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                safeShowToast('error', 'Server error. Please try again.', 5000);
            }
        });
    });

    $(document).on('click', '#checkAllPermissions', function () {
        let boxes = $('#permissionTableBody input[type="checkbox"]:not(:disabled)');
        let checkedCount = boxes.filter(':checked').length;
        let totalCount = boxes.length;
        if (checkedCount === totalCount && totalCount > 0) {
            boxes.prop('checked', false);
            $(this).text('Check All');
        } else {
            boxes.prop('checked', true);
            $(this).text('Uncheck All');
        }
    });

    $(document).on('click', '#checkViewOnly', function () {
        $('#permissionTableBody input[type="checkbox"]:not(:disabled)').prop('checked', false);
        $('#permissionTableBody input[data-field="can_view"]:not(:disabled)').prop('checked', true);
        $('#checkAllPermissions').text('Check All');
    });

    $(document).on('change', '.permission-view-checkbox', function () {
        let menuId = $(this).data('menu-id');
        if (!$(this).is(':checked')) {
            $(`#permissionTableBody input[data-menu-id="${menuId}"]`).prop('checked', false);
        }
    });

    $(document).on('change', '.permission-action-checkbox', function () {
        let menuId = $(this).data('menu-id');
        if ($(this).is(':checked')) {
            $(`#permissionTableBody input[data-menu-id="${menuId}"][data-field="can_view"]:not(:disabled)`).prop('checked', true);
        }
    });

    function loadRoles() {
        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'list_roles' },
            success: function (response) {
                if (response.status === true) {
                    renderRoles(response.data.roles);
                    updateRoleCounts(response.data.roles);
                } else {
                    $('#rolesTableBody').html(`<tr><td colspan="5" class="text-center text-muted">${response.message}</td></tr>`);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#rolesTableBody').html(`<tr><td colspan="5" class="text-center text-danger">Server error.</td></tr>`);
            }
        });
    }

    function loadMenuCount() {
        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_permission_menus', role_id: 0 },
            success: function (response) {
                if (response.status === true) $('#menuCount').text(response.data.menus.length);
            }
        });
    }

    function renderRoles(roles) {
        if (!roles || roles.length === 0) {
            $('#rolesTableBody').html(`<tr><td colspan="5" class="text-center text-muted">No roles found.</td></tr>`);
            return;
        }

        let html = '';
        $.each(roles, function (index, role) {
            let statusBadge = role.status == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
            let nameBadge = role.role_type == 1 ? '<span class="badge bg-info ms-1">Package</span>' : '';
            let lockBadge = role.is_locked == 1 ? '<span class="badge bg-secondary ms-1">Locked</span>' : '';

            let actionButtons = '';
            if (role.is_locked == 1 && window.USER_TYPE !== 'platform_owner') {
                actionButtons = `<span class="badge bg-secondary">Locked</span>`;
            } else {
                actionButtons = `
                    <button type="button" class="btn btn-sm btn-primary edit-role-btn" data-id="${role.id}">Edit</button>
                    <button type="button" class="btn btn-sm btn-danger delete-role-btn" data-id="${role.id}">Delete</button>
                `;
            }

            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(role.role_name)} ${nameBadge} ${lockBadge}</td>
                    <td>${escapeHtml(role.description || '')}</td>
                    <td>${statusBadge}</td>
                    <td>${actionButtons}</td>
                </tr>
            `;
        });
        $('#rolesTableBody').html(html);
    }

    function updateRoleCounts(roles) {
        let total = roles ? roles.length : 0, active = 0, inactive = 0;
        $.each(roles || [], function (_, role) { role.status == 1 ? active++ : inactive++; });
        $('#totalRolesCount').text(total);
        $('#activeRolesCount').text(active);
        $('#inactiveRolesCount').text(inactive);
    }

    function loadPermissionMenus(roleId) {
        $('#permissionTableBody').html(`<tr><td colspan="12" class="text-center text-muted">Loading menus...</td></tr>`);
        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_permission_menus', role_id: roleId },
            success: function (response) {
                if (response.status === true) {
                    if (response.data && parseInt(response.data.is_locked || 0) === 1) {
                        $('#permissionTableBody').html(`<tr><td colspan="12" class="text-center text-muted">This role is package controlled. Menu permissions are not editable here.</td></tr>`);
                        return;
                    }
                    renderPermissionMenus(response.data.menus);
                } else {
                    $('#permissionTableBody').html(`<tr><td colspan="12" class="text-center text-muted">${response.message}</td></tr>`);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#permissionTableBody').html(`<tr><td colspan="12" class="text-center text-danger">Server error.</td></tr>`);
            }
        });
    }

    function renderPermissionMenus(menus) {
        if (!menus || menus.length === 0) {
            $('#permissionTableBody').html(`<tr><td colspan="12" class="text-center text-muted">No menu access found for this branch package. Please check Basic/Premium/Gold package permissions.</td></tr>`);
            return;
        }

        let html = '';
        $.each(menus, function (_, menu) {
            let isParent = !menu.parent_id || menu.parent_id == 0;
            let menuTitle = isParent ? menu.menu_name : '— ' + menu.menu_name;
            let menuClass = isParent ? 'fw-bold' : 'ps-4';
            html += `
                <tr>
                    <td class="${menuClass}">
                        <input type="hidden" name="menu_ids[]" value="${menu.id}">
                        ${escapeHtml(menuTitle)}
                    </td>
                    ${permissionCheckbox(menu.id, 'can_view', menu.can_view, menu.allowed_can_view, true)}
                    ${permissionCheckbox(menu.id, 'can_add', menu.can_add, menu.allowed_can_add, false)}
                    ${permissionCheckbox(menu.id, 'can_edit', menu.can_edit, menu.allowed_can_edit, false)}
                    ${permissionCheckbox(menu.id, 'can_delete', menu.can_delete, menu.allowed_can_delete, false)}
                    ${permissionCheckbox(menu.id, 'can_print', menu.can_print, menu.allowed_can_print, false)}
                    ${permissionCheckbox(menu.id, 'can_export', menu.can_export, menu.allowed_can_export, false)}
                    ${permissionCheckbox(menu.id, 'can_approve', menu.can_approve, menu.allowed_can_approve, false)}
                    ${permissionCheckbox(menu.id, 'can_convert', menu.can_convert, menu.allowed_can_convert, false)}
                    ${permissionCheckbox(menu.id, 'can_adjust', menu.can_adjust, menu.allowed_can_adjust, false)}
                    ${permissionCheckbox(menu.id, 'can_ship', menu.can_ship, menu.allowed_can_ship, false)}
                    ${permissionCheckbox(menu.id, 'can_generate_invoice', menu.can_generate_invoice, menu.allowed_can_generate_invoice, false)}
                </tr>`;
        });
        $('#permissionTableBody').html(html);
    }

    function permissionCheckbox(menuId, field, value, allowedValue, isView) {
        let checked = value == 1 ? 'checked' : '';
        let disabled = (window.USER_TYPE !== 'platform_owner' && allowedValue != 1) ? 'disabled' : '';
        let className = isView ? 'permission-view-checkbox' : 'permission-action-checkbox';
        return `
            <td class="text-center">
                <input type="checkbox" class="form-check-input ${className}" name="permissions[${menuId}][${field}]" value="1" data-menu-id="${menuId}" data-field="${field}" ${checked} ${disabled}>
            </td>`;
    }

    function resetRoleForm() {
        $('#roleForm')[0].reset();
        $('#role_id').val('');
        $('#status').val('1');
        $('#statusSwitch').prop('checked', true);
        $('#permissionTableBody').html('');
        $('#checkAllPermissions').text('Check All');
    }


    function getCsrfToken() {
        let tokenInput = $('input[name="csrf_token"]').first();
        if (tokenInput.length === 0) {
            tokenInput = $('#roleForm input[type="hidden"]').filter(function () {
                return this.name.toLowerCase().indexOf('csrf') !== -1;
            }).first();
        }
        return tokenInput.val() || '';
    }

    function safeShowToast(type, message, duration) {
        if (typeof showToast === 'function') {
            showToast(type, message, duration || 5000);
        } else {
            alert(message);
        }
    }

    function safeSetButtonLoading(buttonId, text) {
        if (typeof setButtonLoading === 'function') {
            setButtonLoading(buttonId, text);
        } else {
            $('#' + buttonId).prop('disabled', true).data('old-text', $('#' + buttonId).html()).html(text);
        }
    }

    function safeResetButtonLoading(buttonId) {
        if (typeof resetButtonLoading === 'function') {
            resetButtonLoading(buttonId);
        } else {
            let btn = $('#' + buttonId);
            btn.prop('disabled', false).html(btn.data('old-text') || 'Save Role');
        }
    }

    function safeHandleApiError(response) {
        if (typeof handleApiError === 'function') {
            safeHandleApiError(response);
        } else {
            safeShowToast('error', response && response.message ? response.message : 'Something went wrong.', 5000);
        }
    }

    function escapeHtml(text) { return $('<div>').text(text).html(); }
});
