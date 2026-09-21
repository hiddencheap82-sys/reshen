<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Identity\LoginLinkService;
use App\Support\IranMobile;
use PHPUnit\Framework\TestCase;

/**
 * لینک ورود یک‌بارمصرف (ت-۳۶).
 *
 * این یک درِ ورود است. هر رفتاری که آن را از «یک‌بارمصرف و کوتاه‌عمر»
 * دربیاورد، یک آسیب‌پذیری است — پس همه‌شان تست دارند.
 */
final class LoginLinkTest extends TestCase
{
    private LoginLinkService $service;
    private string $phone = '+989121110000';

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('DELETE FROM otp_codes WHERE 1');
        DB::statement('DELETE FROM users WHERE 1');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        DB::insert('users', ['phone' => $this->phone, 'name' => 'صاحب سالن']);
        $this->service = new LoginLinkService();
    }

    public function test_issues_a_token_for_a_known_phone(): void
    {
        $result = $this->service->issue(IranMobile::parse($this->phone));

        self::assertTrue($result['ok']);
        self::assertNotNull($result['token']);
        self::assertSame(48, strlen($result['token']), 'توکن باید ۲۴ بایت هگز باشد');
    }

    public function test_unknown_phone_gets_no_token(): void
    {
        $result = $this->service->issue(IranMobile::parse('+989129998877'));

        self::assertFalse($result['ok']);
        self::assertNull($result['token']);
    }

    /** توکن خام نباید در دیتابیس بماند. */
    public function test_only_the_hash_is_stored(): void
    {
        $token = $this->service->issue(IranMobile::parse($this->phone))['token'];

        $row = DB::selectOne('SELECT code_hash FROM otp_codes WHERE purpose = ?', [LoginLinkService::PURPOSE]);

        self::assertNotSame($token, $row['code_hash']);
        self::assertSame(hash('sha256', $token), $row['code_hash']);
    }

    public function test_consuming_returns_the_user(): void
    {
        $token = $this->service->issue(IranMobile::parse($this->phone))['token'];

        $userId = $this->service->consume($token);

        self::assertNotNull($userId);
        self::assertSame($this->phone, DB::selectOne('SELECT phone FROM users WHERE id = ?', [$userId])['phone']);
    }

    /** مهم‌ترین تضمین: بار دوم نباید کار کند. */
    public function test_a_token_works_exactly_once(): void
    {
        $token = $this->service->issue(IranMobile::parse($this->phone))['token'];

        self::assertNotNull($this->service->consume($token));
        self::assertNull($this->service->consume($token), 'لینک باید یک‌بارمصرف باشد');
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = $this->service->issue(IranMobile::parse($this->phone))['token'];

        DB::statement(
            'UPDATE otp_codes SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE purpose = ?',
            [LoginLinkService::PURPOSE]
        );

        self::assertNull($this->service->consume($token));
    }

    /** ساختن لینک تازه، قبلی را باطل می‌کند — دو کلید در گردش نباشد. */
    public function test_issuing_a_new_token_invalidates_the_previous_one(): void
    {
        $first = $this->service->issue(IranMobile::parse($this->phone))['token'];
        $second = $this->service->issue(IranMobile::parse($this->phone))['token'];

        self::assertNull($this->service->consume($first), 'لینک قبلی باید باطل شده باشد');
        self::assertNotNull($this->service->consume($second));
    }

    public function test_garbage_tokens_are_rejected_without_a_query(): void
    {
        self::assertNull($this->service->consume(''));
        self::assertNull($this->service->consume('نه-هگز'));
        self::assertNull($this->service->consume("' OR 1=1 --"));
        self::assertNull($this->service->consume(str_repeat('a', 48)));
    }

    /** لینکِ ورود نباید با کدِ پیامکی قاطی شود. */
    public function test_an_otp_code_cannot_be_used_as_a_login_link(): void
    {
        $raw = 'abcdef0123456789';
        DB::insert('otp_codes', [
            'phone' => $this->phone,
            'code_hash' => hash('sha256', $raw),
            'purpose' => 'login',
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);

        self::assertNull($this->service->consume($raw), 'کد ورود عادی نباید به‌جای لینک بپذیرد');
    }
}
