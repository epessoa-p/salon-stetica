@extends('layouts.app')

@section('title', 'Punto de Venta')

@section('page')
@if($noCompany)
    <h1 class="mb-3"><i class="bi bi-upc-scan"></i> Punto de Venta</h1>
    <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> El punto de venta se usa dentro de una empresa.</div>
@else
@php
    // Todo lo que el JS necesita para trabajar sin más consultas.
    $config = [
        'catalog'          => $catalog,
        'categories'       => $categories,
        'specialists'      => $specialists->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name])->values(),
        'paymentMethods'   => $paymentMethods,
        'openSessions'     => $openSessions->map(fn ($s) => ['id' => $s->id, 'name' => $s->register?->name ?? 'Caja'])->values(),
        'defaultPrincipal' => $defaultPrincipal,
        'currency'         => $setting->currency_symbol,
        'taxLabel'         => $setting->tax_label,
        'taxRate'          => (float) $setting->tax_rate,
        'taxIncluded'      => (bool) $setting->tax_included,
        'requireCash'      => (bool) $setting->require_cash_session,
        'canCreateClient'  => $canCreateClient,
        'canQuote'         => $canQuote,
        'sellsOnCredit'    => $sellsOnCredit,
        'sellsCoupons'     => $sellsCoupons,
        'creditDays'       => (int) $setting->receivable_default_days,
        'quoteId'          => old('quote_id', ($fromQuote['id'] ?? null)),
        'csrf'             => csrf_token(),
        'routes'           => [
            'quote'       => route('sales.pos.quote'),
            'clients'     => route('sales.pos.clients'),
            'client'      => url('admin/sales/pos/clients'),
            'storeClient' => route('sales.pos.clients.store'),
            'cashOpen'    => route('cash-sessions.create'),
            'quoteStore'  => $canQuote ? route('quotes.store') : null,
        ],
        // Si el cobro falla, el carrito se reconstruye con lo enviado; si se
        // viene de una cotización, con sus líneas.
        'old' => old('items') ? [
            'items'           => old('items'),
            'client_id'       => old('client_id'),
            'client_name'     => old('client_name'),
            'personal_id'     => old('personal_id'),
            'payment_method'  => old('payment_method'),
            'cash_session_id' => old('cash_session_id'),
            'branch_id'       => old('branch_id'),
            'global_discount' => old('global_discount'),
            'redeem_points'   => old('redeem_points'),
            'coupon_code'     => old('coupon_code'),
            'credit_amount'   => old('credit_amount'),
            'on_credit'       => old('on_credit'),
            'down_payment'    => old('down_payment'),
            'due_days'        => old('due_days'),
            'tip'             => old('tip'),
            'notes'           => old('notes'),
        ] : ($fromQuote['old'] ?? null),
    ];
@endphp

<div class="pos" id="pos">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-0"><i class="bi bi-upc-scan"></i> Punto de Venta</h1>
            <p class="text-muted mb-0 small d-none d-md-block">
                <kbd>/</kbd> buscar · <kbd>Enter</kbd> agregar el primero · <kbd>Ctrl</kbd>+<kbd>Enter</kbd> cobrar
            </p>
        </div>
        <div class="d-flex gap-2">
            @if($canQuote)
                <a href="{{ route('quotes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-text"></i> Cotizaciones</a>
            @endif
            <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-receipt"></i> Ventas</a>
        </div>
    </div>

    @if(!empty($fromQuote['error']))
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> {{ $fromQuote['error'] }}</div>
    @elseif($fromQuote)
        <div class="alert {{ $fromQuote['valid'] ? 'alert-info' : 'alert-warning' }} d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-text"></i> <span>{{ $fromQuote['message'] }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle"></i> No se pudo cobrar la venta</div>
            <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('sales.store') }}" method="POST" id="pos-form" autocomplete="off">
        @csrf
        <div id="pos-hidden"></div>

        <div class="pos-grid">
            {{-- ===================== Catálogo ===================== --}}
            <section class="pos-catalog">
                <div class="pos-search">
                    <i class="bi bi-search"></i>
                    <input type="search" id="pos-search" class="form-control no-uppercase"
                           placeholder="Buscar servicio{{ $sellsProducts ? ', producto' : '' }}, combo, paquete{{ $sellsMemberships ? ' o membresía' : '' }}…" autofocus>
                    <kbd class="d-none d-md-inline">/</kbd>
                </div>
                <div class="pos-chips" id="pos-chips"></div>
                <div class="pos-items" id="pos-items"></div>
                <div class="pos-empty d-none" id="pos-items-empty">
                    <i class="bi bi-search"></i>
                    <div>No hay resultados para tu búsqueda.</div>
                </div>
            </section>

            {{-- ===================== Ticket ===================== --}}
            <aside class="pos-ticket" id="pos-ticket">
                {{-- Cliente --}}
                <div class="pos-block">
                    <div class="pos-block-title"><i class="bi bi-person"></i> Cliente</div>
                    <div id="pos-client-pick">
                        <div class="position-relative">
                            <input type="search" id="pos-client-search" class="form-control form-control-sm no-uppercase"
                                   placeholder="Buscar por nombre, teléfono o documento…">
                            <div class="pos-dropdown d-none" id="pos-client-results"></div>
                        </div>
                        <div class="d-flex gap-2 mt-2 align-items-center">
                            <input type="text" id="pos-client-name" class="form-control form-control-sm" placeholder="Cliente ocasional (nombre opcional)">
                            @if($canCreateClient)
                                <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="pos-client-new">
                                    <i class="bi bi-person-plus"></i> Nuevo
                                </button>
                            @endif
                        </div>
                    </div>
                    <div id="pos-client-card" class="d-none"></div>
                </div>

                {{-- Profesional principal --}}
                <div class="pos-block">
                    <label class="pos-block-title" for="pos-principal"><i class="bi bi-person-badge"></i> Profesional principal</label>
                    <select id="pos-principal" class="form-select form-select-sm">
                        <option value="">Sin asignar</option>
                        @foreach($specialists as $sp)
                            <option value="{{ $sp->id }}">{{ $sp->full_name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Recibe la propina y lo heredan las líneas sin profesional.</div>
                </div>

                {{-- Líneas --}}
                <div class="pos-block pos-block-lines">
                    <div class="pos-block-title d-flex justify-content-between">
                        <span><i class="bi bi-bag"></i> Venta</span>
                        <button type="button" class="btn btn-link btn-sm p-0 text-danger d-none" id="pos-clear">Vaciar</button>
                    </div>
                    <div id="pos-lines"></div>
                    <div class="pos-empty pos-empty-sm" id="pos-lines-empty">
                        <i class="bi bi-cart"></i>
                        <div>Toca un servicio o producto para agregarlo.</div>
                    </div>
                </div>

                {{-- Totales --}}
                <div class="pos-block pos-totals" id="pos-totals"></div>

                {{-- Puntos --}}
                <div class="pos-block d-none" id="pos-points"></div>

                {{-- Saldo a favor --}}
                <div class="pos-block d-none" id="pos-credit">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="pos-block-title mb-0"><i class="bi bi-wallet2"></i> Saldo a favor</span>
                        <span class="small" id="credit-info"></span>
                    </div>
                    <div class="d-flex gap-2 mt-2 align-items-center">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                            <input type="number" id="pos-credit-input" class="form-control text-end no-uppercase" min="0" step="0.5" placeholder="Monto a usar">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="credit-max">Usar todo</button>
                    </div>
                    <div class="form-text">Paga parte o todo el total (no la propina).</div>
                </div>

                {{-- Propina --}}
                <div class="pos-block">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="pos-block-title mb-0" for="pos-tip"><i class="bi bi-heart"></i> Propina</label>
                        <div class="input-group input-group-sm" style="width: 140px;">
                            <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                            <input type="number" id="pos-tip" class="form-control text-end no-uppercase" min="0" step="0.5" value="0">
                        </div>
                    </div>
                    <div class="form-text">Va íntegra al profesional principal; no es venta del salón.</div>
                </div>

                {{-- Pago --}}
                <div class="pos-block">
                    <div class="pos-block-title"><i class="bi bi-wallet2"></i> Forma de pago</div>
                    <div class="pos-pay" id="pos-pay"></div>
                    <div class="pos-cash d-none" id="pos-cash">
                        <div class="d-flex gap-2 align-items-center">
                            <label for="pos-received" class="small text-muted text-nowrap mb-0">Recibido</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                                <input type="number" id="pos-received" class="form-control text-end no-uppercase" min="0" step="0.5">
                            </div>
                        </div>
                        <div class="pos-quick" id="pos-quick"></div>
                        <div class="pos-change" id="pos-change"></div>
                    </div>
                    @if($sellsOnCredit)
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="pos-on-credit">
                            <label class="form-check-label small fw-semibold" for="pos-on-credit"><i class="bi bi-journal-text"></i> Venta a crédito</label>
                        </div>
                        <div class="pos-credit-sale d-none" id="pos-credit-sale">
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <label for="pos-down" class="small text-muted text-nowrap mb-0">Anticipo</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                                    <input type="number" id="pos-down" class="form-control text-end no-uppercase" min="0" step="0.5" value="0">
                                </div>
                                <label for="pos-due" class="small text-muted text-nowrap mb-0">Vence en</label>
                                <div class="input-group input-group-sm" style="max-width: 120px;">
                                    <input type="number" id="pos-due" class="form-control text-end no-uppercase" min="1" max="365" value="{{ (int) $setting->receivable_default_days }}">
                                    <span class="input-group-text">días</span>
                                </div>
                            </div>
                            <div class="small mt-1" id="pos-credit-info"></div>
                        </div>
                    @endif
                </div>

                {{-- Caja y opciones --}}
                <div class="pos-block">
                    <div id="pos-cash-session"></div>
                    <button class="btn btn-link btn-sm p-0 mt-1" type="button" data-bs-toggle="collapse" data-bs-target="#pos-more">
                        <i class="bi bi-sliders"></i> Más opciones
                    </button>
                    <div class="collapse" id="pos-more">
                        @if($branches->count())
                            <label class="form-label small mt-2 mb-1" for="pos-branch">Sucursal</label>
                            <select id="pos-branch" class="form-select form-select-sm">
                                <option value="">Sin sucursal</option>
                                @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                            </select>
                        @endif
                        <label class="form-label small mt-2 mb-1" for="pos-notes">Notas</label>
                        <textarea id="pos-notes" rows="2" class="form-control form-control-sm"></textarea>
                    </div>
                </div>

                {{-- Cobrar --}}
                <div class="pos-checkout">
                    <ul class="pos-errors d-none" id="pos-errors"></ul>
                    <button type="submit" class="btn btn-success btn-lg w-100" id="pos-submit" disabled>
                        <i class="bi bi-cash-coin"></i> <span id="pos-submit-label">Cobrar</span>
                    </button>
                    @if($canQuote)
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" id="pos-save-quote" disabled>
                            <i class="bi bi-file-earmark-text"></i> Guardar como cotización
                        </button>
                    @endif
                </div>
            </aside>
        </div>

        {{-- Barra fija en móvil --}}
        <div class="pos-mobile-bar d-lg-none">
            <div>
                <div class="small text-muted">Total</div>
                <div class="fw-bold fs-5" id="pos-mobile-total">{{ $setting->currency_symbol }} 0.00</div>
            </div>
            <a href="#pos-ticket" class="btn btn-success"><i class="bi bi-receipt"></i> Ver ticket</a>
        </div>
    </form>
</div>

{{-- Alta rápida de cliente --}}
@if($canCreateClient)
<div class="modal fade" id="pos-client-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="pos-client-form">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Nuevo cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label" for="nc-name">Nombre completo <span class="text-danger">*</span></label>
                    <input type="text" id="nc-name" class="form-control" required maxlength="255">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="nc-phone">Teléfono</label>
                        <input type="text" id="nc-phone" class="form-control" maxlength="30">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="nc-doc">Documento</label>
                        <input type="text" id="nc-doc" class="form-control" maxlength="50">
                    </div>
                </div>
                <div class="text-danger small mt-2 d-none" id="nc-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="nc-save"><i class="bi bi-check-lg"></i> Crear y usar</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>window.POS_CONFIG = @json($config);</script>
@endif
@endsection

@if(!$noCompany)
@push('styles')
<style>
    /* ---------- Punto de venta ---------- */
    .pos { --svc: #a855f7; --prd: #f59e0b; --mem: #ec4899; --cmb: #0ea5e9; --pkg: #10b981; }

    .pos-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 400px;
        gap: 1rem;
        align-items: start;
    }

    .pos-catalog, .pos-ticket {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
    }

    .pos-catalog { padding: 1rem; min-height: 60vh; }

    /* El ticket queda a la vista mientras se recorre el catálogo */
    .pos-ticket {
        position: sticky;
        top: .75rem;
        max-height: calc(100vh - 1.5rem);
        overflow-y: auto;
        scrollbar-width: thin;
    }

    /* Buscador */
    .pos-search { position: relative; }
    .pos-search > .bi-search { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); color: #9ca3af; }
    .pos-search > kbd { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; font-size: .7rem; }
    .pos-search .form-control { padding: .6rem 2.4rem; font-size: .95rem; border-radius: 8px; }

    /* Filtros */
    .pos-chips { display: flex; gap: .4rem; flex-wrap: wrap; margin: .75rem 0; }
    .pos-chip {
        border: 1px solid #e5e7eb; background: #fff; color: #4b5563;
        border-radius: 999px; padding: .25rem .75rem; font-size: .8rem; cursor: pointer;
        transition: all .15s ease;
    }
    .pos-chip:hover { border-color: #c7cbd1; }
    .pos-chip.active { background: #111827; border-color: #111827; color: #fff; }
    .pos-chip .count { opacity: .6; margin-left: .25rem; }

    /* Tarjetas del catálogo */
    .pos-items {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: .6rem;
    }
    .pos-card {
        --c: var(--svc);
        position: relative;
        display: flex; flex-direction: column; align-items: flex-start; gap: .15rem;
        text-align: left; padding: .7rem .75rem;
        background: #fff; border: 1px solid #eef0f2; border-left: 3px solid var(--c);
        border-radius: 8px; cursor: pointer; min-height: 92px;
        transition: transform .1s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .pos-card:hover:not(:disabled) { box-shadow: 0 4px 12px rgba(0, 0, 0, .08); transform: translateY(-1px); }
    .pos-card:active:not(:disabled) { transform: scale(.98); }
    .pos-card:focus-visible { outline: 2px solid var(--c); outline-offset: 2px; }
    .pos-card:disabled { opacity: .45; cursor: not-allowed; }
    .pos-card.kind-product { --c: var(--prd); }
    .pos-card.kind-membership { --c: var(--mem); background: color-mix(in srgb, var(--mem) 5%, #fff); }
    .pos-card.kind-combo { --c: var(--cmb); background: color-mix(in srgb, var(--cmb) 5%, #fff); }
    .pos-card.kind-package { --c: var(--pkg); background: color-mix(in srgb, var(--pkg) 5%, #fff); }
    .pos-card-list { font-size: .72rem; color: #9ca3af; text-decoration: line-through; font-weight: 400; margin-left: .25rem; }
    .pos-card-icon { color: var(--c); font-size: .85rem; }
    .pos-card-name { font-weight: 600; font-size: .85rem; line-height: 1.2; color: #111827; }
    .pos-card-meta { font-size: .72rem; color: #6b7280; line-height: 1.2; }
    .pos-card-price { margin-top: auto; font-weight: 700; font-size: .9rem; color: #111827; }
    .pos-card-qty {
        position: absolute; top: .45rem; right: .45rem;
        background: var(--c); color: #fff; border-radius: 999px;
        font-size: .7rem; font-weight: 700; padding: 0 .45rem; line-height: 1.4rem; min-width: 1.4rem; text-align: center;
    }
    .pos-card.flash { animation: pos-flash .35s ease; }
    @keyframes pos-flash { 50% { background: color-mix(in srgb, var(--c) 18%, #fff); } }

    /* Bloques del ticket */
    .pos-block { padding: .75rem 1rem; border-bottom: 1px solid #f1f2f4; }
    .pos-block-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-bottom: .4rem; display: block; }
    .pos-block-lines { padding-bottom: .4rem; }

    .pos-dropdown {
        position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% + 2px);
        background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .12); max-height: 260px; overflow-y: auto;
    }
    .pos-dropdown button {
        display: block; width: 100%; text-align: left; border: 0; background: none;
        padding: .45rem .7rem; font-size: .85rem;
    }
    .pos-dropdown button:hover, .pos-dropdown button.active { background: #f3f4f6; }
    .pos-dropdown .muted { color: #9ca3af; font-size: .75rem; }

    .pos-client-card { border: 1px solid #eef0f2; border-radius: 8px; padding: .6rem .7rem; }
    .pos-member {
        margin-top: .5rem; padding: .45rem .6rem; border-radius: 6px;
        background: color-mix(in srgb, var(--mem) 9%, #fff); border: 1px solid color-mix(in srgb, var(--mem) 25%, #fff);
        font-size: .78rem;
    }
    .pos-member strong { color: color-mix(in srgb, var(--mem) 80%, #000); }

    /* Líneas */
    .pos-line { display: grid; grid-template-columns: 1fr auto auto; gap: .5rem; align-items: start; padding: .5rem 0; border-bottom: 1px dashed #eef0f2; }
    .pos-line:last-child { border-bottom: 0; }
    .pos-line-name { font-weight: 600; font-size: .85rem; line-height: 1.2; }
    .pos-line-meta { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; margin-top: .3rem; }
    .pos-line-meta .form-select, .pos-line-meta .form-control { font-size: .75rem; padding: .1rem .35rem; height: auto; }
    .pos-line-meta .form-select { max-width: 140px; }
    .pos-line-meta .price { width: 78px; }
    .pos-line-meta .disc { width: 70px; }
    .pos-badge { display: inline-block; font-size: .65rem; font-weight: 700; border-radius: 4px; padding: .05rem .35rem; margin-left: .25rem; vertical-align: middle; }
    .pos-badge.product { background: color-mix(in srgb, var(--prd) 18%, #fff); color: #92400e; }
    .pos-badge.membership, .pos-badge.benefit { background: color-mix(in srgb, var(--mem) 15%, #fff); color: #9d174d; }
    .pos-badge.combo { background: color-mix(in srgb, var(--cmb) 15%, #fff); color: #075985; }
    .pos-badge.package { background: color-mix(in srgb, var(--pkg) 15%, #fff); color: #065f46; }
    .pos-pkg { margin-top: .4rem; padding: .4rem .6rem; border-radius: 6px; font-size: .78rem; background: color-mix(in srgb, var(--pkg) 8%, #fff); border: 1px solid color-mix(in srgb, var(--pkg) 25%, #fff); }
    .pos-credit-chip { margin-top: .4rem; font-size: .78rem; color: #1d4ed8; }
    .pos-account { margin-top: .4rem; font-size: .78rem; }
    .pos-credit-sale { margin-top: .4rem; padding: .5rem .6rem; border-radius: 6px; background: #fff7ed; border: 1px solid #fed7aa; }
    .pos-stepper { display: inline-flex; align-items: center; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; }
    .pos-stepper button { border: 0; background: #f9fafb; width: 26px; height: 26px; line-height: 1; font-weight: 700; color: #374151; }
    .pos-stepper button:hover:not(:disabled) { background: #eef0f2; }
    .pos-stepper button:disabled { color: #c4c4c4; }
    .pos-stepper span { min-width: 28px; text-align: center; font-size: .85rem; font-weight: 600; }
    .pos-line-amount { text-align: right; min-width: 74px; }
    .pos-line-amount .now { font-weight: 700; font-size: .9rem; }
    .pos-line-amount .was { font-size: .72rem; color: #9ca3af; text-decoration: line-through; display: block; }
    .pos-line-remove { border: 0; background: none; color: #9ca3af; padding: 0 0 0 .25rem; }
    .pos-line-remove:hover { color: #dc2626; }
    .pos-link { border: 0; background: none; padding: 0; font-size: .72rem; color: #6b7280; text-decoration: underline dotted; }

    /* Totales */
    .pos-totals .row-t { display: flex; justify-content: space-between; font-size: .85rem; padding: .1rem 0; color: #4b5563; }
    .pos-totals .row-t.benefit { color: #9d174d; }
    .pos-totals .row-t.total { color: #111827; font-size: 1.35rem; font-weight: 800; margin-top: .35rem; padding-top: .45rem; border-top: 1px solid #eef0f2; }
    .pos-totals .row-t.charge { color: #111827; font-weight: 700; }
    .pos-totals.loading { opacity: .55; transition: opacity .15s; }

    /* Pago */
    .pos-pay { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: .4rem; }
    .pos-pay button {
        border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; padding: .5rem .4rem;
        font-size: .8rem; display: flex; flex-direction: column; align-items: center; gap: .15rem; color: #374151;
    }
    .pos-pay button i { font-size: 1.15rem; }
    .pos-pay button.active { border-color: #198754; background: #ecfdf3; color: #146c43; box-shadow: 0 0 0 1px #198754 inset; }
    .pos-cash { margin-top: .6rem; }
    .pos-quick { display: flex; gap: .3rem; flex-wrap: wrap; margin-top: .4rem; }
    .pos-quick button { border: 1px solid #e5e7eb; background: #f9fafb; border-radius: 6px; font-size: .75rem; padding: .15rem .5rem; }
    .pos-change { margin-top: .45rem; font-weight: 700; font-size: .95rem; }

    .pos-checkout { padding: .75rem 1rem 1rem; position: sticky; bottom: 0; background: #fff; border-top: 1px solid #f1f2f4; }
    .pos-errors { list-style: none; padding: .45rem .6rem; margin: 0 0 .5rem; background: #fef2f2; border-radius: 6px; color: #b91c1c; font-size: .78rem; }
    .pos-errors li + li { margin-top: .2rem; }
    #pos-submit { font-weight: 700; }

    .pos-empty { text-align: center; color: #9ca3af; padding: 2.5rem 1rem; font-size: .85rem; }
    .pos-empty i { font-size: 1.8rem; display: block; margin-bottom: .35rem; }
    .pos-empty-sm { padding: 1rem; }
    .pos-empty-sm i { font-size: 1.3rem; }

    .pos-mobile-bar { display: none; }

    @media (max-width: 991.98px) {
        .pos-grid { grid-template-columns: 1fr; }
        .pos-ticket { position: static; max-height: none; }
        .pos-checkout { position: static; }
        .pos-mobile-bar {
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; bottom: 0; margin: 1rem -16px -20px; padding: .6rem 16px;
            background: #fff; box-shadow: 0 -4px 14px rgba(0, 0, 0, .08); z-index: 30;
        }
        .pos-items { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/pos.js') }}?v={{ @filemtime(public_path('js/pos.js')) }}"></script>
@endpush
@endif
