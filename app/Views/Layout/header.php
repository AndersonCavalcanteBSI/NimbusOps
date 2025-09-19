<?php
$role      = $_SESSION['user']['role'] ?? 'user';
$loggedIn  = !empty($_SESSION['user']['id']);
$msLinked  = (int)($_SESSION['user']['ms_linked'] ?? 0) === 1;
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>NimbusOps</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="/assets/app.css" rel="stylesheet">
</head>

<body>
    <?php
    if (!empty($withNav)) {
        include __DIR__ . '/nav.php';
    }
    ?>
    <main class="container my-3">