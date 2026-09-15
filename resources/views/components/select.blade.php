@props([
    'label' => '',
    'name' => '',
    'options' => [],
    'value' => null,
    'emptyOption' => null,
])

<label class="block">
    @if ($label)
        <span class="label">{{ $label }}</span>
    @endif

    <select
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'select']) }}
    >
        @if ($emptyOption !== null)
            <option value="" @selected(old($name, $value) === null || old($name, $value) === '')>{{ $emptyOption }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
</label>