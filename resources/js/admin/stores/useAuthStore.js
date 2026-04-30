import { create } from 'zustand';
import api from '../../lib/api';

const useAuthStore = create((set, get) => ({
    user: JSON.parse(localStorage.getItem('kura_user') || 'null'),
    token: localStorage.getItem('kura_token') || null,
    campaigns: JSON.parse(localStorage.getItem('kura_campaigns') || '[]'),
    globalRoles: JSON.parse(localStorage.getItem('kura_global_roles') || '[]'),
    loading: false,
    error: null,

    get isAuthenticated() {
        return !!get().token;
    },

    login: async (email, password) => {
        set({ loading: true, error: null });
        try {
            const { data } = await api.post('/auth/login', { email, password });
            const token = data.token;
            const user = data.user;
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
            const token = data.token;
            const user = data.user;
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
        localStorage.removeItem('kura_campaigns');
        localStorage.removeItem('kura_global_roles');
        set({ token: null, user: null, campaigns: [], globalRoles: [] });
    },

    fetchMe: async () => {
        try {
            const { data } = await api.get('/auth/me');
            const user = data.user;
            const campaigns = data.campaigns || [];
            const globalRoles = data.roles || [];
            localStorage.setItem('kura_user', JSON.stringify(user));
            localStorage.setItem('kura_campaigns', JSON.stringify(campaigns));
            localStorage.setItem('kura_global_roles', JSON.stringify(globalRoles));
            set({ user, campaigns, globalRoles });
        } catch {
            // silent — user will be redirected on 401
        }
    },

    getCampaignMembership: (campaignId) => {
        const id = parseInt(campaignId, 10);
        return get().campaigns.find((c) => c.id === id) || null;
    },

    clearError: () => set({ error: null }),
}));

export default useAuthStore;
