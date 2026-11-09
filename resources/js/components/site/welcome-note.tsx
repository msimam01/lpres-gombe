import { welcomeNote } from '@/config/site';

export default function WelcomeNote() {
    return (
        <section aria-labelledby="welcome-heading" className="bg-[#f3f8f5]">
            <div className="mx-auto grid w-full max-w-[1200px] px-5 pt-16 pb-16 sm:px-6 sm:pt-20 sm:pb-20 lg:grid-cols-2 lg:pt-[60px] lg:pb-[107px] xl:px-0">
                <div className="mx-auto w-full max-w-[422px] lg:mx-0 lg:ml-4 xl:ml-[15px]">
                    <figure className="relative">
                        <div className="relative">
                            <img
                                src={welcomeNote.image}
                                alt={welcomeNote.imageAlt}
                                width={welcomeNote.width}
                                height={welcomeNote.height}
                                loading="lazy"
                                decoding="async"
                                className="block aspect-[422/591] w-full object-cover"
                            />
                            <p
                                aria-hidden="true"
                                className="font-signature text-lpres-green absolute right-3 bottom-1 text-[clamp(26px,2.9vw,54px)] leading-none whitespace-nowrap"
                            >
                                {welcomeNote.coordinator}
                            </p>
                        </div>

                        <span aria-hidden="true" className="bg-lpres-green mt-[10px] block h-[10px] w-full" />

                        <figcaption className="sr-only">Coordinator: {welcomeNote.coordinator}</figcaption>
                    </figure>
                </div>

                <div className="mt-12 lg:mt-0 lg:ml-10 lg:max-w-[calc(100%-2.5rem)] lg:pt-[57px] xl:ml-[75px] xl:max-w-[510px]">
                    <span aria-hidden="true" className="bg-lpres-green mb-[13px] block h-0.5 w-[35px]" />

                    <h2
                        id="welcome-heading"
                        className="text-lpres-deep text-[34px] leading-[1.15] font-extrabold tracking-[-0.4px] sm:text-[42px] lg:text-[50px] lg:leading-[60px]"
                    >
                        {welcomeNote.heading}
                    </h2>

                    <div className="mt-[30px] space-y-5 text-base leading-[30px] text-black">
                        {welcomeNote.paragraphs.map((paragraph, index) => (
                            <p key={index}>{paragraph}</p>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
