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
        // Idempotent: skip if products already exist so a container restart
        // with RUN_DB_SEED=true does not pile on duplicate data.
        if (Product::query()->exists()) {
            return;
        }

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
