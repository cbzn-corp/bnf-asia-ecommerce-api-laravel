<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Addresses\CreateAddressRequest;
use App\Http\Requests\Addresses\UpdateAddressRequest;
use App\Services\Addresses\AddressesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressesController extends Controller
{
    use ResolvesAuthUser;

    public function __construct(
        private readonly AddressesService $addressesService,
    ) {}

    public function findMine(Request $request): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json($this->addressesService->findByUser($user->id));
    }

    public function create(CreateAddressRequest $request): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json($this->addressesService->create($user->id, $request->validated()));
    }

    public function update(UpdateAddressRequest $request, string $id): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json($this->addressesService->update($user->id, $id, $request->validated()));
    }

    public function remove(Request $request, string $id): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json($this->addressesService->remove($user->id, $id));
    }
}
