<?php

namespace App\Modules\Settings\Controllers;

use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SystemSettingController
{
    public function index(): View
    {
        return view('modules.settings.index');
    }

    public function update(Request $request, SystemSettingService $settings): RedirectResponse
    {
        $values = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'currency' => ['required', 'alpha:ascii', 'size:3'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'date_format' => ['required', Rule::in(['d M Y', 'Y-m-d', 'd/m/Y', 'm/d/Y'])],
            'time_format' => ['required', Rule::in(['h:i A', 'H:i'])],
            'logo' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(2048)],
        ]);

        $logo = $request->file('logo');
        unset($values['logo']);
        $storedLogo = null;
        if ($logo !== null) {
            $path = $logo->storePublicly('business', 'public');
            if ($path === false) {
                throw new \RuntimeException('The business logo could not be stored.');
            }
            $storedLogo = $path;
            $values['logo'] = 'storage/'.$storedLogo;
        }

        try {
            $settings->update($values, $request->user());
        } catch (\Throwable $exception) {
            if ($storedLogo !== null) {
                Storage::disk('public')->delete($storedLogo);
            }
            throw $exception;
        }
        $settings->apply();

        return back()->with('status', __('settings.saved'));
    }
}
