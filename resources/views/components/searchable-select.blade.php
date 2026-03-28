@props(['placeholder' => __('Select...')])

<div wire:ignore class="flex w-full">
    <select
        x-data="{
            tomSelectInstance: null,
            init() {
                this.tomSelectInstance = new TomSelect(this.$refs.select, {
                    placeholder: '{{ $placeholder }}',
                    allowEmptyOption: true,
                    onChange: (value) => {
                        this.$refs.select.value = value;
                        this.$refs.select.dispatchEvent(new Event('input', { bubbles: true }));
                        this.$refs.select.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

                // Listen to Livewire property updates (e.g. form resets)
                Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                    succeed(({ snapshot, effect }) => {
                        // After livewire DOM updates, we might need to sync the internal select value
                        let elValue = this.$refs.select.value;
                        if (this.tomSelectInstance.getValue() !== elValue) {
                            this.tomSelectInstance.setValue(elValue, true);
                        }
                    })
                });
            }
        }"
        x-ref="select"
        {{ $attributes->merge(['class' => 'w-full']) }}
    >
        {{ $slot }}
    </select>
</div>

@once
<style>
    /* Styling adjustments to match standard UI */
    .ts-wrapper {
        width: 100%;
    }
    .ts-control {
        border-radius: 0.375rem; /* rounded-md */
        border-color: #d4d4d8; /* zinc-300 */
        padding: 0.5rem 0.75rem;
        min-height: 40px;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
    }
    
    /* Dark Mode Adjustments */
    .dark .ts-control, .dark .ts-wrapper.single.input-active .ts-control {
        background-color: #18181b; /* zinc-900 */
        border-color: #3f3f46; /* zinc-700 */
        color: #f4f4f5; /* zinc-100 */
    }
    .dark .ts-dropdown, .dark .ts-dropdown.single {
        background-color: #18181b; /* zinc-900 */
        border-color: #3f3f46; /* zinc-700 */
        color: #f4f4f5; /* zinc-100 */
    }
    .dark .ts-dropdown .option.active, .dark .ts-dropdown .active {
        background-color: #3f3f46; /* zinc-700 */
        color: #f4f4f5; /* white */
    }
    .dark .ts-dropdown .option {
        color: #f4f4f5;
    }
</style>
@endonce
