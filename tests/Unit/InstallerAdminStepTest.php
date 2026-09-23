<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * گام «مدیر کل» در نصاب.
 *
 * این گام یک در است: کسی که بازش کند، حسابی با دسترسی به *همهٔ*
 * سالن‌ها، همهٔ مشتری‌ها و همهٔ صورتحساب‌ها می‌سازد. پس دو چیز باید
 * همیشه درست بماند و هیچ‌کدام در آزمایش دستی خودشان را نشان نمی‌دهند:
 *
 * ۱. تا وقتی مدیر کل ساخته نشده، فایل قفل نوشته نمی‌شود. اگر برعکس
 *    شود، کاربری که مرورگرش را وسط کار ببندد با سامانه‌ای می‌ماند که
 *    نه کسی می‌تواند واردش شود و نه صفحهٔ نصب باز می‌شود.
 *
 * ۲. اگر مدیر کلی از قبل هست، این گام باز نمی‌شود — حتی با POST
 *    مستقیم و حتی اگر فایل قفل دستی پاک شده باشد. وگرنه هر کسی که به
 *    install.php برسد، می‌تواند کل سامانه را بردارد.
 *
 * تست روی *متن* فایل است نه اجرای HTTP، چون نصاب عمداً به bootstrap
 * برنامه وابسته نیست و در محیط تست بالا نمی‌آید. چیزی که اینجا قفل
 * می‌شود ترتیب و وجودِ همان دو محافظ است.
 */
final class InstallerAdminStepTest extends TestCase
{
    private static function source(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Setup/installer.php');
    }

    /** قفل فقط بعد از ساخت مدیر نوشته می‌شود. */
    public function testTheLockFileIsWrittenOnlyAfterTheAdminIsCreated(): void
    {
        $src = self::source();

        $writes = preg_match_all('/file_put_contents\(\s*LOCK_FILE/', $src);
        $this->assertSame(1, $writes, 'فایل قفل باید دقیقاً یک جا نوشته شود.');

        $lockAt = strpos($src, 'file_put_contents(LOCK_FILE');
        $createAt = strpos($src, 'create_first_admin(');

        $this->assertIsInt($createAt, 'گام ساخت مدیر کل پیدا نشد.');
        $this->assertIsInt($lockAt);
        $this->assertLessThan(
            $lockAt,
            $createAt,
            'قفل پیش از ساخت مدیر کل نوشته می‌شود؛ نصبِ نیمه‌کاره غیرقابل بازیابی می‌شود.'
        );
    }

    /** مهاجرت‌ها دیگر مستقیم به «پایان» نمی‌روند. */
    public function testMigrationsHandOffToTheAdminStep(): void
    {
        $this->assertStringContainsString("header('Location: ?step=admin')", self::source());
    }

    /** هر دو مسیرِ رسیدن به گام مدیر، اول وجود مدیر را می‌سنجند. */
    public function testTheAdminStepIsGuardedByAnExistenceCheck(): void
    {
        $src = self::source();

        // یک بار پیش از نمایش فرم، یک بار پیش از ساختن.
        $this->assertGreaterThanOrEqual(
            2,
            preg_match_all('/platform_admin_exists\(\)/', $src),
            'بررسی وجودِ مدیر کل باید هم فرم را ببندد و هم ساخت را.'
        );

        $formGuard = strpos($src, 'render_admin_form(');
        $existsBeforeForm = strrpos(substr($src, 0, (int) $formGuard), 'platform_admin_exists()');

        $this->assertIsInt($existsBeforeForm, 'فرم مدیر کل بدون بررسی وجودِ مدیر رندر می‌شود.');
    }

    /** ساختِ مدیر خودش هم مستقل بررسی می‌کند، نه فقط صفحه. */
    public function testTheCreateFunctionRefusesWhenAnAdminAlreadyExists(): void
    {
        $src = self::source();
        $start = strpos($src, 'function create_first_admin(');
        $this->assertIsInt($start);

        $body = substr($src, $start, 3000);

        $this->assertStringContainsString(
            'platform_admin_exists()',
            $body,
            'create_first_admin باید خودش هم بررسی کند — POST مستقیم از صفحه رد می‌شود.'
        );
    }

    /** رمز مدیر با hash ذخیره می‌شود، نه متن ساده. */
    public function testTheAdminPasswordGoesThroughTheHashingService(): void
    {
        $src = self::source();

        $this->assertStringContainsString('PasswordService::set(', $src);
        $this->assertStringNotContainsString("'password' => \$password", $src);
    }

    /** رمز ضعیف در نصاب هم رد می‌شود، نه فقط در برنامه. */
    public function testTheAdminPasswordIsValidated(): void
    {
        $this->assertStringContainsString('PasswordService::reject(', self::source());
    }

    /** CSRF روی فرم مدیر هم بررسی می‌شود. */
    public function testTheAdminFormChecksCsrf(): void
    {
        $src = self::source();
        $post = strpos($src, "(\$_POST['_step'] ?? '') === 'admin'");

        $this->assertIsInt($post, 'شاخهٔ POST گام مدیر پیدا نشد.');
        $this->assertStringContainsString(
            'hash_equals',
            substr($src, (int) $post, 400),
            'فرم مدیر کل بدون بررسی CSRF پذیرفته می‌شود.'
        );
    }
}
