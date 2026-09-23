<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * پوسته‌ای نازک روی PDO.
 *
 * ‏SQL را خودِ مخزن‌های دامنه می‌نویسند؛ اینجا فقط اتصال، اجرای امنِ
 * کوئری آماده، و تراکنش‌ها یک‌جا جمع شده‌اند. عمداً ORM نیست: روی هاست
 * اشتراکی، هر لایهٔ اضافه یعنی کندی و یک چیز بیشتر که می‌تواند خراب شود.
 */
final class DB
{
    private static ?PDO $pdo = null;

    private static bool $profiling = false;

    /** @var array<int,array{sql:string,ms:float}> */
    private static array $queryLog = [];

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $host = Config::get('database.host');
            $port = Config::get('database.port');
            $name = Config::get('database.database');
            $user = Config::get('database.username');
            $pass = Config::get('database.password');
            $charset = Config::get('database.charset', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            self::syncTimeZone(self::$pdo);
        }

        return self::$pdo;
    }

    /**
     * ساعت MySQL را با ساعت PHP یکی می‌کند.
     *
     * بدون این، برنامه دو ساعتِ متفاوت دارد و هیچ‌کدام خطا نمی‌دهند.
     * PHP روی ‎Asia/Tehran‎ است (‎app/bootstrap.php‎)، ولی MySQL روی هاست
     * معمولاً UTC است. یعنی ‎NOW()‎ سه‌ساعت‌ونیم با ‎date()‎ فرق می‌کند، و
     * هر کوئری‌ای که زمانِ نوشته‌شده با PHP را با ساعت MySQL می‌سنجد،
     * بی‌صدا جواب غلط می‌دهد:
     *
     *   • محدودیت تلاش ورود هیچ‌وقت فعال نمی‌شد — پنجرهٔ ۱۵ دقیقه‌ای
     *     هیچ ردیفی را نمی‌دید، پس رمز بی‌نهایت بار قابل حدس زدن بود.
     *   • حفاظ رزرو (`BookingGuard`) و انقضای لینک ورود هم همین‌طور.
     *   • «نوبتی که ساعتش گذشته» در داشبورد کم‌شمار می‌شد.
     *
     * چرا افست عددی و نه نام منطقه: ‎SET time_zone = 'Asia/Tehran'‎ به
     * جدول‌های منطقهٔ زمانی MySQL نیاز دارد که روی هاست اشتراکی معمولاً
     * پر نشده‌اند و دستور با خطا برمی‌گردد. افستِ ‎+03:30‎ همیشه کار
     * می‌کند و از خودِ PHP خوانده می‌شود، پس اگر روزی منطقه عوض شد،
     * همراهش عوض می‌شود.
     *
     * اگر هاست اجازهٔ ‎SET time_zone‎ ندهد، بی‌سروصدا رد می‌شویم: یک
     * ساعتِ ناهماهنگ بهتر از برنامه‌ای است که اصلاً بالا نمی‌آید.
     */
    private static function syncTimeZone(PDO $pdo): void
    {
        $offset = (new \DateTimeImmutable('now'))->format('P'); // مثل +03:30

        try {
            $pdo->prepare('SET time_zone = ?')->execute([$offset]);
        } catch (\Throwable $e) {
            // هاست اجازه نداد. کوئری‌های وابسته به NOW() ممکن است
            // جابه‌جا باشند؛ صفحهٔ doctor این را گزارش می‌دهد.
        }
    }

    public static function statement(string $sql, array $bindings = []): PDOStatement
    {
        if (!self::$profiling) {
            $stmt = self::connection()->prepare($sql);
            $stmt->execute($bindings);

            return $stmt;
        }

        $started = microtime(true);
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($bindings);
        self::$queryLog[] = [
            'sql' => preg_replace('/\s+/', ' ', trim($sql)),
            'ms' => (microtime(true) - $started) * 1000,
        ];

        return $stmt;
    }

    /**
     * ثبت کوئری‌ها برای پیدا کردن کوئریِ تکراری (N+1).
     *
     * پیش‌فرض خاموش است و در مسیر داغ حتی یک شرط بیشتر هزینه ندارد.
     * فقط با ابزار سنجش روشن می‌شود، نه در تولید.
     */
    public static function startProfiling(): void
    {
        self::$profiling = true;
        self::$queryLog = [];
    }

    /** @return array<int,array{sql:string,ms:float}> کوئری‌های اجراشده و زمانشان */
    public static function queryLog(): array
    {
        return self::$queryLog;
    }

    public static function stopProfiling(): void
    {
        self::$profiling = false;
    }

    public static function select(string $sql, array $bindings = []): array
    {
        return self::statement($sql, $bindings)->fetchAll();
    }

    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = self::statement($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    public static function insert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        self::statement($sql, self::bindKeys($data));

        return self::connection()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereBindings = []): int
    {
        $set = implode(', ', array_map(static fn (string $c) => "$c = :set_$c", array_keys($data)));
        $setBindings = [];
        foreach ($data as $key => $value) {
            $setBindings["set_$key"] = $value;
        }

        $sql = "UPDATE $table SET $set WHERE $where";
        $stmt = self::statement($sql, array_merge($setBindings, $whereBindings));

        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $bindings = []): int
    {
        $stmt = self::statement("DELETE FROM $table WHERE $where", $bindings);

        return $stmt->rowCount();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    private static function bindKeys(array $data): array
    {
        $bindings = [];
        foreach ($data as $key => $value) {
            $bindings[$key] = $value;
        }

        return $bindings;
    }
}
