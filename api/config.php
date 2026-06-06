<?php
require_once __DIR__ . '/env.php';
cargarEnv(__DIR__ . '/../.env');

define('DB_HOST', getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost'));
define('DB_PORT', getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306));
define('DB_USER', getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root'));
define('DB_PASS', getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ''));
define('DB_NAME', getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'rp_travels'));