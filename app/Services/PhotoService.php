<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientPhoto;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos antes/después de los clientes. Se guardan en el disco privado
 * (`storage/app/private/client-photos/...`), nunca en `public`: solo se ven a
 * través de una ruta que exige sesión y permiso. Al subirlas se redimensionan
 * (lado mayor 1600 px) y se genera una miniatura de 400 px, en JPEG.
 */
class PhotoService
{
    public const MAX = 1600;
    public const THUMB = 400;

    public function store(Client $client, UploadedFile $file, array $data): ClientPhoto
    {
        $dir = 'client-photos/' . $client->company_id . '/' . $client->id;
        $name = Str::uuid()->toString();

        $image = $this->load($file);
        $path = "$dir/$name.jpg";
        $thumb = "$dir/{$name}_t.jpg";
        Storage::disk('local')->put($path, $this->jpeg($image, self::MAX));
        Storage::disk('local')->put($thumb, $this->jpeg($image, self::THUMB));
        imagedestroy($image);

        return ClientPhoto::create([
            'company_id'     => $client->company_id,
            'client_id'      => $client->id,
            'appointment_id' => $data['appointment_id'] ?? null,
            'service_id'     => $data['service_id'] ?? null,
            'kind'           => $data['kind'] ?? 'other',
            'path'           => $path,
            'thumb_path'     => $thumb,
            'taken_on'       => $data['taken_on'] ?? today()->toDateString(),
            'notes'          => $data['notes'] ?? null,
            'uploaded_by'    => auth()->id(),
        ]);
    }

    public function delete(ClientPhoto $photo): void
    {
        Storage::disk('local')->delete(array_filter([$photo->path, $photo->thumb_path]));
        $photo->delete();
    }

    /** Abre la imagen y la endereza según su orientación EXIF (fotos de celular). */
    protected function load(UploadedFile $file)
    {
        $data = file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($data);
        if (!$image) {
            throw new DomainException('El archivo no es una imagen válida (usa JPG, PNG o WEBP).');
        }

        if (function_exists('exif_read_data') && in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            $exif = @exif_read_data($file->getRealPath());
            $image = match ((int) ($exif['Orientation'] ?? 1)) {
                3       => imagerotate($image, 180, 0),
                6       => imagerotate($image, -90, 0),
                8       => imagerotate($image, 90, 0),
                default => $image,
            };
        }

        return $image;
    }

    /** JPEG con el lado mayor limitado a $max píxeles. */
    protected function jpeg($image, int $max): string
    {
        [$w, $h] = [imagesx($image), imagesy($image)];
        $scale = min(1, $max / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

        $canvas = imagecreatetruecolor($nw, $nh);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); // fondo blanco para PNG con transparencia
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($canvas, null, 82);
        imagedestroy($canvas);

        return ob_get_clean();
    }
}
