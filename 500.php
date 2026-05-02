<?php
http_response_code(500);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Server Error';
require_once __DIR__ . '/includes/header.php';
?>
<section class="container py-5"><div class="empty-state card border-0 shadow-sm"><i class="bi bi-exclamation-triangle"></i><h1>Something went wrong</h1><p>Please try again later or contact support if the problem continues.</p><a href="/" class="btn btn-primary">Back to Home</a></div></section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
