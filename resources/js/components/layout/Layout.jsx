import React from 'react';
import Navbar from './Navbar';
import Footer from './Footer';
import WhatsAppButton from './WhatsAppButton';
import ThemeProvider from '../ThemeProvider';

export default function Layout({ children }) {
    return (
        <ThemeProvider>
            <div className="min-h-screen flex flex-col bg-gray-50">
                <Navbar />
                <main className="flex-1">{children}</main>
                <Footer />
                <WhatsAppButton />
            </div>
        </ThemeProvider>
    );
}
