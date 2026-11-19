<script setup>
const props = defineProps({
    symbol: {
        type: Object,
        required: true,
    },
    translations: {
        type: Object,
        required: true,
    },
    symbols: {
        type: Array,
        required: true,
    },
    state: {
        type: Object,
        required: true,
    },
    nameDraft: {
        type: String,
        required: true,
    },
    uploadUrl: {
        type: String,
        required: true,
    },
    removeUrl: {
        type: String,
        required: true,
    },
    moveUrl: {
        type: String,
        required: true,
    },
    csrfToken: {
        type: String,
        required: true,
    },
    isBulkUploading: {
        type: Boolean,
        required: true,
    },
    imagePreviewUrl: {
        type: String,
        default: '',
    },
});

defineEmits([
    'update:nameDraft',
    'image-selected',
    'upload-submitted',
    'removal-submitted',
]);

const formatMessage = (message, replacements = {}) => Object.entries(replacements)
    .reduce((formatted, [key, value]) => formatted.replaceAll(`:${key}`, String(value)), message);
</script>

<template>
    <article
        class="border border-slate-200 p-4 shadow-xl/10"
        data-symbol-card
        :data-symbol-id="props.symbol.id"
        :data-symbol-image-present="props.symbol.imageUrl ? 'true' : 'false'"
    >
        <div class="flex items-center gap-4">
            <div class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#f5f2e9]">
                <img
                    data-symbol-preview
                    :src="props.imagePreviewUrl || props.symbol.imageUrl || undefined"
                    :alt="props.symbol.name"
                    :class="props.symbol.imageUrl || props.imagePreviewUrl ? 'size-full object-contain p-1' : 'hidden'"
                >
                <span
                    data-symbol-placeholder
                    :class="props.symbol.imageUrl || props.imagePreviewUrl ? 'hidden' : 'text-2xl font-semibold text-slate-400'"
                    aria-hidden="true"
                >{{ props.symbol.id + 1 }}</span>
            </div>
            <div class="min-w-0">
                <h3 data-symbol-name class="truncate font-semibold">{{ props.symbol.name }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ formatMessage(props.translations.number, { number: props.symbol.id + 1 }) }}</p>
            </div>
            <span
                data-symbol-status
                :class="[
                    'ml-auto rounded-full px-2.5 py-1 text-xs font-medium',
                    props.symbol.imageUrl ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900',
                ]"
            >{{ props.symbol.imageUrl ? props.translations.ready : props.translations.missing }}</span>
        </div>
        <ul
            v-if="props.symbol.qualityWarnings.length > 0"
            data-quality-warnings
            class="mt-3 list-inside list-disc rounded-lg bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900"
        >
            <li v-for="warning in props.symbol.qualityWarnings" :key="warning">{{ warning }}</li>
        </ul>

        <form
            method="POST"
            :action="props.uploadUrl"
            enctype="multipart/form-data"
            class="mt-4 space-y-3"
            data-image-upload-form
            :aria-busy="props.state.isUploading"
            @submit.prevent="$emit('upload-submitted', $event)"
        >
            <input type="hidden" name="_token" :value="props.csrfToken">
            <label class="block text-xs font-medium text-slate-700" :for="`symbol-name-${props.symbol.id}`">
                {{ props.translations.name }}
                <input
                    :id="`symbol-name-${props.symbol.id}`"
                    :value="props.nameDraft"
                    name="name"
                    type="text"
                    maxlength="80"
                    required
                    @input="$emit('update:nameDraft', $event.currentTarget.value)"
                    class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[#287c72] focus:outline-none focus:ring-2 focus:ring-[#287c72]/20"
                >
            </label>
            <label class="block text-xs font-medium text-slate-700" :for="`symbol-image-${props.symbol.id}`">
                {{ props.translations.image }}
                <input
                    :id="`symbol-image-${props.symbol.id}`"
                    name="image"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    required
                    data-image-input
                    :disabled="props.isBulkUploading || props.state.isUploading || props.state.isRemoving"
                    @change="$emit('image-selected', $event)"
                    class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#eaf2ef] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#193a38] hover:file:bg-[#dcebe6]"
                >
            </label>
            <p
                data-upload-status
                :role="props.state.uploadStatusIsError ? 'alert' : 'status'"
                aria-live="polite"
                :class="['text-xs', props.state.uploadStatusIsError ? 'text-rose-700' : 'text-slate-600']"
            >{{ props.state.uploadStatus }}</p>
        </form>

        <form
            v-if="props.symbol.imageUrl"
            method="POST"
            :action="props.removeUrl"
            class="mt-2"
            data-symbol-remove-form
            :aria-busy="props.state.isRemoving"
            @submit.prevent="$emit('removal-submitted', $event)"
        >
            <input type="hidden" name="_token" :value="props.csrfToken">
            <input type="hidden" name="_method" value="DELETE">
            <button
                type="submit"
                :disabled="props.isBulkUploading || props.state.isRemoving || props.state.isUploading"
                class="rounded-lg px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50"
            >{{ props.translations.remove }}</button>
            <p
                data-remove-status
                :role="props.state.removeStatusIsError ? 'alert' : 'status'"
                aria-live="polite"
                :class="['text-xs', props.state.removeStatusIsError ? 'text-rose-700' : 'text-slate-600']"
            >{{ props.state.removeStatus }}</p>
        </form>

        <form
            v-if="props.symbol.imageUrl"
            method="POST"
            :action="props.moveUrl"
            class="mt-3 space-y-2"
            data-symbol-move-form
        >
            <input type="hidden" name="_token" :value="props.csrfToken">
            <label class="block text-xs font-medium text-slate-700" :for="`symbol-target-${props.symbol.id}`">
                {{ props.translations.move }}
                <select
                    :id="`symbol-target-${props.symbol.id}`"
                    name="target_symbol"
                    required
                    :disabled="props.isBulkUploading"
                    class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#287c72] focus:outline-none focus:ring-2 focus:ring-[#287c72]/20"
                >
                    <template v-for="target in props.symbols" :key="target.id">
                        <option v-if="target.id !== props.symbol.id" :value="target.id">
                            {{ formatMessage(
                                target.imageUrl
                                    ? props.translations.target_filled
                                    : props.translations.target_empty,
                                { number: target.id + 1 },
                            ) }}
                        </option>
                    </template>
                </select>
            </label>
            <button
                type="submit"
                :disabled="props.isBulkUploading"
                class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
            >{{ props.translations.move_submit }}</button>
        </form>
    </article>
</template>
