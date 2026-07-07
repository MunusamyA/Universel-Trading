<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'Stock Movement | Universal ERP';
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
</head>
<body data-sidebar="dark">
<?php include BASE_PATH . 'includes/pre-loader.php'; ?>

<div id="layout-wrapper">
    <?php include BASE_PATH . 'includes/topbar.php'; ?>

    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include BASE_PATH . 'includes/sidebar.php'; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">Stock Movement</h4>
                            <button type="button" class="btn btn-primary btn-sm" id="reloadBtn">
                                <i class="mdi mdi-refresh me-1"></i> Reload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="card"><div class="card-body"><div class="text-muted">Total IN</div><h4 id="totalIn">0</h4></div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card"><div class="card-body"><div class="text-muted">Total OUT</div><h4 id="totalOut">0</h4></div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card"><div class="card-body"><div class="text-muted">Net Movement</div><h4 id="netMovement">0</h4></div></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="search" placeholder="Product / Code / Source / Batch">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="movement_type">
                                    <option value="">All Type</option>
                                    <option value="IN">IN</option>
                                    <option value="OUT">OUT</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="source_type">
                                    <option value="">All Source</option>
                                    <option value="PURCHASE">Purchase</option>
                                    <option value="SALES">Sales</option>
                                    <option value="SALE_REVERSE">Sale Reverse</option>
                                    <option value="PURCHASE_EDIT_REVERSE">Purchase Edit Reverse</option>
                                    <option value="PURCHASE_DELETE">Purchase Delete</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" id="from_date">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" id="to_date">
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-success w-100" id="filterBtn">Go</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Product</th>
                                    <th>Batch</th>
                                    <th>Source</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Before</th>
                                    <th class="text-end">After</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="movementRows">
                                <tr><td colspan="9" class="text-center text-muted">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
        <?php include BASE_PATH . 'includes/footer.php'; ?>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>
<script>window.BASE_URL = <?= json_encode(BASE_URL); ?>;</script>
<script>
$(document).ready(function () {
    loadStockMovement();

    $('#reloadBtn,#filterBtn').on('click', loadStockMovement);
    $('#search').on('keypress', function (e) {
        if (e.which === 13) {
            loadStockMovement();
        }
    });

    function esc(v) {
        return $('<div>').text(v == null ? '' : v).html();
    }

    function qty(v) {
        return parseFloat(v || 0).toFixed(2);
    }

    function loadStockMovement() {
        $('#movementRows').html('<tr><td colspan="9" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: window.BASE_URL + 'api/stock-movements.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list_movements',
                search: $('#search').val(),
                movement_type: $('#movement_type').val(),
                source_type: $('#source_type').val(),
                from_date: $('#from_date').val(),
                to_date: $('#to_date').val()
            },
            success: function (res) {
                if (!res.status) {
                    $('#movementRows').html('<tr><td colspan="9" class="text-center text-danger">' + esc(res.message || 'Unable to load') + '</td></tr>');
                    return;
                }

                let rows = res.data.rows || [];
                let s = res.data.summary || {};
                let totalIn = parseFloat(s.total_in || 0);
                let totalOut = parseFloat(s.total_out || 0);

                $('#totalIn').text(qty(totalIn));
                $('#totalOut').text(qty(totalOut));
                $('#netMovement').text(qty(totalIn - totalOut));

                if (rows.length === 0) {
                    $('#movementRows').html('<tr><td colspan="9" class="text-center text-muted">No stock movement found.</td></tr>');
                    return;
                }

                let html = '';
                rows.forEach(function (r) {
                    let badge = r.movement_type === 'IN'
                        ? '<span class="badge bg-success">IN</span>'
                        : '<span class="badge bg-danger">OUT</span>';

                    html += '<tr>' +
                        '<td>' + esc(r.movement_date) + '</td>' +
                        '<td>' + badge + '</td>' +
                        '<td><strong>' + esc(r.product_name) + '</strong><br><small class="text-muted">' + esc(r.product_code) + '</small></td>' +
                        '<td>' + esc(r.batch_no) + '</td>' +
                        '<td>' + esc(r.source_type) + '<br><small class="text-muted">' + esc(r.source_no) + '</small></td>' +
                        '<td class="text-end fw-bold">' + qty(r.qty) + '</td>' +
                        '<td class="text-end">' + qty(r.before_qty) + '</td>' +
                        '<td class="text-end">' + qty(r.after_qty) + '</td>' +
                        '<td>' + esc(r.remarks) + '</td>' +
                    '</tr>';
                });

                $('#movementRows').html(html);
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                $('#movementRows').html('<tr><td colspan="9" class="text-center text-danger">Server error.</td></tr>');
            }
        });
    }
});
</script>
</body>
</html>
