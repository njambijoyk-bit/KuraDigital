import React, { useState } from 'react';
import { Dialog, DialogPanel, DialogTitle, DialogBackdrop } from '@headlessui/react';
import { LockClosedIcon, XMarkIcon, ArrowDownTrayIcon } from '@heroicons/react/24/outline';

export default function EncryptedExportModal({ open, onClose, onExport, title = 'Encrypted Export' }) {
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [error, setError] = useState(null);
    const [exporting, setExporting] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (password.length < 8) {
            setError('Password must be at least 8 characters.');
            return;
        }
        if (password !== confirmPassword) {
            setError('Passwords do not match.');
            return;
        }
        setError(null);
        setExporting(true);
        try {
            await onExport(password);
            setPassword('');
            setConfirmPassword('');
            onClose();
        } catch (err) {
            setError(err.message || 'Export failed.');
        }
        setExporting(false);
    };

    return (
        <Dialog open={!!open} onClose={onClose} className="relative z-50">
            <DialogBackdrop className="fixed inset-0 bg-black/30" />
            <div className="fixed inset-0 overflow-y-auto">
                <div className="flex min-h-full items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-md bg-white rounded-xl shadow-xl">
                        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <DialogTitle className="text-lg font-heading font-semibold text-gray-900 flex items-center space-x-2">
                                <LockClosedIcon className="h-5 w-5 text-primary-600" />
                                <span>{title}</span>
                            </DialogTitle>
                            <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                                <XMarkIcon className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
                            <p className="text-sm text-gray-600">
                                This export contains sensitive donor information. It will be encrypted with AES-256
                                using the password you provide below. Share the password separately from the file.
                            </p>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Encryption Password
                                </label>
                                <input
                                    type="password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    className="w-full border rounded-lg px-3 py-2"
                                    minLength={8}
                                    required
                                    autoFocus
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Confirm Password
                                </label>
                                <input
                                    type="password"
                                    value={confirmPassword}
                                    onChange={(e) => setConfirmPassword(e.target.value)}
                                    className="w-full border rounded-lg px-3 py-2"
                                    required
                                />
                            </div>

                            {error && <p className="text-sm text-red-600">{error}</p>}

                            <div className="flex justify-end space-x-3 pt-2">
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="px-4 py-2 text-sm text-gray-700 border rounded-lg hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={exporting}
                                    className="flex items-center space-x-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium disabled:opacity-50"
                                >
                                    <ArrowDownTrayIcon className="h-4 w-4" />
                                    <span>{exporting ? 'Encrypting...' : 'Export Encrypted'}</span>
                                </button>
                            </div>
                        </form>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    );
}
