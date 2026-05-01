import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import {
    PlusIcon,
    TrashIcon,
    PencilIcon,
    UserCircleIcon,
    EnvelopeIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const ROLES = [
    'campaign-owner', 'campaign-director', 'deputy-campaign-director',
    'field-director', 'communications-director', 'digital-director',
    'finance-director', 'strategy-director', 'voter-outreach-director',
    'legal-compliance-officer', 'content-editor', 'field-coordinator',
    'regional-coordinator', 'data-analyst', 'research-officer',
    'finance-officer', 'sms-whatsapp-operator', 'polling-station-agent',
    'campaign-agent', 'volunteer', 'election-observer',
    'coalition-partner', 'donor', 'auditor',
];

export default function TeamPage() {
    const { campaignId } = useParams();
    const [members, setMembers] = useState([]);
    const [invitations, setInvitations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showInvite, setShowInvite] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [inviteForm, setInviteForm] = useState({ email: '', role: 'content-editor', assigned_wards: '' });
    const [editForm, setEditForm] = useState({ role: '', assigned_wards: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [tab, setTab] = useState('members');
    const { can } = useCampaignPermissions();

    const fetch = async () => {
        setLoading(true);
        try {
            const [mRes, iRes] = await Promise.all([
                api.get(`/campaigns/${campaignId}/members`),
                api.get(`/campaigns/${campaignId}/invitations`),
            ]);
            setMembers(mRes.data.data || []);
            setInvitations(iRes.data.invitations || iRes.data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    };

    useEffect(() => { fetch(); }, [campaignId]);

    const handleInvite = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            const payload = {
                email: inviteForm.email,
                role: inviteForm.role,
            };
            if (inviteForm.assigned_wards.trim()) {
                payload.assigned_wards = inviteForm.assigned_wards.split(',').map((w) => w.trim());
            }
            await api.post(`/campaigns/${campaignId}/invite`, payload);
            setShowInvite(false);
            setInviteForm({ email: '', role: 'content-editor', assigned_wards: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to send invite');
        }
        setSubmitting(false);
    };

    const handleUpdate = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            const payload = { role: editForm.role };
            if (editForm.assigned_wards.trim()) {
                payload.assigned_wards = editForm.assigned_wards.split(',').map((w) => w.trim());
            }
            await api.put(`/campaigns/${campaignId}/members/${showEdit.id}`, payload);
            setShowEdit(null);
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to update member');
        }
        setSubmitting(false);
    };

    const handleDeactivate = async (memberId) => {
        if (!confirm('Deactivate this team member?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/members/${memberId}`);
            fetch();
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to deactivate');
        }
    };

    const handleRevokeInvite = async (inviteId) => {
        if (!confirm('Revoke this invitation?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/invitations/${inviteId}`);
            fetch();
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to revoke');
        }
    };

    const openEdit = (member) => {
        setEditForm({
            role: member.roles?.[0]?.name || 'content-editor',
            assigned_wards: (member.pivot?.assigned_wards || []).join(', '),
        });
        setShowEdit(member);
        setError(null);
    };

    const memberColumns = [
        {
            key: 'name', label: 'Name',
            render: (r) => (
                <div className="flex items-center space-x-2">
                    <UserCircleIcon className="h-6 w-6 text-gray-400" />
                    <div>
                        <p className="font-medium text-gray-900">{r.name}</p>
                        <p className="text-xs text-gray-400">{r.email}</p>
                    </div>
                </div>
            ),
        },
        {
            key: 'role', label: 'Role',
            render: (r) => (
                <span className="px-2 py-0.5 bg-primary-50 text-primary-700 text-xs font-medium rounded-full">
                    {r.roles?.[0]?.name || 'No role'}
                </span>
            ),
        },
        {
            key: 'wards', label: 'Assigned Wards',
            render: (r) => {
                const wards = r.pivot?.assigned_wards || [];
                return wards.length > 0
                    ? <span className="text-xs text-gray-500">{wards.join(', ')}</span>
                    : <span className="text-xs text-gray-300">All</span>;
            },
        },
        {
            key: 'actions', label: '',
            render: (r) => (
                <div className="flex items-center space-x-1">
                    {can('team.assign-roles') && <button onClick={() => openEdit(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded transition-colors">
                        <PencilIcon className="h-4 w-4" />
                    </button>}
                    {can('team.deactivate') && <button onClick={() => handleDeactivate(r.id)} className="p-1.5 text-gray-400 hover:text-red-500 rounded transition-colors">
                        <TrashIcon className="h-4 w-4" />
                    </button>}
                </div>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex space-x-1 bg-gray-100 rounded-lg p-0.5">
                    <button
                        onClick={() => setTab('members')}
                        className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${tab === 'members' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'}`}
                    >
                        Members ({members.length})
                    </button>
                    <button
                        onClick={() => setTab('invitations')}
                        className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${tab === 'invitations' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'}`}
                    >
                        Pending Invites ({invitations.length})
                    </button>
                </div>
                <PermissionGate permission="team.invite">
                    <button onClick={() => { setShowInvite(true); setError(null); }} className="btn-primary !py-2 !px-4 text-sm">
                        <PlusIcon className="h-4 w-4 mr-1" />
                        Invite
                    </button>
                </PermissionGate>
            </div>

            {tab === 'members' ? (
                <DataTable columns={memberColumns} data={members} loading={loading} emptyMessage="No team members yet" />
            ) : (
                invitations.length === 0 ? (
                    <EmptyState icon={EnvelopeIcon} title="No pending invitations" />
                ) : (
                    <div className="space-y-2">
                        {invitations.map((inv) => (
                            <div key={inv.id} className="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-3 flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{inv.email}</p>
                                    <p className="text-xs text-gray-400">
                                        Role: <span className="font-medium">{inv.role}</span>
                                        {' · '}Expires {new Date(inv.expires_at).toLocaleDateString('en-KE')}
                                    </p>
                                </div>
                                {can('team.invite') && <button onClick={() => handleRevokeInvite(inv.id)} className="text-red-400 hover:text-red-600 transition-colors">
                                    <TrashIcon className="h-4 w-4" />
                                </button>}
                            </div>
                        ))}
                    </div>
                )
            )}

            {/* Invite Modal */}
            <Modal open={showInvite} onClose={() => setShowInvite(false)} title="Invite Team Member">
                {error && <div className="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
                <form onSubmit={handleInvite} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input
                            type="email"
                            value={inviteForm.email}
                            onChange={(e) => setInviteForm((f) => ({ ...f, email: e.target.value }))}
                            required
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select
                            value={inviteForm.role}
                            onChange={(e) => setInviteForm((f) => ({ ...f, role: e.target.value }))}
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        >
                            {ROLES.map((r) => (
                                <option key={r} value={r}>{r.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Assigned Wards (comma-separated, optional)</label>
                        <input
                            type="text"
                            value={inviteForm.assigned_wards}
                            onChange={(e) => setInviteForm((f) => ({ ...f, assigned_wards: e.target.value }))}
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="kibra, langata, karen"
                        />
                    </div>
                    <div className="flex justify-end space-x-3 pt-2">
                        <button type="button" onClick={() => setShowInvite(false)} className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" disabled={submitting} className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">
                            {submitting ? 'Sending...' : 'Send Invite'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Edit Member Modal */}
            <Modal open={!!showEdit} onClose={() => setShowEdit(null)} title="Edit Team Member">
                {error && <div className="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
                <form onSubmit={handleUpdate} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select
                            value={editForm.role}
                            onChange={(e) => setEditForm((f) => ({ ...f, role: e.target.value }))}
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        >
                            {ROLES.map((r) => (
                                <option key={r} value={r}>{r.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Assigned Wards (comma-separated)</label>
                        <input
                            type="text"
                            value={editForm.assigned_wards}
                            onChange={(e) => setEditForm((f) => ({ ...f, assigned_wards: e.target.value }))}
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div className="flex justify-end space-x-3 pt-2">
                        <button type="button" onClick={() => setShowEdit(null)} className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" disabled={submitting} className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">
                            {submitting ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
