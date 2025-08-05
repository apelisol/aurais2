<?php
/**
 * Contact Model for Ukunahi AI Backend
 * Handles contact form submissions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/EmailService.php';

class Contact {
    private $conn;
    private $table_name = "contacts";
    
    // Contact properties
    public $id;
    public $name;
    public $email;
    public $phone;
    public $company;
    public $subject;
    public $message;
    public $source;
    public $status;
    public $priority;
    public $ip_address;
    public $user_agent;
    public $email_sent;
    public $email_sent_at;
    public $admin_notified;
    public $admin_notified_at;
    public $notes;
    public $created_at;
    public $updated_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Create a new contact
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (name, email, phone, company, subject, message, source, status, priority, ip_address, user_agent, notes) 
                  VALUES 
                  (:name, :email, :phone, :company, :subject, :message, :source, :status, :priority, :ip_address, :user_agent, :notes)
                  RETURNING id, created_at";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = filter_var($this->email, FILTER_SANITIZE_EMAIL);
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->company = htmlspecialchars(strip_tags($this->company));
        $this->subject = htmlspecialchars(strip_tags($this->subject));
        $this->message = htmlspecialchars(strip_tags($this->message));
        $this->source = $this->source ?: 'contact_form';
        $this->status = $this->status ?: 'new';
        $this->priority = $this->calculatePriority();
        $this->notes = $this->notes ?: '[]';
        
        // Bind values
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":company", $this->company);
        $stmt->bindParam(":subject", $this->subject);
        $stmt->bindParam(":message", $this->message);
        $stmt->bindParam(":source", $this->source);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":ip_address", $this->ip_address);
        $stmt->bindParam(":user_agent", $this->user_agent);
        $stmt->bindParam(":notes", $this->notes);
        
        if ($stmt->execute()) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $result['id'];
            $this->created_at = $result['created_at'];
            
            // Send emails
            $this->sendEmails();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Read all contacts with pagination
     */
    public function read($page = 1, $limit = 10, $status = null, $priority = null) {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT id, name, email, phone, company, subject, status, priority, 
                         email_sent, admin_notified, created_at, updated_at 
                  FROM " . $this->table_name;
        
        $conditions = [];
        $params = [];
        
        if ($status) {
            $conditions[] = "status = :status";
            $params[':status'] = $status;
        }
        
        if ($priority) {
            $conditions[] = "priority = :priority";
            $params[':priority'] = $priority;
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Read single contact by ID
     */
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->company = $row['company'];
            $this->subject = $row['subject'];
            $this->message = $row['message'];
            $this->source = $row['source'];
            $this->status = $row['status'];
            $this->priority = $row['priority'];
            $this->ip_address = $row['ip_address'];
            $this->user_agent = $row['user_agent'];
            $this->email_sent = $row['email_sent'];
            $this->email_sent_at = $row['email_sent_at'];
            $this->admin_notified = $row['admin_notified'];
            $this->admin_notified_at = $row['admin_notified_at'];
            $this->notes = $row['notes'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update contact status
     */
    public function updateStatus() {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, notes = :notes 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':notes', $this->notes);
        $stmt->bindParam(':id', $this->id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get contact statistics
     */
    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'new' THEN 1 END) as new,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
                    COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed,
                    COUNT(CASE WHEN email_sent = true THEN 1 END) as emails_sent,
                    COUNT(CASE WHEN admin_notified = true THEN 1 END) as admin_notified
                  FROM " . $this->table_name;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count total contacts with filters
     */
    public function count($status = null, $priority = null) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        
        $conditions = [];
        $params = [];
        
        if ($status) {
            $conditions[] = "status = :status";
            $params[':status'] = $status;
        }
        
        if ($priority) {
            $conditions[] = "priority = :priority";
            $params[':priority'] = $priority;
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'];
    }
    
    /**
     * Calculate priority based on message content
     */
    private function calculatePriority() {
        if (!$this->message) {
            return 'medium';
        }
        
        $messageText = strtolower($this->message . ' ' . $this->subject);
        
        $urgentKeywords = ['urgent', 'asap', 'emergency', 'critical', 'immediately'];
        $highKeywords = ['important', 'priority', 'soon', 'quickly'];
        
        foreach ($urgentKeywords as $keyword) {
            if (strpos($messageText, $keyword) !== false) {
                return 'urgent';
            }
        }
        
        foreach ($highKeywords as $keyword) {
            if (strpos($messageText, $keyword) !== false) {
                return 'high';
            }
        }
        
        return 'medium';
    }
    
    /**
     * Send confirmation and notification emails
     */
    private function sendEmails() {
        try {
            $emailService = new EmailService();
            
            // Prepare user data for email templates
            $userData = [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'company' => $this->company,
                'subject' => $this->subject,
                'message' => $this->message,
                'ip_address' => $this->ip_address,
                'user_agent' => $this->user_agent
            ];
            
            // Send user confirmation email
            $userEmailResult = $emailService->sendUserConfirmation($userData, 'contact');
            
            // Send admin notification email
            $adminEmailResult = $emailService->sendAdminNotification($userData, 'contact');
            
            // Update email status in database
            $this->updateEmailStatus($userEmailResult['success'], $adminEmailResult['success']);
            
        } catch (Exception $e) {
            error_log("Failed to send emails for contact ID {$this->id}: " . $e->getMessage());
        }
    }
    
    /**
     * Update email delivery status
     */
    private function updateEmailStatus($userEmailSent, $adminEmailSent) {
        $query = "UPDATE " . $this->table_name . " SET ";
        $updates = [];
        $params = [':id' => $this->id];
        
        if ($userEmailSent) {
            $updates[] = "email_sent = true, email_sent_at = CURRENT_TIMESTAMP";
        }
        
        if ($adminEmailSent) {
            $updates[] = "admin_notified = true, admin_notified_at = CURRENT_TIMESTAMP";
        }
        
        if (!empty($updates)) {
            $query .= implode(', ', $updates) . " WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
        }
    }
    
    /**
     * Validate contact data
     */
    public function validate() {
        $errors = [];
        
        if (empty($this->name) || strlen($this->name) < 2 || strlen($this->name) > 100) {
            $errors[] = "Name must be between 2 and 100 characters";
        }
        
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please provide a valid email address";
        }
        
        if (!empty($this->phone) && !preg_match('/^[\+]?[0-9\s\-\(\)]{7,20}$/', $this->phone)) {
            $errors[] = "Please provide a valid phone number";
        }
        
        if (empty($this->subject) || strlen($this->subject) < 5 || strlen($this->subject) > 200) {
            $errors[] = "Subject must be between 5 and 200 characters";
        }
        
        if (empty($this->message) || strlen($this->message) < 10 || strlen($this->message) > 2000) {
            $errors[] = "Message must be between 10 and 2000 characters";
        }
        
        return $errors;
    }
}
?>
