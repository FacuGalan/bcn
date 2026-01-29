/**
 * PWA Install Manager
 * Maneja la instalación de la PWA en diferentes plataformas
 */

class PWAInstallManager {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.isIOS = false;
        this.isAndroid = false;
        this.isStandalone = false;
        this.canPrompt = false;

        this.init();
    }

    init() {
        // Detectar plataforma
        this.detectPlatform();

        // Detectar si ya está instalado (modo standalone)
        this.isStandalone = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;

        // Escuchar evento beforeinstallprompt (Chrome, Edge, etc.)
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.canPrompt = true;
            this.notifyStateChange();
        });

        // Escuchar cuando la app es instalada
        window.addEventListener('appinstalled', () => {
            this.isInstalled = true;
            this.isStandalone = true;
            this.deferredPrompt = null;
            this.canPrompt = false;
            this.notifyStateChange();
        });

        // Notificar estado inicial después de un breve delay para asegurar que Alpine esté listo
        setTimeout(() => this.notifyStateChange(), 100);
    }

    /**
     * Notifica a los componentes que el estado ha cambiado
     */
    notifyStateChange() {
        window.dispatchEvent(new CustomEvent('pwa-install-state-changed', {
            detail: this.getStatus()
        }));
    }

    detectPlatform() {
        const ua = navigator.userAgent || navigator.vendor || window.opera;

        // Detectar iOS
        this.isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;

        // Detectar Android
        this.isAndroid = /android/i.test(ua);
    }

    /**
     * Verifica si se puede mostrar el botón de instalación
     */
    canShowInstallButton() {
        // Si ya está en modo standalone, no mostrar
        if (this.isStandalone) {
            return false;
        }

        // Si es iOS, siempre mostrar (para mostrar instrucciones)
        if (this.isIOS) {
            return true;
        }

        // Para otros navegadores, mostrar solo si hay prompt disponible
        return this.deferredPrompt !== null;
    }

    /**
     * Actualiza la visibilidad de los botones de instalación
     */
    updateInstallButtons(showPrompt) {
        const buttons = document.querySelectorAll('[data-pwa-install-button]');
        const containers = document.querySelectorAll('[data-pwa-install-container]');

        const shouldShow = this.canShowInstallButton() || showPrompt || this.isIOS;

        containers.forEach(container => {
            if (shouldShow && !this.isStandalone) {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        });
    }

    /**
     * Intenta instalar la PWA
     * Para iOS, muestra el modal con instrucciones
     */
    async install() {
        if (this.isIOS) {
            this.showIOSModal();
            return;
        }

        if (!this.deferredPrompt) {
            console.log('No hay prompt de instalación disponible');
            return;
        }

        try {
            // Mostrar prompt de instalación (sin overlay todavía)
            this.deferredPrompt.prompt();

            // Esperar respuesta del usuario
            const { outcome } = await this.deferredPrompt.userChoice;

            if (outcome === 'accepted') {
                console.log('Usuario aceptó instalar la PWA');
                // Mostrar overlay DESPUÉS de que el usuario aceptó
                this.showInstallingState();
            } else {
                console.log('Usuario rechazó instalar la PWA');
            }

            // Limpiar el prompt
            this.deferredPrompt = null;
            this.updateInstallButtons(false);
        } catch (error) {
            console.error('Error al instalar PWA:', error);
            this.hideInstallingState();
        }
    }

    /**
     * Muestra el estado de instalación en progreso
     */
    showInstallingState() {
        window.dispatchEvent(new CustomEvent('pwa-installing'));
    }

    /**
     * Oculta el estado de instalación
     */
    hideInstallingState() {
        window.dispatchEvent(new CustomEvent('pwa-install-cancelled'));
    }

    /**
     * Muestra el estado de instalación completada y redirige
     */
    showInstalledState() {
        window.dispatchEvent(new CustomEvent('pwa-installed'));
    }

    /**
     * Muestra el modal con instrucciones para iOS
     */
    showIOSModal() {
        window.dispatchEvent(new CustomEvent('show-ios-install-modal'));
    }

    /**
     * Cierra el modal de iOS
     */
    closeIOSModal() {
        window.dispatchEvent(new CustomEvent('close-ios-install-modal'));
    }

    /**
     * Obtiene información sobre el estado de instalación
     */
    getStatus() {
        return {
            isInstalled: this.isInstalled,
            isStandalone: this.isStandalone,
            isIOS: this.isIOS,
            isAndroid: this.isAndroid,
            canInstall: this.canShowInstallButton(),
            hasPrompt: this.deferredPrompt !== null
        };
    }
}

// Crear instancia global
window.pwaInstallManager = new PWAInstallManager();

// Exponer función global para instalar
window.installPWA = function() {
    window.pwaInstallManager.install();
};

// Exponer función para verificar si se puede instalar
window.canInstallPWA = function() {
    return window.pwaInstallManager.canShowInstallButton();
};

// Exponer función para verificar si es iOS
window.isIOSDevice = function() {
    return window.pwaInstallManager.isIOS;
};

// Exponer función para verificar si está en modo standalone
window.isPWAStandalone = function() {
    return window.pwaInstallManager.isStandalone;
};
