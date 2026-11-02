/**
 * Composable untuk format currency Rupiah.
 * Penggunaan: const { formatRupiah } = useFormatCurrency();
 * formatRupiah(28000) → "Rp 28.000"
 * formatRupiah(1234567.89) → "Rp 1.234.568"
 */
const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

export function useFormatCurrency() {
    const formatRupiah = (value: number | string): string => {
        const num = typeof value === 'string' ? parseFloat(value) : value;
        if (isNaN(num)) return 'Rp 0';

        return rupiahFormatter.format(num);
    };

    return { formatRupiah };
}
