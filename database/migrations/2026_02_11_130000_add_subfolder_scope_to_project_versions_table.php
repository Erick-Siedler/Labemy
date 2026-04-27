<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->syncLegacySubfolderColumns();

        if (Schema::hasTable('project_versions') && !Schema::hasColumn('project_versions', 'subfolder_id')) {
            Schema::table('project_versions', function (Blueprint $table) {
                $table->foreignId('subfolder_id')
                    ->nullable()
                    ->after('project_id')
                    ->constrained('subfolders')
                    ->nullOnDelete();
            });
        }

        if (
            !Schema::hasTable('projects')
            || !Schema::hasTable('subfolders')
            || !Schema::hasTable('project_versions')
            || !Schema::hasColumn('project_versions', 'subfolder_id')
        ) {
            return;
        }

        $projects = DB::table('projects')
            ->select(['id', 'tenant_id', 'lab_id', 'group_id', 'title'])
            ->orderBy('id')
            ->get();

        foreach ($projects as $project) {
            $defaultSubfolder = DB::table('subfolders')
                ->where('project_id', $project->id)
                ->orderBy('order_index')
                ->orderBy('id')
                ->first();

            if (!$defaultSubfolder) {
                $baseSlug = Str::slug((string) ($project->title ?: 'principal')) ?: 'principal';
                $slug = $baseSlug;
                $suffix = 1;

                while (
                    DB::table('subfolders')
                        ->where('project_id', $project->id)
                        ->where('slug', $slug)
                        ->exists()
                ) {
                    $slug = $baseSlug . '-' . $suffix;
                    $suffix++;
                }

                $subfolderId = DB::table('subfolders')->insertGetId([
                    'tenant_id' => $project->tenant_id,
                    'lab_id' => $project->lab_id,
                    'group_id' => $project->group_id,
                    'project_id' => $project->id,
                    'name' => 'Principal',
                    'slug' => $slug,
                    'description' => null,
                    'order_index' => 1,
                    'current_version' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $subfolderId = (int) $defaultSubfolder->id;
            }

            DB::table('project_versions')
                ->where('project_id', $project->id)
                ->whereNull('subfolder_id')
                ->update([
                    'subfolder_id' => $subfolderId,
                ]);
        }

        $subfolders = DB::table('subfolders')
            ->select(['id'])
            ->orderBy('id')
            ->get();

        foreach ($subfolders as $subfolder) {
            $currentVersion = (int) (
                DB::table('project_versions')
                    ->where('subfolder_id', $subfolder->id)
                    ->max('version_number') ?? 0
            );

            DB::table('subfolders')
                ->where('id', $subfolder->id)
                ->update([
                    'current_version' => max(0, $currentVersion),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('project_versions') || !Schema::hasColumn('project_versions', 'subfolder_id')) {
            return;
        }

        try {
            Schema::table('project_versions', function (Blueprint $table) {
                $table->dropForeign(['subfolder_id']);
            });
        } catch (\Throwable $e) {
            // Ignora falha ao remover FK em bancos que não suportam a operação.
        }

        Schema::table('project_versions', function (Blueprint $table) {
            $table->dropColumn('subfolder_id');
        });
    }

    /**
     * Executa a rotina 'syncLegacySubfolderColumns' no fluxo de negocio.
     */
    private function syncLegacySubfolderColumns(): void
    {
        if (!Schema::hasTable('subfolders')) {
            return;
        }

        Schema::table('subfolders', function (Blueprint $table) {
            if (!Schema::hasColumn('subfolders', 'name')) {
                $table->string('name')->after('project_id')->default('Principal');
            }

            if (!Schema::hasColumn('subfolders', 'slug')) {
                $table->string('slug')->after('name')->default('principal');
            }

            if (!Schema::hasColumn('subfolders', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }

            if (!Schema::hasColumn('subfolders', 'order_index')) {
                $table->unsignedInteger('order_index')->default(1)->after('description');
            }

            if (!Schema::hasColumn('subfolders', 'current_version')) {
                $table->unsignedBigInteger('current_version')->default(0)->after('order_index');
            }
        });

        try {
            Schema::table('subfolders', function (Blueprint $table) {
                $table->unique(['project_id', 'slug']);
            });
        } catch (\Throwable $e) {
            // Índice já pode existir em bases que já aplicaram o schema novo.
        }
    }
};

