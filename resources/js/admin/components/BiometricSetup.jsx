import React, { useState, useEffect } from 'react';
import { startRegistration } from '@simplewebauthn/browser';
import { FingerPrintIcon, TrashIcon, PencilIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';

export default function BiometricSetup() {
    const [credentials, setCredentials] = useState([]);
    const [loading, setLoading] = useState(true);
    const [registering, setRegistering] = useState(false);
    const [error, setError] = useState(null);
    const [deviceName, setDeviceName] = useState('');
    const [renaming, setRenaming] = useState(null);
    const [renameValue, setRenameValue] = useState('');

    const fetchCredentials = async () => {
        try {
            const { data } = await api.get('/auth/webauthn/credentials');
            setCredentials(data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    };

    useEffect(() => { fetchCredentials(); }, []);

    const handleRegister = async () => {
        setRegistering(true);
        setError(null);
        try {
            const { data: options } = await api.get('/auth/webauthn/register/options');
            const attestation = await startRegistration(options);
            await api.post('/auth/webauthn/register/verify', {
                ...attestation,
                device_name: deviceName || 'My Device',
            });
            setDeviceName('');
            fetchCredentials();
        } catch (err) {
            setError(err.response?.data?.message || err.message || 'Registration failed.');
        }
        setRegistering(false);
    };

    const handleRevoke = async (id) => {
        if (!window.confirm('Revoke this credential? You will not be able to use it for biometric verification.')) return;
        try {
            await api.delete(`/auth/webauthn/credentials/${id}`);
            fetchCredentials();
        } catch { /* handled */ }
    };

    const handleRename = async (id) => {
        if (!renameValue.trim()) return;
        try {
            await api.put(`/auth/webauthn/credentials/${id}`, { device_name: renameValue });
            setRenaming(null);
            setRenameValue('');
            fetchCredentials();
        } catch { /* handled */ }
    };

    if (loading) return <div className="text-center py-6 text-gray-500">Loading...</div>;

    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-lg font-semibold text-gray-900">Biometric Authentication</h3>
                <p className="text-sm text-gray-500 mt-1">
                    Register your fingerprint or Face ID for step-up verification on sensitive finance operations.
                </p>
            </div>

            {credentials.length > 0 && (
                <div className="space-y-2">
                    {credentials.map((cred) => (
                        <div key={cred.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                            <div className="flex items-center space-x-3">
                                <FingerPrintIcon className={`h-5 w-5 ${cred.is_active ? 'text-green-600' : 'text-gray-400'}`} />
                                <div>
                                    {renaming === cred.id ? (
                                        <div className="flex items-center space-x-2">
                                            <input
                                                type="text"
                                                value={renameValue}
                                                onChange={(e) => setRenameValue(e.target.value)}
                                                className="border rounded px-2 py-1 text-sm"
                                                autoFocus
                                                onKeyDown={(e) => e.key === 'Enter' && handleRename(cred.id)}
                                            />
                                            <button onClick={() => handleRename(cred.id)} className="text-xs text-primary-600 hover:underline">Save</button>
                                            <button onClick={() => setRenaming(null)} className="text-xs text-gray-500 hover:underline">Cancel</button>
                                        </div>
                                    ) : (
                                        <>
                                            <p className="text-sm font-medium">{cred.device_name}</p>
                                            <p className="text-xs text-gray-400">
                                                Added {new Date(cred.created_at).toLocaleDateString()}
                                                {cred.last_used_at && ` · Last used ${new Date(cred.last_used_at).toLocaleDateString()}`}
                                            </p>
                                        </>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-center space-x-1">
                                <button
                                    onClick={() => { setRenaming(cred.id); setRenameValue(cred.device_name); }}
                                    className="p-1 text-gray-400 hover:text-gray-600 rounded"
                                >
                                    <PencilIcon className="h-4 w-4" />
                                </button>
                                <button
                                    onClick={() => handleRevoke(cred.id)}
                                    className="p-1 text-red-400 hover:text-red-600 rounded"
                                >
                                    <TrashIcon className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="border-t pt-4 space-y-3">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Device Name</label>
                    <input
                        type="text"
                        value={deviceName}
                        onChange={(e) => setDeviceName(e.target.value)}
                        placeholder="e.g. MacBook Pro, iPhone 15"
                        className="w-full border rounded-lg px-3 py-2 text-sm"
                    />
                </div>
                <button
                    onClick={handleRegister}
                    disabled={registering}
                    className="flex items-center space-x-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium disabled:opacity-50"
                >
                    <FingerPrintIcon className="h-4 w-4" />
                    <span>{registering ? 'Registering...' : 'Register New Device'}</span>
                </button>
                {error && <p className="text-sm text-red-600">{error}</p>}
            </div>
        </div>
    );
}
