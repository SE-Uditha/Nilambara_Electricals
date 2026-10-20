<?php

namespace App\Modules\PrintTemplates\Controllers;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrintTemplateController
{
    public function index(): View
    {
        $templates = PrintTemplate::query()
            ->orderBy('document_type')
            ->orderByDesc('version')
            ->paginate(50);

        return view('modules.print-templates.index', compact('templates'));
    }

    public function preview(PrintTemplate $template): View
    {
        return view('modules.print-templates.preview', compact('template'));
    }

    public function update(Request $request, PrintTemplate $template): RedirectResponse
    {
        foreach (['table_widths', 'signature_areas'] as $field) {
            if (is_string($request->input($field))) {
                $decoded = json_decode($request->input($field), true);
                if (! is_array($decoded)) {
                    throw ValidationException::withMessages([$field => 'Enter a valid JSON array.']);
                }
                $request->merge([$field => $decoded]);
            }
        }
        $values = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:2000'],
            'telephone_number' => ['nullable', 'string', 'max:40'],
            'document_title' => ['nullable', 'string', 'max:255'],
            'paper_size' => ['required', Rule::in($this->paperSizes($template))],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'thermal_width_mm' => ['nullable', 'numeric', 'between:40,120'],
            'font_size_pt' => ['required', 'numeric', 'between:7,16'],
            'margin_top_mm' => ['required', 'numeric', 'between:0,30'],
            'margin_right_mm' => ['required', 'numeric', 'between:0,30'],
            'margin_bottom_mm' => ['required', 'numeric', 'between:0,30'],
            'margin_left_mm' => ['required', 'numeric', 'between:0,30'],
            'footer' => ['nullable', 'string', 'max:2000'],
            'duplicate_copy_label' => ['required', 'string', 'max:100'],
            'table_widths' => ['sometimes', 'array', 'list', 'max:12'],
            'table_widths.*' => ['numeric', 'between:1,100'],
            'signature_areas' => ['sometimes', 'array', 'list', 'max:6'],
            'signature_areas.*' => ['string', 'max:80'],
            'logo_path' => ['nullable', 'regex:~\A(?:storage/business/|images/)[a-zA-Z0-9_\-/]+\.(?:png|jpg|jpeg)\z~', 'max:255'],
        ]);
        if (isset($values['table_widths']) && array_sum($values['table_widths']) > 100) {
            throw ValidationException::withMessages(['table_widths' => 'Column percentages must total at most 100.']);
        }

        $version = $this->createVersion($template, $values, $request->user(), 'print_template.updated');

        return redirect()->route('print-templates.index')->with('status', __('print_templates.saved', ['version' => $version->version]));
    }

    public function reset(PrintTemplate $template, Request $request): RedirectResponse
    {
        $original = PrintTemplate::query()->where('code', $template->code)->orderBy('version')->firstOrFail();
        $values = $original->only([
            'name', 'business_name', 'business_address', 'telephone_number', 'logo_path',
            'document_title', 'paper_size', 'orientation', 'thermal_width_mm', 'font_size_pt',
            'margin_top_mm', 'margin_right_mm', 'margin_bottom_mm', 'margin_left_mm',
            'table_widths', 'signature_areas', 'footer', 'duplicate_copy_label',
        ]);
        $version = $this->createVersion($template, $values, $request->user(), 'print_template.reset');

        return redirect()->route('print-templates.index')->with('status', __('print_templates.reset_done', ['version' => $version->version]));
    }

    private function createVersion(PrintTemplate $template, array $values, User $actor, string $action): PrintTemplate
    {
        return DB::transaction(function () use ($template, $values, $actor, $action): PrintTemplate {
            $versions = PrintTemplate::query()->where('code', $template->code)->lockForUpdate()->get();
            $versionNumber = (int) $versions->max('version') + 1;
            foreach ($versions->where('is_active', true) as $activeVersion) {
                $activeVersion->is_active = false;
                $activeVersion->save();
            }

            $next = $template->replicate();
            $next->fill($values);
            $next->version = $versionNumber;
            $next->is_active = true;
            $next->save();

            AuditLog::query()->create([
                'user_id' => $actor->getKey(),
                'action' => $action,
                'entity_type' => PrintTemplate::class,
                'entity_id' => $next->getKey(),
                'occurred_at' => now(),
                'old_values' => ['template_code' => $template->code, 'version' => $template->version],
                'new_values' => ['template_code' => $next->code, 'version' => $next->version],
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'request_id' => app()->runningInConsole() ? null : request()->header('X-Request-Id'),
            ]);

            return $next;
        });
    }

    /**
     * @return list<string>
     */
    private function paperSizes(PrintTemplate $template): array
    {
        return match ($template->document_type) {
            'job_card' => ['A6'],
            'invoice' => ['A5'],
            'quotation' => ['A4', 'A5'],
            'pos_receipt' => ['A4', 'A5', 'thermal'],
            'repair_receipt' => ['A4', 'A5', 'A6'],
            default => ['A4', 'A5', 'A6'],
        };
    }
}
