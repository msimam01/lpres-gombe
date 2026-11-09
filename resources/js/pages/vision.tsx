import PublicPage from '@/layouts/public-page';
import { missionVisionValues } from '@/config/site';

const vision = missionVisionValues.blocks.find((block) => block.id === 'vision');

export default function Vision() {
    return (
        <PublicPage
            title="Our Vision"
            eyebrow="About L-PRES"
            description={vision?.text}
            breadcrumbs={[{ title: 'About Us', href: '/about' }, { title: 'Our Vision', href: '' }]}
        >
            <div className="bg-white">
                <span aria-hidden="true" className="bg-lpres-green block h-0.5 w-[35px]" />
                <h2 className="text-lpres-deep mt-3 text-2xl leading-[34px] font-extrabold">{vision?.title}</h2>
                <p className="mt-5 text-xl leading-[34px] text-[#4b5e65]">{vision?.text}</p>

                <div className="mt-10 border-t border-[#e1e8ea] pt-8">
                    <p className="text-base leading-[27px] text-[#838e91]">
                        Realising the vision depends on raising productivity in the Sheep &amp; Goat, Poultry, Beef and Dairy value chains, expanding
                        commercial services around them, and making Gombe State a leading livestock and agribusiness hub in Nigeria.
                    </p>
                </div>
            </div>
        </PublicPage>
    );
}
