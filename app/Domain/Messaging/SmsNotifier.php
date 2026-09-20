<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Core\Config;
use App\Core\DB;
use DateTimeImmutable;

/**
 * The anti-annoyance rules from doc 8.6 wrapped around SmsManager: at most
 * 4 SMS per appointment, nothing between 23:00-08:00 except "chair ready",
 * and "we're running late" is sent at most once — a second one is worse
 * than silence.
 */
final class SmsNotifier
{
    public function notify(int $salonId, array $appointment, string $templateCode, string $body, bool $critical): bool
    {
        $toPhone = DB::selectOne('SELECT phone FROM customers WHERE id = ?', [$appointment['customer_id']])['phone'] ?? null;
        if ($toPhone === null) {
            return false;
        }

        $maxPerAppointment = (int) Config::get('reshen.sms.max_per_appointment', 4);
        $sentCount = (int) (DB::selectOne(
            "SELECT COUNT(*) AS c FROM sms_messages WHERE appointment_id = ? AND status = 'sent'",
            [$appointment['id']]
        )['c'] ?? 0);

        if ($sentCount >= $maxPerAppointment) {
            $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, 'skipped_rate_limit');

            return false;
        }

        if (!$critical && $this->inQuietHours()) {
            $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, 'skipped_quiet_hours');

            return false;
        }

        if ($templateCode === 'queue_delayed') {
            $already = DB::selectOne(
                "SELECT id FROM sms_messages WHERE appointment_id = ? AND template_code = 'queue_delayed' AND status = 'sent'",
                [$appointment['id']]
            );
            if ($already !== null) {
                $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, 'skipped_rate_limit');

                return false;
            }
        }

        $salon = DB::selectOne('SELECT sms_credit FROM salons WHERE id = ?', [$salonId]);
        if (!$critical && (int) ($salon['sms_credit'] ?? 0) <= 0) {
            $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, 'skipped_no_credit');

            return false;
        }
        $emergencyFloor = -1 * (int) Config::get('reshen.sms.emergency_credit', 100);
        if ($critical && (int) ($salon['sms_credit'] ?? 0) <= $emergencyFloor) {
            $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, 'skipped_no_credit');

            return false;
        }

        $result = SmsManager::send($toPhone, $body);
        $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, $result['ok'] ? 'sent' : 'failed', $result);

        if ($result['ok']) {
            DB::update('salons', ['sms_credit' => (int) ($salon['sms_credit'] ?? 0) - 1], 'id = :id', ['id' => $salonId]);
        }

        return $result['ok'];
    }

    public function alreadySent(int $appointmentId, string $templateCode): bool
    {
        return DB::selectOne(
            "SELECT id FROM sms_messages WHERE appointment_id = ? AND template_code = ? AND status = 'sent'",
            [$appointmentId, $templateCode]
        ) !== null;
    }

    private function inQuietHours(): bool
    {
        $hour = (int) date('G');
        $start = (int) Config::get('reshen.sms.quiet_hours_start', 23);
        $end = (int) Config::get('reshen.sms.quiet_hours_end', 8);

        return $hour >= $start || $hour < $end;
    }

    private function log(int $salonId, array $appointment, string $phone, string $template, string $body, bool $critical, string $status, ?array $result = null): void
    {
        DB::insert('sms_messages', [
            'salon_id' => $salonId,
            'appointment_id' => $appointment['id'],
            'to_phone' => $phone,
            'template_code' => $template,
            'body' => $body,
            'is_critical' => $critical ? 1 : 0,
            'provider' => $result['provider'] ?? Config::get('reshen.sms.driver', 'log'),
            'provider_ref' => $result['ref'] ?? null,
            'status' => $status,
            'error_message' => $result['error'] ?? null,
            'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
        ]);
    }
}
