<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContractController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ContractResource::collection(
            Contract::orderBy('customer_name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name'   => ['required', 'string', 'max:255'],
            'annual_usage'    => ['required', 'numeric', 'min:0'],
            'contract_value'  => ['required', 'numeric', 'min:0'],
            'contract_length' => ['required', 'integer', 'min:1'],
            'risk_score'      => ['required', 'numeric', 'min:0', 'max:10'],
        ]);

        $contract = Contract::create($data);

        return (new ContractResource($contract))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $id): ContractResource
    {
        $contract = Contract::findOrFail($id);

        $data = $request->validate([
            'customer_name'   => ['sometimes', 'string', 'max:255'],
            'annual_usage'    => ['sometimes', 'numeric', 'min:0'],
            'contract_value'  => ['sometimes', 'numeric', 'min:0'],
            'contract_length' => ['sometimes', 'integer', 'min:1'],
            'risk_score'      => ['sometimes', 'numeric', 'min:0', 'max:10'],
        ]);

        $contract->update($data);

        return new ContractResource($contract->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        Contract::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
