<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\DB;
use App\Domain\Support\TicketRepository;
use PHPUnit\Framework\TestCase;

/**
 * تیکت پشتیبانی.
 *
 * دو چیز اینجا اهمیت دارند و هر دو می‌توانند بی‌صدا بشکنند:
 *
 * ۱. **وضعیت.** «توپ زمین کیست» را هیچ‌کس دستی نمی‌گذارد؛ خودِ پیام
 *    جابه‌جایش می‌کند. اگر این منطق بشکند، تیکتی که جواب نگرفته
 *    «جواب داده شد» نشان داده می‌شود و شمارندهٔ صفحهٔ نخست صفر
 *    می‌ماند — یعنی کسی منتظر است و ما خبر نداریم.
 *
 * ۲. **تنانسی.** تیکت سالن دیگر نباید با هیچ شناسه‌ای پیدا شود. این
 *    تنها جای برنامه است که دو کسب‌وکار دربارهٔ مشکلاتشان حرف
 *    می‌زنند.
 */
final class SupportTicketTest extends TestCase
{
    private int $salonA;
    private int $salonB;
    private int $userId;
    private TicketRepository $repo;

    protected function setUp(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['support_messages', 'support_tickets', 'salon_user', 'users', 'salons'] as $t) {
            DB::statement("DELETE FROM `{$t}` WHERE 1");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->salonA = $this->makeSalon('سالن الف');
        $this->salonB = $this->makeSalon('سالن ب');
        $this->userId = (int) DB::insert('users', [
            'phone' => '+98912' . random_int(1000000, 9999999),
            'name' => 'صاحب سالن',
        ]);
        $this->repo = new TicketRepository();
    }

    private function makeSalon(string $name): int
    {
        return (int) DB::insert('salons', [
            'slug' => 'tik-' . bin2hex(random_bytes(4)),
            'name' => $name,
            'is_active' => 1,
            'plan_code' => 'trial',
            'seats' => 1,
        ]);
    }

    // ─── وضعیت ───────────────────────────────────────────────────────

    public function test_a_new_ticket_waits_for_us(): void
    {
        $id = $this->repo->open($this->salonA, $this->userId, 'پیامک نمی‌رود', 'دو روز است هیچ یادآوری نرفته.');

        $ticket = $this->repo->find($id);

        self::assertSame(TicketRepository::OPEN, $ticket['status']);
        self::assertSame(1, $this->repo->waitingCount());
    }

    public function test_our_reply_moves_the_ball_to_the_salon(): void
    {
        $id = $this->repo->open($this->salonA, $this->userId, 'سؤال', 'متن');

        $this->repo->reply($id, null, TicketRepository::SIDE_PLATFORM, 'بررسی کردیم.');

        self::assertSame(TicketRepository::ANSWERED, $this->repo->find($id)['status']);
        self::assertSame(0, $this->repo->waitingCount());
    }

    public function test_the_salon_speaking_again_moves_it_back(): void
    {
        $id = $this->repo->open($this->salonA, $this->userId, 'سؤال', 'متن');
        $this->repo->reply($id, null, TicketRepository::SIDE_PLATFORM, 'جواب');

        $this->repo->reply($id, $this->userId, TicketRepository::SIDE_SALON, 'هنوز درست نشده.');

        self::assertSame(TicketRepository::OPEN, $this->repo->find($id)['status']);
        self::assertSame(1, $this->repo->waitingCount());
    }

    /**
     * تیکتِ بسته که طرف دوباره حرف بزند، باز می‌شود.
     *
     * اگر بسته بماند، پیامش هیچ‌جا شمرده نمی‌شود و عملاً گم شده —
     * بدترین حالتِ ممکن برای یک سیستم پشتیبانی.
     */
    public function test_replying_to_a_closed_ticket_reopens_it(): void
    {
        $id = $this->repo->open($this->salonA, $this->userId, 'سؤال', 'متن');
        $this->repo->close($id);
        self::assertSame(TicketRepository::CLOSED, $this->repo->find($id)['status']);

        $this->repo->reply($id, $this->userId, TicketRepository::SIDE_SALON, 'باز هم مشکل دارم.');

        $ticket = $this->repo->find($id);
        self::assertSame(TicketRepository::OPEN, $ticket['status']);
        self::assertNull($ticket['closed_at']);
        self::assertSame(1, $this->repo->waitingCount());
    }

    public function test_closing_stops_it_from_being_counted(): void
    {
        $id = $this->repo->open($this->salonA, $this->userId, 'سؤال', 'متن');

        $this->repo->close($id);

        self::assertSame(0, $this->repo->waitingCount());
        self::assertNotNull($this->repo->find($id)['closed_at']);
    }

    // ─── پیام‌ها ─────────────────────────────────────────────────────

    public function test_the_first_message_is_the_body_of_the_ticket(): void
    {
        $id = $this->repo->open($this->salonA, $this->userId, 'عنوان', 'متن اولیه');

        $messages = $this->repo->messages($id);

        self::assertCount(1, $messages);
        self::assertSame('متن اولیه', $messages[0]['body']);
        self::assertSame(TicketRepository::SIDE_SALON, $messages[0]['side']);
    }

    public function test_messages_come_back_in_order(): void
    {
        $id = $this->repo->open($this->salonA, $this->userId, 'عنوان', 'یک');
        $this->repo->reply($id, null, TicketRepository::SIDE_PLATFORM, 'دو');
        $this->repo->reply($id, $this->userId, TicketRepository::SIDE_SALON, 'سه');

        self::assertSame(['یک', 'دو', 'سه'], array_column($this->repo->messages($id), 'body'));
    }

    // ─── تنانسی ──────────────────────────────────────────────────────

    public function test_a_salon_cannot_open_another_salons_ticket(): void
    {
        $id = $this->repo->open($this->salonA, $this->userId, 'خصوصی', 'متن');

        self::assertNull($this->repo->find($id, $this->salonB));
        self::assertNotNull($this->repo->find($id, $this->salonA));
    }

    public function test_a_salons_list_holds_only_its_own_tickets(): void
    {
        $this->repo->open($this->salonA, $this->userId, 'مال الف', 'متن');
        $this->repo->open($this->salonB, $this->userId, 'مال ب', 'متن');

        self::assertSame(['مال الف'], array_column($this->repo->forSalon($this->salonA), 'subject'));
    }

    // ─── فهرست پلتفرم ────────────────────────────────────────────────

    /** منتظرِ ما بالا، بعد منتظرِ آن‌ها، بعد بسته‌ها. */
    public function test_the_platform_list_puts_waiting_tickets_first(): void
    {
        $closed = $this->repo->open($this->salonA, $this->userId, 'بسته', 'متن');
        $this->repo->close($closed);

        $answered = $this->repo->open($this->salonA, $this->userId, 'جواب‌داده', 'متن');
        $this->repo->reply($answered, null, TicketRepository::SIDE_PLATFORM, 'جواب');

        $this->repo->open($this->salonB, $this->userId, 'منتظر', 'متن');

        self::assertSame(
            ['منتظر', 'جواب‌داده', 'بسته'],
            array_column($this->repo->all(), 'subject')
        );
    }

    public function test_counts_are_grouped_by_status(): void
    {
        $this->repo->open($this->salonA, $this->userId, 'یک', 'متن');
        $two = $this->repo->open($this->salonA, $this->userId, 'دو', 'متن');
        $this->repo->reply($two, null, TicketRepository::SIDE_PLATFORM, 'جواب');

        $counts = $this->repo->counts();

        self::assertSame(1, $counts[TicketRepository::OPEN]);
        self::assertSame(1, $counts[TicketRepository::ANSWERED]);
        self::assertSame(0, $counts[TicketRepository::CLOSED]);
    }
}
