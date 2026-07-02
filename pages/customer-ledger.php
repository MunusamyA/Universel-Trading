<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = "Customer Ledger";
?>

<!doctype html>
<html>
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
</head>

<body data-sidebar="dark">
<?php include BASE_PATH . 'includes/topbar.php'; ?>
<?php include BASE_PATH . 'includes/sidebar.php'; ?>

<div class="main-content">
<div class="page-content">
<div class="container-fluid">

<div class="page-title-box">
    <h4>Customer Ledger</h4>
</div>

<!-- FILTER -->
<div class="card">
<div class="card-body">

<div class="row">

    <div class="col-md-3">
        <label>Customer</label>
        <select id="customerId" class="form-select"></select>
    </div>

    <div class="col-md-2">
        <label>From</label>
        <input type="date" id="fromDate" class="form-control">
    </div>

    <div class="col-md-2">
        <label>To</label>
        <input type="date" id="toDate" class="form-control">
    </div>

    <div class="col-md-5">
        <label>Document Type</label><br>

        <label><input type="checkbox" class="docType" value="1"> Quotation</label>
        <label><input type="checkbox" class="docType" value="2"> Proforma</label>
        <label><input type="checkbox" class="docType" value="3"> Sales Bill</label>
        <label><input type="checkbox" class="docType" value="4"> Direct</label>
        <label><input type="checkbox" class="docType" value="5"> Final</label>
        <label><input type="checkbox" class="docType" value="99"> Payments</label>

    </div>

</div>

<br>

<button class="btn btn-primary" id="filterBtn">Filter</button>

</div>
</div>

<!-- TABLE -->
<div class="card">
<div class="card-body">

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Date</th>
            <th>Particular</th>
            <th class="text-end">Debit</th>
            <th class="text-end">Credit</th>
            <th class="text-end">Balance</th>
        </tr>
    </thead>
    <tbody id="ledgerBody"></tbody>
</table>

</div>
</div>

</div>
</div>
</div>

<script src="<?= BASE_URL ?>pages-js/customer-ledger.js"></script>
</body>
</html>