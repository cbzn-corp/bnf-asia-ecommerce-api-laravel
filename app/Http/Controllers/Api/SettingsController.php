<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CreatePickupLocationRequest;
use App\Http\Requests\Settings\CreateShippingRateRequest;
use App\Http\Requests\Settings\SendTestEmailRequest;
use App\Http\Requests\Settings\UpdateEmailTemplateRequest;
use App\Http\Requests\Settings\UpdatePickupLocationRequest;
use App\Http\Requests\Settings\UpdatePlatformSettingsRequest;
use App\Http\Requests\Settings\UpdateShippingRateRequest;
use App\Services\Audit\AuditService;
use App\Services\Email\EmailService;
use App\Services\Settings\PlatformSettingsService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use ResolvesAuthUser;

    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly PlatformSettingsService $platformSettings,
        private readonly AuditService $auditService,
        private readonly EmailService $emailService,
    ) {}

    public function listShipping(Request $request): JsonResponse
    {
        if ($request->query('activeOnly') === 'true') {
            return response()->json(
                $this->settingsService->listActiveShippingRates($request->query('region')),
            );
        }

        return response()->json($this->settingsService->listShippingRates());
    }

    public function createShipping(CreateShippingRateRequest $request): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json(
            $this->settingsService->createShippingRate($request->validated(), $user->email),
        );
    }

    public function updateShipping(UpdateShippingRateRequest $request, string $id): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json(
            $this->settingsService->updateShippingRate($id, $request->validated(), $user->email),
        );
    }

    public function removeShipping(Request $request, string $id): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json(
            $this->settingsService->removeShippingRate($id, $user->email),
        );
    }

    public function platform(): JsonResponse
    {
        return response()->json($this->platformSettings->getPublicSummary());
    }

    public function platformAdmin(): JsonResponse
    {
        return response()->json($this->platformSettings->getAdminConfig());
    }

    public function updatePlatform(UpdatePlatformSettingsRequest $request): JsonResponse
    {
        $user = $this->requireAuthUser($request);
        $updated = $this->platformSettings->updateAdminConfig($request->validated());

        $this->auditService->log([
            'userEmail' => $user->email,
            'action' => 'platform_settings.update',
            'entity' => 'PlatformSetting',
            'entityId' => 'default',
            'details' => array_keys($request->validated()),
        ]);

        return response()->json($updated);
    }

    public function listPickup(): JsonResponse
    {
        return response()->json($this->settingsService->listPickupLocations(true));
    }

    public function listPickupAdmin(): JsonResponse
    {
        return response()->json($this->settingsService->listPickupLocations(false));
    }

    public function createPickup(CreatePickupLocationRequest $request): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json(
            $this->settingsService->createPickupLocation($request->validated(), $user->email),
        );
    }

    public function updatePickup(UpdatePickupLocationRequest $request, string $id): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json(
            $this->settingsService->updatePickupLocation($id, $request->validated(), $user->email),
        );
    }

    public function removePickup(Request $request, string $id): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json(
            $this->settingsService->removePickupLocation($id, $user->email),
        );
    }

    public function listEmailTemplates(): JsonResponse
    {
        return response()->json($this->settingsService->listEmailTemplates());
    }

    public function updateEmailTemplate(UpdateEmailTemplateRequest $request, string $key): JsonResponse
    {
        return response()->json(
            $this->settingsService->updateEmailTemplate($key, $request->validated()),
        );
    }

    public function sendTestEmail(SendTestEmailRequest $request): JsonResponse
    {
        return response()->json(
            $this->emailService->sendTestEmail($request->validated('to')),
        );
    }

    public function sendTemplateTestEmail(SendTestEmailRequest $request, string $key): JsonResponse
    {
        return response()->json(
            $this->emailService->sendTemplateTestEmail($key, $request->validated('to')),
        );
    }

    public function emailTemplatePlaceholders(): JsonResponse
    {
        return response()->json(\App\Support\Email\EmailTemplatePlaceholders::catalog());
    }
}
