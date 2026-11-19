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

const submitFormAsJson = async (form, status, loadingMessage, formData = new FormData(form)) => {
    setUploadStatus(status, loadingMessage);

    const response = await fetch(form.action, {
        method: form.method,
        body: formData,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const result = await response.json();

    if (!response.ok) {
        const errors = Object.values(result.errors ?? {}).flat();
        setUploadStatus(
            status,
            errors.join(' ') || 'Die Änderung konnte nicht gespeichert werden. Bitte versuche es erneut.',
            true,
        );

        return null;
    }

    return result;
};

const updateSymbolCountAndPrintAction = (result) => {
    document.querySelector('[data-symbol-count]').textContent =
        `${result.uploadedCount} von ${result.totalCount} Bildern vorhanden`;

    const printLink = document.querySelector('[data-print-link]');
    const printRequirements = document.querySelector('[data-print-requirements]');
    printLink.hidden = result.missingCount > 0;
    printRequirements.hidden = result.missingCount === 0;
    printRequirements.textContent = `Noch ${result.missingCount} Bilder erforderlich`;
    if (bulkImageInput instanceof HTMLInputElement) {
        bulkImageInput.disabled = result.missingCount === 0
            || bulkImageInput.getAttribute('aria-busy') === 'true';
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

const updateMoveOptions = () => {
    document.querySelectorAll('[data-symbol-move-form] select').forEach((select) => {
        Array.from(select.options).forEach((option) => {
            const targetCard = document.querySelector(
                `[data-symbol-card][data-symbol-id="${option.value}"]`,
            );
            const hasImage = targetCard?.dataset.symbolImagePresent === 'true';
            option.textContent = `Symbol ${Number(option.value) + 1}${hasImage ? ' (Bild wird getauscht)' : ' (leer)'}`;
        });
    });
};

const updateSymbolCard = (symbol) => {
    if (!symbol || typeof symbol.imageUrl !== 'string') {
        throw new Error('Die Serverantwort enthält keine gültige Symbolzuordnung.');
    }

    const card = document.querySelector(`[data-symbol-card][data-symbol-id="${symbol.id}"]`);
    if (!card) {
        throw new Error('Die Symbolkarte für die Serverantwort wurde nicht gefunden.');
    }

    const preview = card.querySelector('[data-symbol-preview]');
    if (preview instanceof HTMLImageElement) {
        if (preview.dataset.previewUrl) {
            URL.revokeObjectURL(preview.dataset.previewUrl);
            delete preview.dataset.previewUrl;
        }

        preview.src = symbol.imageUrl;
        preview.alt = symbol.name;
        preview.classList.remove('hidden');
        card.querySelector('[data-symbol-placeholder]')?.classList.add('hidden');
    }

    card.querySelector('[data-symbol-name]').textContent = symbol.name;
    card.querySelector('[name="name"]').value = symbol.name;

    const symbolStatus = card.querySelector('[data-symbol-status]');
    symbolStatus.textContent = 'Bereit';
    symbolStatus.classList.remove('bg-amber-50', 'text-amber-900');
    symbolStatus.classList.add('bg-emerald-50', 'text-emerald-800');

    const warnings = card.querySelector('[data-quality-warnings]');
    warnings.replaceChildren(...symbol.qualityWarnings.map((warning) => {
        const item = document.createElement('li');
        item.textContent = warning;

        return item;
    }));
    warnings.classList.toggle('hidden', symbol.qualityWarnings.length === 0);

    card.dataset.symbolImagePresent = 'true';
    card.querySelector('[data-symbol-remove-form]').hidden = false;
    card.querySelector('[data-symbol-move-form]').hidden = false;
    updateCardPreviews(symbol);
    updateMoveOptions();
};

const bulkImageInput = document.querySelector('[data-bulk-image-input]');
const bulkUploadStatus = document.querySelector('[data-bulk-upload-status]');

if (bulkImageInput instanceof HTMLInputElement && bulkUploadStatus instanceof HTMLElement) {
    bulkImageInput.addEventListener('change', async () => {
        const files = Array.from(bulkImageInput.files ?? []);
        if (files.length === 0) {
            return;
        }

        const missingCards = Array.from(
            document.querySelectorAll('[data-symbol-card][data-symbol-image-present="false"]'),
        );
        if (files.length > missingCards.length) {
            setUploadStatus(
                bulkUploadStatus,
                `Es sind nur noch ${missingCards.length} leere Symbole verfügbar. Bitte wähle höchstens so viele Bilder aus.`,
                true,
            );
            bulkImageInput.value = '';

            return;
        }

        const controls = missingCards.flatMap((card) => Array.from(card.querySelectorAll('input, button, select')));
        const previousDisabledStates = controls.map((control) => control.disabled);
        controls.forEach((control) => {
            control.disabled = true;
        });
        bulkImageInput.disabled = true;
        bulkImageInput.setAttribute('aria-busy', 'true');
        let uploadedCount = 0;

        try {
            for (const [index, file] of files.entries()) {
                const card = missingCards[index];
                const form = card.querySelector('[data-image-upload-form]');
                const status = form?.querySelector('[data-upload-status]');
                const csrfToken = form?.querySelector('input[name="_token"]');

                if (!(form instanceof HTMLFormElement) || !(status instanceof HTMLElement) || !(csrfToken instanceof HTMLInputElement)) {
                    throw new Error('Das Upload-Formular für ein Symbol ist nicht verfügbar.');
                }

                const extensionIndex = file.name.lastIndexOf('.');
                const fileName = (extensionIndex > 0 ? file.name.slice(0, extensionIndex) : file.name)
                    .trim()
                    .slice(0, 80);
                const formData = new FormData();
                formData.append('_token', csrfToken.value);
                formData.append('name', fileName || card.querySelector('[data-symbol-name]').textContent.trim());
                formData.append('image', file, file.name);

                const result = await submitFormAsJson(
                    form,
                    status,
                    `Bild ${index + 1} von ${files.length} wird hochgeladen …`,
                    formData,
                );

                if (!result) {
                    throw new Error(status.textContent || 'Bitte prüfe die Dateiformate und versuche es erneut.');
                }

                updateSymbolCard(result.symbol);
                updateSymbolCountAndPrintAction(result);
                uploadedCount++;
                setUploadStatus(bulkUploadStatus, `${uploadedCount} von ${files.length} Bildern hochgeladen …`);
            }

            setUploadStatus(bulkUploadStatus, `${uploadedCount} Bilder wurden den Symbolen zugeordnet.`);
        } catch (error) {
            console.error('Der Bulk-Upload konnte nicht vollständig abgeschlossen werden.', error);
            const detail = error instanceof Error ? error.message : 'Bitte prüfe deine Verbindung und versuche es erneut.';
            setUploadStatus(
                bulkUploadStatus,
                `${uploadedCount} von ${files.length} Bildern wurden hochgeladen. ${detail}`,
                true,
            );
        } finally {
            controls.forEach((control, index) => {
                control.disabled = previousDisabledStates[index];
            });
            bulkImageInput.disabled = document.querySelectorAll(
                '[data-symbol-card][data-symbol-image-present="false"]',
            ).length === 0;
            bulkImageInput.removeAttribute('aria-busy');
            bulkImageInput.value = '';
        }
    });
}

document.querySelectorAll('[data-image-upload-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const input = form.querySelector('[data-image-input]');
        const card = form.closest('[data-symbol-card]');
        const status = form.querySelector('[data-upload-status]');

        if (!(input instanceof HTMLInputElement) || !card || !status) {
            return;
        }

        const formData = new FormData(form);
        input.disabled = true;
        const removeButton = card.querySelector('[data-symbol-remove-form] button[type="submit"]');
        if (removeButton) {
            removeButton.disabled = true;
        }
        form.setAttribute('aria-busy', 'true');

        try {
            const result = await submitFormAsJson(form, status, 'Bild wird hochgeladen …', formData);
            if (!result) {
                return;
            }

            const { symbol } = result;
            updateSymbolCard(symbol);
            updateSymbolCountAndPrintAction(result);

            input.value = '';
            setUploadStatus(status, 'Bild gespeichert.');
        } catch (error) {
            console.error('Das Symbolbild konnte nicht hochgeladen werden.', error);
            setUploadStatus(status, 'Der Upload ist fehlgeschlagen. Bitte prüfe deine Verbindung und versuche es erneut.', true);
        } finally {
            input.disabled = false;
            if (removeButton) {
                removeButton.disabled = false;
            }
            form.removeAttribute('aria-busy');
        }
    });
});

document.querySelectorAll('[data-symbol-remove-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const card = form.closest('[data-symbol-card]');
        const status = form.querySelector('[data-remove-status]');
        const button = form.querySelector('button[type="submit"]');
        const uploadInput = card?.querySelector('[data-image-input]');

        if (!card || !status || !(button instanceof HTMLButtonElement)) {
            return;
        }

        button.disabled = true;
        if (uploadInput instanceof HTMLInputElement) {
            uploadInput.disabled = true;
        }
        form.setAttribute('aria-busy', 'true');

        try {
            const result = await submitFormAsJson(form, status, 'Bild wird entfernt …');
            if (!result) {
                return;
            }

            const { symbol } = result;
            if (!symbol || symbol.imageUrl !== null) {
                throw new Error('Die Serverantwort enthält einen ungültigen Symbolzustand.');
            }

            const preview = card.querySelector('[data-symbol-preview]');
            if (preview instanceof HTMLImageElement) {
                if (preview.dataset.previewUrl) {
                    URL.revokeObjectURL(preview.dataset.previewUrl);
                    delete preview.dataset.previewUrl;
                }

                preview.removeAttribute('src');
                preview.alt = symbol.name;
                preview.classList.add('hidden');
                card.querySelector('[data-symbol-placeholder]')?.classList.remove('hidden');
            }

            card.querySelector('[data-symbol-name]').textContent = symbol.name;
            card.querySelector('[name="name"]').value = symbol.name;

            const symbolStatus = card.querySelector('[data-symbol-status]');
            symbolStatus.textContent = 'Bild fehlt';
            symbolStatus.classList.remove('bg-emerald-50', 'text-emerald-800');
            symbolStatus.classList.add('bg-amber-50', 'text-amber-900');
            card.dataset.symbolImagePresent = 'false';
            updateMoveOptions();

            const warnings = card.querySelector('[data-quality-warnings]');
            warnings.replaceChildren();
            warnings.classList.add('hidden');

            removeCardPreviews(symbol);
            updateSymbolCountAndPrintAction(result);
            form.hidden = true;
            card.querySelector('[data-symbol-move-form]').hidden = true;
            setUploadStatus(card.querySelector('[data-upload-status]'), 'Bild entfernt.');
        } catch (error) {
            console.error('Das Symbolbild konnte nicht entfernt werden.', error);
            setUploadStatus(status, 'Das Bild konnte nicht entfernt werden. Bitte versuche es erneut.', true);
        } finally {
            button.disabled = false;
            if (uploadInput instanceof HTMLInputElement) {
                uploadInput.disabled = false;
            }
            form.removeAttribute('aria-busy');
        }
    });
});

document.querySelectorAll('[data-image-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        const form = input.closest('[data-image-upload-form]');
        const card = form?.closest('[data-symbol-card]');
        const preview = card?.querySelector('[data-symbol-preview]');

        if (!file || !(form instanceof HTMLFormElement)) {
            return;
        }

        if (preview) {
            if (preview.dataset.previewUrl) {
                URL.revokeObjectURL(preview.dataset.previewUrl);
            }

            const previewUrl = URL.createObjectURL(file);
            preview.dataset.previewUrl = previewUrl;
            preview.src = previewUrl;
            preview.alt = file.name;
            preview.classList.remove('hidden');
            card.querySelector('[data-symbol-placeholder]')?.classList.add('hidden');
        }

        form.requestSubmit();
    });
});
