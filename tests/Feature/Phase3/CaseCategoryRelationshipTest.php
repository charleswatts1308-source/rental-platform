<?php

use App\Models\RepairCase;
use App\Models\RepairCategory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores the category key on a case via the factory', function () {
    $case = RepairCase::factory()->create();

    expect($case->category_key)->toBeString();
    expect(RepairCategory::where('key', $case->category_key)->exists())->toBeTrue();
});

it('belongs to a repair category via the category() relationship', function () {
    $category = RepairCategory::factory()->create(['key' => 'damp_mould']);
    $case = RepairCase::factory()->create(['category_key' => $category->key]);

    expect($case->category)->toBeInstanceOf(RepairCategory::class);
    expect($case->category->key)->toBe('damp_mould');
});

it('exposes a cases hasMany relationship on RepairCategory', function () {
    $category = RepairCategory::factory()->create(['key' => 'heating']);
    RepairCase::factory()->count(2)->create(['category_key' => $category->key]);

    expect($category->cases)->toHaveCount(2);
    expect($category->cases->first())->toBeInstanceOf(RepairCase::class);
});

it('rejects an unknown category_key at the database level', function () {
    RepairCase::factory()->create(['category_key' => 'this_key_does_not_exist']);
})->throws(QueryException::class);

it('rejects deletion of a category that still has cases', function () {
    $category = RepairCategory::factory()->create(['key' => 'plumbing']);
    RepairCase::factory()->create(['category_key' => $category->key]);

    $category->delete();
})->throws(QueryException::class);
