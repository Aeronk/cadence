<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvited;
use App\Services\Workspaces\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkspaceInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function workspaceOwnedBy(User $owner): Workspace
    {
        // The factory attaches the owner as a member already.
        return Workspace::factory()->create(['owner_id' => $owner->id]);
    }

    public function test_inviting_stores_only_a_hash_of_the_token(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);

        $invitation = app(InvitationService::class)
            ->invite($workspace, 'viewer@example.com', WorkspaceRole::Viewer, $owner);

        $plain = $invitation->plainToken();

        $this->assertNotNull($plain);
        $this->assertNotSame($plain, $invitation->token_hash);
        $this->assertSame(hash('sha256', $plain), $invitation->token_hash);
        $this->assertDatabaseMissing('workspace_invitations', ['token_hash' => $plain]);
    }

    public function test_a_viewer_can_be_invited_and_accepts_by_following_the_link(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);

        // Invited through the service so the test holds the plaintext token that
        // went out in the mail; the HTTP endpoint is covered separately.
        $invitation = app(InvitationService::class)
            ->invite($workspace, 'viewer@example.com', WorkspaceRole::Viewer, $owner);
        $token = $invitation->plainToken();

        $this->assertSame(WorkspaceRole::Viewer, $invitation->role);

        // The invitee signs up separately, then follows the emailed link.
        $viewer = User::factory()->create(['email' => 'viewer@example.com']);

        $this->actingAs($viewer)
            ->get(route('workspace.invitations.accept', ['token' => $token]))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($workspace->fresh()->hasMember($viewer));
        $this->assertSame(WorkspaceRole::Viewer, $workspace->fresh()->roleFor($viewer));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_resending_rotates_the_token_so_the_old_link_stops_working(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);
        $service = app(InvitationService::class);

        $invitation = $service->invite($workspace, 'newhire@example.com', WorkspaceRole::Member, $owner);
        $firstToken = $invitation->plainToken();

        // The cooldown exists to stop the button mail-bombing an address.
        $invitation->forceFill(['last_sent_at' => now()->subMinutes(5)])->save();

        $secondToken = $service->resend($invitation->fresh())->plainToken();

        $this->assertNotSame($firstToken, $secondToken);

        $this->get(route('workspace.invitations.accept', ['token' => $firstToken]))
            ->assertOk(); // renders the "not valid" state rather than accepting

        $this->assertDatabaseHas('workspace_invitations', [
            'id' => $invitation->id,
            'accepted_at' => null,
            'send_count' => 2,
        ]);
    }

    public function test_resend_is_rate_limited(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);

        $invitation = app(InvitationService::class)
            ->invite($workspace, 'newhire@example.com', WorkspaceRole::Member, $owner);

        // Just sent, so the cooldown has not elapsed.
        $this->actingAs($owner)
            ->from(route('workspace.edit'))
            ->post(route('workspace.invitations.resend', [$workspace, $invitation]))
            ->assertSessionHas('flash.error');

        $this->assertSame(1, $invitation->fresh()->send_count);
    }

    public function test_an_expired_invitation_is_not_accepted(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);

        $invitation = app(InvitationService::class)
            ->invite($workspace, 'late@example.com', WorkspaceRole::Member, $owner);
        $token = $invitation->plainToken();

        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        $user = User::factory()->create(['email' => 'late@example.com']);

        $this->actingAs($user)
            ->get(route('workspace.invitations.accept', ['token' => $token]))
            ->assertOk();

        $this->assertFalse($workspace->fresh()->hasMember($user));
    }

    public function test_an_invitation_cannot_be_accepted_by_a_different_account(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);

        $invitation = app(InvitationService::class)
            ->invite($workspace, 'intended@example.com', WorkspaceRole::Member, $owner);

        $someoneElse = User::factory()->create(['email' => 'someone.else@example.com']);

        $this->actingAs($someoneElse)
            ->get(route('workspace.invitations.accept', ['token' => $invitation->plainToken()]))
            ->assertOk();

        $this->assertFalse($workspace->fresh()->hasMember($someoneElse));
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_a_guest_is_pointed_at_registration_and_joins_after_signing_in(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);

        $invitation = app(InvitationService::class)
            ->invite($workspace, 'guest@example.com', WorkspaceRole::Viewer, $owner);

        $this->get(route('workspace.invitations.accept', ['token' => $invitation->plainToken()]))
            ->assertOk()
            ->assertSessionHas('workspace_invitation_token', $invitation->plainToken());

        // Having signed up, their next authenticated request completes the join.
        $guest = User::factory()->create(['email' => 'guest@example.com']);

        $this->actingAs($guest)->get(route('dashboard'));

        $this->assertTrue($workspace->fresh()->hasMember($guest));
    }

    public function test_a_member_cannot_invite_anyone(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);
        $member = User::factory()->create();
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Member->value]);

        $this->actingAs($member)
            ->post(route('workspace.invitations.store', $workspace), [
                'email' => 'nope@example.com',
                'role' => 'member',
            ])
            ->assertForbidden();
    }

    public function test_an_invitation_can_be_revoked(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);

        $invitation = app(InvitationService::class)
            ->invite($workspace, 'revoked@example.com', WorkspaceRole::Member, $owner);

        $this->actingAs($owner)
            ->delete(route('workspace.invitations.destroy', [$workspace, $invitation]))
            ->assertRedirect();

        $this->assertDatabaseMissing('workspace_invitations', ['id' => $invitation->id]);
    }

    public function test_the_invitation_email_carries_the_accept_link(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = $this->workspaceOwnedBy($owner);

        app(InvitationService::class)
            ->invite($workspace, 'nobody@example.com', WorkspaceRole::Viewer, $owner);

        Notification::assertSentOnDemand(
            WorkspaceInvited::class,
            function (WorkspaceInvited $notification, array $channels, object $notifiable) {
                $mail = $notification->toMail($notifiable);

                return str_contains($notification->acceptUrl, '/invitations/')
                    && $mail->actionUrl === $notification->acceptUrl;
            },
        );
    }
}
