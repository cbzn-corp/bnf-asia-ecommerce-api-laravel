<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CreateStaticPageRequest;
use App\Http\Requests\Content\UpdateHomepageRequest;
use App\Http\Requests\Content\UpdateStaticPageRequest;
use App\Http\Requests\Content\UpdateStorefrontSettingsRequest;
use App\Services\Content\ContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ContentController extends Controller
{
    public function __construct(
        private readonly ContentService $contentService,
    ) {}

    public function getHomepage(): JsonResponse
    {
        return response()->json($this->contentService->getHomepage());
    }

    public function getHomepageRendered(): JsonResponse
    {
        return response()->json($this->contentService->getHomepageRendered());
    }

    public function getStorefrontShell(): JsonResponse
    {
        return response()->json($this->contentService->getStorefrontShell());
    }

    public function updateHomepage(UpdateHomepageRequest $request): JsonResponse
    {
        return response()->json($this->contentService->updateHomepage($request->validated()));
    }

    public function uploadHomepageHeroImage(Request $request): JsonResponse
    {
        $file = $request->file('image');
        if (! $file) {
            throw new BadRequestHttpException('An image file is required');
        }

        return response()->json($this->contentService->uploadHomepageHeroImage($file));
    }

    public function removeHomepageHeroImage(): JsonResponse
    {
        return response()->json($this->contentService->removeHomepageHeroImage());
    }

    public function uploadHomepageCollectionImage(Request $request, int $index): JsonResponse
    {
        $file = $request->file('image');
        if (! $file) {
            throw new BadRequestHttpException('An image file is required');
        }

        return response()->json($this->contentService->uploadHomepageCollectionImage($index, $file));
    }

    public function removeHomepageCollectionImage(int $index): JsonResponse
    {
        return response()->json($this->contentService->removeHomepageCollectionImage($index));
    }

    public function uploadHomepagePromoBannerImage(Request $request, int $index): JsonResponse
    {
        $file = $request->file('image');
        if (! $file) {
            throw new BadRequestHttpException('An image file is required');
        }

        return response()->json($this->contentService->uploadHomepagePromoBannerImage($index, $file));
    }

    public function removeHomepagePromoBannerImage(int $index): JsonResponse
    {
        return response()->json($this->contentService->removeHomepagePromoBannerImage($index));
    }

    public function uploadHomepageSaleCountdownImage(Request $request): JsonResponse
    {
        $file = $request->file('image');
        if (! $file) {
            throw new BadRequestHttpException('An image file is required');
        }

        return response()->json($this->contentService->uploadHomepageSaleCountdownImage($file));
    }

    public function removeHomepageSaleCountdownImage(): JsonResponse
    {
        return response()->json($this->contentService->removeHomepageSaleCountdownImage());
    }

    public function getStorefrontSettings(): JsonResponse
    {
        return response()->json($this->contentService->getStorefrontSettings());
    }

    public function updateStorefrontSettings(UpdateStorefrontSettingsRequest $request): JsonResponse
    {
        return response()->json($this->contentService->updateStorefrontSettings($request->validated()));
    }

    public function uploadStorefrontAsset(Request $request, string $asset): JsonResponse
    {
        if (! in_array($asset, ['logo', 'favicon', 'og-image'], true)) {
            throw new BadRequestHttpException('Asset must be logo, favicon, or og-image');
        }

        $file = $request->file('image');
        if (! $file) {
            throw new BadRequestHttpException('An image file is required');
        }

        return response()->json($this->contentService->uploadStorefrontAsset($asset, $file));
    }

    public function removeStorefrontAsset(string $asset): JsonResponse
    {
        if (! in_array($asset, ['logo', 'favicon', 'og-image'], true)) {
            throw new BadRequestHttpException('Asset must be logo, favicon, or og-image');
        }

        return response()->json($this->contentService->removeStorefrontAsset($asset));
    }

    public function getStaticPage(string $slug): JsonResponse
    {
        return response()->json($this->contentService->getStaticPage($slug));
    }

    public function getAllStaticPages(): JsonResponse
    {
        return response()->json($this->contentService->getAllStaticPages());
    }

    public function getContentPagePaths(): JsonResponse
    {
        return response()->json($this->contentService->getContentPagePaths());
    }

    public function updateStaticPage(UpdateStaticPageRequest $request, string $slug): JsonResponse
    {
        return response()->json($this->contentService->updateStaticPage($slug, $request->validated()));
    }

    public function createStaticPage(CreateStaticPageRequest $request): JsonResponse
    {
        return response()->json($this->contentService->createStaticPage($request->validated()), 201);
    }

    public function deleteStaticPage(string $slug): JsonResponse
    {
        $this->contentService->deleteStaticPage($slug);

        return response()->json(['ok' => true]);
    }

    public function uploadContentPageImage(Request $request): JsonResponse
    {
        $file = $request->file('image');
        if (! $file) {
            throw new BadRequestHttpException('An image file is required');
        }

        return response()->json($this->contentService->uploadContentPageImage($file));
    }

    public function uploadContentPageVideo(Request $request): JsonResponse
    {
        $file = $request->file('video');
        if (! $file) {
            throw new BadRequestHttpException('A video file is required');
        }

        return response()->json($this->contentService->uploadContentPageVideo($file));
    }

    public function revalidateStorefront(): JsonResponse
    {
        return response()->json($this->contentService->revalidateStorefront());
    }

    public function validateMaintenanceBypass(Request $request): JsonResponse
    {
        $key = trim((string) $request->input('key', ''));

        return response()->json([
            'valid' => $key !== '' && $this->contentService->validateMaintenanceBypassKey($key),
        ]);
    }
}
