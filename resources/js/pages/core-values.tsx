import { ShieldCheck, Lightbulb, TrendingUp, Users, Coins } from 'lucide-react';

import PublicPage from '@/layouts/public-page';
import { missionVisionValues } from '@/config/site';

const values = missionVisionValues.blocks.find((block) => block.id === 'values')?.text ?? '';

/** Split the fixed core-values sentence into its five named values. */
const VALUE_ITEMS: { label: string; icon: typeof ShieldCheck; blurb: string }[] = [
    {
        label: 'Professionalism',
        icon: ShieldCheck,
        blurb: 'Delivering project services to the standards expected of a World Bank-supported project.',
    },
    {
        label: 'Teamwork',
        icon: Users,
        blurb: 'Working across government, private actors and communities as one delivery team.',
    },
    {
        label: 'Innovation',
        icon: Lightbulb,
        blurb: 'Bringing modern technology, science and systems into livestock production.',
    },
    {
        label: 'Value Addition',
        icon: TrendingUp,
        blurb: 'Improving processing, quality and market value along every supported value chain.',
    },
    {
        label: 'Wealth Creation',
        icon: Coins,
        blurb: 'Raising incomes and livelihoods for producers, processors and their communities.',
    },
];

export default function CoreValues() {
    return (
        <PublicPage
            title="Our Core Values"
            eyebrow="About L-PRES"
            description="Professionalism, Teamwork, Innovation, Value Addition and Wealth Creation — the values that guide the Gombe State L-PRES project."
            breadcrumbs={[{ title: 'About Us', href: '/about' }, { title: 'Core Values', href: '' }]}
        >
            <div className="bg-white">
                <span aria-hidden="true" className="bg-lpres-green block h-0.5 w-[35px]" />
                <h2 className="text-lpres-deep mt-3 text-2xl leading-[34px] font-extrabold">Our Core Values</h2>
                <p className="mt-5 max-w-[680px] text-xl leading-[34px] text-[#4b5e65]">{values}</p>

                <ul className="mt-10 grid list-none gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {VALUE_ITEMS.map((value) => {
                        const Icon = value.icon;

                        return (
                            <li key={value.label} className="bg-[#f1f5f6] p-7">
                                <Icon aria-hidden="true" className="text-lpres-green size-9" strokeWidth={1.6} />
                                <h3 className="mt-4 text-lg font-extrabold text-lpres-deep">{value.label}</h3>
                                <p className="mt-2 text-base leading-6 text-[#838e91]">{value.blurb}</p>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </PublicPage>
    );
}
