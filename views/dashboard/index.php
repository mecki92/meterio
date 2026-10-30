<?php
/** @var array $meters @var ?array $sel @var array $cards @var array $years @var ?array $activeContract @var ?array $last */
?>
<div class="pagehead">
    <h2>Dashboard</h2>
    <?php if ($sel): ?><a role="button" href="<?= e(url('meters/' . $sel['id'] . '/readings/new')) ?>">+ Ablesung erfassen</a><?php endif; ?>
</div>

<?php if (!$meters): ?>
    <article>
        <p>Willkommen bei <b>meterio</b>! So geht’s los:</p>
        <ol>
            <li><a href="<?= e(url('meters/new')) ?>">Zähler anlegen</a> und Register (OBIS) definieren</li>
            <li><a href="<?= e(url('contracts/new')) ?>">Stromvertrag</a> mit Preisen und Abschlag hinterlegen</li>
            <li>Regelmäßig Zählerstände samt Foto erfassen – meterio rechnet Verbrauch, Kosten und Prognose aus.</li>
        </ol>
    </article>
<?php else: ?>
    <form method="get" action="<?= e(url('dashboard')) ?>" class="metersel">
        <label>Zähler
            <select name="meter" data-autosubmit>
                <?php foreach ($meters as $m): ?><option value="<?= (int) $m['id'] ?>" <?= $sel && $sel['id'] === $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php if ($last): ?><small class="muted">Letzte Ablesung: <?= e(date('d.m.Y H:i', $last['ts'])) ?></small><?php endif; ?>
    </form>

    <?php if (!$cards): ?>
        <article><p>Für diesen Zähler ist noch kein Vertrag hinterlegt – <a href="<?= e(url('contracts/new?meter=' . $sel['id'])) ?>">jetzt Vertrag anlegen</a>, um Kosten und Prognose zu sehen.</p></article>
    <?php endif; ?>

    <?php foreach ($cards as ['c' => $c, 'sum' => $sum]): ?>
        <article>
            <header><a href="<?= e(url('contracts/' . $c['id'])) ?>"><b><?= e($c['data']['provider'] ?: 'Vertrag #' . $c['id']) ?></b></a>
                <span class="small muted"> · <?= e(fmt_date($c['start_date'])) ?> – <?= $c['end_date'] ? e(fmt_date($c['end_date'])) : 'unbefristet' ?></span></header>
            <?php require ROOT . '/views/contracts/_summary.php'; ?>
        </article>
    <?php endforeach; ?>

    <article>
        <header>Verbrauch</header>
        <div class="chartctl" id="usagectl"
             data-api="<?= e(url('api/chart')) ?>" data-meter="<?= (int) $sel['id'] ?>"
             data-contract="<?= (int) ($activeContract['id'] ?? 0) ?>"
             data-years="<?= e(json_encode($activeContract ? ($years[$activeContract['id']] ?? []) : [])) ?>">
            <div role="group">
                <button type="button" data-range="month" class="secondary outline">Monat</button>
                <button type="button" data-range="year" class="secondary">Jahr</button>
                <?php if ($activeContract): ?><button type="button" data-range="contract" class="secondary outline">Vertragsjahr</button><?php endif; ?>
            </div>
            <input type="month" id="refmonth" value="<?= e(date('Y-m')) ?>" hidden>
            <select id="refyear" aria-label="Jahr">
                <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 6; $y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?>
            </select>
            <select id="refcy" aria-label="Vertragsjahr" hidden></select>
        </div>
        <h4 id="usagetitle" class="charttitle"></h4>
        <div class="chartbox"><canvas id="usageChart" aria-label="Verbrauchsdiagramm" role="img"></canvas></div>
        <p class="small muted" id="usageempty" hidden>Für diesen Zeitraum liegen noch keine Ablesedaten vor.</p>
    </article>

    <?php if ($activeContract): ?>
    <article>
        <header>Soll-Ist: Realkosten vs. Abschläge</header>
        <div class="chartctl" id="costctl" data-api="<?= e(url('api/chart')) ?>" data-contract="<?= (int) $activeContract['id'] ?>"
             data-years="<?= e(json_encode($years[$activeContract['id']] ?? [])) ?>">
            <select id="costcy" aria-label="Vertragsjahr"></select>
        </div>
        <h4 id="costtitle" class="charttitle"></h4>
        <div class="chartbox"><canvas id="costChart" aria-label="Kostendiagramm" role="img"></canvas></div>
    </article>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js" defer></script>
<?php endif; ?>
