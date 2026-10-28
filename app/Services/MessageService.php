<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Coupon;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use Illuminate\Support\Collection;

/**
 * Mensajes de WhatsApp "con un clic": arma el texto desde una plantilla,
 * genera el enlace wa.me y registra quién contactó a quién. El envío lo hace
 * la persona en su WhatsApp (sin costo ni API).
 */
class MessageService
{
    /** Plantillas base que recibe cada salón la primera vez. */
    public const DEFAULTS = [
        'reminder' => ['Recordatorio de cita', "Hola {nombre} 👋, te recordamos tu cita de {servicio} el {fecha} a las {hora} en {salon}. ¿Nos confirmas tu asistencia? ¡Te esperamos!"],
        'birthday' => ['Saludo de cumpleaños', "¡Feliz cumpleaños, {nombre}! 🎉 En {salon} queremos celebrarlo contigo: usa el código {cupon} y obtén {descuento} de descuento en tu próxima visita."],
        'campaign' => ['Te extrañamos', "Hola {nombre}, hace tiempo no te vemos en {salon} 💇‍♀️. Reserva esta semana y usa el código {cupon} para obtener {descuento} de descuento."],
        'general'  => ['Mensaje general', "Hola {nombre}, te escribimos de {salon}."],
    ];

    /** Plantillas activas de un tipo. Crea las base si el salón aún no tiene ninguna. */
    public function templatesFor(int $companyId, string $type): Collection
    {
        $query = fn () => MessageTemplate::withoutGlobalScopes()->where('company_id', $companyId)->where('type', $type)->where('active', true)
            ->orderByDesc('is_default')->orderBy('name')->get();

        $list = $query();
        if ($list->isEmpty() && isset(self::DEFAULTS[$type])
            && MessageTemplate::withoutGlobalScopes()->where('company_id', $companyId)->where('type', $type)->doesntExist()) {
            [$name, $body] = self::DEFAULTS[$type];
            MessageTemplate::create(['company_id' => $companyId, 'type' => $type, 'name' => $name, 'body' => $body, 'is_default' => true, 'active' => true]);
            $list = $query();
        }

        return $list;
    }

    public function defaultFor(int $companyId, string $type): ?MessageTemplate
    {
        return $this->templatesFor($companyId, $type)->first();
    }

    /** Reemplaza {variable} por su valor. Las variables sin valor quedan vacías. */
    public function render(string $body, array $vars): string
    {
        $text = preg_replace_callback('/\{(\w+)\}/u', fn ($m) => (string) ($vars[$m[1]] ?? ''), $body);

        // Limpia espacios dobles que dejan las variables vacías.
        return trim(preg_replace('/[ \t]{2,}/', ' ', $text));
    }

    /** Valores de las variables para un cliente (y su cita y cupón, si hay). */
    public function varsFor(Client $client, ?Appointment $appointment = null, ?Coupon $coupon = null, ?Company $company = null): array
    {
        $company ??= $client->company;
        $setting = CompanySetting::current($client->company_id);
        $start = $appointment?->starts_at?->copy()->locale('es');

        return [
            'nombre'      => mb_convert_case(mb_strtolower(explode(' ', trim($client->full_name))[0] ?? ''), MB_CASE_TITLE),
            'cliente'     => mb_convert_case(mb_strtolower($client->full_name), MB_CASE_TITLE),
            'salon'       => $setting->invoice_business_name ?: $company?->name,
            'fecha'       => $start?->isoFormat('dddd D [de] MMMM'),
            'hora'        => $start?->format('H:i'),
            'servicio'    => $appointment?->service?->name ? mb_strtolower($appointment->service->name) : 'tu servicio',
            'profesional' => $appointment?->specialist?->full_name ? mb_convert_case(mb_strtolower($appointment->specialist->full_name), MB_CASE_TITLE) : null,
            'cupon'       => $coupon?->code,
            'descuento'   => $coupon?->valueLabel($setting->currency_symbol),
        ];
    }

    /** `7000-0000` → `59170000000`. Un número local de 8 dígitos recibe el código de Bolivia. */
    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) === 8 ? '591' . $digits : $digits;
    }

    public function whatsappLink(?string $phone, string $text): string
    {
        return 'https://wa.me/' . $this->normalizePhone($phone) . '?text=' . rawurlencode($text);
    }

    /**
     * Arma el mensaje (campaña, plantilla elegida o la predeterminada del
     * tipo), lo registra y devuelve el texto y el enlace de WhatsApp. Lo usan
     * la web y la app.
     *
     * @return array{text: string, url: string}
     */
    public function compose(Company $company, Client $client, string $type, ?int $appointmentId = null, ?int $campaignId = null, ?int $templateId = null, ?int $couponId = null): array
    {
        $appointment = $appointmentId
            ? Appointment::with(['service', 'specialist'])->where('company_id', $company->id)->where('client_id', $client->id)->findOrFail($appointmentId)
            : null;
        $campaign = $campaignId ? \App\Models\Campaign::where('company_id', $company->id)->findOrFail($campaignId) : null;
        $couponId ??= $campaign?->coupon_id;
        $coupon = $couponId ? Coupon::withTrashed()->where('company_id', $company->id)->find($couponId) : null;

        $body = $campaign?->body
            ?? ($templateId ? MessageTemplate::where('company_id', $company->id)->find($templateId)?->body : null)
            ?? $this->defaultFor($company->id, $type)?->body
            ?? '';

        $text = $this->render($body, $this->varsFor($client, $appointment, $coupon, $company));
        $this->log($client, $type, $text, $appointment?->id, $campaign?->id);

        if ($campaign) {
            \App\Models\CampaignRecipient::where('campaign_id', $campaign->id)->where('client_id', $client->id)
                ->update(['sent_at' => now(), 'sent_by' => auth()->id()]);
        }

        return ['text' => $text, 'url' => $this->whatsappLink($client->phone, $text)];
    }

    public function log(Client $client, string $type, string $body, ?int $appointmentId = null, ?int $campaignId = null): MessageLog
    {
        return MessageLog::create([
            'company_id'     => $client->company_id,
            'client_id'      => $client->id,
            'type'           => $type,
            'channel'        => 'whatsapp',
            'appointment_id' => $appointmentId,
            'campaign_id'    => $campaignId,
            'phone'          => $client->phone,
            'body'           => $body,
            'sent_by'        => auth()->id(),
            'sent_at'        => now(),
        ]);
    }
}
