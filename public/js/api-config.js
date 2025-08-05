/**
 * API Configuration for Ukunahi AI Frontend
 * Connects frontend forms to PHP backend API
 */

// API Configuration
const API_CONFIG = {
    BASE_URL: window.location.origin + '/AI-SITE/api',
    TIMEOUT: 30000, // 30 seconds
    RETRY_ATTEMPTS: 3
};

// API Endpoints
const API_ENDPOINTS = {
    HEALTH: `${API_CONFIG.BASE_URL}/health`,
    CONTACT: `${API_CONFIG.BASE_URL}/contact`,
    CONSULTATION: `${API_CONFIG.BASE_URL}/consultation`,
    SERVICE_INQUIRY: `${API_CONFIG.BASE_URL}/services/inquiry`,
    SERVICE_TYPES: `${API_CONFIG.BASE_URL}/services/types`
};

// API Keys (if needed in future)
const API_KEYS = {
    FRONTEND: 'ukunahi-frontend-key' // Not used currently, but ready for future auth
};

/**
 * Enhanced fetch wrapper with error handling and retries
 */
class APIClient {
    constructor() {
        this.baseURL = API_CONFIG.BASE_URL;
        this.timeout = API_CONFIG.TIMEOUT;
        this.retryAttempts = API_CONFIG.RETRY_ATTEMPTS;
    }

    /**
     * Make API request with retry logic
     */
    async request(endpoint, options = {}) {
        const url = endpoint.startsWith('http') ? endpoint : `${this.baseURL}${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            timeout: this.timeout,
            ...options
        };

        let lastError;
        
        for (let attempt = 1; attempt <= this.retryAttempts; attempt++) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), this.timeout);
                
                const response = await fetch(url, {
                    ...defaultOptions,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new APIError(data.error || 'Request failed', response.status, data);
                }
                
                return data;
                
            } catch (error) {
                lastError = error;
                
                // Don't retry on client errors (4xx) except 429 (rate limit)
                if (error.status >= 400 && error.status < 500 && error.status !== 429) {
                    throw error;
                }
                
                // Don't retry on the last attempt
                if (attempt === this.retryAttempts) {
                    throw error;
                }
                
                // Wait before retrying (exponential backoff)
                const delay = Math.min(1000 * Math.pow(2, attempt - 1), 10000);
                await new Promise(resolve => setTimeout(resolve, delay));
            }
        }
        
        throw lastError;
    }

    /**
     * GET request
     */
    async get(endpoint, params = {}) {
        const url = new URL(endpoint.startsWith('http') ? endpoint : `${this.baseURL}${endpoint}`);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.append(key, params[key]);
            }
        });
        
        return this.request(url.toString(), { method: 'GET' });
    }

    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
}

/**
 * Custom API Error class
 */
class APIError extends Error {
    constructor(message, status, data = null) {
        super(message);
        this.name = 'APIError';
        this.status = status;
        this.data = data;
    }
}

// Create global API client instance
const apiClient = new APIClient();

/**
 * API Helper Functions
 */
const API = {
    // Health check
    async checkHealth() {
        return apiClient.get('/health');
    },

    // Contact form submission
    async submitContact(formData) {
        return apiClient.post('/contact', {
            name: formData.full_name || formData.name,
            email: formData.email,
            phone: formData.phone || '',
            company: formData.company || '',
            subject: formData.service || formData.subject || 'General Inquiry',
            message: formData.message
        });
    },

    // Consultation booking
    async bookConsultation(formData) {
        return apiClient.post('/consultation', {
            name: formData.name,
            email: formData.email,
            phone: formData.phone,
            company: formData.company,
            industry: formData.industry || '',
            business_size: formData.business_size,
            current_challenges: formData.current_challenges,
            interested_services: formData.interested_services || [],
            budget: formData.budget,
            timeline: formData.timeline,
            preferred_contact_method: formData.preferred_contact_method || 'email',
            preferred_time: formData.preferred_time || 'flexible',
            timezone: formData.timezone || '',
            additional_notes: formData.additional_notes || ''
        });
    },

    // Service inquiry submission
    async submitServiceInquiry(formData) {
        return apiClient.post('/services/inquiry', {
            name: formData.name,
            email: formData.email,
            phone: formData.phone || '',
            company: formData.company || '',
            service_type: formData.service_type,
            project_description: formData.project_description,
            budget: formData.budget,
            timeline: formData.timeline,
            current_website: formData.current_website || '',
            current_challenges: formData.current_challenges || '',
            specific_requirements: formData.specific_requirements || [],
            target_audience: formData.target_audience || '',
            competitor_websites: formData.competitor_websites || [],
            preferred_style: formData.preferred_style || 'not_sure',
            additional_services: formData.additional_services || []
        });
    },

    // Get available service types
    async getServiceTypes() {
        return apiClient.get('/services/types');
    }
};

// Form validation helpers
const FormValidator = {
    /**
     * Validate email format
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },

    /**
     * Validate phone number format
     */
    isValidPhone(phone) {
        if (!phone) return true; // Phone is optional
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{7,20}$/;
        return phoneRegex.test(phone);
    },

    /**
     * Validate required fields
     */
    validateRequired(data, requiredFields) {
        const errors = {};
        
        requiredFields.forEach(field => {
            if (!data[field] || data[field].toString().trim() === '') {
                errors[field] = `${field.replace('_', ' ')} is required`;
            }
        });
        
        return errors;
    },

    /**
     * Validate contact form
     */
    validateContactForm(formData) {
        const errors = this.validateRequired(formData, ['name', 'email', 'subject', 'message']);
        
        if (formData.email && !this.isValidEmail(formData.email)) {
            errors.email = 'Please enter a valid email address';
        }
        
        if (formData.phone && !this.isValidPhone(formData.phone)) {
            errors.phone = 'Please enter a valid phone number';
        }
        
        if (formData.name && (formData.name.length < 2 || formData.name.length > 100)) {
            errors.name = 'Name must be between 2 and 100 characters';
        }
        
        if (formData.subject && (formData.subject.length < 5 || formData.subject.length > 200)) {
            errors.subject = 'Subject must be between 5 and 200 characters';
        }
        
        if (formData.message && (formData.message.length < 10 || formData.message.length > 2000)) {
            errors.message = 'Message must be between 10 and 2000 characters';
        }
        
        return errors;
    }
};

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { API_CONFIG, API_ENDPOINTS, API_KEYS, API, APIClient, APIError, FormValidator };
}
