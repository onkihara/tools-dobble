<script setup>
defineProps({
    disabled: {
        type: Boolean,
        required: true,
    },
    status: {
        type: String,
        required: true,
    },
    isError: {
        type: Boolean,
        required: true,
    },
    translations: {
        type: Object,
        required: true,
    },
});

defineEmits(['files-selected']);
</script>

<template>
    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
        <label for="bulk-symbol-images" class="block text-sm font-semibold text-slate-800">
            {{ translations.title }}
            <input
                id="bulk-symbol-images"
                name="images[]"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                multiple
                data-bulk-image-input
                :disabled="disabled"
                @change="$emit('files-selected', $event)"
                class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#eaf2ef] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#193a38] hover:file:bg-[#dcebe6] disabled:opacity-50"
            >
        </label>
        <p class="mt-2 text-xs leading-5 text-slate-600">
            {{ translations.description }}
        </p>
        <p
            data-bulk-upload-status
            :role="isError ? 'alert' : 'status'"
            aria-live="polite"
            :class="['mt-2 text-sm', isError ? 'text-rose-700' : 'text-slate-600']"
        >{{ status }}</p>
    </div>
</template>
