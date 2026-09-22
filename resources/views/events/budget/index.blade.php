<x-layouts.app
    :title="'Budget and ledger · '.$event->name"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Events', 'url' => route('events.index')],
        ['label' => $event->name, 'url' => route('events.show', $event)],
        ['label' => 'Budget and ledger'],
    ]"
>
    <x-ui.page-header title="Budget and financial ledger" :subtitle="$event->reference_number.' · '.$event->name">
        <x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('events.show', $event) }}">Back to event</a></x-slot:actions>
    </x-ui.page-header>

    <div class="alert alert-info" role="status">
        Planned budget lines are separate from actual ledger entries. Actual income, expenses, and profit include posted entries only; reversing a posted entry creates an equal posted reversal and preserves the original.
    </div>

    @if (! $budget)
        <section class="card"><div class="card-body p-4">
            <h2 class="h4">Create Event budget</h2>
            <p class="text-secondary">The Event currency is snapshotted as {{ $event->currency_code }}. Template budget suggestions, when present, are copied into editable draft lines.</p>
            @can('create', [\App\Models\EventBudget::class, $event])
                <form method="POST" action="{{ route('events.budget.store', $event) }}">@csrf<button class="btn btn-primary" type="submit">Create budget</button></form>
            @else
                <p class="mb-0 text-secondary">You can view this workspace but cannot create its budget.</p>
            @endcan
        </div></section>
    @else
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-secondary">Planned income</div><div class="fs-4 fw-semibold">{{ $summary['planned_income'] }} {{ $summary['currency_code'] }}</div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-secondary">Planned expense</div><div class="fs-4 fw-semibold">{{ $summary['planned_expense'] }} {{ $summary['currency_code'] }}</div></div></div></div>
            @can('finance.view')
                <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-secondary">Posted actual income / expense</div><div class="fs-5 fw-semibold">{{ $summary['actual_income'] }} / {{ $summary['actual_expense'] }} {{ $summary['currency_code'] }}</div></div></div></div>
                <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-secondary">Recognized profit</div><div class="fs-4 fw-semibold">{{ $summary['profit'] }} {{ $summary['currency_code'] }}</div></div></div></div>
            @endcan
        </div>

        <x-ui.filter-bar :action="route('events.budget.index', $event)">
            <div class="col-lg-3"><x-ui.form.input name="q" label="Search description" :value="$filters['q'] ?? ''"/></div>
            <div class="col-md-3 col-lg-2"><x-ui.form.select name="direction" label="Direction" :options="['income'=>'Income','expense'=>'Expense']" :selected="$filters['direction'] ?? ''" placeholder="Both"/></div>
            <div class="col-md-3 col-lg-2"><x-ui.form.select name="status" label="Actual status" :options="['draft'=>'Draft','posted'=>'Posted','void'=>'Void']" :selected="$filters['status'] ?? ''" placeholder="All"/></div>
            <div class="col-md-3 col-lg-2"><x-ui.form.input type="date" name="date_from" label="From" :value="$filters['date_from'] ?? ''"/></div>
            <div class="col-md-3 col-lg-2"><x-ui.form.input type="date" name="date_to" label="To" :value="$filters['date_to'] ?? ''"/></div>
        </x-ui.filter-bar>

        <section class="card mb-4" aria-labelledby="budget-lines-title"><div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div><h2 class="h4 mb-1" id="budget-lines-title">Planned budget</h2><p class="text-secondary mb-0">Status: <x-ui.status-badge :status="$budget->status"/></p></div>
                @can('approve', $budget)
                    <form method="POST" action="{{ route('events.budget.approve', $event) }}">@csrf<button class="btn btn-success" type="submit">Approve and lock baseline</button></form>
                @endcan
            </div>
            <x-ui.data-table :columns="[['label'=>'Direction'],['label'=>'Category'],['label'=>'Description'],['label'=>'Planned amount'],['label'=>'Actions']]" caption="Planned Event budget lines" :empty="$lines->isEmpty()" empty-title="No budget lines yet">
                @foreach($lines as $line)
                    <tr>
                        <td><x-ui.status-badge :status="$line->direction"/></td>
                        <td>{{ $line->category?->name ?? 'Uncategorized' }}</td>
                        <td>{{ $line->description }}</td>
                        <td>{{ $line->planned_amount }} {{ $line->currency_code }}</td>
                        <td>
                            @can('update', $budget)
                                <details><summary class="btn btn-sm btn-outline-secondary">Edit</summary>
                                    <form class="mt-3" method="POST" action="{{ route('events.budget.lines.update', [$event, $line]) }}">
                                        @csrf @method('PUT')
                                        <x-ui.form.select name="direction" label="Direction" :options="['income'=>'Income','expense'=>'Expense']" :selected="$line->direction" required/>
                                        <x-ui.form.select name="finance_category_id" label="Category" :options="$line->direction === 'income' ? $incomeCategories->pluck('name','id')->all() : $expenseCategories->pluck('name','id')->all()" :selected="$line->finance_category_id" required/>
                                        <x-ui.form.input name="description" label="Description" :value="$line->description" required/>
                                        <x-ui.form.input type="number" step="0.0001" min="0" name="planned_amount" label="Planned amount" :value="$line->planned_amount" required/>
                                        <button class="btn btn-primary btn-sm" type="submit">Save line</button>
                                    </form>
                                    <form class="mt-3" method="POST" action="{{ route('events.budget.lines.archive', [$event, $line]) }}">
                                        @csrf @method('PATCH')
                                        <x-ui.form.input name="reason" label="Archive reason" required/>
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Archive line</button>
                                    </form>
                                </details>
                            @else — @endcan
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
            <x-ui.pagination :paginator="$lines"/>

            @can('update', $budget)
                <hr class="my-4">
                <h3 class="h5">Add planned line</h3>
                <form class="row g-3" method="POST" action="{{ route('events.budget.lines.store', $event) }}">
                    @csrf
                    <div class="col-md-2"><x-ui.form.select name="direction" label="Direction" :options="['income'=>'Income','expense'=>'Expense']" selected="expense" required/></div>
                    <div class="col-md-3"><x-ui.form.select name="finance_category_id" label="Category" :options="$incomeCategories->merge($expenseCategories)->unique('id')->pluck('name','id')->all()" required/></div>
                    <div class="col-md-4"><x-ui.form.input name="description" label="Description" required/></div>
                    <div class="col-md-3"><x-ui.form.input type="number" step="0.0001" min="0" name="planned_amount" label="Planned amount" required/></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Add budget line</button></div>
                </form>
            @endcan
        </div></section>

        @can('finance.view')
            <section class="card mb-4" aria-labelledby="variance-title"><div class="card-body p-4">
                <h2 class="h4" id="variance-title">Actual versus budget by category</h2>
                <x-ui.data-table :columns="[['label'=>'Category'],['label'=>'Planned income'],['label'=>'Posted income'],['label'=>'Income variance'],['label'=>'Planned expense'],['label'=>'Posted expense'],['label'=>'Expense variance']]" caption="Actual versus planned category totals" :empty="$summary['categories']->isEmpty()" empty-title="No categorized finance data">
                    @foreach($summary['categories'] as $row)
                        <tr><td>{{ $row['category'] }}</td><td>{{ $row['planned_income'] }}</td><td>{{ $row['actual_income'] }}</td><td>{{ $row['income_variance'] }}</td><td>{{ $row['planned_expense'] }}</td><td>{{ $row['actual_expense'] }}</td><td>{{ $row['expense_variance'] }}</td></tr>
                    @endforeach
                </x-ui.data-table>
            </div></section>

            <div class="row g-4">
                <div class="col-xl-6">
                    <section class="card h-100"><div class="card-body p-4">
                        <h2 class="h4">Income ledger</h2>
                        <x-ui.data-table :columns="[['label'=>'Date / description'],['label'=>'Category'],['label'=>'Amount'],['label'=>'Status'],['label'=>'Actions']]" caption="Event income entries" :empty="$incomes?->isEmpty() ?? true" empty-title="No income entries match">
                            @foreach($incomes ?? [] as $entry)
                                <tr>
                                    <td>{{ $entry->transaction_date->format('Y-m-d') }}<span class="d-block small text-secondary">{{ $entry->description }}@if($entry->is_reversal) · reversal of #{{ $entry->reversal_of_id }}@endif</span></td>
                                    <td>{{ $entry->category->name }}</td><td>{{ $entry->is_reversal ? '−' : '' }}{{ $entry->amount }} {{ $entry->currency_code }}</td>
                                    <td>@if($entry->reversed_at && ! $entry->is_reversal)<span class="badge text-bg-warning">Reversed</span>@else<x-ui.status-badge :status="$entry->status"/>@endif</td>
                                    <td class="text-nowrap">
                                        @can('post', $entry)<form class="d-inline" method="POST" action="{{ route('events.budget.incomes.post', [$event,$entry]) }}">@csrf<button class="btn btn-sm btn-success" type="submit">Post</button></form>@endcan
                                        @can('void', $entry)<details class="d-inline-block"><summary class="btn btn-sm btn-outline-danger">Void</summary><form class="mt-2" method="POST" action="{{ route('events.budget.incomes.void', [$event,$entry]) }}">@csrf<x-ui.form.input name="reason" label="Reason" required/><button class="btn btn-danger btn-sm" type="submit">Confirm</button></form></details>@endcan
                                        @if($entry->evidenceDocument)<a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.show',$entry->evidenceDocument) }}">Evidence</a>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.data-table>
                        @if($incomes)<x-ui.pagination :paginator="$incomes"/>@endif
                    </div></section>
                </div>
                <div class="col-xl-6">
                    <section class="card h-100"><div class="card-body p-4">
                        <h2 class="h4">Expense ledger</h2>
                        <x-ui.data-table :columns="[['label'=>'Date / description'],['label'=>'Category'],['label'=>'Amount'],['label'=>'Status'],['label'=>'Actions']]" caption="Event expense entries" :empty="$expenses?->isEmpty() ?? true" empty-title="No expense entries match">
                            @foreach($expenses ?? [] as $entry)
                                <tr>
                                    <td>{{ $entry->transaction_date->format('Y-m-d') }}<span class="d-block small text-secondary">{{ $entry->description }}@if($entry->is_reversal) · reversal of #{{ $entry->reversal_of_id }}@endif</span></td>
                                    <td>{{ $entry->category->name }}</td><td>{{ $entry->is_reversal ? '−' : '' }}{{ $entry->amount }} {{ $entry->currency_code }}</td>
                                    <td>@if($entry->reversed_at && ! $entry->is_reversal)<span class="badge text-bg-warning">Reversed</span>@else<x-ui.status-badge :status="$entry->status"/>@endif</td>
                                    <td class="text-nowrap">
                                        @can('post', $entry)<form class="d-inline" method="POST" action="{{ route('events.budget.expenses.post', [$event,$entry]) }}">@csrf<button class="btn btn-sm btn-success" type="submit">Post</button></form>@endcan
                                        @can('void', $entry)<details class="d-inline-block"><summary class="btn btn-sm btn-outline-danger">Void</summary><form class="mt-2" method="POST" action="{{ route('events.budget.expenses.void', [$event,$entry]) }}">@csrf<x-ui.form.input name="reason" label="Reason" required/><button class="btn btn-danger btn-sm" type="submit">Confirm</button></form></details>@endcan
                                        @if($entry->evidenceDocument)<a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.show',$entry->evidenceDocument) }}">Evidence</a>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.data-table>
                        @if($expenses)<x-ui.pagination :paginator="$expenses"/>@endif
                    </div></section>
                </div>
            </div>
        @endcan

        @can('finance.create')
            <div class="row g-4 mt-1">
                <div class="col-xl-6"><section class="card"><div class="card-body p-4">
                    <h2 class="h4">Record draft income</h2>
                    <form method="POST" action="{{ route('events.budget.incomes.store',$event) }}">@csrf
                        <x-ui.form.select name="finance_category_id" label="Category" :options="$incomeCategories->pluck('name','id')->all()" required/>
                        <x-ui.form.select name="client_id" label="Client (optional)" :options="$clients->pluck('display_name','id')->all()" :selected="$event->client_id" placeholder="No client link"/>
                        <x-ui.form.input type="number" step="0.0001" min="0.0001" name="amount" label="Amount" required/>
                        <x-ui.form.input type="date" name="transaction_date" label="Transaction date" :value="now()->toDateString()" required/>
                        <x-ui.form.input name="description" label="Description" required/>
                        <x-ui.form.select name="evidence_document_id" label="Evidence document (optional)" :options="$documents->pluck('title','id')->all()" placeholder="None"/>
                        <button class="btn btn-primary" type="submit">Save draft income</button>
                    </form>
                </div></section></div>
                <div class="col-xl-6"><section class="card"><div class="card-body p-4">
                    <h2 class="h4">Record draft expense</h2>
                    <form method="POST" action="{{ route('events.budget.expenses.store',$event) }}">@csrf
                        <x-ui.form.select name="finance_category_id" label="Category" :options="$expenseCategories->pluck('name','id')->all()" required/>
                        <x-ui.form.select name="vendor_id" label="Vendor (optional)" :options="$vendors->pluck('display_name','id')->all()" placeholder="No vendor link"/>
                        <x-ui.form.input type="number" step="0.0001" min="0.0001" name="amount" label="Amount" required/>
                        <x-ui.form.input type="date" name="transaction_date" label="Transaction date" :value="now()->toDateString()" required/>
                        <x-ui.form.input name="description" label="Description" required/>
                        <x-ui.form.select name="evidence_document_id" label="Evidence document (optional)" :options="$documents->pluck('title','id')->all()" placeholder="None"/>
                        <button class="btn btn-primary" type="submit">Save draft expense</button>
                    </form>
                </div></section></div>
            </div>
        @endcan
    @endif
</x-layouts.app>
