<?php

namespace Core;

/**
 * Event Dispatcher
 * Simple event system for loosely coupled components
 */
class EventDispatcher
{
    private array $listeners = [];

    /**
     * Listen for an event
     */
    public function listen(string $event, callable $listener, int $priority = 0): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];

        // Sort by priority (higher priority first)
        usort($this->listeners[$event], function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
    }

    /**
     * Dispatch an event
     */
    public function dispatch(string $event, $data = null): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $listener) {
            $listener['listener']($data, $event);
        }
    }
}

/**
 * Simple Logger
 */
class Logger
{
    private string $logFile;

    public function __construct()
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logFile = $logDir . '/app.log';
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}