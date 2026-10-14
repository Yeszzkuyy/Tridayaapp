<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function googleEvents(Request $request, GoogleCalendarService $calendar)
    {
        $from = $request->query('start') ? Carbon::parse($request->query('start')) : null;
        $to = $request->query('end') ? Carbon::parse($request->query('end')) : null;

        try {
            $events = $calendar->upcomingEvents(100, $from, $to);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(collect($events)->map(fn ($e) => [
            'id' => 'google-'.md5($e['title'].$e['start']),
            'title' => $e['title'],
            'start' => $e['start']?->toIso8601String(),
            'end' => $e['end']?->toIso8601String(),
            'allDay' => false,
            'description' => $e['description'],
            'location' => $e['location'],
            'google' => true,
        ])->values());
    }
    public function index(GoogleCalendarService $calendar)
    {
        try {
            $stats = $calendar->syncTwoWay();
            $events = $calendar->upcomingEvents(20);
        } catch (\Throwable $e) {
            return view('calendar', [
                'events' => [],
                'error' => $e->getMessage(),
            ]);
        }

        return view('calendar', [
            'events' => $events,
            'error' => null,
        ]);
    }
}