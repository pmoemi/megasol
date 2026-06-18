<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Integrations\PayGroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()->orderByDesc('updated_at');

        if ($search = $request->string('search')->toString()) {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('account_number', 'like', $term);
            });
        }

        if ($status = $request->string('payment_status')->toString()) {
            $query->where('payment_status', $status);
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function sync(PayGroService $payGro): JsonResponse
    {
        $result = $payGro->syncCustomers();

        return response()->json([
            'message' => 'Customer sync completed.',
            'data' => $result,
        ]);
    }
}
