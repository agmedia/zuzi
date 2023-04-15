<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-hero-danger my-2']) }}>
    {{ $slot }}
</button>
