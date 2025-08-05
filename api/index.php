<?php
/**
 * Main API Router for Ukunahi AI Backend
 * Routes requests to appropriate endpoints
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/ResponseHandler.php';

$response = new ResponseHandler();

// Get request URI and method
$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse the path
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Remove 'api' from path if present
if (isset($path_parts[0]) && $path_parts[0] === 'api') {
    array_shift($path_parts);
}

// Health check endpoint
if (empty($path_parts) || $path_parts[0] === 'health') {
    $response->success('Ukunahi AI Backend API is running', [
        'status' => 'OK',
        'timestamp' => date('c'),
        'version' => Config::get('API_VERSION', 'v1'),
        'environment' => Config::get('DEBUG', 'false') === 'true' ? 'development' : 'production'
    ]);
    exit();
}

// Route to appropriate endpoint
$endpoint = $path_parts[0] ?? '';

switch ($endpoint) {
    case 'contact':
        require_once __DIR__ . '/endpoints/contact.php';
        break;
        
    case 'consultation':
        require_once __DIR__ . '/endpoints/consultation.php';
        break;
        
    case 'services':
        require_once __DIR__ . '/endpoints/services.php';
        break;
        
    default:
        $response->error('Endpoint not found', 404, [
            'available_endpoints' => [
                'GET /api/health' => 'Health check',
                'POST /api/contact' => 'Submit contact form',
                'GET /api/contact' => 'Get contacts (admin)',
                'POST /api/consultation' => 'Book free consultation',
                'GET /api/consultation' => 'Get consultations (admin)',
                'POST /api/services/inquiry' => 'Submit service inquiry',
                'GET /api/services/inquiry' => 'Get service inquiries (admin)',
                'GET /api/services/types' => 'Get available service types'
            ]
        ]);
        break;
}
?>
