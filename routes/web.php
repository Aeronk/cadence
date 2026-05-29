<?php

use App\Http\Controllers\Activity\ActivityLogController;
use App\Http\Controllers\Comments\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Integrations\OAuthController;
use App\Http\Controllers\Integrations\WebhookController;
use App\Http\Controllers\Meetings\MeetingController;
use App\Http\Controllers\Notes\NoteController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Todos\TodoController;
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

    Route::post('comments', [CommentController::class, 'store'])->name('comments.store');
    Route::patch('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::resource('meetings', MeetingController::class)->except(['create', 'edit']);

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

require __DIR__.'/settings.php';
