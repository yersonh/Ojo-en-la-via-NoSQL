import { FormConstants } from './utils/Constants.js';
import { ValidationManager } from './ValidationManager.js';
import { UIManager } from './UIManager.js';
import { ImageManager } from './ImageManager.js';
import { CameraManager } from './CameraManager.js';

export class FormManager {
    constructor() {
        this.validationManager = new ValidationManager(this);
        this.uiManager = new UIManager(this);
        this.imageManager = new ImageManager(this);
        this.cameraManager = new CameraManager(this, this.imageManager);

        this.initialized = false;
    }

    initialize() {
        if (this.initialized) return;

        console.log('⚙️ Inicializando FormManager...');

        // Inicializar todos los managers
        this.uiManager.initialize();
        this.validationManager.initialize();
        this.imageManager.initialize();
        this.cameraManager.initialize();

        this.setupFormSubmit();
        this.validationManager.setupCharacterCounter();

        if (window.connectionManager) {
            window.connectionManager.addListener((online) => {
                this.handleConnectionChange(online);
            });
        }

        this.initialized = true;
        console.log('✅ FormManager inicializado correctamente');
    }

    setupFormSubmit() {
        const form = document.querySelector(FormConstants.SELECTORS.FORM);
        if (form) {
            form.addEventListener('submit', (e) => {
                this.handleFormSubmit(e);
            });
        }
    }

    async handleFormSubmit(e) {
        e.preventDefault();

        if (!this.validationManager.validateForm()) {
            console.log('❌ Validación de formulario falló');
            return;
        }

        // Verificación de coordenadas
        if (!this.verificarCoordenadas()) {
            this.handleSubmitError('Por favor, selecciona una ubicación en el mapa');
            return;
        }

        this.uiManager.showLoadingState();

        try {
            console.log('📤 Enviando formulario...');

            const formData = new FormData(e.target);

            const resp = await fetch('../../controllers/reportecontrolador.php?action=registrar', {
                method: 'POST',
                body: formData
            });

            const responseText = await resp.text();
            console.log('📥 Respuesta del servidor:', responseText);

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('❌ Error parseando respuesta:', parseError);
                throw new Error('Respuesta del servidor no válida');
            }

            if (result.success) {

                this.handleSubmitSuccess(result.mensaje);

            } else {
                throw new Error(result.mensaje || result.error || 'Error desconocido');
            }

        } catch (error) {
            console.error('💥 Error en envío:', error);

            const isOffline = !window.connectionManager || !window.connectionManager.getStatus();
            const isNetworkError = error.message.includes('Failed to fetch') ||
                                error.message.includes('NetworkError');

            if (isOffline || isNetworkError) {
                // Estamos offline, guardar localmente
                const timestamp = new Date().getTime();
                this.handleSubmitOfflineSuccess(`offline-${timestamp}`);
            } else {
                this.handleSubmitError(error.message || 'Error al procesar el reporte');
            }
        } finally {
            this.uiManager.hideLoadingState();
        }
    }
    verificarCoordenadas() {
        const latInput = document.getElementById('latitud');
        const lngInput = document.getElementById('longitud');

        if (!latInput || !lngInput) {
            console.error('❌ No se encontraron inputs de coordenadas');
            return false;
        }

        const lat = latInput.value;
        const lng = lngInput.value;

        if (!lat || !lng || lat === '' || lng === '') {
            console.error('❌ Coordenadas vacías:', { lat, lng });
            return false;
        }

        if (lat === 'No seleccionada' || lng === 'No seleccionada') {
            console.error('❌ Coordenadas no seleccionadas');
            return false;
        }

        console.log('✅ Coordenadas válidas:', lat, lng);
        return true;
    }

    handleSubmitOfflineSuccess(idOffline) {
        const mensaje = `✅ Reporte guardado localmente (ID: ${idOffline}). Se enviará automáticamente cuando recuperes conexión.`;

        console.log('💾 Reporte offline guardado exitosamente');

        this.showAlert(mensaje, 'success');

        // Agregar marker al mapa
        this.agregarMarkerOfflineAlMapa(idOffline);

        // Limpiar formulario
        setTimeout(() => {
            this.clearForm();
        }, 2000);
    }

    async agregarMarkerOfflineAlMapa(idOffline) {
        if (!window.mapaSistema || !window.mapaSistema.markerManager) {
            console.log('❌ No se puede agregar marker - mapa o markerManager no disponible');
            return;
        }

        try {
            console.log('📍 Intentando agregar marker offline al mapa:', idOffline);

            // Agregar marker con datos actuales del formulario
            const lat = document.getElementById('latitud').value;
            const lng = document.getElementById('longitud').value;
            const tipo = document.getElementById('tipo');
            const descripcion = document.getElementById('descripcion').value;

            if (lat && lng) {
                window.mapaSistema.markerManager.agregarMarkerOffline({
                    id: idOffline,
                    latitud: parseFloat(lat),
                    longitud: parseFloat(lng),
                    tipo_incidente: tipo.options[tipo.selectedIndex]?.text || 'Desconocido',
                    descripcion: descripcion,
                    fecha: new Date().toISOString()
                });

                console.log('✅ Marker offline agregado al mapa');
            }

        } catch (error) {
            console.error('❌ Error agregando marker offline:', error);
        }
    }

    handleSubmitSuccess(message) {
        this.showAlert('✅ ' + message);
        this.clearForm();
        this.clearTemporaryMarker();
        this.reloadMapReports();
        this.uiManager.showSuccessAnimation();
    }

    handleSubmitError(message) {
        this.showAlert('❌ ' + message, 'error');
        this.uiManager.showErrorAnimation();
    }

    clearForm() {
        const form = document.querySelector(FormConstants.SELECTORS.FORM);
        if (form) form.reset();

        this.imageManager.clearImages();

        const latDisplay = document.querySelector(FormConstants.SELECTORS.LAT_DISPLAY);
        const lngDisplay = document.querySelector(FormConstants.SELECTORS.LNG_DISPLAY);

        if (latDisplay) latDisplay.textContent = 'No seleccionada';
        if (lngDisplay) lngDisplay.textContent = 'No seleccionada';

        document.getElementById('latitud').value = '';
        document.getElementById('longitud').value = '';

        this.validationManager.clearAllErrors();
        this.cameraManager.deactivateCamera();

        console.log('🧹 Formulario limpiado');
    }

    clearTemporaryMarker() {
        if (window.mapaSistema && window.mapaSistema.mapaManager) {
            window.mapaSistema.mapaManager.limpiarMarcadorTemporal();
        }
    }

    async reloadMapReports() {
        if (window.mapaSistema && typeof window.mapaSistema.recargarReportes === 'function') {
            await window.mapaSistema.recargarReportes();
        }
    }

    showAlert(message, type = 'success') {
        this.uiManager.showAlert(message, type);
    }

    updateCoordinates(lat, lng) {
        this.uiManager.updateCoordinates(lat, lng);
    }

    handleConnectionChange(online) {
        if (online) {
            this.updateOnlineUI();
        } else {
            this.updateOfflineUI();
        }

        const searchBtn = document.getElementById('btnBuscar');
        if (searchBtn) {
            searchBtn.disabled = !online;
            if (!online) {
                searchBtn.innerHTML = '🔍 Offline';
                searchBtn.title = 'La búsqueda no está disponible sin conexión';
            } else {
                searchBtn.innerHTML = 'Buscar';
                searchBtn.title = '';
            }
        }

        const panel = document.getElementById('panel');
        if (panel) {
            if (!online) {
                panel.classList.add('offline-mode');
            } else {
                panel.classList.remove('offline-mode');
            }
        }
    }

    updateOfflineUI() {
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false; // IMPORTANTE: NO DESHABILITAR EN OFFLINE
            submitBtn.innerHTML = '<span class="btn-text">💾 Guardar Localmente</span><span class="btn-loading" style="display: none;"><div class="spinner-mini"></div> Guardando...</span>';
            submitBtn.title = 'El reporte se guardará localmente y se enviará automáticamente cuando recuperes conexión';
            submitBtn.classList.add('offline-submit');
        }
    }

    updateOnlineUI() {
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span class="btn-text">📝 Registrar Reporte</span><span class="btn-loading" style="display: none;"><div class="spinner-mini"></div> Procesando...</span>';
            submitBtn.title = '';
            submitBtn.classList.remove('offline-submit');
        }
    }

    // Método para limpieza global
    limpiarFormulario() {
        this.clearForm();
    }
}
export default FormManager;