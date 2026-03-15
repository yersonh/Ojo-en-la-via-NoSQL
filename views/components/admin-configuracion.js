class ConfigManager {
    constructor() {
        this.currentTab = 'general';
        this.unsavedChanges = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupFormValidation();
        this.loadCurrentSettings();
    }

    setupEventListeners() {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchTab(item.getAttribute('data-tab'));
            });
        });

        document.getElementById('configForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveSettings();
        });

        document.getElementById('btnResetConfig').addEventListener('click', () => {
            this.resetSettings();
        });

        document.querySelectorAll('#configForm input, #configForm select').forEach(element => {
            element.addEventListener('change', () => {
                this.unsavedChanges = true;
                this.updateSaveButton();
            });
        });

        document.querySelectorAll('input[name="tema_oscuro"], select[name="tema_color"]').forEach(element => {
            element.addEventListener('change', () => {
                this.updateThemePreview();
            });
        });

        window.addEventListener('beforeunload', (e) => {
            if (this.unsavedChanges) {
                e.preventDefault();
                e.returnValue = 'Tienes cambios sin guardar. ¿Estás seguro de que quieres salir?';
            }
        });
    }

    switchTab(tabId) {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelectorAll('.config-tab').forEach(tab => {
            tab.classList.remove('active');
        });

        // Activar pestaña seleccionada
        document.querySelector(`.nav-item[data-tab="${tabId}"]`).classList.add('active');
        document.getElementById(tabId).classList.add('active');

        this.currentTab = tabId;

        // Guardar última pestaña vista
        localStorage.setItem('lastConfigTab', tabId);
    }

    setupFormValidation() {
        const form = document.getElementById('configForm');

        form.addEventListener('input', (e) => {
            this.validateField(e.target);
        });

        form.addEventListener('change', (e) => {
            this.validateField(e.target);
        });
    }

    validateField(field) {
        const value = field.value;
        let isValid = true;
        let message = '';

        switch (field.name) {
            case 'email_contacto':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                isValid = emailRegex.test(value);
                message = isValid ? '' : 'Por favor ingresa un email válido';
                break;

            case 'longitud_minima_password':
                isValid = value >= 6 && value <= 20;
                message = isValid ? '' : 'La longitud debe estar entre 6 y 20 caracteres';
                break;

            case 'intentos_login':
                isValid = value >= 3 && value <= 10;
                message = isValid ? '' : 'Los intentos deben estar entre 3 y 10';
                break;
        }

        this.setFieldValidation(field, isValid, message);
        return isValid;
    }

    setFieldValidation(field, isValid, message) {
        field.style.borderColor = isValid ? '' : '#e63946';

        const existingMessage = field.parentNode.querySelector('.field-error');
        if (existingMessage) {
            existingMessage.remove();
        }

        // Agregar nuevo mensaje si hay error
        if (!isValid && message) {
            const errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            errorElement.style.color = '#e63946';
            errorElement.style.fontSize = '0.8rem';
            errorElement.style.marginTop = '5px';
            errorElement.textContent = message;
            field.parentNode.appendChild(errorElement);
        }
    }

    async saveSettings() {
        if (!this.validateForm()) {
            this.showMessage('Por favor corrige los errores en el formulario', 'error');
            return;
        }

        try {
            this.showLoading(true);

            const formData = new FormData(document.getElementById('configForm'));
            const settings = Object.fromEntries(formData);

            console.log('Guardando configuración:', settings);
            await new Promise(resolve => setTimeout(resolve, 800));

            this.unsavedChanges = false;
            this.updateSaveButton();
            this.showMessage('Configuración guardada exitosamente', 'success');

            // Aplicar cambios en tiempo real
            this.applyRealTimeChanges(settings);

        } catch (error) {
            console.error('Error guardando configuración:', error);
            this.showMessage('Error al guardar la configuración', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    applyRealTimeChanges(settings) {
        if (settings.tema_oscuro === 'on') {
            document.body.classList.add('dark-theme');
        } else {
            document.body.classList.remove('dark-theme');
        }

        if (settings.nombre_plataforma) {
            document.title = settings.nombre_plataforma + ' - Admin';
            const platformNameElements = document.querySelectorAll('.platform-name');
            platformNameElements.forEach(el => {
                el.textContent = settings.nombre_plataforma;
            });
        }
    }

    validateForm() {
        let isValid = true;
        const form = document.getElementById('configForm');

        form.querySelectorAll('input[required], select[required]').forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        return isValid;
    }

    resetSettings() {
        if (confirm('¿Estás seguro de que quieres restablecer todas las configuraciones a sus valores por defecto?')) {
            document.getElementById('configForm').reset();
            this.unsavedChanges = true;
            this.updateSaveButton();
            this.updateThemePreview();
            this.showMessage('Configuración restablecida. Recuerda guardar los cambios.', 'info');
        }
    }

    loadCurrentSettings() {
        // Cargar última pestaña vista
        const lastTab = localStorage.getItem('lastConfigTab');
        if (lastTab && document.querySelector(`.nav-item[data-tab="${lastTab}"]`)) {
            this.switchTab(lastTab);
        }

        this.updateThemePreview();
    }

    updateThemePreview() {
        const temaOscuro = document.querySelector('input[name="tema_oscuro"]').checked;
        const temaColor = document.querySelector('select[name="tema_color"]').value;

        const preview = document.querySelector('.theme-preview');
        if (preview) {
            if (temaOscuro) {
                preview.style.filter = 'brightness(0.8)';
            } else {
                preview.style.filter = 'brightness(1)';
            }
        }
    }

    updateSaveButton() {
        const saveButton = document.querySelector('button[type="submit"]');
        if (this.unsavedChanges) {
            saveButton.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios *';
            saveButton.style.background = '#f39c12';
            saveButton.classList.add('has-unsaved-changes');
        } else {
            saveButton.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
            saveButton.style.background = '';
            saveButton.classList.remove('has-unsaved-changes');
        }
    }

    showLoading(show) {
        const form = document.getElementById('configForm');
        const saveButton = document.querySelector('button[type="submit"]');

        if (show) {
            form.classList.add('config-saving');
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            saveButton.disabled = true;
        } else {
            form.classList.remove('config-saving');
            this.updateSaveButton();
            saveButton.disabled = false;
        }
    }

    showMessage(message, type = 'info') {
        // Crear o actualizar elemento de mensaje
        let messageElement = document.querySelector('.config-success');

        if (!messageElement) {
            messageElement = document.createElement('div');
            messageElement.className = 'config-success';
            document.querySelector('.config-content').prepend(messageElement);
        }

        const styles = {
            success: { bg: '#d4edda', color: '#155724', border: '#c3e6cb', icon: 'fa-check-circle' },
            error: { bg: '#f8d7da', color: '#721c24', border: '#f5c6cb', icon: 'fa-exclamation-circle' },
            info: { bg: '#cce7ff', color: '#004085', border: '#b3d7ff', icon: 'fa-info-circle' }
        };

        const style = styles[type] || styles.info;

        messageElement.style.background = style.bg;
        messageElement.style.color = style.color;
        messageElement.style.borderColor = style.border;
        messageElement.innerHTML = `<i class="fas ${style.icon}"></i> ${message}`;
        messageElement.classList.add('show');

        setTimeout(() => {
            messageElement.classList.remove('show');
        }, 5000);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const configTab = document.getElementById('configuracion');
    if (configTab && configTab.classList.contains('active')) {
        window.configManager = new ConfigManager();
    }
});

document.addEventListener('click', function(e) {
    if (e.target.matches('.sidebar-menu a[data-tab="configuracion"]')) {
        setTimeout(() => {
            if (!window.configManager) {
                window.configManager = new ConfigManager();
            }
        }, 100);
    }
});