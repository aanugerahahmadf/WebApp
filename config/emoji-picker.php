<?php

// config for TangoDevIt/FilamentEmojiPicker
return [
    'locale' => 'en',
    // i18n di-hardcode langsung di emoji-picker.js untuk mencegah crash WebView (Mobile)
    'i18n' => null,
    // Memaksa datasource mengambil dari master database pusat yang selalu up-to-date
    'datasource' => 'https://cdn.jsdelivr.net/npm/emoji-picker-element-data@^1/en/emojibase/data.json',
];
