<?php
/**
 * Password Confirmation Modal Component
 * Include this file and call showPasswordConfirmModal() to display the modal
 * Then use the onConfirm callback to handle the confirmed action
 */

/**
 * Displays the password confirmation modal
 * @param string $action Description of the action requiring confirmation
 * @param string $formId Optional form ID to submit after confirmation
 */
function showPasswordConfirmModal($action = 'this action', $formId = null) {
    $modalId = 'passwordConfirmModal' . uniqid();
    ?>
    <!-- Password Confirmation Modal -->
    <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="passwordConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="passwordConfirmModalLabel">
                        <i class="fas fa-shield-alt me-2"></i>Confirm Your Identity
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        For security reasons, please confirm your password to <?php echo htmlspecialchars($action); ?>.
                    </div>
                    
                    <form id="passwordConfirmForm" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="verify_password">
                        
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">
                                <i class="fas fa-lock me-2"></i>Enter Your Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmPassword" 
                                       name="password" required autocomplete="current-password">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="invalid-feedback">
                                    Please enter your password.
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary" id="confirmActionBtn">
                                <i class="fas fa-check-circle me-2"></i>Confirm
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = new bootstrap.Modal(document.getElementById('<?php echo $modalId; ?>'));
        const form = document.getElementById('passwordConfirmForm');
        const passwordInput = document.getElementById('confirmPassword');
        const confirmBtn = document.getElementById('confirmActionBtn');
        
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const icon = this.querySelector('i');
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        });
        
        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!form.checkValidity()) {
                e.stopPropagation();
                form.classList.add('was-validated');
                return;
            }
            
            const password = passwordInput.value;
            
            // Disable button and show loading state
            const originalBtnText = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verifying...';
            
            // Verify password via AJAX
            fetch('/auth/verify_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'password=' + encodeURIComponent(password)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Password verified, submit the original form if provided
                    modal.hide();
                    <?php if ($formId): ?>
                        document.getElementById('<?php echo $formId; ?>').submit();
                    <?php else: ?>
                        // If no form ID provided, trigger a custom event
                        document.dispatchEvent(new CustomEvent('passwordVerified', {
                            detail: { password: password }
                        }));
                    <?php endif; ?>
                } else {
                    // Show error
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback d-block mt-2';
                    feedback.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + (data.message || 'Incorrect password');
                    
                    // Remove any existing feedback
                    const existingFeedback = form.querySelector('.invalid-feedback.d-block');
                    if (existingFeedback) {
                        existingFeedback.remove();
                    }
                    
                    form.appendChild(feedback);
                    passwordInput.focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            })
            .finally(() => {
                // Reset button state
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalBtnText;
            });
        });
        
        // Show modal when triggered
        modal.show();
    });
    </script>
    <?php
}
?>
