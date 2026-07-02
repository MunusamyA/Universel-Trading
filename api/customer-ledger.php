<?php

require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';

secureSessionStart();
requireLogin();

$pdo = $GLOBALS['pdo'];

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {

    $customerId = (int)($_GET['customer_id'] ?? 0);
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    $types = $_GET['types'] ?? [];

    if ($customerId <= 0) {
        jsonResponse(false, "Invalid customer");
    }

    $typeFilter = "";
    if (!empty($types)) {
        $list = implode(",", array_map('intval', $types));
        $typeFilter = "AND s.document_type IN ($list)";
    }

    $dateFilter = "";
    if ($from && $to) {
        $dateFilter = "AND DATE(s.sales_date) BETWEEN '$from' AND '$to'";
    }

    $sql = "
    SELECT * FROM (

        SELECT 
            s.sales_date AS date,
            s.sales_no AS ref,
            CONCAT('Sales - ', s.sales_no) AS particular,
            s.grand_total AS debit,
            0 AS credit
        FROM sales s
        WHERE s.customer_id = :cid
        $typeFilter
        $dateFilter

        UNION ALL

        SELECT 
            cp.payment_date AS date,
            cp.payment_no AS ref,
            'Payment Received' AS particular,
            0 AS debit,
            cp.amount AS credit
        FROM customer_payments cp
        WHERE cp.customer_id = :cid

    ) t
    ORDER BY date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cid' => $customerId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $balance = 0;
    foreach ($rows as &$r) {
        $balance += ($r['debit'] - $r['credit']);
        $r['balance'] = $balance;
    }

    jsonResponse(true, "OK", $rows);
}