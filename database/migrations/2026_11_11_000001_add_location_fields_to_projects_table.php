<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive location data for projects. Every column is nullable (or has a
 * non-exposing default), so the 58 imported projects keep their current
 * content, ids, slugs, statuses and media paths untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('lga', 64)->nullable()->after('longitude');
            $table->string('ward', 96)->nullable()->after('lga');
            // Never expose exact locations until an administrator opts in.
            $table->string('location_visibility', 16)->default('hidden')->after('ward');

            $table->index('lga');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['lga']);
            $table->dropColumn(['location_visibility', 'ward', 'lga', 'longitude', 'latitude']);
        });
    }
};
