<?php
/**
 * Admin bootstrap: include this FIRST in every admin page so handlers
 * (and their redirects) run before any HTML output.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_admin();
