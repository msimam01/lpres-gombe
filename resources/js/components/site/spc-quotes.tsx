import { useEffect, useState } from 'react';

import { spcQuotes } from '@/config/site';
import { usePrefersReducedMotion } from '@/hooks/use-prefers-reduced-motion';

/**
 * SPC's Quotes carousel. The original combines a dark testimonial background
 * with a starred quote panel, the rotating avatar thumbnails and the vertical
 * "SPC's Quotes" label. A dedicated autoplay loop mirrors the original
 * swiper: it pauses while hovered or focused, and for reduced-motion
 * visitors the quotes stay on the first slide but remain keyboard-switchable.
 *
 * Live-sourced values: section padding 120px, quote text 22px/1.9 "#003b49"
 * on the dark bg, stars box "#01313c" with green stars, avatar 80px circle,
 * vertical label green rotated 180deg.
 */
export default function SpcQuotes() {
    const { heading, background, avatar, quotes } = spcQuotes;
    const [active, setActive] = useState(0);
    const [paused, setPaused] = useState(false);
    const prefersReducedMotion = usePrefersReducedMotion();

    const effectiveCount = new Set(quotes.map((quote) => quote.id)).size;

    useEffect(() => {
        if (prefersReducedMotion || paused || quotes.length < 2) {
            return;
        }

        const timer = window.setInterval(() => {
            setActive((current) => (current + 1) % quotes.length);
        }, 6000);

        return () => window.clearInterval(timer);
    }, [prefersReducedMotion, paused, quotes.length]);

    return (
        <section
            aria-label={heading}
            className="relative bg-lpres-deep bg-cover bg-center pt-[120px] pb-[120px]"
            style={{ backgroundImage: `url(${background})` }}
        >
            <div className="relative mx-auto w-full max-w-[1200px] px-5 sm:px-6 lg:px-0">
                {/* Vertical label, drawn rotated on the live theme. */}
                <p
                    aria-hidden="true"
                    className="absolute top-[70%] max-lg:hidden -translate-y-1/2 -rotate-180 text-base font-medium tracking-[2px] text-lpres-green [writing-mode:vertical-lr]"
                    style={{ left: -176, transform: 'rotate(180deg)' }}
                >
                    {heading}
                </p>

                <div
                    onMouseEnter={() => setPaused(true)}
                    onMouseLeave={() => setPaused(false)}
                    onFocus={() => setPaused(true)}
                    onBlur={() => setPaused(false)}
                    className="relative"
                >
                    <div
                        aria-roledescription="carousel"
                        aria-label={heading}
                        className="relative mx-auto max-w-[900px] text-center"
                        onMouseEnter={() => setPaused(true)}
                        onMouseLeave={() => setPaused(false)}
                    >
                        <div
                            aria-live={paused || prefersReducedMotion ? 'off' : 'polite'}
                            aria-atomic="true"
                        >
                            {quotes.map((quote, index) => (
                                <figure
                                    key={quote.id}
                                    hidden={index !== active}
                                    aria-hidden={index !== active}
                                    className="m-0"
                                >
                                    <div className="mb-10 inline-block bg-[#01313c] px-10 py-2.5 text-base leading-none text-lpres-green">
                                        <span aria-label="Rated 5 out of 5">★★★★★</span>
                                    </div>

                                    <blockquote className="m-0 text-[22px] leading-[1.9] text-white">
                                        {quote.text}
                                    </blockquote>

                                    <figcaption className="mt-11 mb-[25px]">
                                        <span className="block text-xl font-extrabold text-white">{quote.author}</span>
                                        <span className="text-sm text-[#9cb7bd]">{quote.role}</span>
                                    </figcaption>
                                </figure>
                            ))}
                        </div>

                        {/* Slide pickers: pagination dots on the live theme. */}
                        <div className="absolute top-[45%] right-0 hidden space-y-2.5 lg:block">
                            {quotes.map((quote, index) => (
                                <button
                                    key={`dot-${quote.id}`}
                                    type="button"
                                    onClick={() => setActive(index)}
                                    aria-label={`Show quote ${index + 1} of ${effectiveCount}`}
                                    aria-current={index === active}
                                    className="block h-[13px] w-[13px] rounded-full bg-white opacity-30 transition-opacity duration-300 hover:opacity-70 aria-current:opacity-100"
                                />
                            ))}
                        </div>
                    </div>

                    {/* Avatar thumbnails, mirroring the live active-slide ring. */}
                    <div className="mt-8 flex justify-center gap-4">
                        {quotes.map((quote, index) => (
                            <button
                                key={`thumb-${quote.id}`}
                                type="button"
                                onClick={() => setActive(index)}
                                aria-label={`Show quote by ${quote.author}`}
                                aria-current={index === active}
                                className="relative block cursor-pointer rounded-full p-0 outline-offset-4"
                            >
                                <img
                                    src={avatar}
                                    alt=""
                                    width={80}
                                    height={80}
                                    loading="lazy"
                                    decoding="async"
                                    className={`rounded-full border-[5px] transition-all duration-500 ${
                                        index === active ? 'scale-100 border-lpres-green' : 'scale-80 border-white bg-lpres-deep opacity-90'
                                    }`}
                                />
                                <span
                                    aria-hidden="true"
                                    className={`lpres-icon-quote absolute right-0 bottom-0 flex h-7 w-7 items-center justify-center rounded-full bg-lpres-green text-xs text-white transition-opacity duration-500 ${
                                        index === active ? 'opacity-100' : 'opacity-0'
                                    }`}
                                />
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
