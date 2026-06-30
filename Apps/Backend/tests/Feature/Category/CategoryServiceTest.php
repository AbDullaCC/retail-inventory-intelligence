<?php

declare(strict_types=1);

namespace Tests\Feature\Category;

use App\Modules\Category\DTOs\CategoryData;
use App\Modules\Category\Models\Category;
use App\Modules\Category\Services\Contracts\CategoryServiceInterface;
use App\Modules\Product\Models\Product;
use App\Modules\Shared\Exceptions\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CategoryServiceInterface::class);
    }

    public function test_it_creates_a_category(): void
    {
        $dto = $this->service->create(new CategoryData('Beverages', 'Cold drinks'));

        $this->assertSame('Beverages', $dto->name);
        $this->assertSame(0, $dto->productsCount);
        $this->assertDatabaseHas('categories', ['name' => 'Beverages']);
    }

    public function test_it_updates_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old']);

        $dto = $this->service->update($category->id, new CategoryData('New', null));

        $this->assertSame('New', $dto->name);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New']);
    }

    public function test_it_lists_categories_alphabetically_with_product_counts(): void
    {
        $a = Category::factory()->create(['name' => 'Apparel']);
        Category::factory()->create(['name' => 'Books']);
        Product::factory()->count(2)->create(['category_id' => $a->id]);

        $list = $this->service->list();

        $this->assertCount(2, $list);
        $this->assertSame('Apparel', $list[0]->name);
        $this->assertSame(2, $list[0]->productsCount);
        $this->assertSame(0, $list[1]->productsCount);
    }

    public function test_it_deletes_an_empty_category(): void
    {
        $category = Category::factory()->create();

        $this->service->delete($category->id);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_it_refuses_to_delete_a_category_that_has_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        try {
            $this->service->delete($category->id);
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $e) {
            $this->assertSame(409, $e->status());
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
