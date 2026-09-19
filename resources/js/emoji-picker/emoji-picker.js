import { createPopper } from '@popperjs/core';
import { Picker } from 'emoji-picker-element';

function isDarkTheme() {
    return document.documentElement.classList.contains('dark');
}

const i18nDefault = {
    categoriesLabel: 'Categories',
    emojiUnsupportedMessage: 'Your browser does not support color emoji.',
    favoritesLabel: 'Favorites',
    loadingMessage: 'Loading…',
    networkErrorMessage: 'Could not load emoji.',
    regionLabel: 'Emoji picker',
    searchDescription: 'When search results are available, press up or down to select and enter to choose.',
    searchLabel: 'Search',
    searchResultsLabel: 'Search results',
    skinToneDescription: 'When expanded, press up or down to select and enter to choose.',
    skinToneLabel: 'Choose a skin tone (currently {skinTone})',
    skinTonesLabel: 'Skin tones',
    skinTones: ['Default', 'Light', 'Medium-Light', 'Medium', 'Medium-Dark', 'Dark'],
    categories: {
        custom: 'Custom',
        'smileys-emotion': 'Smileys and emoticons',
        'people-body': 'People and body',
        'animals-nature': 'Animals and nature',
        'food-drink': 'Food and drink',
        'travel-places': 'Travel and places',
        activities: 'Activities',
        objects: 'Objects',
        symbols: 'Symbols',
        flags: 'Flags',
    },
};

async function onEmojiPickerToggle(event) {
    const element = event.detail.element;
    const data    = event.detail.data;
    const button  = element.querySelector('.emoji-picker-button');
    const popup   = element.querySelector('.emoji-picker-popup');

    if (!popup || !button) return;

    // Jika popup tidak visible (open = false), tidak perlu lakukan apa-apa
    if (!data.open) return;

    const existingPicker = popup.querySelector('emoji-picker');

    // Jika sudah ada picker, update tema dan update posisi Popper
    if (existingPicker) {
        existingPicker.classList.remove('dark', 'light');
        existingPicker.classList.add(isDarkTheme() ? 'dark' : 'light');

        // Update posisi Popper jika sudah ada instance
        if (popup._popperInstance) {
            popup._popperInstance.update();
        }
        return;
    }

    try {
        // Hapus IndexedDB korup
        try { window.indexedDB.deleteDatabase('emoji-picker-element-en'); } catch (_) {}

        const picker = new Picker({ 
            i18n: i18nDefault, 
            locale: 'en',
            dataSource: 'https://cdn.jsdelivr.net/npm/emoji-picker-element-data@^1/en/emojibase/data.json'
        });
        picker.classList.add(isDarkTheme() ? 'dark' : 'light');
        popup.appendChild(picker);

        // Posisikan popup via Popper.js dan simpan instance
        popup._popperInstance = createPopper(button, popup, {
            placement: popup.dataset.popupPlacement || 'top-end',
            modifiers: [
                {
                    name: 'offset',
                    options: {
                        offset: [
                            parseInt(popup.dataset.popupOffsetX || '0'),
                            parseInt(popup.dataset.popupOffsetY || '8'),
                        ],
                    },
                },
                {
                    name: 'preventOverflow',
                    options: { padding: 8 },
                },
                {
                    name: 'flip',
                    options: { fallbackPlacements: ['bottom-end', 'top-start', 'bottom-start'] },
                },
            ],
        });
    } catch (err) {
        console.error('[EmojiPicker] Error initializing:', err);
    }
}

document.addEventListener('emoji-picker-toggle', onEmojiPickerToggle);
console.log('[EmojiPicker] Custom listener registered.');
