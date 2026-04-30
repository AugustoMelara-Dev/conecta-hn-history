<div class="biz-card-accent">
    <div class="biz-grid-two">
        <div class="biz-stack">
            <div class="biz-badge biz-badge-success">Superadmin</div>

            <div>
                <p class="biz-kicker">Torre de control multi-negocio</p>
                <h1 class="biz-title">Colas, SLA, churn y seguimiento entre tenants</h1>
                <p class="biz-copy">
                    Opera Vito Business OS como una consola de ejecucion real: pagos, altas,
                    playbooks comerciales, renovaciones y riesgo de churn en un solo frente.
                </p>
            </div>

            <div class="biz-button-row">
                <a href="{{ $operationsUrl }}" class="biz-button">Mesa operativa</a>
                <a href="{{ $tenantsUrl }}" class="biz-button">Ver negocios</a>
                <a href="{{ $paymentsUrl }}" class="biz-button-secondary">Revisar pagos</a>
                <a href="{{ $billingUrl }}" class="biz-button-secondary">Facturacion</a>
                <a href="{{ $settingsUrl }}" class="biz-button-secondary">Ajustar sistema</a>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-stat-label">Pulso de hoy</p>
                    <p class="biz-stat-value">{{ number_format($openAttentionCount) }}</p>
                    <p class="biz-stat-copy">
                        frentes abiertos entre pagos, activaciones, churn y operaciones vivas.
                    </p>
                </div>

                <div class="biz-metrics">
                    <div class="biz-stat">
                        <p class="biz-stat-label">Playbooks activos</p>
                        <p class="biz-stat-value">{{ number_format($activePlaybookCount) }}</p>
                        <p class="biz-stat-copy">Seguimientos vivos entre tenants</p>
                    </div>

                    <div class="biz-stat">
                        <p class="biz-stat-label">SLA vencido</p>
                        <p class="biz-stat-value">{{ number_format($overduePlaybookCount) }}</p>
                        <p class="biz-stat-copy">Casos que ya piden escalacion</p>
                    </div>

                    <div class="biz-stat">
                        <p class="biz-stat-label">Sin owner interno</p>
                        <p class="biz-stat-value">{{ number_format($unassignedPlaybookCount) }}</p>
                        <p class="biz-stat-copy">Casos que todavia no tienen dueno comercial interno</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
