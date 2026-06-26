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
    case 'list_employees': listEmployees($pdo); break;
    case 'get_employee': getEmployee($pdo); break;
    case 'get_roles': getBranchRoles($pdo); break;
    case 'save_employee': verifyCsrfToken(); saveEmployee($pdo); break;
    case 'delete_employee': verifyCsrfToken(); deleteEmployee($pdo); break;
    default: jsonResponse(false, 'Invalid action.');
}

function requireEmployeePermission($action = 'view') {
    if (isPlatformOwner()) return;
    if (function_exists('hasPermission') && !hasPermission('employees', $action)) {
        jsonResponse(false, 'Permission denied.');
    }
}

function scopeData() {
    $businessId = (int)currentBusinessId();
    $branchId = (int)currentBranchId();
    if ($businessId <= 0 || $branchId <= 0) jsonResponse(false, 'Invalid business or branch session.');
    return ['business_id'=>$businessId, 'branch_id'=>$branchId];
}

function listEmployees(PDO $pdo) {
    requireEmployeePermission('view');
    $scope = scopeData();
    $search = cleanInput($_GET['search'] ?? '');
    $status = (int)($_GET['status'] ?? 0);
    $roleId = (int)($_GET['role_id'] ?? 0);

    $where = " WHERE e.business_id=:business_id AND e.branch_id=:branch_id ";
    $params = [':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']];

    if ($status === 1 || $status === 2) { $where .= " AND e.status=:status "; $params[':status']=$status; }
    if ($roleId > 0) { $where .= " AND e.role_id=:role_id "; $params[':role_id']=$roleId; }
    if ($search !== '') {
        $where .= " AND (e.employee_code LIKE :search OR e.employee_name LIKE :search OR u.username LIKE :search OR e.email LIKE :search OR e.mobile LIKE :search OR r.role_name LIKE :search) ";
        $params[':search'] = "%$search%";
    }

    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.user_id,
            e.employee_code,
            e.employee_name,
            e.mobile,
            e.email,
            e.designation,
            e.joining_date,
            e.salary,
            e.role_id,
            e.status,
            e.created_at,
            u.username,
            r.role_name
        FROM employees e
        INNER JOIN users u ON u.id=e.user_id
        LEFT JOIN roles r ON r.id=e.role_id
        $where
        ORDER BY e.id DESC
    ");
    $stmt->execute($params);

    $statsStmt = $pdo->prepare("
        SELECT COUNT(*) total_employees,
               SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) active_employees,
               SUM(CASE WHEN status=2 THEN 1 ELSE 0 END) inactive_employees
        FROM employees
        WHERE business_id=:business_id AND branch_id=:branch_id
    ");
    $statsStmt->execute($scope);

    jsonResponse(true, 'Employees loaded.', ['employees'=>$stmt->fetchAll(PDO::FETCH_ASSOC), 'stats'=>$statsStmt->fetch(PDO::FETCH_ASSOC)]);
}

function getEmployee(PDO $pdo) {
    requireEmployeePermission('view');
    $scope = scopeData();
    $id = (int)($_GET['employee_id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid employee.');

    $stmt = $pdo->prepare("
        SELECT
            e.*,
            u.username
        FROM employees e
        INNER JOIN users u ON u.id=e.user_id
        WHERE e.id=:id AND e.business_id=:business_id AND e.branch_id=:branch_id
        LIMIT 1
    ");
    $stmt->execute([':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) jsonResponse(false, 'Employee not found.');

    jsonResponse(true, 'Employee loaded.', ['employee'=>$employee]);
}

function getBranchRoles(PDO $pdo) {
    requireEmployeePermission('view');
    $scope = scopeData();

    $stmt = $pdo->prepare("
        SELECT id, role_name, description
        FROM roles
        WHERE business_id=:business_id
        AND branch_id=:branch_id
        AND role_type=2
        AND status=1
        AND is_locked=0
        ORDER BY role_name ASC
    ");
    $stmt->execute([':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    jsonResponse(true, 'Roles loaded.', ['roles'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function saveEmployee(PDO $pdo) {
    $scope = scopeData();
    $id = (int)($_POST['employee_id'] ?? 0);
    requireEmployeePermission($id > 0 ? 'edit' : 'add');

    $employeeCode = cleanInput($_POST['employee_code'] ?? '');
    $name = cleanInput($_POST['employee_name'] ?? ($_POST['name'] ?? ''));
    $username = cleanInput($_POST['username'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $mobile = cleanInput($_POST['mobile'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 0);
    $gender = (int)($_POST['gender'] ?? 0);
    $dob = cleanInput($_POST['dob'] ?? '');
    $joiningDate = cleanInput($_POST['joining_date'] ?? '');
    $designation = cleanInput($_POST['designation'] ?? '');
    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? '');
    $pincode = cleanInput($_POST['pincode'] ?? '');
    $salary = round((float)($_POST['salary'] ?? 0), 2);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $status = (int)($_POST['status'] ?? 1);

    if ($name === '') jsonResponse(false, 'Please enter employee name.');
    if ($username === '') jsonResponse(false, 'Please enter username.');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) jsonResponse(false, 'Username minimum 3 characters. Use letters, numbers, dot, hyphen, underscore only.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Please enter valid email.');
    if ($mobile !== '' && !preg_match('/^[0-9]{10}$/', $mobile)) jsonResponse(false, 'Please enter valid 10 digit mobile.');
    if ($roleId <= 0) jsonResponse(false, 'Please select role.');
    validateBranchRole($pdo, $scope, $roleId);
    if (!in_array($gender, [0,1,2,3], true)) $gender = 0;
    if (!in_array($status, [1,2], true)) $status = 1;
    if ($salary < 0) $salary = 0;

    if ($id <= 0 && trim($password) === '') jsonResponse(false, 'Please enter password.');
    if (trim($password) !== '') {
        if (strlen($password) < 6) jsonResponse(false, 'Password must be minimum 6 characters.');
        if ($password !== $confirmPassword) jsonResponse(false, 'Password and confirm password do not match.');
    }

    ensureUsernameUnique($pdo, $username, $id);
    ensureEmployeeCodeUnique($pdo, $scope, $employeeCode, $id);

    try {
        $pdo->beginTransaction();

        if ($id > 0) {
            $check = $pdo->prepare("
                SELECT e.id, e.user_id
                FROM employees e
                WHERE e.id=:id AND e.business_id=:business_id AND e.branch_id=:branch_id
                LIMIT 1
            ");
            $check->execute([':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if (!$existing) throw new Exception('Employee not found.');

            $userSql = "UPDATE users SET name=:name, username=:username, email=:email, mobile=:mobile, role_id=:role_id, status=:status";
            $userParams = [
                ':name'=>$name, ':username'=>$username, ':email'=>$email, ':mobile'=>$mobile,
                ':role_id'=>$roleId, ':status'=>$status, ':user_id'=>$existing['user_id']
            ];
            if (trim($password) !== '') { $userSql .= ", password=:password"; $userParams[':password'] = hashPassword($password); }
            $userSql .= " WHERE id=:user_id";
            $pdo->prepare($userSql)->execute($userParams);

            $stmt = $pdo->prepare("
                UPDATE employees
                SET role_id=:role_id,
                    employee_code=:employee_code,
                    employee_name=:employee_name,
                    mobile=:mobile,
                    email=:email,
                    gender=:gender,
                    dob=:dob,
                    joining_date=:joining_date,
                    designation=:designation,
                    address=:address,
                    city=:city,
                    state=:state,
                    pincode=:pincode,
                    salary=:salary,
                    status=:status
                WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id
            ");
            $stmt->execute([
                ':role_id'=>$roleId, ':employee_code'=>$employeeCode, ':employee_name'=>$name,
                ':mobile'=>$mobile, ':email'=>$email, ':gender'=>$gender ?: null,
                ':dob'=>$dob ?: null, ':joining_date'=>$joiningDate ?: null, ':designation'=>$designation,
                ':address'=>$address, ':city'=>$city, ':state'=>$state, ':pincode'=>$pincode,
                ':salary'=>$salary, ':status'=>$status, ':id'=>$id,
                ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']
            ]);

            $pdo->commit();
            jsonResponse(true, 'Employee updated successfully.', ['employee_id'=>$id, 'redirect'=>BASE_URL.'pages/employees.php']);
        }

        $userStmt = $pdo->prepare("
            INSERT INTO users (business_id, branch_id, role_id, name, username, email, mobile, password, user_type, status)
            VALUES (:business_id, :branch_id, :role_id, :name, :username, :email, :mobile, :password, 'business_user', :status)
        ");
        $userStmt->execute([
            ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id'], ':role_id'=>$roleId,
            ':name'=>$name, ':username'=>$username, ':email'=>$email, ':mobile'=>$mobile,
            ':password'=>hashPassword($password), ':status'=>$status
        ]);
        $userId = (int)$pdo->lastInsertId();

        if ($employeeCode === '') {
            $employeeCode = 'EMP' . str_pad($userId, 5, '0', STR_PAD_LEFT);
        }

        $empStmt = $pdo->prepare("
            INSERT INTO employees
            (
                business_id, branch_id, user_id, role_id, employee_code, employee_name,
                mobile, email, gender, dob, joining_date, designation, address, city, state,
                pincode, salary, status, created_by
            )
            VALUES
            (
                :business_id, :branch_id, :user_id, :role_id, :employee_code, :employee_name,
                :mobile, :email, :gender, :dob, :joining_date, :designation, :address, :city, :state,
                :pincode, :salary, :status, :created_by
            )
        ");
        $empStmt->execute([
            ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id'], ':user_id'=>$userId,
            ':role_id'=>$roleId, ':employee_code'=>$employeeCode, ':employee_name'=>$name,
            ':mobile'=>$mobile, ':email'=>$email, ':gender'=>$gender ?: null,
            ':dob'=>$dob ?: null, ':joining_date'=>$joiningDate ?: null, ':designation'=>$designation,
            ':address'=>$address, ':city'=>$city, ':state'=>$state, ':pincode'=>$pincode,
            ':salary'=>$salary, ':status'=>$status, ':created_by'=>currentUserId()
        ]);

        $employeeId = (int)$pdo->lastInsertId();
        $pdo->commit();

        jsonResponse(true, 'Employee added successfully.', ['employee_id'=>$employeeId, 'redirect'=>BASE_URL.'pages/employees.php']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, $e->getMessage() ?: 'Employee save failed.');
    }
}

function deleteEmployee(PDO $pdo) {
    requireEmployeePermission('delete');
    $scope = scopeData();
    $id = (int)($_POST['employee_id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid employee.');

    $stmt = $pdo->prepare("SELECT user_id FROM employees WHERE id=:id AND business_id=:business_id AND branch_id=:branch_id LIMIT 1");
    $stmt->execute([':id'=>$id, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) jsonResponse(false, 'Employee not found.');
    if ((int)$emp['user_id'] === (int)($_SESSION['user_id'] ?? 0)) jsonResponse(false, 'You cannot delete your own account.');

    $pdo->prepare("DELETE FROM users WHERE id=:user_id")->execute([':user_id'=>$emp['user_id']]);
    jsonResponse(true, 'Employee deleted successfully.');
}

function validateBranchRole(PDO $pdo, array $scope, $roleId) {
    $stmt = $pdo->prepare("
        SELECT id FROM roles
        WHERE id=:role_id AND business_id=:business_id AND branch_id=:branch_id
        AND role_type=2 AND status=1 AND is_locked=0
        LIMIT 1
    ");
    $stmt->execute([':role_id'=>$roleId, ':business_id'=>$scope['business_id'], ':branch_id'=>$scope['branch_id']]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) jsonResponse(false, 'Invalid role selected. Select only this branch role.');
}

function ensureUsernameUnique(PDO $pdo, $username, $employeeId=0) {
    $sql = "SELECT u.id FROM users u";
    $params = [':username'=>$username];

    if ($employeeId > 0) {
        $sql .= " LEFT JOIN employees e ON e.user_id=u.id WHERE u.username=:username AND e.id<>:employee_id LIMIT 1";
        $params[':employee_id'] = $employeeId;
    } else {
        $sql .= " WHERE u.username=:username LIMIT 1";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) jsonResponse(false, 'Username already exists.');
}

function ensureEmployeeCodeUnique(PDO $pdo, array $scope, $code, $employeeId=0) {
    if ($code === '') return;

    $stmt = $pdo->prepare("
        SELECT id FROM employees
        WHERE employee_code=:code AND business_id=:business_id AND branch_id=:branch_id AND id<>:id
        LIMIT 1
    ");
    $stmt->execute([
        ':code'=>$code, ':business_id'=>$scope['business_id'],
        ':branch_id'=>$scope['branch_id'], ':id'=>$employeeId
    ]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) jsonResponse(false, 'Employee code already exists.');
}
