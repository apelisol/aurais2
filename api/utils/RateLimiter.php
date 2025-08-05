<?php
/**
 * Rate Limiter for Ukunahi AI Backend
 * Prevents API abuse
 */

class RateLimiter {
    private $maxRequests;
    private $timeWindow;
    private $storageFile;
    
    public function __construct() {
        $this->maxRequests = (int)Config::get('RATE_LIMIT_REQUESTS', 100);
        $this->timeWindow = (int)Config::get('RATE_LIMIT_WINDOW', 900); // 15 minutes
        $this->storageFile = __DIR__ . '/../storage/rate_limits.json';
        
        // Create storage directory if it doesn't exist
        $storageDir = dirname($this->storageFile);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
    }
    
    /**
     * Check if IP address is within rate limit
     */
    public function checkLimit($ipAddress) {
        $currentTime = time();
        $limits = $this->loadLimits();
        
        // Clean old entries
        $limits = $this->cleanOldEntries($limits, $currentTime);
        
        // Check current IP
        if (!isset($limits[$ipAddress])) {
            $limits[$ipAddress] = [];
        }
        
        // Count requests in current time window
        $requestCount = 0;
        foreach ($limits[$ipAddress] as $timestamp) {
            if ($currentTime - $timestamp < $this->timeWindow) {
                $requestCount++;
            }
        }
        
        // Check if limit exceeded
        if ($requestCount >= $this->maxRequests) {
            return false;
        }
        
        // Add current request
        $limits[$ipAddress][] = $currentTime;
        
        // Keep only recent requests
        $limits[$ipAddress] = array_filter($limits[$ipAddress], function($timestamp) use ($currentTime) {
            return $currentTime - $timestamp < $this->timeWindow;
        });
        
        // Save updated limits
        $this->saveLimits($limits);
        
        return true;
    }
    
    /**
     * Get remaining requests for IP
     */
    public function getRemainingRequests($ipAddress) {
        $currentTime = time();
        $limits = $this->loadLimits();
        
        if (!isset($limits[$ipAddress])) {
            return $this->maxRequests;
        }
        
        $requestCount = 0;
        foreach ($limits[$ipAddress] as $timestamp) {
            if ($currentTime - $timestamp < $this->timeWindow) {
                $requestCount++;
            }
        }
        
        return max(0, $this->maxRequests - $requestCount);
    }
    
    /**
     * Get time until rate limit resets
     */
    public function getResetTime($ipAddress) {
        $limits = $this->loadLimits();
        
        if (!isset($limits[$ipAddress]) || empty($limits[$ipAddress])) {
            return 0;
        }
        
        $oldestRequest = min($limits[$ipAddress]);
        $resetTime = $oldestRequest + $this->timeWindow;
        
        return max(0, $resetTime - time());
    }
    
    /**
     * Load rate limits from storage
     */
    private function loadLimits() {
        if (!file_exists($this->storageFile)) {
            return [];
        }
        
        $content = file_get_contents($this->storageFile);
        $limits = json_decode($content, true);
        
        return $limits ?: [];
    }
    
    /**
     * Save rate limits to storage
     */
    private function saveLimits($limits) {
        file_put_contents($this->storageFile, json_encode($limits));
    }
    
    /**
     * Clean old entries from limits array
     */
    private function cleanOldEntries($limits, $currentTime) {
        foreach ($limits as $ip => $timestamps) {
            $limits[$ip] = array_filter($timestamps, function($timestamp) use ($currentTime) {
                return $currentTime - $timestamp < $this->timeWindow;
            });
            
            // Remove empty IP entries
            if (empty($limits[$ip])) {
                unset($limits[$ip]);
            }
        }
        
        return $limits;
    }
}
?>
