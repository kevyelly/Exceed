<?php
// login.php
session_start(); // Start session to access session variables

// --- Redirect if already logged in ---
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true) {
        header("location: admin_dashboard.php"); // Redirect admin
        exit;
    } elseif (isset($_SESSION["is_trainer"]) && $_SESSION["is_trainer"] === true) {
        header("location: trainer_dashboard.php"); // Redirect trainer (ensure this file exists)
        exit;
    } elseif (isset($_SESSION["is_trainee"]) && $_SESSION["is_trainee"] === true) {
        header("location: trainee_dashboard.php"); // Redirect trainee
        exit;
    } else {
        // If logged in but role is unclear, maybe redirect to a default page or logout
        // For now, let's just redirect to index to be safe
        header("location: index.html"); 
        exit;
    }
}

// --- Check for login error message from session ---
$login_error_message = "";
if (isset($_SESSION['login_error'])) {
    $login_error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Clear the error message after displaying it
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Login</title>
    <link rel="stylesheet" href="styles.css"> 
    <style>
        /* Styles that would typically be in auth.css */
        :root { 
            --primary-color: #01c892;
            --primary-hover-color: #00a97a; /* Darker shade for hover */
            --text-color: #213547;
            --text-light-color: #556a7e; /* Lighter text */
            --bg-color: #f8faf9;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
            --hover-color: #535bf2;
            --danger-color: #ef4444; 
            --danger-bg-color: #fee2e2;
            --danger-border-color: #fca5a5;
        }
        html, body {
            height: 100%; /* Ensure html and body take full height */
        }
        body { 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: var(--bg-color); 
            font-family: Inter, system-ui, -apple-system, sans-serif; 
            color: var(--text-color); 
            margin: 0; 
            padding: 1rem; /* Add padding for small screens */
        }
        .auth-container {
            display: flex;
            width: 100%;
            max-width: 1000px; 
            min-height: 600px; 
            background-color: var(--card-bg);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); /* Softer shadow */
            border-radius: 16px; /* More rounded corners */
            overflow: hidden;
        }
        .auth-card {
            flex: 1;
            padding: 3rem 3.5rem; /* More padding */
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .auth-header {
            text-align: center;
            margin-bottom: 2.5rem; /* More space */
        }
        .auth-header h1 {
            font-size: 2.5rem; /* Larger title */
            color: var(--primary-color);
            margin-bottom: 0.75rem;
            font-weight: 700;
        }
        .auth-header h2 {
            font-size: 1.3rem; /* Slightly smaller subtitle */
            color: var(--text-color);
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        .auth-header p {
            font-size: 0.95rem;
            color: var(--text-light-color);
        }
        .auth-form .form-group {
            margin-bottom: 1.5rem; /* More space between fields */
        }
        .auth-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .auth-form input[type="email"],
        .auth-form input[type="password"],
        .auth-form input[type="text"] { 
            width: 100%;
            padding: 0.85rem 1rem; /* Slightly larger padding */
            border: 1px solid var(--border-color);
            border-radius: 8px; /* More rounded inputs */
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .auth-form input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(1, 200, 146, 0.2); /* Softer focus ring */
        }
        .password-input {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-input input { 
            padding-right: 3.5rem; /* More space for button */
        }
        .toggle-password {
            position: absolute;
            right: 0.25rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            color: #9ca3af; /* Lighter icon color */
            display: flex; 
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }
        .toggle-password:hover {
            color: var(--primary-color);
        }
        .toggle-password svg { /* Ensure SVG size is consistent */
            width: 20px;
            height: 20px;
        }
        .toggle-password .icon-hide.hidden,
        .toggle-password .icon-show.hidden {
            display: none;
        }
        .error-message { 
            display: block;
            font-size: 0.8rem;
            color: var(--danger-color);
            margin-top: 0.25rem;
            min-height: 1em; 
        }
        .login-error-box { 
            background-color: var(--danger-bg-color); 
            color: var(--danger-color); 
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid var(--danger-border-color); 
            text-align: center;
        }
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
            font-size: 0.875rem;
        }
        .remember-me {
            display: flex;
            align-items: center;
        }
        .remember-me input[type="checkbox"] {
            margin-right: 0.5rem;
            accent-color: var(--primary-color);
            width: 1em; /* Explicit size */
            height: 1em;
        }
        .remember-me label {
            font-weight: normal; 
            margin-bottom: 0; 
            color: var(--text-light-color);
        }
        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-password:hover {
            text-decoration: underline;
        }
        .btn-auth { 
            width: 100%;
            padding: 0.85em 1.2em; 
            font-size: 1.05rem; /* Slightly larger text */
            font-weight: 600; /* Bolder */
            display: inline-flex; /* Needed for icon alignment */
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-auth:hover {
            background-color: var(--primary-hover-color);
        }
        .auth-image {
            flex: 1;
            position: relative;
            display: none; 
        }
        .auth-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .auth-image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); /* Darker gradient */
            color: white;
            padding: 2.5rem; /* More padding */
        }
        .auth-image-overlay h3 {
            font-size: 1.75rem; /* Larger heading */
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        .auth-image-overlay p {
            font-size: 1rem;
            line-height: 1.6;
        }
        @media (min-width: 768px) { 
            .auth-image {
                display: block;
            }
        }
        @media (max-width: 767px) { 
            .auth-container {
                flex-direction: column;
                min-height: auto;
                margin: 1rem; /* Adjust margin */
                max-width: 500px; /* Limit width on mobile */
            }
            .auth-card {
                padding: 2rem 1.5rem;
            }
             .auth-header h1 { font-size: 2rem; }
             .auth-header h2 { font-size: 1.2rem; }
        }

    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>EXCEED</h1>
                <h2>Log in to your account</h2>
                <p>Welcome back! Please enter your credentials.</p>
            </div>
            
            <?php if (!empty($login_error_message)): ?>
                <div class="login-error-box">
                    <?php echo htmlspecialchars($login_error_message); ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" class="auth-form" action="process_login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="e.g., admin@exceed.com" required>
                    <span class="error-message" id="email-error"></span> 
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                            <svg class="icon-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-hide hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </button>
                    </div>
                    <span class="error-message" id="password-error"></span>
                </div>
                
                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn-primary btn-auth">
                     <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
                     Log In
                </button>
            </form>
        </div>
        
        <div class="auth-image">
            <img src="https://images.pexels.com/photos/3184291/pexels-photo-3184291.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2" 
                 alt="Team collaboration">
            <div class="auth-image-overlay">
                <h3>Optimize Training Management</h3>
                <p>Welcome to EXCEED, your comprehensive solution for corporate training management.</p>
            </div>
        </div>
    </div>

    <script>
        // Keep only the password toggle functionality on the client-side
        document.addEventListener('DOMContentLoaded', function() {
            const togglePasswordButtons = document.querySelectorAll('.toggle-password');
            togglePasswordButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const passwordField = this.closest('.password-input').querySelector('input');
                    const iconShow = this.querySelector('.icon-show');
                    const iconHide = this.querySelector('.icon-hide');

                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        if(iconShow) iconShow.classList.add('hidden');
                        if(iconHide) iconHide.classList.remove('hidden');
                    } else {
                        passwordField.type = 'password';
                        if(iconShow) iconShow.classList.remove('hidden');
                        if(iconHide) iconHide.classList.add('hidden');
                    }
                });
            });
        });
    </script>
</body>
</html>

