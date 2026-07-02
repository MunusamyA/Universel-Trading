<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'get_settings':
        getSettings($pdo);
        break;

    case 'save_business_profile':
        verifyCsrfToken();
        saveBusinessProfile($pdo);
        break;

    case 'save_branch_profile':
        verifyCsrfToken();
        saveBranchProfile($pdo);
        break;

    case 'save_general_settings':
        verifyCsrfToken();
        saveGeneralSettings($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
}

function requireSettingsPermission($action = 'view') {
    if (isPlatformOwner()) {
        return;
    }

    /*
     * Keep both keys supported.
     */
    $permissionKeys = ['settings', 'business_settings'];

    if (function_exists('hasPermission')) {
        foreach ($permissionKeys as $key) {
            if (hasPermission($key, $action)) {
                return;
            }
        }

        jsonResponse(false, 'Permission denied.');
    }
}

function getScope() {
    $businessId = (int)currentBusinessId();
    $branchId = (int)currentBranchId();

    if ($businessId <= 0 || $branchId <= 0) {
        jsonResponse(false, 'Invalid business or branch session.');
    }

    return [
        'business_id' => $businessId,
        'branch_id' => $branchId
    ];
}

function defaultSettings() {
    return [
        'currency' => 'INR',
        'timezone' => 'Asia/Kolkata',
        'gst_enabled' => 'yes',
        'tax_mode' => 'cgst_sgst',
        'fifo_stock_deduction' => 'yes',
        'sales_flow' => 'proforma_to_quotation_to_sale_order_to_invoice',
        'allow_negative_stock' => 'no',
        'round_off_enabled' => 'yes',
        'default_due_days' => '0',
        'invoice_terms' => ''
    ];
}

function getSettings(PDO $pdo) {
    requireSettingsPermission('view');
    $scope = getScope();

    $businessStmt = $pdo->prepare("
        SELECT id, business_code, business_name, owner_name, mobile, email, gst_number, address, city, state, pincode, status
        FROM businesses
        WHERE id = :business_id
        LIMIT 1
    ");
    $businessStmt->execute([':business_id' => $scope['business_id']]);
    $business = $businessStmt->fetch(PDO::FETCH_ASSOC);

    if (!$business) {
        jsonResponse(false, 'Business not found.');
    }

    $invoiceSettings = getInvoiceSettings($pdo, $scope);
    $business['logo_path'] = $invoiceSettings['logo_path'] ?? '';

    $branchStmt = $pdo->prepare("
        SELECT id, business_id, branch_code, branch_name, mobile, email, address, city, state, pincode, status
        FROM branches
        WHERE id = :branch_id
        AND business_id = :business_id
        LIMIT 1
    ");
    $branchStmt->execute([
        ':branch_id' => $scope['branch_id'],
        ':business_id' => $scope['business_id']
    ]);
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        jsonResponse(false, 'Branch not found.');
    }

    $savedSettings = getSettingsMap($pdo, $scope);
    $settings = array_merge(defaultSettings(), $savedSettings);

    $stats = [
        'total_settings' => count($savedSettings),
        'gst_enabled' => $settings['gst_enabled'],
        'tax_mode' => $settings['tax_mode'],
        'fifo_stock_deduction' => $settings['fifo_stock_deduction'],
        'logo_path' => $business['logo_path']
    ];

    jsonResponse(true, 'Settings loaded.', [
        'business' => $business,
        'branch' => $branch,
        'settings' => $settings,
        'stats' => $stats
    ]);
}

function saveBusinessProfile(PDO $pdo) {
    requireSettingsPermission('edit');
    $scope = getScope();

    $businessName = cleanInput($_POST['business_name'] ?? '');
    $ownerName = cleanInput($_POST['owner_name'] ?? '');
    $mobile = cleanInput($_POST['mobile'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $gstNumber = strtoupper(cleanInput($_POST['gst_number'] ?? ''));
    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? '');
    $pincode = cleanInput($_POST['pincode'] ?? '');
    $removeLogo = (int)($_POST['remove_logo'] ?? 0);

    if ($businessName === '') {
        jsonResponse(false, 'Please enter business name.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter valid business email.');
    }

    if ($gstNumber !== '' && strlen($gstNumber) > 50) {
        jsonResponse(false, 'GST number is too long.');
    }

    $oldInvoiceSettings = getInvoiceSettings($pdo, $scope);
    $oldLogoPath = $oldInvoiceSettings['logo_path'] ?? '';
    $logoPath = $oldLogoPath;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE businesses
            SET business_name = :business_name,
                owner_name = :owner_name,
                mobile = :mobile,
                email = :email,
                gst_number = :gst_number,
                address = :address,
                city = :city,
                state = :state,
                pincode = :pincode
            WHERE id = :business_id
        ");

        $stmt->execute([
            ':business_name' => $businessName,
            ':owner_name' => $ownerName,
            ':mobile' => $mobile,
            ':email' => $email,
            ':gst_number' => $gstNumber,
            ':address' => $address,
            ':city' => $city,
            ':state' => $state,
            ':pincode' => $pincode,
            ':business_id' => $scope['business_id']
        ]);

        if ($removeLogo === 1) {
            deleteUploadedLogo($oldLogoPath);
            $logoPath = null;
        }

        if (!empty($_FILES['business_logo']['name'])) {
            $uploadedLogo = uploadBusinessLogo($_FILES['business_logo'], $scope);
            if ($uploadedLogo !== '') {
                deleteUploadedLogo($oldLogoPath);
                $logoPath = $uploadedLogo;
            }
        }

        saveInvoiceLogoPath($pdo, $scope, $logoPath);

        logSettingsActivity($pdo, $scope, 'business_settings', 'UPDATE', 'Business profile, GST and logo updated');

        $pdo->commit();

        jsonResponse(true, 'Business profile, GST and logo updated successfully.', [
            'logo_path' => $logoPath
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Business profile save failed.');
    }
}

function saveBranchProfile(PDO $pdo) {
    requireSettingsPermission('edit');
    $scope = getScope();

    $branchName = cleanInput($_POST['branch_name'] ?? '');
    $mobile = cleanInput($_POST['mobile'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? '');
    $pincode = cleanInput($_POST['pincode'] ?? '');

    if ($branchName === '') {
        jsonResponse(false, 'Please enter branch name.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter valid branch email.');
    }

    $stmt = $pdo->prepare("
        UPDATE branches
        SET branch_name = :branch_name,
            mobile = :mobile,
            email = :email,
            address = :address,
            city = :city,
            state = :state,
            pincode = :pincode
        WHERE id = :branch_id
        AND business_id = :business_id
    ");

    $stmt->execute([
        ':branch_name' => $branchName,
        ':mobile' => $mobile,
        ':email' => $email,
        ':address' => $address,
        ':city' => $city,
        ':state' => $state,
        ':pincode' => $pincode,
        ':branch_id' => $scope['branch_id'],
        ':business_id' => $scope['business_id']
    ]);

    logSettingsActivity($pdo, $scope, 'branch_settings', 'UPDATE', 'Branch profile updated');

    jsonResponse(true, 'Branch profile updated successfully.');
}

function saveGeneralSettings(PDO $pdo) {
    requireSettingsPermission('edit');
    $scope = getScope();

    $currency = cleanInput($_POST['currency'] ?? 'INR');
    $timezone = cleanInput($_POST['timezone'] ?? 'Asia/Kolkata');
    $gstEnabled = cleanInput($_POST['gst_enabled'] ?? 'yes');
    $taxMode = cleanInput($_POST['tax_mode'] ?? 'cgst_sgst');
    $fifoStockDeduction = cleanInput($_POST['fifo_stock_deduction'] ?? 'yes');
    $salesFlow = cleanInput($_POST['sales_flow'] ?? 'proforma_to_quotation_to_sale_order_to_invoice');
    $allowNegativeStock = cleanInput($_POST['allow_negative_stock'] ?? 'no');
    $roundOffEnabled = cleanInput($_POST['round_off_enabled'] ?? 'yes');
    $defaultDueDays = (int)($_POST['default_due_days'] ?? 0);
    $invoiceTerms = cleanInput($_POST['invoice_terms'] ?? '');

    if (!in_array($currency, ['INR', 'USD', 'AED'], true)) {
        $currency = 'INR';
    }

    if (!in_array($timezone, ['Asia/Kolkata', 'Asia/Dubai', 'UTC'], true)) {
        $timezone = 'Asia/Kolkata';
    }

    if (!in_array($gstEnabled, ['yes', 'no'], true)) {
        $gstEnabled = 'yes';
    }

    if (!in_array($taxMode, ['cgst_sgst', 'igst'], true)) {
        $taxMode = 'cgst_sgst';
    }

    if (!in_array($fifoStockDeduction, ['yes', 'no'], true)) {
        $fifoStockDeduction = 'yes';
    }

    if (!in_array($allowNegativeStock, ['yes', 'no'], true)) {
        $allowNegativeStock = 'no';
    }

    if (!in_array($roundOffEnabled, ['yes', 'no'], true)) {
        $roundOffEnabled = 'yes';
    }

    if (!in_array($salesFlow, [
        'proforma_to_quotation_to_sale_order_to_invoice',
        'quotation_to_invoice',
        'sale_order_to_invoice',
        'direct_invoice'
    ], true)) {
        $salesFlow = 'proforma_to_quotation_to_sale_order_to_invoice';
    }

    if ($defaultDueDays < 0) {
        $defaultDueDays = 0;
    }

    if ($defaultDueDays > 365) {
        $defaultDueDays = 365;
    }

    if (strlen($invoiceTerms) > 1000) {
        jsonResponse(false, 'Invoice terms should be within 1000 characters.');
    }

    $settings = [
        'currency' => $currency,
        'timezone' => $timezone,
        'gst_enabled' => $gstEnabled,
        'tax_mode' => $taxMode,
        'fifo_stock_deduction' => $fifoStockDeduction,
        'sales_flow' => $salesFlow,
        'allow_negative_stock' => $allowNegativeStock,
        'round_off_enabled' => $roundOffEnabled,
        'default_due_days' => (string)$defaultDueDays,
        'invoice_terms' => $invoiceTerms
    ];

    try {
        $pdo->beginTransaction();

        foreach ($settings as $key => $value) {
            saveSettingValue($pdo, $scope, $key, $value);
        }

        logSettingsActivity($pdo, $scope, 'business_settings', 'SAVE', 'General business settings saved');

        $pdo->commit();

        jsonResponse(true, 'General settings saved successfully.', [
            'settings' => array_merge(defaultSettings(), getSettingsMap($pdo, $scope))
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Settings save failed.');
    }
}

function getInvoiceSettings(PDO $pdo, array $scope) {
    $stmt = $pdo->prepare("
        SELECT id, logo_path, signature_path
        FROM invoice_settings
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: [];
}

function saveInvoiceLogoPath(PDO $pdo, array $scope, $logoPath) {
    $stmt = $pdo->prepare("
        INSERT INTO invoice_settings
            (business_id, branch_id, invoice_prefix, proforma_prefix, quotation_prefix, sale_order_prefix,
             next_invoice_no, next_proforma_no, next_quotation_no, next_sale_order_no,
             terms, footer_text, logo_path)
        VALUES
            (:business_id, :branch_id, 'INV', 'PRO', 'QUO', 'SO',
             1, 1, 1, 1,
             'Goods once sold will not be taken back.', 'Thank you for your business.', :logo_path)
        ON DUPLICATE KEY UPDATE
            logo_path = VALUES(logo_path),
            updated_at = NOW()
    ");

    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':logo_path' => $logoPath
    ]);
}

function uploadBusinessLogo(array $file, array $scope) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, 'Logo upload failed.');
    }

    if ((int)$file['size'] > (2 * 1024 * 1024)) {
        jsonResponse(false, 'Logo size should be below 2MB.');
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        jsonResponse(false, 'Only JPG, PNG and WEBP logo files are allowed.');
    }

    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes, true)) {
        jsonResponse(false, 'Invalid logo file type.');
    }

    $uploadDir = BASE_PATH . 'uploads/logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'business_logo_' . $scope['business_id'] . '_' . $scope['branch_id'] . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonResponse(false, 'Unable to save uploaded logo.');
    }

    return 'uploads/logos/' . $fileName;
}

function deleteUploadedLogo($path) {
    $path = trim((string)$path);

    if ($path === '') {
        return;
    }

    // Delete only files inside uploads/logos for safety.
    if (strpos($path, 'uploads/logos/') !== 0) {
        return;
    }

    $fullPath = BASE_PATH . $path;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function getSettingsMap(PDO $pdo, array $scope) {
    $stmt = $pdo->prepare("
        SELECT setting_key, setting_value
        FROM business_settings
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        ORDER BY setting_key ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $settings = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return $settings;
}

function saveSettingValue(PDO $pdo, array $scope, $key, $value) {
    $check = $pdo->prepare("
        SELECT id
        FROM business_settings
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND setting_key = :setting_key
        LIMIT 1
    ");
    $check->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':setting_key' => $key
    ]);

    $existingId = (int)$check->fetchColumn();

    if ($existingId > 0) {
        $update = $pdo->prepare("
            UPDATE business_settings
            SET setting_value = :setting_value,
                updated_at = NOW()
            WHERE id = :id
        ");
        $update->execute([
            ':setting_value' => $value,
            ':id' => $existingId
        ]);

        return;
    }

    $insert = $pdo->prepare("
        INSERT INTO business_settings
            (business_id, branch_id, setting_key, setting_value)
        VALUES
            (:business_id, :branch_id, :setting_key, :setting_value)
    ");
    $insert->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':setting_key' => $key,
        ':setting_value' => $value
    ]);
}

function logSettingsActivity(PDO $pdo, array $scope, $moduleKey, $actionType, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs
                (business_id, branch_id, user_id, module_key, action_type, description, ip_address)
            VALUES
                (:business_id, :branch_id, :user_id, :module_key, :action_type, :description, :ip_address)
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':user_id' => function_exists('currentUserId') ? currentUserId() : null,
            ':module_key' => $moduleKey,
            ':action_type' => $actionType,
            ':description' => $description,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {
        // Settings should not fail only because activity log insert failed.
    }
}
