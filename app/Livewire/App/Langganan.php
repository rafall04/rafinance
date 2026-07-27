<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\QuotaGuard;
use App\Domain\Tenancy\TenantContext;
use Livewire\Component;

class Langganan extends Component
{
    public function render()
    {
        $workspace = app(TenantContext::class)->workspace();
        abort_if($workspace === null, 404);

        $langganan = Subscription::query()
            ->with(['plan', 'payments'])
            ->where('workspace_id', $workspace->getKey())
            ->first();

        return view('livewire.app.langganan', [
            'langganan' => $langganan,
            'pemakaian' => app(QuotaGuard::class)->ringkasan($workspace),
            'planTersedia' => Plan::query()->publik()->get(),
            'pembayaran' => $langganan?->payments->sortByDesc('created_at')->take(10) ?? collect(),
        ])->layout('components.layouts.app', ['title' => 'Langganan']);
    }
}
