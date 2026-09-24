<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Access\Access;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Queue\QueueService;
use App\Domain\Staff\StaffRepository;
use RuntimeException;

final class QueueController extends Controller
{
    public function index(Request $request): Response
    {
        $salonId = Auth::salonId();
        $queue = new QueueService();
        $snapshot = $queue->salonSnapshot($salonId);

        $myStaffId = Auth::staffId();
        $services = (new ServiceRepository())->all($salonId, true);
        $staffList = (new StaffRepository())->all($salonId, true);
        $todayEarnings = (new \App\Domain\Payment\PaymentRepository())->dailyTotal($salonId, date('Y-m-d'), $myStaffId);

        return $this->page('layouts.panel', 'panel.queue.index', [
            'title' => 'صف زنده',
            'snapshot' => $snapshot,
            // همان اثرِ انگشتی که poll برمی‌گرداند؛ صفحه فقط وقتی دوباره
            // بارگذاری می‌شود که این عوض شده باشد.
            'pollEtag' => $this->etagFor($snapshot),
            'myStaffId' => $myStaffId,
            'services' => $services,
            'staffList' => $staffList,
            'todayCount' => (new \App\Domain\Appointment\AppointmentRepository())->todayCompletedCount($salonId, $myStaffId),
            'todayEarnings' => $todayEarnings,
            'todaySummary' => (new \App\Domain\Appointment\AppointmentRepository())->todaySummary($salonId),
            // درآمد کل سالن فقط برای صاحب و مدیر — آرایشگر نباید درآمد
            // بقیه را ببیند، وگرنه در سالن دعوا می‌شود (سند امنیت، بخش ۴).
            'salonEarnings' => Access::allows(Access::VIEW_SALON_EARNINGS)
                ? (new \App\Domain\Payment\PaymentRepository())->dailyTotal($salonId, date('Y-m-d'), null)
                : null,
            // فقط برای کسی که می‌تواند تسویه کند؛ برای بقیه فهرستی است که
            // کاری با آن نمی‌توانند بکنند.
            'awaitingPayment' => Access::allows(Access::TAKE_PAYMENT)
                ? (new AppointmentRepository())->awaitingPayment($salonId)
                : [],
        ]);
    }

    /**
     * نقطهٔ JSON برای به‌روزرسانی صف، تقریباً بی‌درنگ.
     *
     * هر ۱۵ ثانیه پرسیده می‌شود و با ETag جواب می‌دهد، پس وقتی چیزی
     * عوض نشده فقط یک هدر ردوبدل می‌شود. عمداً WebSocket نیست: روی
     * هاست اشتراکی cPanel چیزی که اتصال باز نگه دارد اجرا نمی‌شود.
     */
    public function poll(Request $request): Response
    {
        $salonId = Auth::salonId();
        $snapshot = (new QueueService())->salonSnapshot($salonId);

        $body = json_encode($this->simplify($snapshot), JSON_UNESCAPED_UNICODE);
        $etag = $this->etagFor($snapshot);

        if ($request->header('If-None-Match') === $etag) {
            return new Response('', 304, ['ETag' => $etag]);
        }

        return new Response($body, 200, ['Content-Type' => 'application/json; charset=UTF-8', 'ETag' => $etag]);
    }

    public function addWalkin(Request $request): Response
    {
        $salonId = Auth::salonId();
        $serviceIds = array_filter(array_map('intval', (array) $request->input('service_ids', [])));
        $staffId = $request->input('staff_id') !== '' && $request->input('staff_id') !== null ? (int) $request->input('staff_id') : null;

        $name = trim((string) $request->input('name', '')) ?: null;
        $queue = new QueueService();

        try {
            $appointment = $queue->addWalkin(
                $salonId,
                $name,
                trim((string) $request->input('phone', '')) ?: null,
                $staffId,
                $serviceIds
            );
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/panel');
        }

        return $this->withSuccess($this->walkinMessage($queue, $appointment, $name), '/panel');
    }

    /**
     * پیامِ «اضافه شد» — با همان جوابی که مشتری جلوی پیشخوان می‌خواهد.
     *
     * اولین سؤالِ مشتریِ حضوری «چقدر طول می‌کشه؟» است. پیش‌تر پیام فقط
     * «مشتری به صف اضافه شد» بود و پذیرش باید صف را پایین می‌رفت، ردیفِ
     * تازه را پیدا می‌کرد و زمانش را می‌خواند. حالا جواب در همان پیام است.
     */
    private function walkinMessage(QueueService $queue, array $appointment, ?string $name): string
    {
        $who = $name !== null ? '«' . $name . '»' : 'مشتری';
        $staff = DB::selectOne('SELECT name FROM staff WHERE id = ?', [(int) $appointment['staff_id']]);
        $staffName = (string) ($staff['name'] ?? '');
        $view = $queue->customerView((string) $appointment['public_token']);

        if (($view['appointment']['status'] ?? '') === 'in_chair') {
            return "{$who} روی صندلیِ {$staffName} نشست.";
        }

        if (!empty($view['display']['next'])) {
            return "{$who} نفرِ بعدیِ {$staffName} است.";
        }

        $when = $view['display']['text'] ?? null;

        return $when !== null
            ? "{$who} به صف {$staffName} اضافه شد — نوبتش {$when}."
            : "{$who} به صف {$staffName} اضافه شد.";
    }

    /**
     * دروازهٔ اکشن‌های صف.
     *
     * آرایشگر فقط روی نوبت خودش کار می‌کند. پیش از این هر عضو سالن
     * می‌توانست نوبتِ آرایشگر دیگری را کامل یا لغو کند — و چون همه‌چیز
     * درست ثبت می‌شد، هیچ ردی از اشتباه نمی‌ماند جز شاکی شدن مشتری.
     */
    private function denyForeignAppointment(int $appointmentId): ?Response
    {
        $appointment = (new AppointmentRepository())->find(Auth::salonId(), $appointmentId);

        if ($appointment === null) {
            return $this->withError('نوبت یافت نشد.', '/panel');
        }

        if (!Access::canActOnAppointment($appointment)) {
            return $this->withError('این نوبت برای شما نیست.', '/panel');
        }

        return null;
    }

    public function start(Request $request): Response
    {
        if ($deny = $this->denyForeignAppointment((int) $request->param('id'))) {
            return $deny;
        }

        try {
            (new QueueService())->startService(Auth::salonId(), (int) $request->param('id'));
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/panel');
        }

        return $this->redirect('/panel');
    }

    public function complete(Request $request): Response
    {
        $salonId = Auth::salonId();
        $appointmentId = (int) $request->param('id');

        if ($deny = $this->denyForeignAppointment($appointmentId)) {
            return $deny;
        }

        try {
            $result = (new QueueService())->completeService($salonId, $appointmentId);
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/panel');
        }

        /*
         * آرایشگری که دسترسیِ تسویه ندارد، به صفحهٔ تسویه فرستاده نمی‌شود.
         *
         * پیش‌تر همه به ‎/panel/pay‎ می‌رفتند، و برای نقشِ «آرایشگر» آن
         * مسیر ۴۰۳ است: هر بار که آرایشگر کارِ مشتری را تمام می‌کرد، با
         * صفحهٔ «دسترسی ندارید» روبه‌رو می‌شد — در حالی که کار درست ثبت
         * شده بود. حالا به صف برمی‌گردد و تسویه در فهرستِ «منتظر تسویه»ِ
         * پیشخوان می‌نشیند.
         */
        if (!Access::allows(Access::TAKE_PAYMENT)) {
            return $this->withSuccess('تمام شد. تسویه را پیشخوان ثبت می‌کند.', '/panel');
        }

        $total = array_sum(array_column($result['items'], 'price'));

        return $this->redirect('/panel/pay/' . $appointmentId . '?amount=' . $total);
    }

    public function noShow(Request $request): Response
    {
        if ($deny = $this->denyForeignAppointment((int) $request->param('id'))) {
            return $deny;
        }

        (new QueueService())->markNoShow(Auth::salonId(), (int) $request->param('id'));

        return $this->withSuccess('به‌عنوان غایب ثبت شد.', '/panel');
    }

    public function cancel(Request $request): Response
    {
        if ($deny = $this->denyForeignAppointment((int) $request->param('id'))) {
            return $deny;
        }

        (new QueueService())->cancel(Auth::salonId(), (int) $request->param('id'), (string) $request->input('reason', ''));

        return $this->withSuccess('نوبت لغو شد.', '/panel');
    }

    private function etagFor(array $snapshot): string
    {
        return '"' . md5((string) json_encode($this->simplify($snapshot), JSON_UNESCAPED_UNICODE)) . '"';
    }

    private function simplify(array $snapshot): array
    {
        return array_map(static function ($group) {
            return [
                'staff' => $group['staff'],
                'queue' => array_map(static function ($row) {
                    return [
                        'id' => $row['id'],
                        'status' => $row['status'],
                        'kind' => $row['kind'],
                        'customer_name' => $row['customer_name'],
                        'display' => $row['display'],
                    ];
                }, $group['queue']),
            ];
        }, $snapshot);
    }
}
