import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    CheckIcon,
    XMarkIcon,
    ArrowDownTrayIcon,
    BanknotesIcon,
    CurrencyDollarIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const CATEGORIES = ['operations', 'media', 'events', 'field', 'personnel', 'logistics', 'other'];
const PAYMENT_METHODS = ['cash', 'mpesa', 'bank_transfer', 'cheque'];
const DONATION_CHANNELS = ['mpesa', 'bank_transfer', 'cash', 'online'];
const BUDGET_PERIODS = ['monthly', 'quarterly', 'total'];

const statusColors = {
    pending: 'bg-yellow-50 text-yellow-800',
    approved: 'bg-green-50 text-green-800',
    rejected: 'bg-red-50 text-red-800',
    completed: 'bg-green-50 text-green-800',
    failed: 'bg-red-50 text-red-800',
};

const categoryColors = {
    operations: 'bg-blue-50 text-blue-700',
    media: 'bg-purple-50 text-purple-700',
    events: 'bg-orange-50 text-orange-700',
    field: 'bg-green-50 text-green-700',
    personnel: 'bg-indigo-50 text-indigo-700',
    logistics: 'bg-teal-50 text-teal-700',
    other: 'bg-gray-100 text-gray-700',
};

function formatCurrency(amount, currency = 'KES') {
    return `${currency} ${Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;
}

export default function FinancePage() {
    const { campaignId } = useParams();
    const [tab, setTab] = useState('summary');
    const { can } = useCampaignPermissions();

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-heading font-bold text-gray-900">Finance</h1>
            </div>

            <div className="border-b border-gray-200">
                <nav className="flex space-x-8">
                    {[
                        { id: 'summary', label: 'Summary', perm: 'finance.view' },
                        { id: 'expenses', label: 'Expenses', perm: 'finance.view' },
                        { id: 'budgets', label: 'Budgets', perm: 'finance.view-budget' },
                        { id: 'donations', label: 'Donations', perm: 'finance.view-donations' },
                    ].filter((t) => can(t.perm)).map((t) => (
                        <button key={t.id} onClick={() => setTab(t.id)}
                            className={`py-3 px-1 border-b-2 text-sm font-medium ${tab === t.id ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {tab === 'summary' && <SummaryTab campaignId={campaignId} />}
            {tab === 'expenses' && <ExpensesTab campaignId={campaignId} />}
            {tab === 'budgets' && <BudgetsTab campaignId={campaignId} />}
            {tab === 'donations' && <DonationsTab campaignId={campaignId} />}
        </div>
    );
}

// =====================================================================
// Summary Tab
// =====================================================================

function SummaryTab({ campaignId }) {
    const [summary, setSummary] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        (async () => {
            try {
                const { data } = await api.get(`/campaigns/${campaignId}/finance/summary`);
                setSummary(data);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [campaignId]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!summary) return <EmptyState icon={BanknotesIcon} title="No financial data" description="Start by creating budgets and logging expenses." />;

    const s = summary.summary;

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard label="Total Budget" value={formatCurrency(s.total_budget)} color="blue" />
                <StatCard label="Total Spent" value={formatCurrency(s.total_spent)} color="red" />
                <StatCard label="Budget Remaining" value={formatCurrency(s.budget_remaining)} color="green" />
                <StatCard label="Pending Approvals" value={formatCurrency(s.pending_expenses)} color="yellow" />
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <StatCard label="Total Donations" value={formatCurrency(s.total_donations)} color="emerald" />
                <StatCard label="Donation Count" value={s.donation_count} color="teal" />
                <StatCard label="Net Position" value={formatCurrency(s.net_position)} color={s.net_position >= 0 ? 'green' : 'red'} />
            </div>

            {summary.budgets_by_category && Object.keys(summary.budgets_by_category).length > 0 && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 className="text-lg font-semibold mb-4">Budget by Category</h3>
                    <div className="space-y-3">
                        {Object.entries(summary.budgets_by_category).map(([cat, data]) => {
                            const pct = data.allocated > 0 ? Math.round((data.spent / data.allocated) * 100) : 0;
                            return (
                                <div key={cat}>
                                    <div className="flex justify-between text-sm mb-1">
                                        <span className="capitalize font-medium">{cat}</span>
                                        <span className="text-gray-500">{formatCurrency(data.spent)} / {formatCurrency(data.allocated)} ({pct}%)</span>
                                    </div>
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div className={`h-2 rounded-full ${pct > 90 ? 'bg-red-500' : pct > 70 ? 'bg-yellow-500' : 'bg-green-500'}`} style={{ width: `${Math.min(pct, 100)}%` }} />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}

function StatCard({ label, value, color }) {
    const colorClasses = {
        blue: 'bg-blue-50 text-blue-900',
        red: 'bg-red-50 text-red-900',
        green: 'bg-green-50 text-green-900',
        yellow: 'bg-yellow-50 text-yellow-900',
        emerald: 'bg-emerald-50 text-emerald-900',
        teal: 'bg-teal-50 text-teal-900',
    };
    return (
        <div className={`rounded-xl p-5 ${colorClasses[color] || 'bg-gray-50 text-gray-900'}`}>
            <p className="text-sm font-medium opacity-75">{label}</p>
            <p className="text-2xl font-bold mt-1">{value}</p>
        </div>
    );
}

// =====================================================================
// Expenses Tab
// =====================================================================

function ExpensesTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [form, setForm] = useState({ title: '', description: '', amount: '', category: 'operations', payment_method: 'cash', reference: '', vendor: '', expense_date: '', budget_id: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});
    const [rejectModal, setRejectModal] = useState(null);
    const [rejectReason, setRejectReason] = useState('');
    const { can } = useCampaignPermissions();

    const fetch = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (statusFilter) params.status = statusFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/finance/expenses`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, statusFilter, page]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            const payload = { ...form };
            if (!payload.budget_id) delete payload.budget_id;
            if (showEdit) {
                await api.put(`/campaigns/${campaignId}/finance/expenses/${showEdit.id}`, payload);
                setShowEdit(null);
            } else {
                await api.post(`/campaigns/${campaignId}/finance/expenses`, payload);
                setShowCreate(false);
            }
            setForm({ title: '', description: '', amount: '', category: 'operations', payment_method: 'cash', reference: '', vendor: '', expense_date: '', budget_id: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleApprove = async (id) => {
        if (!window.confirm('Approve this expense?')) return;
        try {
            await api.post(`/campaigns/${campaignId}/finance/expenses/${id}/approve`);
            fetch();
        } catch { /* handled */ }
    };

    const handleReject = async () => {
        if (!rejectReason.trim()) return;
        try {
            await api.post(`/campaigns/${campaignId}/finance/expenses/${rejectModal}/reject`, { rejection_reason: rejectReason });
            setRejectModal(null);
            setRejectReason('');
            fetch();
        } catch { /* handled */ }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this expense?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/finance/expenses/${id}`);
            fetch();
        } catch { /* handled */ }
    };

    const columns = [
        { key: 'title', label: 'Title' },
        { key: 'amount', label: 'Amount', render: (row) => formatCurrency(row.amount) },
        { key: 'category', label: 'Category', render: (row) => <span className={`px-2 py-0.5 rounded text-xs font-medium ${categoryColors[row.category] || ''}`}>{row.category}</span> },
        { key: 'status', label: 'Status', render: (row) => <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusColors[row.status] || ''}`}>{row.status}</span> },
        { key: 'expense_date', label: 'Date', render: (row) => row.expense_date?.split('T')[0] },
        { key: 'vendor', label: 'Vendor' },
        { key: 'creator', label: 'Logged By', render: (row) => row.creator?.name || '' },
    ];

    const actions = (row) => (
        <div className="flex space-x-1">
            {row.status === 'pending' && can('finance.approve-expense') && (
                <>
                    <button onClick={() => handleApprove(row.id)} className="p-1 text-green-600 hover:bg-green-50 rounded" title="Approve"><CheckIcon className="h-4 w-4" /></button>
                    <button onClick={() => { setRejectModal(row.id); setRejectReason(''); }} className="p-1 text-red-600 hover:bg-red-50 rounded" title="Reject"><XMarkIcon className="h-4 w-4" /></button>
                </>
            )}
            {row.status === 'pending' && can('finance.edit') && (
                <button onClick={() => { setShowEdit(row); setForm({ title: row.title, description: row.description || '', amount: row.amount, category: row.category, payment_method: row.payment_method, reference: row.reference || '', vendor: row.vendor || '', expense_date: row.expense_date?.split('T')[0] || '', budget_id: row.budget_id || '' }); }} className="p-1 text-blue-600 hover:bg-blue-50 rounded"><PencilIcon className="h-4 w-4" /></button>
            )}
            {row.status !== 'approved' && can('finance.edit') && (
                <button onClick={() => handleDelete(row.id)} className="p-1 text-red-600 hover:bg-red-50 rounded"><TrashIcon className="h-4 w-4" /></button>
            )}
        </div>
    );

    const formFields = (
        <>
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Amount (KES) *</label>
                    <input type="number" step="0.01" min="0.01" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                        {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <select value={form.payment_method} onChange={(e) => setForm({ ...form, payment_method: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                        {PAYMENT_METHODS.map((m) => <option key={m} value={m}>{m.replace('_', ' ')}</option>)}
                    </select>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                    <input type="date" value={form.expense_date} onChange={(e) => setForm({ ...form, expense_date: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Vendor</label>
                    <input type="text" value={form.vendor} onChange={(e) => setForm({ ...form, vendor: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                </div>
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Reference / Receipt No.</label>
                <input type="text" value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={3} className="w-full border rounded-lg px-3 py-2" />
            </div>
        </>
    );

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <div className="relative">
                        <MagnifyingGlassIcon className="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input type="text" placeholder="Search expenses..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} className="pl-9 pr-3 py-2 border rounded-lg text-sm w-64" />
                    </div>
                    <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div className="flex space-x-2">
                    <PermissionGate permission="finance.export">
                        <button onClick={() => window.open(`/api/v1/campaigns/${campaignId}/finance/expenses/export`)} className="flex items-center space-x-1 px-3 py-2 border rounded-lg text-sm hover:bg-gray-50">
                            <ArrowDownTrayIcon className="h-4 w-4" /><span>Export</span>
                        </button>
                    </PermissionGate>
                    <PermissionGate permission="finance.log-expense">
                        <button onClick={() => { setShowCreate(true); setForm({ title: '', description: '', amount: '', category: 'operations', payment_method: 'cash', reference: '', vendor: '', expense_date: '', budget_id: '' }); }} className="flex items-center space-x-1 bg-primary-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-primary-700">
                            <PlusIcon className="h-4 w-4" /><span>Log Expense</span>
                        </button>
                    </PermissionGate>
                </div>
            </div>

            <DataTable columns={columns} data={items} loading={loading} actions={actions} meta={meta} onPageChange={(pg) => { setPage(pg); fetch(pg); }} />

            <Modal isOpen={showCreate || !!showEdit} onClose={() => { setShowCreate(false); setShowEdit(null); }} title={showEdit ? 'Edit Expense' : 'Log Expense'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="text-red-600 text-sm">{error}</div>}
                    {formFields}
                    <div className="flex justify-end space-x-2">
                        <button type="button" onClick={() => { setShowCreate(false); setShowEdit(null); }} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">{submitting ? 'Saving...' : 'Save'}</button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={!!rejectModal} onClose={() => setRejectModal(null)} title="Reject Expense">
                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Reason for rejection *</label>
                        <textarea value={rejectReason} onChange={(e) => setRejectReason(e.target.value)} rows={3} className="w-full border rounded-lg px-3 py-2" required />
                    </div>
                    <div className="flex justify-end space-x-2">
                        <button onClick={() => setRejectModal(null)} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button onClick={handleReject} disabled={!rejectReason.trim()} className="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">Reject</button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

// =====================================================================
// Budgets Tab
// =====================================================================

function BudgetsTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [form, setForm] = useState({ name: '', category: 'operations', allocated_amount: '', period: 'total', start_date: '', end_date: '', notes: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const { can } = useCampaignPermissions();

    const fetch = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/finance/budgets`);
            setItems(data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            if (showEdit) {
                await api.put(`/campaigns/${campaignId}/finance/budgets/${showEdit.id}`, form);
                setShowEdit(null);
            } else {
                await api.post(`/campaigns/${campaignId}/finance/budgets`, form);
                setShowCreate(false);
            }
            setForm({ name: '', category: 'operations', allocated_amount: '', period: 'total', start_date: '', end_date: '', notes: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this budget?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/finance/budgets/${id}`);
            fetch();
        } catch { /* handled */ }
    };

    const columns = [
        { key: 'name', label: 'Name' },
        { key: 'category', label: 'Category', render: (row) => <span className={`px-2 py-0.5 rounded text-xs font-medium ${categoryColors[row.category] || ''}`}>{row.category}</span> },
        { key: 'allocated_amount', label: 'Allocated', render: (row) => formatCurrency(row.allocated_amount) },
        { key: 'spent_amount', label: 'Spent', render: (row) => formatCurrency(row.spent_amount) },
        { key: 'remaining', label: 'Remaining', render: (row) => formatCurrency(row.allocated_amount - row.spent_amount) },
        { key: 'utilization', label: 'Utilization', render: (row) => {
            const pct = row.allocated_amount > 0 ? Math.round((row.spent_amount / row.allocated_amount) * 100) : 0;
            return <span className={pct > 90 ? 'text-red-600 font-semibold' : pct > 70 ? 'text-yellow-600' : 'text-green-600'}>{pct}%</span>;
        }},
        { key: 'period', label: 'Period' },
    ];

    const actions = (row) => (
        <div className="flex space-x-1">
            {can('finance.edit-budget') && (
                <>
                    <button onClick={() => { setShowEdit(row); setForm({ name: row.name, category: row.category, allocated_amount: row.allocated_amount, period: row.period || 'total', start_date: row.start_date || '', end_date: row.end_date || '', notes: row.notes || '' }); }} className="p-1 text-blue-600 hover:bg-blue-50 rounded"><PencilIcon className="h-4 w-4" /></button>
                    <button onClick={() => handleDelete(row.id)} className="p-1 text-red-600 hover:bg-red-50 rounded"><TrashIcon className="h-4 w-4" /></button>
                </>
            )}
        </div>
    );

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <PermissionGate permission="finance.edit-budget">
                    <button onClick={() => { setShowCreate(true); setForm({ name: '', category: 'operations', allocated_amount: '', period: 'total', start_date: '', end_date: '', notes: '' }); }} className="flex items-center space-x-1 bg-primary-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4" /><span>Create Budget</span>
                    </button>
                </PermissionGate>
            </div>

            <DataTable columns={columns} data={items} loading={loading} actions={actions} />

            <Modal isOpen={showCreate || !!showEdit} onClose={() => { setShowCreate(false); setShowEdit(null); }} title={showEdit ? 'Edit Budget' : 'Create Budget'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="text-red-600 text-sm">{error}</div>}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                        <input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                            <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                                {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Allocated Amount (KES) *</label>
                            <input type="number" step="0.01" min="0" value={form.allocated_amount} onChange={(e) => setForm({ ...form, allocated_amount: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Period</label>
                            <select value={form.period} onChange={(e) => setForm({ ...form, period: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                                {BUDGET_PERIODS.map((p) => <option key={p} value={p}>{p}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} rows={3} className="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div className="flex justify-end space-x-2">
                        <button type="button" onClick={() => { setShowCreate(false); setShowEdit(null); }} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">{submitting ? 'Saving...' : 'Save'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}

// =====================================================================
// Donations Tab
// =====================================================================

function DonationsTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [channelFilter, setChannelFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showStkPush, setShowStkPush] = useState(false);
    const [form, setForm] = useState({ donor_name: '', donor_phone: '', donor_email: '', amount: '', channel: 'cash', mpesa_receipt: '', notes: '', is_anonymous: false });
    const [stkForm, setStkForm] = useState({ phone_number: '', amount: '', donor_name: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});
    const { can } = useCampaignPermissions();

    const fetch = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (channelFilter) params.channel = channelFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/finance/donations`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, channelFilter, page]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            await api.post(`/campaigns/${campaignId}/finance/donations`, form);
            setShowCreate(false);
            setForm({ donor_name: '', donor_phone: '', donor_email: '', amount: '', channel: 'cash', mpesa_receipt: '', notes: '', is_anonymous: false });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleStkPush = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            const { data } = await api.post(`/campaigns/${campaignId}/finance/mpesa/stk-push`, stkForm);
            alert(data.message || 'STK Push sent. Check the donor\'s phone.');
            setShowStkPush(false);
            setStkForm({ phone_number: '', amount: '', donor_name: '' });
        } catch (err) {
            setError(err.response?.data?.message || 'M-Pesa request failed.');
        }
        setSubmitting(false);
    };

    const columns = [
        { key: 'donor_name', label: 'Donor', render: (row) => row.is_anonymous ? 'Anonymous' : (row.donor_name || row.donor_phone || '-') },
        { key: 'amount', label: 'Amount', render: (row) => formatCurrency(row.amount) },
        { key: 'channel', label: 'Channel', render: (row) => <span className="capitalize">{row.channel}</span> },
        { key: 'status', label: 'Status', render: (row) => <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusColors[row.status] || ''}`}>{row.status}</span> },
        { key: 'mpesa_receipt', label: 'Receipt' },
        { key: 'donated_at', label: 'Date', render: (row) => row.donated_at?.split('T')[0] },
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <div className="relative">
                        <MagnifyingGlassIcon className="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input type="text" placeholder="Search donations..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} className="pl-9 pr-3 py-2 border rounded-lg text-sm w-64" />
                    </div>
                    <select value={channelFilter} onChange={(e) => { setChannelFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                        <option value="">All channels</option>
                        {DONATION_CHANNELS.map((c) => <option key={c} value={c}>{c}</option>)}
                    </select>
                </div>
                <div className="flex space-x-2">
                    <PermissionGate permission="finance.edit">
                        <button onClick={() => setShowStkPush(true)} className="flex items-center space-x-1 bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
                            <CurrencyDollarIcon className="h-4 w-4" /><span>M-Pesa STK Push</span>
                        </button>
                    </PermissionGate>
                    <PermissionGate permission="finance.edit">
                        <button onClick={() => { setShowCreate(true); setForm({ donor_name: '', donor_phone: '', donor_email: '', amount: '', channel: 'cash', mpesa_receipt: '', notes: '', is_anonymous: false }); }} className="flex items-center space-x-1 bg-primary-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-primary-700">
                            <PlusIcon className="h-4 w-4" /><span>Record Donation</span>
                        </button>
                    </PermissionGate>
                </div>
            </div>

            <DataTable columns={columns} data={items} loading={loading} meta={meta} onPageChange={(pg) => { setPage(pg); fetch(pg); }} />

            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="Record Donation">
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="text-red-600 text-sm">{error}</div>}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Donor Name</label>
                            <input type="text" value={form.donor_name} onChange={(e) => setForm({ ...form, donor_name: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Amount (KES) *</label>
                            <input type="number" step="0.01" min="1" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" value={form.donor_phone} onChange={(e) => setForm({ ...form, donor_phone: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Channel *</label>
                            <select value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                                {DONATION_CHANNELS.map((c) => <option key={c} value={c}>{c}</option>)}
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">M-Pesa Receipt</label>
                        <input type="text" value={form.mpesa_receipt} onChange={(e) => setForm({ ...form, mpesa_receipt: e.target.value })} className="w-full border rounded-lg px-3 py-2" placeholder="e.g. QHK7B2C5LP" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} rows={2} className="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <label className="flex items-center space-x-2 text-sm">
                        <input type="checkbox" checked={form.is_anonymous} onChange={(e) => setForm({ ...form, is_anonymous: e.target.checked })} />
                        <span>Anonymous donation</span>
                    </label>
                    <div className="flex justify-end space-x-2">
                        <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">{submitting ? 'Saving...' : 'Save'}</button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={showStkPush} onClose={() => setShowStkPush(false)} title="M-Pesa STK Push">
                <form onSubmit={handleStkPush} className="space-y-4">
                    {error && <div className="text-red-600 text-sm">{error}</div>}
                    <p className="text-sm text-gray-500">Send an M-Pesa payment prompt directly to the donor's phone.</p>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                        <input type="text" value={stkForm.phone_number} onChange={(e) => setStkForm({ ...stkForm, phone_number: e.target.value })} className="w-full border rounded-lg px-3 py-2" placeholder="0712345678 or 254712345678" required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Amount (KES) *</label>
                        <input type="number" min="1" max="150000" value={stkForm.amount} onChange={(e) => setStkForm({ ...stkForm, amount: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Donor Name</label>
                        <input type="text" value={stkForm.donor_name} onChange={(e) => setStkForm({ ...stkForm, donor_name: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div className="flex justify-end space-x-2">
                        <button type="button" onClick={() => setShowStkPush(false)} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">{submitting ? 'Sending...' : 'Send STK Push'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
