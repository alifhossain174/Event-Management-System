@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'alert alert-danger']) }} role="alert" tabindex="-1" data-validation-summary>
        <div class="d-flex gap-2">
            <x-ui.icon name="alert" class="flex-shrink-0 mt-1"/>
            <div>
                <h2 class="h6 mb-2">Please correct the following {{ Str::plural('error', $errors->count()) }}:</h2>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
