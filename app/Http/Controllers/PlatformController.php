<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

/** G01/G03/G04 — the platform's own admin panel: salon list, support login-as (audited), key metrics. */
final class PlatformController extends Controller
{
    public function index(Request $request): Response
    {
        $salons = DB::select(
            "SELECT s.*,
                (SELECT COUNT(*) FROM staff st WHERE st.salon_id = s.id AND st.is_active = 1) AS staff_count,
                (SELECT COUNT(*) FROM appointments a WHERE a.salon_id = s.id AND a.status = 'completed') AS completed_count
             FROM salons s ORDER BY s.created_at DESC"
        );

        $metrics = $this->platformMetrics();

        return $this->page('layouts.panel', 'platform.index', [
            'title' => 'پنل پلتفرم',
            'salons' => $salons,
            'metrics' => $metrics,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$id]);
        if ($salon === null) {
            return $this->withError('یافت نشد.', '/platform');
        }

        $owners = DB::select(
            "SELECT u.name, u.phone, su.role FROM salon_user su JOIN users u ON u.id = su.user_id
             WHERE su.salon_id = ? ORDER BY su.role",
            [$id]
        );
        $auditLogs = DB::select(
            "SELECT al.*, u.phone AS actor_phone FROM audit_logs al LEFT JOIN users u ON u.id = al.actor_user_id
             WHERE al.salon_id = ? ORDER BY al.id DESC LIMIT 20",
            [$id]
        );

        return $this->page('layouts.panel', 'platform.show', [
            'title' => $salon['name'],
            'salon' => $salon,
            'owners' => $owners,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function impersonate(Request $request): Response
    {
        $id = (int) $request->param('id');
        $salon = DB::selectOne('SELECT id FROM salons WHERE id = ?', [$id]);
        if ($salon === null) {
            return $this->withError('یافت نشد.', '/platform');
        }

        DB::insert('audit_logs', [
            'salon_id' => $id,
            'actor_user_id' => Auth::id(),
            'action' => 'support_login_as',
            'subject_type' => 'salon',
            'subject_id' => $id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        Auth::startImpersonating($id);

        return $this->redirect('/panel');
    }

    public function stopImpersonating(Request $request): Response
    {
        Auth::stopImpersonating();

        return $this->redirect('/platform');
    }

    public function adjustSmsCredit(Request $request): Response
    {
        $id = (int) $request->param('id');
        $delta = (int) $request->input('delta', 0);
        $salon = DB::selectOne('SELECT sms_credit FROM salons WHERE id = ?', [$id]);
        if ($salon === null) {
            return $this->withError('یافت نشد.', '/platform');
        }

        $newBalance = max(0, (int) $salon['sms_credit'] + $delta);
        DB::update('salons', ['sms_credit' => $newBalance], 'id = :id', ['id' => $id]);
        DB::insert('sms_wallet_transactions', [
            'salon_id' => $id,
            'delta' => $delta,
            'balance_after' => $newBalance,
            'reason' => 'platform_admin_adjustment',
        ]);

        return $this->withSuccess('کیف پیامک به‌روزرسانی شد.', '/platform/' . $id);
    }

    private function platformMetrics(): array
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
