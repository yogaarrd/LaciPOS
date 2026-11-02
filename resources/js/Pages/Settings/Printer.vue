<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Switch } from '@/Components/ui/switch';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/ui/card';
import { useToast } from '@/composables/useToast';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandGroup, CommandItem, CommandList } from '@/Components/ui/command';
import { Check, ChevronsUpDown } from 'lucide-vue-next';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    printerStatus: { type: String, default: 'unavailable' },
    printers: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    printer_name: props.profile?.printer_name || '',
    paper_size: props.profile?.paper_size || '58mm',
    auto_print: props.profile?.auto_print ? true : false,
});

const toast = useToast();
const openAutoPrintBox = ref(false);

const submit = () => {
    form.post('/settings/printer', {
        preserveScroll: true,
    });
};

const isTesting = ref(false);

const withoutLogo = ref(false);
const isRefreshing = ref(false);
const statusMessage = computed(() => ({
    desktop_required: 'Cetak langsung tersedia di aplikasi desktop LaciPOS.',
    unavailable: 'Daftar printer belum dapat diperiksa. Printer tersimpan tetap dapat dicoba.',
    empty: 'Belum ada printer terdaftar. Pasang driver dan tambahkan printer melalui pengaturan komputer.',
    available: 'Daftar printer berhasil diperiksa. Lakukan tes cetak untuk memeriksa hasilnya.',
}[props.printerStatus]));
const selectedMissing = computed(() => props.printerStatus === 'available'
    && form.printer_name && !props.printers.some(p => p.name === form.printer_name));
const refreshPrinters = () => {
    isRefreshing.value = true;
    router.reload({ only: ['printers', 'printerStatus'], onFinish: () => { isRefreshing.value = false; } });
};
const testPrint = () => {
    isTesting.value = true;
    // Save first so the test uses exactly the visible selection.
    let saved = false;
    form.post('/settings/printer', {
        preserveScroll: true,
        onSuccess: () => { saved = true; },
        onFinish: () => {
            if (!saved) {
                isTesting.value = false;
                return;
            }
            router.post('/settings/printer/test', { without_logo: withoutLogo.value }, {
                preserveScroll: true,
                onFinish: () => { isTesting.value = false; },
            });
        },
    });
};
</script>

<template>
    <SettingsLayout title="Pengaturan Printer">
        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-900">Pengaturan Printer</h2>
                    <p class="text-sm text-gray-500 mt-1">Atur printer thermal 58 mm untuk mencetak struk transaksi.</p>
                </div>

                <div class="p-6 space-y-6">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-2 text-sm" role="status" aria-live="polite">
                    <p>{{ statusMessage }}</p>
                    <p v-if="selectedMissing" class="text-amber-700">Nama printer pilihan tidak ditemukan dalam daftar saat ini. Periksa namanya di komputer atau coba tes cetak jika sebelumnya bekerja.</p>
                    <p v-if="!form.printer_name">Printer default komputer akan digunakan. Pastikan printer default adalah printer struk Anda.</p>
                    <p class="text-gray-500">Printer terdaftar belum tentu sedang tersambung atau memiliki kertas. Fitur cetak tidak memerlukan pembelian tambahan.</p>
                    <button type="button" @click="refreshPrinters" :disabled="isRefreshing || isTesting" class="underline font-medium">
                        {{ isRefreshing ? 'Memeriksa...' : 'Periksa ulang daftar printer' }}
                    </button>
                </div>
                <div class="space-y-2">
                    <label for="printer_name" class="block text-sm font-medium text-gray-700 mb-1">Pilih Printer</label>
                    <div class="flex gap-2 items-center">
                        <Select v-if="printers.length > 0" v-model="form.printer_name">
                            <SelectTrigger class="h-10 w-full rounded-xl border-[1.5px] border-[#c1ccd6] bg-white text-sm font-normal text-[#001e2b] shadow-[0_1px_2px_rgba(0,30,43,0.04)] outline-none transition-all focus:border-[#00684a] focus:ring-[3px] focus:ring-[#00684a]/10 px-4">
                                <SelectValue placeholder="Pilih printer OS..." />
                            </SelectTrigger>
                            <SelectContent class="bg-white">
                                <SelectItem v-for="printer in printers" :key="printer.name" :value="printer.name">
                                    {{ printer.displayName }}{{ printer.isDefault ? ' (Default komputer)' : '' }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input v-else
                            id="printer_name"
                            v-model="form.printer_name"
                            placeholder="Nama printer (misal: POS-80C)"
                            class="h-10 w-full rounded-xl border-[1.5px] border-[#c1ccd6] bg-white text-sm font-normal text-[#001e2b] shadow-[0_1px_2px_rgba(0,30,43,0.04)] outline-none transition-all focus:border-[#00684a] focus:ring-[3px] focus:ring-[#00684a]/10 px-4"
                        />
                    </div>
                    <p class="text-sm text-slate-500 mt-1">
                        <span v-if="printers.length > 0">Atau ketik manual jika tidak ada dalam daftar.</span>
                        <span v-else>Jika sebelumnya berhasil, nama printer tersimpan tetap dapat digunakan. Gunakan nama persis seperti di pengaturan printer komputer.</span>
                    </p>
                    <input v-if="printers.length > 0"
                        id="printer_name_manual"
                        v-model="form.printer_name"
                        placeholder="Input nama printer manual..."
                        class="h-10 w-full rounded-xl border-[1.5px] border-[#c1ccd6] bg-white text-sm font-normal text-[#001e2b] shadow-[0_1px_2px_rgba(0,30,43,0.04)] outline-none transition-all focus:border-[#00684a] focus:ring-[3px] focus:ring-[#00684a]/10 px-4 mt-2"
                    />
                    <p v-if="form.errors.printer_name" class="text-sm text-red-500 mt-1">{{ form.errors.printer_name }}</p>
                </div>

                <div class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ukuran Kertas</label>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.paper_size" value="58mm" class="w-4 h-4 text-[#00ed64] focus:ring-[#00ed64]" />
                            <span class="text-sm text-gray-700">58 mm (Kecil)</span>
                        </label>
                    </div>
                    <p v-if="form.errors.paper_size" class="text-sm text-red-500 mt-1">{{ form.errors.paper_size }}</p>
                </div>

                <div class="flex flex-row items-center justify-between rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <div class="space-y-0.5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Auto Print</label>
                        <p class="text-sm text-gray-500">
                            Otomatis cetak struk setelah transaksi berhasil.
                        </p>
                    </div>
                    <div class="w-[160px]">
                        <Popover v-model:open="openAutoPrintBox">
                            <PopoverTrigger as-child>
                                <button
                                    type="button"
                                    role="combobox"
                                    :aria-expanded="openAutoPrintBox"
                                    class="flex items-center justify-between h-10 w-full rounded-xl border-[1.5px] border-[#c1ccd6] bg-white pl-4 pr-3 text-sm font-normal text-[#001e2b] shadow-[0_1px_2px_rgba(0,30,43,0.04)] outline-none transition-all hover:bg-slate-50 focus:border-[#00684a] focus:ring-[3px] focus:ring-[#00684a]/10"
                                >
                                    <span class="truncate">{{ form.auto_print ? 'Aktif' : 'Nonaktif' }}</span>
                                    <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50 text-[#7c8c9a]" />
                                </button>
                            </PopoverTrigger>
                            <PopoverContent class="w-[160px] p-0 bg-white" align="start">
                                <Command>
                                    <CommandList>
                                        <CommandGroup>
                                            <CommandItem
                                                value="Aktif"
                                                @select="() => {
                                                    form.auto_print = true;
                                                    openAutoPrintBox = false;
                                                }"
                                                class="text-sm cursor-pointer"
                                            >
                                                Aktif
                                                <Check
                                                    :class="['ml-auto h-4 w-4', form.auto_print === true ? 'opacity-100 text-[#00684a]' : 'opacity-0']"
                                                />
                                            </CommandItem>
                                            <CommandItem
                                                value="Nonaktif"
                                                @select="() => {
                                                    form.auto_print = false;
                                                    openAutoPrintBox = false;
                                                }"
                                                class="text-sm cursor-pointer"
                                            >
                                                Nonaktif
                                                <Check
                                                    :class="['ml-auto h-4 w-4', form.auto_print === false ? 'opacity-100 text-[#00684a]' : 'opacity-0']"
                                                />
                                            </CommandItem>
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>

                </div>
            </div>

            <p class="text-sm text-gray-500">Simpan &amp; Tes akan menyimpan pilihan di atas. Pastikan printer menyala, kertas tersedia, dan tes cetak dari pengaturan komputer berhasil.</p>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="withoutLogo" :disabled="isTesting" /> Tes tanpa logo (untuk memeriksa masalah gambar)</label>
            <div class="flex justify-between items-center">
                <button type="button" @click="testPrint" :disabled="isTesting || form.processing || props.printerStatus === 'desktop_required'" class="px-6 py-2 bg-white text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors font-medium text-sm flex items-center disabled:opacity-50">
                    {{ isTesting ? 'Mencetak...' : 'Simpan & Tes Cetak' }}
                </button>
                <button 
                    type="submit" 
                    class="px-6 py-2 bg-[#001e2b] text-white rounded-lg hover:bg-gray-800 transition-colors font-medium text-sm flex items-center disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="form.processing || isTesting"
                >
                    <span v-if="form.processing" class="mr-2">Menyimpan...</span>
                    <span v-else>Simpan Perubahan</span>
                </button>
            </div>
        </form>
    </SettingsLayout>
</template>
