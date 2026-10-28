<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\MessageService;
use Illuminate\Http\Request;

/**
 * "Enviar por WhatsApp": arma el mensaje con la plantilla, lo registra y
 * redirige a wa.me con el texto listo. La persona solo toca Enviar.
 */
class MessageController extends Controller
{
    public function __construct(protected MessageService $messages)
    {
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'client'      => ['required', 'integer'],
            'type'        => ['required', 'in:reminder,birthday,campaign,general'],
            'appointment' => ['nullable', 'integer'],
            'campaign'    => ['nullable', 'integer'],
            'template'    => ['nullable', 'integer'],
            'coupon'      => ['nullable', 'integer'],
        ]);

        $user = auth()->user();
        $company = $user->getCurrentCompany();
        abort_unless($company, 403);

        // Recordatorios: con permiso de recordatorios. El resto: enviar mensajes de Marketing.
        $permission = $data['type'] === 'reminder' ? 'reminders.view' : 'marketing.send';
        abort_unless($user->is_super_admin || $user->hasPermissionInCompany($permission, $company), 403);

        $client = Client::where('company_id', $company->id)->findOrFail($data['client']);
        abort_if(!$client->phone, 422, 'El cliente no tiene teléfono.');

        $message = $this->messages->compose($company, $client, $data['type'],
            $data['appointment'] ?? null, $data['campaign'] ?? null, $data['template'] ?? null, $data['coupon'] ?? null);

        return redirect()->away($message['url']);
    }
}
