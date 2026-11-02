<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Printer, Ban, Download, Receipt, CheckCircle, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    order: Object,
});

const page = usePage();
const storeTimezone = computed(() => page.props.storeSettings?.timezone || 'Asia/Jakarta');


const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value || 0);
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    const tz = storeTimezone.value;
    const datePart = date.toLocaleDateString('id-ID', { timeZone: tz, weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const timePart = date.toLocaleTimeString('id-ID', { timeZone: tz, hour: '2-digit', minute: '2-digit' });
    return `${datePart}\n${timePart}`;
};

const isPrinting = ref(false);
const printReceipt = () => {
    if (isPrinting.value) return;
    isPrinting.value = true;
    router.post(`/orders/${props.order.id}/print`, {}, {
        preserveScroll: true,
        onFinish: () => { isPrinting.value = false; }
    });
};

const showCancelDialog = ref(false);

const confirmCancel = () => {
    showCancelDialog.value = true;
};

const executeCancel = () => {
    router.post(`/orders/${props.order.id}/cancel`, {}, {
        preserveScroll: true,
        onFinish: () => {
            showCancelDialog.value = false;
        },
    });
};

const getStatusColor = (statusText) => {
    switch (statusText) {
        case 'completed': return 'bg-green-100 text-green-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-yellow-100 text-yellow-800';
    }
};
</script>

<template>
    <AppLayout title="Detail Transaksi">
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/orders" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white border border-[#e1e5e8] text-slate-500 hover:bg-slate-50 transition-colors">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
                <div>
                    <h1 class="text-lg font-semibold text-[#001e2b]">Detail Transaksi</h1>
                    <p class="text-xs text-[#7c8c9a]">Order #{{ order.order_number }}</p>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Order Details & Items -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Status Banner -->
                <div 
                    class="rounded-xl border px-4 py-3 flex items-center gap-3"
                    :class="[
                        order.status === 'completed' ? 'bg-[#00684a] border-[#00684a] text-white shadow-sm' : 
                        order.status === 'cancelled' ? 'bg-red-600 border-red-600 text-white shadow-sm' : 
                        'bg-yellow-500 border-yellow-500 text-white shadow-sm'
                    ]"
                >
                    <CheckCircle v-if="order.status === 'completed'" class="h-5 w-5 shrink-0 text-white" />
                    <XCircle v-else-if="order.status === 'cancelled'" class="h-5 w-5 shrink-0 text-white" />
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wider">{{ order.status }}</p>
                        <p class="text-xs opacity-80 mt-0.5">
                            {{ order.status === 'completed' ? 'Transaksi telah selesai dan pembayaran diterima.' : 
                               order.status === 'cancelled' ? 'Transaksi telah dibatalkan dan stok dikembalikan.' : 
                               'Transaksi sedang diproses.' }}
                        </p>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="rounded-2xl border border-[#e1e5e8] bg-white shadow-[0_1px_2px_rgba(0,30,43,0.04)] overflow-hidden">
                    <div class="border-b border-[#e1e5e8] bg-[#f4f7f6] px-5 py-3">
                        <h2 class="text-sm font-semibold text-[#001e2b]">Daftar Produk</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-[#3d4f5b]">
                            <thead class="bg-white border-b border-[#e1e5e8] text-xs uppercase text-[#7c8c9a]">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-semibold">Produk</th>
                                    <th scope="col" class="px-5 py-3 font-semibold text-right">Harga (Snapshot)</th>
                                    <th scope="col" class="px-5 py-3 font-semibold text-right">Qty</th>
                                    <th scope="col" class="px-5 py-3 font-semibold text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e1e5e8]">
                                <tr v-for="item in order.items" :key="item.id">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-[#001e2b]">{{ item.product_name_snapshot }}</p>
                                        <p v-if="item.variant_label" class="text-xs text-[#7c8c9a] mt-0.5">Varian: {{ item.variant_label }}</p>
                                        <p v-if="item.notes" class="text-xs text-amber-600 mt-1 flex items-start">
                                            <span class="mr-1 font-medium">Catatan:</span> {{ item.notes }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <p :class="{ 'line-through text-[#a8b3bc] text-xs': item.snapshot_discount_amount > 0 }">{{ formatRupiah(item.snapshot_price) }}</p>
                                        <p v-if="item.snapshot_discount_amount > 0" class="text-sm text-[#001e2b] font-medium">{{ formatRupiah(Math.max(0, item.snapshot_price - item.snapshot_discount_amount)) }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-right font-medium">
                                        {{ item.quantity }}x
                                    </td>
                                    <td class="px-5 py-4 text-right font-semibold text-[#001e2b]">
                                        {{ formatRupiah(item.subtotal) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column: Summary & Actions -->
            <div class="space-y-6">
                <!-- Summary Card -->
                <div class="rounded-2xl border border-[#e1e5e8] bg-white p-5 shadow-[0_1px_2px_rgba(0,30,43,0.04)]">
                    <h2 class="text-sm font-semibold text-[#001e2b] mb-4">Ringkasan Pembayaran</h2>
                    
                    <dl class="space-y-3 text-sm">
                        <div v-if="order.customer_name" class="flex justify-between">
                            <dt class="text-[#7c8c9a]">Nama Pemesan</dt>
                            <dd class="font-medium text-[#001e2b] text-right">{{ order.customer_name }}</dd>
                        </div>
                        <div v-if="order.table_number" class="flex justify-between">
                            <dt class="text-[#7c8c9a]">Nomor Meja</dt>
                            <dd class="font-medium text-[#001e2b] text-right">{{ order.table_number }}</dd>
                        </div>
                        <div v-if="order.notes" class="flex justify-between">
                            <dt class="text-[#7c8c9a]">Catatan</dt>
                            <dd class="font-medium text-[#001e2b] text-right max-w-[200px]">{{ order.notes }}</dd>
                        </div>
                        <div v-if="order.customer_name || order.table_number || order.notes" class="border-t border-[#e1e5e8] my-3"></div>

                        <div class="flex justify-between">
                            <dt class="text-[#7c8c9a]">Tanggal Transaksi</dt>
                            <dd class="font-medium text-[#001e2b] text-right whitespace-pre-line leading-tight">{{ formatDate(order.created_at) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[#7c8c9a]">Kasir</dt>
                            <dd class="font-medium text-[#001e2b] text-right">{{ order.user?.name || 'Unknown' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-[#7c8c9a]">Subtotal</dt>
                            <dd class="font-medium text-[#001e2b] text-right">{{ formatRupiah(order.subtotal) }}</dd>
                        </div>
                        <div v-if="order.discount_amount > 0" class="flex justify-between">
                            <dt class="text-[#7c8c9a]">
                                Diskon Transaksi <span v-if="order.discount_note" class="block text-xs mt-0.5 opacity-80">{{ order.discount_note }}</span>
                            </dt>
                            <dd class="font-medium text-red-500 text-right">-{{ formatRupiah(order.discount_amount) }}</dd>
                        </div>
                        <div v-if="order.tax_amount > 0" class="flex justify-between">
                            <dt class="text-[#7c8c9a]">
                                Pajak <span v-if="order.tax_type === 'percentage'">({{ Number(order.tax_rate) }}%)</span>
                            </dt>
                            <dd class="font-medium text-[#001e2b] text-right">{{ formatRupiah(order.tax_amount) }}</dd>
                        </div>
                        <div v-if="order.service_charge_amount > 0" class="flex justify-between">
                            <dt class="text-[#7c8c9a]">
                                Biaya Layanan <span v-if="order.service_charge_type === 'percentage'">({{ Number(order.service_charge_rate) }}%)</span>
                            </dt>
                            <dd class="font-medium text-[#001e2b] text-right">{{ formatRupiah(order.service_charge_amount) }}</dd>
                        </div>
                        <div class="flex justify-between pt-3 mt-3 border-t border-[#e1e5e8]">
                            <dt class="text-[#7c8c9a]">Metode Pembayaran</dt>
                            <dd class="font-semibold uppercase text-[#001e2b] text-right">{{ order.payment_method }}</dd>
                        </div>
                        <div v-if="order.payment_method === 'cash'" class="flex justify-between">
                            <dt class="text-[#7c8c9a]">Uang Diterima</dt>
                            <dd class="font-medium text-[#001e2b] text-right">{{ formatRupiah(order.cash_received) }}</dd>
                        </div>
                        <div v-if="order.payment_method === 'cash'" class="flex justify-between">
                            <dt class="text-[#7c8c9a]">Kembalian</dt>
                            <dd class="font-medium text-[#001e2b] text-right">{{ formatRupiah(order.change_amount) }}</dd>
                        </div>
                        
                        <div class="pt-4 border-t border-[#e1e5e8] flex justify-between items-center">
                            <dt class="text-base font-bold text-[#001e2b]">Total Harga</dt>
                            <dd class="text-xl font-bold text-[#001e2b]">{{ formatRupiah(order.total_amount) }}</dd>
                        </div>

                        <!-- Rincian MDR Khusus Owner -->
                        <template v-if="$page.props.auth.user.role === 'owner' && order.payment_admin_fee_amount > 0">
                            <div class="pt-3 border-t border-[#e1e5e8] mt-3 flex justify-between items-center">
                                <dt class="text-xs text-orange-600">Potongan MDR ({{ order.payment_admin_fee_type === 'percentage' ? Number(order.payment_admin_fee) + '%' : 'Rp ' + Number(order.payment_admin_fee).toLocaleString('id-ID') }})</dt>
                                <dd class="text-xs font-semibold text-orange-600 text-right">-{{ formatRupiah(order.payment_admin_fee_amount) }}</dd>
                            </div>
                            <div class="pt-2 flex justify-between items-center">
                                <dt class="text-sm font-bold text-[#00684a]">Dana Cair (Bersih)</dt>
                                <dd class="text-sm font-bold text-[#00684a] text-right">{{ formatRupiah(order.total_amount - order.payment_admin_fee_amount) }}</dd>
                            </div>
                        </template>
                    </dl>
                </div>

                <!-- Actions Card -->
                <div class="rounded-2xl border border-[#e1e5e8] bg-white p-5 shadow-[0_1px_2px_rgba(0,30,43,0.04)]">
                    <h2 class="text-sm font-semibold text-[#001e2b] mb-4">Tindakan</h2>
                    <div class="space-y-3">
                        <a
                            :href="`/orders/${order.id}/download`"
                            class="w-full flex items-center justify-center gap-2 rounded-lg bg-[#001e2b] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1c2d38] transition-colors"
                        >
                            <Download class="h-4 w-4" />
                            Download Struk PDF
                        </a>
                        <button
                            @click="printReceipt"
                            :disabled="isPrinting"
                            class="w-full flex items-center justify-center gap-2 rounded-lg bg-white border border-[#001e2b] px-4 py-2.5 text-sm font-semibold text-[#001e2b] hover:bg-[#f4f7f6] transition-colors"
                        >
                            <Printer class="h-4 w-4" />
                            Cetak Ulang Struk
                        </button>
                        
                        <button
                            v-if="order.status === 'completed'"
                            @click="confirmCancel"
                            class="w-full flex items-center justify-center gap-2 rounded-lg bg-white border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors"
                        >
                            <Ban class="h-4 w-4" />
                            Batalkan Transaksi
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Confirmation Modal -->
        <Modal
            :show="showCancelDialog"
            @update:show="showCancelDialog = $event"
            title="Batalkan Transaksi"
            description="Apakah Anda yakin ingin membatalkan transaksi ini? Stok produk akan otomatis dikembalikan."
        >
            <template #footer>
                <Button variant="outline" @click="showCancelDialog = false">Tidak</Button>
                <Button class="bg-red-600 text-white hover:bg-red-700" @click="executeCancel">Ya, Batalkan</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
