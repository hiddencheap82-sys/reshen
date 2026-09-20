<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ImageUpload;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * آپلود تصویر.
 *
 * اینجا جایی است که یک اشتباه، سرور را از دست می‌دهد. پس رفتارهای
 * امنیتی تست دارند، نه فقط مسیر خوشحال.
 */
final class ImageUploadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/reshen-upload-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    /**
     * حذف نباید بتواند از پوشه بیرون برود.
     *
     * نام فایل از دیتابیس می‌آید و *باید* امن باشد، ولی اگر روزی از
     * جای دیگری بیاید، «../../.env» نباید چیزی را پاک کند.
     */
    public function test_delete_cannot_escape_the_directory(): void
    {
        $victim = $this->dir . '/../outside-' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($victim, 'نباید پاک شود');

        ImageUpload::delete($this->dir, '../' . basename($victim));

        self::assertFileExists($victim, 'حذف از پوشه بیرون رفت');
        @unlink($victim);
    }

    public function test_delete_of_missing_file_is_not_an_error(): void
    {
        ImageUpload::delete($this->dir, 'nope.webp');
        ImageUpload::delete($this->dir, null);
        ImageUpload::delete($this->dir, '');

        self::assertTrue(true, 'نبودن فایل نباید خطا بدهد');
    }

    public function test_delete_removes_the_file(): void
    {
        $path = $this->dir . '/logo-abc.webp';
        file_put_contents($path, 'x');

        ImageUpload::delete($this->dir, 'logo-abc.webp');

        self::assertFileDoesNotExist($path);
    }

    /** نبودِ فایل در فرم، خطا نیست — کاربر فقط لوگو عوض نکرده. */
    public function test_no_file_is_not_an_error(): void
    {
        $result = ImageUpload::saveImage(null, $this->dir);
        self::assertFalse($result['ok']);
        self::assertNull($result['error']);

        $result = ImageUpload::saveImage(['error' => UPLOAD_ERR_NO_FILE], $this->dir);
        self::assertFalse($result['ok']);
        self::assertNull($result['error']);
    }

    /** خطاهای آپلود باید پیام فارسیِ قابل فهم بدهند، نه کد عددی. */
    public function test_upload_errors_produce_readable_messages(): void
    {
        foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_CANT_WRITE] as $code) {
            $result = ImageUpload::saveImage(['error' => $code], $this->dir);

            self::assertFalse($result['ok']);
            self::assertNotNull($result['error']);
            self::assertSame(0, preg_match('/[A-Za-z]/', $result['error']), 'پیام باید فارسی باشد');
        }
    }

    /**
     * بزرگ‌تر از حد، کوچک می‌شود و نسبت حفظ می‌ماند.
     *
     * لوگوی ۴۰۰۰ پیکسلی روی موبایل فقط کندی می‌آورد.
     */
    public function test_oversized_image_is_scaled_down_keeping_ratio(): void
    {
        $fit = new ReflectionMethod(ImageUpload::class, 'fitWithin');

        $wide = imagecreatetruecolor(2000, 1000);
        $out = $fit->invoke(null, $wide, 512);

        self::assertSame(512, imagesx($out));
        self::assertSame(256, imagesy($out), 'نسبت باید حفظ شود');

        imagedestroy($wide);
        imagedestroy($out);
    }

    public function test_small_image_is_left_alone(): void
    {
        $fit = new ReflectionMethod(ImageUpload::class, 'fitWithin');

        $small = imagecreatetruecolor(120, 80);
        $out = $fit->invoke(null, $small, 512);

        self::assertSame(120, imagesx($out));
        self::assertSame(80, imagesy($out));

        imagedestroy($small);
        imagedestroy($out);
    }

    /**
     * شفافیت باید بماند.
     *
     * لوگوی PNG شفاف روی سرصفحهٔ تیرهٔ سالن می‌نشیند؛ اگر شفافیتش برود،
     * یک مستطیل سیاه یا سفید وسط سرصفحه می‌ماند.
     */
    public function test_transparency_survives_resizing(): void
    {
        $fit = new ReflectionMethod(ImageUpload::class, 'fitWithin');

        $source = imagecreatetruecolor(1000, 1000);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        imagefill($source, 0, 0, imagecolorallocatealpha($source, 0, 0, 0, 127));

        $out = $fit->invoke(null, $source, 256);

        $corner = imagecolorat($out, 0, 0);
        $alpha = ($corner >> 24) & 0x7F;

        self::assertSame(127, $alpha, 'گوشهٔ شفاف باید شفاف بماند');

        imagedestroy($source);
        imagedestroy($out);
    }

    /** فایلی که تصویر نیست باید رد شود، هرچند پسوندش png باشد. */
    public function test_non_image_is_rejected_regardless_of_extension(): void
    {
        $open = new ReflectionMethod(ImageUpload::class, 'open');
        $fake = $this->dir . '/fake.png';
        file_put_contents($fake, '<?php echo "hi";');

        self::assertFalse(@getimagesize($fake), 'getimagesize باید این را رد کند');
        self::assertNull($open->invoke(null, $fake, IMAGETYPE_PNG));
    }
}
