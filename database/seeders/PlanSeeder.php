<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Billing\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Plan bawaan. Semuanya Rp 0 selama beta — yang berbeda hanya batasnya.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $daftar = [
            [
                'code' => 'free',
                'name' => 'Gratis',
                'sort_order' => 0,
                'limits' => [
                    'workspaces' => 1,
                    'members' => 1,
                    'transactions_per_month' => 500,
                    'attachments_mb' => 0,
                    'retention_months' => 12,
                    'ocr' => false,
                    'llm_parser' => false,
                ],
            ],
            [
                'code' => 'usaha',
                'name' => 'Usaha',
                'sort_order' => 1,
                'limits' => [
                    'workspaces' => 3,
                    'members' => 5,
                    'transactions_per_month' => 5_000,
                    'attachments_mb' => 500,
                    'retention_months' => 60,
                    'ocr' => true,
                    'llm_parser' => false,
                ],
            ],
            [
                'code' => 'tim',
                'name' => 'Tim',
                'sort_order' => 2,
                'limits' => [
                    'workspaces' => Plan::TANPA_BATAS,
                    'members' => 25,
                    'transactions_per_month' => Plan::TANPA_BATAS,
                    'attachments_mb' => 5_000,
                    'retention_months' => Plan::TANPA_BATAS,
                    'ocr' => true,
                    'llm_parser' => false,
                ],
            ],
        ];

        foreach ($daftar as $plan) {
            Plan::query()->updateOrCreate(
                ['code' => $plan['code']],
                [
                    'name' => $plan['name'],
                    // Nol untuk semuanya. Masa beta.
                    'price_minor' => 0,
                    'currency' => 'IDR',
                    'interval' => 'monthly',
                    'is_public' => true,
                    'sort_order' => $plan['sort_order'],
                    'limits' => $plan['limits'],
                ],
            );
        }
    }
}
