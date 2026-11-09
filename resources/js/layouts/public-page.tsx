import { Head } from '@inertiajs/react';

import BackToTop from '@/components/site/back-to-top';
import SiteBanner from '@/components/site/site-banner';
import SiteHeader from '@/components/site/site-header';
import SiteFooter from '@/components/site/site-footer';

import type { BreadcrumbItem } from '@/types';
import type { ReactNode } from 'react';

interface PublicPageProps {
    /** Document title + banner <h1>. */
    title: string;
    /** Document <meta name="description">. */
    description?: string;
    breadcrumbs?: BreadcrumbItem[];
    /** Small green label rendered above the banner title. */
    eyebrow?: string;
    children: ReactNode;
}

/**
 * Scaffold for every public inner page: sticky header, page banner with
 * breadcrumbs, footer and one shared back-to-top control. Content uses the
 * full page width; the repeated "Recent News" sidebar has been retired (the
 * homepage news section and the dedicated /news page remain).
 */
export default function PublicPage({ title, description, breadcrumbs, eyebrow, children }: PublicPageProps) {
    return (
        <>
            <Head title={`Gombe State L-PRES — ${title}`}>
                {description && <meta name="description" content={description} />}
                <meta name="theme-color" content="#003b49" />
            </Head>

            <div className="font-lpres text-lpres-deep flex min-h-screen flex-col bg-white">
                <SiteHeader />

                <main id="main-content" className="grow">
                    <SiteBanner title={title} breadcrumbs={breadcrumbs} eyebrow={eyebrow} />

                    <div className="mx-auto w-full max-w-[1200px] px-5 py-12 sm:px-6 lg:px-0">{children}</div>
                </main>

                <SiteFooter />
                <BackToTop />
            </div>
        </>
    );
}
