<?php

declare(strict_types=1);

namespace App\Support\Content;

final class StorefrontUtils
{
  /**
   * @return array<string, mixed>|null
   */
  private static function normalizeNavLinkItem(mixed $raw): ?array
  {
    $value = is_array($raw) ? $raw : [];
    $label = trim((string) ($value['label'] ?? ''));
    $children = is_array($value['children'] ?? null)
      ? array_values(array_filter(array_map([self::class, 'normalizeNavLinkItem'], $value['children'])))
      : [];
    $href = trim((string) ($value['href'] ?? ''));

    if ($label === '') {
      return null;
    }
    if ($href === '' && $children === []) {
      return null;
    }
    if ($href === '') {
      $href = '#';
    }

    $link = [
      'label' => $label,
      'href' => $href,
      'highlight' => ($value['highlight'] ?? false) === true,
    ];

    if (! empty($value['id']) && is_string($value['id']) && trim($value['id']) !== '') {
      $link['id'] = trim($value['id']);
    }
    if (($value['megaMenu'] ?? false) === true) {
      $link['megaMenu'] = true;
    }
    if ($children !== []) {
      $link['children'] = $children;
    }

    return $link;
  }

  /**
   * @param  list<array<string, mixed>>  $fallback
   * @return list<array<string, mixed>>
   */
  private static function normalizeNavLinks(mixed $raw, array $fallback): array
  {
    if (! is_array($raw)) {
      return $fallback;
    }

    $links = array_values(array_filter(array_map([self::class, 'normalizeNavLinkItem'], $raw)));

    return $links !== [] ? $links : $fallback;
  }

  /**
   * @param  list<array{label: string, url: string}>  $fallback
   * @return list<array{label: string, url: string}>
   */
  private static function normalizeSocialLinks(mixed $raw, array $fallback): array
  {
    if (! is_array($raw)) {
      return $fallback;
    }

    $links = [];
    foreach ($raw as $item) {
      $value = is_array($item) ? $item : [];
      $label = trim((string) ($value['label'] ?? ''));
      $url = trim((string) ($value['url'] ?? ''));
      if ($label !== '' && $url !== '') {
        $links[] = ['label' => $label, 'url' => $url];
      }
    }

    return $links !== [] ? $links : $fallback;
  }

  /**
   * @return array{title: string, subtitle: string, breadcrumb: string}
   */
  private static function normalizeListingPage(mixed $raw, array $fallback): array
  {
    $value = is_array($raw) ? $raw : [];

    return [
      'title' => (string) ($value['title'] ?? $fallback['title']),
      'subtitle' => (string) ($value['subtitle'] ?? $fallback['subtitle']),
      'breadcrumb' => (string) ($value['breadcrumb'] ?? $fallback['breadcrumb']),
    ];
  }

  /**
   * @return array<string, array{title: string, subtitle: string, breadcrumb: string}>
   */
  private static function normalizeListingPages(mixed $raw): array
  {
    $value = is_array($raw) ? $raw : [];
    $defaults = StorefrontDefaults::listingPages();

    return [
      'products' => self::normalizeListingPage($value['products'] ?? null, $defaults['products']),
      'collections' => self::normalizeListingPage($value['collections'] ?? null, $defaults['collections']),
      'bundles' => self::normalizeListingPage($value['bundles'] ?? null, $defaults['bundles']),
      'sale' => self::normalizeListingPage($value['sale'] ?? null, $defaults['sale']),
    ];
  }

  /**
   * @return array<string, string>
   */
  private static function normalizePageCopy(mixed $raw): array
  {
    $value = is_array($raw) ? $raw : [];
    $defaults = StorefrontDefaults::pageCopy();

    $result = [];
    foreach ($defaults as $key => $default) {
      $result[$key] = (string) ($value[$key] ?? $default);
    }

    return $result;
  }

  /**
   * @return array<string, mixed>
   */
  public static function normalizeStorefrontSettings(mixed $raw): array
  {
    $value = is_array($raw) ? $raw : [];
    $defaults = StorefrontDefaults::settings();
    $seoRaw = is_array($value['seo'] ?? null) ? $value['seo'] : [];
    $analyticsRaw = is_array($value['analytics'] ?? null) ? $value['analytics'] : [];

    return [
      'siteName' => (string) ($value['siteName'] ?? $defaults['siteName']),
      'logoUrl' => ! empty($value['logoUrl']) ? (string) $value['logoUrl'] : null,
      'faviconUrl' => ! empty($value['faviconUrl']) ? (string) $value['faviconUrl'] : null,
      'tagline' => (string) ($value['tagline'] ?? $defaults['tagline']),
      'copyright' => (string) ($value['copyright'] ?? $defaults['copyright']),
      'seo' => [
        'title' => (string) ($seoRaw['title'] ?? $defaults['seo']['title']),
        'description' => (string) ($seoRaw['description'] ?? $defaults['seo']['description']),
        'ogImageUrl' => ! empty($seoRaw['ogImageUrl']) ? (string) $seoRaw['ogImageUrl'] : null,
      ],
      'analytics' => [
        'googleAnalyticsId' => ! empty($analyticsRaw['googleAnalyticsId']) ? (string) $analyticsRaw['googleAnalyticsId'] : null,
        'metaPixelId' => ! empty($analyticsRaw['metaPixelId']) ? (string) $analyticsRaw['metaPixelId'] : null,
        'gtmId' => ! empty($analyticsRaw['gtmId']) ? (string) $analyticsRaw['gtmId'] : null,
      ],
      'socialLinks' => self::normalizeSocialLinks($value['socialLinks'] ?? null, $defaults['socialLinks']),
      'headerLinks' => self::normalizeNavLinks($value['headerLinks'] ?? null, $defaults['headerLinks']),
      'navLinks' => self::normalizeNavLinks($value['navLinks'] ?? null, $defaults['navLinks']),
      'footerShopLinks' => self::normalizeNavLinks($value['footerShopLinks'] ?? null, $defaults['footerShopLinks']),
      'footerHelpLinks' => self::normalizeNavLinks($value['footerHelpLinks'] ?? null, $defaults['footerHelpLinks']),
      'productTrustBullets' => is_array($value['productTrustBullets'] ?? null)
        ? array_values(array_filter(array_map(static fn ($item) => trim((string) $item), $value['productTrustBullets'])))
        : $defaults['productTrustBullets'],
      'pdpFreeShippingNote' => (string) ($value['pdpFreeShippingNote'] ?? $defaults['pdpFreeShippingNote']),
      'promoBar' => (string) ($value['promoBar'] ?? $defaults['promoBar']),
      'phone' => (string) ($value['phone'] ?? $defaults['phone']),
      'listingPages' => self::normalizeListingPages($value['listingPages'] ?? null),
      'pageCopy' => self::normalizePageCopy($value['pageCopy'] ?? null),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public static function normalizeStaticPage(mixed $raw, string $key): array
  {
    $value = is_array($raw) ? $raw : [];
    $defaults = StorefrontDefaults::staticPages()[$key] ?? ['title' => '', 'breadcrumb' => '', 'sections' => []];

    $sections = is_array($value['sections'] ?? null)
      ? array_map(static function ($section) {
        $item = is_array($section) ? $section : [];

        return [
          'heading' => ! empty($item['heading']) ? (string) $item['heading'] : null,
          'body' => (string) ($item['body'] ?? ''),
        ];
      }, $value['sections'])
      : $defaults['sections'];

    $sections = array_map(static function ($section) {
      if ($section['heading'] === null) {
        unset($section['heading']);
      }

      return $section;
    }, $sections);

    return [
      'title' => (string) ($value['title'] ?? $defaults['title']),
      'breadcrumb' => (string) ($value['breadcrumb'] ?? $defaults['breadcrumb']),
      'sections' => $sections !== [] ? $sections : $defaults['sections'],
    ];
  }

  public static function staticPageKeyFromSlug(string $slug): ?string
  {
    if (! ContentPagesRegistry::isValidSlug($slug)) {
      return null;
    }

    $key = ContentPagesRegistry::pageKey($slug);
    if (in_array($key, StorefrontDefaults::STATIC_PAGE_KEYS, true)) {
      return $key;
    }

    foreach (ContentPagesRegistry::customPages() as $page) {
      if ($page['slug'] === $slug) {
        return $key;
      }
    }

    return null;
  }
}
