<?php
$footerBranchName = 'SM Traders';

try {
    if (isset($pdo) && $pdo instanceof PDO && function_exists('currentBusinessId') && function_exists('currentBranchId')) {
        $businessId = (int)currentBusinessId();
        $branchId = (int)currentBranchId();

        if ($businessId > 0 && $branchId > 0) {
            $stmt = $pdo->prepare("
                SELECT branch_name
                FROM branches
                WHERE id = :branch_id
                AND business_id = :business_id
                LIMIT 1
            ");
            $stmt->execute([
                ':branch_id' => $branchId,
                ':business_id' => $businessId
            ]);

            $branchName = trim((string)$stmt->fetchColumn());

            if ($branchName !== '') {
                $footerBranchName = $branchName;
            }
        }
    }
} catch (Throwable $e) {
    $footerBranchName = 'SM Traders';
}
?>

<footer class="footer">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 text-center text-sm-end">
                © <script>document.write(new Date().getFullYear())</script>
                <strong><?= htmlspecialchars($footerBranchName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="d-none d-sm-inline-block">
                    - All Rights Reserved.
                </span>
            </div>
        </div>
    </div>
</footer>