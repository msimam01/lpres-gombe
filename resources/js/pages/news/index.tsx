import PublicPage from '@/layouts/public-page';
import EmptyState from '@/components/site/empty-state';
import NewsCard from '@/components/site/news-card';
import Pagination from '@/components/site/pagination';

import type { LatestNewsItem } from '@/components/site/latest-news';

export interface ArticlesPaginator {
    data: LatestNewsItem[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface NewsIndexProps {
    articles: ArticlesPaginator;
}

export default function NewsIndex({ articles }: NewsIndexProps) {
    return (
        <PublicPage
            title="Latest News"
            eyebrow="Newsroom"
            description="Press statements, milestones and updates from the Gombe State Livestock Productivity and Resilience Support Project."
        >
            {articles.data.length > 0 ? (
                <>
                    <div className="grid gap-x-8 gap-y-12 sm:grid-cols-2">
                        {articles.data.map((article) => (
                            <NewsCard key={article.id} article={article} />
                        ))}
                    </div>
                    <Pagination paginator={articles} />
                </>
            ) : (
                <EmptyState message="No news has been published yet. Articles from across Gombe State L-PRES will appear here once staff publish them from the admin panel." />
            )}
        </PublicPage>
    );
}
