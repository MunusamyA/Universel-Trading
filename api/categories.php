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

    case 'list':
        listRecords($pdo);
        break;

    case 'get':
        getRecord($pdo);
        break;

    case 'save':
        verifyCsrfToken();
        saveRecord($pdo);
        break;

    case 'delete':
        verifyCsrfToken();
        deleteRecord($pdo);
        break;

    case 'categories':
        listActiveCategories($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
        break;
}

/*
|--------------------------------------------------------------------------
| Category permission action numbers
|--------------------------------------------------------------------------
| 1 = View
| 2 = List
| 3 = Create / Add
| 4 = Edit
| 5 = Delete
|--------------------------------------------------------------------------
*/

function categoryCan($actionCode)
{
    if (isPlatformOwner()) {
        return true;
    }

    return function_exists('hasPermission') && hasPermission('categories', (int)$actionCode);
}

function requireCategoryPermission($actionCode)
{
    if (!categoryCan((int)$actionCode)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function getPageContext(PDO $pdo)
{
    if (!categoryCan(1) && !categoryCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    jsonResponse(true, 'Page context loaded.', [
        'context' => [
            'can_view' => categoryCan(1),
            'can_list' => categoryCan(2),
            'can_add' => categoryCan(3),
            'can_edit' => categoryCan(4),
            'can_delete' => categoryCan(5),
            'page_title' => 'Categories',
            'page_note' => 'Manage category master data based on your role permission.',
            'add_button_label' => 'Add Category',
            'add_modal_title' => 'Add Category',
            'edit_modal_title' => 'Edit Category'
        ]
    ]);
}

function scopeData()
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

function listRecords(PDO $pdo)
{
    if (!categoryCan(1) && !categoryCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    $scope = scopeData();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    $where = " WHERE business_id = :business_id AND branch_id = :branch_id ";

    if ($status === 1 || $status === 2) {
        $where .= " AND status = :status ";
        $params[':status'] = $status;
    }

    if ($search !== '') {
    $where .= " AND (category_name LIKE :search_category_name OR description LIKE :search_description) ";
    $params[':search_category_name'] = '%' . $search . '%';
    $params[':search_description'] = '%' . $search . '%';
}

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                category_name,
                description,
                status,
                created_at
            FROM categories
            $where
            ORDER BY id DESC
        ");

        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $canEdit = categoryCan(4);
        $canDelete = categoryCan(5);

        foreach ($records as &$record) {
            $record['can_edit'] = $canEdit;
            $record['can_delete'] = $canDelete;
        }
        unset($record);

        $statsStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS inactive
            FROM categories
            WHERE business_id = :business_id
            AND branch_id = :branch_id
        ");

        $statsStmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total' => 0,
            'active' => 0,
            'inactive' => 0
        ];

        jsonResponse(true, 'Category list loaded.', [
            'records' => $records,
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load category list.');
    }
}

function getRecord(PDO $pdo)
{
    requireCategoryPermission(4);

    $scope = scopeData();
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid record.');
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM categories
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            jsonResponse(false, 'Record not found.');
        }

        jsonResponse(true, 'Record loaded.', [
            'record' => $row
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load record.');
    }
}

function saveRecord(PDO $pdo)
{
    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        requireCategoryPermission(4);
    } else {
        requireCategoryPermission(3);
    }

    $name = cleanInput($_POST['category_name'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $status = (int)($_POST['status'] ?? 1);

    if ($name === '') {
        jsonResponse(false, 'Please enter category name.');
    }

    if (!in_array($status, [1, 2], true)) {
        $status = 1;
    }

    try {
        checkDuplicateCategory($pdo, $scope, $name, $id);

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE categories
                SET
                    category_name = :name,
                    description = :description,
                    status = :status
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");

            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':status' => $status,
                ':id' => $id,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            jsonResponse(true, 'Category updated successfully.', [
                'id' => $id
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO categories
            (
                business_id,
                branch_id,
                category_name,
                description,
                status,
                created_by
            )
            VALUES
            (
                :business_id,
                :branch_id,
                :name,
                :description,
                :status,
                :created_by
            )
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':name' => $name,
            ':description' => $description,
            ':status' => $status,
            ':created_by' => currentUserId()
        ]);

        jsonResponse(true, 'Category added successfully.', [
            'id' => (int)$pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Save failed.');
    }
}

function deleteRecord(PDO $pdo)
{
    requireCategoryPermission(5);

    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid record.');
    }

    try {
        checkCategoryUsage($pdo, $scope, $id);

        $stmt = $pdo->prepare("
            DELETE FROM categories
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");

        $stmt->execute([
            ':id' => $id,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        jsonResponse(true, 'Category deleted successfully.');

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Delete failed.');
    }
}

function listActiveCategories(PDO $pdo)
{
    /*
    |--------------------------------------------------------------------------
    | Dropdown API
    |--------------------------------------------------------------------------
    | Kept without categories permission because products/purchase/sales forms may
    | need active categories even when user does not have category master access.
    |--------------------------------------------------------------------------
    */

    $scope = scopeData();

    try {
        $stmt = $pdo->prepare("
            SELECT id, category_name
            FROM categories
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

    } catch (Exception $e) {
        jsonResponse(false, 'Unable to load categories.');
    }
}

function validateCategory(PDO $pdo, array $scope, $categoryId)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
        AND status = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $categoryId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Invalid category selected.');
    }
}

function checkDuplicateCategory(PDO $pdo, array $scope, $categoryName, $ignoreId = 0)
{
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':category_name' => $categoryName
    ];

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND category_name = :category_name
    ";

    if ((int)$ignoreId > 0) {
        $where .= " AND id != :ignore_id ";
        $params[':ignore_id'] = (int)$ignoreId;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM categories
        $where
        LIMIT 1
    ");

    $stmt->execute($params);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Category name already exists.');
    }
}

function checkCategoryUsage(PDO $pdo, array $scope, $categoryId)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM products
        WHERE category_id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");

    $stmt->execute([
        ':id' => $categoryId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ((int)($row['total'] ?? 0) > 0) {
        jsonResponse(false, 'Category already used in products. Make it inactive instead.');
    }
}

function checkSubCategoryUsage(PDO $pdo, array $scope, $subCategoryId)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM products
        WHERE sub_category_id = :id
        AND business_id = :business_id
        AND branch_id = :branch_id
    ");

    $stmt->execute([
        ':id' => $subCategoryId,
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ((int)($row['total'] ?? 0) > 0) {
        jsonResponse(false, 'Sub category already used in products. Make it inactive instead.');
    }
}
