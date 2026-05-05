import { create } from 'zustand';
import axios from 'axios';

const useContentStore = create((set) => ({
    manifesto: [],
    events: [],
    news: [],
    gallery: [],
    projects: [],
    loading: {},

    fetchManifesto: async (siteId) => {
        set((s) => ({ loading: { ...s.loading, manifesto: true } }));
        try {
            const { data } = await axios.get(`/api/sites/${siteId}/manifesto`);
            set({ manifesto: data.data, loading: { manifesto: false } });
        } catch {
            set((s) => ({ loading: { ...s.loading, manifesto: false } }));
        }
    },

    fetchEvents: async (siteId) => {
        set((s) => ({ loading: { ...s.loading, events: true } }));
        try {
            const { data } = await axios.get(`/api/sites/${siteId}/events`);
            set({ events: data.data, loading: { events: false } });
        } catch {
            set((s) => ({ loading: { ...s.loading, events: false } }));
        }
    },

    fetchNewsArticle: async (siteId, articleId) => {
        try {
            const { data } = await axios.get(`/api/sites/${siteId}/news/${articleId}`);
            return data.data;
        } catch {
            return null;
        }
    },

    fetchNews: async (siteId) => {
        set((s) => ({ loading: { ...s.loading, news: true } }));
        try {
            const { data } = await axios.get(`/api/sites/${siteId}/news`);
            set({ news: data.data, loading: { news: false } });
        } catch {
            set((s) => ({ loading: { ...s.loading, news: false } }));
        }
    },

    fetchGallery: async (siteId) => {
        set((s) => ({ loading: { ...s.loading, gallery: true } }));
        try {
            const { data } = await axios.get(`/api/sites/${siteId}/gallery`);
            set({ gallery: data.data, loading: { gallery: false } });
        } catch {
            set((s) => ({ loading: { ...s.loading, gallery: false } }));
        }
    },

    fetchProjects: async (siteId) => {
        set((s) => ({ loading: { ...s.loading, projects: true } }));
        try {
            const { data } = await axios.get(`/api/sites/${siteId}/projects`);
            set({ projects: data.data, loading: { projects: false } });
        } catch {
            set((s) => ({ loading: { ...s.loading, projects: false } }));
        }
    },
}));

export default useContentStore;
