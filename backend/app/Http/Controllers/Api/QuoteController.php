<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\QuoteMail;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\QuoteAIService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuoteController extends Controller
{
    public function __construct(
        private QuoteAIService $aiService
    ) {}

    /**
     * Alle Angebote der Firma.
     */
    public function index(Request $request): JsonResponse
    {
        $quotes = $request->user()->company->quotes()
            ->with('customer')
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($quotes);
    }

    /**
     * Einzelnes Angebot mit allen Details.
     */
    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $quote->load(['customer', 'items', 'creator']);

        return response()->json($quote);
    }

    /**
     * Neues Angebot erstellen (leer oder mit KI).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'project_description' => 'required|string|min:10|max:5000',
            'customer_id' => 'nullable|exists:customers,id',
            'project_address' => 'nullable|string|max:500',
            'use_ai' => 'boolean',
        ]);

        $company = $request->user()->company;

        // Angebot erstellen
        $quote = Quote::create([
            'company_id' => $company->id,
            'customer_id' => $request->customer_id,
            'created_by' => $request->user()->id,
            'quote_number' => $company->generateQuoteNumber(),
            'project_title' => 'Neues Angebot',
            'project_description' => $request->project_description,
            'project_address' => $request->project_address,
            'vat_rate' => $company->default_vat_rate,
            'valid_until' => now()->addDays($company->quote_validity_days),
        ]);

        // KI-Angebot generieren
        if ($request->input('use_ai', true)) {
            try {
                $aiResult = $this->aiService->generateQuote($quote, $request->project_description);
                $quote->refresh();
                $quote->load('items');

                return response()->json([
                    'quote' => $quote,
                    'ai_notes' => $aiResult['notes'] ?? null,
                    'estimated_days' => $aiResult['estimated_days'] ?? null,
                ], 201);
            } catch (\Exception $e) {
                return response()->json([
                    'quote' => $quote,
                    'ai_error' => $e->getMessage(),
                ], 201);
            }
        }

        return response()->json(['quote' => $quote], 201);
    }

    /**
     * Angebot aktualisieren.
     */
    public function update(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $request->validate([
            'project_title' => 'sometimes|string|max:255',
            'project_description' => 'sometimes|string|max:5000',
            'project_address' => 'nullable|string|max:500',
            'customer_id' => 'nullable|exists:customers,id',
            'discount_percent' => 'sometimes|numeric|min:0|max:100',
            'header_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'terms_text' => 'nullable|string',
            'internal_notes' => 'nullable|string',
        ]);

        $quote->update($request->only([
            'project_title',
            'project_description',
            'project_address',
            'customer_id',
            'discount_percent',
            'header_text',
            'footer_text',
            'terms_text',
            'internal_notes',
        ]));

        // Neu kalkulieren falls Rabatt geändert
        if ($request->has('discount_percent')) {
            $quote->recalculate();
        }

        return response()->json($quote->fresh()->load('items'));
    }

    /**
     * Angebot löschen.
     */
    public function destroy(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $quote->delete();

        return response()->json(['message' => 'Angebot gelöscht.']);
    }

    /**
     * KI: Angebot neu generieren.
     */
    public function regenerate(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $request->validate([
            'project_description' => 'required|string|min:10|max:5000',
        ]);

        try {
            $aiResult = $this->aiService->generateQuote($quote, $request->project_description);
            $quote->refresh()->load('items');

            return response()->json([
                'quote' => $quote,
                'ai_notes' => $aiResult['notes'] ?? null,
                'estimated_days' => $aiResult['estimated_days'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Position hinzufügen.
     */
    public function addItem(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $request->validate([
            'group_name' => 'required|string|max:100',
            'type' => 'required|in:material,labor,flat,text',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'unit_price' => 'required|numeric|min:0',
            'material_id' => 'nullable|exists:materials,id',
        ]);

        $lastPosition = $quote->items()->max('position_number') ?? 0;

        $item = QuoteItem::create([
            'quote_id' => $quote->id,
            'position_number' => $lastPosition + 1,
            'group_name' => $request->group_name,
            'type' => $request->type,
            'title' => $request->title,
            'description' => $request->description,
            'quantity' => $request->quantity,
            'unit' => $request->unit,
            'unit_price' => $request->unit_price,
            'is_ai_generated' => false,
            'sort_order' => $lastPosition + 1,
            'material_id' => $request->material_id,
        ]);

        return response()->json($item, 201);
    }

    /**
     * Position aktualisieren.
     */
    public function updateItem(Request $request, Quote $quote, QuoteItem $item): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:20',
            'unit_price' => 'sometimes|numeric|min:0',
            'group_name' => 'sometimes|string|max:100',
        ]);

        $item->update($request->only([
            'title', 'description', 'quantity', 'unit', 'unit_price', 'group_name',
        ]));

        return response()->json($item);
    }

    /**
     * Position löschen.
     */
    public function deleteItem(Request $request, Quote $quote, QuoteItem $item): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $item->delete();

        return response()->json(['message' => 'Position gelöscht.']);
    }

    /**
     * Angebot senden (Status auf "sent").
     */
public function send(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
 
        $request->validate([
            'recipient_email' => 'required|email',
            'recipient_name' => 'required|string|max:255',
            'subject' => 'nullable|string|max:500',
            'message' => 'nullable|string|max:5000',
        ]);
 
        // Quote mit allen Relations laden
        $quote->load(['company', 'customer', 'items', 'creator']);
 
        // PDF generieren
        $groupedItems = $quote->items->groupBy('group_name');
        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'company' => $quote->company,
            'customer' => $quote->customer,
            'groupedItems' => $groupedItems,
            'creator' => $quote->creator,
        ]);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('defaultFont', 'DejaVu Sans');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
 
        $pdfContent = $pdf->output();
        $pdfFilename = $quote->quote_number . '.pdf';
 
        // Absender-Info
        $user = $request->user();
        $company = $quote->company;
        $senderName = $user->name;
        $replyToEmail = $company->email ?? $user->email;
 
        try {
            // E-Mail senden
            Mail::to($request->recipient_email)
                ->send(new QuoteMail(
                    quote: $quote,
                    recipientName: $request->recipient_name,
                    senderName: $senderName,
                    customMessage: $request->message,
                    replyToEmail: $replyToEmail,
                    pdfContent: $pdfContent,
                    pdfFilename: $pdfFilename,
                ));
 
            // Status auf "sent" setzen
            $quote->markAsSent();
 
            // PDF lokal speichern
            $storagePath = 'angebote/' . $company->id . '/' . $pdfFilename;
            \Illuminate\Support\Facades\Storage::disk('local')->put($storagePath, $pdfContent);
            $quote->update([
                'pdf_path' => $storagePath,
                'pdf_generated_at' => now(),
            ]);
 
            Log::info("Angebot versendet", [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'recipient' => $request->recipient_email,
                'sender' => $replyToEmail,
            ]);
 
            return response()->json([
                'message' => 'Angebot wurde erfolgreich per E-Mail versendet.',
                'quote' => $quote->fresh()->load(['customer', 'items']),
            ]);
 
        } catch (\Exception $e) {
            Log::error("E-Mail-Versand fehlgeschlagen", [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);
 
            return response()->json([
                'message' => 'E-Mail konnte nicht gesendet werden: ' . $e->getMessage(),
            ], 500);
        }
    }
 
    /**
     * Angebot duplizieren.
     */
    public function duplicate(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $company = $request->user()->company;

        $newQuote = $quote->replicate();
        $newQuote->uuid = null; // Wird automatisch generiert
        $newQuote->quote_number = $company->generateQuoteNumber();
        $newQuote->status = 'draft';
        $newQuote->sent_at = null;
        $newQuote->viewed_at = null;
        $newQuote->accepted_at = null;
        $newQuote->rejected_at = null;
        $newQuote->pdf_path = null;
        $newQuote->pdf_generated_at = null;
        $newQuote->save();

        // Positionen kopieren
        foreach ($quote->items as $item) {
            $newItem = $item->replicate();
            $newItem->quote_id = $newQuote->id;
            $newItem->save();
        }

        $newQuote->recalculate();

        return response()->json($newQuote->load('items'), 201);
    }

    /**
     * Stellt sicher, dass das Angebot zur Firma des Users gehört.
     */
    private function authorizeQuote(Request $request, Quote $quote): void
    {
        if ($quote->company_id !== $request->user()->company_id) {
            abort(403, 'Zugriff verweigert.');
        }
    }
}