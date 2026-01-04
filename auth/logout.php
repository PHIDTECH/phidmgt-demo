<?php
require_once __DIR__ . '/../config/config.php';

// Clear session
session_destroy();

// Redirect to homepage
header("Location: " . SITE_URL);
exit;
