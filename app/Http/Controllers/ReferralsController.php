<?php

namespace App\Http\Controllers;

use App\Services\ReferralsService;
use Illuminate\Http\Request;

class ReferralsController extends Controller
{
    public function __construct(
        private readonly ReferralsService $referralsService,
    ) {}

    public function recordClick(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:2', 'max:64'],
            'productId' => ['nullable', 'string'],
            'landingPath' => ['nullable', 'string', 'max:500'],
            'sessionId' => ['nullable', 'string', 'max:128'],
        ]);

        return response()->json($this->referralsService->recordClick($data));
    }

    public function findAllPartners(Request $request)
    {
        return response()->json($this->referralsService->findAllPartners($request->query('search')));
    }

    public function createPartner(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'code' => ['required', 'string', 'min:2', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'commissionRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'productIds' => ['required', 'array', 'min:1'],
            'productIds.*' => ['string'],
            'isActive' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json($this->referralsService->createPartner($data), 201);
    }

    public function updatePartner(Request $request, string $id)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'min:2', 'max:120'],
            'code' => ['nullable', 'string', 'min:2', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'commissionRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'productIds' => ['nullable', 'array', 'min:1'],
            'productIds.*' => ['string'],
            'isActive' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json($this->referralsService->updatePartner($id, $data));
    }

    public function removePartner(string $id)
    {
        return response()->json($this->referralsService->removePartner($id));
    }

    public function partnerStats(string $id)
    {
        return response()->json($this->referralsService->getPartnerStats($id));
    }

    public function findAllCommissions(Request $request)
    {
        return response()->json(
            $this->referralsService->findAllCommissions($request->query('partnerId')),
        );
    }
}
