<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import SymbolBulkUpload from './SymbolBulkUpload.vue';
import SymbolCard from './SymbolCard.vue';

const props = defineProps({
    symbols: {
        type: Array,
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
});

const symbols = ref(props.symbols);
const nameDrafts = reactive(Object.fromEntries(
    props.symbols.map((symbol) => [symbol.id, symbol.name]),
));
const symbolStates = reactive(Object.fromEntries(
    props.symbols.map((symbol) => [symbol.id, {
        isUploading: false,
        isRemoving: false,
        uploadStatus: '',
        uploadStatusIsError: false,
        removeStatus: '',
        removeStatusIsError: false,
    }]),
));
const imagePreviewUrls = reactive({});
const isBulkUploading = ref(false);
const bulkUploadState = reactive({
    status: '',
    isError: false,
});
const missingSymbols = computed(() => symbols.value.filter((symbol) => !symbol.imageUrl));
const uploadedCount = computed(() => symbols.value.length - missingSymbols.value.length);

const symbolUrl = (template, symbolId) => template.replace('__SYMBOL_ID__', String(symbolId));

const setStatus = (state, key, message, isError = false) => {
    state[key] = message;
    state[`${key}IsError`] = isError;
};

const revokePreviewUrl = (symbolId) => {
    if (imagePreviewUrls[symbolId]) {
        URL.revokeObjectURL(imagePreviewUrls[symbolId]);
        delete imagePreviewUrls[symbolId];
    }
};

const updatePrintRequirements = (missingCount) => {
    const printLink = document.querySelector('[data-print-link]');
    const printRequirements = document.querySelector('[data-print-requirements]');

    if (printLink && printRequirements) {
        printLink.hidden = missingCount > 0;
        printRequirements.hidden = missingCount === 0;
        printRequirements.textContent = `Noch ${missingCount} Bilder erforderlich`;
    }
};

const updateCardPreviews = (symbol) => {
    document.querySelectorAll(`[data-symbol-image-id="${symbol.id}"]`).forEach((element) => {
        if (element instanceof HTMLImageElement) {
            element.src = symbol.imageUrl;
            element.alt = symbol.name;
            element.title = symbol.name;

            return;
        }

        const image = document.createElement('img');
        image.className = 'dobble-preview-symbol';
        image.style.cssText = element.style.cssText;
        image.dataset.symbolImageId = symbol.id;
        image.src = symbol.imageUrl;
        image.alt = symbol.name;
        image.title = symbol.name;
        image.loading = 'lazy';
        element.replaceWith(image);
    });
};

const removeCardPreviews = (symbol) => {
    document.querySelectorAll(`img[data-symbol-image-id="${symbol.id}"]`).forEach((image) => {
        const placeholder = document.createElement('span');
        placeholder.className = 'dobble-preview-symbol dobble-preview-placeholder';
        placeholder.style.cssText = image.style.cssText;
        placeholder.dataset.symbolImageId = symbol.id;
        placeholder.title = symbol.name;
        placeholder.textContent = String(symbol.id + 1);
        image.replaceWith(placeholder);
    });
};

const updateSymbol = (updatedSymbol) => {
    if (
        !updatedSymbol
        || !Number.isInteger(updatedSymbol.id)
        || typeof updatedSymbol.name !== 'string'
        || (updatedSymbol.imageUrl !== null && typeof updatedSymbol.imageUrl !== 'string')
        || !Array.isArray(updatedSymbol.qualityWarnings)
    ) {
        throw new Error('Die Serverantwort enthält keine gültige Symbolzuordnung.');
    }

    const symbol = symbols.value.find((item) => item.id === updatedSymbol.id);
    if (!symbol) {
        throw new Error('Die Symbolkarte für die Serverantwort wurde nicht gefunden.');
    }

    revokePreviewUrl(symbol.id);
    Object.assign(symbol, updatedSymbol);
    nameDrafts[symbol.id] = symbol.name;

    if (symbol.imageUrl) {
        updateCardPreviews(symbol);
    } else {
        removeCardPreviews(symbol);
    }
};

const submitFormAsJson = async (url, formData, loadingMessage, state, statusKey) => {
    setStatus(state, statusKey, loadingMessage);

    const response = await fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const result = await response.json();

    if (!response.ok) {
        const errors = Object.values(result.errors ?? {}).flat();
        throw new Error(errors.join(' ') || 'Die Änderung konnte nicht gespeichert werden. Bitte versuche es erneut.');
    }

    return result;
};

const updateSetFromResponse = (result) => {
    updateSymbol(result.symbol);
    updatePrintRequirements(result.missingCount);
};

const handleImageSelection = (symbol, event) => {
    const input = event.currentTarget;
    if (!(input instanceof HTMLInputElement) || !input.files?.[0]) {
        return;
    }

    revokePreviewUrl(symbol.id);
    imagePreviewUrls[symbol.id] = URL.createObjectURL(input.files[0]);
    input.form?.requestSubmit();
};

const handleUpload = async (symbol, event) => {
    const form = event.currentTarget;
    const state = symbolStates[symbol.id];
    const input = form.querySelector('[data-image-input]');

    if (!(form instanceof HTMLFormElement) || !(input instanceof HTMLInputElement)) {
        throw new Error('Das Upload-Formular für ein Symbol ist nicht verfügbar.');
    }

    const formData = new FormData(form);
    state.isUploading = true;

    try {
        const result = await submitFormAsJson(
            form.action,
            formData,
            'Bild wird hochgeladen …',
            state,
            'uploadStatus',
        );
        updateSetFromResponse(result);
        input.value = '';
        setStatus(state, 'uploadStatus', 'Bild gespeichert.');
    } catch (error) {
        console.error('Das Symbolbild konnte nicht hochgeladen werden.', error);
        setStatus(
            state,
            'uploadStatus',
            error instanceof Error
                ? error.message
                : 'Der Upload ist fehlgeschlagen. Bitte prüfe deine Verbindung und versuche es erneut.',
            true,
        );
    } finally {
        state.isUploading = false;
    }
};

const handleRemoval = async (symbol, event) => {
    const form = event.currentTarget;
    const state = symbolStates[symbol.id];

    if (!(form instanceof HTMLFormElement)) {
        throw new Error('Das Formular zum Entfernen des Bildes ist nicht verfügbar.');
    }

    state.isRemoving = true;

    try {
        const result = await submitFormAsJson(
            form.action,
            new FormData(form),
            'Bild wird entfernt …',
            state,
            'removeStatus',
        );

        if (!result.symbol || result.symbol.imageUrl !== null) {
            throw new Error('Die Serverantwort enthält einen ungültigen Symbolzustand.');
        }

        updateSetFromResponse(result);
        setStatus(state, 'uploadStatus', 'Bild entfernt.');
    } catch (error) {
        console.error('Das Symbolbild konnte nicht entfernt werden.', error);
        setStatus(
            state,
            'removeStatus',
            error instanceof Error
                ? error.message
                : 'Das Bild konnte nicht entfernt werden. Bitte versuche es erneut.',
            true,
        );
    } finally {
        state.isRemoving = false;
    }
};

const handleBulkUpload = async (event) => {
    const input = event.currentTarget;
    if (!(input instanceof HTMLInputElement)) {
        throw new Error('Die Dateiauswahl für den Mehrfachupload ist nicht verfügbar.');
    }

    const files = Array.from(input.files ?? []);
    if (files.length === 0) {
        return;
    }

    if (files.length > missingSymbols.value.length) {
        setStatus(
            bulkUploadState,
            'status',
            `Es sind nur noch ${missingSymbols.value.length} leere Symbole verfügbar. Bitte wähle höchstens so viele Bilder aus.`,
            true,
        );
        input.value = '';

        return;
    }

    const symbolsToUpload = [...missingSymbols.value];
    isBulkUploading.value = true;
    let uploadedFiles = 0;

    try {
        for (const [index, file] of files.entries()) {
            const symbol = symbolsToUpload[index];
            const state = symbolStates[symbol.id];
            const extensionIndex = file.name.lastIndexOf('.');
            const fileName = (extensionIndex > 0 ? file.name.slice(0, extensionIndex) : file.name)
                .trim()
                .slice(0, 80);
            const formData = new FormData();
            formData.append('_token', props.csrfToken);
            formData.append('name', fileName || symbol.name);
            formData.append('image', file, file.name);
            state.isUploading = true;

            const result = await submitFormAsJson(
                symbolUrl(props.uploadUrl, symbol.id),
                formData,
                `Bild ${index + 1} von ${files.length} wird hochgeladen …`,
                state,
                'uploadStatus',
            );

            updateSetFromResponse(result);
            uploadedFiles++;
            setStatus(
                bulkUploadState,
                'status',
                `${uploadedFiles} von ${files.length} Bildern hochgeladen …`,
            );
        }

        setStatus(
            bulkUploadState,
            'status',
            `${uploadedFiles} Bilder wurden den Symbolen zugeordnet.`,
        );
    } catch (error) {
        console.error('Der Bulk-Upload konnte nicht vollständig abgeschlossen werden.', error);
        const detail = error instanceof Error
            ? error.message
            : 'Bitte prüfe deine Verbindung und versuche es erneut.';
        setStatus(
            bulkUploadState,
            'status',
            `${uploadedFiles} von ${files.length} Bildern wurden hochgeladen. ${detail}`,
            true,
        );
    } finally {
        symbols.value.forEach((symbol) => {
            symbolStates[symbol.id].isUploading = false;
        });
        isBulkUploading.value = false;
        input.value = '';
    }
};

onBeforeUnmount(() => {
    Object.values(imagePreviewUrls).forEach((previewUrl) => URL.revokeObjectURL(previewUrl));
});
</script>

<template>
    <section id="symbole" class="mt-8 scroll-mt-6 border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-[#e7674e]">Schritt 2</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight">Eigene Symbole zuordnen</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                    Lade einzelne Bilder oder mehrere PNG-, JPEG- oder WebP-Bilder auf einmal hoch. Bilder bleiben privat und sind nur in deiner Sitzung erreichbar.
                    Für ein scharfes Druckbild empfehlen sich quadratische Dateien ab 256 × 256 Pixel.
                </p>
            </div>
            <p data-symbol-count class="rounded-full bg-amber-50 px-4 py-2 text-sm font-medium text-amber-900">
                {{ uploadedCount }} von {{ symbols.length }} Bildern vorhanden
            </p>
        </div>

        <SymbolBulkUpload
            :disabled="missingSymbols.length === 0 || isBulkUploading"
            :status="bulkUploadState.status"
            :is-error="bulkUploadState.isError"
            @files-selected="handleBulkUpload"
        />

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <SymbolCard
                v-for="symbol in symbols"
                :key="symbol.id"
                :symbol="symbol"
                :symbols="symbols"
                :state="symbolStates[symbol.id]"
                v-model:name-draft="nameDrafts[symbol.id]"
                :upload-url="symbolUrl(props.uploadUrl, symbol.id)"
                :remove-url="symbolUrl(props.removeUrl, symbol.id)"
                :move-url="symbolUrl(props.moveUrl, symbol.id)"
                :csrf-token="props.csrfToken"
                :is-bulk-uploading="isBulkUploading"
                :image-preview-url="imagePreviewUrls[symbol.id]"
                @image-selected="handleImageSelection(symbol, $event)"
                @upload-submitted="handleUpload(symbol, $event)"
                @removal-submitted="handleRemoval(symbol, $event)"
            />
        </div>
    </section>
</template>
