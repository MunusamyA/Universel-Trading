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
}

function requireMasterPermission($action = 'view')
{
    if (isPlatformOwner()) return;
    if (function_exists('hasPermission') && !hasPermission('sub_categories', $action)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function scopeData()
{
    $businessId = (int)currentBusinessId();
    $branchId = (int)currentBranchId();
    if ($businessId <= 0 || $branchId <= 0) {
        jsonResponse(false, 'Invalid business or branch session.');
    }
    return ['business_id' => $businessId, 'branch_id' => $branchId];
}

function listRecords(PDO $pdo)
{
    requireMasterPermission('view');
    $scope = scopeData();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);

    $params = [':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']];
    $where = " WHERE sc.business_id = :business_id AND sc.branch_id = :branch_id ";

    if ($status === 1 || $status === 2) {
        $where .= " AND sc.status = :status ";
        $params[':status'] = $status;
    }

    if ($search !== '') {
        $where .= " AND (sc.sub_category_name LIKE :search OR sc.description LIKE :search) ";
        $params[':search'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT sc.id, sc.category_id, sc.sub_category_name, sc.description, sc.status, sc.created_at, c.category_name
        FROM sub_categories sc LEFT JOIN categories c ON c.id = sc.category_id
        $where
        ORDER BY sc.id DESC
    ");
    $stmt->execute($params);

    $statsStmt = $pdo->prepare("
        SELECT COUNT(*) AS total, SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN status=2 THEN 1 ELSE 0 END) AS inactive
        FROM sub_categories
        WHERE business_id = :business_id AND branch_id = :branch_id
    ");
    $statsStmt->execute($scope);

    jsonResponse(true, 'Sub Category list loaded.', ['records'=>$stmt->fetchAll(PDO::FETCH_ASSOC), 'stats'=>$statsStmt->fetch(PDO::FETCH_ASSOC)]);
}

function getRecord(PDO $pdo)
{
    requireMasterPermission('view');
    $scope = scopeData();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid record.');

    $stmt = $pdo->prepare("SELECT * FROM sub_categories WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id LIMIT 1");
    $stmt->execute([':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) jsonResponse(false, 'Record not found.');
    jsonResponse(true, 'Record loaded.', ['record'=>$row]);
}

function saveRecord(PDO $pdo)
{
    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);
    requireMasterPermission($id > 0 ? 'edit' : 'add');

    $name = cleanInput($_POST['sub_category_name'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $status = (int)($_POST['status'] ?? 1);
    if ($name === '') jsonResponse(false, 'Please enter sub category name.');
    if (!in_array($status, [1,2], true)) $status = 1;
     $categoryId = (int)($_POST['category_id'] ?? 0); if ($categoryId <= 0) jsonResponse(false, 'Please select category.'); validateCategory($pdo, $scope, $categoryId);

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE sub_categories
                SET sub_category_name = :name, description = :description, status = :status , category_id = :category_id
                WHERE id = :id AND business_id = :business_id AND branch_id = :branch_id
            ");
            $params = [':name'=>$name, ':description'=>$description, ':status'=>$status, ':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']];
            $params[':category_id'] = $categoryId;
            $stmt->execute($params);
            jsonResponse(true, 'Sub Category updated successfully.', ['id'=>$id]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO sub_categories (business_id, branch_id, category_id, sub_category_name, description, status, created_by)
            VALUES (:business_id, :branch_id, :category_id, :name, :description, :status, :created_by)
        ");
        $params = [':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id'], ':name'=>$name, ':description'=>$description, ':status'=>$status, ':created_by'=>currentUserId()];
        $params[':category_id'] = $categoryId;
        $stmt->execute($params);
        jsonResponse(true, 'Sub Category added successfully.', ['id'=>(int)$pdo->lastInsertId()]);
    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage() ?: 'Save failed.');
    }
}

function deleteRecord(PDO $pdo)
{
    requireMasterPermission('delete');
    $scope = scopeData();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid record.');
    checkSubCategoryUsage($pdo, $scope, $id);
    $stmt = $pdo->prepare("DELETE FROM sub_categories WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id");
    $stmt->execute([':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    jsonResponse(true, 'Sub Category deleted successfully.');
}

function listActiveCategories(PDO $pdo)
{
    $scope = scopeData();
    $stmt = $pdo->prepare("SELECT id, category_name FROM categories WHERE business_id=:business_id AND branch_id=:branch_id AND status=1 ORDER BY category_name ASC");
    $stmt->execute([':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    jsonResponse(true, 'Categories loaded.', ['categories'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function validateCategory(PDO $pdo, array $scope, $categoryId)
{
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id AND status=1 LIMIT 1");
    $stmt->execute([':id'=>$categoryId, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) jsonResponse(false, 'Invalid category selected.');
}

function checkCategoryUsage(PDO $pdo, array $scope, $categoryId)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM products WHERE category_id=:id AND business_id=:business_id AND branch_id=:branch_id");
    $stmt->execute([':id'=>$categoryId, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    if ((int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) > 0) jsonResponse(false, 'Category already used in products. Make it inactive instead.');
}

function checkSubCategoryUsage(PDO $pdo, array $scope, $subCategoryId)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM products WHERE sub_category_id=:id AND business_id=:business_id AND branch_id=:branch_id");
    $stmt->execute([':id'=>$subCategoryId, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    if ((int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) > 0) jsonResponse(false, 'Sub category already used in products. Make it inactive instead.');
}
