<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * Public document library: published records only, ordered by the
     * administrator-defined sort order. Uploaded files are never linked
     * directly; the browser gets the controlled download route instead.
     */
    public function index(): Response
    {
        return Inertia::render('documents/index', [
            'documents' => Document::published()
                ->get(['title', 'slug', 'description', 'file_path', 'url', 'updated_at'])
                ->map(fn (Document $document) => [
                    'title' => $document->title,
                    'description' => $document->description,
                    'format' => $document->hasFile()
                        ? strtoupper(pathinfo($document->file_path, PATHINFO_EXTENSION) ?: 'FILE')
                        : 'LINK',
                    'href' => $document->hasFile()
                        ? route('documents.download', $document->slug)
                        : $document->url,
                    'external' => ! $document->hasFile(),
                    'updated' => $document->updated_at?->format('j M Y'),
                ])
                ->all(),
        ]);
    }

    /**
     * Stream an uploaded document from the private disk. Hidden documents and
     * missing files return 404, so the URL cannot be guessed.
     */
    public function download(Document $document): BinaryFileResponse|StreamedResponse
    {
        abort_unless($document->status === true && $document->hasFile(), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($document->file_path), 404);

        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);

        return $disk->download(
            $document->file_path,
            Str::slug($document->title).($extension !== '' ? '.'.$extension : ''),
        );
    }
}
