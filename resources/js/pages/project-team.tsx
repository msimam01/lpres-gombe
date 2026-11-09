import React, { useState } from 'react';

import PublicPage from '@/layouts/public-page';
import EmptyState from '@/components/site/empty-state';

interface TeamMemberCard {
    id: number;
    name: string;
    image: string | null;
    designation: string | null;
    category: string | null;
}

interface ProjectTeamProps {
    members: TeamMemberCard[];
}

/** Circular portrait; broken/missing files fall back to the member's initial. */
function TeamPortrait({ name, image }: { name: string; image: string | null }) {
    const [failed, setFailed] = useState(false);

    if (!image || failed) {
        return (
            <div aria-hidden="true" className="grid size-full place-items-center text-4xl font-extrabold text-lpres-deep/25">
                {name.slice(0, 1).toUpperCase()}
            </div>
        );
    }

    return (
        <img
            src={image}
            alt={name}
            width={160}
            height={160}
            loading="lazy"
            decoding="async"
            onError={() => setFailed(true)}
            className="block size-full object-cover"
        />
    );
}

/** Supplied SPC summary for the introduction section (editorial copy). */
const SPC_SUMMARY =
    'Professor Usman Bello Abubakar was born on January 22nd, 1970, in Tongo, Funakaye Local Government Area of Gombe State. He is a highly respected figure in the field of veterinary medicine. Professor U.B. Abubakar’s journey to excellence has been marked by a strong work ethic and a dedication to continuous learning. He possesses diverse experience in academia, public service, and community leadership, coupled with extensive knowledge, skills, and expertise.';

const SPC_PORTRAIT = '/images/lpres/spc-prof-u-b-abubakar.png';

/**
 * Project Team page, modelled on the original site's team presentation:
 * a two-column SPC introduction with decorative brush-stroke artwork, then
 * the "Project Specialists" grid with circular portraits in white cards,
 * grouped by their managed team category. Ordinary members show only
 * photograph, name and designation — only the SPC intro has a Read More
 * link, and it opens the local dedicated profile at /spc-profile.
 */
export default function ProjectTeam({ members }: ProjectTeamProps) {
    const grouped = new Map<string, TeamMemberCard[]>();
    for (const member of members) {
        const key = member.category?.trim() || 'Project Team';
        grouped.set(key, [...(grouped.get(key) ?? []), member]);
    }

    return (
        <PublicPage title="Project Team" eyebrow="Meet the team" description="The project management and delivery team of Gombe State L-PRES.">
            {/* A. SPC introduction */}
            <section aria-labelledby="spc-intro-heading" className="relative mb-16 overflow-hidden bg-lpres-mist p-6 sm:p-10 lg:p-14">
                {/* dotted decorative background */}
                <div
                    aria-hidden="true"
                    className="absolute inset-0 opacity-25"
                    style={{ backgroundImage: 'radial-gradient(#9db7bd 1px, transparent 1px)', backgroundSize: '22px 22px' }}
                />

                <div className="relative grid items-center gap-10 lg:grid-cols-2">
                    {/* Portrait with decorative brush-stroke artwork behind it */}
                    <div className="relative mx-auto w-full max-w-[380px]">
                        <span
                            aria-hidden="true"
                            className="bg-lpres-green/85 absolute -top-4 -left-4 -z-10 block h-full w-full rounded-[48%_52%_55%_45%/55%_48%_52%_45%] lg:-left-8"
                        />
                        <img
                            src={SPC_PORTRAIT}
                            alt="Official portrait of the State Project Coordinator, Professor U.B. Abubakar"
                            width={348}
                            height={420}
                            className="relative block w-full object-cover"
                            decoding="async"
                        />
                    </div>

                    <div>
                        <p className="text-lpres-green flex items-center gap-3 text-sm font-bold tracking-[1.5px] uppercase">
                            <span aria-hidden="true" className="bg-lpres-green inline-block h-0.5 w-8" />
                            Meet The
                        </p>
                        <h2 id="spc-intro-heading" className="text-lpres-deep mt-2 text-3xl leading-10 font-extrabold lg:text-4xl lg:leading-11">
                            State Project Coordinator
                        </h2>

                        <div className="mt-6 border border-[#e1e8ea] bg-white p-6">
                            <div className="flex items-center gap-3">
                                <span aria-hidden="true" className="grid size-8 place-items-center rounded-full bg-lpres-green/15">
                                    <svg viewBox="0 0 20 20" fill="currentColor" className="text-lpres-green size-4">
                                        <path d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" />
                                    </svg>
                                </span>
                                <p className="text-sm font-extrabold tracking-[0.5px] text-lpres-deep uppercase">Profile</p>
                            </div>
                            <p className="mt-4 text-[15px] leading-[26px] text-[#4b5e65]">{SPC_SUMMARY}</p>
                        </div>

                        <a
                            href="/spc-profile"
                            className="focus-visible:outline-lpres-deep mt-8 inline-flex items-center gap-2 text-lg font-extrabold tracking-[0.5px] text-lpres-green uppercase transition-colors hover:text-lpres-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-none"
                        >
                            <span aria-hidden="true" className="bg-lpres-green inline-block h-[3px] w-10 transition-colors group-hover:bg-lpres-deep" />
                            Read More
                        </a>
                    </div>
                </div>
            </section>

            {/* B. Project Specialists */}
            {members.length === 0 ? (
                <EmptyState message="Project specialists added in the admin panel will appear here with their names and designations." />
            ) : (
                [...grouped.entries()].map(([category, categoryMembers]) => (
                    <section key={category} aria-labelledby={`team-${category.replace(/\W+/g, '-')}`} className="relative mb-16 overflow-hidden bg-[#f3f6f7] p-6 sm:p-10 last:mb-0 lg:p-14">
                        {/* dotted decorative background */}
                        <div
                            aria-hidden="true"
                            className="absolute inset-0 opacity-25"
                            style={{ backgroundImage: 'radial-gradient(#9db7bd 1px, transparent 1px)', backgroundSize: '22px 22px' }}
                        />

                        <div className="relative">
                        <div className="relative mb-10 inline-block">
                            <p className="text-lpres-green text-sm font-bold tracking-[1.5px] uppercase">Meet Our</p>
                            <h2 id={`team-${category.replace(/\W+/g, '-')}`} className="text-lpres-deep mt-2 text-3xl leading-10 font-extrabold lg:text-4xl">
                                {category}
                            </h2>
                        </div>

                        <ul className="grid list-none grid-cols-2 gap-x-8 gap-y-12 lg:grid-cols-4 xl:gap-x-12">
                            {categoryMembers.map((member) => (
                                <li key={member.id} className="bg-white p-6 text-center shadow-[0_2px_14px_rgba(0,59,73,0.08)]">
                                    {/* Circular portrait with a dashed ring */}
                                    <div className="relative mx-auto max-w-[170px]">
                                        <span aria-hidden="true" className="absolute inset-0 rounded-full border-2 border-dashed border-lpres-green/60" />
                                        <div className="m-2 overflow-hidden rounded-full bg-lpres-mist aspect-square">
                                            <TeamPortrait name={member.name} image={member.image} />
                                        </div>
                                    </div>

                                    <h3 className="text-lpres-deep mt-4 text-lg leading-7 font-extrabold">{member.name}</h3>
                                    {member.designation && <p className="mt-1 text-sm leading-6 text-[#4b5e65]">{member.designation}</p>}
                                </li>
                            ))}
                        </ul>
                        </div>
                    </section>
                ))
            )}
        </PublicPage>
    );
}
