<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Plantillas de mensajes de WhatsApp. */
class TemplateController extends Controller
{
    public function __construct(protected MessageService $messages)
    {
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('marketing.templates.index', ['noCompany' => true]);
        }

        // Asegura las plantillas base de cada tipo.
        foreach (array_keys(MessageTemplate::TYPES) as $type) {
            $this->messages->templatesFor($companyId, $type);
        }

        return view('marketing.templates.index', [
            'noCompany' => false,
            'templates' => MessageTemplate::where('company_id', $companyId)->orderBy('type')->orderByDesc('is_default')->orderBy('name')->get()->groupBy('type'),
            'sample'    => $this->sampleVars(),
        ]);
    }

    public function create(Request $request)
    {
        return view('marketing.templates.form', [
            'template' => new MessageTemplate(['type' => $request->input('type', 'general'), 'active' => true]),
            'sample'   => $this->sampleVars(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $companyId) {
            $template = MessageTemplate::create($data + ['company_id' => $companyId, 'created_by' => auth()->id()]);
            $this->syncDefault($template);
        });

        return redirect()->route('marketing.templates.index')->with('success', 'Plantilla creada.');
    }

    public function edit(MessageTemplate $template)
    {
        $this->authorizeTemplate($template);

        return view('marketing.templates.form', ['template' => $template, 'sample' => $this->sampleVars()]);
    }

    public function update(Request $request, MessageTemplate $template)
    {
        $this->authorizeTemplate($template);
        $data = $this->validated($request);

        DB::transaction(function () use ($template, $data) {
            $template->update($data);
            $this->syncDefault($template);
        });

        return redirect()->route('marketing.templates.index')->with('success', 'Plantilla actualizada.');
    }

    public function destroy(MessageTemplate $template)
    {
        $this->authorizeTemplate($template);
        $template->delete();

        return redirect()->route('marketing.templates.index')->with('success', 'Plantilla eliminada.');
    }

    protected function validated(Request $request): array
    {
        $v = $request->validate([
            'type' => ['required', Rule::in(array_keys(MessageTemplate::TYPES))],
            'name' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
        ], ['body.required' => 'Escribe el mensaje.']);

        return $v + ['is_default' => $request->boolean('is_default'), 'active' => $request->boolean('active', true)];
    }

    /** Una sola plantilla predeterminada por tipo. */
    protected function syncDefault(MessageTemplate $template): void
    {
        if ($template->is_default) {
            MessageTemplate::where('company_id', $template->company_id)->where('type', $template->type)
                ->where('id', '!=', $template->id)->update(['is_default' => false]);
        }
    }

    protected function sampleVars(): array
    {
        $company = auth()->user()->getCurrentCompany();

        return [
            'nombre' => 'María', 'cliente' => 'María López', 'salon' => $company?->name ?? 'tu salón',
            'fecha' => today()->addDay()->locale('es')->isoFormat('dddd D [de] MMMM'), 'hora' => '10:30',
            'servicio' => 'manicura', 'profesional' => 'Ana', 'cupon' => 'CUMPLE15', 'descuento' => '15 %',
        ];
    }

    protected function authorizeTemplate(MessageTemplate $template): void
    {
        abort_if($template->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
