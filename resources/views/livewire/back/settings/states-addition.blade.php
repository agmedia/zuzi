<div>
    <div class="form-group mb-4" wire:ignore>
        <label for="countries-select">Države</label>
        <select class="js-select2 form-control" id="countries-select" style="width: 100%;">
            <option></option>
            @foreach ($countries as $country)
                <option value="{{ $country['name'] }}">{{ $country['name'] }}</option>
            @endforeach
        </select>
    </div>

    @if ( ! empty($states))
        <table class="table table-striped table-borderless table-vcenter mt-5">
            <thead class="thead-light">
            <tr>
                <th style="width: 80%;">Lista država unutar geo zone</th>
                <th class="text-right">Obriši</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($states as $key => $state)
                <tr>
                    <td>
                        {{ $state }}
                        <input type="hidden" name="state[{{ $key + 1 }}]" value="{{ $state }}">
                    </td>
                    <td class="text-right font-size-sm">
                        <a class="btn btn-sm btn-alt-danger" wire:click="deleteState('{{ $state }}')">
                            <i class="fa fa-fw fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

</div>

@push('js_after')
    <script>
        document.addEventListener('livewire:load', function () {
            $('#countries-select').select2({
                placeholder: "Odaberi državu..."
            });

            $('#countries-select').on('change', function (e) {
                var data = $('#countries-select').select2("val");
                console.log(data);
                @this.stateSelected(data);
            });
        });

        Livewire.on('success_alert', () => {

        });

        Livewire.on('error_alert', () => {

        });
    </script>
@endpush
