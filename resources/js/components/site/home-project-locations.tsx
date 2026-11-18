import { Link } from '@inertiajs/react';
import { ArrowRight, MapPin } from 'lucide-react';
import { useEffect, useState } from 'react';

import ProjectsMap, { type PublicMapConfig, type PublicMapMarker } from '@/components/site/projects-map';
import SectionHeading from '@/components/site/section-heading';

interface HomeProjectLocationsProps {
    map: PublicMapConfig & { dataUrl: string };
}

/**
 * Homepage "Project Locations" band: the same public projects map used on the
 * Projects listing page, reading the unfiltered marker feed so every
 * published project with an approved location is shown. The section keeps its
 * heading and a link to the full listing even if the map data cannot load.
 */
export default function HomeProjectLocations({ map }: HomeProjectLocationsProps) {
    const [markers, setMarkers] = useState<PublicMapMarker[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [reloadKey, setReloadKey] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        fetch(map.dataUrl, { signal: controller.signal, headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Map data request failed with ${response.status}`);
                }

                return response.json() as Promise<{ data?: PublicMapMarker[] }>;
            })
            .then((payload) => {
                setMarkers(Array.isArray(payload.data) ? payload.data : []);
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setError('Project locations could not be loaded. Please try again shortly.');
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [map.dataUrl, reloadKey]);

    return (
        <section aria-labelledby="home-project-locations-heading" className="bg-white pt-[120px] max-[1199px]:pt-[70px]">
            <div className="mx-auto w-full max-w-[1200px] px-5 sm:px-6 lg:px-0">
                <SectionHeading eyebrow="Where We Work" title="Project Locations" id="home-project-locations-heading" />

                <p className="mx-auto -mt-1 mb-8 max-w-[760px] text-center text-base leading-[27px] text-[#4b5e65]">
                    Interventions delivered by the Gombe State L-PRES project across the state. Every marker is a published project
                    location approved for publication; pan, zoom or open a marker to reach the project.
                </p>

                <ProjectsMap
                    markers={markers}
                    config={map}
                    loading={loading}
                    error={error}
                    onRetry={() => setReloadKey((key) => key + 1)}
                    emptyMessage="Project locations will appear here as soon as published projects have an approved location."
                    heightClass="h-[380px] sm:h-[460px] lg:h-[520px]"
                    ariaLabel="Map of published L-PRES project locations in Gombe State"
                />

                <div className="mt-6 flex flex-wrap text-center items-center justify-between gap-4">
                    <p className="inline-flex items-center gap-2 text-sm font-semibold text-[#4b5e65]">
                        <MapPin aria-hidden="true" className="text-lpres-green size-4" />
                        {loading
                            ? 'Loading project locations…'
                            : `${markers.length} project ${markers.length === 1 ? 'location' : 'locations'} shown on the map`}
                    </p>
                    <div className="mb-10 mt-12 text-center">
                        <Link
                            href="/our-projects"
                            className="focus-visible:outline-lpres-deep group border-lpres-green text-lpres-deep hover:bg-lpres-green inline-flex h-[52px] items-center gap-3 border px-8 text-sm font-extrabold tracking-[0.5px] uppercase transition-colors duration-500 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-none"
                        >
                            View all projects
                            <ArrowRight
                                aria-hidden="true"
                                className="size-4 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transition-none"
                            />
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}
