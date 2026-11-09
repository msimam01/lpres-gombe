import { Link, usePage } from '@inertiajs/react';

import { valueChains } from '@/config/site';

export default function ValueChainShortcuts() {
    // Hrefs are resolved server-side from the published value chain
    // clusters, so a shortcut only deep-links when its detail page exists;
    // otherwise it falls back to the value-chains listing page and can
    // never 404.
    const hrefs = (usePage().props.valueChainHrefs ?? {}) as Record<string, string>;
    return (
        <section
            aria-labelledby="value-chains-heading"
            style={{ backgroundImage: "url('/images/lpres/department-bg.png')" }}
            className="bg-lpres-mist w-full bg-repeat"
        >
            <div className="mx-auto w-full max-w-[1176px] bg-white px-5 pt-24 pb-20 sm:px-6 sm:pt-28 sm:pb-24">
                <h2 id="value-chains-heading" className="sr-only">
                    Livestock value chains
                </h2>

                <ul className="mx-auto grid w-full max-w-[784px] list-none grid-cols-2 gap-y-12 md:grid-cols-4 md:gap-y-0">
                    {valueChains.map((valueChain) => (
                        <li key={valueChain.id} className="flex justify-center">
                            <Link
                                href={hrefs[valueChain.id] ?? '/value-chains'}
                                className="group focus-visible:ring-lpres-deep flex w-[166px] flex-col items-center rounded-sm text-center focus-visible:ring-2 focus-visible:ring-offset-4 focus-visible:ring-offset-white focus-visible:outline-none"
                            >
                                <span className="mb-5 flex size-20 shrink-0 items-center justify-center rounded-full bg-white shadow-[0_0_10px_rgba(0,0,0,0.07)] transition-shadow duration-300 group-hover:shadow-[0_0_16px_rgba(0,59,73,0.16)]">
                                    <img src={valueChain.icon} alt="" aria-hidden="true" width={80} height={80} loading="lazy" className="size-20" />
                                </span>
                                <span className="text-lpres-deep max-w-[88px] text-xl leading-6 font-extrabold">{valueChain.label}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
