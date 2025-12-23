<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login - Taste of Africa</title>
    <link href="../css/sweetgreen-style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <div class="form-layout">
        <div class="form-container">
            <div class="card card-form card-structured">
                <div class="card-header">
                    <div class="header-with-back">
                        <a href="../index.php" class="back-button">
                            <i class="fa fa-arrow-left"></i> Back to Home
                        </a>
                        <h4>Login</h4>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form" id="login-form">
                        <?php
                        // Include core functions for CSRF protection
                        require_once '../settings/core.php';
                        echo csrf_token_field();
                        ?>
                        <div class="form-group">
                            <label for="email" class="form-label">Email <i class="fa fa-envelope"></i></label>
                            <input type="email" class="form-input" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password" class="form-label">Password <i class="fa fa-lock"></i></label>
                            <input type="password" class="form-input" id="password" name="password" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-block" id="login-btn">
                                <span id="login-text">Login</span>
                                <span id="loading-spinner" style="display: none;">
                                    <i class="fa fa-spinner fa-spin"></i> Logging in...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    Don't have an account? <a href="register.php">Register here</a>.
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../js/login.js"></script>
</body>

</html>