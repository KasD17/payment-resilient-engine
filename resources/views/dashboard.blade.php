<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Resilient Engine - Fintech Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans antialiased selection:bg-indigo-500 selection:text-white"
      x-data="fintechApp()">

    <!-- Top Navigation Bar -->
    <header class="border-b border-slate-800 bg-slate-900/60 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                </div>
                <div>
                    <h1 class="text-base font-bold tracking-tight text-white flex items-center gap-2">
                        Payment Resilient Engine
                        <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">Production Ready</span>
                    </h1>
                    <p class="text-xs text-slate-400">Fintech & E-Commerce Fault-Tolerant Architecture</p>
                </div>
            </div>

            <div class="flex items-center space-x-3 text-xs">
                <span class="hidden md:inline-flex items-center px-2.5 py-1 rounded-md bg-slate-800 text-slate-300 font-mono">PHP 8.3</span>
                <span class="hidden md:inline-flex items-center px-2.5 py-1 rounded-md bg-slate-800 text-slate-300 font-mono">Laravel 11/12</span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-mono">RFC 9457 Idempotency</span>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Metrics Overview Grid -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden group hover:border-indigo-500/40 transition">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Volumen Procesado</div>
                <div class="text-2xl font-bold text-white mt-2 font-mono">${{ number_format($stats['total_volume'] / 100, 2) }}</div>
                <div class="text-xs text-emerald-400 mt-1 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Liquidado en centavos
                </div>
            </div>

            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden group hover:border-indigo-500/40 transition">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Cobros Exitosos</div>
                <div class="text-2xl font-bold text-white mt-2 font-mono">{{ $stats['successful_payments'] }}</div>
                <div class="text-xs text-slate-400 mt-1">Transacciones ACID</div>
            </div>

            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden group hover:border-indigo-500/40 transition">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Suscripciones Activas</div>
                <div class="text-2xl font-bold text-indigo-400 mt-2 font-mono">{{ $stats['active_subscriptions'] }}</div>
                <div class="text-xs text-slate-400 mt-1">Ciclo recurrente</div>
            </div>

            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden group hover:border-indigo-500/40 transition">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Webhooks Recibidos</div>
                <div class="text-2xl font-bold text-white mt-2 font-mono">{{ $stats['webhook_events'] }}</div>
                <div class="text-xs text-slate-400 mt-1">&lt; 50ms ACK</div>
            </div>

            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 relative overflow-hidden group hover:border-indigo-500/40 transition">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Dead Letter Queue (DLQ)</div>
                <div class="text-2xl font-bold text-white mt-2 font-mono" :class="dlqCount > 0 ? 'text-amber-400' : 'text-slate-300'">
                    <span x-text="dlqCount">{{ $stats['dlq_failed'] }}</span>
                </div>
                <div class="text-xs text-slate-400 mt-1">Eventos aislados</div>
            </div>
        </section>

        <!-- Main Interactive Split Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- Left: Interactive Idempotency & Payment Sandbox (7 cols) -->
            <section class="lg:col-span-7 bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-xl">
                <div class="flex items-center justify-between pb-5 border-b border-slate-800 mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <span>🛡️</span> Simulador de Idempotencia en Vivo
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Demostración interactiva anti-doble cobro y detección de fraude</p>
                    </div>
                    <span class="text-xs font-mono px-3 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-lg">
                        Header: Idempotency-Key
                    </span>
                </div>

                <div class="space-y-4">
                    <!-- Form Controls -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Cliente Pagador</label>
                            <select x-model="form.customer_id" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->email }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1.5">Monto de Cobro (USD)</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 text-xs text-slate-500">$</span>
                                <input type="number" step="0.50" x-model="form.amount" class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-8 pr-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>
                    </div>

                    <!-- Idempotency Key Bar -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-xs font-medium text-slate-300">Clave de Idempotencia (UUID v4)</label>
                            <button @click="generateNewKey()" type="button" class="text-xs text-indigo-400 hover:text-indigo-300 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Generar nueva
                            </button>
                        </div>
                        <input type="text" x-model="form.idempotency_key" readonly class="w-full bg-slate-950 border border-slate-800 text-indigo-400 font-mono text-xs rounded-xl px-3.5 py-2.5 focus:outline-none">
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                        <button @click="executeCharge('normal')" :disabled="loading" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs py-3 px-4 rounded-xl shadow-lg shadow-indigo-600/20 flex items-center justify-center gap-2 transition disabled:opacity-50">
                            <span x-show="!loading">💳 Cobro Normal</span>
                            <span x-show="loading" class="animate-spin text-xs">🌀</span>
                        </button>

                        <button @click="executeCharge('replay')" :disabled="loading" class="w-full bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-400 border border-emerald-500/30 font-semibold text-xs py-3 px-4 rounded-xl flex items-center justify-center gap-1.5 transition disabled:opacity-50">
                            <span>🔁 Simular Doble Clic</span>
                        </button>

                        <button @click="executeCharge('tamper')" :disabled="loading" class="w-full bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 border border-rose-500/30 font-semibold text-xs py-3 px-4 rounded-xl flex items-center justify-center gap-1.5 transition disabled:opacity-50">
                            <span>⚠️ Alterar Monto</span>
                        </button>
                    </div>

                    <!-- Live Terminal Output Console -->
                    <div class="mt-6 pt-4 border-t border-slate-800/80">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-mono text-slate-400 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full" :class="consoleStatus === 201 || consoleStatus === 200 ? 'bg-emerald-400' : (consoleStatus ? 'bg-rose-400' : 'bg-slate-600')"></span>
                                Consola de Respuesta API
                            </span>
                            <div class="flex items-center gap-2">
                                <span x-show="cachedHit" class="text-xs px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-mono font-bold animate-pulse">
                                    X-Cache-Lookup: HIT (Zero Double-Charges)
                                </span>
                                <span x-show="consoleStatus" class="text-xs font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-300 font-bold" x-text="'HTTP ' + consoleStatus"></span>
                            </div>
                        </div>

                        <div class="bg-slate-950 border border-slate-800/90 rounded-2xl p-4 font-mono text-xs text-slate-300 overflow-x-auto max-h-52">
                            <pre x-text="consoleOutput || 'Esperando ejecución de cobro... Haz clic en uno de los 3 botones superiores para probar en vivo.'"></pre>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Right: Webhooks & DLQ Operator (5 cols) -->
            <section class="lg:col-span-5 space-y-6">
                <!-- Webhook Simulator Box -->
                <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 shadow-xl">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-4">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>⚡</span> Pipeline de Webhooks
                        </h3>
                        <span class="text-xs px-2.5 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">Async Queues</span>
                    </div>

                    <p class="text-xs text-slate-400 mb-4 leading-relaxed">
                        Dispara eventos simulados de pasarela bancaria. Se valida la firma HMAC, se guarda en <code class="text-indigo-300">webhook_events</code> en &lt;50ms y se procesa en cola generando la factura PDF.
                    </p>

                    <div class="space-y-3">
                        <button @click="triggerWebhook('invoice.payment_succeeded')" :disabled="webhookLoading" class="w-full bg-slate-800 hover:bg-slate-700 text-slate-100 font-medium text-xs py-2.5 px-4 rounded-xl border border-slate-700 flex items-center justify-between transition">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                                Disparar <code class="text-indigo-300">invoice.payment_succeeded</code>
                            </span>
                            <span x-show="!webhookLoading">Disparar &rarr;</span>
                            <span x-show="webhookLoading" class="animate-spin text-xs">🌀</span>
                        </button>

                        <button @click="retryDlq()" :disabled="dlqLoading" class="w-full bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 font-medium text-xs py-2.5 px-4 rounded-xl border border-amber-500/30 flex items-center justify-between transition">
                            <span class="flex items-center gap-2">
                                <span>🛠️</span> Recuperar Dead Letter Queue (DLQ)
                            </span>
                            <span x-show="!dlqLoading">Ejecutar CLI &rarr;</span>
                            <span x-show="dlqLoading" class="animate-spin text-xs">🌀</span>
                        </button>
                    </div>
                </div>

                <!-- Webhooks Stream Table -->
                <div class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 shadow-xl">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Últimos Eventos Ingeridos</h3>
                    <div class="space-y-2.5 overflow-y-auto max-h-64 pr-1">
                        @forelse($webhookEvents as $event)
                            <div class="p-3 bg-slate-950/70 border border-slate-800/80 rounded-xl flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-mono font-semibold text-slate-200">{{ $event->event_type }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ $event->event_id }}</div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase font-mono
                                    @if($event->status->value === 'processed') bg-emerald-500/10 text-emerald-400 border border-emerald-500/20
                                    @elseif($event->status->value === 'failed') bg-rose-500/10 text-rose-400 border border-rose-500/20
                                    @else bg-amber-500/10 text-amber-400 border border-amber-500/20 @endif">
                                    {{ $event->status->value }}
                                </span>
                            </div>
                        @empty
                            <div class="text-xs text-slate-500 text-center py-4">No hay eventos registrados aún.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        <!-- Bottom Section: Transactions & Invoices Table -->
        <section class="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-xl">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
                <div>
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <span>🧾</span> Transacciones y Facturación Automatizada en PDF
                    </h3>
                    <p class="text-xs text-slate-400">Historial de cobros auditados y descarga de comprobantes en tiempo real</p>
                </div>
                <span class="text-xs font-mono text-slate-400">DomPDF Engine Active</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 font-mono uppercase text-[11px]">
                            <th class="py-3 px-4">Transacción</th>
                            <th class="py-3 px-4">Cliente</th>
                            <th class="py-3 px-4">Pasarela</th>
                            <th class="py-3 px-4">Monto</th>
                            <th class="py-3 px-4">Estado</th>
                            <th class="py-3 px-4">Fecha</th>
                            <th class="py-3 px-4 text-right">Factura PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-mono">
                        @forelse($payments as $payment)
                            <tr class="hover:bg-slate-800/20 transition">
                                <td class="py-3.5 px-4 font-bold text-slate-200">
                                    {{ $payment->gateway_transaction_id ?? substr($payment->id, 0, 13) . '...' }}
                                </td>
                                <td class="py-3.5 px-4 font-sans text-slate-300">
                                    {{ $payment->customer->name ?? 'N/A' }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-medium uppercase
                                        {{ $payment->gateway === 'stripe' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-sky-500/10 text-sky-400 border border-sky-500/20' }}">
                                        {{ $payment->gateway }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-white">
                                    {{ $payment->formattedAmount() }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase
                                        {{ $payment->status->value === 'succeeded' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                        {{ $payment->status->value }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-400">
                                    {{ $payment->created_at->format('M d, H:i') }}
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    @if($payment->invoices->isNotEmpty())
                                        <a href="{{ route('invoices.download', $payment->invoices->first()->id) }}"
                                           target="_blank"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600/20 text-indigo-300 hover:bg-indigo-600/30 border border-indigo-500/30 transition text-xs font-sans font-medium">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            Descargar PDF
                                        </a>
                                    @else
                                        <span class="text-slate-500 text-[11px]">Cobro directo</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-slate-500">No hay pagos registrados aún. Usa el simulador superior para crear el primero.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        function fintechApp() {
            return {
                loading: false,
                webhookLoading: false,
                dlqLoading: false,
                cachedHit: false,
                consoleStatus: null,
                consoleOutput: '',
                dlqCount: {{ $stats['dlq_failed'] }},
                form: {
                    customer_id: '{{ $customers->first()?->id ?? "00000000-0000-0000-0000-000000000001" }}',
                    amount: 50.00,
                    idempotency_key: '{{ (string) Str::uuid() }}',
                    payment_method_id: 'pm_card_visa'
                },

                generateNewKey() {
                    this.form.idempotency_key = crypto.randomUUID();
                    this.cachedHit = false;
                },

                async executeCharge(mode) {
                    this.loading = true;
                    this.cachedHit = false;

                    let targetAmount = Math.round(parseFloat(this.form.amount) * 100);

                    if (mode === 'tamper') {
                        // Tamper amount by changing to $999.00 with the exact same key
                        targetAmount = 99900;
                    }

                    const payload = {
                        customer_id: this.form.customer_id,
                        amount: targetAmount,
                        currency: 'USD',
                        payment_method_id: this.form.payment_method_id,
                        description: mode === 'tamper' ? 'Tampered Fraudulent Attempt' : 'Live Dashboard Charge'
                    };

                    try {
                        const response = await fetch('/api/v1/payments/charge', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'Idempotency-Key': this.form.idempotency_key
                            },
                            body: JSON.stringify(payload)
                        });

                        this.consoleStatus = response.status;
                        const replayHeader = response.headers.get('Idempotent-Replay');
                        const cacheHeader = response.headers.get('X-Cache-Lookup');
                        this.cachedHit = (replayHeader === 'true' || cacheHeader === 'HIT');

                        const json = await response.json();
                        this.consoleOutput = JSON.stringify(json, null, 2);

                        if (response.ok && mode === 'normal') {
                            // If normal payment created, reload table after 1s
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } catch (e) {
                        this.consoleStatus = 500;
                        this.consoleOutput = "Error de red: " + e.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async triggerWebhook(eventType) {
                    this.webhookLoading = true;
                    try {
                        const response = await fetch('/dashboard/simulate-webhook', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ event_type: eventType })
                        });
                        const data = await response.json();
                        alert('✅ ' + data.message);
                        window.location.reload();
                    } catch (e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.webhookLoading = false;
                    }
                },

                async retryDlq() {
                    this.dlqLoading = true;
                    try {
                        const response = await fetch('/dashboard/retry-dlq', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await response.json();
                        alert('✅ ' + data.message);
                        window.location.reload();
                    } catch (e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.dlqLoading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>
