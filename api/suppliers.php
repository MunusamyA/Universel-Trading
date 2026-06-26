<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

/** @var PDO $pdo */

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'list_suppliers':
        listSuppliers($pdo);
        break;

    case 'get_supplier':
        getSupplier($pdo);
        break;

    case 'save_supplier':
        verifyCsrfToken();
        saveSupplier($pdo);
        break;

    case 'delete_supplier':
        verifyCsrfToken();
        deleteSupplier($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

function requireSupplierPermission($action = 'can_view')
{
    if (isPlatformOwner()) {
        return;
    }

    $permissionMap = [
        'can_view' => 'view',
        'can_add' => 'add',
        'can_edit' => 'edit',
        'can_delete' => 'delete'
    ];

    $permissionAction = $permissionMap[$action] ?? 'view';

    if (function_exists('hasPermission') && !hasPermission('suppliers', $permissionAction)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function getScope()
{
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

function listSuppliers(PDO $pdo)
{
    requireSupplierPermission('can_view');

    $scope = getScope();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($status === 1 || $status === 2) {
        $where .= " AND status = :status ";
        $params[':status'] = $status;
    }

    if ($search !== '') {
        $where .= "
            AND (
                supplier_name LIKE :search
                OR mobile LIKE :search
                OR email LIKE :search
                OR gst_number LIKE :search
                OR pan_number LIKE :search
                OR dl_number LIKE :search
                OR fl_number LIKE :search
                OR bank_name LIKE :search
                OR bank_account_no LIKE :search
            )
        ";
        $params[':search'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            supplier_name,
            mobile,
            email,
            gst_number,
            pan_number,
            dl_number,
            fl_number,
            address,
            city,
            state,
            pincode,
            opening_outstanding,
            current_outstanding,
            bank_name,
            bank_account_no,
            bank_branch,
            bank_ifsc,
            upi_id,
            status,
            created_at,
            updated_at
        FROM suppliers
        $where
        ORDER BY id DESC
    ");

    $stmt->execute($params);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_suppliers,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_suppliers,
            SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS inactive_suppliers,
            COALESCE(SUM(current_outstanding), 0) AS total_outstanding
        FROM suppliers
        WHERE business_id = :business_id
        AND branch_id = :branch_id
    ");

    $statsStmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse(true, 'Suppliers loaded.', [
        'suppliers' => $suppliers,
        'stats' => $stats ?: []
    ]);
}

function getSupplier(PDO $pdo)
{
    requireSupplierPermission('can_view');

    $scope = getScope();
    $supplierId = (int)($_GET['supplier_id'] ?? 0);

    if ($supplierId <= 0) {
        jsonResponse(false, 'Invalid supplier.');
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            supplier_name,
            mobile,
            email,
            gst_number,
            pan_number,
            dl_number,
            fl_number,
            address,
            city,
            state,
            pincode,
            opening_outstanding,
            current_outstanding,
            bank_name,
            bank_account_no,
            bank_branch,
            bank_ifsc,
            upi_id,
            status
        FROM suppliers
        WHERE id = :supplier_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        LIMIT 1
    ");

    $stmt->execute([
        ':supplier_id' => $supplierId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        jsonResponse(false, 'Supplier not found.');
    }

    jsonResponse(true, 'Supplier loaded.', [
        'supplier' => $supplier
    ]);
}

function saveSupplier(PDO $pdo)
{
    $scope = getScope();
    $supplierId = (int)($_POST['supplier_id'] ?? 0);

    if ($supplierId > 0) {
        requireSupplierPermission('can_edit');
    } else {
        requireSupplierPermission('can_add');
    }

    $supplierName = cleanInput($_POST['supplier_name'] ?? '');
    $mobile = cleanInput($_POST['mobile'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $gstNumber = strtoupper(cleanInput($_POST['gst_number'] ?? ''));
    $panNumber = strtoupper(cleanInput($_POST['pan_number'] ?? ''));
    $dlNumber = strtoupper(cleanInput($_POST['dl_number'] ?? ''));
    $flNumber = strtoupper(cleanInput($_POST['fl_number'] ?? ''));

    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? 'Tamil Nadu');
    $pincode = cleanInput($_POST['pincode'] ?? '');

    $openingOutstanding = (float)($_POST['opening_outstanding'] ?? 0);
    $status = (int)($_POST['status'] ?? 1);

    $bankName = cleanInput($_POST['bank_name'] ?? '');
    $bankAccountNo = cleanInput($_POST['bank_account_no'] ?? '');
    $bankBranch = cleanInput($_POST['bank_branch'] ?? '');
    $bankIfsc = strtoupper(cleanInput($_POST['bank_ifsc'] ?? ''));
    $upiId = cleanInput($_POST['upi_id'] ?? '');

    if ($supplierName === '') {
        jsonResponse(false, 'Please enter supplier name.');
    }

    if ($mobile !== '' && !preg_match('/^[0-9]{10}$/', $mobile)) {
        jsonResponse(false, 'Please enter valid 10 digit mobile number.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter valid email address.');
    }

    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        jsonResponse(false, 'Please enter valid 6 digit pincode.');
    }

    if ($bankIfsc !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $bankIfsc)) {
        jsonResponse(false, 'Please enter valid IFSC code.');
    }

    if ($openingOutstanding < 0) {
        jsonResponse(false, 'Opening outstanding cannot be negative.');
    }

    if (!in_array($status, [1, 2], true)) {
        $status = 1;
    }

    try {
        if ($supplierId > 0) {
            $oldStmt = $pdo->prepare("
                SELECT opening_outstanding, current_outstanding
                FROM suppliers
                WHERE id = :supplier_id
                AND business_id = :business_id
                AND branch_id = :branch_id
                LIMIT 1
            ");

            $oldStmt->execute([
                ':supplier_id' => $supplierId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                jsonResponse(false, 'Supplier not found.');
            }

            $oldOpening = (float)($old['opening_outstanding'] ?? 0);
            $oldCurrent = (float)($old['current_outstanding'] ?? 0);

            $difference = $openingOutstanding - $oldOpening;
            $newCurrentOutstanding = $oldCurrent + $difference;

            if ($newCurrentOutstanding < 0) {
                $newCurrentOutstanding = 0;
            }

            $stmt = $pdo->prepare("
                UPDATE suppliers
                SET supplier_name = :supplier_name,
                    mobile = :mobile,
                    email = :email,
                    gst_number = :gst_number,
                    pan_number = :pan_number,
                    dl_number = :dl_number,
                    fl_number = :fl_number,
                    address = :address,
                    city = :city,
                    state = :state,
                    pincode = :pincode,
                    opening_outstanding = :opening_outstanding,
                    current_outstanding = :current_outstanding,
                    bank_name = :bank_name,
                    bank_account_no = :bank_account_no,
                    bank_branch = :bank_branch,
                    bank_ifsc = :bank_ifsc,
                    upi_id = :upi_id,
                    status = :status
                WHERE id = :supplier_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");

            $stmt->execute([
                ':supplier_name' => $supplierName,
                ':mobile' => $mobile,
                ':email' => $email,
                ':gst_number' => $gstNumber,
                ':pan_number' => $panNumber,
                ':dl_number' => $dlNumber,
                ':fl_number' => $flNumber,
                ':address' => $address,
                ':city' => $city,
                ':state' => $state,
                ':pincode' => $pincode,
                ':opening_outstanding' => $openingOutstanding,
                ':current_outstanding' => $newCurrentOutstanding,
                ':bank_name' => $bankName,
                ':bank_account_no' => $bankAccountNo,
                ':bank_branch' => $bankBranch,
                ':bank_ifsc' => $bankIfsc,
                ':upi_id' => $upiId,
                ':status' => $status,
                ':supplier_id' => $supplierId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            jsonResponse(true, 'Supplier updated successfully.', [
                'supplier_id' => $supplierId
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO suppliers
            (
                business_id,
                branch_id,
                supplier_name,
                mobile,
                email,
                gst_number,
                pan_number,
                dl_number,
                fl_number,
                address,
                city,
                state,
                pincode,
                opening_outstanding,
                current_outstanding,
                bank_name,
                bank_account_no,
                bank_branch,
                bank_ifsc,
                upi_id,
                status,
                created_by
            )
            VALUES
            (
                :business_id,
                :branch_id,
                :supplier_name,
                :mobile,
                :email,
                :gst_number,
                :pan_number,
                :dl_number,
                :fl_number,
                :address,
                :city,
                :state,
                :pincode,
                :opening_outstanding,
                :current_outstanding,
                :bank_name,
                :bank_account_no,
                :bank_branch,
                :bank_ifsc,
                :upi_id,
                :status,
                :created_by
            )
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':supplier_name' => $supplierName,
            ':mobile' => $mobile,
            ':email' => $email,
            ':gst_number' => $gstNumber,
            ':pan_number' => $panNumber,
            ':dl_number' => $dlNumber,
            ':fl_number' => $flNumber,
            ':address' => $address,
            ':city' => $city,
            ':state' => $state,
            ':pincode' => $pincode,
            ':opening_outstanding' => $openingOutstanding,
            ':current_outstanding' => $openingOutstanding,
            ':bank_name' => $bankName,
            ':bank_account_no' => $bankAccountNo,
            ':bank_branch' => $bankBranch,
            ':bank_ifsc' => $bankIfsc,
            ':upi_id' => $upiId,
            ':status' => $status,
            ':created_by' => currentUserId()
        ]);

        jsonResponse(true, 'Supplier added successfully.', [
            'supplier_id' => (int)$pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Supplier save failed.');
    }
}

function deleteSupplier(PDO $pdo)
{
    requireSupplierPermission('can_delete');

    $scope = getScope();
    $supplierId = (int)($_POST['supplier_id'] ?? 0);

    if ($supplierId <= 0) {
        jsonResponse(false, 'Invalid supplier.');
    }

    $usedStmt = $pdo->prepare("
        SELECT COUNT(*) AS total_used
        FROM purchases
        WHERE supplier_id = :supplier_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");

    $usedStmt->execute([
        ':supplier_id' => $supplierId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $used = $usedStmt->fetch(PDO::FETCH_ASSOC);

    if ((int)($used['total_used'] ?? 0) > 0) {
        jsonResponse(false, 'Supplier already used in purchase. You can make it inactive instead.');
    }

    $stmt = $pdo->prepare("
        DELETE FROM suppliers
        WHERE id = :supplier_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");

    $stmt->execute([
        ':supplier_id' => $supplierId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Supplier deleted successfully.');
}
