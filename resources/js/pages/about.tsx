import PublicPage from '@/layouts/public-page';

const aboutCopy = {
    heading: 'About Gombe L-PRES Project',
    intro: [
        'The Livestock Productivity and Resilience Support (L-PRES), is a 6-year World Bank supported transformative initiative aimed at enhancing livestock productivity and strengthening the resilience of pastoral communities and all actors along the livestock value chain in Nigeria. L-PRES is currently being implemented in 20 states, with Gombe State being one of the key beneficiaries.',
        'Gombe State officially joined the L-PRES Project in October 2023, with actual implementation commencing in December 2023 following the release of project funds. Since implementation began, the state has made substantial progress in improving livestock productivity, disease control, and value chain development, benefiting thousands of farmers across the state.',
    ],
    coverage: {
        title: 'Coverage/Scope',
        text: 'Strengthening sector institutions, disease control and prevention, enhancing livestock value chains, and mitigating climate change mediated conflicts.',
    },
    population: {
        title: 'Project Target Population and Beneficiaries',
        text: 'The project is expected to directly benefit 1.43 million individuals in the whole country (of which at least 30 percent will be women), including livestock producers, pastoral and sedentary farm households and smallholder producers and other value chain actors.',
    },
    valueChains: [
        { number: '01', label: 'Sheep and Goat' },
        { number: '02', label: 'Beef' },
        { number: '03', label: 'Dairy' },
        { number: '04', label: 'Poultry' },
    ],
};

export default function About() {
    return (
        <PublicPage
            title="About Us"
            eyebrow="Get to know us"
            description="The Gombe State Livestock Productivity and Resilience Support Project (L-PRES) — a six-year World Bank supported transformative initiative for livestock productivity and resilience in Nigeria."
        >
            <div className="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,460px)] lg:gap-[70px]">
                {/* Left column: heading and the supplied editorial copy. */}
                <div className="min-w-0">
                    <span aria-hidden="true" className="bg-lpres-green mb-4 block h-1 w-[55px]" />

                    <h2 className="text-lpres-deep text-[32px] leading-[1.2] font-extrabold tracking-[-0.4px] sm:text-[40px] sm:leading-tight lg:text-[46px]">
                        {aboutCopy.heading}
                    </h2>

                    <div className="mt-6 space-y-5">
                        {aboutCopy.intro.map((paragraph, index) => (
                            <p key={index} className="text-base leading-[30px] text-[#4b5e65]">
                                {paragraph}
                            </p>
                        ))}
                    </div>

                    <div className="mt-10 grid gap-6 sm:grid-cols-2">
                        <div className="border border-[#e1e8ea] border-t-2 border-t-lpres-green bg-[#f7fafb] p-6">
                            <h3 className="text-lpres-deep text-base leading-6 font-extrabold uppercase">{aboutCopy.coverage.title}</h3>
                            <p className="mt-3 text-sm leading-6 text-[#6b7f86]">{aboutCopy.coverage.text}</p>
                        </div>
                        <div className="border border-[#e1e8ea] border-t-2 border-t-lpres-green bg-[#f7fafb] p-6">
                            <h3 className="text-lpres-deep text-base leading-6 font-extrabold uppercase">{aboutCopy.population.title}</h3>
                            <p className="mt-3 text-sm leading-6 text-[#6b7f86]">{aboutCopy.population.text}</p>
                        </div>
                    </div>

                    <h3 className="text-lpres-deep mt-12 text-[26px] leading-[34px] font-extrabold tracking-[-0.3px]">
                        The project targets the following livestock value chains in Gombe State
                    </h3>

                    <ul className="mt-6 grid list-none gap-4 sm:grid-cols-2">
                        {aboutCopy.valueChains.map((chain) => (
                            <li
                                key={chain.number}
                                className="group flex items-center gap-5 border border-[#e1e8ea] bg-white p-5 transition-colors duration-300 hover:border-lpres-green/60 hover:bg-[#f2f7f4]"
                            >
                                <span
                                    aria-hidden="true"
                                    className="grid size-12 shrink-0 place-items-center rounded-full bg-lpres-mist text-lpres-green text-lg font-extrabold"
                                >
                                    {chain.number}
                                </span>
                                <span className="text-lpres-deep text-lg font-extrabold">{chain.label}</span>
                            </li>
                        ))}
                    </ul>
                </div>

                {/* Right column: prominent project photograph. */}
                <figure className="lg:sticky lg:top-[120px]">
                    <div className="relative overflow-hidden rounded-md shadow-[0_28px_60px_-30px_rgba(0,59,73,0.5)]">
                        <img
                            src="/images/lpres/news/news-17.jpg"
                            alt="Gombe State L-PRES officials on a field visit with livestock value chain actors"
                            width={570}
                            height={438}
                            loading="eager"
                            decoding="async"
                            className="block aspect-[570/438] w-full object-cover"
                        />
                        <span aria-hidden="true" className="bg-lpres-green absolute bottom-0 left-0 h-1.5 w-full" />
                    </div>

                    <figcaption className="text-lpres-deep mt-4 inline-flex items-baseline gap-3">
                        <span aria-hidden="true" className="bg-lpres-green inline-block h-0.5 w-8" />
                        <span className="text-sm font-semibold tracking-[0.5px] uppercase">
                            Gombe L-PRES in the field
                        </span>
                    </figcaption>
                </figure>
            </div>
        </PublicPage>
    );
}
