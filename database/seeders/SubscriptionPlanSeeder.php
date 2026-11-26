<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price_monthly' => 14999.00,
                'currency' => 'PKR',
                'features' => ['1 location', 'Up to 10 staff', 'Basic support'],
                'is_active' => true,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'price_monthly' => 34999.00,
                'currency' => 'PKR',
                'features' => ['Up to 5 locations', 'Unlimited staff', 'Priority support'],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price_monthly' => 99999.00,
                'currency' => 'PKR',
                'features' => ['Unlimited locations', 'Dedicated success manager', 'SLA'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
