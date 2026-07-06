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
    case 'get_page_context':
        getPageContext($pdo);
        break;

    case 'list_customers':
        listCustomers($pdo);
        break;

    case 'get_customer':
        getCustomer($pdo);
        break;

    case 'save_customer':
        verifyCsrfToken();
        saveCustomer($pdo);
        break;

    case 'delete_customer':
        verifyCsrfToken();
        deleteCustomer($pdo);
        break;

    case 'get_zones':
        getZones($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

/*
|--------------------------------------------------------------------------
| Customer permission action numbers
|--------------------------------------------------------------------------
| 1  = View
| 2  = List
| 3  = Create / Add
| 4  = Edit
| 5  = Delete
| 17 = Receive Payment
|--------------------------------------------------------------------------
*/

function customerCan($actionCode)
{
    if (isPlatformOwner()) {
        return true;
    }

    return function_exists('hasPermission') && hasPermission('customers', (int)$actionCode);
}

function requireCustomerPermission($actionCode)
{
    if (!customerCan((int)$actionCode)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function getPageContext(PDO $pdo)
{
    if (!customerCan(1) && !customerCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    jsonResponse(true, 'Page context loaded.', [
        'context' => [
            'can_view' => customerCan(1),
            'can_list' => customerCan(2),
            'can_add' => customerCan(3),
            'can_edit' => customerCan(4),
            'can_delete' => customerCan(5),
            'can_receive_payment' => customerCan(17),
            'page_title' => 'Customers',
            'page_note' => 'Manage customer master data based on your role permission.',
            'add_button_label' => 'Add Customer',
            'add_url' => BASE_URL . 'pages/customers-create.php'
        ]
    ]);
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

function getZones(PDO $pdo)
{
    /*
    |--------------------------------------------------------------------------
    | Zone dropdown
    |--------------------------------------------------------------------------
    | Allowed for users who can view/list/add/edit customers.
    |--------------------------------------------------------------------------
    */

    if (!customerCan(1) && !customerCan(2) && !customerCan(3) && !customerCan(4)) {
        jsonResponse(false, 'Permission denied.');
    }

    $scope = getScope();

    try {
        $stmt = $pdo->prepare("
            SELECT id, zone_name, zone_code
            FROM zones
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND status = 1
            ORDER BY zone_name ASC
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        jsonResponse(true, 'Zones loaded.', [
            'zones' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load zones.');
    }
}

function listCustomers(PDO $pdo)
{
    if (!customerCan(1) && !customerCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    $scope = getScope();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);
    $zoneId = (int)($_GET['zone_id'] ?? 0);

    $where = "
        WHERE c.business_id = :business_id
        AND c.branch_id = :branch_id
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($status === 1 || $status === 2) {
        $where .= " AND c.status = :status ";
        $params[':status'] = $status;
    }

    if ($zoneId > 0) {
        $where .= " AND c.zone_id = :zone_id ";
        $params[':zone_id'] = $zoneId;
    }

    if ($search !== '') {
        /*
        |--------------------------------------------------------------------------
        | Do not reuse same PDO placeholder multiple times.
        | This avoids SQLSTATE[HY093]: Invalid parameter number.
        |--------------------------------------------------------------------------
        */

        $where .= "
            AND (
                c.customer_name LIKE :search_customer_name
                OR c.mobile LIKE :search_mobile
                OR c.email LIKE :search_email
                OR c.gst_number LIKE :search_gst_number
                OR c.address LIKE :search_address
                OR c.city LIKE :search_city
                OR c.state LIKE :search_state
                OR c.pincode LIKE :search_pincode
                OR z.zone_name LIKE :search_zone_name
                OR z.zone_code LIKE :search_zone_code
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[':search_customer_name'] = $searchValue;
        $params[':search_mobile'] = $searchValue;
        $params[':search_email'] = $searchValue;
        $params[':search_gst_number'] = $searchValue;
        $params[':search_address'] = $searchValue;
        $params[':search_city'] = $searchValue;
        $params[':search_state'] = $searchValue;
        $params[':search_pincode'] = $searchValue;
        $params[':search_zone_name'] = $searchValue;
        $params[':search_zone_code'] = $searchValue;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.zone_id,
                c.customer_name,
                c.mobile,
                c.email,
                c.gst_number,
                c.address,
                c.city,
                c.state,
                c.pincode,
                c.opening_outstanding,
                c.current_outstanding,
                c.status,
                c.created_at,
                c.updated_at,
                z.zone_name,
                z.zone_code
            FROM customers c
            LEFT JOIN zones z
                ON z.id = c.zone_id
                AND z.business_id = c.business_id
                AND z.branch_id = c.branch_id
            $where
            ORDER BY c.id DESC
        ");

        $stmt->execute($params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $canEdit = customerCan(4);
        $canDelete = customerCan(5);
        $canReceivePayment = customerCan(17);

        foreach ($customers as &$customer) {
            $customer['can_edit'] = $canEdit;
            $customer['can_delete'] = $canDelete;
            $customer['can_receive_payment'] = $canReceivePayment;
        }
        unset($customer);

        $statsStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_customers,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_customers,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS inactive_customers,
                COALESCE(SUM(current_outstanding), 0) AS total_outstanding
            FROM customers
            WHERE business_id = :business_id
            AND branch_id = :branch_id
        ");

        $statsStmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        jsonResponse(true, 'Customers loaded.', [
            'customers' => $customers,
            'stats' => $statsStmt->fetch(PDO::FETCH_ASSOC)
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load customers.');
    }
}

function getCustomer(PDO $pdo)
{
    requireCustomerPermission(4);

    $scope = getScope();
    $customerId = (int)($_GET['customer_id'] ?? 0);

    if ($customerId <= 0) {
        jsonResponse(false, 'Invalid customer.');
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                zone_id,
                customer_name,
                mobile,
                email,
                gst_number,
                address,
                city,
                state,
                pincode,
                opening_outstanding,
                current_outstanding,
                status
            FROM customers
            WHERE id = :customer_id
            AND business_id = :business_id
            AND branch_id = :branch_id
            LIMIT 1
        ");

        $stmt->execute([
            ':customer_id' => $customerId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            jsonResponse(false, 'Customer not found.');
        }

        jsonResponse(true, 'Customer loaded.', [
            'customer' => $customer
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load customer.');
    }
}

function saveCustomer(PDO $pdo)
{
    $scope = getScope();
    $customerId = (int)($_POST['customer_id'] ?? 0);

    if ($customerId > 0) {
        requireCustomerPermission(4);
    } else {
        requireCustomerPermission(3);
    }

    $zoneId = (int)($_POST['zone_id'] ?? 0);
    $customerName = cleanInput($_POST['customer_name'] ?? '');
    $mobile = cleanInput($_POST['mobile'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $gstNumber = strtoupper(cleanInput($_POST['gst_number'] ?? ''));
    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? 'Tamil Nadu');
    $pincode = cleanInput($_POST['pincode'] ?? '');
    $openingOutstanding = (float)($_POST['opening_outstanding'] ?? 0);
    $status = (int)($_POST['status'] ?? 1);

    if ($customerName === '') {
        jsonResponse(false, 'Please enter customer name.');
    }

    if ($zoneId <= 0) {
        jsonResponse(false, 'Please select zone.');
    }

    validateZone($pdo, $scope, $zoneId);

    if ($mobile !== '' && !preg_match('/^[0-9]{10}$/', $mobile)) {
        jsonResponse(false, 'Please enter valid 10 digit mobile number.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter valid email address.');
    }

    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        jsonResponse(false, 'Please enter valid 6 digit pincode.');
    }

    if ($openingOutstanding < 0) {
        jsonResponse(false, 'Opening outstanding cannot be negative.');
    }

    if (!in_array($status, [1, 2], true)) {
        $status = 1;
    }

    try {
        checkDuplicateCustomer($pdo, $scope, $mobile, $gstNumber, $customerId);

        if ($customerId > 0) {
            $oldStmt = $pdo->prepare("
                SELECT opening_outstanding, current_outstanding
                FROM customers
                WHERE id = :customer_id
                AND business_id = :business_id
                AND branch_id = :branch_id
                LIMIT 1
            ");

            $oldStmt->execute([
                ':customer_id' => $customerId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                jsonResponse(false, 'Customer not found.');
            }

            $oldOpening = (float)($old['opening_outstanding'] ?? 0);
            $oldCurrent = (float)($old['current_outstanding'] ?? 0);
            $difference = $openingOutstanding - $oldOpening;
            $newCurrentOutstanding = $oldCurrent + $difference;

            if ($newCurrentOutstanding < 0) {
                $newCurrentOutstanding = 0;
            }

            $stmt = $pdo->prepare("
                UPDATE customers
                SET
                    zone_id = :zone_id,
                    customer_name = :customer_name,
                    mobile = :mobile,
                    email = :email,
                    gst_number = :gst_number,
                    address = :address,
                    city = :city,
                    state = :state,
                    pincode = :pincode,
                    opening_outstanding = :opening_outstanding,
                    current_outstanding = :current_outstanding,
                    status = :status
                WHERE id = :customer_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");

            $stmt->execute([
                ':zone_id' => $zoneId,
                ':customer_name' => $customerName,
                ':mobile' => $mobile,
                ':email' => $email,
                ':gst_number' => $gstNumber,
                ':address' => $address,
                ':city' => $city,
                ':state' => $state,
                ':pincode' => $pincode,
                ':opening_outstanding' => $openingOutstanding,
                ':current_outstanding' => $newCurrentOutstanding,
                ':status' => $status,
                ':customer_id' => $customerId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            jsonResponse(true, 'Customer updated successfully.', [
                'customer_id' => $customerId
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO customers
            (
                business_id,
                branch_id,
                zone_id,
                customer_name,
                mobile,
                email,
                gst_number,
                address,
                city,
                state,
                pincode,
                opening_outstanding,
                current_outstanding,
                status,
                created_by
            )
            VALUES
            (
                :business_id,
                :branch_id,
                :zone_id,
                :customer_name,
                :mobile,
                :email,
                :gst_number,
                :address,
                :city,
                :state,
                :pincode,
                :opening_outstanding,
                :current_outstanding,
                :status,
                :created_by
            )
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':zone_id' => $zoneId,
            ':customer_name' => $customerName,
            ':mobile' => $mobile,
            ':email' => $email,
            ':gst_number' => $gstNumber,
            ':address' => $address,
            ':city' => $city,
            ':state' => $state,
            ':pincode' => $pincode,
            ':opening_outstanding' => $openingOutstanding,
            ':current_outstanding' => $openingOutstanding,
            ':status' => $status,
            ':created_by' => currentUserId()
        ]);

        jsonResponse(true, 'Customer added successfully.', [
            'customer_id' => (int)$pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Customer save failed.');
    }
}

function deleteCustomer(PDO $pdo)
{
    requireCustomerPermission(5);

    $scope = getScope();
    $customerId = (int)($_POST['customer_id'] ?? 0);

    if ($customerId <= 0) {
        jsonResponse(false, 'Invalid customer.');
    }

    try {
        $usedStmt = $pdo->prepare("
            SELECT COUNT(*) AS total_used
            FROM sales_documents
            WHERE customer_id = :customer_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");

        $usedStmt->execute([
            ':customer_id' => $customerId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $used = $usedStmt->fetch(PDO::FETCH_ASSOC);

        if ((int)($used['total_used'] ?? 0) > 0) {
            jsonResponse(false, 'Customer already used in sales. You can make it inactive instead.');
        }

        $stmt = $pdo->prepare("
            DELETE FROM customers
            WHERE id = :customer_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");

        $stmt->execute([
            ':customer_id' => $customerId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        jsonResponse(true, 'Customer deleted successfully.');

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Customer delete failed.');
    }
}

function validateZone(PDO $pdo, array $scope, $zoneId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM zones
        WHERE id = :zone_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':zone_id' => $zoneId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Invalid zone selected.');
    }
}

function checkDuplicateCustomer(PDO $pdo, array $scope, $mobile, $gstNumber, $ignoreCustomerId = 0)
{
    if ($mobile === '' && $gstNumber === '') {
        return;
    }

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    $conditions = [];

    if ($mobile !== '') {
        $conditions[] = "mobile = :mobile";
        $params[':mobile'] = $mobile;
    }

    if ($gstNumber !== '') {
        $conditions[] = "gst_number = :gst_number";
        $params[':gst_number'] = $gstNumber;
    }

    if (empty($conditions)) {
        return;
    }

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND (" . implode(' OR ', $conditions) . ")
    ";

    if ((int)$ignoreCustomerId > 0) {
        $where .= " AND id != :ignore_customer_id ";
        $params[':ignore_customer_id'] = (int)$ignoreCustomerId;
    }

    $stmt = $pdo->prepare("
        SELECT id, mobile, gst_number
        FROM customers
        $where
        LIMIT 1
    ");

    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ($mobile !== '' && (string)$row['mobile'] === (string)$mobile) {
            jsonResponse(false, 'Mobile number already exists.');
        }

        jsonResponse(false, 'GST number already exists.');
    }
}
