<?php
/**
 * Response Handler for Ukunahi AI Backend
 * Standardizes API responses
 */

class ResponseHandler {
    
    /**
     * Send success response
     */
    public function success($message, $data = null, $statusCode = 200, $meta = null) {
        http_response_code($statusCode);
        
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($meta !== null) {
            $response['meta'] = $meta;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Send error response
     */
    public function error($message, $statusCode = 400, $details = null) {
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ];
        
        if ($details !== null) {
            $response = array_merge($response, $details);
        }
        
        // Log error for debugging
        if ($statusCode >= 500) {
            error_log("API Error ({$statusCode}): {$message}");
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Send validation error response
     */
    public function validationError($errors) {
        $this->error('Validation failed', 422, ['validation_errors' => $errors]);
    }
    
    /**
     * Send not found response
     */
    public function notFound($resource = 'Resource') {
        $this->error("{$resource} not found", 404);
    }
    
    /**
     * Send unauthorized response
     */
    public function unauthorized($message = 'Unauthorized access') {
        $this->error($message, 401);
    }
    
    /**
     * Send forbidden response
     */
    public function forbidden($message = 'Access forbidden') {
        $this->error($message, 403);
    }
    
    /**
     * Send rate limit exceeded response
     */
    public function rateLimitExceeded($message = 'Rate limit exceeded') {
        $this->error($message, 429);
    }
}
?>
