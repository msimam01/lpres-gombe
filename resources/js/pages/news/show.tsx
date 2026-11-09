import { Link } from '@inertiajs/react';

import AssociatedMedia from '@/components/site/associated-media';
import ImageGallery from '@/components/site/image-gallery';
import PublicPage from '@/layouts/public-page';

interface ArticleItem {
    title: string;
    href: string;
    published: string | null;
}

interface NewsShowProps {
    article: {
        title: string;
        excerpt: string | null;
        /** Plain-text body (escaped at render; paragraphs split on blank lines). */
        body: string;
        image: string | null;
        published: string | null;
        /** Images embedded in the article body, in author order. */
        gallery: string[];
    };
    moreNews: ArticleItem[];
    mediaGalleries: { title: string; href: string; imageCount: number; cover: string | null }[];
    mediaVideos: { title: string; caption: string | null; videoUrl: string; thumbnail: string | null }[];
    mediaImages: { title: string; caption: string | null; src: string | null }[];
}

export default function NewsShow({ article, moreNews, mediaGalleries, mediaVideos, mediaImages }: NewsShowProps) {
    return (
        <PublicPage
            title={article.title}
            description={article.excerpt ?? undefined}
            breadcrumbs={[{ title: 'Latest News', href: '/news' }, { title: 'Article', href: '' }]}
        >
            <article className="bg-white">
                {article.image && (
                    <img src={article.image} alt={article.title} width={1200} height={675} className="mb-8 block w-full object-cover" loading="lazy" decoding="async" />
                )}

                <p className="text-lpres-green text-sm font-bold tracking-[1px] uppercase">{article.published ?? 'Published'}</p>
                <h2 className="text-lpres-deep mt-2 text-[34px] leading-[44px] font-extrabold tracking-[-0.4px]">{article.title}</h2>

                <div className="mt-8">
                    {article.body
                        .split(/\r?\n\r?\n|\n{2,}/)
                        .map((paragraph, index) => (
                            <p key={index} className="mb-5 text-base leading-[30px] text-[#4b5e65] last:mb-0">
                                {paragraph}
                            </p>
                        ))}
                </div>

                {article.gallery.length > 0 && (
                    <section aria-label="Article photo gallery" className="mt-10">
                        <div className="mt-5">
                            <ImageGallery
                                items={article.gallery.map((src, index) => ({
                                    src,
                                    alt: `${article.title} — image ${index + 1}`,
                                }))}
                            />
                        </div>
                    </section>
                )}

                {article.excerpt && (
                    <p className="mt-8 border-l-4 border-lpres-green bg-[#f1f5f6] p-6 text-base leading-[27px] text-[#4b5e65]">{article.excerpt}</p>
                )}

                <AssociatedMedia galleries={mediaGalleries} videos={mediaVideos} images={mediaImages} />
            </article>

            {moreNews.length > 0 && (
                <section aria-labelledby="more-news" className="mt-16">
                    <h3 id="more-news" className="text-lpres-deep text-xl font-extrabold">
                        More News
                    </h3>
                    <ul className="mt-5 list-none divide-y divide-[#e1e8ea] border-y border-[#e1e8ea]">
                        {moreNews.map((item) => (
                            <li key={item.href} className="py-4">
                                <Link href={item.href} className="group flex flex-col gap-1 no-underline">
                                    <span className="text-lpres-green text-xs font-bold tracking-[0.5px] uppercase">{item.published ?? 'Published'}</span>
                                    <span className="group-hover:text-lpres-green text-base leading-6 font-bold text-lpres-deep transition-colors">{item.title}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </PublicPage>
    );
}
