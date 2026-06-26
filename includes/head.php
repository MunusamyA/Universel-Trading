<?php
require_once __DIR__ . '/config.php';

$page_title = $page_title ?? 'Universal ERP';
$page_description = $page_description ?? 'Universal Multi Business ERP';
$page_author = $page_author ?? 'Ecommer';
?>

<meta charset="utf-8" />
<title><?= htmlspecialchars($page_title); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta content="<?= htmlspecialchars($page_description); ?>" name="description" />
<meta content="<?= htmlspecialchars($page_author); ?>" name="author" />

<link rel="shortcut icon" href="<?= BASE_URL; ?>assets/images/favicon.ico">

<link href="<?= BASE_URL; ?>assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />
<link href="<?= BASE_URL; ?>assets/css/icons.min.css" rel="stylesheet" type="text/css" />
<link href="<?= BASE_URL; ?>assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />