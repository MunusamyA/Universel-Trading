<?php
/**
 * Stock Movement Helper
 * ------------------------------------------------------------
 * Common helper for Purchase IN, Sales OUT and reverse entries.
 * Safe to include from api/purchases.php, api/sales.php and product opening stock.
 */

if (!function_exists('sm_table_exists')) {
    function sm_table_exists(PDO $pdo, $tableName)
    {
        static $cache = [];
        $tableName = (string)$tableName;

        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        $stmt = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM INFORMATION_SCHEMA.TABLES\n            WHERE TABLE_SCHEMA = DATABASE()\n            AND TABLE_NAME = :table_name\n        ");
        $stmt->execute([':table_name' => $tableName]);

        $cache[$tableName] = ((int)$stmt->fetchColumn() > 0);
        return $cache[$tableName];
    }
}

if (!function_exists('sm_column_exists')) {
    function sm_column_exists(PDO $pdo, $tableName, $columnName)
    {
        static $cache = [];
        $key = (string)$tableName . '.' . (string)$columnName;

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $stmt = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM INFORMATION_SCHEMA.COLUMNS\n            WHERE TABLE_SCHEMA = DATABASE()\n            AND TABLE_NAME = :table_name\n            AND COLUMN_NAME = :column_name\n        ");
        $stmt->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName
        ]);

        $cache[$key] = ((int)$stmt->fetchColumn() > 0);
        return $cache[$key];
    }
}

if (!function_exists('sm_current_user_id')) {
    function sm_current_user_id()
    {
        if (function_exists('currentUserIdSafe')) {
            return (int)currentUserIdSafe();
        }

        if (function_exists('currentUserId')) {
            return (int)currentUserId();
        }

        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('stockProductBalance')) {
    function stockProductBalance(PDO $pdo, array $scope, $productId)
    {
        $productId = (int)$productId;

        if ($productId <= 0 || !sm_table_exists($pdo, 'purchase_items')) {
            return 0.0;
        }

        $stmt = $pdo->prepare("\n            SELECT COALESCE(SUM(available_qty), 0)\n            FROM purchase_items\n            WHERE business_id = :business_id\n            AND branch_id = :branch_id\n            AND product_id = :product_id\n            AND status = 1\n        ");
        $stmt->execute([
            ':business_id' => (int)($scope['business_id'] ?? 0),
            ':branch_id' => (int)($scope['branch_id'] ?? 0),
            ':product_id' => $productId
        ]);

        return round((float)$stmt->fetchColumn(), 4);
    }
}

if (!function_exists('addStockMovement')) {
    function addStockMovement(PDO $pdo, array $scope, array $data)
    {
        if (!sm_table_exists($pdo, 'stock_movements')) {
            return 0;
        }

        $movementType = strtoupper(trim((string)($data['movement_type'] ?? 'IN')));
        if (!in_array($movementType, ['IN', 'OUT'], true)) {
            $movementType = 'IN';
        }

        $movementDate = trim((string)($data['movement_date'] ?? date('Y-m-d')));
        if ($movementDate === '' || strtotime($movementDate) === false) {
            $movementDate = date('Y-m-d');
        } else {
            $movementDate = date('Y-m-d', strtotime($movementDate));
        }

        $row = [
            'business_id' => (int)($scope['business_id'] ?? 0),
            'branch_id' => (int)($scope['branch_id'] ?? 0),
            'product_id' => (int)($data['product_id'] ?? 0),
            'purchase_id' => !empty($data['purchase_id']) ? (int)$data['purchase_id'] : null,
            'purchase_item_id' => !empty($data['purchase_item_id']) ? (int)$data['purchase_item_id'] : null,
            'sales_id' => !empty($data['sales_id']) ? (int)$data['sales_id'] : null,
            'sales_item_id' => !empty($data['sales_item_id']) ? (int)$data['sales_item_id'] : null,
            'movement_date' => $movementDate,
            'movement_type' => $movementType,
            'source_type' => substr(trim((string)($data['source_type'] ?? 'MANUAL')), 0, 50),
            'source_no' => isset($data['source_no']) ? substr(trim((string)$data['source_no']), 0, 100) : null,
            'batch_no' => isset($data['batch_no']) ? substr(trim((string)$data['batch_no']), 0, 100) : null,
            'product_code' => isset($data['product_code']) ? substr(trim((string)$data['product_code']), 0, 100) : null,
            'product_name' => isset($data['product_name']) ? substr(trim((string)$data['product_name']), 0, 255) : null,
            'qty' => round((float)($data['qty'] ?? 0), 4),
            'rate' => round((float)($data['rate'] ?? 0), 4),
            'amount' => round((float)($data['amount'] ?? 0), 2),
            'before_qty' => round((float)($data['before_qty'] ?? 0), 4),
            'after_qty' => round((float)($data['after_qty'] ?? 0), 4),
            'remarks' => isset($data['remarks']) ? trim((string)$data['remarks']) : null,
            'status' => (int)($data['status'] ?? 1),
            'created_by' => !empty($data['created_by']) ? (int)$data['created_by'] : sm_current_user_id()
        ];

        if ($row['business_id'] <= 0 || $row['branch_id'] <= 0 || $row['product_id'] <= 0 || $row['qty'] <= 0) {
            return 0;
        }

        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($row as $column => $value) {
            if (!sm_column_exists($pdo, 'stock_movements', $column)) {
                continue;
            }

            $columns[] = $column;
            $placeholders[] = ':' . $column;
            $params[':' . $column] = $value;
        }

        if (!$columns) {
            return 0;
        }

        $stmt = $pdo->prepare("\n            INSERT INTO stock_movements\n            (" . implode(', ', $columns) . ")\n            VALUES\n            (" . implode(', ', $placeholders) . ")\n        ");
        $stmt->execute($params);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('markStockMovementsInactive')) {
    function markStockMovementsInactive(PDO $pdo, array $scope, array $filters)
    {
        if (!sm_table_exists($pdo, 'stock_movements')) {
            return 0;
        }

        $where = [
            'business_id = :business_id',
            'branch_id = :branch_id',
            'status = 1'
        ];
        $params = [
            ':business_id' => (int)($scope['business_id'] ?? 0),
            ':branch_id' => (int)($scope['branch_id'] ?? 0)
        ];

        $allowed = [
            'product_id', 'purchase_id', 'purchase_item_id',
            'sales_id', 'sales_item_id', 'source_type', 'source_no', 'batch_no'
        ];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $filters) || $filters[$key] === '' || $filters[$key] === null) {
                continue;
            }

            if (!sm_column_exists($pdo, 'stock_movements', $key)) {
                continue;
            }

            $where[] = $key . ' = :' . $key;
            $params[':' . $key] = $filters[$key];
        }

        $stmt = $pdo->prepare("\n            UPDATE stock_movements\n            SET status = 2\n            WHERE " . implode(' AND ', $where) . "\n        ");
        $stmt->execute($params);

        return $stmt->rowCount();
    }
}
