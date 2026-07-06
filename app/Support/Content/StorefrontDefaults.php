<?php

declare(strict_types=1);

namespace App\Support\Content;

final class StorefrontDefaults
{
  /** @var list<string> */
  public const STATIC_PAGE_KEYS = [
    'page-about',
    'page-help',
    'page-returns',
    'page-warranty',
    'page-store-locator',
  ];

  /**
   * @return array<string, mixed>
   */
  public static function listingPages(): array
  {
    return [
      'products' => [
        'title' => 'All Products',
        'subtitle' => 'Browse our full catalog of metal furniture.',
        'breadcrumb' => 'All Products',
      ],
      'collections' => [
        'title' => 'Collections',
        'subtitle' => 'Curated sets of products for every space.',
        'breadcrumb' => 'Collections',
      ],
      'bundles' => [
        'title' => 'Product Bundles',
        'subtitle' => 'Save when you buy complete sets together.',
        'breadcrumb' => 'Bundles',
      ],
      'sale' => [
        'title' => 'Sale',
        'subtitle' => 'Limited-time deals on selected items.',
        'breadcrumb' => 'Sale',
      ],
    ];
  }

  /**
   * @return array<string, string>
   */
  public static function pageCopy(): array
  {
    return [
      'trackOrderTitle' => 'Track your order',
      'trackOrderSubtitle' => 'Enter your order number and the email used at checkout.',
      'trackOrderBreadcrumb' => 'Track order',
      'checkoutTitle' => 'Checkout',
      'checkoutSubtitle' => 'One page — contact, delivery, and payment.',
      'checkoutBreadcrumb' => 'Checkout',
      'authSignInTitle' => 'Sign in',
      'authSignInSubtitle' => 'Access your orders and saved addresses.',
      'authRegisterTitle' => 'Create account',
      'authRegisterSubtitle' => 'Track orders and save delivery addresses for faster checkout.',
      'storeLocatorEmpty' => 'Pickup locations are not configured yet.',
      'storeLocatorFooter' => 'Prefer to shop online?',
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public static function settings(): array
  {
    return [
      'siteName' => 'BNF ASIA',
      'logoUrl' => null,
      'faviconUrl' => null,
      'tagline' => 'Quality metal furniture for every room. Built to last, priced to fit.',
      'copyright' => '© 2025 BNF Asia. All rights reserved.',
      'seo' => [
        'title' => 'BNF Asia — Premium Metal Furniture',
        'description' => 'Shop wardrobes, sofas, dining sets, beds, and metal furniture with nationwide delivery.',
        'ogImageUrl' => null,
      ],
      'analytics' => [
        'googleAnalyticsId' => null,
        'metaPixelId' => null,
        'gtmId' => null,
      ],
      'socialLinks' => [
        ['label' => 'Facebook', 'url' => 'https://facebook.com'],
        ['label' => 'Instagram', 'url' => 'https://instagram.com'],
      ],
      'headerLinks' => [
        ['label' => 'Store Locator', 'href' => '/pages/store-locator'],
        ['label' => 'Track Order', 'href' => '/track-order'],
        ['label' => 'Help Center', 'href' => '/pages/help'],
      ],
      'navLinks' => [
        ['label' => 'All Categories', 'href' => '/products', 'highlight' => false],
        ['label' => 'Collections', 'href' => '/collections'],
        ['label' => 'Bundles', 'href' => '/bundles'],
        ['label' => 'Sale', 'href' => '/products?deals=on-sale', 'highlight' => true],
      ],
      'footerShopLinks' => [
        ['label' => 'All Products', 'href' => '/products'],
        ['label' => 'Collections', 'href' => '/collections'],
        ['label' => 'Bundles', 'href' => '/bundles'],
        ['label' => 'Sale', 'href' => '/products?deals=on-sale'],
      ],
      'footerHelpLinks' => [
        ['label' => 'Track Order', 'href' => '/track-order'],
        ['label' => 'Returns & Refunds', 'href' => '/pages/returns'],
        ['label' => 'Warranty Policy', 'href' => '/pages/warranty'],
        ['label' => 'Contact Us', 'href' => '/pages/help'],
      ],
      'productTrustBullets' => [
        'Free delivery nationwide on qualifying orders',
        '5-year warranty on metal frame products',
        '30-day hassle-free returns',
        '0% installment options available',
      ],
      'pdpFreeShippingNote' => '',
      'promoBar' => 'FREE DELIVERY NATIONWIDE · 5-YEAR WARRANTY · EASY 0% INSTALLMENT',
      'phone' => '+63 2 8888 0000',
      'listingPages' => self::listingPages(),
      'pageCopy' => self::pageCopy(),
    ];
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public static function staticPages(): array
  {
    return [
      'page-about' => [
        'title' => 'About BNF Asia',
        'breadcrumb' => 'About',
        'sections' => [
          ['body' => 'BNF Asia is a leading supplier of quality metal furniture for homes and businesses across the Philippines. We design and manufacture durable beds, dining sets, office furniture, and storage solutions built to last.'],
          ['heading' => 'Our mission', 'body' => 'To make premium metal furniture accessible to every Filipino household — with honest pricing, nationwide delivery, and industry-leading warranty coverage.'],
          ['heading' => 'Why choose us', 'body' => '<ul><li>5-year warranty on all metal frame products</li><li>Free nationwide delivery on orders over ₱50,000</li><li>Easy 0% installment options available</li><li>30-day hassle-free returns</li></ul>'],
        ],
      ],
      'page-help' => [
        'title' => 'Help Center',
        'breadcrumb' => 'Help',
        'sections' => [
          ['heading' => 'Frequently asked questions', 'body' => '<p><strong>How do I track my order?</strong><br/>Use the Track Order page with your order number and email.</p><p><strong>What payment methods do you accept?</strong><br/>GCash, Maya, credit/debit cards, COD, and BNPL for eligible orders.</p><p><strong>How long does delivery take?</strong><br/>Metro Manila: 3–5 business days. Provincial: 5–10 business days.</p>'],
          ['heading' => 'Contact us', 'body' => '<p>Email: support@bnfasia.com<br/>Phone: +63 2 8888 0000<br/>Hours: Mon–Sat, 9 AM – 6 PM</p>'],
        ],
      ],
      'page-returns' => [
        'title' => 'Returns & Refunds',
        'breadcrumb' => 'Returns',
        'sections' => [
          ['heading' => 'Eligibility', 'body' => '<ul><li>Items must be unused and in original packaging</li><li>Return request within 30 days of delivery</li><li>Custom or clearance items may be final sale</li></ul>'],
          ['heading' => 'How to return', 'body' => '<ol><li>Contact support@bnfasia.com with your order number</li><li>Receive return authorization and instructions</li><li>Pack items securely for pickup or drop-off</li><li>Refund processed within 7–14 business days after inspection</li></ol>'],
        ],
      ],
      'page-warranty' => [
        'title' => 'Warranty Policy',
        'breadcrumb' => 'Warranty',
        'sections' => [
          ['heading' => 'Covered', 'body' => '<ul><li>Structural defects in metal frames for 5 years</li><li>Manufacturing defects reported within warranty period</li><li>Hardware and fittings under normal use</li></ul>'],
          ['heading' => 'Not covered', 'body' => '<ul><li>Normal wear and tear, scratches, or dents</li><li>Damage from misuse, improper assembly, or modifications</li><li>Commercial use beyond residential guidelines</li></ul>'],
          ['heading' => 'How to claim', 'body' => 'Email support@bnfasia.com with photos, order number, and description of the issue. Our team will guide you through repair or replacement.'],
        ],
      ],
      'page-store-locator' => [
        'title' => 'Store Locator',
        'breadcrumb' => 'Store Locator',
        'sections' => [
          ['body' => 'Visit a BNF Asia showroom to see our furniture in person. Our staff can help you choose the right pieces and arrange delivery from any location.'],
        ],
      ],
    ];
  }
}
