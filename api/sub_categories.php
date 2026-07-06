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
| Sub Category permission action numbers
|--------------------------------------------------------------------------
| 1 = View
| 2 = List
| 3 = Create / Add
| 4 = Edit
| 5 = Delete
|--------------------------------------------------------------------------
*/

function subCategoryCan($actionCode)
{
    if (isPlatformOwner()) {
        return true;
    }

    return function_exists('hasPermission') && hasPermission('sub_categories', (int)$actionCode);
}

function requireSubCategoryPermission($actionCode)
{
    if (!subCategoryCan((int)$actionCode)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function getPageContext(PDO $pdo)
{
    if (!subCategoryCan(1) && !subCategoryCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    jsonResponse(true, 'Page context loaded.', [
        'context' => [
            'can_view' => subCategoryCan(1),
            'can_list' => subCategoryCan(2),
            'can_add' => subCategoryCan(3),
            'can_edit' => subCategoryCan(4),
            'can_delete' => subCategoryCan(5),
            'page_title' => 'Sub Categories',
            'page_note' => 'Manage sub category master data based on your role permission.',
            'add_button_label' => 'Add Sub Category',
            'add_modal_title' => 'Add Sub Category',
            'edit_modal_title' => 'Edit Sub Category'
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
    if (!subCategoryCan(1) && !subCategoryCan(2)) {
        jsonResponse(false, 'Permission denied.');
    }

    $scope = scopeData();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);

    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id']
    ];

    $where = " WHERE sc.business_id = :business_id AND sc.branch_id = :branch_id ";

    if ($status === 1 || $status === 2) {
        $where .= " AND sc.status = :status ";
        $params[':status'] = $status;
    }

    if ($search !== '') {
    $where .= " AND (
        sc.sub_category_name LIKE :search_sub_category_name
        OR sc.description LIKE :search_description
        OR c.category_name LIKE :search_category_name
    ) ";
    $params[':search_sub_category_name'] = '%' . $search . '%';
    $params[':search_description'] = '%' . $search . '%';
    $params[':search_category_name'] = '%' . $search . '%';
}


    try {
        $stmt = $pdo->prepare("
            SELECT
                sc.id,
                sc.category_id,
                sc.sub_category_name,
                sc.description,
                sc.status,
                sc.created_at,
                c.category_name
            FROM sub_categories sc
            LEFT JOIN categories c
                ON c.id = sc.category_id
                AND c.business_id = sc.business_id
                AND c.branch_id = sc.branch_id
            $where
            ORDER BY sc.id DESC
        ");

        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $canEdit = subCategoryCan(4);
        $canDelete = subCategoryCan(5);

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
            FROM sub_categories
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

        jsonResponse(true, 'Sub Category list loaded.', [
            'records' => $records,
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Unable to load sub category list.');
    }
}

function getRecord(PDO $pdo)
{
    requireSubCategoryPermission(4);

    $scope = scopeData();
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid record.');
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM sub_categories
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
        requireSubCategoryPermission(4);
    } else {
        requireSubCategoryPermission(3);
    }

    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = cleanInput($_POST['sub_category_name'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $status = (int)($_POST['status'] ?? 1);

    if ($categoryId <= 0) {
        jsonResponse(false, 'Please select category.');
    }

    if ($name === '') {
        jsonResponse(false, 'Please enter sub category name.');
    }

    if (!in_array($status, [1, 2], true)) {
        $status = 1;
    }

    validateCategory($pdo, $scope, $categoryId);

    try {
        checkDuplicateSubCategory($pdo, $scope, $categoryId, $name, $id);

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE sub_categories
                SET
                    category_id = :category_id,
                    sub_category_name = :name,
                    description = :description,
                    status = :status
                WHERE id = :id
                AND business_id = :business_id
                AND branch_id = :branch_id
            ");

            $stmt->execute([
                ':category_id' => $categoryId,
                ':name' => $name,
                ':description' => $description,
                ':status' => $status,
                ':id' => $id,
                ':business_id' => $scope['business_id'],
                ':branch_id' => $scope['branch_id']
            ]);

            jsonResponse(true, 'Sub Category updated successfully.', [
                'id' => $id
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO sub_categories
            (
                business_id,
                branch_id,
                category_id,
                sub_category_name,
                description,
                status,
                created_by
            )
            VALUES
            (
                :business_id,
                :branch_id,
                :category_id,
                :name,
                :description,
                :status,
                :created_by
            )
        ");

        $stmt->execute([
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id'],
            ':category_id' => $categoryId,
            ':name' => $name,
            ':description' => $description,
            ':status' => $status,
            ':created_by' => currentUserId()
        ]);

        jsonResponse(true, 'Sub Category added successfully.', [
            'id' => (int)$pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Save failed.');
    }
}

function deleteRecord(PDO $pdo)
{
    requireSubCategoryPermission(5);

    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(false, 'Invalid record.');
    }

    try {
        checkSubCategoryUsage($pdo, $scope, $id);

        $stmt = $pdo->prepare("
            DELETE FROM sub_categories
            WHERE id = :id
            AND business_id = :business_id
            AND branch_id = :branch_id
        ");

        $stmt->execute([
            ':id' => $id,
            ':business_id' => $scope['business_id'],
            ':branch_id' => $scope['branch_id']
        ]);

        jsonResponse(true, 'Sub Category deleted successfully.');

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
    | Kept without sub category page permission because this dropdown can be used
    | by form dependencies.
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

function checkDuplicateSubCategory(PDO $pdo, array $scope, $categoryId, $subCategoryName, $ignoreId = 0)
{
    $params = [
        ':business_id' => $scope['business_id'],
        ':branch_id' => $scope['branch_id'],
        ':category_id' => $categoryId,
        ':sub_category_name' => $subCategoryName
    ];

    $where = "
        WHERE business_id = :business_id
        AND branch_id = :branch_id
        AND category_id = :category_id
        AND sub_category_name = :sub_category_name
    ";

    if ((int)$ignoreId > 0) {
        $where .= " AND id != :ignore_id ";
        $params[':ignore_id'] = (int)$ignoreId;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM sub_categories
        $where
        LIMIT 1
    ");

    $stmt->execute($params);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(false, 'Sub category name already exists for this category.');
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
