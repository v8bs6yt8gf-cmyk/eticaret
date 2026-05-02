<?php
http_response_code(404);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Page Not Found';
require_once __DIR__ . '/includes/header.php';
?>
<section class="container py-5"><div class="empty-state card border-0 shadow-sm"><i class="bi bi-compass"></i><h1>Page not found</h1><p>The page you are looking for does not exist or has moved.</p><a href="/" class="btn btn-primary">Back to Home</a></div></section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
