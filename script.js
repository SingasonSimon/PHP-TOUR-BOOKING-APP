// tour_app/script.js

document.addEventListener('DOMContentLoaded', function() {
    // Find all password toggle buttons
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');

    togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Get the ID of the input field this button controls
            const targetInputId = this.dataset.target; // e.g., 'password', 'confirm_password'
            const passwordInput = document.getElementById(targetInputId);

            if (passwordInput) {
                // Check the current type of the input
                const currentType = passwordInput.getAttribute('type');

                // Toggle between 'password' and 'text'
                if (currentType === 'password') {
                    passwordInput.setAttribute('type', 'text');
                    this.textContent = 'Hide'; // Change button text
                    // If using an icon font, you'd toggle classes here instead
                } else {
                    passwordInput.setAttribute('type', 'password');
                    this.textContent = 'Show'; // Change button text back
                }
            }
        });
    });

    // You can add other site-wide JavaScript here later if needed
});