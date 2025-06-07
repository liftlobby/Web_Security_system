/**
 * Session Management JavaScript
 * Monitors session timeout and provides user warnings
 */

class SessionManager {
    constructor() {
        this.checkInterval = 300000; // Check every 5 minutes
        this.warningShown = false;
        this.init();
    }

    init() {
        // Start monitoring when page loads
        this.startMonitoring();
        
        // Reset warning flag on user activity
        this.bindActivityEvents();
    }

    startMonitoring() {
        setInterval(() => {
            this.checkSession();
        }, this.checkInterval);
        
        // Initial check
        this.checkSession();
    }

    bindActivityEvents() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.warningShown = false;
            }, true);
        });
    }

    async checkSession() {
        try {
            const response = await fetch('/Web_Security_system/includes/check_session.php', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            if (data.expired) {
                this.handleSessionExpired(data.redirect_url);
            } else if (data.warning && !this.warningShown) {
                this.showWarning(data.message, data.remaining);
                this.warningShown = true;
            }
            
        } catch (error) {
            console.warn('Session check failed:', error);
        }
    }

    handleSessionExpired(redirectUrl) {
        alert('Your session has expired. You will be redirected to the login page.');
        window.location.href = redirectUrl;
    }

    showWarning(message, remaining) {
        // Create a styled warning message
        const warningDiv = document.createElement('div');
        warningDiv.id = 'session-warning';
        warningDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 10000;
            max-width: 400px;
            font-family: Arial, sans-serif;
        `;
        
        warningDiv.innerHTML = `
            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                <span style="font-size: 18px; margin-right: 8px;">⚠️</span>
                <strong>Session Warning</strong>
            </div>
            <div style="margin-bottom: 15px;">${message}</div>
            <div style="text-align: right;">
                <button onclick="document.getElementById('session-warning').remove(); sessionManager.warningShown = false;" 
                        style="background: #856404; color: white; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer;">
                    OK
                </button>
            </div>
        `;
        
        // Remove existing warning if present
        const existingWarning = document.getElementById('session-warning');
        if (existingWarning) {
            existingWarning.remove();
        }
        
        document.body.appendChild(warningDiv);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (document.getElementById('session-warning')) {
                document.getElementById('session-warning').remove();
            }
        }, 10000);
    }

    // Method to manually refresh session (can be called on important user actions)
    refreshSession() {
        fetch('/Web_Security_system/includes/refresh_session.php', {
            method: 'POST',
            credentials: 'same-origin'
        }).catch(error => {
            console.warn('Session refresh failed:', error);
        });
    }
}

// Initialize session manager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.sessionManager = new SessionManager();
});

// Export for manual use
window.SessionManager = SessionManager;
