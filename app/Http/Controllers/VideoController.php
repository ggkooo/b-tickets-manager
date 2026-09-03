<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoLinkRequest;
use App\Http\Requests\UploadVideoRequest;
use App\Models\Video;
use App\Support\LocationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $location = LocationResolver::resolveFromRequest($request);

        $videos = Video::query()
            ->forLocation($location)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Video $video) => [
                'id' => $video->id,
                'type' => $video->type,
                'filename' => $video->filename,
                'url' => $video->playback_url,
            ]);

        return response()->json($videos);
    }

    public function upload(UploadVideoRequest $request): JsonResponse
    {
        $file = $request->file('video');
        $filename = 'video_' . Str::random(16) . '.mp4';
        $file->storeAs('videos', $filename, 'public');

        $video = Video::query()->create([
            'location' => $request->user()->location,
            'type' => Video::TYPE_UPLOAD,
            'filename' => $filename,
        ]);

        return response()->json([
            'message' => 'Video uploaded successfully',
            'data' => [
                'id' => $video->id,
                'type' => $video->type,
                'filename' => $video->filename,
                'url' => $video->playback_url,
            ],
        ], 201);
    }

    public function storeLink(StoreVideoLinkRequest $request): JsonResponse
    {
        $video = Video::query()->create([
            'location' => $request->user()->location,
            'type' => Video::TYPE_LINK,
            'url' => $request->validated('url'),
        ]);

        return response()->json([
            'message' => 'Video link added successfully',
            'data' => [
                'id' => $video->id,
                'type' => $video->type,
                'filename' => $video->filename,
                'url' => $video->playback_url,
            ],
        ], 201);
    }

    public function show(string $filename): JsonResponse|StreamedResponse
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

    public function destroy(Request $request, Video $video): JsonResponse
    {
        $this->authorize('manage', $video);

        if ($video->type === Video::TYPE_UPLOAD && $video->filename) {
            Storage::disk('public')->delete('videos/' . $video->filename);
        }

        $video->delete();

        return response()->json([
            'message' => 'Video deleted successfully',
        ]);
    }
}
