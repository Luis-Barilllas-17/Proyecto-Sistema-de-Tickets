@props(['id', 'name', 'label', 'endpoint', 'selected' => [], 'multiple' => false, 'disabled' => false, 'hint' => '', 'placeholder' => 'Escribe para buscar…', 'limit' => 10])

<div class="field search-picker" data-search-picker data-label="{{ mb_strtolower($label) }}" data-name="{{ $name }}" data-endpoint="{{ $endpoint }}" data-multiple="{{ $multiple ? 'true' : 'false' }}" data-disabled="{{ $disabled ? 'true' : 'false' }}" data-limit="{{ $limit }}" data-selected="{{ json_encode($selected) }}">
    <label for="{{ $id }}-search">{{ $label }}</label>
    <div class="search-picker__control">
        <input id="{{ $id }}-search" type="search" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}-results" aria-describedby="{{ $id }}-hint" placeholder="{{ $placeholder }}" maxlength="100" value="{{ ! $multiple ? ($selected[0]['label'] ?? '') : '' }}" @disabled($disabled) data-picker-search>
        @unless ($multiple || $disabled)<button type="button" class="search-picker__clear" data-picker-clear aria-label="Cambiar {{ mb_strtolower($label) }}">×</button>@endunless
    </div>
    <div class="search-picker__results" id="{{ $id }}-results" role="listbox" aria-label="Resultados de {{ mb_strtolower($label) }}" data-picker-results hidden></div>
    <div class="search-picker__selected" data-picker-selected>
        @if ($multiple)
            @foreach ($selected as $item)<span class="search-picker__chip"><span>{{ $item['label'] }}</span></span>@endforeach
        @endif
    </div>
    <div data-picker-inputs>
        @unless ($disabled)
            @if ($multiple)
                @foreach ($selected as $item)<input type="hidden" name="{{ $item['input_name'] ?? $name }}" value="{{ $item['input_value'] ?? $item['value'] }}">@endforeach
            @else
                <input id="{{ $name }}" type="hidden" name="{{ $name }}" value="{{ $selected[0]['value'] ?? '' }}">
            @endif
        @endunless
    </div>
    <span id="{{ $id }}-hint" class="field__hint">{{ $hint }}</span>
    <span class="search-picker__status" role="status" aria-live="polite" data-picker-status></span>
</div>
