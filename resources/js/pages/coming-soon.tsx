import { Head, Link } from '@inertiajs/react';

import SiteHeader from '@/components/site/site-header';

interface ComingSoonProps {
    title?: string;
}

export default function ComingSoon({ title = 'Coming soon' }: ComingSoonProps) {
    return (
        <>
            <Head title={title} />

            <div className="font-lpres text-lpres-deep flex min-h-screen flex-col bg-white">
                <SiteHeader />

                <main className="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center justify-center px-6 py-24 text-center">
                    <p className="text-lpres-green text-xs font-bold tracking-[0.22em] uppercase">Coming soon</p>
                    <h1 className="mt-4 text-4xl leading-tight font-extrabold tracking-[-0.02em] sm:text-5xl">{title}</h1>
                    <p className="text-lpres-deep/70 mt-5 max-w-xl text-base leading-7">
                        This section is part of the L-PRES website rebuild and is scheduled for a later phase. The homepage header, hero carousel and
                        value-chain shortcuts are available now.
                    </p>
                    <Link
                        href="/"
                        className="bg-lpres-deep hover:bg-lpres-ink focus-visible:ring-lpres-green mt-8 inline-flex min-h-11 items-center justify-center px-6 py-3 text-sm font-bold text-white transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        Return to homepage
                    </Link>
                </main>
            </div>
        </>
    );
}
