import FormManager from './FormManager.js';
import { ValidationManager } from './ValidationManager.js';
import { UIManager } from './UIManager.js';
import { ImageManager } from './ImageManager.js';
import { CameraManager } from './CameraManager.js';

export { FormManager, ValidationManager, UIManager, ImageManager, CameraManager };

export class SistemaFormulario {
    constructor() {
        this.formManager = new FormManager();
        this.initialized = false;
    }

    initialize() {
        if (this.initialized) return;

        try {
            this.formManager.initialize();
            this.initialized = true;
            console.log('🎉 Sistema de formularios completamente inicializado');
            return this;
        } catch (error) {
            console.error('❌ Error al inicializar sistema de formularios:', error);
            throw error;
        }
    }

    getFormManager() {
        return this.formManager;
    }

    updateCoordinates(lat, lng) {
        this.formManager.updateCoordinates(lat, lng);
    }

    clearForm() {
        this.formManager.clearForm();
    }

    handleConnectionChange(online) {
        this.formManager.handleConnectionChange(online);
    }

    limpiarFormulario() {
        this.formManager.limpiarFormulario();
    }
}

export const formularioSistema = new SistemaFormulario();

// Exportar para uso tradicional (backward compatibility)
export const FormularioManager = formularioSistema;