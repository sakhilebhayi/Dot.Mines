<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Perfect for small operations getting started with fleet management',
                'price' => 1782.00,
                'yearly_price' => 17982.00, // ~17% discount
                'paystack_plan_code' => env('PAYSTACK_BASIC_MONTHLY_PLAN_CODE'),
                'paystack_yearly_plan_code' => env('PAYSTACK_BASIC_YEARLY_PLAN_CODE'),
                'features' => [
                    'Real-time fleet tracking',
                    'Basic geofencing',
                    'Standard reporting',
                    'Email notifications',
                    'Mobile app access',
                ],
                'max_machines' => 10,
                'max_users' => 5,
                'max_geofences' => 20,
                'max_mine_areas' => 3,
                'has_advanced_analytics' => false,
                'has_api_access' => false,
                'has_priority_support' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Advanced features for growing mining operations',
                'price' => 4482.00,
                'yearly_price' => 44982.00, // ~17% discount
                'paystack_plan_code' => env('PAYSTACK_PRO_MONTHLY_PLAN_CODE'),
                'paystack_yearly_plan_code' => env('PAYSTACK_PRO_YEARLY_PLAN_CODE'),
                'features' => [
                    'Everything in Basic',
                    'Advanced analytics',
                    'Route optimization',
                    'Fuel management',
                    'Maintenance tracking',
                    'Custom geofences',
                    'Priority support',
                ],
                'max_machines' => 50,
                'max_users' => 20,
                'max_geofences' => 100,
                'max_mine_areas' => 10,
                'has_advanced_analytics' => true,
                'has_api_access' => false,
                'has_priority_support' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Complete solution for large-scale mining operations',
                'price' => 8982.00,
                'yearly_price' => 89982.00, // ~17% discount
                'paystack_plan_code' => env('PAYSTACK_ENTERPRISE_MONTHLY_PLAN_CODE'),
                'paystack_yearly_plan_code' => env('PAYSTACK_ENTERPRISE_YEARLY_PLAN_CODE'),
                'features' => [
                    'Everything in Professional',
                    'Unlimited machines',
                    'Unlimited users',
                    'Unlimited geofences',
                    'API access',
                    'Custom integrations',
                    'Dedicated support',
                    'White-label options',
                    'SLA guarantee',
                ],
                'max_machines' => 999,
                'max_users' => 999,
                'max_geofences' => 999,
                'max_mine_areas' => 999,
                'has_advanced_analytics' => true,
                'has_api_access' => true,
                'has_priority_support' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
