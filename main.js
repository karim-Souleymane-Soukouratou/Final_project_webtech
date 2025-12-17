document.addEventListener('DOMContentLoaded', function() {
    
    //  ADMIN PAYMENT RUN PROTECTION 
    // Prevents double submission on the high-stakes payment commitment page.
    const paymentRunForm = document.getElementById('payment-run-form'); 
    
    if (paymentRunForm) {
        paymentRunForm.addEventListener('submit', function() {
            const button = paymentRunForm.querySelector('button[type="submit"]');
            
            // Disable the button immediately
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing... Please Wait';
        });
    }

    // PASSWORD STRENGTH CHECK
    // Provides immediate feedback to the student (UX and Security)
    const passwordField = document.getElementById('password');
    const signupContainer = document.querySelector('.signup-container');
    
    if (passwordField && signupContainer) {
        // Create a visual indicator element
        const strengthDiv = document.createElement('div');
        strengthDiv.id = 'password-strength-indicator';
        strengthDiv.style.marginTop = '10px';
        strengthDiv.style.fontWeight = 'bold';
        
        // Find the form group for the password field and insert the indicator below it
        const passwordGroup = passwordField.closest('.form-group');
        if (passwordGroup) {
            passwordGroup.appendChild(strengthDiv);
        }

        passwordField.addEventListener('input', function() {
            const password = this.value;
            let score = 0;
            let status = 'Weak';
            let color = 'red';

            if (password.length >= 8) { score += 1; }
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) { score += 1; }
            if (password.match(/\d/)) { score += 1; }
            if (password.match(/[^a-zA-Z\d\s]/)) { score += 1; }

            if (score === 4) { status = 'Very Strong'; color = 'green'; }
            else if (score === 3) { status = 'Strong'; color = 'orange'; }
            else if (score >= 1) { status = 'Moderate'; color = '#FFC300'; }

            strengthDiv.textContent = `Strength: ${status}`;
            strengthDiv.style.color = color;
        });
    }

    //  MOBILE NAVIGATION TOGGLE
    const sidebar = document.querySelector('.sidebar');
    const menuButton = document.querySelector('.menu-toggle-button'); 
    
    if (sidebar) {
        if (menuButton) {
            menuButton.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
            }
        });
    }
});