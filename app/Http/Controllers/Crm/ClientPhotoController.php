<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPhoto;
use App\Services\PhotoService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Fotos antes/después en la ficha del cliente (archivos privados). */
class ClientPhotoController extends Controller
{
    public function __construct(protected PhotoService $photos)
    {
    }

    public function store(Request $request, Client $client)
    {
        $this->authorizeClient($client);
        $data = $request->validate([
            'photos'     => ['required', 'array', 'max:10'],
            'photos.*'   => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'kind'       => ['required', Rule::in(array_keys(ClientPhoto::KINDS))],
            'taken_on'   => ['nullable', 'date', 'before_or_equal:today'],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('company_id', $client->company_id)],
            'notes'      => ['nullable', 'string', 'max:255'],
        ], [
            'photos.required' => 'Elige al menos una foto.',
            'photos.*.image'  => 'Solo se pueden subir imágenes (JPG, PNG o WEBP).',
            'photos.*.mimes'  => 'Solo se pueden subir imágenes (JPG, PNG o WEBP).',
            'photos.*.max'    => 'Cada foto puede pesar hasta 10 MB.',
        ]);

        try {
            foreach ($request->file('photos') as $file) {
                $this->photos->store($client, $file, $data);
            }
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->to(route('clients.show', $client) . '#fotos')->with('success', count($request->file('photos')) . ' foto(s) subida(s).');
    }

    /** Sirve la imagen solo a usuarios de la empresa con permiso. */
    public function image(ClientPhoto $photo, string $size = 'full')
    {
        abort_if($photo->company_id !== auth()->user()->getCurrentCompany()?->id, 403);
        $path = $size === 'thumb' ? ($photo->thumb_path ?: $photo->path) : $photo->path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function destroy(ClientPhoto $photo)
    {
        abort_if($photo->company_id !== auth()->user()->getCurrentCompany()?->id, 403);
        $client = $photo->client;
        $this->photos->delete($photo);

        return redirect()->to(route('clients.show', $client) . '#fotos')->with('success', 'Foto eliminada.');
    }

    protected function authorizeClient(Client $client): void
    {
        abort_if($client->company_id !== auth()->user()->getCurrentCompany()?->id, 403);
    }
}
