import { Link } from '@inertiajs/react';

import ImageGallery from '@/components/site/image-gallery';
import PublicPage from '@/layouts/public-page';
import AssociatedMedia from '@/components/site/associated-media';

interface ProjectShowProps {
    project: {
        title: string;
        description: string | null;
        image: string | null;
        gallery: string[];
        category: string | null;
    };
    others: { title: string; href: string }[];
    mediaGalleries: { title: string; href: string; imageCount: number; cover: string | null }[];
    mediaVideos: { title: string; caption: string | null; videoUrl: string; thumbnail: string | null }[];
    mediaImages: { title: string; caption: string | null; src: string | null }[];
}

export default function ProjectShow({ project, others, mediaGalleries, mediaVideos, mediaImages }: ProjectShowProps) {
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
                                    <span className="group-hover:text-lpres-green text-base leading-6 font-bold text-lpres-deep transition-colors">
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
