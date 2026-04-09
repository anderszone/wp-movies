console.log('Contact validation script loaded');

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.contact-section form');
    const successMessage = document.querySelector('.contact-success');

    // ---- FORM VALIDATION ----
    if (form) {

        const nameField = form.querySelector('#name');
        const emailField = form.querySelector('#email');
        const messageField = form.querySelector('#message');
        const submitBtn = form.querySelector('button[type="submit"]');

        function removeError(field) {
            field.classList.remove('input-error');
            const error = field.parentElement.querySelector('.field-error');
            if (error) error.remove();
        }

        function showError(field, message) {
            removeError(field);
            field.classList.add('input-error');

            const error = document.createElement('div');
            error.className = 'field-error';
            error.textContent = message;

            // Accessibility
            error.setAttribute('role', 'alert');
            error.setAttribute('aria-live', 'polite');

            field.parentElement.appendChild(error);
        }

        function isValidEmail(email) {
            // Strängare regex för e-post, men fortfarande enkel
            return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
        }

        form.addEventListener('submit', function (e) {

            let hasError = false;
            let firstErrorField = null;

            [nameField, emailField, messageField].forEach(removeError);

            if (!nameField?.value.trim()) {
                showError(nameField, 'Please enter your name.');
                hasError = true;
                firstErrorField = firstErrorField || nameField;
            }

            if (!emailField?.value.trim()) {
                showError(emailField, 'Please enter your email.');
                hasError = true;
                firstErrorField = firstErrorField || emailField;
            } else if (!isValidEmail(emailField.value.trim())) {
                showError(emailField, 'Please enter a valid email.');
                hasError = true;
                firstErrorField = firstErrorField || emailField;
            }

            if (!messageField?.value.trim()) {
                showError(messageField, 'Please enter a message.');
                hasError = true;
                firstErrorField = firstErrorField || messageField;
            }

            if (hasError) {
                e.preventDefault();
                firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstErrorField.focus();
                return false;
            }

            submitBtn.disabled = true;
            submitBtn.classList.add('is-loading');
            submitBtn.textContent = 'Sending...';
        });
    }

    // ---- AUTO-HIDE SUCCESS ----
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            setTimeout(() => successMessage.remove(), 400);
        }, 4000);
    }
});
