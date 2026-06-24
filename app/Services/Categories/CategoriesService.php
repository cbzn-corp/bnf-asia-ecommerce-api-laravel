<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Models\Category;
use App\Services\Supabase\SupabaseService;
use App\Support\Cache\ApiCache;
use App\Support\Utils\Slug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoriesService
{
  public function __construct(
    private readonly SupabaseService $supabase,
  ) {}

  /**
   * @return list<string>
   */
  public function collectDescendantIds(string $categoryId): array
  {
    $rows = Category::query()->select(['id', 'parentId'])->get();
    $ids = [$categoryId];

    $walk = function (string $parentId) use (&$walk, $rows, &$ids): void {
      foreach ($rows as $row) {
        if ($row->parentId === $parentId) {
          $ids[] = $row->id;
          $walk($row->id);
        }
      }
    };

    $walk($categoryId);

    return $ids;
  }

  /**
   * @return list<string>
   */
  public function resolveCategoryIdsBySlug(string $slug): array
  {
    $category = Category::query()->where('slug', $slug)->first();
    if (! $category) {
      return [];
    }

    return $this->collectDescendantIds($category->id);
  }

  public function findAllPublic(): SupportCollection
  {
    $rows = ApiCache::remember(ApiCache::DOMAIN_CATALOG, 'categories:public', function () {
      return Category::query()
        ->withCount('products')
        ->orderBy('sortOrder')
        ->orderBy('name')
        ->get()
        ->map(fn (Category $category) => $this->formatCategory($category))
        ->values()
        ->all();
    });

    return collect($rows);
  }

  public function findTreePublic(): array
  {
    return ApiCache::remember(ApiCache::DOMAIN_CATALOG, 'categories:tree:public', function () {
      $rows = Category::query()
        ->withCount('products')
        ->orderBy('sortOrder')
        ->orderBy('name')
        ->get()
        ->map(fn (Category $category) => $this->formatCategory($category))
        ->all();

      return $this->aggregateProductCounts($this->buildTree($rows));
    });
  }

  public function findAll(?string $search = null): SupportCollection
  {
    $query = Category::query()->withCount('products');

    if ($search) {
      $query->where(function ($builder) use ($search) {
        $builder->where('name', 'ilike', "%{$search}%")
          ->orWhere('slug', 'ilike', "%{$search}%");
      });
    }

    return $query
      ->orderBy('sortOrder')
      ->orderBy('name')
      ->get()
      ->map(fn (Category $category) => $this->formatCategory($category));
  }

  public function findTree(?string $search = null): array
  {
    $rows = $this->findAll($search)->all();

    return $this->buildTree($rows);
  }

  public function findOne(string $id): array
  {
    $row = Category::query()->withCount('products')->find($id);
    if (! $row) {
      throw new NotFoundHttpException("Category not found: {$id}");
    }

    return $this->formatCategory($row);
  }

  /**
   * @param  array<string, mixed>  $dto
   */
  public function create(array $dto): array
  {
    $slug = trim((string) ($dto['slug'] ?? '')) ?: Slug::slugify((string) $dto['name']);

    if (Category::query()->where('slug', $slug)->exists()) {
      throw new BadRequestHttpException("Slug already exists: {$slug}");
    }

    $this->validateParent($dto['parentId'] ?? null);

    $category = Category::query()->create([
      'name' => $dto['name'],
      'slug' => $slug,
      'description' => $dto['description'] ?? null,
      'imageUrl' => $dto['imageUrl'] ?? null,
      'parentId' => $dto['parentId'] ?? null,
      'sortOrder' => $dto['sortOrder'] ?? 0,
    ]);

    $category->loadCount('products');

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return $this->formatCategory($category);
  }

  /**
   * @param  array<string, mixed>  $dto
   */
  public function update(string $id, array $dto): array
  {
    $this->findOne($id);

    if (! empty($dto['slug'])) {
      $conflict = Category::query()
        ->where('slug', $dto['slug'])
        ->where('id', '!=', $id)
        ->exists();

      if ($conflict) {
        throw new BadRequestHttpException("Slug already exists: {$dto['slug']}");
      }
    }

    if (array_key_exists('parentId', $dto)) {
      $this->validateParent($dto['parentId'], $id);
    }

    $category = Category::query()->findOrFail($id);
    $category->update(array_intersect_key($dto, array_flip([
      'name', 'slug', 'description', 'imageUrl', 'parentId', 'sortOrder',
    ])));
    $category->loadCount('products');

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return $this->formatCategory($category);
  }

  public function remove(string $id): array
  {
    $row = Category::query()->withCount('products')->findOrFail($id);

    if (Category::query()->where('parentId', $id)->exists()) {
      throw new BadRequestHttpException('Remove or reassign child categories first');
    }

    if (($row->products_count ?? 0) > 0) {
      throw new BadRequestHttpException('Reassign products before deleting this category');
    }

    $row->delete();

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return ['deleted' => true];
  }

  public function uploadCoverImage(string $id, ?UploadedFile $file): array
  {
    if (! $file) {
      throw new BadRequestHttpException('A cover image file is required');
    }

    $this->findOne($id);
    $imageUrl = $this->supabase->uploadCategoryCoverImage($id, $file);

    $category = Category::query()->findOrFail($id);
    $category->update(['imageUrl' => $imageUrl]);
    $category->loadCount('products');

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return $this->formatCategory($category);
  }

  /**
   * @param  list<array<string, mixed>>  $rows
   * @return list<array<string, mixed>>
   */
  private function buildTree(array $rows): array
  {
    $byId = [];
    foreach ($rows as $row) {
      $row['children'] = [];
      $byId[$row['id']] = $row;
    }

    $roots = [];
    foreach ($byId as $node) {
      $parentId = $node['parentId'] ?? null;
      if ($parentId && isset($byId[$parentId])) {
        $byId[$parentId]['children'][] = &$byId[$node['id']];
      } else {
        $roots[] = &$byId[$node['id']];
      }
    }
    unset($node);

    $sortNodes = function (array &$nodes) use (&$sortNodes): void {
      usort($nodes, function (array $a, array $b): int {
        return ($a['sortOrder'] <=> $b['sortOrder']) ?: strcmp($a['name'], $b['name']);
      });
      foreach ($nodes as &$node) {
        if (! empty($node['children'])) {
          $sortNodes($node['children']);
        }
      }
    };
    $sortNodes($roots);

    return $roots;
  }

  /**
   * @param  list<array<string, mixed>>  $nodes
   * @return list<array<string, mixed>>
   */
  private function aggregateProductCounts(array $nodes): array
  {
    return array_map(function (array $node): array {
      $children = $this->aggregateProductCounts($node['children'] ?? []);
      $childTotal = array_reduce(
        $children,
        fn (int $sum, array $child): int => $sum + (int) ($child['_count']['products'] ?? 0),
        0,
      );
      $direct = (int) ($node['_count']['products'] ?? 0);

      return [
        ...$node,
        'children' => $children,
        '_count' => ['products' => $direct + $childTotal],
      ];
    }, $nodes);
  }

  private function validateParent(?string $parentId, ?string $categoryId = null): void
  {
    if (! $parentId) {
      return;
    }

    if ($categoryId && $parentId === $categoryId) {
      throw new BadRequestHttpException('A category cannot be its own parent');
    }

    if (! Category::query()->where('id', $parentId)->exists()) {
      throw new BadRequestHttpException("Parent category not found: {$parentId}");
    }

    if ($categoryId) {
      $descendantIds = $this->collectDescendantIds($categoryId);
      if (in_array($parentId, $descendantIds, true)) {
        throw new BadRequestHttpException('A category cannot be nested under its own descendant');
      }
    }
  }

  /**
   * @return array<string, mixed>
   */
  private function formatCategory(Category $category): array
  {
    return [
      'id' => $category->id,
      'name' => $category->name,
      'slug' => $category->slug,
      'description' => $category->description,
      'imageUrl' => $category->imageUrl,
      'sortOrder' => $category->sortOrder,
      'parentId' => $category->parentId,
      'createdAt' => $category->createdAt,
      'updatedAt' => $category->updatedAt,
      '_count' => ['products' => (int) ($category->products_count ?? 0)],
    ];
  }
}
