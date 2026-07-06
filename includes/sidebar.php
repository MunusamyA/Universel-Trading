<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** @var PDO $pdo */

$currentPage = basename($_SERVER['PHP_SELF']);
$user = currentLiveUser();

function sidebarUrl($url)
{
    if ($url === '' || $url === '#' || $url === null) {
        return 'javascript: void(0);';
    }

    $url = ltrim($url, '/');

    if (strpos($url, 'pages/') === 0) {
        return BASE_URL . $url;
    }

    return BASE_URL . 'pages/' . $url;
}

function isActiveMenu($url, $currentPage)
{
    if (!$url || $url === '#') {
        return false;
    }

    return basename($url) === $currentPage;
}

function sidebarAccessHasViewOrList($actions)
{
    $actions = parseAccessActions($actions);
    return in_array(1, $actions, true) || in_array(2, $actions, true);
}

$menus = [];

try {
    if ($user && (int)$user['user_status'] === 1) {

        $userType = $user['user_type'] ?? 'business_user';
        $roleId = (int)($user['role_id'] ?? 0);
        $packageRoleId = (int)($user['parent_role_id'] ?? 0);

        if ($userType === 'platform_owner') {
            $stmt = $pdo->prepare("
                SELECT
                    id AS menu_id,
                    parent_id,
                    menu_key,
                    menu_name,
                    menu_url,
                    icon_class,
                    sort_order
                FROM sidebar_menus
                WHERE is_sidebar = 1
                AND status = 1
                AND menu_for IN ('platform', 'both')
                ORDER BY COALESCE(parent_id, 0), sort_order ASC, id ASC
            ");

            $stmt->execute();
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {

            if (
                $roleId > 0 &&
                (int)($user['business_status'] ?? 0) === 1 &&
                (int)($user['approval_status'] ?? 0) === 1 &&
                (int)($user['branch_status'] ?? 0) === 1 &&
                (int)($user['role_status'] ?? 0) === 1
            ) {
                $menuStmt = $pdo->prepare("
                    SELECT
                        id AS menu_id,
                        parent_id,
                        menu_key,
                        menu_name,
                        menu_url,
                        icon_class,
                        sort_order
                    FROM sidebar_menus
                    WHERE is_sidebar = 1
                    AND status = 1
                    AND menu_for IN ('customer', 'both')
                    ORDER BY COALESCE(parent_id, 0), sort_order ASC, id ASC
                ");
                $menuStmt->execute();
                $allMenus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);

                $accessRoleId = 0;
                $ownPermissionMode = roleHasOwnPermissionRows($roleId);

                if ($ownPermissionMode) {
                    $accessRoleId = $roleId;
                } elseif ($packageRoleId > 0) {
                    $accessRoleId = $packageRoleId;
                }

                $accessMap = [];

                if ($accessRoleId > 0) {
                    $accessStmt = $pdo->prepare("
                        SELECT menu_id, access_actions, status
                        FROM role_base_access
                        WHERE role_id = :role_id
                    ");

                    $accessStmt->execute([
                        ':role_id' => $accessRoleId
                    ]);

                    while ($accessRow = $accessStmt->fetch(PDO::FETCH_ASSOC)) {
                        $accessMap[(int)$accessRow['menu_id']] = [
                            'access_actions' => $accessRow['access_actions'] ?? '',
                            'status' => (int)($accessRow['status'] ?? 0)
                        ];
                    }
                }

                $allowedMenuIds = [];

                foreach ($allMenus as $menu) {
                    $menuId = (int)$menu['menu_id'];

                    if (!isset($accessMap[$menuId])) {
                        continue;
                    }

                    if ((int)$accessMap[$menuId]['status'] !== 1) {
                        continue;
                    }

                    if (sidebarAccessHasViewOrList($accessMap[$menuId]['access_actions'])) {
                        $allowedMenuIds[$menuId] = true;
                    }
                }

                foreach ($allMenus as $menu) {
                    $menuId = (int)$menu['menu_id'];
                    $parentId = (int)($menu['parent_id'] ?? 0);

                    if ($parentId > 0 && isset($allowedMenuIds[$menuId])) {
                        $allowedMenuIds[$parentId] = true;
                    }
                }

                foreach ($allMenus as $menu) {
                    $menuId = (int)$menu['menu_id'];

                    if (isset($allowedMenuIds[$menuId])) {
                        $menus[] = $menu;
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    $menus = [];
}

$parentMenus = [];
$childMenus = [];

foreach ($menus as $menu) {
    if (empty($menu['parent_id'])) {
        $parentMenus[] = $menu;
    } else {
        $childMenus[(int)$menu['parent_id']][] = $menu;
    }
}
?>

<div id="sidebar-menu">
    <ul class="metismenu list-unstyled" id="side-menu">

        <?php if (empty($parentMenus)) { ?>
            <li>
                <a href="<?= BASE_URL; ?>pages/dashboard.php" class="waves-effect">
                    <i class="dripicons-device-desktop"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        <?php } else { ?>

            <?php foreach ($parentMenus as $parent) { ?>
                <?php
                $parentId = (int)$parent['menu_id'];
                $hasChild = isset($childMenus[$parentId]) && !empty($childMenus[$parentId]);
                $parentUrl = sidebarUrl($parent['menu_url']);
                $parentIcon = !empty($parent['icon_class']) ? $parent['icon_class'] : 'dripicons-chevron-right';
                $parentName = $parent['menu_name'];
                $parentActive = isActiveMenu($parent['menu_url'], $currentPage);
                $childActive = false;

                if ($hasChild) {
                    foreach ($childMenus[$parentId] as $childCheck) {
                        if (isActiveMenu($childCheck['menu_url'], $currentPage)) {
                            $childActive = true;
                            break;
                        }
                    }
                }

                $liClass = ($parentActive || $childActive) ? 'mm-active' : '';
                $aClass = $hasChild ? 'has-arrow waves-effect' : 'waves-effect';
                ?>

                <li class="<?= $liClass; ?>">
                    <a href="<?= $hasChild ? 'javascript: void(0);' : htmlspecialchars($parentUrl); ?>" class="<?= $aClass; ?>">
                        <i class="<?= htmlspecialchars($parentIcon); ?>"></i>
                        <span><?= htmlspecialchars($parentName); ?></span>
                    </a>

                    <?php if ($hasChild) { ?>
                        <ul class="sub-menu <?= $childActive ? 'mm-show' : ''; ?>" aria-expanded="<?= $childActive ? 'true' : 'false'; ?>">
                            <?php foreach ($childMenus[$parentId] as $child) { ?>
                                <?php
                                $childUrl = sidebarUrl($child['menu_url']);
                                $childName = $child['menu_name'];
                                $activeClass = isActiveMenu($child['menu_url'], $currentPage) ? 'active' : '';
                                ?>
                                <li>
                                    <a href="<?= htmlspecialchars($childUrl); ?>" class="<?= $activeClass; ?>">
                                        <?= htmlspecialchars($childName); ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </li>
            <?php } ?>

        <?php } ?>
    </ul>
</div>
