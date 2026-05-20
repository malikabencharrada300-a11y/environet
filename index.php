<?php
require_once 'config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EnviroNet · Connexion</title>
  <!-- Poppins font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      background: linear-gradient(145deg, #e9f0f5 0%, #dee8f0 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Poppins', sans-serif;
      padding: 1rem;
    }
    .card {
      max-width: 500px;
      width: 100%;
      background-color: #ffffff;
      border-radius: 40px;
      box-shadow: 0 30px 60px -15px rgba(12, 40, 60, 0.3);
      padding: 2.4rem 2.2rem 2.2rem;
      transition: all 0.2s ease;
    }
    .app-logo {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 16px;
      margin-bottom: 1.5rem;
      background: #f0f7fe;
      padding: 0.8rem 1.8rem;
      border-radius: 80px;
      width: fit-content;
      border: 1px solid #cde0f0;
    }
    .logo-image {
      width: 70px;
      height: 70px;
      background: #ffffff;
      border-radius: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 16px -4px rgba(20,60,100,0.3);
      overflow: hidden;
      border: 3px solid white;
    }
    .logo-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .logo-text {
      font-weight: 700;
      font-size: 1.9rem;
      letter-spacing: -0.5px;
      color: #113853;
    }
    .greeting-line {
      display: flex;
      align-items: baseline;
      gap: 0.5rem;
      margin-bottom: 1.8rem;
      flex-wrap: wrap;
    }
    .greeting { font-size: 1.3rem; font-weight: 400; color: #2f5577; }
    .morning {
      font-size: 1.9rem; font-weight: 600; color: #0a2b40;
      letter-spacing: -0.02em; border-left: 5px solid #3b82f6; padding-left: 1rem;
    }
    .dual-header {
      display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.8rem;
    }
    .login-title {
      font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 500;
      color: #3d658b; background: #ecf3fa; padding: 0.3rem 1rem; border-radius: 40px;
    }
    .signin-sub {
      font-size: 1rem; font-weight: 400; color: #2b5b7c; margin-bottom: 1.8rem;
      padding-bottom: 0.5rem; border-bottom: 2px dotted #b9d6ed;
    }
    .input-group { margin-bottom: 1.6rem; }
    .input-group label {
      display: block; font-size: 0.9rem; font-weight: 500; color: #1a3f5c;
      margin-bottom: 0.3rem; margin-left: 0.5rem;
    }
    .input-wrapper {
      display: flex; align-items: center; border: 1.8px solid #d3e2f0; border-radius: 30px;
      padding: 0.1rem 1.3rem; background: #ffffff; transition: border-color 0.15s, box-shadow 0.15s;
    }
    .input-wrapper i { color: #5f8bb3; font-size: 1rem; width: 26px; }
    .input-wrapper input {
      width: 100%; border: none; padding: 0.9rem 0.2rem; font-size: 1rem;
      font-family: 'Poppins', sans-serif; background: transparent; outline: none; color: #15364d;
    }
    .input-wrapper input::placeholder { color: #a1bedb; }
    .input-wrapper.input-error { border-color: #dc2626; background-color: #fff8f8; }
    .error-message {
      color: #b91c1c; font-size: 0.8rem; margin-top: 0.3rem; margin-left: 1rem;
      display: flex; align-items: center; gap: 0.3rem;
    }
    .robot-check {
      margin: 1.2rem 0 1.5rem 0; display: flex; align-items: center; gap: 0.8rem;
      background: #f0f7fe; padding: 0.8rem 1.2rem; border-radius: 50px; border: 1px solid #c5dff7;
    }
    .robot-check input[type="checkbox"] { width: 22px; height: 22px; accent-color: #3b82f6; cursor: pointer; }
    .robot-check label { font-size: 1rem; color: #1e4b74; font-weight: 500; cursor: pointer; flex: 1; }
    .robot-check i { color: #5495c0; font-size: 1.3rem; }
    .robot-check.check-error { border-color: #dc2626; background: #fff1f0; }
    .row-remember {
      display: flex; align-items: center; justify-content: space-between; margin: 1rem 0 2.2rem 0;
    }
    .checkbox-label {
      display: flex; align-items: center; gap: 0.6rem; color: #1e4a70; cursor: pointer;
      background: #f3f9ff; padding: 0.3rem 1rem; border-radius: 40px;
    }
    .checkbox-label input[type="checkbox"] { width: 18px; height: 18px; accent-color: #3b82f6; }
    .forgot-link {
      color: #1f5a9e; text-decoration: none; font-weight: 500; border-bottom: 2px solid #fcd34d;
    }
    .forgot-link:hover { color: #0d3f6b; border-bottom-color: #fbbf24; }
    .submit-btn {
      background: #11324d; color: white; border: none; border-radius: 50px; padding: 1.1rem 2rem;
      width: 100%; font-size: 1.25rem; font-weight: 600; letter-spacing: 1.2px; text-transform: uppercase;
      cursor: pointer; box-shadow: 0 10px 20px -8px rgba(7,42,68,0.5); transition: background 0.2s;
      margin-bottom: 1.8rem;
    }
    .submit-btn:hover { background: #1e4a70; }
    .submit-btn:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }
    .create-account {
      text-align: center; font-size: 1.2rem; font-weight: 500; color: #174261; margin-bottom: 0.8rem;
    }
    .create-account a {
      color: #124e7a; text-decoration: none; font-weight: 700; background: #fdf8e7;
      padding: 0.3rem 1.5rem; border-radius: 40px; border: 1px solid #facf7b; transition: 0.1s;
    }
    .create-account a:hover { background: #fcf1d5; }
    .site-footer {
      text-align: center; margin-top: 1.8rem; font-size: 1rem; color: #597b9b;
      letter-spacing: 2.5px; border-top: 2px solid #e3eef9; padding-top: 1.4rem;
    }
    .hidden { display: none; }
    .back-link {
      display: inline-block;
      margin-top: 1rem;
      color: #1f5a9e;
      text-decoration: none;
      font-weight: 500;
      cursor: pointer;
    }
    .back-link:hover {
      text-decoration: underline;
    }
    .info-message {
      background: #e6f3ff;
      padding: 1rem;
      border-radius: 30px;
      margin-bottom: 1.5rem;
      text-align: center;
      color: #1e4a70;
    }
    .password-strength {
      font-size: 0.75rem;
      margin-top: 0.5rem;
      margin-left: 1rem;
    }
    .strength-weak { color: #dc2626; }
    .strength-medium { color: #f59e0b; }
    .strength-strong { color: #10b981; }
  </style>
</head>

<body>
  <!-- PAGE 1 : LOGIN -->
  <div class="card" id="loginPage">
      <div class="app-logo">
        <div class="logo-image">
          <img src="logo.png" alt="EnviroNet logo" onerror="this.src='https://via.placeholder.com/70x70?text=EN'">
        </div>
        <div class="logo-text">EnviroNet</div>
      </div>
      <div class="greeting-line">
        <span class="greeting">Hello!</span>
        <span class="morning">Welcome</span>
      </div>
      <div class="dual-header">
        <div class="login-title">Login Your Account</div>
      </div>
      <div class="signin-sub">Sign In to your account</div>
      <div class="input-group">
        <label>Email Address</label>
        <div class="input-wrapper" id="emailWrapper">
          <i class="far fa-envelope"></i>
          <input type="email" id="emailInput" name="email" placeholder="student@example.com">
        </div>
        <div id="emailError" class="error-message"></div>
      </div>
      <div class="input-group">
        <label>Password</label>
        <div class="input-wrapper" id="passwordWrapper">
          <i class="fas fa-lock"></i>
          <input type="password" id="passwordInput" name="password" placeholder="··········">
        </div>
        <div id="passwordError" class="error-message"></div>
      </div>
      <div class="robot-check" id="robotWrapper">
        <i class="fas fa-robot"></i>
        <input type="checkbox" id="robotCheckbox">
        <label for="robotCheckbox">Je ne suis pas un robot</label>
      </div>
      <div id="robotError" class="error-message"></div>
      <div class="row-remember">
        <label class="checkbox-label">
          <input type="checkbox" id="rememberCheckbox" checked> Remember
        </label>
        <a href="#" id="forgotPasswordLink" class="forgot-link">Forgot Password?</a>
      </div>
      <button class="submit-btn" id="submitBtn" >SUBMIT</button>
      <div class="create-account">
        <a href="#" id="createAccountLink">Create Account</a>
      </div>
      <div class="site-footer">WWW.EnviroNet.COM</div>
    
  </div>

  <!-- PAGE 2 : FORGOT PASSWORD (Email verification) -->
  <div class="card hidden" id="forgotPage1">
    
      <div class="app-logo">
        <div class="logo-image">
          <img src="logo.png" alt="EnviroNet logo" onerror="this.src='https://via.placeholder.com/70x70?text=EN'">
        </div>
        <div class="logo-text">EnviroNet</div>
      </div>
      <div class="greeting-line">
        <span class="greeting">Reset</span>
        <span class="morning">Password</span>
      </div>
      <div class="info-message">
        <i class="fas fa-info-circle"></i> Enter your email address and we'll send you a link to reset your password.
      </div>
      <div class="input-group">
        <label>Email Address</label>
        <div class="input-wrapper" id="forgotEmailWrapper">
          <i class="far fa-envelope"></i>
          <input type="email" id="forgotEmailInput" placeholder="your@email.com">
        </div>
        <div id="forgotEmailError" class="error-message"></div>
      </div>
      <button class="submit-btn" id="goToResetPage">Send Reset Code</button>
      <div style="text-align: center;">
        <a href="#" class="back-link" id="backToLoginFromForgot1">← Back to Login</a>
      </div>    
      <div class="site-footer">WWW.EnviroNet.COM</div>
    
  </div>

  <!-- PAGE 3 : RESET PASSWORD -->
  <div class="card hidden" id="resetPage">
    
      <div class="app-logo">
        <div class="logo-image">
          <img src="logo.png" alt="EnviroNet logo" onerror="this.src='https://via.placeholder.com/70x70?text=EN'">
        </div>
        <div class="logo-text">EnviroNet</div>
      </div>
      <div class="greeting-line">
        <span class="greeting">Create New</span>
        <span class="morning">Password</span>
      </div>
      <div class="info-message">
        <i class="fas fa-key"></i> Please enter your new password
      </div>
     <div class="input-group">
    <label>Verification Code</label>
    <div class="input-wrapper" id="verificationCodeWrapper">
        <i class="fas fa-shield-alt"></i>
        <input
            type="text"
            id="verificationCodeInput"
            placeholder="Enter 4-digit code"
            maxlength="4">
    </div>
    <div id="verificationCodeError" class="error-message"></div>
</div>
      <div class="input-group">
        <label>New Password</label>
        <div class="input-wrapper" id="newPasswordWrapper">
          <i class="fas fa-lock"></i>
          <input type="password" id="newPasswordInput" placeholder="··········">
        </div>
        <div id="newPasswordError" class="error-message"></div>
      </div>
      <div class="input-group">
        <label>Confirm Password</label>
        <div class="input-wrapper" id="confirmPasswordWrapper">
          <i class="fas fa-check-circle"></i>
          <input type="password" id="confirmPasswordInput" placeholder="··········">
        </div>
        <div id="confirmPasswordError" class="error-message"></div>
      </div>    
      <button class="submit-btn" id="resetPasswordBtn">Reset Password</button>    
      <div style="text-align: center;">
        <a href="#" class="back-link" id="backToLoginFromReset">← Back to Login</a>
      </div>
      <div class="site-footer">WWW.EnviroNet.COM</div>
        
  </div>

  <!-- PAGE 4 : CREATE ACCOUNT -->
  <div class="card hidden" id="createAccountPage">
    
      <div class="app-logo">
      <div class="logo-image">
          <img src="logo.png" alt="EnviroNet logo" onerror="this.src='https://via.placeholder.com/70x70?text=EN'">
        </div>
        <div class="logo-text">EnviroNet</div>
      </div>
      <div class="greeting-line">
        <span class="greeting">Join</span>
        <span class="morning">EnviroNet</span>
      </div>
      <div class="signin-sub">Create your account to get started</div>
      <div class="input-group">
        <label>Full Name</label>
        <div class="input-wrapper" id="nameWrapper">
          <i class="fas fa-user"></i>
          <input type="text" id="nameInput" placeholder="John Doe">
        </div>
        <div id="nameError" class="error-message"></div>
      </div>
      <div class="input-group">
        <label>Email Address</label>
        <div class="input-wrapper" id="createEmailWrapper">
          <i class="far fa-envelope"></i>
          <input type="email" id="createEmailInput" placeholder="your@email.com">
        </div>
        <div id="createEmailError" class="error-message"></div>
      </div>
      <div class="input-group">
        <label>Password</label>
        <div class="input-wrapper" id="createPasswordWrapper">
          <i class="fas fa-lock"></i>
          <input type="password" id="createPasswordInput" placeholder="··········">
        </div>
        <div id="createPasswordError" class="error-message"></div>
        <div id="passwordStrength" class="password-strength"></div>
      </div>
      <div class="input-group">
        <label>Confirm Password</label>
        <div class="input-wrapper" id="createConfirmWrapper">
          <i class="fas fa-check-circle"></i>
          <input type="password" id="createConfirmInput" placeholder="··········">
        </div>
        <div id="createConfirmError" class="error-message"></div>
      </div>
      <div class="robot-check" id="createRobotWrapper">
        <i class="fas fa-robot"></i>
        <input type="checkbox" id="createRobotCheckbox">
        <label for="createRobotCheckbox">Je ne suis pas un robot</label>
      </div>
      <div id="createRobotError" class="error-message"></div>
      <button class="submit-btn" id="createAccountBtn">Create Account</button>    
      <div style="text-align: center;">
        <a href="#" class="back-link" id="backToLoginFromCreate">← Already have an account? Sign In</a>
      </div>    
      <div class="site-footer">WWW.EnviroNet.COM</div>
      
  </div>

  <script>
  (function() {
      // Récupération des pages
      const loginPage = document.getElementById('loginPage');
      const forgotPage1 = document.getElementById('forgotPage1');
      const resetPage = document.getElementById('resetPage');
      const createPage = document.getElementById('createAccountPage');
      // Liens de navigation
      const forgotLink = document.getElementById('forgotPasswordLink');
      const createLink = document.getElementById('createAccountLink');      
      // Tous les "Back to Login"
      const backLinks = [
          document.getElementById('backToLoginFromForgot1'),
          document.getElementById('backToLoginFromReset'),
          document.getElementById('backToLoginFromCreate')
      ].filter(el => el !== null);
      // Boutons
      const goToResetBtn = document.getElementById('goToResetPage');
      const resetPasswordBtn = document.getElementById('resetPasswordBtn');
      const createAccountBtn = document.getElementById('createAccountBtn');
      let generatedVerificationCode = "";
      // Fonctions de changement de page
      function showLogin() {
          loginPage.classList.remove('hidden');
          forgotPage1.classList.add('hidden');
          resetPage.classList.add('hidden');
          createPage.classList.add('hidden');
      }      
      function showForgot1() {
          loginPage.classList.add('hidden');
          forgotPage1.classList.remove('hidden');
          resetPage.classList.add('hidden');
          createPage.classList.add('hidden');
          // Reset forgot form
          document.getElementById('forgotEmailInput').value = '';
          document.getElementById('forgotEmailError').innerHTML = '';
          document.getElementById('forgotEmailWrapper').classList.remove('input-error');
      }      
      function showReset() {
          loginPage.classList.add('hidden');
          forgotPage1.classList.add('hidden');
          resetPage.classList.remove('hidden');
          createPage.classList.add('hidden');
          // Reset reset form
          document.getElementById('newPasswordInput').value = '';
          document.getElementById('confirmPasswordInput').value = '';
          document.getElementById('newPasswordError').innerHTML = '';
          document.getElementById('confirmPasswordError').innerHTML = '';
          document.getElementById('newPasswordWrapper').classList.remove('input-error');
          document.getElementById('confirmPasswordWrapper').classList.remove('input-error');
          document.getElementById('verificationCodeInput').value = '';
        document.getElementById('verificationCodeError').innerHTML = '';
        document.getElementById('verificationCodeWrapper').classList.remove('input-error');
      }      
      function showCreate() {
          loginPage.classList.add('hidden');
          forgotPage1.classList.add('hidden');
          resetPage.classList.add('hidden');
          createPage.classList.remove('hidden');
          // Reset create form
          document.getElementById('nameInput').value = '';
          document.getElementById('createEmailInput').value = '';
          document.getElementById('createPasswordInput').value = '';
          document.getElementById('createConfirmInput').value = '';
          document.getElementById('createRobotCheckbox').checked = false;
          // Clear errors
          const errorDivs = createPage.querySelectorAll('.error-message');
          errorDivs.forEach(div => div.innerHTML = '');
          const wrappers = createPage.querySelectorAll('.input-wrapper, .robot-check');
          wrappers.forEach(wrapper => wrapper.classList.remove('input-error', 'check-error'));
      }
      // Event listeners pour la navigation
      if (forgotLink) {
          forgotLink.addEventListener('click', (e) => {
              e.preventDefault();
              showForgot1();
          });
      }
      if (createLink) {
          createLink.addEventListener('click', (e) => {
              e.preventDefault();
              showCreate();
          });
      }
      if (goToResetBtn) {
          goToResetBtn.addEventListener('click', (e) => {
              e.preventDefault();
              const email = document.getElementById('forgotEmailInput').value.trim();
              const emailWrapper = document.getElementById('forgotEmailWrapper');
              const emailError = document.getElementById('forgotEmailError');              
              if (!email) {
                  emailWrapper.classList.add('input-error');
                  emailError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Email required';
                  return;
              }
              if (!email.includes('@') || !email.includes('.')) {
                  emailWrapper.classList.add('input-error');
                  emailError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Valid email required';
                  return;
              }              
              // Envoyer la requête de réinitialisation
              const formData = new FormData();
              formData.append('email', email);              
              fetch('forgot_password.php', {
                  method: 'POST',
                  body: formData
              })
              .then(response => response.json())
              .then(data => {
                  if (data.success) {

    // Générer code aléatoire 4 chiffres
    generatedVerificationCode = Math.floor(1000 + Math.random() * 9000).toString();

    showReset();

    setTimeout(() => {

        alert("Your verification code is : " + generatedVerificationCode);

    }, 100);

} else {
                      emailWrapper.classList.add('input-error');
                      emailError.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                  }
              })
              .catch(error => {
                  console.error('Error:', error);
                  emailWrapper.classList.add('input-error');
                  emailError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erreur de connexion';
              });
          });
      }      
      if (resetPasswordBtn) {
          resetPasswordBtn.addEventListener('click', (e) => {
              e.preventDefault();
              const newPassword = document.getElementById('newPasswordInput').value;
              const confirmPassword = document.getElementById('confirmPasswordInput').value;
              const newPasswordWrapper = document.getElementById('newPasswordWrapper');
              const confirmWrapper = document.getElementById('confirmPasswordWrapper');
              const newPasswordError = document.getElementById('newPasswordError');
              const confirmError = document.getElementById('confirmPasswordError');
              const verificationCode = document.getElementById('verificationCodeInput').value.trim();
            const verificationWrapper = document.getElementById('verificationCodeWrapper');
            const verificationError = document.getElementById('verificationCodeError');
              let isValid = true;
              // Vérification code

if (!verificationCode) {

    verificationWrapper.classList.add('input-error');

    verificationError.innerHTML =
        '<i class="fas fa-exclamation-circle"></i> Verification code required';

    isValid = false;

} else if (verificationCode !== generatedVerificationCode) {

    verificationWrapper.classList.add('input-error');

    verificationError.innerHTML =
        '<i class="fas fa-exclamation-circle"></i> Invalid verification code';

    isValid = false;

} else {

    verificationWrapper.classList.remove('input-error');

    verificationError.innerHTML = '';

}
              if (!newPassword) {
                  newPasswordWrapper.classList.add('input-error');
                  newPasswordError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Password required';
                  isValid = false;
              } else if (newPassword.length < 6) {
                  newPasswordWrapper.classList.add('input-error');
                  newPasswordError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Minimum 6 characters';
                  isValid = false;
              } else {
                  newPasswordWrapper.classList.remove('input-error');
                  newPasswordError.innerHTML = '';
              }              
              if (!confirmPassword) {
                  confirmWrapper.classList.add('input-error');
                  confirmError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please confirm your password';
                  isValid = false;
              } else if (newPassword !== confirmPassword) {
                  confirmWrapper.classList.add('input-error');
                  confirmError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match';
                  isValid = false;
              } else {
                  confirmWrapper.classList.remove('input-error');
                  confirmError.innerHTML = '';
              }              
              if (isValid) {
                  // Envoyer la requête de réinitialisation
                  const urlParams = new URLSearchParams(window.location.search);
                  const token = urlParams.get('token');                  
                  const formData = new FormData();
                  formData.append('token', token || '');
                  formData.append('new_password', newPassword);
                  formData.append('confirm_password', confirmPassword);                  
                  fetch('reset_password.php', {
                      method: 'POST',
                      body: formData
                  })
                  .then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          alert(data.message);
                          showLogin();
                      } else {
                          alert('Erreur: ' + data.message);
                      }
                  })
                  .catch(error => {
                      console.error('Error:', error);
                      alert('Erreur de connexion au serveur');
                  });
              }
          });
      }     
      if (createAccountBtn) {
          createAccountBtn.addEventListener('click', async (e) => {
              e.preventDefault();              
              const name = document.getElementById('nameInput').value.trim();
              const email = document.getElementById('createEmailInput').value.trim();
              const password = document.getElementById('createPasswordInput').value;
              const confirmPassword = document.getElementById('createConfirmInput').value;
              const robotChecked = document.getElementById('createRobotCheckbox').checked;       
              // Clear previous errors
              const errorElements = {
                  name: { wrapper: 'nameWrapper', error: 'nameError' },
                  email: { wrapper: 'createEmailWrapper', error: 'createEmailError' },
                  password: { wrapper: 'createPasswordWrapper', error: 'createPasswordError' },
                  confirm: { wrapper: 'createConfirmWrapper', error: 'createConfirmError' },
                  robot: { wrapper: 'createRobotWrapper', error: 'createRobotError' }
              };
              Object.values(errorElements).forEach(el => {
                  const wrapper = document.getElementById(el.wrapper);
                  const error = document.getElementById(el.error);
                  if (wrapper) wrapper.classList.remove('input-error', 'check-error');
                  if (error) error.innerHTML = '';
              });
              let isValid = true;
              if (!name) {
                  document.getElementById('nameWrapper').classList.add('input-error');
                  document.getElementById('nameError').innerHTML = '<i class="fas fa-exclamation-circle"></i> Full name required';
                  isValid = false;
              }
              if (!email) {
                  document.getElementById('createEmailWrapper').classList.add('input-error');
                  document.getElementById('createEmailError').innerHTML = '<i class="fas fa-exclamation-circle"></i> Email required';
                  isValid = false;
              } else if (!email.includes('@') || !email.includes('.')) {
                  document.getElementById('createEmailWrapper').classList.add('input-error');
                  document.getElementById('createEmailError').innerHTML = '<i class="fas fa-exclamation-circle"></i> Valid email required';
                  isValid = false;
              }
              if (!password) {
                  document.getElementById('createPasswordWrapper').classList.add('input-error');
                  document.getElementById('createPasswordError').innerHTML = '<i class="fas fa-exclamation-circle"></i> Password required';
                  isValid = false;
              } else if (password.length < 6) {
                  document.getElementById('createPasswordWrapper').classList.add('input-error');
                  document.getElementById('createPasswordError').innerHTML = '<i class="fas fa-exclamation-circle"></i> Minimum 6 characters';
                  isValid = false;
              }
              if (!confirmPassword) {
                  document.getElementById('createConfirmWrapper').classList.add('input-error');
                  document.getElementById('createConfirmError').innerHTML = '<i class="fas fa-exclamation-circle"></i> Please confirm your password';
                  isValid = false;
              } else if (password !== confirmPassword) {
                  document.getElementById('createConfirmWrapper').classList.add('input-error');
                  document.getElementById('createConfirmError').innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match';
                  isValid = false;
              }
              if (!robotChecked) {
                  document.getElementById('createRobotWrapper').classList.add('check-error');
                  document.getElementById('createRobotError').innerHTML = '<i class="fas fa-exclamation-circle"></i> Please confirm you are not a robot';
                  isValid = false;
              }
              if (isValid) {
                  createAccountBtn.disabled = true;
                  createAccountBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';   
                  const formData = new FormData();
                  formData.append('name', name);
                  formData.append('email', email);
                  formData.append('password', password);
                  formData.append('confirm_password', confirmPassword);
                  try {
                      const response = await fetch('register.php', {
                          method: 'POST',
                          body: formData
                      });
                      const data = await response.json();
                      if (data.success) {
                          alert(data.message);
                          showLogin();
                      } else {
                          alert('Erreur: ' + data.message);
                      }
                  } catch (error) {
                      console.error('Error:', error);
                      alert('Erreur de connexion au serveur');
                  } finally {
                      createAccountBtn.disabled = false;
                      createAccountBtn.innerHTML = 'Create Account';
                  }
              }
          });
      }
      // Password strength indicator for create account
      const createPasswordInput = document.getElementById('createPasswordInput');
      const passwordStrengthDiv = document.getElementById('passwordStrength');
      if (createPasswordInput) {
          createPasswordInput.addEventListener('input', function() {
              const password = this.value;
              let strength = 0;
              if (password.length >= 6) strength++;
              if (password.length >= 8) strength++;
              if (/[A-Z]/.test(password)) strength++;
              if (/[0-9]/.test(password)) strength++;
              if (/[^A-Za-z0-9]/.test(password)) strength++;
              let strengthText = '';
              let strengthClass = '';
              if (password.length === 0) {
                  strengthText = '';
              } else if (strength <= 2) {
                  strengthText = 'Weak password';
                  strengthClass = 'strength-weak';
              } else if (strength <= 4) {
                  strengthText = 'Medium password';
                  strengthClass = 'strength-medium';
              } else {
                  strengthText = 'Strong password';
                  strengthClass = 'strength-strong';
              }
              passwordStrengthDiv.textContent = strengthText;
              passwordStrengthDiv.className = `password-strength ${strengthClass}`;
          });
        }
      backLinks.forEach(link => {
          if (link) {
              link.addEventListener('click', (e) => {
                  e.preventDefault();
                  showLogin();
              });
          }
      });
      // Gestion du submit du login avec AJAX
      const emailInput = document.getElementById('emailInput');
      const passwordInput = document.getElementById('passwordInput');
      const rememberCheckbox = document.getElementById('rememberCheckbox');
      const robotCheck = document.getElementById('robotCheckbox');
      const submitBtn = document.getElementById('submitBtn');
      const emailWrapper = document.getElementById('emailWrapper');
      const passwordWrapper = document.getElementById('passwordWrapper');
      const robotWrapper = document.getElementById('robotWrapper');
      const emailError = document.getElementById('emailError');
      const passwordError = document.getElementById('passwordError');
      const robotError = document.getElementById('robotError');
      function resetErrors() {
          emailWrapper.classList.remove('input-error');
          passwordWrapper.classList.remove('input-error');
          robotWrapper.classList.remove('check-error');
          emailError.innerHTML = '';
          passwordError.innerHTML = '';
          robotError.innerHTML = '';
          const generalError = document.querySelector('.general-error');
          if (generalError) generalError.remove();
      }
      function showLoading(show) {
          if (show) {
              submitBtn.disabled = true;
              submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion...';
          } else {
              submitBtn.disabled = false;
              submitBtn.innerHTML = 'SUBMIT';
          }
      }
      if (submitBtn) {
          submitBtn.addEventListener('click', async function(e) {
              e.preventDefault();
              resetErrors();       
              let isValid = true;
              const email = emailInput.value.trim();
              const pwd = passwordInput.value;
              const remember = rememberCheckbox ? rememberCheckbox.checked : false;
              if (email === '') {
                  emailWrapper.classList.add('input-error');
                  emailError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Email requis';
                  isValid = false;
              } else if (!email.includes('@') || !email.includes('.')) {
                  emailWrapper.classList.add('input-error');
                  emailError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Email valide requis';
                  isValid = false;
              }
              if (pwd === '') {
                  passwordWrapper.classList.add('input-error');
                  passwordError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Mot de passe requis';
                  isValid = false;
              } else if (pwd.length < 6) {
                  passwordWrapper.classList.add('input-error');
                  passwordError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Minimum 6 caractères';
                  isValid = false;
              }
              if (!robotCheck.checked) {
                  robotWrapper.classList.add('check-error');
                  robotError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Confirmez que vous n\'êtes pas un robot';
                  isValid = false;
              }
              if (isValid) {
                  showLoading(true);   
                  try {
                      const formData = new FormData();
                      formData.append('email', email);
                      formData.append('password', pwd);
                      formData.append('remember', remember);
                      const response = await fetch('login.php', {
                          method: 'POST',
                          body: formData
                      });
                      const data = await response.json();
                      if (data.success) {
                          window.location.href = data.data.redirect;
                      } else {
                          if (data.message.includes('Email') || data.message.includes('mot de passe')) {
                              emailWrapper.classList.add('input-error');
                              passwordWrapper.classList.add('input-error');
                              emailError.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                          } else {
                              const errorDiv = document.createElement('div');
                              errorDiv.className = 'error-message general-error';
                              errorDiv.style.marginTop = '10px';
                              errorDiv.style.textAlign = 'center';
                              errorDiv.style.backgroundColor = '#fee9e9';
                              errorDiv.style.padding = '10px';
                              errorDiv.style.borderRadius = '40px';
                              errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                              const submitBtnParent = submitBtn.parentElement;
                              const existingError = submitBtnParent.querySelector('.general-error');
                              if (existingError) existingError.remove();
                              submitBtnParent.insertBefore(errorDiv, submitBtn);
                              setTimeout(() => {
                                  if (errorDiv.parentElement) errorDiv.remove();
                              }, 5000);
                          }
                      }
                  } catch (error) {
                      console.error('Erreur:', error);
                      emailError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erreur de connexion au serveur';
                      emailWrapper.classList.add('input-error');
                  } finally {
                      showLoading(false);
                  }
              } else {
                  const firstError = document.querySelector('.input-error, .check-error');
                  if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
              }
          });
      }
      if (passwordInput) {
          passwordInput.addEventListener('input', () => { 
              passwordWrapper.classList.remove('input-error'); 
              passwordError.innerHTML = ''; 
          });
      }
      if (emailInput) {
          emailInput.addEventListener('input', () => { 
              emailWrapper.classList.remove('input-error'); 
              emailError.innerHTML = ''; 
          });
      }
      if (robotCheck) {
          robotCheck.addEventListener('change', () => { 
              robotWrapper.classList.remove('check-error'); 
              robotError.innerHTML = ''; 
          });
      }
      async function checkRememberToken() {
          try {
              const response = await fetch('check_token.php');
              const data = await response.json();
              if (data.success) {
                  window.location.href = 'dashboard.php';
              }
          } catch (error) {
              console.log('Pas de token valide');
          }
      }
      checkRememberToken();
  })();
  </script>
</body>
</html>
