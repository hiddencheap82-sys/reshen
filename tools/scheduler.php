<?php

declare(strict_types=1);

/**
 * زمان‌بند را دستی اجرا می‌کند — برای وقتی که می‌خواهید بفهمید چرا
 * کاری انجام نشده.
 *
 * **این جایگزین کرون نیست.** کارهای دوره‌ای خودشان با هر درخواست وب
 * اجرا می‌شوند و نیازی به تنظیم چیزی نیست. این فایل فقط ابزار
 * تشخیص است:
 *
 *   php tools/scheduler.php            کارهای سررسیدشده را اجرا کن
 *   php tools/scheduler.php --force    همه را اجرا کن، فاصله را نادیده بگیر
 *   php tools/scheduler.php --status   فقط وضعیت را نشان بده
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Scheduler;

$args = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$statusOnly = in_array('--status', $args, true);

if (!$statusOnly) {
    $report = Scheduler::runDue($force);

    if ($report === []) {
        echo "هیچ کاری سررسید نشده بود. با --force اجبارش کنید.\n\n";
    } else {
        echo "اجرا شد:\n";
        foreach ($report as $task => $result) {
            printf("  %-26s %s\n", $task, $result);
        }
        echo "\n";
    }
}

echo "وضعیت کارها:\n";
$rows = Scheduler::status();

if ($rows === []) {
    echo "  هنوز هیچ کاری اجرا نشده.\n";
    exit(0);
}

foreach ($rows as $row) {
    printf(
        "  %-26s آخرین: %-20s %s\n",
        $row['name'],
        $row['last_run_at'] ?? 'هرگز',
        $row['last_error'] !== null ? '✗ ' . $row['last_error'] : ($row['last_result'] ?? '')
    );
}
