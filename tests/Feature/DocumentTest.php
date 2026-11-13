<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function documentEditor(): User
{
    return User::factory()->admin()->create();
}

function fakePdf(string $name = 'report.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 120, 'application/pdf');
}

it('creates a document with a generated slug and a privately stored file', function () {
    Storage::fake('local');

    $this->actingAs(documentEditor())
        ->post(route('admin.documents.store'), [
            'title' => 'Annual progress report',
            'description' => 'A summary of the year.',
            'sort_order' => 2,
            'status' => true,
            'file_path' => fakePdf(),
        ])
        ->assertRedirect(route('admin.documents.index'));

    $document = Document::query()->where('slug', 'annual-progress-report')->firstOrFail();

    expect($document->status)->toBeTrue()
        ->and($document->sort_order)->toBe(2)
        ->and($document->file_path)->not->toBeNull();

    Storage::disk('local')->assertExists($document->file_path);
});

it('accepts a Google Drive link instead of an uploaded file', function () {
    $this->actingAs(documentEditor())
        ->post(route('admin.documents.store'), [
            'title' => 'Drive hosted policy',
            'url' => 'https://drive.google.com/file/d/abc123/view?usp=sharing',
            'status' => true,
        ])
        ->assertRedirect(route('admin.documents.index'));

    $document = Document::query()->where('slug', 'drive-hosted-policy')->firstOrFail();

    expect($document->file_path)->toBeNull()
        ->and($document->url)->toBe('https://drive.google.com/file/d/abc123/view?usp=sharing');
});

it('requires either a file or a URL', function () {
    $this->actingAs(documentEditor())
        ->post(route('admin.documents.store'), ['title' => 'Nothing attached', 'status' => true])
        ->assertSessionHasErrors(['file_path', 'url']);

    $this->assertDatabaseCount('documents', 0);
});

it('rejects unsupported file types', function () {
    Storage::fake('local');

    $this->actingAs(documentEditor())
        ->post(route('admin.documents.store'), [
            'title' => 'Executable upload',
            'status' => true,
            'file_path' => UploadedFile::fake()->create('payload.exe', 20, 'application/octet-stream'),
        ])
        ->assertSessionHasErrors('file_path');

    Storage::disk('local')->assertDirectoryEmpty('documents');
});

it('rejects insecure links and non-Drive Google URLs', function () {
    $this->actingAs(documentEditor())
        ->post(route('admin.documents.store'), [
            'title' => 'Insecure link',
            'url' => 'http://drive.google.com/file/d/abc/view',
        ])
        ->assertSessionHasErrors('url');

    $this->actingAs(documentEditor())
        ->post(route('admin.documents.store'), [
            'title' => 'Wrong Google host',
            'url' => 'https://google.com/search?q=report',
        ])
        ->assertSessionHasErrors('url');
});

it('lists only published documents in sort order', function () {
    Document::query()->create([
        'title' => 'Second', 'slug' => 'second', 'sort_order' => 20, 'status' => true,
        'file_path' => 'documents/second.pdf',
    ]);
    Document::query()->create([
        'title' => 'First', 'slug' => 'first', 'sort_order' => 5, 'status' => true,
        'url' => 'https://drive.google.com/file/d/first/view',
    ]);
    Document::query()->create([
        'title' => 'Hidden', 'slug' => 'hidden', 'sort_order' => 1, 'status' => false,
        'file_path' => 'documents/hidden.pdf',
    ]);

    $this->get('/our-documents')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/index')
            ->has('documents', 2)
            ->where('documents.0.title', 'First')
            ->where('documents.0.external', true)
            ->where('documents.0.href', 'https://drive.google.com/file/d/first/view')
            ->where('documents.1.title', 'Second')
            ->where('documents.1.format', 'PDF')
            ->where('documents.1.href', url('/our-documents/second/download')));
});

it('downloads a privately stored document through the controlled route', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/guide.pdf', '%PDF-1.4 test');

    Document::query()->create([
        'title' => 'Field guide', 'slug' => 'field-guide', 'status' => true,
        'file_path' => 'documents/guide.pdf',
    ]);

    $response = $this->get('/our-documents/field-guide/download');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

it('returns 404 for unpublished or missing document files', function () {
    Storage::fake('local');

    Document::query()->create([
        'title' => 'Hidden file', 'slug' => 'hidden-file', 'status' => false,
        'file_path' => 'documents/hidden.pdf',
    ]);

    Document::query()->create([
        'title' => 'Missing file', 'slug' => 'missing-file', 'status' => true,
        'file_path' => 'documents/missing.pdf',
    ]);

    $this->get('/our-documents/hidden-file/download')->assertNotFound();
    $this->get('/our-documents/missing-file/download')->assertNotFound();
    $this->get('/our-documents/does-not-exist/download')->assertNotFound();
});

it('replaces a document file and releases the previous one', function () {
    Storage::fake('local');
    $admin = documentEditor();

    $this->actingAs($admin)->post(route('admin.documents.store'), [
        'title' => 'Replaceable file', 'status' => true,
        'file_path' => fakePdf('first.pdf'),
    ]);

    $document = Document::query()->where('slug', 'replaceable-file')->firstOrFail();
    $oldPath = $document->file_path;

    $this->actingAs($admin)->put(route('admin.documents.update', $document->id), [
        'title' => 'Replaceable file', 'status' => true,
        'file_path' => fakePdf('second.pdf'),
    ])->assertRedirect(route('admin.documents.index'));

    $document->refresh();

    expect($document->file_path)->not->toBe($oldPath);
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($document->file_path);
});

it('deletes a document and its file, but not a file another record uses', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/shared.pdf', '%PDF-1.4 shared');

    $first = Document::query()->create([
        'title' => 'First shared', 'slug' => 'first-shared', 'status' => true,
        'file_path' => 'documents/shared.pdf',
    ]);
    $second = Document::query()->create([
        'title' => 'Second shared', 'slug' => 'second-shared', 'status' => true,
        'file_path' => 'documents/shared.pdf',
    ]);

    $this->actingAs(documentEditor())
        ->delete(route('admin.documents.destroy', $first->id))
        ->assertRedirect();

    expect(Document::query()->whereKey($first->id)->exists())->toBeFalse();
    Storage::disk('local')->assertExists('documents/shared.pdf');

    $this->actingAs(documentEditor())
        ->delete(route('admin.documents.destroy', $second->id))
        ->assertRedirect();

    Storage::disk('local')->assertMissing('documents/shared.pdf');
});

it('generates unique slugs for documents with the same title', function () {
    $this->actingAs(documentEditor())->post(route('admin.documents.store'), [
        'title' => 'Quarterly bulletin', 'status' => true,
        'url' => 'https://drive.google.com/file/d/one/view',
    ]);

    $this->actingAs(documentEditor())->post(route('admin.documents.store'), [
        'title' => 'Quarterly bulletin', 'status' => true,
        'url' => 'https://drive.google.com/file/d/two/view',
    ]);

    expect(Document::query()->where('slug', 'quarterly-bulletin')->exists())->toBeTrue()
        ->and(Document::query()->where('slug', 'quarterly-bulletin-2')->exists())->toBeTrue();
});

it('links the public document library from the site navigation', function () {
    $navigation = file_get_contents(base_path('resources/js/config/site.ts'));

    expect($navigation)->toContain("href: '/our-documents'");

    $this->get('/our-documents')->assertOk();
});

it('forbids non-administrators from managing documents', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $document = Document::query()->create([
        'title' => 'Protected document', 'slug' => 'protected-document', 'status' => true,
        'url' => 'https://drive.google.com/file/d/protected/view',
    ]);

    $this->actingAs($user)->get('/admin/documents')->assertForbidden();
    $this->actingAs($user)->post(route('admin.documents.store'), ['title' => 'Nope'])->assertForbidden();
    $this->actingAs($user)->put(route('admin.documents.update', $document->id), ['title' => 'Nope'])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.documents.destroy', $document->id))->assertForbidden();

    expect(Document::query()->count())->toBe(1);
});
