<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * هر برچسب به کنترلِ خودش وصل است.
 *
 * چرا این تست هست: ‎<label for="x">‎ برچسب را به عنصری با ‎id="x"‎ وصل
 * می‌کند، *حتی اگر خودِ برچسب چک‌باکسِ دیگری را در بر گرفته باشد*.
 * در تنظیمات سالن، هفت برچسبِ «تعطیل» ‎for="off_staff_id"‎ داشتند و
 * برچسبِ «حذف لوگوی فعلی» ‎for="name"‎: زدنِ متن، روز را نمی‌بست یا
 * لوگو را علامت نمی‌زد — فیلدِ دیگری در جای دیگری از صفحه فعال می‌شد.
 * هیچ خطایی هم دیده نمی‌شد؛ فقط «کار نمی‌کرد».
 */
final class LabelForTest extends TestCase
{
    public function test_a_label_wrapping_a_control_points_at_that_control(): void
    {
        $offenders = [];

        foreach ($this->views() as $path => $body) {
            preg_match_all('~<label\b([^>]*)>(.*?)</label>~s', $body, $labels, PREG_SET_ORDER);

            foreach ($labels as [, $attrs, $inner]) {
                if (!preg_match('~\bfor="([^"]+)"~', $attrs, $for)) {
                    continue;
                }
                if (!preg_match('~<(?:input|select|textarea)\b([^>]*)>~', $inner, $control)) {
                    continue;
                }
                preg_match('~\bid="([^"]+)"~', $control[1], $id);
                if (($id[1] ?? null) !== $for[1]) {
                    $offenders[] = "{$path}: for=\"{$for[1]}\" ولی کنترلِ درونش " . (isset($id[1]) ? "id=\"{$id[1]}\"" : 'بی‌id') . ' است';
                }
            }
        }

        self::assertSame([], $offenders);
    }

    public function test_every_for_points_at_an_id_on_the_same_page(): void
    {
        $offenders = [];

        foreach ($this->views() as $path => $body) {
            preg_match_all('~\bid="([^"<>]+)"~', $body, $ids);
            preg_match_all('~<label\b[^>]*\bfor="([^"<>]+)"~', $body, $fors);

            foreach (array_diff(array_unique($fors[1]), $ids[1]) as $orphan) {
                $offenders[] = "{$path}: for=\"{$orphan}\"";
            }
        }

        self::assertSame([], $offenders, 'برچسبی که به عنصری ناموجود اشاره می‌کند.');
    }

    /** @return array<string,string> */
    private function views(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . '/resources/views'));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $out[str_replace(BASE_PATH . '/resources/views/', '', $file->getPathname())] = (string) file_get_contents($file->getPathname());
            }
        }

        return $out;
    }
}
