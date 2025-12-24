# Complete Governorate-Based Delivery Fee Implementation Guide

## Overview

This guide documents **EVERY** change made to convert the delivery fee system from radius/distance-based to governorate-based with fixed fees. Follow these steps **EXACTLY** to replicate on a clone project.

---

## Part 1: Database Migrations

### Migration 1: Update Delivery Zones Table

**File:** `database/migrations/2025_12_24_073000_update_delivery_zones_table.php`

**Create this migration:**

```bash
php artisan make:migration update_delivery_zones_table
```

**Full migration code:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            // ADD these new columns
            $table->string('governorate_name')->nullable()->after('name');
            $table->decimal('delivery_fee', 8, 2)->default(0)->after('delivery_charge_per_kilo');

            // MAKE NULLABLE - Old location-based fields
            $table->string('latitude')->nullable()->change();
            $table->string('longitude')->nullable()->change();
            $table->string('delivery_radius_kilometer')->nullable()->change();
            $table->text('address')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->dropColumn(['governorate_name', 'delivery_fee']);
        });
    }
};
```

### Migration 2: Make Delivery Charge Nullable

**File:** `database/migrations/2025_12_24_074500_make_delivery_charge_nullable.php`

**Create this migration:**

```bash
php artisan make:migration make_delivery_charge_nullable
```

**Full migration code:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            // CRITICAL: Make this nullable for new governorate-based logic
            $table->decimal('delivery_charge_per_kilo', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->decimal('delivery_charge_per_kilo', 8, 2)->nullable(false)->change();
        });
    }
};
```

**Run migrations:**

```bash
php artisan migrate
```

---

## Part 2: Backend Model & Validation Updates

### A. DeliveryZone Model

**File:** `app/Models/DeliveryZone.php`

**FIND the `$fillable` array and ADD:**

```php
protected $fillable = [
    'name',
    'governorate_name',  // ADD THIS
    'email',
    'phone',
    'latitude',
    'longitude',
    'delivery_radius_kilometer',
    'delivery_charge_per_kilo',
    'delivery_fee',  // ADD THIS
    'minimum_order_amount',
    'address',
    'status',
];
```

**FIND the `$casts` array and ADD:**

```php
protected $casts = [
    'id'                           => 'integer',
    'name'                         => 'string',
    'governorate_name'             => 'string',  // ADD THIS
    'email'                        => 'string',
    'phone'                        => 'string',
    'latitude'                     => 'string',
    'longitude'                    => 'string',
    'delivery_radius_kilometer'    => 'decimal:6',
    'delivery_charge_per_kilo'     => 'decimal:6',
    'delivery_fee'                 => 'decimal:6',  // ADD THIS
    'minimum_order_amount'         => 'decimal:6',
    'address'                      => 'string',
    'status'                       => 'integer',
];
```

### B. DeliveryZoneRequest Validation

**File:** `app/Http/Requests/DeliveryZoneRequest.php`

**REPLACE the entire `rules()` method:**

```php
public function rules(): array
{
    return [
        'name'                      => [
            'required',
            'string',
            'max:190',
            Rule::unique("delivery_zones", "name")->ignore($this->route('deliveryZone.id'))
        ],
        'governorate_name'          => ['nullable', 'string', 'max:100'],  // CHANGED: was not present
        'email'                     => ['nullable', 'email', 'max:190'],
        'phone'                     => ['nullable', 'string', 'max:20'],
        'latitude'                  => ['nullable', 'max:190'],              // CHANGED: was 'required'
        'longitude'                 => ['nullable', 'max:190'],              // CHANGED: was 'required'
        'delivery_radius_kilometer' => ['nullable', 'numeric'],              // CHANGED: was 'required'
        'delivery_charge_per_kilo'  => ['nullable', 'numeric'],              // CHANGED: was 'required'
        'delivery_fee'              => ['nullable', 'numeric'],              // ADD THIS
        'minimum_order_amount'      => ['required', 'numeric'],
        'address'                   => ['nullable', 'string', 'max:500'],    // CHANGED: was 'required'
        'status'                    => ['required', 'numeric', 'max:24'],
    ];
}
```

---

## Part 3: Backend Service Logic

### DeliveryZoneService

**File:** `app/Services/DeliveryZoneService.php`

**FIND the `deliveryZoneCheck` method (around line 128-144) and REPLACE with:**

```php
public function deliveryZoneCheck(Request $request)
{
    try {
        // CHANGED: New logic to check by governorate first
        $addressId = $request->address_id ?? $request->id;  // Support both field names
        
        if ($addressId) {
            $address = Address::find($addressId);
            if ($address && $address->governorate) {
                // Find delivery zone for this specific governorate
                $deliveryZone = DeliveryZone::where('governorate_name', $address->governorate)
                    ->where('status', Status::ACTIVE)
                    ->first();

                if ($deliveryZone) {
                    return $deliveryZone;
                }
            }
        }

        // Fallback 1: General Zone (null governorate_name = serves all areas)
        $deliveryZone = DeliveryZone::where('status', Status::ACTIVE)
            ->whereNull('governorate_name')
            ->first();
        
        if ($deliveryZone) {
            return $deliveryZone;
        }

        // Fallback 2: Any active zone
        $deliveryZone = DeliveryZone::where('status', Status::ACTIVE)->first();
        if ($deliveryZone) {
            return $deliveryZone;
        }

        throw new Exception(trans('all.message.out_of_delivery_zone'), 422);

    } catch (Exception $exception) {
        Log::info($exception->getMessage());
        throw new Exception(QueryExceptionLibrary::message($exception), 422);
    }
}
```

**OLD CODE (what to REPLACE):**

```php
// The old version used latitude/longitude distance calculation
// It checked coordinates and calculated radius
// COMPLETELY REPLACE with the new governorate-based logic above
```

---

## Part 4: Backend API Resource

### DeliveryZoneResource

**File:** `app/Http/Resources/DeliveryZoneResource.php`

**FIND the `toArray` method and UPDATE the return array to include:**

```php
public function toArray($request): array
{
    return [
        "id"                            => $this->id,
        "name"                          => $this->name,
        "governorate_name"              => $this->governorate_name,  // ADD THIS
        "email"                         => $this->email === null ? '' : $this->email,
        "phone"                         => $this->phone === null ? '' : $this->phone,
        "latitude"                      => $this->latitude === null ? '' : $this->latitude,
        "longitude"                     => $this->longitude === null ? '' : $this->longitude,
        "address"                       => $this->address,
        "delivery_radius_kilometer"     => $this->delivery_radius_kilometer,
        "delivery_charge_per_kilo"      => AppLibrary::flatAmountFormat($this->delivery_charge_per_kilo),
        "delivery_fee"                  => AppLibrary::flatAmountFormat($this->delivery_fee),  // ADD THIS
        "currency_delivery_charge"      => AppLibrary::currencyAmountFormat($this->delivery_charge_per_kilo),
        "currency_delivery_fee"         => AppLibrary::currencyAmountFormat($this->delivery_fee),  // ADD THIS
        "minimum_order_amount"          => AppLibrary::flatAmountFormat($this->minimum_order_amount),
        "currency_minimum_order_amount" => AppLibrary::currencyAmountFormat($this->minimum_order_amount),
        "status"                        => $this->status,
    ];
}
```

---

## Part 5: Critical Order Service Fix

### FrontendOrderService - OrderAddress Creation

**File:** `app/Services/FrontendOrderService.php`

**FIND the `myOrderStore` method, specifically the OrderAddress::create() call (around line 180-188)**

**REPLACE the OLD code:**

```php
// OLD CODE - REMOVE THIS
OrderAddress::create([
    'order_id'  => $this->order->id,
    'user_id'   => Auth::user()->id,
    'label'     => $address->label ?? $request->label,
    'latitude'  => $address->latitude ?? $request->latitude,    // WRONG
    'longitude' => $address->longitude ?? $request->longitude,  // WRONG
    'address'   => $address->address ?? $request->address,      // WRONG
    'phone'     => $address->phone ?? $request->phone
]);
```

**WITH NEW CODE:**

```php
// NEW CODE - Use manual address fields
OrderAddress::create([
    'order_id'        => $this->order->id,
    'user_id'         => Auth::user()->id,
    'label'           => $address->label ?? $request->label,
    'governorate'     => $address->governorate ?? $request->governorate,          // ADD
    'city'            => $address->city ?? $request->city,                        // ADD
    'street'          => $address->street ?? $request->street,                    // ADD
    'building_number' => $address->building_number ?? $request->building_number,  // ADD
    'apartment'       => $address->apartment ?? $request->apartment,              // ADD
    'full_address'    => $address->full_address ?? $request->full_address,        // ADD
    'phone'           => $address->phone ?? $request->phone
]);
```

**This fix is CRITICAL - it resolves the "Field 'governorate' doesn't have a default value" error.**

---

## Part 6: Frontend Admin Panel - Vue Components

### A. DeliveryZoneCreateComponent.vue

**File:** `resources/js/components/admin/settings/DeliveryZone/DeliveryZoneCreateComponent.vue`

#### Changes to `data()`

**ADD this array of Egyptian governorates:**

```javascript
data() {
    return {
        // ... existing data
        governorates: [
            'القاهرة',
            'الجيزة',
            'الإسكندرية',
            'القليوبية',
            'الشرقية',
            'الدقهلية',
            'البحيرة',
            'كفر الشيخ',
            'الغربية',
            'المنوفية',
            'دمياط',
            'بورسعيد',
            'الإسماعيلية',
            'السويس',
            'شمال سيناء',
            'جنوب سيناء',
            'المنيا',
            'بني سويف',
            'الفيوم',
            'أسيوط',
            'سوهاج',
            'قنا',
            'الأقصر',
            'أسوان',
            'البحر الأحمر',
            'الوادي الجديد',
            'مطروح'
        ]
    }
}
```

#### Template Changes

**REMOVE these fields (around lines where map inputs were):**

```html
<!-- REMOVE: Map component imports and usage -->
<!-- REMOVE: Latitude input -->
<!-- REMOVE: Longitude input -->
<!-- REMOVE: Radius input -->
<!-- REMOVE: Any map-related div containers -->
```

**ADD these new fields (after the name field):**

```html
<div class="form-col-12 sm:form-col-6">
    <label for="governorate_name" class="db-field-title required">
        {{ $t("label.governorate") }}
    </label>
    <select 
        v-model="props.form.governorate_name" 
        v-bind:class="errors.governorate_name ? 'invalid' : ''"
        id="governorate_name" 
        class="db-field-control">
        <option value="">{{ $t("label.select_governorate") }}</option>
        <option v-for="gov in governorates" :key="gov" :value="gov">{{ gov }}</option>
    </select>
    <small class="db-field-alert" v-if="errors.governorate_name">
        {{ errors.governorate_name[0] }}
    </small>
</div>

<div class="form-col-12 sm:form-col-6">
    <label for="delivery_fee" class="db-field-title required">
        {{ $t("label.delivery_fee") }}
    </label>
    <input 
        v-model="props.form.delivery_fee"
        v-bind:class="errors.delivery_fee ? 'invalid' : ''" 
        type="text"
        id="delivery_fee" 
        class="db-field-control" />
    <small class="db-field-alert" v-if="errors.delivery_fee">
        {{ errors.delivery_fee[0] }}
    </small>
</div>
```

#### Methods Changes - FIX null reference errors

**FIND the `reset()` method and REPLACE with null-safe version:**

```javascript
reset: function () {
    // ADD null checks to prevent "Cannot read properties of undefined"
    if (this.props && this.props.form) {
        this.props.form = {
            name: "",
            governorate_name: "",  // ADD
            email: "",
            phone: "",
            latitude: "",
            longitude: "",
            delivery_radius_kilometer: "",
            delivery_charge_per_kilo: "",
            delivery_fee: "",  // ADD
            minimum_order_amount: "",
            address: "",
            status: 5,
        };
    }
    
    if (this.$props && this.$props.props && this.$props.props.form) {
        this.$props.props.form = {
            name: "",
            governorate_name: "",  // ADD
            email: "",
            phone: "",
            latitude: "",
            longitude: "",
            delivery_radius_kilometer: "",
            delivery_charge_per_kilo: "",
            delivery_fee: "",  // ADD
            minimum_order_amount: "",
            address: "",
            status: 5,
        };
    }
    
    this.errors = {};
},
```

**FIND the `save()` method success callback and UPDATE:**

```javascript
save: function () {
    try {
        const fd = new FormData();
        // ... existing FormData appends
        fd.append("governorate_name", this.props.form.governorate_name);  // ADD
        fd.append("delivery_fee", this.props.form.delivery_fee);          // ADD
        // ... rest of the method
        
        // In success callback, add null check:
        .then((res) => {
            if (this.props && this.props.form) {  // ADD this check
                this.reset();
            }
            alertService.successFlip(
                this.$props.editId === null ? 1 : 0,
                this.$t("label.delivery_zone")
            );
            this.props.form = {
                name: "",
                governorate_name: "",  // ADD
                // ... rest of reset
            };
            // ... existing code
        })
    } catch (err) {
        // ...
    }
}
```

### B. DeliveryZoneListComponent.vue

**File:** `resources/js/components/admin/settings/DeliveryZone/DeliveryZoneListComponent.vue`

**FIND the `edit` method (around line 151-157) and UPDATE to include new fields:**

```javascript
edit: function (deliveryZone) {
    composables.openModal('modal');
    this.loading.isActive = true;
    this.$store.dispatch("deliveryZone/edit", deliveryZone.id);
    this.props.form = {
        name: deliveryZone.name,
        governorate_name: deliveryZone.governorate_name,  // ADD THIS
        email: deliveryZone.email,
        phone: deliveryZone.phone,
        latitude: deliveryZone.latitude,
        longitude: deliveryZone.longitude,
        delivery_radius_kilometer: deliveryZone.delivery_radius_kilometer,
        delivery_charge_per_kilo: deliveryZone.delivery_charge_per_kilo,
        delivery_fee: deliveryZone.delivery_fee,  // ADD THIS
        minimum_order_amount: deliveryZone.minimum_order_amount,
        address: deliveryZone.address,
        status: deliveryZone.status,
    };
    this.loading.isActive = false;
},
```

---

## Part 7: Frontend Cart Logic

### frontendCart.js Vuex Store

**File:** `resources/js/store/modules/frontend/frontendCart.js`

**FIND the `deliveryCharge` mutation (around line 312-315) and REPLACE:**

**OLD CODE:**

```javascript
deliveryCharge: (state) => {
    state.deliveryCharge = parseFloat(state.deliveryZone.delivery_charge_per_kilo);
}
```

**NEW CODE:**

```javascript
deliveryCharge: (state) => {
    if (state.deliveryZone) {
        // Priority: delivery_fee (new) -> delivery_charge_per_kilo (fallback) -> 0
        state.deliveryCharge = parseFloat(state.deliveryZone.delivery_fee) || 
                               parseFloat(state.deliveryZone.delivery_charge_per_kilo) || 
                               0;
    } else {
        state.deliveryCharge = 0;
    }
}
```

---

## Part 8: Language Files

### English Translations

**File:** `lang/en/all.php`

**ADD to the `label` array (around line 37-38):**

```php
'governorate'             => 'Governorate',
'select_governorate'      => 'Select Governorate',
'delivery_fee'            => 'Delivery Fee',
'currency_delivery_fee'   => 'Delivery Fee',
```

### Arabic Translations

**File:** `lang/ar/all.php`

**ADD to the `label` array (around line 37-38):**

```php
'governorate'             => 'المحافظة',
'select_governorate'      => 'اختر المحافظة',
'delivery_fee'            => 'رسوم التوصيل',
'currency_delivery_fee'   => 'رسوم التوصيل',
```

---

## Part 9: Optional - CheckoutComponent Update

### CheckoutComponent.vue

**File:** `resources/js/components/frontend/checkout/checkout/CheckoutComponent.vue`

**FIND the `deliveryAddress` method and ensure it passes the full address object:**

```javascript
deliveryAddress: async function (address) {
    // Ensure we're passing the address object (not just ID) for governorate lookup
    await this.$store.dispatch("frontendDeliveryZone/selectDeliveryZone", address);
}
```

---

## Part 10: Deployment Checklist

### Local Development (Windows)

```bash
# 1. Commit changes
git add .
git commit -m "Implement governorate-based delivery fees"
git push origin main

# 2. Run migrations locally
php artisan migrate

# 3. Build frontend assets
npm install
npm run build
```

### Production Server (SSH Linux)

```bash
# 1. Navigate to project
cd /var/www/sheikel3ashabeen

# 2. Pull latest code
sudo git pull origin main

# 3. Run migrations
php artisan migrate --force

# 4. Install & build
npm install
npm run build

# 5. Clear all caches
php artisan optimize:clear

# 6. Restart PHP-FPM (adjust version as needed)
sudo systemctl reload php8.2-fpm
# OR
sudo service php8.2-fpm restart
```

---

## Part 11: Troubleshooting Guide

### Issue 1: "Integrity constraint violation: Column 'delivery_charge_per_kilo' cannot be null"

**Solution:** Run the second migration to make this column nullable:

```bash
php artisan migrate
```

### Issue 2: "TypeError: Cannot read properties of undefined (reading 'name')"

**Solution:** This was fixed in DeliveryZoneCreateComponent.vue by adding null checks. Ensure you have the updated `reset()` and `save()` methods.

### Issue 3: "Field 'governorate' doesn't have a default value"

**Solution:** Update FrontendOrderService.php to use the new address fields (governorate, city, street) instead of old fields (latitude, longitude, address).

### Issue 4: "Add to Cart" button disabled / "Out of Stock"

**Root Cause:** Product stock quantity is 0 in database.

**Investigation Code:**

```php
$product = App\Models\Product::find(PRODUCT_ID);
echo 'Stock: ' . $product->stocks()->where('status', 5)->sum('quantity');
echo 'Show Stock Out: ' . $product->show_stock_out;
echo 'Can Purchasable: ' . $product->can_purchasable;
```

**Solution:**

1. Go to Admin Panel → Products
2. Edit the product
3. Adjust stock to be > 0
4. Save

**Note:** This is a data issue, not a code issue. Likely caused by backup restore or database seeding that didn't include stock records.

---

## Part 12: Testing Checklist

After deployment, verify:

- [ ] Admin can create delivery zone with governorate
- [ ] Admin can edit existing delivery zones
- [ ] Customer can select address with governorate
- [ ] Delivery fee is calculated based on governorate (not distance)
- [ ] Order creation works without "governorate" error
- [ ] Checkout flow completes successfully
- [ ] Order address stored with new fields (governorate, city, street)
- [ ] Products with stock > 0 show "Add to Cart" button enabled

---

## Summary of Files Modified

### Backend PHP Files (8)

1. `database/migrations/YYYY_MM_DD_HHMMSS_update_delivery_zones_table.php` ✅ NEW
2. `database/migrations/YYYY_MM_DD_HHMMSS_make_delivery_charge_nullable.php` ✅ NEW
3. `app/Models/DeliveryZone.php` ✏️ MODIFIED
4. `app/Http/Requests/DeliveryZoneRequest.php` ✏️ MODIFIED
5. `app/Services/DeliveryZoneService.php` ✏️ MODIFIED
6. `app/Http/Resources/DeliveryZoneResource.php` ✏️ MODIFIED
7. `app/Services/FrontendOrderService.php` ✏️ MODIFIED (CRITICAL FIX)
8. `lang/en/all.php` + `lang/ar/all.php` ✏️ MODIFIED

### Frontend JavaScript/Vue Files (3)

1. `resources/js/components/admin/settings/DeliveryZone/DeliveryZoneCreateComponent.vue` ✏️ MODIFIED (MAJOR CHANGES)
2. `resources/js/components/admin/settings/DeliveryZone/DeliveryZoneListComponent.vue` ✏️ MODIFIED
3. `resources/js/store/modules/frontend/frontendCart.js` ✏️ MODIFIED

---

## Key Architectural Changes

**Old System:**

- DeliveryZone had: `latitude`, `longitude`, `radius`
- Service calculated distance using coordinates
- Fee = `delivery_charge_per_kilo` × distance

**New System:**

- DeliveryZone has: `governorate_name`, `delivery_fee`
- Service looks up zone by governorate (exact match)
- Fee = Fixed `delivery_fee` from matched zone

**Backward Compatibility:**

- Old fields remain in DB (nullable)
- Fallback to `delivery_charge_per_kilo` if `delivery_fee` is null
- General zone (null governorate) serves as default

---

**END OF GUIDE**
