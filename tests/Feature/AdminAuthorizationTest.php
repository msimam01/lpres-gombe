<?php

use App\Models\Project;
use App\Models\User;

it('forbids ordinary authenticated users from the administration area', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $project = Project::query()->create([
        'title' => 'Protected project',
        'slug' => 'protected-project',
        'status' => 'published',
    ]);

    $this->actingAs($user)->get('/dashboard')->assertForbidden();
    $this->actingAs($user)->get('/admin/projects')->assertForbidden();
    $this->actingAs($user)->get('/admin/projects/create')->assertForbidden();
    $this->actingAs($user)->post(route('admin.projects.store'), ['title' => 'Unauthorised'])->assertForbidden();
    $this->actingAs($user)
        ->put(route('admin.projects.update', $project->id), ['title' => 'Changed'])
        ->assertForbidden();
    $this->actingAs($user)->post(route('admin.projects.toggle', $project->id))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.projects.destroy', $project->id))->assertForbidden();

    expect(Project::query()->count())->toBe(1)
        ->and($project->fresh()->title)->toBe('Protected project')
        ->and($project->fresh()->status)->toBe('published');
});

it('allows designated administrators to manage the administration area', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/dashboard')->assertOk();
    $this->actingAs($admin)->get('/admin/projects')->assertOk();
    $this->actingAs($admin)->get('/admin/projects/create')->assertOk();
});

it('keeps public registration open without granting administration', function () {
    $this->post('/register', [
        'name' => 'Public Reader',
        'email' => 'reader@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('home'));

    $user = User::query()->where('email', 'reader@example.com')->firstOrFail();

    expect($user->is_admin)->toBeFalse();
    $this->actingAs($user)->get('/admin/projects')->assertForbidden();
});

it('sends administrators to the dashboard on login', function () {
    $admin = User::factory()->admin()->create();

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
});
