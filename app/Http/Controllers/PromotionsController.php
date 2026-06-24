<?php

namespace App\Http\Controllers;

use App\Services\PromotionsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionsController extends Controller
{
    public function __construct(
        private readonly PromotionsService $promotionsService,
    ) {}

    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'string'],
            'items.*.lineTotalInPHP' => ['required', 'numeric', 'min:0'],
            'userId' => ['nullable', 'string'],
        ]);

        $result = $this->promotionsService->validateAndCalculate(
            $data['code'],
            $data['items'],
            $data['userId'] ?? null,
        );

        return response()->json([
            'code' => $result['promotionCode'],
            'discountInPHP' => (float) $result['discountInPHP'],
            'type' => $result['promotion']?->type?->value,
        ]);
    }

    public function findAll(Request $request)
    {
        return response()->json($this->promotionsService->findAll($request->query('search')));
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:2'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(\App\Enums\PromotionType::class)],
            'value' => ['required', 'numeric', 'min:0'],
            'minOrderPHP' => ['nullable', 'numeric', 'min:0'],
            'maxUses' => ['nullable', 'integer', 'min:1'],
            'oneUsePerAccount' => ['nullable', 'boolean'],
            'productIds' => ['nullable', 'array'],
            'productIds.*' => ['string'],
            'startsAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->promotionsService->create($data), 201);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'min:2'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', Rule::enum(\App\Enums\PromotionType::class)],
            'value' => ['nullable', 'numeric', 'min:0'],
            'minOrderPHP' => ['nullable', 'numeric', 'min:0'],
            'maxUses' => ['nullable', 'integer', 'min:1'],
            'oneUsePerAccount' => ['nullable', 'boolean'],
            'productIds' => ['nullable', 'array'],
            'productIds.*' => ['string'],
            'startsAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->promotionsService->update($id, $data));
    }

    public function remove(string $id)
    {
        return response()->json($this->promotionsService->remove($id));
    }
}
