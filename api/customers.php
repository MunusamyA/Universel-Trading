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

        case 'get_customer_view':
            getCustomerView($pdo);
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


    function customerOpeningSqlExpressions(PDO $pdo, $customerAlias = 'c')
    {
        $hasOpeningOutstanding = columnExists($pdo, 'customers', 'opening_outstanding');
        $hasOpeningBalance = columnExists($pdo, 'customers', 'opening_balance');
        $hasOpeningPaid = columnExists($pdo, 'customers', 'opening_paid');

        if ($hasOpeningOutstanding) {
            $openingBalanceExpr = 'COALESCE(' . $customerAlias . '.opening_outstanding, 0)';
        } elseif ($hasOpeningBalance) {
            $openingBalanceExpr = 'COALESCE(' . $customerAlias . '.opening_balance, 0)';
        } else {
            $openingBalanceExpr = '0';
        }

        $openingPaidExpr = $hasOpeningPaid ? 'COALESCE(' . $customerAlias . '.opening_paid, 0)' : '0';
        $openingDueExpr = 'GREATEST((' . $openingBalanceExpr . ') - (' . $openingPaidExpr . '), 0)';

        return [
            'opening_balance' => $openingBalanceExpr,
            'opening_paid' => $openingPaidExpr,
            'opening_due' => $openingDueExpr
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

        $openingExpr = customerOpeningSqlExpressions($pdo, 'c');
        $openingBalanceExpr = $openingExpr['opening_balance'];
        $openingPaidExpr = $openingExpr['opening_paid'];
        $openingDueExpr = $openingExpr['opening_due'];

        $salesSummaryJoin = "
            LEFT JOIN (
                SELECT
                    customer_id,
                    COALESCE(SUM(grand_total), 0) AS overall_sales,
                    COALESCE(SUM(paid_amount), 0) AS sales_paid_amount,
                    COALESCE(SUM(due_amount), 0) AS sales_due_amount
                FROM sales
                WHERE business_id = :sales_business_id
                AND branch_id = :sales_branch_id
                AND status IN (1, 2)
                GROUP BY customer_id
            ) ss ON ss.customer_id = c.id
        ";

        $where = "
            WHERE c.business_id = :business_id
            AND c.branch_id = :branch_id
        ";

        $params = [
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':sales_business_id' => $scope['business_id'],
            ':sales_branch_id' => $scope['branch_id']
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
                    $openingBalanceExpr AS opening_balance,
                    $openingPaidExpr AS opening_paid,
                    $openingDueExpr AS opening_due,
                    COALESCE(ss.overall_sales, 0) AS overall_sales,
                    COALESCE(ss.sales_paid_amount, 0) AS sales_paid_amount,
                    COALESCE(ss.sales_due_amount, 0) AS sales_due_amount,
                    (($openingPaidExpr) + COALESCE(ss.sales_paid_amount, 0)) AS paid_amount,
                    (($openingDueExpr) + COALESCE(ss.sales_due_amount, 0)) AS due_amount,
                    (($openingDueExpr) + COALESCE(ss.sales_due_amount, 0)) AS current_outstanding,
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
                $salesSummaryJoin
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
                    SUM(CASE WHEN c.status = 1 THEN 1 ELSE 0 END) AS active_customers,
                    SUM(CASE WHEN c.status = 2 THEN 1 ELSE 0 END) AS inactive_customers,
                    COALESCE(SUM($openingBalanceExpr), 0) AS total_opening_balance,
                    COALESCE(SUM($openingPaidExpr), 0) AS total_opening_paid,
                    COALESCE(SUM($openingDueExpr), 0) AS total_opening_due,
                    COALESCE(SUM(COALESCE(ss.overall_sales, 0)), 0) AS total_overall_sales,
                    COALESCE(SUM(($openingPaidExpr) + COALESCE(ss.sales_paid_amount, 0)), 0) AS total_paid_amount,
                    COALESCE(SUM(($openingDueExpr) + COALESCE(ss.sales_due_amount, 0)), 0) AS total_due_amount,
                    COALESCE(SUM(($openingDueExpr) + COALESCE(ss.sales_due_amount, 0)), 0) AS total_outstanding
                FROM customers c
                $salesSummaryJoin
                WHERE c.business_id = :stats_business_id
                AND c.branch_id = :stats_branch_id
            ");

            $statsStmt->execute([
                ':sales_business_id' => $scope['business_id'],
                ':sales_branch_id' => $scope['branch_id'],
                ':stats_business_id' => $scope['business_id'],
                ':stats_branch_id' => $scope['branch_id']
            ]);

            jsonResponse(true, 'Customers loaded.', [
                'customers' => $customers,
                'stats' => $statsStmt->fetch(PDO::FETCH_ASSOC)
            ]);

        } catch (Exception $e) {
            jsonResponse(false, $e->getMessage() ?: 'Unable to load customers.');
        }
    }


    function getCustomerView(PDO $pdo)
    {
        if (!customerCan(1) && !customerCan(2)) {
            jsonResponse(false, 'Permission denied.');
        }

        $scope = getScope();
        $customerId = (int)($_GET['customer_id'] ?? $_GET['id'] ?? 0);

        if ($customerId <= 0) {
            jsonResponse(false, 'Invalid customer.');
        }

        $openingExpr = customerOpeningSqlExpressions($pdo, 'c');
        $openingBalanceExpr = $openingExpr['opening_balance'];
        $openingPaidExpr = $openingExpr['opening_paid'];
        $openingDueExpr = $openingExpr['opening_due'];

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
                    c.status,
                    c.created_at,
                    z.zone_name,
                    z.zone_code,
                    $openingBalanceExpr AS opening_balance,
                    $openingPaidExpr AS opening_paid,
                    $openingDueExpr AS opening_due,
                    COALESCE(ss.overall_sales, 0) AS overall_sales,
                    COALESCE(ss.sales_paid_amount, 0) AS sales_paid_amount,
                    COALESCE(ss.sales_due_amount, 0) AS sales_due_amount,
                    (($openingPaidExpr) + COALESCE(ss.sales_paid_amount, 0)) AS paid_amount,
                    (($openingDueExpr) + COALESCE(ss.sales_due_amount, 0)) AS due_amount,
                    (($openingDueExpr) + COALESCE(ss.sales_due_amount, 0)) AS current_outstanding
                FROM customers c
                LEFT JOIN zones z
                    ON z.id = c.zone_id
                    AND z.business_id = c.business_id
                    AND z.branch_id = c.branch_id
                LEFT JOIN (
                    SELECT
                        customer_id,
                        COALESCE(SUM(grand_total), 0) AS overall_sales,
                        COALESCE(SUM(paid_amount), 0) AS sales_paid_amount,
                        COALESCE(SUM(due_amount), 0) AS sales_due_amount
                    FROM sales
                    WHERE business_id = :sales_business_id
                    AND branch_id = :sales_branch_id
                    AND status IN (1, 2)
                    GROUP BY customer_id
                ) ss ON ss.customer_id = c.id
                WHERE c.id = :customer_id
                AND c.business_id = :business_id
                AND c.branch_id = :branch_id
                LIMIT 1
            ");
            $stmt->execute([
                ':sales_business_id' => $scope['business_id'],
                ':sales_branch_id' => $scope['branch_id'],
                ':customer_id' => $customerId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                jsonResponse(false, 'Customer not found.');
            }

            $salesStmt = $pdo->prepare("
                SELECT
                    id,
                    sales_no,
                    document_type,
                    invoice_type,
                    sales_date,
                    due_date,
                    sub_total,
                    discount_amount,
                    tax_amount,
                    grand_total,
                    paid_amount,
                    due_amount,
                    payment_status,
                    stock_deducted,
                    status,
                    notes
                FROM sales
                WHERE customer_id = :customer_id
                AND business_id = :business_id
                AND branch_id = :branch_id
                ORDER BY sales_date DESC, id DESC
                LIMIT 500
            ");
            $salesStmt->execute([
                ':customer_id' => $customerId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
            $salesRows = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

            $documentTypes = [
                1 => 'Quotation',
                2 => 'Proforma Bill',
                3 => 'Sales Bill',
                4 => 'Direct Sale',
                5 => 'Final Invoice'
            ];

            foreach ($salesRows as &$row) {
                $rowType = (int)($row['document_type'] ?? 0);
                $row['document_label'] = $documentTypes[$rowType] ?? 'Document';
                $row['can_view'] = true;
                $row['can_print'] = true;
                $row['can_receive_payment'] = customerCan(17) && (float)($row['due_amount'] ?? 0) > 0 && in_array((int)($row['status'] ?? 0), [1, 2], true);
            }
            unset($row);

            jsonResponse(true, 'Customer view loaded.', [
                'customer' => $customer,
                'sales' => $salesRows,
                'context' => [
                    'can_view' => customerCan(1),
                    'can_list' => customerCan(2),
                    'can_edit' => customerCan(4),
                    'can_delete' => customerCan(5),
                    'can_receive_payment' => customerCan(17)
                ]
            ]);
        } catch (Exception $e) {
            jsonResponse(false, $e->getMessage() ?: 'Unable to load customer view.');
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

                syncCustomerOpeningColumns($pdo, $scope, $customerId, $openingOutstanding);
                recalculateCustomerOutstanding($pdo, $scope, $customerId);

                jsonResponse(true, 'Customer updated successfully.', [
                    'customer_id' => $customerId
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Opening balance storage
            |--------------------------------------------------------------------------
            | Customer form sends only opening_outstanding. During create, store the
            | same value in opening_due also. opening_paid starts with 0.
            | This avoids opening_due showing 0 until the helper update runs and also
            | supports databases where opening_due is NOT NULL without default.
            |--------------------------------------------------------------------------
            */
            $openingPaid = 0.00;
            $openingDue = round(max($openingOutstanding - $openingPaid, 0), 2);

            $insertColumns = [
                'business_id',
                'branch_id',
                'zone_id',
                'customer_name',
                'mobile',
                'email',
                'gst_number',
                'address',
                'city',
                'state',
                'pincode',
                'opening_outstanding',
                'current_outstanding',
                'status',
                'created_by'
            ];

            $insertValues = [
                ':business_id',
                ':branch_id',
                ':zone_id',
                ':customer_name',
                ':mobile',
                ':email',
                ':gst_number',
                ':address',
                ':city',
                ':state',
                ':pincode',
                ':opening_outstanding',
                ':current_outstanding',
                ':status',
                ':created_by'
            ];

            $insertParams = [
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
                ':current_outstanding' => $openingDue,
                ':status' => $status,
                ':created_by' => currentUserId()
            ];

            if (columnExists($pdo, 'customers', 'opening_balance')) {
                $insertColumns[] = 'opening_balance';
                $insertValues[] = ':opening_balance';
                $insertParams[':opening_balance'] = $openingOutstanding;
            }

            if (columnExists($pdo, 'customers', 'opening_paid')) {
                $insertColumns[] = 'opening_paid';
                $insertValues[] = ':opening_paid';
                $insertParams[':opening_paid'] = $openingPaid;
            }

            if (columnExists($pdo, 'customers', 'opening_due')) {
                $insertColumns[] = 'opening_due';
                $insertValues[] = ':opening_due';
                $insertParams[':opening_due'] = $openingDue;
            }

            $stmt = $pdo->prepare("
                INSERT INTO customers
                (" . implode(', ', $insertColumns) . ")
                VALUES
                (" . implode(', ', $insertValues) . ")
            ");

            $stmt->execute($insertParams);

            $newCustomerId = (int)$pdo->lastInsertId();
            syncCustomerOpeningColumns($pdo, $scope, $newCustomerId, $openingOutstanding);
            recalculateCustomerOutstanding($pdo, $scope, $newCustomerId);

            jsonResponse(true, 'Customer added successfully.', [
                'customer_id' => $newCustomerId
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


    function syncCustomerOpeningColumns(PDO $pdo, array $scope, $customerId, $openingBalance)
    {
        $customerId = (int)$customerId;
        $openingBalance = round((float)$openingBalance, 2);

        if ($customerId <= 0) {
            return;
        }

        $openingPaid = 0.0;
        if (columnExists($pdo, 'customers', 'opening_paid')) {
            $paidStmt = $pdo->prepare("
                SELECT COALESCE(opening_paid, 0)
                FROM customers
                WHERE id = :customer_id
                AND business_id = :business_id
                AND branch_id = :branch_id
                LIMIT 1
            ");
            $paidStmt->execute([
                ':customer_id' => $customerId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
            $openingPaid = round((float)$paidStmt->fetchColumn(), 2);
        }

        $openingDue = max($openingBalance - $openingPaid, 0);

        $sets = [];
        $params = [
            ':customer_id' => $customerId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':opening_balance_value' => $openingBalance,
            ':opening_due_value' => $openingDue
        ];

        if (columnExists($pdo, 'customers', 'opening_outstanding')) {
            $sets[] = 'opening_outstanding = :opening_balance_value';
        }

        if (columnExists($pdo, 'customers', 'opening_balance')) {
            $sets[] = 'opening_balance = :opening_balance_value';
        }

        if (columnExists($pdo, 'customers', 'opening_due')) {
            $sets[] = 'opening_due = :opening_due_value';
        }

        if (!$sets) {
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE customers
            SET " . implode(', ', $sets) . "
            WHERE id = :customer_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $stmt->execute($params);
    }

    function recalculateCustomerOutstanding(PDO $pdo, array $scope, $customerId)
    {
        $customerId = (int)$customerId;
        if ($customerId <= 0) {
            return;
        }

        $openingBalanceColumn = columnExists($pdo, 'customers', 'opening_outstanding')
            ? 'opening_outstanding'
            : (columnExists($pdo, 'customers', 'opening_balance') ? 'opening_balance' : null);

        $openingBalanceExpr = $openingBalanceColumn ? 'COALESCE(c.' . $openingBalanceColumn . ', 0)' : '0';
        $openingPaidExpr = columnExists($pdo, 'customers', 'opening_paid') ? 'COALESCE(c.opening_paid, 0)' : '0';

        $stmt = $pdo->prepare("
            SELECT
                GREATEST(($openingBalanceExpr) - ($openingPaidExpr), 0) +
                COALESCE((
                    SELECT SUM(s.due_amount)
                    FROM sales s
                    WHERE s.business_id = :business_id
                    AND s.branch_id = :branch_id
                    AND s.customer_id = :customer_id
                    AND s.status IN (1, 2)
                ), 0) AS outstanding
            FROM customers c
            WHERE c.id = :customer_id2
            AND c.business_id = :business_id2
            AND c.branch_id = :branch_id2
            LIMIT 1
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':customer_id' => $customerId,
            ':customer_id2' => $customerId,
            ':business_id2' => $scope['business_id'],
            ':branch_id2' => $scope['branch_id']
        ]);

        $outstanding = round((float)$stmt->fetchColumn(), 2);

        $sets = [];
        $params = [
            ':outstanding' => $outstanding,
            ':customer_id' => $customerId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ];

        if (columnExists($pdo, 'customers', 'current_outstanding')) {
            $sets[] = 'current_outstanding = :outstanding';
        }

        if (columnExists($pdo, 'customers', 'total_outstanding')) {
            $sets[] = 'total_outstanding = :outstanding';
        }

        if (!$sets) {
            return;
        }

        $upd = $pdo->prepare("
            UPDATE customers
            SET " . implode(', ', $sets) . "
            WHERE id = :customer_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $upd->execute($params);
    }


    function columnExists(PDO $pdo, $tableName, $columnName)
    {
        static $cache = [];

        $key = $tableName . '.' . $columnName;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");
        $stmt->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName
        ]);

        $cache[$key] = ((int)$stmt->fetchColumn() > 0);
        return $cache[$key];
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
