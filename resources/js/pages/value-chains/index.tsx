import PublicPage from '@/layouts/public-page';
import EmptyState from '@/components/site/empty-state';
import { Link } from '@inertiajs/react';

interface ClusterCard {
    id: number;
    name: string;
    href: string;
    excerpt: string | null;
    image: string | null;
    recordCount: number;
    totalBeneficiaries: number;
}

interface ValueChainsIndexProps {
    clusters: ClusterCard[];
}

/**
 * Value Chain Clusters landing page. Each card shows the cluster's uploaded
 * chart/image (the authoritative visual) together with the record count and
 * the total number of beneficiaries held in its table.
 */
export default function ValueChainsIndex({ clusters }: ValueChainsIndexProps) {
    return (
        <PublicPage
            title="Value Chain Clusters"
            eyebrow="Our Value Chains"
            description="The commodity value-chain clusters supported by the Gombe State L-PRES project."
        >
            {clusters.length > 0 ? (
                <ul className="grid list-none gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    {clusters.map((cluster) => (
                        <li key={cluster.id}>
                            <Link href={cluster.href} className="group block h-full bg-[#f1f5f6] text-center no-underline">
                                {cluster.image ? (
                                    <img
                                        src={cluster.image}
                                        alt={`Cluster chart for ${cluster.name}`}
                                        width={370}
                                        height={280}
                                        loading="lazy"
                                        decoding="async"
                                        className="block aspect-[370/280] w-full object-cover transition-[filter] duration-500 group-hover:brightness-110"
                                    />
                                ) : (
                                    <div aria-hidden="true" className="block aspect-[370/280] w-full bg-lpres-deep" />
                                )}

                                <div className="px-6 py-6">
                                    <h2 className="text-xl leading-8 font-extrabold text-lpres-deep transition-colors duration-500 group-hover:text-lpres-green">
                                        {cluster.name}
                                    </h2>
                                    {cluster.excerpt && <p className="mt-2 text-base leading-[27px] text-[#838e91]">{cluster.excerpt}</p>}

                                    <p className="mt-4 text-sm font-bold text-lpres-deep">
                                        {cluster.recordCount} {cluster.recordCount === 1 ? 'record' : 'records'}
                                        <span className="text-neutral-400"> · </span>
                                        {cluster.totalBeneficiaries.toLocaleString()} beneficiaries
                                    </p>

                                    <span className="text-lpres-green mt-4 inline-block text-xs font-bold tracking-[1px] uppercase transition-colors duration-500 group-hover:text-lpres-deep">
                                        View the cluster
                                    </span>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyState message="No value chain clusters have been published yet. Clusters added in the admin panel will appear here." />
            )}
        </PublicPage>
    );
}
