<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    /**
     * Reserve a seat for the authenticated user.
     */
    public function reserve(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $reservation = DB::transaction(function () use ($event, $user) {

            // Lock the event row so concurrent requests
            // cannot modify reserved_count at the same time.
            $event = Event::whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Check whether this user already has an active reservation.
            $reservation = Reservation::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first();

            if ($reservation && $reservation->status === 'reserved') {
                abort(response()->json([
                    'message' => 'You already have a reservation for this event.'
                ], 409));
            }

            // Make sure there is still a seat available.
            if ($event->reserved_count >= $event->capacity) {
                abort(response()->json([
                    'message' => 'No seats available for this event.'
                ], 409));
            }

            // If the user previously cancelled, reactivate
            // the existing reservation instead of creating another one.
            if ($reservation) {
                $reservation->update([
                    'status' => 'reserved',
                ]);
            } else {
                $reservation = Reservation::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'status' => 'reserved',
                ]);
            }

            // Increase the reserved seat count.
            $event->increment('reserved_count');

            return $reservation->fresh();
        });

        return response()->json([
            'message' => 'Seat reserved successfully.',
            'reservation' => $reservation,
        ], 201);
    }

    /**
     * Cancel the authenticated user's reservation.
     */
    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        $user = $request->user();

        if ($reservation->user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to cancel this reservation.'
            ], 403);
        }

        DB::transaction(function () use ($reservation) {

            // Lock the event first so cancellation and reservation
            // operations use the same locking order.
            $event = Event::whereKey($reservation->event_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Lock the reservation row as well.
            $reservation = Reservation::whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status === 'cancelled') {
                abort(response()->json([
                    'message' => 'Reservation is already cancelled.'
                ], 409));
            }

            $reservation->update([
                'status' => 'cancelled',
            ]);

            // Release the seat without allowing reserved_count to become negative.
            if ($event->reserved_count <= 0) {
                abort(response()->json([
                  'message' => 'Invalid event reservation count.'
                 ], 409));
            }
            $event->decrement('reserved_count');
        });

        return response()->json([
            'message' => 'Reservation cancelled successfully.',
        ], 200);
    }
}