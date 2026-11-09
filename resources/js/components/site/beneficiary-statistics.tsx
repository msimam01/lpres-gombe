import type { BeneficiaryStatistic } from '@/config/site';
import { beneficiaries } from '@/config/site';

function Statistic({ stat }: { stat: BeneficiaryStatistic }) {
    return (
        <div>
            <div className="flex items-center gap-[25px]">
                <span className="bg-lpres-deep flex h-[102px] w-[102px] shrink-0 items-center justify-center">
                    <i aria-hidden="true" className={`${stat.iconClass} text-lpres-green text-[54px] leading-none`} />
                </span>
                <div className="flex items-center">
                    <span aria-hidden="true" className="text-[60px] leading-[72px] font-medium text-white">
                        {stat.value}
                    </span>
                    {stat.suffix && (
                        <span aria-hidden="true" className="text-[60px] leading-none font-medium text-white">
                            {stat.suffix}
                        </span>
                    )}
                    <span className="sr-only">
                        {stat.value}
                        {stat.suffix ?? ''}
                    </span>
                </div>
            </div>
            <p className="mt-[15px] text-lg leading-[33.75px] font-medium text-white">
                {stat.label}
                {stat.subtitle && (
                    <>
                        <br />
                        {stat.subtitle}
                    </>
                )}
            </p>
        </div>
    );
}

/**
 * Green patterned band beneath the PDO panel. Live arrangement: a white
 * 24px heading raised 37px up and outdented 37px left (leaning into the
 * section's top padding), then the three statistics in the first three
 * quarters of a four-column grid, the fourth column staying empty.
 * 120px/111px section padding, 102px deep-teal icon squares, 60px figures,
 * 18px white labels. The heading is kept outside the grid so the column
 * tracks match the reference exactly.
 */
export default function BeneficiaryStatistics() {
    const { heading, background, stats } = beneficiaries;

    return (
        <section aria-labelledby="beneficiaries-heading" className="bg-lpres-green" style={{ backgroundImage: `url(${background})` }}>
            <div className="mx-auto w-full max-w-[1200px] px-5 pt-16 pb-14 sm:px-6 lg:px-0 lg:pt-[120px] lg:pb-[111px]">
                <h2
                    id="beneficiaries-heading"
                    className="mb-4 text-xl leading-6 font-semibold text-white lg:relative lg:-top-[37px] lg:-left-[37px] lg:-mb-[24px]"
                >
                    {heading}
                </h2>
                <div className="grid grid-cols-1 gap-y-10 sm:grid-cols-3 sm:gap-x-6 lg:grid-cols-4">
                    {stats.map((stat) => (
                        <div key={stat.id} className="lg:px-[15px]">
                            <Statistic stat={stat} />
                        </div>
                    ))}
                    <div aria-hidden="true" className="hidden lg:block" />
                </div>
            </div>
        </section>
    );
}
