<?php
/**
 * ServiceInquiry Model for Ukunahi AI Backend
 * Handles service inquiry submissions with value estimation
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/EmailService.php';

class ServiceInquiry {
    private $conn;
    private $table_name = "service_inquiries";
    
    // Service inquiry properties
    public $id;
    public $name;
    public $email;
    public $phone;
    public $company;
    public $service_type;
    public $project_description;
    public $budget;
    public $timeline;
    public $current_website;
    public $current_challenges;
    public $specific_requirements;
    public $target_audience;
    public $competitor_websites;
    public $preferred_style;
    public $additional_services;
    public $status;
    public $priority;
    public $estimated_value;
    public $quote_sent;
    public $quote_sent_at;
    public $quote_amount;
    public $follow_up_date;
    public $assigned_to;
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
     * Create a new service inquiry
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (name, email, phone, company, service_type, project_description, budget, timeline, 
                   current_website, current_challenges, specific_requirements, target_audience, 
                   competitor_websites, preferred_style, additional_services, status, priority, 
                   estimated_value, follow_up_date, ip_address, user_agent, notes) 
                  VALUES 
                  (:name, :email, :phone, :company, :service_type, :project_description, :budget, :timeline, 
                   :current_website, :current_challenges, :specific_requirements, :target_audience, 
                   :competitor_websites, :preferred_style, :additional_services, :status, :priority, 
                   :estimated_value, :follow_up_date, :ip_address, :user_agent, :notes)
                  RETURNING id, created_at";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = filter_var($this->email, FILTER_SANITIZE_EMAIL);
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->company = htmlspecialchars(strip_tags($this->company));
        $this->project_description = htmlspecialchars(strip_tags($this->project_description));
        $this->current_website = filter_var($this->current_website, FILTER_SANITIZE_URL);
        $this->current_challenges = htmlspecialchars(strip_tags($this->current_challenges));
        $this->target_audience = htmlspecialchars(strip_tags($this->target_audience));
        $this->assigned_to = htmlspecialchars(strip_tags($this->assigned_to));
        
        // Set defaults
        $this->status = $this->status ?: 'new';
        $this->preferred_style = $this->preferred_style ?: 'not_sure';
        $this->notes = $this->notes ?: '[]';
        
        // Ensure JSON fields are properly formatted
        if (is_array($this->specific_requirements)) {
            $this->specific_requirements = json_encode($this->specific_requirements);
        } else {
            $this->specific_requirements = $this->specific_requirements ?: '[]';
        }
        
        if (is_array($this->competitor_websites)) {
            $this->competitor_websites = json_encode($this->competitor_websites);
        } else {
            $this->competitor_websites = $this->competitor_websites ?: '[]';
        }
        
        if (is_array($this->additional_services)) {
            $this->additional_services = json_encode($this->additional_services);
        } else {
            $this->additional_services = $this->additional_services ?: '[]';
        }
        
        // Calculate estimated value and priority
        $this->estimated_value = $this->calculateEstimatedValue();
        $this->priority = $this->calculatePriority();
        $this->follow_up_date = $this->calculateFollowUpDate();
        
        // Bind values
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":company", $this->company);
        $stmt->bindParam(":service_type", $this->service_type);
        $stmt->bindParam(":project_description", $this->project_description);
        $stmt->bindParam(":budget", $this->budget);
        $stmt->bindParam(":timeline", $this->timeline);
        $stmt->bindParam(":current_website", $this->current_website);
        $stmt->bindParam(":current_challenges", $this->current_challenges);
        $stmt->bindParam(":specific_requirements", $this->specific_requirements);
        $stmt->bindParam(":target_audience", $this->target_audience);
        $stmt->bindParam(":competitor_websites", $this->competitor_websites);
        $stmt->bindParam(":preferred_style", $this->preferred_style);
        $stmt->bindParam(":additional_services", $this->additional_services);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":priority", $this->priority);
        $stmt->bindParam(":estimated_value", $this->estimated_value);
        $stmt->bindParam(":follow_up_date", $this->follow_up_date);
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
     * Read all service inquiries with pagination
     */
    public function read($page = 1, $limit = 10, $serviceType = null, $status = null, $priority = null, $sortBy = 'created_at', $sortOrder = 'DESC') {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT id, name, email, phone, company, service_type, budget, timeline, 
                         status, priority, estimated_value, quote_sent, quote_amount, 
                         assigned_to, email_sent, admin_notified, created_at, updated_at 
                  FROM " . $this->table_name;
        
        $conditions = [];
        $params = [];
        
        if ($serviceType) {
            $conditions[] = "service_type = :service_type";
            $params[':service_type'] = $serviceType;
        }
        
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
        
        $allowedSortFields = ['created_at', 'estimated_value', 'priority', 'service_type'];
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
     * Read single service inquiry by ID
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
            $this->specific_requirements = json_decode($this->specific_requirements, true) ?: [];
            $this->competitor_websites = json_decode($this->competitor_websites, true) ?: [];
            $this->additional_services = json_decode($this->additional_services, true) ?: [];
            $this->notes = json_decode($this->notes, true) ?: [];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Update service inquiry status
     */
    public function updateStatus() {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = :status, quote_amount = :quote_amount, assigned_to = :assigned_to, notes = :notes";
        
        if ($this->status === 'quoted' && $this->quote_amount) {
            $query .= ", quote_sent = true, quote_sent_at = CURRENT_TIMESTAMP";
        }
        
        $query .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->assigned_to = htmlspecialchars(strip_tags($this->assigned_to));
        
        // Handle notes array
        if (is_array($this->notes)) {
            $this->notes = json_encode($this->notes);
        }
        
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':quote_amount', $this->quote_amount);
        $stmt->bindParam(':assigned_to', $this->assigned_to);
        $stmt->bindParam(':notes', $this->notes);
        $stmt->bindParam(':id', $this->id);
        
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get service inquiry statistics
     */
    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'new' THEN 1 END) as new,
                    COUNT(CASE WHEN status = 'reviewing' THEN 1 END) as reviewing,
                    COUNT(CASE WHEN status = 'quoted' THEN 1 END) as quoted,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                    SUM(estimated_value) as total_estimated_value,
                    AVG(estimated_value) as average_estimated_value,
                    SUM(quote_amount) as total_quote_amount,
                    AVG(quote_amount) as average_quote_amount,
                    COUNT(CASE WHEN email_sent = true THEN 1 END) as emails_sent,
                    COUNT(CASE WHEN admin_notified = true THEN 1 END) as admin_notified
                  FROM " . $this->table_name;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get statistics by service type
     */
    public function getStatsByService() {
        $query = "SELECT 
                    service_type,
                    COUNT(*) as count,
                    SUM(estimated_value) as total_value,
                    AVG(estimated_value) as average_value,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
                  FROM " . $this->table_name . "
                  GROUP BY service_type
                  ORDER BY total_value DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count total service inquiries with filters
     */
    public function count($serviceType = null, $status = null, $priority = null) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        
        $conditions = [];
        $params = [];
        
        if ($serviceType) {
            $conditions[] = "service_type = :service_type";
            $params[':service_type'] = $serviceType;
        }
        
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
     * Calculate estimated value based on service type, budget, and other factors
     */
    private function calculateEstimatedValue() {
        // Base values for different services
        $serviceBaseValues = [
            'ai_website' => 15000,
            'smart_chatbot' => 8000,
            'email_marketing' => 5000,
            'social_media_automation' => 6000,
            'custom_ai_solution' => 25000,
            'consultation' => 2000
        ];
        
        $estimatedValue = $serviceBaseValues[$this->service_type] ?? 10000;
        
        // Adjust based on budget
        $budgetMultipliers = [
            'under_5k' => 0.3,
            '5k_15k' => 0.6,
            '15k_50k' => 1.0,
            '50k_100k' => 1.5,
            '100k_plus' => 2.0,
            'not_sure' => 0.8
        ];
        
        $estimatedValue *= ($budgetMultipliers[$this->budget] ?? 0.8);
        
        // Adjust based on timeline (urgent = premium)
        $timelineMultipliers = [
            'asap' => 1.5,
            '1_month' => 1.2,
            '3_months' => 1.0,
            '6_months' => 0.9,
            '1_year' => 0.8,
            'flexible' => 0.9
        ];
        
        $estimatedValue *= ($timelineMultipliers[$this->timeline] ?? 1.0);
        
        // Add value for additional services
        $additionalServiceValues = [
            'seo' => 3000,
            'content_creation' => 2000,
            'maintenance' => 1500,
            'training' => 1000,
            'analytics' => 1000,
            'hosting' => 500
        ];
        
        $additionalServices = is_array($this->additional_services) ? $this->additional_services : json_decode($this->additional_services, true);
        if ($additionalServices) {
            foreach ($additionalServices as $service) {
                $estimatedValue += ($additionalServiceValues[$service] ?? 0);
            }
        }
        
        return round($estimatedValue, 2);
    }
    
    /**
     * Calculate priority based on estimated value and timeline
     */
    private function calculatePriority() {
        if ($this->estimated_value >= 50000 || $this->timeline === 'asap') {
            return 'high';
        } elseif ($this->estimated_value >= 20000 || $this->timeline === '1_month') {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Calculate follow-up date based on priority
     */
    private function calculateFollowUpDate() {
        $daysToAdd = $this->priority === 'high' ? 1 : ($this->priority === 'medium' ? 3 : 7);
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
                'service_type' => $this->service_type,
                'project_description' => $this->project_description,
                'budget' => $this->budget,
                'timeline' => $this->timeline,
                'current_website' => $this->current_website,
                'current_challenges' => $this->current_challenges,
                'specific_requirements' => is_array($this->specific_requirements) ? $this->specific_requirements : json_decode($this->specific_requirements, true),
                'additional_services' => is_array($this->additional_services) ? $this->additional_services : json_decode($this->additional_services, true),
                'estimated_value' => $this->estimated_value,
                'priority' => $this->priority,
                'ip_address' => $this->ip_address,
                'user_agent' => $this->user_agent
            ];
            
            // Send user confirmation email
            $userEmailResult = $emailService->sendUserConfirmation($userData, 'service');
            
            // Send admin notification email
            $adminEmailResult = $emailService->sendAdminNotification($userData, 'service');
            
            // Update email status in database
            $this->updateEmailStatus($userEmailResult['success'], $adminEmailResult['success']);
            
        } catch (Exception $e) {
            error_log("Failed to send emails for service inquiry ID {$this->id}: " . $e->getMessage());
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
     * Validate service inquiry data
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
        
        $validServiceTypes = ['ai_website', 'smart_chatbot', 'email_marketing', 'social_media_automation', 'custom_ai_solution', 'consultation'];
        if (!in_array($this->service_type, $validServiceTypes)) {
            $errors[] = "Please select a valid service type";
        }
        
        if (empty($this->project_description) || strlen($this->project_description) < 20 || strlen($this->project_description) > 2000) {
            $errors[] = "Project description must be between 20 and 2000 characters";
        }
        
        $validBudgets = ['under_5k', '5k_15k', '15k_50k', '50k_100k', '100k_plus', 'not_sure'];
        if (!in_array($this->budget, $validBudgets)) {
            $errors[] = "Please select a valid budget range";
        }
        
        $validTimelines = ['asap', '1_month', '3_months', '6_months', '1_year', 'flexible'];
        if (!in_array($this->timeline, $validTimelines)) {
            $errors[] = "Please select a valid timeline";
        }
        
        if (!empty($this->current_website) && !filter_var($this->current_website, FILTER_VALIDATE_URL)) {
            $errors[] = "Please provide a valid website URL";
        }
        
        return $errors;
    }
    
    /**
     * Get available service types
     */
    public static function getServiceTypes() {
        return [
            [
                'id' => 'ai_website',
                'name' => 'AI-Powered Website',
                'description' => 'High-converting, SEO-optimized websites tailored for business growth',
                'base_price' => 15000,
                'features' => ['AI-driven design', 'SEO optimization', 'Mobile responsive', 'CMS integration']
            ],
            [
                'id' => 'smart_chatbot',
                'name' => 'Smart Chatbot',
                'description' => '24/7 automated customer support for lead generation and customer service',
                'base_price' => 8000,
                'features' => ['Natural language processing', '24/7 availability', 'Lead qualification', 'Multi-platform integration']
            ],
            [
                'id' => 'email_marketing',
                'name' => 'Email Marketing Automation',
                'description' => 'AI-driven campaigns to increase open rates and conversions',
                'base_price' => 5000,
                'features' => ['Automated campaigns', 'Personalization', 'Analytics', 'A/B testing']
            ],
            [
                'id' => 'social_media_automation',
                'name' => 'Social Media Automation',
                'description' => 'AI-generated captions and content scheduling for enhanced audience engagement',
                'base_price' => 6000,
                'features' => ['Content generation', 'Scheduling', 'Analytics', 'Multi-platform support']
            ],
            [
                'id' => 'custom_ai_solution',
                'name' => 'Custom AI Solution',
                'description' => 'Tailored AI solutions designed specifically for your business needs',
                'base_price' => 25000,
                'features' => ['Custom development', 'AI model training', 'Integration support', 'Ongoing maintenance']
            ],
            [
                'id' => 'consultation',
                'name' => 'AI Strategy Consultation',
                'description' => 'Expert consultation to develop your AI transformation roadmap',
                'base_price' => 2000,
                'features' => ['Business analysis', 'AI strategy', 'Implementation roadmap', 'ROI projections']
            ]
        ];
    }
}
?>
