<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreCustomerAddressRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\Customers\CustomerAddressService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CustomerAddressController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CustomerAddressService $addresses)
    {
    }

    public function index(Request $request, Customer $customer): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomerAddress::class);

        $addresses = $customer->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return CustomerAddressResource::collection($addresses);
    }

    public function store(StoreCustomerAddressRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('create', [CustomerAddress::class, $customer]);

        $address = $this->addresses->crear($customer, $request->validated());

        return CustomerAddressResource::make($address)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Customer $customer, CustomerAddress $address): CustomerAddressResource
    {
        $this->authorize('view', $address);

        return CustomerAddressResource::make($address);
    }

    public function update(
        UpdateCustomerAddressRequest $request,
        Customer $customer,
        CustomerAddress $address,
    ): CustomerAddressResource {
        $this->authorize('update', $address);

        $address = $this->addresses->actualizar($address, $request->validated());

        return CustomerAddressResource::make($address);
    }

    public function destroy(Customer $customer, CustomerAddress $address): Response
    {
        $this->authorize('delete', $address);

        $this->addresses->eliminar($address);

        return response()->noContent();
    }
}
