import PublicPage from '@/layouts/public-page';
import EmptyState from '@/components/site/empty-state';
import VisualReportsGallery from '@/components/site/visual-reports-gallery';

interface ReportCard {
    id: number;
    title: string;
    description: string | null;
    image: string | null;
    file: string | null;
}

interface VisualReportsProps {
    reports: ReportCard[];
}

/**
 * Visual Reports page: an image-based report gallery. Visuals are uploaded
 * dashboard/chart images managed in the admin panel; the gallery opens each
 * image in an accessible lightbox (keyboard navigable) with an optional
 * underlaid file download. No dummy records — an empty state is shown until
 * staff publish reports.
 */
export default function VisualReports({ reports }: VisualReportsProps) {
    return (
        <PublicPage
            title="Visual Reports"
            eyebrow="Knowledge & Results"
            description="Visual reports and publications from the Gombe State L-PRES project."
        >
            {reports.length > 0 ? (
                <VisualReportsGallery reports={reports} />
            ) : (
                <EmptyState message="No visual reports have been published yet. Reports added in the admin panel will appear here with their visuals and files." />
            )}
        </PublicPage>
    );
}
