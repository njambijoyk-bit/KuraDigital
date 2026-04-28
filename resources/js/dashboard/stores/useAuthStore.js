import { create } from 'zustand';
import axios from 'axios';

const api = axios.create({ baseURL: '/api' });

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('kura_token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

const useAuthStore = create((set, get) => ({
    user: null,
    token: localStorage.getItem('kura_token'),
    loading: true,
    error: null,

    setAuth: (user, token) => {
        localStorage.setItem('kura_token', token);
        set({ user, token, error: null });
    },

    register: async (data) => {
        set({ error: null });
        try {
            const res = await api.post('/auth/register', data);
            get().setAuth(res.data.user, res.data.token);
            return true;
        } catch (err) {
            set({ error: err.response?.data?.message || 'Registration failed' });
            return false;
        }
    },

    login: async (email, password) => {
        set({ error: null });
        try {
            const res = await api.post('/auth/login', { email, password });
            get().setAuth(res.data.user, res.data.token);
            return true;
        } catch (err) {
            set({ error: err.response?.data?.message || 'Invalid credentials' });
            return false;
        }
    },

    logout: async () => {
        try {
            await api.post('/auth/logout');
        } catch {}
        localStorage.removeItem('kura_token');
        set({ user: null, token: null });
    },

    fetchUser: async () => {
        const token = localStorage.getItem('kura_token');
        if (!token) {
            set({ loading: false });
            return;
        }
        try {
            const res = await api.get('/auth/me');
            set({ user: res.data.user, loading: false });
        } catch {
            localStorage.removeItem('kura_token');
            set({ user: null, token: null, loading: false });
        }
    },

    isAuthenticated: () => !!get().token && !!get().user,
}));

export { api };
export default useAuthStore;
