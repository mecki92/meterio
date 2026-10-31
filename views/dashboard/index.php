<?php
/** @var array $meters @var ?array $sel @var array $cards @var array $years @var ?array $activeContract @var ?array $last */
?>
<div class="page-head">
    <div><h1>Dashboard</h1>
        <?php if ($sel): ?><p class="sub"><?= e($sel['name']) ?><?= $last ? ' · Letzte Ablesung ' . e(date('d.m.Y', $last['ts'])) : ' · noch keine Ablesung' ?></p><?php endif; ?></div>
    <?php if ($sel): ?>
    <div class="actions">
        <?php if (count($meters) > 1): ?>
        <form method="get" action="<?= e(url('dashboard')) ?>" class="m-0">
            <select name="meter" class="form-select" aria-label="Zähler" data-autosubmit>
                <?php foreach ($meters as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $sel['id'] === $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('meters/' . $sel['id'] . '/readings/new')) ?>"><i class="bi bi-plus-lg"></i> Ablesung erfassen</a>
    </div>
    <?php endif; ?>
</div>

<?php foreach ($expiring ?? [] as $x): $url = e(url('meters/' . $x['meter_id'] . ($x['role'] === 'owner' ? '#freigabe' : ''))); ?>
    <div class="alert alert-<?= $x['expired'] ? 'danger' : 'warning' ?> d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="bi bi-hourglass-split"></i>
        <?php if ($x['role'] === 'owner'): ?>
            Die Freigabe von <b><?= e($x['meter']) ?></b> für <b><?= e($x['other']) ?></b> <?= $x['expired'] ? 'ist am ' . e(fmt_date($x['until'])) . ' abgelaufen.' : 'läuft in ' . (int) $x['days'] . ' Tag' . ($x['days'] === 1 ? '' : 'en') . ' ab (' . e(fmt_date($x['until'])) . ').' ?>
        <?php else: ?>
            Dein Zugriff auf <b><?= e($x['meter']) ?></b> (von <?= e($x['other']) ?>) endet in <?= (int) $x['days'] ?> Tag<?= $x['days'] === 1 ? '' : 'en' ?> (<?= e(fmt_date($x['until'])) ?>).
        <?php endif; ?></span>
        <?php if ($x['role'] === 'owner'): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $url ?>">Verlängern / verwalten</a><?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if (!$meters): ?>
    <div class="card"><div class="card-body p-4">
        <p class="fs-5">Willkommen bei <b>meterio</b>! So geht’s los:</p>
        <ol class="mb-0">
            <li><a href="<?= e(url('meters/new')) ?>">Zähler anlegen</a> (analog oder digital mit OBIS-Registern)</li>
            <li><a href="<?= e(url('contracts/new')) ?>">Stromvertrag</a> mit Preisen und Abschlag hinterlegen</li>
            <li>Regelmäßig Zählerstände samt Foto erfassen – meterio rechnet Verbrauch, Kosten und Prognose aus.</li>
        </ol>
    </div></div>
<?php else: ?>
    <?php if (!$cards): ?>
        <div class="alert alert-info">Für diesen Zähler ist kein aktiver Vertrag hinterlegt – <a href="<?= e(url('contracts/new?meter=' . $sel['id'])) ?>">Vertrag anlegen</a> oder unter <a href="<?= e(url('contracts')) ?>">Verträge</a> prüfen.</div>
    <?php endif; ?>

    <?php foreach ($cards as ['c' => $c, 'sum' => $sum]): ?>
        <div class="card mb-3">
            <div class="card-header split"><a class="text-decoration-none" href="<?= e(url('contracts/' . $c['id'])) ?>"><i class="bi bi-file-earmark-text"></i> <?= e($c['data']['provider'] ?: 'Vertrag #' . $c['id']) ?></a>
                <span class="small fw-normal text-body-secondary"><?= e(fmt_date($c['start_date'])) ?> – <?= $c['end_date'] ? e(fmt_date($c['end_date'])) : 'unbefristet' ?><?= $c['data']['contract_no'] !== '' ? ' · Nr. ' . e($c['data']['contract_no']) : '' ?></span></div>
            <div class="card-body"><?php require ROOT . '/views/contracts/_summary.php'; ?></div>
        </div>
    <?php endforeach; ?>

    <div class="row g-3">
    <div class="col-xl-6"><div class="card h-100">
        <div class="card-header split"><span><i class="bi bi-bar-chart-line"></i> Verbrauch</span>
            <div class="chart-ctl" id="usagectl"
                 data-api="<?= e(url('api/chart')) ?>" data-meter="<?= (int) $sel['id'] ?>"
                 data-contract="<?= (int) ($activeContract['id'] ?? 0) ?>"
                 data-years="<?= e(json_encode($activeContract ? ($years[$activeContract['id']] ?? []) : [])) ?>">
                <div class="btn-group" role="group">
                    <?php if ($activeContract): ?><button type="button" data-range="contract" class="btn btn-primary btn-sm"><?= e(period_word($activeContract)) ?></button><?php endif; ?>
                    <button type="button" data-range="year" class="btn <?= $activeContract ? 'btn-outline-primary' : 'btn-primary' ?> btn-sm">Jahr</button>
                </div>
                <select id="refyear" class="form-select form-select-sm w-auto" aria-label="Jahr"<?= $activeContract ? ' hidden' : '' ?>>
                    <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 6; $y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?>
                </select>
                <select id="refcy" class="form-select form-select-sm w-auto" aria-label="<?= $activeContract ? e(period_word($activeContract)) : 'Vertragsjahr' ?>"<?= $activeContract ? '' : ' hidden' ?>></select>
            </div>
        </div>
        <div class="card-body">
            <h3 id="usagetitle" class="h6 fw-normal text-body-secondary mb-2"></h3>
            <div class="usage-stats" id="usagestats"></div>
            <div class="chartbox"><canvas id="usageChart" aria-label="Verbrauchsdiagramm" role="img"></canvas></div>
            <p class="small text-body-secondary mt-2 mb-0" id="usageempty" hidden>Für diesen Zeitraum liegen noch keine Ablesedaten vor.</p>
        </div>
    </div></div>

    <?php if ($activeContract): ?>
    <div class="col-xl-6"><div class="card h-100">
        <div class="card-header split"><span><i class="bi bi-graph-up"></i> Soll-Ist: Realkosten vs. Abschläge</span>
            <div class="chart-ctl" id="costctl" data-api="<?= e(url('api/chart')) ?>" data-contract="<?= (int) $activeContract['id'] ?>"
                 data-years="<?= e(json_encode($years[$activeContract['id']] ?? [])) ?>">
                <select id="costcy" class="form-select form-select-sm w-auto" aria-label="<?= $activeContract ? e(period_word($activeContract)) : 'Vertragsjahr' ?>"></select>
            </div>
        </div>
        <div class="card-body">
            <h3 id="costtitle" class="h6 fw-normal text-body-secondary mb-3"></h3>
            <div class="chartbox"><canvas id="costChart" aria-label="Kostendiagramm" role="img"></canvas></div>
        </div>
    </div></div>
    <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js" defer></script>
<?php endif; ?>
