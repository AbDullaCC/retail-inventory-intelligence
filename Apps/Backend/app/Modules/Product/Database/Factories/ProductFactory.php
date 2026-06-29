<?php

declare(strict_types=1);

namespace App\Modules\Product\Database\Factories;

use App\Modules\Category\Models\Category;
use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 1, 300);

        return [
            'category_id' => Category::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-???')),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'price' => round($cost * fake()->randomFloat(2, 1.2, 2.5), 2),
            'cost' => $cost,
            'quantity' => fake()->numberBetween(0, 200),
            'reorder_level' => fake()->numberBetween(5, 30),
            'is_active' => fake()->boolean(90),
        ];
    }
}
