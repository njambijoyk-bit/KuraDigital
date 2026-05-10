import React, { useState } from 'react';
import { Dialog, DialogPanel, DialogTitle, DialogBackdrop } from '@headlessui/react';
import { FingerPrintIcon, ShieldCheckIcon, XMarkIcon } from '@heroicons/react/24/outline';
import useBiometricChallenge from '../hooks/useBiometricChallenge';

export default function BiometricChallengeModal({ open, onClose, onVerified, challengeData }) {
    const { authenticate, authenticateWithMfa, storeBiometricToken, challenging, error } = useBiometricChallenge();
    const [mode, setMode] = useState('biometric');
    const [mfaCode, setMfaCode] = useState('');
    const [mfaError, setMfaError] = useState(null);

    const hasWebAuthn = challengeData?.has_webauthn;
    const hasMfa = challengeData?.mfa_fallback;

    const handleBiometric = async () => {
        const token = await authenticate();
        if (token) {
            storeBiometricToken(token);
            onVerified(token);
            onClose();
        }
    };

    const handleMfa = async (e) => {
        e.preventDefault();
        if (mfaCode.length !== 6) {
            setMfaError('Enter a 6-digit code.');
            return;
        }
        setMfaError(null);
        const token = await authenticateWithMfa(mfaCode);
        if (token) {
            onVerified(token);
            onClose();
        }
    };

    return (
        <Dialog open={!!open} onClose={onClose} className="relative z-50">
            <DialogBackdrop className="fixed inset-0 bg-black/30" />
            <div className="fixed inset-0 overflow-y-auto">
                <div className="flex min-h-full items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-md bg-white rounded-xl shadow-xl">
                        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <DialogTitle className="text-lg font-heading font-semibold text-gray-900">
                                Verify Your Identity
                            </DialogTitle>
                            <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                                <XMarkIcon className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="px-6 py-6 space-y-4">
                            <p className="text-sm text-gray-600">
                                This action requires additional verification. Choose a method below.
                            </p>

                            {mode === 'biometric' && hasWebAuthn && (
                                <div className="space-y-4">
                                    <button
                                        onClick={handleBiometric}
                                        disabled={challenging}
                                        className="w-full flex items-center justify-center space-x-3 py-4 px-4 bg-primary-50 border-2 border-primary-200 rounded-xl hover:bg-primary-100 transition-colors disabled:opacity-50"
                                    >
                                        <FingerPrintIcon className="h-8 w-8 text-primary-600" />
                                        <span className="text-lg font-medium text-primary-700">
                                            {challenging ? 'Waiting for device...' : 'Use Biometric'}
                                        </span>
                                    </button>
                                    {hasMfa && (
                                        <button
                                            onClick={() => setMode('mfa')}
                                            className="w-full text-sm text-gray-500 hover:text-gray-700"
                                        >
                                            Use MFA code instead
                                        </button>
                                    )}
                                </div>
                            )}

                            {(mode === 'mfa' || (!hasWebAuthn && hasMfa)) && (
                                <form onSubmit={handleMfa} className="space-y-4">
                                    <div className="flex items-center justify-center">
                                        <ShieldCheckIcon className="h-12 w-12 text-blue-500" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Enter your 6-digit MFA code
                                        </label>
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            pattern="[0-9]{6}"
                                            maxLength={6}
                                            value={mfaCode}
                                            onChange={(e) => setMfaCode(e.target.value.replace(/\D/g, ''))}
                                            className="w-full border rounded-lg px-4 py-3 text-center text-2xl tracking-widest font-mono"
                                            autoFocus
                                        />
                                    </div>
                                    {mfaError && <p className="text-sm text-red-600">{mfaError}</p>}
                                    <button
                                        type="submit"
                                        className="w-full py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium"
                                    >
                                        Verify
                                    </button>
                                    {hasWebAuthn && (
                                        <button
                                            type="button"
                                            onClick={() => setMode('biometric')}
                                            className="w-full text-sm text-gray-500 hover:text-gray-700"
                                        >
                                            Use biometric instead
                                        </button>
                                    )}
                                </form>
                            )}

                            {!hasWebAuthn && !hasMfa && (
                                <div className="text-center py-4">
                                    <p className="text-sm text-red-600">
                                        No verification methods available. Please set up biometric authentication or MFA in your profile settings.
                                    </p>
                                </div>
                            )}

                            {error && <p className="text-sm text-red-600 text-center">{error}</p>}
                        </div>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    );
}
