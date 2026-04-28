import { create } from 'zustand';
import axios from 'axios';

const useSiteStore = create((set, get) => ({
    site: null,
    loading: true,
    error: null,
    language: 'en',

    fetchSite: async (slug) => {
        set({ loading: true, error: null });
        try {
            const { data } = await axios.get(`/api/sites/${slug}`);
            set({ site: data.data, loading: false });
        } catch (err) {
            set({ error: err.response?.data?.message || 'Site not found', loading: false });
        }
    },

    toggleLanguage: () => {
        set((state) => ({ language: state.language === 'en' ? 'sw' : 'en' }));
    },

    getTranslation: (field) => {
        const { site, language } = get();
        if (!site) return '';
        if (language === 'sw' && site[`${field}_sw`]) return site[`${field}_sw`];
        return site[field] || '';
    },
}));

export default useSiteStore;
