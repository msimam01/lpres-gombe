import { Link } from '@inertiajs/react';

import AssociatedMedia from '@/components/site/associated-media';
import ImageGallery from '@/components/site/image-gallery';
import ProjectsMap, { type PublicMapConfig, type PublicMapMarker } from '@/components/site/projects-map';
import PublicPage from '@/layouts/public-page';

interface ProjectLocation {
    visibility: 'exact' | 'approximate' | 'lga_only' | 'hidden';
    lga: string | null;
    ward: string | null;
    description: string | null;
    coordinates: { latitude: number; longitude: number; precision: 'exact' | 'approximate' } | null;
    directionsUrl: string | null;
    isApproximate: boolean;
}

interface ProjectShowProps {
    project: {
        title: string;
        description: string | null;
        image: string | null;
        gallery: string[];
        category: string | null;
        location: ProjectLocation;
    };
    map: PublicMapConfig;
    others: { title: string; href: string }[];
    mediaGalleries: { title: string; href: string; imageCount: number; cover: string | null }[];
    mediaVideos: { title: string; caption: string | null; videoUrl: string; thumbnail: string | null }[];
    mediaImages: { title: string; caption: string | null; src: string | null }[];
}

export default function ProjectShow({ project, map, others, mediaGalleries, mediaVideos, mediaImages }: ProjectShowProps) {
    const location = project.location;

    // Detail pages only ever receive coordinates the privacy layer cleared,
    // so this marker is safe to build from the prop as-is.
    const marker: PublicMapMarker | null = location.coordinates
        ? {
              id: 0,
              title: project.title,
              slug: '',
              href: '',
              category: project.category,
              lga: location.lga,
              latitude: location.coordinates.latitude,
              longitude: location.coordinates.longitude,
              precision: location.coordinates.precision,
              directionsUrl: location.directionsUrl,
          }
        : null;

    return (
        <PublicPage
            title={project.title}
            description={project.category ?? undefined}
            breadcrumbs={[{ title: 'Our Projects', href: '/our-projects' }, { title: 'Project', href: '' }]}
        >
            <article className="bg-white">
                {project.image && (
                    <img src={project.image} alt={project.title} width={1200} height={675} className="mb-8 block w-full object-cover" loading="lazy" decoding="async" />
                )}

                {project.category && (
                    <p className="text-lpres-green text-sm font-bold tracking-[1px] uppercase">{project.category}</p>
                )}
                <h2 className="text-lpres-deep mt-2 text-[34px] leading-[44px] font-extrabold tracking-[-0.4px]">{project.title}</h2>

                {project.description && (
                    <div className="mt-6 max-w-[820px]">
                        {project.description
                            .split(/\r?\n\r?\n|\n{2,}/)
                            .filter((part) => part.trim().length > 0)
                            .map((paragraph, index) => (
                                <p key={index} className="mb-5 text-base leading-[30px] text-[#4b5e65] last:mb-0">
                                    {paragraph}
                                </p>
                            ))}
                    </div>
                )}

                {location.visibility !== 'hidden' && (
                    <section aria-labelledby="project-location-heading" className="mt-10 border-t border-[#e1e8ea] pt-8">
                        <h3 id="project-location-heading" className="text-lpres-deep text-xl font-extrabold">
                            Project Location
                        </h3>

                        <div className="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
                            <div>
                                {marker ? (
                                    <>
                                        <ProjectsMap
                                            markers={[marker]}
                                            config={map}
                                            compact
                                            showPopups={false}
                                            heightClass="h-[280px]"
                                            ariaLabel={`Map showing the location of ${project.title}`}
                                        />
                                        <p className="mt-2 text-xs leading-5 text-[#6b7d84]">
                                            {location.isApproximate
                                                ? 'This marker shows an approximate position within about 1 km of the project, not the exact site.'
                                                : 'Marker position is published by the project team.'}
                                        </p>
                                    </>
                                ) : (
                                    <div className="flex h-[160px] items-center justify-center border border-[#e1e8ea] bg-[#f7fafb] px-6 text-center">
                                        <p className="text-sm leading-6 text-[#6b7d84]">
                                            No map position has been published for this project.
                                            {location.lga ? ` It is delivered in ${location.lga} Local Government Area.` : ''}
                                        </p>
                                    </div>
                                )}
                            </div>

                            <div className="grid gap-4">
                                <dl className="grid gap-3 text-sm">
                                    {location.lga && (
                                        <div>
                                            <dt className="text-xs font-bold tracking-[1px] text-[#6b7d84] uppercase">Local government area</dt>
                                            <dd className="mt-1 text-base font-semibold text-[#223645]">{location.lga}</dd>
                                        </div>
                                    )}
                                    {location.ward && (
                                        <div>
                                            <dt className="text-xs font-bold tracking-[1px] text-[#6b7d84] uppercase">Ward</dt>
                                            <dd className="mt-1 text-base font-semibold text-[#223645]">{location.ward}</dd>
                                        </div>
                                    )}
                                    {location.description && (
                                        <div>
                                            <dt className="text-xs font-bold tracking-[1px] text-[#6b7d84] uppercase">Location description</dt>
                                            <dd className="mt-1 text-base leading-6 text-[#4b5e65]">{location.description}</dd>
                                        </div>
                                    )}
                                </dl>

                                {location.directionsUrl && (
                                    <a
                                        href={location.directionsUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex h-11 w-fit items-center bg-[#007a4d] px-5 text-sm font-bold text-white no-underline hover:bg-[#006340]"
                                    >
                                        Get directions
                                    </a>
                                )}

                                <p className="text-xs leading-5 text-[#6b7d84]">
                                    Directions open in Google Maps in a new tab.
                                </p>
                            </div>
                        </div>
                    </section>
                )}

                {project.gallery.length > 0 && (
                    <section aria-label="Project photo gallery" className="mt-10">
                        <h3 className="text-lpres-deep text-xl font-extrabold">Project Gallery</h3>
                        <div className="mt-5">
                            <ImageGallery
                                items={project.gallery.map((src, index) => ({
                                    src,
                                    alt: `${project.title} — gallery image ${index + 1}`,
                                }))}
                            />
                        </div>
                    </section>
                )}

                <AssociatedMedia galleries={mediaGalleries} videos={mediaVideos} images={mediaImages} />
            </article>

            {others.length > 0 && (
                <section aria-labelledby="other-projects" className="mt-14">
                    <h3 id="other-projects" className="text-lpres-deep text-xl font-extrabold">
                        More Projects
                    </h3>
                    <ul className="mt-5 list-none divide-y divide-[#e1e8ea] border-y border-[#e1e8ea]">
                        {others.map((item) => (
                            <li key={item.href} className="py-4">
                                <Link href={item.href} className="group block no-underline">
                                    <span className="group-hover:text-lpres-green text-lpres-deep text-base leading-6 font-bold transition-colors">
                                        {item.title}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </PublicPage>
    );
}
