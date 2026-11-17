<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dobble-Werkstatt</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f5ef] text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-[#FF9900]">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
            <a href="https://www.blikk.it" class="flex items-center gap-3 font-semibold tracking-tight">
                <img src="{{ asset('blikk_gross.png') }}" alt="blikk.it" class="h-8 w-auto">
                <span class="text-xl">Dobble-Werkstatt</span>
            </a>
            <span class="hidden text-sm text-slate-500 sm:block">Dein eigenes Symbolkarten-Set</span>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-12">
        @yield('main')
    </main>
    <footer class="py-8 text-center text-sm bg-[#FF9900]">

        <p>Die Bilder und Set-Einstellungen werden nur während deiner aktuellen Sitzung gespeichert.</p>
        <div>

	    <p>&copy; <a class="text-red-700 hover:text-red-900" href="https://www.blikk.it">Bildungsserver blikk</a> <span class="e">der <a class="text-red-700 hover:text-red-900" href="https://deutsche-bildung.provinz.bz.it/de/didaktik-beratung">Pädagogischen Abteilung</a> - Bozen @php echo date('Y') @endphp</span></p>

</div>
    </footer>
</body>
</html>
