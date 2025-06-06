<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Quest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Account $account): JsonResponse
    {
        return response()->json([
            'quests' => $account->quests,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Account $account): JsonResponse
    {
        $request->validate([
            'quests' => ['required', 'array'],
            'quests.*' => ['required', 'array'],
            'quests.*.0' => ['required', 'string'],
            'quests.*.1' => ['required', 'integer'],
        ]);

        // This does not work for MongoDB
        //    $account->quests()->updateOrCreate([
        //        'account_id' => $account->id
        //    ], [
        //        'quests' => $request->input('quests')
        //    ]);

        $quests = Quest::where('account_id', $account->id)->first();

        if (! $quests) {
            $quests = new Quest;
            $quests->account_id = $account->id;
        }

        $quests->quests = $request['quests'];

        $quests->save();

        return response()->json([
            'data' => $account->quests,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
