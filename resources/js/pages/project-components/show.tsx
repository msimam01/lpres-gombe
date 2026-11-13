import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Layers } from 'lucide-react';

import PublicPage from '@/layouts/public-page';

interface ComponentDetail {
    title: string;
    description: string | null;
    image: string | null;
    updated: string | null;
    metaDescription: string | null;
}

interface OtherComponent {
    title: string;
    href: string;
    image: string | null;
}

interface ProjectComponentShowProps {
    component: ComponentDetail;
    others: OtherComponent[];
}

/**
 * One published project component. The featured image is shown whole (contain
 * inside a tinted frame) rather than cropped, and the full administrator
 * description is rendered paragraph by paragraph. Opens, refreshes and shares
 * fine because the page is a normal server-side Inertia route.
 */
export default function ProjectComponentShow({ component, others }: ProjectComponentShowProps) {
    const paragraphs = (component.description ?? '')
        .split(/\r?\n\r?\n|\n{2,}/)
        .map((part) => part.trim())
        .filter((part) => part.length > 0);

    return (
        <PublicPage
            title={component.title}
            description={component.metaDescription ?? undefined}
            eyebrow="L-PRES Project"
            breadcrumbs={[{ title: 'Project Components', href: '/#project-components' }, { title: 'Component', href: '' }]}
        >
            <article className="mx-auto w-full max-w-[900px]">
                {component.image && (
                    <div className="border-[#e1e8ea] mb-10 border bg-[#eef3f4]">
                        <img
                            src={component.image}
                            alt={component.title}
                            width={1200}
                            height={800}
                            className="mx-auto block max-h-[620px] w-full object-contain"
                            loading="eager"
                            decoding="async"
                        />
                    </div>
                )}

                <p className="text-lpres-green text-sm font-bold tracking-[1px] uppercase">Project Component</p>
                <h2 className="text-lpres-deep mt-2 text-[34px] leading-[1.2] font-extrabold tracking-[-0.4px] max-[1199px]:text-[28px]">
                    {component.title}
                </h2>

                {paragraphs.length > 0 ? (
                    <div className="mt-8">
                        {paragraphs.map((paragraph, index) => (
                            <p key={index} className="mb-5 text-base leading-[30px] text-[#4b5e65] last:mb-0">
                                {paragraph}
                            </p>
                        ))}
                    </div>
                ) : (
                    <p className="mt-8 border-l-4 border-lpres-green bg-[#f1f5f6] p-6 text-base leading-[27px] text-[#4b5e65]">
                        A full description for this component is being prepared.
                    </p>
                )}

                <div className="border-[#e1e8ea] mt-10 flex flex-wrap items-center justify-between gap-4 border-t pt-6">
                    <Link
                        href="/#project-components"
                        className="text-lpres-deep hover:text-lpres-green focus-visible:outline-lpres-green inline-flex items-center gap-2 text-sm font-bold underline decoration-lpres-green decoration-2 underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4"
                    >
                        <ArrowLeft aria-hidden="true" className="size-4" />
                        Back to the homepage components
                    </Link>
                    {component.updated && <p className="text-xs text-[#6f7b7e]">Updated {component.updated}</p>}
                </div>
            </article>

            {others.length > 0 && (
                <section aria-labelledby="other-components" className="mx-auto mt-16 w-full max-w-[900px]">
                    <h3 id="other-components" className="text-lpres-deep text-xl font-extrabold">
                        Other components
                    </h3>
                    <ul className="mt-5 grid list-none gap-5 sm:grid-cols-2">
                        {others.map((other) => (
                            <li key={other.href}>
                                <Link
                                    href={other.href}
                                    className="group border-[#e1e8ea] focus-visible:outline-lpres-green flex h-full items-center gap-4 border bg-white no-underline transition-[border-color,box-shadow] duration-500 hover:border-transparent hover:shadow-[0_20px_40px_-24px_rgba(0,59,73,0.4)] focus-visible:outline-2 focus-visible:outline-offset-4"
                                >
                                    <div className="bg-[#eef3f4] flex h-24 w-28 shrink-0 items-center justify-center overflow-hidden">
                                        {other.image ? (
                                            <img
                                                src={other.image}
                                                alt=""
                                                width={160}
                                                height={120}
                                                loading="lazy"
                                                decoding="async"
                                                className="h-full w-full object-contain"
                                            />
                                        ) : (
                                            <Layers aria-hidden="true" className="text-lpres-deep/25 size-7" />
                                        )}
                                    </div>
                                    <span className="text-lpres-deep group-hover:text-lpres-green flex items-center gap-2 pr-4 text-sm leading-6 font-bold transition-colors duration-500">
                                        {other.title}
                                        <ArrowRight
                                            aria-hidden="true"
                                            className="size-4 shrink-0 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transition-none"
                                        />
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
