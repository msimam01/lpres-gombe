<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\Document;
use App\Rules\DocumentUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Documents module: admin-only CRUD for downloadable documents and Drive
 * links. Files are stored on the private disk with generated names and served
 * through the public download route; only the path is stored in the database.
 */
class DocumentAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: Document::class,
            routeBase: 'documents',
            label: 'Document',
            titleFrom: 'title',
            fields: [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                [
                    'key' => 'slug',
                    'label' => 'Slug',
                    'type' => 'text',
                    'help' => 'Leave blank to generate one from the title. Used in the download link.',
                ],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                [
                    'key' => 'file_path',
                    'label' => 'Document file',
                    'type' => 'document',
                    'removable' => true,
                    'help' => 'PDF, Word, Excel, CSV or PowerPoint up to 10 MB. Stored privately and served through a controlled download.',
                ],
                [
                    'key' => 'url',
                    'label' => 'External URL',
                    'type' => 'text',
                    'help' => 'Optional https link (for example a Google Drive file). Provide a file or a URL, not both.',
                ],
                [
                    'key' => 'sort_order',
                    'label' => 'Sort order',
                    'type' => 'integer',
                    'default' => 0,
                    'help' => 'Lower numbers are listed first on the public page.',
                ],
                ['key' => 'status', 'label' => 'Published', 'type' => 'boolean'],
            ],
        );
    }

    /**
     * Slug uniqueness, exclusive file/URL requirement and combined
     * extension + MIME validation (guards against renamed executables).
     */
    protected function validationRules(ModuleConfig $config, ?int $ignoreId = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('documents', 'slug')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
            'file_path' => [
                'nullable',
                'file',
                'required_without:url',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx'])->max('10mb'),
                'mimes:pdf,doc,docx,xls,xlsx,csv,ppt,pptx',
                'mimetypes:application/pdf,application/msword,'
                    .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                    .'application/vnd.ms-excel,'
                    .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                    .'text/csv,text/plain,application/vnd.ms-powerpoint,'
                    .'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
            'url' => ['nullable', 'string', 'max:2048', 'required_without:file_path', new DocumentUrl],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
            'remove_file_path' => ['sometimes', 'boolean'],
        ];
    }

    protected function applySlug(ModuleConfig $config, array $data): array
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['title'] ?? '');

        return $data;
    }

    /** Slug changes are allowed on update, still unique and URL-safe. */
    public function update(Request $request, int $id): RedirectResponse
    {
        $document = Document::query()->findOrFail($id);
        $data = $this->validatePayload($request, $this->config(), $id);

        // A blank slug keeps the existing public link; an explicit value moves it.
        $requested = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $requested !== ''
            ? $this->uniqueSlug($requested, $data['title'] ?? $document->title, $document->id)
            : ($document->slug ?: $this->uniqueSlug(null, $data['title'] ?? $document->title, $document->id));

        $document->update($data);
        $this->storeFiles($request, $this->config(), $document);

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Document updated.');
    }

    /**
     * Upload to the private disk (random filename), keep the previous file
     * until the new one is stored, and release the old file safely.
     */
    protected function storeFiles(Request $request, ModuleConfig $config, $record): void
    {
        $key = 'file_path';

        if ((bool) $request->boolean('remove_'.$key) && ! $request->hasFile($key)) {
            $previous = $record->{$key};
            $record->forceFill([$key => null])->save();

            if (filled($previous)) {
                $this->releaseFile($previous);
            }

            return;
        }

        if (! $request->hasFile($key)) {
            return;
        }

        $previous = $record->{$key};
        $path = $request->file($key)->store('documents', 'local');

        $record->forceFill([$key => $path])->save();

        if (filled($previous) && $previous !== $path) {
            $this->releaseFile($previous);
        }
    }

    /** Remove a document and its stored file when nothing else references it. */
    public function destroy(int $id): RedirectResponse
    {
        $document = Document::query()->findOrFail($id);
        $path = $document->file_path;

        $document->delete();

        if (filled($path)) {
            $this->releaseFile($path);
        }

        return redirect()->back()->with('success', 'Document deleted.');
    }

    protected function releaseFile(string $path): void
    {
        if (Document::query()->where('file_path', $path)->exists()) {
            return;
        }

        Storage::disk('local')->delete($path);
    }

    private function uniqueSlug(?string $requested, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim((string) $requested));

        if ($base === '') {
            $base = Str::slug(Str::limit($title, 120, '')) ?: 'document-'.Str::lower(Str::random(8));
        }

        $slug = $base;
        $attempt = 2;

        while (Document::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$attempt++;
        }

        return $slug;
    }
}
