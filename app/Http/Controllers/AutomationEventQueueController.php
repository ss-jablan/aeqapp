<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAutomationEventQueueRequest;
use App\Http\Requests\UpdateAutomationEventQueueRequest;
use App\Models\AutomationEventQueue;

class AutomationEventQueueController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\StoreAutomationEventQueueRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAutomationEventQueueRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\AutomationEventQueue $automationEventQueue
     *
     * @return \Illuminate\Http\Response
     */
    public function show(AutomationEventQueue $automationEventQueue)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\AutomationEventQueue $automationEventQueue
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(AutomationEventQueue $automationEventQueue)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\UpdateAutomationEventQueueRequest $request
     * @param \App\Models\AutomationEventQueue                     $automationEventQueue
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAutomationEventQueueRequest $request, AutomationEventQueue $automationEventQueue)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\AutomationEventQueue $automationEventQueue
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(AutomationEventQueue $automationEventQueue)
    {
        //
    }
}
