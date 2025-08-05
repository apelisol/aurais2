-- Ukunahi AI Database Schema for PostgreSQL
-- Run this script to create the required tables

-- Create database (run this separately if needed)
-- CREATE DATABASE ukunahi_ai;

-- Use the database
-- \c ukunahi_ai;

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Contacts table for contact form submissions
CREATE TABLE contacts (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    company VARCHAR(100),
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    source VARCHAR(50) DEFAULT 'contact_form',
    status VARCHAR(20) DEFAULT 'new' CHECK (status IN ('new', 'in_progress', 'resolved', 'closed')),
    priority VARCHAR(10) DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    ip_address INET,
    user_agent TEXT,
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP,
    admin_notified BOOLEAN DEFAULT FALSE,
    admin_notified_at TIMESTAMP,
    notes JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Consultations table for free consultation bookings
CREATE TABLE consultations (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    company VARCHAR(100) NOT NULL,
    industry VARCHAR(50),
    business_size VARCHAR(10) NOT NULL CHECK (business_size IN ('1-10', '11-50', '51-200', '201-500', '500+')),
    current_challenges TEXT NOT NULL,
    interested_services JSONB NOT NULL DEFAULT '[]',
    budget VARCHAR(20) NOT NULL CHECK (budget IN ('under_5k', '5k_15k', '15k_50k', '50k_100k', '100k_plus', 'not_sure')),
    timeline VARCHAR(20) NOT NULL CHECK (timeline IN ('asap', '1_month', '3_months', '6_months', '1_year', 'flexible')),
    preferred_contact_method VARCHAR(20) DEFAULT 'email' CHECK (preferred_contact_method IN ('email', 'phone', 'video_call', 'in_person')),
    preferred_time VARCHAR(20) DEFAULT 'flexible' CHECK (preferred_time IN ('morning', 'afternoon', 'evening', 'flexible')),
    timezone VARCHAR(50),
    additional_notes TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'scheduled', 'completed', 'cancelled', 'no_show')),
    priority VARCHAR(10) DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    lead_score INTEGER DEFAULT 50 CHECK (lead_score >= 0 AND lead_score <= 100),
    scheduled_date TIMESTAMP,
    consultation_notes TEXT,
    follow_up_required BOOLEAN DEFAULT TRUE,
    follow_up_date TIMESTAMP,
    ip_address INET,
    user_agent TEXT,
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP,
    admin_notified BOOLEAN DEFAULT FALSE,
    admin_notified_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Service inquiries table for service requests
CREATE TABLE service_inquiries (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    company VARCHAR(100),
    service_type VARCHAR(50) NOT NULL CHECK (service_type IN ('ai_website', 'smart_chatbot', 'email_marketing', 'social_media_automation', 'custom_ai_solution', 'consultation')),
    project_description TEXT NOT NULL,
    budget VARCHAR(20) NOT NULL CHECK (budget IN ('under_5k', '5k_15k', '15k_50k', '50k_100k', '100k_plus', 'not_sure')),
    timeline VARCHAR(20) NOT NULL CHECK (timeline IN ('asap', '1_month', '3_months', '6_months', '1_year', 'flexible')),
    current_website VARCHAR(200),
    current_challenges TEXT,
    specific_requirements JSONB DEFAULT '[]',
    target_audience VARCHAR(500),
    competitor_websites JSONB DEFAULT '[]',
    preferred_style VARCHAR(20) DEFAULT 'not_sure' CHECK (preferred_style IN ('modern', 'classic', 'minimalist', 'bold', 'corporate', 'creative', 'not_sure')),
    additional_services JSONB DEFAULT '[]',
    status VARCHAR(20) DEFAULT 'new' CHECK (status IN ('new', 'reviewing', 'quoted', 'approved', 'in_progress', 'completed', 'cancelled')),
    priority VARCHAR(10) DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    estimated_value DECIMAL(10,2) DEFAULT 0,
    quote_sent BOOLEAN DEFAULT FALSE,
    quote_sent_at TIMESTAMP,
    quote_amount DECIMAL(10,2),
    follow_up_date TIMESTAMP,
    assigned_to VARCHAR(100),
    ip_address INET,
    user_agent TEXT,
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP,
    admin_notified BOOLEAN DEFAULT FALSE,
    admin_notified_at TIMESTAMP,
    notes JSONB DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX idx_contacts_email ON contacts(email);
CREATE INDEX idx_contacts_status ON contacts(status);
CREATE INDEX idx_contacts_created_at ON contacts(created_at DESC);
CREATE INDEX idx_contacts_priority ON contacts(priority);

CREATE INDEX idx_consultations_email ON consultations(email);
CREATE INDEX idx_consultations_status ON consultations(status);
CREATE INDEX idx_consultations_priority ON consultations(priority);
CREATE INDEX idx_consultations_lead_score ON consultations(lead_score DESC);
CREATE INDEX idx_consultations_created_at ON consultations(created_at DESC);
CREATE INDEX idx_consultations_scheduled_date ON consultations(scheduled_date);

CREATE INDEX idx_service_inquiries_email ON service_inquiries(email);
CREATE INDEX idx_service_inquiries_service_type ON service_inquiries(service_type);
CREATE INDEX idx_service_inquiries_status ON service_inquiries(status);
CREATE INDEX idx_service_inquiries_priority ON service_inquiries(priority);
CREATE INDEX idx_service_inquiries_estimated_value ON service_inquiries(estimated_value DESC);
CREATE INDEX idx_service_inquiries_created_at ON service_inquiries(created_at DESC);

-- Create function to automatically update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers to automatically update updated_at
CREATE TRIGGER update_contacts_updated_at BEFORE UPDATE ON contacts
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_consultations_updated_at BEFORE UPDATE ON consultations
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_service_inquiries_updated_at BEFORE UPDATE ON service_inquiries
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert some sample data for testing (optional)
-- INSERT INTO contacts (name, email, subject, message) VALUES 
-- ('John Doe', 'john@example.com', 'Website Inquiry', 'I need a new website for my business.');

-- INSERT INTO consultations (name, email, phone, company, business_size, current_challenges, interested_services, budget, timeline) VALUES 
-- ('Jane Smith', 'jane@company.com', '+1234567890', 'Smith Corp', '11-50', 'Need to automate customer service', '["smart_chatbots", "ai_websites"]', '15k_50k', '3_months');

-- INSERT INTO service_inquiries (name, email, service_type, project_description, budget, timeline) VALUES 
-- ('Bob Johnson', 'bob@startup.com', 'ai_website', 'Need a modern AI-powered website for my startup', '5k_15k', '1_month');
