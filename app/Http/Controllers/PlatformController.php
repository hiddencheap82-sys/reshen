<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Platform\AuditLog;
use App\Domain\Platform\InvoiceRepository;
use App\Domain\Platform\PlanRepository;
use App\Domain\Platform\SalonAdminRepository;
use App\Support\Money;
use DateTimeImmutable;

/**
 * پنل خودِ پلتفرم — بالاترین سطح دسترسی.
 *
 * تنها جایی در برنامه که داده را بدون محدودیت `salon_id` می‌بیند. به
 * همین دلیل هر مسیرش پشت `PlatformAdminRequired` است و هر کنشی که
 * روی دادهٔ یک سالن اثر بگذارد، در `audit_logs` رد می‌گذارد —
 * دسترسی‌ای که رد نگذارد، دسترسی‌ای است که کسی جوابگویش نیست.
 *
 * چه چیزی اینجاست: سالن‌ها و پلنشان، صورتحساب اشتراک، کاربران و
 * دسترسی مدیر پلتفرم، مصرف پیامک، و گزارش فعالیت.
 *
 * چه چیزی عمداً اینجا نیست: پرداخت خودکار اشتراک. در ایران پرداخت
 * دوره‌ای برای کسب‌وکار کوچک جا نیفتاده و درگاه‌ها هم برایش ساخته
 * نشده‌اند؛ سیستمِ نیم‌کاره‌ای که آدم به آن تکیه کند بدتر از نبودنش
 * است. صورتحساب ثبت می‌شود، پرداخت بیرون انجام می‌گیرد، و اینجا
 * «پرداخت شد» می‌خورد.
 */
final class PlatformController extends Controller
{
    public function index(Request $request): Response
    {
        $salons = new SalonAdminRepository();
        $invoices = new InvoiceRepository();

        return $this->page('layouts.panel', 'platform.index', [
            'title' => 'پنل پلتفرم',
            'metrics' => $salons->metrics() + $this->qualityMetrics(),
            'quiet' => $salons->goingQuiet(),
            'invoiceTotals' => $invoices->totals(),
            'recent' => AuditLog::recent(8),
        ]);
    }

    /** فهرست سالن‌ها، با جستجو و فیلتر. */
    public function salons(Request $request): Response
    {
        $repo = new SalonAdminRepository();
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        return $this->page('layouts.panel', 'platform.salons', [
            'title' => 'سالن‌ها',
            'salons' => $repo->all($search, $status),
            'plans' => $this->planNames(),
            'q' => $search,
            'status' => $status,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new SalonAdminRepository();
        $salon = $repo->find($id);

        if ($salon === null) {
            return $this->withError('سالن یافت نشد.', '/platform/salons');
        }

        $owners = DB::select(
            'SELECT u.id, u.name, u.phone, su.role
               FROM salon_user su JOIN users u ON u.id = su.user_id
              WHERE su.salon_id = ? ORDER BY su.role',
            [$id]
        );

        $plans = new PlanRepository();

        return $this->page('layouts.panel', 'platform.show', [
            'title' => $salon['name'],
            'salon' => $salon,
            'owners' => $owners,
            'plans' => $plans->all(),
            'monthlyPrice' => $plans->monthlyPriceFor(
                (string) $salon['plan_code'],
                (int) $salon['seats']
            ),
            'invoices' => (new InvoiceRepository())->forSalon($id),
            'auditLogs' => AuditLog::recent(20, $id),
        ]);
    }

    /**
     * تغییر پلن و تعداد صندلی.
     *
     * تعداد صندلی پیش از ذخیره سنجیده می‌شود: پلن «تک‌صندلی» صندلی
     * اضافه ندارد، پس دو صندلی رویش اصلاً ممکن نیست — نه اینکه
     * گران‌تر شود. اگر جلویش گرفته نشود، سالن سه صندلی می‌گیرد و پول
     * یک صندلی می‌دهد.
     */
    public function updatePlan(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new SalonAdminRepository();
        $salon = $repo->find($id);

        if ($salon === null) {
            return $this->withError('سالن یافت نشد.', '/platform/salons');
        }

        $planCode = (string) $request->input('plan_code', '');
        $seats = max(1, (int) $request->input('seats', 1));
        $plans = new PlanRepository();

        if ($plans->find($planCode) === null) {
            return $this->withError('این پلن وجود ندارد.', '/platform/' . $id);
        }
        if (!$plans->allowsSeats($planCode, $seats)) {
            return $this->withError(
                'این پلن بیش از این تعداد صندلی را نمی‌پذیرد.',
                '/platform/' . $id
            );
        }

        /*
         * «بدون مهلت» یک تیک جداست، نه تاریخِ خالی.
         *
         * چون سلکت‌های شمسی همیشه مقداری دارند (پیش‌فرضشان امروز
         * است)، «خالی گذاشتن» ممکن نیست. بدون این تیک، هیچ راهی برای
         * برداشتن مهلت نبود.
         */
        $trialEndsAt = $request->input('no_trial_end') === '1'
            ? null
            : jalali_date_from_request($request, 'trial_ends');

        if ($trialEndsAt !== null) {
            $trialEndsAt .= ' 23:59:59';
        }

        $repo->updatePlan($id, $planCode, $seats, $trialEndsAt);

        AuditLog::record(AuditLog::PLAN_CHANGED, $id, 'salon', $id, [
            'from' => $salon['plan_code'],
            'to' => $planCode,
            'seats' => $seats,
        ]);

        return $this->withSuccess('پلن سالن به‌روز شد.', '/platform/' . $id);
    }

    /** فعال یا غیرفعال کردن سالن. */
    public function toggleActive(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new SalonAdminRepository();
        $salon = $repo->find($id);

        if ($salon === null) {
            return $this->withError('سالن یافت نشد.', '/platform/salons');
        }

        $active = !(bool) $salon['is_active'];
        $repo->setActive($id, $active);

        AuditLog::record(
            $active ? AuditLog::SALON_ACTIVATED : AuditLog::SALON_DEACTIVATED,
            $id,
            'salon',
            $id
        );

        return $this->withSuccess(
            $active
                ? 'سالن فعال شد و صفحهٔ عمومی‌اش باز است.'
                : 'سالن غیرفعال شد. داده‌اش دست‌نخورده می‌ماند.',
            '/platform/' . $id
        );
    }

    // ─── صورتحساب اشتراک ─────────────────────────────────────────────

    public function invoices(Request $request): Response
    {
        $repo = new InvoiceRepository();
        $repo->markOverdue();

        return $this->page('layouts.panel', 'platform.invoices', [
            'title' => 'صورتحساب‌ها',
            'invoices' => $repo->all((string) $request->query('status', '')),
            'totals' => $repo->totals(),
            'status' => (string) $request->query('status', ''),
        ]);
    }

    /**
     * صدور صورتحساب ماه جاری برای یک سالن.
     *
     * مبلغ از روی پلن و تعداد صندلی *همین لحظه* حساب می‌شود، نه از
     * روی چیزی که کاربر تایپ کرده. اگر دستی بود، اولین اشتباهِ تایپی
     * می‌شد اختلاف حساب با مشتری.
     */
    public function issueInvoice(Request $request): Response
    {
        $id = (int) $request->param('id');
        $salon = (new SalonAdminRepository())->find($id);

        if ($salon === null) {
            return $this->withError('سالن یافت نشد.', '/platform/salons');
        }

        $planCode = (string) $salon['plan_code'];
        $amount = (new PlanRepository())->monthlyPriceFor($planCode, (int) $salon['seats']);

        if ($amount <= 0) {
            return $this->withError(
                'این پلن قیمت ثابت ندارد (آزمایشی یا توافقی). صورتحسابش باید بیرون از سیستم ساخته شود.',
                '/platform/' . $id
            );
        }

        $invoiceId = (new InvoiceRepository())->issue(
            $id,
            $planCode,
            $amount,
            new DateTimeImmutable('today')
        );

        AuditLog::record(AuditLog::INVOICE_ISSUED, $id, 'invoice', $invoiceId, [
            'amount' => $amount,
            'plan' => $planCode,
        ]);

        return $this->withSuccess(
            'صورتحساب این ماه صادر شد: ' . Money::fromRials($amount)->formatToman(),
            '/platform/' . $id
        );
    }

    public function payInvoice(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new InvoiceRepository();
        $invoice = $repo->find($id);

        if ($invoice === null) {
            return $this->withError('صورتحساب یافت نشد.', '/platform/invoices');
        }

        $repo->markPaid($id);
        AuditLog::record(
            AuditLog::INVOICE_PAID,
            (int) $invoice['salon_id'],
            'invoice',
            $id,
            ['amount' => (int) $invoice['amount']]
        );

        return $this->withSuccess('صورتحساب پرداخت‌شده علامت خورد.', '/platform/invoices');
    }

    public function cancelInvoice(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new InvoiceRepository();
        $invoice = $repo->find($id);

        if ($invoice === null) {
            return $this->withError('صورتحساب یافت نشد.', '/platform/invoices');
        }

        $repo->cancel($id);
        AuditLog::record(AuditLog::INVOICE_CANCELLED, (int) $invoice['salon_id'], 'invoice', $id);

        return $this->withSuccess('صورتحساب لغو شد.', '/platform/invoices');
    }

    // ─── کاربران ─────────────────────────────────────────────────────

    public function users(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $args = [];
        $sql = "SELECT u.*,
                    (SELECT GROUP_CONCAT(CONCAT(s.name, ' (', su.role, ')') SEPARATOR ' · ')
                       FROM salon_user su JOIN salons s ON s.id = su.salon_id
                      WHERE su.user_id = u.id) AS memberships
                  FROM users u";

        if ($search !== '') {
            $sql .= ' WHERE u.phone LIKE ? OR u.name LIKE ?';
            $args[] = '%' . $search . '%';
            $args[] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY u.is_platform_admin DESC, u.id DESC LIMIT 200';

        return $this->page('layouts.panel', 'platform.users', [
            'title' => 'کاربران',
            'users' => DB::select($sql, $args),
            'q' => $search,
        ]);
    }

    /**
     * دادن یا گرفتن دسترسی مدیر پلتفرم.
     *
     * دو محافظ اینجا هست و هر دو لازم‌اند: کسی نمی‌تواند دسترسی خودش
     * را بگیرد (وگرنه با یک کلیک همه بیرون می‌مانند)، و آخرین مدیر
     * هم نمی‌تواند برداشته شود (وگرنه هیچ‌کس نمی‌تواند برش گرداند —
     * جز با دسترسی مستقیم به دیتابیس).
     */
    public function togglePlatformAdmin(Request $request): Response
    {
        $id = (int) $request->param('id');
        $user = DB::selectOne('SELECT id, phone, is_platform_admin FROM users WHERE id = ?', [$id]);

        if ($user === null) {
            return $this->withError('کاربر یافت نشد.', '/platform/users');
        }
        if ($id === Auth::id()) {
            return $this->withError('دسترسی خودتان را نمی‌توانید عوض کنید.', '/platform/users');
        }

        $making = !(bool) $user['is_platform_admin'];

        if (!$making) {
            $count = (int) (DB::selectOne(
                'SELECT COUNT(*) AS c FROM users WHERE is_platform_admin = 1'
            )['c'] ?? 0);

            if ($count <= 1) {
                return $this->withError(
                    'این تنها مدیر پلتفرم است. اول یکی دیگر را مدیر کنید.',
                    '/platform/users'
                );
            }
        }

        DB::update('users', ['is_platform_admin' => $making ? 1 : 0], 'id = :id', ['id' => $id]);

        AuditLog::record(
            $making ? AuditLog::PLATFORM_ADMIN_GRANTED : AuditLog::PLATFORM_ADMIN_REVOKED,
            null,
            'user',
            $id,
            ['phone' => $user['phone']]
        );

        return $this->withSuccess(
            $making ? 'این کاربر حالا مدیر پلتفرم است.' : 'دسترسی مدیر پلتفرم گرفته شد.',
            '/platform/users'
        );
    }

    // ─── مصرف پیامک و گزارش فعالیت ───────────────────────────────────

    public function sms(Request $request): Response
    {
        return $this->page('layouts.panel', 'platform.sms', [
            'title' => 'مصرف پیامک',
            'usage' => (new SalonAdminRepository())->smsUsage(),
            'failures' => DB::select(
                "SELECT m.template_code, m.error_message, COUNT(*) AS count, MAX(m.created_at) AS last_at
                   FROM sms_messages m
                  WHERE m.status = 'failed'
                    AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  GROUP BY m.template_code, m.error_message
                  ORDER BY count DESC LIMIT 20"
            ),
        ]);
    }

    public function activity(Request $request): Response
    {
        return $this->page('layouts.panel', 'platform.activity', [
            'title' => 'گزارش فعالیت',
            'logs' => AuditLog::recent(100),
        ]);
    }

    // ─── پلن‌ها ──────────────────────────────────────────────────────

    public function plans(Request $request): Response
    {
        return $this->page('layouts.panel', 'platform.plans', [
            'title' => 'پلن‌ها',
            'plans' => (new PlanRepository())->all(),
            'counts' => $this->salonsPerPlan(),
        ]);
    }

    /** @return array<string,string> کد پلن => نام فارسی */
    private function planNames(): array
    {
        $out = [];
        foreach ((new PlanRepository())->all() as $plan) {
            $out[(string) $plan['code']] = (string) $plan['name'];
        }

        return $out;
    }

    /** @return array<string,int> */
    private function salonsPerPlan(): array
    {
        $out = [];
        foreach (DB::select('SELECT plan_code, COUNT(*) AS c FROM salons GROUP BY plan_code') as $row) {
            $out[(string) $row['plan_code']] = (int) $row['c'];
        }

        return $out;
    }

    public function impersonate(Request $request): Response
    {
        $id = (int) $request->param('id');
        $salon = DB::selectOne('SELECT id FROM salons WHERE id = ?', [$id]);
        if ($salon === null) {
            return $this->withError('سالن یافت نشد.', '/platform/salons');
        }

        AuditLog::record(AuditLog::SUPPORT_LOGIN, $id, 'salon', $id);

        Auth::startImpersonating($id);

        return $this->redirect('/panel');
    }

    public function stopImpersonating(Request $request): Response
    {
        Auth::stopImpersonating();

        return $this->redirect('/platform');
    }

    private function qualityMetrics(): array
    {
        $activeSalons = (int) (DB::selectOne("SELECT COUNT(*) AS c FROM salons WHERE is_active = 1")['c'] ?? 0);
        $completedThisWeek = (int) (DB::selectOne(
            "SELECT COUNT(*) AS c FROM appointments WHERE status = 'completed' AND actual_end_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )['c'] ?? 0);
        $avgMae = DB::selectOne(
            "SELECT AVG(ABS(TIMESTAMPDIFF(MINUTE, estimated_start_at, actual_start_at))) AS mae
             FROM appointments WHERE estimated_start_at IS NOT NULL AND actual_start_at IS NOT NULL
             AND actual_end_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )['mae'] ?? null;
        $completionRate = DB::selectOne(
            "SELECT
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status IN ('completed','no_show') THEN 1 ELSE 0 END) AS total
             FROM appointments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $endRate = $completionRate && (int) $completionRate['total'] > 0
            ? round(($completionRate['completed'] / $completionRate['total']) * 100, 1)
            : null;

        return [
            'active_salons' => $activeSalons,
            'completed_this_week' => $completedThisWeek,
            'mae_minutes' => $avgMae !== null ? round((float) $avgMae, 1) : null,
            'end_registration_rate' => $endRate,
        ];
    }
}
