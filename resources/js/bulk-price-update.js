/**
 * State and review summary for the Pricelist bulk editor.
 *
 * Only changed rows are sent to the existing cost-history workflow. The
 * server remains the source of truth for VAT conversion and stored costs.
 */
export function bulkPriceUpdate(config = {}) {
    return {
        vatRate: Number(config.vatRate) || 0,
        labels: config.labels || {},
        search: '',
        changedCount: 0,
        revision: 0,

        init() {
            this.recount();
            this.$nextTick(() => this.recount());
        },

        rows() {
            return [...(this.$root?.querySelectorAll('[data-bulk-row]') || [])];
        },

        parseMoney(value) {
            const digits = String(value ?? '').replace(/\D/g, '');
            return digits === '' ? null : Number(digits);
        },

        formatMoney(value) {
            const amount = Number(value);
            if (!Number.isFinite(amount)) return '';
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(amount);
        },

        formatCurrency(value) {
            return `Rp ${this.formatMoney(value)}`;
        },

        formatPrice(event) {
            const input = event.target;
            const previousValue = input.value;
            const previousCaret = input.selectionStart ?? previousValue.length;
            const digitsBeforeCaret = previousValue.slice(0, previousCaret).replace(/\D/g, '').length;
            const digits = previousValue.replace(/\D/g, '');
            input.value = digits === '' ? '' : this.formatMoney(digits);

            let caret = 0;
            let counted = 0;
            while (caret < input.value.length && counted < digitsBeforeCaret) {
                if (/\d/.test(input.value[caret])) counted += 1;
                caret += 1;
            }
            input.setSelectionRange(caret, caret);

            this.recount();
        },

        field(row, selector) {
            return row?.querySelector(selector) || null;
        },

        changedParts(row) {
            const price = this.field(row, '[data-bulk-price]');
            const supplier = this.field(row, '[data-bulk-supplier] input[type="hidden"]');
            const validUntil = this.field(row, '[data-bulk-valid-until]');

            const originalSupplier = String(row?.dataset.originalSupplier || '');
            const originalValidUntil = String(row?.dataset.originalValidUntil || '');
            const previousNet = Number(row?.dataset.previousNetCost);
            const currentNet = this.newNetCost(row);
            const netCostChanged = currentNet === null
                || Math.round(currentNet * 100) !== Math.round(previousNet * 100);

            return {
                price: netCostChanged,
                supplier: String(supplier?.value || '') !== originalSupplier,
                validUntil: String(validUntil?.value || '') !== originalValidUntil,
            };
        },

        isChanged(row) {
            const parts = this.changedParts(row);

            return parts.price || parts.supplier || parts.validUntil;
        },

        recount() {
            const rows = this.rows();
            this.changedCount = rows.filter((row) => this.isChanged(row)).length;
            this.revision += 1;

            rows.forEach((row) => {
                const changed = this.isChanged(row);
                const price = this.field(row, '[data-bulk-price]');
                const supplierMount = this.field(row, '[data-bulk-supplier] [data-glide-mount]');

                row.dataset.changed = changed ? '1' : '0';
                if (price) price.required = changed;
                if (supplierMount) supplierMount.dataset.required = changed ? '1' : '0';
            });
        },

        matchesSearch(searchText) {
            const query = String(this.search || '').trim().toLowerCase();
            return !query || String(searchText || '').toLowerCase().includes(query);
        },

        visibleCount() {
            this.revision;

            return this.rows().filter((row) => this.matchesSearch(row.dataset.search)).length;
        },

        newNetCost(row) {
            const entered = this.parseMoney(this.field(row, '[data-bulk-price]')?.value);
            if (entered === null) return null;

            const includesTax = Boolean(this.field(row, '[data-bulk-includes-tax]')?.checked);
            if (!includesTax || this.vatRate <= 0) return entered;

            return Math.round(((entered / (1 + this.vatRate / 100)) + Number.EPSILON) * 100) / 100;
        },

        difference(row) {
            this.revision;

            const previous = Number(row?.dataset.previousNetCost);
            const current = this.newNetCost(row);
            if (!Number.isFinite(previous) || current === null) return this.labels.noPrice || '—';

            const amount = Math.round((current - previous) * 100) / 100;
            if (amount === 0) return this.labels.noChange || 'No change';

            const direction = amount < 0 ? '↓' : '↑';
            const percentage = previous > 0 ? Math.abs((amount / previous) * 100) : null;
            const percentLabel = percentage === null
                ? ''
                : ` (${new Intl.NumberFormat(globalThis.document?.documentElement?.lang || 'en', { maximumFractionDigits: 1 }).format(percentage)}%)`;

            return `${direction} ${this.formatCurrency(Math.abs(amount))}${percentLabel}`;
        },

        selectedSupplierName(row) {
            const mount = this.field(row, '[data-bulk-supplier] [data-glide-mount]');
            const hidden = this.field(row, '[data-bulk-supplier] input[type="hidden"]');
            if (!mount || !hidden?.value) return '';

            try {
                const options = JSON.parse(mount.dataset.options || '[]');

                return options.find((option) => String(option.value) === String(hidden.value))?.label || '';
            } catch (error) {
                return '';
            }
        },

        formatDate(value) {
            if (!value) return this.labels.noExpiry || 'No expiry date';

            const date = new Date(`${value}T00:00:00Z`);
            if (Number.isNaN(date.getTime())) return value;

            return new Intl.DateTimeFormat(globalThis.document?.documentElement?.lang || 'en', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                timeZone: 'UTC',
            }).format(date);
        },

        reviewDetails() {
            this.revision;
            const details = [];

            this.rows().filter((row) => this.isChanged(row)).forEach((row) => {
                const parts = this.changedParts(row);
                const product = row.dataset.productName || this.labels.product || 'Product';

                if (parts.price) {
                    details.push({
                        label: `${product} - ${this.labels.cost || 'Cost'}`,
                        value: `${this.formatCurrency(row.dataset.previousNetCost)} → ${this.formatCurrency(this.newNetCost(row))}`,
                    });

                    details.push({
                        label: `${product} - ${this.labels.vat || 'VAT'}`,
                        value: `${this.field(row, '[data-bulk-includes-tax]')?.checked
                            ? (this.labels.included || 'Included')
                            : (this.labels.excluded || 'Excluded')} (${this.vatRate}%)`,
                    });
                }

                if (parts.supplier) {
                    const previousSupplier = row.dataset.originalSupplierName || this.labels.notSelected || 'Not selected';
                    const nextSupplier = this.selectedSupplierName(row) || this.labels.notSelected || 'Not selected';
                    details.push({
                        label: `${product} - ${this.labels.supplier || 'Supplier'}`,
                        value: `${previousSupplier} → ${nextSupplier}`,
                    });
                }

                if (parts.validUntil) {
                    const previousDate = this.formatDate(row.dataset.originalValidUntil);
                    const nextDate = this.formatDate(this.field(row, '[data-bulk-valid-until]')?.value);
                    details.push({
                        label: `${product} - ${this.labels.validUntil || 'Valid Until'}`,
                        value: `${previousDate} → ${nextDate}`,
                    });
                }
            });

            return details;
        },

        confirmMessage() {
            const template = this.labels.confirm || 'Review price changes for :count products.';

            return template.replace(':count', String(this.changedCount));
        },

        stripUnchanged(formData) {
            this.rows().filter((row) => !this.isChanged(row)).forEach((row) => {
                row.querySelectorAll('[name]').forEach((field) => formData.delete(field.name));
            });
        },

        async guardPagination(event) {
            const link = event.target?.closest?.('a[href]');
            if (!link || this.changedCount === 0) return;

            event.preventDefault();
            event.stopPropagation();

            const accepted = await window.askConfirm({
                title: this.labels.unsavedTitle,
                message: this.labels.unsavedMessage,
                tone: 'warning',
                confirmLabel: this.labels.leave,
                cancelLabel: this.labels.stay,
                details: [{ label: this.labels.changed || 'Changed products', value: String(this.changedCount) }],
            });

            if (accepted) window.location.assign(link.href);
        },
    };
}

export function installBulkPriceUpdate(windowRef = globalThis.window) {
    if (!windowRef) return;
    windowRef.bulkPriceUpdate = bulkPriceUpdate;
}

installBulkPriceUpdate();
