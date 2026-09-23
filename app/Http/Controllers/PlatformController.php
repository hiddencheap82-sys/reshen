<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Scheduler;
use App\Domain\Identity\PasswordService;
use App\Domain\Platform\AuditLog;
use App\Domain\Platform\InvoiceRepository;
use App\Domain\Platform\PlanRepository;
use App\Domain\Platform\SalonAdminRepository;
use App\Domain\Platform\SalonHealth;
use App\Domain\Support\TicketRepository;
use App\Support\IranMobile;
use App\Support\Str;
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
            'alerts' => $this->alerts(),
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
            'issues' => (new SalonHealth())->forSalon($id),
            'tickets' => (new TicketRepository())->forSalon($id),
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

    /**
     * ساخت کاربر تازه، بدون نیاز به اینکه خودش وارد شده باشد.
     *
     * تا پیش از این، کاربر فقط وقتی وجود پیدا می‌کرد که یک بار با کد
     * پیامکی وارد شود. یعنی مدیر نمی‌توانست پیش از شروع کار، حساب
     * پذیرشِ تازه را آماده کند — و روی هاستی که پیامکش هنوز تنظیم
     * نشده، اصلاً نمی‌توانست.
     */
    public function storeUser(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $rawPhone = (string) $request->input('phone', '');
        $password = (string) $request->input('password', '');
        $makeAdmin = $request->input('is_platform_admin') !== null;

        $phone = IranMobile::tryParse($rawPhone);
        if ($phone === null) {
            return $this->withError('شمارهٔ موبایل نامعتبر است.', '/platform/users');
        }

        if (DB::selectOne('SELECT id FROM users WHERE phone = ?', [$phone->e164]) !== null) {
            return $this->withError('کاربری با این شماره از قبل هست.', '/platform/users');
        }

        // رمز اختیاری است: کاربری که رمز ندارد با کد پیامکی وارد می‌شود.
        if ($password !== '') {
            $weak = PasswordService::reject($password, $rawPhone);
            if ($weak !== null) {
                return $this->withError($weak, '/platform/users');
            }
        }

        $userId = (int) DB::insert('users', [
            'phone' => $phone->e164,
            'name' => $name !== '' ? $name : null,
            'is_platform_admin' => $makeAdmin ? 1 : 0,
        ]);

        if ($password !== '') {
            PasswordService::set($userId, $password);
        }

        AuditLog::record(AuditLog::USER_CREATED, null, 'user', $userId, [
            'phone' => $phone->e164,
            'platform_admin' => $makeAdmin,
        ]);

        return $this->withSuccess(
            'کاربر ساخته شد' . ($password !== '' ? ' و رمزش گذاشته شد.' : ' — با کد پیامکی وارد می‌شود.'),
            '/platform/users'
        );
    }

    /**
     * بازنشانی رمز یک کاربر.
     *
     * مدیر رمز تازه را می‌نویسد و شفاهی به صاحبش می‌دهد. جایگزینِ
     * «فراموشی رمز» با ایمیل است، که نداریم چون ایمیل نداریم.
     *
     * رمز قبلی هرگز نمایش داده نمی‌شود و نمی‌تواند هم بشود — چیزی جز
     * hash ذخیره نشده.
     */
    public function resetUserPassword(Request $request): Response
    {
        $id = (int) $request->param('id');
        $user = DB::selectOne('SELECT id, phone FROM users WHERE id = ?', [$id]);

        if ($user === null) {
            return $this->withError('کاربر یافت نشد.', '/platform/users');
        }

        $password = (string) $request->input('password', '');
        $weak = PasswordService::reject($password, (string) $user['phone']);
        if ($weak !== null) {
            return $this->withError($weak, '/platform/users');
        }

        PasswordService::set($id, $password);

        AuditLog::record(AuditLog::USER_PASSWORD_RESET, null, 'user', $id, [
            'phone' => $user['phone'],
        ]);

        return $this->withSuccess('رمز این کاربر عوض شد. خودتان به او اطلاع دهید.', '/platform/users');
    }

    /**
     * مرجع زندهٔ سیستم دیزاین.
     *
     * صفحه است نه سند، چون سند از روز دوم با کد فرق می‌کند و کسی
     * نمی‌فهمد. این یکی همان CSS و همان مؤلفه‌های واقعی را رندر
     * می‌کند: اگر چیزی بشکند، همین‌جا شکسته دیده می‌شود.
     */
    public function design(Request $request): Response
    {
        return $this->page('layouts.panel', 'platform.design', [
            'title' => 'سیستم دیزاین',
        ]);
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

    // ─── ساخت سالن ───────────────────────────────────────────────────

    /**
     * فرم ساخت سالن از پنل پلتفرم.
     *
     * چرا لازم شد: تا حالا سالن فقط با ثبت‌نام خودِ صاحبش ساخته
     * می‌شد. یعنی برای راه‌اندازی یک مشتری، باید پای تلفن راهنمایی‌اش
     * می‌کردیم که خودش ثبت‌نام کند — و بیشترِ آرایشگرها همان‌جا گیر
     * می‌کردند. حالا ما سالن و حسابش را می‌سازیم و فقط شماره و رمز را
     * به او می‌دهیم.
     */
    public function createSalon(Request $request): Response
    {
        return $this->page('layouts.panel', 'platform.salon-create', [
            'title' => 'سالن تازه',
            'plans' => (new PlanRepository())->all(),
        ]);
    }

    public function storeSalon(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $city = trim((string) $request->input('city', ''));
        $address = trim((string) $request->input('address', ''));
        $planCode = (string) $request->input('plan_code', 'trial');
        $seats = max(1, (int) $request->input('seats', 1));
        $ownerPhoneRaw = trim((string) $request->input('owner_phone', ''));
        $ownerName = trim((string) $request->input('owner_name', ''));
        $ownerPassword = (string) $request->input('owner_password', '');

        if ($name === '') {
            return $this->withError('نام سالن را وارد کنید.', '/platform/salons/create');
        }

        $phone = IranMobile::tryParse($ownerPhoneRaw);
        if ($phone === null) {
            return $this->withError('شمارهٔ موبایل صاحب سالن معتبر نیست.', '/platform/salons/create');
        }

        $plans = new PlanRepository();
        if ($plans->find($planCode) === null) {
            return $this->withError('پلن انتخاب‌شده وجود ندارد.', '/platform/salons/create');
        }

        // همان قاعدهٔ صفحهٔ سالن: پلن تک‌صندلی، دو صندلی نمی‌گیرد.
        if (!$plans->allowsSeats($planCode, $seats)) {
            return $this->withError(
                'این پلن بیش از این تعداد صندلی را پوشش نمی‌دهد.',
                '/platform/salons/create'
            );
        }

        // رمز اختیاری است: صاحب سالنی که رمز ندارد با کد پیامکی وارد می‌شود.
        if ($ownerPassword !== '') {
            $weak = PasswordService::reject($ownerPassword, $ownerPhoneRaw);
            if ($weak !== null) {
                return $this->withError($weak, '/platform/salons/create');
            }
        }

        /*
         * slug پایهٔ لینک عمومی و QR است، پس باید لاتین و یکتا باشد.
         * اگر تکراری شد، عدد می‌گیرد — همان کاری که onboarding می‌کند.
         */
        $slug = Str::slug($name) ?: 'salon';
        $base = $slug;
        $i = 1;
        while (DB::selectOne('SELECT id FROM salons WHERE slug = ?', [$slug]) !== null) {
            $slug = $base . '-' . (++$i);
        }

        $result = DB::transaction(function () use (
            $name, $slug, $city, $address, $seats, $planCode, $phone, $ownerName, $ownerPassword
        ) {
            $user = DB::selectOne('SELECT id, name FROM users WHERE phone = ?', [$phone->e164]);
            $isNewUser = $user === null;

            if ($isNewUser) {
                $userId = (int) DB::insert('users', [
                    'phone' => $phone->e164,
                    'name' => $ownerName !== '' ? $ownerName : null,
                ]);
            } else {
                $userId = (int) $user['id'];
                // نامِ خالیِ کاربرِ موجود را پر می‌کنیم، ولی نامِ پرشده را
                // با چیزی که اینجا تایپ شده عوض نمی‌کنیم.
                if ($ownerName !== '' && ($user['name'] ?? '') === '') {
                    DB::update('users', ['name' => $ownerName], 'id = :id', ['id' => $userId]);
                }
            }

            if ($ownerPassword !== '') {
                PasswordService::set($userId, $ownerPassword);
            }

            $salonId = (int) DB::insert('salons', [
                'slug' => $slug,
                'name' => $name,
                'city' => $city !== '' ? $city : null,
                'address' => $address !== '' ? $address : null,
                'seats' => $seats,
                'plan_code' => $planCode,
                'sms_credit' => 200,
                'trial_ends_at' => $planCode === 'trial'
                    ? date('Y-m-d H:i:s', strtotime('+30 days'))
                    : null,
            ]);

            DB::insert('salon_user', [
                'salon_id' => $salonId,
                'user_id' => $userId,
                'role' => 'owner',
            ]);

            /*
             * ساعت کاری پیش‌فرض. بدون این، سالنِ تازه هیچ سانس آزادی
             * ندارد و صفحهٔ عمومی‌اش خالی است — همان ایرادی که صفحهٔ
             * سلامت «هیچ روز بازی ندارد» می‌نامدش.
             */
            for ($weekday = 0; $weekday <= 6; $weekday++) {
                DB::insert('working_hours', [
                    'salon_id' => $salonId,
                    'staff_id' => null,
                    'weekday' => $weekday,
                    'opens_at' => '09:00:00',
                    'closes_at' => '21:00:00',
                    'is_closed' => $weekday === 6 ? 1 : 0,
                ]);
            }

            return ['salon_id' => $salonId, 'user_id' => $userId, 'new_user' => $isNewUser];
        });

        AuditLog::record(AuditLog::SALON_CREATED, $result['salon_id'], 'salon', $result['salon_id'], [
            'name' => $name,
            'slug' => $slug,
            'owner_phone' => $phone->e164,
            'new_user' => $result['new_user'],
        ]);

        $note = $result['new_user']
            ? ' حساب صاحب سالن هم ساخته شد.'
            : ' شمارهٔ صاحب سالن از قبل حساب داشت و به همین سالن وصل شد.';

        return $this->withSuccess(
            'سالن «' . $name . '» ساخته شد.' . $note,
            '/platform/' . $result['salon_id']
        );
    }

    // ─── سلامت سالن‌ها ───────────────────────────────────────────────

    /**
     * کدام سالن همین الان کار نمی‌کند.
     *
     * صفحه‌ای که نبودش بزرگ‌ترین حفرهٔ این پنل بود: می‌شد دید چند سالن
     * داریم، نمی‌شد دید کدامشان خراب است. و خرابی‌های واقعی بی‌صدایند
     * — سالن بدون آرایشگر هیچ خطایی نمی‌دهد، فقط هیچ سانسی ندارد.
     */
    public function health(Request $request): Response
    {
        $onlyBroken = (string) $request->query('all', '') !== '1';
        $health = new SalonHealth();

        return $this->page('layouts.panel', 'platform.health', [
            'title' => 'سلامت سالن‌ها',
            'entries' => $health->all($onlyBroken),
            'summary' => $health->summary(),
            'onlyBroken' => $onlyBroken,
        ]);
    }

    // ─── پشتیبانی ────────────────────────────────────────────────────

    public function support(Request $request): Response
    {
        $repo = new TicketRepository();
        $status = (string) $request->query('status', '');

        return $this->page('layouts.panel', 'platform.support', [
            'title' => 'پشتیبانی',
            'tickets' => $repo->all($status),
            'counts' => $repo->counts(),
            'status' => $status,
        ]);
    }

    public function supportShow(Request $request): Response
    {
        $repo = new TicketRepository();
        $ticket = $repo->find((int) $request->param('id'));

        if ($ticket === null) {
            return $this->withError('تیکت یافت نشد.', '/platform/support');
        }

        return $this->page('layouts.panel', 'platform.support-show', [
            'title' => $ticket['subject'],
            'ticket' => $ticket,
            'messages' => $repo->messages((int) $ticket['id']),
            'issues' => (new SalonHealth())->forSalon((int) $ticket['salon_id']),
        ]);
    }

    public function supportReply(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new TicketRepository();
        $ticket = $repo->find($id);

        if ($ticket === null) {
            return $this->withError('تیکت یافت نشد.', '/platform/support');
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return $this->withError('متن جواب خالی است.', '/platform/support/' . $id);
        }

        $repo->reply($id, Auth::id(), TicketRepository::SIDE_PLATFORM, $body);

        AuditLog::record(AuditLog::SUPPORT_REPLIED, (int) $ticket['salon_id'], 'ticket', $id);

        return $this->withSuccess('جواب ثبت شد.', '/platform/support/' . $id);
    }

    public function supportClose(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new TicketRepository();
        $ticket = $repo->find($id);

        if ($ticket === null) {
            return $this->withError('تیکت یافت نشد.', '/platform/support');
        }

        $repo->close($id);

        AuditLog::record(AuditLog::SUPPORT_CLOSED, (int) $ticket['salon_id'], 'ticket', $id);

        return $this->withSuccess('تیکت بسته شد.', '/platform/support');
    }

    /**
     * چه چیزی *همین الان* خراب است.
     *
     * بالای صفحهٔ نخست می‌نشیند و اگر خالی باشد اصلاً نمایش داده
     * نمی‌شود. قاعده‌اش این است: هر ردیف باید کاری باشد که همین امروز
     * می‌شود انجامش داد. «۱۲۰۰ نوبت ثبت شده» هشدار نیست، عدد است — و
     * عددها پایین‌ترند.
     *
     * @return array<int,array{level:string,title:string,note:string,href:string}>
     */
    private function alerts(): array
    {
        $out = [];

        // ۱. زمان‌بند. اگر نخوابیده، یادآورها نمی‌روند و هیچ خطایی هم
        //    جایی ثبت نمی‌شود — همان چیزی که کرون را شکننده کرده بود.
        $lastRun = Scheduler::lastRunAt();
        if ($lastRun === null || $lastRun < time() - 86400) {
            $out[] = [
                'level' => 'bad',
                'title' => 'زمان‌بند اجرا نشده',
                'note' => $lastRun === null
                    ? 'هنوز هیچ کار دوره‌ای اجرا نشده. یادآورها و پاک‌سازی متوقف‌اند.'
                    : 'بیش از یک روز است اجرا نشده. یادآورها نمی‌روند.',
                'href' => 'doctor.php',
            ];
        }

        // ۲. ارائه‌دهندهٔ پیامک روی log یعنی هیچ پیامکی واقعاً نمی‌رود.
        if ((string) Config::get('reshen.sms.driver', 'log') === 'log') {
            $out[] = [
                'level' => 'bad',
                'title' => 'پیامک روی حالت آزمایشی است',
                'note' => 'هیچ پیامکی ارسال نمی‌شود، فقط در فایل نوشته می‌شود. در .env مقدار SMS_DRIVER را عوض کنید.',
                'href' => 'platform/sms',
            ];
        }

        // ۳. سالن‌هایی که از کار افتاده‌اند.
        $health = (new SalonHealth())->summary();
        if ($health['blocking'] > 0) {
            $out[] = [
                'level' => 'bad',
                'title' => fa_num($health['blocking']) . ' سالن نمی‌تواند نوبت بگیرد',
                'note' => 'آرایشگر، خدمت یا ساعت کاری ندارند. صفحهٔ عمومی‌شان باز می‌شود ولی هیچ سانسی ندارد.',
                'href' => 'platform/health',
            ];
        } elseif ($health['warning'] > 0) {
            $out[] = [
                'level' => 'warn',
                'title' => fa_num($health['warning']) . ' سالن هشدار دارد',
                'note' => 'کار می‌کنند ولی چیزی دارد بد پیش می‌رود.',
                'href' => 'platform/health',
            ];
        }

        // ۴. تیکت‌های بی‌جواب.
        $waiting = (new TicketRepository())->waitingCount();
        if ($waiting > 0) {
            $out[] = [
                'level' => 'warn',
                'title' => fa_num($waiting) . ' تیکت منتظر جواب',
                'note' => 'صاحب سالن پرسیده و هنوز جوابی نگرفته.',
                'href' => 'platform/support',
            ];
        }

        // ۵. پیامک‌های ناموفق در ۷ روز.
        $failed = (int) (DB::selectOne(
            "SELECT COUNT(*) AS c FROM sms_messages
              WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )['c'] ?? 0);
        if ($failed > 0) {
            $out[] = [
                'level' => 'warn',
                'title' => fa_num($failed) . ' پیامک ناموفق در هفتهٔ گذشته',
                'note' => 'معمولاً یعنی الگو تأیید نشده یا حساب اپراتور تمام شده.',
                'href' => 'platform/sms',
            ];
        }

        // ۶. مهاجرت‌های اجرانشده — بعد از آپدیت بسته پیش می‌آید و
        //    تا اجرا نشوند، صفحه‌هایی که ستون تازه می‌خواهند خطا می‌دهند.
        $pending = (new Migrator(BASE_PATH . '/database/migrations'))->pendingCount();
        if ($pending > 0) {
            $out[] = [
                'level' => 'bad',
                'title' => fa_num($pending) . ' مهاجرت اجرا نشده',
                'note' => 'بستهٔ تازه آپلود شده ولی دیتابیس به‌روز نشده. install.php را باز کنید.',
                'href' => 'doctor.php',
            ];
        }

        return $out;
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
