<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Staff\StaffRepository;
use App\Support\Money;

final class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $repo = new ServiceRepository();
        $services = $repo->all(Auth::salonId());

        return $this->page('layouts.panel', 'panel.services.index', [
            'title' => 'خدمات',
            'services' => $services,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->page('layouts.panel', 'panel.services.form', ['title' => 'خدمت جدید', 'service' => null]);
    }

    public function store(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            return $this->withError('نام خدمت را وارد کنید.', '/panel/services/create');
        }

        $repo = new ServiceRepository();
        $repo->create(Auth::salonId(), [
            'name' => $name,
            'duration_minutes' => max(5, (int) $request->input('duration_minutes', 30)),
            'price' => Money::fromToman(max(0, (int) $request->input('price_toman', 0)))->rials,
        ]);

        return $this->withSuccess('خدمت اضافه شد.', '/panel/services');
    }

    public function edit(Request $request): Response
    {
        $repo = new ServiceRepository();
        $service = $repo->find(Auth::salonId(), (int) $request->param('id'));
        if ($service === null) {
            return $this->withError('یافت نشد.', '/panel/services');
        }

        $staffRepo = new StaffRepository();

        return $this->page('layouts.panel', 'panel.services.form', [
            'title' => 'ویرایش خدمت',
            'service' => $service,
            'staff' => $staffRepo->all(Auth::salonId(), true),
            'overrides' => $repo->overridesFor(Auth::salonId(), (int) $service['id']),
        ]);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new ServiceRepository();
        $repo->update(Auth::salonId(), $id, [
            'name' => trim((string) $request->input('name', '')),
            'duration_minutes' => max(5, (int) $request->input('duration_minutes', 30)),
            'price' => Money::fromToman(max(0, (int) $request->input('price_toman', 0)))->rials,
        ]);

        return $this->withSuccess('تغییرات ذخیره شد.', '/panel/services/' . $id . '/edit');
    }

    public function setOverride(Request $request): Response
    {
        $serviceId = (int) $request->param('id');
        $staffId = (int) $request->input('staff_id');
        $durationRaw = $request->input('duration_minutes');
        $priceRaw = $request->input('price_toman');

        $duration = $durationRaw !== '' && $durationRaw !== null ? (int) $durationRaw : null;
        $price = $priceRaw !== '' && $priceRaw !== null ? Money::fromToman((int) $priceRaw)->rials : null;

        (new ServiceRepository())->setOverride(Auth::salonId(), $staffId, $serviceId, $duration, $price);

        return $this->withSuccess('اختصاصی‌سازی ذخیره شد.', '/panel/services/' . $serviceId . '/edit');
    }

    public function toggle(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new ServiceRepository();
        $service = $repo->find(Auth::salonId(), $id);
        if ($service !== null) {
            $repo->setActive(Auth::salonId(), $id, !((bool) $service['is_active']));
        }

        return $this->redirect('/panel/services');
    }
}
