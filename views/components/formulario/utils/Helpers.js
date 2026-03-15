import { FormConstants, AnimationConstants } from './Constants.js';

export class FormHelpers {
    static injectStyles() {
        if (document.getElementById('form-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'form-styles';
        style.textContent = `
            ${AnimationConstants.KEYFRAMES.SHAKE}
            ${AnimationConstants.KEYFRAMES.FADE_IN_OUT}
            ${AnimationConstants.KEYFRAMES.SLIDE_DOWN}
            
            .spinner-mini {
                width: 16px; height: 16px; border: 2px solid transparent;
                border-top: 2px solid white; border-radius: 50%;
                animation: spin 1s linear infinite; display: inline-block;
                margin-right: 8px;
            }
            
            .formulario-moderno {
                transition: all 0.3s ease;
            }
            
            .campo-error {
                border-color: ${FormConstants.STYLES.ERROR_COLOR} !important;
                background-color: #fef2f2 !important;
            }
            
            .campo-exito {
                border-color: ${FormConstants.STYLES.SUCCESS_COLOR} !important;
                background-color: #f0fdf4 !important;
            }
            
            .campo-focus {
                border-color: ${FormConstants.STYLES.PRIMARY_COLOR} !important;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            
            .mensaje-error {
                color: ${FormConstants.STYLES.ERROR_COLOR};
                font-size: 12px;
                margin-top: 4px;
                font-weight: 500;
            }
            
            .contador-caracteres {
                font-size: 12px;
                color: #6b7280;
                text-align: right;
                margin-top: 4px;
                transition: color 0.3s ease;
            }
        `;
        document.head.appendChild(style);
    }

    static createLoadingSpinner() {
        const spinner = document.createElement('div');
        spinner.className = 'spinner-mini';
        return spinner;
    }

    static showElement(element, withAnimation = true) {
        if (withAnimation) {
            element.style.display = 'block';
            element.style.opacity = '0';
            element.style.transform = 'scale(0.8)';
            
            setTimeout(() => {
                element.style.opacity = '1';
                element.style.transform = 'scale(1)';
            }, 10);
        } else {
            element.style.display = 'block';
        }
    }

    static hideElement(element, withAnimation = true) {
        if (withAnimation) {
            element.style.opacity = '0';
            element.style.transform = 'scale(0.8)';
            
            setTimeout(() => {
                element.style.display = 'none';
                element.style.opacity = '1';
                element.style.transform = 'scale(1)';
            }, 300);
        } else {
            element.style.display = 'none';
        }
    }

    static addTemporaryStyle(element, styles, duration = 2000) {
        const originalStyles = {};
        
        Object.keys(styles).forEach(property => {
            originalStyles[property] = element.style[property];
            element.style[property] = styles[property];
        });
        
        setTimeout(() => {
            Object.keys(styles).forEach(property => {
                element.style[property] = originalStyles[property];
            });
        }, duration);
    }

    static formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    static isValidImageFile(file) {
        return file && file.type.startsWith('image/');
    }

    static isFileSizeValid(file) {
        return file.size <= FormConstants.MAX_FILE_SIZE;
    }
}