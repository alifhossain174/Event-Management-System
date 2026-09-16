# Administration UI Components

## Purpose

Prompt 05 establishes reusable Blade and Bootstrap patterns for administration screens. New modules should compose these components instead of copying layout, message, table, filter, form, or confirmation markup. Components improve consistency but never replace Form Requests, Policies, Gates, or permission-scoped queries.

## Administration shell

Use the authenticated application layout for protected screens:

~~~blade
<x-layouts.app
    title="Users"
    wide
    :breadcrumbs="[
        ['label' => 'Overview', 'url' => route('home')],
        ['label' => 'Users'],
    ]"
>
    <x-ui.page-header title="Users" subtitle="Manage accounts.">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <a class="btn btn-primary" href="{{ route('users.create') }}">Create user</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>
</x-layouts.app>
~~~

The layout provides a desktop sidebar, Bootstrap offcanvas mobile navigation, sticky top bar, notification placeholder, account menu, breadcrumbs, feedback messages, validation summary, skip link, and shared confirmation modal. Permission-filtered navigation is supplied by App\Support\Navigation. Guest authentication pages use the same layout without the administration sidebar.

## Navigation

Add routes to App\Support\Navigation only after their screen exists. Each item declares a label, route, route-name patterns, local SVG icon name, and optional permission. UI filtering is a convenience only. The route and controller independently enforce authorization.

Do not add disabled links for unimplemented modules. Do not expose records through counts, labels, search suggestions, or notification text when the user lacks permission.

## Page and feedback components

### Page header

~~~blade
<x-ui.page-header title="Events" subtitle="Plan and manage authorized events.">
    <x-slot:actions>
        <a class="btn btn-primary" href="...">Create event</a>
    </x-slot:actions>
</x-ui.page-header>
~~~

The application layout includes x-ui.flash-messages and x-ui.validation-summary. Flash messages support status, warning, info, and error session keys. The validation summary receives focus after a failed request.

### Empty state

~~~blade
<x-ui.empty-state title="No events yet" description="Create the first event when client details are ready.">
    <x-slot:action>
        <a class="btn btn-primary" href="...">Create event</a>
    </x-slot:action>
</x-ui.empty-state>
~~~

## Lists and filters

List screens use a GET filter form, bounded server-side pagination, explicit table captions, column headers with scope, and an empty state.

~~~blade
<x-ui.filter-bar :action="route('users.index')" :clear-url="route('users.index')">
    <!-- Labeled search and select controls -->
</x-ui.filter-bar>

<x-ui.data-table
    :columns="[
        ['label' => 'User'],
        ['label' => 'Status'],
        ['label' => 'Actions'],
    ]"
    caption="User accounts"
    :empty="$users->isEmpty()"
    empty-title="No users found"
>
    @foreach ($users as $user)
        <tr><!-- Escaped cells and authorized actions --></tr>
    @endforeach
</x-ui.data-table>

<x-ui.pagination :paginator="$users"/>
~~~

Tables must not load unbounded collections. Preserve filters with withQueryString() in the controller query.

## Status and tabs

x-ui.status-badge maps common statuses to consistent Bootstrap variants. Pass a plain status key and optionally replace its display text:

~~~blade
<x-ui.status-badge status="in-progress">In progress</x-ui.status-badge>
~~~

x-ui.tabs renders link-based section navigation. The active tab uses aria-current. Tabs are navigation, not a substitute for permission checks.

## Form controls

Use x-ui.form.input, x-ui.form.select, and x-ui.form.textarea. Each associates its label, required indicator, validation state, and help text. Use unique IDs when the same field name appears more than once on a page. Password components never repopulate their value.

~~~blade
<x-ui.form.input name="name" label="Name" :value="$record->name" required autocomplete="name"/>
~~~

## Confirmation modal

The layout contains one shared Bootstrap modal. A trigger identifies the protected form to submit and supplies plain-text content:

~~~blade
<form id="archive-form" method="POST" action="...">
    @csrf
    @method('DELETE')
    <button
        type="button"
        class="btn btn-danger"
        data-bs-toggle="modal"
        data-bs-target="#confirmationModal"
        data-confirm-form="archive-form"
        data-confirm-title="Archive this record?"
        data-confirm-message="Historical records will be preserved."
        data-confirm-button="Archive"
    >Archive</button>
</form>
~~~

The script uses textContent rather than HTML insertion. The destination form still needs CSRF protection, validation, and server-side authorization.

## Icons

x-ui.icon renders a fixed whitelist of inline SVG paths from the repository. Decorative icons are hidden from assistive technology; pass a label only when the icon conveys meaning without nearby text. Do not paste arbitrary SVG or HTML from user data.

No icon package, CDN, web font, general admin template, or frontend framework is required.

## Accessibility and responsive checklist

- Preserve one logical main landmark and a descending heading hierarchy.
- Label every input and interactive control.
- Keep keyboard focus visible; do not remove the global focus ring.
- Use buttons for actions and links for navigation.
- Provide text with icons and never rely on color alone for status.
- Keep interactive controls usable at mobile widths; tables may scroll horizontally.
- Test the sidebar toggle, dropdowns, modal focus trap, form errors, and skip link with a keyboard.
- Respect reduced-motion preferences.
- Render untrusted values with escaped Blade output, never raw Blade echo.
- Verify server-side permission denial separately from hidden navigation or actions.

The protected /style-guide page demonstrates these patterns only when the application environment is local or testing.
