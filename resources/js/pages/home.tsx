import { Head } from '@inertiajs/react';

import BeneficiaryStatistics from '@/components/site/beneficiary-statistics';
import HeroCarousel from '@/components/site/hero-carousel';
import HomeContact, { type HomeContactSettings } from '@/components/site/home-contact';
import HomeEvents, { type HomeEventItem } from '@/components/site/home-events';
import HomeProjects, { type HomeProjectItem } from '@/components/site/home-projects';
import LatestNews, { type LatestNewsItem } from '@/components/site/latest-news';
import MissionVisionValues from '@/components/site/mission-vision-values';
import Newsletter from '@/components/site/newsletter';
import PartnersSupporters from '@/components/site/partners-supporters';
import PdoIndicators from '@/components/site/pdo-indicators';
import ProjectComponents, { type ProjectComponentItem } from '@/components/site/project-components';
import SiteHeader from '@/components/site/site-header';
import SiteFooter from '@/components/site/site-footer';
import SpcQuotes from '@/components/site/spc-quotes';
import ValueChainShortcuts from '@/components/site/value-chain-shortcuts';
import WelcomeNote from '@/components/site/welcome-note';
import { heroSlides } from '@/config/site';
import BackToTop from '@/components/site/back-to-top';

interface HomeProps {
    /** Published project components (admin-managed), already ordered. */
    dashboardComponents: ProjectComponentItem[];
    /** Published news capped to the configured six or nine. */
    latestNews: LatestNewsItem[];
    /** Published projects capped by config (default three). */
    homeProjects: HomeProjectItem[];
    /** Next upcoming published events capped by config (default three). */
    upcomingEvents: HomeEventItem[];
    /** Admin-managed contact details. */
    contact: HomeContactSettings;
}

export default function Home({ dashboardComponents, latestNews, homeProjects, upcomingEvents, contact }: HomeProps) {
    return (
        <>
            <Head title="Home">
                <meta
                    name="description"
                    content="Gombe State Livestock Productivity and Resilience Support Project (L-PRES) — improving livestock productivity, resilience and commercialisation in Gombe State."
                />
                <meta name="theme-color" content="#003b49" />
                <link rel="preload" as="image" href={heroSlides[0].image} fetchPriority="high" />
            </Head>

            <div className="font-lpres text-lpres-deep flex min-h-screen flex-col bg-white">
                <SiteHeader />

                <main id="main-content" className="grow">
                    <HeroCarousel slides={heroSlides} />
                    <ValueChainShortcuts />
                    <WelcomeNote />
                    <ProjectComponents components={dashboardComponents} />
                    <PdoIndicators />
                    <BeneficiaryStatistics />
                    <MissionVisionValues />
                    <PartnersSupporters />
                    <SpcQuotes />
                    <HomeProjects projects={homeProjects} />
                    <LatestNews articles={latestNews} />
                    <HomeEvents events={upcomingEvents} />
                    <HomeContact contact={contact} />
                    <Newsletter />
                </main>

                <SiteFooter />
                <BackToTop />
            </div>
        </>
    );
}
