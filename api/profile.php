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
    case 'get_profile':
        getProfile($pdo);
        break;

    case 'update_profile':
        verifyCsrfToken();
        updateProfile($pdo);
        break;

    case 'change_password':
        verifyCsrfToken();
        changePassword($pdo);
        break;

    default:
        jsonResponse(false, 'Invalid action.');
}

function profileUserId() {
    $userId = function_exists('currentUserId') ? (int)currentUserId() : 0;

    if ($userId <= 0 && isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    }

    if ($userId <= 0) {
        jsonResponse(false, 'Invalid user session.');
    }

    return $userId;
}

function getProfile(PDO $pdo) {
    $userId = profileUserId();

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.business_id,
            u.branch_id,
            u.role_id,
            u.name,
            u.username,
            u.email,
            u.mobile,
            u.user_type,
            u.status,
            u.created_at,
            r.role_name,
            b.business_name,
            br.branch_name
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        LEFT JOIN businesses b ON b.id = u.business_id
        LEFT JOIN branches br ON br.id = u.branch_id
        WHERE u.id = :user_id
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonResponse(false, 'User not found.');
    }

    jsonResponse(true, 'Profile loaded.', ['user' => $user]);
}

function updateProfile(PDO $pdo) {
    $userId = profileUserId();

    $name = cleanInput($_POST['name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $mobile = cleanInput($_POST['mobile'] ?? '');

    if ($name === '') {
        jsonResponse(false, 'Please enter name.');
    }

    if (strlen($name) > 150) {
        jsonResponse(false, 'Name should be within 150 characters.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Please enter valid email.');
    }

    if (strlen($email) > 150) {
        jsonResponse(false, 'Email should be within 150 characters.');
    }

    if (strlen($mobile) > 20) {
        jsonResponse(false, 'Mobile should be within 20 characters.');
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET name = :name,
            email = :email,
            mobile = :mobile
        WHERE id = :user_id
    ");
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':mobile' => $mobile,
        ':user_id' => $userId
    ]);

    logProfileActivity($pdo, $userId, 'profile', 'UPDATE', 'Profile details updated');

    jsonResponse(true, 'Profile updated successfully.');
}

function changePassword(PDO $pdo) {
    $userId = profileUserId();

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '') {
        jsonResponse(false, 'Please enter current password.');
    }

    if (strlen($newPassword) < 6) {
        jsonResponse(false, 'New password must be minimum 6 characters.');
    }

    if ($newPassword !== $confirmPassword) {
        jsonResponse(false, 'Confirm password does not match.');
    }

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $userId]);
    $hash = (string)$stmt->fetchColumn();

    if ($hash === '') {
        jsonResponse(false, 'User password not found.');
    }

    if (!password_verify($currentPassword, $hash)) {
        jsonResponse(false, 'Current password is wrong.');
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

    $update = $pdo->prepare("
        UPDATE users
        SET password = :password
        WHERE id = :user_id
    ");
    $update->execute([
        ':password' => $newHash,
        ':user_id' => $userId
    ]);

    logProfileActivity($pdo, $userId, 'profile', 'PASSWORD_CHANGE', 'User password changed');

    jsonResponse(true, 'Password changed successfully.');
}

function logProfileActivity(PDO $pdo, int $userId, string $moduleKey, string $actionType, string $description) {
    try {
        $stmt = $pdo->prepare("
            SELECT business_id, branch_id
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return;
        }

        $log = $pdo->prepare("
            INSERT INTO activity_logs
                (business_id, branch_id, user_id, module_key, action_type, description, ip_address)
            VALUES
                (:business_id, :branch_id, :user_id, :module_key, :action_type, :description, :ip_address)
        ");
        $log->execute([
            ':business_id' => $user['business_id'] ?: null,
            ':branch_id' => $user['branch_id'] ?: null,
            ':user_id' => $userId,
            ':module_key' => $moduleKey,
            ':action_type' => $actionType,
            ':description' => $description,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {
        // Profile save should not fail because of activity log.
    }
}
