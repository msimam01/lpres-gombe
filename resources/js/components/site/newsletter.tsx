import { router, usePage } from '@inertiajs/react';
import { FormEvent, FormEventHandler, useEffect, useState } from 'react';

import { newsletter } from '@/config/site';
import { type SharedData } from '@/types';

/**
 * Newsletter band. Reproduces the green "Stay Connected / Join Our Newsletter"
 * strip with the email field and Subscribe button.
 *
 * Submission goes to the real backend workflow (`/newsletter/subscribe`,
 * Laravel-managed subscribers), and success is reported only from the
 * actual server response — never fabricated client-side.
 */
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

interface NewsletterFlash extends SharedData {
    flash: { newsletter: string | null };
}

export default function Newsletter() {
    const { heading, subheading, placeholder, button } = newsletter;
    const [email, setEmail] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const flash = usePage<NewsletterFlash>().props.flash.newsletter;

    useEffect(() => {
        if (flash) {
            setProcessing(false);
            setEmail('');
        }
    }, [flash]);

    const handleSubmit: FormEventHandler<HTMLFormElement> = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!EMAIL_PATTERN.test(email.trim())) {
            setError('Please enter a valid email address.');
            return;
        }

        setError(null);
        setProcessing(true);
        router.post('/newsletter/subscribe', { email: email.trim() }, { preserveScroll: true });
    };

    return (
        <section
            aria-labelledby="newsletter-heading"
            className="bg-lpres-green bg-cover bg-repeat py-[60px]"
            style={{ backgroundImage: 'url(/images/lpres/cta-two-bg-1.png)' }}
        >
            <div className="mx-auto flex w-full max-w-[1200px] flex-col items-center gap-8 px-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-0">
                <div className="flex items-center max-lg:text-center">
                    <span
                        aria-hidden="true"
                        className="lpres-icon-envelope text-[60px] leading-none text-white"
                    />
                    <div className="ml-[35px] max-md:ml-5">
                        <p className="text-base leading-[30px] text-white">{subheading}</p>
                        <h2 id="newsletter-heading" className="text-[34px] leading-10 font-extrabold tracking-[-0.4px] text-white">
                            {heading}
                        </h2>
                    </div>
                </div>

                <form onSubmit={handleSubmit} noValidate className="w-full max-w-[570px]" aria-describedby="newsletter-status">
                    <label htmlFor="newsletter-email" className="sr-only">
                        Email address
                    </label>
                    <div className="flex max-md:flex-col">
                        <input
                            id="newsletter-email"
                            name="email"
                            type="email"
                            autoComplete="email"
                            required
                            placeholder={placeholder}
                            value={email}
                            onChange={(event) => {
                                setEmail(event.target.value);
                                setError(null);
                            }}
                            aria-invalid={Boolean(error)}
                            className="h-[60px] w-full border-0 bg-white px-[25px] text-base font-semibold text-[#838e91] outline-none placeholder:text-[#838e91] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lpres-deep max-md:mb-5"
                        />
                        <button
                            type="submit"
                            disabled={processing}
                            className="ml-2.5 flex h-[60px] shrink-0 items-center bg-lpres-deep px-[49px] text-sm font-extrabold text-white transition-colors duration-500 hover:bg-[#002733] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lpres-deep disabled:opacity-60 max-md:ml-0 max-md:justify-center"
                        >
                            {button}
                        </button>
                    </div>

                    <p id="newsletter-status" role="status" aria-live="polite" className="mt-3 text-sm leading-6 font-semibold text-white">
                        {error ?? flash ?? null}
                    </p>
                </form>
            </div>
        </section>
    );
}
