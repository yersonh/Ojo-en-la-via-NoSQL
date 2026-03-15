export const FormConstants = {
    // Límites y configuraciones
    MAX_IMAGES: 10,
    MAX_FILE_SIZE: 5 * 1024 * 1024, // 5MB
    MAX_DESCRIPTION_LENGTH: 500,
    MIN_DESCRIPTION_LENGTH: 10,
    
    // Selectores DOM
    SELECTORS: {
        FORM: '#formReporte',
        TIPO: '#tipo',
        DESCRIPCION: '#descripcion',
        FOTO: '#foto',
        PREVIEW_IMG: '#previewImg',
        SIN_IMAGEN: '#sinImagen',
        VIDEO_CAMARA: '#videoCamara',
        CANVAS_CAPTURA: '#canvasCaptura',
        CONTROLES_CAMARA: '#controlesCamara',
        SUBMIT_BTN: '#submitBtn',
        LOADING: '#loading',
        LAT_DISPLAY: '#latDisplay',
        LNG_DISPLAY: '#lngDisplay',
        LATITUD: '#latitud',
        LONGITUD: '#longitud',
        BTN_TOMAR_FOTO: '#btnTomarFoto',
        BTN_SELECCIONAR_ARCHIVO: '#btnSeleccionarArchivo',
        BTN_CAPTURAR: '#btnCapturar',
        BTN_CANCELAR_CAMARA: '#btnCancelarCamara'
    },
    
    // Mensajes
    MESSAGES: {
        SUCCESS: {
            IMAGE_LOADED: '✅ Imagen cargada correctamente',
            IMAGES_LOADED: ' imágenes cargadas correctamente',
            FORM_SUBMITTED: 'Reporte registrado exitosamente'
        },
        ERROR: {
            NO_LOCATION: 'Debe seleccionar una ubicación en el mapa',
            INVALID_TYPE: 'Por favor selecciona un tipo de incidente',
            DESCRIPTION_REQUIRED: 'La descripción es obligatoria',
            DESCRIPTION_TOO_SHORT: 'La descripción debe tener al menos ',
            DESCRIPTION_TOO_LONG: 'La descripción no puede exceder ',
            TOO_MANY_IMAGES: 'Máximo ',
            IMAGES_ALLOWED: ' imágenes permitidas',
            FILE_TOO_LARGE: 'La imagen "',
            EXCEEDS_SIZE: '" supera los 5MB',
            INVALID_FILE: '" no es una imagen válida',
            CAMERA_ERROR: 'No se pudo acceder a la cámara. Verifica los permisos.',
            CAPTURE_ERROR: 'Error al capturar la foto',
            SUBMIT_ERROR: 'Error al enviar el reporte'
        }
    },
    
    // Estilos
    STYLES: {
        SUCCESS_COLOR: '#10b981',
        ERROR_COLOR: '#ef4444',
        WARNING_COLOR: '#f59e0b',
        PRIMARY_COLOR: '#3b82f6'
    }
};

export const AnimationConstants = {
    DURATIONS: {
        SHAKE: 500,
        FADE: 300,
        SCALE: 200,
        CONFIRMATION: 2000
    },
    KEYFRAMES: {
        SHAKE: `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
        `,
        FADE_IN_OUT: `
            @keyframes fadeInOut {
                0%, 100% { opacity: 0; transform: translateY(-10px); }
                50% { opacity: 1; transform: translateY(0); }
            }
        `,
        SLIDE_DOWN: `
            @keyframes slideDown {
                from { transform: translateY(-100%); }
                to { transform: translateY(0); }
            }
        `
    }
};