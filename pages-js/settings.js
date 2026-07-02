$(document).ready(function () {
    $('#preloader').fadeOut('slow');

    loadSettings();

    $('#refreshSettingsBtn').on('click', loadSettings);

    $('#saveBusinessProfileBtn').on('click', function () {
        saveBusinessProfile(this);
    });

    $('#saveBranchProfileBtn').on('click', function () {
        saveBranchProfile(this);
    });

    $('#saveGeneralSettingsBtn').on('click', function () {
        saveGeneralSettings(this);
    });

    $('#business_logo').on('change', function () {
        $('#remove_logo').val('0');
        previewSelectedLogo(this);
    });

    $('#removeLogoBtn').on('click', function () {
        $('#remove_logo').val('1');
        $('#business_logo').val('');
        renderLogoPreview('');
    });

    function loadSettings() {
        $.ajax({
            url: window.BASE_URL + 'api/settings.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'get_settings' },
            success: function (response) {
                if (response.status === true) {
                    renderBusiness(response.data.business || {});
                    renderBranch(response.data.branch || {});
                    renderGeneralSettings(response.data.settings || {});
                    renderStats(response.data.stats || {});
                } else {
                    handleError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error while loading settings.');
            }
        });
    }

    function saveBusinessProfile(button) {
        let form = document.getElementById('businessProfileForm');
        let data = new FormData(form);

        data.append('action', 'save_business_profile');

        setButtonLoading(button, true);

        $.ajax({
            url: window.BASE_URL + 'api/settings.php',
            type: 'POST',
            dataType: 'json',
            data: data,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Business profile saved.');
                    $('#remove_logo').val('0');
                    loadSettings();
                } else {
                    handleError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error while saving business profile.');
            },
            complete: function () {
                setButtonLoading(button, false);
            }
        });
    }

    function saveBranchProfile(button) {
        let data = $('#branchProfileForm').serializeArray();
        data.push({ name: 'action', value: 'save_branch_profile' });

        setButtonLoading(button, true);

        $.ajax({
            url: window.BASE_URL + 'api/settings.php',
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Branch profile saved.');
                    loadSettings();
                } else {
                    handleError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error while saving branch profile.');
            },
            complete: function () {
                setButtonLoading(button, false);
            }
        });
    }

    function saveGeneralSettings(button) {
        let data = $('#generalSettingsForm').serializeArray();
        data.push({ name: 'action', value: 'save_general_settings' });

        setButtonLoading(button, true);

        $.ajax({
            url: window.BASE_URL + 'api/settings.php',
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (response) {
                if (response.status === true) {
                    showToastSafe('success', response.message || 'Settings saved.');
                    loadSettings();
                } else {
                    handleError(response);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                showToastSafe('error', 'Server error while saving settings.');
            },
            complete: function () {
                setButtonLoading(button, false);
            }
        });
    }

    function renderBusiness(business) {
        $('#business_code').val(business.business_code || '');
        $('#business_name').val(business.business_name || '');
        $('#owner_name').val(business.owner_name || '');
        $('#business_mobile').val(business.mobile || '');
        $('#business_email').val(business.email || '');
        $('#gst_number').val(business.gst_number || '');
        $('#business_address').val(business.address || '');
        $('#business_city').val(business.city || '');
        $('#business_state').val(business.state || '');
        $('#business_pincode').val(business.pincode || '');
        $('#remove_logo').val('0');
        $('#business_logo').val('');
        renderLogoPreview(business.logo_path || '');
    }

    function renderBranch(branch) {
        $('#branch_code').val(branch.branch_code || '');
        $('#branch_name').val(branch.branch_name || '');
        $('#branch_mobile').val(branch.mobile || '');
        $('#branch_email').val(branch.email || '');
        $('#branch_address').val(branch.address || '');
        $('#branch_city').val(branch.city || '');
        $('#branch_state').val(branch.state || '');
        $('#branch_pincode').val(branch.pincode || '');
        $('#branchStatusBadge').html(statusBadge(branch.status));
    }

    function renderGeneralSettings(settings) {
        $('#currency').val(settings.currency || 'INR');
        $('#timezone').val(settings.timezone || 'Asia/Kolkata');
        $('#gst_enabled').val(settings.gst_enabled || 'yes');
        $('#tax_mode').val(settings.tax_mode || 'cgst_sgst');
        $('#fifo_stock_deduction').val(settings.fifo_stock_deduction || 'yes');
        $('#sales_flow').val(settings.sales_flow || 'proforma_to_quotation_to_sale_order_to_invoice');
        $('#allow_negative_stock').val(settings.allow_negative_stock || 'no');
        $('#round_off_enabled').val(settings.round_off_enabled || 'yes');
        $('#default_due_days').val(settings.default_due_days || '0');
        $('#invoice_terms').val(settings.invoice_terms || '');
    }

    function renderStats(stats) {
        $('#settingsTotalCount').text(stats.total_settings || 0);
        $('#gstStatusText').text((stats.gst_enabled || '-').toString().toUpperCase());
        $('#taxModeText').text(formatTaxMode(stats.tax_mode || '-'));
        $('#logoStatusText').text(stats.logo_path ? 'Uploaded' : 'Missing');
    }

    function renderLogoPreview(path) {
        if (!path) {
            $('#logoPreviewBox').html('<span class="text-muted small">No Logo</span>');
            return;
        }

        let src = path;
        if (!/^https?:\/\//i.test(src) && !src.startsWith('data:image')) {
            src = window.BASE_URL + src.replace(/^\/+/, '');
        }

        $('#logoPreviewBox').html(
            '<img src="' + src + '" class="img-fluid" style="max-width:145px;max-height:105px;object-fit:contain;" alt="Business Logo">'
        );
    }

    function previewSelectedLogo(input) {
        let file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) return;

        let allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if ($.inArray(file.type, allowed) === -1) {
            showToastSafe('error', 'Only JPG, PNG and WEBP logo files are allowed.');
            $('#business_logo').val('');
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            showToastSafe('error', 'Logo size should be below 2MB.');
            $('#business_logo').val('');
            return;
        }

        let reader = new FileReader();
        reader.onload = function (e) {
            renderLogoPreview(e.target.result);
        };
        reader.readAsDataURL(file);
    }

    function formatTaxMode(value) {
        if (value === 'cgst_sgst') return 'CGST/SGST';
        if (value === 'igst') return 'IGST';
        return value;
    }

    function statusBadge(status) {
        return parseInt(status) === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Inactive</span>';
    }


    function setButtonLoading(button, loading) {
        let $btn = $(button);

        if (loading) {
            $btn.data('original-html', $btn.html());
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Saving...');
            return;
        }

        $btn.prop('disabled', false).html($btn.data('original-html'));
    }

    function showToastSafe(type, message) {
        if (typeof showToast === 'function') {
            showToast(type, message, 5000);
        } else if (typeof toastr !== 'undefined') {
            toastr[type === 'success' ? 'success' : 'error'](message);
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
