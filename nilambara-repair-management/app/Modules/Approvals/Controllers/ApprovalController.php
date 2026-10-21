<?php

namespace App\Modules\Approvals\Controllers;

use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Approvals\Requests\SubmitApprovalRequest;
use App\Modules\Approvals\Services\ApprovalAccess;
use App\Modules\Approvals\Services\ApprovalService;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Payments\Models\Payment;
use App\Modules\Purchases\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApprovalController
{
    public function index(Request $request)
    {
        $kinds = ApprovalAccess::reviewKinds($request->user());
        abort_if($kinds === [], 403);
        $filters = $request->validate(['status' => 'nullable|in:pending,approved,rejected', 'kind' => ['nullable', Rule::in(array_keys(ApprovalAccess::LABELS))]]);
        $status = $filters['status'] ?? 'pending';
        $kind = $filters['kind'] ?? null;
        $query = ApprovalRequest::with(['requester', 'reviewer'])->whereIn('kind', $kinds)->where('status', $status)->when($kind, fn ($q) => $q->where('kind', $kind));

        return view('modules.approvals.index', [
            'requests' => $query->latest('id')->paginate(20)->withQueryString(), 'status' => $status, 'kind' => $kind,
            'kinds' => array_intersect_key(ApprovalAccess::LABELS, array_flip($kinds)),
            'purchases' => $status === 'pending' && (! $kind || $kind === 'purchase') && in_array('purchase', $kinds, true) ? Purchase::with(['supplier', 'createdByUser'])->where('status', 'draft')->oldest('id')->paginate(10, ['*'], 'purchases_page')->withQueryString() : null,
        ]);
    }

    public function mine(Request $request)
    {
        abort_if(ApprovalAccess::requestKinds($request->user()) === [], 403);

        return view('modules.approvals.mine', ['requests' => ApprovalRequest::with('requester')->where('requested_by_user_id', $request->user()->id)->latest('id')->paginate(20)]);
    }

    public function create(Request $request)
    {
        $kinds = ApprovalAccess::requestKinds($request->user());
        abort_if($kinds === [], 403);
        $data = $request->validate(['kind' => ['nullable', Rule::in(array_keys($kinds))], 'subject_id' => 'nullable|integer|min:1']);

        $subjectId = $data['subject_id'] ?? null;
        $selectedKind = $data['kind'] ?? array_key_first($kinds);
        $choices = match ($selectedKind) {
            'payment_refund', 'payment_reversal' => Payment::query()->latest('id')->limit(200)->get()->mapWithKeys(fn ($payment) => [$payment->id => $payment->payment_number.' - LKR '.$payment->amount]),
            'stock_adjustment', 'stock_count' => SparePart::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($part) => [$part->id => $part->sku.' - '.$part->name]),
            default => collect(),
        };

        if ($subjectId && in_array($selectedKind, ['payment_refund', 'payment_reversal'], true)) {
            $payment = Payment::findOrFail($subjectId);
            $choices->put($payment->id, $payment->payment_number.' - LKR '.$payment->amount);
        }

        return view('modules.approvals.create', ['choices' => $choices, 'kinds' => array_intersect_key(ApprovalAccess::LABELS, $kinds), 'kind' => $data['kind'] ?? array_key_first($kinds), 'subjectId' => $data['subject_id'] ?? '', 'locations' => isset($kinds['stock_adjustment']) || isset($kinds['stock_count']) ? StockLocation::where('is_active', true)->orderBy('name')->get() : collect()]);
    }

    public function store(SubmitApprovalRequest $request, ApprovalService $service)
    {
        $approval = $service->submit($request->validated(), $request->user());

        return redirect()->route('approval-requests.show', $approval)->with('status', 'Request submitted. No money or stock has been changed.');
    }

    public function show(Request $request, ApprovalRequest $approval)
    {
        abort_unless((int) $approval->requested_by_user_id === (int) $request->user()->id || ApprovalAccess::canReview($request->user(), $approval->kind), 403);

        return view('modules.approvals.show', ['approval' => $approval->load(['requester', 'reviewer']), 'canDecide' => ApprovalAccess::canReview($request->user(), $approval->kind) && (int) $approval->requested_by_user_id !== (int) $request->user()->id]);
    }

    public function decide(Request $request, ApprovalRequest $approval, ApprovalService $service)
    {
        $data = $this->decision($request);
        $service->decide($approval, $data['decision'], $data['comment'], $request->user());

        return back()->with('status', 'Decision recorded. Approved operations have been applied.');
    }

    public function document(Request $request, string $kind, int $id)
    {
        abort_unless(ApprovalAccess::canReview($request->user(), $kind), 403);
        if ($existing = ApprovalRequest::where('source_key', $kind.':'.$id)->first()) {
            return redirect()->route('approval-requests.show', $existing);
        }
        $document = Purchase::with(['supplier', 'createdByUser', 'items'])->findOrFail($id);
        abort_unless($document->status->value === 'draft', 404);

        return view('modules.approvals.document', compact('kind', 'document'));
    }

    public function decideDocument(Request $request, string $kind, int $id, ApprovalService $service)
    {
        $data = $this->decision($request);
        $approval = $service->decideDocument($kind, $id, $data['decision'], $data['comment'], $request->user());

        return redirect()->route('approval-requests.show', $approval)->with('status', 'Decision recorded.');
    }

    private function decision(Request $request): array
    {
        return $request->validate(['decision' => 'required|in:approved,rejected', 'comment' => 'required|string|min:5|max:2000']);
    }
}
