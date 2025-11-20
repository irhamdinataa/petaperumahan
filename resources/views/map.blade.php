<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Peta Perumahan Cendikia 2</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cpath fill='%23dc2626' d='M8 28L32 8l24 20v28H36V40H28v16H8z'/%3E%3Cpath fill='%23fff' d='M24 36h16v20H24z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, Noto Sans, sans-serif; }
        #map { width: 100%; min-height: 600px; height: 100vh; }
        .controls { position: absolute; top: 16px; right: 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1); z-index: 1000; }
        .controls label { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #111827; margin-bottom: 8px; }
        .legend { position: absolute; bottom: 16px; left: 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1); z-index: 1000; font-size: 12px; color: #111827; }
        .legend-bar { width: 200px; height: 12px; background: linear-gradient(to right, #fee5d9, #a50f15); border-radius: 6px; margin: 6px 0; }
        .legend-scale { display: flex; justify-content: space-between; }
        .blok-label { color: #374151; font-size: 12px; font-weight: 600; text-shadow: 0 1px 0 #fff; }
    </style>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
    <link rel="preconnect" href="https://{s}.tile.openstreetmap.org">
</head>
<body>
    <div class="controls">
        <label><input type="checkbox" id="toggle-blok" checked> Blok</label>
        <label><input type="checkbox" id="toggle-tempat" checked> Nama Tempat</label>
        <label><input type="checkbox" id="toggle-heat" checked> Kepadatan Penduduk</label>
    </div>
    <div id="map"></div>
    <div class="legend">
        <div>Kepadatan Penduduk</div>
        <div class="legend-bar"></div>
        <div class="legend-scale"><span>8</span><span>26</span></div>
    </div>
    <script>
        const blokUrl = '/data/blok.geojson';
        const tempatUrl = '/data/nama-tempat.geojson';

        const map = L.map('map').setView([-5.34, 105.27], 16);
        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' });
        osm.addTo(map);
        const bounds = L.latLngBounds([]);
        const blocksGroup = L.layerGroup().addTo(map);
        const placesGroup = L.layerGroup().addTo(map);
        let heatLayer = null;
        const minPop = 8;
        const maxPop = 26;
        function mix(a, b, t) { return Math.round(a + (b - a) * t); }
        function colorFor(value) {
            const t = Math.max(0, Math.min(1, (value - minPop) / (maxPop - minPop)));
            const r = mix(254, 165, t);
            const g = mix(229, 15, t);
            const b = mix(217, 21, t);
            return `rgb(${r},${g},${b})`;
        }
        function addBlockLabels(feature, layer) {
            const center = layer.getBounds().getCenter();
            const blokName = feature.properties.blok ? `${feature.properties.blok}` : '';
            const label = L.divIcon({ className: 'blok-label', html: blokName });
            L.marker(center, { icon: label, interactive: false }).addTo(blocksGroup);
        }
        function buildHeat(features) {
            const points = [];
            features.forEach(f => {
                if (!f.properties) return;
                const v = f.properties['data penduduk'];
                if (typeof v !== 'number') return;
                const geo = f.geometry;
                if (!geo || geo.type !== 'Polygon') return;
                const ring = Array.isArray(geo.coordinates) && geo.coordinates.length > 0 ? geo.coordinates[0] : null;
                if (!ring || ring.length === 0) return;
                let sumLat = 0;
                let sumLng = 0;
                let count = 0;
                for (const coord of ring) {
                    if (!Array.isArray(coord) || coord.length < 2) continue;
                    const lng = Number(coord[0]);
                    const lat = Number(coord[1]);
                    if (Number.isNaN(lat) || Number.isNaN(lng)) continue;
                    sumLat += lat;
                    sumLng += lng;
                    count += 1;
                }
                if (count === 0) return;
                const centerLat = sumLat / count;
                const centerLng = sumLng / count;
                const intensity = Math.max(0, Math.min(1, (v - minPop) / (maxPop - minPop)));
                points.push([centerLat, centerLng, intensity]);
            });
            if (heatLayer) { map.removeLayer(heatLayer); }
            heatLayer = L.heatLayer(points, { radius: 25, blur: 15, maxZoom: 17 });
            heatLayer.addTo(map);
        }
        async function loadData() {
            try {
                const blokRes = await fetch(blokUrl);
                if (!blokRes.ok) {
                    throw new Error(`Gagal memuat blok: ${blokRes.status} ${blokRes.statusText}`);
                }
                const blok = await blokRes.json();
                const tempatRes = await fetch(tempatUrl);
                if (!tempatRes.ok) {
                    throw new Error(`Gagal memuat nama tempat: ${tempatRes.status} ${tempatRes.statusText}`);
                }
                const tempat = await tempatRes.json();
                const blocksLayer = L.geoJSON(blok, {
                style: f => ({ color: '#6b7280', weight: 1, fillColor: colorFor(f.properties['data penduduk']), fillOpacity: 0.7 }),
                onEachFeature: (feature, layer) => {
                    addBlockLabels(feature, layer);
                    const v = feature.properties['data penduduk'];
                    const name = feature.properties.blok;
                    const blokText = name ? `Blok ${name}` : 'Blok';
                    const rumahText = typeof v === 'number' ? `${v} rumah` : 'Jumlah rumah tidak tersedia';
                    layer.bindTooltip(`${blokText} • ${rumahText}`);
                }
                });
                blocksLayer.addTo(blocksGroup);
                buildHeat(blok.features || []);
                const placesLayer = L.geoJSON(tempat, {
                pointToLayer: (feature, latlng) => L.marker(latlng),
                onEachFeature: (feature, layer) => {
                    const name = feature.properties && feature.properties.nama ? feature.properties.nama : 'Lokasi';
                    layer.bindPopup(name);
                }
                });
                placesLayer.addTo(placesGroup);
                const b1 = blocksLayer.getBounds();
                const b2 = placesLayer.getBounds();
                if (b1.isValid()) bounds.extend(b1);
                if (b2.isValid()) bounds.extend(b2);
                if (bounds.isValid()) { map.fitBounds(bounds.pad(0.1)); } else { console.warn('Bounds tidak valid, menggunakan view default'); }
                console.log('Data berhasil dimuat:', blok.features.length, 'blok,', tempat.features.length, 'tempat');
            } catch (err) {
                console.error('Error memuat data peta:', err);
                console.error('Detail error:', err.message, err.stack);
                const banner = document.createElement('div');
                banner.textContent = 'Error: ' + err.message;
                banner.style.position = 'absolute';
                banner.style.top = '16px';
                banner.style.left = '16px';
                banner.style.background = '#fee2e2';
                banner.style.color = '#7f1d1d';
                banner.style.border = '1px solid #fecaca';
                banner.style.borderRadius = '8px';
                banner.style.padding = '8px 10px';
                banner.style.boxShadow = '0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1)';
                banner.style.zIndex = '1000';
                document.body.appendChild(banner);
                return;
            }
        }
        console.log('Memulai load data peta...');
        loadData();
        const toggleBlok = document.getElementById('toggle-blok');
        const toggleTempat = document.getElementById('toggle-tempat');
        const toggleHeat = document.getElementById('toggle-heat');
        toggleBlok.addEventListener('change', () => {
            if (toggleBlok.checked) { blocksGroup.addTo(map); } else { map.removeLayer(blocksGroup); }
        });
        toggleTempat.addEventListener('change', () => {
            if (toggleTempat.checked) { placesGroup.addTo(map); } else { map.removeLayer(placesGroup); }
        });
        toggleHeat.addEventListener('change', () => {
            if (!heatLayer) return;
            if (toggleHeat.checked) { heatLayer.addTo(map); } else { map.removeLayer(heatLayer); }
        });
    </script>
</body>
</html>