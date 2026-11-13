import { Link } from '@inertiajs/react';
import { ArrowRight, FolderOpen } from 'lucide-react';

import SectionHeading from '@/components/site/section-heading';

export interface HomeProjectItem {
    id: number;
    title: string;
    href: string;
    excerpt: string | null;
    image: string | null;
    category: string | null;
}

/**
 * Homepage Projects section: admin-managed project records (capped by
 * config). The photograph zooms gently under a category chip while the card
 * lifts with a deep-teal shadow; the body shows title, clamped excerpt and
 * a green "Explore project" affordance. The category chip also shows on the
 * placeholder tile when a project has no photograph. Hidden entirely when no
 * projects are published — nothing is fabricated.
 */
export default function HomeProjects({ projects }: { projects: HomeProjectItem[] }) {
    if (projects.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="home-projects-heading"
            className="bg-[#eef5f0] pt-[120px] pb-[120px] max-[1199px]:pt-[70px] max-[1199px]:pb-[70px]"
        >
            <div className="mx-auto w-full max-w-[1200px] px-5 sm:px-6 lg:px-0">
                <SectionHeading eyebrow="Our Work" title="Projects" id="home-projects-heading" />

                <ul className="grid list-none gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    {projects.map((project) => (
                        <li key={project.id}>
                            <Link
                                href={project.href}
                                className="group focus-visible:outline-lpres-green block h-full border border-[#e1e8ea] bg-white no-underline transition-[transform,box-shadow] duration-500 hover:-translate-y-1.5 hover:border-transparent hover:shadow-[0_24px_44px_-20px_rgba(0,59,73,0.35)] focus-visible:outline-2 focus-visible:outline-offset-4 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                            >
                                {/* Uniform 465/375 frame, fully covered by the photo. */}
                                <div className="relative overflow-hidden border-b border-[#e1e8ea] bg-[#eef3f4]">
                                    {project.image ? (
                                        <img
                                            src={project.image}
                                            alt={project.title}
                                            width={465}
                                            height={375}
                                            loading="lazy"
                                            decoding="async"
                                            className="block aspect-[465/375] w-full object-cover"
                                        />
                                    ) : (
                                        <div
                                            aria-hidden="true"
                                            className="flex aspect-[465/375] w-full items-center justify-center"
                                        >
                                            <FolderOpen className="text-lpres-deep/25 size-12" aria-hidden="true" />
                                        </div>
                                    )}

                                    {project.category && (
                                        <span className="bg-lpres-deep/80 group-hover:bg-lpres-green group-focus-visible:bg-lpres-green absolute top-4 left-4 rounded-sm px-3 py-1 text-[11px] font-extrabold tracking-[1px] text-white uppercase backdrop-blur-sm transition-colors duration-500">
                                            <span className="sr-only">Category: </span>
                                            {project.category}
                                        </span>
                                    )}
                                </div>

                                <div className="px-[30px] py-7">
                                    <h3 className="text-lpres-deep group-hover:text-lpres-green group-focus-visible:text-lpres-green line-clamp-2 text-xl leading-8 font-extrabold transition-colors duration-500">
                                        {project.title}
                                    </h3>
                                    {project.excerpt && (
                                        <p className="mt-2 line-clamp-3 border-t border-[#e1e8ea] pt-3 text-[15px] leading-[26px] text-[#6f7b7e]">
                                            {project.excerpt}
                                        </p>
                                    )}

                                    <span className="text-lpres-green mt-5 inline-flex items-center gap-2 text-xs font-extrabold tracking-[1px] uppercase opacity-0 transition-all duration-500 group-hover:opacity-100 group-focus-visible:opacity-100 [@media(hover:none)]:opacity-100">
                                        Explore project
                                        <ArrowRight aria-hidden="true" className="size-4" />
                                    </span>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>

                <div className="mt-12 text-center">
                    <Link
                        href="/our-projects"
                        className="focus-visible:outline-lpres-deep group border-lpres-green text-lpres-deep hover:bg-lpres-green inline-flex h-[52px] items-center gap-3 border px-8 text-sm font-extrabold tracking-[0.5px] uppercase transition-colors duration-500 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-none"
                    >
                        View All Projects
                        <ArrowRight aria-hidden="true" className="size-4 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transition-none" />
                    </Link>
                </div>
            </div>
        </section>
    );
}
