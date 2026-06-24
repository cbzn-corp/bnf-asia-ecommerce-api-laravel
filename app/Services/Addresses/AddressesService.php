<?php

declare(strict_types=1);

namespace App\Services\Addresses;

use App\Models\Address;
use App\Support\Utils\PhProvince;
use App\Support\Utils\ShippingRegion;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AddressesService
{
  /**
   * @return \Illuminate\Database\Eloquent\Collection<int, Address>
   */
    public function findByUser(string $userId)
    {
        return Address::query()
            ->where('userId', $userId)
            ->orderByDesc('isDefault')
            ->orderByDesc('updatedAt')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $dto
     */
    public function create(string $userId, array $dto): Address
    {
        $data = $this->normalizeDto($dto);

        if (! empty($data['isDefault'])) {
            Address::query()->where('userId', $userId)->update(['isDefault' => false]);
        }

        return Address::query()->create([
            ...$data,
            'userId' => $userId,
            'label' => $data['label'] ?? 'Home',
        ]);
    }

    /**
     * @param  array<string, mixed>  $dto
     */
    public function update(string $userId, string $id, array $dto): Address
    {
        $existing = $this->ensureOwned($userId, $id);
        $data = $this->normalizeDto($dto, $existing->country);

        if (! empty($data['isDefault'])) {
            Address::query()->where('userId', $userId)->update(['isDefault' => false]);
        }

        $existing->update($data);

        return $existing->fresh();
    }

    /**
     * @return array{deleted: bool}
     */
    public function remove(string $userId, string $id): array
    {
        $this->ensureOwned($userId, $id);
        Address::query()->where('id', $id)->delete();

        return ['deleted' => true];
    }

    private function ensureOwned(string $userId, string $id): Address
    {
        $row = Address::query()->find($id);
        if (! $row) {
            throw new NotFoundHttpException('Address not found.');
        }
        if ($row->userId !== $userId) {
            throw new AccessDeniedHttpException();
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    private function normalizeDto(array $dto, ?string $country = null): array
    {
        $resolvedCountry = $dto['country'] ?? $country;
        if (
            ! $resolvedCountry
            || ! ShippingRegion::isPhilippines($resolvedCountry)
            || ! array_key_exists('province', $dto)
        ) {
            return $dto;
        }

        return [
            ...$dto,
            'province' => PhProvince::normalizePhProvince($dto['province']),
        ];
    }
}
