<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NewFeedbackNotification;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:bug,idea,kalkulation,sonstiges',
            'message' => 'required|string|max:5000',
            'quote_id' => 'nullable|integer|exists:quotes,id',
            'page_context' => 'nullable|string|max:255',
        ]);

        $feedback = Feedback::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'quote_id' => $request->quote_id,
            'type' => $request->type,
            'message' => $request->message,
            'page_context' => $request->page_context,
        ]);

        Mail::to('info@angebotspilot.app')
            ->send(new NewFeedbackNotification($feedback->load(['company', 'user'])));

        return response()->json([
            'message' => 'Danke für dein Feedback!',
        ], 201);
    }
}