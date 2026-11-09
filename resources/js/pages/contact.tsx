import { Link } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';

import PublicPage from '@/layouts/public-page';

/**
 * Contact Us page. Address/email/phone come from the admin-managed site
 * settings, shown beside a map preview of the project's location. There is
 * deliberately no public enquiry form.
 */
export default function ContactPage({ contact }: { contact: { address: string | null; email: string | null; phone: string | null } }) {
    return (
        <PublicPage title="Contact Us" eyebrow="Reach the project office" description="Get in touch with the Gombe State L-PRES project office.">
            <div className="bg-white">
                <section aria-label="Office details" className="mb-10 grid gap-6 sm:grid-cols-3">
                    <div className="bg-[#f1f5f6] p-6">
                        <h2 className="text-lpres-deep flex items-center gap-2 text-lg font-extrabold">
                            <MapPin aria-hidden="true" className="text-lpres-green size-5" />
                            Office
                        </h2>
                        <p className="mt-2 text-base leading-6 text-[#4b5e65]">{contact.address || 'GSADP Quarters, Opposite Buhari Estate, Gombe, Gombe State Nigeria'}</p>
                    </div>
                    <div className="bg-[#f1f5f6] p-6">
                        <h2 className="text-lpres-deep flex items-center gap-2 text-lg font-extrabold">
                            <Mail aria-hidden="true" className="text-lpres-green size-5" />
                            Email
                        </h2>
                        {contact.email ? (
                            <a href={`mailto:${contact.email}`} className="mt-2 block text-base leading-6 text-[#4b5e65] underline hover:text-lpres-green">
                                {contact.email}
                            </a>
                        ) : (
                            <p className="mt-2 text-base leading-6 text-[#4b5e65]">To be published.</p>
                        )}
                    </div>
                    <div className="bg-[#f1f5f6] p-6">
                        <h2 className="text-lpres-deep flex items-center gap-2 text-lg font-extrabold">
                            <Phone aria-hidden="true" className="text-lpres-green size-5" />
                            Phone
                        </h2>
                        {contact.phone ? (
                            <a href={`tel:${contact.phone.replace(/\s+/g, '')}`} className="mt-2 block text-base leading-6 text-[#4b5e65] underline hover:text-lpres-green">
                                {contact.phone}
                            </a>
                        ) : (
                            <p className="mt-2 text-base leading-6 text-[#4b5e65]">To be published.</p>
                        )}
                    </div>
                </section>

                <section aria-label="Location map" className="mb-6">
                    <div className="min-h-[320px] border border-[#e1e8ea] bg-[#f1f5f6] sm:min-h-[420px]">
                        {/* OpenStreetMap embed centered on Gombe — decorative
                            context, not a claimed office pin. */}
                        <iframe
                            title="Map preview showing Gombe, Gombe State, Nigeria"
                            src="https://www.openstreetmap.org/export/embed.html?bbox=11.1153%2C10.2383%2C11.2353%2C10.3183&layer=mapnik&marker=10.2783%2C11.1753"
                            loading="lazy"
                            className="h-full min-h-[320px] w-full border-0 sm:min-h-[420px]"
                        />
                    </div>
                </section>

                <div className="max-w-[780px] border-l-4 border-lpres-green bg-[#f1f5f6] p-5">
                    <p className="text-base leading-6 text-[#4b5e65]">
                        For direct enquiries, reach the project office using the details above — our team responds as soon as possible.
                    </p>
                    <Link href="/about" className="text-lpres-green mt-3 inline-block text-sm font-bold tracking-[1px] uppercase no-underline hover:underline">
                        Learn more about L-PRES
                    </Link>
                </div>
            </div>
        </PublicPage>
    );
}
