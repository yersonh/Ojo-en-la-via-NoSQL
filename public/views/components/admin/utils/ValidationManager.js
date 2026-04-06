import { FormConstants } from './utils/Constants.js';
import { FormHelpers } from './utils/Helpers.js';

export class ValidationManager {
    constructor(formManager) {
        this.formManager = formManager;
    }

    initialize() {
        this.setupRealTimeValidation();
    }

    setupRealTimeValidation() {
        const campos = ['tipo', 'descripcion'];
        
        campos.forEach(campoId => {
            const campo = document.getElementById(campoId);
            if (campo) {
                campo.addEventListener('blur', () => {
                    this.validateField(campoId);
                });
                
                campo.addEventListener('input', () => {
                    this.clearFieldError(campoId);
                });
            }
        });
    }

    validateForm() {
        const campos = ['tipo', 'descripcion'];
        let isValid = true;
        
        // Validar cada campo
        campos.forEach(campoId => {
            if (!this.validateField(campoId)) {
                isValid = false;
            }
        });
        
        // Validar ubicación
        if (!this.validateLocation()) {
            isValid = false;
        }
        
        return isValid;
    }

    validateField(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return false;
        
        let isValid = true;
        let errorMessage = '';
        
        switch(fieldId) {
            case 'tipo':
                if (!field.value) {
                    errorMessage = FormConstants.MESSAGES.ERROR.INVALID_TYPE;
                    isValid = false;
                }
                break;
                
            case 'descripcion':
                const descripcion = field.value.trim();
                if (!descripcion) {
                    errorMessage = FormConstants.MESSAGES.ERROR.DESCRIPTION_REQUIRED;
                    isValid = false;
                } else if (descripcion.length < FormConstants.MIN_DESCRIPTION_LENGTH) {
                    errorMessage = FormConstants.MESSAGES.ERROR.DESCRIPTION_TOO_SHORT + 
                                  FormConstants.MIN_DESCRIPTION_LENGTH + ' caracteres';
                    isValid = false;
                } else if (descripcion.length > FormConstants.MAX_DESCRIPTION_LENGTH) {
                    errorMessage = FormConstants.MESSAGES.ERROR.DESCRIPTION_TOO_LONG + 
                                  FormConstants.MAX_DESCRIPTION_LENGTH + ' caracteres';
                    isValid = false;
                }
                break;
        }
        
        if (!isValid) {
            this.showFieldError(fieldId, errorMessage);
        } else {
            this.showFieldSuccess(fieldId);
        }
        
        return isValid;
    }

    validateLocation() {
        const lat = document.getElementById('latitud').value;
        const lng = document.getElementById('longitud').value;
        
        if (!lat || !lng) {
            this.formManager.showAlert(
                FormConstants.MESSAGES.ERROR.NO_LOCATION, 
                'error'
            );
            return false;
        }
        
        return true;
    }

    showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const group = field.closest('.form-group') || field.parentElement;
        
        // Limpiar errores anteriores
        this.clearFieldError(fieldId);
        
        // Agregar clase de error
        field.classList.add('campo-error');
        
        // Crear mensaje de error
        const errorDiv = document.createElement('div');
        errorDiv.className = 'mensaje-error';
        errorDiv.textContent = message;
        
        group.appendChild(errorDiv);
    }

    showFieldSuccess(fieldId) {
        const field = document.getElementById(fieldId);
        field.classList.remove('campo-error');
        field.classList.add('campo-exito');
        
        setTimeout(() => {
            field.classList.remove('campo-exito');
        }, 2000);
    }

    clearFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        const group = field.closest('.form-group') || field.parentElement;
        const errorMessage = group.querySelector('.mensaje-error');
        
        if (errorMessage) {
            errorMessage.remove();
        }
        
        field.classList.remove('campo-error');
    }

    clearAllErrors() {
        const errors = document.querySelectorAll('.mensaje-error');
        errors.forEach(error => error.remove());
        
        const fields = document.querySelectorAll('.campo-error, .campo-exito');
        fields.forEach(field => {
            field.classList.remove('campo-error', 'campo-exito');
        });
    }

    setupCharacterCounter() {
        const descripcion = document.getElementById('descripcion');
        if (!descripcion) return;
        
        const counter = document.createElement('div');
        counter.className = 'contador-caracteres';
        counter.innerHTML = '<span class="contador-actual">0</span>/<span class="contador-maximo">' + 
                           FormConstants.MAX_DESCRIPTION_LENGTH + '</span> caracteres';
        
        descripcion.parentNode.insertBefore(counter, descripcion.nextSibling);
        
        descripcion.addEventListener('input', (e) => {
            this.updateCharacterCounter(e.target.value.length, counter);
        });
    }

    updateCharacterCounter(currentLength, counter) {
        const maxLength = FormConstants.MAX_DESCRIPTION_LENGTH;
        const currentSpan = counter.querySelector('.contador-actual');
        const maxSpan = counter.querySelector('.contador-maximo');
        
        currentSpan.textContent = currentLength;
        maxSpan.textContent = maxLength;
        
        // Cambiar color según el número de caracteres
        if (currentLength < 10) {
            counter.style.color = FormConstants.STYLES.ERROR_COLOR;
        } else if (currentLength < 50) {
            counter.style.color = FormConstants.STYLES.WARNING_COLOR;
        } else {
            counter.style.color = FormConstants.STYLES.SUCCESS_COLOR;
        }
        
        if (currentLength > maxLength) {
            counter.style.color = FormConstants.STYLES.ERROR_COLOR;
            currentSpan.style.fontWeight = 'bold';
        } else {
            currentSpan.style.fontWeight = 'normal';
        }
    }
}