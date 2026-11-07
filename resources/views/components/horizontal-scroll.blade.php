@props(['id', 'label'])

<div class="horizontal-scroll" data-horizontal-scroll>
    <div class="horizontal-scroll__controls" data-scroll-controls hidden>
        <div class="horizontal-scroll__toolbar">
            <span>Desplaza para ver todas las columnas</span>
            <div>
                <button type="button" data-scroll-direction="-1" aria-controls="{{ $id }}" aria-label="Desplazar columnas a la izquierda">←</button>
                <button type="button" data-scroll-direction="1" aria-controls="{{ $id }}" aria-label="Desplazar columnas a la derecha">→</button>
            </div>
        </div>
        <div class="horizontal-scroll__top" data-scroll-top tabindex="0" role="region" aria-label="Desplazamiento superior: {{ $label }}" aria-controls="{{ $id }}">
            <div data-scroll-spacer></div>
        </div>
    </div>
    <div {{ $attributes->class(['horizontal-scroll__body']) }} id="{{ $id }}" data-scroll-body tabindex="0" role="region" aria-label="{{ $label }}">
        {{ $slot }}
    </div>
</div>
