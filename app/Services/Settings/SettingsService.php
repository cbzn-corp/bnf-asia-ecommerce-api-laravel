<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\EmailTemplate;
use App\Models\PickupLocation;
use App\Models\ShippingRate;
use App\Services\Audit\AuditService;
use App\Support\Cache\ApiCache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SettingsService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

  /**
   * @return \Illuminate\Database\Eloquent\Collection<int, ShippingRate>
   */
    public function listShippingRates()
    {
        return ApiCache::remember(ApiCache::DOMAIN_SETTINGS, 'shipping:all', function () {
            return ShippingRate::query()
                ->orderBy('sortOrder')
                ->orderBy('label')
                ->get();
        });
    }

  /**
   * @return \Illuminate\Database\Eloquent\Collection<int, ShippingRate>
   */
    public function listActiveShippingRates(?string $region = null)
    {
        $cacheKey = 'shipping:active'.($region ? ':'.rawurlencode($region) : '');

        return ApiCache::remember(ApiCache::DOMAIN_SETTINGS, $cacheKey, function () use ($region) {
            return ShippingRate::query()
                ->where('isActive', true)
                ->when($region, fn ($q) => $q->where('region', $region))
                ->orderBy('sortOrder')
                ->get();
        });
    }

    /**
     * @param  array<string, mixed>  $dto
     */
    public function createShippingRate(array $dto, ?string $actorEmail = null): ShippingRate
    {
        $row = ShippingRate::query()->create([
            'label' => $dto['label'],
            'region' => $dto['region'],
            'zone' => $dto['zone'] ?? null,
            'estimatedDays' => $dto['estimatedDays'] ?? null,
            'feeInPHP' => $dto['feeInPHP'],
            'minWeightGrams' => $dto['minWeightGrams'] ?? null,
            'maxWeightGrams' => $dto['maxWeightGrams'] ?? null,
            'isActive' => $dto['isActive'] ?? true,
            'sortOrder' => $dto['sortOrder'] ?? 0,
        ]);

        if ($actorEmail) {
            $this->auditService->log([
                'userEmail' => $actorEmail,
                'action' => 'shipping_rate.create',
                'entity' => 'ShippingRate',
                'entityId' => $row->id,
                'details' => [
                    'label' => $row->label,
                    'region' => $row->region,
                    'feeInPHP' => (float) $row->feeInPHP,
                ],
            ]);
        }

        ApiCache::bump(ApiCache::DOMAIN_SETTINGS);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $dto
     */
    public function updateShippingRate(string $id, array $dto, ?string $actorEmail = null): ShippingRate
    {
        $this->findShippingRate($id);
        $row = ShippingRate::query()->findOrFail($id);
        $row->update($dto);

        if ($actorEmail) {
            $this->auditService->log([
                'userEmail' => $actorEmail,
                'action' => 'shipping_rate.update',
                'entity' => 'ShippingRate',
                'entityId' => $id,
                'details' => $dto,
            ]);
        }

        ApiCache::bump(ApiCache::DOMAIN_SETTINGS);

        return $row->fresh();
    }

    /**
     * @return array{deleted: bool}
     */
    public function removeShippingRate(string $id, ?string $actorEmail = null): array
    {
        $row = $this->findShippingRate($id);
        $row->delete();

        if ($actorEmail) {
            $this->auditService->log([
                'userEmail' => $actorEmail,
                'action' => 'shipping_rate.delete',
                'entity' => 'ShippingRate',
                'entityId' => $id,
                'details' => ['label' => $row->label],
            ]);
        }

        ApiCache::bump(ApiCache::DOMAIN_SETTINGS);

        return ['deleted' => true];
    }

    public function findShippingRate(string $id): ShippingRate
    {
        $row = ShippingRate::query()->find($id);
        if (! $row) {
            throw new NotFoundHttpException("Shipping rate not found: {$id}");
        }

        return $row;
    }

  /**
   * @return \Illuminate\Database\Eloquent\Collection<int, PickupLocation>
   */
    public function listPickupLocations(bool $activeOnly = true)
    {
        $cacheKey = 'pickup:'.($activeOnly ? 'active' : 'all');

        return ApiCache::remember(ApiCache::DOMAIN_SETTINGS, $cacheKey, function () use ($activeOnly) {
            return PickupLocation::query()
                ->when($activeOnly, fn ($q) => $q->where('isActive', true))
                ->orderBy('sortOrder')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPickupLocation(array $data, ?string $actorEmail = null): PickupLocation
    {
        $row = PickupLocation::query()->create([
            'name' => $data['name'],
            'address' => $data['address'],
            'city' => $data['city'],
            'province' => $data['province'],
            'phone' => $data['phone'] ?? null,
            'isActive' => $data['isActive'] ?? true,
            'sortOrder' => $data['sortOrder'] ?? 0,
        ]);

        if ($actorEmail) {
            $this->auditService->log([
                'userEmail' => $actorEmail,
                'action' => 'pickup_location.create',
                'entity' => 'PickupLocation',
                'entityId' => $row->id,
                'details' => ['name' => $row->name, 'city' => $row->city],
            ]);
        }

        ApiCache::bump(ApiCache::DOMAIN_SETTINGS);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePickupLocation(string $id, array $data, ?string $actorEmail = null): PickupLocation
    {
        $this->findPickupLocation($id);
        $row = PickupLocation::query()->findOrFail($id);
        $row->update($data);

        if ($actorEmail) {
            $this->auditService->log([
                'userEmail' => $actorEmail,
                'action' => 'pickup_location.update',
                'entity' => 'PickupLocation',
                'entityId' => $id,
                'details' => $data,
            ]);
        }

        ApiCache::bump(ApiCache::DOMAIN_SETTINGS);

        return $row->fresh();
    }

    /**
     * @return array{deleted: bool}
     */
    public function removePickupLocation(string $id, ?string $actorEmail = null): array
    {
        $row = $this->findPickupLocation($id);
        $row->delete();

        if ($actorEmail) {
            $this->auditService->log([
                'userEmail' => $actorEmail,
                'action' => 'pickup_location.delete',
                'entity' => 'PickupLocation',
                'entityId' => $id,
                'details' => ['name' => $row->name],
            ]);
        }

        ApiCache::bump(ApiCache::DOMAIN_SETTINGS);

        return ['deleted' => true];
    }

    public function findPickupLocation(string $id): PickupLocation
    {
        $row = PickupLocation::query()->find($id);
        if (! $row) {
            throw new NotFoundHttpException("Pickup location not found: {$id}");
        }

        return $row;
    }

  /**
   * @return \Illuminate\Database\Eloquent\Collection<int, EmailTemplate>
   */
    public function listEmailTemplates()
    {
        return EmailTemplate::query()->orderBy('key')->get();
    }

    /**
     * @param  array{subject?: string, bodyText?: string, bodyHtml?: string|null}  $data
     */
    public function updateEmailTemplate(string $key, array $data): EmailTemplate
    {
        $template = EmailTemplate::query()->findOrFail($key);

        return tap($template, fn () => $template->update($data));
    }
}
