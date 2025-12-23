<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use App\Models\ThemeSetting;
use App\Models\Slider;
use App\Models\Page;
use App\Models\Stock;
use App\Models\ProductVariation;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Coupon;
use App\Models\Address;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BackupService
{
    protected string $backupPath;

    public function __construct()
    {
        $this->backupPath = database_path('backups');
        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }
    }

    /**
     * Create a full backup of production data
     */
    public function createBackup(int $retention = 10): array
    {
        try {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "valdor_backup_{$timestamp}.json";

            $data = [
                'created_at' => Carbon::now()->toIso8601String(),
                'version' => '1.0',
                'tables' => [
                    'product_categories' => $this->backupProductCategories(),
                    'product_brands' => $this->backupProductBrands(),
                    'units' => $this->backupUnits(),
                    'taxes' => $this->backupTaxes(),
                    'products' => $this->backupProducts(),
                    'stocks' => $this->backupStocks(),
                    'product_variations' => $this->backupProductVariations(),
                    'users' => $this->backupUsers(),
                    'addresses' => $this->backupAddresses(),
                    'orders' => $this->backupOrders(),
                    'order_items' => $this->backupOrderItems(),
                    'sliders' => $this->backupSliders(),
                    'pages' => $this->backupPages(),
                    'coupons' => $this->backupCoupons(),
                    'settings' => $this->backupSettings(),
                    'media' => $this->backupMedia(),
                ]
            ];

            $filepath = $this->backupPath . DIRECTORY_SEPARATOR . $filename;
            File::put($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Keep only last N backups
            $this->cleanOldBackups($retention);

            return [
                'success' => true,
                'filename' => $filename,
                'path' => $filepath,
                'size' => $this->formatBytes(filesize($filepath)),
                'tables_count' => count($data['tables']),
                'created_at' => $data['created_at']
            ];
        } catch (\Exception $e) {
            Log::error('Backup failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Restore from a backup file
     */
    public function restoreBackup(string $filename): array
    {
        try {
            $filepath = $this->backupPath . DIRECTORY_SEPARATOR . $filename;

            if (!File::exists($filepath)) {
                throw new \Exception("Backup file not found: {$filename}");
            }

            $data = json_decode(File::get($filepath), true);

            if (!$data || !isset($data['tables'])) {
                throw new \Exception("Invalid backup file format");
            }

            $restored = [];

            // Disable foreign key checks for the entire restore operation
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            try {
                // Clear tables before restore (in reverse dependency order)
                Product::query()->delete();
                Tax::query()->delete();
                Unit::query()->delete();
                ProductBrand::query()->delete();
                ProductCategory::query()->delete();

                // Restore in order (dependencies first)
                if (isset($data['tables']['product_categories'])) {
                    $restored['product_categories'] = $this->restoreProductCategories($data['tables']['product_categories']);
                }
                if (isset($data['tables']['product_brands'])) {
                    $restored['product_brands'] = $this->restoreProductBrands($data['tables']['product_brands']);
                }
                if (isset($data['tables']['units'])) {
                    $restored['units'] = $this->restoreUnits($data['tables']['units']);
                }
                if (isset($data['tables']['taxes'])) {
                    $restored['taxes'] = $this->restoreTaxes($data['tables']['taxes']);
                }
                if (isset($data['tables']['products'])) {
                    $restored['products'] = $this->restoreProducts($data['tables']['products']);
                }
                if (isset($data['tables']['settings'])) {
                    $restored['settings'] = $this->restoreSettings($data['tables']['settings']);
                }

                return [
                    'success' => true,
                    'restored' => $restored,
                    'backup_date' => $data['created_at']
                ];
            } finally {
                // Always re-enable foreign key checks
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        } catch (\Exception $e) {
            // Ensure FK checks are re-enabled even on error
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            Log::error('Restore failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get list of available backups
     */
    public function listBackups(): array
    {
        $files = File::files($this->backupPath);
        $backups = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'json') {
                $data = json_decode(File::get($file->getPathname()), true);
                $backups[] = [
                    'filename' => $file->getFilename(),
                    'size' => $this->formatBytes($file->getSize()),
                    'created_at' => $data['created_at'] ?? $file->getMTime(),
                    'tables_count' => isset($data['tables']) ? count($data['tables']) : 0,
                ];
            }
        }

        // Sort by date descending
        usort($backups, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $backups;
    }

    /**
     * Delete a backup file
     */
    public function deleteBackup(string $filename): bool
    {
        $filepath = $this->backupPath . DIRECTORY_SEPARATOR . $filename;
        if (File::exists($filepath)) {
            return File::delete($filepath);
        }
        return false;
    }

    /**
     * Download backup file path
     */
    public function getBackupPath(string $filename): ?string
    {
        $filepath = $this->backupPath . DIRECTORY_SEPARATOR . $filename;
        return File::exists($filepath) ? $filepath : null;
    }

    // ===================== BACKUP METHODS =====================

    protected function backupProductCategories(): array
    {
        return ProductCategory::all()->toArray();
    }

    protected function backupProductBrands(): array
    {
        return ProductBrand::all()->toArray();
    }

    protected function backupUnits(): array
    {
        return Unit::all()->toArray();
    }

    protected function backupTaxes(): array
    {
        return Tax::all()->toArray();
    }

    protected function backupProducts(): array
    {
        return Product::with(['seo', 'tags', 'taxes'])->get()->toArray();
    }

    protected function backupStocks(): array
    {
        return Stock::all()->toArray();
    }

    protected function backupProductVariations(): array
    {
        return ProductVariation::all()->toArray();
    }

    protected function backupUsers(): array
    {
        // Backup only customers (not demo users)
        return User::whereHas('roles', function ($q) {
            $q->where('name', 'customer');
        })->with('addresses')->get()->toArray();
    }

    protected function backupAddresses(): array
    {
        return Address::all()->toArray();
    }

    protected function backupOrders(): array
    {
        return Order::with(['orderProducts'])->get()->toArray();
    }

    protected function backupOrderItems(): array
    {
        return DB::table('stocks')->whereNotNull('model_id')->get()->toArray();
    }

    protected function backupSliders(): array
    {
        return Slider::all()->toArray();
    }

    protected function backupPages(): array
    {
        return Page::all()->toArray();
    }

    protected function backupCoupons(): array
    {
        return Coupon::all()->toArray();
    }

    protected function backupSettings(): array
    {
        return DB::table('settings')->get()->toArray();
    }

    protected function backupMedia(): array
    {
        return Media::all()->map(function ($media) {
            return [
                'id' => $media->id,
                'model_type' => $media->model_type,
                'model_id' => $media->model_id,
                'collection_name' => $media->collection_name,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'disk' => $media->disk,
            ];
        })->toArray();
    }

    // ===================== RESTORE METHODS =====================

    /**
     * Convert ISO 8601 datetime to MySQL format
     */
    protected function convertDateTime($value): ?string
    {
        if (empty($value)) return null;
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clean data row by converting datetime fields
     */
    protected function cleanDataRow(array $item, array $validColumns): array
    {
        $cleaned = collect($item)->only($validColumns)->toArray();

        // Convert datetime fields
        if (isset($cleaned['created_at'])) {
            $cleaned['created_at'] = $this->convertDateTime($cleaned['created_at']);
        }
        if (isset($cleaned['updated_at'])) {
            $cleaned['updated_at'] = $this->convertDateTime($cleaned['updated_at']);
        }
        if (isset($cleaned['offer_start_date'])) {
            $cleaned['offer_start_date'] = $this->convertDateTime($cleaned['offer_start_date']);
        }
        if (isset($cleaned['offer_end_date'])) {
            $cleaned['offer_end_date'] = $this->convertDateTime($cleaned['offer_end_date']);
        }

        return $cleaned;
    }

    protected function restoreProductCategories(array $data): int
    {
        if (empty($data)) return 0;

        $validColumns = ['id', 'name', 'slug', 'description', 'status', 'parent_id', 'creator_type', 'creator_id', 'editor_type', 'editor_id', 'created_at', 'updated_at'];

        $cleanData = collect($data)->map(function ($item) use ($validColumns) {
            return $this->cleanDataRow($item, $validColumns);
        })->toArray();

        DB::table('product_categories')->insert($cleanData);
        return count($cleanData);
    }

    protected function restoreProductBrands(array $data): int
    {
        if (empty($data)) return 0;

        $validColumns = ['id', 'name', 'slug', 'description', 'status', 'creator_type', 'creator_id', 'editor_type', 'editor_id', 'created_at', 'updated_at'];

        $cleanData = collect($data)->map(function ($item) use ($validColumns) {
            return $this->cleanDataRow($item, $validColumns);
        })->toArray();

        DB::table('product_brands')->insert($cleanData);
        return count($cleanData);
    }

    protected function restoreUnits(array $data): int
    {
        if (empty($data)) return 0;

        $validColumns = ['id', 'name', 'code', 'status', 'created_at', 'updated_at'];

        $cleanData = collect($data)->map(function ($item) use ($validColumns) {
            return $this->cleanDataRow($item, $validColumns);
        })->toArray();

        DB::table('units')->insert($cleanData);
        return count($cleanData);
    }

    protected function restoreTaxes(array $data): int
    {
        if (empty($data)) return 0;

        $validColumns = ['id', 'name', 'code', 'tax_rate', 'status', 'created_at', 'updated_at'];

        $cleanData = collect($data)->map(function ($item) use ($validColumns) {
            return $this->cleanDataRow($item, $validColumns);
        })->toArray();

        DB::table('taxes')->insert($cleanData);
        return count($cleanData);
    }

    protected function restoreProducts(array $data): int
    {
        if (empty($data)) return 0;

        $validColumns = ['id', 'name', 'slug', 'sku', 'product_category_id', 'product_brand_id', 'barcode_id', 'unit_id', 'buying_price', 'selling_price', 'variation_price', 'status', 'order', 'can_purchasable', 'show_stock_out', 'file_attachment', 'maximum_purchase_quantity', 'low_stock_quantity_warning', 'weight', 'refundable', 'sell_by_fraction', 'description', 'add_to_flash_sale', 'discount', 'offer_start_date', 'offer_end_date', 'created_at', 'updated_at'];

        $cleanData = collect($data)->map(function ($item) use ($validColumns) {
            return $this->cleanDataRow($item, $validColumns);
        })->toArray();

        DB::table('products')->insert($cleanData);
        return count($cleanData);
    }

    protected function restoreSettings(array $data): int
    {
        $count = 0;
        foreach ($data as $item) {
            // Handle both array and object access, and check for key existence
            $key = is_array($item) ? ($item['key'] ?? null) : ($item->key ?? null);
            $payload = is_array($item) ? ($item['payload'] ?? null) : ($item->payload ?? null);
            $group = is_array($item) ? ($item['group'] ?? null) : ($item->group ?? null);

            if ($key === null) {
                continue; // Skip invalid entries
            }

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                [
                    'payload' => is_array($payload) ? json_encode($payload) : $payload,
                    'group' => $group
                ]
            );
            $count++;
        }
        return $count;
    }

    // ===================== HELPERS =====================

    protected function cleanOldBackups(int $keep = 10): void
    {
        $files = File::files($this->backupPath);
        $jsonFiles = array_filter($files, fn($f) => $f->getExtension() === 'json');

        // Sort by modification time descending
        usort($jsonFiles, fn($a, $b) => $b->getMTime() - $a->getMTime());

        // Delete old files
        foreach (array_slice($jsonFiles, $keep) as $file) {
            File::delete($file->getPathname());
        }
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
