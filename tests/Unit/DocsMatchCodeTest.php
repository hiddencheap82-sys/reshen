<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * اسناد با کد می‌خوانند؟
 *
 * قاعدهٔ خودِ پروژه این است: «این سند ساختار *واقعی* کد را توصیف
 * می‌کند. اگر چیزی اینجا نوشته شده و در کد نیست، سند غلط است — نه
 * کد.» ولی هیچ‌چیز آن را اجرا نمی‌کرد.
 *
 * و نمی‌خواند: فهرست مهاجرت‌ها در سند مدل داده، برنامهٔ *پیش از
 * نوشتن کد* بود. می‌گفت ‎0020 create_waitlist_entries_table‎ در حالی
 * که ‎0020‎ رمز عبور است و waitlist در ‎0004‎ ساخته شده. کسی که سند را
 * می‌خواند تا بفهمد کجا چه چیزی است، به جای اشتباه می‌رفت.
 */
final class DocsMatchCodeTest extends TestCase
{
    private const DATA_MODEL = '/docs/10-architecture/03-data-model.md';

    /** @return list<string> نام فایل مهاجرت، بدون پسوند */
    private static function migrationFiles(): array
    {
        $out = [];
        foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $path) {
            $out[] = basename($path, '.sql');
        }
        sort($out);

        return $out;
    }

    public function testEveryMigrationAppearsInTheDataModelDoc(): void
    {
        $doc = (string) file_get_contents(BASE_PATH . self::DATA_MODEL);
        $missing = [];

        foreach (self::migrationFiles() as $file) {
            // «0004_appointments» در سند «0004 appointments» نوشته می‌شود
            [$number, $name] = explode('_', $file, 2);

            if (!str_contains($doc, $number . ' ' . $name)) {
                $missing[] = $file;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "این مهاجرت‌ها در سند مدل داده نیستند.\n"
            . 'کسی که سند را می‌خواند تا بفهمد جدولی کجا ساخته شده، پیدایشان نمی‌کند.'
        );
    }

    /** و برعکس: سند از مهاجرتی نام نبرد که وجود ندارد. */
    public function testTheDocDoesNotInventMigrations(): void
    {
        $doc = (string) file_get_contents(BASE_PATH . self::DATA_MODEL);
        $real = self::migrationFiles();

        // فقط بلوک کدِ فهرست مهاجرت‌ها را می‌خوانیم
        preg_match('~## ۸\..*?```\n(.*?)```~s', $doc, $m);
        $this->assertNotEmpty($m, 'بلوک فهرست مهاجرت‌ها در سند پیدا نشد.');

        preg_match_all('~^(\d{4}) ([a-z_]+)~m', $m[1], $listed, PREG_SET_ORDER);
        $this->assertNotEmpty($listed, 'هیچ مهاجرتی در فهرست سند نبود.');

        $invented = [];
        foreach ($listed as [, $number, $name]) {
            if (!in_array($number . '_' . $name, $real, true)) {
                $invented[] = $number . '_' . $name;
            }
        }

        $this->assertSame(
            [],
            $invented,
            'سند از مهاجرت‌هایی نام برده که در database/migrations/ وجود ندارند.'
        );
    }

    /**
     * جدول‌هایی که سند «هنوز کد ندارند» می‌خواند، واقعاً کد ندارند؟
     *
     * اگر روزی یکی‌شان ساخته شود و سند به‌روز نشود، این تست می‌گوید —
     * و آن‌وقت یک فیچر تمام‌شده در سند «هنوز نساخته‌ایم» می‌ماند.
     */
    public function testTablesTheDocCallsUnusedAreReallyUnused(): void
    {
        $unused = ['waitlist_entries', 'products', 'staff_payouts', 'loyalty_cards', 'reviews', 'subscription_plans'];
        $stillUnused = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . '/app'));
        $code = '';
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Setup/')) {
                continue;
            }
            /*
             * کامنت‌ها کنار گذاشته می‌شوند.
             *
             * ‎PlanRepository‎ در توضیحش از ‎subscription_plans‎ نام
             * می‌برد تا بگوید *آن یکی نیست* — و همین باعث می‌شد تست
             * فکر کند جدول استفاده می‌شود.
             */
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }
        }

        foreach ($unused as $table) {
            if (preg_match('~\b' . preg_quote($table, '~') . '\b~', $code) === 1) {
                $stillUnused[] = $table;
            }
        }

        $this->assertSame(
            [],
            $stillUnused,
            'این جدول‌ها حالا در کد استفاده می‌شوند، ولی سند هنوز «هنوز کد ندارند» می‌خواندشان.'
        );
    }

    /** هر جدولی که سند می‌گوید ساخته شده، واقعاً در مهاجرت‌ها هست. */
    public function testTablesTheDocLocatesAreWhereItSays(): void
    {
        $doc = (string) file_get_contents(BASE_PATH . self::DATA_MODEL);

        // ردیف‌های جدولِ «کجا ساخته شده»: | `table` | `0004` | … |
        preg_match_all('~\|\s*`([a-z_]+)`\s*\|\s*`(\d{4})`\s*\|~', $doc, $m, PREG_SET_ORDER);
        $this->assertNotEmpty($m, 'جدولِ «کجا ساخته شده» پیدا نشد.');

        $wrong = [];
        foreach ($m as [, $table, $number]) {
            $matches = glob(BASE_PATH . '/database/migrations/' . $number . '_*.sql') ?: [];
            $sql = $matches === [] ? '' : (string) file_get_contents($matches[0]);

            if (preg_match('~CREATE TABLE[^;]*?\b' . preg_quote($table, '~') . '\b~i', $sql) !== 1) {
                $wrong[] = "{$table} → {$number}";
            }
        }

        $this->assertSame([], $wrong, 'سند می‌گوید این جدول‌ها در آن مهاجرت ساخته شده‌اند، ولی نیستند.');
    }
}
