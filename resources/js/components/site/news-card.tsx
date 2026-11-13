import { Link } from '@inertiajs/react';
import { ArrowRight, Newspaper } from 'lucide-react';

import type { LatestNewsItem } from '@/components/site/latest-news';

/**
 * The blog card shared by the news archive and detail pages, matching the
 * homepage Latest News card look (notched green date badge, photo rising
 * above the card body, centred title + excerpt) with the same polished
 * hover: photo zoom, card lift with deep-teal shadow, green title and a
 * "Read more" affordance.
 */
export default function NewsCard({ article }: { article: LatestNewsItem }) {
    return (
        <Link
            href={article.href}
            className="group block h-full bg-[#f1f5f6] text-center no-underline transition-[transform,box-shadow] duration-500 hover:-translate-y-1.5 hover:shadow-[0_24px_44px_-20px_rgba(0,59,73,0.35)]"
        >
            <div className="relative overflow-hidden bg-[#eef3f4]">
                <span className="bg-lpres-green after:border-r-lpres-green absolute top-[15px] right-[20px] z-10 block px-[11px] pt-[3px] pb-[10px] text-xs font-extrabold text-white after:absolute after:top-full after:right-0 after:border-t-[10px] after:border-r-[10px] after:border-t-transparent">
                    {article.dateDay}
                    {article.dateMonth}
                </span>

                {article.image ? (
                    <img
                        src={article.image}
                        alt={article.title}
                        width={465}
                        height={375}
                        loading="lazy"
                        decoding="async"
                        className="block aspect-[465/375] w-full object-contain"
                    />
                ) : (
                    <div aria-hidden="true" className="flex aspect-[465/375] w-full items-center justify-center">
                        <Newspaper className="text-lpres-deep/25 size-12" aria-hidden="true" />
                    </div>
                )}
            </div>

            <div className="px-[30px] pb-8 pt-6">
                <h3 className="line-clamp-2 text-2xl leading-9 text-[#223645] transition-colors duration-500 group-hover:text-lpres-green">
                    {article.title}
                </h3>
                <p className="mt-2 line-clamp-3 text-base leading-[27px] text-[#838e91]">{article.excerpt}</p>
                <span className="text-lpres-green mt-5 inline-flex items-center gap-2 text-xs font-extrabold tracking-[1px] uppercase opacity-0 transition-all duration-500 group-hover:opacity-100">
                    Read more
                    <ArrowRight aria-hidden="true" className="size-4" />
                </span>
            </div>
        </Link>
    );
}
