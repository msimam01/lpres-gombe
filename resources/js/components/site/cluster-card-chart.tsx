interface CardChartPoint {
    label: string;
    value: number;
}

interface ClusterCardChartProps {
    clusterName: string;
    series: CardChartPoint[];
}

/** LGA part of an "LGA, Ward" label, trimmed to fit a card. */
function shortLabel(label: string): string {
    const [lga, ward] = label.split(/,\s*/);
    const text = lga || label;
    return text.length > 10 ? `${text.slice(0, 9)}…` : text;
}

/**
 * Compact version of the cluster's beneficiary chart, used on the listing
 * cards when a cluster has no featured image. It is a preview only: the full
 * chart with ward labels, axis and counts lives on the cluster page. Bars use
 * the same records (and the same LGA+ward aggregation) as that page.
 */
export default function ClusterCardChart({ clusterName, series }: ClusterCardChartProps) {
    const shown = series.slice(0, 14);
    const peak = shown.reduce((max, point) => Math.max(max, point.value), 0) || 1;

    const summary = shown.map((point) => `${point.label}: ${point.value.toLocaleString()}`).join(', ');

    return (
        <div
            role="img"
            aria-label={`Beneficiary distribution for ${clusterName}. ${summary}${series.length > shown.length ? `, and ${series.length - shown.length} more` : ''}`}
            className="flex aspect-[370/280] w-full flex-col justify-end overflow-hidden bg-[#f1f5f6] px-4 pt-7 pb-3"
        >
            <div className="flex flex-1 items-end gap-1">
                {shown.map((point, index) => (
                    <div key={`${point.label}-${index}`} className="flex h-full flex-1 items-end justify-center">
                        <span
                            className="bg-lpres-green/85 group-hover:bg-lpres-green w-full max-w-[26px] transition-colors"
                            style={{ height: `${Math.max((point.value / peak) * 100, 1.5)}%` }}
                        />
                    </div>
                ))}
            </div>
            <div className="mt-2 flex gap-1">
                {shown.map((point, index) => (
                    <span
                        key={`label-${point.label}-${index}`}
                        className="text-lpres-deep min-w-0 flex-1 truncate text-center text-[9px] leading-3 font-semibold"
                        title={point.label}
                    >
                        {shortLabel(point.label)}
                    </span>
                ))}
            </div>
        </div>
    );
}
