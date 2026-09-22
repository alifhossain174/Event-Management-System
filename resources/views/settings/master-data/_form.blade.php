<x-ui.form.input name="name" label="Name" :value="$category->name ?? null" required/>
<x-ui.form.input name="slug" label="Stable slug" :value="$category->slug ?? null" help="Leave blank to generate from the name. Once referenced by business records, avoid changing it."/>
<x-ui.form.textarea name="description" label="Description" :value="$category->description ?? null" rows="3"/>
@if($definition['direction'] ?? false)
    <x-ui.form.select name="direction" label="Finance direction" :options="['income' => 'Income', 'expense' => 'Expense', 'both' => 'Both']" :value="$category->direction ?? 'both'" required/>
@endif
<x-ui.form.input name="sort_order" label="Sort order" type="number" min="0" max="65535" :value="$category->sort_order ?? 0" required/>
<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))><label class="form-check-label" for="is_active">Active</label></div>
