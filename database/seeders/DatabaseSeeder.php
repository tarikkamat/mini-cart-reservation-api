<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Product::factory()
            ->count(40)
            ->has(ProductPrice::factory(), 'prices')
            ->has(Inventory::factory(), 'inventory')
            ->create();

        Product::factory()
            ->count(10)
            ->inactive()
            ->has(ProductPrice::factory(), 'prices')
            ->has(Inventory::factory()->empty(), 'inventory')
            ->create();
    }
}
