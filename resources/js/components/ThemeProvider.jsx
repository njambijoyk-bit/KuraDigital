import { useEffect } from 'react';
import useSiteStore from '../stores/useSiteStore';

function hexToHSL(hex) {
    if (!hex) return null;
    hex = hex.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16) / 255;
    const g = parseInt(hex.substring(2, 4), 16) / 255;
    const b = parseInt(hex.substring(4, 6), 16) / 255;

    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;

    if (max === min) {
        h = s = 0;
    } else {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
            case g: h = ((b - r) / d + 2) / 6; break;
            case b: h = ((r - g) / d + 4) / 6; break;
        }
    }
    return { h: Math.round(h * 360), s: Math.round(s * 100), l: Math.round(l * 100) };
}

function generateShades(hex) {
    const hsl = hexToHSL(hex);
    if (!hsl) return {};
    const { h, s } = hsl;
    return {
        50:  `hsl(${h}, ${Math.min(s + 20, 100)}%, 97%)`,
        100: `hsl(${h}, ${Math.min(s + 15, 100)}%, 93%)`,
        200: `hsl(${h}, ${Math.min(s + 10, 100)}%, 85%)`,
        300: `hsl(${h}, ${s}%, 72%)`,
        400: `hsl(${h}, ${s}%, 58%)`,
        500: `hsl(${h}, ${s}%, 48%)`,
        600: `hsl(${h}, ${s}%, 40%)`,
        700: `hsl(${h}, ${s}%, 33%)`,
        800: `hsl(${h}, ${s}%, 26%)`,
        900: `hsl(${h}, ${s}%, 18%)`,
        950: `hsl(${h}, ${s}%, 10%)`,
    };
}

export default function ThemeProvider({ children }) {
    const { site } = useSiteStore();

    useEffect(() => {
        if (!site?.primary_color) return;
        const shades = generateShades(site.primary_color);
        const root = document.documentElement;
        Object.entries(shades).forEach(([shade, value]) => {
            root.style.setProperty(`--color-campaign-${shade}`, value);
        });

        let style = document.getElementById('campaign-theme');
        if (!style) {
            style = document.createElement('style');
            style.id = 'campaign-theme';
            document.head.appendChild(style);
        }
        style.textContent = Object.entries(shades).map(([shade, value]) =>
            `.bg-primary-${shade} { background-color: ${value} !important; }
             .text-primary-${shade} { color: ${value} !important; }
             .border-primary-${shade} { border-color: ${value} !important; }
             .ring-primary-${shade} { --tw-ring-color: ${value} !important; }
             .hover\\:bg-primary-${shade}:hover { background-color: ${value} !important; }
             .hover\\:text-primary-${shade}:hover { color: ${value} !important; }
             .focus\\:ring-primary-${shade}:focus { --tw-ring-color: ${value} !important; }
             .focus\\:border-primary-${shade}:focus { border-color: ${value} !important; }`
        ).join('\n');

        return () => {
            if (style) style.textContent = '';
        };
    }, [site?.primary_color]);

    return children;
}
