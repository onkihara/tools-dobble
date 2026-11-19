import { createApp } from 'vue';
import SymbolManager from './components/SymbolManager.vue';

const orderSelect = document.querySelector('[data-order-select]');
const cardCountInput = document.querySelector('[data-card-count]');
const orderSummary = document.querySelector('[data-order-summary]');

if (orderSelect && cardCountInput && orderSummary) {
    const updateOrderSummary = () => {
        const selectedOption = orderSelect.selectedOptions[0];
        const totalCards = selectedOption.dataset.total;

        orderSummary.textContent = `Vollständige Konstruktion: ${totalCards} Karten`;
        cardCountInput.max = totalCards;
    };

    orderSelect.addEventListener('change', updateOrderSummary);
    updateOrderSummary();
}

const backgroundModeInputs = document.querySelectorAll('[data-background-mode]');
const backgroundColorField = document.querySelector('[data-background-color-field]');
const backgroundImageField = document.querySelector('[data-background-image-field]');
const backgroundImageInput = document.querySelector('[data-background-image-input]');
const backgroundImagePreview = document.querySelector('[data-background-image-preview]');
const backgroundImageStatus = document.querySelector('[data-background-image-status]');
const backgroundColorInput = document.querySelector('#background_color');
const cardBackgroundPreviews = document.querySelectorAll('[data-card-background-preview]');
let backgroundPreviewUrl = null;

const updateCardBackgroundPreviews = () => {
    const selectedMode = document.querySelector('[data-background-mode]:checked')?.value;
    const savedImageUrl = backgroundImageInput instanceof HTMLInputElement
        ? backgroundImageInput.dataset.savedUrl
        : '';
    const imageUrl = backgroundPreviewUrl || savedImageUrl;
    const backgroundColor = backgroundColorInput instanceof HTMLInputElement
        ? backgroundColorInput.value
        : '';

    if (backgroundImagePreview instanceof HTMLImageElement) {
        if (imageUrl) {
            backgroundImagePreview.src = imageUrl;
        } else {
            backgroundImagePreview.removeAttribute('src');
        }
        backgroundImagePreview.hidden = !imageUrl;
    }

    cardBackgroundPreviews.forEach((preview) => {
        if (!(preview instanceof HTMLImageElement)) {
            return;
        }

        if (imageUrl) {
            preview.src = imageUrl;
        } else {
            preview.removeAttribute('src');
        }
        preview.hidden = selectedMode !== 'image' || !imageUrl;

        const card = preview.closest('[data-card-preview]');
        if (card instanceof HTMLElement && backgroundColor) {
            card.style.backgroundColor = backgroundColor;
        }
    });
};

const updateBackgroundFields = () => {
    const selectedMode = document.querySelector('[data-background-mode]:checked')?.value;

    if (backgroundColorField instanceof HTMLElement) {
        backgroundColorField.hidden = selectedMode !== 'color';
    }

    if (backgroundImageField instanceof HTMLElement) {
        backgroundImageField.hidden = selectedMode !== 'image';
    }

    updateCardBackgroundPreviews();
};

backgroundModeInputs.forEach((input) => input.addEventListener('change', updateBackgroundFields));
if (backgroundImageInput instanceof HTMLInputElement) {
    backgroundImageInput.addEventListener('change', () => {
        if (backgroundPreviewUrl) {
            URL.revokeObjectURL(backgroundPreviewUrl);
            backgroundPreviewUrl = null;
        }

        const file = backgroundImageInput.files?.[0];
        if (file) {
            backgroundPreviewUrl = URL.createObjectURL(file);
            const imageModeInput = document.querySelector('[data-background-mode][value="image"]');
            if (imageModeInput instanceof HTMLInputElement) {
                imageModeInput.checked = true;
            }
        }

        if (backgroundImageStatus instanceof HTMLElement) {
            backgroundImageStatus.textContent = file
                ? 'Neues Bild ausgewählt. Übernimm die Konfiguration, um es zu speichern.'
                : backgroundImageInput.dataset.savedUrl
                    ? 'Ein Bild ist gespeichert. Ein neues Bild ersetzt es.'
                    : 'Wähle ein Bild aus. Es wird auf die Kartenfläche zugeschnitten.';
        }

        updateBackgroundFields();
    });
}
backgroundColorInput?.addEventListener('input', updateCardBackgroundPreviews);
updateBackgroundFields();

const setUploadStatus = (status, message, isError = false) => {
    status.textContent = message;
    status.setAttribute('role', isError ? 'alert' : 'status');
    status.classList.toggle('text-rose-700', isError);
    status.classList.toggle('text-slate-600', !isError);
};

document.querySelectorAll('[data-card-preview]').forEach((preview) => {
    const randomizeCard = async () => {
        const status = preview.parentElement?.querySelector('[data-card-layout-status]');
        if (!(status instanceof HTMLElement) || preview.getAttribute('aria-busy') === 'true') {
            return;
        }

        preview.setAttribute('aria-busy', 'true');
        setUploadStatus(status, 'Symbole werden neu angeordnet …');

        try {
            const response = await fetch(preview.dataset.randomizeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            });
            const result = await response.json();

            if (!response.ok) {
                const errors = Object.values(result.errors ?? {}).flat();
                throw new Error(errors.join(' ') || 'Die Karte konnte nicht neu angeordnet werden.');
            }

            const symbols = Array.from(preview.querySelectorAll('[data-symbol-image-id]'));
            const placementsBySymbol = new Map(
                result.placements.map((placement) => [placement.symbol_id, placement]),
            );
            const updatedSymbols = symbols.map((symbol) => {
                const placement = placementsBySymbol.get(Number(symbol.dataset.symbolImageId));
                if (!placement) {
                    throw new Error('Die Serverantwort enthält keine vollständige Symbolanordnung.');
                }

                return { symbol, placement };
            });

            if (updatedSymbols.length !== result.placements.length) {
                throw new Error('Die Serverantwort enthält eine ungültige Symbolanordnung.');
            }

            updatedSymbols.forEach(({ symbol, placement }) => {
                symbol.style.setProperty('--symbol-left', `${placement.left}%`);
                symbol.style.setProperty('--symbol-top', `${placement.top}%`);
                symbol.style.setProperty('--symbol-rotation', `${placement.rotation}deg`);
                symbol.style.setProperty('--symbol-scale', String(placement.scale));
            });
            setUploadStatus(status, 'Symbole und Größen wurden neu gemischt.');
        } catch (error) {
            console.error('Die Karte konnte nicht neu angeordnet werden.', error);
            setUploadStatus(
                status,
                error instanceof Error ? error.message : 'Die Karte konnte nicht neu angeordnet werden.',
                true,
            );
        } finally {
            preview.removeAttribute('aria-busy');
        }
    };

    preview.addEventListener('click', randomizeCard);
    preview.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            randomizeCard();
        }
    });
});

const symbolManager = document.querySelector('[data-symbol-manager]');
if (symbolManager instanceof HTMLElement) {
    createApp(SymbolManager, {
        symbols: JSON.parse(symbolManager.dataset.symbols),
        uploadUrl: symbolManager.dataset.uploadUrl,
        removeUrl: symbolManager.dataset.removeUrl,
        moveUrl: symbolManager.dataset.moveUrl,
        csrfToken: document.querySelector('meta[name="csrf-token"]').content,
    }).mount(symbolManager);
}
