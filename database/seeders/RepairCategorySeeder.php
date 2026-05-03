<?php

namespace Database\Seeders;

use App\Models\RepairCategory;
use Illuminate\Database\Seeder;

class RepairCategorySeeder extends Seeder
{
    /**
     * Seed the 11 starter repair categories per the design doc.
     * Idempotent: existing keys are updated, new keys are inserted.
     */
    public function run(): void
    {
        $categories = [
            ['key' => 'damp_mould',       'label' => 'Damp and mould',                                'sort_order' => 10,  'requires_description' => false],
            ['key' => 'heating',          'label' => 'Heating and hot water',                         'sort_order' => 20,  'requires_description' => false],
            ['key' => 'electrical',       'label' => 'Electrical',                                    'sort_order' => 30,  'requires_description' => false],
            ['key' => 'plumbing',         'label' => 'Plumbing and drainage',                         'sort_order' => 40,  'requires_description' => false],
            ['key' => 'structural',       'label' => 'Structural (walls, ceilings, floors, roof)',    'sort_order' => 50,  'requires_description' => false],
            ['key' => 'windows_doors',    'label' => 'Windows and doors',                             'sort_order' => 60,  'requires_description' => false],
            ['key' => 'pest_infestation', 'label' => 'Pest infestation',                              'sort_order' => 70,  'requires_description' => false],
            ['key' => 'appliances',       'label' => 'Landlord-supplied appliances',                  'sort_order' => 80,  'requires_description' => false],
            ['key' => 'safety',           'label' => 'Safety (alarms, gas, security)',                'sort_order' => 90,  'requires_description' => false],
            ['key' => 'external',         'label' => 'External (gutters, drainage, garden)',          'sort_order' => 100, 'requires_description' => false],
            ['key' => 'other',            'label' => 'Other',                                         'sort_order' => 110, 'requires_description' => true],
        ];

        foreach ($categories as $category) {
            RepairCategory::updateOrCreate(
                ['key' => $category['key']],
                $category + ['active' => true],
            );
        }
    }
}
