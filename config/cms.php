<?php

return [
    /**
     * Maximum number of published news articles shown in the homepage
     * Latest News section (six or nine both match the original 3/4-column
     * grid; nine fills the three xl-column rows exactly).
     */
    'homepage_news_count' => (int) env('CMS_HOMEPAGE_NEWS_COUNT', 9),

    /**
     * Number of published projects shown in the homepage Projects section.
     */
    'homepage_projects_count' => (int) env('CMS_HOMEPAGE_PROJECTS_COUNT', 3),

    /**
     * Number of upcoming published events shown in the homepage Events
     * section.
     */
    'homepage_events_count' => (int) env('CMS_HOMEPAGE_EVENTS_COUNT', 3),

    /**
     * Public archive pagination size (server-side).
     */
    'archive_per_page' => (int) env('CMS_ARCHIVE_PER_PAGE', 12),
];
