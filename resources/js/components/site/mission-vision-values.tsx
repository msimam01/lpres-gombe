import { missionVisionValues } from '@/config/site';

/**
 * Mission, Vision and Core Values. On the live home page this is a single
 * white section: the three text blocks on the left, and on the right the
 * "image showcase" — the coordinator's photograph with the theme's white
 * mountain shape floating out to its left and bottom.
 *
 * Live-sourced geometry (1440px): 1200px content, 120px top / 100px bottom
 * padding, 50%/50% columns, photo 570x438, shape 444x246 at left -160px
 * bottom -120px behind the photo.
 */
export default function MissionVisionValues() {
    const { blocks, image, imageAlt, width, height, shape } = missionVisionValues;

    return (
        <section
            aria-label="Our mission, vision and core values"
            className="bg-[#f2f5f6] pt-[120px] pb-[100px] max-[1199px]:pt-[70px] max-[1199px]:pb-[70px]"
        >
            <div className="mx-auto flex w-full max-w-[1200px] flex-col px-5 sm:px-6 lg:flex-row lg:items-start lg:px-0">
                {/* Left: the three statement blocks. */}
                <div className="lg:w-1/2 lg:px-[15px]">
                    <div className="flex flex-col gap-8 max-lg:order-1">
                        {blocks.map((block) => (
                            <div key={block.id}>
                                <h3 className="text-base font-extrabold text-lpres-deep">{block.title}</h3>
                                <p className="mt-2 max-w-[410px] text-base leading-6 font-medium text-[#838e91]">
                                    {block.text}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Right: photograph overhanging the white mountain shape. */}
                <div className="relative mt-12 lg:mt-0 lg:w-1/2 lg:px-[15px]">
                    <div className="relative mx-0 ml-0 mr-auto w-full min-[420px]:mr-[30px] lg:ml-[30px] lg:w-[570px] lg:max-w-full">
                        <img
                            aria-hidden="true"
                            src={shape.image}
                            alt=""
                            width={shape.width}
                            height={shape.height}
                            loading="lazy"
                            decoding="async"
                            className="absolute w-[84%] min-w-[280px] max-w-[444px] bottom-[-40px] left-[-24%] max-lg:hidden"
                        />
                        <img
                            src={image}
                            alt={imageAlt}
                            width={width}
                            height={height}
                            loading="lazy"
                            decoding="async"
                            className="relative h-auto w-full object-cover max-lg:mr-0 lg:w-[570px]"
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}
