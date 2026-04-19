@php
    $combinedCategoryRules = old('combined_categories', isset($action) ? ($action->data['combined_categories'] ?? []) : []);
    $combinedCategoryRules = collect($combinedCategoryRules)
        ->map(function ($rule) {
            if (is_object($rule)) {
                $rule = (array) $rule;
            }

            return [
                'category_id' => isset($rule['category_id']) ? (int) $rule['category_id'] : null,
                'discount' => isset($rule['discount']) ? $rule['discount'] : null,
                'apply_to' => $rule['apply_to'] ?? 'all',
            ];
        })
        ->values();
@endphp

<div class="block block-rounded d-none" id="combined-category-module">
    <div class="block-header block-header-default">
        <h3 class="block-title">Kombinirane kategorije</h3>
        <div class="block-options">
            <button type="button" class="btn btn-sm btn-alt-success" id="add-combined-category-row">
                <i class="fa fa-plus mr-1"></i> Dodaj red
            </button>
        </div>
    </div>
    <div class="block-content">
        <div class="alert alert-info mb-3">
            Za svaki red odaberi kategoriju, postotak popusta i odnosi li se akcija na sve knjige ili samo na rabljene.
            Rabljene knjige su svi proizvodi kojima `condition` nije `NOVO` ni `Nova knjiga`.
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-striped table-vcenter">
                <thead>
                    <tr>
                        <th>Kategorija</th>
                        <th style="width: 170px;">Popust</th>
                        <th style="width: 220px;">Primjena</th>
                        <th class="text-right" style="width: 90px;">Makni</th>
                    </tr>
                </thead>
                <tbody id="combined-category-rows" data-next-index="{{ $combinedCategoryRules->count() }}">
                    @foreach ($combinedCategoryRules as $index => $rule)
                        <tr class="combined-category-row" data-index="{{ $index }}">
                            <td>
                                <select
                                    class="form-control combined-category-select"
                                    name="combined_categories[{{ $index }}][category_id]"
                                    data-selected-category-id="{{ $rule['category_id'] ?: '' }}"
                                >
                                    <option value=""></option>
                                    @foreach ($category_options as $option)
                                        <option value="{{ $option->id }}" @if((int) $rule['category_id'] === (int) $option->id) selected @endif>
                                            {{ $option->label }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <div class="input-group">
                                    <input
                                        type="number"
                                        class="form-control"
                                        name="combined_categories[{{ $index }}][discount]"
                                        value="{{ $rule['discount'] }}"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                    >
                                    <div class="input-group-append">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <select class="form-control" name="combined_categories[{{ $index }}][apply_to]">
                                    <option value="all" @if(($rule['apply_to'] ?? 'all') === 'all') selected @endif>Sve knjige</option>
                                    <option value="used" @if(($rule['apply_to'] ?? 'all') === 'used') selected @endif>Samo rabljene</option>
                                </select>
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-sm btn-alt-danger remove-combined-category-row">
                                    <i class="fa fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-muted font-size-sm mb-0 @if ($combinedCategoryRules->isNotEmpty()) d-none @endif" id="combined-category-empty-state">
            Još nema dodanih kategorija. Dodaj prvi red za konfiguraciju akcije.
        </p>
    </div>
</div>

<script type="text/template" id="combined-category-row-template">
    <tr class="combined-category-row" data-index="__INDEX__">
        <td>
            <select class="form-control combined-category-select" name="combined_categories[__INDEX__][category_id]">
                <option value=""></option>
                @foreach ($category_options as $option)
                    <option value="{{ $option->id }}">{{ $option->label }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <div class="input-group">
                <input
                    type="number"
                    class="form-control"
                    name="combined_categories[__INDEX__][discount]"
                    value="__DISCOUNT__"
                    min="0"
                    step="0.01"
                    placeholder="0"
                >
                <div class="input-group-append">
                    <span class="input-group-text">%</span>
                </div>
            </div>
        </td>
        <td>
            <select class="form-control" name="combined_categories[__INDEX__][apply_to]">
                <option value="all" __ALL_SELECTED__>Sve knjige</option>
                <option value="used" __USED_SELECTED__>Samo rabljene</option>
            </select>
        </td>
        <td class="text-right">
            <button type="button" class="btn btn-sm btn-alt-danger remove-combined-category-row">
                <i class="fa fa-times"></i>
            </button>
        </td>
    </tr>
</script>
