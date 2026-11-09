import { partners } from '@/config/site';

/**
 * Partners & supporters marquee. The original is an auto-stepping owl
 * carousel of five logos inside a container whose top border carries the
 * centered white label. Rebuilt as a seamless CSS marquee: one set of five
 * 196px cells (10px gap = 1030px) repeats three times and slides one set
 * width; duplicated copies are aria-hidden so screen readers still meet
 * each partner exactly once.
 *
 * Live-sourced geometry: container 1200px, border #e1e8ea, track top padding
 * 80px / bottom 90px, logo images 171px content inside 30px padding,
 * 40% opacity (full on hover).
 */
const REPEATS = 3;

export default function PartnersSupporters() {
    // Enough repeated sets to cover the container plus one full loop.
    const track = Array.from({ length: REPEATS }, (_, repeat) =>
        partners.map((logo) => ({ logo, repeat })),
    ).flat();

    return (
        <section aria-label="Our partners and supporters" className="bg-white pt-[60px] pb-[50px]">
            <div className="relative mx-auto max-w-[1200px] px-5 sm:px-6 lg:px-0">
                {/* Label sits over the top border, centered, like the live theme. */}
                <div className="absolute inset-x-0 top-0 z-10 flex justify-center">
                    <h2 className="z-10 -translate-y-1/2 bg-white px-0 max-[420px]:px-0 px-[20px] py-0 text-sm font-medium tracking-[1px] text-[#838e91] uppercase">
                        Our Partners &amp; Supporters
                    </h2>
                </div>

                <div className="lpres-partner-viewport overflow-hidden border-t border-[#e1e8ea] pt-[80px] pb-[90px]">
                    <ul className="lpres-partner-track m-0 flex list-none gap-[10px]">
                        {track.map(({ logo, repeat }) => (
                            <li
                                key={`${logo.id}-${repeat}`}
                                aria-hidden={repeat > 0 || undefined}
                                className="w-[196px] shrink-0"
                            >
                                <img
                                    src={logo.image}
                                    alt={repeat === 0 ? logo.imageAlt : ''}
                                    width={logo.width}
                                    height={logo.height}
                                    loading="lazy"
                                    decoding="async"
                                    className="mx-auto p-[30px] opacity-40 transition-all duration-500 hover:bg-lpres-mist hover:opacity-100"
                                />
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </section>
    );
}
