<?php
// ==========================
// FILE: /core/Logger.php - Simple Logger Implementation
// ==========================

namespace Core;

class Logger {
    
    public function info($message) {
        echo "[INFO] " . date('Y-m-d H:i:s') . " - " . $message . "\n";
    }
    
    public function error($message) {
        echo "[ERROR] " . date('Y-m-d H:i:s') . " - " . $message . "\n";
    }
    
    public function warning($message) {
        echo "[WARNING] " . date('Y-m-d H:i:s') . " - " . $message . "\n";
    }
    
    public function debug($message) {
        echo "[DEBUG] " . date('Y-m-d H:i:s') . " - " . $message . "\n";
    }
}