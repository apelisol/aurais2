# Ukunahi AI PHP Backend API

Complete PHP backend for the Ukunahi AI business automation platform with PostgreSQL database and email notifications.

## 🚀 Quick Setup

### Prerequisites
- XAMPP with PHP 7.4+ and PostgreSQL
- Composer (PHP package manager)
- PostgreSQL database server

### 1. Install Dependencies
```bash
cd c:\xampp\htdocs\AI-SITE\api
composer install
```

### 2. Database Setup
1. Create PostgreSQL database:
```sql
CREATE DATABASE ukunahi_ai;
```

2. Run the schema:
```bash
psql -U postgres -d ukunahi_ai -f database/schema.sql
```

### 3. Configure Environment
1. Update `.env` file with your settings:
```env
# Database
DB_HOST=localhost
DB_NAME=ukunahi_ai
DB_USER=postgres
DB_PASS=your_postgres_password

# Email (Gmail example)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password
ADMIN_EMAIL=admin@ukunahi.com
```

### 4. Test the API
Visit: `http://localhost/AI-SITE/api/health`

You should see:
```json
{
  "success": true,
  "message": "Ukunahi AI Backend API is running",
  "data": {
    "status": "OK",
    "timestamp": "2024-01-01T12:00:00+00:00",
    "version": "v1"
  }
}
```

## 📡 API Endpoints

### Health Check
- `GET /api/health` - API status

### Contact Forms
- `POST /api/contact` - Submit contact form
- `GET /api/contact` - List all contacts (admin)
- `GET /api/contact/{id}` - Get specific contact
- `PUT /api/contact/{id}/status` - Update contact status
- `GET /api/contact/stats/summary` - Contact statistics

### Consultations
- `POST /api/consultation` - Book free consultation
- `GET /api/consultation` - List consultations (admin)
- `GET /api/consultation/{id}` - Get specific consultation
- `PUT /api/consultation/{id}/status` - Update consultation status
- `GET /api/consultation/stats/summary` - Consultation statistics
- `GET /api/consultation/stats/leads` - Lead scoring statistics

### Service Inquiries
- `POST /api/services/inquiry` - Submit service inquiry
- `GET /api/services/inquiry` - List service inquiries (admin)
- `GET /api/services/inquiry/{id}` - Get specific inquiry
- `PUT /api/services/inquiry/{id}/status` - Update inquiry status
- `GET /api/services/stats/summary` - Service statistics
- `GET /api/services/stats/by-service` - Statistics by service type
- `GET /api/services/types` - Available service types

## 📝 Example API Calls

### Submit Contact Form
```javascript
fetch('http://localhost/AI-SITE/api/contact', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'John Doe',
    email: 'john@example.com',
    phone: '+1234567890',
    company: 'Example Corp',
    subject: 'Website Inquiry',
    message: 'I need a new website for my business.'
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

### Book Free Consultation
```javascript
fetch('http://localhost/AI-SITE/api/consultation', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'Jane Smith',
    email: 'jane@company.com',
    phone: '+1234567890',
    company: 'Smith Corp',
    industry: 'Technology',
    business_size: '11-50',
    current_challenges: 'Need to automate customer service',
    interested_services: ['smart_chatbots', 'ai_websites'],
    budget: '15k_50k',
    timeline: '3_months',
    preferred_contact_method: 'email',
    preferred_time: 'afternoon'
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

### Submit Service Inquiry
```javascript
fetch('http://localhost/AI-SITE/api/services/inquiry', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'Bob Johnson',
    email: 'bob@startup.com',
    service_type: 'ai_website',
    project_description: 'Need a modern AI-powered website for my startup',
    budget: '5k_15k',
    timeline: '1_month',
    current_website: 'https://oldsite.com',
    additional_services: ['seo', 'maintenance']
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

## 🔧 Features

### Email Notifications
- **User Confirmations**: Professional email templates for all form submissions
- **Admin Notifications**: Detailed notifications with all submission data
- **SMTP Support**: Works with Gmail, Outlook, and other SMTP providers
- **Email Tracking**: Tracks delivery status in database

### Database Models
- **Contacts**: Basic contact form submissions
- **Consultations**: Free consultation bookings with lead scoring (0-100)
- **Service Inquiries**: Detailed service requests with value estimation

### Security & Performance
- **Rate Limiting**: Prevents API abuse (100 requests per 15 minutes)
- **Input Validation**: Comprehensive validation for all inputs
- **CORS Support**: Configurable cross-origin resource sharing
- **Error Handling**: Standardized error responses
- **SQL Injection Protection**: PDO prepared statements

### Lead Scoring System
Consultations are automatically scored based on:
- Budget range (30% weight)
- Timeline urgency (20% weight)
- Number of services (20% weight)
- Business size (20% weight)
- Industry type (10% weight)

### Value Estimation
Service inquiries get automatic value estimation based on:
- Service type base values
- Budget multipliers
- Timeline urgency
- Additional services

## 🎨 Frontend Integration

### HTML Form Example
```html
<form id="contactForm">
  <input type="text" name="name" required>
  <input type="email" name="email" required>
  <input type="tel" name="phone">
  <input type="text" name="company">
  <input type="text" name="subject" required>
  <textarea name="message" required></textarea>
  <button type="submit">Send Message</button>
</form>

<script>
document.getElementById('contactForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const data = Object.fromEntries(formData);
  
  try {
    const response = await fetch('/AI-SITE/api/contact', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert('Thank you! Your message has been sent.');
      e.target.reset();
    } else {
      alert('Error: ' + result.error);
    }
  } catch (error) {
    alert('Network error. Please try again.');
  }
});
</script>
```

## 🛠️ Development

### File Structure
```
api/
├── config/
│   ├── database.php      # Database connection
│   └── config.php        # Configuration manager
├── models/
│   ├── Contact.php       # Contact model
│   ├── Consultation.php  # Consultation model
│   └── ServiceInquiry.php # Service inquiry model
├── endpoints/
│   ├── contact.php       # Contact API
│   ├── consultation.php  # Consultation API
│   └── services.php      # Services API
├── utils/
│   ├── EmailService.php  # Email handling
│   ├── ResponseHandler.php # API responses
│   └── RateLimiter.php   # Rate limiting
├── database/
│   └── schema.sql        # Database schema
├── storage/              # File storage (auto-created)
├── vendor/               # Composer dependencies
├── .env                  # Environment variables
├── .htaccess            # Apache configuration
├── composer.json        # PHP dependencies
├── index.php            # Main API router
└── README.md            # This file
```

### Adding New Endpoints
1. Create new model in `models/`
2. Create new endpoint in `endpoints/`
3. Add route in `index.php`
4. Update `.htaccess` if needed

## 🚀 Production Deployment

1. Set `DEBUG=false` in `.env`
2. Use strong passwords and secrets
3. Configure SSL certificates
4. Set up database backups
5. Monitor logs and performance
6. Use a process manager like PM2 for Node.js or similar for PHP

## 📞 Support

For questions or issues:
- Check the logs in XAMPP error logs
- Verify database connection
- Test email configuration
- Check file permissions

## 🔒 Security Notes

- Never commit `.env` file to version control
- Use strong database passwords
- Enable HTTPS in production
- Regularly update dependencies
- Monitor for suspicious activity
