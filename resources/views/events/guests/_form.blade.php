<div class="row g-4">
    <div class="col-lg-8">
        <section class="card"><div class="card-body p-4">
            <h2 class="h4">Guest identity</h2>
            <div class="row g-3">
                <div class="col-md-6"><x-ui.form.input name="first_name" label="First name" :value="$guest->first_name" required/></div>
                <div class="col-md-6"><x-ui.form.input name="last_name" label="Last name" :value="$guest->last_name"/></div>
                <div class="col-md-6"><x-ui.form.input name="email" label="Email" type="email" :value="$guest->email"/></div>
                <div class="col-md-6"><x-ui.form.input name="phone" label="Phone" :value="$guest->phone"/></div>
                <div class="col-md-6"><x-ui.form.input name="external_reference" label="External/import reference" :value="$guest->external_reference" help="Optional stable reference for a later import process."/></div>
                <div class="col-md-6"><x-ui.form.select name="source" label="Record source" :options="collect(['manual'=>'Manual','import'=>'Import','registration'=>'Registration'])" :value="$guest->source ?: 'manual'"/></div>
            </div>
        </div></section>
    </div>
    <div class="col-lg-4">
        <section class="card"><div class="card-body p-4">
            <h2 class="h4">Party and group</h2>
            <x-ui.form.select name="guest_group_id" label="Family/group" :options="$groups->pluck('name','id')" :value="$guest->groupMembership?->guest_group_id" placeholder="No group"/>
            <x-ui.form.input name="relationship_label" label="Relationship in group" :value="$guest->groupMembership?->relationship_label" placeholder="e.g. Parent, colleague"/>
            <x-ui.form.select name="plus_one_policy" label="Plus-one policy" :options="collect(['none'=>'No plus-one','allowed'=>'Allowed','approval_required'=>'Approval required'])" :value="$guest->plus_one_policy ?: 'none'" required/>
            <x-ui.form.input name="plus_one_limit" label="Plus-one limit" type="number" min="0" max="20" :value="$guest->plus_one_limit ?? 0"/>
            <x-ui.form.input name="invited_party_size" label="Invited party size" type="number" min="1" max="100" :value="$guest->invited_party_size ?: 1" required/>
            <input type="hidden" name="is_vip" value="0">
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_vip" value="1" id="is_vip" @checked(old('is_vip', $guest->is_vip))><label class="form-check-label" for="is_vip">VIP guest</label></div>
        </div></section>
    </div>
</div>
