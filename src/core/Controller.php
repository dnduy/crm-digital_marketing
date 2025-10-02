<?php

namespace Core;

/**
 * Base Controller Class
 * Provides common functionality for all controllers
 */
abstract class Controller
{
    protected \PDO $db;
    protected Config $config;
    protected EventDispatcher $events;
    protected Logger $logger;

    public function __construct(\PDO $db, Config $config, EventDispatcher $events, Logger $logger)
    {
        $this->db = $db;
        $this->config = $config;
        $this->events = $events;
        $this->logger = $logger;
    }

    /**
     * Render a view
     */
    protected function view(string $view, array $data = []): void
    {
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include view file
        $viewFile = __DIR__ . "/../views/{$view}.php";
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            throw new \Exception("View {$view} not found");
        }
        
        // Get content and clean buffer
        $content = ob_get_clean();
        
        // Render layout if specified
        if (isset($data['layout']) && $data['layout'] !== false) {
            $this->renderLayout($data['layout'] ?? 'main', $content, $data);
        } else {
            echo $content;
        }
    }

    /**
     * Render layout
     */
    private function renderLayout(string $layout, string $content, array $data = []): void
    {
        extract($data);
        $content = $content; // Make content available in layout
        
        $layoutFile = __DIR__ . "/../views/layouts/{$layout}.php";
        if (file_exists($layoutFile)) {
            include $layoutFile;
        } else {
            echo $content; // Fallback to content without layout
        }
    }

    /**
     * Return JSON response
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirect to a URL
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrf(): bool
    {
        $token = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
        return $token === ($_SESSION['csrf'] ?? '');
    }

    /**
     * Get current user
     */
    protected function user(): ?array
    {
        if (!isset($_SESSION['uid'])) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['uid']]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['uid']);
    }

    /**
     * Require authentication
     */
    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirect('?action=login');
        }
    }

    /**
     * Flash message
     */
    protected function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = [
            'message' => $message,
            'type' => $type
        ];
    }

    /**
     * Get and clear flash message
     */
    protected function getFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }
}