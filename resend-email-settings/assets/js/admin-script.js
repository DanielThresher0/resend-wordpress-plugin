/* 
1. Document Ready Handler
   Initializes the settings interface and event handlers
*/
jQuery(document).ready(function($) {
    // Fade in settings container on load
    $('.resend-settings-wrap').hide().fadeIn(800);

    /* 
    2. Show Toast Notification
       Displays a temporary notification message with icon and progress bar
       @param {string} message - Text to display
       @param {string} [type=success] - Notification type (success/error)
    */
    function showToast(message, type = 'success') {
        const container = document.getElementById('resend-toast-container');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `resend-toast resend-toast-${type}`;
        
        const header = document.createElement('div');
        header.className = 'toast-header';
        
        const svgNS = 'http://www.w3.org/2000/svg';
        const icon = document.createElementNS(svgNS, 'svg');
        icon.setAttribute('viewBox', '0 0 24 24');
        icon.setAttribute('fill', 'none');
        icon.setAttribute('stroke-width', '1.5');
        icon.setAttribute('stroke', type === 'success' ? '#22c55e' : '#ef4444');
        icon.innerHTML = type === 'success' ? 
            '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />' :
            '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />';
        
        header.appendChild(icon);
        
        const span = document.createElement('span');
        span.textContent = message;
        header.appendChild(span);
        toast.appendChild(header);
        
        const progressBar = document.createElement('div');
        progressBar.className = 'progress-bar';
        const progressFill = document.createElement('div');
        progressFill.className = 'progress-fill';
        progressBar.appendChild(progressFill);
        toast.appendChild(progressBar);
        
        container.appendChild(toast);
        
        void toast.offsetWidth; 
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                container.contains(toast) && container.removeChild(toast);
            }, 300);
        }, 4000);
    }
    /* 
    3. Save Settings Button Click Handler
       Handles settings form submission via AJAX
    */
    $('#save-settings-button').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const data = {
            action: 'resend_save_settings',
            resend_api_key: $('#resend_api_key').val(),
            resend_from_email: $('#resend_from_email').val(),
            resend_sender_name: $('#resend_sender_name').val(),
            security: $('#resend_save_settings_nonce').val()
        };
        
        $btn.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: (response) => {
                $btn.prop('disabled', false);
                showToast(response.data.message, response.success ? 'success' : 'error');
            },
            error: (xhr, status, error) => {
                $btn.prop('disabled', false);
                showToast(`AJAX error: ${error}`, 'error');
            }
        });
    });
    /* 
    4. Send Test Email Button Click Handler
       Handles test email submission via AJAX
    */
    $('#send-test-email-button').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const testEmail = $('#test_email').val();
        const nonce = $('#resend_test_email_nonce').val();
        
        $btn.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'resend_send_test_email',
                test_email: testEmail,
                security: nonce
            },
            success: (response) => {
                $btn.prop('disabled', false);
                showToast(response.data.message, response.success ? 'success' : 'error');
            },
            error: (xhr, status, error) => {
                $btn.prop('disabled', false);
                showToast(`AJAX error: ${error}`, 'error');
            }
        });
    });
});