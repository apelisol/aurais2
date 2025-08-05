<?php
/**
 * Configuration Manager for Ukunahi AI Backend
 */

class Config {
    private static $config = null;
    
    public static function load() {
        if (self::$config === null) {
            self::$config = [];
            
            // Load environment variables from .env file
            $envFile = __DIR__ . '/../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) {
                        continue; // Skip comments
                    }
                    
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if (preg_match('/^"(.*)"$/', $value, $matches)) {
                        $value = $matches[1];
                    } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                        $value = $matches[1];
                    }
                    
                    self::$config[$name] = $value;
                }
            }
        }
        
        return self::$config;
    }
    
    public static function get($key, $default = null) {
        $config = self::load();
        return isset($config[$key]) ? $config[$key] : $default;
    }
    
    public static function getDatabase() {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'name' => self::get('DB_NAME', 'ukunahi_ai'),
            'user' => self::get('DB_USER', 'postgres'),
            'pass' => self::get('DB_PASS', ''),
            'port' => self::get('DB_PORT', '5432')
        ];
    }
    
    public static function getEmail() {
        return [
            'host' => self::get('SMTP_HOST', 'smtp.gmail.com'),
            'port' => self::get('SMTP_PORT', 587),
            'username' => self::get('SMTP_USERNAME', ''),
            'password' => self::get('SMTP_PASSWORD', ''),
            'encryption' => self::get('SMTP_ENCRYPTION', 'tls'),
            'admin_email' => self::get('ADMIN_EMAIL', 'admin@ukunahi.com'),
            'admin_name' => self::get('ADMIN_NAME', 'Ukunahi AI Admin')
        ];
    }
    
    public static function getApp() {
        return [
            'name' => self::get('APP_NAME', 'Ukunahi AI'),
            'url' => self::get('APP_URL', 'https://ai-site-nqsw.vercel.app'),
            'version' => self::get('API_VERSION', 'v1'),
            'timezone' => self::get('TIMEZONE', 'UTC'),
            'debug' => self::get('DEBUG', 'false') === 'true'
        ];
    }
    
    public static function getSecurity() {
        return [
            'jwt_secret' => self::get('JWT_SECRET', 'default_secret_change_in_production'),
            'cors_origins' => explode(',', self::get('CORS_ORIGINS', 'http://localhost:3000')),
            'rate_limit_requests' => (int)self::get('RATE_LIMIT_REQUESTS', 100),
            'rate_limit_window' => (int)self::get('RATE_LIMIT_WINDOW', 900)
        ];
    }
}

// Set timezone
date_default_timezone_set(Config::get('TIMEZONE', 'UTC'));

// Error reporting based on debug mode
if (Config::get('DEBUG', 'false') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>
