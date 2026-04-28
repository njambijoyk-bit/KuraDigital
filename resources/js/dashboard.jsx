import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import DashboardApp from './dashboard/DashboardApp';

const root = createRoot(document.getElementById('dashboard'));
root.render(
    <BrowserRouter basename="/dashboard">
        <DashboardApp />
    </BrowserRouter>
);
