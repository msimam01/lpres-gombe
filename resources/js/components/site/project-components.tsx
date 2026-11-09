import { projectComponents as fallbackComponents } from '@/config/site';

export interface ProjectComponentItem {
    id: number;
    title: string;
    description: string | null;
    image: string | null;
}

interface ProjectComponentsProps {
    /** Component rows managed through the admin panel (already ordered). */
    components: ProjectComponentItem[];
}

export default function ProjectComponents({ components }: ProjectComponentsProps) {
    return (
        <section aria-label="L-PRES Project Components" className="bg-white">
            <div className="bg-lpres-green w-full">
                <div className="mx-auto flex min-h-[76px] w-full max-w-[1140px] items-center gap-5 px-5 py-3 sm:min-h-[90px] sm:gap-[35px] sm:px-6 sm:py-0 xl:px-0">
                    <span aria-hidden="true" className="lpres-valve-icon shrink-0 text-[44px] leading-none text-white sm:text-[60px]" />

                    <h2 id="project-components-heading" className="text-white">
                        <span className="block text-base leading-[30px] font-normal">{fallbackComponents.eyebrow}</span>
                        <span className="block text-[26px] leading-8 font-extrabold tracking-[-0.4px] sm:text-[34px] sm:leading-10">
                            {fallbackComponents.heading}
                        </span>
                    </h2>
                </div>
            </div>

            <div className="mx-auto w-full max-w-[1140px] px-5 pt-14 pb-20 sm:px-6 sm:pt-[70px] lg:pb-24 xl:px-0">
                <div className="grid grid-cols-1 gap-x-5 gap-y-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-0">
                    <div className="lg:p-2.5">
                        <img
                            src={fallbackComponents.image}
                            alt={fallbackComponents.imageAlt}
                            width={fallbackComponents.width}
                            height={fallbackComponents.height}
                            loading="lazy"
                            decoding="async"
                            className="block aspect-[525/352] w-full object-cover"
                        />
                    </div>

                    {components.map((component, index) => (
                        <div key={component.id} className="lg:p-2.5">
                            <span aria-hidden="true" className="bg-lpres-green block h-0.5 w-[35px]" />

                            <p className="text-lpres-green mt-[13px] text-[14px] leading-6 font-bold tracking-[1px] uppercase">
                                Component {index + 1}
                            </p>

                            <h3 className="mt-8 text-base leading-[30px] font-medium text-black">{component.title}</h3>
                            {component.description && (
                                <p className="mt-2 text-sm leading-6 font-medium text-[#838e91]">{component.description}</p>
                            )}
                        </div>
                    ))}
                </div>

                {components.length === 0 && (
                    <p aria-live="polite" className="mt-10 text-base text-[#838e91]">
                        Project components will be published here once they are added in the admin panel.
                    </p>
                )}
            </div>
        </section>
    );
}
