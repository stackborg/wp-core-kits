<?php
/**
 * PHPStan bootstrap — define WordPress constants for standalone analysis.
 *
 * @package Stackborg\WPCoreKits
 */

// WordPress constants used in the codebase
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

// WordPress security keys (used in LicenseGuard::siteKey())
if (!defined('AUTH_KEY')) {
    define('AUTH_KEY', 'phpstan_auth_key');
}
if (!defined('SECURE_AUTH_KEY')) {
    define('SECURE_AUTH_KEY', 'phpstan_secure_auth_key');
}
