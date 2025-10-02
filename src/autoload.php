<?php
/**
 * Modern PHP Autoloader for CRM Digital Marketing Platform
 * PSR-4 compliant autoloader with namespace mapping
 */

spl_autoload_register(function ($class) {
    // Namespace mapping
    $namespaces = [
        'App\\' => __DIR__ . '/app/',
        'Core\\' => __DIR__ . '/core/',
        'Services\\' => __DIR__ . '/services/',
        'Models\\' => __DIR__ . '/models/',
        'Controllers\\' => __DIR__ . '/controllers/',
        'Middleware\\' => __DIR__ . '/middleware/',
        'AI\\' => __DIR__ . '/ai/',
        'Integrations\\' => __DIR__ . '/integrations/',
        'Repositories\\' => __DIR__ . '/repositories/',
        'Jobs\\' => __DIR__ . '/jobs/',
    ];

    // Convert namespace to file path
    foreach ($namespaces as $namespace => $path) {
        if (strpos($class, $namespace) === 0) {
            $relativeClass = substr($class, strlen($namespace));
            $file = $path . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// Legacy autoloader for existing procedural functions
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';