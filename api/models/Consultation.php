<?php
/**
 * Consultation Model for Ukunahi AI Backend
 * Handles free consultation bookings with lead scoring
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/EmailService.php';

class Consultation {
    private $conn;
    private $table_name = "consultations";
    
    // Consultation properties
    public $id;
    public $name;
    public $email;
    public $phone;
    public $company;
    public $industry;
    public $business_size;
    public $current_challenges;
    public $interested_services;
    public $budget;
    public $timeline;
    public $preferred_contact_method;
    public $preferred_time;
    public $timezone;
    public $additional_notes;
    public $status;
    public $priority;
    public $lead_score;
    public $scheduled_date;
    public $consultation_notes;
    public $follow_up_required;
    public $follow_up_date;
    public $ip_address;
    public $user_agent;
    public $email_sent;
    public $email_sent_at;
    public $admin_notified;
    public $admin_notified_at;
    public $created_at;
    public $updated_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Create a new consultation booking
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (name, email, phone, company, industry, business_size, current_challenges, 
                   interested_services, budget, timeline, preferred_contact_method, preferred_time, 
                   timezone, additional_notes, status, priority, lead_score, follow_up_required, 
                   follow_up_date, ip_address, user_agent) 
                  VALUES 
                  (:name, :email, :phone, :company, :industry, :business_size, :current_challenges, 
                   :interested_services, :budget, :timeline, :preferred_contact_method, :preferred_time, 
                   :timezone, :additional_notes, :status, :priority, :lead_score, :follow_up_required, 
                   :follow_up_date, :ip_address, :user_agent)
                  RETURNING id, created_at";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = filter_var($this->email, FILTER_SANITIZE_EMAIL);
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->company = htmlspecialchars(strip_tags($this->company));
        $this->industry = htmlspecialchars(strip_tags($this->industry));
        $this->current_challenges = htmlspecialchars(strip_tags($this->current_challenges));
        $this->additional_notes = htmlspecialchars(strip_tags($this->additional_notes));
        
        // Set defaults
        $this->status = $this->status ?: 'pending';
        $this->preferred_contact_method = $this->preferred_contact_method ?: 'email';
        $this->preferred_time = $this->preferred_time ?: 'flexible';
        $this->follow_up_required = $this->follow_up_required !== null ? $this->follow_up_required : true;
        
        // Calculate lead score and priority
        $this->lead_score = $this->calculateLeadScore();
        $this->priority = $this->calculatePriority();
        $this->follow_up_date = $this->calculateFollowUpDate();
        
        // Ensure interested_services is JSON
        if (is_array($this->interested_services)) {
            $this->interested_services = json_encode($this->interested_services);
        }
        
        // Bind values
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":company", $this->company);
        $stmt->bindParam(":industry", $this->industry);
        $stmt->bindParam(":business_size", $this->business_size);
        $stmt->bindParam(":current_challenges", $this->current_challenges);
        $stmt->bindParam(":interested_services", $this->interested_services);
        $stmt->bindParam(":budget", $this->budget);
        $stmt->bindParam(":timeline", $this->timeline);
        $stmt->bindParam(":preferred_contact_method", $this->preferred_contact_method);
        $stmt->bindParam(":preferred_time", $this->preferred_time);
        $stmt->bindParam(":timezone", $this->timezone);
        $stmt->bindParam(":additional_notes", $this->additional_notes);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":lead_score", $this->lead_score);
        $stmt->bindParam(":follow_up_required", $this->follow_up_required, PDO::PARAM_BOOL);
        $stmt->bindParam(":follow_up_date", $this->follow_up_date);
        $stmt->bindParam(":ip_address", $this->ip_address);
        $stmt->bindParam(":user_agent", $this->user_agent);
        
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
     * Read all consultations with pagination
     */
    public function read($page = 1, $limit = 10, $status = null, $priority = null, $sortBy = 'created_at', $sortOrder = 'DESC') {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT id, name, email, phone, company, industry, business_size, budget, 
                         timeline, status, priority, lead_score, scheduled_date, 
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
        
        $allowedSortFields = ['created_at', 'lead_score', 'scheduled_date', 'priority'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'created_at';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        
        $query .= " ORDER BY {$sortBy} {$sortOrder} LIMIT :limit OFFSET :offset";
        
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
     * Read single consultation by ID
     */
    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            foreach ($row as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
            
            // Decode JSON fields
            if ($this->interested_services) {
                $this->interested_services = json_decode($this->interested_services, true);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update consultation status
     */
    public function updateStatus() {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, scheduled_date = :scheduled_date, consultation_notes = :consultation_notes 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->consultation_notes = htmlspecialchars(strip_tags($this->consultation_notes));
        
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':scheduled_date', $this->scheduled_date);
        $stmt->bindParam(':consultation_notes', $this->consultation_notes);
        $stmt->bindParam(':id', $this->id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get consultation statistics
     */
    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                    COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show,
                    COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority,
                    AVG(lead_score) as average_lead_score,
                    COUNT(CASE WHEN email_sent = true THEN 1 END) as emails_sent,
                    COUNT(CASE WHEN admin_notified = true THEN 1 END) as admin_notified
                  FROM " . $this->table_name;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get lead scoring statistics
     */
    public function getLeadStats() {
        $query = "SELECT 
                    CASE 
                        WHEN lead_score >= 80 THEN 'hot'
                        WHEN lead_score >= 60 THEN 'warm'
                        WHEN lead_score >= 40 THEN 'cold'
                        ELSE 'very_cold'
                    END as lead_category,
                    COUNT(*) as count,
                    AVG(lead_score) as average_score
                  FROM " . $this->table_name . "
                  GROUP BY 
                    CASE 
                        WHEN lead_score >= 80 THEN 'hot'
                        WHEN lead_score >= 60 THEN 'warm'
                        WHEN lead_score >= 40 THEN 'cold'
                        ELSE 'very_cold'
                    END
                  ORDER BY average_score DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count total consultations with filters
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
     * Calculate lead score based on various factors
     */
    private function calculateLeadScore() {
        $score = 50; // Base score
        
        // Budget scoring (30% weight)
        $budgetScores = [
            'under_5k' => 20,
            '5k_15k' => 40,
            '15k_50k' => 70,
            '50k_100k' => 90,
            '100k_plus' => 100,
            'not_sure' => 30
        ];
        $score += ($budgetScores[$this->budget] ?? 30) * 0.3;
        
        // Timeline scoring (20% weight) - urgent = higher score
        $timelineScores = [
            'asap' => 100,
            '1_month' => 80,
            '3_months' => 60,
            '6_months' => 40,
            '1_year' => 20,
            'flexible' => 30
        ];
        $score += ($timelineScores[$this->timeline] ?? 30) * 0.2;
        
        // Service interest scoring (20% weight)
        $services = is_array($this->interested_services) ? $this->interested_services : json_decode($this->interested_services, true);
        if ($services) {
            $score += count($services) * 5;
        }
        
        // Business size scoring (20% weight)
        $sizeScores = [
            '1-10' => 30,
            '11-50' => 50,
            '51-200' => 70,
            '201-500' => 85,
            '500+' => 100
        ];
        $score += ($sizeScores[$this->business_size] ?? 30) * 0.2;
        
        // Industry bonus (10% weight)
        $highValueIndustries = ['technology', 'finance', 'healthcare', 'manufacturing', 'retail'];
        if ($this->industry && in_array(strtolower($this->industry), $highValueIndustries)) {
            $score += 10;
        }
        
        return min(100, max(0, round($score)));
    }
    
    /**
     * Calculate priority based on lead score and timeline
     */
    private function calculatePriority() {
        if ($this->lead_score >= 80 || $this->timeline === 'asap') {
            return 'high';
        } elseif ($this->lead_score >= 60 || $this->timeline === '1_month') {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Calculate follow-up date based on priority
     */
    private function calculateFollowUpDate() {
        $daysToAdd = $this->priority === 'high' ? 3 : 7;
        return date('Y-m-d H:i:s', strtotime("+{$daysToAdd} days"));
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
                'industry' => $this->industry,
                'business_size' => $this->business_size,
                'current_challenges' => $this->current_challenges,
                'interested_services' => is_array($this->interested_services) ? $this->interested_services : json_decode($this->interested_services, true),
                'budget' => $this->budget,
                'timeline' => $this->timeline,
                'preferred_contact_method' => $this->preferred_contact_method,
                'preferred_time' => $this->preferred_time,
                'additional_notes' => $this->additional_notes,
                'lead_score' => $this->lead_score,
                'priority' => $this->priority,
                'ip_address' => $this->ip_address,
                'user_agent' => $this->user_agent
            ];
            
            // Send user confirmation email
            $userEmailResult = $emailService->sendUserConfirmation($userData, 'consultation');
            
            // Send admin notification email
            $adminEmailResult = $emailService->sendAdminNotification($userData, 'consultation');
            
            // Update email status in database
            $this->updateEmailStatus($userEmailResult['success'], $adminEmailResult['success']);
            
        } catch (Exception $e) {
            error_log("Failed to send emails for consultation ID {$this->id}: " . $e->getMessage());
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
     * Validate consultation data
     */
    public function validate() {
        $errors = [];
        
        if (empty($this->name) || strlen($this->name) < 2 || strlen($this->name) > 100) {
            $errors[] = "Name must be between 2 and 100 characters";
        }
        
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please provide a valid email address";
        }
        
        if (empty($this->phone) || !preg_match('/^[\+]?[0-9\s\-\(\)]{7,20}$/', $this->phone)) {
            $errors[] = "Please provide a valid phone number";
        }
        
        if (empty($this->company) || strlen($this->company) < 2 || strlen($this->company) > 100) {
            $errors[] = "Company name must be between 2 and 100 characters";
        }
        
        $validBusinessSizes = ['1-10', '11-50', '51-200', '201-500', '500+'];
        if (!in_array($this->business_size, $validBusinessSizes)) {
            $errors[] = "Please select a valid business size";
        }
        
        if (empty($this->current_challenges) || strlen($this->current_challenges) < 10 || strlen($this->current_challenges) > 1000) {
            $errors[] = "Current challenges must be between 10 and 1000 characters";
        }
        
        $validServices = ['ai_websites', 'smart_chatbots', 'email_marketing', 'social_media_automation', 'custom_ai_solutions', 'consultation_only'];
        $services = is_array($this->interested_services) ? $this->interested_services : json_decode($this->interested_services, true);
        if (!$services || empty($services)) {
            $errors[] = "Please select at least one service";
        } else {
            foreach ($services as $service) {
                if (!in_array($service, $validServices)) {
                    $errors[] = "Invalid service selection: {$service}";
                }
            }
        }
        
        $validBudgets = ['under_5k', '5k_15k', '15k_50k', '50k_100k', '100k_plus', 'not_sure'];
        if (!in_array($this->budget, $validBudgets)) {
            $errors[] = "Please select a valid budget range";
        }
        
        $validTimelines = ['asap', '1_month', '3_months', '6_months', '1_year', 'flexible'];
        if (!in_array($this->timeline, $validTimelines)) {
            $errors[] = "Please select a valid timeline";
        }
        
        return $errors;
    }
}
?>
