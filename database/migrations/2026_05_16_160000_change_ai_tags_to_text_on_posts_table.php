<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('ai_tags_migrated')->nullable();
        });

        foreach (DB::table('posts')->select('id', 'ai_tags')->orderBy('id')->cursor() as $row) {
            DB::table('posts')->where('id', $row->id)->update([
                'ai_tags_migrated' => $this->jsonTagsToCommaString($row->ai_tags),
            ]);
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('ai_tags');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->text('ai_tags')->nullable();
        });

        DB::table('posts')->update(['ai_tags' => DB::raw('ai_tags_migrated')]);

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('ai_tags_migrated');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->json('ai_tags_json')->nullable();
        });

        foreach (DB::table('posts')->select('id', 'ai_tags')->orderBy('id')->cursor() as $row) {
            $tags = trim((string) ($row->ai_tags ?? ''));
            $json = null;
            if ($tags !== '') {
                $arr = array_values(array_filter(array_map('trim', preg_split('/[,;|，、]/u', $tags) ?: [])));
                $json = $arr === [] ? null : json_encode(array_values($arr));
            }
            DB::table('posts')->where('id', $row->id)->update(['ai_tags_json' => $json]);
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('ai_tags');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->json('ai_tags')->nullable();
        });

        DB::table('posts')->update(['ai_tags' => DB::raw('ai_tags_json')]);

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('ai_tags_json');
        });
    }

    private function jsonTagsToCommaString(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $parts = [];
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $parts[] = trim($item);
                    }
                }

                return $parts === [] ? null : implode(', ', $parts);
            }

            return trim($raw) !== '' ? trim($raw) : null;
        }

        return null;
    }
};
