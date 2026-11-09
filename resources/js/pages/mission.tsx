import PublicPage from '@/layouts/public-page';
import { missionVisionValues } from '@/config/site';

const mission = missionVisionValues.blocks.find((block) => block.id === 'mission');

export default function Mission() {
    return (
        <PublicPage
            title="Our Mission"
            eyebrow="About L-PRES"
            description={mission?.text}
            breadcrumbs={[{ title: 'About Us', href: '/about' }, { title: 'Our Mission', href: '' }]}
        >
            <div className="bg-white">
                <span aria-hidden="true" className="bg-lpres-green block h-0.5 w-[35px]" />
                <h2 className="text-lpres-deep mt-3 text-2xl leading-[34px] font-extrabold">{mission?.title}</h2>
                <p className="mt-5 text-xl leading-[34px] text-[#4b5e65]">{mission?.text}</p>

                <div className="mt-10 border-t border-[#e1e8ea] pt-8">
                    <p className="text-base leading-[27px] text-[#838e91]">
                        The mission is delivered through the project's three components — Institutional and Innovation System Strengthening, Livestock
                        Value Chain Enhancement, and Crisis Prevention and Management, Conflict Mitigation, Peace-Building, Environment and Coordination —
                        implemented across all local government areas of Gombe State.
                    </p>
                </div>
            </div>
        </PublicPage>
    );
}
