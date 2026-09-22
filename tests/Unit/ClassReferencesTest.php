<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * هر کلاسی که در کد نام برده شده، واقعاً وجود دارد؟
 *
 * چرا این تست هست: هنگام جابه‌جا کردن AppointmentRepository به نام‌فضای
 * دیگر، یک ارجاع از قلم افتاد — چون QueueService هم‌نام‌فضا بود و
 * بدون `use` صدایش می‌زد، پس جست‌وجوی نام کامل پیدایش نکرد. ۵۱۲ تست
 * سبز ماندند و خطا فقط وقتی دیده شد که صفحهٔ عمومی سالن باز شد:
 * «Class App\Domain\Queue\AppointmentRepository not found».
 *
 * PHP این را تا لحظهٔ اجرا نمی‌فهمد. یک `new X()` وسط یک متد که فقط در
 * یک مسیر خاص صدا زده می‌شود، می‌تواند ماه‌ها پنهان بماند و بعد روی
 * هاست مشتری بترکد.
 *
 * پس اینجا خودمان همان کاری را می‌کنیم که PHP موقع اجرا می‌کند: هر نام
 * کلاسی را در فایل پیدا می‌کنیم، با `use`ها و نام‌فضای فایل به نام کامل
 * تبدیلش می‌کنیم، و می‌پرسیم وجود دارد یا نه.
 */
final class ClassReferencesTest extends TestCase
{
    /** نام‌هایی که کلاس نیستند و در کد به‌شکل کلاس دیده می‌شوند. */
    private const NOT_CLASSES = ['self', 'static', 'parent', 'class'];

    public function test_every_referenced_class_exists(): void
    {
        $missing = [];

        foreach ($this->projectFiles() as $path) {
            foreach ($this->referencedClasses($path) as $line => $class) {
                if ($this->exists($class)) {
                    continue;
                }
                $missing[] = sprintf(
                    '%s:%d  →  %s',
                    substr($path, strlen(dirname(__DIR__, 2)) + 1),
                    $line,
                    $class
                );
            }
        }

        self::assertSame([], $missing, "کلاس‌هایی که نام برده شده‌اند ولی وجود ندارند:\n"
            . implode("\n", $missing));
    }

    private function exists(string $class): bool
    {
        return class_exists($class) || interface_exists($class)
            || trait_exists($class) || enum_exists($class);
    }

    /** @return list<string> */
    private function projectFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $out = [];

        foreach (['app', 'routes'] as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->getExtension() === 'php') {
                    $out[] = $file->getPathname();
                }
            }
        }

        sort($out);

        return $out;
    }

    /**
     * نام‌های کلاسی که یک فایل به آن‌ها اشاره می‌کند، به‌صورت کامل.
     *
     * با توکن‌های خود PHP خوانده می‌شود: نام کلاسی که داخل یک رشته یا
     * کامنت فارسی آمده نباید به حساب بیاید.
     *
     * @return array<int,string> شمارهٔ خط => نام کامل
     */
    private function referencedClasses(string $path): array
    {
        $src = (string) file_get_contents($path);
        $tokens = token_get_all($src);

        $namespace = '';
        $aliases = [];
        $found = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readName($tokens, $i);
                continue;
            }

            if ($token[0] === T_USE && $this->isImport($tokens, $i)) {
                $name = $this->readName($tokens, $i);
                if ($name !== '' && !str_starts_with($name, 'function ')) {
                    $short = substr(strrchr('\\' . $name, '\\') ?: '', 1);
                    $aliases[strtolower($short)] = $name;
                }
                continue;
            }

            // `new X`، `X::`، و `instanceof X`
            $isNew = $token[0] === T_NEW || $token[0] === T_INSTANCEOF;
            $isStatic = $token[0] === T_DOUBLE_COLON;

            if ($isNew) {
                $name = $this->readName($tokens, $i);
            } elseif ($isStatic) {
                $name = $this->nameBefore($tokens, $i);
            } else {
                continue;
            }

            if ($name === '' || in_array(strtolower($name), self::NOT_CLASSES, true)) {
                continue;
            }
            if (str_contains($name, '$')) {
                continue; // نام پویا — از روی کد معلوم نیست
            }

            $found[$token[2]] = $this->resolve($name, $namespace, $aliases);
        }

        return $found;
    }

    /** آیا این `use` یک import است یا استفاده از trait / closure؟ */
    private function isImport(array $tokens, int $i): bool
    {
        // import همیشه در سطح فایل است؛ trait داخل کلاس، و closure با «(» می‌آید.
        for ($j = $i - 1; $j >= 0; $j--) {
            $t = $tokens[$j];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($t === ';' || $t === '{' || $t === '}') {
                break;
            }
            if (is_array($t) && $t[0] === T_OPEN_TAG) {
                break;
            }

            return false;
        }

        for ($j = $i + 1; $j < count($tokens); $j++) {
            $t = $tokens[$j];
            if (is_array($t) && $t[0] === T_WHITESPACE) {
                continue;
            }

            return $t !== '(';
        }

        return true;
    }

    private function readName(array $tokens, int $i): string
    {
        $name = '';
        for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
            $t = $tokens[$j];
            if (is_array($t) && $t[0] === T_WHITESPACE) {
                if ($name !== '') {
                    break;
                }
                continue;
            }
            if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name .= $t[1];
                continue;
            }
            break;
        }

        return $name;
    }

    private function nameBefore(array $tokens, int $i): string
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            $t = $tokens[$j];
            if (is_array($t) && $t[0] === T_WHITESPACE) {
                continue;
            }
            if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                return $t[1];
            }

            return '';
        }

        return '';
    }

    private function resolve(string $name, string $namespace, array $aliases): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $head = explode('\\', $name)[0];
        $key = strtolower($head);

        if (isset($aliases[$key])) {
            $rest = substr($name, strlen($head));

            return $aliases[$key] . $rest;
        }

        // بدون import: اول نام‌فضای خود فایل، بعد فضای سراسری.
        $inNamespace = $namespace === '' ? $name : $namespace . '\\' . $name;

        return $this->exists($inNamespace) ? $inNamespace : $name;
    }
}
