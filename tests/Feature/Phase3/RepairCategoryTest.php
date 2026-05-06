<?php

use App\Models\RepairCategory;
use Database\Seeders\RepairCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a valid repair category via factory', function () {
    $category = RepairCategory::factory()->create();

    expect($category->id)->toBeInt();
    expect($category->key)->toBeString();
    expect($category->label)->toBeString();
    expect($category->active)->toBeTrue();
    expect($category->requires_description)->toBeFalse();
});

it('casts active and requires_description to booleans', function () {
    $category = RepairCategory::factory()->create([
        'active' => 0,
        'requires_description' => 1,
    ]);

    expect($category->active)->toBeFalse();
    expect($category->requires_description)->toBeTrue();
});

it('casts sort_order to integer', function () {
    $category = RepairCategory::factory()->create(['sort_order' => 25]);

    expect($category->sort_order)->toBe(25)->toBeInt();
});

it('produces an inactive category via the inactive state', function () {
    $category = RepairCategory::factory()->inactive()->create();

    expect($category->active)->toBeFalse();
});

it('enforces unique key at the database level', function () {
    RepairCategory::factory()->create(['key' => 'duplicate']);
    RepairCategory::factory()->create(['key' => 'duplicate']);
})->throws(QueryException::class);

it('seeds exactly the 11 starter categories from the design doc', function () {
    $this->seed(RepairCategorySeeder::class);

    expect(RepairCategory::count())->toBe(11);

    $expected = [
        'damp_mould', 'heating', 'electrical', 'plumbing', 'structural',
        'windows_doors', 'pest_infestation', 'appliances', 'safety', 'external', 'other',
    ];
    expect(RepairCategory::pluck('key')->all())->toEqualCanonicalizing($expected);
});

it('seeds the other category with requires_description=true', function () {
    $this->seed(RepairCategorySeeder::class);

    $other = RepairCategory::where('key', 'other')->sole();
    expect($other->requires_description)->toBeTrue();
});

it('seeds categories in sort_order multiples of 10 with other last', function () {
    $this->seed(RepairCategorySeeder::class);

    $byOrder = RepairCategory::orderBy('sort_order')->pluck('key', 'sort_order')->all();

    expect(array_keys($byOrder))->toBe([10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110]);
    expect($byOrder[10])->toBe('damp_mould');
    expect($byOrder[110])->toBe('other');
});

it('is idempotent: running seeder twice does not duplicate rows', function () {
    $this->seed(RepairCategorySeeder::class);
    $this->seed(RepairCategorySeeder::class);

    expect(RepairCategory::count())->toBe(11);
});
