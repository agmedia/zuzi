@props(['submit'])

<div {{ $attributes->merge(['class' => 'block block-rounded mb-5']) }}>
    <div class="block-header block-header-default">
        <span class="font-weight-bold">{{ $title }}</span>
    </div>

    <form wire:submit.prevent="{{ $submit }}">
        <div class="block-content">
            <div class="row push {{ isset($actions) ? '' : 'text-center' }}">
                <div class="col-lg-4">
                    <p class="text-muted">{{ $description }}</p>
                </div>
                <div class="col-lg-8 col-xl-5">
                    {{ $form }}
                </div>
            </div>
        </div>
        @if (isset($actions))
            <div class="block-content bg-body-light">
                <div class="row">
                    <div class="col-sm-12">
                        {{ $actions }}
                    </div>
                </div>
            </div>
        @endif
    </form>
</div>
