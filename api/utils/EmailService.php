<?php
/**
 * Email Service for Ukunahi AI Backend
 * Handles all email notifications using PHPMailer
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $config;
    
    public function __construct() {
        $this->config = Config::getEmail();
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['username'];
            $this->mailer->Password = $this->config['password'];
            $this->mailer->SMTPSecure = $this->config['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->Port = $this->config['port'];
            
            // Default sender
            $this->mailer->setFrom($this->config['username'], Config::get('APP_NAME', 'Ukunahi AI'));
            
        } catch (Exception $e) {
            error_log("Email service initialization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send confirmation email to user
     */
    public function sendUserConfirmation($userData, $type = 'contact') {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userData['email'], $userData['name']);
            
            $template = $this->getTemplate($userData, $type, 'user');
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $template['subject'];
            $this->mailer->Body = $template['html'];
            $this->mailer->AltBody = $template['text'];
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("User confirmation email sent to: " . $userData['email']);
                return ['success' => true, 'message' => 'Email sent successfully'];
            }
            
        } catch (Exception $e) {
            error_log("Failed to send user confirmation email: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
        
        return ['success' => false, 'error' => 'Unknown error'];
    }
    
    /**
     * Send notification email to admin
     */
    public function sendAdminNotification($userData, $type = 'contact') {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($this->config['admin_email'], $this->config['admin_name']);
            $this->mailer->addReplyTo($userData['email'], $userData['name']);
            
            $template = $this->getTemplate($userData, $type, 'admin');
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $template['subject'];
            $this->mailer->Body = $template['html'];
            $this->mailer->AltBody = $template['text'];
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Admin notification email sent for: " . $userData['email']);
                return ['success' => true, 'message' => 'Admin notification sent'];
            }
            
        } catch (Exception $e) {
            error_log("Failed to send admin notification email: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
        
        return ['success' => false, 'error' => 'Unknown error'];
    }
    
    /**
     * Get email template based on type and recipient
     */
    private function getTemplate($data, $type, $recipient) {
        $method = "get" . ucfirst($type) . ucfirst($recipient) . "Template";
        
        if (method_exists($this, $method)) {
            return $this->$method($data);
        }
        
        // Fallback to contact template
        return $this->getContactUserTemplate($data);
    }
    
    /**
     * Contact form user confirmation template
     */
    private function getContactUserTemplate($data) {
        $appName = Config::get('APP_NAME', 'Ukunahi AI');
        $appUrl = Config::get('APP_URL', 'https://ai-site-nqsw.vercel.app');
        
        return [
            'subject' => "Thank you for contacting {$appName}",
            'html' => $this->getContactUserHtml($data, $appName, $appUrl),
            'text' => $this->getContactUserText($data, $appName, $appUrl)
        ];
    }
    
    private function getContactUserHtml($data, $appName, $appUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Thank you for contacting {$appName}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 14px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .highlight { background: #e8f4f8; padding: 15px; border-left: 4px solid #667eea; margin: 15px 0; }
                h1 { margin: 0; font-size: 24px; }
                h3 { color: #333; margin-top: 20px; }
                ul { padding-left: 20px; }
                li { margin-bottom: 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚀 Thank You for Reaching Out!</h1>
                    <p>We've received your message and we're excited to help you grow your business with AI.</p>
                </div>
                <div class='content'>
                    <p>Hi {$data['name']},</p>
                    
                    <p>Thank you for contacting <strong>{$appName}</strong>! We've successfully received your inquiry and our team is already reviewing your message.</p>
                    
                    <div class='highlight'>
                        <h3>📋 Your Inquiry Details:</h3>
                        <p><strong>Subject:</strong> {$data['subject']}</p>
                        <p><strong>Message:</strong> {$data['message']}</p>
                        " . (isset($data['company']) && $data['company'] ? "<p><strong>Company:</strong> {$data['company']}</p>" : "") . "
                        " . (isset($data['phone']) && $data['phone'] ? "<p><strong>Phone:</strong> {$data['phone']}</p>" : "") . "
                    </div>
                    
                    <p><strong>What happens next?</strong></p>
                    <ul>
                        <li>✅ Our AI business automation experts will review your inquiry</li>
                        <li>📞 We'll contact you within 24 hours to discuss your needs</li>
                        <li>💡 We'll provide personalized recommendations for your business</li>
                        <li>🚀 Together, we'll create an AI strategy to scale your SME</li>
                    </ul>
                    
                    <p>In the meantime, feel free to explore our services:</p>
                    <a href='{$appUrl}/services.html' class='button'>View Our AI Solutions</a>
                    
                    <p>Questions? Simply reply to this email or call us directly.</p>
                    
                    <p>Best regards,<br>
                    <strong>The {$appName} Team</strong><br>
                    AI-Powered Business Automation for SMEs</p>
                </div>
                <div class='footer'>
                    <p>© 2024 {$appName}. Empowering SMEs with AI-driven growth solutions.</p>
                    <p>Visit us: <a href='{$appUrl}'>{$appUrl}</a></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getContactUserText($data, $appName, $appUrl) {
        return "
        Thank you for contacting {$appName}!
        
        Hi {$data['name']},
        
        We've received your inquiry about: {$data['subject']}
        
        Your message: {$data['message']}
        
        Our team will contact you within 24 hours to discuss how we can help grow your business with AI-powered solutions.
        
        Best regards,
        The {$appName} Team
        
        Visit us: {$appUrl}
        ";
    }
    
    /**
     * Contact form admin notification template
     */
    private function getContactAdminTemplate($data) {
        return [
            'subject' => "🔔 New Contact Form Submission - {$data['name']}",
            'html' => "
                <h2>New Contact Form Submission</h2>
                <p><strong>Name:</strong> {$data['name']}</p>
                <p><strong>Email:</strong> {$data['email']}</p>
                <p><strong>Phone:</strong> " . ($data['phone'] ?? 'Not provided') . "</p>
                <p><strong>Company:</strong> " . ($data['company'] ?? 'Not provided') . "</p>
                <p><strong>Subject:</strong> {$data['subject']}</p>
                <p><strong>Message:</strong></p>
                <blockquote style='background: #f5f5f5; padding: 15px; border-left: 4px solid #667eea; margin: 15px 0;'>{$data['message']}</blockquote>
                <p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>IP Address:</strong> " . ($data['ip_address'] ?? 'Unknown') . "</p>
            ",
            'text' => "
                New Contact Form Submission
                
                Name: {$data['name']}
                Email: {$data['email']}
                Phone: " . ($data['phone'] ?? 'Not provided') . "
                Company: " . ($data['company'] ?? 'Not provided') . "
                Subject: {$data['subject']}
                
                Message:
                {$data['message']}
                
                Submitted: " . date('Y-m-d H:i:s') . "
            "
        ];
    }
    
    /**
     * Consultation booking user confirmation template
     */
    private function getConsultationUserTemplate($data) {
        $appName = Config::get('APP_NAME', 'Ukunahi AI');
        
        return [
            'subject' => "Free Consultation Booked - {$appName}",
            'html' => $this->getConsultationUserHtml($data, $appName),
            'text' => $this->getConsultationUserText($data, $appName)
        ];
    }
    
    private function getConsultationUserHtml($data, $appName) {
        $servicesText = is_array($data['interested_services']) ? implode(', ', $data['interested_services']) : $data['interested_services'];
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
                .highlight { background: #e8f4f8; padding: 15px; border-left: 4px solid #667eea; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Free Consultation Booked!</h1>
                    <p>Your AI business transformation journey starts here.</p>
                </div>
                <div class='content'>
                    <p>Hi {$data['name']},</p>
                    
                    <div class='success'>
                        <h3>✅ Consultation Successfully Booked</h3>
                        <p>We're excited to help <strong>{$data['company']}</strong> leverage AI for exponential growth!</p>
                    </div>
                    
                    <div class='highlight'>
                        <h3>📋 Your Consultation Details:</h3>
                        <p><strong>Company:</strong> {$data['company']}</p>
                        <p><strong>Industry:</strong> " . ($data['industry'] ?? 'Not specified') . "</p>
                        <p><strong>Business Size:</strong> {$data['business_size']} employees</p>
                        <p><strong>Budget Range:</strong> " . str_replace('_', '-', strtoupper($data['budget'])) . "</p>
                        <p><strong>Timeline:</strong> " . str_replace('_', ' ', $data['timeline']) . "</p>
                        <p><strong>Interested Services:</strong> {$servicesText}</p>
                        <p><strong>Preferred Contact:</strong> {$data['preferred_contact_method']}</p>
                    </div>
                    
                    <p><strong>What to expect in your FREE consultation:</strong></p>
                    <ul>
                        <li>🔍 Comprehensive business analysis</li>
                        <li>💡 Personalized AI strategy recommendations</li>
                        <li>📊 ROI projections for AI implementation</li>
                        <li>🛣️ Step-by-step implementation roadmap</li>
                        <li>💰 Custom pricing based on your needs</li>
                    </ul>
                    
                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>Our AI consultant will contact you within 4 hours</li>
                        <li>We'll schedule your consultation at your preferred time</li>
                        <li>Prepare any questions about AI automation for your business</li>
                    </ol>
                    
                    <p>Best regards,<br>
                    <strong>The {$appName} Consulting Team</strong></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getConsultationUserText($data, $appName) {
        $servicesText = is_array($data['interested_services']) ? implode(', ', $data['interested_services']) : $data['interested_services'];
        
        return "
        Free Consultation Booked - {$appName}
        
        Hi {$data['name']},
        
        Your free consultation has been successfully booked for {$data['company']}!
        
        Details:
        - Business Size: {$data['business_size']} employees
        - Budget: {$data['budget']}
        - Timeline: {$data['timeline']}
        - Services: {$servicesText}
        
        Our consultant will contact you within 4 hours to schedule your session.
        
        Best regards,
        The {$appName} Team
        ";
    }
    
    /**
     * Consultation booking admin notification template
     */
    private function getConsultationAdminTemplate($data) {
        $servicesText = is_array($data['interested_services']) ? implode(', ', $data['interested_services']) : $data['interested_services'];
        $leadScore = $data['lead_score'] ?? 'N/A';
        $priority = isset($data['priority']) ? strtoupper($data['priority']) : 'MEDIUM';
        
        return [
            'subject' => "🎯 New Consultation Booking - {$data['company']} (Lead Score: {$leadScore})",
            'html' => "
                <h2>New Free Consultation Booking</h2>
                <p><strong>Lead Score:</strong> {$leadScore}/100</p>
                <p><strong>Priority:</strong> {$priority}</p>
                <hr>
                <p><strong>Name:</strong> {$data['name']}</p>
                <p><strong>Email:</strong> {$data['email']}</p>
                <p><strong>Phone:</strong> {$data['phone']}</p>
                <p><strong>Company:</strong> {$data['company']}</p>
                <p><strong>Industry:</strong> " . ($data['industry'] ?? 'Not specified') . "</p>
                <p><strong>Business Size:</strong> {$data['business_size']} employees</p>
                <p><strong>Budget:</strong> " . str_replace('_', '-', strtoupper($data['budget'])) . "</p>
                <p><strong>Timeline:</strong> " . str_replace('_', ' ', $data['timeline']) . "</p>
                <p><strong>Services:</strong> {$servicesText}</p>
                <p><strong>Challenges:</strong></p>
                <blockquote style='background: #f5f5f5; padding: 15px; border-left: 4px solid #667eea;'>{$data['current_challenges']}</blockquote>
                <p><strong>Contact Preference:</strong> {$data['preferred_contact_method']}</p>
                <p><strong>Time Preference:</strong> {$data['preferred_time']}</p>
                " . (isset($data['additional_notes']) && $data['additional_notes'] ? "<p><strong>Notes:</strong> {$data['additional_notes']}</p>" : "") . "
                <p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
            ",
            'text' => "
                New Consultation Booking - {$data['company']}
                Lead Score: {$leadScore}/100
                Priority: {$priority}
                
                Contact: {$data['name']} ({$data['email']}, {$data['phone']})
                Company: {$data['company']} ({$data['business_size']} employees)
                Industry: " . ($data['industry'] ?? 'Not specified') . "
                Budget: {$data['budget']}
                Timeline: {$data['timeline']}
                Services: {$servicesText}
                
                Challenges: {$data['current_challenges']}
                
                Contact via: {$data['preferred_contact_method']} ({$data['preferred_time']})
            "
        ];
    }
    
    /**
     * Service inquiry user confirmation template
     */
    private function getServiceUserTemplate($data) {
        $appName = Config::get('APP_NAME', 'Ukunahi AI');
        $appUrl = Config::get('APP_URL', 'https://ai-site-nqsw.vercel.app');
        
        return [
            'subject' => "Service Inquiry Received - " . str_replace('_', ' ', strtoupper($data['service_type'])),
            'html' => $this->getServiceUserHtml($data, $appName, $appUrl),
            'text' => $this->getServiceUserText($data, $appName)
        ];
    }
    
    private function getServiceUserHtml($data, $appName, $appUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .highlight { background: #e8f4f8; padding: 15px; border-left: 4px solid #667eea; margin: 15px 0; }
                .button { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚀 Service Inquiry Received!</h1>
                    <p>We're excited to bring your AI vision to life.</p>
                </div>
                <div class='content'>
                    <p>Hi {$data['name']},</p>
                    
                    <p>Thank you for your interest in our <strong>" . str_replace('_', ' ', strtoupper($data['service_type'])) . "</strong> service!</p>
                    
                    <div class='highlight'>
                        <h3>📋 Your Project Details:</h3>
                        <p><strong>Service:</strong> " . str_replace('_', ' ', strtoupper($data['service_type'])) . "</p>
                        <p><strong>Budget:</strong> " . str_replace('_', '-', strtoupper($data['budget'])) . "</p>
                        <p><strong>Timeline:</strong> " . str_replace('_', ' ', $data['timeline']) . "</p>
                        " . (isset($data['company']) && $data['company'] ? "<p><strong>Company:</strong> {$data['company']}</p>" : "") . "
                        <p><strong>Project Description:</strong> {$data['project_description']}</p>
                    </div>
                    
                    <p><strong>What happens next?</strong></p>
                    <ul>
                        <li>📋 Our specialists will review your project requirements</li>
                        <li>💰 We'll prepare a detailed quote within 48 hours</li>
                        <li>📞 Schedule a project discussion call</li>
                        <li>🎯 Create a customized solution for your needs</li>
                    </ul>
                    
                    <p>Our team specializes in delivering high-impact AI solutions that drive real business results.</p>
                    
                    <a href='{$appUrl}/services.html' class='button'>View Our Portfolio</a>
                    
                    <p>Best regards,<br>
                    <strong>The {$appName} Development Team</strong></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getServiceUserText($data, $appName) {
        return "
        Service Inquiry Received - {$appName}
        
        Hi {$data['name']},
        
        We've received your inquiry for: " . str_replace('_', ' ', strtoupper($data['service_type'])) . "
        
        Project: {$data['project_description']}
        Budget: {$data['budget']}
        Timeline: {$data['timeline']}
        
        Our team will review your requirements and send you a detailed quote within 48 hours.
        
        Best regards,
        The {$appName} Team
        ";
    }
    
    /**
     * Service inquiry admin notification template
     */
    private function getServiceAdminTemplate($data) {
        $estimatedValue = $data['estimated_value'] ?? 'TBD';
        $priority = isset($data['priority']) ? strtoupper($data['priority']) : 'MEDIUM';
        
        return [
            'subject' => "💼 New Service Inquiry - " . strtoupper($data['service_type']) . " (Est. Value: $" . $estimatedValue . ")",
            'html' => "
                <h2>New Service Inquiry</h2>
                <p><strong>Service:</strong> " . str_replace('_', ' ', strtoupper($data['service_type'])) . "</p>
                <p><strong>Estimated Value:</strong> $" . $estimatedValue . "</p>
                <p><strong>Priority:</strong> {$priority}</p>
                <hr>
                <p><strong>Name:</strong> {$data['name']}</p>
                <p><strong>Email:</strong> {$data['email']}</p>
                <p><strong>Phone:</strong> " . ($data['phone'] ?? 'Not provided') . "</p>
                <p><strong>Company:</strong> " . ($data['company'] ?? 'Not provided') . "</p>
                <p><strong>Budget:</strong> " . str_replace('_', '-', strtoupper($data['budget'])) . "</p>
                <p><strong>Timeline:</strong> " . str_replace('_', ' ', $data['timeline']) . "</p>
                <p><strong>Current Website:</strong> " . ($data['current_website'] ?? 'Not provided') . "</p>
                <p><strong>Project Description:</strong></p>
                <blockquote style='background: #f5f5f5; padding: 15px; border-left: 4px solid #667eea;'>{$data['project_description']}</blockquote>
                " . (isset($data['current_challenges']) && $data['current_challenges'] ? "<p><strong>Current Challenges:</strong> {$data['current_challenges']}</p>" : "") . "
                <p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
            ",
            'text' => "
                New Service Inquiry - " . strtoupper($data['service_type']) . "
                Estimated Value: $" . $estimatedValue . "
                Priority: {$priority}
                
                Contact: {$data['name']} ({$data['email']})
                Company: " . ($data['company'] ?? 'Not provided') . "
                Budget: {$data['budget']}
                Timeline: {$data['timeline']}
                
                Project: {$data['project_description']}
                
                Submitted: " . date('Y-m-d H:i:s') . "
            "
        ];
    }
}
?>
