import { siteFooter } from '@/config/site';

/**
 * Footer band: logo/description column, Explore and Useful Links columns and
 * the Contact block (address, mailto: and tel: links) over the flat #003b49
 * ink background — the theme's footer pattern image is a 404 on the live
 * site itself, so the original footer renders flat ink — with the bottom
 * copyright strip.
 *
 * Live-sourced values: footer padding 100px top / 10px bottom, logo 78px
 * atop 35px gap, headings 16px white, links #778e91 with 8px list spacing,
 * copyright strip separated by a hairline and padded 15px vertically.
 *
 * The verified X/Facebook/Instagram/YouTube/TikTok/LinkedIn addresses stay
 * in the siteFooter config for the upcoming social block; the supplied
 * footer content for this phase contains no social column, so none is
 * rendered here.
 */
export default function SiteFooter() {
    const { logo, logoAlt, description, explore, usefulLinks, contact, copyright } = siteFooter;

    return (
        <footer id="site-footer" className="bg-lpres-ink text-[#778e91]">
            <div className="mx-auto w-full max-w-[1200px] px-5 pt-[100px] pb-[10px] sm:px-6 lg:px-0">
                <div className="grid gap-y-10 md:grid-cols-2 lg:grid-cols-[350px_repeat(2,minmax(0,1fr))_minmax(0,1.35fr)] lg:gap-x-8">
                    <div>
                        <a href="/" aria-label="Gombe State L-PRES home">
                            <img src={logo} alt={logoAlt} width={185} height={85} loading="lazy" decoding="async" className="h-20 w-auto" />
                        </a>
                        <p className="mt-[35px] max-w-[300px] text-base leading-[26px]">{description}</p>
                    </div>

                    <nav aria-labelledby="footer-explore-heading">
                        <h3 id="footer-explore-heading" className="text-base leading-9 font-extrabold text-white">
                            Explore
                        </h3>
                        <ul className="mt-[5px] space-y-2">
                            {explore.map((link) => (
                                <li key={link.label}>
                                    <a
                                        href={link.href}
                                        className="transition-colors duration-500 hover:text-lpres-green focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lpres-green"
                                    >
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <nav aria-labelledby="footer-useful-links-heading">
                        <h3 id="footer-useful-links-heading" className="text-base leading-9 font-extrabold text-white">
                            Useful Links
                        </h3>
                        <ul className="mt-[5px] space-y-2">
                            {usefulLinks.map((link) => (
                                <li key={link.label}>
                                    <a
                                        href={link.href}
                                        {...(link.external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                                        className="transition-colors duration-500 hover:text-lpres-green focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lpres-green"
                                    >
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <div>
                        <h3 className="text-base leading-9 font-extrabold text-white">Contact</h3>
                        <address className="mt-[5px] space-y-2 not-italic">
                            <p>
                                {contact.addressLines.map((line) => (
                                    <span key={line} className="block">
                                        {line}
                                    </span>
                                ))}
                            </p>
                            <p>
                                <a
                                    href={`mailto:${contact.email}`}
                                    className="transition-colors duration-500 hover:text-lpres-green focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lpres-green"
                                >
                                    {contact.email}
                                </a>
                            </p>
                            <p>
                                <a
                                    href={`tel:${contact.phone.replace(/\s+/g, '')}`}
                                    className="transition-colors duration-500 hover:text-lpres-green focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lpres-green"
                                >
                                    {contact.phone}
                                </a>
                            </p>
                        </address>
                    </div>
                </div>

                <div className="mt-[50px] border-t border-solid border-[#ffffff1a] py-[15px] text-center max-lg:text-left">
                    <p className="text-sm leading-6">&copy; {copyright}</p>
                </div>
            </div>
        </footer>
    );
}
