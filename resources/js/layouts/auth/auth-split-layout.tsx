import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Mail, MapPin, Phone } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

/**
 * The L-PRES administrative sign-in layout. The left half is the site's deep
 * teal brand panel — real L-PRES logo on a white badge, project blurb and the
 * office contact details shared from the admin-managed site settings —
 * decorated with soft green glows and the dotted texture used across the
 * public site. The right half holds the form card on white, with the L-PRES
 * logo above it so the brand is present at every viewport width.
 */
export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    const { name, contact } = usePage<SharedData>().props;

    return (
        <div className="font-lpres grid min-h-dvh flex-col lg:grid-cols-2">
            {/* Brand panel */}
            <aside className="relative hidden overflow-hidden bg-lpres-ink lg:block">
                {/* Soft green glows */}
                <div aria-hidden="true" className="bg-lpres-green/20 absolute -top-32 -right-32 size-[420px] rounded-full blur-[120px]" />
                <div aria-hidden="true" className="bg-lpres-green/10 absolute bottom-[-140px] left-[-100px] size-[380px] rounded-full blur-[110px]" />
                {/* Dotted texture, echoing the public site's decorative background */}
                <div
                    aria-hidden="true"
                    className="absolute inset-0 opacity-[0.08]"
                    style={{ backgroundImage: 'radial-gradient(rgba(255,255,255,0.9) 1px, transparent 1px)', backgroundSize: '24px 24px' }}
                />

                <div className="relative z-10 flex h-full flex-col p-10 xl:p-14">
                    <Link href="/" className="flex items-center gap-4">
                        <span className="inline-block rounded-lg bg-white p-2 shadow-sm">
                            <img src="/images/lpres/lpres-logo.jpeg" alt="Gombe State L-PRES" className="h-11 w-auto object-contain" />
                        </span>
                        <span className="text-white">
                            <span className="block text-base leading-5 font-extrabold">{name}</span>
                            <span className="text-lpres-green block text-xs tracking-[2px] uppercase">Livestock Productivity &amp; Resilience</span>
                        </span>
                    </Link>

                    <div className="mt-auto mb-auto max-w-md py-12">
                        <span aria-hidden="true" className="bg-lpres-green mb-6 block h-1 w-[55px]" />
                        <h2 className="text-3xl leading-snug font-extrabold text-white xl:text-4xl">
                            Improving livestock productivity, resilience and commercialisation in Gombe&nbsp;State.
                        </h2>
                        <p className="mt-5 text-[15px] leading-7 text-white/70">
                            This portal is for L-PRES staff: publish news and events, manage projects, the project team, value chain clusters
                            and visual reports — every update appears on the public website immediately.
                        </p>
                    </div>

                    <ul className="space-y-2.5 text-sm text-white/75">
                        {contact.address && (
                            <li className="flex items-start gap-2.5">
                                <MapPin aria-hidden="true" className="text-lpres-green mt-0.5 size-4 shrink-0" />
                                <span>{contact.address}</span>
                            </li>
                        )}
                        {contact.email && (
                            <li className="flex items-center gap-2.5">
                                <Mail aria-hidden="true" className="text-lpres-green size-4 shrink-0" />
                                <a href={`mailto:${contact.email}`} className="transition-colors hover:text-white">
                                    {contact.email}
                                </a>
                            </li>
                        )}
                        {contact.phone && (
                            <li className="flex items-center gap-2.5">
                                <Phone aria-hidden="true" className="text-lpres-green size-4 shrink-0" />
                                <span>{contact.phone}</span>
                            </li>
                        )}
                    </ul>
                </div>
            </aside>

            {/* Form column */}
            <div className="flex flex-col bg-white">
                <div className="flex flex-1 flex-col items-center justify-center px-6 py-12 sm:px-10">
                    <div className="w-full max-w-[430px]">
                        <Link href="/" className="mb-10 flex flex-col items-center gap-3 lg:hidden">
                            <img src="/images/lpres/lpres-logo.jpeg" alt="Gombe State L-PRES" className="h-12 w-auto object-contain" />
                        </Link>

                        <div className="mb-8 flex flex-col gap-2 text-center">
                            <h1 className="font-lpres text-2xl font-extrabold text-[#223645]">{title}</h1>
                            {description && <p className="text-sm leading-6 text-[#838e91]">{description}</p>}
                        </div>

                        {children}

                        <Link
                            href="/"
                            className="text-lpres-green mt-10 flex items-center justify-center gap-2 text-sm font-bold tracking-[0.5px] uppercase transition-colors hover:text-lpres-deep"
                        >
                            <ArrowLeft aria-hidden="true" className="size-4" />
                            Return to website
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
