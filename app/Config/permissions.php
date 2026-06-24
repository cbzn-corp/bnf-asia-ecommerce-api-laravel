<?php

declare(strict_types=1);

namespace App\Config;

final class Permissions
{
    /** @var list<string> */
    public const PERMISSION_KEYS = [
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
        'abandoned_carts.manage',
        'users.manage',
        'roles.manage',
        'settings.shipping',
        'settings.payments',
        'settings.email_templates',
        'logs.payments',
        'logs.audit',
    ];

    /** @var list<array{key: string, label: string, description: string, group: string}> */
    public const PERMISSION_DEFINITIONS = [
        ['key' => 'dashboard.view', 'label' => 'Dashboard', 'description' => 'View operations KPIs', 'group' => 'Operations'],
        ['key' => 'analytics.view', 'label' => 'Analytics', 'description' => 'Revenue charts and product insights', 'group' => 'Operations'],
        ['key' => 'customers.view', 'label' => 'Customer accounts', 'description' => 'Browse registered customers', 'group' => 'Operations'],
        ['key' => 'orders.manage', 'label' => 'Orders & fulfillment', 'description' => 'Manage orders, tracking, notes, and refunds', 'group' => 'Operations'],
        ['key' => 'abandoned_carts.manage', 'label' => 'Abandoned carts', 'description' => 'Send cart recovery emails', 'group' => 'Operations'],
        ['key' => 'products.manage', 'label' => 'Products', 'description' => 'Create and edit products, variants, and images', 'group' => 'Catalog'],
        ['key' => 'categories.manage', 'label' => 'Categories', 'description' => 'Manage product categories', 'group' => 'Catalog'],
        ['key' => 'collections.manage', 'label' => 'Collections', 'description' => 'Manage curated product collections', 'group' => 'Catalog'],
        ['key' => 'bundles.manage', 'label' => 'Bundles', 'description' => 'Manage product bundles', 'group' => 'Catalog'],
        ['key' => 'homepage.manage', 'label' => 'Homepage CMS', 'description' => 'Edit storefront homepage content', 'group' => 'Catalog'],
        ['key' => 'reviews.manage', 'label' => 'Reviews', 'description' => 'Moderate product reviews', 'group' => 'Catalog'],
        ['key' => 'promotions.manage', 'label' => 'Promotions', 'description' => 'Manage voucher codes', 'group' => 'Marketing'],
        ['key' => 'users.manage', 'label' => 'Staff users', 'description' => 'Invite and manage staff accounts', 'group' => 'Platform'],
        ['key' => 'roles.manage', 'label' => 'Roles & permissions', 'description' => 'Create roles and assign permissions', 'group' => 'Platform'],
        ['key' => 'settings.shipping', 'label' => 'Shipping settings', 'description' => 'Configure shipping rates', 'group' => 'Platform'],
        ['key' => 'settings.payments', 'label' => 'Payment settings', 'description' => 'Configure PayMongo, Stripe, SMTP, and VAT', 'group' => 'Platform'],
        ['key' => 'settings.email_templates', 'label' => 'Email templates', 'description' => 'Edit transactional email templates', 'group' => 'Platform'],
        ['key' => 'logs.payments', 'label' => 'Payment logs', 'description' => 'View payment webhook events', 'group' => 'Platform'],
        ['key' => 'logs.audit', 'label' => 'Audit logs', 'description' => 'View staff action history', 'group' => 'Platform'],
    ];

    /** @var list<string> */
    public const ALL_PERMISSION_KEYS = self::PERMISSION_KEYS;

    public const ADMIN_ROLE_KEY = 'ADMIN';

    public const STORE_MANAGER_ROLE_KEY = 'STORE_MANAGER';

    public const CUSTOMER_ROLE_KEY = 'CUSTOMER';

    /** @var list<string> */
    public const STORE_MANAGER_PERMISSIONS = [
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

    /**
     * @return list<array{id: string, label: string, permissions: list<array{key: string, label: string, description: string, group: string}>}>
     */
    public static function permissionGroups(): array
    {
        $groups = [];

        foreach (self::PERMISSION_DEFINITIONS as $permission) {
            $groupId = strtolower(preg_replace('/\s+/', '-', $permission['group']) ?? $permission['group']);
            $found = false;

            foreach ($groups as &$group) {
                if ($group['id'] === $groupId) {
                    $group['permissions'][] = $permission;
                    $found = true;
                    break;
                }
            }
            unset($group);

            if (! $found) {
                $groups[] = [
                    'id' => $groupId,
                    'label' => $permission['group'],
                    'permissions' => [$permission],
                ];
            }
        }

        return $groups;
    }

    public static function isValidPermissionKey(string $key): bool
    {
        return in_array($key, self::PERMISSION_KEYS, true);
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public static function sanitizePermissions(array $permissions): array
    {
        return array_values(array_filter($permissions, [self::class, 'isValidPermissionKey']));
    }

    /**
     * @param  list<string>  $userPermissions
     * @param  string|list<string>  $required
     */
    public static function hasPermission(array $userPermissions, string|array $required): bool
    {
        $requiredList = is_array($required) ? $required : [$required];

        foreach ($requiredList as $permission) {
            if (! in_array($permission, $userPermissions, true)) {
                return false;
            }
        }

        return true;
    }
}
