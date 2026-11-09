import { Link } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';

export interface HomeContactSettings {
    address: string | null;
    email: string | null;
    phone: string | null;
}

/**
 * Homepage Contact section: admin-managed office details beside a map
 * preview of the project's location, with a clear link to the Contact Us
 * page. There is deliberately no enquiry form on the public website.
 */
export default function HomeContact({ contact }: { contact: HomeContactSettings }) {
    return (
        <section aria-labelledby="home-contact-heading" className="pt-[120px] pb-[120px] max-[1199px]:pt-[70px] max-[1199px]:pb-[70px]">
            <div className="mx-auto w-full max-w-[1200px] px-5 sm:px-6 lg:px-0">
                <div className="text-center">
                    <div className="relative inline-block">
                        <span aria-hidden="true" className="bg-lpres-green absolute top-[-13px] left-0 block h-0.5 w-[35px]" />
                        <p className="text-sm font-bold tracking-[1px] text-lpres-green uppercase">Get In Touch</p>
                    </div>
                    <h2 id="home-contact-heading" className="mt-[13px] mb-[30px] text-[50px] leading-tight text-[#223645] max-[1199px]:text-4xl">
                        Contact Us
                    </h2>
                </div>

                <div className="grid gap-10 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
                    <div className="grid content-start gap-5">
                        <div className="border border-[#e1e8ea] bg-[#f1f5f6] p-6">
                            <h3 className="text-lpres-deep flex items-center gap-2 text-lg font-extrabold">
                                <MapPin aria-hidden="true" className="text-lpres-green size-5" />
                                Office
                            </h3>
                            <p className="mt-2 text-base leading-6 text-[#4b5e65]">
                                {contact.address || 'GSADP Quarters, Opposite Buhari Estate, Gombe, Gombe State Nigeria'}
                            </p>
                        </div>

                        <div className="border border-[#e1e8ea] bg-[#f1f5f6] p-6">
                            <h3 className="text-lpres-deep flex items-center gap-2 text-lg font-extrabold">
                                <Mail aria-hidden="true" className="text-lpres-green size-5" />
                                Email
                            </h3>
                            {contact.email ? (
                                <a href={`mailto:${contact.email}`} className="mt-2 block text-base leading-6 text-[#4b5e65] underline hover:text-lpres-green">
                                    {contact.email}
                                </a>
                            ) : (
                                <p className="mt-2 text-base leading-6 text-[#4b5e65]">To be published.</p>
                            )}
                        </div>

                        <div className="border border-[#e1e8ea] bg-[#f1f5f6] p-6">
                            <h3 className="text-lpres-deep flex items-center gap-2 text-lg font-extrabold">
                                <Phone aria-hidden="true" className="text-lpres-green size-5" />
                                Phone
                            </h3>
                            {contact.phone ? (
                                <a href={`tel:${contact.phone.replace(/\s+/g, '')}`} className="mt-2 block text-base leading-6 text-[#4b5e65] underline hover:text-lpres-green">
                                    {contact.phone}
                                </a>
                            ) : (
                                <p className="mt-2 text-base leading-6 text-[#4b5e65]">To be published.</p>
                            )}
                        </div>

                        <Link href="/contact" className="focus-visible:outline-lpres-deep inline-flex h-[52px] items-center justify-center bg-lpres-deep px-8 text-sm font-extrabold tracking-[0.5px] text-white uppercase transition-colors duration-500 hover:bg-lpres-green focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-none sm:justify-self-start">
                            View Contact Details
                        </Link>
                    </div>

                    {/* Location preview. OpenStreetMap embed centered on Gombe —
                        the map is decorative context, not a claimed office pin. */}
                    <div className="min-h-[280px] border border-[#e1e8ea] bg-[#f1f5f6] sm:min-h-[360px] lg:min-h-full">
                        <iframe
                            title="Map preview showing Gombe, Gombe State, Nigeria"
                            src="https://www.openstreetmap.org/export/embed.html?bbox=11.1153%2C10.2383%2C11.2353%2C10.3183&layer=mapnik&marker=10.2783%2C11.1753"
                            loading="lazy"
                            className="h-full min-h-[280px] w-full border-0 sm:min-h-[360px]"
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}
