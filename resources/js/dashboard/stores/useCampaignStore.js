import { create } from 'zustand';
import { api } from './useAuthStore';

const useCampaignStore = create((set, get) => ({
    campaigns: [],
    currentCampaign: null,
    members: [],
    loading: false,
    error: null,

    fetchCampaigns: async () => {
        set({ loading: true });
        try {
            const res = await api.get('/campaigns');
            set({ campaigns: res.data.data, loading: false });
        } catch (err) {
            set({ error: err.response?.data?.message || 'Failed to load campaigns', loading: false });
        }
    },

    fetchCampaign: async (id) => {
        set({ loading: true });
        try {
            const res = await api.get(`/campaigns/${id}`);
            set({ currentCampaign: res.data.data, loading: false });
        } catch (err) {
            set({ error: err.response?.data?.message || 'Failed to load campaign', loading: false });
        }
    },

    createCampaign: async (data) => {
        set({ error: null });
        try {
            const res = await api.post('/campaigns', data);
            set((state) => ({ campaigns: [res.data.data, ...state.campaigns] }));
            return res.data.data;
        } catch (err) {
            set({ error: err.response?.data?.message || 'Failed to create campaign' });
            return null;
        }
    },

    updateCampaign: async (id, data) => {
        try {
            const res = await api.put(`/campaigns/${id}`, data);
            set((state) => ({
                campaigns: state.campaigns.map((c) => (c.id === id ? res.data.data : c)),
                currentCampaign: state.currentCampaign?.id === id ? res.data.data : state.currentCampaign,
            }));
            return res.data.data;
        } catch (err) {
            set({ error: err.response?.data?.message || 'Failed to update campaign' });
            return null;
        }
    },

    deleteCampaign: async (id) => {
        try {
            await api.delete(`/campaigns/${id}`);
            set((state) => ({
                campaigns: state.campaigns.filter((c) => c.id !== id),
                currentCampaign: state.currentCampaign?.id === id ? null : state.currentCampaign,
            }));
            return true;
        } catch {
            return false;
        }
    },

    fetchMembers: async (campaignId) => {
        try {
            const res = await api.get(`/campaigns/${campaignId}/members`);
            set({ members: res.data.data });
        } catch {}
    },

    inviteMember: async (campaignId, data) => {
        try {
            await api.post(`/campaigns/${campaignId}/members`, data);
            await get().fetchMembers(campaignId);
            return true;
        } catch (err) {
            set({ error: err.response?.data?.message || 'Failed to invite member' });
            return false;
        }
    },

    removeMember: async (campaignId, memberId) => {
        try {
            await api.delete(`/campaigns/${campaignId}/members/${memberId}`);
            set((state) => ({ members: state.members.filter((m) => m.pivot?.id !== memberId && m.id !== memberId) }));
            await get().fetchMembers(campaignId);
            return true;
        } catch {
            return false;
        }
    },

    setCurrentCampaign: (campaign) => set({ currentCampaign: campaign }),
}));

export default useCampaignStore;
