<?php

$configuraciones = [
    'nombre_plataforma' => 'Ojo en la Vía',
    'email_contacto' => 'ojoenlaviaparalosciudadanos@gmail.com',
    'idioma' => 'es',
    'zona_horaria' => 'America/Bogota',
    'notificaciones_activas' => true,
    'tema_oscuro' => false
];
?>

<div class="section">
    <div class="section-header">
        <h3><i class="fas fa-cog"></i> Configuración del Sistema</h3>
        <div class="config-actions">
            <button type="button" class="btn btn-secondary" id="btnResetConfig">
                <i class="fas fa-undo"></i> Restablecer
            </button>
            <button type="submit" form="configForm" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
    </div>

    <div class="section-content">
        <div class="config-container">
            <!-- Navegación lateral simplificada -->
            <div class="config-sidebar">
                <nav class="config-nav">
                    <a href="#general" class="nav-item active" data-tab="general">
                        <i class="fas fa-sliders-h"></i>
                        <span>General</span>
                    </a>
                    <a href="#notificaciones" class="nav-item" data-tab="notificaciones">
                        <i class="fas fa-bell"></i>
                        <span>Notificaciones</span>
                    </a>
                    <a href="#seguridad" class="nav-item" data-tab="seguridad">
                        <i class="fas fa-shield-alt"></i>
                        <span>Seguridad</span>
                    </a>
                    <a href="#apariencia" class="nav-item" data-tab="apariencia">
                        <i class="fas fa-palette"></i>
                        <span>Apariencia</span>
                    </a>
                </nav>
            </div>

            <!-- Contenido principal -->
            <div class="config-content">
                <form id="configForm">
                    <!-- Pestaña General -->
                    <div id="general" class="config-tab active">
                        <h4><i class="fas fa-sliders-h"></i> Configuración General</h4>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombre_plataforma" class="form-label">
                                    <i class="fas fa-signature"></i>
                                    Nombre de la Plataforma
                                </label>
                                <input type="text" id="nombre_plataforma" name="nombre_plataforma"
                                       class="form-control" value="<?php echo htmlspecialchars($configuraciones['nombre_plataforma']); ?>">
                                <div class="form-help">Nombre que aparecerá en el sitio web y correos</div>
                            </div>

                            <div class="form-group">
                                <label for="email_contacto" class="form-label">
                                    <i class="fas fa-envelope"></i>
                                    Email de Contacto
                                </label>
                                <input type="email" id="email_contacto" name="email_contacto"
                                       class="form-control" value="<?php echo htmlspecialchars($configuraciones['email_contacto']); ?>">
                                <div class="form-help">Email para que los usuarios contacten soporte</div>
                            </div>

                            <div class="form-group">
                                <label for="idioma" class="form-label">
                                    <i class="fas fa-language"></i>
                                    Idioma del Sistema
                                </label>
                                <select id="idioma" name="idioma" class="form-control">
                                    <option value="es" <?php echo $configuraciones['idioma'] == 'es' ? 'selected' : ''; ?>>Español</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="zona_horaria" class="form-label">
                                    <i class="fas fa-clock"></i>
                                    Zona Horaria
                                </label>
                                <select id="zona_horaria" name="zona_horaria" class="form-control">
                                    <option value="America/Bogota" <?php echo $configuraciones['zona_horaria'] == 'America/Bogota' ? 'selected' : ''; ?>>Bogotá (UTC-5)</option>
                                    <option value="America/Mexico_City" <?php echo $configuraciones['zona_horaria'] == 'America/Mexico_City' ? 'selected' : ''; ?>>Ciudad de México (UTC-6)</option>
                                    <option value="America/Argentina/Buenos_Aires" <?php echo $configuraciones['zona_horaria'] == 'America/Argentina/Buenos_Aires' ? 'selected' : ''; ?>>Buenos Aires (UTC-3)</option>
                                    <option value="America/Lima" <?php echo $configuraciones['zona_horaria'] == 'America/Lima' ? 'selected' : ''; ?>>Lima (UTC-5)</option>
                                    <option value="America/Santiago" <?php echo $configuraciones['zona_horaria'] == 'America/Santiago' ? 'selected' : ''; ?>>Santiago (UTC-4)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Pestaña Notificaciones -->
                    <div id="notificaciones" class="config-tab">
                        <h4><i class="fas fa-bell"></i> Configuración de Notificaciones</h4>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-bell"></i>
                                    Sistema de Notificaciones
                                </label>
                                <div class="toggle-group">
                                    <label class="toggle">
                                        <input type="checkbox" name="notificaciones_activas"
                                               <?php echo $configuraciones['notificaciones_activas'] ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">Activar sistema de notificaciones</span>
                                </div>
                                <div class="form-help">Habilita las notificaciones del sistema para administradores</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-envelope"></i>
                                    Notificaciones por Email
                                </label>
                                <div class="toggle-group">
                                    <label class="toggle">
                                        <input type="checkbox" name="notificaciones_email" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">Enviar notificaciones por correo electrónico</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-mobile-alt"></i>
                                    Notificaciones Push
                                </label>
                                <div class="toggle-group">
                                    <label class="toggle">
                                        <input type="checkbox" name="notificaciones_push">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">Activar notificaciones push en el navegador</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="frecuencia_notificaciones" class="form-label">
                                    <i class="fas fa-sync"></i>
                                    Frecuencia de Resumen
                                </label>
                                <select id="frecuencia_notificaciones" name="frecuencia_notificaciones" class="form-control">
                                    <option value="diario">Resumen Diario</option>
                                    <option value="semanal" selected>Resumen Semanal</option>
                                    <option value="mensual">Resumen Mensual</option>
                                    <option value="ninguno">Sin resumen</option>
                                </select>
                                <div class="form-help">Frecuencia del resumen de actividades para administradores</div>
                            </div>
                        </div>
                    </div>

                    <!-- Pestaña Seguridad -->
                    <div id="seguridad" class="config-tab">
                        <h4><i class="fas fa-shield-alt"></i> Configuración de Seguridad</h4>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="longitud_minima_password" class="form-label">
                                    <i class="fas fa-key"></i>
                                    Longitud Mínima de Contraseña
                                </label>
                                <input type="number" id="longitud_minima_password" name="longitud_minima_password"
                                       class="form-control" value="8" min="6" max="20">
                                <div class="form-help">Longitud mínima requerida para las contraseñas de usuario</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-redo"></i>
                                    Forzar Cambio de Contraseña
                                </label>
                                <div class="toggle-group">
                                    <label class="toggle">
                                        <input type="checkbox" name="forzar_cambio_password">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">Forzar cambio de contraseña cada 90 días</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="intentos_login" class="form-label">
                                    <i class="fas fa-lock"></i>
                                    Intentos de Login Permitidos
                                </label>
                                <input type="number" id="intentos_login" name="intentos_login"
                                       class="form-control" value="5" min="3" max="10">
                                <div class="form-help">Número de intentos fallidos antes de bloquear la cuenta</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-fingerprint"></i>
                                    Autenticación de Dos Factores
                                </label>
                                <div class="toggle-group">
                                    <label class="toggle">
                                        <input type="checkbox" name="auth_dos_factores">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">Requerir autenticación de dos factores para administradores</span>
                                </div>
                                <div class="form-help">Aumenta la seguridad del panel de administración</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-history"></i>
                                    Historial de Sesiones
                                </label>
                                <div class="toggle-group">
                                    <label class="toggle">
                                        <input type="checkbox" name="historial_sesiones" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">Guardar historial de inicios de sesión</span>
                                </div>
                                <div class="form-help">Registra todos los accesos al sistema</div>
                            </div>
                        </div>
                    </div>

                    <!-- Pestaña Apariencia -->
                    <div id="apariencia" class="config-tab">
                        <h4><i class="fas fa-palette"></i> Configuración de Apariencia</h4>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-moon"></i>
                                    Tema Oscuro
                                </label>
                                <div class="toggle-group">
                                    <label class="toggle">
                                        <input type="checkbox" name="tema_oscuro"
                                               <?php echo $configuraciones['tema_oscuro'] ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">Activar tema oscuro en el panel de administración</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="tema_color" class="form-label">
                                    <i class="fas fa-paint-brush"></i>
                                    Color del Tema
                                </label>
                                <select id="tema_color" name="tema_color" class="form-control">
                                    <option value="azul" selected>Azul</option>
                                    <option value="verde">Verde</option>
                                    <option value="rojo">Rojo</option>
                                    <option value="purpura">Púrpura</option>
                                    <option value="naranja">Naranja</option>
                                </select>
                                <div class="form-help">Color principal de la interfaz de administración</div>
                            </div>

                            <div class="form-group">
                                <label for="densidad_interfaz" class="form-label">
                                    <i class="fas fa-arrows-alt-v"></i>
                                    Densidad de la Interfaz
                                </label>
                                <select id="densidad_interfaz" name="densidad_interfaz" class="form-control">
                                    <option value="compacta">Compacta</option>
                                    <option value="normal" selected>Normal</option>
                                    <option value="espaciosa">Espaciosa</option>
                                </select>
                                <div class="form-help">Controla el espaciado entre elementos de la interfaz</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-eye"></i>
                                    Mostrar Estadísticas en Dashboard
                                </label>
                                <div class="toggle-group">
                                    <label class="toggle">
                                        <input type="checkbox" name="mostrar_estadisticas" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label">Mostrar gráficas y estadísticas en el dashboard</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-desktop"></i>
                                Vista Previa del Tema
                            </label>
                            <div class="theme-preview">
                                <div class="preview-header">
                                    <div class="preview-nav"></div>
                                </div>
                                <div class="preview-content">
                                    <div class="preview-sidebar"></div>
                                    <div class="preview-main">
                                        <div class="preview-card"></div>
                                        <div class="preview-card"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>