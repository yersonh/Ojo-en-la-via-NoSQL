import { FormConstants, AnimationConstants } from './utils/Constants.js';
import { FormHelpers } from './utils/Helpers.js';

export class UIManager {
    constructor(formManager) {
        this.formManager = formManager;
    }

    initialize() {
        FormHelpers.injectStyles();
        this.enhanceFormUI();
        this.setupVisualEffects();
        this.setupMobilePanel();
        this.setupMapIntegration();
    }

    enhanceFormUI() {
        console.log('🎨 Mejorando UI del formulario...');
        
        const form = document.querySelector(FormConstants.SELECTORS.FORM);
        form.classList.add('formulario-moderno');
        
        this.enhanceSelect();
        this.enhanceTextarea();
        this.enhanceButtons();
        
        console.log('✅ UI del formulario mejorada');
    }

    enhanceSelect() {
        const select = document.querySelector(FormConstants.SELECTORS.TIPO);
        if (select) {
            select.classList.add('select-moderno');
        }
    }

    enhanceTextarea() {
        const textarea = document.querySelector(FormConstants.SELECTORS.DESCRIPCION);
        if (textarea) {
            textarea.classList.add('textarea-moderno');
            textarea.setAttribute('placeholder', 'Describe detalladamente el incidente...');
        }
    }

    enhanceButtons() {
        const submitBtn = document.querySelector(FormConstants.SELECTORS.SUBMIT_BTN);
        if (submitBtn) {
            submitBtn.innerHTML = `
                <span class="btn-text">📝 Registrar Reporte</span>
                <span class="btn-loading" style="display: none;">
                    ${FormHelpers.createLoadingSpinner().outerHTML} Procesando...
                </span>
            `;
        }
        
        const fileBtn = document.querySelector(FormConstants.SELECTORS.BTN_SELECCIONAR_ARCHIVO);
        if (fileBtn) {
            fileBtn.innerHTML = '📁 Seleccionar Archivos';
        }
    }

    setupVisualEffects() {
        // Efecto de focus para todos los campos
        const fields = document.querySelectorAll('#formReporte select, #formReporte textarea, #formReporte input');
        
        fields.forEach(field => {
            field.addEventListener('focus', function() {
                this.parentElement.classList.add('campo-focus');
            });
            
            field.addEventListener('blur', function() {
                this.parentElement.classList.remove('campo-focus');
            });
        });
    }
showOfflineSuccessAnimation() {
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.classList.add('offline-success-animation');
        setTimeout(() => {
            submitBtn.classList.remove('offline-success-animation');
        }, 600);
    }
}
showAlert(message, type = 'success') {
    console.log('📢 Mostrando alerta:', message, 'Tipo:', type);
    
    let alertElement;
    
    if (type === 'offline-success') {
        // Usar alertSuccess pero con estilo diferente
        alertElement = document.getElementById('alertSuccess');
        if (alertElement) {
            alertElement.textContent = message;
            alertElement.className = 'alert alert-success alert-offline';
            alertElement.style.display = 'block';
            
            setTimeout(() => {
                alertElement.style.display = 'none';
            }, 5000);
        }
    } else if (type === 'success') {
        alertElement = document.getElementById('alertSuccess');
        if (alertElement) {
            alertElement.textContent = message;
            alertElement.className = 'alert alert-success';
            alertElement.style.display = 'block';
            
            setTimeout(() => {
                alertElement.style.display = 'none';
            }, 5000);
        }
    } else if (type === 'error') {
        alertElement = document.getElementById('alertError');
        if (alertElement) {
            alertElement.textContent = message;
            alertElement.className = 'alert alert-error';
            alertElement.style.display = 'block';
            
            setTimeout(() => {
                alertElement.style.display = 'none';
            }, 5000);
        }
    }
    
    // 🆕 FALLBACK SI NO HAY ELEMENTOS DE ALERTA
    if (!alertElement) {
        console.warn('⚠️ No se encontraron elementos de alerta, usando fallback');
        this.showFallbackAlert(message, type);
    }
}
showFallbackAlert(message, type) {
    // Crear alerta temporal
    const alertDiv = document.createElement('div');
    alertDiv.textContent = message;
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: bold;
        z-index: 10000;
        max-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideInRight 0.5s ease;
    `;
    
    if (type === 'offline-success') {
        alertDiv.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
    } else if (type === 'success') {
        alertDiv.style.background = 'linear-gradient(135deg, #10b981, #059669)';
    } else {
        alertDiv.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
    }
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
    setupMobilePanel() {
        const panelToggle = document.getElementById('panelToggle');
        const panel = document.getElementById('panel');
        
        if (!panelToggle || !panel) return;
        
        panelToggle.addEventListener('click', function() {
            panel.classList.toggle('active');
            panelToggle.textContent = panel.classList.contains('active') ? '🗺️ Mapa' : '📋 Formulario';
        });
        
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                const isClickInsidePanel = panel.contains(event.target);
                const isClickOnToggle = panelToggle.contains(event.target);
                
                if (!isClickInsidePanel && !isClickOnToggle && panel.classList.contains('active')) {
                    panel.classList.remove('active');
                    panelToggle.textContent = '📋 Formulario';
                }
            }
        });
    }

    showLoadingState() {
    const submitBtn = document.querySelector('button[type="submit"]');
    
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        if (btnText) btnText.style.display = 'none';
        if (btnLoading) btnLoading.style.display = 'block';
    }
}

    hideLoadingState() {
    const submitBtn = document.querySelector('button[type="submit"]');
    
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        if (btnText) btnText.style.display = 'block';
        if (btnLoading) btnLoading.style.display = 'none';
    }
}

    showSuccessAnimation() {
        const form = document.querySelector(FormConstants.SELECTORS.FORM);
        form.style.transform = 'scale(0.98)';
        
        setTimeout(() => {
            form.style.transform = 'scale(1)';
        }, 300);
    }

    showErrorAnimation() {
        const form = document.querySelector(FormConstants.SELECTORS.FORM);
        form.style.animation = 'shake 0.5s ease-in-out';
        
        setTimeout(() => {
            form.style.animation = '';
        }, 500);
    }

   updateCoordinates(lat, lng) {
    const latInput = document.getElementById('latitud');
    const lngInput = document.getElementById('longitud');
    const latDisplay = document.querySelector(FormConstants.SELECTORS.LAT_DISPLAY);
    const lngDisplay = document.querySelector(FormConstants.SELECTORS.LNG_DISPLAY);
    
    if (latInput && lngInput && latDisplay && lngDisplay) {
        try {
            // 🆕 CONVERTIR A NÚMEROS Y VALIDAR
            const latNum = typeof lat === 'string' ? parseFloat(lat) : lat;
            const lngNum = typeof lng === 'string' ? parseFloat(lng) : lng;
            
            // Validar que sean números válidos
            if (isNaN(latNum) || isNaN(lngNum)) {
                console.error('❌ Coordenadas inválidas:', lat, lng);
                return false;
            }
            
            // Actualizar inputs hidden
            latInput.value = latNum;
            lngInput.value = lngNum;
            
            // Actualizar displays
            latDisplay.textContent = latNum.toFixed(6);
            lngDisplay.textContent = lngNum.toFixed(6);
            
            console.log('✅ Coordenadas actualizadas:', latNum, lngNum);
            
            // 🆕 GUARDAR EN LOCALSTORAGE SOLO PARA PERSISTENCIA ONLINE/OFFLINE
            // No para restaurar automáticamente al cargar la página
            localStorage.setItem('ultimaLatitud', latNum);
            localStorage.setItem('ultimaLongitud', lngNum);
            
            return true;
            
        } catch (error) {
            console.error('❌ Error actualizando coordenadas:', error);
            return false;
        }
    } else {
        console.error('❌ No se encontraron elementos para actualizar coordenadas');
        return false;
    }
}
// En UIManager.js - ELIMINAR este método si no es necesario
// restaurarCoordenadas() {
//     const latGuardada = localStorage.getItem('ultimaLatitud');
//     const lngGuardada = localStorage.getItem('ultimaLongitud');
//     
//     if (latGuardada && lngGuardada) {
//         console.log('🔄 Restaurando coordenadas guardadas:', latGuardada, lngGuardada);
//         this.updateCoordinates(parseFloat(latGuardada), parseFloat(lngGuardada));
//         return true;
//     }
//     console.log('📭 No hay coordenadas guardadas para restaurar');
//     return false;
// }
// En UIManager.js, agrega este método:
setupMapIntegration() {
    // Escuchar eventos de ubicación del mapa
    document.addEventListener('ubicacionSeleccionada', (event) => {
        const { lat, lng } = event.detail;
        this.updateCoordinates(lat, lng);
    });
    
    // También escuchar cambios directos si el mapa los proporciona
    if (window.mapaSistema) {
        // Podrías agregar un listener directo al mapa si es necesario
        console.log('🔗 Integración con mapa configurada');
    }
}
}