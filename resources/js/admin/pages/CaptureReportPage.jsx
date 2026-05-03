import React, { useState, useRef, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
    CameraIcon,
    VideoCameraIcon,
    MicrophoneIcon,
    DocumentTextIcon,
    PaperAirplaneIcon,
    MapPinIcon,
    XMarkIcon,
    ArrowLeftIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    CloudArrowUpIcon,
    SignalSlashIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import useOfflineSyncStore from '../../lib/offlineSync';

const REPORT_TYPES = [
    { type: 'photo', icon: CameraIcon, label: 'Photo', accept: 'image/*', capture: 'environment' },
    { type: 'video', icon: VideoCameraIcon, label: 'Video', accept: 'video/*', capture: 'environment' },
    { type: 'audio', icon: MicrophoneIcon, label: 'Audio', accept: 'audio/*', capture: null },
    { type: 'text', icon: DocumentTextIcon, label: 'Text', accept: null, capture: null },
];

export default function CaptureReportPage() {
    const { campaignId } = useParams();
    const navigate = useNavigate();
    const fileInputRef = useRef(null);

    const [step, setStep] = useState('type'); // type | form | success
    const [selectedType, setSelectedType] = useState(null);
    const [files, setFiles] = useState([]);
    const [previews, setPreviews] = useState([]);
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const [tags, setTags] = useState('');
    const [location, setLocation] = useState(null);
    const [locationLoading, setLocationLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [successMessage, setSuccessMessage] = useState('');

    const { isOnline, pendingCount, queueReport, syncing, syncPending } = useOfflineSyncStore();

    // Auto-detect location
    const getLocation = useCallback(() => {
        if (!navigator.geolocation) return;
        setLocationLoading(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setLocation({
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                });
                setLocationLoading(false);
            },
            () => setLocationLoading(false),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }, []);

    useEffect(() => {
        getLocation();
    }, [getLocation]);

    const handleTypeSelect = (type) => {
        setSelectedType(type);
        if (type.accept) {
            // Trigger file input
            setTimeout(() => fileInputRef.current?.click(), 100);
        } else {
            setStep('form');
        }
    };

    const handleFileChange = (e) => {
        const selected = Array.from(e.target.files || []);
        if (selected.length === 0) return;

        setFiles((prev) => [...prev, ...selected]);

        // Generate previews
        selected.forEach((file) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    setPreviews((prev) => [...prev, { name: file.name, url: ev.target.result, type: 'image' }]);
                };
                reader.readAsDataURL(file);
            } else if (file.type.startsWith('video/')) {
                setPreviews((prev) => [...prev, { name: file.name, url: URL.createObjectURL(file), type: 'video' }]);
            } else {
                setPreviews((prev) => [...prev, { name: file.name, type: 'file' }]);
            }
        });

        setStep('form');
    };

    const removeFile = (index) => {
        setFiles((prev) => prev.filter((_, i) => i !== index));
        setPreviews((prev) => prev.filter((_, i) => i !== index));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);

        const reportData = {
            type: selectedType.type,
            title: title || null,
            body: body || null,
            capturedAt: new Date().toISOString(),
            campaignId,
            ...(location && { latitude: location.latitude, longitude: location.longitude }),
            ...(tags && { tags: tags.split(',').map((t) => t.trim()).filter(Boolean) }),
        };

        if (isOnline) {
            try {
                const formData = new FormData();
                formData.append('type', reportData.type);
                formData.append('client_id', crypto.randomUUID());
                if (reportData.title) formData.append('title', reportData.title);
                if (reportData.body) formData.append('body', reportData.body);
                if (reportData.latitude) formData.append('latitude', String(reportData.latitude));
                if (reportData.longitude) formData.append('longitude', String(reportData.longitude));
                if (reportData.capturedAt) formData.append('captured_at', reportData.capturedAt);
                if (reportData.tags) {
                    reportData.tags.forEach((t) => formData.append('tags[]', t));
                }
                files.forEach((f) => formData.append('files[]', f));

                await api.post(`/campaigns/${campaignId}/field-reports`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                setSuccessMessage('Report uploaded successfully!');
                setStep('success');
            } catch (err) {
                // If network error, fall back to offline queue
                if (!err.response) {
                    await queueOffline(reportData);
                } else {
                    setError(err.response?.data?.message || 'Upload failed. Please try again.');
                }
            }
        } else {
            await queueOffline(reportData);
        }

        setSubmitting(false);
    };

    const queueOffline = async (reportData) => {
        try {
            // Convert files to blobs for IndexedDB storage
            const fileEntries = [];
            for (const file of files) {
                const buffer = await file.arrayBuffer();
                fileEntries.push({
                    blob: new Blob([buffer], { type: file.type }),
                    name: file.name,
                    type: file.type,
                });
            }

            await queueReport({ ...reportData, files: fileEntries });
            setSuccessMessage('Report saved offline. It will sync when you\'re back online.');
            setStep('success');
        } catch {
            setError('Failed to save report offline.');
        }
    };

    const resetForm = () => {
        setStep('type');
        setSelectedType(null);
        setFiles([]);
        setPreviews([]);
        setTitle('');
        setBody('');
        setTags('');
        setError(null);
        setSuccessMessage('');
    };

    return (
        <div className="min-h-screen bg-gray-50 pb-20">
            {/* Header */}
            <div className="bg-white border-b sticky top-0 z-10">
                <div className="flex items-center justify-between px-4 py-3">
                    <button
                        onClick={() => step === 'type' ? navigate(-1) : setStep('type')}
                        className="p-1 -ml-1"
                    >
                        <ArrowLeftIcon className="h-6 w-6 text-gray-700" />
                    </button>
                    <h1 className="text-lg font-heading font-semibold text-gray-900">
                        {step === 'type' ? 'New Report' : step === 'success' ? 'Done' : `${selectedType?.label} Report`}
                    </h1>
                    <div className="w-6">
                        {!isOnline && <SignalSlashIcon className="h-5 w-5 text-orange-500" />}
                    </div>
                </div>
            </div>

            {/* Offline banner */}
            {!isOnline && (
                <div className="bg-orange-50 border-b border-orange-100 px-4 py-2 flex items-center gap-2">
                    <SignalSlashIcon className="h-4 w-4 text-orange-600 flex-shrink-0" />
                    <p className="text-sm text-orange-700">
                        You're offline. Reports will be saved and synced when you reconnect.
                    </p>
                </div>
            )}

            {/* Pending sync indicator */}
            {pendingCount > 0 && (
                <div className="bg-blue-50 border-b border-blue-100 px-4 py-2 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <CloudArrowUpIcon className="h-4 w-4 text-blue-600" />
                        <p className="text-sm text-blue-700">
                            {pendingCount} report{pendingCount !== 1 ? 's' : ''} pending sync
                        </p>
                    </div>
                    {isOnline && !syncing && (
                        <button
                            onClick={syncPending}
                            className="text-sm text-blue-700 font-medium hover:text-blue-800"
                        >
                            Sync now
                        </button>
                    )}
                    {syncing && (
                        <span className="text-sm text-blue-500">Syncing...</span>
                    )}
                </div>
            )}

            <div className="px-4 py-6 max-w-lg mx-auto">
                {/* Step 1: Select type */}
                {step === 'type' && (
                    <div className="space-y-4">
                        <p className="text-sm text-gray-500 text-center">What type of report?</p>
                        <div className="grid grid-cols-2 gap-4">
                            {REPORT_TYPES.map((rt) => (
                                <button
                                    key={rt.type}
                                    onClick={() => handleTypeSelect(rt)}
                                    className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col items-center gap-3 hover:shadow-md active:bg-gray-50 transition-all"
                                >
                                    <rt.icon className="h-10 w-10 text-primary-600" />
                                    <span className="text-sm font-medium text-gray-900">{rt.label}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* Hidden file input */}
                <input
                    ref={fileInputRef}
                    type="file"
                    accept={selectedType?.accept || '*/*'}
                    capture={selectedType?.capture || undefined}
                    multiple
                    onChange={handleFileChange}
                    className="hidden"
                />

                {/* Step 2: Form */}
                {step === 'form' && (
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {error && (
                            <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm flex items-start gap-2">
                                <ExclamationCircleIcon className="h-5 w-5 flex-shrink-0 mt-0.5" />
                                {error}
                            </div>
                        )}

                        {/* File previews */}
                        {previews.length > 0 && (
                            <div className="grid grid-cols-2 gap-2">
                                {previews.map((p, idx) => (
                                    <div key={idx} className="relative rounded-lg overflow-hidden bg-gray-100">
                                        {p.type === 'image' ? (
                                            <img src={p.url} alt={p.name} className="w-full h-32 object-cover" />
                                        ) : p.type === 'video' ? (
                                            <video src={p.url} className="w-full h-32 object-cover" />
                                        ) : (
                                            <div className="h-32 flex items-center justify-center">
                                                <MicrophoneIcon className="h-8 w-8 text-gray-400" />
                                                <p className="text-xs text-gray-500 ml-2 truncate">{p.name}</p>
                                            </div>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => removeFile(idx)}
                                            className="absolute top-1 right-1 bg-black/50 rounded-full p-1"
                                        >
                                            <XMarkIcon className="h-4 w-4 text-white" />
                                        </button>
                                    </div>
                                ))}
                                {selectedType?.accept && (
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="h-32 border-2 border-dashed border-gray-300 rounded-lg flex flex-col items-center justify-center text-gray-400 hover:text-gray-500 hover:border-gray-400"
                                    >
                                        <selectedType.icon className="h-6 w-6" />
                                        <span className="text-xs mt-1">Add more</span>
                                    </button>
                                )}
                            </div>
                        )}

                        {/* Title */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Title (optional)</label>
                            <input
                                type="text"
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                placeholder="Brief description..."
                                className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                        </div>

                        {/* Body */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                {selectedType?.type === 'text' ? 'Report *' : 'Notes (optional)'}
                            </label>
                            <textarea
                                value={body}
                                onChange={(e) => setBody(e.target.value)}
                                placeholder={selectedType?.type === 'text' ? 'Write your report...' : 'Add any observations...'}
                                rows={selectedType?.type === 'text' ? 6 : 3}
                                required={selectedType?.type === 'text'}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                        </div>

                        {/* Tags */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Tags (comma-separated)</label>
                            <input
                                type="text"
                                value={tags}
                                onChange={(e) => setTags(e.target.value)}
                                placeholder="rally, voter-registration, incident..."
                                className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                        </div>

                        {/* Location */}
                        <div className="bg-gray-50 rounded-lg p-3 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <MapPinIcon className="h-5 w-5 text-gray-400" />
                                {locationLoading ? (
                                    <span className="text-sm text-gray-500">Getting location...</span>
                                ) : location ? (
                                    <span className="text-sm text-gray-700">
                                        {location.latitude.toFixed(4)}, {location.longitude.toFixed(4)}
                                    </span>
                                ) : (
                                    <span className="text-sm text-gray-500">Location unavailable</span>
                                )}
                            </div>
                            {!locationLoading && (
                                <button
                                    type="button"
                                    onClick={getLocation}
                                    className="text-xs text-primary-600 font-medium"
                                >
                                    {location ? 'Refresh' : 'Retry'}
                                </button>
                            )}
                        </div>

                        {/* Submit */}
                        <button
                            type="submit"
                            disabled={submitting || (selectedType?.type === 'text' && !body.trim())}
                            className="w-full bg-primary-600 text-white py-3 rounded-lg font-medium text-sm hover:bg-primary-700 disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            {submitting ? (
                                <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white" />
                            ) : (
                                <>
                                    <PaperAirplaneIcon className="h-5 w-5" />
                                    {isOnline ? 'Submit Report' : 'Save for Later'}
                                </>
                            )}
                        </button>
                    </form>
                )}

                {/* Step 3: Success */}
                {step === 'success' && (
                    <div className="text-center py-8 space-y-4">
                        <CheckCircleIcon className="h-16 w-16 text-green-500 mx-auto" />
                        <h2 className="text-lg font-heading font-semibold text-gray-900">{successMessage}</h2>
                        <div className="flex flex-col gap-3 pt-4">
                            <button
                                onClick={resetForm}
                                className="w-full bg-primary-600 text-white py-3 rounded-lg font-medium text-sm hover:bg-primary-700"
                            >
                                Submit Another Report
                            </button>
                            <button
                                onClick={() => navigate(-1)}
                                className="w-full bg-gray-100 text-gray-700 py-3 rounded-lg font-medium text-sm hover:bg-gray-200"
                            >
                                Go Back
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
