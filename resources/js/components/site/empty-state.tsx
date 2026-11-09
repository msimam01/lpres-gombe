interface EmptyStateProps {
    /** One clear sentence about what will appear here. */
    message: string;
}

export default function EmptyState({ message }: EmptyStateProps) {
    return (
        <div className="border-t border-[#e1e8ea] bg-white px-8 py-14 text-center">
            <span aria-hidden="true" className="bg-lpres-green mb-5 inline-block h-1 w-[55px]" />
            <p className="mx-auto max-w-[560px] text-lg leading-7 text-[#838e91]">{message}</p>
        </div>
    );
}
