<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private const ALL_PERMISSION_KEYS = [
        'dashboard.view',
        'analytics.view',
        'customers.view',
        'orders.manage',
        'products.manage',
        'categories.manage',
        'collections.manage',
        'bundles.manage',
        'homepage.manage',
        'reviews.manage',
        'promotions.manage',
        'referrals.manage',
        'abandoned_carts.manage',
        'users.manage',
        'roles.manage',
        'settings.shipping',
        'settings.payments',
        'settings.email_templates',
        'logs.payments',
        'logs.audit',
    ];

    private const STORE_MANAGER_PERMISSIONS = [
        'dashboard.view',
        'analytics.view',
        'orders.manage',
        'products.manage',
        'categories.manage',
        'collections.manage',
        'bundles.manage',
        'homepage.manage',
        'reviews.manage',
        'abandoned_carts.manage',
    ];

    private const CATEGORY_TREE = [
        [
            'id' => 'cat_bedroom',
            'name' => 'Bedroom',
            'slug' => 'bedroom',
            'sortOrder' => 1,
            'children' => [
                ['name' => 'Wardrobe Cabinets', 'slug' => 'wardrobe-cabinets', 'sortOrder' => 1],
                ['name' => 'Bed Frames and Mattresses', 'slug' => 'bed-frames-mattresses', 'sortOrder' => 2],
            ],
        ],
        [
            'id' => 'cat_living_room',
            'name' => 'Living Room',
            'slug' => 'living-room',
            'sortOrder' => 2,
            'children' => [
                ['name' => 'Center and Side Tables', 'slug' => 'center-side-tables', 'sortOrder' => 1],
                [
                    'name' => 'Sofas',
                    'slug' => 'sofas',
                    'sortOrder' => 2,
                    'children' => [
                        ['name' => 'Sofa Beds', 'slug' => 'sofa-beds', 'sortOrder' => 1],
                        ['name' => 'Sectional Sofa', 'slug' => 'sectional-sofa', 'sortOrder' => 2],
                        ['name' => 'Compressed Sofa', 'slug' => 'compressed-sofa', 'sortOrder' => 3],
                    ],
                ],
                ['name' => 'Multipurpose TV Racks', 'slug' => 'multipurpose-tv-racks', 'sortOrder' => 3],
            ],
        ],
        [
            'id' => 'cat_dining_room',
            'name' => 'Dining Room',
            'slug' => 'dining-room',
            'sortOrder' => 3,
            'children' => [
                ['name' => 'Dining Set', 'slug' => 'dining-set', 'sortOrder' => 1],
            ],
        ],
        [
            'id' => 'cat_office',
            'name' => 'Office Furniture',
            'slug' => 'office-furniture',
            'sortOrder' => 4,
            'children' => [
                ['name' => 'Gaming/Office Tables', 'slug' => 'gaming-office-tables', 'sortOrder' => 1],
                ['name' => 'Gaming/Office Chairs', 'slug' => 'gaming-office-chairs', 'sortOrder' => 2],
            ],
        ],
        [
            'id' => 'cat_outdoor',
            'name' => 'Outdoor Furniture',
            'slug' => 'outdoor-furniture',
            'sortOrder' => 5,
            'children' => [
                ['name' => 'Outdoor Set (Tables and Chairs)', 'slug' => 'outdoor-set', 'sortOrder' => 1],
            ],
        ],
    ];

    private const DEFAULT_SHIPPING_RATES = [
        ['label' => 'NCR delivery', 'region' => 'PH', 'zone' => 'NCR', 'feeInPHP' => 150, 'sortOrder' => 1],
        ['label' => 'Luzon delivery', 'region' => 'PH', 'zone' => 'LUZON', 'feeInPHP' => 350, 'sortOrder' => 2],
        ['label' => 'Visayas delivery', 'region' => 'PH', 'zone' => 'VISAYAS', 'feeInPHP' => 650, 'sortOrder' => 3],
        ['label' => 'Mindanao delivery', 'region' => 'PH', 'zone' => 'MINDANAO', 'feeInPHP' => 750, 'sortOrder' => 4],
        ['label' => 'Remote islands delivery', 'region' => 'PH', 'zone' => 'REMOTE', 'feeInPHP' => 1200, 'sortOrder' => 5],
        ['label' => 'Philippines standard', 'region' => 'PH', 'zone' => null, 'feeInPHP' => 120, 'sortOrder' => 10],
        ['label' => 'International standard', 'region' => 'INTL', 'zone' => null, 'feeInPHP' => 950, 'sortOrder' => 20],
    ];

    public function run(): void
    {
        $now = now();
        $adminRoleId = $this->seedRoles($now);
        $this->seedPlatformSetting($now);
        $this->seedCategories(self::CATEGORY_TREE, null, $now);
        $this->seedShippingRates($now);
        $this->seedAdminUser($adminRoleId, $now);
    }

    private function seedRoles(\DateTimeInterface $now): string
    {
        $roles = [
            [
                'id' => 'role_admin',
                'key' => 'ADMIN',
                'name' => 'Super Admin',
                'description' => 'Full platform control including staff, settings, and payment configuration.',
                'isSystem' => true,
                'isStaff' => true,
                'permissions' => self::ALL_PERMISSION_KEYS,
            ],
            [
                'id' => 'role_store_manager',
                'key' => 'STORE_MANAGER',
                'name' => 'Store Manager',
                'description' => 'Day-to-day catalog and order fulfillment without platform settings access.',
                'isSystem' => true,
                'isStaff' => true,
                'permissions' => self::STORE_MANAGER_PERMISSIONS,
            ],
            [
                'id' => 'role_customer',
                'key' => 'CUSTOMER',
                'name' => 'Customer',
                'description' => 'Storefront shopper account.',
                'isSystem' => true,
                'isStaff' => false,
                'permissions' => [],
            ],
        ];

        foreach ($roles as $role) {
            $permissions = $this->formatTextArray($role['permissions']);

            DB::statement('
                INSERT INTO "Role" ("id", "key", "name", "description", "isSystem", "isStaff", "permissions", "createdAt", "updatedAt")
                VALUES (?, ?, ?, ?, ?, ?, ?::TEXT[], ?, ?)
                ON CONFLICT ("key") DO UPDATE SET
                    "name" = EXCLUDED."name",
                    "description" = EXCLUDED."description",
                    "isSystem" = EXCLUDED."isSystem",
                    "isStaff" = EXCLUDED."isStaff",
                    "permissions" = EXCLUDED."permissions",
                    "updatedAt" = EXCLUDED."updatedAt"
            ', [
                $role['id'],
                $role['key'],
                $role['name'],
                $role['description'],
                $role['isSystem'],
                $role['isStaff'],
                $permissions,
                $now,
                $now,
            ]);
        }

        return 'role_admin';
    }

    private function seedPlatformSetting(\DateTimeInterface $now): void
    {
        DB::table('PlatformSetting')->upsert(
            ['id' => 'default', 'updatedAt' => $now],
            ['id'],
            ['updatedAt']
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function seedCategories(array $items, ?string $parentId, \DateTimeInterface $now): void
    {
        foreach ($items as $item) {
            $children = $item['children'] ?? [];
            unset($item['children']);

            $id = $item['id'] ?? ('cat_'.Str::slug($item['slug'], '_'));
            unset($item['id']);

            DB::table('Category')->upsert(
                [
                    'id' => $id,
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'sortOrder' => $item['sortOrder'],
                    'parentId' => $parentId,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ],
                ['slug'],
                ['name', 'sortOrder', 'parentId', 'updatedAt']
            );

            if ($children !== []) {
                $this->seedCategories($children, $id, $now);
            }
        }
    }

    private function seedShippingRates(\DateTimeInterface $now): void
    {
        if (DB::table('ShippingRate')->count() > 0) {
            return;
        }

        $rows = array_map(fn (array $rate) => [
            'id' => (string) Str::uuid(),
            'label' => $rate['label'],
            'region' => $rate['region'],
            'zone' => $rate['zone'],
            'feeInPHP' => $rate['feeInPHP'],
            'isActive' => true,
            'sortOrder' => $rate['sortOrder'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ], self::DEFAULT_SHIPPING_RATES);

        DB::table('ShippingRate')->insert($rows);
    }

    private function seedAdminUser(string $adminRoleId, \DateTimeInterface $now): void
    {
        $email = trim((string) env('SEED_ADMIN_EMAIL', ''));
        $password = env('SEED_ADMIN_PASSWORD');

        if ($email === '' || $password === null || $password === '') {
            return;
        }

        $existing = DB::table('User')->where('email', $email)->first();

        if ($existing) {
            DB::table('User')->where('email', $email)->update([
                'roleId' => $adminRoleId,
                'passwordHash' => Hash::make($password),
                'updatedAt' => $now,
            ]);

            return;
        }

        DB::table('User')->insert([
            'id' => (string) Str::uuid(),
            'email' => $email,
            'passwordHash' => Hash::make($password),
            'roleId' => $adminRoleId,
            'isActive' => true,
            'marketingOptIn' => false,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function formatTextArray(array $values): string
    {
        if ($values === []) {
            return '{}';
        }

        $escaped = array_map(
            fn (string $value) => '"'.str_replace('"', '\\"', $value).'"',
            $values
        );

        return '{'.implode(',', $escaped).'}';
    }
}
