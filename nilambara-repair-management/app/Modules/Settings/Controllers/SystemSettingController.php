<?php

namespace App\Modules\Settings\Controllers;

use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

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
            'default_language' => ['sometimes', Rule::in(['en', 'si'])],
            'document_footer' => ['nullable', 'string', 'max:2000'],
            'print_paper' => ['sometimes', Rule::in(['A4', 'A5'])],
            'logo' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png'])->max(2048)],
            'common_faults' => ['nullable', 'string', 'max:10000'],
        ]);
        if (array_key_exists('common_faults', $values)) {
            $values['common_faults'] = $this->normalizeFaults($values['common_faults']);
        }

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

    /** One fault per line: trimmed, de-duplicated (case-insensitive), at most 100 entries of 150 characters. */
    private function normalizeFaults(?string $raw): ?string
    {
        $faults = [];
        foreach (preg_split('/\R/u', (string) $raw) ?: [] as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line === '') {
                continue;
            }
            if (mb_strlen($line) > 150) {
                throw ValidationException::withMessages(['common_faults' => __('settings.common_faults_too_long')]);
            }
            $faults[mb_strtolower($line)] ??= $line;
        }
        if (count($faults) > 100) {
            throw ValidationException::withMessages(['common_faults' => __('settings.common_faults_too_many')]);
        }

        return $faults === [] ? null : implode("\n", $faults);
    }
}
