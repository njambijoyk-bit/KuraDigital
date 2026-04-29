import { create } from 'zustand';
import api from '../../lib/api';

const useAuthStore = create((set, get) => ({
    user: JSON.parse(localStorage.getItem('kura_user') || 'null'),
    token: localStorage.getItem('kura_token') || null,
    loading: false,
    error: null,

    get isAuthenticated() {
        return !!get().token;
    },

    login: async (email, password) => {
        set({ loading: true, error: null });
        try {
            const { data } = await api.post('/auth/login', { email, password });
            const token = data.data.token;
            const user = data.data.user;
            localStorage.setItem('kura_token', token);
            localStorage.setItem('kura_user', JSON.stringify(user));
            set({ token, user, loading: false });
            return data;
        } catch (err) {
            const msg = err.response?.data?.message || 'Login failed';
            set({ error: msg, loading: false });
            throw err;
        }
    },

    register: async (payload) => {
        set({ loading: true, error: null });
        try {
            const { data } = await api.post('/auth/register', payload);
            const token = data.data.token;
            const user = data.data.user;
            localStorage.setItem('kura_token', token);
            localStorage.setItem('kura_user', JSON.stringify(user));
            set({ token, user, loading: false });
            return data;
        } catch (err) {
            const msg = err.response?.data?.errors
                ? Object.values(err.response.data.errors).flat().join(', ')
                : err.response?.data?.message || 'Registration failed';
            set({ error: msg, loading: false });
            throw err;
        }
    },

    logout: async () => {
        try {
            await api.post('/auth/logout');
        } catch {
            // ignore — token might already be invalid
        }
        localStorage.removeItem('kura_token');
        localStorage.removeItem('kura_user');
        set({ token: null, user: null });
    },

    fetchMe: async () => {
        try {
            const { data } = await api.get('/auth/me');
            const user = data.data;
            localStorage.setItem('kura_user', JSON.stringify(user));
            set({ user });
        } catch {
            // silent — user will be redirected on 401
        }
    },

    clearError: () => set({ error: null }),
}));

export default useAuthStore;
