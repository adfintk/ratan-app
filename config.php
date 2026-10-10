<?php
// Database credentials — create the DB + user in hPanel > Databases > MySQL Databases
define('DB_HOST', 'localhost');
define('DB_NAME', 'u171397200_apischeduler');
define('DB_USER', 'apischeduler');
define('DB_PASS', 'Admin@980173#');

// Full base URL of this app, no trailing slash. Required so cron_runner.php
// (which has no browser context) knows where to send its request.
define('APP_BASE_URL', 'https://scheduler.yourdomain.com');

// Allowed interval choices, in seconds.
define('ALLOWED_INTERVALS', [15, 30]);

// Max seconds one cron invocation loops before exiting, so it never
// overlaps the next cron tick (which fires every 60s).
define('CRON_TIME_BUDGET_SECONDS', 50);