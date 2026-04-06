import { FormConstants } from './utils/Constants.js';
import { FormHelpers } from './utils/Helpers.js';

export class CameraManager {
    constructor(formManager, imageManager) {
        this.formManager = formManager;
        this.imageManager = imageManager;
        this.stream = null;
        this.isActive = false;
    }

    initialize() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        const takePhotoBtn = document.querySelector(FormConstants.SELECTORS.BTN_TOMAR_FOTO);
        const captureBtn = document.querySelector(FormConstants.SELECTORS.BTN_CAPTURAR);
        const cancelBtn = document.querySelector(FormConstants.SELECTORS.BTN_CANCELAR_CAMARA);

        if (takePhotoBtn) {
            takePhotoBtn.addEventListener('click', () => this.activateCamera());
        }
        
        if (captureBtn) {
            captureBtn.addEventListener('click', () => this.capturePhoto());
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.deactivateCamera());
        }
    }

    async activateCamera() {
        try {
            console.log('📸 Activando cámara...');
            
            this.showCameraUI();
            
            const constraints = {
                video: { 
                    facingMode: 'environment',
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }, 
                audio: false 
            };
            
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            
            const video = document.querySelector(FormConstants.SELECTORS.VIDEO_CAMARA);
            video.srcObject = this.stream;
            
            await new Promise((resolve) => {
                video.onloadedmetadata = () => {
                    video.play();
                    resolve();
                };
            });
            
            this.isActive = true;
            console.log('✅ Cámara activada correctamente');
            
        } catch (error) {
            console.error('❌ Error al acceder a la cámara:', error);
            this.showCameraError('No se pudo acceder a la cámara. Verifica los permisos.');
            this.deactivateCamera();
        }
    }

    showCameraUI() {
        const takePhotoBtn = document.querySelector(FormConstants.SELECTORS.BTN_TOMAR_FOTO);
        FormHelpers.addTemporaryStyle(
            takePhotoBtn,
            {
                background: FormConstants.STYLES.PRIMARY_COLOR,
                color: 'white'
            },
            2000
        );
        
        document.querySelector(FormConstants.SELECTORS.SIN_IMAGEN).style.display = 'none';
        document.querySelector(FormConstants.SELECTORS.PREVIEW_IMG).style.display = 'none';
        
        const video = document.querySelector(FormConstants.SELECTORS.VIDEO_CAMARA);
        const controls = document.querySelector(FormConstants.SELECTORS.CONTROLES_CAMARA);
        
        FormHelpers.showElement(video);
        FormHelpers.showElement(controls);
    }

    capturePhoto() {
        try {
            const video = document.querySelector(FormConstants.SELECTORS.VIDEO_CAMARA);
            const canvas = document.querySelector(FormConstants.SELECTORS.CANVAS_CAPTURA);
            
            // Efecto de captura
            FormHelpers.addTemporaryStyle(
                video,
                { opacity: '0.7' },
                200
            );
            
            // Configurar canvas
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Dibujar frame actual
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Convertir a blob y procesar
            canvas.toBlob((blob) => {
                this.processCapturedPhoto(blob);
                this.deactivateCamera();
                this.showCaptureConfirmation();
            }, 'image/jpeg', 0.8);
            
        } catch (error) {
            console.error('❌ Error al capturar foto:', error);
            this.formManager.showAlert('Error al capturar la foto', 'error');
        }
    }

    processCapturedPhoto(blob) {
        const archivo = new File([blob], `foto_${Date.now()}.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now()
        });
        
        // Agregar al input file existente
        const fileInput = document.querySelector(FormConstants.SELECTORS.FOTO);
        const dataTransfer = new DataTransfer();
        
        // Mantener archivos existentes
        for (let i = 0; i < fileInput.files.length; i++) {
            dataTransfer.items.add(fileInput.files[i]);
        }
        
        // Agregar nuevo archivo
        dataTransfer.items.add(archivo);
        fileInput.files = dataTransfer.files;
        
        // Disparar evento change para previsualizar
        const event = new Event('change', { bubbles: true });
        fileInput.dispatchEvent(event);
    }

    showCaptureConfirmation() {
        const previewContainer = document.querySelector('.preview');
        const confirmation = document.createElement('div');
        confirmation.className = 'confirmacion-foto';
        confirmation.innerHTML = '✅ Foto capturada';
        confirmation.style.cssText = `
            position: absolute; top: 10px; right: 10px; background: #10b981; 
            color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;
            font-weight: 600; z-index: 10; animation: fadeInOut 2s ease-in-out;
        `;
        
        previewContainer.style.position = 'relative';
        previewContainer.appendChild(confirmation);
        
        setTimeout(() => {
            confirmation.remove();
        }, 2000);
    }

    deactivateCamera() {
        // Detener stream
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        
        this.hideCameraUI();
        this.isActive = false;
    }

    hideCameraUI() {
        const video = document.querySelector(FormConstants.SELECTORS.VIDEO_CAMARA);
        const controls = document.querySelector(FormConstants.SELECTORS.CONTROLES_CAMARA);
        
        FormHelpers.hideElement(video);
        FormHelpers.hideElement(controls);
    }

    showCameraError(message) {
        this.formManager.showAlert(message, 'error');
        
        const takePhotoBtn = document.querySelector(FormConstants.SELECTORS.BTN_TOMAR_FOTO);
        FormHelpers.addTemporaryStyle(
            takePhotoBtn,
            {
                background: FormConstants.STYLES.ERROR_COLOR,
                color: 'white'
            },
            2000
        );
    }

    isCameraActive() {
        return this.isActive;
    }
}