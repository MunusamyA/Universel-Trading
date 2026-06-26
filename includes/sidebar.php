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

    if ($url === 'dashboard.php' || $url === 'index.php' || $url === 'login.php') {
        return BASE_URL . $url;
    }

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

$menus = [];

try {
    if ($user && (int)$user['user_status'] === 1) {

        $userType = $user['user_type'] ?? 'business_user';
        $roleId = (int)($user['role_id'] ?? 0);

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

                $stmt = $pdo->prepare("
                    SELECT DISTINCT
                        sm.id AS menu_id,
                        sm.parent_id,
                        sm.menu_key,
                        sm.menu_name,
                        sm.menu_url,
                        sm.icon_class,
                        sm.sort_order
                    FROM sidebar_menus sm
                    INNER JOIN roles r
                        ON r.id = :role_id
                        AND r.status = 1
                    LEFT JOIN role_menu_permissions rmp_direct
                        ON rmp_direct.menu_id = sm.id
                        AND rmp_direct.role_id = r.id
                        AND rmp_direct.can_view = 1
                        AND rmp_direct.status = 1
                    LEFT JOIN role_menu_permissions rmp_package
                        ON rmp_package.menu_id = sm.id
                        AND rmp_package.role_id = r.parent_role_id
                        AND rmp_package.can_view = 1
                        AND rmp_package.status = 1
                    WHERE sm.status = 1
                    AND sm.is_sidebar = 1
                    AND (
                        rmp_direct.id IS NOT NULL
                        OR rmp_package.id IS NOT NULL
                        OR sm.id IN (
                            SELECT child_sm.parent_id
                            FROM sidebar_menus child_sm
                            LEFT JOIN role_menu_permissions child_direct
                                ON child_direct.menu_id = child_sm.id
                                AND child_direct.role_id = r.id
                                AND child_direct.can_view = 1
                                AND child_direct.status = 1
                            LEFT JOIN role_menu_permissions child_package
                                ON child_package.menu_id = child_sm.id
                                AND child_package.role_id = r.parent_role_id
                                AND child_package.can_view = 1
                                AND child_package.status = 1
                            WHERE child_sm.parent_id IS NOT NULL
                            AND child_sm.status = 1
                            AND child_sm.is_sidebar = 1
                            AND (
                                child_direct.id IS NOT NULL
                                OR child_package.id IS NOT NULL
                            )
                        )
                    )
                    ORDER BY COALESCE(sm.parent_id, 0), sm.sort_order ASC, sm.id ASC
                ");

                $stmt->execute([':role_id' => $roleId]);
                $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <a href="<?= BASE_URL; ?>dashboard.php" class="waves-effect">
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
                        <ul class="sub-menu" aria-expanded="<?= $childActive ? 'true' : 'false'; ?>">
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
