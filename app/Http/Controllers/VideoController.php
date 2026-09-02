<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoLinkRequest;
use App\Http\Requests\UploadVideoRequest;
use App\Models\Video;
use App\Support\LocationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    /**
     * List the media (uploaded videos + links) for one location. Public —
     * the TV screen calls this without logging in, the same way it reads
     * tickets. Location comes from the authenticated user when present,
     * otherwise from the `location` input / `X-UNILAB-LOCATION` header.
     */
    public function index(Request $request)
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

    /**
     * Upload an mp4 file for the authenticated superadmin's location.
     */
    public function upload(UploadVideoRequest $request)
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

    /**
     * Register an external video link (YouTube or a direct video URL) for
     * the authenticated superadmin's location.
     */
    public function storeLink(StoreVideoLinkRequest $request)
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

    /**
     * Serve an uploaded video file by filename. Legacy direct-streaming
     * route — uploaded files are also reachable directly through the
     * `storage` symlink via the URL returned by index()/upload().
     */
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

    /**
     * Remove a video (upload or link) belonging to the authenticated
     * superadmin's location. Deletes the underlying file for uploads.
     */
    public function destroy(Request $request, Video $video)
    {
        if ($video->location !== $request->user()->location) {
            abort(404);
        }

        if ($video->type === Video::TYPE_UPLOAD && $video->filename) {
            Storage::disk('public')->delete('videos/' . $video->filename);
        }

        $video->delete();

        return response()->json([
            'message' => 'Video deleted successfully',
        ]);
    }
}
