$(document).ready(function () {

    $('#preloader').fadeOut('slow');

    let roleModalElement = document.getElementById('roleModal');
    let roleModal = roleModalElement ? new bootstrap.Modal(roleModalElement) : null;

    let pageContext = {
        user_type: '',
        is_platform_owner: false,
        can_view: false,
        can_add: false,
        can_edit: false,
        can_delete: false,
        add_button_label: 'Add Role',
        add_modal_title: 'Add Role',
        edit_modal_title: 'Edit Role',
        page_note: '',
        modal_note: '',
        permission_source_note: ''
    };

    loadPageContext();

    $('#refreshRolesBtn').on('click', function () {
        loadPageContext();
    });

    $('#statusSwitch').on('change', function () {
        $('#status').val($(this).is(':checked') ? '1' : '2');
    });

    $('#addRoleBtn').on('click', function () {
        if (!pageContext.can_add) {
            notify('error', 'Permission denied.');
            return;
        }

        resetRoleForm();

        $('#roleModalTitle').text(pageContext.add_modal_title || 'Add Role');

        if (roleModal) {
            roleModal.show();
        }

        loadPermissionMenus(0);
    });

    $('#roleModal').on('shown.bs.modal', function () {
        let roleId = parseInt($('#role_id').val() || 0);

        if (roleId === 0 && $('#permissionTableBody').data('loaded-add-mode') !== 1) {
            loadPermissionMenus(0);
        }
    });

    $('#roleForm').on('submit', function (e) {
        e.preventDefault();

        let roleId = parseInt($('#role_id').val() || 0);

        if (roleId > 0 && !pageContext.can_edit) {
            notify('error', 'Permission denied.');
            return;
        }

        if (roleId === 0 && !pageContext.can_add) {
            notify('error', 'Permission denied.');
            return;
        }

        let roleName = $.trim($('#role_name').val());
        $('#status').val($('#statusSwitch').is(':checked') ? '1' : '2');

        if (roleName === '') {
            notify('warning', 'Please enter role name.');
            $('#role_name').focus();
            return;
        }

        setLoading('saveRoleBtn', 'Saving...');

        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'POST',
            dataType: 'json',
            data: $('#roleForm').serialize() + '&action=save_role',
            success: function (response) {
                if (response.status === true) {
                    notify('success', response.message || 'Role saved successfully.');

                    if (roleModal) {
                        roleModal.hide();
                    }

                    loadRoles();
                    loadMenuCount();
                } else {
                    notify('error', response.message || 'Unable to save role.');
                }

                resetLoading('saveRoleBtn', 'Save Role');
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                notify('error', 'Server error. Please try again.');
                resetLoading('saveRoleBtn', 'Save Role');
            }
        });
    });

    $(document).on('click', '.edit-role-btn', function () {
        if (!pageContext.can_edit) {
            notify('error', 'Permission denied.');
            return;
        }

        let roleId = parseInt($(this).data('id') || 0);

        resetRoleForm();
        $('#roleModalTitle').text(pageContext.edit_modal_title || 'Edit Role');

        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_role',
                role_id: roleId
            },
            success: function (response) {
                if (response.status === true) {
                    let role = response.data.role || {};

                    $('#role_id').val(role.id);
                    $('#role_name').val(role.role_name || '');
                    $('#description').val(role.description || '');
                    $('#status').val(role.status || 1);
                    $('#statusSwitch').prop('checked', parseInt(role.status || 1) === 1);

                    if (roleModal) {
                        roleModal.show();
                    }

                    loadPermissionMenus(role.id);
                } else {
                    notify('error', response.message || 'Unable to load role.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                notify('error', 'Server error. Please try again.');
            }
        });
    });

    $(document).on('click', '.delete-role-btn', function () {
        if (!pageContext.can_delete) {
            notify('error', 'Permission denied.');
            return;
        }

        let roleId = parseInt($(this).data('id') || 0);

        if (!confirm('Are you sure you want to delete this role?')) {
            return;
        }

        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_role',
                role_id: roleId,
                csrf_token: getCsrfToken()
            },
            success: function (response) {
                if (response.status === true) {
                    notify('success', response.message || 'Role deleted successfully.');
                    loadRoles();
                    loadMenuCount();
                } else {
                    notify('error', response.message || 'Unable to delete role.');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                notify('error', 'Server error. Please try again.');
            }
        });
    });

    $('#checkAllPermissions').on('click', function () {
        let boxes = $('#permissionTableBody input[type="checkbox"]');
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

    $('#checkViewListOnly').on('click', function () {
        $('#permissionTableBody input[type="checkbox"]').prop('checked', false);
        $('#permissionTableBody input[type="checkbox"][value="1"]').prop('checked', true);
        $('#permissionTableBody input[type="checkbox"][value="2"]').prop('checked', true);
        $('#checkAllPermissions').text('Check All');
    });

    function loadPageContext() {
        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_page_context' },
            success: function (response) {
                if (response.status === true) {
                    pageContext = response.data.context || pageContext;
                    applyPageContext();
                    loadRoles();
                    loadMenuCount();
                } else {
                    $('#rolesTableBody').html('<tr><td colspan="5" class="text-center text-danger">' + escapeHtml(response.message || 'Permission denied.') + '</td></tr>');
                    $('#addRoleBtn').addClass('d-none');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#rolesTableBody').html('<tr><td colspan="5" class="text-center text-danger">Server error.</td></tr>');
                $('#addRoleBtn').addClass('d-none');
            }
        });
    }

    function applyPageContext() {
        $('#pageSubTitleText').text(pageContext.page_note || '');
        $('#roleListNote').text(pageContext.page_note || '');
        $('#roleModalNote').text(pageContext.modal_note || '');
        $('#permissionSourceNote').text(pageContext.permission_source_note || '');
        $('#addRoleBtnText').text(pageContext.add_button_label || 'Add Role');

        if (pageContext.can_add) {
            $('#addRoleBtn').removeClass('d-none');
        } else {
            $('#addRoleBtn').addClass('d-none');
        }
    }

    function loadRoles() {
        $('#rolesTableBody').html('<tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'list_roles' },
            success: function (response) {
                if (response.status === true) {
                    renderRoles(response.data.roles || []);
                    renderStats(response.data.stats || {});
                } else {
                    $('#rolesTableBody').html('<tr><td colspan="5" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load roles.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#rolesTableBody').html('<tr><td colspan="5" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function renderRoles(roles) {
        if (!roles.length) {
            $('#rolesTableBody').html('<tr><td colspan="5" class="text-center text-muted">No roles found.</td></tr>');
            return;
        }

        let html = '';

        $.each(roles, function (index, role) {
            let statusBadge = parseInt(role.status || 0) === 1
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-danger">Inactive</span>';

            let isLocked = parseInt(role.is_locked || 0) === 1;

            let actionHtml = '';

            if (isLocked) {
                actionHtml = '<span class="badge bg-secondary">Locked</span>';
            } else {
                if (role.can_edit) {
                    actionHtml += '<button type="button" class="btn btn-primary btn-sm edit-role-btn" data-id="' + role.id + '"><i class="mdi mdi-pencil"></i></button>';
                }

                if (role.can_delete) {
                    actionHtml += '<button type="button" class="btn btn-danger btn-sm delete-role-btn ms-1" data-id="' + role.id + '"><i class="mdi mdi-delete"></i></button>';
                }

                if (actionHtml === '') {
                    actionHtml = '<span class="text-muted">No access</span>';
                }
            }

            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td><strong>' + escapeHtml(role.role_name || '-') + '</strong>';

            if (isLocked) {
                html += '<br><small class="text-muted">System locked role</small>';
            }

            html += '</td>';
            html += '<td>' + escapeHtml(role.description || '-') + '</td>';
            html += '<td>' + statusBadge + '</td>';
            html += '<td>' + actionHtml + '</td>';
            html += '</tr>';
        });

        $('#rolesTableBody').html(html);
    }

    function renderStats(stats) {
        $('#totalRolesCount').text(stats.total_roles || 0);
        $('#activeRolesCount').text(stats.active_roles || 0);
        $('#inactiveRolesCount').text(stats.inactive_roles || 0);
    }

    function loadMenuCount() {
        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_menu_count' },
            success: function (response) {
                if (response.status === true) {
                    $('#menuCount').text(response.data.menu_count || 0);
                }
            }
        });
    }

    function loadPermissionMenus(roleId) {
        roleId = parseInt(roleId || 0);

        $('#permissionTableBody')
            .data('loaded-add-mode', roleId === 0 ? 1 : 0)
            .html('<tr><td colspan="2" class="text-center text-muted">Loading permissions...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/roles.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_permission_menus',
                role_id: roleId
            },
            success: function (response) {
                if (response.status === true) {
                    renderPermissionMenus(response.data.menus || []);
                } else {
                    $('#permissionTableBody').html('<tr><td colspan="2" class="text-center text-danger">' + escapeHtml(response.message || 'Unable to load permissions.') + '</td></tr>');
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#permissionTableBody').html('<tr><td colspan="2" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }

    function getActionLabel(actionCode) {
        const actionLabels = {
            1: 'View',
            2: 'List',
            3: 'Create / Add',
            4: 'Edit',
            5: 'Delete',
            6: 'Print',
            7: 'Export',
            8: 'Approve',
            9: 'Convert',
            10: 'Adjust',
            11: 'Ship',
            12: 'Generate Invoice',
            13: 'Generate Proforma Bill',
            14: 'Generate Quotation',
            15: 'Generate Sale Order',
            16: 'Generate Sales Bill',
            17: 'Receive Payment',
            18: 'Cancel',
            19: 'Return',
            20: 'Upload',
            21: 'Download',
            22: 'WhatsApp',
            23: 'Email',
            24: 'Import',
            25: 'Duplicate',
            26: 'Restore',
            27: 'Generate Delivery Challan',
            28: 'Generate Purchase Order',
            29: 'Generate Purchase Bill',
            30: 'Generate Receipt',
            31: 'Generate Report',32 : 'Create Quotation',
    33 : 'Create Proforma Bill',
    34 : 'Create Sales Bill',
    35 : 'Create Final Invoice',
        };

        return actionLabels[actionCode] || actionCode;
    }

    function renderPermissionMenus(menus) {
        if (!menus.length) {
            $('#permissionTableBody').html('<tr><td colspan="2" class="text-center text-muted">No permissions found.</td></tr>');
            return;
        }

        let html = '';

        $.each(menus, function (_, row) {
            let allowedActions = row.allowed_action_codes || [];
            let selectedActions = row.selected_action_codes || [];
            let isParent = !row.parent_id || parseInt(row.parent_id || 0) === 0;
            let menuPad = isParent ? '' : 'ps-4';

            html += '<tr>';
            html += '<td class="' + menuPad + '">';
            html += isParent ? '<strong>' + escapeHtml(row.menu_name || '') + '</strong>' : escapeHtml(row.menu_name || '');
            html += '<br><small class="text-muted">' + escapeHtml(row.menu_key || '') + '</small>';
            html += '</td>';

            html += '<td>';

            if (!allowedActions.length) {
                html += '<span class="text-muted">No actions</span>';
            } else {
                $.each(allowedActions, function (_, actionCode) {
                    actionCode = parseInt(actionCode);
                    let checked = selectedActions.includes(actionCode) ? 'checked' : '';

                    html += '<label class="me-3 mb-2 d-inline-flex align-items-center">';
                    html += '<input type="checkbox" class="permission-checkbox me-1" name="permissions[' + row.id + '][]" value="' + actionCode + '" ' + checked + '>';
                    html += '<span>' + escapeHtml(getActionLabel(actionCode)) + '</span>';
                    html += '</label>';
                });
            }

            html += '</td>';
            html += '</tr>';
        });

        $('#permissionTableBody').html(html);
    }

    function resetRoleForm() {
        $('#roleForm')[0].reset();
        $('#role_id').val('');
        $('#status').val('1');
        $('#statusSwitch').prop('checked', true);
        $('#permissionTableBody').data('loaded-add-mode', 0);
        $('#permissionTableBody').html('<tr><td colspan="2" class="text-center text-muted">Loading permissions...</td></tr>');
        $('#checkAllPermissions').text('Check All');
        resetLoading('saveRoleBtn', 'Save Role');
    }

    function getCsrfToken() {
        return $('#csrf_token').val() || $('input[name="csrf_token"]').val() || '';
    }

    function setLoading(buttonId, text) {
        let btn = $('#' + buttonId);
        btn.data('old-html', btn.html());
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>' + text);
    }

    function resetLoading(buttonId, defaultText) {
        let btn = $('#' + buttonId);
        btn.prop('disabled', false).html(btn.data('old-html') || defaultText);
    }

    function notify(type, message) {
        if (typeof showAppToast === 'function') {
            showAppToast(type, message);
            return;
        }

        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
            return;
        }

        console.log(type + ': ' + message);
    }

    function escapeHtml(value) {
        value = value === null || value === undefined ? '' : String(value);

        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

});