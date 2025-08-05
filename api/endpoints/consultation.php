<?php
/**
 * Consultation API Endpoint for Ukunahi AI Backend
 * Handles free consultation bookings
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
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';
require_once __DIR__ . '/../utils/RateLimiter.php';

// Initialize
$database = new Database();
$db = $database->getConnection();
$consultation = new Consultation($db);
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
            // Create new consultation booking
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!$data) {
                $response->error('Invalid JSON data', 400);
                break;
            }
            
            // Set consultation properties
            $consultation->name = $data['name'] ?? '';
            $consultation->email = $data['email'] ?? '';
            $consultation->phone = $data['phone'] ?? '';
            $consultation->company = $data['company'] ?? '';
            $consultation->industry = $data['industry'] ?? '';
            $consultation->business_size = $data['business_size'] ?? '';
            $consultation->current_challenges = $data['current_challenges'] ?? '';
            $consultation->interested_services = $data['interested_services'] ?? [];
            $consultation->budget = $data['budget'] ?? '';
            $consultation->timeline = $data['timeline'] ?? '';
            $consultation->preferred_contact_method = $data['preferred_contact_method'] ?? 'email';
            $consultation->preferred_time = $data['preferred_time'] ?? 'flexible';
            $consultation->timezone = $data['timezone'] ?? '';
            $consultation->additional_notes = $data['additional_notes'] ?? '';
            $consultation->ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $consultation->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Validate input
            $errors = $consultation->validate();
            if (!empty($errors)) {
                $response->validationError($errors);
                break;
            }
            
            // Create consultation
            if ($consultation->create()) {
                $response->success('Free consultation booked successfully', [
                    'id' => $consultation->id,
                    'name' => $consultation->name,
                    'email' => $consultation->email,
                    'company' => $consultation->company,
                    'status' => $consultation->status,
                    'priority' => $consultation->priority,
                    'lead_score' => $consultation->lead_score,
                    'email_sent' => $consultation->email_sent,
                    'admin_notified' => $consultation->admin_notified,
                    'follow_up_date' => $consultation->follow_up_date,
                    'booked_at' => $consultation->created_at
                ], 201);
            } else {
                $response->error('Failed to book consultation. Please try again.', 500);
            }
            break;
            
        case 'GET':
            // Check for specific routes
            if (isset($path_parts[3]) && $path_parts[3] === 'stats') {
                if (isset($path_parts[4]) && $path_parts[4] === 'summary') {
                    // Get statistics
                    $stats = $consultation->getStats();
                    $response->success('Consultation statistics retrieved', $stats);
                } elseif (isset($path_parts[4]) && $path_parts[4] === 'leads') {
                    // Get lead statistics
                    $leadStats = $consultation->getLeadStats();
                    $response->success('Lead statistics retrieved', $leadStats);
                }
            } elseif (isset($path_parts[3]) && !empty($path_parts[3])) {
                // Get specific consultation
                $consultation->id = $path_parts[3];
                
                if ($consultation->readOne()) {
                    $response->success('Consultation retrieved', [
                        'id' => $consultation->id,
                        'name' => $consultation->name,
                        'email' => $consultation->email,
                        'phone' => $consultation->phone,
                        'company' => $consultation->company,
                        'industry' => $consultation->industry,
                        'business_size' => $consultation->business_size,
                        'current_challenges' => $consultation->current_challenges,
                        'interested_services' => $consultation->interested_services,
                        'budget' => $consultation->budget,
                        'timeline' => $consultation->timeline,
                        'preferred_contact_method' => $consultation->preferred_contact_method,
                        'preferred_time' => $consultation->preferred_time,
                        'timezone' => $consultation->timezone,
                        'additional_notes' => $consultation->additional_notes,
                        'status' => $consultation->status,
                        'priority' => $consultation->priority,
                        'lead_score' => $consultation->lead_score,
                        'scheduled_date' => $consultation->scheduled_date,
                        'consultation_notes' => $consultation->consultation_notes,
                        'follow_up_required' => $consultation->follow_up_required,
                        'follow_up_date' => $consultation->follow_up_date,
                        'email_sent' => $consultation->email_sent,
                        'email_sent_at' => $consultation->email_sent_at,
                        'admin_notified' => $consultation->admin_notified,
                        'admin_notified_at' => $consultation->admin_notified_at,
                        'created_at' => $consultation->created_at,
                        'updated_at' => $consultation->updated_at
                    ]);
                } else {
                    $response->notFound('Consultation');
                }
            } else {
                // Get all consultations with pagination
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $status = $_GET['status'] ?? null;
                $priority = $_GET['priority'] ?? null;
                $sortBy = $_GET['sortBy'] ?? 'created_at';
                $sortOrder = $_GET['sortOrder'] ?? 'DESC';
                
                $stmt = $consultation->read($page, $limit, $status, $priority, $sortBy, $sortOrder);
                $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $total = $consultation->count($status, $priority);
                
                $response->success('Consultations retrieved', $consultations, 200, [
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            }
            break;
            
        case 'PUT':
            // Update consultation status
            if (isset($path_parts[3]) && isset($path_parts[4]) && $path_parts[4] === 'status') {
                $data = json_decode(file_get_contents("php://input"), true);
                
                if (!$data) {
                    $response->error('Invalid JSON data', 400);
                    break;
                }
                
                $consultation->id = $path_parts[3];
                
                // Check if consultation exists
                if (!$consultation->readOne()) {
                    $response->notFound('Consultation');
                    break;
                }
                
                $validStatuses = ['pending', 'scheduled', 'completed', 'cancelled', 'no_show'];
                if (!in_array($data['status'], $validStatuses)) {
                    $response->error('Invalid status. Must be one of: ' . implode(', ', $validStatuses), 400);
                    break;
                }
                
                $consultation->status = $data['status'];
                $consultation->scheduled_date = $data['scheduled_date'] ?? null;
                $consultation->consultation_notes = $data['consultation_notes'] ?? '';
                
                if ($consultation->updateStatus()) {
                    $response->success('Consultation status updated successfully', [
                        'id' => $consultation->id,
                        'status' => $consultation->status,
                        'scheduled_date' => $consultation->scheduled_date,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    $response->error('Failed to update consultation status', 500);
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
    error_log('Consultation API Error: ' . $e->getMessage());
    $response->error('Internal server error', 500);
}

$database->closeConnection();
?>
