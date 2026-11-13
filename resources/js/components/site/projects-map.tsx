import { useEffect, useRef, useState } from 'react';

import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

import type { Map as LeafletMap, Marker as LeafletMarker } from 'leaflet';
import type { MarkerClusterGroup } from 'leaflet';

export interface PublicMapMarker {
    id: number;
    title: string;
    slug: string;
    href: string;
    category: string | null;
    lga: string | null;
    latitude: number;
    longitude: number;
    precision: 'exact' | 'approximate';
    directionsUrl: string | null;
}

export interface PublicMapConfig {
    /** Null when no approved basemap provider is configured. */
    tileUrl: string | null;
    attribution: string;
    center: { lat: number; lng: number };
    zoom: number;
    /** Atlas raster limit; defaults keep the cluster plugin's finite max zoom. */
    minZoom?: number;
    maxZoom?: number;
}

interface ProjectsMapProps {
    markers: PublicMapMarker[];
    config: PublicMapConfig;
    /** Tailwind height class for the map frame. */
    heightClass?: string;
    loading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    /** Shown over the map when there are no markers. Pass null to stay silent. */
    emptyMessage?: string | null;
    ariaLabel?: string;
    /** Compact variant for detail pages: no clustering, calmer zoom. */
    compact?: boolean;
    /** Detail pages already show the project title, so popups add nothing. */
    showPopups?: boolean;
}

const PIN_HTML = (color: string) =>
    `<span style="display:block;width:18px;height:18px;border-radius:9999px;border:3px solid #fff;background:${color};box-shadow:0 1px 6px rgba(0,0,0,.45)"></span>`;

/** Builds popup content as DOM nodes so project titles can never inject markup. */
function buildPopup(marker: PublicMapMarker): HTMLElement {
    const root = document.createElement('div');
    root.className = 'w-[230px]';

    const title = document.createElement('p');
    title.className = 'text-[15px] leading-5 font-extrabold text-[#003b49]';
    title.textContent = marker.title;
    root.append(title);

    const meta = document.createElement('p');
    meta.className = 'mt-1 text-xs text-[#6b7d84]';
    meta.textContent = [marker.category, marker.lga].filter((value): value is string => Boolean(value)).join(' · ') || 'Gombe State L-PRES';
    root.append(meta);

    if (marker.precision === 'approximate') {
        const note = document.createElement('p');
        note.className = 'mt-1 text-xs font-semibold text-[#8a5a00]';
        note.textContent = 'Approximate location';
        root.append(note);
    }

    const actions = document.createElement('div');
    actions.className = 'mt-3 flex flex-wrap items-center gap-x-4 gap-y-2';

    const detail = document.createElement('a');
    detail.href = marker.href;
    detail.className = 'text-sm font-semibold text-[#007a4d] underline';
    detail.textContent = 'View project';
    actions.append(detail);

    if (marker.directionsUrl) {
        const directions = document.createElement('a');
        directions.href = marker.directionsUrl;
        directions.target = '_blank';
        directions.rel = 'noopener noreferrer';
        directions.className = 'text-sm font-semibold text-[#003b49] underline';
        directions.textContent = 'Directions';
        actions.append(directions);
    }

    root.append(actions);

    return root;
}

/**
 * Public projects map.
 *
 * Leaflet and its cluster plugin load lazily so the public listing keeps its
 * server-rendered cards even if the map bundle or the tile provider fails.
 * Marker data always arrives pre-privacy-filtered from the server; this
 * component never decides what may be shown.
 */
export default function ProjectsMap({
    markers,
    config,
    heightClass = 'h-[420px]',
    loading = false,
    error = null,
    onRetry,
    emptyMessage = null,
    ariaLabel = 'Map of published project locations in Gombe State',
    compact = false,
    showPopups = true,
}: ProjectsMapProps) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const mapRef = useRef<LeafletMap | null>(null);
    const clusterRef = useRef<MarkerClusterGroup | null>(null);
    const leafletRef = useRef<typeof import('leaflet') | null>(null);
    const [ready, setReady] = useState(false);
    const [tileIssue, setTileIssue] = useState(false);

    const minZoom = Number.isFinite(config.minZoom) ? (config.minZoom as number) : 3;
    const maxZoom = Number.isFinite(config.maxZoom) ? (config.maxZoom as number) : 19;

    // Create the map once; configuration is fixed for the page lifetime.
    useEffect(() => {
        let cancelled = false;

        void (async () => {
            const L = (await import('leaflet')).default;
            await import('leaflet.markercluster');

            if (cancelled || !containerRef.current || mapRef.current) {
                return;
            }

            // Panning and zooming work with no basemap: only the imagery is optional.
            // maxZoom must be explicit: without a tile layer Leaflet has no
            // finite maximum, and the cluster plugin refuses to attach to a map
            // whose max zoom is infinite.
            const instance = L.map(containerRef.current, {
                center: [config.center.lat, config.center.lng],
                zoom: config.zoom,
                minZoom: minZoom,
                maxZoom: maxZoom,
                scrollWheelZoom: !compact,
                zoomControl: true,
            });

            leafletRef.current = L;
            mapRef.current = instance;
            setReady(true);

            window.setTimeout(() => instance.invalidateSize(), 0);

            const tileUrl = typeof config.tileUrl === 'string' ? config.tileUrl.trim() : '';

            if (tileUrl === '') {
                setTileIssue(true);

                return;
            }

            try {
                const layer = L.tileLayer(tileUrl, { attribution: config.attribution ?? '', maxZoom });
                layer.on('tileerror', () => setTileIssue(true));
                layer.addTo(instance);
            } catch (issue) {
                console.error('Public project map basemap failed to load', issue);
                setTileIssue(true);
            }
        })();

        return () => {
            cancelled = true;
            mapRef.current?.remove();
            mapRef.current = null;
            clusterRef.current = null;
            leafletRef.current = null;
            setReady(false);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Marker layer follows the filtered marker set.
    useEffect(() => {
        const L = leafletRef.current;
        const instance = mapRef.current;

        if (!ready || !L || !instance) {
            return;
        }

        let cluster = clusterRef.current;

        if (!cluster) {
            cluster = L.markerClusterGroup({
                showCoverageOnHover: false,
                spiderfyOnMaxZoom: true,
                maxClusterRadius: compact ? 0 : 55,
                disableClusteringAtZoom: compact ? 0 : 13,
            });
            clusterRef.current = cluster;
            instance.addLayer(cluster);
        }

        cluster.clearLayers();

        const entries = markers.filter((marker) => Number.isFinite(marker.latitude) && Number.isFinite(marker.longitude));

        const layers: LeafletMarker[] = entries.map((marker) => {
            const pin = L.marker([marker.latitude, marker.longitude], {
                keyboard: true,
                title: marker.title,
                alt: marker.title,
                icon: L.divIcon({
                    className: 'lpres-public-pin',
                    html: PIN_HTML(marker.precision === 'approximate' ? '#c98a00' : '#2dcd7c'),
                    iconSize: [18, 18],
                    iconAnchor: [9, 9],
                    popupAnchor: [0, -12],
                }),
            });

            if (showPopups) {
                pin.bindPopup(buildPopup(marker), { maxWidth: 260, minWidth: 230 });
            }

            return pin;
        });

        for (const layer of layers) {
            cluster.addLayer(layer);
        }

        if (entries.length === 0) {
            instance.setView([config.center.lat, config.center.lng], config.zoom);

            return;
        }

        if (entries.length === 1) {
            instance.setView([entries[0].latitude, entries[0].longitude], compact ? 12 : Math.max(config.zoom, 10));

            return;
        }

        instance.fitBounds(
            entries.map((marker) => [marker.latitude, marker.longitude] as [number, number]),
            { padding: [40, 40], maxZoom: compact ? 12 : 10 },
        );
        // Data drives placement; config is static per page.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [markers, ready, compact, showPopups]);

    // The map can mount inside a hidden or resizing panel.
    useEffect(() => {
        const instance = mapRef.current;
        const container = containerRef.current;

        if (!ready || !instance || !container || typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(() => instance.invalidateSize());
        observer.observe(container);

        return () => observer.disconnect();
    }, [ready]);

    const overlayCard = 'pointer-events-auto max-w-[420px] rounded-md border border-[#e1e8ea] bg-white px-5 py-4 text-sm text-[#4b5e65] shadow-[0_18px_40px_-24px_rgba(0,59,73,0.45)]';

    return (
        <div className={`relative w-full overflow-hidden border border-[#e1e8ea] bg-[#eef3f4] ${heightClass}`}>
            <div ref={containerRef} role="application" aria-label={ariaLabel} className="h-full w-full" />

            {loading && (
                <div className="pointer-events-none absolute inset-0 flex items-end justify-center bg-[#eef3f4]/60 p-5">
                    <p role="status" className={overlayCard}>
                        Loading project locations…
                    </p>
                </div>
            )}

            {!loading && error && (
                <div className="absolute inset-0 flex items-center justify-center p-5">
                    <div className={overlayCard} role="alert">
                        <p>{error}</p>
                        {onRetry && (
                            <button type="button" onClick={onRetry} className="mt-3 text-sm font-semibold text-[#007a4d] underline">
                                Try again
                            </button>
                        )}
                    </div>
                </div>
            )}

            {!loading && !error && markers.length === 0 && emptyMessage && (
                <div className="pointer-events-none absolute inset-0 flex items-end justify-center p-5">
                    <p className={overlayCard}>{emptyMessage}</p>
                </div>
            )}

            {tileIssue && !loading && (
                <p className="pointer-events-none absolute bottom-2 left-2 z-[500] max-w-[300px] rounded bg-white/95 px-3 py-2 text-xs leading-4 text-[#4b5e65]">
                    Basemap imagery is unavailable. Project markers remain visible.
                </p>
            )}
        </div>
    );
}
