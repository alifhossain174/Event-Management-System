@php
    $recommendations = old('module_recommendations', isset($eventTemplate) ? $eventTemplate->modules->pluck('recommendation','module_key')->all() : []);
    $starterTasksText = old('starter_tasks_text', isset($eventTemplate) ? collect($eventTemplate->starter_tasks ?? [])->pluck('title')->join("\n") : '');
    $budgetLinesText = old('budget_lines_text', isset($eventTemplate) ? collect($eventTemplate->budget_lines ?? [])->map(fn($line) => ($line['direction']??'expense').'|'.($line['label']??'').'|'.($line['amount']??''))->join("\n") : '');
@endphp
<x-ui.validation-summary class="mb-4"/>
<div class="row g-4"><div class="col-xl-7">
    <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Template identity</h2>
        <x-ui.form.input name="name" label="Template name" :value="$eventTemplate->name??null" required/>
        <x-ui.form.input name="slug" label="Stable template slug" :value="$eventTemplate->slug??null" help="Generated from the name when left blank. Existing Event snapshots use copied data, not this slug."/>
        <x-ui.form.select name="event_category_id" label="Event category" :options="$categories->pluck('name','id')" :value="$eventTemplate->event_category_id??null" placeholder="No category"/>
        <x-ui.form.textarea name="description" label="Description" :value="$eventTemplate->description??null" rows="3"/>
        <x-ui.form.textarea name="service_notes" label="Suggested service notes" :value="$eventTemplate->service_notes??null" rows="5" help="Copied into a future Event initialization plan and always editable there."/>
    </div></section>
    <section class="card"><div class="card-body p-4"><h2 class="h4">Module suggestions</h2><p class="text-secondary">Default modules start enabled; optional modules are highlighted but start disabled. Every selection remains editable per Event.</p>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Module</th><th>Stable key</th><th>Recommendation</th></tr></thead><tbody>
        @foreach($moduleDefinitions as $definition)<tr><td>{{ $definition->label }} @unless($definition->is_active)<span class="badge text-bg-secondary">Inactive</span>@endunless</td><td><code>{{ $definition->key }}</code></td><td><select class="form-select" name="module_recommendations[{{ $definition->key }}]" aria-label="Recommendation for {{ $definition->label }}"><option value="disabled" @selected(($recommendations[$definition->key]??'disabled')==='disabled')>Not suggested</option><option value="default" @selected(($recommendations[$definition->key]??'')==='default')>Default enabled</option><option value="optional" @selected(($recommendations[$definition->key]??'')==='optional')>Optional add-on</option></select></td></tr>@endforeach
        </tbody></table></div>
    </div></section>
</div><div class="col-xl-5">
    <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Optional starter tasks</h2><x-ui.form.textarea name="starter_tasks_text" label="One task per line" :value="$starterTasksText" rows="10" help="These are copied as editable suggestions when an Event is initialized."/></div></section>
    <section class="card"><div class="card-body p-4"><h2 class="h4">Optional budget lines</h2><x-ui.form.textarea name="budget_lines_text" label="One line per budget item" :value="$budgetLinesText" rows="10" help="Format: expense|Venue estimate|0.00 or income|Sponsorship|0.00. Values are suggestions only."/></div></section>
</div></div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">{{ isset($eventTemplate)?'Save changes':'Create template' }}</button><a class="btn btn-outline-secondary" href="{{ isset($eventTemplate)?route('settings.event-templates.show',$eventTemplate):route('settings.event-templates.index') }}">Cancel</a></div>
