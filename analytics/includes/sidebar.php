<?php
$activePage = $activePage ?? '';
$_initials  = strtoupper(substr($user['display_name'], 0, 1));
function _nav($href, $page, $active, $icon, $label) {
    $cls = $page === $active ? ' active' : '';
    echo "<a href=\"$href\" class=\"nav-link$cls\" title=\"$label\">"
       . "<svg viewBox=\"0 0 24 24\">$icon</svg>"
       . "<span class=\"nav-label\">$label</span>"
       . "</a>";
}
?>
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
    <div class="logo-text-wrap">
      <div class="logo-text"><span>digi</span>Mind</div>
      <div class="logo-sub">AI assistant</div>
    </div>
    <button class="sb-toggle" id="sbToggle" onclick="toggleSidebar()" title="Réduire / agrandir">
      <span class="ico-collapse"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="6"/><line x1="18" y1="12" x2="6" y2="12"/><line x1="18" y1="18" x2="6" y2="18"/></svg></span>
      <span class="ico-expand"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="6"/><line x1="18" y1="12" x2="6" y2="12"/><line x1="18" y1="18" x2="6" y2="18"/></svg></span>
    </button>
  </div>
  <div class="sidebar-pharmacy"><?= htmlspecialchars($user['pharmacy_name']) ?></div>
  <nav>
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
    <?php _nav('/analytics/suppliers.php', 'suppliers', $activePage,
      '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
      'Fournisseurs'); ?>
    <?php _nav('/analytics/orders.php', 'orders', $activePage,
      '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
      'Commandes'); ?>
    <?php _nav('/analytics/sync.php', 'sync', $activePage,
      '<polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/>',
      'Synchronisation'); ?>
    <?php _nav('/analytics/settings.php', 'settings', $activePage,
      '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
      'Paramètres'); ?>
    <?php _nav('/analytics/account.php', 'account', $activePage,
      '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      'Mon compte'); ?>
  </nav>
  <div class="sidebar-footer">
    <div class="avatar"><?= $_initials ?></div>
    <div class="avatar-info">
      <div class="avatar-name"><?= htmlspecialchars($user['display_name']) ?></div>
      <div class="avatar-role"><?= $user['role'] === 'admin' ? 'Administrateur' : 'Lecteur' ?></div>
    </div>
    <a href="/analytics/logout.php" class="logout-btn" title="Déconnexion">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </a>
  </div>
</aside>

<script>
(function () {
  var KEY = 'digimind_sb_col';
  if (localStorage.getItem(KEY) === '1') {
    document.body.classList.add('sb-col');
  }
  window.toggleSidebar = function () {
    var collapsed = document.body.classList.toggle('sb-col');
    localStorage.setItem(KEY, collapsed ? '1' : '0');
  };
  // Mobile open/close (called from each page's hamburger button)
  window.openSidebar  = function () {
    document.getElementById('sidebarOverlay').classList.add('open');
    document.getElementById('mainSidebar').classList.add('open');
  };
  window.closeSidebar = function () {
    document.getElementById('sidebarOverlay').classList.remove('open');
    document.getElementById('mainSidebar').classList.remove('open');
  };
}());
</script>
