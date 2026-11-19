<div
    data-symbol-manager
    data-symbols='@json($symbols)'
    data-upload-url="{{ route('dobble.symbols.upload', ['symbol' => '__SYMBOL_ID__']) }}"
    data-remove-url="{{ route('dobble.symbols.remove', ['symbol' => '__SYMBOL_ID__']) }}"
    data-move-url="{{ route('dobble.symbols.move', ['symbol' => '__SYMBOL_ID__']) }}"
></div>
