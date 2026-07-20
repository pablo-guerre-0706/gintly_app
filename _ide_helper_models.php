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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductRecipe> $recipeItems
 * @property-read int|null $recipe_items_count
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

