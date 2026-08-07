<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateMediaToR2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:migrate-to-r2 {--dry : ドライラン（アップロードしない、対象件数のみ表示）}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'publicディスクの画像ファイル（diary_images/profile_icons）をR2ディスクへ移行する';

    private const TARGET_DIRS = ['diary_images', 'profile_icons'];

    public function handle(): int
    {
        $dry = (bool)$this->option('dry');

        $source = Storage::disk('public');
        $dest = Storage::disk('r2');

        $files = collect(self::TARGET_DIRS)
            ->flatMap(fn($dir) => $source->allFiles($dir))
            // .DS_Store等のドットファイルは対象外
            ->reject(fn($path) => str_starts_with(basename($path), '.'))
            ->values();

        $total = $files->count();
        if ($total === 0) {
            $this->info('移行対象ファイルは0件でした。');
            return self::SUCCESS;
        }

        $this->info("移行対象: {$total} 件");

        if ($dry) {
            $this->line('---先頭5件---');
            $this->line(print_r($files->take(5)->all(), true));
            $this->warn('ドライランのためアップロードは行いません。');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $skipped = 0;
        $failed = [];

        foreach ($files as $path) {
            // 再実行時、既にR2に存在するファイルはスキップ（冪等性の担保）
            if ($dest->exists($path)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $stream = $source->readStream($path);
            if ($stream === null) {
                $failed[] = $path;
                $bar->advance();
                continue;
            }

            try {
                $dest->writeStream($path, $stream);
            } catch (\Throwable $e) {
                $failed[] = $path;
                $this->newLine();
                $this->error("失敗: {$path} - {$e->getMessage()}");
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $uploaded = $total - $skipped - count($failed);
        $this->info("完了: アップロード {$uploaded} 件 / スキップ（既存） {$skipped} 件 / 失敗 " . count($failed) . ' 件');

        if (!empty($failed)) {
            $this->error('失敗したファイル: ' . implode(', ', $failed));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
