<form method="POST" action="{{ $decisionAction }}" class="mt-6 space-y-3 rounded-xl border bg-white p-5">
@csrf
<label class="block font-semibold" for="comment">Decision comment</label><textarea id="comment" name="comment" class="form-input w-full" required minlength="5" maxlength="2000" rows="3">{{ old('comment') }}</textarea>
<p class="text-xs text-slate-500">Approval applies the operation immediately. Rejection leaves the requested money or stock operation unapplied. Decisions are retained in the audit log.</p>
<div class="flex flex-wrap gap-3"><button name="decision" value="approved" class="rounded-lg bg-teal-700 px-4 py-2 font-semibold text-white" onclick="return confirm('Approve and apply this operation?')">Approve</button><button name="decision" value="rejected" class="rounded-lg border border-red-300 px-4 py-2 font-semibold text-red-700" onclick="return confirm('Reject this request?')">Reject</button></div>
</form>
