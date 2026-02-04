// js/email.js - CORRECTED VERSION
class EmailHandler {
    constructor() {
        this.form = document.getElementById('contactForm');
        this.csrfTokenInput = document.getElementById('csrf_token');
        this.recaptchaTokenInput = document.getElementById('recaptcha_token');
        this.submitBtn = document.getElementById('submitBtn');
        this.submitText = document.getElementById('submitText');
        this.submitLoading = document.getElementById('submitLoading');
        this.charCount = document.getElementById('char-count');
        this.messageInput = document.getElementById('message');
        
        // reCAPTCHA site key - REPLACE WITH YOUR KEY
        this.recaptchaSiteKey = '6LdJ1WAsAAAAAFOECIxb8pH7fPvf1IglV59i0OWD'; // Replace with actual key
        
        this.init();
    }
    
    init() {
        if (!this.form) return;
        
        // Generate CSRF token
        this.generateCSRFToken();
        
        // Initialize character counter
        this.initCharCounter();
        
        // Initialize form validation
        this.initFormValidation();
        
        // Handle form submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }
    
    generateCSRFToken() {
        // Generate a simple CSRF token (in production, get from server)
        const token = Math.random().toString(36).substring(2) + 
                     Date.now().toString(36) + 
                     Math.random().toString(36).substring(2);
        this.csrfTokenInput.value = token;
        sessionStorage.setItem('csrf_token', token);
    }
    
    initCharCounter() {
        if (!this.messageInput || !this.charCount) return;
        
        const updateCounter = () => {
            const length = this.messageInput.value.length;
            this.charCount.textContent = length;
            
            if (length > 4500) {
                this.charCount.classList.add('error');
                this.charCount.classList.remove('warning');
            } else if (length > 4000) {
                this.charCount.classList.add('warning');
                this.charCount.classList.remove('error');
            } else {
                this.charCount.classList.remove('warning', 'error');
            }
        };
        
        this.messageInput.addEventListener('input', updateCounter);
        updateCounter(); // Initial count
    }
    
    initFormValidation() {
        const inputs = this.form.querySelectorAll('input[required], textarea[required]');
        
        inputs.forEach(input => {
            // Real-time validation on blur
            input.addEventListener('blur', () => this.validateField(input));
            // Clear error on input
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }
    
    validateField(field) {
        const formGroup = field.closest('.form-group');
        const errorElement = formGroup ? formGroup.querySelector('.error-message') : null;
        
        if (!formGroup || !errorElement) return true;
        
        // Clear previous error state
        formGroup.classList.remove('error', 'success');
        errorElement.textContent = '';
        
        const value = field.value.trim();
        const fieldId = field.id;
        
        // Check required fields
        if (field.hasAttribute('required') && !value) {
            this.setFieldError(formGroup, errorElement, 'Detta fält är obligatoriskt');
            return false;
        }
        
        // Field-specific validations
        switch(fieldId) {
            case 'name':
                if (value.length < 2) {
                    this.setFieldError(formGroup, errorElement, 'Namnet måste vara minst 2 tecken långt');
                    return false;
                }
                if (value.length > 100) {
                    this.setFieldError(formGroup, errorElement, 'Namnet får inte vara längre än 100 tecken');
                    return false;
                }
                break;
                
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    this.setFieldError(formGroup, errorElement, 'Ange en giltig e-postadress');
                    return false;
                }
                break;
                
            case 'subject':
                if (value.length < 3) {
                    this.setFieldError(formGroup, errorElement, 'Ämnet måste vara minst 3 tecken långt');
                    return false;
                }
                if (value.length > 200) {
                    this.setFieldError(formGroup, errorElement, 'Ämnet får inte vara längre än 200 tecken');
                    return false;
                }
                break;
                
            case 'message':
                if (value.length < 10) {
                    this.setFieldError(formGroup, errorElement, 'Meddelandet måste vara minst 10 tecken långt');
                    return false;
                }
                if (value.length > 5000) {
                    this.setFieldError(formGroup, errorElement, 'Meddelandet får inte vara längre än 5000 tecken');
                    return false;
                }
                break;
        }
        
        // If valid
        formGroup.classList.add('success');
        return true;
    }
    
    setFieldError(formGroup, errorElement, message) {
        formGroup.classList.add('error');
        formGroup.classList.remove('success');
        errorElement.textContent = message;
    }
    
    clearFieldError(field) {
        const formGroup = field.closest('.form-group');
        const errorElement = formGroup ? formGroup.querySelector('.error-message') : null;
        
        if (formGroup && errorElement) {
            formGroup.classList.remove('error');
            errorElement.textContent = '';
        }
    }
    
    validateForm() {
        let isValid = true;
        const requiredFields = this.form.querySelectorAll('input[required], textarea[required]');
        
        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    async handleSubmit(event) {
        event.preventDefault();
        
        console.log('Form submission started');
        
        // Validate form
        if (!this.validateForm()) {
            this.showMessage('Vänligen korrigera felen i formuläret.', 'error');
            
            // Scroll to first error
            const firstError = this.form.querySelector('.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        
        console.log('Form validation passed');
        
        // Execute reCAPTCHA
        let recaptchaToken;
        try {
            console.log('Executing reCAPTCHA...');
            recaptchaToken = await this.executeRecaptcha();
            console.log('reCAPTCHA token received');
        } catch (error) {
            console.error('reCAPTCHA error:', error);
            this.showMessage('Kunde inte verifiera säkerhetskontrollen. Ladda om sidan och försök igen.', 'error');
            return;
        }
        
        // Prepare form data
        const formData = new FormData(this.form);
        const data = Object.fromEntries(formData);
        data.recaptcha_token = recaptchaToken; // Add recaptcha token
        
        console.log('Sending data:', { 
            name: data.name, 
            email: data.email, 
            subject: data.subject,
            messageLength: data.message?.length,
            hasCsrf: !!data.csrf_token,
            hasRecaptcha: !!data.recaptcha_token 
        });
        
        // Show loading state
        this.setLoading(true);
        
        try {
            // Send request
            const response = await fetch('send-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            console.log('Response status:', response.status);
            
            let result;
            try {
                result = await response.json();
                console.log('Response data:', result);
            } catch (jsonError) {
                console.error('JSON parse error:', jsonError);
                throw new Error('Invalid response from server');
            }
            
            if (response.ok && result.success) {
                // Success
                this.showMessage('Meddelande skickat! Du får snart ett bekräftelsemail.', 'success');
                this.form.reset();
                this.generateCSRFToken(); // New token after successful submission
                this.resetCharCounter();
                
                // Reset form success states
                setTimeout(() => {
                    const formGroups = this.form.querySelectorAll('.form-group');
                    formGroups.forEach(group => {
                        group.classList.remove('success');
                    });
                }, 3000);
            } else {
                // Handle specific error codes
                let errorMessage = result.message || 'Ett fel uppstod vid sändning';
                
                switch (result.code) {
                    case 'RATE_LIMIT_EXCEEDED':
                        const minutes = Math.ceil((result.retry_after || 3600) / 60);
                        errorMessage = `För många förfrågningar. Försök igen om ${minutes} minut${minutes === 1 ? '' : 'er'}.`;
                        break;
                    case 'INVALID_RECAPTCHA':
                        errorMessage = 'Säkerhetsverifiering misslyckades. Ladda om sidan och försök igen.';
                        break;
                    case 'INVALID_EMAIL':
                        errorMessage = 'Ogiltig e-postadress.';
                        break;
                    case 'MESSAGE_TOO_LONG':
                        errorMessage = 'Meddelandet är för långt. Max 5000 tecken.';
                        break;
                    case 'MISSING_FIELDS':
                        errorMessage = 'Alla obligatoriska fält måste fyllas i.';
                        break;
                    case 'INVALID_CSRF_TOKEN':
                        errorMessage = 'Säkerhetstoken har utgått. Ladda om sidan och försök igen.';
                        break;
                }
                
                this.showMessage(errorMessage, 'error');
            }
        } catch (error) {
            console.error('Submission error:', error);
            this.showMessage('Kunde inte skicka meddelandet. Kontrollera din anslutning och försök igen.', 'error');
        } finally {
            this.setLoading(false);
        }
    }
    
    async executeRecaptcha() {
        return new Promise((resolve, reject) => {
            if (typeof grecaptcha === 'undefined') {
                reject(new Error('reCAPTCHA not loaded'));
                return;
            }
            
            grecaptcha.ready(async () => {
                try {
                    console.log('Getting reCAPTCHA token...');
                    const token = await grecaptcha.execute(this.recaptchaSiteKey, {
                        action: 'contact_submit'
                    });
                    
                    console.log('reCAPTCHA token received');
                    resolve(token);
                } catch (error) {
                    console.error('reCAPTCHA execution error:', error);
                    reject(error);
                }
            });
        });
    }
    
    setLoading(isLoading) {
        if (isLoading) {
            this.submitBtn.disabled = true;
            this.submitText.style.display = 'none';
            this.submitLoading.style.display = 'inline-block';
            this.submitBtn.classList.add('btn-loading');
        } else {
            this.submitBtn.disabled = false;
            this.submitText.style.display = 'inline';
            this.submitLoading.style.display = 'none';
            this.submitBtn.classList.remove('btn-loading');
        }
    }
    
    showMessage(text, type) {
        console.log('Showing message:', { text, type });
        
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.message');
        existingMessages.forEach(msg => {
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-10px)';
            setTimeout(() => msg.remove(), 300);
        });
        
        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        
        // Icon based on type
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        
        messageDiv.innerHTML = `
            <span class="message-icon">${icons[type] || icons.info}</span>
            <span>${text}</span>
        `;
        
        // Add to form messages container
        const messagesContainer = document.getElementById('form-messages');
        if (messagesContainer) {
            // Add message with animation
            messageDiv.style.opacity = '0';
            messageDiv.style.transform = 'translateY(-10px)';
            messagesContainer.appendChild(messageDiv);
            
            // Trigger animation
            setTimeout(() => {
                messageDiv.style.opacity = '1';
                messageDiv.style.transform = 'translateY(0)';
                messageDiv.style.transition = 'all 0.3s ease';
            }, 10);
            
            // Auto-remove success messages after 10 seconds
            if (type === 'success') {
                setTimeout(() => {
                    messageDiv.style.opacity = '0';
                    messageDiv.style.transform = 'translateY(-10px)';
                    setTimeout(() => messageDiv.remove(), 300);
                }, 10000);
            }
            
            // Scroll to message if it's an error
            if (type === 'error') {
                setTimeout(() => {
                    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
        } else {
            // Fallback if container doesn't exist
            const form = document.querySelector('.contact-form');
            if (form) {
                form.insertBefore(messageDiv, form.firstChild);
            }
        }
    }
    
    resetCharCounter() {
        if (this.charCount) {
            this.charCount.textContent = '0';
            this.charCount.classList.remove('warning', 'error');
        }
    }
    
    // Public method for debugging
    debugValidate() {
        const isValid = this.validateForm();
        console.log('Form validation result:', isValid);
        
        const requiredFields = this.form.querySelectorAll('input[required], textarea[required]');
        requiredFields.forEach(field => {
            console.log(`${field.id}:`, {
                value: field.value,
                valid: this.validateField(field)
            });
        });
        
        return isValid;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('Initializing EmailHandler...');
    
    // Check if reCAPTCHA is loaded
    if (typeof grecaptcha === 'undefined') {
        console.error('reCAPTCHA not loaded! Check if script is included.');
    } else {
        console.log('reCAPTCHA loaded successfully');
    }
    
    // Initialize email handler
    try {
        window.emailHandler = new EmailHandler();
        console.log('EmailHandler initialized successfully');
        
        // Debug button for testing (remove in production)
        const debugBtn = document.createElement('button');
        debugBtn.textContent = 'Debug Validate';
        debugBtn.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px;
            background: #333;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            z-index: 9999;
            font-size: 12px;
        `;
        debugBtn.addEventListener('click', () => {
            if (window.emailHandler) {
                window.emailHandler.debugValidate();
            }
        });
        document.body.appendChild(debugBtn);
        
    } catch (error) {
        console.error('Failed to initialize EmailHandler:', error);
        alert('Form initialization failed. Please refresh the page.');
    }
    
    // Form reset on page refresh prevention
    window.addEventListener('beforeunload', (e) => {
        const form = document.getElementById('contactForm');
        if (form) {
            const hasData = Array.from(form.elements).some(element => {
                return (element.type !== 'submit' && 
                        element.type !== 'button' && 
                        element.type !== 'hidden' && 
                        element.value.trim() !== '');
            });
            
            if (hasData) {
                e.preventDefault();
                e.returnValue = 'Du har osparade ändringar. Är du säker på att du vill lämna sidan?';
            }
        }
    });
});