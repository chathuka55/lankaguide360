<?php

namespace App\Services;

use App\Enums\SenderRole;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Mail\TripMessageReceived;
use App\Mail\TripStatusChanged;
use App\Models\Message;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Status changes and messages for a trip (SRS 8.5, 8.8, FR-20 to FR-22). Every change is
 * written to trip_status_history and emailed to the traveller.
 */
class TripWorkflow
{
    /**
     * @param  array<string, mixed>  $attributes  extra trip columns to save with the change (e.g. final_total)
     */
    public function transition(Trip $trip, TripStatus $to, ?User $actor, ?string $note = null, array $attributes = []): Trip
    {
        $from = $trip->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidArgumentException("A trip can't go from {$from->label()} to {$to->label()}.");
        }

        if ($to === TripStatus::Rejected && blank($note)) {
            throw new InvalidArgumentException('Give the traveller a reason when rejecting a trip.');
        }

        DB::transaction(function () use ($trip, $from, $to, $actor, $note, $attributes) {
            $trip->forceFill([
                ...$attributes,
                'status' => $to,
                'approved_at' => $to === TripStatus::Approved ? now() : $trip->approved_at,
                'completed_at' => $to === TripStatus::Completed ? now() : $trip->completed_at,
                'agent_id' => $to === TripStatus::UnderReview && $actor?->isStaff() ? ($trip->agent_id ?? $actor->id) : $trip->agent_id,
            ])->save();

            $trip->statusHistory()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => $actor?->id,
                'note' => $note,
            ]);
        });

        // Editing during review (under_review → under_review) is not news for the traveller.
        if ($from !== $to && $trip->contactEmail()) {
            Mail::to($trip->contactEmail())->queue(new TripStatusChanged($trip->fresh(['leadTraveller', 'user']), $to, $note));
        }

        return $trip;
    }

    public function assign(Trip $trip, ?User $agent, User $actor): void
    {
        if ($agent && ! $agent->isStaff()) {
            throw new InvalidArgumentException('Trips can only be assigned to agents or admins.');
        }

        $trip->forceFill(['agent_id' => $agent?->id])->save();
        $trip->statusHistory()->create([
            'from_status' => $trip->status->value,
            'to_status' => $trip->status->value,
            'changed_by' => $actor->id,
            'note' => $agent ? "Assigned to {$agent->name}." : 'Unassigned.',
        ]);
    }

    /**
     * Post a message on the trip and email the other side.
     */
    public function message(Trip $trip, string $body, ?User $sender, SenderRole $role): Message
    {
        $message = $trip->messages()->create([
            'sender_id' => $sender?->id,
            'sender_role' => $role,
            'body' => trim($body),
        ]);
        $message->setRelation('trip', $trip);

        if ($role === SenderRole::Agent) {
            if ($trip->contactEmail()) {
                Mail::to($trip->contactEmail())->queue(new TripMessageReceived($message, $trip->privateUrl()));
            }
        } else {
            $recipients = $trip->agent
                ? [$trip->agent->email]
                : User::whereIn('role', [UserRole::Agent->value, UserRole::Admin->value])->pluck('email')->all();
            if ($recipients) {
                Mail::to($recipients)->queue(new TripMessageReceived($message, route('admin.trips.show', $trip)));
            }
        }

        return $message;
    }

    /**
     * Mark messages from the other side as read.
     */
    public function markRead(Trip $trip, string $readerSide): void
    {
        $trip->messages()
            ->whereNull('read_at')
            ->where('sender_role', $readerSide === 'agent' ? SenderRole::Traveller->value : SenderRole::Agent->value)
            ->update(['read_at' => now()]);
    }
}
