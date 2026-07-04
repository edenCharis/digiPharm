<?php
// $activePage must be set before including (e.g., 'dashboard', 'trends', 'inventory', 'alerts', 'sync', 'settings')
$activePage = $activePage ?? '';
$_initials  = strtoupper(substr($user['display_name'], 0, 1));
function _nav($href, $page, $active, $icon, $label) {
    $cls = $page === $active ? ' active' : '';
    echo "<a href=\"$href\" class=\"nav-link$cls\"><svg viewBox=\"0 0 24 24\">$icon</svg>$label</a>";
}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
    <div class="logo-text">DigiPharm<span> AI</span></div>
  </div>
  <div class="sidebar-pharmacy"><?= htmlspecialchars($user['pharmacy_name']) ?></div>
  <nav>
    <div class="nav-section">Analyse</div>
    <?php _nav('/analytics/', 'dashboard', $activePage,
      '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
      'Vue d\'ensemble'); ?>
    <?php _nav('/analytics/trends.php', 'trends', $activePage,
      '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
      'Tendances'); ?>
    <?php _nav('/analytics/inventory.php', 'inventory', $activePage,
      '<path d="M21 16V8l-9-5-9 5v8l9 5 9-5z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
      'Inventaire'); ?>
    <?php _nav('/analytics/alerts.php', 'alerts', $activePage,
      '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
      'Alertes'); ?>
    <div class="nav-section" style="margin-top:8px">Données</div>
    <?php _nav('/analytics/sync.php', 'sync', $activePage,
      '<polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/>',
      'Synchronisation'); ?>
    <?php _nav('/analytics/settings.php', 'settings', $activePage,
      '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
      'Paramètres'); ?>
  </nav>
  <div class="sidebar-footer">
    <div class="avatar"><?= $_initials ?></div>
    <div class="avatar-info">
      <div class="avatar-name"><?= htmlspecialchars($user['display_name']) ?></div>
      <div class="avatar-role"><?= $user['role'] === 'admin' ? 'Administrateur' : 'Lecteur' ?></div>
    </div>
    <a href="/analytics/logout.php" class="logout-btn" title="Déconnexion">✕</a>
  </div>
</aside>
