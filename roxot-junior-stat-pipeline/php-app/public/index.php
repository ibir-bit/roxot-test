<?php

declare(strict_types=1);

use App\Database;
use App\Repository\PlacementRepository;
use App\Repository\StatsRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
}

function htmlResponse(string $html, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}

function renderDashboard(): string
{
    return <<<'HTML'
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mini Stat Pipeline</title>
  <style>
    :root {
      color-scheme: light;
      --bg: #f4f6f8;
      --surface: #ffffff;
      --surface-muted: #eef2f6;
      --border: #d8dee6;
      --text: #17202a;
      --muted: #667085;
      --accent: #1473e6;
      --danger: #b42318;
      --ok: #067647;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font: 14px/1.45 Arial, Helvetica, sans-serif;
    }

    .page {
      max-width: 1180px;
      margin: 0 auto;
      padding: 28px 20px 40px;
    }

    .header {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 22px;
    }

    h1 {
      margin: 0;
      font-size: 28px;
      line-height: 1.2;
      font-weight: 700;
    }

    .subtitle {
      margin: 6px 0 0;
      color: var(--muted);
      font-size: 14px;
    }

    .toolbar {
      display: grid;
      grid-template-columns: 180px minmax(260px, 1fr) auto;
      gap: 12px;
      align-items: end;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 16px;
    }

    label {
      display: grid;
      gap: 6px;
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
    }

    input,
    select,
    button {
      min-height: 38px;
      border-radius: 6px;
      font: inherit;
    }

    input,
    select {
      width: 100%;
      border: 1px solid var(--border);
      background: #fff;
      color: var(--text);
      padding: 8px 10px;
    }

    button {
      border: 1px solid var(--accent);
      background: var(--accent);
      color: #fff;
      padding: 8px 14px;
      cursor: pointer;
      font-weight: 700;
    }

    button:disabled {
      cursor: wait;
      opacity: .65;
    }

    .summary {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 16px;
    }

    .metric {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 14px 16px;
    }

    .metric__label {
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
    }

    .metric__value {
      margin-top: 5px;
      font-size: 24px;
      line-height: 1.1;
      font-weight: 700;
    }

    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      overflow: hidden;
    }

    .panel__bar {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 16px;
      border-bottom: 1px solid var(--border);
      background: var(--surface-muted);
      color: var(--muted);
    }

    .api-link {
      color: var(--accent);
      overflow-wrap: anywhere;
      text-decoration: none;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 12px 16px;
      border-bottom: 1px solid var(--border);
      text-align: left;
      vertical-align: middle;
      white-space: nowrap;
    }

    th {
      color: var(--muted);
      font-size: 12px;
      text-transform: uppercase;
      background: #fafbfc;
    }

    td.numeric {
      text-align: right;
      font-variant-numeric: tabular-nums;
    }

    .status {
      margin: 0 0 12px;
      min-height: 20px;
      color: var(--muted);
    }

    .status--error {
      color: var(--danger);
    }

    .status--ok {
      color: var(--ok);
    }

    .empty {
      padding: 28px 16px;
      color: var(--muted);
      text-align: center;
    }

    @media (max-width: 760px) {
      .header,
      .toolbar {
        display: grid;
        grid-template-columns: 1fr;
      }

      .summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .panel {
        overflow-x: auto;
      }
    }
  </style>
</head>
<body>
  <main class="page">
    <header class="header">
      <div>
        <h1>Mini Stat Pipeline</h1>
        <p class="subtitle">Daily stats dashboard</p>
      </div>
    </header>

    <form class="toolbar" id="filters">
      <label>
        Date
        <input id="date" name="date" type="date" value="2026-08-07">
      </label>
      <label>
        Placement
        <select id="placement" name="placement_id">
          <option value="">All placements</option>
        </select>
      </label>
      <button id="refresh" type="submit">Refresh</button>
    </form>

    <p class="status" id="status"></p>

    <section class="summary" aria-label="Totals">
      <div class="metric">
        <div class="metric__label">Rows</div>
        <div class="metric__value" id="totalRows">0</div>
      </div>
      <div class="metric">
        <div class="metric__label">Impressions</div>
        <div class="metric__value" id="totalImpressions">0</div>
      </div>
      <div class="metric">
        <div class="metric__label">Clicks</div>
        <div class="metric__value" id="totalClicks">0</div>
      </div>
      <div class="metric">
        <div class="metric__label">Revenue</div>
        <div class="metric__value" id="totalRevenue">0.00</div>
      </div>
    </section>

    <section class="panel">
      <div class="panel__bar">
        <span>Daily stats</span>
        <a class="api-link" id="apiLink" href="/api/daily-stats">/api/daily-stats</a>
      </div>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Placement ID</th>
            <th>Code</th>
            <th>Format</th>
            <th>Domain</th>
            <th>Impressions</th>
            <th>Clicks</th>
            <th>Revenue</th>
          </tr>
        </thead>
        <tbody id="statsBody">
          <tr><td colspan="8" class="empty">Loading...</td></tr>
        </tbody>
      </table>
    </section>
  </main>

  <script>
    const state = {
      placements: [],
      stats: [],
    };

    const elements = {
      form: document.getElementById('filters'),
      date: document.getElementById('date'),
      placement: document.getElementById('placement'),
      refresh: document.getElementById('refresh'),
      status: document.getElementById('status'),
      apiLink: document.getElementById('apiLink'),
      body: document.getElementById('statsBody'),
      totalRows: document.getElementById('totalRows'),
      totalImpressions: document.getElementById('totalImpressions'),
      totalClicks: document.getElementById('totalClicks'),
      totalRevenue: document.getElementById('totalRevenue'),
    };

    elements.form.addEventListener('submit', (event) => {
      event.preventDefault();
      loadStats();
    });

    bootstrap();

    async function bootstrap() {
      await loadPlacements();
      await loadStats();
    }

    async function loadPlacements() {
      try {
        const payload = await fetchJSON('/api/placements');
        state.placements = payload.items || [];

        for (const placement of state.placements) {
          const option = document.createElement('option');
          option.value = placement.id;
          option.textContent = `${placement.id} (${placement.format})`;
          elements.placement.appendChild(option);
        }
      } catch (error) {
        setStatus(error.message, true);
      }
    }

    async function loadStats() {
      setStatus('Loading...');
      elements.refresh.disabled = true;

      const query = new URLSearchParams();
      query.set('date', elements.date.value);

      if (elements.placement.value) {
        query.set('placement_id', elements.placement.value);
      }

      const url = `/api/daily-stats?${query.toString()}`;
      elements.apiLink.href = url;
      elements.apiLink.textContent = url;

      try {
        const payload = await fetchJSON(url);
        state.stats = payload.items || [];
        renderStats();
        setStatus(`Loaded ${state.stats.length} row(s)`, false, true);
      } catch (error) {
        renderStats([]);
        setStatus(error.message, true);
      } finally {
        elements.refresh.disabled = false;
      }
    }

    async function fetchJSON(url) {
      const response = await fetch(url, {headers: {'Accept': 'application/json'}});
      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(payload.message || payload.error || `HTTP ${response.status}`);
      }

      return payload;
    }

    function renderStats() {
      elements.body.innerHTML = '';

      if (!state.stats.length) {
        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="8" class="empty">No rows</td>';
        elements.body.appendChild(row);
        renderTotals();
        return;
      }

      for (const item of state.stats) {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${escapeHTML(item.stat_date || '')}</td>
          <td>${escapeHTML(item.placement_id || '')}</td>
          <td>${escapeHTML(item.placement_code || '')}</td>
          <td>${escapeHTML(item.format || '')}</td>
          <td>${escapeHTML(item.domain || '')}</td>
          <td class="numeric">${formatInt(item.impressions)}</td>
          <td class="numeric">${formatInt(item.clicks)}</td>
          <td class="numeric">${formatMoney(item.revenue)}</td>
        `;
        elements.body.appendChild(row);
      }

      renderTotals();
    }

    function renderTotals() {
      const totals = state.stats.reduce((acc, item) => {
        acc.impressions += Number(item.impressions || 0);
        acc.clicks += Number(item.clicks || 0);
        acc.revenue += Number(item.revenue || 0);
        return acc;
      }, {impressions: 0, clicks: 0, revenue: 0});

      elements.totalRows.textContent = String(state.stats.length);
      elements.totalImpressions.textContent = formatInt(totals.impressions);
      elements.totalClicks.textContent = formatInt(totals.clicks);
      elements.totalRevenue.textContent = formatMoney(totals.revenue);
    }

    function setStatus(message, isError = false, isOk = false) {
      elements.status.textContent = message;
      elements.status.className = `status${isError ? ' status--error' : ''}${isOk ? ' status--ok' : ''}`;
    }

    function formatInt(value) {
      return new Intl.NumberFormat('en-US').format(Number(value || 0));
    }

    function formatMoney(value) {
      return Number(value || 0).toFixed(2);
    }

    function escapeHTML(value) {
      return String(value).replace(/[&<>"']/g, (char) => {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
      });
    }
  </script>
</body>
</html>
HTML;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET' && $path === '/') {
        htmlResponse(renderDashboard());
        return;
    }

    $pdo = Database::connect();

    if ($method === 'GET' && $path === '/health') {
        jsonResponse(['status' => 'ok']);
        return;
    }

    if ($method === 'GET' && $path === '/api/placements') {
        $placements = (new PlacementRepository($pdo))->findAll();
        jsonResponse(['items' => $placements]);
        return;
    }

    if ($method === 'GET' && $path === '/api/daily-stats') {
        $stats = (new StatsRepository($pdo))->findDailyStats($_GET);
        jsonResponse(['items' => $stats]);
        return;
    }

    jsonResponse(['error' => 'not_found'], 404);
} catch (Throwable $exception) {
    jsonResponse([
        'error' => 'internal_error',
        'message' => $exception->getMessage(),
    ], 500);
}
