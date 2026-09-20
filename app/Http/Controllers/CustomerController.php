<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Customer\CustomerPreferencesRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Staff\StaffRepository;
use App\Support\IranMobile;

/** B01/B02/B03/B05/B08 — customer records and the barber's notebook. */
final class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $salonId = Auth::salonId();
        $term = trim((string) $request->query('q', ''));
        $repo = new CustomerRepository();
        $customers = $term !== '' ? $repo->search($salonId, $term) : $repo->recent($salonId, 30);

        return $this->page('layouts.panel', 'panel.customers.index', [
            'title' => 'مشتریان',
            'customers' => $customers,
            'q' => $term,
        ]);
    }

    public function show(Request $request): Response
    {
        $salonId = Auth::salonId();
        $id = (int) $request->param('id');
        $repo = new CustomerRepository();
        $customer = $repo->find($salonId, $id);
        if ($customer === null) {
            return $this->withError('مشتری یافت نشد.', '/panel/customers');
        }

        $preferences = (new CustomerPreferencesRepository())->find($salonId, $id);
        $history = $repo->history($salonId, $id);
        $staff = (new StaffRepository())->all($salonId, true);

        return $this->page('layouts.panel', 'panel.customers.show', [
            'title' => $customer['name'] ?: 'مشتری',
            'customer' => $customer,
            'preferences' => $preferences,
            'history' => $history,
            'staff' => $staff,
        ]);
    }

    public function update(Request $request): Response
    {
        $salonId = Auth::salonId();
        $id = (int) $request->param('id');

        $phoneRaw = trim((string) $request->input('phone', ''));
        $phone = $phoneRaw !== '' ? IranMobile::tryParse($phoneRaw) : null;

        (new CustomerRepository())->find($salonId, $id); // 404 guard implicit via update no-op if missing
        \App\Core\DB::update('customers', [
            'name' => trim((string) $request->input('name', '')) ?: null,
            'phone' => $phone?->e164,
            'preferred_staff_id' => $request->input('preferred_staff_id') !== '' ? (int) $request->input('preferred_staff_id') : null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ], 'salon_id = :salon_id AND id = :id', ['salon_id' => $salonId, 'id' => $id]);

        (new CustomerPreferencesRepository())->upsert($salonId, $id, [
            'clipper_size' => trim((string) $request->input('clipper_size', '')) ?: null,
            'hair_shape' => trim((string) $request->input('hair_shape', '')) ?: null,
            'beard_notes' => trim((string) $request->input('beard_notes', '')) ?: null,
            'skin_sensitivity' => trim((string) $request->input('skin_sensitivity', '')) ?: null,
            'last_barber_said' => trim((string) $request->input('last_barber_said', '')) ?: null,
        ]);

        return $this->withSuccess('پروندهٔ مشتری ذخیره شد.', '/panel/customers/' . $id);
    }
}
