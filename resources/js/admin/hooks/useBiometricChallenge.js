import { useState, useCallback } from 'react';
import { startAuthentication } from '@simplewebauthn/browser';
import api from '../../lib/api';

export default function useBiometricChallenge() {
    const [challenging, setChallenging] = useState(false);
    const [error, setError] = useState(null);

    const authenticate = useCallback(async () => {
        setChallenging(true);
        setError(null);
        try {
            const { data: options } = await api.get('/auth/webauthn/authenticate/options');
            const assertion = await startAuthentication(options);
            const { data } = await api.post('/auth/webauthn/authenticate/verify', assertion);
            setChallenging(false);
            return data.biometric_token;
        } catch (err) {
            const msg = err.response?.data?.message || err.message || 'Biometric authentication failed.';
            setError(msg);
            setChallenging(false);
            return null;
        }
    }, []);

    const authenticateWithMfa = useCallback(async (code) => {
        setError(null);
        return code;
    }, []);

    const callWithBiometric = useCallback(async (method, url, body = null) => {
        try {
            const config = {};
            const biometricToken = localStorage.getItem('kura_biometric_token');
            if (biometricToken) {
                config.headers = { 'X-Biometric-Token': biometricToken };
            }

            const res = method === 'get'
                ? await api.get(url, config)
                : method === 'put'
                    ? await api.put(url, body, config)
                    : method === 'delete'
                        ? await api.delete(url, config)
                        : await api.post(url, body, config);

            return { success: true, data: res.data };
        } catch (err) {
            if (err.response?.status === 428 && err.response?.data?.biometric_required) {
                return { success: false, biometricRequired: true, challengeData: err.response.data };
            }
            return { success: false, error: err.response?.data?.message || 'Request failed.' };
        }
    }, []);

    const storeBiometricToken = useCallback((token) => {
        localStorage.setItem('kura_biometric_token', token);
    }, []);

    return { authenticate, authenticateWithMfa, callWithBiometric, storeBiometricToken, challenging, error };
}
