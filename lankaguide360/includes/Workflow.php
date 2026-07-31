<?php
/**
 * Workflow — the single source of truth for booking lifecycle transitions,
 * status labels/badges, and the audit trail. Shared by the customer, dispatcher,
 * manager, performer and admin areas so status handling stays consistent.
 */

class Workflow
{
    /** status => [label, bootstrap badge colour, short description] */
    public const STATUSES = [
        'submitted'       => ['Submitted',        'secondary', 'Awaiting a dispatcher'],
        'dispatching'     => ['Dispatching',      'info',      'Dispatcher assigning resources'],
        'assigned'        => ['Resources assigned','primary',  'Guide/driver/vehicle assigned'],
        'manager_review'  => ['Manager review',    'warning',   'Awaiting manager approval'],
        'approved'        => ['Approved',          'primary',   'Approved, preparing payment'],
        'awaiting_payment'=> ['Awaiting payment',  'warning',   'Ready for the customer to pay'],
        'paid'            => ['Paid',              'success',   'Payment received'],
        'confirmed'       => ['Confirmed',         'success',   'Booking confirmed'],
        'in_progress'     => ['In progress',       'info',      'Trip underway'],
        'completed'       => ['Completed',         'success',   'Trip completed'],
        'cancelled'       => ['Cancelled',         'secondary', 'Booking cancelled'],
        'rejected'        => ['Rejected',          'danger',    'Booking rejected'],
    ];

    public static function label(string $status): string
    {
        return self::STATUSES[$status][0] ?? ucfirst($status);
    }

    public static function color(string $status): string
    {
        return self::STATUSES[$status][1] ?? 'secondary';
    }

    public static function desc(string $status): string
    {
        return self::STATUSES[$status][2] ?? '';
    }

    /** Render a coloured status badge. */
    public static function badge(string $status): string
    {
        $label = htmlspecialchars(self::label($status));
        return '<span class="badge text-bg-' . self::color($status) . '">' . $label . '</span>';
    }

    /**
     * Transition a booking to a new status, mirror the simple `status` column,
     * and write an audit-trail row. Returns true on success.
     */
    public static function advance(PDO $db, int $bookingId, string $toStatus, ?int $userId = null, ?string $note = null): bool
    {
        if (!isset(self::STATUSES[$toStatus])) {
            return false;
        }
        $cur = $db->prepare("SELECT workflow_status FROM bookings WHERE id = ?");
        $cur->execute([$bookingId]);
        $from = $cur->fetchColumn() ?: null;

        // Keep the legacy 3-state `status` column in sync for quick filters.
        $simple = 'pending';
        if (in_array($toStatus, ['paid', 'confirmed', 'in_progress', 'completed'], true)) {
            $simple = 'confirmed';
        } elseif (in_array($toStatus, ['cancelled', 'rejected'], true)) {
            $simple = 'cancelled';
        }

        $upd = $db->prepare("UPDATE bookings SET workflow_status = ?, status = ? WHERE id = ?");
        $upd->execute([$toStatus, $simple, $bookingId]);

        $log = $db->prepare(
            "INSERT INTO booking_status_history (booking_id, from_status, to_status, changed_by, note)
             VALUES (?,?,?,?,?)"
        );
        $log->execute([$bookingId, $from, $toStatus, $userId, $note]);
        return true;
    }

    /** Full status history for a booking, newest first. */
    public static function history(PDO $db, int $bookingId): array
    {
        $stmt = $db->prepare(
            "SELECT h.*, u.full_name AS actor
             FROM booking_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.booking_id = ? ORDER BY h.created_at DESC, h.id DESC"
        );
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll();
    }
}
