<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Bookings\BookingController;
use App\Http\Controllers\Bookings\BookingConversionController;
use App\Http\Controllers\Bookings\BookingWorkflowController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Clients\ClientContactController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\ClientLookupController;
use App\Http\Controllers\Clients\ClientStatusController;
use App\Http\Controllers\Clients\ClientUserLinkController;
use App\Http\Controllers\Clients\MergeClientController;
use App\Http\Controllers\Clients\QuickCreateClientController;
use App\Http\Controllers\Communications\EventCommunicationController;
use App\Http\Controllers\Communications\MessageTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Events\DuplicateEventController;
use App\Http\Controllers\Events\DuplicateEventTemplateController;
use App\Http\Controllers\Events\EventArchiveController;
use App\Http\Controllers\Events\EventController;
use App\Http\Controllers\Events\EventCorrectionController;
use App\Http\Controllers\Events\EventModuleSettingController;
use App\Http\Controllers\Events\EventModuleWorkspaceController;
use App\Http\Controllers\Events\EventNoteController;
use App\Http\Controllers\Events\EventStatusController;
use App\Http\Controllers\Events\EventTemplateController;
use App\Http\Controllers\Events\EventTemplateStatusController;
use App\Http\Controllers\Events\ModuleDefinitionController;
use App\Http\Controllers\Finance\BudgetLineController;
use App\Http\Controllers\Finance\EventBudgetController;
use App\Http\Controllers\Finance\FinanceEntryController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\Guests\GuestCheckInController;
use App\Http\Controllers\Guests\GuestController;
use App\Http\Controllers\Guests\GuestGroupController;
use App\Http\Controllers\Guests\GuestInvitationController;
use App\Http\Controllers\Guests\GuestNoteController as EventGuestNoteController;
use App\Http\Controllers\Guests\GuestRsvpController;
use App\Http\Controllers\Guests\GuestSeatController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Invoices\ClientInvoiceController;
use App\Http\Controllers\Invoices\EventInvoiceController;
use App\Http\Controllers\Invoices\InvoiceActionController;
use App\Http\Controllers\Invoices\InvoiceOutputController;
use App\Http\Controllers\LoginHistoryController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Payments\ClientPaymentController;
use App\Http\Controllers\Payments\EventPaymentController;
use App\Http\Controllers\Payments\PaymentDueController;
use App\Http\Controllers\Payments\PaymentReceiptController;
use App\Http\Controllers\Payments\PaymentRefundController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Registrations\PublicRegistrationController;
use App\Http\Controllers\Registrations\RegistrationActionController;
use App\Http\Controllers\Registrations\RegistrationController;
use App\Http\Controllers\Registrations\RegistrationFieldController;
use App\Http\Controllers\Registrations\RegistrationFormController;
use App\Http\Controllers\Settings\BranchController;
use App\Http\Controllers\Settings\MasterDataController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Staff\EventStaffAssignmentController;
use App\Http\Controllers\Staff\StaffOperationsController;
use App\Http\Controllers\Staff\StaffProfileController;
use App\Http\Controllers\Staff\StaffStatusController;
use App\Http\Controllers\Staff\StaffUserLinkController;
use App\Http\Controllers\Tasks\EventTaskController;
use App\Http\Controllers\Tasks\TaskActionController;
use App\Http\Controllers\Tickets\PromoCodeController;
use App\Http\Controllers\Tickets\PublicTicketController;
use App\Http\Controllers\Tickets\TicketController;
use App\Http\Controllers\Tickets\TicketOrderController;
use App\Http\Controllers\Tickets\TicketPublicationController;
use App\Http\Controllers\Tickets\TicketRefundController;
use App\Http\Controllers\Tickets\TicketTypeController;
use App\Http\Controllers\Tickets\TicketValidationController;
use App\Http\Controllers\UiStyleGuideController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserStatusController;
use App\Http\Controllers\Vendors\EventVendorAssignmentController;
use App\Http\Controllers\Vendors\VendorAvailabilityController;
use App\Http\Controllers\Vendors\VendorContactController;
use App\Http\Controllers\Vendors\VendorController;
use App\Http\Controllers\Vendors\VendorServiceAreaController;
use App\Http\Controllers\Vendors\VendorStatusController;
use App\Http\Controllers\Vendors\VendorUserLinkController;
use App\Http\Controllers\Venues\EventVenueAllocationController;
use App\Http\Controllers\Venues\VenueAvailabilityController;
use App\Http\Controllers\Venues\VenueComponentController;
use App\Http\Controllers\Venues\VenueController;
use App\Http\Controllers\Venues\VenueStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/up', HealthController::class)->name('health');
Route::get('/', HomeController::class)->name('home');

Route::get('/register/{registrationForm:public_slug}', [PublicRegistrationController::class, 'show'])->name('public.registrations.show');
Route::post('/register/{registrationForm:public_slug}', [PublicRegistrationController::class, 'store'])->middleware('throttle:10,1')->name('public.registrations.store');
Route::get('/register/{registrationForm:public_slug}/thanks/{reference}', [PublicRegistrationController::class, 'thanks'])->middleware('throttle:30,1')->name('public.registrations.thanks');
Route::get('/tickets/event/{event:ticketing_public_slug}', [PublicTicketController::class, 'catalogue'])->middleware('throttle:60,1')->name('public.tickets.catalogue');
Route::get('/tickets/entry/{token}', [PublicTicketController::class, 'entry'])->middleware('throttle:60,1')->name('public.tickets.entry');
Route::get('/tickets/entry/{token}/qr', [PublicTicketController::class, 'qr'])->middleware('throttle:60,1')->name('public.tickets.qr');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/search', GlobalSearchController::class)->name('search');
    Route::get('/calendar', CalendarController::class)->name('calendar.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{recipient}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::patch('/notifications/{recipient}/unread', [NotificationController::class, 'unread'])->name('notifications.unread');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');

    Route::patch('/users/{user}/status', UserStatusController::class)->name('users.status.update');
    Route::resource('users', UserController::class);

    Route::get('/clients/lookup', ClientLookupController::class)->name('clients.lookup');
    Route::post('/clients/quick-create', QuickCreateClientController::class)->name('clients.quick-create');
    Route::patch('/clients/{client}/status', ClientStatusController::class)->name('clients.status');
    Route::put('/clients/{client}/user-link', ClientUserLinkController::class)->name('clients.user-link');
    Route::post('/clients/{client}/merge', MergeClientController::class)->name('clients.merge');
    Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store'])->name('clients.contacts.store');
    Route::get('/clients/{client}/contacts/{contact}/edit', [ClientContactController::class, 'edit'])->name('clients.contacts.edit');
    Route::put('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'update'])->name('clients.contacts.update');
    Route::delete('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'destroy'])->name('clients.contacts.destroy');
    Route::resource('clients', ClientController::class)->except(['destroy']);
    Route::get('/clients/{client}/payments', ClientPaymentController::class)->name('clients.payments');
    Route::get('/clients/{client}/invoices', ClientInvoiceController::class)->name('clients.invoices');

    Route::post('/bookings/{booking}/review', [BookingWorkflowController::class, 'review'])->name('bookings.review');
    Route::post('/bookings/{booking}/confirm', [BookingWorkflowController::class, 'confirm'])->name('bookings.confirm');
    Route::post('/bookings/{booking}/waitlist', [BookingWorkflowController::class, 'waitlist'])->name('bookings.waitlist');
    Route::post('/bookings/{booking}/cancel', [BookingWorkflowController::class, 'cancel'])->name('bookings.cancel');
    Route::patch('/bookings/{booking}/reschedule', [BookingWorkflowController::class, 'reschedule'])->name('bookings.reschedule');
    Route::get('/bookings/{booking}/convert', [BookingConversionController::class, 'create'])->name('bookings.convert.create');
    Route::post('/bookings/{booking}/convert', [BookingConversionController::class, 'store'])->name('bookings.convert.store');
    Route::resource('bookings', BookingController::class)->only(['index', 'create', 'store', 'show']);

    Route::get('/venues/availability', VenueAvailabilityController::class)->name('venues.availability');
    Route::patch('/venues/{venue}/status', VenueStatusController::class)->name('venues.status');
    Route::post('/venues/{venue}/spaces', [VenueComponentController::class, 'space'])->name('venues.spaces.store');
    Route::delete('/venues/{venue}/spaces/{space}', [VenueComponentController::class, 'destroySpace'])->name('venues.spaces.destroy');
    Route::post('/venues/{venue}/facilities', [VenueComponentController::class, 'facility'])->name('venues.facilities.store');
    Route::delete('/venues/{venue}/facilities/{facility}', [VenueComponentController::class, 'destroyFacility'])->name('venues.facilities.destroy');
    Route::post('/venues/{venue}/rates', [VenueComponentController::class, 'rate'])->name('venues.rates.store');
    Route::delete('/venues/{venue}/rates/{rate}', [VenueComponentController::class, 'destroyRate'])->name('venues.rates.destroy');
    Route::post('/venues/{venue}/seating-plans', [VenueComponentController::class, 'seatingPlan'])->name('venues.seating-plans.store');
    Route::delete('/venues/{venue}/seating-plans/{seatingPlan}', [VenueComponentController::class, 'destroySeatingPlan'])->name('venues.seating-plans.destroy');
    Route::post('/venues/{venue}/media', [VenueComponentController::class, 'media'])->name('venues.media.store');
    Route::resource('venues', VenueController::class)->except(['destroy']);

    Route::patch('/events/{event}/status', EventStatusController::class)->name('events.status');
    Route::post('/events/{event}/correction', EventCorrectionController::class)->name('events.correction');
    Route::patch('/events/{event}/archive', EventArchiveController::class)->name('events.archive');
    Route::post('/events/{event}/duplicate', DuplicateEventController::class)->name('events.duplicate');
    Route::post('/events/{event}/notes', [EventNoteController::class, 'store'])->name('events.notes.store');
    Route::get('/events/{event}/payments', [EventPaymentController::class, 'index'])->middleware('event.module.enabled:payments')->name('events.payments.index');
    Route::post('/events/{event}/payments', [EventPaymentController::class, 'store'])->middleware('event.module.enabled:payments')->name('events.payments.store');
    Route::post('/events/{event}/payment-schedules', [EventPaymentController::class, 'storeSchedule'])->middleware('event.module.enabled:payments')->name('events.payment-schedules.store');
    Route::patch('/events/{event}/payment-schedules/{schedule}/cancel', [EventPaymentController::class, 'cancelSchedule'])->middleware('event.module.enabled:payments')->name('events.payment-schedules.cancel');
    Route::get('/events/{event}/payments/{payment}/receipt', PaymentReceiptController::class)->middleware('event.module.enabled:payments')->name('events.payments.receipt');
    Route::post('/events/{event}/payments/{payment}/refunds', PaymentRefundController::class)->middleware('event.module.enabled:payments')->name('events.payments.refunds.store');
    Route::get('/events/{event}/invoices', [EventInvoiceController::class, 'index'])->middleware('event.module.enabled:invoices')->name('events.invoices.index');
    Route::get('/events/{event}/invoices/create', [EventInvoiceController::class, 'create'])->middleware('event.module.enabled:invoices')->name('events.invoices.create');
    Route::post('/events/{event}/invoices', [EventInvoiceController::class, 'store'])->middleware('event.module.enabled:invoices')->name('events.invoices.store');
    Route::get('/events/{event}/invoices/{invoice}', [EventInvoiceController::class, 'show'])->middleware('event.module.enabled:invoices')->name('events.invoices.show');
    Route::get('/events/{event}/invoices/{invoice}/edit', [EventInvoiceController::class, 'edit'])->middleware('event.module.enabled:invoices')->name('events.invoices.edit');
    Route::put('/events/{event}/invoices/{invoice}', [EventInvoiceController::class, 'update'])->middleware('event.module.enabled:invoices')->name('events.invoices.update');
    Route::post('/events/{event}/invoices/{invoice}/issue', [InvoiceActionController::class, 'issue'])->middleware('event.module.enabled:invoices')->name('events.invoices.issue');
    Route::post('/events/{event}/invoices/{invoice}/cancel', [InvoiceActionController::class, 'cancel'])->middleware('event.module.enabled:invoices')->name('events.invoices.cancel');
    Route::post('/events/{event}/invoices/{invoice}/credit', [InvoiceActionController::class, 'credit'])->middleware('event.module.enabled:invoices')->name('events.invoices.credit');
    Route::post('/events/{event}/invoices/{invoice}/email', [InvoiceActionController::class, 'email'])->middleware('event.module.enabled:invoices')->name('events.invoices.email');
    Route::get('/events/{event}/invoices/{invoice}/pdf', [InvoiceOutputController::class, 'pdf'])->middleware('event.module.enabled:invoices')->name('events.invoices.pdf');
    Route::get('/events/{event}/invoices/{invoice}/print', [InvoiceOutputController::class, 'print'])->middleware('event.module.enabled:invoices')->name('events.invoices.print');
    Route::get('/events/{event}/communications', [EventCommunicationController::class, 'index'])->middleware('event.module.enabled:communications')->name('events.communications.index');
    Route::post('/events/{event}/communications', [EventCommunicationController::class, 'store'])->middleware('event.module.enabled:communications')->name('events.communications.store');
    Route::get('/events/{event}/communications/{message}', [EventCommunicationController::class, 'show'])->middleware('event.module.enabled:communications')->name('events.communications.show');
    Route::post('/events/{event}/communications/{message}/retry', [EventCommunicationController::class, 'retry'])->middleware('event.module.enabled:communications')->name('events.communications.retry');
    Route::post('/events/{event}/reminders', [EventCommunicationController::class, 'schedule'])->middleware('event.module.enabled:communications')->name('events.reminders.store');
    Route::get('/events/{event}/budget', [EventBudgetController::class, 'index'])->middleware('event.module.enabled:budget')->name('events.budget.index');
    Route::post('/events/{event}/budget', [EventBudgetController::class, 'store'])->middleware('event.module.enabled:budget')->name('events.budget.store');
    Route::post('/events/{event}/budget/approve', [EventBudgetController::class, 'approve'])->middleware('event.module.enabled:budget')->name('events.budget.approve');
    Route::post('/events/{event}/budget/lines', [BudgetLineController::class, 'store'])->middleware('event.module.enabled:budget')->name('events.budget.lines.store');
    Route::put('/events/{event}/budget/lines/{line}', [BudgetLineController::class, 'update'])->middleware('event.module.enabled:budget')->name('events.budget.lines.update');
    Route::patch('/events/{event}/budget/lines/{line}/archive', [BudgetLineController::class, 'archive'])->middleware('event.module.enabled:budget')->name('events.budget.lines.archive');
    Route::post('/events/{event}/budget/incomes', [FinanceEntryController::class, 'storeIncome'])->middleware('event.module.enabled:budget')->name('events.budget.incomes.store');
    Route::put('/events/{event}/budget/incomes/{income}', [FinanceEntryController::class, 'updateIncome'])->middleware('event.module.enabled:budget')->name('events.budget.incomes.update');
    Route::post('/events/{event}/budget/incomes/{income}/post', [FinanceEntryController::class, 'postIncome'])->middleware('event.module.enabled:budget')->name('events.budget.incomes.post');
    Route::post('/events/{event}/budget/incomes/{income}/void', [FinanceEntryController::class, 'voidIncome'])->middleware('event.module.enabled:budget')->name('events.budget.incomes.void');
    Route::post('/events/{event}/budget/expenses', [FinanceEntryController::class, 'storeExpense'])->middleware('event.module.enabled:budget')->name('events.budget.expenses.store');
    Route::put('/events/{event}/budget/expenses/{expense}', [FinanceEntryController::class, 'updateExpense'])->middleware('event.module.enabled:budget')->name('events.budget.expenses.update');
    Route::post('/events/{event}/budget/expenses/{expense}/post', [FinanceEntryController::class, 'postExpense'])->middleware('event.module.enabled:budget')->name('events.budget.expenses.post');
    Route::post('/events/{event}/budget/expenses/{expense}/void', [FinanceEntryController::class, 'voidExpense'])->middleware('event.module.enabled:budget')->name('events.budget.expenses.void');
    Route::get('/events/{event}/tasks', [EventTaskController::class, 'index'])->middleware('event.module.enabled:tasks')->name('events.tasks.index');
    Route::post('/events/{event}/tasks', [EventTaskController::class, 'store'])->middleware('event.module.enabled:tasks')->name('events.tasks.store');
    Route::get('/events/{event}/tasks/{task}', [EventTaskController::class, 'show'])->middleware('event.module.enabled:tasks')->name('events.tasks.show');
    Route::put('/events/{event}/tasks/{task}', [EventTaskController::class, 'update'])->middleware('event.module.enabled:tasks')->name('events.tasks.update');
    Route::post('/events/{event}/tasks/{task}/assignments', [TaskActionController::class, 'assign'])->middleware('event.module.enabled:tasks')->name('events.tasks.assignments.store');
    Route::post('/events/{event}/tasks/{task}/comments', [TaskActionController::class, 'comment'])->middleware('event.module.enabled:tasks')->name('events.tasks.comments.store');
    Route::post('/events/{event}/tasks/{task}/attachments', [TaskActionController::class, 'attach'])->middleware('event.module.enabled:tasks')->name('events.tasks.attachments.store');
    Route::patch('/events/{event}/tasks/{task}/status', [TaskActionController::class, 'transition'])->middleware('event.module.enabled:tasks')->name('events.tasks.status');
    Route::patch('/events/{event}/tasks/{task}/archive', [TaskActionController::class, 'archive'])->middleware('event.module.enabled:tasks')->name('events.tasks.archive');
    Route::get('/events/{event}/guests/check-in', [GuestCheckInController::class, 'index'])->middleware('event.module.enabled:guests')->name('events.guests.check-in.lookup');
    Route::post('/events/{event}/guests/check-in', [GuestCheckInController::class, 'store'])->middleware('event.module.enabled:guests')->name('events.guests.check-in.store');
    Route::post('/events/{event}/guest-groups', [GuestGroupController::class, 'store'])->middleware('event.module.enabled:guests')->name('events.guest-groups.store');
    Route::get('/events/{event}/guests', [GuestController::class, 'index'])->middleware('event.module.enabled:guests')->name('events.guests.index');
    Route::get('/events/{event}/guests/create', [GuestController::class, 'create'])->middleware('event.module.enabled:guests')->name('events.guests.create');
    Route::post('/events/{event}/guests', [GuestController::class, 'store'])->middleware('event.module.enabled:guests')->name('events.guests.store');
    Route::get('/events/{event}/guests/{guest}', [GuestController::class, 'show'])->middleware('event.module.enabled:guests')->name('events.guests.show');
    Route::get('/events/{event}/guests/{guest}/edit', [GuestController::class, 'edit'])->middleware('event.module.enabled:guests')->name('events.guests.edit');
    Route::put('/events/{event}/guests/{guest}', [GuestController::class, 'update'])->middleware('event.module.enabled:guests')->name('events.guests.update');
    Route::patch('/events/{event}/guests/{guest}/status', [GuestController::class, 'status'])->middleware('event.module.enabled:guests')->name('events.guests.status');
    Route::post('/events/{event}/guests/{guest}/invitations', [GuestInvitationController::class, 'store'])->middleware('event.module.enabled:guests')->name('events.guests.invitations.store');
    Route::patch('/events/{event}/guests/{guest}/invitations/{invitation}/revoke', [GuestInvitationController::class, 'revoke'])->middleware('event.module.enabled:guests')->name('events.guests.invitations.revoke');
    Route::get('/events/{event}/guests/{guest}/invitations/{invitation}/qr', [GuestInvitationController::class, 'qr'])->middleware('event.module.enabled:guests')->name('events.guests.invitations.qr');
    Route::put('/events/{event}/guests/{guest}/rsvp', [GuestRsvpController::class, 'store'])->middleware('event.module.enabled:guests')->name('events.guests.rsvp');
    Route::put('/events/{event}/guests/{guest}/seat', [GuestSeatController::class, 'store'])->middleware('event.module.enabled:guests')->name('events.guests.seat');
    Route::post('/events/{event}/guests/{guest}/notes', [EventGuestNoteController::class, 'store'])->middleware('event.module.enabled:guests')->name('events.guests.notes.store');
    Route::get('/events/{event}/registration/forms', [RegistrationFormController::class, 'index'])->middleware('event.module.enabled:registration')->name('events.registration-forms.index');
    Route::get('/events/{event}/registration/forms/create', [RegistrationFormController::class, 'create'])->middleware('event.module.enabled:registration')->name('events.registration-forms.create');
    Route::post('/events/{event}/registration/forms', [RegistrationFormController::class, 'store'])->middleware('event.module.enabled:registration')->name('events.registration-forms.store');
    Route::get('/events/{event}/registration/forms/{registrationForm}/edit', [RegistrationFormController::class, 'edit'])->middleware('event.module.enabled:registration')->name('events.registration-forms.edit');
    Route::put('/events/{event}/registration/forms/{registrationForm}', [RegistrationFormController::class, 'update'])->middleware('event.module.enabled:registration')->name('events.registration-forms.update');
    Route::patch('/events/{event}/registration/forms/{registrationForm}/publish', [RegistrationFormController::class, 'publish'])->middleware('event.module.enabled:registration')->name('events.registration-forms.publish');
    Route::post('/events/{event}/registration/forms/{registrationForm}/fields', [RegistrationFieldController::class, 'store'])->middleware('event.module.enabled:registration')->name('events.registration-fields.store');
    Route::put('/events/{event}/registration/forms/{registrationForm}/fields/reorder', [RegistrationFieldController::class, 'reorder'])->middleware('event.module.enabled:registration')->name('events.registration-fields.reorder');
    Route::get('/events/{event}/registration/forms/{registrationForm}/fields/{registrationField}/edit', [RegistrationFieldController::class, 'edit'])->middleware('event.module.enabled:registration')->name('events.registration-fields.edit');
    Route::put('/events/{event}/registration/forms/{registrationForm}/fields/{registrationField}', [RegistrationFieldController::class, 'update'])->middleware('event.module.enabled:registration')->name('events.registration-fields.update');
    Route::get('/events/{event}/registrations', [RegistrationController::class, 'index'])->middleware('event.module.enabled:registration')->name('events.registrations.index');
    Route::get('/events/{event}/registrations/export', [RegistrationController::class, 'export'])->middleware('event.module.enabled:registration')->name('events.registrations.export');
    Route::get('/events/{event}/registration/forms/{registrationForm}/entry', [RegistrationController::class, 'create'])->middleware('event.module.enabled:registration')->name('events.registrations.create');
    Route::post('/events/{event}/registration/forms/{registrationForm}/entry', [RegistrationController::class, 'store'])->middleware('event.module.enabled:registration')->name('events.registrations.store');
    Route::get('/events/{event}/registrations/{registration}', [RegistrationController::class, 'show'])->middleware('event.module.enabled:registration')->name('events.registrations.show');
    Route::post('/events/{event}/registrations/{registration}/review', [RegistrationActionController::class, 'review'])->middleware('event.module.enabled:registration')->name('events.registrations.review');
    Route::post('/events/{event}/registrations/{registration}/guest', [RegistrationActionController::class, 'convertGuest'])->middleware('event.module.enabled:registration')->name('events.registrations.guest');
    Route::get('/events/{event}/tickets', [TicketController::class, 'index'])->middleware('event.module.enabled:ticketing')->name('events.tickets.index');
    Route::post('/events/{event}/ticket-types', [TicketTypeController::class, 'store'])->middleware('event.module.enabled:ticketing')->name('events.ticket-types.store');
    Route::put('/events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'update'])->middleware('event.module.enabled:ticketing')->name('events.ticket-types.update');
    Route::patch('/events/{event}/ticket-types/{ticketType}/archive', [TicketTypeController::class, 'archive'])->middleware('event.module.enabled:ticketing')->name('events.ticket-types.archive');
    Route::post('/events/{event}/promo-codes', [PromoCodeController::class, 'store'])->middleware('event.module.enabled:ticketing')->name('events.promo-codes.store');
    Route::put('/events/{event}/promo-codes/{promoCode}', [PromoCodeController::class, 'update'])->middleware('event.module.enabled:ticketing')->name('events.promo-codes.update');
    Route::patch('/events/{event}/promo-codes/{promoCode}/archive', [PromoCodeController::class, 'archive'])->middleware('event.module.enabled:ticketing')->name('events.promo-codes.archive');
    Route::post('/events/{event}/ticket-orders', [TicketOrderController::class, 'store'])->middleware('event.module.enabled:ticketing')->name('events.ticket-orders.store');
    Route::get('/events/{event}/ticket-orders/{ticketOrder}', [TicketController::class, 'show'])->middleware('event.module.enabled:ticketing')->name('events.ticket-orders.show');
    Route::get('/events/{event}/ticket-orders/{ticketOrder}/print', [TicketController::class, 'print'])->middleware('event.module.enabled:ticketing')->name('events.ticket-orders.print');
    Route::post('/events/{event}/ticket-orders/{ticketOrder}/email', [TicketOrderController::class, 'email'])->middleware('event.module.enabled:ticketing')->name('events.ticket-orders.email');
    Route::patch('/events/{event}/ticket-orders/{ticketOrder}/cancel', [TicketOrderController::class, 'cancel'])->middleware('event.module.enabled:ticketing')->name('events.ticket-orders.cancel');
    Route::get('/events/{event}/tickets/{ticket}/qr', [TicketController::class, 'qr'])->middleware('event.module.enabled:ticketing')->name('events.tickets.qr');
    Route::post('/events/{event}/tickets/{ticket}/refunds', TicketRefundController::class)->middleware('event.module.enabled:ticketing')->name('events.tickets.refunds.store');
    Route::get('/events/{event}/tickets/validate', [TicketValidationController::class, 'index'])->middleware('event.module.enabled:ticketing')->name('events.tickets.validate');
    Route::post('/events/{event}/tickets/validate', [TicketValidationController::class, 'store'])->middleware('event.module.enabled:ticketing')->name('events.tickets.validate.store');
    Route::patch('/events/{event}/tickets/publish', TicketPublicationController::class)->middleware('event.module.enabled:ticketing')->name('events.tickets.publish');
    Route::get('/events/{event}/staff', [EventStaffAssignmentController::class, 'index'])->middleware('event.module.enabled:staff')->name('events.staff.index');
    Route::post('/events/{event}/staff', [EventStaffAssignmentController::class, 'store'])->middleware('event.module.enabled:staff')->name('events.staff.store');
    Route::patch('/events/{event}/staff/{assignment}/status', [EventStaffAssignmentController::class, 'transition'])->middleware('event.module.enabled:staff')->name('events.staff.status');
    Route::get('/events/{event}/vendors', [EventVendorAssignmentController::class, 'index'])->middleware('event.module.enabled:vendors')->name('events.vendors.index');
    Route::post('/events/{event}/vendors', [EventVendorAssignmentController::class, 'store'])->middleware('event.module.enabled:vendors')->name('events.vendors.store');
    Route::get('/events/{event}/vendors/{assignment}', [EventVendorAssignmentController::class, 'show'])->middleware('event.module.enabled:vendors')->name('events.vendors.show');
    Route::put('/events/{event}/vendors/{assignment}', [EventVendorAssignmentController::class, 'update'])->middleware('event.module.enabled:vendors')->name('events.vendors.update');
    Route::patch('/events/{event}/vendors/{assignment}/status', [EventVendorAssignmentController::class, 'transition'])->middleware('event.module.enabled:vendors')->name('events.vendors.status');
    Route::patch('/events/{event}/vendors/{assignment}/delivery', [EventVendorAssignmentController::class, 'updateDelivery'])->middleware('event.module.enabled:vendors')->name('events.vendors.delivery');
    Route::post('/events/{event}/vendors/{assignment}/work-orders', [EventVendorAssignmentController::class, 'storeWorkOrder'])->middleware('event.module.enabled:vendors')->name('events.vendors.work-orders.store');
    Route::patch('/events/{event}/vendors/{assignment}/work-orders/{workOrder}/status', [EventVendorAssignmentController::class, 'transitionWorkOrder'])->middleware('event.module.enabled:vendors')->name('events.vendors.work-orders.status');
    Route::post('/events/{event}/vendors/{assignment}/contracts', [EventVendorAssignmentController::class, 'storeContract'])->middleware('event.module.enabled:vendors')->name('events.vendors.contracts.store');
    Route::post('/events/{event}/vendors/{assignment}/invoices', [EventVendorAssignmentController::class, 'storeInvoice'])->middleware('event.module.enabled:vendors')->name('events.vendors.invoices.store');
    Route::patch('/events/{event}/vendors/{assignment}/invoices/{invoice}/status', [EventVendorAssignmentController::class, 'transitionInvoice'])->middleware('event.module.enabled:vendors')->name('events.vendors.invoices.status');
    Route::post('/events/{event}/vendors/{assignment}/rating', [EventVendorAssignmentController::class, 'storeRating'])->middleware('event.module.enabled:vendors')->name('events.vendors.rating.store');
    Route::get('/events/{event}/venue', [EventVenueAllocationController::class, 'index'])->middleware('event.module.enabled:venue')->name('events.venue.index');
    Route::post('/events/{event}/venue/allocations', [EventVenueAllocationController::class, 'store'])->middleware('event.module.enabled:venue')->name('events.venue.allocations.store');
    Route::patch('/events/{event}/venue/allocations/{allocation}/cancel', [EventVenueAllocationController::class, 'cancel'])->middleware('event.module.enabled:venue')->name('events.venue.allocations.cancel');
    Route::get('/events/{event}/modules', [EventModuleSettingController::class, 'edit'])->name('events.modules.edit');
    Route::put('/events/{event}/modules', [EventModuleSettingController::class, 'update'])->name('events.modules.update');
    Route::get('/events/{event}/workspace/{moduleKey}', EventModuleWorkspaceController::class)
        ->middleware('event.module.enabled')
        ->name('events.workspace.module');
    Route::resource('events', EventController::class)->except(['destroy']);

    Route::get('/payments/due', PaymentDueController::class)->name('payments.due');

    Route::patch('/vendors/{vendor}/status', VendorStatusController::class)->name('vendors.status');
    Route::post('/vendors/{vendor}/availability', [VendorAvailabilityController::class, 'store'])->name('vendors.availability.store');
    Route::delete('/vendors/{vendor}/availability/{availability}', [VendorAvailabilityController::class, 'destroy'])->name('vendors.availability.destroy');
    Route::put('/vendors/{vendor}/user-link', VendorUserLinkController::class)->name('vendors.user-link');
    Route::post('/vendors/{vendor}/contacts', [VendorContactController::class, 'store'])->name('vendors.contacts.store');
    Route::delete('/vendors/{vendor}/contacts/{contact}', [VendorContactController::class, 'destroy'])->name('vendors.contacts.destroy');
    Route::post('/vendors/{vendor}/service-areas', [VendorServiceAreaController::class, 'store'])->name('vendors.service-areas.store');
    Route::delete('/vendors/{vendor}/service-areas/{area}', [VendorServiceAreaController::class, 'destroy'])->name('vendors.service-areas.destroy');
    Route::resource('vendors', VendorController::class)->except(['destroy']);

    Route::get('/staff/operations', [StaffOperationsController::class, 'index'])->name('staff.operations.index');
    Route::post('/staff/{staff}/shifts', [StaffOperationsController::class, 'storeShift'])->name('staff.shifts.store');
    Route::patch('/staff/{staff}/shifts/{shift}/status', [StaffOperationsController::class, 'transitionShift'])->name('staff.shifts.status');
    Route::post('/staff/{staff}/leave', [StaffOperationsController::class, 'storeLeave'])->name('staff.leave.store');
    Route::patch('/staff/leave/{leaveRequest}', [StaffOperationsController::class, 'reviewLeave'])->name('staff.leave.review');
    Route::post('/staff/{staff}/attendance', [StaffOperationsController::class, 'storeAttendance'])->name('staff.attendance.store');
    Route::post('/staff/{staff}/salary-records', [StaffOperationsController::class, 'storeSalary'])->name('staff.salary.store');
    Route::patch('/staff/{staff}/salary-records/{salaryRecord}/status', [StaffOperationsController::class, 'transitionSalary'])->name('staff.salary.status');
    Route::post('/staff/{staff}/performance-records', [StaffOperationsController::class, 'storePerformance'])->name('staff.performance.store');
    Route::patch('/staff/{staff}/status', StaffStatusController::class)->name('staff.status');
    Route::put('/staff/{staff}/user-link', StaffUserLinkController::class)->name('staff.user-link');
    Route::resource('staff', StaffProfileController::class)->except(['destroy'])->parameters(['staff' => 'staff']);

    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/audit/logins', [LoginHistoryController::class, 'index'])->name('audit.logins.index');
    Route::get('/audit/logins/{loginHistory}', [LoginHistoryController::class, 'show'])->name('audit.logins.show');
    Route::get('/audit/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'replace'])->name('documents.versions.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/versions/{version}/download', [DocumentController::class, 'downloadVersion'])->name('documents.versions.download');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/message-templates/{message_template}/archive', [MessageTemplateController::class, 'archive'])->name('message-templates.archive');
    Route::resource('message-templates', MessageTemplateController::class)->except(['show', 'destroy']);
    Route::put('/settings/company', [SettingsController::class, 'updateCompany'])->name('settings.company.update');
    Route::put('/settings/system', [SettingsController::class, 'updateSystem'])->name('settings.system.update');
    Route::patch('/settings/event-templates/{event_template}/status', EventTemplateStatusController::class)->name('settings.event-templates.status');
    Route::post('/settings/event-templates/{event_template}/duplicate', DuplicateEventTemplateController::class)->name('settings.event-templates.duplicate');
    Route::resource('/settings/event-templates', EventTemplateController::class)
        ->except(['destroy'])
        ->parameters(['event-templates' => 'event_template'])
        ->names('settings.event-templates');
    Route::get('/settings/module-definitions', [ModuleDefinitionController::class, 'index'])->name('settings.module-definitions.index');
    Route::get('/settings/module-definitions/{module_definition}/edit', [ModuleDefinitionController::class, 'edit'])->name('settings.module-definitions.edit');
    Route::put('/settings/module-definitions/{module_definition}', [ModuleDefinitionController::class, 'update'])->name('settings.module-definitions.update');
    Route::resource('/settings/branches', BranchController::class)
        ->except(['show'])
        ->names('settings.branches');
    Route::prefix('/settings/master-data/{type}')->name('settings.master-data.')->group(function () {
        Route::get('/', [MasterDataController::class, 'index'])->name('index');
        Route::get('/create', [MasterDataController::class, 'create'])->name('create');
        Route::post('/', [MasterDataController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [MasterDataController::class, 'edit'])->name('edit');
        Route::put('/{category}', [MasterDataController::class, 'update'])->name('update');
        Route::delete('/{category}', [MasterDataController::class, 'destroy'])->name('destroy');
    });

    if (app()->environment(['local', 'testing'])) {
        Route::get('/style-guide', UiStyleGuideController::class)->name('style-guide');
    }
});
