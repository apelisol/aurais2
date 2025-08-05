<?php
/**
 * Contact API Endpoint for Ukunahi AI Backend
 * Handles contact form submissions
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
require_once __DIR__ . '/../models/Contact.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';
require_once __DIR__ . '/../utils/RateLimiter.php';

// Initialize
$database = new Database();
$db = $database->getConnection();
$contact = new Contact($db);
$response = new ResponseHandler();
$rateLimiter = new RateLimiter();

// Rate limiting
if (!$rateLimiter->checkLimit($_SERVER['REMOTE_ADDR'])) {
    $response->error('Too many requests. Please try again later.', 429);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));

try {
    switch ($method) {
        case 'POST':
            // Create new contact
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!$data) {
                $response->error('Invalid JSON data', 400);
                break;
            }
            
            // Set contact properties
            $contact->name = $data['name'] ?? '';
            $contact->email = $data['email'] ?? '';
            $contact->phone = $data['phone'] ?? '';
            $contact->company = $data['company'] ?? '';
            $contact->subject = $data['subject'] ?? '';
            $contact->message = $data['message'] ?? '';
            $contact->source = $data['source'] ?? 'contact_form';
            $contact->ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $contact->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Validate input
            $errors = $contact->validate();
            if (!empty($errors)) {
                $response->error('Validation failed', 400, ['details' => $errors]);
                break;
            }
            
            // Create contact
            if ($contact->create()) {
                $response->success('Contact form submitted successfully', [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'subject' => $contact->subject,
                    'status' => $contact->status,
                    'priority' => $contact->priority,
                    'email_sent' => $contact->email_sent,
                    'admin_notified' => $contact->admin_notified,
                    'submitted_at' => $contact->created_at
                ], 201);
            } else {
                $response->error('Failed to submit contact form. Please try again.', 500);
            }
            break;
            
        case 'GET':
            // Check if getting specific contact or list
            if (isset($path_parts[3]) && $path_parts[3] === 'stats' && isset($path_parts[4]) && $path_parts[4] === 'summary') {
                // Get statistics
                $stats = $contact->getStats();
                $response->success('Contact statistics retrieved', $stats);
            } elseif (isset($path_parts[3]) && !empty($path_parts[3])) {
                // Get specific contact
                $contact->id = $path_parts[3];
                
                if ($contact->readOne()) {
                    $contactData = [
                        'id' => $contact->id,
                        'name' => $contact->name,
                        'email' => $contact->email,
                        'phone' => $contact->phone,
                        'company' => $contact->company,
                        'subject' => $contact->subject,
                        'message' => $contact->message,
                        'source' => $contact->source,
                        'status' => $contact->status,
                        'priority' => $contact->priority,
                        'email_sent' => $contact->email_sent,
                        'email_sent_at' => $contact->email_sent_at,
                        'admin_notified' => $contact->admin_notified,
                        'admin_notified_at' => $contact->admin_notified_at,
                        'notes' => json_decode($contact->notes, true),
                        'created_at' => $contact->created_at,
                        'updated_at' => $contact->updated_at
                    ];
                    
                    $response->success('Contact retrieved', $contactData);
                } else {
                    $response->error('Contact not found', 404);
                }
            } else {
                // Get all contacts with pagination
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $status = $_GET['status'] ?? null;
                $priority = $_GET['priority'] ?? null;
                
                $stmt = $contact->read($page, $limit, $status, $priority);
                $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $total = $contact->count($status, $priority);
                
                $response->success('Contacts retrieved', $contacts, 200, [
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
            // Update contact status
            if (isset($path_parts[3]) && isset($path_parts[4]) && $path_parts[4] === 'status') {
                $data = json_decode(file_get_contents("php://input"), true);
                
                if (!$data) {
                    $response->error('Invalid JSON data', 400);
                    break;
                }
                
                $contact->id = $path_parts[3];
                
                // Check if contact exists
                if (!$contact->readOne()) {
                    $response->error('Contact not found', 404);
                    break;
                }
                
                $validStatuses = ['new', 'in_progress', 'resolved', 'closed'];
                if (!in_array($data['status'], $validStatuses)) {
                    $response->error('Invalid status. Must be one of: ' . implode(', ', $validStatuses), 400);
                    break;
                }
                
                $contact->status = $data['status'];
                
                // Add note if provided
                if (isset($data['notes']) && !empty($data['notes'])) {
                    $notes = json_decode($contact->notes, true) ?: [];
                    $notes[] = [
                        'content' => $data['notes'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => 'admin'
                    ];
                    $contact->notes = json_encode($notes);
                }
                
                if ($contact->updateStatus()) {
                    $response->success('Contact status updated successfully', [
                        'id' => $contact->id,
                        'status' => $contact->status,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    $response->error('Failed to update contact status', 500);
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
    error_log('Contact API Error: ' . $e->getMessage());
    $response->error('Internal server error', 500);
}

$database->closeConnection();
?>
