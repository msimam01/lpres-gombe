<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesAssociatedMedia;
use App\Models\News;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    use ResolvesAssociatedMedia;

    public function index(): Response
    {
        return Inertia::render('news/index', [
            'articles' => News::published()
                ->paginate(12)
                ->through(fn (News $article) => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'href' => route('news.show', $article->slug),
                    'excerpt' => $article->excerpt ?: Str::words(strip_tags($article->body), 32, '…'),
                    'image' => $article->featured_image_path ? asset('storage/'.$article->featured_image_path) : null,
                    'published' => $article->published_at?->format('Y-m-d'),
                    'dateDay' => $article->published_at?->format('d'),
                    'dateMonth' => ' '.strtoupper($article->published_at?->format('M')),
                ]),
        ]);
    }

    public function show(News $article): Response
    {
        abort_unless($article->status === 'published' && $article->published_at && $article->published_at->lte(now()), 404);

        return Inertia::render('news/show', [
            'article' => [
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'body' => $article->body,
                'image' => $article->featured_image_path ? asset('storage/'.$article->featured_image_path) : null,
                'published' => $article->published_at?->format('j F Y'),
                // Images that appeared inside the article body, in author order.
                'gallery' => collect($article->galleryPaths())
                    ->map(fn (string $path) => asset('storage/'.$path))
                    ->values()
                    ->all(),
            ],
            'moreNews' => News::published()
                ->where('id', '!=', $article->id)
                ->take(3)
                ->get(['id', 'title', 'slug', 'published_at'])
                ->map(fn (News $item) => [
                    'title' => $item->title,
                    'href' => route('news.show', $item->slug),
                    'published' => $item->published_at?->format('j F Y'),
                ])
                ->all(),

            // Published galleries, videos and image items associated with
            // this article.
            ...$this->associatedMedia('news', $article->id),
        ]);
    }
}
