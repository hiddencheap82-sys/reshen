<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\DB;
use App\Support\Str;

final class AppointmentRepository
{
    public function find(int $salonId, int $id): ?array
    {
        return DB::selectOne('SELECT * FROM appointments WHERE salon_id = ? AND id = ?', [$salonId, $id]);
    }

    public function findByToken(string $token): ?array
    {
        return DB::selectOne('SELECT * FROM appointments WHERE public_token = ?', [$token]);
    }

    /** Everything still "live" for a salon today: queued, in_chair, or confirmed (booked, not yet arrived). */
    public function activeForSalon(int $salonId): array
    {
        return DB::select(
            "SELECT a.*, c.name AS customer_name, c.phone AS customer_phone, s.name AS staff_name, s.color AS staff_color
             FROM appointments a
             JOIN customers c ON c.id = a.customer_id
             LEFT JOIN staff s ON s.id = a.staff_id
             WHERE a.salon_id = ? AND a.status IN ('confirmed','queued','in_chair')
             ORDER BY a.queued_at IS NULL, a.queued_at, a.scheduled_at, a.id",
            [$salonId]
        );
    }

    public function activeForStaff(int $salonId, int $staffId): array
    {
        return DB::select(
            "SELECT a.*, c.name AS customer_name, c.phone AS customer_phone
             FROM appointments a JOIN customers c ON c.id = a.customer_id
             WHERE a.salon_id = ? AND a.staff_id = ? AND a.status IN ('confirmed','queued','in_chair')
             ORDER BY a.id",
            [$salonId, $staffId]
        );
    }

    public function inChairFor(int $salonId, int $staffId): ?array
    {
        return DB::selectOne(
            "SELECT * FROM appointments WHERE salon_id = ? AND staff_id = ? AND status = 'in_chair' LIMIT 1",
            [$salonId, $staffId]
        );
    }

    public function itemsFor(int $salonId, int $appointmentId): array
    {
        return DB::select(
            'SELECT ai.*, sv.name AS service_name FROM appointment_items ai
             JOIN services sv ON sv.id = ai.service_id
             WHERE ai.salon_id = ? AND ai.appointment_id = ?',
            [$salonId, $appointmentId]
        );
    }

    public function create(int $salonId, array $data): int
    {
        $data['salon_id'] = $salonId;
        $data['public_token'] = $data['public_token'] ?? Str::token(12);

        return (int) DB::insert('appointments', $data);
    }

    public function addItem(int $salonId, int $appointmentId, int $serviceId, int $price, ?int $durationMinutes): void
    {
        DB::insert('appointment_items', [
            'salon_id' => $salonId,
            'appointment_id' => $appointmentId,
            'service_id' => $serviceId,
            'price' => $price,
            'duration_minutes' => $durationMinutes,
        ]);
    }

    public function update(int $salonId, int $id, array $data): void
    {
        DB::update('appointments', $data, 'salon_id = :salon_id AND id = :id', ['salon_id' => $salonId, 'id' => $id]);
    }

    public function todayCompletedCount(int $salonId, ?int $staffId = null): int
    {
        $sql = "SELECT COUNT(*) AS c FROM appointments WHERE salon_id = ? AND status = 'completed' AND DATE(actual_end_at) = CURDATE()";
        $params = [$salonId];
        if ($staffId !== null) {
            $sql .= ' AND staff_id = ?';
            $params[] = $staffId;
        }

        return (int) (DB::selectOne($sql, $params)['c'] ?? 0);
    }
}
