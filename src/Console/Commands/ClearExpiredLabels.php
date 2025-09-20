<?php

namespace Widia\Shipping\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ClearExpiredLabels extends Command
{
    protected $signature = 'shipping:clear-labels';
    protected $description = '刪除 storage/app/public/labels 下超過指定天數的檔案 : 過期天數，預設值存在config.shipping';

    public function handle()
    {
        $days = config('shipping.label.lifetime_days', '30');
        $expireDate = Carbon::now()->subDays($days);

        $disk = Storage::disk('public');
        $directory = config('shipping.label.directory', 'labels');
        $files = $disk->allFiles($directory);

        $deletedCount = 0;
        foreach ($files as $file) {
            if (preg_match('/' . $directory . '\/(\d{4})\/(\d{2})\/(\d{2})\//', $file, $matches)) {
                $year = $matches[1];
                $month = $matches[2];
                $day = $matches[3];

                $fileDate = Carbon::createFromDate($year, $month, $day);

                if ($fileDate->lt($expireDate)) {
                    $disk->delete($file);
                    $deletedCount++;

                    // 檢查並刪除空目錄
                    $dir = dirname($file);
                    while ($dir && $dir !== $directory && empty($disk->allFiles($dir))) {
                        $disk->deleteDirectory($dir);
                        $dir = dirname($dir);
                    }
                }
            }
        }

        \Log::info('已清理過期標籤檔案', [
            'command' => 'shipping:clear-labels',
            'deleted_count' => $deletedCount,
            'expire_days' => $days,
        ]);
    }
}