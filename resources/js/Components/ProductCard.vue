<script setup>
import { Package, Plus } from 'lucide-vue-next';
import { useFormatCurrency } from '@/composables/useFormatCurrency';
import { useCart } from '@/composables/useCart';
import { computed } from 'vue';

const props = defineProps({
    product: { type: Object, required: true },
});

const emit = defineEmits(['click']);
const { formatRupiah } = useFormatCurrency();
const { getQuantityByProductId } = useCart();

const isOverStock = computed(() => {
    return getQuantityByProductId(props.product.id) >= props.product.stock;
});
</script>

<template>
    <button
        @click="(e) => $emit('click', product, e)"
        class="product-card"
        :class="{
            'product-card--out-of-stock': product.stock <= 0,
            'product-card--over-stock': product.stock > 0 && isOverStock
        }"
        :disabled="product.stock <= 0"
    >
        <!-- Photo -->
        <div class="product-card__photo">
            <img
                v-if="product.photo_url"
                :src="product.photo_url"
                :alt="product.name"
                loading="lazy"
                decoding="async"
                class="product-card__img"
            />
            <div v-else class="product-card__placeholder">
                <Package class="product-card__placeholder-icon" />
            </div>

            <!-- Badges -->
            <span v-if="product.stock <= 0" class="product-card__badge product-card__badge--out">
                Habis
            </span>
            <span v-else-if="product.stock <= product.min_stock_alert" class="product-card__badge product-card__badge--low">
                Sisa {{ product.stock }}
            </span>
            <span v-if="product.has_variants && product.stock > 0" class="product-card__badge product-card__badge--variant">
                VARIAN
            </span>
        </div>

        <!-- Info -->
        <div class="product-card__info">
            <p class="product-card__name" :title="product.name">{{ product.name }}</p>
            <p class="product-card__stock-text">Stok: {{ product.stock }}</p>
            <div class="product-card__footer">
                <div class="flex flex-col flex-1 min-w-0 mr-2">
                    <div v-if="product.is_discount_active" class="flex items-center gap-1 mb-0.5">
                        <span v-if="product.discount_type === 'percentage'" class="text-[10px] font-bold bg-red-100 text-red-600 px-1 py-0.5 rounded leading-none">{{ Number(product.discount_value) }}%</span>
                        <span class="product-card__price-original truncate">{{ formatRupiah(product.price) }}</span>
                    </div>
                    <p class="product-card__price" :class="{ 'text-red-600': product.is_discount_active }">
                        {{ product.is_discount_active ? formatRupiah(Math.max(0, product.price - product.calculated_discount_amount)) : formatRupiah(product.price) }}
                    </p>
                </div>
                <div class="product-card__add-btn mt-auto mb-auto">
                    <Plus class="product-card__add-icon" />
                </div>
            </div>
        </div>
    </button>
</template>

<style scoped>
.product-card {
    display: flex;
    flex-direction: column;
    border-radius: 12px;
    border: 1.5px solid #e1e5e8;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    overflow: hidden;
    outline: none;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 30, 43, 0.1);
    border-color: #c1ccd6;
}

.product-card:active {
    transform: scale(0.98);
    box-shadow: 0 2px 8px rgba(0, 30, 43, 0.08);
}

.product-card:focus-visible {
    box-shadow: 0 0 0 3px rgba(0, 104, 74, 0.25);
    border-color: #00684a;
}

.product-card--out-of-stock {
    opacity: 0.55;
    filter: grayscale(40%);
    cursor: not-allowed;
}

.product-card--out-of-stock:hover {
    transform: none;
    box-shadow: none;
}

.product-card--over-stock {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
}

/* Photo */
.product-card__photo {
    position: relative;
    aspect-ratio: 4 / 3;
    width: 100%;
    overflow: hidden;
    background: #f4f7f6;
}

.product-card__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card:hover .product-card__img {
    transform: scale(1.05);
}

.product-card__placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.product-card__placeholder-icon {
    width: 32px;
    height: 32px;
    color: #c1ccd6;
}

/* Badges */
.product-card__badge {
    position: absolute;
    top: 8px;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 9999px;
    line-height: 1.4;
    letter-spacing: 0.5px;
}

.product-card__badge--out {
    left: 8px;
    background: #ef4444;
    color: #ffffff;
}

.product-card__badge--low {
    left: 8px;
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
}

.product-card__badge--variant {
    right: 8px;
    background: #001e2b;
    color: #00ed64;
}

/* Info */
.product-card__info {
    padding: 10px 12px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.product-card__name {
    font-size: 13px;
    font-weight: 500;
    color: #001e2b;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    overflow-wrap: break-word;
    word-break: break-word;
}

.product-card__stock-text {
    font-size: 12px;
    color: #5c6c7a;
    margin-top: -4px;
}

.product-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.product-card__price {
    font-size: 13px;
    font-weight: 700;
    color: #001e2b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-card__price-original {
    font-size: 11px;
    color: #a8b3bc;
    text-decoration: line-through;
}

.product-card__add-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 9999px;
    background: #001e2b;
    flex-shrink: 0;
    transition: background 0.15s ease, transform 0.15s ease;
}

.product-card:hover .product-card__add-btn {
    background: #00684a;
    transform: scale(1.08);
}

.product-card__add-icon {
    width: 14px;
    height: 14px;
    color: #00ed64;
}
</style>
