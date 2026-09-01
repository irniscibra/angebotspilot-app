<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectPhotoController extends Controller
{
    /**
     * Alle Fotos eines Projekts.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $photos = $project->photos()->orderBy('created_at', 'desc')->get();

        return response()->json($photos);
    }

    /**
     * Ein oder mehrere Fotos hochladen (Mehrfachauswahl im Frontend).
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $request->validate([
            'photos' => 'required|array|min:1',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp,heic,heif|max:10240', // max 10 MB/Foto
            'caption' => 'nullable|string|max:255',
        ]);

        $created = [];

        foreach ($request->file('photos') as $file) {
            $path = $file->store('projects/' . $project->id . '/photos', 'public');

            $created[] = ProjectPhoto::create([
                'company_id' => $request->user()->company_id,
                'project_id' => $project->id,
                'uploaded_by' => $request->user()->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'caption' => $request->input('caption'),
            ]);
        }

        return response()->json($created, 201);
    }

    /**
     * Bildunterschrift aktualisieren.
     */
    public function update(Request $request, Project $project, ProjectPhoto $photo): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizePhoto($project, $photo);

        $request->validate([
            'caption' => 'nullable|string|max:255',
        ]);

        $photo->update($request->only(['caption']));

        return response()->json($photo->fresh());
    }

    /**
     * Foto löschen (Datei + Datenbankeintrag).
     */
    public function destroy(Request $request, Project $project, ProjectPhoto $photo): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizePhoto($project, $photo);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return response()->json(['message' => 'Foto gelöscht.']);
    }

    /**
     * Stellt sicher, dass das Projekt zur Firma des Users gehört.
     */
    private function authorizeProject(Request $request, Project $project): void
    {
        if ($project->company_id !== $request->user()->company_id) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    /**
     * Stellt sicher, dass das Foto zu diesem Projekt gehört.
     */
    private function authorizePhoto(Project $project, ProjectPhoto $photo): void
    {
        if ($photo->project_id !== $project->id) {
            abort(404);
        }
    }
}
