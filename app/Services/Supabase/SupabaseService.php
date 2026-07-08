<?php

declare(strict_types=1);

namespace App\Services\Supabase;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SupabaseService
{
  private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

  private const ALLOWED_VIDEO_MIME = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];

  private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

  private const MAX_VIDEO_BYTES = 50 * 1024 * 1024;

  private ?string $url;

  private ?string $serviceRoleKey;

  public function __construct()
  {
    $this->url = env('SUPABASE_URL') ?: null;
    $this->serviceRoleKey = env('SUPABASE_SERVICE_ROLE_KEY') ?: null;

    if (! $this->isConfigured()) {
      Log::warning('Supabase not configured — set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY');
    }
  }

  public function isConfigured(): bool
  {
    return $this->url !== null && $this->serviceRoleKey !== null;
  }

  public function getBucket(): string
  {
    return env('SUPABASE_STORAGE_BUCKET', 'product-images');
  }

  public function uploadProductImage(string $productId, UploadedFile $file): string
  {
    return $this->uploadFile("products/{$productId}/".time().'-'.$this->safeFilename($file), $file);
  }

  /**
   * @param  list<UploadedFile>  $files
   * @return list<string>
   */
  public function uploadProductImages(string $productId, array $files): array
  {
    $urls = [];
    foreach ($files as $file) {
      $urls[] = $this->uploadProductImage($productId, $file);
    }

    return $urls;
  }

  public function uploadDescriptionImage(string $productId, UploadedFile $file): string
  {
    return $this->uploadFile("products/{$productId}/description/".time().'-'.$this->safeFilename($file), $file);
  }

  public function uploadVariantImage(string $productId, string $variantId, UploadedFile $file): string
  {
    return $this->uploadFile(
      "products/{$productId}/variants/{$variantId}/".time().'-'.$this->safeFilename($file),
      $file,
    );
  }

  /**
   * @param  list<UploadedFile>  $files
   * @return list<string>
   */
  public function uploadVariantImages(string $productId, string $variantId, array $files): array
  {
    $urls = [];
    foreach ($files as $file) {
      $urls[] = $this->uploadVariantImage($productId, $variantId, $file);
    }

    return $urls;
  }

  public function uploadCollectionCoverImage(string $collectionId, UploadedFile $file): string
  {
    return $this->uploadFile("collections/{$collectionId}/".time().'-'.$this->safeFilename($file), $file);
  }

  public function uploadCategoryCoverImage(string $categoryId, UploadedFile $file): string
  {
    return $this->uploadFile("categories/{$categoryId}/".time().'-'.$this->safeFilename($file), $file);
  }

  public function uploadBundleCoverImage(string $bundleId, UploadedFile $file): string
  {
    return $this->uploadFile("bundles/{$bundleId}/".time().'-'.$this->safeFilename($file), $file);
  }

  public function uploadStorefrontAsset(string $asset, UploadedFile $file): string
  {
    $faviconMime = array_merge(self::ALLOWED_MIME, [
      'image/x-icon',
      'image/vnd.microsoft.icon',
      'image/svg+xml',
    ]);
    $allowed = $asset === 'favicon' ? $faviconMime : self::ALLOWED_MIME;

    return $this->uploadFile("storefront/{$asset}/".time().'-'.$this->safeFilename($file), $file, $allowed);
  }

  public function uploadHomepageHeroTopCarouselImage(int $index, UploadedFile $file): string
  {
    return $this->uploadFile("homepage/hero-top-carousel/{$index}/".time().'-'.$this->safeFilename($file), $file);
  }

  public function uploadHomepageHeroImage(UploadedFile $file): string
  {
    return $this->uploadFile('homepage/hero/'.time().'-'.$this->safeFilename($file), $file);
  }

  public function uploadHomepageCollectionImage(int $index, UploadedFile $file): string
  {
    return $this->uploadFile("homepage/collections/{$index}/".time().'-'.$this->safeFilename($file), $file);
  }

  public function uploadHomepagePromoBannerImage(int $index, UploadedFile $file): string
  {
    return $this->uploadFile("homepage/promo-banners/{$index}/".time().'-'.$this->safeFilename($file), $file);
  }

  public function uploadHomepageSaleCountdownImage(UploadedFile $file): string
  {
    return $this->uploadFile('homepage/sale-countdown/'.time().'-'.$this->safeFilename($file), $file);
  }

  public function uploadContentPageImage(UploadedFile $file): string
  {
    return $this->uploadFile('content/pages/'.time().'-'.$this->safeFilename($file), $file);
  }

  public function uploadContentPageVideo(UploadedFile $file): string
  {
    return $this->uploadFile(
      'content/pages/videos/'.time().'-'.$this->safeFilename($file),
      $file,
      self::ALLOWED_VIDEO_MIME,
      self::MAX_VIDEO_BYTES,
      'Video',
    );
  }

  /**
   * @param  list<string>|null  $allowedMime
   */
  private function uploadFile(
    string $path,
    UploadedFile $file,
    ?array $allowedMime = null,
    ?int $maxBytes = null,
    string $fileLabel = 'Image',
  ): string {
    if (! $this->isConfigured()) {
      throw new BadRequestHttpException('Supabase storage is not configured');
    }

    $mime = $file->getMimeType() ?? 'application/octet-stream';
    $allowed = $allowedMime ?? self::ALLOWED_MIME;

    if (! in_array($mime, $allowed, true)) {
      throw new BadRequestHttpException("Unsupported {$fileLabel} type: {$mime}");
    }

    $sizeLimit = $maxBytes ?? self::MAX_IMAGE_BYTES;
    if ($file->getSize() > $sizeLimit) {
      $limitMb = (int) round($sizeLimit / (1024 * 1024));
      throw new BadRequestHttpException("{$fileLabel} must be {$limitMb} MB or less");
    }

    $bucket = $this->getBucket();
    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
    $endpoint = rtrim($this->url, '/')."/storage/v1/object/{$bucket}/{$encodedPath}";

    $response = Http::withHeaders([
      'Authorization' => 'Bearer '.$this->serviceRoleKey,
      'apikey' => $this->serviceRoleKey,
      'Content-Type' => $mime,
      'x-upsert' => 'false',
    ])->withBody($file->getContent(), $mime)->post($endpoint);

    if (! $response->successful()) {
      $message = $response->json('message') ?? $response->json('error') ?? $response->body();
      throw new BadRequestHttpException('Upload failed: '.$message);
    }

    return rtrim($this->url, '/')."/storage/v1/object/public/{$bucket}/{$encodedPath}";
  }

  private function safeFilename(UploadedFile $file): string
  {
    return preg_replace('/[^a-zA-Z0-9.-]/', '_', $file->getClientOriginalName()) ?? 'upload';
  }
}
