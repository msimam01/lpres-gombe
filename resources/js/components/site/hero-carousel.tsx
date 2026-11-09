import { ArrowLeft, ArrowRight, Pause, Play } from 'lucide-react';
import { Fragment, useCallback, useEffect, useRef, useState, type FocusEvent, type KeyboardEvent, type PointerEvent } from 'react';

import type { HeroSlide } from '@/config/site';
import { usePrefersReducedMotion } from '@/hooks/use-prefers-reduced-motion';
import { cn } from '@/lib/utils';

const AUTOPLAY_INTERVAL_MS = 6000;
const SWIPE_THRESHOLD_PX = 50;

interface HeroCarouselProps {
    slides: HeroSlide[];
}

/**
 * Home hero. Behaviour additions over the original:
 *  - a visible pause/play control (autoplay longer than 5s must be stoppable)
 *  - slide indicators that double as direct navigation
 *  - touch/pen swipe (mouse is excluded so desktop image-drag is unaffected)
 *  - only the first slide is eager/high priority; later slides load at low priority
 * Props and the HeroSlide shape are unchanged.
 */
export default function HeroCarousel({ slides }: HeroCarouselProps) {
    const [activeIndex, setActiveIndex] = useState(0);
    const [isHovered, setIsHovered] = useState(false);
    const [isFocused, setIsFocused] = useState(false);
    const [isDocumentVisible, setIsDocumentVisible] = useState(true);
    const [isPaused, setIsPaused] = useState(false);
    const [hasUserInteracted, setHasUserInteracted] = useState(false);
    const prefersReducedMotion = usePrefersReducedMotion();
    const swipeStartX = useRef<number | null>(null);

    const goToSlide = useCallback(
        (index: number) => {
            if (slides.length === 0) {
                return;
            }

            setActiveIndex(((index % slides.length) + slides.length) % slides.length);
            setHasUserInteracted(true);
        },
        [slides.length],
    );

    const goToPreviousSlide = () => goToSlide(activeIndex - 1);
    const goToNextSlide = () => goToSlide(activeIndex + 1);

    useEffect(() => {
        const handleVisibilityChange = () => setIsDocumentVisible(document.visibilityState === 'visible');

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
    }, []);

    useEffect(() => {
        const canAutoRotate = slides.length > 1 && !prefersReducedMotion && !isPaused && !isHovered && !isFocused && isDocumentVisible;

        if (!canAutoRotate) {
            return;
        }

        const interval = window.setInterval(() => {
            setActiveIndex((current) => (current + 1) % slides.length);
        }, AUTOPLAY_INTERVAL_MS);

        return () => window.clearInterval(interval);
    }, [activeIndex, isDocumentVisible, isFocused, isHovered, isPaused, prefersReducedMotion, slides.length]);

    const handleKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            goToPreviousSlide();
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            goToNextSlide();
        }
    };

    const handleBlurCapture = (event: FocusEvent<HTMLElement>) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
            setIsFocused(false);
        }
    };

    const handlePointerDown = (event: PointerEvent<HTMLDivElement>) => {
        if (event.pointerType === 'mouse') {
            return;
        }

        swipeStartX.current = event.clientX;
    };

    const handlePointerUp = (event: PointerEvent<HTMLDivElement>) => {
        if (swipeStartX.current === null) {
            return;
        }

        const delta = event.clientX - swipeStartX.current;
        swipeStartX.current = null;

        if (Math.abs(delta) < SWIPE_THRESHOLD_PX) {
            return;
        }

        if (delta < 0) {
            goToNextSlide();
        } else {
            goToPreviousSlide();
        }
    };

    const activeSlide = slides[activeIndex];
    const showControls = slides.length > 1;
    const autoplayAvailable = showControls && !prefersReducedMotion;

    return (
        <section
            aria-roledescription="carousel"
            aria-label="Gombe State L-PRES highlights"
            aria-describedby="hero-carousel-instructions"
            tabIndex={0}
            onKeyDown={handleKeyDown}
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
            onFocusCapture={() => {
                setIsFocused(true);
                setHasUserInteracted(true);
            }}
            onBlurCapture={handleBlurCapture}
            className="bg-lpres-deep focus-visible:ring-lpres-green relative isolate h-[478px] w-full overflow-hidden focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset sm:h-[520px] md:h-[550px] lg:h-[600px] xl:h-[670px]"
        >
            <p id="hero-carousel-instructions" className="sr-only">
                Use the previous and next buttons, the slide indicators, or the left and right arrow keys to browse the slides.
            </p>

            <div
                id="hero-carousel-slides"
                onPointerDown={handlePointerDown}
                onPointerUp={handlePointerUp}
                onPointerCancel={() => {
                    swipeStartX.current = null;
                }}
                className={cn(
                    'flex h-full w-full touch-pan-y',
                    !prefersReducedMotion && 'transition-transform duration-700 ease-[cubic-bezier(0.33,1,0.68,1)]',
                )}
                style={{ transform: `translate3d(-${activeIndex * 100}%, 0, 0)` }}
            >
                {slides.map((slide, index) => {
                    const isActive = index === activeIndex;
                    const Heading = isActive ? 'h1' : 'p';

                    return (
                        <div
                            key={slide.id}
                            role="group"
                            aria-roledescription="slide"
                            aria-label={`${index + 1} of ${slides.length}`}
                            aria-hidden={!isActive}
                            className="bg-lpres-deep relative h-full w-full min-w-full shrink-0 overflow-hidden"
                        >
                            <img
                                src={slide.image}
                                alt={slide.imageAlt}
                                width={slide.width}
                                height={slide.height}
                                loading="eager"
                                fetchPriority={index === 0 ? 'high' : 'low'}
                                decoding="async"
                                sizes="100vw"
                                draggable={false}
                                className={cn('absolute inset-0 h-full w-full object-cover select-none', isActive && 'lpres-hero-zoom')}
                                style={{ objectPosition: slide.objectPosition ?? 'center' }}
                            />

                            {/* Scrim: heavier at the bottom so the controls stay legible on bright photos. */}
                            <div aria-hidden="true" className="bg-lpres-deep/50 absolute inset-0" />
                            <div
                                aria-hidden="true"
                                className="from-lpres-deep/70 absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t to-transparent"
                            />

                            <div className="relative z-10 mx-auto flex h-full w-full max-w-[1140px] px-3">
                                <div className="w-full pt-[140px] pb-24 lg:pt-[170px] xl:pt-[200px]">
                                    <div
                                        className={cn(
                                            'transition-[opacity,translate] duration-700 ease-out motion-reduce:transition-none',
                                            isActive ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                                        )}
                                    >
                                        <p className="text-xl leading-6 font-medium text-white">{slide.tagline}</p>
                                        <Heading className="text-[30px] leading-[1.2] font-extrabold tracking-[-0.4px] text-white md:text-[60px] xl:text-[90px]">
                                            {slide.titleLines.map((line, lineIndex) => (
                                                <Fragment key={line}>
                                                    {line}
                                                    {lineIndex < slide.titleLines.length - 1 && <br />}
                                                </Fragment>
                                            ))}
                                        </Heading>
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {showControls && (
                <div className="absolute top-1/2 right-4 z-20 flex -translate-y-1/2 flex-col gap-2.5 md:top-[58%] md:right-[15px] md:gap-[10px]">
                    <button
                        type="button"
                        onClick={goToPreviousSlide}
                        aria-label="Previous slide"
                        aria-controls="hero-carousel-slides"
                        className="text-lpres-deep focus-visible:ring-lpres-green flex size-[50px] items-center justify-center rounded-full bg-white opacity-80 transition-opacity duration-300 hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none md:opacity-35"
                    >
                        <ArrowLeft aria-hidden="true" className="size-5" strokeWidth={2} />
                    </button>
                    <button
                        type="button"
                        onClick={goToNextSlide}
                        aria-label="Next slide"
                        aria-controls="hero-carousel-slides"
                        className="text-lpres-deep focus-visible:ring-lpres-green flex size-[50px] items-center justify-center rounded-full bg-white opacity-80 transition-opacity duration-300 hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none md:opacity-35"
                    >
                        <ArrowRight aria-hidden="true" className="size-5" strokeWidth={2} />
                    </button>
                </div>
            )}

            {showControls && (
                <div className="absolute inset-x-0 bottom-6 z-20 mx-auto flex w-full max-w-[1140px] items-center gap-4 px-3">
                    {autoplayAvailable && (
                        <button
                            type="button"
                            onClick={() => setIsPaused((paused) => !paused)}
                            aria-label={isPaused ? 'Resume automatic slide rotation' : 'Pause automatic slide rotation'}
                            className="focus-visible:ring-lpres-green flex size-9 items-center justify-center rounded-full border border-white/60 text-white transition-colors duration-300 hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-transparent focus-visible:outline-none"
                        >
                            {isPaused ? <Play aria-hidden="true" className="size-4" /> : <Pause aria-hidden="true" className="size-4" />}
                        </button>
                    )}

                    <div className="flex items-center gap-1">
                        {slides.map((slide, index) => {
                            const isActive = index === activeIndex;

                            return (
                                <button
                                    key={slide.id}
                                    type="button"
                                    onClick={() => goToSlide(index)}
                                    aria-label={`Go to slide ${index + 1} of ${slides.length}`}
                                    aria-controls="hero-carousel-slides"
                                    aria-current={isActive ? 'true' : undefined}
                                    className="focus-visible:ring-lpres-green group flex h-6 items-center px-0.5 focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'block h-1.5 rounded-full transition-[width,background-color] duration-300 motion-reduce:transition-none',
                                            isActive ? 'w-8 bg-white' : 'w-3 bg-white/50 group-hover:bg-white/80',
                                        )}
                                    />
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            <div className="sr-only" aria-live={hasUserInteracted ? 'polite' : 'off'} aria-atomic="true">
                {activeSlide ? `Slide ${activeIndex + 1} of ${slides.length}: ${activeSlide.title}` : ''}
            </div>
        </section>
    );
}
