<?php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Registro seguro de usuario - Ojo en la vía">
    <link rel="shortcut icon" href="../imagenes/fiveicon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <title>Registrar Usuario - Ojo en la vía</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: url("../imagenes/login3.jpg") no-repeat center center/cover;
            color: #fff;
            text-align: center;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .form-box {
            background: rgba(59, 57, 57, 0.5);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 30px 25px;
            border-radius: 15px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        h2 {
            margin-bottom: 20px;
            color: #fff;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #ccc;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #444;
            border-radius: 5px;
            background: #333;
            color: #fff;
            font-size: 16px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #007bff;
        }

        .input-group {
            position: relative;
        }

        .icono-alerta {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #ffcc00;
            display: none;
            cursor: pointer;
        }

        .mensaje-error {
            display: none;
            color: #ff5555;
            font-size: 12px;
            margin-top: 5px;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }

        .strength-weak { color: #ff4444; }
        .strength-medium { color: #ffaa00; }
        .strength-strong { color: #44ff44; }

        button {
            background: #007bff;
            color: #fff;
            padding: 14px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.3s;
        }

        button:hover {
            background: #0056b3;
        }

        button:disabled {
            background: #555;
            cursor: not-allowed;
        }

        .volver-link {
            color: #0af;
            text-decoration: none;
            display: block;
            margin-top: 20px;
            font-size: 14px;
        }

        .volver-link:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
        }

        .alert-error {
            background: #ff4444;
            color: white;
        }

        .alert-success {
            background: #44ff44;
            color: black;
        }

        /* Media Queries para Responsive */
        @media (max-width: 480px) {
            body {
                padding: 15px;
                justify-content: flex-start;
                padding-top: 30px;
            }

            .form-box {
                padding: 20px 15px;
                border-radius: 10px;
            }

            h2 {
                font-size: 20px;
                margin-bottom: 15px;
            }

            .form-group {
                margin-bottom: 15px;
            }

            input, select {
                padding: 14px;
                font-size: 16px;
            }

            button {
                padding: 16px 20px;
            }

            .volver-link {
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="form-box">
        <h2>👤 Registrar Usuario</h2>

        <div id="alert-message" class="alert"></div>

        <form method="POST" id="registroForm">
            <!-- Token CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="form-group">
                <label for="nombres">Nombres:</label>
                <input type="text" id="nombres" name="nombres" placeholder="Ingresa tus nombres"
                    maxlength="50" pattern="[A-Za-záéíóúÁÉÍÓÚñÑ\s]+" required>
            </div>

            <div class="form-group">
                <label for="apellidos">Apellidos:</label>
                <input type="text" id="apellidos" name="apellidos" placeholder="Ingresa tus apellidos"
                    maxlength="50" pattern="[A-Za-záéíóúÁÉÍÓÚñÑ\s]+" required>
            </div>

            <div class="form-group">
                <label for="correo">Correo electrónico:</label>
                <div class="input-group">
                    <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com"
                        maxlength="100" required>
                    <span id="correo-alerta" class="icono-alerta" title="Este correo ya está registrado">⚠️</span>
                </div>
                <small id="mensaje-error" class="mensaje-error"></small>
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono:</label>
                <input type="tel" id="telefono" name="telefono" placeholder="Ingresa tu teléfono"
                    pattern="[0-9]{7,15}" maxlength="15" required>
                <small style="color: #ccc;">Solo números, 7-15 dígitos</small>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password"
                    placeholder="Mínimo 8 caracteres con mayúsculas, minúsculas y números"
                    minlength="8" required>
                <div id="password-strength" class="password-strength"></div>
            </div>

            <!-- Campos ocultos para rol y estado fijos -->
            <input type="hidden" name="id_rol" value="2">
            <input type="hidden" name="id_estado" value="1">

            <button type="submit" name="registrar" id="btnRegistrar">Registrar</button>
        </form>

        <a href="../index.php" class="volver-link">⬅ Volver al inicio</a>
    </div>

    <script>
        // Mostrar/ocultar alerta
        function mostrarAlerta(mensaje, tipo) {
            const alerta = document.getElementById('alert-message');
            alerta.textContent = mensaje;
            alerta.className = 'alert ' + (tipo === 'error' ? 'alert-error' : 'alert-success');
            alerta.style.display = 'block';

            setTimeout(() => {
                alerta.style.display = 'none';
            }, 5000);
        }

        // Verificar fortaleza de contraseña
        document.getElementById("password").addEventListener("input", function(e) {
            const password = this.value;
            const strengthElement = document.getElementById("password-strength");

            if (password.length === 0) {
                strengthElement.textContent = "";
                return;
            }

            let strength = 0;
            let feedback = "";

            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            if (strength <= 2) {
                feedback = "Débil";
                strengthElement.className = "password-strength strength-weak";
            } else if (strength <= 4) {
                feedback = "Media";
                strengthElement.className = "password-strength strength-medium";
            } else {
                feedback = "Fuerte";
                strengthElement.className = "password-strength strength-strong";
            }

            strengthElement.textContent = `Fortaleza: ${feedback}`;
        });

        document.getElementById("correo").addEventListener("blur", function() {
            verificarCorreo(this.value);
        });

        let timeoutId;
        document.getElementById("correo").addEventListener("input", function() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                verificarCorreo(this.value);
            }, 1000);
        });

        function verificarCorreo(correo) {
            const alerta = document.getElementById("correo-alerta");
            const mensajeError = document.getElementById("mensaje-error");
            const btnRegistrar = document.getElementById("btnRegistrar");

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (correo.trim() === "") {
                alerta.style.display = "none";
                mensajeError.style.display = "none";
                btnRegistrar.disabled = false;
                return;
            }

            if (!emailRegex.test(correo)) {
                alerta.style.display = "inline";
                mensajeError.style.display = "block";
                mensajeError.textContent = "Formato de correo inválido";
                btnRegistrar.disabled = true;
                return;
            }

            // Verificar si el correo existe
            fetch("manage/verificar_correoManage.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "correo=" + encodeURIComponent(correo)
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return res.json();
            })
            .then(data => {
                if (data.existe) {
                    alerta.style.display = "inline";
                    mensajeError.style.display = "block";
                    mensajeError.textContent = "Este correo ya está registrado. Intenta con otro.";
                    btnRegistrar.disabled = true;
                } else {
                    alerta.style.display = "none";
                    mensajeError.style.display = "none";
                    btnRegistrar.disabled = false;
                }
            })
            .catch(err => {
                console.error("Error en la verificación:", err);
                alerta.style.display = "none";
                mensajeError.style.display = "none";
                btnRegistrar.disabled = false;
            });
        }

        // Validar formulario antes de enviar
        document.getElementById("registroForm").addEventListener("submit", function(e) {
            const correo = document.getElementById("correo").value;
            const alerta = document.getElementById("correo-alerta");
            const password = document.getElementById("password").value;

            if (alerta.style.display === "inline") {
                e.preventDefault();
                mostrarAlerta("Por favor, usa un correo electrónico que no esté registrado.", "error");
                return false;
            }

            // Validar fortaleza de contraseña
            if (password.length < 8) {
                e.preventDefault();
                mostrarAlerta("La contraseña debe tener al menos 8 caracteres.", "error");
                return false;
            }

            if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])/.test(password)) {
                e.preventDefault();
                mostrarAlerta("La contraseña debe contener mayúsculas, minúsculas y números.", "error");
                return false;
            }

            const campos = ['nombres', 'apellidos', 'telefono', 'password'];
            for (let campo of campos) {
                if (!document.getElementById(campo).value.trim()) {
                    e.preventDefault();
                    mostrarAlerta("Por favor, completa todos los campos.", "error");
                    return false;
                }
            }

            return true;
        });

        document.getElementById("telefono").addEventListener("input", function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>