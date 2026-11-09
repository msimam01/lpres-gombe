import { Link } from '@inertiajs/react';

import PublicPage from '@/layouts/public-page';

interface ClusterShowProps {
    cluster: {
        name: string;
        description: string | null;
        chartImage: string | null;
        chartAltText: string;
        records: {
            sNo: number;
            lga: string;
            ward: string;
            beneficiaries: number;
        }[];
        totalBeneficiaries: number;
    };
    others: { name: string; href: string }[];
}

function formatNumber(value: number) {
    return value.toLocaleString();
}

/**
 * Value chain cluster detail: hero + heading, the authorititative uploaded
 * chart image, the beneficiary table (LGA / Ward / Number of Beneficiaries
 * with automatically-numbered S/No) and a total derived from the displayed
 * rows. The table stays usable on small screens by scrolling horizontally.
 */
export default function ClusterShow({ cluster, others }: ClusterShowProps) {
    return (
        <PublicPage
            title={cluster.name}
            breadcrumbs={[{ title: 'Value Chain Clusters', href: '/value-chains' }, { title: cluster.name, href: '' }]}
        >
            <article className="bg-white">
                <p className="text-lpres-green text-sm font-bold tracking-[1px] uppercase">Value Chain Cluster</p>
                <h2 className="text-lpres-deep mt-2 text-[34px] leading-[44px] font-extrabold tracking-[-0.4px]">{cluster.name}</h2>

                {cluster.description ? (
                    <div className="mt-6 max-w-[820px]">
                        {cluster.description
                            .split(/\r?\n\r?\n|\n{2,}/)
                            .filter((part) => part.trim().length > 0)
                            .map((paragraph, index) => (
                                <p key={index} className="mb-5 text-base leading-[30px] text-[#4b5e65] last:mb-0">
                                    {paragraph}
                                </p>
                            ))}
                    </div>
                ) : null}

                {/* Beneficiary table with derived S/No and derived total */}
                {cluster.records.length > 0 && (
                    <section aria-labelledby="beneficiary-table" className="mt-10 border border-[#e1e8ea]">
                        <div className="flex flex-wrap items-center justify-between gap-3 bg-[#f1f5f6] px-5 py-4">
                            <h3 id="beneficiary-table" className="text-lpres-deep text-lg font-extrabold">
                                Beneficiary Records
                            </h3>
                            <p className="text-lpres-deep text-sm font-bold">
                                Total: <span className="text-lpres-green">{formatNumber(cluster.totalBeneficiaries)}</span> beneficiaries
                                <span className="sr-only"> (calculated from the records below)</span>
                            </p>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[560px] border-collapse text-left text-sm">
                                <thead>
                                    <tr className="text-lpres-deep border-b border-[#e1e8ea] bg-white text-xs tracking-wider uppercase">
                                        <th scope="col" className="w-20 px-5 py-3 font-bold">S/No</th>
                                        <th scope="col" className="w-[28%] px-5 py-3 font-bold">LGA</th>
                                        <th scope="col" className="w-[34%] px-5 py-3 font-bold">Ward</th>
                                        <th scope="col" className="w-[22%] px-5 py-3 text-right font-bold">Number of Beneficiaries</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {cluster.records.map((record, index) => (
                                        <tr className="border-b border-[#e1e8ea] last:border-0" key={`${record.lga}-${record.ward}-${index}`}>
                                            <td className="px-5 py-3 text-neutral-500">{record.sNo}</td>
                                            <td className="text-lpres-deep px-5 py-3 font-semibold">{record.lga}</td>
                                            <td className="text-[#4b5e65] px-5 py-3">{record.ward}</td>
                                            <td className="px-5 py-3 text-right font-bold tabular-nums">{formatNumber(record.beneficiaries)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="bg-[#f1f5f6]">
                                        <td className="px-5 py-3" colSpan={3}>
                                            <span className="text-lpres-deep text-sm font-extrabold uppercase">Total</span>
                                        </td>
                                        <td className="text-lpres-green px-5 py-3 text-right text-base font-extrabold tabular-nums">
                                            {formatNumber(cluster.totalBeneficiaries)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>
                )}

                {/* The uploaded chart image is the authoritative visual */}
                {cluster.chartImage && (
                    <figure className="mt-10">
                        <img
                            src={cluster.chartImage}
                            alt={cluster.chartAltText}
                            width={1200}
                            height={800}
                            loading="lazy"
                            decoding="async"
                            className="border-[#e1e8ea] block w-full border object-contain"
                        />
                        <figcaption className="mt-2 text-sm text-[#838e91]">
                            The {cluster.name} cluster chart published by the project team — the uploaded
                            visual is authoritative; the table above holds the underlying records.
                        </figcaption>
                    </figure>
                )}

                {others.length > 0 && (
                    <nav aria-label="Other value chain clusters" className="mt-12">
                        <h3 className="text-lpres-deep text-xl font-extrabold">Explore Other Clusters</h3>
                        <ul className="mt-5 flex list-none flex-wrap gap-3">
                            {others.map((item) => (
                                <li key={item.href}>
                                    <Link
                                        href={item.href}
                                        className="hover:bg-lpres-deep hover:text-white inline-block border border-[#e1e8ea] bg-[#f1f5f6] px-5 py-2 text-sm font-bold text-lpres-deep transition-colors"
                                    >
                                        {item.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                )}
            </article>
        </PublicPage>
    );
}
