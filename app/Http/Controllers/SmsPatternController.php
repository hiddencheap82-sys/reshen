<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Messaging\SmsTemplates;

/**
 * راهنمای الگوهای پیامک.
 *
 * صفحهٔ خواندنی است نه فرم: شناسهٔ الگو در `.env` می‌نشیند، نه در
 * دیتابیس. دلیلش این است که الگوها به **حساب اپراتور** بسته‌اند نه به
 * سالن — همهٔ سالن‌های روی این نصب، از یک خط خدماتی می‌فرستند. اگر
 * هر سالن شناسهٔ خودش را داشت، معنی‌اش این بود که هر سالن حساب جدا
 * دارد، که نه درست است نه ارزان.
 *
 * پس اینجا فقط نشان می‌دهد: چه الگوهایی باید ثبت شوند، متن دقیقشان
 * چیست، و کدام‌ها هنوز تنظیم نشده‌اند.
 */
final class SmsPatternController extends Controller
{
    public function index(Request $request): Response
    {
        $driver = (string) Config::get('reshen.sms.driver', 'log');
        $rows = [];

        foreach (SmsTemplates::all() as $code => $template) {
            $rows[$code] = $template + [
                'melipayamak' => (string) Config::get("reshen.sms.patterns.melipayamak.{$code}", ''),
                'kavenegar' => (string) Config::get("reshen.sms.patterns.kavenegar.{$code}", ''),
                'envKey' => 'SMS_PATTERN_' . strtoupper($driver === 'log' ? 'melipayamak' : $driver) . '_' . strtoupper($code),
            ];
        }

        return $this->page('layouts.panel', 'panel.sms.index', [
            'title' => 'الگوهای پیامک',
            'rows' => $rows,
            'driver' => $driver,
            'dedicatedLine' => (bool) Config::get('reshen.sms.dedicated_line', false),
        ]);
    }
}
