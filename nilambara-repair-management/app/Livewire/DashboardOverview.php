<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardOverview extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard-overview', [
            'statistics' => [
                ['label' => 'total_repair_jobs', 'icon' => 'tool', 'tone' => 'teal'],
                ['label' => 'pending_repairs', 'icon' => 'clock', 'tone' => 'amber'],
                ['label' => 'completed_repairs', 'icon' => 'check-circle', 'tone' => 'blue'],
                ['label' => 'todays_sales', 'icon' => 'wallet', 'tone' => 'teal'],
                ['label' => 'outstanding_credit', 'icon' => 'credit', 'tone' => 'violet'],
                ['label' => 'low_stock_items', 'icon' => 'box', 'tone' => 'amber'],
            ],
        ]);
    }
}
