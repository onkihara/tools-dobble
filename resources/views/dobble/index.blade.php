@extends('dobble.frame')

@section('main')

    @include('dobble.partials.hero')

    @if (session('status'))
        <div role="status" class="mt-6 border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mt-6 border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900">
            <p class="font-semibold">Bitte prüfe deine Eingaben:</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('dobble.partials.configuration')
    @include('dobble.partials.symbols')
    @include('dobble.partials.preview')

@endsection



