import { Link, router } from '@inertiajs/react';
import { FolderOpen, MapPin } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

import ProjectsMap, { type PublicMapConfig, type PublicMapMarker } from '@/components/site/projects-map';
import EmptyState from '@/components/site/empty-state';
import PublicPage from '@/layouts/public-page';

interface ProjectCard {
    id: number;
    title: string;
    href: string;
    excerpt: string | null;
    image: string | null;
    category: string | null;
    lga: string | null;
    isMapped: boolean;
}

interface Filters {
    lga: string | null;
    category: string | null;
}

interface ProjectsIndexProps {
    projects: ProjectCard[];
    filters: Filters;
    filterOptions: {
        lgas: string[];
        categories: { name: string; slug: string }[];
    };
    map: PublicMapConfig & { dataUrl: string };
}

const selectClass =
    'h-11 w-full appearance-none rounded-md border border-[#cfdbdf] bg-white px-3 pr-9 text-sm font-semibold text-[#223645] focus:border-[#007a4d] focus:ring-2 focus:ring-[#007a4d]/30 focus:outline-none';

/**
 * Our Projects listing.
 *
 * Filters live in the URL and are applied on the server, so cards and links
 * stay correct with JavaScript disabled and a shared link reproduces the same
 * view. The map reads the same filters from the dedicated map-data endpoint
 * instead of duplicating location logic in the browser.
 */
export default function ProjectsIndex({ projects, filters, filterOptions, map }: ProjectsIndexProps) {
    const [markers, setMarkers] = useState<PublicMapMarker[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [reloadKey, setReloadKey] = useState(0);

    const queryString = useMemo(() => {
        const params = new URLSearchParams();

        if (filters.lga) {
            params.set('lga', filters.lga);
        }

        if (filters.category) {
            params.set('category', filters.category);
        }

        return params.toString();
    }, [filters.lga, filters.category]);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        const url = queryString ? `${map.dataUrl}?${queryString}` : map.dataUrl;

        fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } })
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
                    setError('Project locations could not be loaded. The project list below is unaffected.');
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [map.dataUrl, queryString, reloadKey]);

    const applyFilters = useCallback((next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};

        if (merged.lga) {
            params.lga = merged.lga;
        }

        if (merged.category) {
            params.category = merged.category;
        }

        router.get(route('our-projects'), params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }, [filters]);

    const grouped = useMemo(() => {
        const groups = new Map<string, ProjectCard[]>();

        for (const project of projects) {
            const key = project.category?.trim() || 'Projects';
            groups.set(key, [...(groups.get(key) ?? []), project]);
        }

        return groups;
    }, [projects]);

    const hasFilters = Boolean(filters.lga || filters.category);
    const mapSummary = loading
        ? 'Loading project locations…'
        : `${markers.length} of ${projects.length} listed ${projects.length === 1 ? 'project' : 'projects'} shown on the map`;

    return (
        <PublicPage
            title="Our Projects"
            eyebrow="Our Work"
            description="Projects being delivered by the Gombe State Livestock Productivity and Resilience Support Project."
        >
            <section aria-labelledby="projects-map-heading" className="mb-12">
                <div className="mb-6 max-w-[820px]">
                    <h2 id="projects-map-heading" className="text-lpres-deep text-2xl font-extrabold sm:text-3xl">
                        Explore L-PRES projects across Gombe State
                    </h2>
                    <p className="mt-3 text-base leading-[27px] text-[#4b5e65]">
                        Filter by local government area or project category. The map and the project list always show the same
                        results. Only locations that have been approved for publication are mapped — every published project stays
                        listed whether or not it has a public location.
                    </p>
                </div>

                <div className="mb-6 border border-[#e1e8ea] bg-[#f7fafb] p-4 sm:p-5">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                        <div className="grid gap-2">
                            <label htmlFor="filter-lga" className="text-xs font-bold tracking-[1px] text-[#223645] uppercase">
                                Local government area
                            </label>
                            <select
                                id="filter-lga"
                                className={selectClass}
                                value={filters.lga ?? ''}
                                onChange={(event) => applyFilters({ lga: event.target.value || null })}
                            >
                                <option value="">All local government areas</option>
                                {filterOptions.lgas.map((lga) => (
                                    <option key={lga} value={lga}>
                                        {lga}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {filterOptions.categories.length > 0 && (
                            <div className="grid gap-2">
                                <label htmlFor="filter-category" className="text-xs font-bold tracking-[1px] text-[#223645] uppercase">
                                    Category
                                </label>
                                <select
                                    id="filter-category"
                                    className={selectClass}
                                    value={filters.category ?? ''}
                                    onChange={(event) => applyFilters({ category: event.target.value || null })}
                                >
                                    <option value="">All categories</option>
                                    {filterOptions.categories.map((category) => (
                                        <option key={category.slug} value={category.slug}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        <div className="lg:pb-0.5">
                            {hasFilters ? (
                                <Link
                                    href={route('our-projects')}
                                    className="inline-flex h-11 items-center text-sm font-bold text-[#007a4d] underline underline-offset-2"
                                >
                                    Clear filters
                                </Link>
                            ) : (
                                <p className="text-xs leading-5 text-[#6b7d84] lg:max-w-[180px]">
                                    Choose a filter to narrow the map and the list.
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <p role="status" className="mb-3 text-sm font-semibold text-[#6b7d84]">
                    {mapSummary}
                </p>

                <ProjectsMap
                    markers={markers}
                    config={map}
                    loading={loading}
                    error={error}
                    onRetry={() => setReloadKey((key) => key + 1)}
                    emptyMessage={
                        hasFilters
                            ? 'No published project locations match these filters yet. The list below shows the matching projects.'
                            : 'No project locations have been approved for publication yet. Published projects are listed below.'
                    }
                    ariaLabel="Map of published L-PRES project locations in Gombe State"
                />
            </section>

            {projects.length === 0 ? (
                <div>
                    <EmptyState
                        message={
                            hasFilters
                                ? 'No published projects match these filters. Clear the filters to see all projects.'
                                : 'No projects have been published yet. Project records added in the admin panel will appear here with their images and descriptions.'
                        }
                    />
                    {hasFilters && (
                        <p className="mt-4 text-center">
                            <Link href={route('our-projects')} className="text-sm font-bold text-[#007a4d] underline">
                                Clear filters
                            </Link>
                        </p>
                    )}
                </div>
            ) : (
                [...grouped.entries()].map(([category, categoryProjects]) => (
                    <section key={category} aria-labelledby={`projects-${category.replace(/\W+/g, '-')}`} className="mb-14 last:mb-0">
                        {grouped.size > 1 && (
                            <div className="relative mb-8 inline-block">
                                <span aria-hidden="true" className="bg-lpres-green absolute top-[-13px] left-0 block h-0.5 w-[35px]" />
                                <p className="text-lpres-green text-sm font-bold tracking-[1px] uppercase">{category}</p>
                            </div>
                        )}

                        <ul className="grid list-none gap-8 sm:grid-cols-2">
                            {categoryProjects.map((project) => (
                                <li key={project.id}>
                                    <Link
                                        href={project.href}
                                        className="group focus-visible:outline-lpres-green block h-full bg-[#f1f5f6] no-underline transition-[transform,box-shadow] duration-500 hover:-translate-y-1.5 hover:shadow-[0_24px_44px_-20px_rgba(0,59,73,0.35)] focus-visible:outline-2 focus-visible:outline-offset-4 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                                    >
                                        {/* The featured image is shown whole inside a
                                            fixed, tinted frame so no photograph is cropped. */}
                                        <div className="overflow-hidden border-b border-[#e1e8ea] bg-[#eef3f4]">
                                            {project.image ? (
                                                <img
                                                    src={project.image}
                                                    alt={project.title}
                                                    width={465}
                                                    height={375}
                                                    loading="lazy"
                                                    decoding="async"
                                                    className="block aspect-[465/375] w-full object-contain"
                                                />
                                            ) : (
                                                <div
                                                    aria-hidden="true"
                                                    className="flex aspect-[465/375] w-full items-center justify-center"
                                                >
                                                    <FolderOpen className="text-lpres-deep/25 size-12" aria-hidden="true" />
                                                </div>
                                            )}
                                        </div>

                                        <div className="px-[30px] py-6">
                                            <h3 className="text-lpres-deep group-hover:text-lpres-green text-xl leading-8 font-extrabold transition-colors duration-500">
                                                {project.title}
                                            </h3>
                                            {project.excerpt && (
                                                <p className="mt-2 line-clamp-3 text-base leading-[27px] text-[#838e91]">{project.excerpt}</p>
                                            )}
                                            <p className="mt-4 flex items-center gap-1.5 text-xs font-bold tracking-[0.5px] text-[#6b7d84] uppercase">
                                                <MapPin aria-hidden="true" className="size-3.5 shrink-0 text-[#007a4d]" />
                                                <span>
                                                    {project.isMapped
                                                        ? (project.lga ?? 'Gombe State')
                                                        : project.lga
                                                          ? `${project.lga} · not mapped`
                                                          : 'Location not mapped'}
                                                </span>
                                            </p>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))
            )}
        </PublicPage>
    );
}
