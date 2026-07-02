<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
header('Content-Type: application/json');
requireApiLogin();

/** @var PDO $pdo */

$action = cleanInput($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'list_expenses':
        listExpenses($pdo);
        break;
    case 'get_expense':
        getExpense($pdo);
        break;
    case 'save_expense':
        verifyCsrfToken();
        saveExpense($pdo);
        break;
    case 'delete_expense':
        verifyCsrfToken();
        deleteExpense($pdo);
        break;
    case 'cancel_expense':
        verifyCsrfToken();
        cancelExpense($pdo);
        break;
    case 'get_categories':
        getExpenseCategories($pdo);
        break;
    case 'quick_add_category':
        verifyCsrfToken();
        quickAddExpenseCategory($pdo);
        break;
    case 'get_payment_modes':
        getPaymentModes($pdo);
        break;
    default:
        jsonResponse(false, 'Invalid action.');
}


function executeExpenseStatement(PDOStatement $stmt, array $params = [])
{
    /*
     * HY093 hard fix:
     * PDO named placeholders fail when extra params are passed.
     * This helper binds only placeholders that actually exist in the SQL.
     */
    $sql = (string)$stmt->queryString;

    if ($sql === '' || empty($params)) {
        $stmt->execute($params);
        return;
    }

    preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $sql, $matches);
    $needed = array_unique($matches[0] ?? []);

    $filtered = [];
    foreach ($needed as $placeholder) {
        if (array_key_exists($placeholder, $params)) {
            $filtered[$placeholder] = $params[$placeholder];
        }
    }

    $stmt->execute($filtered);
}


function requireExpensePermission($action = 'view')
{
    if (function_exists('isPlatformOwner') && isPlatformOwner()) {
        return;
    }

    /*
     * Permission fallback:
     * Different projects may store the expense menu key as expenses / expense / expense_list.
     * If no permission helper exists, allow the logged-in user.
     */
    if (!function_exists('hasPermission')) {
        return;
    }

    $keys = ['expenses', 'expense', 'expense_list', 'expense_master'];

    foreach ($keys as $key) {
        if (hasPermission($key, $action)) {
            return;
        }
    }

    jsonResponse(false, 'Permission denied. Please give access for Expenses menu in Roles & Permissions.');
}

function getExpenseScope()
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

function getExpenseCategories(PDO $pdo)
{
    requireExpensePermission('view');

    $scope = getExpenseScope();

    $stmt = $pdo->prepare("
        SELECT id, category_name
        FROM expense_categories
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY category_name ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Categories loaded.', [
        'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function getPaymentModes(PDO $pdo)
{
    requireExpensePermission('view');

    $scope = getExpenseScope();

    $stmt = $pdo->prepare("
        SELECT id, mode_name
        FROM payment_modes
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        ORDER BY id ASC
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    jsonResponse(true, 'Payment modes loaded.', [
        'payment_modes' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function quickAddExpenseCategory(PDO $pdo)
{
    requireExpensePermission('add');

    $scope = getExpenseScope();
    $categoryName = cleanInput($_POST['category_name'] ?? '');

    if ($categoryName === '') {
        jsonResponse(false, 'Please enter category name.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO expense_categories
        (business_id, branch_id, category_name, status, created_by)
        VALUES
        (:business_id, :branch_id, :category_name, 1, :created_by)
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':category_name' => $categoryName,
        ':created_by' => currentUserId()
    ]);

    jsonResponse(true, 'Expense category added.', [
        'id' => (int)$pdo->lastInsertId(),
        'category_name' => $categoryName
    ]);
}

function listExpenses(PDO $pdo)
{
    requireExpensePermission('view');

    $scope = getExpenseScope();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);
    $categoryId = (int)($_GET['category_id'] ?? 0);
    $fromDate = expenseCleanDate($_GET['from_date'] ?? '');
    $toDate = expenseCleanDate($_GET['to_date'] ?? '');

    $where = "
        WHERE e.business_id = :business_id
        AND e.branch_id = :branch_id
    ";

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($status > 0) {
        $where .= " AND e.status = :status";
        $params[':status'] = $status;
    }

    if ($categoryId > 0) {
        $where .= " AND e.expense_category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    if ($fromDate) {
        $where .= " AND e.expense_date >= :from_date";
        $params[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $where .= " AND e.expense_date <= :to_date";
        $params[':to_date'] = $toDate;
    }

    if ($search !== '') {
        $where .= "
            AND (
                e.expense_no LIKE :search_expense_no
                OR e.vendor_name LIKE :search_vendor_name
                OR e.reference_no LIKE :search_reference_no
                OR e.notes LIKE :search_notes
                OR ec.category_name LIKE :search_category_name
            )
        ";
        $searchValue = '%' . $search . '%';
        $params[':search_expense_no'] = $searchValue;
        $params[':search_vendor_name'] = $searchValue;
        $params[':search_reference_no'] = $searchValue;
        $params[':search_notes'] = $searchValue;
        $params[':search_category_name'] = $searchValue;
    }

    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.expense_no,
            e.expense_date,
            e.expense_category_id AS expense_category_id,
            e.expense_category_id AS category_id,
            ec.category_name,
            e.vendor_name,
            e.reference_no,
            e.taxable_amount,
            e.gst_amount,
            e.total_amount,
            e.status,
            e.notes,
            e.created_at,
            COALESCE(split_summary.mode_summary, '') AS split_summary
        FROM expenses e
        LEFT JOIN expense_categories ec
            ON ec.id = e.expense_category_id
        LEFT JOIN (
            SELECT
                eps.expense_id,
                GROUP_CONCAT(CONCAT(COALESCE(pm.mode_name, 'Mode'), ' ₹', FORMAT(eps.amount, 2)) ORDER BY eps.id SEPARATOR ', ') AS mode_summary
            FROM expense_payment_splits eps
            LEFT JOIN payment_modes pm ON pm.id = eps.payment_mode_id
            WHERE eps.business_id = :split_business_id
            AND eps.branch_id = :split_branch_id
            AND eps.status = 1
            GROUP BY eps.expense_id
        ) split_summary ON split_summary.expense_id = e.id
        $where
        ORDER BY e.expense_date DESC, e.id DESC
    ");

    $params[':split_business_id'] = $scope['business_id'];
    $params[':split_branch_id'] = $scope['branch_id'];
    executeExpenseStatement($stmt, $params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statsWhere = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
    ";
    $statsParams = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    if ($fromDate) {
        $statsWhere .= " AND expense_date >= :from_date";
        $statsParams[':from_date'] = $fromDate;
    }

    if ($toDate) {
        $statsWhere .= " AND expense_date <= :to_date";
        $statsParams[':to_date'] = $toDate;
    }

    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_expenses,
            COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END), 0) AS active_expenses,
            COALESCE(SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END), 0) AS cancelled_expenses,
            COALESCE(SUM(CASE WHEN status = 1 THEN total_amount ELSE 0 END), 0) AS total_amount
        FROM expenses
        $statsWhere
    ");
    executeExpenseStatement($statsStmt, $statsParams);

    jsonResponse(true, 'Expenses loaded.', [
        'expenses' => $expenses,
        'stats' => $statsStmt->fetch(PDO::FETCH_ASSOC)
    ]);
}

function getExpense(PDO $pdo)
{
    requireExpensePermission('view');

    $scope = getExpenseScope();
    $expenseId = (int)($_GET['expense_id'] ?? 0);

    if ($expenseId <= 0) {
        jsonResponse(false, 'Invalid expense.');
    }

    $stmt = $pdo->prepare("
        SELECT e.*, e.expense_category_id AS category_id
        FROM expenses e
        WHERE e.id = :expense_id
        AND e.business_id = :business_id
        AND e.branch_id = :branch_id
        LIMIT 1
    ");
    $stmt->execute([
        ':expense_id' => $expenseId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$expense) {
        jsonResponse(false, 'Expense not found.');
    }

    $splitStmt = $pdo->prepare("
        SELECT
            eps.id,
            eps.payment_mode_id,
            pm.mode_name,
            eps.amount,
            eps.reference_no
        FROM expense_payment_splits eps
        LEFT JOIN payment_modes pm ON pm.id = eps.payment_mode_id
        WHERE eps.business_id = :business_id
        AND eps.branch_id = :branch_id
        AND eps.expense_id = :expense_id
        AND eps.status = 1
        ORDER BY eps.id ASC
    ");
    $splitStmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':expense_id' => $expenseId
    ]);

    jsonResponse(true, 'Expense loaded.', [
        'expense' => $expense,
        'splits' => $splitStmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function saveExpense(PDO $pdo)
{
    $scope = getExpenseScope();
    $expenseId = (int)($_POST['expense_id'] ?? 0);

    requireExpensePermission($expenseId > 0 ? 'edit' : 'add');

    $expenseDate = expenseCleanDate($_POST['expense_date'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $vendorName = cleanInput($_POST['vendor_name'] ?? '');
    $referenceNo = cleanInput($_POST['reference_no'] ?? '');
    $taxableAmount = round2($_POST['taxable_amount'] ?? 0);
    $gstAmount = round2($_POST['gst_amount'] ?? 0);
    $notes = cleanInput($_POST['notes'] ?? '');
    $status = (int)($_POST['status'] ?? 1);
    $splits = readExpenseSplits();

    if (!$expenseDate) {
        jsonResponse(false, 'Please select expense date.');
    }

    if ($categoryId <= 0) {
        jsonResponse(false, 'Please select expense category.');
    }

    if (!$splits) {
        jsonResponse(false, 'Enter at least one payment split.');
    }

    $splitTotal = 0;
    foreach ($splits as $split) {
        $splitTotal += round2($split['amount']);
    }
    $splitTotal = round2($splitTotal);

    if ($splitTotal <= 0) {
        jsonResponse(false, 'Expense amount must be greater than zero.');
    }

    $totalAmount = round2($taxableAmount + $gstAmount);
    if ($totalAmount <= 0) {
        $totalAmount = $splitTotal;
        $taxableAmount = $splitTotal;
        $gstAmount = 0;
    }

    if (abs($totalAmount - $splitTotal) > 0.01) {
        jsonResponse(false, 'Split payment total must match expense total.');
    }

    if (!in_array($status, [1,2], true)) {
        $status = 1;
    }

    validateExpenseCategory($pdo, $scope, $categoryId);

    try {
        $pdo->beginTransaction();

        if ($expenseId > 0) {
            $old = getExpenseForUpdate($pdo, $scope, $expenseId);
            if (!$old) {
                throw new Exception('Expense not found.');
            }

            $stmt = $pdo->prepare("
                UPDATE expenses
                SET expense_date = :expense_date,
                    expense_category_id = :expense_category_id,
                    vendor_name = :vendor_name,
                    reference_no = :reference_no,
                    taxable_amount = :taxable_amount,
                    gst_amount = :gst_amount,
                    total_amount = :total_amount,
                    notes = :notes,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :expense_id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");
            $stmt->execute([
                ':expense_date' => $expenseDate,
                ':expense_category_id' => $categoryId,
                ':vendor_name' => $vendorName,
                ':reference_no' => $referenceNo,
                ':taxable_amount' => $taxableAmount,
                ':gst_amount' => $gstAmount,
                ':total_amount' => $totalAmount,
                ':notes' => $notes,
                ':status' => $status,
                ':expense_id' => $expenseId,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            cancelExpenseSplits($pdo, $scope, $expenseId);
            saveExpenseSplits($pdo, $scope, $expenseId, $splits);
        } else {
            $expenseNo = generateExpenseNo($pdo, $scope);

            $stmt = $pdo->prepare("
                INSERT INTO expenses
                (
                    business_id,
                    branch_id,
                    expense_no,
                    expense_date,
                    expense_category_id,
                    vendor_name,
                    reference_no,
                    taxable_amount,
                    gst_amount,
                    total_amount,
                    notes,
                    status,
                    created_by,
                    created_at
                )
                VALUES
                (
                    :business_id,
                    :branch_id,
                    :expense_no,
                    :expense_date,
                    :expense_category_id,
                    :vendor_name,
                    :reference_no,
                    :taxable_amount,
                    :gst_amount,
                    :total_amount,
                    :notes,
                    :status,
                    :created_by,
                    NOW()
                )
            ");
            $stmt->execute([
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id'],
                ':expense_no' => $expenseNo,
                ':expense_date' => $expenseDate,
                ':expense_category_id' => $categoryId,
                ':vendor_name' => $vendorName,
                ':reference_no' => $referenceNo,
                ':taxable_amount' => $taxableAmount,
                ':gst_amount' => $gstAmount,
                ':total_amount' => $totalAmount,
                ':notes' => $notes,
                ':status' => $status,
                ':created_by' => currentUserId()
            ]);

            $expenseId = (int)$pdo->lastInsertId();
            saveExpenseSplits($pdo, $scope, $expenseId, $splits);
        }

        $pdo->commit();

        jsonResponse(true, $expenseId > 0 ? 'Expense saved successfully.' : 'Expense added successfully.', [
            'expense_id' => $expenseId,
            'redirect' => BASE_URL . 'pages/expenses.php'
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Expense save failed.');
    }
}

function deleteExpense(PDO $pdo)
{
    requireExpensePermission('delete');

    $scope = getExpenseScope();
    $expenseId = (int)($_POST['expense_id'] ?? 0);

    if ($expenseId <= 0) {
        jsonResponse(false, 'Invalid expense.');
    }

    try {
        $pdo->beginTransaction();

        $old = getExpenseForUpdate($pdo, $scope, $expenseId);
        if (!$old) {
            throw new Exception('Expense not found.');
        }

        cancelExpenseSplits($pdo, $scope, $expenseId);

        $stmt = $pdo->prepare("
            DELETE FROM expenses
            WHERE id = :expense_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':expense_id' => $expenseId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $pdo->commit();
        jsonResponse(true, 'Expense deleted successfully.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Expense delete failed.');
    }
}

function cancelExpense(PDO $pdo)
{
    requireExpensePermission('edit');

    $scope = getExpenseScope();
    $expenseId = (int)($_POST['expense_id'] ?? 0);

    if ($expenseId <= 0) {
        jsonResponse(false, 'Invalid expense.');
    }

    try {
        $pdo->beginTransaction();

        $old = getExpenseForUpdate($pdo, $scope, $expenseId);
        if (!$old) {
            throw new Exception('Expense not found.');
        }

        $stmt = $pdo->prepare("
            UPDATE expenses
            SET status = 2,
                updated_at = NOW()
            WHERE id = :expense_id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");
        $stmt->execute([
            ':expense_id' => $expenseId,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        cancelExpenseSplits($pdo, $scope, $expenseId);

        $pdo->commit();
        jsonResponse(true, 'Expense cancelled successfully.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        jsonResponse(false, $e->getMessage() ?: 'Expense cancel failed.');
    }
}

function readExpenseSplits()
{
    $json = $_POST['split_payments'] ?? '[]';
    $rows = json_decode((string)$json, true);

    if (!is_array($rows)) {
        return [];
    }

    $splits = [];
    foreach ($rows as $row) {
        $modeId = (int)($row['payment_mode_id'] ?? 0);
        $amount = round2($row['amount'] ?? 0);
        $referenceNo = cleanInput($row['reference_no'] ?? '');

        if ($modeId <= 0 || $amount <= 0) {
            continue;
        }

        $splits[] = [
            'payment_mode_id' => $modeId,
            'amount' => $amount,
            'reference_no' => $referenceNo
        ];
    }

    return $splits;
}

function saveExpenseSplits(PDO $pdo, array $scope, $expenseId, array $splits)
{
    foreach ($splits as $split) {
        $stmt = $pdo->prepare("
            INSERT INTO expense_payment_splits
            (
                business_id,
                branch_id,
                expense_id,
                payment_mode_id,
                amount,
                reference_no,
                status,
                created_at
            )
            VALUES
            (
                :business_id,
                :branch_id,
                :expense_id,
                :payment_mode_id,
                :amount,
                :reference_no,
                1,
                NOW()
            )
        ");
        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':expense_id' => $expenseId,
            ':payment_mode_id' => $split['payment_mode_id'],
            ':amount' => $split['amount'],
            ':reference_no' => $split['reference_no']
        ]);
    }
}

function cancelExpenseSplits(PDO $pdo, array $scope, $expenseId)
{
    $stmt = $pdo->prepare("
        UPDATE expense_payment_splits
        SET status = 2
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND expense_id = :expense_id
        AND status = 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':expense_id' => $expenseId
    ]);
}

function getExpenseForUpdate(PDO $pdo, array $scope, $expenseId)
{
    $stmt = $pdo->prepare("
        SELECT e.*, e.expense_category_id AS category_id
        FROM expenses e
        WHERE e.id = :expense_id
        AND e.business_id = :business_id
        AND e.branch_id = :branch_id
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([
        ':expense_id' => $expenseId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function validateExpenseCategory(PDO $pdo, array $scope, $categoryId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM expense_categories
        WHERE id = :category_id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        LIMIT 1
    ");
    $stmt->execute([
        ':category_id' => $categoryId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Invalid expense category selected.');
    }
}

function generateExpenseNo(PDO $pdo, array $scope)
{
    $prefix = 'EXP-' . date('Ym') . '-';

    $stmt = $pdo->prepare("
        SELECT expense_no
        FROM expenses
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND expense_no LIKE :prefix
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':prefix' => $prefix . '%'
    ]);

    $lastNo = (string)$stmt->fetchColumn();
    $next = 1;

    if ($lastNo !== '') {
        $parts = explode('-', $lastNo);
        $next = ((int)end($parts)) + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function expenseCleanDate($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function round2($value)
{
    return round((float)$value, 2);
}
