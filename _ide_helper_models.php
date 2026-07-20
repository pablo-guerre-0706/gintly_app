<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property \App\Enums\AccountPayableStatus $status
 * @property-read string $balance
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\GoodsReceipt|null $goodsReceipt
 * @property-read \App\Models\PurchaseOrder|null $purchaseOrder
 * @property-read \App\Models\Supplier|null $supplier
 * @property-read \App\Models\User|null $unblockedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountPayable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountPayable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountPayable query()
 */
	class AccountPayable extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int|null $user_id
 * @property string $action
 * @property string $auditable_type
 * @property int|null $auditable_id
 * @property array<array-key, mixed>|null $old_values
 * @property array<array-key, mixed>|null $new_values
 * @property string|null $ip_address
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $auditable
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereAuditableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereAuditableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereUserId($value)
 */
	class AuditLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string $address
 * @property int|null $manager_user_id
 * @property \Illuminate\Support\Carbon $opened_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\User|null $manager
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereManagerUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereOpenedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch withoutTrashed()
 */
	class Branch extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand withoutTrashed()
 */
	class Brand extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $owner_user_id
 * @property string $plan
 * @property string $timezone
 * @property numeric $tax_rate
 * @property \App\Enums\BusinessStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Branch> $branches
 * @property-read int|null $branches_count
 * @property-read \App\Models\User|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereOwnerUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business wherePlan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereTaxRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business withoutTrashed()
 */
	class Business extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $cash_session_id
 * @property int $user_id
 * @property \App\Enums\CashMovementType $type
 * @property \App\Enums\CashMovementCategory $category
 * @property \App\Enums\PaymentMethod $payment_method
 * @property numeric $amount
 * @property int|null $sale_id
 * @property int|null $authorized_by
 * @property string|null $description
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property-read \App\Models\User|null $authorizedBy
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\CashSession $cashSession
 * @property-read \App\Models\Sale|null $sale
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereAuthorizedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereCashSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereSaleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashMovement whereUserId($value)
 */
	class CashMovement extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $branch_id
 * @property string $name
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashSession> $sessions
 * @property-read int|null $sessions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegister withoutTrashed()
 */
	class CashRegister extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $cash_register_id
 * @property int $opened_by
 * @property int|null $closed_by
 * @property \App\Enums\CashSessionStatus $status
 * @property numeric $opening_amount
 * @property numeric|null $expected_amount
 * @property numeric|null $counted_amount
 * @property array<array-key, mixed>|null $counted_denominations
 * @property \Illuminate\Support\Carbon $opened_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property string|null $closing_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $open_register_lock
 * @property int|null $open_user_lock
 * @property numeric|null $difference
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\CashRegister|null $cashRegister
 * @property-read \App\Models\User|null $closedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashMovement> $movements
 * @property-read int|null $movements_count
 * @property-read \App\Models\User|null $openedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereCashRegisterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereClosedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereClosedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereClosingNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereCountedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereCountedDenominations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereDifference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereExpectedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereOpenRegisterLock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereOpenUserLock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereOpenedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereOpenedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereOpeningAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashSession whereUpdatedAt($value)
 */
	class CashSession extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int|null $parent_id
 * @property string $name
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $children
 * @property-read int|null $children_count
 * @property-read Category|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category withoutTrashed()
 */
	class Category extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property \App\Enums\DocumentType $document_type
 * @property string|null $document_number
 * @property string|null $email
 * @property string|null $phone_number
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property bool $is_generic
 * @property bool $is_active
 * @property numeric $credit_limit
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $generic_lock
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomerAddress> $addresses
 * @property-read int|null $addresses_count
 * @property-read \App\Models\Business|null $business
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer real()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreditLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDocumentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDocumentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereGenericLock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereIsGeneric($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer withoutTrashed()
 */
	class Customer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $customer_id
 * @property string $label
 * @property string $address_line
 * @property string|null $reference
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\Customer|null $customer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress whereAddressLine($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerAddress whereUpdatedAt($value)
 */
	class CustomerAddress extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $document_type
 * @property string $prefix
 * @property int $next_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentSequence newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentSequence newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentSequence query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentSequence whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentSequence whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentSequence whereDocumentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentSequence whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentSequence whereNextNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentSequence wherePrefix($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DocumentSequence whereUpdatedAt($value)
 */
	class DocumentSequence extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $purchase_order_id
 * @property int $warehouse_id
 * @property int $user_id
 * @property string|null $supplier_invoice_number
 * @property numeric|null $supplier_invoice_total
 * @property \App\Enums\GoodsReceiptMatchStatus $match_status
 * @property \Illuminate\Support\Carbon $received_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GoodsReceiptItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\PurchaseOrder|null $purchaseOrder
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereMatchStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt wherePurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereReceivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereSupplierInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereSupplierInvoiceTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceipt whereWarehouseId($value)
 */
	class GoodsReceipt extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $goods_receipt_id
 * @property int $purchase_order_item_id
 * @property int $product_id
 * @property numeric $received_quantity
 * @property numeric $invoiced_unit_cost
 * @property numeric $line_total
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\GoodsReceipt $goodsReceipt
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\PurchaseOrderItem $purchaseOrderItem
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereGoodsReceiptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereInvoicedUnitCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereLineTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem wherePurchaseOrderItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GoodsReceiptItem whereReceivedQuantity($value)
 */
	class GoodsReceiptItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $warehouse_id
 * @property int $user_id
 * @property int|null $physical_count_id
 * @property \App\Enums\InventoryAdjustmentType $type
 * @property string $reason
 * @property \Illuminate\Support\Carbon $adjusted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InventoryMovement> $movements
 * @property-read int|null $movements_count
 * @property-read \App\Models\PhysicalCount|null $physicalCount
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment whereAdjustedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment wherePhysicalCountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryAdjustment whereWarehouseId($value)
 */
	class InventoryAdjustment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $product_id
 * @property int $warehouse_id
 * @property int|null $user_id
 * @property \App\Enums\InventoryMovementType $type
 * @property numeric $quantity
 * @property numeric $balance_after
 * @property numeric|null $unit_cost
 * @property int|null $stock_transfer_id
 * @property int|null $inventory_adjustment_id
 * @property int|null $purchase_order_id
 * @property int|null $dispatch_id
 * @property string|null $reason
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\InventoryAdjustment|null $inventoryAdjustment
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\PurchaseOrder|null $purchaseOrder
 * @property-read \App\Models\StockTransfer|null $stockTransfer
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereDispatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereInventoryAdjustmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement wherePurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereStockTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereUnitCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InventoryMovement whereWarehouseId($value)
 */
	class InventoryMovement extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $branch_id
 * @property int $customer_id
 * @property int|null $cash_session_id
 * @property int $issued_by
 * @property string $folio
 * @property \App\Enums\InvoicePaymentType $payment_type
 * @property \App\Enums\InvoicePaymentStatus $payment_status
 * @property \App\Enums\InvoiceStatus $status
 * @property numeric $subtotal
 * @property numeric $tax_amount
 * @property numeric $discount_amount
 * @property numeric $total
 * @property numeric $paid_amount
 * @property int|null $voided_by
 * @property \Illuminate\Support\Carbon|null $voided_at
 * @property string|null $void_reason
 * @property \Illuminate\Support\Carbon $issued_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\CashSession|null $cashSession
 * @property-read \App\Models\Customer|null $customer
 * @property-read \App\Models\User|null $issuedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoicePayment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Sale> $sales
 * @property-read int|null $sales_count
 * @property-read \App\Models\User|null $voidedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCashSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereFolio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssuedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePaidAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePaymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTaxAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereVoidReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereVoidedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereVoidedBy($value)
 */
	class Invoice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $invoice_id
 * @property int|null $cash_session_id
 * @property int $user_id
 * @property \App\Enums\PaymentMethod $payment_method
 * @property numeric $amount
 * @property string|null $reference
 * @property \Illuminate\Support\Carbon $paid_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\CashSession|null $cashSession
 * @property-read \App\Models\Invoice $invoice
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereCashSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoicePayment whereUserId($value)
 */
	class InvoicePayment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $product_id
 * @property int $warehouse_id
 * @property int $user_id
 * @property numeric $system_quantity
 * @property numeric $counted_quantity
 * @property numeric|null $difference
 * @property \App\Enums\PhysicalCountStatus $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $counted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InventoryAdjustment> $adjustments
 * @property-read int|null $adjustments_count
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereCountedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereCountedQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereDifference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereSystemQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhysicalCount whereWarehouseId($value)
 */
	class PhysicalCount extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $category_id
 * @property int|null $brand_id
 * @property int $unit_id
 * @property string $sku
 * @property string $name
 * @property \App\Enums\ProductType $type
 * @property numeric $sale_price
 * @property numeric $cost
 * @property bool $is_taxable
 * @property bool $tracks_inventory
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Brand|null $brand
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\Category|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $ingredients
 * @property-read int|null $ingredients_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InventoryMovement> $inventoryMovements
 * @property-read int|null $inventory_movements_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrderItem> $purchaseOrderItems
 * @property-read int|null $purchase_order_items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductRecipe> $recipeItems
 * @property-read int|null $recipe_items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleItem> $saleItems
 * @property-read int|null $sale_items_count
 * @property-read \App\Models\UnitOfMeasure $unit
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductRecipe> $usedAsIngredientIn
 * @property-read int|null $used_as_ingredient_in_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereBrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsTaxable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSalePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereTracksInventory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product withoutTrashed()
 */
	class Product extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $compound_id
 * @property int $ingredient_id
 * @property numeric $quantity
 * @property int $unit_id
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\Product|null $compound
 * @property-read \App\Models\Product|null $ingredient
 * @property-read \App\Models\UnitOfMeasure $unit
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductRecipe newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductRecipe newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductRecipe query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductRecipe whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductRecipe whereCompoundId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductRecipe whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductRecipe whereIngredientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductRecipe whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductRecipe whereUnitId($value)
 */
	class ProductRecipe extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $branch_id
 * @property int $supplier_id
 * @property int $user_id
 * @property string $code
 * @property \App\Enums\PurchaseOrderStatus $status
 * @property numeric $expected_total
 * @property \Illuminate\Support\Carbon $ordered_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccountPayable> $accountsPayable
 * @property-read int|null $accounts_payable_count
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GoodsReceipt> $goodsReceipts
 * @property-read int|null $goods_receipts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrderItem> $items
 * @property-read int|null $items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InventoryMovement> $movements
 * @property-read int|null $movements_count
 * @property-read \App\Models\Supplier|null $supplier
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereExpectedTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereOrderedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrder withoutTrashed()
 */
	class PurchaseOrder extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $purchase_order_id
 * @property int $product_id
 * @property numeric $ordered_quantity
 * @property numeric $received_quantity
 * @property numeric $agreed_unit_cost
 * @property numeric $line_total
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\PurchaseOrder|null $purchaseOrder
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem whereAgreedUnitCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem whereLineTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem whereOrderedQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem wherePurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurchaseOrderItem whereReceivedQuantity($value)
 */
	class PurchaseOrderItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $branch_id
 * @property int $customer_id
 * @property int $user_id
 * @property string $code
 * @property \App\Enums\SaleStatus $status
 * @property string|null $table_reference
 * @property numeric $subtotal
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $opened_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleItem> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereOpenedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereTableReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sale whereUserId($value)
 */
	class Sale extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $sale_id
 * @property int $product_id
 * @property array<array-key, mixed>|null $recipe_snapshot
 * @property string $description
 * @property numeric $quantity
 * @property numeric $unit_price
 * @property numeric $unit_cost
 * @property numeric $discount_amount
 * @property numeric $line_total
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\Sale $sale
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereLineTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereRecipeSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereSaleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereUnitCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SaleItem whereUnitPrice($value)
 */
	class SaleItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $product_id
 * @property int $warehouse_id
 * @property numeric $quantity
 * @property numeric|null $min_stock
 * @property numeric|null $max_stock
 * @property numeric $reserved_quantity
 * @property numeric $average_cost
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $available
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\Product|null $product
 * @property-read \App\Models\Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel whereAverageCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel whereMaxStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel whereMinStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel whereReservedQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockLevel whereWarehouseId($value)
 */
	class StockLevel extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $from_warehouse_id
 * @property int $to_warehouse_id
 * @property int $user_id
 * @property string $code
 * @property \App\Enums\StockTransferStatus $status
 * @property \Illuminate\Support\Carbon $transferred_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \App\Models\Warehouse|null $fromWarehouse
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InventoryMovement> $movements
 * @property-read int|null $movements_count
 * @property-read \App\Models\Warehouse|null $toWarehouse
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereFromWarehouseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereToWarehouseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereTransferredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer whereUserId($value)
 */
	class StockTransfer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $tax_id
 * @property string|null $email
 * @property string|null $phone
 * @property \App\Enums\SupplierStatus $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccountPayable> $accountsPayable
 * @property-read int|null $accounts_payable_count
 * @property-read \App\Models\User|null $approvedBy
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrder> $purchaseOrders
 * @property-read int|null $purchase_orders_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereTaxId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supplier withoutTrashed()
 */
	class Supplier extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string $abbreviation
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductRecipe> $recipeLines
 * @property-read int|null $recipe_lines_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereAbbreviation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitOfMeasure whereUpdatedAt($value)
 */
	class UnitOfMeasure extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int|null $branch_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Branch> $managedBranches
 * @property-read int|null $managed_branches_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\Business|null $ownedBusiness
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User team($teams, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $branch_id
 * @property string $name
 * @property bool $is_default
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $default_lock
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InventoryAdjustment> $adjustments
 * @property-read int|null $adjustments_count
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StockTransfer> $incomingTransfers
 * @property-read int|null $incoming_transfers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InventoryMovement> $movements
 * @property-read int|null $movements_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StockTransfer> $outgoingTransfers
 * @property-read int|null $outgoing_transfers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PhysicalCount> $physicalCounts
 * @property-read int|null $physical_counts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StockLevel> $stockLevels
 * @property-read int|null $stock_levels_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereDefaultLock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Warehouse withoutTrashed()
 */
	class Warehouse extends \Eloquent {}
}

