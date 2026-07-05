<?php /* Shared CSS for all analytics pages */ ?>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --green:#1a7f4b; --green-dk:#155e38; --green-lt:#e8f5ee; --green-bg:#f0faf4;
  --amber:#d97706; --amber-lt:#fef3c7;
  --red:#dc2626; --red-lt:#fee2e2;
  --blue:#2563eb; --blue-lt:#dbeafe;
  --border:#dadce0; --border-lt:#f0f0f0;
  --text:#111827; --text-2:#4b5563; --text-3:#9ca3af;
  --surface:#ffffff; --surface-alt:#f8f9fa; --bg:#f3f4f6;
  --sidebar-w:240px; --header-h:56px; --radius:10px;
}
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; overflow-x:hidden; }
.sidebar { width:var(--sidebar-w); min-height:100vh; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0;left:0;bottom:0; z-index:100; }
.sidebar-logo { padding:14px 16px; display:flex; align-items:center; gap:10px; border-bottom:1px solid var(--border-lt); }
.logo-text-wrap { flex:1; min-width:0; }
.logo-icon { width:32px;height:32px;background:var(--green);border-radius:7px;display:grid;place-items:center; }
.logo-icon svg { width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round; }
.logo-text { font-size:15px;font-weight:700;color:var(--text);letter-spacing:-.3px; }
.logo-text span { color:var(--green); }
.sidebar-pharmacy { padding:12px 20px;font-size:11px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--border-lt); }
nav { flex:1;padding:8px 0; }
.nav-section { padding:16px 20px 4px;font-size:10px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.6px; }
.nav-link { display:flex;align-items:center;gap:10px;padding:8px 14px;margin:1px 8px;border-radius:8px;font-size:13.5px;color:var(--text-2);text-decoration:none;transition:background .12s,color .12s; }
.nav-link svg { width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0; }
.nav-link:hover { background:var(--surface-alt);color:var(--text); }
.nav-link.active { background:var(--green-lt);color:var(--green-dk);font-weight:600; }
.sidebar-footer { padding:14px 16px;border-top:1px solid var(--border-lt);display:flex;align-items:center;gap:10px; }
.avatar { width:32px;height:32px;background:var(--green);border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0; }
.avatar-info { flex:1;min-width:0; }
.avatar-name { font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.avatar-role { font-size:11px;color:var(--text-3); }
.logout-btn { color:var(--text-3);text-decoration:none;display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;flex-shrink:0;transition:background .12s,color .12s; }
.logout-btn:hover { color:var(--red);background:var(--red-lt); }
.logout-btn svg { width:16px;height:16px; }
.main { margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh; }
.topbar { height:var(--header-h);background:var(--surface);border-bottom:1px solid var(--border);padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50; }
.topbar-left { display:flex;flex-direction:column; }
.topbar-title { font-size:16px;font-weight:700;color:var(--text); }
.topbar-meta { font-size:12px;color:var(--text-3); }
.topbar-right { display:flex;align-items:center;gap:10px; }
.period-select { padding:6px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text);background:var(--surface);cursor:pointer;outline:none; }
.refresh-btn { padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);cursor:pointer;color:var(--text-2);font-size:13px;display:flex;align-items:center;gap:5px; }
.refresh-btn svg { width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round; }
.content { padding:24px 28px;flex:1; }
.kpi-row { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px; }
.kpi { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px; }
.kpi-label { font-size:11.5px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px; }
.kpi-value { font-size:26px;font-weight:700;color:var(--text);font-variant-numeric:tabular-nums;line-height:1; }
.kpi-value.red { color:var(--red); } .kpi-value.amber { color:var(--amber); } .kpi-value.green { color:var(--green); }
.kpi-sub { font-size:12px;color:var(--text-3);margin-top:4px; }
.kpi-badge { display:inline-block;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;margin-top:6px; }
.kpi-badge.up { background:var(--green-lt);color:var(--green-dk); }
.kpi-badge.down { background:var(--red-lt);color:var(--red); }
.kpi-badge.flat { background:var(--border-lt);color:var(--text-3); }
.card { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius); }
.card-header { padding:14px 18px;border-bottom:1px solid var(--border-lt);display:flex;align-items:center;justify-content:space-between; }
.card-title { font-size:14px;font-weight:700;color:var(--text); }
.card-meta { font-size:12px;color:var(--text-3); }
.card-body { padding:18px; }
.tbl { width:100%;border-collapse:collapse;font-size:13px; }
.tbl th { text-align:left;padding:8px 12px;font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border); }
.tbl td { padding:10px 12px;border-bottom:1px solid var(--border-lt);color:var(--text-2); }
.tbl tr:last-child td { border-bottom:none; }
.tbl td:first-child { font-weight:500;color:var(--text); }
.tbl td.num { text-align:right;font-variant-numeric:tabular-nums; }
.tbl-empty { text-align:center;color:var(--text-3);padding:20px!important; }
.alert-item { display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-lt); }
.alert-item:last-child { border-bottom:none; }
.alert-dot { width:8px;height:8px;border-radius:50%;margin-top:5px;flex-shrink:0; }
.alert-dot.critical { background:var(--red); }
.alert-dot.warning  { background:var(--amber); }
.alert-dot.info     { background:var(--blue); }
.alert-name { font-size:13px;font-weight:500;color:var(--text); }
.alert-msg  { font-size:12px;color:var(--text-2); }
.severity-badge { display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600; }
.severity-badge.critical { background:var(--red-lt);color:var(--red); }
.severity-badge.warning  { background:var(--amber-lt);color:#92400e; }
.severity-badge.info     { background:var(--blue-lt);color:var(--blue); }
.notice { padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px; }
.notice.ok    { background:var(--green-lt);color:var(--green-dk);border:1px solid #bbf7d0; }
.notice.error { background:var(--red-lt);color:var(--red);border:1px solid #fecaca; }
.btn { padding:9px 18px;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;transition:background .15s; }
.btn-primary { background:var(--green);color:#fff; }
.btn-primary:hover { background:var(--green-dk); }
.btn-outline { background:var(--surface);color:var(--text-2);border:1px solid var(--border); }
.btn-sync { background:#f0faf4;color:var(--green-dk);border:1px solid #bbf7d0; }
.btn-sync:hover { background:var(--green-lt); }
.status-dot { display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--text-3);margin-right:5px;animation:pulse 2s infinite; }
.status-dot.online { background:var(--green); }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }
@media(max-width:1100px) { .kpi-row{grid-template-columns:repeat(2,1fr);} }

/* ── Sidebar collapse (desktop) ───────────────────────────────────────── */
.sidebar { transition:width .2s ease, transform .25s ease; overflow:hidden; }
.main    { transition:margin-left .2s ease; }
.nav-label { white-space:nowrap; overflow:hidden; transition:opacity .15s; }

/* toggle button — inside logo header, icon-only */
.sb-toggle { display:flex;align-items:center;justify-content:center;width:28px;height:28px;
  padding:0;border-radius:6px;background:none;border:none;cursor:pointer;
  color:var(--text-3);flex-shrink:0;margin-left:auto;transition:background .12s,color .12s; }
.sb-toggle:hover { background:var(--surface-alt);color:var(--text); }
.sb-toggle svg { width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;
  stroke-linecap:round;stroke-linejoin:round; }
.sb-toggle .ico-collapse { display:flex; }
.sb-toggle .ico-expand   { display:none; }

/* collapsed state */
body.sb-col .sidebar           { width:64px; }
body.sb-col .main              { margin-left:64px; }
body.sb-col .logo-text         { display:none; }
body.sb-col .logo-text-wrap    { display:none; }
body.sb-col .sidebar-pharmacy  { display:none; }
body.sb-col .nav-label         { display:none; }
body.sb-col .nav-link          { justify-content:center; padding:10px 0; margin:1px 10px; }
body.sb-col .sidebar-footer    { justify-content:center; padding:14px 0; }
body.sb-col .avatar-info       { display:none; }
body.sb-col .logout-btn        { display:none; }
body.sb-col .sb-toggle .ico-collapse { display:none; }
body.sb-col .sb-toggle .ico-expand   { display:flex; }

/* ── Responsive sidebar (mobile) ─────────────────────────────────────── */
.sidebar-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99; }
.sidebar-overlay.open { display:block; }
.hamburger { display:none;background:none;border:none;cursor:pointer;padding:4px;color:var(--text-2);flex-shrink:0; }
.hamburger svg { width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round; }
@media(max-width:768px) {
  .sidebar { transform:translateX(-100%);width:var(--sidebar-w)!important; }
  .sidebar.open { transform:translateX(0); }
  .main { margin-left:0!important; }
  .hamburger { display:flex;align-items:center; }
  .sb-toggle { display:none !important; }
  body.sb-col .logo-text,
  body.sb-col .sidebar-pharmacy,
  body.sb-col .nav-label  { display:unset; }
  body.sb-col .nav-link   { justify-content:flex-start;padding:8px 14px;margin:1px 8px; }
  body.sb-col .sb-toggle  { display:none !important; }
  body.sb-col .avatar-info,
  body.sb-col .logout-btn { display:unset; }
  body.sb-col .sidebar-footer { justify-content:unset;padding:14px 16px; }
  /* Layout */
  .content  { padding:14px 14px 28px!important; }
  .topbar   { padding:0 12px!important; height:52px; }
  .topbar-left { flex-direction:row!important;align-items:center;gap:8px; }
  .topbar-title { font-size:15px; }
  .topbar-meta  { font-size:11px; }
  /* Tables */
  .kpi-row  { grid-template-columns:repeat(2,1fr)!important; }
  .tbl th, .tbl td { padding:8px 10px; font-size:12px; }
  /* Cards */
  .card-header { padding:12px 14px; }
  .card-body   { padding:14px; }
  /* Topbar action buttons — icon only on mobile */
  .refresh-btn .btn-label { display:none; }
  .refresh-btn { padding:6px 8px; }
}
@media(max-width:480px) {
  .kpi-row  { grid-template-columns:1fr!important; }
  .content  { padding:12px 12px 24px!important; }
  .topbar   { padding:0 10px!important; }
  .topbar-title { font-size:14px; }
}
