import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    BookOpenIcon,
    PlusIcon,
    ArrowPathIcon,
    ChartBarIcon,
    ScaleIcon,
    BanknotesIcon,
    ArrowTrendingUpIcon,
    ShieldCheckIcon,
    DocumentTextIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

function formatCurrency(amount, currency = 'KES') {
    return `${currency} ${Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;
}

export default function LedgerPage({ campaignId: propCampaignId }) {
    const params = useParams();
    const campaignId = propCampaignId || params.campaignId;
    const [subTab, setSubTab] = useState('journal');
    const { can } = useCampaignPermissions();

    const subTabs = [
        { id: 'journal', label: 'Journal Entries', icon: BookOpenIcon },
        { id: 'chart', label: 'Chart of Accounts', icon: DocumentTextIcon },
        { id: 'trial-balance', label: 'Trial Balance', icon: ScaleIcon },
        { id: 'income-statement', label: 'Income Statement', icon: ArrowTrendingUpIcon },
        { id: 'balance-sheet', label: 'Balance Sheet', icon: BanknotesIcon },
        { id: 'budget-vs-actual', label: 'Budget vs Actual', icon: ChartBarIcon },
        { id: 'funds', label: 'Funds', icon: ShieldCheckIcon },
    ];

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-2">
                {subTabs.map((t) => (
                    <button key={t.id} onClick={() => setSubTab(t.id)}
                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition ${subTab === t.id ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                        <t.icon className="h-4 w-4" />
                        {t.label}
                    </button>
                ))}
            </div>

            {subTab === 'journal' && <JournalEntriesView campaignId={campaignId} />}
            {subTab === 'chart' && <ChartOfAccountsView campaignId={campaignId} />}
            {subTab === 'trial-balance' && <TrialBalanceView campaignId={campaignId} />}
            {subTab === 'income-statement' && <IncomeStatementView campaignId={campaignId} />}
            {subTab === 'balance-sheet' && <BalanceSheetView campaignId={campaignId} />}
            {subTab === 'budget-vs-actual' && <BudgetVsActualView campaignId={campaignId} />}
            {subTab === 'funds' && <FundsView campaignId={campaignId} />}
        </div>
    );
}

// =====================================================================
// Journal Entries
// =====================================================================

function JournalEntriesView({ campaignId }) {
    const [entries, setEntries] = useState([]);
    const [loading, setLoading] = useState(true);
    const [meta, setMeta] = useState({});
    const [page, setPage] = useState(1);
    const [showDetail, setShowDetail] = useState(null);
    const [showCreate, setShowCreate] = useState(false);
    const { can } = useCampaignPermissions();

    const fetch = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/ledger/journal-entries`, { params: { page: pg, per_page: 15 } });
            setEntries(data.data || []);
            setMeta({ current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, page]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleReverse = async (entryId) => {
        if (!window.confirm('Reverse this journal entry? A new reversing entry will be created.')) return;
        try {
            await api.post(`/campaigns/${campaignId}/ledger/journal-entries/${entryId}/reverse`);
            fetch();
        } catch { /* handled */ }
    };

    if (loading) return <div className="text-center py-10 text-gray-500">Loading journal entries...</div>;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-900">Journal Entries</h3>
                <div className="flex gap-2">
                    <button onClick={() => fetch()} className="flex items-center gap-1 px-3 py-2 text-sm bg-gray-100 rounded-lg hover:bg-gray-200">
                        <ArrowPathIcon className="h-4 w-4" /> Refresh
                    </button>
                    {can('ledger.manual-entry') && (
                        <button onClick={() => setShowCreate(true)} className="flex items-center gap-1 px-3 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                            <PlusIcon className="h-4 w-4" /> Manual Entry
                        </button>
                    )}
                </div>
            </div>

            {entries.length === 0 ? (
                <EmptyState icon={BookOpenIcon} title="No journal entries yet" description="Journal entries will be auto-created when expenses are approved or donations are recorded." />
            ) : (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entry #</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {entries.map((entry) => {
                                const totalDebit = entry.lines?.reduce((s, l) => s + Number(l.debit), 0) || 0;
                                const totalCredit = entry.lines?.reduce((s, l) => s + Number(l.credit), 0) || 0;
                                return (
                                    <tr key={entry.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => setShowDetail(entry)}>
                                        <td className="px-4 py-3 text-sm font-mono text-gray-900">{entry.entry_number}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{entry.date?.split('T')[0]}</td>
                                        <td className="px-4 py-3 text-sm text-gray-900 max-w-xs truncate">{entry.description}</td>
                                        <td className="px-4 py-3">
                                            <span className={`px-2 py-0.5 rounded text-xs font-medium ${
                                                entry.reference_type === 'manual' ? 'bg-blue-50 text-blue-700' :
                                                entry.reference_type === 'donation' ? 'bg-green-50 text-green-700' :
                                                entry.reference_type === 'expense' ? 'bg-orange-50 text-orange-700' :
                                                'bg-gray-100 text-gray-700'
                                            }`}>{entry.reference_type || 'auto'}</span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-right font-mono">{formatCurrency(totalDebit)}</td>
                                        <td className="px-4 py-3 text-sm text-right font-mono">{formatCurrency(totalCredit)}</td>
                                        <td className="px-4 py-3">
                                            {entry.is_reversed ? (
                                                <span className="px-2 py-0.5 rounded text-xs font-medium bg-red-50 text-red-700">Reversed</span>
                                            ) : (
                                                <span className="px-2 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700">Posted</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right" onClick={(e) => e.stopPropagation()}>
                                            {!entry.is_reversed && can('ledger.reverse-entry') && (
                                                <button onClick={() => handleReverse(entry.id)} className="text-xs text-red-600 hover:underline">Reverse</button>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {(meta.last_page > 1) && (
                <div className="flex justify-center gap-2">
                    <button disabled={page <= 1} onClick={() => { setPage(page - 1); }} className="px-3 py-1 text-sm rounded border disabled:opacity-40">Prev</button>
                    <span className="px-3 py-1 text-sm text-gray-600">Page {meta.current_page} of {meta.last_page}</span>
                    <button disabled={page >= meta.last_page} onClick={() => { setPage(page + 1); }} className="px-3 py-1 text-sm rounded border disabled:opacity-40">Next</button>
                </div>
            )}

            {showDetail && <JournalEntryDetail entry={showDetail} onClose={() => setShowDetail(null)} />}
            {showCreate && <ManualEntryModal campaignId={campaignId} onClose={() => setShowCreate(false)} onCreated={() => { setShowCreate(false); fetch(); }} />}
        </div>
    );
}

function JournalEntryDetail({ entry, onClose }) {
    return (
        <Modal open onClose={onClose} title={`Journal Entry ${entry.entry_number}`}>
            <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4 text-sm">
                    <div><span className="text-gray-500">Date:</span> <span className="font-medium">{entry.date?.split('T')[0]}</span></div>
                    <div><span className="text-gray-500">Type:</span> <span className="font-medium capitalize">{entry.reference_type || 'auto'}</span></div>
                    <div className="col-span-2"><span className="text-gray-500">Description:</span> <span className="font-medium">{entry.description}</span></div>
                    {entry.poster && <div><span className="text-gray-500">Posted by:</span> <span className="font-medium">{entry.poster.name}</span></div>}
                    <div>
                        <span className="text-gray-500">Status:</span>{' '}
                        {entry.is_reversed ? <span className="text-red-600 font-medium">Reversed</span> : <span className="text-green-600 font-medium">Posted</span>}
                    </div>
                </div>

                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Account</th>
                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Description</th>
                            <th className="px-3 py-2 text-right text-xs font-medium text-gray-500">Debit</th>
                            <th className="px-3 py-2 text-right text-xs font-medium text-gray-500">Credit</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {(entry.lines || []).map((line, i) => (
                            <tr key={i}>
                                <td className="px-3 py-2 font-mono text-xs">{line.account?.code} – {line.account?.name}</td>
                                <td className="px-3 py-2 text-gray-600">{line.description}</td>
                                <td className="px-3 py-2 text-right font-mono">{Number(line.debit) > 0 ? formatCurrency(line.debit) : ''}</td>
                                <td className="px-3 py-2 text-right font-mono">{Number(line.credit) > 0 ? formatCurrency(line.credit) : ''}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="bg-gray-50 font-semibold">
                        <tr>
                            <td className="px-3 py-2" colSpan={2}>Total</td>
                            <td className="px-3 py-2 text-right font-mono">{formatCurrency(entry.lines?.reduce((s, l) => s + Number(l.debit), 0) || 0)}</td>
                            <td className="px-3 py-2 text-right font-mono">{formatCurrency(entry.lines?.reduce((s, l) => s + Number(l.credit), 0) || 0)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </Modal>
    );
}

function ManualEntryModal({ campaignId, onClose, onCreated }) {
    const [accounts, setAccounts] = useState([]);
    const [form, setForm] = useState({ date: new Date().toISOString().split('T')[0], description: '' });
    const [lines, setLines] = useState([
        { account_id: '', debit: '', credit: '', description: '' },
        { account_id: '', debit: '', credit: '', description: '' },
    ]);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        (async () => {
            try {
                const { data } = await api.get(`/campaigns/${campaignId}/ledger/chart-of-accounts`);
                setAccounts(data.data || []);
            } catch { /* handled */ }
        })();
    }, [campaignId]);

    const addLine = () => setLines([...lines, { account_id: '', debit: '', credit: '', description: '' }]);
    const removeLine = (idx) => { if (lines.length > 2) setLines(lines.filter((_, i) => i !== idx)); };
    const updateLine = (idx, field, value) => {
        const updated = [...lines];
        updated[idx] = { ...updated[idx], [field]: value };
        setLines(updated);
    };

    const totalDebit = lines.reduce((s, l) => s + (Number(l.debit) || 0), 0);
    const totalCredit = lines.reduce((s, l) => s + (Number(l.credit) || 0), 0);
    const isBalanced = Math.abs(totalDebit - totalCredit) < 0.01 && totalDebit > 0;

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            await api.post(`/campaigns/${campaignId}/ledger/journal-entries`, {
                ...form,
                lines: lines.map((l) => ({
                    account_id: Number(l.account_id),
                    debit: Number(l.debit) || 0,
                    credit: Number(l.credit) || 0,
                    description: l.description || null,
                })),
            });
            onCreated();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to post entry');
        }
        setSubmitting(false);
    };

    return (
        <Modal open onClose={onClose} title="Manual Journal Entry" maxWidth="max-w-3xl">
            <form onSubmit={handleSubmit} className="space-y-4">
                {error && <div className="text-sm text-red-600 bg-red-50 p-3 rounded-lg">{error}</div>}

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                        <input type="text" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                    </div>
                </div>

                <div>
                    <div className="flex items-center justify-between mb-2">
                        <label className="text-sm font-medium text-gray-700">Lines</label>
                        <button type="button" onClick={addLine} className="text-sm text-primary-600 hover:underline">+ Add Line</button>
                    </div>
                    <div className="space-y-2">
                        {lines.map((line, idx) => (
                            <div key={idx} className="grid grid-cols-12 gap-2 items-center">
                                <select value={line.account_id} onChange={(e) => updateLine(idx, 'account_id', e.target.value)} className="col-span-4 border rounded-lg px-2 py-1.5 text-sm" required>
                                    <option value="">Select account...</option>
                                    {accounts.map((a) => <option key={a.id} value={a.id}>{a.code} – {a.name}</option>)}
                                </select>
                                <input type="text" placeholder="Description" value={line.description} onChange={(e) => updateLine(idx, 'description', e.target.value)} className="col-span-3 border rounded-lg px-2 py-1.5 text-sm" />
                                <input type="number" step="0.01" min="0" placeholder="Debit" value={line.debit} onChange={(e) => updateLine(idx, 'debit', e.target.value)} className="col-span-2 border rounded-lg px-2 py-1.5 text-sm text-right" />
                                <input type="number" step="0.01" min="0" placeholder="Credit" value={line.credit} onChange={(e) => updateLine(idx, 'credit', e.target.value)} className="col-span-2 border rounded-lg px-2 py-1.5 text-sm text-right" />
                                <button type="button" onClick={() => removeLine(idx)} className="col-span-1 text-red-500 hover:text-red-700 text-sm" disabled={lines.length <= 2}>x</button>
                            </div>
                        ))}
                    </div>
                    <div className="flex justify-end gap-6 mt-2 text-sm font-semibold">
                        <span>Debits: {formatCurrency(totalDebit)}</span>
                        <span>Credits: {formatCurrency(totalCredit)}</span>
                        <span className={isBalanced ? 'text-green-600' : 'text-red-600'}>{isBalanced ? 'Balanced' : 'Unbalanced'}</span>
                    </div>
                </div>

                <div className="flex justify-end gap-3 pt-4">
                    <button type="button" onClick={onClose} className="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" disabled={submitting || !isBalanced} className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">{submitting ? 'Posting...' : 'Post Entry'}</button>
                </div>
            </form>
        </Modal>
    );
}

// =====================================================================
// Chart of Accounts
// =====================================================================

function ChartOfAccountsView({ campaignId }) {
    const [accounts, setAccounts] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        (async () => {
            try {
                const { data } = await api.get(`/campaigns/${campaignId}/ledger/chart-of-accounts`);
                setAccounts(data.data || []);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [campaignId]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;

    const types = ['asset', 'liability', 'equity', 'revenue', 'expense'];
    const typeLabels = { asset: 'Assets', liability: 'Liabilities', equity: 'Equity', revenue: 'Revenue', expense: 'Expenses' };
    const typeColors = { asset: 'blue', liability: 'red', equity: 'purple', revenue: 'green', expense: 'orange' };

    return (
        <div className="space-y-6">
            <h3 className="text-lg font-semibold text-gray-900">Chart of Accounts</h3>
            {types.map((type) => {
                const typeAccounts = accounts.filter((a) => a.type === type);
                if (typeAccounts.length === 0) return null;
                return (
                    <div key={type} className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div className={`px-4 py-3 bg-${typeColors[type]}-50 border-b`}>
                            <h4 className="font-semibold text-gray-900">{typeLabels[type]}</h4>
                        </div>
                        <table className="min-w-full">
                            <tbody className="divide-y divide-gray-100">
                                {typeAccounts.map((acct) => (
                                    <tr key={acct.id}>
                                        <td className="px-4 py-2 text-sm font-mono text-gray-600 w-24">{acct.code}</td>
                                        <td className={`px-4 py-2 text-sm text-gray-900 ${acct.parent_id ? 'pl-10' : 'font-medium'}`}>{acct.name}</td>
                                        <td className="px-4 py-2 text-sm text-gray-500 text-right">
                                            {acct.is_system && <span className="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded">System</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                );
            })}
        </div>
    );
}

// =====================================================================
// Trial Balance
// =====================================================================

function TrialBalanceView({ campaignId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        (async () => {
            try {
                const { data: res } = await api.get(`/campaigns/${campaignId}/ledger/trial-balance`);
                setData(res.data);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [campaignId]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!data || data.accounts.length === 0) return <EmptyState icon={ScaleIcon} title="No trial balance data" description="Post journal entries to see the trial balance." />;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-900">Trial Balance</h3>
                <span className="text-sm text-gray-500">As of {data.as_of}</span>
            </div>
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {data.accounts.map((acct, i) => (
                            <tr key={i}>
                                <td className="px-4 py-2 text-sm font-mono text-gray-600">{acct.code}</td>
                                <td className="px-4 py-2 text-sm text-gray-900">{acct.name}</td>
                                <td className="px-4 py-2 text-sm text-right font-mono">{acct.debit > 0 ? formatCurrency(acct.debit) : ''}</td>
                                <td className="px-4 py-2 text-sm text-right font-mono">{acct.credit > 0 ? formatCurrency(acct.credit) : ''}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="bg-gray-50">
                        <tr className="font-semibold">
                            <td className="px-4 py-3" colSpan={2}>Total</td>
                            <td className="px-4 py-3 text-right font-mono">{formatCurrency(data.total_debits)}</td>
                            <td className="px-4 py-3 text-right font-mono">{formatCurrency(data.total_credits)}</td>
                        </tr>
                        <tr>
                            <td className="px-4 py-2" colSpan={4}>
                                <span className={`text-sm font-medium ${data.balanced ? 'text-green-600' : 'text-red-600'}`}>
                                    {data.balanced ? 'Trial balance is in balance' : 'Trial balance is NOT in balance'}
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}

// =====================================================================
// Income Statement
// =====================================================================

function IncomeStatementView({ campaignId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        (async () => {
            try {
                const { data: res } = await api.get(`/campaigns/${campaignId}/ledger/income-statement`);
                setData(res.data);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [campaignId]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!data) return <EmptyState icon={ArrowTrendingUpIcon} title="No data" description="Post transactions to generate the income statement." />;

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-semibold text-gray-900">Income Statement</h3>
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">
                <div>
                    <h4 className="text-sm font-semibold text-gray-500 uppercase mb-3">Revenue</h4>
                    {data.revenue.length === 0 ? (
                        <p className="text-sm text-gray-400">No revenue recorded</p>
                    ) : (
                        <div className="space-y-1">
                            {data.revenue.map((r, i) => (
                                <div key={i} className="flex justify-between text-sm">
                                    <span className="text-gray-700">{r.code} – {r.name}</span>
                                    <span className="font-mono">{formatCurrency(r.balance)}</span>
                                </div>
                            ))}
                            <div className="flex justify-between text-sm font-semibold border-t pt-2 mt-2">
                                <span>Total Revenue</span>
                                <span className="font-mono text-green-700">{formatCurrency(data.total_revenue)}</span>
                            </div>
                        </div>
                    )}
                </div>

                <div>
                    <h4 className="text-sm font-semibold text-gray-500 uppercase mb-3">Expenses</h4>
                    {data.expenses.length === 0 ? (
                        <p className="text-sm text-gray-400">No expenses recorded</p>
                    ) : (
                        <div className="space-y-1">
                            {data.expenses.map((e, i) => (
                                <div key={i} className="flex justify-between text-sm">
                                    <span className="text-gray-700">{e.code} – {e.name}</span>
                                    <span className="font-mono">{formatCurrency(e.balance)}</span>
                                </div>
                            ))}
                            <div className="flex justify-between text-sm font-semibold border-t pt-2 mt-2">
                                <span>Total Expenses</span>
                                <span className="font-mono text-red-700">{formatCurrency(data.total_expenses)}</span>
                            </div>
                        </div>
                    )}
                </div>

                <div className="border-t-2 border-gray-300 pt-4">
                    <div className="flex justify-between text-lg font-bold">
                        <span>Net Income</span>
                        <span className={`font-mono ${data.net_income >= 0 ? 'text-green-700' : 'text-red-700'}`}>{formatCurrency(data.net_income)}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

// =====================================================================
// Balance Sheet
// =====================================================================

function BalanceSheetView({ campaignId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        (async () => {
            try {
                const { data: res } = await api.get(`/campaigns/${campaignId}/ledger/balance-sheet`);
                setData(res.data);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [campaignId]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!data) return <EmptyState icon={BanknotesIcon} title="No data" description="Post transactions to generate the balance sheet." />;

    const Section = ({ title, items, total, color }) => (
        <div>
            <h4 className="text-sm font-semibold text-gray-500 uppercase mb-3">{title}</h4>
            {items.length === 0 ? (
                <p className="text-sm text-gray-400">None</p>
            ) : (
                <div className="space-y-1">
                    {items.map((item, i) => (
                        <div key={i} className="flex justify-between text-sm">
                            <span className="text-gray-700">{item.code} – {item.name}</span>
                            <span className="font-mono">{formatCurrency(item.balance)}</span>
                        </div>
                    ))}
                </div>
            )}
            <div className={`flex justify-between text-sm font-semibold border-t pt-2 mt-2 text-${color}-700`}>
                <span>Total {title}</span>
                <span className="font-mono">{formatCurrency(total)}</span>
            </div>
        </div>
    );

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-900">Balance Sheet</h3>
                <span className={`text-sm font-medium ${data.balanced ? 'text-green-600' : 'text-red-600'}`}>
                    {data.balanced ? 'Balanced' : 'Not balanced'}
                </span>
            </div>
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">
                <Section title="Assets" items={data.assets} total={data.total_assets} color="blue" />
                <Section title="Liabilities" items={data.liabilities} total={data.total_liabilities} color="red" />
                <Section title="Equity" items={data.equity} total={data.total_equity} color="purple" />
                {data.retained_earnings !== 0 && (
                    <div className="flex justify-between text-sm">
                        <span className="text-gray-700 italic">Retained Earnings (Net Income)</span>
                        <span className="font-mono">{formatCurrency(data.retained_earnings)}</span>
                    </div>
                )}
            </div>
        </div>
    );
}

// =====================================================================
// Budget vs Actual
// =====================================================================

function BudgetVsActualView({ campaignId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        (async () => {
            try {
                const { data: res } = await api.get(`/campaigns/${campaignId}/ledger/budget-vs-actual`);
                setData(res.data);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [campaignId]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!data || data.items.length === 0) return <EmptyState icon={ChartBarIcon} title="No budget data" description="Create budgets and post expenses to see the analysis." />;

    const statusColors = {
        on_track: 'bg-green-50 text-green-700',
        warning: 'bg-yellow-50 text-yellow-700',
        over_budget: 'bg-red-50 text-red-700',
    };

    return (
        <div className="space-y-4">
            <h3 className="text-lg font-semibold text-gray-900">Budget vs Actual</h3>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-blue-50 rounded-xl p-4">
                    <p className="text-sm text-blue-600 font-medium">Total Budgeted</p>
                    <p className="text-xl font-bold text-blue-900">{formatCurrency(data.total_budgeted)}</p>
                </div>
                <div className="bg-orange-50 rounded-xl p-4">
                    <p className="text-sm text-orange-600 font-medium">Total Actual</p>
                    <p className="text-xl font-bold text-orange-900">{formatCurrency(data.total_actual)}</p>
                </div>
                <div className={`rounded-xl p-4 ${data.total_variance >= 0 ? 'bg-green-50' : 'bg-red-50'}`}>
                    <p className={`text-sm font-medium ${data.total_variance >= 0 ? 'text-green-600' : 'text-red-600'}`}>Variance</p>
                    <p className={`text-xl font-bold ${data.total_variance >= 0 ? 'text-green-900' : 'text-red-900'}`}>{formatCurrency(data.total_variance)}</p>
                </div>
                <div className="bg-purple-50 rounded-xl p-4">
                    <p className="text-sm text-purple-600 font-medium">Utilization</p>
                    <p className="text-xl font-bold text-purple-900">{data.overall_utilization}%</p>
                </div>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Budget</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Budgeted</th>
                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actual</th>
                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Variance</th>
                            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Utilization</th>
                            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {data.items.map((item, i) => (
                            <tr key={i}>
                                <td className="px-4 py-3 text-sm text-gray-900">{item.budget_name}</td>
                                <td className="px-4 py-3 text-sm text-gray-600 capitalize">{item.category}</td>
                                <td className="px-4 py-3 text-sm text-right font-mono">{formatCurrency(item.budgeted)}</td>
                                <td className="px-4 py-3 text-sm text-right font-mono">{formatCurrency(item.actual)}</td>
                                <td className={`px-4 py-3 text-sm text-right font-mono ${item.variance >= 0 ? 'text-green-700' : 'text-red-700'}`}>{formatCurrency(item.variance)}</td>
                                <td className="px-4 py-3 text-sm text-center">
                                    <div className="flex items-center gap-2">
                                        <div className="flex-1 bg-gray-200 rounded-full h-2">
                                            <div className={`h-2 rounded-full ${item.utilization_percent > 100 ? 'bg-red-500' : item.utilization_percent > 90 ? 'bg-yellow-500' : 'bg-green-500'}`}
                                                style={{ width: `${Math.min(item.utilization_percent, 100)}%` }} />
                                        </div>
                                        <span className="text-xs w-10">{item.utilization_percent}%</span>
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusColors[item.status] || ''}`}>
                                        {item.status?.replace('_', ' ')}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// =====================================================================
// Funds
// =====================================================================

function FundsView({ campaignId }) {
    const [funds, setFunds] = useState([]);
    const [summary, setSummary] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({ name: '', code: '', description: '', is_restricted: false });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const { can } = useCampaignPermissions();

    const fetch = useCallback(async () => {
        setLoading(true);
        try {
            const [fundsRes, summaryRes] = await Promise.all([
                api.get(`/campaigns/${campaignId}/ledger/funds`),
                api.get(`/campaigns/${campaignId}/ledger/funds/summary`),
            ]);
            setFunds(fundsRes.data.data || []);
            setSummary(summaryRes.data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            await api.post(`/campaigns/${campaignId}/ledger/funds`, form);
            setShowCreate(false);
            setForm({ name: '', code: '', description: '', is_restricted: false });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to create fund');
        }
        setSubmitting(false);
    };

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-900">Funds</h3>
                {can('ledger.manage-funds') && (
                    <button onClick={() => setShowCreate(true)} className="flex items-center gap-1 px-3 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4" /> Create Fund
                    </button>
                )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {funds.map((fund) => {
                    const s = summary.find((ss) => ss.fund_id === fund.id);
                    return (
                        <div key={fund.id} className="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                            <div className="flex items-center justify-between mb-2">
                                <h4 className="font-semibold text-gray-900">{fund.name}</h4>
                                <span className={`px-2 py-0.5 rounded text-xs font-medium ${fund.is_restricted ? 'bg-yellow-50 text-yellow-700' : 'bg-green-50 text-green-700'}`}>
                                    {fund.is_restricted ? 'Restricted' : 'Unrestricted'}
                                </span>
                            </div>
                            <p className="text-xs text-gray-500 font-mono mb-2">{fund.code}</p>
                            {fund.description && <p className="text-sm text-gray-600 mb-3">{fund.description}</p>}
                            {s && (
                                <div className="text-sm space-y-1 border-t pt-2">
                                    <div className="flex justify-between"><span className="text-gray-500">Total Debits:</span><span className="font-mono">{formatCurrency(s.total_debits)}</span></div>
                                    <div className="flex justify-between"><span className="text-gray-500">Total Credits:</span><span className="font-mono">{formatCurrency(s.total_credits)}</span></div>
                                    <div className="flex justify-between font-semibold"><span>Net Balance:</span><span className="font-mono">{formatCurrency(s.net_balance)}</span></div>
                                </div>
                            )}
                            {fund.is_default && <p className="text-xs text-gray-400 mt-2 italic">Default fund</p>}
                        </div>
                    );
                })}
            </div>

            {showCreate && (
                <Modal open onClose={() => setShowCreate(false)} title="Create Fund">
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {error && <div className="text-sm text-red-600 bg-red-50 p-3 rounded-lg">{error}</div>}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Code *</label>
                            <input type="text" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} className="w-full border rounded-lg px-3 py-2" maxLength={20} required />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={2} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <label className="flex items-center gap-2">
                            <input type="checkbox" checked={form.is_restricted} onChange={(e) => setForm({ ...form, is_restricted: e.target.checked })} className="rounded" />
                            <span className="text-sm text-gray-700">This is a restricted fund (earmarked for specific purposes)</span>
                        </label>
                        <div className="flex justify-end gap-3 pt-2">
                            <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">Cancel</button>
                            <button type="submit" disabled={submitting} className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">{submitting ? 'Creating...' : 'Create'}</button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
}
