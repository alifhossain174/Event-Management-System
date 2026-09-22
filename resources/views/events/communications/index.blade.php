<x-layouts.app :title="'Communications · '.$event->name" wide :breadcrumbs="[['label'=>'Overview','url'=>route('home')],['label'=>'Events','url'=>route('events.index')],['label'=>$event->name,'url'=>route('events.show',$event)],['label'=>'Communications']]">
    <x-ui.page-header title="Communication center" :subtitle="$event->reference_number.' · '.$event->name"><x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('events.show',$event) }}">Back to event</a></x-slot:actions></x-ui.page-header>
    <div class="alert alert-info">Delivery is synchronous. Email uses the configured Laravel mailer. SMS and WhatsApp remain disabled until real providers and credentials are configured. No queue, WebSocket, push, or broadcast server is used.</div>
    <div class="row g-4"><div class="col-xl-8">
        <section class="card"><div class="card-body p-4"><h2 class="h4">Message history</h2>
            <form class="row g-3 align-items-end mb-4" method="GET"><div class="col-md-4"><x-ui.form.select name="status" label="Status" :options="['pending'=>'Pending','sent'=>'Sent','failed'=>'Failed']" :value="$filters['status'] ?? ''" placeholder="All statuses"/></div><div class="col-md-4"><x-ui.form.select name="channel" label="Channel" :options="['email'=>'Email','sms'=>'SMS','whatsapp'=>'WhatsApp']" :value="$filters['channel'] ?? ''" placeholder="All channels"/></div><div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div></form>
            <x-ui.data-table :columns="[['label'=>'Message'],['label'=>'Recipient'],['label'=>'Channel'],['label'=>'Status'],['label'=>'Sent/attempted']]" caption="Outbound communications" :empty="$messages->isEmpty()" empty-title="No communications recorded">
                @foreach($messages as $message)<tr><td><a href="{{ route('events.communications.show',[$event,$message]) }}">{{ $message->subject ?: 'Message #'.$message->id }}</a><span class="d-block small text-secondary">{{ str($message->category)->headline() }}</span></td><td>{{ $message->recipients->first()?->address }}</td><td>{{ str($message->channel)->headline() }}</td><td><x-ui.status-badge :status="$message->status"/></td><td>{{ $message->last_attempt_at?->setTimezone($organizationTimezone)->format('Y-m-d H:i T') ?: 'Not attempted' }}</td></tr>@endforeach
            </x-ui.data-table><x-ui.pagination :paginator="$messages" class="mt-4"/>
        </div></section>
    </div><aside class="col-xl-4">
        @can('create',[App\Models\OutboundMessage::class,$event])
            <section class="card mb-4"><div class="card-body p-4"><h2 class="h4">Send message</h2>
                <form method="POST" action="{{ route('events.communications.store',$event) }}">@csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string)Illuminate\Support\Str::uuid()) }}">
                    <x-ui.form.select name="message_template_id" label="Template (optional)" :options="$templates->pluck('name','id')" placeholder="Manual message"/>
                    <x-ui.form.select name="channel" label="Channel" :options="collect(['email'=>'Email','sms'=>'SMS','whatsapp'=>'WhatsApp'])->map(fn($label,$key)=>$label.($availability[$key] ? '' : ' — unavailable'))" value="email" required/>
                    <x-ui.form.select name="category" label="Purpose" :options="['operational'=>'Operational','marketing'=>'Marketing']" value="operational" required/>
                    <x-ui.form.input name="recipient_address" type="email" label="Recipient email/address" :value="$event->client?->primary_email" required/>
                    <x-ui.form.input name="recipient_name" label="Recipient name" :value="$event->client?->display_name"/>
                    <x-ui.form.input name="subject" label="Subject"/>
                    <x-ui.form.textarea name="body" label="Body" rows="6" help="Leave blank to use the selected template body."/>
                    <div class="form-check mb-3"><input class="form-check-input" id="marketing_consent_confirmed" name="marketing_consent_confirmed" type="checkbox" value="1"><label class="form-check-label" for="marketing_consent_confirmed">Marketing consent explicitly confirmed (required only for marketing)</label></div>
                    <button class="btn btn-primary w-100" type="submit">Send synchronously</button>
                </form>
            </div></section>
        @endcan
        @if(auth()->user()->hasPermission('communications.configure'))
            <section class="card"><div class="card-body p-4"><h2 class="h4">Schedule my reminder</h2><form method="POST" action="{{ route('events.reminders.store',$event) }}">@csrf<x-ui.form.input name="due_at" type="datetime-local" label="Due at" required/><button class="btn btn-outline-primary w-100" type="submit">Schedule reminder</button></form>
                @if($reminders->isNotEmpty())<ul class="list-group list-group-flush mt-3">@foreach($reminders as $reminder)<li class="list-group-item px-0 d-flex justify-content-between"><span>{{ $reminder->due_at->setTimezone($organizationTimezone)->format('Y-m-d H:i') }}</span><x-ui.status-badge :status="$reminder->status"/></li>@endforeach</ul>@endif
            </div></section>
        @endif
    </aside></div>
</x-layouts.app>
