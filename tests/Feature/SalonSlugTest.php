<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Http\Controllers\SalonSettingsController;
use PHPUnit\Framework\TestCase;

/**
 * ویرایش نشانی عمومی سالن.
 *
 * نشانی عمومی تنها چیزی است که مشتری می‌بیند و روی QR چاپ می‌شود. دو
 * خطا اینجا گران تمام می‌شوند: برخورد دو سالن روی یک نشانی (یکی از
 * آن‌ها لینکش را از دست می‌دهد)، و عوض شدن ناخواستهٔ نشانی هنگام ذخیرهٔ
 * فرم (که QRهای چاپ‌شده را می‌کشد). هر دو اینجا قفل شده‌اند.
 */
final class SalonSlugTest extends TestCase
{
    private int $salonId;
    private int $otherSalonId;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['appointments', 'salon_user', 'staff', 'users', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonId = (int) DB::insert('salons', [
            'slug' => 'shahab',
            'name' => 'آرایشگاه شهاب',
            'is_active' => 1,
        ]);
        $this->otherSalonId = (int) DB::insert('salons', [
            'slug' => 'kurush',
            'name' => 'پیرایش کوروش',
            'is_active' => 1,
        ]);

        $userId = (int) DB::insert('users', [
            'phone' => '+989120000001',
            'name' => 'مالک',
        ]);
        DB::insert('salon_user', [
            'salon_id' => $this->salonId,
            'user_id' => $userId,
            'role' => 'owner',
        ]);

        $_SESSION = [];
        Auth::login($userId);
        Auth::setSalon($this->salonId);
    }

    protected function tearDown(): void
    {
        $_POST = $_GET = [];
        $_SESSION = [];
        Auth::logout();
    }

    private function saveProfile(array $input): void
    {
        $_POST = array_merge(['name' => 'آرایشگاه شهاب'], $input);
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/panel/settings/profile';

        (new SalonSettingsController())->updateProfile(new Request());
    }

    private function currentSlug(): string
    {
        $row = DB::selectOne('SELECT slug FROM salons WHERE id = ?', [$this->salonId]);

        return (string) $row['slug'];
    }

    public function test_a_new_slug_is_saved(): void
    {
        $this->saveProfile(['slug' => 'shahab-barber']);

        self::assertSame('shahab-barber', $this->currentSlug());
    }

    public function test_an_empty_slug_leaves_the_link_alone(): void
    {
        // مهم‌ترین حالت: صاحب سالن فقط آدرس یا تم را عوض کرده. اگر
        // خالی بودن یعنی «پاک کن»، لینک عمومی و همهٔ QRها می‌مردند.
        $this->saveProfile(['slug' => '']);

        self::assertSame('shahab', $this->currentSlug());
    }

    public function test_a_persian_slug_is_transliterated_before_saving(): void
    {
        $this->saveProfile(['slug' => 'آرایشگاه شهاب']);

        self::assertSame('araishgah-shhab', $this->currentSlug());
    }

    public function test_a_slug_taken_by_another_salon_is_refused(): void
    {
        $this->saveProfile(['slug' => 'kurush']);

        self::assertSame('shahab', $this->currentSlug(), 'نشانی نباید عوض می‌شد.');
        self::assertSame(
            'kurush',
            (string) DB::selectOne('SELECT slug FROM salons WHERE id = ?', [$this->otherSalonId])['slug'],
            'سالن دیگر نباید دست‌خورده باشد.'
        );
    }

    public function test_keeping_your_own_slug_is_not_a_collision(): void
    {
        $this->saveProfile(['slug' => 'shahab']);

        self::assertSame('shahab', $this->currentSlug());
    }

    public function test_a_slug_with_no_usable_characters_is_refused(): void
    {
        $this->saveProfile(['slug' => '😀😀']);

        self::assertSame('shahab', $this->currentSlug());
    }

    public function test_other_profile_fields_still_save_when_the_slug_is_refused(): void
    {
        // خطای نشانی نباید بقیهٔ فرم را دور بیندازد — وگرنه صاحب سالن
        // آدرس را عوض می‌کند، پیام خطای نامربوط می‌بیند، و متوجه
        // نمی‌شود که آدرسش هم ذخیره نشده.
        $this->saveProfile(['slug' => 'kurush', 'city' => 'شیراز']);

        $row = DB::selectOne('SELECT slug, city FROM salons WHERE id = ?', [$this->salonId]);
        self::assertSame('shahab', (string) $row['slug']);
        self::assertSame('شیراز', (string) $row['city']);
    }
}
