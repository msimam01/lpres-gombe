import { Link } from '@inertiajs/react';

import type { BreadcrumbItem } from '@/types';

interface SiteBannerProps {
    title: string;
    breadcrumbs?: BreadcrumbItem[];
    /** Small green label above the title, e.g. "GET TO KNOW US". */
    eyebrow?: string;
    /** Optional one-line lead sentence under the title. */
    lead?: string;
}

/**
 * Shared page hero used by every inner public page: deep-teal image
 * background with a subtle patterned wash, green eyebrow label, breadcrumb
 * navigation and a large page title. Responsive type scale; decorative
 * layers are aria-hidden and never obscure the text.
 */
export default function SiteBanner({ title, breadcrumbs, eyebrow, lead }: SiteBannerProps) {
    return (
        <div
            aria-labelledby="page-banner"
            className="relative isolate overflow-hidden bg-lpres-deep bg-cover bg-center py-14 lg:py-20"
            style={{ backgroundImage: "url('/images/lpres/department-bg.png')" }}
        >
            {/* Subtle lightening + dotted pattern so headings stay readable over the photo */}
            <div aria-hidden="true" className="absolute inset-0 -z-10 bg-gradient-to-b from-black/20 via-[#003b49]/70 to-[#003b49]/85" />
            <div
                aria-hidden="true"
                className="absolute inset-0 -z-10 opacity-[0.22]"
                style={{
                    backgroundImage: 'radial-gradient(rgba(255,255,255,0.55) 1.5px, transparent 1.5px)',
                    backgroundSize: '26px 26px',
                    maskImage: 'linear-gradient(105deg, transparent 30%, black 75%)',
                    WebkitMaskImage: 'linear-gradient(105deg, transparent 30%, black 75%)',
                }}
            />

            <div className="mx-auto max-w-[1200px] px-5 sm:px-6 lg:px-0">
                <nav aria-label="Breadcrumb" className="mb-4 text-sm text-white/65">
                    <ol className="m-0 flex list-none flex-wrap items-center gap-1.5">
                        <li>
                            <Link href="/" className="hover:text-lpres-green transition-colors">
                                Home
                            </Link>
                        </li>
                        {(breadcrumbs ?? [{ title, href: '' }]).map((crumb) => (
                            <li key={crumb.title} aria-current={crumb.href ? undefined : 'page'} className="flex items-center gap-1.5">
                                <span aria-hidden="true">›</span>
                                {crumb.href ? (
                                    <Link href={crumb.href} className="hover:text-lpres-green transition-colors">
                                        {crumb.title}
                                    </Link>
                                ) : (
                                    <span className="text-lpres-green">{crumb.title}</span>
                                )}
                            </li>
                        ))}
                    </ol>
                </nav>

                {eyebrow && (
                    <p className="text-lpres-green mb-2 flex items-center gap-3 text-sm font-bold tracking-[1.5px] uppercase">
                        <span aria-hidden="true" className="bg-lpres-green inline-block h-0.5 w-8" />
                        {eyebrow}
                    </p>
                )}

                <h1
                    id="page-banner"
                    tabIndex={0}
                    className="focus-visible:outline-lpres-green text-3xl font-extrabold tracking-[-0.4px] text-white sm:text-4xl lg:text-5xl lg:leading-[1.1]"
                >
                    {title}
                </h1>
                {lead && <p className="mt-4 max-w-[640px] text-base leading-7 text-white/80 lg:text-lg">{lead}</p>}
            </div>
        </div>
    );
}
