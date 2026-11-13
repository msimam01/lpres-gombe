import { Link } from '@inertiajs/react';
import { ArrowRight, Layers } from 'lucide-react';

import { projectComponents as section } from '@/config/site';

export interface ProjectComponentItem {
    id: number;
    title: string;
    href: string;
    description: string | null;
    excerpt: string | null;
    image: string | null;
}

interface ProjectComponentsProps {
    /** Published component rows managed through the admin panel (already ordered). */
    components: ProjectComponentItem[];
}

/**
 * Homepage Project Components. Every card is an administrator-managed record:
 * its title, photograph, description, order and visibility come from Laravel,
 * and each card links to that component's own public page. Photographs sit
 * inside a fixed frame using object-contain, so the whole image is always
 * visible whatever its aspect ratio.
 */
export default function ProjectComponents({ components }: ProjectComponentsProps) {
    return (
        <section aria-labelledby="project-components-heading" className="bg-white">
            <div className="bg-lpres-green w-full">
                <div className="mx-auto flex min-h-[76px] w-full max-w-[1200px] items-center gap-5 px-5 py-3 sm:min-h-[90px] sm:gap-[35px] sm:px-6 sm:py-0 lg:px-0">
                    <span aria-hidden="true" className="lpres-valve-icon shrink-0 text-[44px] leading-none text-white sm:text-[60px]" />

                    <h2 id="project-components-heading" className="text-white">
                        <span className="block text-base leading-[30px] font-normal">{section.eyebrow}</span>
                        <span className="block text-[26px] leading-8 font-extrabold tracking-[-0.4px] sm:text-[34px] sm:leading-10">
                            {section.heading}
                        </span>
                    </h2>
                </div>
            </div>

            <div className="mx-auto w-full max-w-[1200px] px-5 pt-14 pb-20 sm:px-6 lg:pt-[70px] lg:pb-24 lg:px-0">
                {components.length > 0 ? (
                    <ul className="grid list-none grid-cols-1 gap-7 sm:grid-cols-2 lg:grid-cols-3">
                        {components.map((component, index) => (
                            <li key={component.id}>
                                <Link
                                    href={component.href}
                                    className="group border-[#e1e8ea] focus-visible:outline-lpres-green flex h-full flex-col border bg-white no-underline transition-[transform,box-shadow,border-color] duration-500 hover:-translate-y-1.5 hover:border-transparent hover:shadow-[0_24px_44px_-20px_rgba(0,59,73,0.35)] focus-visible:outline-2 focus-visible:outline-offset-4 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                                >
                                    <div className="relative overflow-hidden border-b border-[#e1e8ea] bg-[#eef3f4]">
                                        <span className="bg-lpres-deep absolute top-0 left-0 z-10 px-3 py-1.5 text-[11px] font-extrabold tracking-[1px] text-white uppercase">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>

                                        {component.image ? (
                                            <img
                                                src={component.image}
                                                alt={component.title}
                                                width={480}
                                                height={360}
                                                loading="lazy"
                                                decoding="async"
                                                className="block aspect-[4/3] w-full object-contain"
                                            />
                                        ) : (
                                            <div
                                                aria-hidden="true"
                                                className="text-lpres-deep/25 flex aspect-[4/3] w-full items-center justify-center"
                                            >
                                                <Layers className="size-12" aria-hidden="true" />
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex grow flex-col px-6 py-7">
                                        <p className="text-lpres-green text-[11px] font-extrabold tracking-[1px] uppercase">
                                            Component {index + 1}
                                        </p>
                                        <h3 className="text-lpres-deep group-hover:text-lpres-green mt-2 line-clamp-3 text-lg leading-7 font-extrabold transition-colors duration-500">
                                            {component.title}
                                        </h3>
                                        {component.excerpt && (
                                            <p className="mt-3 line-clamp-3 text-sm leading-6 text-[#6f7b7e]">{component.excerpt}</p>
                                        )}

                                        <span className="text-lpres-deep group-hover:text-lpres-green mt-auto inline-flex items-center gap-2 pt-6 text-xs font-extrabold tracking-[1px] uppercase transition-colors duration-500">
                                            Learn more
                                            <ArrowRight
                                                aria-hidden="true"
                                                className="size-4 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transition-none"
                                            />
                                        </span>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div aria-live="polite" className="border-[#e1e8ea] bg-[#f7fafb] border px-8 py-14 text-center">
                        <Layers aria-hidden="true" className="text-lpres-green mx-auto mb-4 size-10" />
                        <p className="mx-auto max-w-[560px] text-base leading-7 text-[#6f7b7e]">
                            Project components will appear here once they are published in the admin panel.
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}
