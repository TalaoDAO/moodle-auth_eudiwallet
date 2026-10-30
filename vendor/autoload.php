<?php
// Distribution autoloader; Moodle autoloads the plugin's classes itself.
spl_autoload_register(static function ($class) {
    $prefix = 'auth_eudiwallet\\vendor\\jwt\\';
    if (!str_starts_with($class, $prefix)) { return; }
    $file = __DIR__ . '/jwt/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($file)) { require_once($file); }
});
