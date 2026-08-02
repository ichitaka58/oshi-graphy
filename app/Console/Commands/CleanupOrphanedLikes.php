<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedLikes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'likes:cleanup-orphans {--dry : ドライラン（削除しない、対象件数のみ表示）}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '参照先のDiary/Commentが既に存在しないlikesレコード（孤児データ）を削除する';

    public function handle(): int
    {
        $dry = (bool)$this->option('dry');

        $targets = [
            \App\Models\Diary::class => 'diaries',
            \App\Models\Comment::class => 'comments',
        ];

        $totalToDelete = 0;

        foreach ($targets as $likeableType => $parentTable) {
            $query = DB::table('likes')
                ->where('likeable_type', $likeableType)
                ->whereNotIn('likeable_id', DB::table($parentTable)->select('id'));

            $count = (clone $query)->count();
            $totalToDelete += $count;

            $this->info("[{$likeableType}] 孤児いいね: {$count} 件");

            if ($dry && $count > 0) {
                $sample = (clone $query)->limit(5)->get(['id', 'user_id', 'likeable_id', 'created_at']);
                $this->line('---サンプル(先頭5件)---');
                $this->line(print_r($sample->toArray(), true));
            }
        }

        if ($totalToDelete === 0) {
            $this->info('孤児いいねは0件でした。削除不要です。');
            return self::SUCCESS;
        }

        if ($dry) {
            $this->warn("ドライランのため削除は行いません。合計 {$totalToDelete} 件が削除対象です。");
            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            $deleted = 0;
            foreach ($targets as $likeableType => $parentTable) {
                $deleted += DB::table('likes')
                    ->where('likeable_type', $likeableType)
                    ->whereNotIn('likeable_id', DB::table($parentTable)->select('id'))
                    ->delete();
            }
            DB::commit();
            $this->info("削除完了: {$deleted} 件");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('削除に失敗: '.$e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
