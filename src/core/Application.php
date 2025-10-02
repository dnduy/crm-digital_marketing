<?php

namespace Core;

/**
 * Modern Application Core
 * Handles request routing, dependency injection, and application lifecycle
 */
class Application
{
    private Container $container;
    private array $routes = [];
    private array $middleware = [];

    public function __construct()
    {
        $this->container = new Container();
        $this->registerCoreServices();
    }

    /**
     * Register core services in the container
     */
    private function registerCoreServices(): void
    {
        // Database connection
        $this->container->singleton('db', function() {
            return new \PDO(
                'sqlite:' . __DIR__ . '/../crm.sqlite',
                null,
                null,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        });

        // Configuration
        $this->container->singleton('config', function() {
            return new Config();
        });

        // Event dispatcher
        $this->container->singleton('events', function() {
            return new EventDispatcher();
        });

        // Logger
        $this->container->singleton('logger', function() {
            return new Logger();
        });
    }

    /**
     * Get service from container
     */
    public function get(string $service)
    {
        return $this->container->get($service);
    }

    /**
     * Register a route
     */
    public function route(string $method, string $path, $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler
        ];
    }

    /**
     * Add GET route
     */
    public function get_route(string $path, $handler): void
    {
        $this->route('GET', $path, $handler);
    }

    /**
     * Add POST route
     */
    public function post(string $path, $handler): void
    {
        $this->route('POST', $path, $handler);
    }

    /**
     * Add middleware
     */
    public function middleware($middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Handle incoming request
     */
    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        // Legacy support for action-based routing
        if (isset($_GET['action'])) {
            $this->handleLegacyAction($_GET['action'], $_GET['op'] ?? '');
            return;
        }

        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                $this->executeHandler($route['handler']);
                return;
            }
        }

        // Default dashboard
        $this->handleLegacyAction('dashboard', '');
    }

    /**
     * Handle legacy action-based routing (temporary during migration)
     */
    private function handleLegacyAction(string $action, string $op): void
    {
        $controllerMap = [
            'dashboard' => \Controllers\DashboardController::class,
            'campaigns' => \Controllers\CampaignController::class,
            'analytics' => \Controllers\AnalyticsController::class,
            'content' => \Controllers\ContentController::class,
            'keywords' => \Controllers\KeywordController::class,
            'ab_testing' => \Controllers\ABTestController::class,
            'roi_calculator' => \Controllers\ROIController::class,
        ];

        if (isset($controllerMap[$action])) {
            $controller = $this->container->get($controllerMap[$action]);
            $method = $op ? 'handle' . ucfirst($op) : 'index';
            
            if (method_exists($controller, $method)) {
                $controller->$method();
            } else {
                $controller->index();
            }
        } else {
            // Fall back to legacy system
            $this->handleLegacySystem($action, $op);
        }
    }

    /**
     * Fallback to legacy system during migration
     */
    private function handleLegacySystem(string $action, string $op): void
    {
        // Include legacy files as needed
        switch ($action) {
            case 'contacts':
                require_once __DIR__ . '/../views/contacts.php';
                view_contacts($op);
                break;
            case 'deals':
                require_once __DIR__ . '/../views/deals.php';
                view_deals($op);
                break;
            case 'activities':
                require_once __DIR__ . '/../views/activities.php';
                view_activities($op);
                break;
            case 'tasks':
                require_once __DIR__ . '/../views/tasks.php';
                view_tasks($op);
                break;
            case 'reports':
                require_once __DIR__ . '/../views/reports.php';
                view_reports();
                break;
            case 'users':
                require_once __DIR__ . '/../views/users.php';
                view_users($op);
                break;
            default:
                header('HTTP/1.0 404 Not Found');
                echo '404 - Page not found';
        }
    }

    /**
     * Execute route handler
     */
    private function executeHandler($handler): void
    {
        if (is_callable($handler)) {
            $handler($this);
        } else if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controller, $method] = explode('@', $handler);
            $controllerInstance = $this->container->get($controller);
            $controllerInstance->$method();
        }
    }

    /**
     * Simple path matching
     */
    private function matchPath(string $routePath, string $requestPath): bool
    {
        return $routePath === $requestPath;
    }
}