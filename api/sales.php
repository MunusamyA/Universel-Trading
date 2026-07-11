<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';
require_once BASE_PATH . 'includes/stock-movement-helper.php';

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

/** @var PDO $pdo */

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'sales_permission_debug':
        salesPermissionDebug($pdo);
        break;

    case 'get_page_context':
        getPageContext($pdo);
        break;

    case 'customer_payments_page_context':
        customerPaymentsPageContext($pdo);
        break;

    case 'get_document_permissions':
        getSalesDocumentPermissions($pdo);
        break;
    case 'list_sales':
        listSales($pdo);
        break;
    case 'get_sale':
        getSale($pdo);
        break;
    case 'save_sale':
        verifyCsrfToken();
        saveSale($pdo);
        break;
    case 'delete_sale':
        verifyCsrfToken();
        deleteSale($pdo, false);
        break;
    case 'cancel_sale':
        verifyCsrfToken();
        deleteSale($pdo, true);
        break;
    case 'search_customers':
        searchCustomers($pdo);
        break;
    case 'search_products':
        searchProducts($pdo);
        break;
    case 'get_product_batches':
        getProductBatches($pdo);
        break;
    case 'get_payment_modes':
        getPaymentModes($pdo);
        break;
    case 'payment_page_init':
        paymentPageInit($pdo);
        break;
    case 'list_customer_payments':
        listCustomerPayments($pdo);
        break;
    case 'get_customer_payment':
        getCustomerPayment($pdo);
        break;
    case 'save_customer_payment':
        verifyCsrfToken();
        saveCustomerPayment($pdo);
        break;
    case 'cancel_customer_payment':
        verifyCsrfToken();
        cancelCustomerPayment($pdo);
        break;
    case 'list_customer_due_documents':
        listCustomerDueDocuments($pdo);
        break;
    default:
        jsonResponse(false, 'Invalid action.');
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

function currentUserIdSafe()
{
    if (function_exists('currentUserId')) {
        return (int)currentUserId();
    }

    return (int)($_SESSION['user_id'] ?? 0);
}

function permissionActionCode($action)
{
    if (is_numeric($action)) {
        return (int)$action;
    }

    $map = [
        'view' => 1,
        'list' => 2,
        'add' => 3,
        'create' => 3,
        'edit' => 4,
        'update' => 4,
        'delete' => 5,
        'print' => 6,
        'export' => 7,
        'approve' => 8,
        'convert' => 9,
        'adjust' => 10,
        'ship' => 11,
        'generate_invoice' => 12,
        'generate_proforma_bill' => 13,
        'generate_quotation' => 14,
        'generate_sale_order' => 15,
        'generate_sales_bill' => 16,
        'create_quotation' => 32,
        'create_proforma_bill' => 33,
        'create_sales_bill' => 34,
        'create_final_invoice' => 35,
        'receive_payment' => 17,
        'cancel' => 18,
        'return' => 19
    ];

    $key = strtolower(trim((string)$action));

    return $map[$key] ?? 1;
}

function salesPermissionKeys()
{
    $businessId = function_exists('currentBusinessId') ? (int)currentBusinessId() : 0;
    $branchId = function_exists('currentBranchId') ? (int)currentBranchId() : 0;

    /*
    |--------------------------------------------------------------------------
    | Global sales entry keys only.
    |--------------------------------------------------------------------------
    | Do not add quotation/proforma/sales list keys here.
    | Document type permission must be checked separately by document type.
    |--------------------------------------------------------------------------
    */

    return [
        'sales',
        'sales_' . $businessId . '_' . $branchId
    ];
}

function currentRoleIdsForSalesPermission()
{
    static $roleIds = null;

    if ($roleIds !== null) {
        return $roleIds;
    }

    global $pdo;

    $roleIds = [];

    if (function_exists('currentRoleId')) {
        $roleId = (int)currentRoleId();
        if ($roleId > 0) {
            $roleIds[] = $roleId;
        }
    }

    foreach (['role_id', 'current_role_id', 'user_role_id'] as $sessionKey) {
        if (!empty($_SESSION[$sessionKey])) {
            $roleId = (int)$_SESSION[$sessionKey];
            if ($roleId > 0) {
                $roleIds[] = $roleId;
            }
        }
    }

    $userId = currentUserIdSafe();

    if ($userId > 0 && isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT role_id FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            $dbRoleId = (int)$stmt->fetchColumn();

            if ($dbRoleId > 0) {
                $roleIds[] = $dbRoleId;
            }
        } catch (Throwable $e) {
            // Ignore and continue with session role ids.
        }
    }

    $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

    /*
    |--------------------------------------------------------------------------
    | Add parent package roles also.
    |--------------------------------------------------------------------------
    | Some roles depend on parent_role_id package permission.
    |--------------------------------------------------------------------------
    */

    if ($roleIds && isset($pdo) && $pdo instanceof PDO) {
        try {
            $holders = [];
            $params = [];

            foreach ($roleIds as $index => $roleId) {
                $key = ':role_' . $index;
                $holders[] = $key;
                $params[$key] = $roleId;
            }

            $stmt = $pdo->prepare("
                SELECT parent_role_id
                FROM roles
                WHERE id IN (" . implode(',', $holders) . ")
                AND parent_role_id IS NOT NULL
                AND parent_role_id > 0
            ");
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $parentRoleId) {
                $parentRoleId = (int)$parentRoleId;
                if ($parentRoleId > 0) {
                    $roleIds[] = $parentRoleId;
                }
            }
        } catch (Throwable $e) {
            // Ignore parent lookup errors.
        }
    }

    $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

    return $roleIds;
}

function roleBasePermissionAllowed($moduleKeys, $actionCode)
{
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    $moduleKeys = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $moduleKeys)))));

    if (!$moduleKeys) {
        return false;
    }

    $roleIds = currentRoleIdsForSalesPermission();

    if (!$roleIds) {
        return false;
    }

    try {
        $keyHolders = [];
        $roleHolders = [];
        $params = [
            ':action_code' => (string)(int)$actionCode
        ];

        foreach ($moduleKeys as $index => $moduleKey) {
            $key = ':menu_key_' . $index;
            $keyHolders[] = $key;
            $params[$key] = $moduleKey;
        }

        foreach ($roleIds as $index => $roleId) {
            $key = ':role_id_' . $index;
            $roleHolders[] = $key;
            $params[$key] = (int)$roleId;
        }

        $sql = "
            SELECT 1
            FROM role_base_access rba
            INNER JOIN sidebar_menus sm ON sm.id = rba.menu_id
            WHERE rba.status = 1
            AND sm.status = 1
            AND rba.role_id IN (" . implode(',', $roleHolders) . ")
            AND sm.menu_key IN (" . implode(',', $keyHolders) . ")
            AND FIND_IN_SET(:action_code, REPLACE(COALESCE(rba.access_actions, ''), ' ', '')) > 0
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function roleBaseMenuRowsExist($moduleKeys)
{
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    $moduleKeys = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $moduleKeys)))));
    $roleIds = currentRoleIdsForSalesPermission();

    if (!$moduleKeys || !$roleIds) {
        return false;
    }

    try {
        $keyHolders = [];
        $roleHolders = [];
        $params = [];

        foreach ($moduleKeys as $index => $moduleKey) {
            $key = ':rb_exists_menu_' . $index;
            $keyHolders[] = $key;
            $params[$key] = $moduleKey;
        }

        foreach ($roleIds as $index => $roleId) {
            $key = ':rb_exists_role_' . $index;
            $roleHolders[] = $key;
            $params[$key] = (int)$roleId;
        }

        $sql = "
            SELECT 1
            FROM role_base_access rba
            INNER JOIN sidebar_menus sm ON sm.id = rba.menu_id
            WHERE rba.role_id IN (" . implode(',', $roleHolders) . ")
            AND sm.menu_key IN (" . implode(',', $keyHolders) . ")
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function permissionAllowed($moduleKeys, $action)
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return true;
    }

    $actionCode = permissionActionCode($action);

    if (!is_array($moduleKeys)) {
        $moduleKeys = [$moduleKeys];
    }

    /*
    |--------------------------------------------------------------------------
    | Role permission source
    |--------------------------------------------------------------------------
    | All sales permissions are checked only from role_base_access + sidebar_menus.
    | access_actions must contain the required action code.
    |--------------------------------------------------------------------------
    */

    return roleBasePermissionAllowed($moduleKeys, $actionCode);
}

function requireSalesPermission($action = 1)
{
    if (!permissionAllowed(salesPermissionKeys(), $action)) {
        jsonResponse(false, 'Permission denied.');
    }
}


function salesDocumentTypes()
{
    return [
        1 => [
            'label' => 'Quotation',
            'permission_key' => 'sales_quotation_list',
            'legacy_keys' => ['sales_quotation', 'quotation', 'quotation_list']
        ],
        2 => [
            'label' => 'Proforma Bill',
            'permission_key' => 'sales_proforma_bill_list',
            'legacy_keys' => ['sales_proforma_bill', 'proforma_bill', 'proforma_bill_list']
        ],
        3 => [
            'label' => 'Sales Bill',
            'permission_key' => 'sales_bill_list',
            'legacy_keys' => ['sales_bill', 'sale_order', 'sales-bill', 'sales_list']
        ],
        4 => [
            'label' => 'Direct Sale',
            'permission_key' => 'direct_sale_list',
            'legacy_keys' => ['sales_direct_sale_list', 'sales_direct_sale', 'direct_sale']
        ],
        5 => [
            'label' => 'Final Invoice',
            'permission_key' => 'final_invoice_list',
            'legacy_keys' => ['sales_final_invoice_list', 'sales_final_invoice', 'sales_invoice']
        ],
    ];
}

function salesEntryCreatableDocumentTypes()
{
    /*
    |--------------------------------------------------------------------------
    | Sales Entry Create/Add controls document creation.
    |--------------------------------------------------------------------------
    | These are the 4 sales documents allowed from Sales Entry.
    | Sales Order is not used. Direct Sale is not included in this final flow.
    |--------------------------------------------------------------------------
    */

    return [1, 2, 3, 5];
}

function salesEntryCreateAllowed()
{
    return permissionAllowed(salesPermissionKeys(), 3);
}

function salesEntryDocumentCreateActionMap()
{
    /*
    |--------------------------------------------------------------------------
    | Sales Entry same-row document controls.
    |--------------------------------------------------------------------------
    | Document Type ID => Permission Action Code
    |
    | 1 = Quotation       => 32 = Create Quotation
    | 2 = Proforma Bill   => 33 = Create Proforma Bill
    | 3 = Sales Bill      => 34 = Create Sales Bill
    | 5 = Final Invoice   => 35 = Create Final Invoice
    |--------------------------------------------------------------------------
    */

    return [
        1 => 32,
        2 => 33,
        3 => 34,
        5 => 35,
    ];
}

function salesEntryDocumentCreateAllowed($documentType)
{
    $documentType = (int)$documentType;
    $map = salesEntryDocumentCreateActionMap();

    if (!salesEntryCreateAllowed()) {
        return false;
    }

    if (!in_array($documentType, salesEntryCreatableDocumentTypes(), true)) {
        return false;
    }

    if (!isset($map[$documentType])) {
        return false;
    }

    /*
     * Important:
     * Document type dropdown is now controlled from the Sales Entry row itself.
     * It does NOT use list page Create/Add action 3.
     */
    return permissionAllowed(salesPermissionKeys(), $map[$documentType]);
}

function requireSalesEntryDocumentCreatePermission($documentType)
{
    if (!salesEntryDocumentCreateAllowed((int)$documentType)) {
        jsonResponse(false, 'You do not have permission to create this document type.');
    }
}



function salesDocumentPermissionKeys($documentType)
{
    $types = salesDocumentTypes();
    $documentType = (int)$documentType;

    if (!isset($types[$documentType])) {
        return [];
    }

    return array_values(array_unique(array_merge(
        [$types[$documentType]['permission_key']],
        $types[$documentType]['legacy_keys'] ?? []
    )));
}

function hasSalesDocumentPermission($documentType, $action)
{
    /*
    |--------------------------------------------------------------------------
    | Document type permission must be separate from Sales Entry permission.
    |--------------------------------------------------------------------------
    | Do not fallback to global 'sales' key here.
    | This fixes Document Type dropdown and Generate button behaving the same.
    |--------------------------------------------------------------------------
    */

    $keys = salesDocumentPermissionKeys((int)$documentType);

    if (!$keys) {
        return false;
    }

    return permissionAllowed($keys, $action);
}

function requireSalesDocumentPermission($documentType, $action, $message = 'Document type permission denied.')
{
    if (!hasSalesDocumentPermission((int)$documentType, $action)) {
        jsonResponse(false, $message);
    }
}

function salesDocumentAllowedTypes($action = 1)
{
    $allowed = [];

    foreach (salesDocumentTypes() as $typeId => $type) {
        if (hasSalesDocumentPermission((int)$typeId, $action)) {
            $allowed[] = (int)$typeId;
        }
    }

    return $allowed;
}


function hasAnySalesDocumentPermission($documentType, array $actions)
{
    foreach ($actions as $action) {
        if (hasSalesDocumentPermission((int)$documentType, $action)) {
            return true;
        }
    }

    return false;
}

function requireSalesDocumentAnyPermission($documentType, array $actions, $message = 'Document type permission denied.')
{
    if (!hasAnySalesDocumentPermission((int)$documentType, $actions)) {
        jsonResponse(false, $message);
    }
}

function salesGenerateActionsForTarget($targetType)
{
    $targetType = (int)$targetType;

    /*
    |--------------------------------------------------------------------------
    | Generate target -> frontend source action key names.
    |--------------------------------------------------------------------------
    | No Generate Quotation.
    | No Generate Sale Order.
    |--------------------------------------------------------------------------
    */

    $map = [
        2 => ['generate_proforma_bill'],
        3 => ['generate_sales_bill'],
        5 => ['generate_invoice']
    ];

    return $map[$targetType] ?? [];
}

function salesGenerateActionCodesForTarget($targetType)
{
    $targetType = (int)$targetType;

    /*
    |--------------------------------------------------------------------------
    | Final generate action mapping.
    |--------------------------------------------------------------------------
    | 12 = Generate Final Invoice
    | 13 = Generate Proforma Bill
    | 16 = Generate Sales Bill
    |
    | Removed from sales flow:
    | 14 = Generate Quotation
    | 15 = Generate Sale Order (not used in Sales flow)
    |--------------------------------------------------------------------------
    */

    $map = [
        2 => [13],
        3 => [16],
        5 => [12],
    ];

    return $map[$targetType] ?? [];
}

function allowedGenerateTargetsForSource($sourceType)
{
    $sourceType = (int)$sourceType;

    /*
    |--------------------------------------------------------------------------
    | Final row based generate flow.
    |--------------------------------------------------------------------------
    | Quotation row  -> Proforma, Sales Bill, Final Invoice
    | Proforma row   -> Sales Bill, Final Invoice
    | Sales Bill row -> Final Invoice
    | Final Invoice  -> No generate
    |--------------------------------------------------------------------------
    */

    $map = [
        1 => [2, 3, 5],
        2 => [3, 5],
        3 => [5],
    ];

    return $map[$sourceType] ?? [];
}

function canGenerateSourceToTarget($sourceType, $targetType)
{
    $sourceType = (int)$sourceType;
    $targetType = (int)$targetType;

    if ($sourceType <= 0 || $targetType <= 0 || $sourceType === $targetType) {
        return false;
    }

    if (!in_array($targetType, allowedGenerateTargetsForSource($sourceType), true)) {
        return false;
    }

    foreach (salesGenerateActionCodesForTarget($targetType) as $actionCode) {
        if (hasSalesDocumentPermission($sourceType, $actionCode)) {
            return true;
        }
    }

    return false;
}

function generatePermissionTextForTarget($targetType)
{
    $targetType = (int)$targetType;

    $map = [
        2 => 'source document action 13 Generate Proforma Bill',
        3 => 'source document action 16 Generate Sales Bill',
        5 => 'source document action 12 Generate Final Invoice'
    ];

    return $map[$targetType] ?? 'source document generate permission';
}

function salesDocumentAllowedTypesAny(array $actions)
{
    $allowed = [];

    foreach (salesDocumentTypes() as $typeId => $type) {
        if (hasAnySalesDocumentPermission((int)$typeId, $actions)) {
            $allowed[] = (int)$typeId;
        }
    }

    return $allowed;
}

function salesEntryDataAllowed()
{
    /*
     * Sales Entry is controlled by the Sales Entry menu.
     * Generate mode can also open when the source document has generate permission.
     */
    return permissionAllowed(salesPermissionKeys(), 1)
        || permissionAllowed(salesPermissionKeys(), 2)
        || permissionAllowed(salesPermissionKeys(), 3)
        || permissionAllowed(salesPermissionKeys(), 4)
        || permissionAllowed(salesPermissionKeys(), 6)
        || permissionAllowed(salesPermissionKeys(), 17)
        || !empty(salesDocumentAllowedTypesAny([12, 13, 16]));
}

function requireSalesEntryDataPermission()
{
    if (!salesEntryDataAllowed()) {
        jsonResponse(false, 'Permission denied.');
    }
}


function salesDocumentAny($action)
{
    return !empty(salesDocumentAllowedTypesAny([(int)permissionActionCode($action)]));
}


function salesDocumentPermissionPayload()
{
    $types = salesDocumentTypes();
    $permissions = [];
    $allowedDocumentTypes = [];

    $salesEntryCreateAllowed = salesEntryCreateAllowed();

    foreach ($types as $typeId => $type) {
        $typeId = (int)$typeId;

        $permissions[$typeId] = [
            'view' => hasSalesDocumentPermission($typeId, 1),
            'list' => hasSalesDocumentPermission($typeId, 2),

            /*
             * Direct Sales Entry document dropdown is role based document-wise.
             * Sales Entry action 3 opens create mode.
             * Target document list action 3 decides whether that document type appears.
             */
            'add' => salesEntryDocumentCreateAllowed($typeId),

            'edit' => hasSalesDocumentPermission($typeId, 4),
            'delete' => hasSalesDocumentPermission($typeId, 5),
            'print' => hasSalesDocumentPermission($typeId, 6),
            'export' => hasSalesDocumentPermission($typeId, 7),

            /*
             * Generate icons are source-row based.
             * Do not use Generate Quotation or Generate Sale Order.
             */
            'generate_invoice' => in_array(5, allowedGenerateTargetsForSource($typeId), true) && hasSalesDocumentPermission($typeId, 12),
            'generate_proforma_bill' => in_array(2, allowedGenerateTargetsForSource($typeId), true) && hasSalesDocumentPermission($typeId, 13),
            'generate_quotation' => false,
            'generate_sale_order' => false,
            'generate_sales_bill' => in_array(3, allowedGenerateTargetsForSource($typeId), true) && hasSalesDocumentPermission($typeId, 16),

            'receive_payment' => hasSalesDocumentPermission($typeId, 17),
            'cancel' => hasSalesDocumentPermission($typeId, 18),
            'whatsapp' => hasSalesDocumentPermission($typeId, 22),
            'email' => hasSalesDocumentPermission($typeId, 23),
        ];

        /*
         * Sales Entry dropdown shows only document types allowed by role base:
         * Sales Entry action 3 + target document action 3.
         */
        if ($permissions[$typeId]['add']) {
            $allowedDocumentTypes[] = $typeId;
        }
    }

    return [
        'document_types' => $types,
        'allowed_document_types' => $allowedDocumentTypes,
        'view_document_types' => salesDocumentAllowedTypes(1),
        'list_document_types' => salesDocumentAllowedTypes(2),
        'permissions' => $permissions,
        'can_view' => permissionAllowed(salesPermissionKeys(), 1),
        'can_list' => permissionAllowed(salesPermissionKeys(), 2),
        'can_add' => $salesEntryCreateAllowed && !empty($allowedDocumentTypes),
        'can_edit' => permissionAllowed(salesPermissionKeys(), 4),
        'can_delete' => permissionAllowed(salesPermissionKeys(), 5),
        'can_print' => permissionAllowed(salesPermissionKeys(), 6),
        'can_export' => permissionAllowed(salesPermissionKeys(), 7),
        'can_generate_invoice' => salesDocumentAny(12),
        'can_generate_proforma_bill' => salesDocumentAny(13),
        'can_generate_quotation' => false,
        'can_generate_sale_order' => false,
        'can_generate_sales_bill' => salesDocumentAny(16),
        'can_receive_payment' => permissionAllowed(salesPermissionKeys(), 17),
        'can_cancel' => permissionAllowed(salesPermissionKeys(), 18),
        'can_quick_add_customer' => permissionAllowed(['customers', 'customers_create'], 3),
        'can_view_customers' => permissionAllowed(['customers', 'customers_create'], 1),
        'can_hold_bill' => $salesEntryCreateAllowed && !empty($allowedDocumentTypes),
        'can_clear_draft' => $salesEntryCreateAllowed && !empty($allowedDocumentTypes)
    ];
}

function getSalesDocumentPermissions(PDO $pdo)
{
    if (!salesEntryDataAllowed()) {
        jsonResponse(false, 'Permission denied.');
    }

    jsonOk('Document permissions loaded.', salesDocumentPermissionPayload());
}

function getPageContext(PDO $pdo)
{
    if (!salesEntryDataAllowed()) {
        jsonResponse(false, 'Permission denied.');
    }

    $payload = salesDocumentPermissionPayload();

    jsonOk('Page context loaded.', [
        'context' => array_merge($payload, [
            'page_title' => 'Sales',
            'page_note' => 'Sales documents controlled by role permission.',
            'create_url' => BASE_URL . 'pages/sales-create.php',
            'payment_url' => BASE_URL . 'pages/customer-payments.php'
        ])
    ]);
}

function customerPaymentCan($action)
{
    $actionCode = permissionActionCode($action);

    return permissionAllowed([
        'customer_payments',
        'customer-payments',
        'customers',
        'sales'
    ], $actionCode);
}

function requireCustomerPaymentPermission($action)
{
    if (!customerPaymentCan($action)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function customerPaymentsPageContext(PDO $pdo)
{
    if (!customerPaymentCan(1) && !customerPaymentCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    jsonOk('Page context loaded.', [
        'context' => [
            'can_view' => customerPaymentCan(1),
            'can_list' => customerPaymentCan(2),
            'can_receive_payment' => customerPaymentCan(17) || customerPaymentCan(3),
            'can_edit' => customerPaymentCan(4),
            'can_cancel' => customerPaymentCan(18) || customerPaymentCan(5),
            'can_sales_list' => permissionAllowed(salesPermissionKeys(), 1) || permissionAllowed(salesPermissionKeys(), 2),
            'page_title' => 'Customer Payments',
            'page_note' => 'Individual bill payment, overall FIFO payment and opening balance payment',
            'new_payment_label' => 'New Payment',
            'sales_list_url' => BASE_URL . 'pages/all-sales-list.php'
        ]
    ]);
}


function salesPermissionDebug(PDO $pdo)
{
    $roleIds = currentRoleIdsForSalesPermission();

    $menuKeys = array_values(array_unique(array_merge(
        salesPermissionKeys(),
        salesDocumentPermissionKeys(1),
        salesDocumentPermissionKeys(2),
        salesDocumentPermissionKeys(3),
        salesDocumentPermissionKeys(4),
        salesDocumentPermissionKeys(5),
        ['all_sales_list', 'sales_print', 'customer_payments']
    )));

    $menuRows = [];
    $roleBaseRows = [];

    if ($menuKeys) {
        $holders = [];
        $params = [];

        foreach ($menuKeys as $index => $menuKey) {
            $key = ':menu_key_' . $index;
            $holders[] = $key;
            $params[$key] = $menuKey;
        }

        $stmt = $pdo->prepare("
            SELECT id, parent_id, menu_for, menu_key, menu_name, menu_url, allowed_actions, status
            FROM sidebar_menus
            WHERE menu_key IN (" . implode(',', $holders) . ")
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute($params);
        $menuRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($menuKeys && $roleIds) {
        $keyHolders = [];
        $roleHolders = [];
        $params = [];

        foreach ($menuKeys as $index => $menuKey) {
            $key = ':rba_menu_key_' . $index;
            $keyHolders[] = $key;
            $params[$key] = $menuKey;
        }

        foreach ($roleIds as $index => $roleId) {
            $key = ':rba_role_id_' . $index;
            $roleHolders[] = $key;
            $params[$key] = (int)$roleId;
        }

        $stmt = $pdo->prepare("
            SELECT
                rba.role_id,
                r.role_name,
                r.role_type,
                r.parent_role_id,
                sm.menu_key,
                sm.menu_name,
                sm.menu_url,
                sm.allowed_actions,
                rba.access_actions,
                rba.status
            FROM role_base_access rba
            INNER JOIN roles r ON r.id = rba.role_id
            INNER JOIN sidebar_menus sm ON sm.id = rba.menu_id
            WHERE rba.role_id IN (" . implode(',', $roleHolders) . ")
            AND sm.menu_key IN (" . implode(',', $keyHolders) . ")
            ORDER BY rba.role_id ASC, sm.sort_order ASC, sm.id ASC
        ");
        $stmt->execute($params);
        $roleBaseRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    $docPermissions = [];

    foreach (salesDocumentTypes() as $typeId => $type) {
        $docPermissions[$typeId] = [
            'label' => $type['label'],
            'keys_checked' => salesDocumentPermissionKeys($typeId),
            'add_document_type' => hasSalesDocumentPermission($typeId, 3),
            'view' => hasSalesDocumentPermission($typeId, 1),
            'list' => hasSalesDocumentPermission($typeId, 2),
            'edit' => hasSalesDocumentPermission($typeId, 4),
            'print' => hasSalesDocumentPermission($typeId, 6),
            'convert' => hasSalesDocumentPermission($typeId, 9),
            'generate_invoice' => hasSalesDocumentPermission($typeId, 12),
            'generate_proforma_bill' => hasSalesDocumentPermission($typeId, 13),
            'generate_quotation' => false,
            'generate_sale_order' => false,
            'generate_sales_bill' => hasSalesDocumentPermission($typeId, 16),
            'receive_payment' => hasSalesDocumentPermission($typeId, 17),
            'cancel' => hasSalesDocumentPermission($typeId, 18)
        ];
    }

    jsonOk('Sales permission debug loaded.', [
        'debug' => [
            'current_user_id' => currentUserIdSafe(),
            'session_role_id' => $_SESSION['role_id'] ?? null,
            'role_ids_checked' => $roleIds,
            'global_sales_keys' => salesPermissionKeys(),
            'document_permissions' => $docPermissions,
            'sidebar_menu_rows' => $menuRows,
            'role_base_access_rows' => $roleBaseRows
        ]
    ]);
}


function updateIfColumnExists(PDO $pdo, array $scope, $saleId, array $values)
{
    $sets = [];
    $params = [
        ':id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    foreach ($values as $column => $value) {
        if (columnExists($pdo, 'sales', $column)) {
            $param = ':' . $column;
            $sets[] = "$column = $param";
            $params[$param] = $value;
        }
    }

    if (!$sets) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE sales
        SET " . implode(', ', $sets) . "
        WHERE id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");
    $stmt->execute($params);
}


function jsonOk($message, $data = [])
{
    jsonResponse(true, $message, $data);
}

function readPayload()
{
    $payload = $_POST['payload'] ?? '';
    if ($payload === '') {
        $raw = file_get_contents('php://input');
        $decodedRaw = json_decode($raw, true);
        if (is_array($decodedRaw)) {
            return $decodedRaw;
        }
    }

    $data = json_decode($payload, true);
    if (!is_array($data)) {
        jsonResponse(false, 'Invalid sales payload.');
    }

    return $data;
}

function cleanDateOrNull($date)
{
    $date = trim((string)$date);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
}

function toFloat($value, $default = 0.0)
{
    if ($value === null || $value === '') {
        return (float)$default;
    }

    return round((float)$value, 4);
}

function round2($value)
{
    return round((float)$value, 2);
}

function listSales(PDO $pdo)
{
    $allowedListTypes = salesDocumentAllowedTypesAny([1, 2]);

    if (!$allowedListTypes) {
        jsonResponse(false, 'No sales document permission available.');
    }

    $scope = getScope();
    $search = cleanInput($_GET['search'] ?? '');
    $documentType = (int)($_GET['document_type'] ?? 0);
    $status = (int)($_GET['status'] ?? 0);
    $fromDate = cleanDateOrNull($_GET['from_date'] ?? '');
    $toDate = cleanDateOrNull($_GET['to_date'] ?? '');

    $where = "WHERE s.business_id = :business_id AND s.branch_id = :branch_id";
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($documentType > 0) {
        requireSalesDocumentAnyPermission(
            $documentType,
            [1, 2],
            'You do not have permission to list this document type.'
        );

        $where .= " AND s.document_type = :document_type";
        $params[':document_type'] = $documentType;
    } else {
        $holders = [];

        foreach ($allowedListTypes as $index => $typeId) {
            $key = ':allowed_document_type_' . $index;
            $holders[] = $key;
            $params[$key] = (int)$typeId;
        }

        $where .= " AND s.document_type IN (" . implode(',', $holders) . ")";
    }

    if ($status > 0) {
        $where .= " AND s.status = :status";
        $params[':status'] = $status;
    }

    if ($fromDate) {
        $where .= " AND s.sales_date >= :from_date";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $where .= " AND s.sales_date <= :to_date";
        $params[':to_date'] = $toDate;
    }

    if ($search !== '') {
        /*
        |--------------------------------------------------------------------------
        | Unique PDO placeholders avoid SQLSTATE[HY093].
        |--------------------------------------------------------------------------
        */

        $where .= "
            AND (
                s.sales_no LIKE :search_sales_no
                OR c.customer_name LIKE :search_customer
                OR c.mobile LIKE :search_mobile
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[':search_sales_no'] = $searchValue;
        $params[':search_customer'] = $searchValue;
        $params[':search_mobile'] = $searchValue;
    }

    $stmt = $pdo->prepare("
        SELECT
            s.*,
            c.customer_name,
            c.mobile AS customer_mobile
        FROM sales s
        INNER JOIN customers c ON c.id = s.customer_id
        $where
        ORDER BY s.id DESC
        LIMIT 500
    ");
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $rowDocumentType = (int)($row['document_type'] ?? 0);

        $row['can_view'] = hasSalesDocumentPermission($rowDocumentType, 1);
        $row['can_list'] = hasSalesDocumentPermission($rowDocumentType, 2);
        $row['can_edit'] = hasSalesDocumentPermission($rowDocumentType, 4);
        $row['can_delete'] = hasSalesDocumentPermission($rowDocumentType, 5);
        $row['can_print'] = hasSalesDocumentPermission($rowDocumentType, 6);
        $row['can_export'] = hasSalesDocumentPermission($rowDocumentType, 7);
        $row['can_convert'] = false;
        $row['can_generate_invoice'] = in_array(5, allowedGenerateTargetsForSource($rowDocumentType), true) && hasSalesDocumentPermission($rowDocumentType, 12);
        $row['can_generate_proforma_bill'] = in_array(2, allowedGenerateTargetsForSource($rowDocumentType), true) && hasSalesDocumentPermission($rowDocumentType, 13);
        $row['can_generate_quotation'] = false;
        $row['can_generate_sale_order'] = false;
        $row['can_generate_sales_bill'] = in_array(3, allowedGenerateTargetsForSource($rowDocumentType), true) && hasSalesDocumentPermission($rowDocumentType, 16);
        $row['can_receive_payment'] = hasSalesDocumentPermission($rowDocumentType, 17) || customerPaymentCan(17);
        $row['can_cancel'] = hasSalesDocumentPermission($rowDocumentType, 18);
        $row['can_whatsapp'] = hasSalesDocumentPermission($rowDocumentType, 22);
        $row['can_email'] = hasSalesDocumentPermission($rowDocumentType, 23);
    }
    unset($row);

    $permissionPayload = salesDocumentPermissionPayload();

    jsonOk('Sales loaded.', [
        'rows' => $rows,
        'document_types' => $permissionPayload['document_types'],
        'permissions' => $permissionPayload['permissions'],
        'allowed_document_types' => $permissionPayload['allowed_document_types'],
        'view_document_types' => $permissionPayload['view_document_types'],
        'list_document_types' => $permissionPayload['list_document_types']
    ]);
}

function getSale(PDO $pdo)
{
    requireSalesEntryDataPermission();

    $scope = getScope();
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid sales id.');
    }

    $stmt = $pdo->prepare("
        SELECT s.*, c.customer_name, c.mobile AS customer_mobile, c.gst_number, c.address
        FROM sales s
        INNER JOIN customers c ON c.id = s.customer_id
        WHERE s.id = :id AND s.business_id = :business_id AND s.branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $id,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        jsonResponse(false, 'Sale not found.');
    }

    requireSalesDocumentAnyPermission((int)$sale['document_type'], [1, 2, 4, 12, 13, 14, 15, 16], 'You do not have permission to open this document type.');

    $itemsStmt = $pdo->prepare("
        SELECT *
        FROM sales_items
        WHERE sales_id = :sales_id AND business_id = :business_id AND branch_id = :branch_id AND status = 1
        ORDER BY id ASC
    ");
    $itemsStmt->execute([
        ':sales_id' => $id,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $payStmt = $pdo->prepare("
        SELECT sp.*, pm.mode_name
        FROM sales_payments sp
        INNER JOIN payment_modes pm ON pm.id = sp.payment_mode_id
        WHERE sp.sales_id = :sales_id AND sp.business_id = :business_id AND sp.branch_id = :branch_id AND sp.status = 1
        ORDER BY sp.id ASC
    ");
    $payStmt->execute([
        ':sales_id' => $id,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonOk('Sale loaded.', [
        'sale' => $sale,
        'items' => $itemsStmt->fetchAll(PDO::FETCH_ASSOC),
        'payments' => $payStmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function searchCustomers(PDO $pdo)
{
    requireSalesEntryDataPermission();

    $scope = getScope();
    $q = cleanInput($_GET['q'] ?? '');
    $zoneId = (int)($_GET['zone_id'] ?? 0);
    $hasZoneId = columnExists($pdo, 'customers', 'zone_id');

    $where = "business_id = :business_id AND branch_id = :branch_id AND status = 1";
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($q !== '') {
        $where .= " AND (customer_name LIKE :q_customer OR mobile LIKE :q_mobile OR gst_number LIKE :q_gst)";
        $params[':q_customer'] = '%' . $q . '%';
        $params[':q_mobile'] = '%' . $q . '%';
        $params[':q_gst'] = '%' . $q . '%';
    }

    if ($zoneId > 0 && $hasZoneId) {
        $where .= " AND zone_id = :zone_id";
        $params[':zone_id'] = $zoneId;
    }

    $zoneSelect = $hasZoneId ? 'zone_id' : 'NULL AS zone_id';

    $stmt = $pdo->prepare("
        SELECT id, customer_name, mobile, gst_number, address, city, state, pincode, current_outstanding, $zoneSelect
        FROM customers
        WHERE $where
        ORDER BY customer_name ASC
        LIMIT 50
    ");
    $stmt->execute($params);

    jsonOk('Customers loaded.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function searchProducts(PDO $pdo)
{
    requireSalesEntryDataPermission();

    $scope = getScope();
    $q = cleanInput($_GET['q'] ?? '');

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    $where = "p.business_id = :business_id AND p.branch_id = :branch_id AND p.status = 1";

    if ($q !== '') {
        $where .= " AND (p.product_code LIKE :q_code OR p.product_name LIKE :q_name)";
        $params[':q_code'] = '%' . $q . '%';
        $params[':q_name'] = '%' . $q . '%';
    }

    $wholesaleMarkupTypeSelect = columnExists($pdo, 'products', 'wholesale_markup_type')
        ? 'p.wholesale_markup_type'
        : 'NULL AS wholesale_markup_type';

    $wholesaleMarkupValueSelect = columnExists($pdo, 'products', 'wholesale_markup_value')
        ? 'p.wholesale_markup_value'
        : 'NULL AS wholesale_markup_value';

    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.product_code,
            p.product_name,
            p.retail_price,
            p.wholesale_price,
            p.retail_markup_type,
            p.retail_markup_value,
            $wholesaleMarkupTypeSelect,
            $wholesaleMarkupValueSelect,
            p.base_unit,
            p.box_label,
            p.secondary_unit_label,
            p.secondary_unit_value,
            p.gst_type,
            h.hsn_code,
            h.cgst_percentage,
            h.sgst_percentage,
            h.igst_percentage,
            COALESCE(SUM(pi.available_qty), 0) AS available_qty
        FROM products p
        LEFT JOIN hsn_codes h ON h.id = p.hsn_id
        LEFT JOIN purchase_items pi ON pi.product_id = p.id
            AND pi.business_id = p.business_id
            AND pi.branch_id = p.branch_id
            AND pi.available_qty > 0
            AND pi.status = 1
        WHERE $where
        GROUP BY p.id
        HAVING available_qty > 0
        ORDER BY p.product_name ASC
        LIMIT 20
    ");
    $stmt->execute($params);

    jsonOk('Products loaded.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getProductBatches(PDO $pdo)
{
    requireSalesEntryDataPermission();

    $scope = getScope();
    $productId = (int)($_GET['product_id'] ?? 0);

    if ($productId <= 0) {
        jsonResponse(false, 'Invalid product.');
    }

    $wholesaleMarkupTypeSelect = columnExists($pdo, 'products', 'wholesale_markup_type')
        ? 'pr.wholesale_markup_type'
        : 'NULL AS wholesale_markup_type';

    $wholesaleMarkupValueSelect = columnExists($pdo, 'products', 'wholesale_markup_value')
        ? 'pr.wholesale_markup_value'
        : 'NULL AS wholesale_markup_value';

    $stmt = $pdo->prepare("
        SELECT 
            pi.id AS purchase_item_id,
            pi.purchase_id,
            pi.product_id,
            pi.product_code,
            pi.product_name,
            pi.available_qty,
            pi.purchase_price,
            pi.unit_conversion,
            pi.unit_label,
            pi.base_unit,
            pi.box_label,
            pi.expiry_date,
            pi.hsn_id,
            pi.hsn_code,
            pi.cgst_percentage,
            pi.sgst_percentage,
            pi.igst_percentage,
            p.batch_no,
            p.bill_no,
            p.purchase_date,
            pr.base_unit AS product_base_unit,
            pr.secondary_unit_label,
            pr.secondary_unit_value,
            pr.retail_price,
            pr.wholesale_price,
            pr.retail_markup_type,
            pr.retail_markup_value,
            $wholesaleMarkupTypeSelect,
            $wholesaleMarkupValueSelect
        FROM purchase_items pi
        INNER JOIN purchases p ON p.id = pi.purchase_id
        INNER JOIN products pr ON pr.id = pi.product_id
        WHERE pi.business_id = :business_id
        AND pi.branch_id = :branch_id
        AND pi.product_id = :product_id
        AND pi.available_qty > 0
        AND pi.status = 1
        ORDER BY p.purchase_date ASC, pi.id ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':product_id' => $productId
    ]);

    jsonOk('Batches loaded.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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

function ensureDefaultPaymentModes(PDO $pdo, array $scope)
{
    $defaultModes = ['Cash', 'UPI', 'Bank', 'Cheque'];

    foreach ($defaultModes as $modeName) {
        $check = $pdo->prepare("
            SELECT id
            FROM payment_modes
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND LOWER(mode_name) = LOWER(:mode_name)
            LIMIT 1
        ");
        $check->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':mode_name' => $modeName
        ]);

        if (!$check->fetchColumn()) {
            $insert = $pdo->prepare("
                INSERT INTO payment_modes
                (business_id, branch_id, mode_name, status)
                VALUES
                (:business_id, :branch_id, :mode_name, 1)
            ");
            $insert->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':mode_name' => $modeName
            ]);
        }
    }
}

function getPaymentModes(PDO $pdo)
{
    requireSalesEntryDataPermission();

    $scope = getScope();

    ensureDefaultPaymentModes($pdo, $scope);

    $stmt = $pdo->prepare("
        SELECT id, mode_name
        FROM payment_modes
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        AND LOWER(mode_name) IN ('cash', 'upi', 'bank', 'cheque')
        ORDER BY FIELD(LOWER(mode_name), 'cash', 'upi', 'bank', 'cheque')
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonOk('Payment modes loaded.', ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}



function salesDocumentLabelByType($typeId)
{
    $types = salesDocumentTypes();
    return isset($types[(int)$typeId]) ? $types[(int)$typeId]['label'] : 'Document';
}

function listCustomerDueDocuments(PDO $pdo)
{
    requireCustomerPaymentPermission(1);

    $scope = getScope();
    $customerId = (int)($_GET['customer_id'] ?? 0);
    $includePaid = (int)($_GET['include_paid'] ?? 0);

    if ($customerId <= 0) {
        jsonResponse(false, 'Invalid customer.');
    }

    $whereDue = $includePaid === 1 ? "" : "AND s.due_amount > 0";

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.sales_no,
            s.document_type,
            s.sales_date,
            s.grand_total,
            s.paid_amount,
            s.due_amount,
            s.payment_status,
            s.status
        FROM sales s
        WHERE s.business_id = :business_id
        AND s.branch_id = :branch_id
        AND s.customer_id = :customer_id
        AND s.status IN (1,2)
        $whereDue
        ORDER BY s.sales_date ASC, s.id ASC
        LIMIT 500
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':customer_id' => $customerId
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['document_label'] = salesDocumentLabelByType((int)$row['document_type']);
    }

    jsonOk('Customer due documents loaded.', ['rows' => $rows]);
}

function getPaymentModeSummary(PDO $pdo, array $scope, $paymentId)
{
    $splits = fetchCustomerPaymentSplits($pdo, $scope, $paymentId);
    if (!$splits) {
        return '';
    }

    $parts = [];
    foreach ($splits as $split) {
        $parts[] = ($split['mode_name'] ?: 'Mode') . ' ₹' . number_format((float)$split['amount'], 2);
    }

    return implode(', ', $parts);
}


function paymentPageInit(PDO $pdo)
{
    requireCustomerPaymentPermission(1);

    $scope = getScope();
    ensureDefaultPaymentModes($pdo, $scope);

    $customerId = (int)($_GET['customer_id'] ?? 0);
    $salesId = (int)($_GET['sales_id'] ?? 0);

    $data = [
        'payment_modes' => [],
        'customers' => [],
        'selected_customer' => null,
        'selected_sale' => null,
        'payments' => []
    ];

    $modes = $pdo->prepare("
        SELECT id, mode_name
        FROM payment_modes
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY mode_name ASC
    ");
    $modes->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
    $data['payment_modes'] = $modes->fetchAll(PDO::FETCH_ASSOC);

    if ($salesId > 0) {
        $saleStmt = $pdo->prepare("
            SELECT s.id, s.sales_no, s.customer_id, s.document_type, s.sales_date,
                   s.grand_total, s.paid_amount, s.due_amount, s.payment_status,
                   c.customer_name, c.mobile AS customer_mobile,
                   COALESCE(c.opening_due, c.opening_outstanding, 0) AS opening_due,
                   COALESCE(c.current_outstanding, c.total_outstanding, 0) AS total_outstanding
            FROM sales s
            INNER JOIN customers c ON c.id = s.customer_id
            WHERE s.id = :sales_id
            AND s.business_id = :business_id
            AND s.branch_id = :branch_id
            LIMIT 1
        ");
        $saleStmt->execute([
            ':sales_id' => $salesId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
        $data['selected_sale'] = $saleStmt->fetch(PDO::FETCH_ASSOC);
        if ($data['selected_sale']) {
            $data['selected_sale']['document_label'] = salesDocumentLabelByType((int)$data['selected_sale']['document_type']);
            $customerId = (int)$data['selected_sale']['customer_id'];
        }
    }

    if ($customerId > 0) {
        $data['selected_customer'] = getCustomerPaymentSnapshot($pdo, $scope, $customerId);
        $data['payments'] = fetchCustomerPaymentRows($pdo, $scope, $customerId, 100);
    }

    jsonOk('Payment page loaded.', $data);
}

function listCustomerPayments(PDO $pdo)
{
    requireCustomerPaymentPermission(2);

    $scope = getScope();
    $customerId = (int)($_GET['customer_id'] ?? 0);
    $search = cleanInput($_GET['search'] ?? '');

    $where = "WHERE cp.business_id = :business_id AND cp.branch_id = :branch_id";
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($customerId > 0) {
        $where .= " AND cp.customer_id = :customer_id";
        $params[':customer_id'] = $customerId;
    }

    if ($search !== '') {
        $where .= " AND (cp.payment_no LIKE :search_payment OR c.customer_name LIKE :search_customer OR c.mobile LIKE :search_mobile OR s.sales_no LIKE :search_sales)";
        $params[':search_payment'] = '%' . $search . '%';
        $params[':search_customer'] = '%' . $search . '%';
        $params[':search_mobile'] = '%' . $search . '%';
        $params[':search_sales'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT cp.*, c.customer_name, c.mobile AS customer_mobile,
               CASE
                   WHEN COALESCE(split_count.cnt, 0) > 1 THEN 'Split'
                   ELSE COALESCE(split_one.mode_name, pm.mode_name)
               END AS mode_name,
               s.sales_no, s.document_type
        FROM customer_payments cp
        INNER JOIN customers c ON c.id = cp.customer_id
        LEFT JOIN payment_modes pm ON pm.id = cp.payment_mode_id
        LEFT JOIN (
            SELECT payment_id, COUNT(*) AS cnt
            FROM customer_payment_splits
            WHERE status = 1
            GROUP BY payment_id
        ) split_count ON split_count.payment_id = cp.id
        LEFT JOIN (
            SELECT cps.payment_id, MAX(pm2.mode_name) AS mode_name
            FROM customer_payment_splits cps
            LEFT JOIN payment_modes pm2 ON pm2.id = cps.payment_mode_id
            WHERE cps.status = 1
            GROUP BY cps.payment_id
        ) split_one ON split_one.payment_id = cp.id
        LEFT JOIN sales s ON s.id = cp.sales_id
        $where
        ORDER BY cp.id DESC
        LIMIT 500
    ");
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['document_label'] = !empty($row['document_type']) ? salesDocumentLabelByType((int)$row['document_type']) : '';
        $row['split_summary'] = getPaymentModeSummary($pdo, $scope, (int)$row['id']);
    }

    jsonOk('Payments loaded.', ['rows' => $rows]);
}

function getCustomerPayment(PDO $pdo)
{
    requireCustomerPaymentPermission(4);

    $scope = getScope();
    $paymentId = (int)($_GET['id'] ?? 0);

    if ($paymentId <= 0) {
        jsonResponse(false, 'Invalid payment id.');
    }

    $stmt = $pdo->prepare("
        SELECT cp.*, c.customer_name, c.mobile AS customer_mobile,
               CASE
                   WHEN COALESCE(split_count.cnt, 0) > 1 THEN 'Split'
                   ELSE COALESCE(split_one.mode_name, pm.mode_name)
               END AS mode_name,
               s.sales_no, s.document_type
        FROM customer_payments cp
        INNER JOIN customers c ON c.id = cp.customer_id
        LEFT JOIN payment_modes pm ON pm.id = cp.payment_mode_id
        LEFT JOIN (
            SELECT payment_id, COUNT(*) AS cnt
            FROM customer_payment_splits
            WHERE status = 1
            GROUP BY payment_id
        ) split_count ON split_count.payment_id = cp.id
        LEFT JOIN (
            SELECT cps.payment_id, MAX(pm2.mode_name) AS mode_name
            FROM customer_payment_splits cps
            LEFT JOIN payment_modes pm2 ON pm2.id = cps.payment_mode_id
            WHERE cps.status = 1
            GROUP BY cps.payment_id
        ) split_one ON split_one.payment_id = cp.id
        LEFT JOIN sales s ON s.id = cp.sales_id
        WHERE cp.id = :id
        AND cp.business_id = :business_id
        AND cp.branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        jsonResponse(false, 'Payment not found.');
    }

    $alloc = $pdo->prepare("
        SELECT cpa.*, s.sales_no
        FROM customer_payment_allocations cpa
        LEFT JOIN sales s ON s.id = cpa.sales_id
        WHERE cpa.payment_id = :payment_id
        AND cpa.business_id = :business_id
        AND cpa.branch_id = :branch_id
        ORDER BY cpa.id ASC
    ");
    $alloc->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonOk('Payment loaded.', [
        'payment' => $payment,
        'allocations' => $alloc->fetchAll(PDO::FETCH_ASSOC),
        'splits' => fetchCustomerPaymentSplits($pdo, $scope, $paymentId),
        'customer' => getCustomerPaymentSnapshot($pdo, $scope, (int)$payment['customer_id'])
    ]);
}


function readPaymentSplitsFromRequest()
{
    $raw = $_POST['split_payments'] ?? '';
    $rows = [];

    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                $modeId = (int)($row['payment_mode_id'] ?? 0);
                $amount = round2($row['amount'] ?? 0);
                $ref = cleanInput($row['reference_no'] ?? '');

                if ($modeId > 0 && $amount > 0) {
                    $rows[] = [
                        'payment_mode_id' => $modeId,
                        'amount' => $amount,
                        'reference_no' => $ref
                    ];
                }
            }
        }
    }

    if (!$rows) {
        $modeId = (int)($_POST['payment_mode_id'] ?? 0);
        $amount = round2($_POST['amount'] ?? 0);
        $ref = cleanInput($_POST['reference_no'] ?? '');

        if ($modeId > 0 && $amount > 0) {
            $rows[] = [
                'payment_mode_id' => $modeId,
                'amount' => $amount,
                'reference_no' => $ref
            ];
        }
    }

    return $rows;
}

function saveCustomerPaymentSplits(PDO $pdo, array $scope, $paymentId, array $splits)
{
    if (!tableExists($pdo, 'customer_payment_splits')) {
        return;
    }

    $old = $pdo->prepare("
        UPDATE customer_payment_splits
        SET status = 2
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND payment_id = :payment_id
        AND status = 1
    ");
    $old->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId
    ]);

    foreach ($splits as $split) {
        $stmt = $pdo->prepare("
            INSERT INTO customer_payment_splits
            (
                business_id, branch_id, payment_id,
                payment_mode_id, amount, reference_no, status
            )
            VALUES
            (
                :business_id, :branch_id, :payment_id,
                :payment_mode_id, :amount, :reference_no, 1
            )
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':payment_id' => $paymentId,
            ':payment_mode_id' => $split['payment_mode_id'],
            ':amount' => $split['amount'],
            ':reference_no' => $split['reference_no']
        ]);
    }
}

function fetchCustomerPaymentSplits(PDO $pdo, array $scope, $paymentId)
{
    if (!tableExists($pdo, 'customer_payment_splits')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT cps.*, pm.mode_name
        FROM customer_payment_splits cps
        LEFT JOIN payment_modes pm ON pm.id = cps.payment_mode_id
        WHERE cps.payment_id = :payment_id
        AND cps.business_id = :business_id
        AND cps.branch_id = :branch_id
        AND cps.status = 1
        ORDER BY cps.id ASC
    ");
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cancelCustomerPaymentSplits(PDO $pdo, array $scope, $paymentId)
{
    if (!tableExists($pdo, 'customer_payment_splits')) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE customer_payment_splits
        SET status = 2
        WHERE payment_id = :payment_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
    ");
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}


function saveCustomerPayment(PDO $pdo)
{
    $scope = getScope();
    $userId = currentUserIdSafe();

    $paymentId = (int)($_POST['payment_id'] ?? 0);
    requireCustomerPaymentPermission($paymentId > 0 ? 4 : 17);

    $customerId = (int)($_POST['customer_id'] ?? 0);
    $paymentType = (int)($_POST['payment_type'] ?? 0);
    $salesId = (int)($_POST['sales_id'] ?? 0);
    $paymentDate = cleanDateOrNull($_POST['payment_date'] ?? '') ?: date('Y-m-d');
    $referenceNo = cleanInput($_POST['reference_no'] ?? '');
    $notes = cleanInput($_POST['notes'] ?? '');

    $splits = readPaymentSplitsFromRequest();
    $amount = round2(array_sum(array_column($splits, 'amount')));

    if (!in_array($paymentType, [1,2,3], true)) {
        jsonResponse(false, 'Invalid payment type.');
    }

    if (!$splits || $amount <= 0) {
        jsonResponse(false, 'Add at least one valid payment split.');
    }

    if ($paymentType === 1 && $salesId <= 0) {
        jsonResponse(false, 'Select particular document for individual payment.');
    }

    $primaryModeId = (int)$splits[0]['payment_mode_id'];

    try {
        $pdo->beginTransaction();

        if ($paymentType === 1) {
            $sale = getSaleForUpdate($pdo, $scope, $salesId);
            if (!$sale) {
                throw new Exception('Sales document not found.');
            }
            $customerId = (int)$sale['customer_id'];
            requireSalesDocumentPermission((int)$sale['document_type'], 'view', 'You do not have permission for this document type.');
        }

        if ($customerId <= 0) {
            throw new Exception('Select customer.');
        }

        $customer = getCustomerForPaymentUpdate($pdo, $scope, $customerId);
        if (!$customer) {
            throw new Exception('Customer not found.');
        }

        if ($paymentId > 0) {
            $old = getCustomerPaymentForUpdate($pdo, $scope, $paymentId);
            if (!$old) {
                throw new Exception('Payment not found.');
            }

            if ((int)$old['status'] !== 1) {
                throw new Exception('Cancelled payment cannot be edited.');
            }

            reversePaymentAllocations($pdo, $scope, $paymentId);

            $paymentNo = $old['payment_no'];
            $upd = $pdo->prepare("
                UPDATE customer_payments
                SET customer_id = :customer_id,
                    payment_type = :payment_type,
                    sales_id = :sales_id,
                    payment_date = :payment_date,
                    payment_mode_id = :payment_mode_id,
                    amount = :amount,
                    reference_no = :reference_no,
                    notes = :notes,
                    updated_by = :updated_by,
                    status = 1
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $upd->execute([
                ':customer_id' => $customerId,
                ':payment_type' => $paymentType,
                ':sales_id' => $paymentType === 1 ? $salesId : null,
                ':payment_date' => $paymentDate,
                ':payment_mode_id' => $primaryModeId,
                ':amount' => $amount,
                ':reference_no' => $referenceNo,
                ':notes' => $notes,
                ':updated_by' => $userId,
                ':id' => $paymentId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        } else {
            $paymentNo = nextCustomerPaymentNo($pdo, $scope);
            $ins = $pdo->prepare("
                INSERT INTO customer_payments
                (
                    business_id, branch_id, payment_no, customer_id,
                    payment_type, sales_id, payment_date, payment_mode_id,
                    amount, reference_no, notes, status, created_by
                )
                VALUES
                (
                    :business_id, :branch_id, :payment_no, :customer_id,
                    :payment_type, :sales_id, :payment_date, :payment_mode_id,
                    :amount, :reference_no, :notes, 1, :created_by
                )
            ");
            $ins->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':payment_no' => $paymentNo,
                ':customer_id' => $customerId,
                ':payment_type' => $paymentType,
                ':sales_id' => $paymentType === 1 ? $salesId : null,
                ':payment_date' => $paymentDate,
                ':payment_mode_id' => $primaryModeId,
                ':amount' => $amount,
                ':reference_no' => $referenceNo,
                ':notes' => $notes,
                ':created_by' => $userId
            ]);
            $paymentId = (int)$pdo->lastInsertId();
        }

        saveCustomerPaymentSplits($pdo, $scope, $paymentId, $splits);
        applyCustomerPaymentAllocation($pdo, $scope, $paymentId, $customerId, $paymentType, $salesId, $amount);
        updateCustomerOutstanding($pdo, $scope, $customerId);

        addActivityLog($pdo, $scope, $userId, 'customer_payment', $paymentId > 0 ? 'SAVE' : 'CREATE', 'Payment ' . $paymentNo . ' saved.', $paymentId, $paymentNo);

        $pdo->commit();

        jsonOk('Payment saved successfully.', [
            'payment_id' => $paymentId,
            'payment_no' => $paymentNo,
            'amount' => $amount,
            'splits' => $splits,
            'customer' => getCustomerPaymentSnapshot($pdo, $scope, $customerId)
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage());
    }
}

function cancelCustomerPayment(PDO $pdo)
{
    requireCustomerPaymentPermission(18);

    $scope = getScope();
    $userId = currentUserIdSafe();
    $paymentId = (int)($_POST['id'] ?? 0);
    $reason = cleanInput($_POST['reason'] ?? '');

    if ($paymentId <= 0) {
        jsonResponse(false, 'Invalid payment id.');
    }

    try {
        $pdo->beginTransaction();

        $payment = getCustomerPaymentForUpdate($pdo, $scope, $paymentId);
        if (!$payment) {
            throw new Exception('Payment not found.');
        }

        if ((int)$payment['status'] !== 1) {
            throw new Exception('Payment already cancelled.');
        }

        reversePaymentAllocations($pdo, $scope, $paymentId);
        cancelCustomerPaymentSplits($pdo, $scope, $paymentId);

        $upd = $pdo->prepare("
            UPDATE customer_payments
            SET status = 2,
                cancelled_by = :cancelled_by,
                cancelled_at = NOW(),
                cancel_reason = :cancel_reason
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $upd->execute([
            ':cancelled_by' => $userId,
            ':cancel_reason' => $reason,
            ':id' => $paymentId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        updateCustomerOutstanding($pdo, $scope, (int)$payment['customer_id']);
        addActivityLog($pdo, $scope, $userId, 'customer_payment', 'CANCEL', 'Payment ' . $payment['payment_no'] . ' cancelled.', $paymentId, $payment['payment_no']);

        $pdo->commit();

        jsonOk('Payment cancelled and reversed successfully.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage());
    }
}

function getCustomerPaymentSnapshot(PDO $pdo, array $scope, $customerId)
{
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.customer_name,
            c.mobile,
            COALESCE(c.opening_balance, c.opening_outstanding, 0) AS opening_balance,
            COALESCE(c.opening_paid, 0) AS opening_paid,
            COALESCE(c.opening_due, c.opening_outstanding, 0) AS opening_due,
            COALESCE((
                SELECT SUM(due_amount)
                FROM sales
                WHERE business_id = :business_id
                AND branch_id = :branch_id
                AND customer_id = :customer_id
                AND status IN (1,2)
            ), 0) AS sales_due,
            COALESCE(c.current_outstanding, c.total_outstanding, 0) AS total_outstanding
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

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetchCustomerPaymentRows(PDO $pdo, array $scope, $customerId, $limit = 100)
{
    $stmt = $pdo->prepare("
        SELECT cp.*, pm.mode_name, s.sales_no
        FROM customer_payments cp
        LEFT JOIN payment_modes pm ON pm.id = cp.payment_mode_id
        LEFT JOIN sales s ON s.id = cp.sales_id
        WHERE cp.business_id = :business_id
        AND cp.branch_id = :branch_id
        AND cp.customer_id = :customer_id
        ORDER BY cp.id DESC
        LIMIT " . (int)$limit . "
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':customer_id' => $customerId
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomerForPaymentUpdate(PDO $pdo, array $scope, $customerId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM customers
        WHERE id = :customer_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        FOR UPDATE
    ");
    $stmt->execute([
        ':customer_id' => $customerId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCustomerPaymentForUpdate(PDO $pdo, array $scope, $paymentId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM customer_payments
        WHERE id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
        FOR UPDATE
    ");
    $stmt->execute([
        ':id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function applyCustomerPaymentAllocation(PDO $pdo, array $scope, $paymentId, $customerId, $paymentType, $salesId, $amount)
{
    $remaining = round2($amount);

    if ($paymentType === 1) {
        $sale = getSaleForUpdate($pdo, $scope, $salesId);
        if (!$sale || (int)$sale['customer_id'] !== (int)$customerId) {
            throw new Exception('Invalid sales document for this customer.');
        }

        $due = round2($sale['due_amount'] ?? 0);
        if ($remaining - $due > 0.01) {
            throw new Exception('Payment amount cannot be greater than selected bill due.');
        }

        allocateToSale($pdo, $scope, $paymentId, $customerId, $salesId, $remaining);
        return;
    }

    if ($paymentType === 3 || $paymentType === 2) {
        $customer = getCustomerForPaymentUpdate($pdo, $scope, $customerId);
        $openingDue = round2($customer['opening_due'] ?? $customer['opening_outstanding'] ?? 0);

        if ($openingDue > 0 && $remaining > 0) {
            $payOpening = min($openingDue, $remaining);
            allocateToOpeningBalance($pdo, $scope, $paymentId, $customerId, $payOpening);
            $remaining = round2($remaining - $payOpening);
        }

        if ($paymentType === 3) {
            if ($remaining > 0.01) {
                throw new Exception('Payment amount cannot be greater than opening balance due.');
            }
            return;
        }
    }

    if ($paymentType === 2 && $remaining > 0) {
        $stmt = $pdo->prepare("
            SELECT id, due_amount
            FROM sales
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND customer_id = :customer_id
            AND status IN (1,2)
            AND due_amount > 0
            ORDER BY sales_date ASC, id ASC
            FOR UPDATE
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':customer_id' => $customerId
        ]);

        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sales as $sale) {
            if ($remaining <= 0) {
                break;
            }

            $due = round2($sale['due_amount'] ?? 0);
            if ($due <= 0) {
                continue;
            }

            $apply = min($due, $remaining);
            allocateToSale($pdo, $scope, $paymentId, $customerId, (int)$sale['id'], $apply);
            $remaining = round2($remaining - $apply);
        }

        if ($remaining > 0.01) {
            throw new Exception('Payment amount is greater than total customer due.');
        }
    }
}

function allocateToOpeningBalance(PDO $pdo, array $scope, $paymentId, $customerId, $amount)
{
    $amount = round2($amount);
    if ($amount <= 0) {
        return;
    }

    $ins = $pdo->prepare("
        INSERT INTO customer_payment_allocations
        (
            business_id, branch_id, payment_id, customer_id,
            allocation_type, sales_id, allocated_amount, status
        )
        VALUES
        (
            :business_id, :branch_id, :payment_id, :customer_id,
            1, NULL, :allocated_amount, 1
        )
    ");
    $ins->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId,
        ':customer_id' => $customerId,
        ':allocated_amount' => $amount
    ]);

    $upd = $pdo->prepare("
        UPDATE customers
        SET opening_paid = COALESCE(opening_paid, 0) + :paid_amount,
            opening_due = GREATEST(COALESCE(opening_balance, opening_outstanding, 0) - (COALESCE(opening_paid, 0) + :paid_amount_again), 0)
        WHERE id = :customer_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");
    $upd->execute([
        ':paid_amount' => $amount,
        ':paid_amount_again' => $amount,
        ':customer_id' => $customerId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function allocateToSale(PDO $pdo, array $scope, $paymentId, $customerId, $salesId, $amount)
{
    $amount = round2($amount);
    if ($amount <= 0) {
        return;
    }

    $sale = getSaleForUpdate($pdo, $scope, $salesId);
    if (!$sale) {
        throw new Exception('Sales document not found while allocating payment.');
    }

    $due = round2($sale['due_amount'] ?? 0);
    if ($amount - $due > 0.01) {
        throw new Exception('Payment allocation cannot be greater than bill due.');
    }

    $ins = $pdo->prepare("
        INSERT INTO customer_payment_allocations
        (
            business_id, branch_id, payment_id, customer_id,
            allocation_type, sales_id, allocated_amount, status
        )
        VALUES
        (
            :business_id, :branch_id, :payment_id, :customer_id,
            2, :sales_id, :allocated_amount, 1
        )
    ");
    $ins->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId,
        ':customer_id' => $customerId,
        ':sales_id' => $salesId,
        ':allocated_amount' => $amount
    ]);

    $newPaid = round2((float)($sale['paid_amount'] ?? 0) + $amount);
    $newDue = round2((float)($sale['grand_total'] ?? 0) - $newPaid);
    if ($newDue < 0) {
        $newDue = 0;
    }
    $status = $newPaid <= 0 ? 0 : ($newDue > 0 ? 1 : 2);

    $upd = $pdo->prepare("
        UPDATE sales
        SET paid_amount = :paid_amount,
            due_amount = :due_amount,
            payment_status = :payment_status
        WHERE id = :sales_id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");
    $upd->execute([
        ':paid_amount' => $newPaid,
        ':due_amount' => $newDue,
        ':payment_status' => $status,
        ':sales_id' => $salesId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function reversePaymentAllocations(PDO $pdo, array $scope, $paymentId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM customer_payment_allocations
        WHERE payment_id = :payment_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY id DESC
        FOR UPDATE
    ");
    $stmt->execute([
        ':payment_id' => $paymentId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allocations as $allocation) {
        $amount = round2($allocation['allocated_amount'] ?? 0);
        if ($amount <= 0) {
            continue;
        }

        if ((int)$allocation['allocation_type'] === 1) {
            $upd = $pdo->prepare("
                UPDATE customers
                SET opening_paid = GREATEST(COALESCE(opening_paid, 0) - :amount, 0),
                    opening_due = GREATEST(COALESCE(opening_balance, opening_outstanding, 0) - GREATEST(COALESCE(opening_paid, 0) - :amount_again, 0), 0)
                WHERE id = :customer_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $upd->execute([
                ':amount' => $amount,
                ':amount_again' => $amount,
                ':customer_id' => (int)$allocation['customer_id'],
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        } elseif ((int)$allocation['allocation_type'] === 2 && (int)$allocation['sales_id'] > 0) {
            $sale = getSaleForUpdate($pdo, $scope, (int)$allocation['sales_id']);
            if ($sale) {
                $newPaid = round2(max(0, (float)($sale['paid_amount'] ?? 0) - $amount));
                $newDue = round2((float)($sale['grand_total'] ?? 0) - $newPaid);
                if ($newDue < 0) {
                    $newDue = 0;
                }
                $status = $newPaid <= 0 ? 0 : ($newDue > 0 ? 1 : 2);

                $upd = $pdo->prepare("
                    UPDATE sales
                    SET paid_amount = :paid_amount,
                        due_amount = :due_amount,
                        payment_status = :payment_status
                    WHERE id = :sales_id
                    AND business_id = :business_id
                    AND branch_id = :branch_id
                ");
                $upd->execute([
                    ':paid_amount' => $newPaid,
                    ':due_amount' => $newDue,
                    ':payment_status' => $status,
                    ':sales_id' => (int)$allocation['sales_id'],
                    ':business_id' => $scope['business_id'],
                    ':branch_id' => $scope['branch_id']
                ]);
            }
        }

        $rev = $pdo->prepare("
            UPDATE customer_payment_allocations
            SET status = 2,
                reversed_at = NOW()
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $rev->execute([
            ':id' => (int)$allocation['id'],
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
    }
}


function saveSale(PDO $pdo)
{
    $data = readPayload();
    $documentType = (int)($data['document_type'] ?? 0);
    $mode = cleanInput($data['mode'] ?? 'new');
    $sourceSaleId = (int)($data['source_id'] ?? 0);
    $sourceType = (int)($data['source_type'] ?? 0);
    $targetType = (int)($data['target_type'] ?? 0);

    $scope = getScope();
    $userId = currentUserIdSafe();

    $saleId = (int)($data['id'] ?? 0);
    $isConvertMode = ($mode === 'convert');

    if (!isset(salesDocumentTypes()[$documentType])) {
        jsonResponse(false, 'Invalid document type.');
    }

    if ($isConvertMode) {
        /*
         * Robust generate/convert request handling.
         *
         * Front-end may sometimes send source_type as old source_document_type
         * or may miss source_type/target_type after cache/refresh.
         *
         * Source of truth is always current sales.document_type in DB.
         */
        if ($sourceSaleId <= 0 && $saleId > 0) {
            $sourceSaleId = $saleId;
        }

        if ($sourceSaleId <= 0) {
            jsonResponse(false, 'Invalid conversion request: source document missing.');
        }

        $sourceStmt = $pdo->prepare("
            SELECT id, document_type, converted_to_sale_id, converted_to_document_type, conversion_status
            FROM sales
            WHERE id = :id
              AND business_id = :business_id
              AND branch_id = :branch_id
              AND status != 3
            LIMIT 1
        ");
        $sourceStmt->execute([
            ':id' => $sourceSaleId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
        $sourceRowForGenerate = $sourceStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sourceRowForGenerate) {
            jsonResponse(false, 'Source document not found.');
        }

        $realSourceType = (int)($sourceRowForGenerate['document_type'] ?? 0);

        if ($realSourceType <= 0 || !isset(salesDocumentTypes()[$realSourceType])) {
            jsonResponse(false, 'Invalid source document type.');
        }

        /*
         * Force source_type to current DB document_type.
         * This prevents:
         * "Source document type mismatch. Please reload and try again."
         */
        $sourceType = $realSourceType;

        if ($targetType <= 0) {
            $targetType = $documentType;
        }

        if ($targetType <= 0 || !isset(salesDocumentTypes()[$targetType])) {
            jsonResponse(false, 'Invalid conversion request: target document missing.');
        }

        if ($targetType === $sourceType) {
            // Same selected document type means update the existing document, not generate.
            $documentType = $sourceType;
            $saleId = $sourceSaleId;
            $isConvertMode = false;
            $mode = 'edit';
        } else {
            // Convert / Generate must UPDATE the same sales row.
            $documentType = $targetType;
            $saleId = $sourceSaleId;

            if (!canGenerateSourceToTarget($sourceType, $targetType)) {
                jsonResponse(
                    false,
                    'You do not have permission to generate this document. Required permission: source document generate action. Check ' . generatePermissionTextForTarget($targetType) . '.'
                );
            }
        }
    }

    requireSalesEntryDataPermission();

    $customerId = (int)($data['customer_id'] ?? 0);
    $invoiceType = (int)($data['invoice_type'] ?? 1);
    $salesDate = cleanDateOrNull($data['sales_date'] ?? date('Y-m-d')) ?: date('Y-m-d');
    $validityDate = cleanDateOrNull($data['validity_date'] ?? '');
    $dueDate = cleanDateOrNull($data['due_date'] ?? '');
    $deliveryAddress = cleanInput($data['delivery_address'] ?? '');
    $shippingCharges = round2($data['shipping_charges'] ?? 0);
    $notes = cleanInput($data['notes'] ?? '');
    $terms = cleanInput($data['terms'] ?? '');
    $roundOff = round2($data['round_off'] ?? 0);
    $headerDiscountType = (int)($data['discount_type'] ?? 1);
    $headerDiscountValue = round2($data['discount_value'] ?? 0);
    $items = $data['items'] ?? [];
    $payments = $data['payments'] ?? [];

    if ($customerId <= 0) {
        jsonResponse(false, 'Select customer.');
    }

    if (!in_array($documentType, salesEntryCreatableDocumentTypes(), true)) {
        jsonResponse(false, 'Invalid document type.');
    }

    if (!in_array($invoiceType, [1,2], true)) {
        jsonResponse(false, 'Invalid invoice type.');
    }

    if (!is_array($items) || count($items) === 0) {
        jsonResponse(false, 'Add at least one product.');
    }

    if (!$isConvertMode && $saleId <= 0) {
        /*
         * Direct Sales Entry save must be controlled from the Sales Entry row:
         * Sales Entry action 3 + selected document action 32/33/34/35.
         */
        requireSalesPermission('add');
        requireSalesEntryDocumentCreatePermission($documentType);
    }

    $documentNeedsStockDeduction = in_array($documentType, [4,5], true);
    $shouldDeductStock = $documentNeedsStockDeduction;
    $saleStockDeductedValue = $documentNeedsStockDeduction ? 1 : 0;
    $carryForwardExistingStockDeduction = false;

    try {
        $pdo->beginTransaction();

        $oldSale = null;
        $oldCustomerId = 0;
        $oldDocumentType = 0;

        if ($saleId > 0) {
            $oldSale = getSaleForUpdate($pdo, $scope, $saleId);
            if (!$oldSale) {
                throw new Exception('Sale not found.');
            }

            $oldCustomerId = (int)$oldSale['customer_id'];
            $oldDocumentType = (int)$oldSale['document_type'];

            /*
             * Stock movement rule:
             * If a stock-deducted document is generated to another stock-deducted document
             * without changing product/batch/qty, keep the existing deduction.
             * This prevents duplicate OUT movement for Generate / Convert.
             */
            if (
                $isConvertMode
                && (int)($oldSale['stock_deducted'] ?? 0) === 1
                && $documentNeedsStockDeduction
                && saleSubmittedItemsMatchActiveStockItems($pdo, $scope, $saleId, $items)
            ) {
                $carryForwardExistingStockDeduction = true;
                $shouldDeductStock = false;
                $saleStockDeductedValue = 1;
            }

            if ($isConvertMode) {
                /*
                 * Do not fail if frontend sent stale source_type.
                 * Current DB document_type is the correct source type.
                 */
                if ($oldDocumentType !== $sourceType) {
                    $sourceType = $oldDocumentType;
                }

                if ((int)($oldSale['conversion_status'] ?? 0) === 1 && (int)($oldSale['converted_to_sale_id'] ?? 0) > 0) {
                    throw new Exception('This document is already generated.');
                }
            } else {
                // Existing normal edit keeps document type locked.
                $documentType = $oldDocumentType;
                if (!permissionAllowed(salesPermissionKeys(), 4) && !hasSalesDocumentPermission($documentType, 'edit')) {
                    jsonResponse(false, 'You do not have permission to edit this document.');
                }
            }

            if ((int)$oldSale['stock_deducted'] === 1 && !$carryForwardExistingStockDeduction) {
                requireSalesPermission('adjust');
                reverseSaleStock($pdo, $scope, $saleId);
            }

            // Reverse old individual sales page payments before saving new payment rows.
            markOldSaleRowsInactive($pdo, $scope, $saleId);
            markOldPaymentsInactive($pdo, $scope, $saleId);

            if ($isConvertMode) {
                // Same row update. Generate the new document number for the target type.
                $salesNo = reserveDocumentNumber($pdo, $scope, $documentType, $invoiceType, $saleId);

                // Release old register number as converted/closed, not reusable.
                $oldReg = $pdo->prepare("
                    UPDATE invoice_number_register
                    SET status = 3
                    WHERE business_id = :business_id
                    AND branch_id = :branch_id
                    AND sales_id = :sales_id
                    AND document_type = :old_document_type
                ");
                $oldReg->execute([
                    ':business_id' => $scope['business_id'],
                    ':branch_id' => $scope['branch_id'],
                    ':sales_id' => $saleId,
                    ':old_document_type' => $oldDocumentType
                ]);

                attachDocumentNumberToSale($pdo, $scope, $documentType, $invoiceType, $salesNo, $saleId);
            } else {
                $salesNo = $oldSale['sales_no'];
            }
        } else {
            $salesNo = reserveDocumentNumber($pdo, $scope, $documentType, $invoiceType, 0);
        }

        $computedItems = computeItems($pdo, $scope, $items, $invoiceType, $shouldDeductStock);

        $subTotal = array_sum(array_column($computedItems, 'gross_amount'));
        $itemDiscount = array_sum(array_column($computedItems, 'discount_amount'));
        $itemTaxable = array_sum(array_column($computedItems, 'taxable_amount'));
        $cgstAmount = array_sum(array_column($computedItems, 'cgst_amount'));
        $sgstAmount = array_sum(array_column($computedItems, 'sgst_amount'));
        $igstAmount = array_sum(array_column($computedItems, 'igst_amount'));
        $taxAmount = $cgstAmount + $sgstAmount + $igstAmount;

        $headerDiscountAmount = 0.0;
        $beforeHeaderDiscountTotal = round2($itemTaxable + $taxAmount);
        if ($headerDiscountValue > 0) {
            /* Match sales entry screen: header discount is applied on visible line total. */
            $headerDiscountAmount = $headerDiscountType === 1
                ? round2($beforeHeaderDiscountTotal * $headerDiscountValue / 100)
                : $headerDiscountValue;
        }

        if ($headerDiscountAmount > $beforeHeaderDiscountTotal) {
            $headerDiscountAmount = $beforeHeaderDiscountTotal;
        }

        $grandTotal = round2($beforeHeaderDiscountTotal - $headerDiscountAmount + $roundOff + $shippingCharges);
        if ($grandTotal < 0) {
            $grandTotal = 0;
        }

        $computedPayments = computePayments($payments, $grandTotal);
        $paidAmount = array_sum(array_column($computedPayments, 'payment_amount'));
        $dueAmount = round2($grandTotal - $paidAmount);
        $paymentStatus = $paidAmount <= 0 ? 0 : ($dueAmount > 0 ? 1 : 2);
        $finalStatus = in_array($documentType, [4,5], true) ? 2 : 1;

        if ($saleId > 0) {
            $stmt = $pdo->prepare("
                UPDATE sales SET
                    customer_id = :customer_id,
                    document_type = :document_type,
                    invoice_type = :invoice_type,
                    sales_no = :sales_no,
                    sales_date = :sales_date,
                    validity_date = :validity_date,
                    sub_total = :sub_total,
                    discount_type = :discount_type,
                    discount_value = :discount_value,
                    discount_amount = :discount_amount,
                    taxable_amount = :taxable_amount,
                    cgst_amount = :cgst_amount,
                    sgst_amount = :sgst_amount,
                    igst_amount = :igst_amount,
                    tax_amount = :tax_amount,
                    round_off = :round_off,
                    grand_total = :grand_total,
                    paid_amount = :paid_amount,
                    due_amount = :due_amount,
                    payment_status = :payment_status,
                    stock_deducted = :stock_deducted,
                    notes = :notes,
                    terms = :terms,
                    status = :status,
                    updated_by = :updated_by
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $stmt->execute([
                ':customer_id' => $customerId,
                ':document_type' => $documentType,
                ':invoice_type' => $invoiceType,
                ':sales_no' => $salesNo,
                ':sales_date' => $salesDate,
                ':validity_date' => $validityDate,
                ':sub_total' => round2($subTotal),
                ':discount_type' => $headerDiscountType,
                ':discount_value' => $headerDiscountValue,
                ':discount_amount' => round2($headerDiscountAmount + $itemDiscount),
                ':taxable_amount' => round2($itemTaxable),
                ':cgst_amount' => round2($cgstAmount),
                ':sgst_amount' => round2($sgstAmount),
                ':igst_amount' => round2($igstAmount),
                ':tax_amount' => round2($taxAmount),
                ':round_off' => $roundOff,
                ':grand_total' => $grandTotal,
                ':paid_amount' => round2($paidAmount),
                ':due_amount' => $dueAmount,
                ':payment_status' => $paymentStatus,
                ':stock_deducted' => $saleStockDeductedValue,
                ':notes' => $notes,
                ':terms' => $terms,
                ':status' => $finalStatus,
                ':updated_by' => $userId,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            if ($isConvertMode) {
                updateIfColumnExists($pdo, $scope, $saleId, [
                    'source_sale_id' => $sourceSaleId,
                    'source_document_type' => $sourceType,
                    'converted_to_sale_id' => null,
                    'converted_to_document_type' => $documentType,
                    'conversion_status' => 1
                ]);
            }
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO sales
                (
                    business_id, branch_id, customer_id,
                    document_type, invoice_type, sales_no, sales_date, validity_date,
                    sub_total, discount_type, discount_value, discount_amount,
                    taxable_amount, cgst_amount, sgst_amount, igst_amount, tax_amount,
                    round_off, grand_total, paid_amount, due_amount, payment_status,
                    stock_deducted, notes, terms, status, created_by
                )
                VALUES
                (
                    :business_id, :branch_id, :customer_id,
                    :document_type, :invoice_type, :sales_no, :sales_date, :validity_date,
                    :sub_total, :discount_type, :discount_value, :discount_amount,
                    :taxable_amount, :cgst_amount, :sgst_amount, :igst_amount, :tax_amount,
                    :round_off, :grand_total, :paid_amount, :due_amount, :payment_status,
                    :stock_deducted, :notes, :terms, :status, :created_by
                )
            ");
            $stmt->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':customer_id' => $customerId,
                ':document_type' => $documentType,
                ':invoice_type' => $invoiceType,
                ':sales_no' => $salesNo,
                ':sales_date' => $salesDate,
                ':validity_date' => $validityDate,
                ':sub_total' => round2($subTotal),
                ':discount_type' => $headerDiscountType,
                ':discount_value' => $headerDiscountValue,
                ':discount_amount' => round2($headerDiscountAmount + $itemDiscount),
                ':taxable_amount' => round2($itemTaxable),
                ':cgst_amount' => round2($cgstAmount),
                ':sgst_amount' => round2($sgstAmount),
                ':igst_amount' => round2($igstAmount),
                ':tax_amount' => round2($taxAmount),
                ':round_off' => $roundOff,
                ':grand_total' => $grandTotal,
                ':paid_amount' => round2($paidAmount),
                ':due_amount' => $dueAmount,
                ':payment_status' => $paymentStatus,
                ':stock_deducted' => $saleStockDeductedValue,
                ':notes' => $notes,
                ':terms' => $terms,
                ':status' => $finalStatus,
                ':created_by' => $userId
            ]);
            $saleId = (int)$pdo->lastInsertId();

            attachDocumentNumberToSale($pdo, $scope, $documentType, $invoiceType, $salesNo, $saleId);
        }

        if (columnExists($pdo, 'sales', 'due_date')) {
            $dueStmt = $pdo->prepare("
                UPDATE sales
                SET due_date = :due_date
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $dueStmt->execute([
                ':due_date' => $dueDate,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }

        if (columnExists($pdo, 'sales', 'delivery_address')) {
            $deliveryStmt = $pdo->prepare("
                UPDATE sales
                SET delivery_address = :delivery_address
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $deliveryStmt->execute([
                ':delivery_address' => $deliveryAddress,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }

        if (columnExists($pdo, 'sales', 'shipping_charges')) {
            $shippingStmt = $pdo->prepare("
                UPDATE sales
                SET shipping_charges = :shipping_charges
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $shippingStmt->execute([
                ':shipping_charges' => $shippingCharges,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }

        foreach ($computedItems as $item) {
            $salesItemId = insertSaleItem($pdo, $scope, $saleId, $item);

            if ($shouldDeductStock) {
                deductBatchStock($pdo, $scope, (int)$item['purchase_item_id'], (float)$item['qty']);

                $afterQty = stockProductBalance($pdo, $scope, (int)$item['product_id']);
                $qty = (float)$item['qty'];

                addStockMovement($pdo, $scope, [
                    'product_id' => (int)$item['product_id'],
                    'purchase_id' => !empty($item['purchase_id']) ? (int)$item['purchase_id'] : null,
                    'purchase_item_id' => !empty($item['purchase_item_id']) ? (int)$item['purchase_item_id'] : null,
                    'sales_id' => (int)$saleId,
                    'sales_item_id' => (int)$salesItemId,
                    'movement_date' => $salesDate ?: date('Y-m-d'),
                    'movement_type' => 'OUT',
                    'source_type' => 'SALES',
                    'source_no' => $salesNo,
                    'batch_no' => $item['purchase_batch_no'] ?? null,
                    'product_code' => $item['product_code'] ?? null,
                    'product_name' => $item['product_name'] ?? null,
                    'qty' => $qty,
                    'rate' => (float)($item['selling_rate'] ?? 0),
                    'amount' => round((float)($item['line_total'] ?? 0), 2),
                    'before_qty' => round($afterQty + $qty, 4),
                    'after_qty' => $afterQty,
                    'remarks' => 'Sales stock OUT'
                ]);
            }
        }

        insertSalePagePaymentReceipt($pdo, $scope, $saleId, $customerId, $computedPayments, $userId);

        updateCustomerOutstanding($pdo, $scope, $customerId);
        if ($oldCustomerId > 0 && $oldCustomerId !== $customerId) {
            updateCustomerOutstanding($pdo, $scope, $oldCustomerId);
        }

        $logAction = $isConvertMode ? 'CONVERT_UPDATE' : ($saleId > 0 ? 'SAVE' : 'CREATE');
        $logText = $isConvertMode
            ? ('Sales ' . $salesNo . ' converted by updating same document.')
            : ('Sales ' . $salesNo . ' saved.');

        addActivityLog($pdo, $scope, $userId, 'sales', $logAction, $logText, $saleId, $salesNo);

        $pdo->commit();

        $editUrl = BASE_URL . 'pages/sales.php?id=' . (int)$saleId . '&mode=edit';
        $allSalesListUrl = BASE_URL . 'pages/all-sales-list.php';

        jsonOk($isConvertMode ? 'Document updated successfully. No duplicate created.' : 'Sale saved successfully.', [
            'id' => $saleId,
            'sales_no' => $salesNo,
            'document_type' => $documentType,
            'grand_total' => $grandTotal,
            'paid_amount' => round2($paidAmount),
            'due_amount' => $dueAmount,
            'edit_url' => $editUrl,
            'redirect_url' => $allSalesListUrl,
            'all_sales_list_url' => $allSalesListUrl,
            'mode' => 'edit',
            'sale' => [
                'id' => $saleId,
                'sales_no' => $salesNo,
                'document_type' => $documentType,
                'conversion_status' => $isConvertMode ? 1 : 0,
                'converted_to_sale_id' => 0
            ],
            'clear_session' => 1
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage());
    }
}

function getSaleForUpdate(PDO $pdo, array $scope, $saleId)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM sales
        WHERE id = :id AND business_id = :business_id AND branch_id = :branch_id
        FOR UPDATE
    ");
    $stmt->execute([
        ':id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function markOldSaleRowsInactive(PDO $pdo, array $scope, $saleId)
{
    $stmt = $pdo->prepare("
        UPDATE sales_items
        SET status = 2
        WHERE sales_id = :sales_id AND business_id = :business_id AND branch_id = :branch_id AND status = 1
    ");
    $stmt->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}


function nextCustomerPaymentNo(PDO $pdo, array $scope)
{
    $prefix = 'PAY';

    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING(payment_no, LENGTH(:prefix_like) + 2) AS UNSIGNED)), 0) + 1
            FROM customer_payments
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND payment_no LIKE :like_pattern
            FOR UPDATE
        ");
        $stmt->execute([
            ':prefix_like' => $prefix,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':like_pattern' => $prefix . '-%'
        ]);
        $nextNo = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $nextNo = 1;
    }

    if ($nextNo <= 0) {
        $nextNo = 1;
    }

    return $prefix . '-' . str_pad((string)$nextNo, 5, '0', STR_PAD_LEFT);
}

function insertCustomerPaymentForSale(PDO $pdo, array $scope, $saleId, $customerId, array $payment, $userId)
{
    $paymentNo = nextCustomerPaymentNo($pdo, $scope);

    $stmt = $pdo->prepare("
        INSERT INTO customer_payments
        (
            business_id, branch_id, payment_no, customer_id,
            payment_type, sales_id, payment_date, payment_mode_id,
            amount, reference_no, notes, status, created_by
        )
        VALUES
        (
            :business_id, :branch_id, :payment_no, :customer_id,
            1, :sales_id, :payment_date, :payment_mode_id,
            :amount, :reference_no, :notes, 1, :created_by
        )
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_no' => $paymentNo,
        ':customer_id' => $customerId,
        ':sales_id' => $saleId,
        ':payment_date' => $payment['payment_date'],
        ':payment_mode_id' => $payment['payment_mode_id'],
        ':amount' => $payment['payment_amount'],
        ':reference_no' => $payment['reference_no'],
        ':notes' => $payment['notes'],
        ':created_by' => $userId > 0 ? $userId : null
    ]);

    $paymentId = (int)$pdo->lastInsertId();

    $alloc = $pdo->prepare("
        INSERT INTO customer_payment_allocations
        (
            business_id, branch_id, payment_id, customer_id,
            allocation_type, sales_id, allocated_amount, status
        )
        VALUES
        (
            :business_id, :branch_id, :payment_id, :customer_id,
            2, :sales_id, :allocated_amount, 1
        )
    ");
    $alloc->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId,
        ':customer_id' => $customerId,
        ':sales_id' => $saleId,
        ':allocated_amount' => $payment['payment_amount']
    ]);

    return $paymentId;
}

function reverseCustomerPaymentsForSale(PDO $pdo, array $scope, $saleId, $userId = 0, $reason = 'Sales edited or cancelled')
{
    if (!tableExists($pdo, 'customer_payments') || !tableExists($pdo, 'customer_payment_allocations')) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM customer_payments
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND sales_id = :sales_id
        AND payment_type = 1
        AND status = 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':sales_id' => $saleId
    ]);

    $paymentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$paymentIds) {
        return;
    }

    $placeholders = [];
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    foreach ($paymentIds as $index => $paymentId) {
        $key = ':payment_id_' . $index;
        $placeholders[] = $key;
        $params[$key] = $paymentId;
    }

    $allocationSql = "
        UPDATE customer_payment_allocations
        SET status = 2,
            reversed_at = NOW()
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND payment_id IN (" . implode(',', $placeholders) . ")
        AND status = 1
    ";
    $alloc = $pdo->prepare($allocationSql);
    $alloc->execute($params);

    $paymentParams = $params;
    $paymentParams[':cancelled_by'] = $userId > 0 ? $userId : null;
    $paymentParams[':cancel_reason'] = $reason;

    if (tableExists($pdo, 'customer_payment_splits')) {
        $splitSql = "
            UPDATE customer_payment_splits
            SET status = 2
            WHERE business_id = :business_id
            AND branch_id = :branch_id
            AND payment_id IN (" . implode(',', $placeholders) . ")
            AND status = 1
        ";
        $split = $pdo->prepare($splitSql);
        $split->execute($params);
    }

    $paymentSql = "
        UPDATE customer_payments
        SET status = 2,
            cancelled_by = :cancelled_by,
            cancelled_at = NOW(),
            cancel_reason = :cancel_reason
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND id IN (" . implode(',', $placeholders) . ")
        AND status = 1
    ";
    $pay = $pdo->prepare($paymentSql);
    $pay->execute($paymentParams);
}

function tableExists(PDO $pdo, $tableName)
{
    static $cache = [];

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :table_name
    ");
    $stmt->execute([':table_name' => $tableName]);

    $cache[$tableName] = (int)$stmt->fetchColumn() > 0;
    return $cache[$tableName];
}


function markOldPaymentsInactive(PDO $pdo, array $scope, $saleId)
{
    reverseCustomerPaymentsForSale($pdo, $scope, $saleId, currentUserIdSafe(), 'Sales document edited / regenerated');

    $stmt = $pdo->prepare("
        UPDATE sales_payments
        SET status = 2
        WHERE sales_id = :sales_id AND business_id = :business_id AND branch_id = :branch_id AND status = 1
    ");
    $stmt->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}


function saleSubmittedItemsMatchActiveStockItems(PDO $pdo, array $scope, $saleId, array $submittedItems)
{
    $stmt = $pdo->prepare(""
        . "SELECT product_id, purchase_item_id, qty "
        . "FROM sales_items "
        . "WHERE sales_id = :sales_id "
        . "AND business_id = :business_id "
        . "AND branch_id = :branch_id "
        . "AND status = 1 "
        . "ORDER BY id ASC"
    );
    $stmt->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $oldItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($oldItems) !== count($submittedItems)) {
        return false;
    }

    $oldMap = [];
    foreach ($oldItems as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $purchaseItemId = (int)($item['purchase_item_id'] ?? 0);
        $qty = round((float)($item['qty'] ?? 0), 4);
        if ($productId <= 0 || $purchaseItemId <= 0 || $qty <= 0) {
            return false;
        }
        $key = $productId . ':' . $purchaseItemId;
        if (!isset($oldMap[$key])) {
            $oldMap[$key] = 0.0;
        }
        $oldMap[$key] = round($oldMap[$key] + $qty, 4);
    }

    $newMap = [];
    foreach ($submittedItems as $row) {
        $productId = (int)($row['product_id'] ?? 0);
        $purchaseItemId = (int)($row['purchase_item_id'] ?? 0);
        if ($productId <= 0 || $purchaseItemId <= 0) {
            return false;
        }

        $qty = toFloat($row['qty'] ?? 0);
        if ($qty <= 0) {
            $qty = toFloat($row['entered_total_qty'] ?? 0);
        }
        if ($qty <= 0) {
            $unitQty = toFloat($row['unit_qty'] ?? 0);
            $qtyPerUnit = toFloat($row['qty_per_unit'] ?? 1);
            if ($qtyPerUnit <= 0) {
                $qtyPerUnit = 1;
            }
            $looseQty = toFloat($row['loose_qty'] ?? 0);
            $qty = round($unitQty * $qtyPerUnit + $looseQty, 4);
        }

        $qty = round((float)$qty, 4);
        if ($qty <= 0) {
            return false;
        }

        $key = $productId . ':' . $purchaseItemId;
        if (!isset($newMap[$key])) {
            $newMap[$key] = 0.0;
        }
        $newMap[$key] = round($newMap[$key] + $qty, 4);
    }

    if (count($oldMap) !== count($newMap)) {
        return false;
    }

    foreach ($oldMap as $key => $oldQty) {
        if (!array_key_exists($key, $newMap)) {
            return false;
        }
        if (abs((float)$oldQty - (float)$newMap[$key]) > 0.0001) {
            return false;
        }
    }

    return true;
}

function computeItems(PDO $pdo, array $scope, array $items, $invoiceType, $shouldDeductStock)
{
    $computed = [];

    foreach ($items as $index => $row) {
        $productId = (int)($row['product_id'] ?? 0);
        $purchaseItemId = (int)($row['purchase_item_id'] ?? 0);

        if ($productId <= 0 || $purchaseItemId <= 0) {
            throw new Exception('Invalid product or batch in row ' . ($index + 1));
        }

        $unitQty = toFloat($row['unit_qty'] ?? 0);
        $qtyPerUnit = toFloat($row['qty_per_unit'] ?? 1);
        if ($qtyPerUnit <= 0) {
            $qtyPerUnit = 1;
        }
        $looseQty = toFloat($row['loose_qty'] ?? 0);

        /*
         * IMPORTANT FIX:
         * Frontend already calculates total qty and stores it in item.qty.
         * Do not multiply again while saving, otherwise list/header amount becomes wrong.
         * Use submitted qty first; calculate from Unit x Qty/Unit + Loose only as fallback.
         */
        $submittedQty = toFloat($row['qty'] ?? 0);
        if ($submittedQty <= 0) {
            $submittedQty = toFloat($row['entered_total_qty'] ?? 0);
        }

        $calculatedQty = round($unitQty * $qtyPerUnit + $looseQty, 4);
        $qty = $submittedQty > 0 ? round($submittedQty, 4) : $calculatedQty;

        if ($qty <= 0) {
            throw new Exception('Total quantity must be greater than zero in row ' . ($index + 1));
        }

        $batch = getBatchForSale($pdo, $scope, $purchaseItemId, true);

        if (!$batch || (int)$batch['product_id'] !== $productId) {
            throw new Exception('Selected batch does not match product in row ' . ($index + 1));
        }

        if ($shouldDeductStock && (float)$batch['available_qty'] < $qty) {
            throw new Exception('Insufficient stock for ' . $batch['product_name'] . '. Available: ' . $batch['available_qty']);
        }

        $priceType = (int)($row['price_type'] ?? 1);
        if (!in_array($priceType, [1, 2], true)) {
            $priceType = 1;
        }

        $submittedRate = toFloat($row['selling_rate'] ?? 0);

        /*
         * IMPORTANT RATE FIX:
         * The POS screen calculates sale rate from the selected purchase batch cost.
         * Example: purchase_price 127.22 + Retail markup 20% = 152.66 per piece.
         * Then 5 qty = 763.30 + GST 5% = 801.46.
         *
         * Older server code used products.retail_price / wholesale_price as base and
         * applied markup again. If retail_price already had the row total value,
         * the server stored 801.46 as ONE PIECE rate and again multiplied by 5.
         *
         * So for Retail / Wholesale, always use purchase_items.purchase_price as
         * base rate and then apply the selected markup. Use submitted selling_rate
         * as a fallback when markup/base is missing.
         */
        $baseRate = toFloat($row['base_rate'] ?? 0);
        if ($baseRate <= 0) {
            $baseRate = toFloat($batch['purchase_price'] ?? 0);
        }
        if ($baseRate <= 0) {
            $baseRate = toFloat($batch['retail_price'] ?? 0);
        }
        if ($baseRate <= 0 && $submittedRate > 0) {
            $baseRate = $submittedRate;
        }

        $markupType = (int)($row['markup_type'] ?? 1);
        if (!in_array($markupType, [1, 2], true)) {
            $markupType = 1;
        }
        $markupValue = toFloat($row['markup_value'] ?? 0);

        $sellingRate = $baseRate;

        if ($markupValue > 0) {
            if ($markupType === 1) {
                $sellingRate += ($baseRate * $markupValue / 100);
            } else {
                $sellingRate += $markupValue;
            }
        } elseif ($submittedRate > 0) {
            // Fallback for edited rows with no markup saved.
            $sellingRate = $submittedRate;
        }

        $sellingRate = round2($sellingRate);
        $grossAmount = round2($qty * $sellingRate);

        $discountType = (int)($row['discount_type'] ?? 1);
        $discountValue = toFloat($row['discount_value'] ?? 0);
        $discountAmount = 0.0;

        if ($discountValue > 0) {
            $discountAmount = $discountType === 1
                ? round2($grossAmount * $discountValue / 100)
                : round2($discountValue);
        }

        if ($discountAmount > $grossAmount) {
            $discountAmount = $grossAmount;
        }

        $taxableAmount = round2($grossAmount - $discountAmount);
        $gstPercentage = $invoiceType === 1 ? toFloat($row['gst_percentage'] ?? (($batch['cgst_percentage'] + $batch['sgst_percentage'] + $batch['igst_percentage']))) : 0.0;

        $cgstPercentage = 0.0;
        $sgstPercentage = 0.0;
        $igstPercentage = 0.0;
        $cgstAmount = 0.0;
        $sgstAmount = 0.0;
        $igstAmount = 0.0;
        $gstAmount = 0.0;

        if ($invoiceType === 1 && $gstPercentage > 0) {
            $cgstPercentage = toFloat($batch['cgst_percentage'] ?? 0);
            $sgstPercentage = toFloat($batch['sgst_percentage'] ?? 0);
            $igstPercentage = toFloat($batch['igst_percentage'] ?? 0);

            if ($igstPercentage > 0) {
                $igstAmount = round2($taxableAmount * $igstPercentage / 100);
            } else {
                if ($cgstPercentage <= 0 && $sgstPercentage <= 0) {
                    $cgstPercentage = round($gstPercentage / 2, 2);
                    $sgstPercentage = round($gstPercentage / 2, 2);
                }
                $cgstAmount = round2($taxableAmount * $cgstPercentage / 100);
                $sgstAmount = round2($taxableAmount * $sgstPercentage / 100);
            }

            $gstAmount = round2($cgstAmount + $sgstAmount + $igstAmount);
        }

        $lineTotal = round2($taxableAmount + $gstAmount);

        $computed[] = [
            'product_id' => $productId,
            'purchase_id' => (int)$batch['purchase_id'],
            'purchase_item_id' => $purchaseItemId,
            'purchase_batch_no' => $batch['batch_no'],
            'purchase_bill_no' => $batch['bill_no'],
            'purchase_date' => $batch['purchase_date'],
            'purchase_price' => round2($batch['purchase_price']),
            'product_code' => $batch['product_code'],
            'product_name' => $batch['product_name'],
            'hsn_id' => $batch['hsn_id'] > 0 ? (int)$batch['hsn_id'] : null,
            'hsn_code' => $batch['hsn_code'],
            'unit_qty' => $unitQty,
            'qty_per_unit' => $qtyPerUnit,
            'loose_qty' => $looseQty,
            'qty' => $qty,
            'price_type' => $priceType,
            'base_rate' => round2($baseRate),
            'markup_type' => $markupType,
            'markup_value' => $markupValue,
            'selling_rate' => $sellingRate,
            'gross_amount' => $grossAmount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'taxable_amount' => $taxableAmount,
            'taxable_per_piece' => $qty > 0 ? round($taxableAmount / $qty, 4) : 0,
            'gst_percentage' => $gstPercentage,
            'cgst_percentage' => $cgstPercentage,
            'sgst_percentage' => $sgstPercentage,
            'igst_percentage' => $igstPercentage,
            'cgst_amount' => $cgstAmount,
            'sgst_amount' => $sgstAmount,
            'igst_amount' => $igstAmount,
            'gst_amount' => $gstAmount,
            'net_per_piece' => $qty > 0 ? round($lineTotal / $qty, 4) : 0,
            'line_total' => $lineTotal,
            'expiry_date' => $batch['expiry_date']
        ];
    }

    return $computed;
}

function getBatchForSale(PDO $pdo, array $scope, $purchaseItemId, $lock = false)
{
    $sql = "
        SELECT 
            pi.*,
            p.batch_no,
            p.bill_no,
            p.purchase_date,
            pr.retail_price,
            pr.wholesale_price,
            pr.retail_markup_type,
            pr.retail_markup_value,
            h.hsn_code AS product_hsn_code,
            h.cgst_percentage AS product_cgst_percentage,
            h.sgst_percentage AS product_sgst_percentage,
            h.igst_percentage AS product_igst_percentage
        FROM purchase_items pi
        INNER JOIN purchases p ON p.id = pi.purchase_id
        INNER JOIN products pr ON pr.id = pi.product_id
        LEFT JOIN hsn_codes h ON h.id = pr.hsn_id
        WHERE pi.id = :purchase_item_id
        AND pi.business_id = :business_id
        AND pi.branch_id = :branch_id
        AND pi.status = 1
        " . ($lock ? "FOR UPDATE" : "");

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':purchase_item_id' => $purchaseItemId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch) {
        if (empty($batch['hsn_code'])) {
            $batch['hsn_code'] = $batch['product_hsn_code'] ?? '';
        }
        if ((float)$batch['cgst_percentage'] <= 0) {
            $batch['cgst_percentage'] = $batch['product_cgst_percentage'] ?? 0;
        }
        if ((float)$batch['sgst_percentage'] <= 0) {
            $batch['sgst_percentage'] = $batch['product_sgst_percentage'] ?? 0;
        }
        if ((float)$batch['igst_percentage'] <= 0) {
            $batch['igst_percentage'] = $batch['product_igst_percentage'] ?? 0;
        }
    }

    return $batch;
}

function insertSaleItem(PDO $pdo, array $scope, $saleId, array $item)
{
    $stmt = $pdo->prepare("
        INSERT INTO sales_items
        (
            business_id, branch_id, sales_id,
            product_id, purchase_id, purchase_item_id,
            purchase_batch_no, purchase_bill_no, purchase_date, purchase_price,
            product_code, product_name, hsn_id, hsn_code,
            unit_qty, qty_per_unit, loose_qty, qty,
            price_type, base_rate, markup_type, markup_value, selling_rate,
            gross_amount, discount_type, discount_value, discount_amount,
            taxable_amount, taxable_per_piece,
            gst_percentage, cgst_percentage, sgst_percentage, igst_percentage,
            cgst_amount, sgst_amount, igst_amount, gst_amount,
            net_per_piece, line_total, expiry_date, status
        )
        VALUES
        (
            :business_id, :branch_id, :sales_id,
            :product_id, :purchase_id, :purchase_item_id,
            :purchase_batch_no, :purchase_bill_no, :purchase_date, :purchase_price,
            :product_code, :product_name, :hsn_id, :hsn_code,
            :unit_qty, :qty_per_unit, :loose_qty, :qty,
            :price_type, :base_rate, :markup_type, :markup_value, :selling_rate,
            :gross_amount, :discount_type, :discount_value, :discount_amount,
            :taxable_amount, :taxable_per_piece,
            :gst_percentage, :cgst_percentage, :sgst_percentage, :igst_percentage,
            :cgst_amount, :sgst_amount, :igst_amount, :gst_amount,
            :net_per_piece, :line_total, :expiry_date, 1
        )
    ");

    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':sales_id' => $saleId,
        ':product_id' => $item['product_id'],
        ':purchase_id' => $item['purchase_id'],
        ':purchase_item_id' => $item['purchase_item_id'],
        ':purchase_batch_no' => $item['purchase_batch_no'],
        ':purchase_bill_no' => $item['purchase_bill_no'],
        ':purchase_date' => $item['purchase_date'],
        ':purchase_price' => $item['purchase_price'],
        ':product_code' => $item['product_code'],
        ':product_name' => $item['product_name'],
        ':hsn_id' => $item['hsn_id'],
        ':hsn_code' => $item['hsn_code'],
        ':unit_qty' => $item['unit_qty'],
        ':qty_per_unit' => $item['qty_per_unit'],
        ':loose_qty' => $item['loose_qty'],
        ':qty' => $item['qty'],
        ':price_type' => $item['price_type'],
        ':base_rate' => $item['base_rate'],
        ':markup_type' => $item['markup_type'],
        ':markup_value' => $item['markup_value'],
        ':selling_rate' => $item['selling_rate'],
        ':gross_amount' => $item['gross_amount'],
        ':discount_type' => $item['discount_type'],
        ':discount_value' => $item['discount_value'],
        ':discount_amount' => $item['discount_amount'],
        ':taxable_amount' => $item['taxable_amount'],
        ':taxable_per_piece' => $item['taxable_per_piece'],
        ':gst_percentage' => $item['gst_percentage'],
        ':cgst_percentage' => $item['cgst_percentage'],
        ':sgst_percentage' => $item['sgst_percentage'],
        ':igst_percentage' => $item['igst_percentage'],
        ':cgst_amount' => $item['cgst_amount'],
        ':sgst_amount' => $item['sgst_amount'],
        ':igst_amount' => $item['igst_amount'],
        ':gst_amount' => $item['gst_amount'],
        ':net_per_piece' => $item['net_per_piece'],
        ':line_total' => $item['line_total'],
        ':expiry_date' => $item['expiry_date']
    ]);

    return (int)$pdo->lastInsertId();
}

function deductBatchStock(PDO $pdo, array $scope, $purchaseItemId, $qty)
{
    $stmt = $pdo->prepare("
        UPDATE purchase_items
        SET 
            sold_qty = sold_qty + :sold_qty,
            available_qty = available_qty - :available_qty
        WHERE id = :purchase_item_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND available_qty >= :qty_check
    ");
    $stmt->execute([
        ':sold_qty' => $qty,
        ':available_qty' => $qty,
        ':purchase_item_id' => $purchaseItemId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':qty_check' => $qty
    ]);

    if ($stmt->rowCount() <= 0) {
        throw new Exception('Stock deduction failed. Please check available quantity.');
    }
}

function reverseSaleStock(PDO $pdo, array $scope, $saleId)
{
    $stmt = $pdo->prepare("
        SELECT si.*, s.sales_no, s.sales_date
        FROM sales_items si
        INNER JOIN sales s ON s.id = si.sales_id
        WHERE si.sales_id = :sales_id
        AND si.business_id = :business_id
        AND si.branch_id = :branch_id
        AND si.status = 1
        AND si.purchase_item_id IS NOT NULL
    ");
    $stmt->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $qty = (float)$item['qty'];
        if ($qty <= 0) {
            continue;
        }

        $update = $pdo->prepare("
            UPDATE purchase_items
            SET 
                sold_qty = GREATEST(sold_qty - :sold_qty, 0),
                available_qty = available_qty + :available_qty
            WHERE id = :purchase_item_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $update->execute([
            ':sold_qty' => $qty,
            ':available_qty' => $qty,
            ':purchase_item_id' => (int)$item['purchase_item_id'],
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $afterQty = stockProductBalance($pdo, $scope, (int)$item['product_id']);

        addStockMovement($pdo, $scope, [
            'product_id' => (int)$item['product_id'],
            'purchase_id' => !empty($item['purchase_id']) ? (int)$item['purchase_id'] : null,
            'purchase_item_id' => !empty($item['purchase_item_id']) ? (int)$item['purchase_item_id'] : null,
            'sales_id' => (int)$saleId,
            'sales_item_id' => (int)$item['id'],
            'movement_date' => date('Y-m-d'),
            'movement_type' => 'IN',
            'source_type' => 'SALE_REVERSE',
            'source_no' => $item['sales_no'] ?? ('SALE-' . $saleId),
            'batch_no' => $item['purchase_batch_no'] ?? null,
            'product_code' => $item['product_code'] ?? null,
            'product_name' => $item['product_name'] ?? null,
            'qty' => $qty,
            'rate' => (float)($item['selling_rate'] ?? 0),
            'amount' => round((float)($item['line_total'] ?? 0), 2),
            'before_qty' => round($afterQty - $qty, 4),
            'after_qty' => $afterQty,
            'remarks' => 'Sales reverse/cancel stock IN'
        ]);
    }

    $flag = $pdo->prepare("
        UPDATE sales
        SET stock_deducted = 0
        WHERE id = :sales_id AND business_id = :business_id AND branch_id = :branch_id
    ");
    $flag->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);
}

function computePayments(array $payments, $grandTotal)
{
    $computed = [];

    foreach ($payments as $row) {
        $modeId = (int)($row['payment_mode_id'] ?? 0);
        $amount = round2($row['payment_amount'] ?? 0);

        if ($modeId <= 0 || $amount <= 0) {
            continue;
        }

        $computed[] = [
            'payment_mode_id' => $modeId,
            'payment_date' => cleanDateOrNull($row['payment_date'] ?? date('Y-m-d')) ?: date('Y-m-d'),
            'payment_amount' => $amount,
            'reference_no' => cleanInput($row['reference_no'] ?? ''),
            'notes' => cleanInput($row['notes'] ?? '')
        ];
    }

    $paid = array_sum(array_column($computed, 'payment_amount'));
    if ($paid - $grandTotal > 0.01) {
        throw new Exception('Paid amount cannot be greater than grand total.');
    }

    return $computed;
}


function insertSalePagePaymentReceipt(PDO $pdo, array $scope, $saleId, $customerId, array $payments, $userId)
{
    if (!$payments) {
        return;
    }

    // Keep old sales_payments rows for existing screens / get_sale compatibility.
    foreach ($payments as $payment) {
        insertLegacySalePayment($pdo, $scope, $saleId, $customerId, $payment, $userId);
    }

    if (!tableExists($pdo, 'customer_payments') || !tableExists($pdo, 'customer_payment_allocations')) {
        return;
    }

    $totalAmount = round2(array_sum(array_column($payments, 'payment_amount')));
    if ($totalAmount <= 0) {
        return;
    }

    $paymentNo = nextCustomerPaymentNo($pdo, $scope);
    $firstPayment = $payments[0];

    $stmt = $pdo->prepare("
        INSERT INTO customer_payments
        (
            business_id, branch_id, payment_no, customer_id,
            payment_type, sales_id, payment_date, payment_mode_id,
            amount, reference_no, notes, status, created_by
        )
        VALUES
        (
            :business_id, :branch_id, :payment_no, :customer_id,
            1, :sales_id, :payment_date, :payment_mode_id,
            :amount, :reference_no, :notes, 1, :created_by
        )
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_no' => $paymentNo,
        ':customer_id' => $customerId,
        ':sales_id' => $saleId,
        ':payment_date' => $firstPayment['payment_date'],
        ':payment_mode_id' => $firstPayment['payment_mode_id'],
        ':amount' => $totalAmount,
        ':reference_no' => $firstPayment['reference_no'],
        ':notes' => $firstPayment['notes'],
        ':created_by' => $userId > 0 ? $userId : null
    ]);

    $paymentId = (int)$pdo->lastInsertId();

    if (tableExists($pdo, 'customer_payment_splits')) {
        foreach ($payments as $payment) {
            $split = $pdo->prepare("
                INSERT INTO customer_payment_splits
                (
                    business_id, branch_id, payment_id,
                    payment_mode_id, amount, reference_no, status
                )
                VALUES
                (
                    :business_id, :branch_id, :payment_id,
                    :payment_mode_id, :amount, :reference_no, 1
                )
            ");
            $split->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':payment_id' => $paymentId,
                ':payment_mode_id' => $payment['payment_mode_id'],
                ':amount' => $payment['payment_amount'],
                ':reference_no' => $payment['reference_no']
            ]);
        }
    }

    $alloc = $pdo->prepare("
        INSERT INTO customer_payment_allocations
        (
            business_id, branch_id, payment_id, customer_id,
            allocation_type, sales_id, allocated_amount, status
        )
        VALUES
        (
            :business_id, :branch_id, :payment_id, :customer_id,
            2, :sales_id, :allocated_amount, 1
        )
    ");
    $alloc->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':payment_id' => $paymentId,
        ':customer_id' => $customerId,
        ':sales_id' => $saleId,
        ':allocated_amount' => $totalAmount
    ]);
}

function insertLegacySalePayment(PDO $pdo, array $scope, $saleId, $customerId, array $payment, $userId)
{
    $stmt = $pdo->prepare("
        INSERT INTO sales_payments
        (
            business_id, branch_id, sales_id, customer_id,
            payment_mode_id, payment_date, payment_amount,
            reference_no, notes, status, received_by
        )
        VALUES
        (
            :business_id, :branch_id, :sales_id, :customer_id,
            :payment_mode_id, :payment_date, :payment_amount,
            :reference_no, :notes, 1, :received_by
        )
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':sales_id' => $saleId,
        ':customer_id' => $customerId,
        ':payment_mode_id' => $payment['payment_mode_id'],
        ':payment_date' => $payment['payment_date'],
        ':payment_amount' => $payment['payment_amount'],
        ':reference_no' => $payment['reference_no'],
        ':notes' => $payment['notes'],
        ':received_by' => $userId
    ]);
}


function insertSalePayment(PDO $pdo, array $scope, $saleId, $customerId, array $payment, $userId)
{
    insertSalePagePaymentReceipt($pdo, $scope, $saleId, $customerId, [$payment], $userId);
}

function reserveDocumentNumber(PDO $pdo, array $scope, $documentType, $invoiceType, $saleId)
{
    $prefix = getDocumentPrefix($pdo, $scope, $documentType);

    $reusable = $pdo->prepare("
        SELECT id, document_no, number_value
        FROM invoice_number_register
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND document_type = :document_type
        AND invoice_type = :invoice_type
        AND status = 2
        ORDER BY number_value ASC
        LIMIT 1
        FOR UPDATE
    ");
    $reusable->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':document_type' => $documentType,
        ':invoice_type' => $invoiceType
    ]);
    $row = $reusable->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $upd = $pdo->prepare("
            UPDATE invoice_number_register
            SET status = 1, sales_id = :sales_id, deleted_sales_id = NULL, deleted_at = NULL
            WHERE id = :id
        ");
        $upd->execute([
            ':sales_id' => $saleId > 0 ? $saleId : null,
            ':id' => $row['id']
        ]);

        return $row['document_no'];
    }

    $maxStmt = $pdo->prepare("
        SELECT COALESCE(MAX(number_value), 0) + 1 AS next_no
        FROM invoice_number_register
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND document_type = :document_type
        AND invoice_type = :invoice_type
        FOR UPDATE
    ");
    $maxStmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':document_type' => $documentType,
        ':invoice_type' => $invoiceType
    ]);

    $nextNo = (int)$maxStmt->fetchColumn();
    if ($nextNo <= 0) {
        $nextNo = 1;
    }

    $documentNo = $prefix . '-' . str_pad((string)$nextNo, 3, '0', STR_PAD_LEFT);

    $ins = $pdo->prepare("
        INSERT INTO invoice_number_register
        (
            business_id, branch_id, document_type, invoice_type,
            prefix, number_value, document_no, sales_id, status
        )
        VALUES
        (
            :business_id, :branch_id, :document_type, :invoice_type,
            :prefix, :number_value, :document_no, :sales_id, 1
        )
    ");
    $ins->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':document_type' => $documentType,
        ':invoice_type' => $invoiceType,
        ':prefix' => $prefix,
        ':number_value' => $nextNo,
        ':document_no' => $documentNo,
        ':sales_id' => $saleId > 0 ? $saleId : null
    ]);

    return $documentNo;
}

function attachDocumentNumberToSale(PDO $pdo, array $scope, $documentType, $invoiceType, $documentNo, $saleId)
{
    $stmt = $pdo->prepare("
        UPDATE invoice_number_register
        SET sales_id = :sales_id, status = 1
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND document_type = :document_type
        AND invoice_type = :invoice_type
        AND document_no = :document_no
    ");
    $stmt->execute([
        ':sales_id' => $saleId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':document_type' => $documentType,
        ':invoice_type' => $invoiceType,
        ':document_no' => $documentNo
    ]);
}

function getDocumentPrefix(PDO $pdo, array $scope, $documentType)
{
    $defaults = [
        1 => 'QUO',
        2 => 'PRO',
        3 => 'SB',
        4 => 'DS',
        5 => 'INV'
    ];

    $fieldMap = [
        1 => 'quotation_prefix',
        2 => 'proforma_prefix',
        3 => 'sale_order_prefix',
        4 => 'invoice_prefix',
        5 => 'invoice_prefix'
    ];

    $field = $fieldMap[$documentType] ?? 'invoice_prefix';

    try {
        $stmt = $pdo->prepare("
            SELECT $field
            FROM invoice_settings
            WHERE business_id = :business_id AND branch_id = :branch_id
            LIMIT 1
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);
        $prefix = trim((string)$stmt->fetchColumn());

        if ($prefix !== '') {
            return $documentType === 4 && $prefix === 'INV' ? 'DS' : $prefix;
        }
    } catch (Throwable $e) {}

    return $defaults[$documentType] ?? 'DOC';
}

function deleteSale(PDO $pdo, $cancel = false)
{

    $scope = getScope();
    $userId = currentUserIdSafe();
    $saleId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $reason = cleanInput($_POST['reason'] ?? '');

    if ($saleId <= 0) {
        jsonResponse(false, 'Invalid sales id.');
    }

    try {
        $pdo->beginTransaction();

        $sale = getSaleForUpdate($pdo, $scope, $saleId);
        if (!$sale) {
            throw new Exception('Sale not found.');
        }

        requireSalesDocumentPermission(
            (int)$sale['document_type'],
            $cancel ? 18 : 5,
            $cancel ? 'You do not have permission to cancel this document type.' : 'You do not have permission to delete this document type.'
        );

        if ((int)$sale['status'] === 3 || (int)$sale['status'] === 4) {
            throw new Exception('Sale already closed.');
        }

        if ((int)$sale['stock_deducted'] === 1) {
            reverseSaleStock($pdo, $scope, $saleId);
        }

        markOldPaymentsInactive($pdo, $scope, $saleId);
        markOldSaleRowsInactive($pdo, $scope, $saleId);

        if ($cancel) {
            $stmt = $pdo->prepare("
                UPDATE sales
                SET status = 4,
                    cancelled_by = :user_id,
                    cancelled_at = NOW(),
                    cancel_reason = :reason,
                    stock_deducted = 0
                WHERE id = :id AND business_id = :business_id AND branch_id = :branch_id
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':reason' => $reason,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            $reg = $pdo->prepare("
                UPDATE invoice_number_register
                SET status = 3
                WHERE business_id = :business_id
                AND branch_id = :branch_id
                AND sales_id = :sales_id
            ");
            $reg->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':sales_id' => $saleId
            ]);

            addActivityLog($pdo, $scope, $userId, 'sales', 'CANCEL_INVOICE', 'Invoice ' . $sale['sales_no'] . ' cancelled.', $saleId, $sale['sales_no']);
            $message = 'Invoice cancelled. Stock returned. Invoice number locked.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE sales
                SET status = 3,
                    deleted_by = :user_id,
                    deleted_at = NOW(),
                    delete_reason = :reason,
                    stock_deducted = 0
                WHERE id = :id AND business_id = :business_id AND branch_id = :branch_id
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':reason' => $reason,
                ':id' => $saleId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            $reg = $pdo->prepare("
                UPDATE invoice_number_register
                SET status = 2,
                    sales_id = NULL,
                    deleted_sales_id = :deleted_sales_id,
                    deleted_at = NOW()
                WHERE business_id = :business_id
                AND branch_id = :branch_id
                AND sales_id = :sales_id
            ");
            $reg->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':deleted_sales_id' => $saleId,
                ':sales_id' => $saleId
            ]);

            addActivityLog($pdo, $scope, $userId, 'sales', 'DELETE_INVOICE', 'Invoice ' . $sale['sales_no'] . ' deleted and number reusable.', $saleId, $sale['sales_no']);
            $message = 'Invoice deleted. Stock returned. Invoice number reusable.';
        }

        updateCustomerOutstanding($pdo, $scope, (int)$sale['customer_id']);

        $pdo->commit();

        jsonOk($message);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage());
    }
}

function updateCustomerOutstanding(PDO $pdo, array $scope, $customerId)
{
    try {
        $openingColumn = '0';
        if (columnExists($pdo, 'customers', 'opening_due')) {
            $openingColumn = 'opening_due';
        } elseif (columnExists($pdo, 'customers', 'opening_outstanding')) {
            $openingColumn = 'opening_outstanding';
        } elseif (columnExists($pdo, 'customers', 'opening_balance')) {
            $openingColumn = 'opening_balance';
        }

        $stmt = $pdo->prepare("
            SELECT COALESCE($openingColumn, 0) +
                   COALESCE((
                       SELECT SUM(due_amount)
                       FROM sales
                       WHERE business_id = :business_id
                       AND branch_id = :branch_id
                       AND customer_id = :customer_id
                       AND status IN (1,2)
                   ), 0) AS outstanding
            FROM customers
            WHERE id = :customer_id2
            AND business_id = :business_id2
            AND branch_id = :branch_id2
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
        $outstanding = round2((float)$stmt->fetchColumn());

        if (columnExists($pdo, 'customers', 'current_outstanding')) {
            $upd = $pdo->prepare("
                UPDATE customers
                SET current_outstanding = :outstanding
                WHERE id = :customer_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $upd->execute([
                ':outstanding' => $outstanding,
                ':customer_id' => $customerId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }

        if (columnExists($pdo, 'customers', 'total_outstanding')) {
            $upd = $pdo->prepare("
                UPDATE customers
                SET total_outstanding = :outstanding
                WHERE id = :customer_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $upd->execute([
                ':outstanding' => $outstanding,
                ':customer_id' => $customerId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);
        }
    } catch (Throwable $e) {}
}

function addActivityLog(PDO $pdo, array $scope, $userId, $module, $action, $description, $referenceId, $recordReference)
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs
            (
                business_id, branch_id, user_id, module_key, action_type,
                description, reference_id, record_reference, ip_address
            )
            VALUES
            (
                :business_id, :branch_id, :user_id, :module_key, :action_type,
                :description, :reference_id, :record_reference, :ip_address
            )
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':user_id' => $userId > 0 ? $userId : null,
            ':module_key' => $module,
            ':action_type' => $action,
            ':description' => $description,
            ':reference_id' => $referenceId,
            ':record_reference' => $recordReference,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Throwable $e) {}
}
