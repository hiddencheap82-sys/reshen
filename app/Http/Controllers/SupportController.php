<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Support\TicketRepository;

/**
 * پشتیبانی از دید سالن.
 *
 * تا حالا اگر چیزی خراب می‌شد، صاحب سالن هیچ راهی نداشت جز زنگ زدن.
 * حالا از همان پنلی که هر روز باز می‌کند می‌پرسد، و جوابش همان‌جا
 * می‌ماند — نه در یک پیام تلگرام که هفتهٔ بعد پیدا نمی‌شود.
 *
 * تنانسی: هر کوئری `salon_id` جاری را می‌گیرد. تیکت سالن دیگر، حتی
 * با شناسهٔ درست در URL، «پیدا نشد» می‌گیرد — نه «اجازه ندارید»، که
 * خودش وجود تیکت را لو می‌دهد.
 */
final class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $repo = new TicketRepository();

        return $this->page('layouts.panel', 'panel.support.index', [
            'title' => 'پشتیبانی',
            'tickets' => $repo->forSalon(Auth::salonId()),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->page('layouts.panel', 'panel.support.create', [
            'title' => 'سؤال تازه',
        ]);
    }

    public function store(Request $request): Response
    {
        $subject = trim((string) $request->input('subject', ''));
        $body = trim((string) $request->input('body', ''));

        if ($subject === '' || $body === '') {
            return $this->withError('هم عنوان و هم متن لازم است.', '/panel/support/new');
        }

        $id = (new TicketRepository())->open(Auth::salonId(), Auth::id(), $subject, $body);

        return $this->withSuccess(
            'سؤالتان ثبت شد. جواب را همین‌جا می‌بینید.',
            '/panel/support/' . $id
        );
    }

    public function show(Request $request): Response
    {
        $repo = new TicketRepository();
        $ticket = $repo->find((int) $request->param('id'), Auth::salonId());

        if ($ticket === null) {
            return $this->withError('این سؤال پیدا نشد.', '/panel/support');
        }

        return $this->page('layouts.panel', 'panel.support.show', [
            'title' => $ticket['subject'],
            'ticket' => $ticket,
            'messages' => $repo->messages((int) $ticket['id']),
        ]);
    }

    public function reply(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new TicketRepository();
        $ticket = $repo->find($id, Auth::salonId());

        if ($ticket === null) {
            return $this->withError('این سؤال پیدا نشد.', '/panel/support');
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return $this->withError('متن پیام خالی است.', '/panel/support/' . $id);
        }

        $repo->reply($id, Auth::id(), TicketRepository::SIDE_SALON, $body);

        return $this->withSuccess('پیامتان ثبت شد.', '/panel/support/' . $id);
    }
}
