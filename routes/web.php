<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NewsletterSubscriberController;
use App\Http\Controllers\ProjectComponentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMapDataController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\ValueChainClusterController;
use App\Http\Controllers\VisualReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Static organisation pages (fixed org copy).
Route::get('/about', fn () => Inertia::render('about'))->name('about');
Route::get('/vision', fn () => Inertia::render('vision'))->name('vision');
Route::get('/mission', fn () => Inertia::render('mission'))->name('mission');
Route::get('/core-values', fn () => Inertia::render('core-values'))->name('core-values');

// Backend-driven listing/detail pages. The SPC profile is a dedicated
// hardcoded editorial page (not a TeamMember CRUD record), always available.
Route::get('/project-team', [TeamMemberController::class, 'index'])->name('project-team');
Route::get('/spc-profile', fn () => Inertia::render('team/spc-profile'))->name('spc.profile');
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{article:slug}', [NewsController::class, 'show'])->name('news.show');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');
// Homepage project components, one stable slug-based page each. Kept on its
// own prefix so it can never shadow /projects/{slug} or /our-projects.
Route::get('/project-components/{component:slug}', [ProjectComponentController::class, 'show'])
    ->name('project-components.show');

Route::get('/our-projects', [ProjectController::class, 'index'])->name('our-projects');
Route::get('/our-projects/map-data', ProjectMapDataController::class)->name('our-projects.map-data');
Route::get('/projects/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');
Route::get('/value-chains', [ValueChainClusterController::class, 'index'])->name('value-chains.index');
Route::get('/value-chains/{cluster:slug}', [ValueChainClusterController::class, 'show'])->name('value-chains.show');
Route::get('/media', [MediaController::class, 'index'])->name('media');
Route::get('/media/galleries/{gallery:slug}', [MediaController::class, 'gallery'])->name('media.galleries.show');
Route::get('/visual-reports', [VisualReportController::class, 'index'])->name('visual-reports');
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/newsletter/subscribe', [NewsletterSubscriberController::class, 'store'])->name('newsletter.subscribe');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::name('admin.')->prefix('admin')->group(function () {
        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

        Route::controller(Admin\NewsAdminController::class)->name('news.')->prefix('news')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{id}/edit', 'edit')->name('edit');
            Route::put('{id}', 'update')->name('update');
            Route::delete('{id}', 'destroy')->name('destroy');
            Route::post('{id}/toggle', 'toggle')->name('toggle');
        });

        // Publishable content modules.
        foreach ([
            Admin\EventAdminController::class => 'events',
            Admin\ProjectAdminController::class => 'projects',
            Admin\ClusterAdminController::class => 'clusters',
            Admin\VisualReportAdminController::class => 'visual-reports',
        ] as $crudController => $segment) {
            Route::controller($crudController)->name($segment.'.')->prefix($segment)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{id}/edit', 'edit')->name('edit');
                Route::put('{id}', 'update')->name('update');
                Route::delete('{id}', 'destroy')->name('destroy');
                Route::post('{id}/toggle', 'toggle')->name('toggle');
            });
        }

        // Media: dedicated Image / Video / Gallery workflows plus a combined
        // "All Media" index (replaces the earlier mixed media CRUD).
        Route::get('media', [Admin\AllMediaController::class, 'index'])->name('media.index');

        foreach ([
            Admin\MediaImageAdminController::class => 'media/images',
            Admin\MediaVideoAdminController::class => 'media/videos',
            Admin\MediaGalleryAdminController::class => 'media/galleries',
        ] as $crudController => $segment) {
            Route::controller($crudController)
                ->name(str_replace('/', '.', $segment).'.')
                ->prefix($segment)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('{id}/edit', 'edit')->name('edit');
                    Route::put('{id}', 'update')->name('update');
                    Route::delete('{id}', 'destroy')->name('destroy');
                    Route::post('{id}/toggle', 'toggle')->name('toggle');
                });
        }

        // Category classification modules: no publication workflow, so no
        // publish/unpublish action exists for them.
        foreach ([
            Admin\ProjectCategoryAdminController::class => 'project-categories',
            Admin\TeamCategoryAdminController::class => 'team-categories',
        ] as $crudController => $segment) {
            Route::controller($crudController)->name($segment.'.')->prefix($segment)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{id}/edit', 'edit')->name('edit');
                Route::put('{id}', 'update')->name('update');
                Route::delete('{id}', 'destroy')->name('destroy');
            });
        }

        Route::controller(Admin\ProjectComponentAdminController::class)->name('project-components.')->prefix('project-components')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{id}/edit', 'edit')->name('edit');
            Route::put('{id}', 'update')->name('update');
            Route::delete('{id}', 'destroy')->name('destroy');
            Route::post('{id}/toggle', 'toggle')->name('toggle');
        });

        Route::controller(Admin\TeamMemberAdminController::class)->name('team.')->prefix('team')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{id}/edit', 'edit')->name('edit');
            Route::put('{id}', 'update')->name('update');
            Route::delete('{id}', 'destroy')->name('destroy');
            Route::post('{id}/toggle', 'toggle')->name('toggle');
        });

        Route::get('enquiries', [Admin\ContactEnquiryAdminController::class, 'index'])->name('enquiries.index');
        Route::post('enquiries/{id}/toggle', [Admin\ContactEnquiryAdminController::class, 'toggle'])->name('enquiries.toggle');
        Route::delete('enquiries/{id}', [Admin\ContactEnquiryAdminController::class, 'destroy'])->name('enquiries.destroy');

        Route::get('subscribers', [Admin\NewsletterSubscriberAdminController::class, 'index'])->name('subscribers.index');
        Route::delete('subscribers/{id}', [Admin\NewsletterSubscriberAdminController::class, 'destroy'])->name('subscribers.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
