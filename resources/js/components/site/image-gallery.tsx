/**
 * Shared photo gallery for project and event detail pages. A restrained,
 * responsive grid of the record's uploaded gallery images — consistent
 * aspect ratios, no distortion, lazy loading, and a subtle hover zoom.
 */
interface ImageGalleryProps {
    items: { src: string; alt: string }[];
}

export default function ImageGallery({ items }: ImageGalleryProps) {
    if (items.length === 0) {
        return null;
    }

    return (
        <ul className="grid list-none grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((item, index) => (
                <li key={item.src} className="group overflow-hidden bg-[#f1f5f6]">
                    <img
                        src={item.src}
                        alt={item.alt}
                        width={640}
                        height={420}
                        loading={index === 0 ? 'eager' : 'lazy'}
                        decoding="async"
                        className="block aspect-[640/420] w-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none"
                    />
                </li>
            ))}
        </ul>
    );
}
