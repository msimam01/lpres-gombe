<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A value chain cluster holds a structured table of beneficiary records
     * (LGA / Ward / beneficiary count) instead of a free-text description
     * table. The displayed S/No is the record's position within the cluster,
     * determined by `sort_index` — never typed by staff.
     */
    public function up(): void
    {
        Schema::create('cluster_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('value_chain_cluster_id')
                ->constrained('value_chain_clusters')
                ->cascadeOnDelete();
            $table->string('lga');
            $table->string('ward');
            $table->unsignedInteger('beneficiaries');
            $table->unsignedInteger('sort_index')->default(0)->index();
            $table->timestamps();

            $table->index(['value_chain_cluster_id', 'sort_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_beneficiaries');
    }
};
