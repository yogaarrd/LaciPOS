import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export interface CartItem {
    cartId: string;
    productId: string;
    productName: string;
    variantLabel: string | null;
    price: number;
    cogs: number;
    quantity: number;
    stock: number;
    notes: string;
    photoUrl: string | null;
    discountType?: string | null;
    discountValue?: number | null;
    discountQuotaRemaining?: number | null;
}

const items = ref<CartItem[]>([]);
const cartDiscountType = ref<string | null>(null);
const cartDiscountValue = ref<number | null>(null);
const cartDiscountNote = ref<string | null>(null);

const quantitiesByProduct = computed(() => {
    const quantities = new Map<string, { total: number; discounted: number }>();
    for (const item of items.value) {
        const quantity = quantities.get(item.productId) ?? { total: 0, discounted: 0 };
        quantity.total += item.quantity;
        if (item.discountType && item.discountValue) quantity.discounted += item.quantity;
        quantities.set(item.productId, quantity);
    }
    return quantities;
});

export function useCart() {
    const page = usePage();
    const storeSettings = computed(() => page.props.storeSettings as any);

    /**
     * Tambah item ke cart.
     * Jika item dengan productId + variantLabel yang sama sudah ada, tambah quantity.
     */
    const addItem = (item: Omit<CartItem, 'cartId' | 'quantity' | 'notes'>): void => {
        const hasDiscount = !!(item.discountType && item.discountValue);
        const cartId = `${item.productId}_${item.variantLabel || 'default'}_${hasDiscount ? 'discount' : 'normal'}`;

        const existing = items.value.find(i => i.cartId === cartId);
        if (existing) {
            existing.quantity += 1;
        } else {
            items.value.push({
                ...item,
                cartId,
                quantity: 1,
                notes: '',
            });
        }
    };

    /**
     * Hapus item dari cart berdasarkan cartId.
     */
    const removeItem = (cartId: string): void => {
        items.value = items.value.filter(i => i.cartId !== cartId);
    };

    /**
     * Update quantity item. Jika quantity <= 0, hapus item.
     */
    const updateQuantity = (cartId: string, quantity: number): void => {
        if (quantity <= 0) {
            removeItem(cartId);
            return;
        }
        const item = items.value.find(i => i.cartId === cartId);
        if (item) {
            item.quantity = quantity;
        }
    };

    /**
     * Update catatan per item.
     */
    const updateNotes = (cartId: string, notes: string): void => {
        const item = items.value.find(i => i.cartId === cartId);
        if (item) {
            item.notes = notes;
        }
    };

    /**
     * Kosongkan seluruh cart dan hapus diskon keranjang.
     */
    const clearCart = (): void => {
        items.value = [];
        cartDiscountType.value = null;
        cartDiscountValue.value = null;
        cartDiscountNote.value = null;
    };

    /**
     * Hitung subtotal per item = (price - diskon) × quantity.
     */
    const getSubtotal = (cartId: string): number => {
        const item = items.value.find(i => i.cartId === cartId);
        if (!item) return 0;
        let itemDiscountAmount = 0;
        if (item.discountType && item.discountValue) {
            if (item.discountType === 'percentage') {
                itemDiscountAmount = item.price * (item.discountValue / 100);
            } else {
                itemDiscountAmount = Number(item.discountValue);
            }
        }
        return Math.max(0, (item.price - itemDiscountAmount)) * item.quantity;
    };

    /**
     * Subtotal seluruh cart (setelah diskon per item).
     */
    const subtotal = computed<number>(() => {
        return items.value.reduce((sum, item) => sum + getSubtotal(item.cartId), 0);
    });

    /**
     * Total Diskon Keranjang
     */
    const cartDiscountAmount = computed<number>(() => {
        if (!cartDiscountType.value || !cartDiscountValue.value) return 0;
        if (cartDiscountType.value === 'percentage') {
            return subtotal.value * (cartDiscountValue.value / 100);
        }
        return Number(cartDiscountValue.value);
    });

    /**
     * Subtotal setelah Diskon Keranjang
     */
    const subtotalAfterCartDiscount = computed<number>(() => {
        return Math.max(0, subtotal.value - cartDiscountAmount.value);
    });

    const taxType = computed<string>(() => storeSettings.value?.tax_type || 'percentage');
    const taxValue = computed<number>(() => Number(storeSettings.value?.tax_value) || 0);

    const taxAmount = computed<number>(() => {
        const settings = storeSettings.value;
        if (!settings?.tax_enabled) return 0;
        
        const value = taxValue.value;
        if (taxType.value === 'percentage') {
            return subtotalAfterCartDiscount.value * (value / 100);
        }
        return value;
    });

    const serviceChargeType = computed<string>(() => storeSettings.value?.service_charge_type || 'percentage');
    const serviceChargeValue = computed<number>(() => Number(storeSettings.value?.service_charge_value) || 0);

    const serviceChargeAmount = computed<number>(() => {
        const settings = storeSettings.value;
        if (!settings?.service_charge_enabled) return 0;
        
        const value = serviceChargeValue.value;
        if (serviceChargeType.value === 'percentage') {
            return subtotalAfterCartDiscount.value * (value / 100);
        }
        return value;
    });

    /**
     * Total akhir cart (subtotal setelah diskon + tax + service charge).
     */
    const finalTotal = computed<number>(() => {
        return subtotalAfterCartDiscount.value + taxAmount.value + serviceChargeAmount.value;
    });

    /**
     * Total item di cart.
     */
    const totalItems = computed<number>(() => {
        return items.value.reduce((sum, item) => sum + item.quantity, 0);
    });

    /**
     * Apakah cart kosong?
     */
    const isEmpty = computed<boolean>(() => items.value.length === 0);

    const getQuantityByProductId = (productId: string): number => {
        return quantitiesByProduct.value.get(productId)?.total ?? 0;
    };

    const getDiscountedQuantityByProductId = (productId: string): number => {
        return quantitiesByProduct.value.get(productId)?.discounted ?? 0;
    };

    return {
        items,
        cartDiscountType,
        cartDiscountValue,
        cartDiscountNote,
        cartDiscountAmount,
        subtotalAfterCartDiscount,
        addItem,
        removeItem,
        updateQuantity,
        updateNotes,
        clearCart,
        getSubtotal,
        subtotal,
        taxType,
        taxValue,
        taxAmount,
        serviceChargeType,
        serviceChargeValue,
        serviceChargeAmount,
        finalTotal,
        totalItems,
        isEmpty,
        getQuantityByProductId,
        getDiscountedQuantityByProductId,
    };
}
