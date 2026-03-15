<?php
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ojo en la vía</title>
  <link rel="icon" href="/imagenes/fiveicon.png" type="image/png">
  <link rel="shortcut icon" href="/imagenes/fiveicon.png" type="image/png">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: Arial, sans-serif;
    }

    body {
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      background: url("/imagenes/login3.jpg") no-repeat center center/cover;
      padding: 20px;
    }

    .container {
      width: 100%;
      max-width: 1000px;
      height: auto;
      min-height: 500px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 0 25px rgba(0, 0, 0, 0.6);
    }

    /* Lado izquierdo */
    .left {
      background: rgba(59, 57, 57, 0.8);
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 40px;
    }

    .left h1 {
      font-size: clamp(2rem, 4vw, 2.5rem);
      margin-bottom: 20px;
      text-align: center;
    }

    .left p {
      margin-bottom: 20px;
      color: #ddd;
      line-height: 1.5;
      text-align: center;
      font-size: clamp(0.9rem, 2vw, 1.1rem);
    }

    .icons {
      text-align: center;
    }

    .icons i {
      margin: 0 10px;
      cursor: pointer;
      font-size: 1.5rem;
      transition: color 0.3s;
    }

    .icons i:hover {
      color: #1e8ee9;
    }

    /* Lado derecho */
    .right {
      background: rgba(40, 38, 38, 0.85);
      backdrop-filter: blur(10px);
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 40px;
      color: white;
    }

    .right h2 {
      text-align: center;
      margin-bottom: 30px;
      font-size: clamp(1.5rem, 3vw, 1.8rem);
    }

    .input-box {
      position: relative;
      margin-bottom: 25px;
    }

    .input-box input {
      width: 100%;
      padding: 14px 40px;
      border: none;
      border-bottom: 2px solid #fff;
      background: transparent;
      outline: none;
      color: white;
      font-size: 16px;
      transition: border-color 0.3s;
    }

    .input-box input:focus {
      border-bottom-color: #1e8ee9;
    }

    .input-box input::placeholder {
      color: #ccc;
    }

    .input-box i {
      position: absolute;
      top: 50%;
      left: 10px;
      transform: translateY(-50%);
      color: white;
      z-index: 2;
    }

    /* Botón para mostrar/ocultar contraseña */
    .toggle-password {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #ccc;
      cursor: pointer;
      font-size: 16px;
      z-index: 2;
      padding: 5px;
      transition: color 0.3s;
    }

    .toggle-password:hover {
      color: #1e8ee9;
    }

    .options {
      display: flex;
      justify-content: space-between;
      font-size: 14px;
      margin-bottom: 25px;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }

    .options label {
      display: flex;
      align-items: center;
      gap: 5px;
      white-space: nowrap;
    }

    .options a {
      color: #1e8ee9;
      text-decoration: none;
      transition: color 0.3s;
      cursor: pointer;
    }

    .options a:hover {
      text-decoration: underline;
    }

    .btn {
      background: #1e8ee9;
      border: none;
      padding: 14px;
      width: 100%;
      color: white;
      font-size: 16px;
      cursor: pointer;
      border-radius: 8px;
      transition: all 0.3s;
      font-weight: bold;
    }

    .btn:hover {
      background: #1865c2;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(30, 142, 233, 0.3);
    }

    .btn:active {
      transform: translateY(0);
    }

    .signup {
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
    }

    .signup a {
      color: #1e8ee9;
      text-decoration: none;
      font-weight: bold;
      transition: color 0.3s;
    }

    .signup a:hover {
      text-decoration: underline;
    }

    /* Mensajes (ahora ocultos por defecto) */
    .alert-error {
      background: rgba(255, 68, 68, 0.9);
      color: white;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      display: none;
      border-left: 4px solid #ff4444;
    }

    .alert-success {
      background: rgba(76, 175, 80, 0.9);
      color: white;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      display: none;
      border-left: 4px solid #4CAF50;
    }

    /* Modal de recuperación */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      backdrop-filter: blur(5px);
    }

    .modal-content {
      background: rgba(40, 38, 38, 0.95);
      margin: 15% auto;
      padding: 30px;
      border-radius: 15px;
      width: 90%;
      max-width: 400px;
      color: white;
      box-shadow: 0 0 30px rgba(0,0,0,0.5);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .modal-header h3 {
      margin: 0;
      color: #1e8ee9;
    }

    .close {
      color: #aaa;
      font-size: 24px;
      font-weight: bold;
      cursor: pointer;
      transition: color 0.3s;
    }

    .close:hover {
      color: white;
    }

    .modal-text {
      margin-bottom: 20px;
      color: #ddd;
      line-height: 1.5;
    }

    /* Mejorar el autocompletar del navegador */
    input:-webkit-autofill,
    input:-webkit-autofill:hover,
    input:-webkit-autofill:focus {
      -webkit-text-fill-color: white !important;
      -webkit-box-shadow: 0 0 0px 1000px transparent inset !important;
      transition: background-color 5000s ease-in-out 0s !important;
    }

    /* Responsive */
    @media (max-width: 768px) {
      body {
        padding: 15px;
        height: auto;
        min-height: 100vh;
        align-items: flex-start;
        padding-top: 40px;
      }

      .container {
        grid-template-columns: 1fr;
        height: auto;
        margin: 0;
      }

      .left, .right {
        padding: 30px 25px;
      }

      .left {
        order: 2;
      }

      .right {
        order: 1;
      }

      .options {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
      }

      .modal-content {
        margin: 20%;
        width: 95%;
      }
    }

    @media (max-width: 480px) {
      .left, .right {
        padding: 25px 20px;
      }

      .input-box input {
        padding: 12px 35px;
        font-size: 16px;
      }

      .btn {
        padding: 12px;
      }

      .modal-content {
        padding: 20px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Lado izquierdo -->
    <div class="left">
      <h1>Bienvenido!</h1>
      <p>Explora nuestra plataforma "Ojo en la vía", donde podrás reportar y consultar el estado de las calles de Villavicencio.</p>
      <div class="icons">
        <i class="fab fa-facebook"></i>
        <i class="fab fa-twitter"></i>
        <i class="fab fa-instagram"></i>
      </div>
    </div>

    <!-- Lado derecho -->
    <div class="right">
      <h2>Iniciar Sesión</h2>

      <div class="alert-error"></div>
      <div class="alert-success"></div>

      <form method="POST" action="" id="loginForm" autocomplete="on">
        <div class="input-box">
            <i class="fa-solid fa-envelope"></i>
            <input
                type="email"
                name="email"
                placeholder="Email"
                required
                autocomplete="email"
                id="email-field"
            >
        </div>

        <div class="input-box">
            <i class="fa-solid fa-lock"></i>
            <input
                type="password"
                name="password"
                placeholder="Contraseña"
                required
                autocomplete="current-password"
                id="password-field"
            >
            <button type="button" class="toggle-password" id="togglePassword">
                <i class="fa-solid fa-eye"></i>
            </button>
        </div>

        <div class="options">
          <label>
            <input type="checkbox" name="remember"> Recuérdame
          </label>
          <a id="openRecoveryModal">¿Olvidaste tu contraseña?</a>
        </div>

        <button class="btn" type="submit">Ingresar</button>

        <div class="signup">
          ¿No tienes cuenta? <a href="/views/usuarioregistrar.php">Regístrate</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal de recuperación de contraseña -->
  <div id="recoveryModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fa-solid fa-key"></i> Recuperar Contraseña</h3>
        <span class="close">&times;</span>
      </div>
      <div class="modal-text">
        <p>Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
      </div>
      <form method="POST" action="" id="recoveryForm">
        <div class="input-box">
          <i class="fa-solid fa-envelope"></i>
          <input type="email" name="email" placeholder="Tu correo electrónico" required autocomplete="email">
        </div>
        <button class="btn" type="submit">Enviar Enlace</button>
      </form>
    </div>
  </div>

  <script>
    // Validación básica del formulario de login
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.querySelector('#loginForm input[name="email"]').value;
        const password = document.querySelector('#loginForm input[name="password"]').value;

        if (!email || !password) {
            e.preventDefault();
            alert('Por favor, completa todos los campos.');
            return false;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Por favor, ingresa un email válido.');
            return false;
        }

        return true;
    });

    // Manejo del modal de recuperación
    const modal = document.getElementById('recoveryModal');
    const openBtn = document.getElementById('openRecoveryModal');
    const closeBtn = document.querySelector('.close');

    openBtn.addEventListener('click', function() {
        modal.style.display = 'block';
    });

    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    document.getElementById('recoveryForm').addEventListener('submit', function(e) {
        const email = document.querySelector('#recoveryForm input[name="email"]').value;

        if (!email) {
            e.preventDefault();
            alert('Por favor, ingresa tu correo electrónico.');
            return false;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Por favor, ingresa un email válido.');
            return false;
        }

        return true;
    });

    // NUEVO CÓDIGO PARA MOSTRAR/OCULTAR CONTRASEÑA Y MEJORAS
    document.addEventListener('DOMContentLoaded', function() {
        // Función para mostrar/ocultar contraseña
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password-field');
        const toggleIcon = togglePassword.querySelector('i');

        togglePassword.addEventListener('click', function() {
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'fa-solid fa-eye-slash';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'fa-solid fa-eye';
            }
        });

        // Prevenir envío múltiple del formulario
        const loginForm = document.getElementById('loginForm');
        let isSubmitting = false;

        loginForm.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return;
            }

            isSubmitting = true;
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Ingresando...';

            // Re-enable after 5 seconds in case of error
            setTimeout(() => {
                isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 5000);
        });

        // Mejora: Recordar el email localmente
        const emailInput = document.querySelector('input[name="email"]');
        const rememberCheckbox = document.querySelector('input[name="remember"]');

        // Cargar email guardado si existe
        const savedEmail = localStorage.getItem('remembered_email');
        if (savedEmail && emailInput.value === '') {
            emailInput.value = savedEmail;
            rememberCheckbox.checked = true;
        }

        // Guardar email cuando el usuario marque "Recuérdame"
        rememberCheckbox.addEventListener('change', function() {
            if (this.checked && emailInput.value) {
                localStorage.setItem('remembered_email', emailInput.value);
            } else {
                localStorage.removeItem('remembered_email');
            }
        });

        // Enfocar automáticamente el campo de contraseña después de email
        emailInput.addEventListener('input', function() {
            if (this.value.length > 3) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailRegex.test(this.value)) {
                    passwordField.focus();
                }
            }
        });
    });

    // Mejora para el botón de recuperación
    document.getElementById('recoveryForm').addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

        // Re-enable after 5 seconds
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }, 5000);
    });
    </script>
</body>
</html>