import { pdoSection } from '@/config/site';

/**
 * Dark photographic band from the live homepage: the "Gombe L-PRES" heading
 * and PDO subtitle on the left, the white five-indicator panel overhanging
 * into the green statistics band on the right.
 *
 * Live-sourced geometry (1440px): 1200px content, 120px top padding, white
 * panel 470px wide with 60px padding, 120px play-button column, panel bottom
 * 60px overhang; arrow decoration tucked at the title block's top-right.
 */
export default function PdoIndicators() {
    const { heading, subtitle, arrow, videoHref, videoLabel, background, items } = pdoSection;

    return (
        <section aria-labelledby="pdo-heading" className="bg-lpres-ink bg-cover bg-top" style={{ backgroundImage: `url(${background})` }}>
            <div className="mx-auto flex w-full max-w-[1200px] flex-col px-5 pt-[72px] pb-0 sm:px-6 lg:flex-row lg:items-start lg:px-0 lg:pt-[120px]">
                {/* Left: section title, with the hand-drawn arrow decoration. */}
                <div className="relative lg:w-1/2 lg:px-[15px]">
                    <div className="relative max-w-[570px] lg:pt-[12px]">
                        <span aria-hidden="true" className="bg-lpres-green absolute top-[-15px] left-0 block h-0.5 w-[35px]" />

                        <h2
                            id="pdo-heading"
                            className="text-[40px] leading-[48px] font-extrabold tracking-[-0.4px] text-white lg:text-[50px] lg:leading-[60px] lg:font-extrabold"
                        >
                            {heading}
                        </h2>

                        <p className="mt-8 text-base leading-6 font-medium text-[#9cb7bd]">{subtitle}</p>

                        <img
                            aria-hidden="true"
                            src={arrow.image}
                            alt={arrow.imageAlt}
                            width={arrow.width}
                            height={arrow.height}
                            loading="lazy"
                            decoding="async"
                            className="absolute top-[-65px] right-[-65px] hidden h-auto w-[159px] lg:block"
                        />
                    </div>
                </div>

                {/* Right: play button column plus the white indicator panel. */}
                <div className="relative mt-10 lg:mt-0 lg:w-1/2">
                    <a
                        href={videoHref}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={videoLabel}
                        className="flex h-[120px] w-[120px] items-center justify-center bg-white lg:absolute lg:top-0 lg:left-[-5px] lg:z-10"
                    >
                        <span className="bg-lpres-deep text-lpres-green flex h-20 w-20 items-center justify-center">
                            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" className="h-5 w-5 translate-x-[1px]">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                        </span>
                    </a>

                    <div
                        className="relative z-10 bg-white bg-[position:50%_100%] bg-repeat px-6 py-10 sm:px-10 lg:-mb-[60px] lg:ml-[115px] lg:w-[470px] lg:px-[60px] lg:py-[60px]"
                        style={{ backgroundImage: 'url(/images/lpres/service-3.jpg)' }}
                    >
                        <ul>
                            {items.map((item, index) => (
                                <li key={item.id} className={index > 0 ? 'border-t border-[#e1e8ea]' : undefined}>
                                    <div className="group flex items-center justify-between gap-6 px-5 py-[15px]">
                                        <span className="group-hover:text-lpres-green text-base leading-6 font-medium text-[#838e91] transition-colors duration-300">
                                            {item.title}
                                        </span>
                                        <svg
                                            aria-hidden="true"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            className="group-hover:text-lpres-green h-3 w-3 shrink-0 text-[#838e91] transition-colors duration-300"
                                        >
                                            <path d="m9 18 6-6-6-6" />
                                        </svg>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    );
}
