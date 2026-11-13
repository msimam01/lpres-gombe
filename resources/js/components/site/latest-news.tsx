import { Link } from '@inertiajs/react';
import { ArrowRight, Newspaper } from 'lucide-react';

import SectionHeading from '@/components/site/section-heading';

export interface LatestNewsItem {
    id: number;
    title: string;
    href: string;
    excerpt: string | null;
    image: string | null;
    published: string | null;
    dateDay: string | null;
    dateMonth: string | null;
}

interface LatestNewsProps {
    /** Backend-provided published news (already capped by config). */
    articles: LatestNewsItem[];
    /** Whether the archive has more records than shown (drives View More). */
    showViewMore?: boolean;
}

/**
 * Latest News grid. The original L-PRES card language is retained — light
 * grey body with the 465x375 photograph rising 121px above it into the
 * gutter, and the green notched date badge at the card's top-right. The
 * badge now stacks day over month, and is skipped when a record has no date.
 * The full published date is exposed to screen readers. Hover affordances
 * also fire on keyboard focus and stay visible on touch devices, and card
 * motion is disabled under prefers-reduced-motion. Cards come from Laravel
 * (published news, capped by config) and link internally to each article.
 */
export default function LatestNews({ articles, showViewMore = true }: LatestNewsProps) {
    return (
        <section aria-labelledby="latest-news-heading" className="pt-[120px] pb-[120px] max-[1199px]:pt-[70px] max-[1199px]:pb-[70px]">
            <div className="mx-auto w-full max-w-[1200px] px-5 sm:px-6 lg:px-0">
                <SectionHeading eyebrow="Gombe L-PRES" title="Latest News" id="latest-news-heading" />

                {articles.length > 0 ? (
                    <ul className="grid list-none grid-cols-1 gap-x-[30px] gap-y-[70px] md:grid-cols-2 lg:grid-cols-3">
                        {articles.map((article) => {
                            const hasDate = Boolean(article.dateDay || article.dateMonth);

                            return (
                                <li key={article.id} className="mt-[155px]">
                                    <article className="group relative bg-[#f1f5f6] text-center transition-[transform,box-shadow] duration-500 hover:-translate-y-1.5 hover:shadow-[0_24px_44px_-20px_rgba(0,59,73,0.35)] motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                                        {hasDate && (
                                            <span
                                                aria-hidden="true"
                                                className="bg-lpres-green after:border-r-lpres-green absolute top-[-85px] right-[20px] z-10 flex flex-col items-center px-[11px] pt-[5px] pb-[10px] leading-none font-extrabold text-white after:absolute after:top-full after:right-0 after:border-t-[10px] after:border-r-[10px] after:border-t-transparent"
                                            >
                                                <span className="text-xl">{article.dateDay}</span>
                                                <span className="mt-1 text-[11px] tracking-[1px] uppercase">{article.dateMonth}</span>
                                            </span>
                                        )}

                                        <Link
                                            href={article.href}
                                            className="focus-visible:outline-lpres-green block focus-visible:outline-2 focus-visible:outline-offset-4"
                                        >
                                            {/* Homepage news shows the whole photograph inside a
                                                tinted frame, so nothing is cut off. */}
                                            <div className="mx-[30px] overflow-hidden bg-[#eef3f4]">
                                                {article.image ? (
                                                    <img
                                                        src={article.image}
                                                        alt={article.title}
                                                        width={465}
                                                        height={375}
                                                        loading="lazy"
                                                        decoding="async"
                                                        className="mt-[-121px] h-[375px] w-full object-contain"
                                                    />
                                                ) : (
                                                    <div
                                                        aria-hidden="true"
                                                        className="bg-lpres-deep mt-[-121px] flex h-[375px] w-full items-center justify-center"
                                                    >
                                                        <Newspaper aria-hidden="true" className="size-16 text-white/30" />
                                                    </div>
                                                )}
                                            </div>

                                            <div className="px-[30px] pt-6 pb-8">
                                                {article.published && <p className="sr-only">Published {article.published}</p>}
                                                <h3 className="group-hover:text-lpres-green group-focus-within:text-lpres-green line-clamp-2 text-2xl leading-9 text-[#223645] transition-colors duration-500">
                                                    {article.title}
                                                </h3>
                                                {article.excerpt && (
                                                    <p className="mt-2 line-clamp-3 text-base leading-[27px] text-[#6f7b7e]">{article.excerpt}</p>
                                                )}
                                                <span className="text-lpres-green mt-5 inline-flex items-center gap-2 text-xs font-extrabold tracking-[1px] uppercase opacity-0 transition-all duration-500 group-focus-within:opacity-100 group-hover:opacity-100 [@media(hover:none)]:opacity-100">
                                                    Read more
                                                    <ArrowRight aria-hidden="true" className="size-4" />
                                                </span>
                                            </div>
                                        </Link>
                                    </article>
                                </li>
                            );
                        })}
                    </ul>
                ) : (
                    <div className="border-t border-[#e1e8ea] bg-white px-8 py-16 text-center">
                        <Newspaper aria-hidden="true" className="text-lpres-green mb-4 inline-block size-10" />
                        <p className="mx-auto max-w-[560px] text-lg leading-7 text-[#6f7b7e]">
                            News articles are being prepared. New stories will appear here as soon as they are published in the admin panel.
                        </p>
                    </div>
                )}

                {showViewMore && (
                    <div className="mt-12 text-center">
                        <Link
                            href="/news"
                            className="focus-visible:outline-lpres-deep group border-lpres-green text-lpres-deep hover:bg-lpres-green inline-flex h-[52px] items-center gap-3 border px-8 text-sm font-extrabold tracking-[0.5px] uppercase transition-colors duration-500 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-none"
                        >
                            View More
                            <ArrowRight aria-hidden="true" className="size-4 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transition-none" />
                        </Link>
                    </div>
                )}
            </div>
        </section>
    );
}
