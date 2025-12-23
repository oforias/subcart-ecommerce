<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register - Taste of Africa</title>
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
                        <h4>Register</h4>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="form" id="register-form">
                        <?php
                        // Include core functions for CSRF protection
                        require_once '../settings/core.php';
                        echo csrf_token_field();
                        ?>
                        <div class="form-group">
                            <label for="name" class="form-label">Name <i class="fa fa-user"></i></label>
                            <input type="text" class="form-input" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email <i class="fa fa-envelope"></i></label>
                            <input type="email" class="form-input" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password" class="form-label">Password <i class="fa fa-lock"></i></label>
                            <input type="password" class="form-input" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="country" class="form-label">Country <i class="fa fa-globe"></i></label>
                            <select class="form-input" id="country" name="country" required>
                                <option value="">Select your country</option>
                                <optgroup label="Africa">
                                    <option value="Ghana">Ghana</option>
                                    <option value="Nigeria">Nigeria</option>
                                    <option value="Kenya">Kenya</option>
                                    <option value="South Africa">South Africa</option>
                                    <option value="Egypt">Egypt</option>
                                    <option value="Morocco">Morocco</option>
                                    <option value="Ethiopia">Ethiopia</option>
                                    <option value="Uganda">Uganda</option>
                                    <option value="Tanzania">Tanzania</option>
                                    <option value="Zimbabwe">Zimbabwe</option>
                                    <option value="Zambia">Zambia</option>
                                    <option value="Botswana">Botswana</option>
                                    <option value="Namibia">Namibia</option>
                                    <option value="Malawi">Malawi</option>
                                    <option value="Rwanda">Rwanda</option>
                                    <option value="Senegal">Senegal</option>
                                    <option value="Ivory Coast">Ivory Coast</option>
                                    <option value="Cameroon">Cameroon</option>
                                    <option value="Mali">Mali</option>
                                    <option value="Burkina Faso">Burkina Faso</option>
                                </optgroup>
                                <optgroup label="North America">
                                    <option value="United States">United States</option>
                                    <option value="Canada">Canada</option>
                                    <option value="Mexico">Mexico</option>
                                </optgroup>
                                <optgroup label="Europe">
                                    <option value="United Kingdom">United Kingdom</option>
                                    <option value="France">France</option>
                                    <option value="Germany">Germany</option>
                                    <option value="Italy">Italy</option>
                                    <option value="Spain">Spain</option>
                                    <option value="Netherlands">Netherlands</option>
                                    <option value="Belgium">Belgium</option>
                                    <option value="Switzerland">Switzerland</option>
                                    <option value="Austria">Austria</option>
                                    <option value="Sweden">Sweden</option>
                                    <option value="Norway">Norway</option>
                                    <option value="Denmark">Denmark</option>
                                    <option value="Finland">Finland</option>
                                    <option value="Poland">Poland</option>
                                </optgroup>
                                <optgroup label="Asia">
                                    <option value="China">China</option>
                                    <option value="Japan">Japan</option>
                                    <option value="South Korea">South Korea</option>
                                    <option value="India">India</option>
                                    <option value="Pakistan">Pakistan</option>
                                    <option value="Bangladesh">Bangladesh</option>
                                    <option value="Thailand">Thailand</option>
                                    <option value="Vietnam">Vietnam</option>
                                    <option value="Philippines">Philippines</option>
                                    <option value="Malaysia">Malaysia</option>
                                    <option value="Singapore">Singapore</option>
                                    <option value="Indonesia">Indonesia</option>
                                </optgroup>
                                <optgroup label="Oceania">
                                    <option value="Australia">Australia</option>
                                    <option value="New Zealand">New Zealand</option>
                                </optgroup>
                                <optgroup label="South America">
                                    <option value="Brazil">Brazil</option>
                                    <option value="Argentina">Argentina</option>
                                    <option value="Chile">Chile</option>
                                    <option value="Colombia">Colombia</option>
                                    <option value="Peru">Peru</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="city" class="form-label">City <i class="fa fa-map-marker"></i></label>
                            <input type="text" class="form-input" id="city" name="city" required>
                        </div>
                        <div class="form-group">
                            <label for="phone_number" class="form-label">Phone Number <i class="fa fa-phone"></i></label>
                            <div class="phone-field-container">
                                <input type="text" class="form-input" id="phone_number" name="phone_number" required placeholder="Enter your phone number">
                                <small class="phone-helper-text" id="phone-helper">
                                    <i class="fa fa-info-circle"></i> Country code will be added automatically when you select your country
                                </small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Register As</label>
                            <div class="radio-group horizontal">
                                <div class="radio-item">
                                    <input type="radio" name="role" id="customer" value="1" checked>
                                    <span class="radio-custom"></span>
                                    <span class="radio-label">Customer</span>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="role" id="admin" value="0">
                                    <span class="radio-custom"></span>
                                    <span class="radio-label">Admin</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-block">Register</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    Already have an account? <a href="login.php">Login here</a>.
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../js/register.js"></script>
</body>

</html>