import EmptyState from '@/components/site/empty-state';

export interface ChartPoint {
    label: string;
    value: number;
}

interface BeneficiaryChartProps {
    /** Cluster-specific chart heading, e.g. "Beef cluster beneficiary distribution". */
    title: string;
    series: ChartPoint[];
    total: number;
    recordCount: number;
}

const AXIS_STEPS = [1, 2, 2.5, 5, 10];

/** Rounds a raw axis interval up to a readable step (1, 2, 2.5, 5, 10 × 10ⁿ). */
function niceStep(rough: number): number {
    const magnitude = 10 ** Math.floor(Math.log10(Math.max(rough, 1)));
    const normalized = rough / magnitude;
    const nice = AXIS_STEPS.find((candidate) => normalized <= candidate) ?? 10;
    return Math.max(1, nice * magnitude);
}

/** Evenly spaced, whole-number axis ticks from 0 to the first step above the peak. */
function axisTicks(maxValue: number): number[] {
    if (maxValue <= 0) {
        return [0, 1, 2, 3, 4];
    }

    const step = niceStep(maxValue / 4);
    const top = Math.ceil(maxValue / step) * step;

    const ticks: number[] = [];
    for (let value = 0; value <= top + 0.0001; value += step) {
        ticks.push(Math.round(value));
    }

    return ticks;
}

/** Keeps very long LGA/ward labels inside the rotated label band. */
function shortenLabel(label: string, limit = 28): string {
    return label.length > limit ? `${label.slice(0, limit - 1).trimEnd()}…` : label;
}

/**
 * Vertical bar chart of a cluster's beneficiary records: LGA + ward labels
 * along the horizontal axis, beneficiary counts on the vertical axis, counts
 * above each bar, grid lines, a cluster-specific title and a horizontal
 * scroll area so many wards never squeeze the labels. Bars and the total are
 * derived from the same records as the page's beneficiary table.
 */
export default function BeneficiaryChart({ title, series, total, recordCount }: BeneficiaryChartProps) {
    const peak = series.reduce((max, point) => Math.max(max, point.value), 0);
    const ticks = axisTicks(peak);
    const top = ticks[ticks.length - 1] || 1;

    return (
        <figure className="mt-10 border border-[#e1e8ea] bg-white">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-[#e1e8ea] bg-[#f1f5f6] px-5 py-4">
                <div>
                    <h3 className="text-lpres-deep text-lg font-extrabold">{title}</h3>
                    <p className="mt-1 text-sm text-[#4b5e65]">
                        Beneficiaries by LGA and ward · {recordCount} {recordCount === 1 ? 'record' : 'records'}
                    </p>
                </div>
                <p className="text-lpres-deep text-sm font-bold">
                    Total: <span className="text-lpres-green">{total.toLocaleString()}</span> beneficiaries
                </p>
            </header>

            {series.length === 0 ? (
                <EmptyState message="No beneficiary records have been added for this cluster yet. The chart will appear here automatically once records are entered in the admin panel." />
            ) : (
                <div className="px-4 pt-6 pb-1 sm:px-5">
                    <div className="overflow-x-auto">
                        <div style={{ minWidth: `${series.length * 72 + 56}px` }}>
                            <div className="relative ml-12 h-[300px]">
                                {ticks.map((tick) => (
                                    <div
                                        key={tick}
                                        className="absolute inset-x-0 border-t border-[#e1e8ea]"
                                        style={{ bottom: `${(tick / top) * 100}%` }}
                                    >
                                        <span className="absolute -top-2 right-full mr-2 w-10 text-right text-[11px] font-medium text-[#838e91] tabular-nums">
                                            {tick.toLocaleString()}
                                        </span>
                                    </div>
                                ))}

                                <div className="absolute inset-0 flex items-end gap-2">
                                    {series.map((point, index) => (
                                        <div
                                            key={`${point.label}-${index}`}
                                            className="flex h-full flex-1 flex-col items-center justify-end"
                                            title={`${point.label}: ${point.value.toLocaleString()} beneficiaries`}
                                        >
                                            <span className="text-lpres-deep mb-1 text-[11px] font-bold tabular-nums">
                                                {point.value.toLocaleString()}
                                            </span>
                                            <div
                                                aria-hidden="true"
                                                className="bg-lpres-green hover:bg-lpres-deep w-full max-w-[52px] transition-colors"
                                                style={{
                                                    height: `${(point.value / top) * 88}%`,
                                                    minHeight: point.value > 0 ? 4 : 2,
                                                }}
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="relative mt-2 ml-12 flex gap-2 pb-2" style={{ height: 132 }}>
                                {series.map((point, index) => (
                                    <div key={`label-${point.label}-${index}`} className="relative flex-1" style={{ minWidth: 72 }}>
                                        <span
                                            className="absolute top-0 right-1/2 origin-top-right -rotate-45 text-[11px] leading-4 font-medium whitespace-nowrap text-[#4b5e65]"
                                            title={point.label}
                                        >
                                            {shortenLabel(point.label)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <figcaption className="mt-4 text-xs leading-5 text-[#838e91]">
                        Bars are generated from this cluster&apos;s beneficiary records. Records that share the same LGA and ward are combined
                        into one bar.
                    </figcaption>
                </div>
            )}

            {/* Text alternative for screen readers and non-visual clients. */}
            {series.length > 0 && (
                <table className="sr-only">
                    <caption>{title}</caption>
                    <thead>
                        <tr>
                            <th scope="col">LGA and ward</th>
                            <th scope="col">Beneficiaries</th>
                        </tr>
                    </thead>
                    <tbody>
                        {series.map((point, index) => (
                            <tr key={`sr-${point.label}-${index}`}>
                                <th scope="row">{point.label}</th>
                                <td>{point.value}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </figure>
    );
}
