<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StoreProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'store_name',
        'timezone',
        'address',
        'phone',
        'logo_path',
        'receipt_footer',
        'printer_name',
        'paper_size',
        'auto_print',
        'default_min_stock',
        'tax_enabled',
        'tax_type',
        'tax_value',
        'service_charge_enabled',
        'service_charge_type',
        'service_charge_value',
        'enable_customer_name',
        'enable_table_number',
        'enable_order_notes',
    ];

    protected function casts(): array
    {
        return [
            'auto_print' => 'boolean',
            'tax_enabled' => 'boolean',
            'tax_value' => 'decimal:2',
            'service_charge_enabled' => 'boolean',
            'service_charge_value' => 'decimal:2',
            'enable_customer_name' => 'boolean',
            'enable_table_number' => 'boolean',
            'enable_order_notes' => 'boolean',
        ];
    }

    /**
     * Ambil profil toko (hanya 1 record).
     * Jika belum ada, return null.
     */
    public static function getProfile(): ?self
    {
        if (app()->runningInConsole()) {
            return static::first();
        }
        $attributes = request()->attributes;
        if (!$attributes->has('laci.store_profile')) {
            $attributes->set('laci.store_profile', static::first());
        }
        return $attributes->get('laci.store_profile');
    }

    protected static function booted(): void
    {
        $forget = static function () {
            if (app()->bound('request')) {
                request()->attributes->remove('laci.store_profile');
            }
        };
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Cek apakah onboarding (setup toko) sudah selesai.
     * Onboarding dianggap selesai jika store_name sudah diisi.
     */
    public static function isOnboardingCompleted(): bool
    {
        $profile = static::getProfile();
        return $profile !== null && !empty($profile->store_name);
    }

    /**
     * Dapatkan URL logo toko.
     * Return null jika logo belum diupload.
     *
     * Menggunakan built-in Laravel 11 route 'storage.local' agar bekerja
     * di NativePHP desktop app tanpa bergantung pada symlink.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return route('app.img', ['path' => $this->logo_path]);
    }
}
