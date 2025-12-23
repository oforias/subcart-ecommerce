$(document).ready(function() {
    // Regular expressions for validation
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    const passwordRegex = /^.+$/; // Non-empty validation only for login

    $('#login-form').submit(function(e) {
        e.preventDefault();

        // Get form values
        const email = $('#email').val().trim();
        const password = $('#password').val();

        // Check for empty required fields
        if (email === '') {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Email is required!',
            });
            return;
        }

        if (password === '') {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Password is required!',
            });
            return;
        }

        // Validate email format
        if (!emailRegex.test(email)) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please enter a valid email address!',
            });
            return;
        }

        // Validate password is non-empty
        if (!passwordRegex.test(password)) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Password cannot be empty!',
            });
            return;
        }

        // Show loading indicator
        $('#login-text').hide();
        $('#loading-spinner').show();
        $('#login-btn').prop('disabled', true);

        // Submit form via AJAX
        $.ajax({
            url: '../actions/login_customer_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                email: email,
                password: password,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                // Hide loading indicator
                $('#login-text').show();
                $('#loading-spinner').hide();
                $('#login-btn').prop('disabled', false);

                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Redirect to landing page
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            window.location.href = '../index.php';
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Failed',
                        text: response.message,
                    });
                }
            },
            error: function(xhr, status, error) {
                // Hide loading indicator
                $('#login-text').show();
                $('#loading-spinner').hide();
                $('#login-btn').prop('disabled', false);

                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);

                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'An error occurred while connecting to the server. Please try again later.',
                });
            }
        });
    });
});