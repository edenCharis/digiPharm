<?php
// $pageTitle must be set before including
$pageTitle = $pageTitle ?? 'digiMind';
?>
<div class="topbar">
  <div class="topbar-left">
    <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
    <div class="topbar-meta">
      <span class="status-dot" id="aiDot"></span>
      <span id="aiStatus">Chargement…</span>
    </div>
  </div>
  <div class="topbar-right">
    <select class="period-select" id="periodSelect" onchange="load()">
      <option value="7">7 jours</option>
      <option value="30" selected>30 jours</option>
      <option value="90">90 jours</option>
      <option value="180">6 mois</option>
    </select>
    <button class="refresh-btn" onclick="load()">
      <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
      Actualiser
    </button>
  </div>
</div>
