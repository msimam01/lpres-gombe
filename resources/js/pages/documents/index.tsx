import { Download, ExternalLink, FileText } from 'lucide-react';

import EmptyState from '@/components/site/empty-state';
import PublicPage from '@/layouts/public-page';

interface DocumentItem {
    title: string;
    description: string | null;
    format: string;
    href: string | null;
    external: boolean;
    updated: string | null;
}

interface DocumentsIndexProps {
    documents: DocumentItem[];
}

/**
 * Public document library. Uploaded files are served through the controlled
 * download route; Google Drive/other links open in a new tab. Only published
 * records ever reach this page (filtered server-side).
 */
export default function DocumentsIndex({ documents }: DocumentsIndexProps) {
    return (
        <PublicPage
            title="Documents"
            eyebrow="Resources"
            description="Reports, guidelines and publications from the Gombe State Livestock Productivity and Resilience Support Project."
        >
            {documents.length > 0 ? (
                <ul className="grid list-none gap-6 sm:grid-cols-2">
                    {documents.map((document) => (
                        <li key={document.title}>
                            <article className="border-[#e1e8ea] group flex h-full gap-5 border bg-white p-6 transition-[box-shadow,border-color] duration-500 hover:border-transparent hover:shadow-[0_24px_44px_-24px_rgba(0,59,73,0.35)]">
                                <div aria-hidden="true" className="bg-lpres-green/10 text-lpres-green flex h-14 w-14 shrink-0 items-center justify-center">
                                    <FileText className="size-7" />
                                </div>

                                <div className="flex min-w-0 grow flex-col">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="bg-lpres-deep rounded-sm px-2 py-0.5 text-[10px] font-extrabold tracking-[1px] text-white uppercase">
                                            {document.format}
                                        </span>
                                        {document.updated && <span className="text-xs text-[#6f7b7e]">Updated {document.updated}</span>}
                                    </div>

                                    <h2 className="text-lpres-deep mt-2 text-lg leading-7 font-extrabold">{document.title}</h2>

                                    {document.description && (
                                        <p className="mt-2 line-clamp-3 text-sm leading-6 text-[#4b5e65]">{document.description}</p>
                                    )}

                                    {document.href && (
                                        <a
                                            href={document.href}
                                            {...(document.external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                                            className="text-lpres-deep hover:bg-lpres-green focus-visible:outline-lpres-green mt-auto inline-flex w-fit items-center gap-2 border border-[#cfdbdf] px-4 py-2 text-xs font-extrabold tracking-[1px] uppercase transition-colors duration-300 hover:border-transparent hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                                        >
                                            {document.external ? (
                                                <>
                                                    Open link
                                                    <ExternalLink aria-hidden="true" className="size-4" />
                                                </>
                                            ) : (
                                                <>
                                                    Download
                                                    <Download aria-hidden="true" className="size-4" />
                                                </>
                                            )}
                                            {document.external && <span className="sr-only">(opens in a new tab)</span>}
                                        </a>
                                    )}
                                </div>
                            </article>
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyState message="No documents have been published yet. Reports and publications will appear here once staff add them in the admin panel." />
            )}
        </PublicPage>
    );
}
