/**
 * Shared photo gallery for project, event and news pages. A restrained,
 * responsive grid of uploaded gallery images — consistent aspect ratios, no
 * distortion, lazy loading and a subtle hover zoom. When `onSelect` is given
 * each image becomes a button that opens a larger viewer.
 */
interface ImageGalleryProps {
    items: { src: string; alt: string }[];
    onSelect?: (index: number) => void;
}

export default function ImageGallery({ items, onSelect }: ImageGalleryProps) {
    if (items.length === 0) {
        return null;
    }

    return (
        <ul className="grid list-none grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((item, index) => {
                const image = (
                    <img
                        src={item.src}
                        alt={item.alt}
                        width={640}
                        height={420}
                        loading={index === 0 ? 'eager' : 'lazy'}
                        decoding="async"
                        className="block aspect-[640/420] w-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none"
                    />
                );

                return (
                    <li key={item.src} className="group overflow-hidden bg-[#f1f5f6]">
                        {onSelect ? (
                            <button
                                type="button"
                                onClick={() => onSelect(index)}
                                aria-label={`View image ${index + 1} full size`}
                                className="focus-visible:outline-lpres-green block w-full cursor-zoom-in focus-visible:outline-2 focus-visible:outline-offset-2"
                            >
                                {image}
                            </button>
                        ) : (
                            image
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
