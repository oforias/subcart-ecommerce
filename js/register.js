$(document).ready(function() {
    // Regular expressions for validation
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
    const phoneRegex = /^\+?[1-9]\d{9,14}$/;
    const nameRegex = /^[a-zA-Z\s]{1,100}$/;
    const countryRegex = /^[a-zA-Z\s]{1,30}$/;
    const cityRegex = /^[a-zA-Z\s]{1,30}$/;

    // Country code mapping
    const countryCodes = {
        'ghana': '+233',
        'nigeria': '+234',
        'kenya': '+254',
        'south africa': '+27',
        'egypt': '+20',
        'morocco': '+212',
        'ethiopia': '+251',
        'uganda': '+256',
        'tanzania': '+255',
        'zimbabwe': '+263',
        'zambia': '+260',
        'botswana': '+267',
        'namibia': '+264',
        'malawi': '+265',
        'rwanda': '+250',
        'senegal': '+221',
        'ivory coast': '+225',
        'cameroon': '+237',
        'mali': '+223',
        'burkina faso': '+226',
        'niger': '+227',
        'chad': '+235',
        'sudan': '+249',
        'libya': '+218',
        'tunisia': '+216',
        'algeria': '+213',
        'madagascar': '+261',
        'mauritius': '+230',
        'seychelles': '+248',
        'united states': '+1',
        'canada': '+1',
        'united kingdom': '+44',
        'france': '+33',
        'germany': '+49',
        'italy': '+39',
        'spain': '+34',
        'netherlands': '+31',
        'belgium': '+32',
        'switzerland': '+41',
        'austria': '+43',
        'sweden': '+46',
        'norway': '+47',
        'denmark': '+45',
        'finland': '+358',
        'poland': '+48',
        'czech republic': '+420',
        'hungary': '+36',
        'romania': '+40',
        'bulgaria': '+359',
        'greece': '+30',
        'portugal': '+351',
        'ireland': '+353',
        'luxembourg': '+352',
        'iceland': '+354',
        'malta': '+356',
        'cyprus': '+357',
        'estonia': '+372',
        'latvia': '+371',
        'lithuania': '+370',
        'slovakia': '+421',
        'slovenia': '+386',
        'croatia': '+385',
        'serbia': '+381',
        'montenegro': '+382',
        'bosnia and herzegovina': '+387',
        'north macedonia': '+389',
        'albania': '+355',
        'moldova': '+373',
        'ukraine': '+380',
        'belarus': '+375',
        'russia': '+7',
        'china': '+86',
        'japan': '+81',
        'south korea': '+82',
        'india': '+91',
        'pakistan': '+92',
        'bangladesh': '+880',
        'sri lanka': '+94',
        'nepal': '+977',
        'bhutan': '+975',
        'maldives': '+960',
        'afghanistan': '+93',
        'iran': '+98',
        'iraq': '+964',
        'turkey': '+90',
        'israel': '+972',
        'jordan': '+962',
        'lebanon': '+961',
        'syria': '+963',
        'saudi arabia': '+966',
        'uae': '+971',
        'qatar': '+974',
        'kuwait': '+965',
        'bahrain': '+973',
        'oman': '+968',
        'yemen': '+967',
        'thailand': '+66',
        'vietnam': '+84',
        'philippines': '+63',
        'malaysia': '+60',
        'singapore': '+65',
        'indonesia': '+62',
        'brunei': '+673',
        'myanmar': '+95',
        'cambodia': '+855',
        'laos': '+856',
        'australia': '+61',
        'new zealand': '+64',
        'fiji': '+679',
        'papua new guinea': '+675',
        'solomon islands': '+677',
        'vanuatu': '+678',
        'new caledonia': '+687',
        'french polynesia': '+689',
        'brazil': '+55',
        'argentina': '+54',
        'chile': '+56',
        'colombia': '+57',
        'peru': '+51',
        'venezuela': '+58',
        'ecuador': '+593',
        'bolivia': '+591',
        'paraguay': '+595',
        'uruguay': '+598',
        'guyana': '+592',
        'suriname': '+597',
        'french guiana': '+594',
        'mexico': '+52',
        'guatemala': '+502',
        'belize': '+501',
        'el salvador': '+503',
        'honduras': '+504',
        'nicaragua': '+505',
        'costa rica': '+506',
        'panama': '+507',
        'cuba': '+53',
        'jamaica': '+1876',
        'haiti': '+509',
        'dominican republic': '+1809',
        'puerto rico': '+1787',
        'trinidad and tobago': '+1868',
        'barbados': '+1246',
        'bahamas': '+1242'
    };

    // Auto-update phone number prefix when country changes
    $('#country').on('change', function() {
        const country = $(this).val().trim();
        const countryLower = country.toLowerCase();
        const phoneField = $('#phone_number');
        const phoneHelper = $('#phone-helper');
        const currentPhone = phoneField.val().trim();
        
        if (country && countryCodes[countryLower]) {
            const countryCode = countryCodes[countryLower];
            
            // Remove any existing country code first
            let cleanPhone = currentPhone.replace(/^\+\d{1,4}\s?/, '');
            
            // Add the new country code
            phoneField.val(countryCode + (cleanPhone ? ' ' + cleanPhone : ''));
            
            // Update helper text
            phoneHelper.html(`<i class="fa fa-check-circle"></i> Country code ${countryCode} added for ${country}`);
            phoneHelper.addClass('active');
            
            // Show a brief success notification
            Swal.fire({
                icon: 'success',
                title: 'Country Code Added',
                text: `${countryCode} prefix added for ${country}`,
                timer: 1500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else if (country) {
            // Country selected but no code found
            phoneHelper.html(`<i class="fa fa-exclamation-triangle"></i> Please enter full phone number with country code for ${country}`);
            phoneHelper.removeClass('active');
        } else {
            // No country selected
            phoneHelper.html(`<i class="fa fa-info-circle"></i> Country code will be added automatically when you select your country`);
            phoneHelper.removeClass('active');
        }
    });

    // Prevent manual editing of country code once set
    $('#phone_number').on('input', function() {
        const country = $('#country').val().trim().toLowerCase();
        const currentPhone = $(this).val().trim();
        
        if (country && countryCodes[country]) {
            const countryCode = countryCodes[country];
            
            // If user tries to remove the country code, add it back
            if (currentPhone && !currentPhone.startsWith(countryCode)) {
                // Check if they're trying to enter a number without the code
                const phoneWithoutPlus = currentPhone.replace(/^\+/, '');
                if (/^\d/.test(phoneWithoutPlus) && !phoneWithoutPlus.startsWith(countryCode.substring(1))) {
                    $(this).val(countryCode + ' ' + currentPhone);
                }
            }
        }
    });

    $('#register-form').submit(function(e) {
        e.preventDefault();

        // Get form values
        const name = $('#name').val().trim();
        const email = $('#email').val().trim();
        const password = $('#password').val();
        const country = $('#country').val().trim();
        const city = $('#city').val().trim();
        const phone_number = $('#phone_number').val().trim();
        const role = $('input[name="role"]:checked').val();

        // Check for empty required fields
        if (name === '' || email === '' || password === '' || country === '' || city === '' || phone_number === '') {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Please fill in all required fields!',
            });
            return;
        }

        // Validate field lengths
        if (name.length > 100) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Name must not exceed 100 characters!',
            });
            return;
        }

        if (email.length > 50) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Email must not exceed 50 characters!',
            });
            return;
        }

        if (country.length > 30) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Country must not exceed 30 characters!',
            });
            return;
        }

        if (city.length > 30) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'City must not exceed 30 characters!',
            });
            return;
        }

        if (phone_number.length > 15) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Contact number must not exceed 15 characters!',
            });
            return;
        }

        // Validate name format (letters and spaces only)
        if (!nameRegex.test(name)) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Name must contain only letters and spaces!',
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

        // Validate password strength
        if (!passwordRegex.test(password)) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one digit, and one special character (@$!%*?&)!',
            });
            return;
        }

        // Validate country selection
        if (country === '') {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please select your country!',
            });
            return;
        }

        // Validate city format (letters and spaces only)
        if (!cityRegex.test(city)) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'City must contain only letters and spaces!',
            });
            return;
        }

        // Validate phone number format
        if (!phoneRegex.test(phone_number)) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Please enter a valid phone number (10-15 digits, optionally starting with +)!',
            });
            return;
        }

        // Show loading indicator
        Swal.fire({
            title: 'Processing...',
            text: 'Please wait while we register your account',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Submit form via AJAX
        $.ajax({
            url: '../actions/register_customer_action.php',
            type: 'POST',
            data: {
                name: name,
                email: email,
                password: password,
                country: country,
                city: city,
                phone_number: phone_number,
                role: role,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                // Hide loading indicator
                Swal.close();

                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message,
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'login.php';
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: response.message,
                    });
                }
            },
            error: function() {
                // Hide loading indicator
                Swal.close();

                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'An error occurred! Please try again later.',
                });
            }
        });
    });
});