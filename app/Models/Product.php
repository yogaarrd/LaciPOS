<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'category_id', 'discount_id', 'name', 'photo_path', 'cogs', 'price',
        'stock', 'min_stock_alert', 'is_active',
    ];

    protected $appends = ['photo_url', 'is_discount_active', 'calculated_discount_amount'];

    protected function casts(): array
    {
        return [
            'cogs' => 'decimal:2',
            'price' => 'decimal:2',
            'stock' => 'integer',
            'min_stock_alert' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // --- Relasi ---
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function discount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stockLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockLog::class);
    }

    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // --- Scopes ---
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'min_stock_alert');
    }

    // --- Accessors ---
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path) {
            return null;
        }

        return route('app.img', ['path' => $this->photo_path]);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock <= $this->min_stock_alert;
    }

    public function getHasVariantsAttribute(): bool
    {
        return $this->variants()->exists();
    }

    public function getIsDiscountActiveAttribute(): bool
    {
        return $this->discount_id ? ($this->discount?->is_discount_active ?? false) : false;
    }

    public function getCalculatedDiscountAmountAttribute(): float
    {
        if (!$this->is_discount_active || !$this->discount) {
            return 0.0;
        }

        $amount = $this->discount->discount_type === 'percentage'
            ? $this->price * ($this->discount->discount_value / 100)
            : $this->discount->discount_value;

        return (float) min($amount, $this->price);
    }
}
