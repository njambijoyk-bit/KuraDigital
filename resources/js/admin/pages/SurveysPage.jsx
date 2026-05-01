import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    ClipboardDocumentListIcon,
    EyeIcon,
    PaperAirplaneIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const QUESTION_TYPES = ['text', 'number', 'select', 'multiselect', 'boolean'];

const emptyForm = {
    title: '', description: '', status: 'draft',
    ward: '', constituency: '', county: '',
    starts_at: '', ends_at: '',
    questions: [{ id: 'q1', type: 'text', text: '', options: [], required: true }],
};

const statusColors = {
    draft: 'bg-gray-100 text-gray-500',
    active: 'bg-green-50 text-green-700',
    closed: 'bg-red-50 text-red-700',
};

export default function SurveysPage() {
    const { campaignId } = useParams();
    const [items, setItems] = useState([]);
    const [meta, setMeta] = useState({});
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [showResponses, setShowResponses] = useState(null);
    const [responses, setResponses] = useState([]);
    const [form, setForm] = useState({ ...emptyForm });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const { can } = useCampaignPermissions();

    const fetchSurveys = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (statusFilter) params.status = statusFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/surveys`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, statusFilter, page]);

    useEffect(() => { fetchSurveys(); }, [fetchSurveys]);

    const fetchResponses = async (surveyId) => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/surveys/${surveyId}/responses`);
            setResponses(data.data || []);
        } catch { /* handled */ }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            const payload = { ...form };
            if (showEdit) {
                await api.put(`/campaigns/${campaignId}/surveys/${showEdit.id}`, payload);
                setShowEdit(null);
            } else {
                await api.post(`/campaigns/${campaignId}/surveys`, payload);
                setShowCreate(false);
            }
            setForm({ ...emptyForm });
            fetchSurveys();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this survey?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/surveys/${id}`);
            fetchSurveys();
        } catch { /* handled */ }
    };

    const openEdit = (survey) => {
        setForm({
            title: survey.title || '',
            description: survey.description || '',
            status: survey.status || 'draft',
            ward: survey.ward || '',
            constituency: survey.constituency || '',
            county: survey.county || '',
            starts_at: survey.starts_at ? survey.starts_at.slice(0, 10) : '',
            ends_at: survey.ends_at ? survey.ends_at.slice(0, 10) : '',
            questions: survey.questions || emptyForm.questions,
        });
        setShowEdit(survey);
        setError(null);
    };

    const addQuestion = () => {
        setForm({
            ...form,
            questions: [...form.questions, {
                id: `q${form.questions.length + 1}`,
                type: 'text', text: '', options: [], required: true,
            }],
        });
    };

    const updateQuestion = (idx, field, value) => {
        const updated = [...form.questions];
        updated[idx] = { ...updated[idx], [field]: value };
        setForm({ ...form, questions: updated });
    };

    const removeQuestion = (idx) => {
        setForm({ ...form, questions: form.questions.filter((_, i) => i !== idx) });
    };

    const columns = [
        { key: 'title', label: 'Survey', render: (r) => (
            <div>
                <div className="font-medium text-gray-900">{r.title}</div>
                {r.description && <div className="text-xs text-gray-500 truncate max-w-xs">{r.description}</div>}
            </div>
        )},
        { key: 'status', label: 'Status', render: (r) => (
            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[r.status] || statusColors.draft}`}>
                {r.status}
            </span>
        )},
        { key: 'questions_count', label: 'Questions', render: (r) => r.questions?.length || 0 },
        { key: 'responses_count', label: 'Responses', render: (r) => r.responses_count || 0 },
        { key: 'location', label: 'Location', render: (r) => (
            <div className="text-sm text-gray-500">{[r.ward, r.constituency, r.county].filter(Boolean).join(', ') || 'All areas'}</div>
        )},
        { key: 'created_at', label: 'Created', render: (r) => new Date(r.created_at).toLocaleDateString() },
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-2">
                <PermissionGate permission="field.view-reports">
                    <button onClick={() => { fetchResponses(r.id); setShowResponses(r); }}
                        className="text-gray-400 hover:text-primary-600"><EyeIcon className="h-4 w-4" /></button>
                </PermissionGate>
                <PermissionGate permission="field.create-reports">
                    <button onClick={() => openEdit(r)} className="text-gray-400 hover:text-primary-600"><PencilIcon className="h-4 w-4" /></button>
                    <button onClick={() => handleDelete(r.id)} className="text-gray-400 hover:text-red-600"><TrashIcon className="h-4 w-4" /></button>
                </PermissionGate>
            </div>
        )},
    ];

    const formFields = (
        <>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input type="text" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })}
                    className="w-full border rounded-lg px-3 py-2" required />
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })}
                    className="w-full border rounded-lg px-3 py-2" rows={2} />
            </div>
            <div className="grid grid-cols-3 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2">
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" value={form.ends_at} onChange={(e) => setForm({ ...form, ends_at: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
            </div>
            <div className="grid grid-cols-3 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                    <input type="text" value={form.ward} onChange={(e) => setForm({ ...form, ward: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                    <input type="text" value={form.constituency} onChange={(e) => setForm({ ...form, constituency: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                    <input type="text" value={form.county} onChange={(e) => setForm({ ...form, county: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
            </div>

            <div className="border-t pt-4">
                <div className="flex items-center justify-between mb-3">
                    <label className="block text-sm font-medium text-gray-700">Questions</label>
                    <button type="button" onClick={addQuestion}
                        className="text-sm text-primary-600 hover:text-primary-700 font-medium">
                        + Add Question
                    </button>
                </div>
                {form.questions.map((q, idx) => (
                    <div key={idx} className="bg-gray-50 rounded-lg p-3 mb-3 space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-gray-500">Question {idx + 1}</span>
                            {form.questions.length > 1 && (
                                <button type="button" onClick={() => removeQuestion(idx)}
                                    className="text-xs text-red-500 hover:text-red-700">Remove</button>
                            )}
                        </div>
                        <input type="text" value={q.text} onChange={(e) => updateQuestion(idx, 'text', e.target.value)}
                            placeholder="Question text" className="w-full border rounded px-3 py-1.5 text-sm" required />
                        <div className="flex items-center space-x-3">
                            <select value={q.type} onChange={(e) => updateQuestion(idx, 'type', e.target.value)}
                                className="border rounded px-2 py-1 text-sm">
                                {QUESTION_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
                            </select>
                            <label className="flex items-center space-x-1 text-sm text-gray-600">
                                <input type="checkbox" checked={q.required}
                                    onChange={(e) => updateQuestion(idx, 'required', e.target.checked)} />
                                <span>Required</span>
                            </label>
                        </div>
                        {(q.type === 'select' || q.type === 'multiselect') && (
                            <input type="text" value={(q.options || []).join(', ')}
                                onChange={(e) => updateQuestion(idx, 'options', e.target.value.split(',').map((s) => s.trim()).filter(Boolean))}
                                placeholder="Options (comma-separated)" className="w-full border rounded px-3 py-1.5 text-sm" />
                        )}
                    </div>
                ))}
            </div>
        </>
    );

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-heading font-bold text-gray-900">Surveys</h1>
                <PermissionGate permission="field.create-reports">
                    <button onClick={() => { setForm({ ...emptyForm }); setShowCreate(true); setError(null); }}
                        className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4 mr-2" /> New Survey
                    </button>
                </PermissionGate>
            </div>

            <div className="flex items-center space-x-4">
                <div className="relative flex-1 max-w-md">
                    <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input type="text" placeholder="Search surveys..." value={search}
                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                        className="w-full pl-10 pr-4 py-2 border rounded-lg text-sm" />
                </div>
                <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
                    className="border rounded-lg px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
                </div>
            ) : items.length === 0 ? (
                <EmptyState icon={ClipboardDocumentListIcon} title="No surveys" description="Create a survey to start collecting field data." />
            ) : (
                <>
                    <DataTable columns={columns} data={items} />
                    {meta.last_page > 1 && (
                        <div className="flex justify-center space-x-2">
                            {Array.from({ length: meta.last_page }, (_, i) => (
                                <button key={i + 1} onClick={() => { setPage(i + 1); fetchSurveys(i + 1); }}
                                    className={`px-3 py-1 rounded text-sm ${page === i + 1 ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}>
                                    {i + 1}
                                </button>
                            ))}
                        </div>
                    )}
                </>
            )}

            {/* Create / Edit Modal */}
            <Modal open={showCreate || !!showEdit} onClose={() => { setShowCreate(false); setShowEdit(null); }}
                title={showEdit ? 'Edit Survey' : 'New Survey'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm">{error}</div>}
                    {formFields}
                    <div className="flex justify-end space-x-3 pt-4">
                        <button type="button" onClick={() => { setShowCreate(false); setShowEdit(null); }}
                            className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting}
                            className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">
                            {submitting ? 'Saving...' : showEdit ? 'Update' : 'Create Survey'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Responses Modal */}
            <Modal open={!!showResponses} onClose={() => setShowResponses(null)}
                title={showResponses ? `Responses: ${showResponses.title}` : 'Responses'}>
                {responses.length === 0 ? (
                    <p className="text-sm text-gray-500 py-4">No responses submitted yet.</p>
                ) : (
                    <div className="space-y-3 max-h-96 overflow-y-auto">
                        {responses.map((r) => (
                            <div key={r.id} className="p-3 bg-gray-50 rounded-lg">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="font-medium text-sm text-gray-900">{r.submitter?.name || 'Unknown'}</span>
                                    <span className="text-xs text-gray-500">{new Date(r.created_at).toLocaleString()}</span>
                                </div>
                                {r.ward && <div className="text-xs text-gray-500 mb-2">{r.ward}</div>}
                                <div className="space-y-1">
                                    {(r.answers || []).map((a, i) => (
                                        <div key={i} className="text-sm">
                                            <span className="text-gray-500">{a.question_id}: </span>
                                            <span className="text-gray-900">{typeof a.value === 'object' ? JSON.stringify(a.value) : a.value}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Modal>
        </div>
    );
}
