import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import useAuthStore from '../stores/useAuthStore';

export default function ProtectedRoute({ children }) {
    const token = useAuthStore((s) => s.token);
    const location = useLocation();

    if (!token) {
        return <Navigate to="/admin/login" state={{ from: location }} replace />;
    }

    return children;
}
