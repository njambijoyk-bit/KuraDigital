import React, { useEffect } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import useAuthStore from '../stores/useAuthStore';

export default function ProtectedRoute({ children }) {
    const token = useAuthStore((s) => s.token);
    const campaigns = useAuthStore((s) => s.campaigns);
    const fetchMe = useAuthStore((s) => s.fetchMe);
    const location = useLocation();

    useEffect(() => {
        if (token && campaigns.length === 0) {
            fetchMe();
        }
    }, [token, campaigns.length, fetchMe]);

    if (!token) {
        return <Navigate to="/admin/login" state={{ from: location }} replace />;
    }

    return children;
}
