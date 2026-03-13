<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    // Upload a video (mp4 only)
    public function upload(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4',
        ]);

        $file = $request->file('video');
        $filename = 'video_' . Str::random(16) . '.mp4';
        $path = $file->storeAs('videos', $filename, 'public');

        return response()->json([
            'message' => 'Video uploaded successfully',
            'filename' => $filename,
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }

    // Serve a video by filename
    public function show($filename)
    {
        $path = 'videos/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Video not found'], 404);
        }
        $stream = Storage::disk('public')->readStream($path);
        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function index()
    {
        $files = Storage::disk('public')->files('videos');
        $videos = collect($files)
            ->filter(fn($file) => str_ends_with($file, '.mp4'))
            ->map(fn($file) => [
                'filename' => basename($file),
                'url' => Storage::disk('public')->url($file),
            ])
            ->values();
        return response()->json($videos);
    }

    public function destroy(string $filename)
    {
        $path = 'videos/' . $filename;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Video not found'], 404);
        }

        Storage::disk('public')->delete($path);

        return response()->json([
            'message' => 'Video deleted successfully',
        ]);
    }
}
