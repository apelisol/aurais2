<?php
/**
 * Services API Endpoint for Ukunahi AI Backend
 * Handles service inquiries and service information
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/ServiceInquiry.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';
require_once __DIR__ . '/../utils/RateLimiter.php';

// Initialize
$database = new Database();
$db = $database->getConnection();
$serviceInquiry = new ServiceInquiry($db);
$response = new ResponseHandler();
$rateLimiter = new RateLimiter();

// Rate limiting
if (!$rateLimiter->checkLimit($_SERVER['REMOTE_ADDR'])) {
    $response->rateLimitExceeded();
}

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));

try {
    switch ($method) {
        case 'POST':
            // Check if it's an inquiry submission
            if (isset($path_parts[3]) && $path_parts[3] === 'inquiry') {
                // Create new service inquiry
                $data = json_decode(file_get_contents("php://input"), true);
                
                if (!$data) {
                    $response->error('Invalid JSON data', 400);
                    break;
                }
                
                // Set service inquiry properties
                $serviceInquiry->name = $data['name'] ?? '';
                $serviceInquiry->email = $data['email'] ?? '';
                $serviceInquiry->phone = $data['phone'] ?? '';
                $serviceInquiry->company = $data['company'] ?? '';
                $serviceInquiry->service_type = $data['service_type'] ?? '';
                $serviceInquiry->project_description = $data['project_description'] ?? '';
                $serviceInquiry->budget = $data['budget'] ?? '';
                $serviceInquiry->timeline = $data['timeline'] ?? '';
                $serviceInquiry->current_website = $data['current_website'] ?? '';
                $serviceInquiry->current_challenges = $data['current_challenges'] ?? '';
                $serviceInquiry->specific_requirements = $data['specific_requirements'] ?? [];
                $serviceInquiry->target_audience = $data['target_audience'] ?? '';
                $serviceInquiry->competitor_websites = $data['competitor_websites'] ?? [];
                $serviceInquiry->preferred_style = $data['preferred_style'] ?? 'not_sure';
                $serviceInquiry->additional_services = $data['additional_services'] ?? [];
                $serviceInquiry->ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $serviceInquiry->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                // Validate input
                $errors = $serviceInquiry->validate();
                if (!empty($errors)) {
                    $response->validationError($errors);
                    break;
                }
                
                // Create service inquiry
                if ($serviceInquiry->create()) {
                    $response->success('Service inquiry submitted successfully', [
                        'id' => $serviceInquiry->id,
                        'name' => $serviceInquiry->name,
                        'email' => $serviceInquiry->email,
                        'service_type' => $serviceInquiry->service_type,
                        'status' => $serviceInquiry->status,
                        'priority' => $serviceInquiry->priority,
                        'estimated_value' => $serviceInquiry->estimated_value,
                        'email_sent' => $serviceInquiry->email_sent,
                        'admin_notified' => $serviceInquiry->admin_notified,
                        'follow_up_date' => $serviceInquiry->follow_up_date,
                        'submitted_at' => $serviceInquiry->created_at
                    ], 201);
                } else {
                    $response->error('Failed to submit service inquiry. Please try again.', 500);
                }
            } else {
                $response->error('Invalid endpoint', 404);
            }
            break;
            
        case 'GET':
            // Check for specific routes
            if (isset($path_parts[3])) {
                if ($path_parts[3] === 'inquiry') {
                    if (isset($path_parts[4]) && !empty($path_parts[4])) {
                        // Get specific service inquiry
                        $serviceInquiry->id = $path_parts[4];
                        
                        if ($serviceInquiry->readOne()) {
                            $response->success('Service inquiry retrieved', [
                                'id' => $serviceInquiry->id,
                                'name' => $serviceInquiry->name,
                                'email' => $serviceInquiry->email,
                                'phone' => $serviceInquiry->phone,
                                'company' => $serviceInquiry->company,
                                'service_type' => $serviceInquiry->service_type,
                                'project_description' => $serviceInquiry->project_description,
                                'budget' => $serviceInquiry->budget,
                                'timeline' => $serviceInquiry->timeline,
                                'current_website' => $serviceInquiry->current_website,
                                'current_challenges' => $serviceInquiry->current_challenges,
                                'specific_requirements' => $serviceInquiry->specific_requirements,
                                'target_audience' => $serviceInquiry->target_audience,
                                'competitor_websites' => $serviceInquiry->competitor_websites,
                                'preferred_style' => $serviceInquiry->preferred_style,
                                'additional_services' => $serviceInquiry->additional_services,
                                'status' => $serviceInquiry->status,
                                'priority' => $serviceInquiry->priority,
                                'estimated_value' => $serviceInquiry->estimated_value,
                                'quote_sent' => $serviceInquiry->quote_sent,
                                'quote_sent_at' => $serviceInquiry->quote_sent_at,
                                'quote_amount' => $serviceInquiry->quote_amount,
                                'follow_up_date' => $serviceInquiry->follow_up_date,
                                'assigned_to' => $serviceInquiry->assigned_to,
                                'email_sent' => $serviceInquiry->email_sent,
                                'email_sent_at' => $serviceInquiry->email_sent_at,
                                'admin_notified' => $serviceInquiry->admin_notified,
                                'admin_notified_at' => $serviceInquiry->admin_notified_at,
                                'notes' => $serviceInquiry->notes,
                                'created_at' => $serviceInquiry->created_at,
                                'updated_at' => $serviceInquiry->updated_at
                            ]);
                        } else {
                            $response->notFound('Service inquiry');
                        }
                    } else {
                        // Get all service inquiries with pagination
                        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                        $serviceType = $_GET['service_type'] ?? null;
                        $status = $_GET['status'] ?? null;
                        $priority = $_GET['priority'] ?? null;
                        $sortBy = $_GET['sortBy'] ?? 'created_at';
                        $sortOrder = $_GET['sortOrder'] ?? 'DESC';
                        
                        $stmt = $serviceInquiry->read($page, $limit, $serviceType, $status, $priority, $sortBy, $sortOrder);
                        $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $total = $serviceInquiry->count($serviceType, $status, $priority);
                        
                        $response->success('Service inquiries retrieved', $inquiries, 200, [
                            'pagination' => [
                                'page' => $page,
                                'limit' => $limit,
                                'total' => $total,
                                'pages' => ceil($total / $limit)
                            ]
                        ]);
                    }
                } elseif ($path_parts[3] === 'stats') {
                    if (isset($path_parts[4]) && $path_parts[4] === 'summary') {
                        // Get statistics
                        $stats = $serviceInquiry->getStats();
                        $response->success('Service inquiry statistics retrieved', $stats);
                    } elseif (isset($path_parts[4]) && $path_parts[4] === 'by-service') {
                        // Get statistics by service type
                        $serviceStats = $serviceInquiry->getStatsByService();
                        $response->success('Service statistics by type retrieved', $serviceStats);
                    }
                } elseif ($path_parts[3] === 'types') {
                    // Get available service types
                    $serviceTypes = ServiceInquiry::getServiceTypes();
                    $response->success('Service types retrieved', $serviceTypes);
                }
            } else {
                $response->error('Invalid endpoint', 404);
            }
            break;
            
        case 'PUT':
            // Update service inquiry status
            if (isset($path_parts[3]) && $path_parts[3] === 'inquiry' && 
                isset($path_parts[4]) && isset($path_parts[5]) && $path_parts[5] === 'status') {
                
                $data = json_decode(file_get_contents("php://input"), true);
                
                if (!$data) {
                    $response->error('Invalid JSON data', 400);
                    break;
                }
                
                $serviceInquiry->id = $path_parts[4];
                
                // Check if service inquiry exists
                if (!$serviceInquiry->readOne()) {
                    $response->notFound('Service inquiry');
                    break;
                }
                
                $validStatuses = ['new', 'reviewing', 'quoted', 'approved', 'in_progress', 'completed', 'cancelled'];
                if (!in_array($data['status'], $validStatuses)) {
                    $response->error('Invalid status. Must be one of: ' . implode(', ', $validStatuses), 400);
                    break;
                }
                
                $serviceInquiry->status = $data['status'];
                $serviceInquiry->quote_amount = $data['quote_amount'] ?? null;
                $serviceInquiry->assigned_to = $data['assigned_to'] ?? '';
                
                // Add note if provided
                if (isset($data['notes']) && !empty($data['notes'])) {
                    $notes = $serviceInquiry->notes ?: [];
                    $notes[] = [
                        'content' => $data['notes'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => 'admin'
                    ];
                    $serviceInquiry->notes = $notes;
                }
                
                if ($serviceInquiry->updateStatus()) {
                    $response->success('Service inquiry status updated successfully', [
                        'id' => $serviceInquiry->id,
                        'status' => $serviceInquiry->status,
                        'quote_amount' => $serviceInquiry->quote_amount,
                        'assigned_to' => $serviceInquiry->assigned_to,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    $response->error('Failed to update service inquiry status', 500);
                }
            } else {
                $response->error('Invalid endpoint', 404);
            }
            break;
            
        default:
            $response->error('Method not allowed', 405);
            break;
    }
    
} catch (Exception $e) {
    error_log('Services API Error: ' . $e->getMessage());
    $response->error('Internal server error', 500);
}

$database->closeConnection();
?>
