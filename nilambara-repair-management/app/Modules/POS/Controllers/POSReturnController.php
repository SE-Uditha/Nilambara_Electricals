<?php

namespace App\Modules\POS\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\POS\Requests\POSReturnRequest;
use App\Modules\POS\Services\POSReturnService;
use Illuminate\Http\RedirectResponse;

class POSReturnController extends Controller
{
    public function store(POSReturnRequest $request, POSReturnService $returns): RedirectResponse
    {
        $return = $returns->record($request->validated(), $request->user());

        return redirect()->route('pos.sales.show', $return->pos_sale_id)
            ->with('status', __('pos.return_recorded', ['return' => $return->return_number]));
    }
}
