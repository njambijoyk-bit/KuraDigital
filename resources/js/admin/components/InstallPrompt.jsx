import React, { useState, useEffect } from 'react';
import { DevicePhoneMobileIcon, XMarkIcon } from '@heroicons/react/24/outline';

export default function InstallPrompt() {
    const [deferredPrompt, setDeferredPrompt] = useState(null);
    const [dismissed, setDismissed] = useState(
        () => localStorage.getItem('kura_install_dismissed') === 'true'
    );
    const [isInstalled, setIsInstalled] = useState(false);

    useEffect(() => {
        // Check if already installed
        if (window.matchMedia('(display-mode: standalone)').matches) {
            setIsInstalled(true);
            return;
        }

        const handler = (e) => {
            e.preventDefault();
            setDeferredPrompt(e);
        };

        window.addEventListener('beforeinstallprompt', handler);

        window.addEventListener('appinstalled', () => {
            setIsInstalled(true);
            setDeferredPrompt(null);
        });

        return () => window.removeEventListener('beforeinstallprompt', handler);
    }, []);

    const handleInstall = async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        if (outcome === 'accepted') {
            setDeferredPrompt(null);
        }
    };

    const handleDismiss = () => {
        setDismissed(true);
        localStorage.setItem('kura_install_dismissed', 'true');
    };

    if (isInstalled || dismissed || !deferredPrompt) return null;

    return (
        <div className="fixed bottom-4 left-4 right-4 md:left-auto md:right-4 md:max-w-sm bg-white rounded-xl shadow-lg border border-gray-100 p-4 z-50">
            <div className="flex items-start gap-3">
                <div className="p-2 bg-primary-50 rounded-lg flex-shrink-0">
                    <DevicePhoneMobileIcon className="h-6 w-6 text-primary-600" />
                </div>
                <div className="flex-1">
                    <h3 className="text-sm font-semibold text-gray-900">Install KuraDigital</h3>
                    <p className="text-xs text-gray-500 mt-0.5">
                        Add to your home screen for quick access and offline support
                    </p>
                    <div className="flex gap-2 mt-3">
                        <button
                            onClick={handleInstall}
                            className="px-3 py-1.5 bg-primary-600 text-white text-xs font-medium rounded-lg hover:bg-primary-700"
                        >
                            Install
                        </button>
                        <button
                            onClick={handleDismiss}
                            className="px-3 py-1.5 text-gray-500 text-xs font-medium hover:text-gray-700"
                        >
                            Not now
                        </button>
                    </div>
                </div>
                <button onClick={handleDismiss} className="text-gray-400 hover:text-gray-600">
                    <XMarkIcon className="h-5 w-5" />
                </button>
            </div>
        </div>
    );
}
