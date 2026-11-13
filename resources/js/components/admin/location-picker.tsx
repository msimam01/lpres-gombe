import 'leaflet/dist/leaflet.css';
import type { Map as LeafletMap, Marker as LeafletMarker } from 'leaflet';
import { useEffect, useRef, useState } from 'react';

export interface AdminMapConfig {
    /** Tile URL template. Kept in sync with config/lpres.php (`admin_map.tileUrl`). */
    tileUrl: string;
    attribution: string;
    center: { lat: number; lng: number };
    zoom: number;
}

export interface CoordinatePatch {
    latitude?: string;
    longitude?: string;
}

interface LocationPickerProps {
    latitude: unknown;
    longitude: unknown;
    /** Applied as one update so a click never loses one of the two values. */
    onChange: (patch: CoordinatePatch) => void;
    errors?: { latitude?: string; longitude?: string };
    map: AdminMapConfig;
}

/** Coordinates rounded to the stored precision (decimal(10,7)). */
function toCoordinate(value: number): string {
    return value.toFixed(7);
}

function parseCoordinate(value: unknown): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

/**
 * Map position editor for the project admin form.
 *
 * Leaflet is imported lazily inside the effect so the page stays
 * server-renderable and the map bundle only loads on the admin form.
 * Clicking the map, dragging the marker or typing coordinates all write to
 * the same `latitude` / `longitude` form keys. The device-location button
 * never touches the chosen LGA or ward.
 *
 * Map creation, marker placement and dragging are wired up before the
 * basemap layer is requested: a missing or blocked tile provider must never
 * disable position selection.
 */
export default function LocationPicker({ latitude, longitude, onChange, errors, map }: LocationPickerProps) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const mapRef = useRef<LeafletMap | null>(null);
    const markerRef = useRef<LeafletMarker | null>(null);
    const leafletRef = useRef<typeof import('leaflet') | null>(null);
    const onChangeRef = useRef(onChange);
    const [ready, setReady] = useState(false);
    const [status, setStatus] = useState('');
    const [tileError, setTileError] = useState('');

    onChangeRef.current = onChange;

    const lat = parseCoordinate(latitude);
    const lng = parseCoordinate(longitude);
    const inRange = lat !== null && lng !== null && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
    const hasPoint = inRange;

    // Create the map once.
    useEffect(() => {
        let cancelled = false;

        void (async () => {
            const L = (await import('leaflet')).default;

            if (cancelled || !containerRef.current || mapRef.current) {
                return;
            }

            leafletRef.current = L;

            const start: [number, number] = hasPoint ? [lat as number, lng as number] : [map.center.lat, map.center.lng];
            const instance = L.map(containerRef.current, {
                center: start,
                zoom: hasPoint ? 13 : map.zoom,
                scrollWheelZoom: true,
            });

            // Interaction first: everything below this line must stay usable
            // even when the basemap cannot be fetched.
            instance.on('click', (event) => {
                onChangeRef.current({
                    latitude: toCoordinate(event.latlng.lat),
                    longitude: toCoordinate(event.latlng.lng),
                });
            });

            mapRef.current = instance;
            setReady(true);

            // The panel can mount while hidden; recalculate the tile viewport.
            window.setTimeout(() => instance.invalidateSize(), 0);

            const tileUrl = typeof map.tileUrl === 'string' ? map.tileUrl.trim() : '';

            if (tileUrl === '') {
                setTileError('No basemap address is configured. You can still place and drag the marker.');

                return;
            }

            try {
                L.tileLayer(tileUrl, { attribution: map.attribution ?? '', maxZoom: 19 }).addTo(instance);
            } catch (error) {
                // A bad tile template or a blocked tile host must not stop the
                // map from accepting clicks and marker drags.
                console.error('Project location basemap failed to load', error);
                setTileError('The basemap could not be loaded. You can still place and drag the marker.');
            }
        })();

        return () => {
            cancelled = true;
            mapRef.current?.remove();
            mapRef.current = null;
            markerRef.current = null;
            setReady(false);
        };
        // Map configuration is static for the lifetime of the form.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Keep the tile viewport correct when the panel or window is resized.
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

    // Keep the marker in sync with the coordinate fields.
    useEffect(() => {
        const L = leafletRef.current;
        const instance = mapRef.current;

        if (!ready || !L || !instance) {
            return;
        }

        if (lat === null || lng === null) {
            markerRef.current?.remove();
            markerRef.current = null;

            return;
        }

        const position: [number, number] = [lat, lng];

        if (!markerRef.current) {
            const marker = L.marker(position, {
                draggable: true,
                keyboard: true,
                title: 'Project location',
                icon: L.divIcon({
                    className: 'lpres-map-pin',
                    html: '<span style="display:block;width:20px;height:20px;border-radius:9999px;border:3px solid #fff;background:#2dcd7c;box-shadow:0 1px 6px rgba(0,0,0,.45)"></span>',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10],
                }),
            }).addTo(instance);

            marker.on('dragend', () => {
                const point = marker.getLatLng();
                onChangeRef.current({
                    latitude: toCoordinate(point.lat),
                    longitude: toCoordinate(point.lng),
                });
            });

            markerRef.current = marker;
        } else {
            markerRef.current.setLatLng(position);
        }

        instance.setView(position, Math.max(instance.getZoom(), 13));
    }, [lat, lng, ready]);

    const useCurrentLocation = () => {
        if (!('geolocation' in navigator)) {
            setStatus('This browser cannot read your device location.');
            return;
        }

        setStatus('Locating…');

        navigator.geolocation.getCurrentPosition(
            (position) => {
                onChange({
                    latitude: toCoordinate(position.coords.latitude),
                    longitude: toCoordinate(position.coords.longitude),
                });
                setStatus(
                    `Using this device's location (±${Math.round(position.coords.accuracy)} m). Check the pin before saving — the LGA and ward are not changed.`,
                );
            },
            (error) => {
                setStatus(
                    error.code === error.PERMISSION_DENIED
                        ? 'Location permission was denied. You can still place the pin on the map.'
                        : 'Your location could not be determined. You can still place the pin on the map.',
                );
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
        );
    };

    const inputClass = 'h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm';

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    onClick={useCurrentLocation}
                    className="rounded-md border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-neutral-100"
                >
                    Use my current location
                </button>
                <p className="text-xs text-neutral-500">
                    Or click the map to place the pin, then drag it to refine the position.
                </p>
            </div>

            <div
                ref={containerRef}
                role="application"
                aria-label="Project location map"
                className="h-[320px] w-full overflow-hidden rounded-lg border border-[#e1e8ea] bg-[#eef3f4]"
            />

            {tileError && (
                <p role="status" className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                    {tileError}
                </p>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <label htmlFor="field-latitude" className="text-sm font-medium">
                        Latitude
                    </label>
                    <input
                        id="field-latitude"
                        type="number"
                        inputMode="decimal"
                        step="0.0000001"
                        min={-90}
                        max={90}
                        placeholder="e.g. 10.2891000"
                        value={latitude === null || latitude === undefined ? '' : String(latitude)}
                        onChange={(event) => onChange({ latitude: event.target.value })}
                        aria-invalid={Boolean(errors?.latitude)}
                        aria-describedby="latitude-help"
                        className={inputClass}
                    />
                    <p id="latitude-help" className="text-xs text-neutral-500">
                        Decimal degrees, between -90 and 90.
                    </p>
                    {errors?.latitude && (
                        <p role="alert" className="text-sm text-red-600">
                            {errors.latitude}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <label htmlFor="field-longitude" className="text-sm font-medium">
                        Longitude
                    </label>
                    <input
                        id="field-longitude"
                        type="number"
                        inputMode="decimal"
                        step="0.0000001"
                        min={-180}
                        max={180}
                        placeholder="e.g. 11.1671000"
                        value={longitude === null || longitude === undefined ? '' : String(longitude)}
                        onChange={(event) => onChange({ longitude: event.target.value })}
                        aria-invalid={Boolean(errors?.longitude)}
                        aria-describedby="longitude-help"
                        className={inputClass}
                    />
                    <p id="longitude-help" className="text-xs text-neutral-500">
                        Decimal degrees, between -180 and 180.
                    </p>
                    {errors?.longitude && (
                        <p role="alert" className="text-sm text-red-600">
                            {errors.longitude}
                        </p>
                    )}
                </div>
            </div>

            <p className="rounded-lg border border-[#e1e8ea] bg-[#f7fafb] px-4 py-3 text-sm text-neutral-600">
                {hasPoint ? (
                    <>
                        Selected position:{' '}
                        <strong className="font-semibold text-[#223645]">
                            {(lat as number).toFixed(7)}, {(lng as number).toFixed(7)}
                        </strong>
                    </>
                ) : (
                    'No position selected. Leave both coordinates empty when the project location is not known.'
                )}
            </p>

            <p className="text-xs text-neutral-500">
                Both coordinates are required together. Coordinates are stored on the project record and are only published
                according to the visibility setting below.
            </p>

            {status && (
                <p role="status" className="text-sm text-neutral-700">
                    {status}
                </p>
            )}
        </div>
    );
}
