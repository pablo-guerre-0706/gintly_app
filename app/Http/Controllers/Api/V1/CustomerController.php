<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\IndexCustomerRequest;
use App\Http\Requests\Api\V1\Customer\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Customers\CustomerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class CustomerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CustomerService $customers)
    {
    }

    public function index(IndexCustomerRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        // IndexCustomerRequest valida filtros/orden/paginación (contrato MOD-05).
        $customers = Customer::query()
            ->when(! $request->includesGeneric(), fn ($query) => $query->real())
            ->when(
                $request->validated('document_type'),
                fn ($query, $type) => $query->where('document_type', $type),
            )
            ->when(
                $request->has('is_active'),
                fn ($query) => $query->where('is_active', $request->boolean('is_active')),
            )
            ->when(
                $request->validated('search'),
                fn ($query, $search) => $query->where(fn ($sub) => $sub
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")),
            )
            ->orderBy($request->sortColumn('created_at'), $request->sortDirection('desc'))
            ->paginate($request->perPage());

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customers->crear($request->validated());

        return CustomerResource::make($customer)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        // El contrato: show incluye addresses[]. Eager-load para que el Resource las exponga.
        return CustomerResource::make($customer->load('addresses'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer); // candado del genérico + rango ROL-02

        $customer = $this->customers->actualizar($customer, $request->validated());

        return CustomerResource::make($customer);
    }

    public function destroy(Customer $customer): Response
    {
        $this->authorize('delete', $customer);

        $this->customers->eliminar($customer); // soft-delete + guardas genérico/CxC

        return response()->noContent();
    }
}
