/**
 * Gombe State L-PRES website — fixed site content.
 *
 * This file holds the *fixed organisation copy* (hero slides, SPC welcome
 * note, mission/vision/values statements, SPC quotes, footer, navigation).
 * Content that staff manage through the admin panel — news, team members,
 * projects, value chain clusters, media, events and project components —
 * is supplied by Laravel and never lives here.
 */

export interface SiteNavLink {
    label: string;
    href: string;
    exact?: boolean;
    children?: SiteNavLink[];
}

/**
 * Shared public navigation, as an ordered tree. News and Events keep their
 * own top-level entries so visitors can reach them without digging through
 * menus; logically grouped pages (About, Value Chain Clusters) use a
 * dropdown of their children.
 */
export const siteNavigation: SiteNavLink[] = [
    { label: 'Home', href: '/' },
    {
        label: 'About Us',
        href: '/about',
        children: [
            { label: 'Our Vision', href: '/vision' },
            { label: 'Our Mission', href: '/mission' },
            { label: 'Core Values', href: '/core-values' },
        ],
    },
    { label: 'Project Team', href: '/project-team' },
    { label: 'Our Projects', href: '/our-projects' },
    {
        label: 'Media',
        href: '/media',
        children: [
            { label: 'Galleries', href: '/media#galleries' },
            { label: 'News', href: '/news' },
            { label: 'Events', href: '/events' },
        ],
    },
    { label: 'Visual Reports', href: '/visual-reports' },
    {
        label: 'Value Chain Clusters',
        href: '/value-chains',
        children: [
            { label: 'Beef', href: '/value-chains/beef' },
            { label: 'Broiler', href: '/value-chains/broiler' },
            { label: 'Dairy', href: '/value-chains/dairy' },
            { label: 'Eggs', href: '/value-chains/eggs' },
            { label: 'Feed', href: '/value-chains/feed' },
            { label: 'Fodder', href: '/value-chains/fodder' },
            { label: 'Pig', href: '/value-chains/pig' },
            { label: 'Sheep and Goat', href: '/value-chains/sheep-and-goat' },
            { label: 'Leather', href: '/value-chains/leather' },
        ],
    },
    { label: 'News', href: '/news' },
    { label: 'Events', href: '/events' },
    { label: 'Contact Us', href: '/contact' },
];

export interface HeroSlide {
    id: string;
    tagline: string;
    title: string;
    titleLines: string[];
    image: string;
    imageAlt: string;
    width: number;
    height: number;
    objectPosition: string;
}

/** Hero carousel slides (captured look in docs references). */
export const heroSlides: HeroSlide[] = [
    {
        id: 'agro-livestock-zone',
        tagline: 'Gombe L-PRES',
        title: 'Gombe Flags Off Landmark Agro-Livestock Development Zone',
        titleLines: ['Gombe Flags Off', 'Landmark Agro-Livestock Development Zone'],
        image: '/images/hero/lpres-launch.jpg',
        imageAlt: 'Gombe L-PRES flag-off ceremony for the Agro-Livestock Development Zone',
        width: 960,
        height: 640,
        objectPosition: 'center 42%',
    },
    {
        id: 'fodder-seedlings',
        tagline: 'Gombe L-PRES',
        title: 'Distribute Fodder Seedlings',
        titleLines: ['Distribute Fodder', 'Seedlings'],
        image: '/images/hero/lpres-fodder.jpg',
        imageAlt: 'Women carrying fodder seedlings at a Gombe L-PRES distribution event',
        width: 1080,
        height: 720,
        objectPosition: 'center 38%',
    },
    {
        id: 'value-chain-training',
        tagline: 'Gombe L-PRES Trains',
        title: 'Livestock Farmers and Value Chain Actors',
        titleLines: ['Livestock Farmers and', 'Value Chain Actors'],
        image: '/images/hero/lpres-training.jpg',
        imageAlt: 'Livestock farmers and value chain actors at a Gombe L-PRES training session',
        width: 2560,
        height: 1706,
        objectPosition: 'center 48%',
    },
    {
        id: 'abattoir',
        tagline: 'Ultra-Modern Abattoir',
        title: 'Prototype of the 184-Hectare Agro-Industrial Development Zone',
        titleLines: ['Prototype of the 184-Hectare Agro-Industrial Development Zone'],
        image: '/images/hero/lpres-abattoir.jpg',
        imageAlt: 'Architectural render of the L-PRES agro-industrial development zone entrance',
        width: 1080,
        height: 490,
        objectPosition: 'center 50%',
    },
];

export const welcomeNote = {
    coordinator: 'Prof. U.B Abubakar',
    heading: "SPC's Welcome Note",
    image: '/images/lpres/spc-prof-u-b-abubakar.png',
    imageAlt: 'Prof. U.B Abubakar, Coordinator of Gombe State L-PRES',
    width: 422,
    height: 591,
    paragraphs: [
        'L-PRES is a 6-Year Project with Development Objective (PDO) focusing on improving livestock productivity, resilience and commercialization of selected value chains and to strengthen the country’s capacity to respond to an eligible crisis or emergency.',
        'Livestock is vital to Nigeria’s economy and poverty alleviation. It employs 30 percent of the rural population but accounts only for less than 10 percent of the Agricultural GDP and 2-3 percent of the National GDP , which highlights the sub-sector under-development. Livestock provides increased significant economic stability to rural households in the form of cash buffers, as capital reserves, and creates a hedge against inflation.',
        'It is against this backdrop that Gombe L-PRES remains committed to championing the cause of livestock transformation in Gombe State. We stand ready to collaborate with all actors and stakeholders to modernize the sector and make it more resilient, profitable, and productive — ultimately contributing to improved livelihoods and overall economic development.',
    ] as string[],
};

export const projectComponents = {
    eyebrow: 'L-PRES Project',
    heading: 'Components',
    image: '/images/lpres/project-components.jpg',
    imageAlt: 'Poultry at a Gombe State L-PRES project site',
    width: 1200,
    height: 803,
    items: [
        {
            id: 'institutional-and-innovation-system-strengthening',
            number: 1,
            title: 'Institutional and Innovation System Strengthening',
        },
        {
            id: 'livestock-value-chain-enhancement',
            number: 2,
            title: 'Livestock Value Chain Enhancement',
        },
        {
            id: 'crisis-prevention-and-management',
            number: 3,
            title: 'Crisis Prevention and Management, Conflict Mitigation, Peace-Building, Environment and Coordination',
        },
    ],
};

export const pdoSection = {
    heading: 'Gombe L-PRES',
    subtitle: 'KEY PDO - LEVEL RESULTS INDICATIONS',
    arrow: {
        image: '/images/lpres/arrow.png',
        imageAlt: '',
        width: 159,
        height: 48,
    },
    videoHref: 'https://www.youtube.com/@gombelpres',
    videoLabel: 'Watch Gombe L-PRES videos on YouTube',
    background: '/images/lpres/service-bg-1.jpg',
    items: [
        { id: 'increased-yields', title: 'Increased yields in Targeted Value Chains' },
        { id: 'increased-value-of-sales', title: 'Increased Value of Sales from Targeted Value Chains' },
        { id: 'decreased-mortality', title: 'Decreased Mortality Rates in Livestock Production Systems' },
        { id: 'reduced-conflicts', title: 'Reduced incidence of Resource-based Farmer- Harder Conflicts' },
        { id: 'climate-smart', title: 'Farmer Adopting Climate-Smart Technologies' },
    ],
};

export interface BeneficiaryStatistic {
    /** Stable key, not rendered. Matches the value's meaning for tests. */
    id: string;
    value: number;
    /** e.g. "k". */
    suffix?: string;
    label: string;
    /** Smaller line under the label. */
    subtitle?: string;
    /** Class name from the lpres icon font, e.g. lpres-icon-professor. */
    iconClass?: string;
}

export const beneficiaries = {
    heading: 'Statistics of Livestock Targeted Beneficiaries',
    background: '/images/lpres/funfact-bg-1.png',
    stats: [
        {
            id: 'total-profiled',
            value: 34,
            suffix: 'k',
            label: 'Total Profiled',
            iconClass: 'lpres-icon-professor',
        },
        {
            id: 'locations',
            value: 11,
            label: 'Locations',
            subtitle: '11 LGAs',
            iconClass: 'lpres-icon-location-pin',
        },
        {
            id: 'clusters',
            value: 9,
            label: 'Total',
            subtitle: 'Clusters',
            iconClass: 'lpres-icon-handshake',
        },
    ] satisfies BeneficiaryStatistic[],
};

export const missionVisionValues = {
    blocks: [
        {
            id: 'mission',
            title: 'Our Mission',
            text: 'A Livestock Industry where value addition and quality services enable sustainable, diversified and productive livestock economy in Nigeria.',
        },
        {
            id: 'vision',
            title: 'Our Vision',
            text: 'To build a modern, inclusive, and resilient livestock economy in Gombe State through effective coordination, infrastructure development, and sustainable practices that empower farmers, enhance productivity, and drive prosperity.',
        },
        {
            id: 'values',
            title: 'Our Core Values',
            text: 'Professionalism, Teamwork, Innovation, Value Addition and Wealth Creation',
        },
    ],
    image: '/images/lpres/mission-photo.jpg',
    imageAlt: 'Gombe State L-PRES officials on site',
    width: 570,
    height: 438,
    shape: {
        image: '/images/lpres/shape-1.png',
        width: 444,
        height: 246,
    },
};

export const valueChains = [
    {
        id: 'sheep-goat',
        label: 'Sheep & Goat',
        href: '/value-chains/sheep-and-goat',
        icon: '/images/value-chains/sheep-goat.svg',
    },
    {
        id: 'poultry',
        label: 'Poultry',
        href: '/value-chains/poultry',
        icon: '/images/value-chains/poultry.svg',
    },
    {
        id: 'beef',
        label: 'Beef',
        href: '/value-chains/beef',
        icon: '/images/value-chains/beef.svg',
    },
    {
        id: 'dairy',
        label: 'Dairy',
        href: '/value-chains/dairy',
        icon: '/images/value-chains/dairy.svg',
    },
];

export const partners = [
    {
        id: 'fmafs',
        image: '/images/lpres/partners/fmafs-logo.jpg',
        imageAlt: 'Federal Ministry of Agriculture and Food Security',
        width: 136,
        height: 136,
    },
    {
        id: 'fmlc',
        image: '/images/lpres/partners/fmlc.png',
        imageAlt: 'Federal Ministry of Livestock',
        width: 136,
        height: 136,
    },
    {
        id: 'gombe-state',
        image: '/images/lpres/partners/gombe-state.png',
        imageAlt: 'Gombe State Government',
        width: 136,
        height: 136,
    },
    {
        id: 'nigeria-flag',
        image: '/images/lpres/partners/nigeria-flag.png',
        imageAlt: 'Federal Republic of Nigeria',
        width: 136,
        height: 136,
    },
    {
        id: 'wmca',
        image: '/images/lpres/partners/wmca.png',
        imageAlt: 'World Bank',
        width: 136,
        height: 136,
    },
];

export const spcQuotes = {
    heading: "SPC's Quotes",
    background: '/images/lpres/testimonial-bg-1.jpg',
    avatar: '/images/lpres/spc-prof-u-b-abubakar.png',
    avatarAlt: 'Prof. U.B Abubakar, Coordinator of Gombe State L-PRES',
    quotes: [
        /*
         * WordPress provenance (Phase 6.2 migration):
         *   id 1 = WP 118, id 2 = WP 3114 — the two quotes already published here.
         *   id 3 = WP 114, id 4 = WP 116 — migrated from the WordPress export; WP 116's trailing
         *   double quote (U+0022) was dropped.
         * WP 3115 (duplicate wording of WP 118) and WP 3670 (the SPC Profile) are excluded.
         */
        {
            id: 1,
            text: 'The L-PRES Project: bridging gaps, fostering resilience, and driving progress in the livestock sector.',
            author: 'Prof. U.B. Abubakar',
            role: 'SPC',
        },
        {
            id: 2,
            text: 'Investing in livestock productivity today ensures food security, economic stability, and environmental sustainability for tomorrow.',
            author: 'Prof. U.B. Abubakar',
            role: 'SPC',
        },
        // WP 114
        {
            id: 3,
            text: "Resilient livestock systems aren't just about animals; they're about people, innovation, and sustainable development.",
            author: 'Prof. U.B. Abubakar',
            role: 'SPC',
        },
        // WP 116 (the source's trailing U+0022 is omitted)
        {
            id: 4,
            text: 'From pasture to plate, every step in the livestock value chain strengthens our economy and supports sustainable growth.',
            author: 'Prof. U.B. Abubakar',
            role: 'SPC',
        },
    ] as { id: number; text: string; author: string; role: string }[],
};

export const newsletter = {
    heading: 'Stay Connected',
    subheading: 'Join Our Newsletter',
    placeholder: 'Your Email Address',
    button: 'Subscribe',
    notice: 'Thank you for subscribing to the Gombe L-PRES newsletter.',
};

export const siteFooter = {
    logo: '/images/lpres/footer-logo.jpg',
    logoAlt: 'Gombe State L-PRES',
    description:
        'Transforming livestock productivity, profitability and resilience for sustainable future',
    explore: [
        { label: 'Contact Us', href: '/contact' },
        { label: 'Project', href: '/our-projects' },
        { label: 'About', href: '/about' },
    ],
    usefulLinks: [
        { label: 'Worldbank', href: 'https://www.worldbank.org/', external: true },
        { label: 'FMAFS', href: 'https://fmafs.gov.ng/', external: true },
        {
            label: 'Gombe State Government',
            href: 'https://www.gombestate.gov.ng/',
            external: true,
        },
        { label: 'State Ministry of Agriculture', href: '/', external: false },
        { label: 'State Finance Ministry', href: '/', external: false },
    ],
    socials: [
        { label: 'YouTube', href: 'https://www.youtube.com/@gombelpres' },
    ],
    contact: {
        addressLines: ['GSADP Quarters, Opposite Buhari Estate,', 'Gombe, Gombe State Nigeria'],
        email: 'gombe@lpresnigeria.gov.ng',
        phone: '+234 8035336243',
    },
    copyright: 'Copyright 2026 by Gombe State L-PRES',
};
