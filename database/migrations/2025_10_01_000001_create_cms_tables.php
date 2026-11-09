<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('project_components', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('status')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('photograph_path')->nullable();
            $table->string('designation')->nullable();
            $table->string('category')->nullable()->index();
            $table->boolean('is_spc')->default(false)->index();
            $table->text('biography')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->boolean('status')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('featured_image_path')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->date('published_at')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('featured_image_path')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable()->index();
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('value_chain_clusters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('visual_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('media_type')->index(); // image | video
            $table->string('image_path')->nullable();
            $table->string('video_url')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->text('caption')->nullable();
            $table->string('album')->nullable()->index();
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('contact_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->text('message');
            $table->timestamp('handled_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }
};
