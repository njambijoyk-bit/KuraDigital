import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { HelmetProvider } from 'react-helmet-async';
import App from './App';
import { registerServiceWorker } from './lib/registerSW';
import '../css/app.css';
import 'leaflet/dist/leaflet.css';

const container = document.getElementById('app');
const root = createRoot(container);

root.render(
    <HelmetProvider>
        <BrowserRouter>
            <App />
        </BrowserRouter>
    </HelmetProvider>
);

registerServiceWorker();
