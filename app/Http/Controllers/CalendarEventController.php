<?php

namespace App\Http\Controllers;

use App\Services\PortalActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarEventController extends Controller
{
    private function data(Request $request): array
    {
        return $request->validate(['title' => 'required|string|max:150', 'category' => 'required|in:meeting,personal,other', 'starts_at' => 'required|date_format:Y-m-d\TH:i', 'ends_at' => 'required|date_format:Y-m-d\TH:i|after:starts_at', 'location' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:2000', 'status' => 'required|in:planned,completed']);
    }

    public function store(Request $request)
    {
        $data = $this->data($request);
        DB::transaction(function () use ($data, $request) {
            $id = DB::table('calendar_events')->insertGetId([...$data, 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            PortalActivity::audit($request->user()->id, 'calendar.created', 'calendar_event', $id, array_keys($data));
        });

        return back()->with('success', 'Agenda berhasil ditambahkan.');
    }

    public function update(Request $request, int $event)
    {
        $item = DB::table('calendar_events')->where('user_id', $request->user()->id)->where('id', $event)->first();
        abort_unless($item, 404);
        $data = $this->data($request);
        DB::transaction(function () use ($data, $event, $request) {
            DB::table('calendar_events')->where('id', $event)->where('user_id', $request->user()->id)->update([...$data, 'updated_at' => now()]);
            PortalActivity::audit($request->user()->id, 'calendar.updated', 'calendar_event', $event, array_keys($data));
        });

        return back()->with('success', 'Agenda berhasil diperbarui.');
    }

    public function destroy(Request $request, int $event)
    {
        $item = DB::table('calendar_events')->where('user_id', $request->user()->id)->where('id', $event)->first();
        abort_unless($item, 404);
        DB::transaction(function () use ($event, $request) {
            DB::table('calendar_events')->where('id', $event)->where('user_id', $request->user()->id)->delete();
            PortalActivity::audit($request->user()->id, 'calendar.deleted', 'calendar_event', $event, []);
        });

        return back()->with('success','Agenda dihapus.');
    }
}
