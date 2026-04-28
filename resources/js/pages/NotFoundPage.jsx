import React from 'react';
import { Link } from 'react-router-dom';

export default function NotFoundPage() {
    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
            <div className="text-center">
                <h1 className="text-6xl font-heading font-bold text-primary-600 mb-4">404</h1>
                <h2 className="text-2xl font-heading font-bold text-gray-800 mb-2">Page Not Found</h2>
                <p className="text-gray-500 mb-8">The page you're looking for doesn't exist or has been moved.</p>
                <Link to="/" className="btn-primary">Go Home</Link>
            </div>
        </div>
    );
}
