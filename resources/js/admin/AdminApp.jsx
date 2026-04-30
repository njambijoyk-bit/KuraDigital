import React, { lazy, Suspense } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import ProtectedRoute from './components/ProtectedRoute';
import RequirePermission from './components/RequirePermission';
import AdminLayout from './components/AdminLayout';

const LoginPage = lazy(() => import('./pages/LoginPage'));
const RegisterPage = lazy(() => import('./pages/RegisterPage'));
const CampaignsPage = lazy(() => import('./pages/CampaignsPage'));
const DashboardPage = lazy(() => import('./pages/DashboardPage'));
const TeamPage = lazy(() => import('./pages/TeamPage'));
const SiteSettingsPage = lazy(() => import('./pages/SiteSettingsPage'));
const ManifestoPage = lazy(() => import('./pages/ManifestoPage'));
const EventsPage = lazy(() => import('./pages/EventsPage'));
const NewsPage = lazy(() => import('./pages/NewsPage'));
const GalleryPage = lazy(() => import('./pages/GalleryPage'));
const ProjectsPage = lazy(() => import('./pages/ProjectsPage'));
const VolunteersPage = lazy(() => import('./pages/VolunteersPage'));
const ContactsPage = lazy(() => import('./pages/ContactsPage'));
const MediaPage = lazy(() => import('./pages/MediaPage'));
const AuditLogPage = lazy(() => import('./pages/AuditLogPage'));
const OpponentsPage = lazy(() => import('./pages/OpponentsPage'));

function Loading() {
    return (
        <div className="flex items-center justify-center py-20">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
        </div>
    );
}

export default function AdminApp() {
    return (
        <Suspense fallback={<Loading />}>
            <Routes>
                <Route path="login" element={<LoginPage />} />
                <Route path="register" element={<RegisterPage />} />

                {/* Campaign list */}
                <Route
                    index
                    element={
                        <ProtectedRoute>
                            <CampaignsPage />
                        </ProtectedRoute>
                    }
                />

                {/* Campaign-scoped routes with sidebar layout */}
                <Route
                    path="campaigns/:campaignId"
                    element={
                        <ProtectedRoute>
                            <AdminLayout />
                        </ProtectedRoute>
                    }
                >
                    <Route index element={<DashboardPage />} />
                    <Route path="team" element={<RequirePermission permission="team.view"><TeamPage /></RequirePermission>} />
                    <Route path="site" element={<RequirePermission permission="site.view"><SiteSettingsPage /></RequirePermission>} />
                    <Route path="manifesto" element={<RequirePermission permission="manifesto.view"><ManifestoPage /></RequirePermission>} />
                    <Route path="events" element={<RequirePermission permission="events.view"><EventsPage /></RequirePermission>} />
                    <Route path="news" element={<RequirePermission permission="news.view"><NewsPage /></RequirePermission>} />
                    <Route path="gallery" element={<RequirePermission permission="gallery.view"><GalleryPage /></RequirePermission>} />
                    <Route path="projects" element={<RequirePermission permission="projects.view"><ProjectsPage /></RequirePermission>} />
                    <Route path="volunteers" element={<RequirePermission permission="volunteers.view"><VolunteersPage /></RequirePermission>} />
                    <Route path="contacts" element={<RequirePermission permission="contacts.view"><ContactsPage /></RequirePermission>} />
                    <Route path="opponents" element={<RequirePermission permission="opponents.view"><OpponentsPage /></RequirePermission>} />
                    <Route path="media" element={<RequirePermission permission="media.view"><MediaPage /></RequirePermission>} />
                    <Route path="audit" element={<RequirePermission permission="audit.view"><AuditLogPage /></RequirePermission>} />
                </Route>

                <Route path="*" element={<Navigate to="/admin" replace />} />
            </Routes>
        </Suspense>
    );
}
