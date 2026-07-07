<?php
require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . 'includes/db.php';
require_once BASE_PATH . 'includes/security.php';
require_once BASE_PATH . 'includes/auth.php';

secureSessionStart();
requireLogin();

$page_title = 'All Reports | Universal ERP';
$fromDate = date('Y-m-01');
$toDate = date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
    <?php include BASE_PATH . 'includes/head.php'; ?>
    <style>
        .all-report-shell .hero-card {
            border: 0;
            overflow: hidden;
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-info));
            color: #fff;
        }

        .all-report-shell .hero-card .text-muted {
            color: rgba(255, 255, 255, .78) !important;
        }

        .all-report-shell .filter-card,
        .all-report-shell .report-output-card {
            border: 0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
        }

        .report-group-title {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin: 1rem 0 .75rem;
            font-size: .82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--bs-primary);
        }

        .report-group-title::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--bs-border-color);
        }

        .report-card {
            cursor: pointer;
            border: 1px solid var(--bs-border-color);
            border-radius: 14px;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            background: var(--bs-body-bg);
        }

        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(15, 23, 42, .08);
            border-color: rgba(var(--bs-primary-rgb), .35);
        }

        .report-card.active {
            border-color: var(--bs-primary);
            background: rgba(var(--bs-primary-rgb), .06);
            box-shadow: 0 8px 22px rgba(var(--bs-primary-rgb), .14);
        }

        .report-icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: var(--bs-primary);
            background: rgba(var(--bs-primary-rgb), .10);
            flex: 0 0 42px;
        }

        .summary-tile {
            border: 1px solid var(--bs-border-color);
            border-radius: 12px;
            padding: .75rem;
            background: var(--bs-body-bg);
            height: 100%;
        }

        .summary-tile small {
            display: block;
            margin-bottom: .25rem;
        }

        .report-table-wrap {
            max-height: 62vh;
            overflow: auto;
        }

        .report-table-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--bs-light);
        }

        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
            .card { border: 0 !important; box-shadow: none !important; }
            .report-table-wrap { max-height: none !important; overflow: visible !important; }
        }
    </style>
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
            <div class="container-fluid all-report-shell">

                <div class="row no-print">
                    <div class="col-12">
                        <div class="card hero-card mb-3">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <div>
                                        <h4 class="mb-1 text-white">All Reports</h4>
                                        <p class="mb-0 text-muted">Sales, purchase, stock, GST, outstanding and profit reports in one place.</p>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-light" id="exportReportBtn">
                                            <i class="mdi mdi-file-excel-outline me-1"></i> Export
                                        </button>
                                        <button type="button" class="btn btn-outline-light" id="printReportBtn">
                                            <i class="mdi mdi-printer me-1"></i> Print
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card filter-card no-print">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Report Group</label>
                                <select class="form-select" id="reportGroupSelect">
                                    <option value="">Loading...</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Report Name</label>
                                <select class="form-select" id="reportTypeSelect">
                                    <option value="">Loading...</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-none" id="reportEntityFilterBox">
                                <label class="form-label" id="reportEntityFilterLabel">Select Party</label>
                                <select class="form-select" id="reportEntityFilter">
                                    <option value="">Select</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                    <input type="text" class="form-control" id="reportSearch" placeholder="Bill No / Party / Product / Reference">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" id="fromDate" value="<?= $fromDate; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" id="toDate" value="<?= $toDate; ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary w-100" id="loadReportBtn">
                                    <i class="mdi mdi-filter me-1"></i> Load Report
                                </button>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">
                                    Select report group, report name and related customer / supplier / product. Overall report loading is disabled where a selection is required.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="printArea">
                    <div class="card report-output-card">
                        <div class="card-header bg-white">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <h5 class="mb-1" id="reportTitle">Select Report</h5>
                                    <small class="text-muted" id="reportPeriod">-</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary-subtle text-primary font-size-13" id="recordCount">0 Records</span>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div id="summaryBox" class="row g-2 mb-3"></div>

                            <div class="table-responsive report-table-wrap">
                                <table class="table table-bordered table-sm table-centered align-middle mb-0">
                                    <thead class="table-light" id="reportHead">
                                    <tr><th>Select report</th></tr>
                                    </thead>
                                    <tbody id="reportBody">
                                    <tr><td class="text-center text-muted">No report selected.</td></tr>
                                    </tbody>
                                    <tfoot class="table-light" id="reportFoot"></tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include BASE_PATH . 'includes/footer.php'; ?>
    </div>
</div>

<?php include BASE_PATH . 'includes/rightbar.php'; ?>
<?php include BASE_PATH . 'includes/scripts.php'; ?>

<script>
    window.BASE_URL = "<?= BASE_URL; ?>";
    window.ALL_REPORTS_INITIAL_DATES = {
        from_date: "<?= $fromDate; ?>",
        to_date: "<?= $toDate; ?>"
    };
</script>
<script src="<?= BASE_URL; ?>pages-js/all-reports.js?v=<?= time(); ?>"></script>
</body>
</html>
