<div class="block block-rounded">
    <div class="block-content bg-body-light" style="padding: 12px 20px;">
        <div class="row">
            <div class="col-md-12">
                <p class="text-sm font-weight-bold mb-1">{!! $title !!}</p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="block-options pl-0">
                    <input type="search" wire:model.debounce.300ms="search" class="form-control" style="display: block;" placeholder="Traži..." @if($disabled) disabled @endif>
                    @if( ! empty($search_results))
                        <div class="autocomplete" >
                            <div id="myInputautocomplete-list" class="autocomplete-items">
                                @foreach($search_results as $item)
                                    @php
                                        $itemTitle = isset($item->title) ? $item->title : (isset($item->name) ? $item->name : trim((string) data_get($item, 'fname') . ' ' . (string) data_get($item, 'lname')));
                                        $itemTitle = $itemTitle !== '' ? $itemTitle : \Illuminate\Support\Str::limit(strip_tags((string) data_get($item, 'message')), 70);
                                        $itemMeta = isset($item->sku) ? $item->sku : trim((string) data_get($item, 'product.name'));
                                    @endphp
                                    <div wire:click="addItem({{ $item->id }})">{{ $itemTitle }}{{ $itemMeta ? ' - ' . $itemMeta : '' }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="block-content">
        <!-- All Products Table -->
        <div class="table-responsive">
            <table class="table table-sm table-borderless table-striped table-vcenter">
                <thead>
                <tr>
                    <th>Naziv</th>
                    <th class="text-right" style="width: 100px;">Izbriši</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($list as $item)
                    @php
                        $itemTitle = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : trim((string) data_get($item, 'fname') . ' ' . (string) data_get($item, 'lname')));
                        $itemTitle = $itemTitle !== '' ? $itemTitle : \Illuminate\Support\Str::limit(strip_tags((string) data_get($item, 'message')), 70);
                        $itemMeta = isset($item['sku']) ? $item['sku'] : trim((string) data_get($item, 'product.name'));
                    @endphp
                    <tr>
                        <td class="font-size-sm">
                            {{ $itemTitle }}{{ $itemMeta ? ' - ' . $itemMeta : '' }}
                            <input type="hidden" name="action_list[{{ isset($item['id']) ? $item['id'] : '' }}]" value="{{ isset($item['id']) ? $item['id'] : '' }}">
                        </td>
                        <td class="text-right font-size-sm">
                            <a class="btn btn-sm btn-alt-secondary" href="javascript:void(0)" wire:click="removeItem({{ isset($item['id']) ? $item['id'] : '' }})">
                                <i class="fa fa-fw fa-times text-danger"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <input type="hidden" value="{{ $group }}" name="action_group">

    </div>
</div>
