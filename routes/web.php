<?php

use App\Http\Controllers\Activity\ActivityLogController;
use App\Http\Controllers\Calendar\CalendarController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Comments\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Integrations\OAuthController;
use App\Http\Controllers\Integrations\WebhookController;
use App\Http\Controllers\Meetings\MeetingController;
use App\Http\Controllers\Milestones\MilestoneController;
use App\Http\Controllers\Projects\ProjectArchiveController;
use App\Http\Controllers\Projects\ProjectFileController;
use App\Http\Controllers\Notes\NoteController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Reminders\ReminderController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Todos\TodoController;
use App\Http\Controllers\Trips\TripController;
use App\Http\Controllers\Workspaces\SwitchWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::view('offline', 'offline')->name('offline');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::put('workspaces/{workspace}/switch', SwitchWorkspaceController::class)
        ->name('workspaces.switch');

    Route::resource('projects', ProjectController::class);
    Route::resource('tasks', TaskController::class);
    Route::resource('todos', TodoController::class)->except(['show', 'create', 'edit']);
    Route::resource('notes', NoteController::class)->except(['show', 'create', 'edit']);
    Route::resource('clients', ClientController::class)->except(['show', 'create', 'edit']);

    Route::post('projects/{project}/archive', [ProjectArchiveController::class, 'store'])->name('projects.archive');
    Route::delete('projects/{project}/archive', [ProjectArchiveController::class, 'destroy'])->name('projects.unarchive');

    Route::post('projects/{project}/files', [ProjectFileController::class, 'store'])->name('projects.files.store');
    Route::get('files/{file}/download', [ProjectFileController::class, 'download'])->name('projects.files.download');
    Route::delete('files/{file}', [ProjectFileController::class, 'destroy'])->name('projects.files.destroy');

    Route::post('milestones', [MilestoneController::class, 'store'])->name('milestones.store');
    Route::patch('milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');
    Route::delete('milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('milestones.destroy');

    // Trips + segments + checklist
    Route::resource('trips', TripController::class)->except(['create', 'edit']);
    Route::post('trips/{trip}/segments', [TripController::class, 'storeSegment'])->name('trips.segments.store');
    Route::delete('trip-segments/{segment}', [TripController::class, 'destroySegment'])->name('trips.segments.destroy');
    Route::post('trips/{trip}/checklist', [TripController::class, 'storeChecklistItem'])->name('trips.checklist.store');
    Route::patch('trip-checklist/{item}/toggle', [TripController::class, 'toggleChecklistItem'])->name('trips.checklist.toggle');
    Route::delete('trip-checklist/{item}', [TripController::class, 'destroyChecklistItem'])->name('trips.checklist.destroy');

    Route::post('reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');

    Route::post('comments', [CommentController::class, 'store'])->name('comments.store');
    Route::patch('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::resource('meetings', MeetingController::class)->except(['create', 'edit']);

    Route::get('calendar', CalendarController::class)->name('calendar.index');

    Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // OAuth connect flows for email / calendar providers
    Route::get('integrations/{provider}/connect', [OAuthController::class, 'redirect'])
        ->name('integrations.connect')
        ->whereIn('provider', ['gmail', 'microsoft']);
    Route::get('integrations/{provider}/callback', [OAuthController::class, 'callback'])
        ->name('integrations.callback')
        ->whereIn('provider', ['gmail', 'microsoft']);
});

// Inbound webhooks — public, signature-verified inside the controller.
Route::post('integrations/gmail/webhook', [WebhookController::class, 'gmail'])
    ->name('integrations.webhooks.gmail');
Route::match(['get', 'post'], 'integrations/microsoft/webhook', [WebhookController::class, 'microsoft'])
    ->name('integrations.webhooks.microsoft');
Route::post('integrations/twilio/webhook', [WebhookController::class, 'twilio'])
    ->name('integrations.webhooks.twilio');
Route::match(['get', 'post'], 'integrations/whatsapp/webhook', [WebhookController::class, 'whatsapp'])
    ->name('integrations.webhooks.whatsapp');

require __DIR__.'/settings.php';
