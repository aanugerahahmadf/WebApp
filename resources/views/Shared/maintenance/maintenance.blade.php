<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Mode Pemeliharaan') }} — {{ $brandName }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/css/app-font.css" />
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-6 selection:bg-indigo-500 selection:text-white" style="font-family: 'Google Sans Flex', sans-serif;">
    <div class="max-w-md w-full text-center space-y-6">
        <div class="relative inline-flex items-center justify-center">
            <div class="w-20 h-20 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center animate-pulse">
                <svg class="w-10 h-10 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.32l-3.276 3.277a3.004 3.004 0 01-2.25 2.25l-3.276-3.276a4.5 4.5 0 00-6.32 4.486c.048.58.072 1.193-.092 1.743" />
                </svg>
            </div>
        </div>

        <div class="space-y-2">
            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                {{ __('Sedang Dalam Pemeliharaan') }}
            </h1>
            <p class="text-sm text-gray-400 font-medium">
                {{ $brandName }} ({{ ucfirst($panel) }})
            </p>
        </div>

        <div class="p-5 rounded-2xl bg-gray-800/60 border border-gray-700/60 text-sm text-gray-300 leading-relaxed backdrop-blur-md shadow-xl">
            {{ $message }}
        </div>

        <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="/" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-all shadow-md">
                {{ __('Muat Ulang Halaman') }}
            </a>
            <a href="/admin/signin" class="px-4 py-2.5 rounded-xl border border-gray-700 text-gray-400 hover:text-white font-medium text-xs transition-all">
                {{ __('Masuk Sebagai Admin') }}
            </a>
        </div>
    </div>
</body>
</html>
