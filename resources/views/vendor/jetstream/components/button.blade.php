<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-hero-success mb-3']) }}>
    {{ $slot }}
</button>
