import { useEffect } from 'react';
import { useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet.heat';

export default function HeatmapLayer({ points }) {
    const map = useMap();

    useEffect(() => {
        if (!points || points.length === 0) return;

        const heatPoints = points.map((p) => [p.lat, p.lng, p.intensity || 0.5]);
        const heat = L.heatLayer(heatPoints, {
            radius: 25,
            blur: 15,
            maxZoom: 17,
            gradient: { 0.2: '#2563EB', 0.4: '#10B981', 0.6: '#F59E0B', 0.8: '#EF4444', 1.0: '#7C3AED' },
        });

        heat.addTo(map);

        return () => {
            map.removeLayer(heat);
        };
    }, [map, points]);

    return null;
}
