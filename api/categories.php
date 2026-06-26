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
    if (function_exists('hasPermission') && !hasPermission('categories', $action)) {
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
    $where = " WHERE business_id = :business_id AND branch_id = :branch_id ";

    if ($status === 1 || $status === 2) {
        $where .= " AND status = :status ";
        $params[':status'] = $status;
    }

    if ($search !== '') {
        $where .= " AND (category_name LIKE :search OR description LIKE :search) ";
        $params[':search'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("
        SELECT id, category_name, description, status, created_at
        FROM categories 
        $where
        ORDER BY id DESC
    ");
    $stmt->execute($params);

    $statsStmt = $pdo->prepare("
        SELECT COUNT(*) AS total, SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN status=2 THEN 1 ELSE 0 END) AS inactive
        FROM categories
        WHERE business_id = :business_id AND branch_id = :branch_id
    ");
    $statsStmt->execute($scope);

    jsonResponse(true, 'Category list loaded.', ['records'=>$stmt->fetchAll(PDO::FETCH_ASSOC), 'stats'=>$statsStmt->fetch(PDO::FETCH_ASSOC)]);
}

function getRecord(PDO $pdo)
{
    requireMasterPermission('view');
    $scope = scopeData();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid record.');

    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id LIMIT 1");
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

    $name = cleanInput($_POST['category_name'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $status = (int)($_POST['status'] ?? 1);
    if ($name === '') jsonResponse(false, 'Please enter category name.');
    if (!in_array($status, [1,2], true)) $status = 1;
    

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE categories
                SET category_name = :name, description = :description, status = :status 
                WHERE id = :id AND business_id = :business_id AND branch_id = :branch_id
            ");
            $params = [':name'=>$name, ':description'=>$description, ':status'=>$status, ':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']];
            
            $stmt->execute($params);
            jsonResponse(true, 'Category updated successfully.', ['id'=>$id]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO categories (business_id, branch_id, category_name, description, status, created_by)
            VALUES (:business_id, :branch_id, :name, :description, :status, :created_by)
        ");
        $params = [':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id'], ':name'=>$name, ':description'=>$description, ':status'=>$status, ':created_by'=>currentUserId()];
        
        $stmt->execute($params);
        jsonResponse(true, 'Category added successfully.', ['id'=>(int)$pdo->lastInsertId()]);
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
    checkCategoryUsage($pdo, $scope, $id);
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id");
    $stmt->execute([':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    jsonResponse(true, 'Category deleted successfully.');
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
