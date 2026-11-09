import PublicPage from '@/layouts/public-page';
import EmptyState from '@/components/site/empty-state';
import { Link } from '@inertiajs/react';

interface ProjectCard {
    id: number;
    title: string;
    href: string;
    excerpt: string | null;
    image: string | null;
    category: string | null;
}

interface ProjectsIndexProps {
    projects: ProjectCard[];
}

/**
 * Our Projects listing. Backend-managed records grouped client-side by the
 * project's category while keeping the server display order inside each
 * group. Every card links to the local project detail page.
 */
export default function ProjectsIndex({ projects }: ProjectsIndexProps) {
    if (projects.length === 0) {
        return (
            <PublicPage title="Our Projects" eyebrow="Our Work" description="Projects being delivered by the Gombe State Livestock Productivity and Resilience Support Project.">
                <EmptyState message="No projects have been published yet. Project records added in the admin panel will appear here with their images and descriptions." />
            </PublicPage>
        );
    }

    const grouped = new Map<string, ProjectCard[]>();
    for (const project of projects) {
        const key = project.category?.trim() || 'Projects';
        grouped.set(key, [...(grouped.get(key) ?? []), project]);
    }

    return (
        <PublicPage title="Our Projects" eyebrow="Our Work" description="Projects being delivered by the Gombe State Livestock Productivity and Resilience Support Project.">
            {[...grouped.entries()].map(([category, categoryProjects]) => (
                <section key={category} aria-labelledby={`projects-${category.replace(/\W+/g, '-')}`} className="mb-14 last:mb-0">
                    {grouped.size > 1 && (
                        <div className="relative mb-8 inline-block">
                            <span aria-hidden="true" className="bg-lpres-green absolute top-[-13px] left-0 block h-0.5 w-[35px]" />
                            <p className="text-sm font-bold tracking-[1px] text-lpres-green uppercase">{category}</p>
                        </div>
                    )}

                    <ul className="grid list-none gap-8 sm:grid-cols-2">
                        {categoryProjects.map((project) => (
                            <li key={project.id}>
                                <Link
                                    href={project.href}
                                    className="group block h-full bg-[#f1f5f6] no-underline transition-[transform,box-shadow] duration-500 hover:-translate-y-1.5 hover:shadow-[0_24px_44px_-20px_rgba(0,59,73,0.35)]"
                                >
                                    <div className="overflow-hidden">
                                        {project.image ? (
                                            <img
                                                src={project.image}
                                                alt={project.title}
                                                width={465}
                                                height={375}
                                                loading="lazy"
                                                decoding="async"
                                                className="block aspect-[465/375] w-full object-cover transition-transform duration-700 group-hover:scale-[1.06]"
                                            />
                                        ) : (
                                            <div aria-hidden="true" className="block aspect-[465/375] w-full bg-lpres-deep" />
                                        )}
                                    </div>

                                    <div className="px-[30px] py-6">
                                        <h3 className="text-xl leading-8 font-extrabold text-lpres-deep transition-colors duration-500 group-hover:text-lpres-green">
                                            {project.title}
                                        </h3>
                                        {project.excerpt && (
                                            <p className="mt-2 line-clamp-3 text-base leading-[27px] text-[#838e91]">{project.excerpt}</p>
                                        )}
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </PublicPage>
    );
}
