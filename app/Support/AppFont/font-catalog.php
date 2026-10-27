<?php

/*
 * DIHASILKAN scripts/generate-fonts.py -- DAFTAR NAMA KELUARGA FONT.
 *
 * Satu entri = satu keluarga @font-face di public/css/app-font.css.
 * Sumber nama: file TTF di assets/fonts/<Keluarga>/ -- TTF variable
 * jadi keluarga dasar folder-nya; instansi static/ jadi keluarga
 * ter-nama ('Asap Sharp Condensed Black', 'Google Sans Flex 24pt
 * Bold', ...). Dibaca AppFont::families(): whitelist pilihan font
 * (select searchable) + validasi nilai yang disimpan admin.
 *
 * Ulang script setelah menambah folder/berkas font.
 */

return [
    'Asap Sharp', // AsapSharp-Italic-VariableFont_wdth,wght.ttf, AsapSharp-VariableFont_wdth,wght.ttf
    'Asap Sharp Black', // AsapSharp-Black.ttf
    'Asap Sharp Black Italic', // AsapSharp-BlackItalic.ttf
    'Asap Sharp Bold', // AsapSharp-Bold.ttf
    'Asap Sharp Bold Italic', // AsapSharp-BoldItalic.ttf
    'Asap Sharp Condensed Black', // AsapSharp_Condensed-Black.ttf
    'Asap Sharp Condensed Black Italic', // AsapSharp_Condensed-BlackItalic.ttf
    'Asap Sharp Condensed Bold', // AsapSharp_Condensed-Bold.ttf
    'Asap Sharp Condensed Bold Italic', // AsapSharp_Condensed-BoldItalic.ttf
    'Asap Sharp Condensed ExtraBold', // AsapSharp_Condensed-ExtraBold.ttf
    'Asap Sharp Condensed ExtraBold Italic', // AsapSharp_Condensed-ExtraBoldItalic.ttf
    'Asap Sharp Condensed ExtraLight', // AsapSharp_Condensed-ExtraLight.ttf
    'Asap Sharp Condensed ExtraLight Italic', // AsapSharp_Condensed-ExtraLightItalic.ttf
    'Asap Sharp Condensed Italic', // AsapSharp_Condensed-Italic.ttf
    'Asap Sharp Condensed Light', // AsapSharp_Condensed-Light.ttf
    'Asap Sharp Condensed Light Italic', // AsapSharp_Condensed-LightItalic.ttf
    'Asap Sharp Condensed Medium', // AsapSharp_Condensed-Medium.ttf
    'Asap Sharp Condensed Medium Italic', // AsapSharp_Condensed-MediumItalic.ttf
    'Asap Sharp Condensed Regular', // AsapSharp_Condensed-Regular.ttf
    'Asap Sharp Condensed SemiBold', // AsapSharp_Condensed-SemiBold.ttf
    'Asap Sharp Condensed SemiBold Italic', // AsapSharp_Condensed-SemiBoldItalic.ttf
    'Asap Sharp Condensed Thin', // AsapSharp_Condensed-Thin.ttf
    'Asap Sharp Condensed Thin Italic', // AsapSharp_Condensed-ThinItalic.ttf
    'Asap Sharp Expanded Black', // AsapSharp_Expanded-Black.ttf
    'Asap Sharp Expanded Black Italic', // AsapSharp_Expanded-BlackItalic.ttf
    'Asap Sharp Expanded Bold', // AsapSharp_Expanded-Bold.ttf
    'Asap Sharp Expanded Bold Italic', // AsapSharp_Expanded-BoldItalic.ttf
    'Asap Sharp Expanded ExtraBold', // AsapSharp_Expanded-ExtraBold.ttf
    'Asap Sharp Expanded ExtraBold Italic', // AsapSharp_Expanded-ExtraBoldItalic.ttf
    'Asap Sharp Expanded ExtraLight', // AsapSharp_Expanded-ExtraLight.ttf
    'Asap Sharp Expanded ExtraLight Italic', // AsapSharp_Expanded-ExtraLightItalic.ttf
    'Asap Sharp Expanded Italic', // AsapSharp_Expanded-Italic.ttf
    'Asap Sharp Expanded Light', // AsapSharp_Expanded-Light.ttf
    'Asap Sharp Expanded Light Italic', // AsapSharp_Expanded-LightItalic.ttf
    'Asap Sharp Expanded Medium', // AsapSharp_Expanded-Medium.ttf
    'Asap Sharp Expanded Medium Italic', // AsapSharp_Expanded-MediumItalic.ttf
    'Asap Sharp Expanded Regular', // AsapSharp_Expanded-Regular.ttf
    'Asap Sharp Expanded SemiBold', // AsapSharp_Expanded-SemiBold.ttf
    'Asap Sharp Expanded SemiBold Italic', // AsapSharp_Expanded-SemiBoldItalic.ttf
    'Asap Sharp Expanded Thin', // AsapSharp_Expanded-Thin.ttf
    'Asap Sharp Expanded Thin Italic', // AsapSharp_Expanded-ThinItalic.ttf
    'Asap Sharp ExtraBold', // AsapSharp-ExtraBold.ttf
    'Asap Sharp ExtraBold Italic', // AsapSharp-ExtraBoldItalic.ttf
    'Asap Sharp ExtraLight', // AsapSharp-ExtraLight.ttf
    'Asap Sharp ExtraLight Italic', // AsapSharp-ExtraLightItalic.ttf
    'Asap Sharp Italic', // AsapSharp-Italic.ttf
    'Asap Sharp Light', // AsapSharp-Light.ttf
    'Asap Sharp Light Italic', // AsapSharp-LightItalic.ttf
    'Asap Sharp Medium', // AsapSharp-Medium.ttf
    'Asap Sharp Medium Italic', // AsapSharp-MediumItalic.ttf
    'Asap Sharp Regular', // AsapSharp-Regular.ttf
    'Asap Sharp SemiBold', // AsapSharp-SemiBold.ttf
    'Asap Sharp SemiBold Italic', // AsapSharp-SemiBoldItalic.ttf
    'Asap Sharp SemiCondensed Black', // AsapSharp_SemiCondensed-Black.ttf
    'Asap Sharp SemiCondensed Black Italic', // AsapSharp_SemiCondensed-BlackItalic.ttf
    'Asap Sharp SemiCondensed Bold', // AsapSharp_SemiCondensed-Bold.ttf
    'Asap Sharp SemiCondensed Bold Italic', // AsapSharp_SemiCondensed-BoldItalic.ttf
    'Asap Sharp SemiCondensed ExtraBold', // AsapSharp_SemiCondensed-ExtraBold.ttf
    'Asap Sharp SemiCondensed ExtraBold Italic', // AsapSharp_SemiCondensed-ExtraBoldItalic.ttf
    'Asap Sharp SemiCondensed ExtraLight', // AsapSharp_SemiCondensed-ExtraLight.ttf
    'Asap Sharp SemiCondensed ExtraLight Italic', // AsapSharp_SemiCondensed-ExtraLightItalic.ttf
    'Asap Sharp SemiCondensed Italic', // AsapSharp_SemiCondensed-Italic.ttf
    'Asap Sharp SemiCondensed Light', // AsapSharp_SemiCondensed-Light.ttf
    'Asap Sharp SemiCondensed Light Italic', // AsapSharp_SemiCondensed-LightItalic.ttf
    'Asap Sharp SemiCondensed Medium', // AsapSharp_SemiCondensed-Medium.ttf
    'Asap Sharp SemiCondensed Medium Italic', // AsapSharp_SemiCondensed-MediumItalic.ttf
    'Asap Sharp SemiCondensed Regular', // AsapSharp_SemiCondensed-Regular.ttf
    'Asap Sharp SemiCondensed SemiBold', // AsapSharp_SemiCondensed-SemiBold.ttf
    'Asap Sharp SemiCondensed SemiBold Italic', // AsapSharp_SemiCondensed-SemiBoldItalic.ttf
    'Asap Sharp SemiCondensed Thin', // AsapSharp_SemiCondensed-Thin.ttf
    'Asap Sharp SemiCondensed Thin Italic', // AsapSharp_SemiCondensed-ThinItalic.ttf
    'Asap Sharp SemiExpanded Black', // AsapSharp_SemiExpanded-Black.ttf
    'Asap Sharp SemiExpanded Black Italic', // AsapSharp_SemiExpanded-BlackItalic.ttf
    'Asap Sharp SemiExpanded Bold', // AsapSharp_SemiExpanded-Bold.ttf
    'Asap Sharp SemiExpanded Bold Italic', // AsapSharp_SemiExpanded-BoldItalic.ttf
    'Asap Sharp SemiExpanded ExtraBold', // AsapSharp_SemiExpanded-ExtraBold.ttf
    'Asap Sharp SemiExpanded ExtraBold Italic', // AsapSharp_SemiExpanded-ExtraBoldItalic.ttf
    'Asap Sharp SemiExpanded ExtraLight', // AsapSharp_SemiExpanded-ExtraLight.ttf
    'Asap Sharp SemiExpanded ExtraLight Italic', // AsapSharp_SemiExpanded-ExtraLightItalic.ttf
    'Asap Sharp SemiExpanded Italic', // AsapSharp_SemiExpanded-Italic.ttf
    'Asap Sharp SemiExpanded Light', // AsapSharp_SemiExpanded-Light.ttf
    'Asap Sharp SemiExpanded Light Italic', // AsapSharp_SemiExpanded-LightItalic.ttf
    'Asap Sharp SemiExpanded Medium', // AsapSharp_SemiExpanded-Medium.ttf
    'Asap Sharp SemiExpanded Medium Italic', // AsapSharp_SemiExpanded-MediumItalic.ttf
    'Asap Sharp SemiExpanded Regular', // AsapSharp_SemiExpanded-Regular.ttf
    'Asap Sharp SemiExpanded SemiBold', // AsapSharp_SemiExpanded-SemiBold.ttf
    'Asap Sharp SemiExpanded SemiBold Italic', // AsapSharp_SemiExpanded-SemiBoldItalic.ttf
    'Asap Sharp SemiExpanded Thin', // AsapSharp_SemiExpanded-Thin.ttf
    'Asap Sharp SemiExpanded Thin Italic', // AsapSharp_SemiExpanded-ThinItalic.ttf
    'Asap Sharp Thin', // AsapSharp-Thin.ttf
    'Asap Sharp Thin Italic', // AsapSharp-ThinItalic.ttf
    'Google Sans', // GoogleSans-Italic-VariableFont_GRAD,opsz,wght.ttf, GoogleSans-VariableFont_GRAD,opsz,wght.ttf
    'Google Sans 17pt Bold', // GoogleSans_17pt-Bold.ttf
    'Google Sans 17pt Bold Italic', // GoogleSans_17pt-BoldItalic.ttf
    'Google Sans 17pt Italic', // GoogleSans_17pt-Italic.ttf
    'Google Sans 17pt Medium', // GoogleSans_17pt-Medium.ttf
    'Google Sans 17pt Medium Italic', // GoogleSans_17pt-MediumItalic.ttf
    'Google Sans 17pt Regular', // GoogleSans_17pt-Regular.ttf
    'Google Sans 17pt SemiBold', // GoogleSans_17pt-SemiBold.ttf
    'Google Sans 17pt SemiBold Italic', // GoogleSans_17pt-SemiBoldItalic.ttf
    'Google Sans Bold', // GoogleSans-Bold.ttf
    'Google Sans Bold Italic', // GoogleSans-BoldItalic.ttf
    'Google Sans Flex', // GoogleSansFlex-VariableFont_ROND,opsz,slnt,wdth,wght.ttf, GoogleSansFlex-VariableFont_ROND,opsz,slnt,wdth,wght.ttf
    'Google Sans Flex 120pt Black', // GoogleSansFlex_120pt-Black.ttf
    'Google Sans Flex 120pt Bold', // GoogleSansFlex_120pt-Bold.ttf
    'Google Sans Flex 120pt ExtraBold', // GoogleSansFlex_120pt-ExtraBold.ttf
    'Google Sans Flex 120pt ExtraLight', // GoogleSansFlex_120pt-ExtraLight.ttf
    'Google Sans Flex 120pt Light', // GoogleSansFlex_120pt-Light.ttf
    'Google Sans Flex 120pt Medium', // GoogleSansFlex_120pt-Medium.ttf
    'Google Sans Flex 120pt Regular', // GoogleSansFlex_120pt-Regular.ttf
    'Google Sans Flex 120pt SemiBold', // GoogleSansFlex_120pt-SemiBold.ttf
    'Google Sans Flex 120pt Thin', // GoogleSansFlex_120pt-Thin.ttf
    'Google Sans Flex 24pt Black', // GoogleSansFlex_24pt-Black.ttf
    'Google Sans Flex 24pt Bold', // GoogleSansFlex_24pt-Bold.ttf
    'Google Sans Flex 24pt ExtraBold', // GoogleSansFlex_24pt-ExtraBold.ttf
    'Google Sans Flex 24pt ExtraLight', // GoogleSansFlex_24pt-ExtraLight.ttf
    'Google Sans Flex 24pt Light', // GoogleSansFlex_24pt-Light.ttf
    'Google Sans Flex 24pt Medium', // GoogleSansFlex_24pt-Medium.ttf
    'Google Sans Flex 24pt Regular', // GoogleSansFlex_24pt-Regular.ttf
    'Google Sans Flex 24pt SemiBold', // GoogleSansFlex_24pt-SemiBold.ttf
    'Google Sans Flex 24pt Thin', // GoogleSansFlex_24pt-Thin.ttf
    'Google Sans Flex 36pt Black', // GoogleSansFlex_36pt-Black.ttf
    'Google Sans Flex 36pt Bold', // GoogleSansFlex_36pt-Bold.ttf
    'Google Sans Flex 36pt ExtraBold', // GoogleSansFlex_36pt-ExtraBold.ttf
    'Google Sans Flex 36pt ExtraLight', // GoogleSansFlex_36pt-ExtraLight.ttf
    'Google Sans Flex 36pt Light', // GoogleSansFlex_36pt-Light.ttf
    'Google Sans Flex 36pt Medium', // GoogleSansFlex_36pt-Medium.ttf
    'Google Sans Flex 36pt Regular', // GoogleSansFlex_36pt-Regular.ttf
    'Google Sans Flex 36pt SemiBold', // GoogleSansFlex_36pt-SemiBold.ttf
    'Google Sans Flex 36pt Thin', // GoogleSansFlex_36pt-Thin.ttf
    'Google Sans Flex 72pt Black', // GoogleSansFlex_72pt-Black.ttf
    'Google Sans Flex 72pt Bold', // GoogleSansFlex_72pt-Bold.ttf
    'Google Sans Flex 72pt ExtraBold', // GoogleSansFlex_72pt-ExtraBold.ttf
    'Google Sans Flex 72pt ExtraLight', // GoogleSansFlex_72pt-ExtraLight.ttf
    'Google Sans Flex 72pt Light', // GoogleSansFlex_72pt-Light.ttf
    'Google Sans Flex 72pt Medium', // GoogleSansFlex_72pt-Medium.ttf
    'Google Sans Flex 72pt Regular', // GoogleSansFlex_72pt-Regular.ttf
    'Google Sans Flex 72pt SemiBold', // GoogleSansFlex_72pt-SemiBold.ttf
    'Google Sans Flex 72pt Thin', // GoogleSansFlex_72pt-Thin.ttf
    'Google Sans Flex 9pt Black', // GoogleSansFlex_9pt-Black.ttf
    'Google Sans Flex 9pt Bold', // GoogleSansFlex_9pt-Bold.ttf
    'Google Sans Flex 9pt ExtraBold', // GoogleSansFlex_9pt-ExtraBold.ttf
    'Google Sans Flex 9pt ExtraLight', // GoogleSansFlex_9pt-ExtraLight.ttf
    'Google Sans Flex 9pt Light', // GoogleSansFlex_9pt-Light.ttf
    'Google Sans Flex 9pt Medium', // GoogleSansFlex_9pt-Medium.ttf
    'Google Sans Flex 9pt Regular', // GoogleSansFlex_9pt-Regular.ttf
    'Google Sans Flex 9pt SemiBold', // GoogleSansFlex_9pt-SemiBold.ttf
    'Google Sans Flex 9pt Thin', // GoogleSansFlex_9pt-Thin.ttf
    'Google Sans Italic', // GoogleSans-Italic.ttf
    'Google Sans Medium', // GoogleSans-Medium.ttf
    'Google Sans Medium Italic', // GoogleSans-MediumItalic.ttf
    'Google Sans Regular', // GoogleSans-Regular.ttf
    'Google Sans SemiBold', // GoogleSans-SemiBold.ttf
    'Google Sans SemiBold Italic', // GoogleSans-SemiBoldItalic.ttf
    'Instrument Sans', // InstrumentSans-Italic-VariableFont_wdth,wght.ttf, InstrumentSans-VariableFont_wdth,wght.ttf
    'Instrument Sans Bold', // InstrumentSans-Bold.ttf
    'Instrument Sans Bold Italic', // InstrumentSans-BoldItalic.ttf
    'Instrument Sans Italic', // InstrumentSans-Italic.ttf
    'Instrument Sans Medium', // InstrumentSans-Medium.ttf
    'Instrument Sans Medium Italic', // InstrumentSans-MediumItalic.ttf
    'Instrument Sans Regular', // InstrumentSans-Regular.ttf
    'Instrument Sans SemiBold', // InstrumentSans-SemiBold.ttf
    'Instrument Sans SemiBold Italic', // InstrumentSans-SemiBoldItalic.ttf
    'Open Sans', // OpenSans-Italic-VariableFont_wdth,wght.ttf, OpenSans-VariableFont_wdth,wght.ttf
    'Open Sans Bold', // OpenSans-Bold.ttf
    'Open Sans Bold Italic', // OpenSans-BoldItalic.ttf
    'Open Sans Condensed Bold', // OpenSans_Condensed-Bold.ttf
    'Open Sans Condensed Bold Italic', // OpenSans_Condensed-BoldItalic.ttf
    'Open Sans Condensed ExtraBold', // OpenSans_Condensed-ExtraBold.ttf
    'Open Sans Condensed ExtraBold Italic', // OpenSans_Condensed-ExtraBoldItalic.ttf
    'Open Sans Condensed Italic', // OpenSans_Condensed-Italic.ttf
    'Open Sans Condensed Light', // OpenSans_Condensed-Light.ttf
    'Open Sans Condensed Light Italic', // OpenSans_Condensed-LightItalic.ttf
    'Open Sans Condensed Medium', // OpenSans_Condensed-Medium.ttf
    'Open Sans Condensed Medium Italic', // OpenSans_Condensed-MediumItalic.ttf
    'Open Sans Condensed Regular', // OpenSans_Condensed-Regular.ttf
    'Open Sans Condensed SemiBold', // OpenSans_Condensed-SemiBold.ttf
    'Open Sans Condensed SemiBold Italic', // OpenSans_Condensed-SemiBoldItalic.ttf
    'Open Sans ExtraBold', // OpenSans-ExtraBold.ttf
    'Open Sans ExtraBold Italic', // OpenSans-ExtraBoldItalic.ttf
    'Open Sans Italic', // OpenSans-Italic.ttf
    'Open Sans Light', // OpenSans-Light.ttf
    'Open Sans Light Italic', // OpenSans-LightItalic.ttf
    'Open Sans Medium', // OpenSans-Medium.ttf
    'Open Sans Medium Italic', // OpenSans-MediumItalic.ttf
    'Open Sans Regular', // OpenSans-Regular.ttf
    'Open Sans SemiBold', // OpenSans-SemiBold.ttf
    'Open Sans SemiBold Italic', // OpenSans-SemiBoldItalic.ttf
    'Open Sans SemiCondensed Bold', // OpenSans_SemiCondensed-Bold.ttf
    'Open Sans SemiCondensed Bold Italic', // OpenSans_SemiCondensed-BoldItalic.ttf
    'Open Sans SemiCondensed ExtraBold', // OpenSans_SemiCondensed-ExtraBold.ttf
    'Open Sans SemiCondensed ExtraBold Italic', // OpenSans_SemiCondensed-ExtraBoldItalic.ttf
    'Open Sans SemiCondensed Italic', // OpenSans_SemiCondensed-Italic.ttf
    'Open Sans SemiCondensed Light', // OpenSans_SemiCondensed-Light.ttf
    'Open Sans SemiCondensed Light Italic', // OpenSans_SemiCondensed-LightItalic.ttf
    'Open Sans SemiCondensed Medium', // OpenSans_SemiCondensed-Medium.ttf
    'Open Sans SemiCondensed Medium Italic', // OpenSans_SemiCondensed-MediumItalic.ttf
    'Open Sans SemiCondensed Regular', // OpenSans_SemiCondensed-Regular.ttf
    'Open Sans SemiCondensed SemiBold', // OpenSans_SemiCondensed-SemiBold.ttf
    'Open Sans SemiCondensed SemiBold Italic', // OpenSans_SemiCondensed-SemiBoldItalic.ttf
    'Roboto', // Roboto-Italic-VariableFont_wdth,wght.ttf, Roboto-VariableFont_wdth,wght.ttf
    'Roboto Black', // Roboto-Black.ttf
    'Roboto Black Italic', // Roboto-BlackItalic.ttf
    'Roboto Bold', // Roboto-Bold.ttf
    'Roboto Bold Italic', // Roboto-BoldItalic.ttf
    'Roboto Condensed Black', // Roboto_Condensed-Black.ttf
    'Roboto Condensed Black Italic', // Roboto_Condensed-BlackItalic.ttf
    'Roboto Condensed Bold', // Roboto_Condensed-Bold.ttf
    'Roboto Condensed Bold Italic', // Roboto_Condensed-BoldItalic.ttf
    'Roboto Condensed ExtraBold', // Roboto_Condensed-ExtraBold.ttf
    'Roboto Condensed ExtraBold Italic', // Roboto_Condensed-ExtraBoldItalic.ttf
    'Roboto Condensed ExtraLight', // Roboto_Condensed-ExtraLight.ttf
    'Roboto Condensed ExtraLight Italic', // Roboto_Condensed-ExtraLightItalic.ttf
    'Roboto Condensed Italic', // Roboto_Condensed-Italic.ttf
    'Roboto Condensed Light', // Roboto_Condensed-Light.ttf
    'Roboto Condensed Light Italic', // Roboto_Condensed-LightItalic.ttf
    'Roboto Condensed Medium', // Roboto_Condensed-Medium.ttf
    'Roboto Condensed Medium Italic', // Roboto_Condensed-MediumItalic.ttf
    'Roboto Condensed Regular', // Roboto_Condensed-Regular.ttf
    'Roboto Condensed SemiBold', // Roboto_Condensed-SemiBold.ttf
    'Roboto Condensed SemiBold Italic', // Roboto_Condensed-SemiBoldItalic.ttf
    'Roboto Condensed Thin', // Roboto_Condensed-Thin.ttf
    'Roboto Condensed Thin Italic', // Roboto_Condensed-ThinItalic.ttf
    'Roboto ExtraBold', // Roboto-ExtraBold.ttf
    'Roboto ExtraBold Italic', // Roboto-ExtraBoldItalic.ttf
    'Roboto ExtraLight', // Roboto-ExtraLight.ttf
    'Roboto ExtraLight Italic', // Roboto-ExtraLightItalic.ttf
    'Roboto Italic', // Roboto-Italic.ttf
    'Roboto Light', // Roboto-Light.ttf
    'Roboto Light Italic', // Roboto-LightItalic.ttf
    'Roboto Medium', // Roboto-Medium.ttf
    'Roboto Medium Italic', // Roboto-MediumItalic.ttf
    'Roboto Regular', // Roboto-Regular.ttf
    'Roboto SemiBold', // Roboto-SemiBold.ttf
    'Roboto SemiBold Italic', // Roboto-SemiBoldItalic.ttf
    'Roboto SemiCondensed Black', // Roboto_SemiCondensed-Black.ttf
    'Roboto SemiCondensed Black Italic', // Roboto_SemiCondensed-BlackItalic.ttf
    'Roboto SemiCondensed Bold', // Roboto_SemiCondensed-Bold.ttf
    'Roboto SemiCondensed Bold Italic', // Roboto_SemiCondensed-BoldItalic.ttf
    'Roboto SemiCondensed ExtraBold', // Roboto_SemiCondensed-ExtraBold.ttf
    'Roboto SemiCondensed ExtraBold Italic', // Roboto_SemiCondensed-ExtraBoldItalic.ttf
    'Roboto SemiCondensed ExtraLight', // Roboto_SemiCondensed-ExtraLight.ttf
    'Roboto SemiCondensed ExtraLight Italic', // Roboto_SemiCondensed-ExtraLightItalic.ttf
    'Roboto SemiCondensed Italic', // Roboto_SemiCondensed-Italic.ttf
    'Roboto SemiCondensed Light', // Roboto_SemiCondensed-Light.ttf
    'Roboto SemiCondensed Light Italic', // Roboto_SemiCondensed-LightItalic.ttf
    'Roboto SemiCondensed Medium', // Roboto_SemiCondensed-Medium.ttf
    'Roboto SemiCondensed Medium Italic', // Roboto_SemiCondensed-MediumItalic.ttf
    'Roboto SemiCondensed Regular', // Roboto_SemiCondensed-Regular.ttf
    'Roboto SemiCondensed SemiBold', // Roboto_SemiCondensed-SemiBold.ttf
    'Roboto SemiCondensed SemiBold Italic', // Roboto_SemiCondensed-SemiBoldItalic.ttf
    'Roboto SemiCondensed Thin', // Roboto_SemiCondensed-Thin.ttf
    'Roboto SemiCondensed Thin Italic', // Roboto_SemiCondensed-ThinItalic.ttf
    'Roboto Thin', // Roboto-Thin.ttf
    'Roboto Thin Italic', // Roboto-ThinItalic.ttf
];
