interface SectionHeadingProps {
    /** Small uppercase label above the title, e.g. "Our Work". */
    eyebrow: string;
    /** Section title, e.g. "Projects". */
    title: string;
    /** Optional id for the accessible heading (aria-labelledby targets). */
    id?: string;
    /** Center matches the homepage band style; left suits list-style sections. */
    align?: 'center' | 'left';
}

/**
 * The shared homepage section header: green eyebrow with the signature short
 * green dash, then the large deep-teal title. One component so every
 * homepage band keeps identical spacing and typography.
 */
export default function SectionHeading({ eyebrow, title, id, align = 'center' }: SectionHeadingProps) {
    return (
        <div className={align === 'center' ? 'text-center' : undefined}>
            <div className="relative inline-block">
                <span aria-hidden="true" className="bg-lpres-green absolute top-[-13px] left-0 block h-0.5 w-[35px]" />
                <p className="text-sm font-bold tracking-[1px] text-lpres-green uppercase">{eyebrow}</p>
            </div>
            <h2
                id={id}
                className="mt-[13px] mb-[30px] text-[50px] leading-tight text-[#223645] max-[1199px]:text-4xl"
            >
                {title}
            </h2>
        </div>
    );
}
