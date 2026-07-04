<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>digiPharm — Connexion</title>
<style>
/* ═══════════════════════════════════════════════════════
   TOKENS
═══════════════════════════════════════════════════════ */
:root {
  --green:        #16A34A;
  --green-h:      #15803D;
  --green-lit:    #4ADE80;
  --green-glow:   rgba(22,163,74,.18);
  --green-surf:   rgba(22,163,74,.10);
  --green-border: rgba(22,163,74,.25);
  --ink:          #07120D;
  --ink-1:        #081510;
  --ink-2:        #0D1713;
  --ink-card:     rgba(255,255,255,.04);
  --ink-b:        rgba(255,255,255,.08);
  --ink-b2:       rgba(255,255,255,.13);
  --ink-dim:      rgba(255,255,255,.45);
  --ink-mute:     rgba(255,255,255,.25);
  --text:         #111827;
  --text-2:       #6B7280;
  --text-3:       #9CA3AF;
  --border:       #E5E7EB;
  --surf:         #FFFFFF;
  --surf-2:       #F9FAFB;
  --font: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --sh-card: 0 1px 2px rgba(0,0,0,.04), 0 6px 18px rgba(0,0,0,.06),
             0 24px 56px rgba(0,0,0,.09), 0 56px 100px rgba(0,0,0,.08);
  --sh-btn:   0 4px 16px rgba(22,163,74,.32);
  --sh-btn-h: 0 8px 28px rgba(22,163,74,.46);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  height: 100%;
  overflow: hidden;
  font-family: var(--font);
  -webkit-font-smoothing: antialiased;
}

/* ── shell ── */
.auth-shell {
  display: flex;
  width: 100%;
  height: 100vh;
  overflow: hidden;
}

/* ═══════════════════════════════════════════════════════
   LEFT PANEL
═══════════════════════════════════════════════════════ */
.l {
  width: 50%; height: 100vh;
  position: relative; overflow: hidden;
  background: linear-gradient(158deg, var(--ink) 0%, var(--ink-1) 52%, var(--ink-2) 100%);
  display: flex; flex-direction: column;
}
.l-dots {
  position: absolute; inset: 0; z-index: 0; pointer-events: none;
  background-image: radial-gradient(rgba(255,255,255,.065) 1px, transparent 1px);
  background-size: 26px 26px;
}
.l-glow {
  position: absolute; border-radius: 50%; pointer-events: none; z-index: 0;
}
.l-glow-1 {
  width: 500px; height: 500px;
  background: radial-gradient(circle, rgba(22,163,74,.22) 0%, rgba(22,163,74,.07) 42%, transparent 70%);
  top: -170px; left: -140px;
  animation: gd1 14s ease-in-out infinite alternate;
}
.l-glow-2 {
  width: 360px; height: 360px;
  background: radial-gradient(circle, rgba(22,163,74,.12) 0%, transparent 70%);
  bottom: -70px; right: -90px;
  animation: gd2 11s ease-in-out infinite alternate;
}
@keyframes gd1 { to { transform: translate(22px, 18px); } }
@keyframes gd2 { to { transform: translate(-18px, -14px); } }

.l-scroll {
  position: relative; z-index: 1;
  height: 100%; overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: none;
  -ms-overflow-style: none;
  padding: 1.875rem 2.375rem;
  display: flex; flex-direction: column; gap: 1.125rem;
}
.l-scroll::-webkit-scrollbar { display: none; width: 0; }

/* brand */
.brand { display: flex; align-items: center; gap: 11px; flex-shrink: 0; }
.brand-mark {
  width: 37px; height: 37px; background: var(--green); border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  box-shadow: 0 0 0 1px rgba(22,163,74,.5), 0 4px 16px rgba(22,163,74,.28);
}
.brand-name {
  font-size: 16px; font-weight: 800; color: #fff;
  letter-spacing: -.4px; line-height: 1; display: block;
}
.brand-tag {
  font-size: 9.5px; color: var(--ink-mute); letter-spacing: .05em;
  text-transform: uppercase; font-weight: 500; display: block; margin-top: 3px;
}

/* hero */
.hero { flex-shrink: 0; }
.hero-h1 {
  font-size: clamp(25px, 2.5vw, 37px); font-weight: 800; color: #fff;
  line-height: 1.14; letter-spacing: -.05em; margin-bottom: .7rem;
}
.hero-h1 .a { color: var(--green-lit); }
.hero-p { font-size: 13px; color: var(--ink-dim); line-height: 1.72; max-width: 355px; }

/* feature cards */
.feat-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 7px; flex-shrink: 0;
}
.feat-card {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  background: var(--ink-card); border: 1px solid var(--ink-b); border-radius: 14px;
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  transition: background 250ms, border-color 250ms, transform 250ms, box-shadow 250ms;
}
.feat-card:hover {
  background: var(--green-surf); border-color: var(--green-border);
  transform: translateY(-3px);
  box-shadow: 0 10px 28px var(--green-glow), 0 0 0 1px var(--green-border);
}
.feat-ico {
  width: 30px; height: 30px; background: rgba(22,163,74,.15); border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  color: var(--green-lit); flex-shrink: 0;
}
.feat-ico svg { width: 14px; height: 14px; }
.feat-lbl { font-size: 12.5px; font-weight: 600; color: rgba(255,255,255,.78); }

/* dashboard mockup */
.mockup {
  flex-shrink: 0; border-radius: 22px; overflow: hidden;
  border: 1px solid var(--ink-b2);
  box-shadow: 0 0 0 1px rgba(22,163,74,.1), 0 28px 72px rgba(0,0,0,.65), 0 0 80px rgba(22,163,74,.05);
  position: relative;
}
.mockup::after {
  content: ''; position: absolute; inset: 0; pointer-events: none; z-index: 3;
  background: linear-gradient(135deg, rgba(22,163,74,.07) 0%, transparent 55%);
  border-radius: 22px;
}
.m-chrome {
  display: flex; align-items: center; gap: 7px;
  padding: 8px 12px; background: #0B160D;
  border-bottom: 1px solid rgba(255,255,255,.04);
}
.m-dots { display: flex; gap: 5px; }
.m-dot { width: 9px; height: 9px; border-radius: 50%; }
.dot-r { background: #FF5F57; } .dot-y { background: #FEBC2E; } .dot-g { background: #28C840; }
.m-addr {
  flex: 1; background: rgba(255,255,255,.05); border-radius: 4px;
  padding: 3px 8px; font-size: 9px; color: rgba(255,255,255,.2);
  font-family: 'Consolas', 'SF Mono', monospace; margin: 0 6px;
}
.m-body { display: flex; height: 158px; background: #091208; }
.m-sb {
  width: 42px; background: #07100A; border-right: 1px solid rgba(255,255,255,.04);
  display: flex; flex-direction: column; align-items: center;
  padding: 10px 0; gap: 4px; flex-shrink: 0;
}
.sb-logo {
  width: 24px; height: 24px; background: var(--green); border-radius: 6px;
  display: flex; align-items: center; justify-content: center; margin-bottom: 10px;
}
.sb-item {
  width: 28px; height: 28px; border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
}
.sb-item.on { background: rgba(22,163,74,.22); }
.sb-item svg { width: 12px; height: 12px; stroke: rgba(255,255,255,.2); stroke-width: 2; fill: none; }
.sb-item.on svg { stroke: #4ADE80; }
.m-content {
  flex: 1; padding: 10px; overflow: hidden;
  display: flex; flex-direction: column; gap: 7px;
}
.kpi-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 5px; flex-shrink: 0; }
.kpi {
  background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.05);
  border-radius: 6px; padding: 6px 7px;
}
.kpi-v { font-size: 12px; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 2px; font-variant-numeric: tabular-nums; }
.kpi-l { font-size: 7px; color: rgba(255,255,255,.28); }
.kpi-t { font-size: 6.5px; color: #4ADE80; margin-top: 2px; }
.kpi-t.dn { color: #F87171; }
.chart-row { display: flex; gap: 5px; flex: 1; min-height: 0; }
.c-bars {
  flex: 1.5; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.04);
  border-radius: 6px; padding: 7px 8px; display: flex; flex-direction: column;
}
.c-lbl {
  font-size: 6.5px; color: rgba(255,255,255,.25); font-weight: 600;
  text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; flex-shrink: 0;
}
.bars { display: flex; align-items: flex-end; gap: 3px; flex: 1; }
.bar { flex: 1; border-radius: 2px 2px 0 0; background: rgba(22,163,74,.22); }
.bar.hi { background: var(--green); }
.c-table {
  flex: 1.2; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.04);
  border-radius: 6px; padding: 7px 8px; overflow: hidden;
}
.mt-row { display: flex; justify-content: space-between; align-items: center; padding: 3.5px 0; border-bottom: 1px solid rgba(255,255,255,.04); }
.mt-row:last-child { border: none; }
.mt-n { font-size: 7px; color: rgba(255,255,255,.35); }
.mt-v { font-size: 7px; font-weight: 700; color: #4ADE80; font-variant-numeric: tabular-nums; }
.c-donut {
  width: 82px; flex-shrink: 0; background: rgba(255,255,255,.025);
  border: 1px solid rgba(255,255,255,.04); border-radius: 6px; padding: 7px;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px;
}
.donut-svg { width: 42px; height: 42px; }
.d-legend { display: flex; flex-direction: column; gap: 3px; }
.d-item { display: flex; align-items: center; gap: 3px; font-size: 6.5px; color: rgba(255,255,255,.35); }
.d-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }

/* left footer */
.l-foot {
  flex-shrink: 0; padding-top: 1rem;
  border-top: 1px solid rgba(255,255,255,.07);
}
.l-reg-link {
  font-size: 13px; color: rgba(255,255,255,.45); text-decoration: none;
  transition: color .2s;
}
.l-reg-link:hover { color: rgba(255,255,255,.85); }
.l-reg-link span { color: var(--green-lit); font-weight: 600; }

/* stats bar */
.stats {
  display: grid; grid-template-columns: repeat(4,1fr);
  border: 1px solid var(--ink-b2); border-radius: 14px; overflow: hidden;
  flex-shrink: 0; background: rgba(255,255,255,.035);
  backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
}
.stat { padding: 12px 8px; text-align: center; border-right: 1px solid var(--ink-b); }
.stat:last-child { border-right: none; }
.stat-v { display: block; font-size: 18px; font-weight: 800; color: #fff; letter-spacing: -.04em; line-height: 1; margin-bottom: 3px; font-variant-numeric: tabular-nums; }
.stat-l { font-size: 9.5px; color: var(--ink-mute); font-weight: 500; }

/* ═══════════════════════════════════════════════════════
   RIGHT PANEL
═══════════════════════════════════════════════════════ */
.r {
  width: 50%; height: 100vh; background: var(--surf);
  display: flex; align-items: center; justify-content: center;
  overflow-y: auto; padding: 2rem 1.5rem; position: relative;
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.r::-webkit-scrollbar { display: none; }
.r::before {
  content: ''; position: absolute; inset: 0; pointer-events: none;
  background-image: radial-gradient(rgba(22,163,74,.024) 1px, transparent 1px);
  background-size: 24px 24px;
}
.r::after {
  content: ''; position: absolute; pointer-events: none; border-radius: 50%;
  width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(22,163,74,.04) 0%, transparent 70%);
  top: -200px; right: -100px;
}

.r-inner {
  width: 100%; max-width: 468px; position: relative; z-index: 1;
  animation: cardIn .45s cubic-bezier(.16,1,.3,1) both;
}
@keyframes cardIn { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }

/* card */
.card {
  background: #fff; border-radius: 24px; padding: 44px 44px 36px;
  border: 1px solid rgba(0,0,0,.055); box-shadow: var(--sh-card); margin-bottom: 15px;
}

/* lock icon */
.card-ico {
  width: 52px; height: 52px;
  background: linear-gradient(135deg, #F0FDF4, #DCFCE7);
  border: 1.5px solid rgba(22,163,74,.22); border-radius: 15px;
  display: flex; align-items: center; justify-content: center;
  color: var(--green); margin-bottom: 20px;
  box-shadow: 0 4px 14px rgba(22,163,74,.14);
  transition: background .3s, border-color .3s;
}
.card-ico svg { width: 22px; height: 22px; }

.c-title { font-size: 1.6rem; font-weight: 800; color: var(--text); letter-spacing: -.045em; line-height: 1.1; margin-bottom: 5px; }
.c-desc  { font-size: 13.5px; color: var(--text-2); line-height: 1.55; margin-bottom: 22px; }

/* messages */
.msg-area { margin-bottom: 14px; }
.msg {
  display: flex; align-items: center; gap: 9px;
  padding: 11px 14px; border-radius: 10px; font-size: 13px;
}
.msg svg { width: 15px; height: 15px; flex-shrink: 0; }
.msg.error   { background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; }
.msg.success { background: #F0FDF4; border: 1px solid #BBF7D0; color: #0D652D; }
.msg.info    { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1D4ED8; }

/* fields */
.field { margin-bottom: 13px; }
.f-lbl { display: block; font-size: 12.5px; font-weight: 600; color: var(--text); margin-bottom: 7px; letter-spacing: .01em; }
.f-wrap { position: relative; }
.f-ico {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  color: var(--text-3); pointer-events: none; display: flex;
  transition: color 200ms;
}
.f-ico svg { width: 16px; height: 16px; }
.f-input {
  width: 100%; height: 54px; padding: 0 44px;
  border: 1.5px solid var(--border); border-radius: 14px;
  font-size: 14px; font-family: var(--font); color: var(--text);
  background: #FAFAFA; outline: none; appearance: none; -webkit-appearance: none;
  transition: border-color 200ms, box-shadow 200ms, background 200ms;
}
.f-input::placeholder { color: var(--text-3); }
.f-input:hover { border-color: #D1D5DB; background: #fff; }
.f-input:focus { border-color: var(--green); box-shadow: 0 0 0 3.5px rgba(22,163,74,.1); background: #fff; }
.f-input.otp-style {
  text-align: center; font-size: 2rem; font-weight: 700;
  letter-spacing: .5rem; padding: 0 1rem; height: 68px;
}
.pw-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; padding: 4px;
  color: var(--text-3); display: flex; border-radius: 6px; transition: color 150ms;
}
.pw-btn:hover { color: var(--text-2); }
.pw-btn svg { width: 16px; height: 16px; }

/* extras */
.extras { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.remember { display: flex; align-items: center; gap: 7px; font-size: 13px; color: var(--text-2); cursor: pointer; user-select: none; }
.remember input[type=checkbox] { width: 15px; height: 15px; border-radius: 4px; accent-color: var(--green); cursor: pointer; }
.forgot { font-size: 13px; color: var(--green); font-weight: 500; text-decoration: none; transition: color 150ms; }
.forgot:hover { color: var(--green-h); }

/* buttons */
.btn-go {
  width: 100%; height: 54px; background: var(--green); color: #fff;
  border: none; border-radius: 14px; font-size: 15px; font-weight: 700; font-family: var(--font);
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  position: relative; overflow: hidden; letter-spacing: -.01em;
  transition: background 250ms, transform 200ms, box-shadow 250ms;
  box-shadow: var(--sh-btn);
}
.btn-go::after { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,.16) 0%, transparent 55%); opacity: 0; transition: opacity 250ms; }
.btn-go:hover:not(:disabled) { background: var(--green-h); transform: translateY(-2px) scale(1.01); box-shadow: var(--sh-btn-h); }
.btn-go:hover:not(:disabled)::after { opacity: 1; }
.btn-go:active:not(:disabled) { transform: translateY(0) scale(.99); }
.btn-go:disabled { background: #D1D5DB; box-shadow: none; cursor: not-allowed; }
.btn-go svg { width: 17px; height: 17px; flex-shrink: 0; }

.btn-sec {
  width: 100%; height: 46px; background: var(--surf-2); color: var(--text-2);
  border: 1.5px solid var(--border); border-radius: 14px; font-size: 14px; font-weight: 500; font-family: var(--font);
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-top: 9px; transition: background 200ms, border-color 200ms;
}
.btn-sec:hover:not(:disabled) { background: #F3F4F6; border-color: #D1D5DB; }
.btn-sec:disabled { opacity: .55; cursor: not-allowed; }
.btn-sec svg { width: 15px; height: 15px; flex-shrink: 0; }

/* countdown */
.countdown {
  font-size: 12px; color: var(--text-3); text-align: center; margin-top: 10px;
}

/* security note */
.sec-note {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 11px 14px; background: var(--surf-2); border: 1px solid #EDEDED;
  border-radius: 10px; margin-top: 20px;
}
.sec-ico { color: var(--green); flex-shrink: 0; margin-top: 1px; }
.sec-ico svg { width: 15px; height: 15px; }
.sec-main { font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
.sec-sub  { font-size: 11px; color: var(--text-3); }

/* footer */
.c-foot { text-align: center; }
.c-foot p { font-size: 13.5px; color: var(--text-2); margin-bottom: 5px; }
.c-foot a { color: var(--green); font-weight: 600; text-decoration: none; }
.c-foot a:hover { color: var(--green-h); }
.c-foot-copy { font-size: 12px; color: var(--text-3); }

/* register link inside card */
.card-foot-link {
  text-align: center; margin-top: 16px;
  font-size: 13px; color: var(--text-2);
}
.card-foot-link a { color: var(--green); font-weight: 600; text-decoration: none; }
.card-foot-link a:hover { color: var(--green-h); }

/* helpers */
.hidden { display: none !important; }
@keyframes fadeIn    { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideRight { from { opacity:0; transform:translateX(16px); } to { opacity:1; transform:translateX(0); } }
.fade-in       { animation: fadeIn .25s ease-out; }
.slide-in-right { animation: slideRight .25s ease-out; }
.loading-spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,.35); border-top-color: #fff;
  border-radius: 50%; animation: spin .65s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* responsive */
@media (max-width: 900px) { .l { display: none; } .r { width: 100%; } }
@media (max-width: 520px)  { .r { padding: 1.5rem 1rem; } .card { padding: 28px 22px 24px; } }
@media (prefers-reduced-motion: reduce) {
  .l-glow-1, .l-glow-2, .r-inner { animation: none; }
  .feat-card, .btn-go, .btn-sec { transition: none; }
}
</style>
</head>
<body>
<div class="auth-shell">

<!-- ═══════ LEFT ═══════ -->
<div class="l" aria-hidden="true">
  <div class="l-dots"></div>
  <div class="l-glow l-glow-1"></div>
  <div class="l-glow l-glow-2"></div>

  <div class="l-scroll">

    <div class="brand">
      <div class="brand-mark">
        <svg width="17" height="17" viewBox="0 0 20 20" fill="#fff"><path d="M11 2H9v7H2v2h7v7h2v-7h7V9h-7V2z"/></svg>
      </div>
      <div>
        <span class="brand-name">digiPharm</span>
        <span class="brand-tag">Modern Pharmacy Operating System</span>
      </div>
    </div>

    <div class="hero">
      <h1 class="hero-h1">Le système d'exploitation<br>tout-en-un pour<br><span class="a">pharmacies</span></h1>
      <p class="hero-p">Gérez vos ventes, stocks, achats, caisses et rapports grâce à une plateforme moderne pensée pour les pharmacies congolaises.</p>
    </div>

    <div class="feat-grid">
      <div class="feat-card">
        <div class="feat-ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 001.95-1.56l1.65-9.44H6"/>
          </svg>
        </div>
        <span class="feat-lbl">Ventes</span>
      </div>
      <div class="feat-card">
        <div class="feat-ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
          </svg>
        </div>
        <span class="feat-lbl">Stock intelligent</span>
      </div>
      <div class="feat-card">
        <div class="feat-ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>
          </svg>
        </div>
        <span class="feat-lbl">Sécurité OTP</span>
      </div>
      <div class="feat-card">
        <div class="feat-ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
          </svg>
        </div>
        <span class="feat-lbl">Analyses</span>
      </div>
    </div>

    <!-- dashboard mockup -->
    <div class="mockup">
      <div class="m-chrome">
        <div class="m-dots">
          <div class="m-dot dot-r"></div><div class="m-dot dot-y"></div><div class="m-dot dot-g"></div>
        </div>
        <div class="m-addr">pharma.digitaltechnologiescongo.com/admin</div>
      </div>
      <div class="m-body">
        <div class="m-sb">
          <div class="sb-logo">
            <svg width="9" height="9" viewBox="0 0 20 20" fill="#fff"><path d="M11 2H9v7H2v2h7v7h2v-7h7V9h-7V2z"/></svg>
          </div>
          <div class="sb-item on">
            <svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10h4v-6h6v6h4V10"/></svg>
          </div>
          <div class="sb-item">
            <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
          </div>
          <div class="sb-item">
            <svg viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
          </div>
          <div class="sb-item">
            <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          </div>
        </div>
        <div class="m-content">
          <div class="kpi-row">
            <div class="kpi"><div class="kpi-v">97k</div><div class="kpi-l">Revenus</div><div class="kpi-t">↑ +12%</div></div>
            <div class="kpi"><div class="kpi-v">342</div><div class="kpi-l">Ventes</div><div class="kpi-t">↑ +8%</div></div>
            <div class="kpi"><div class="kpi-v">186</div><div class="kpi-l">Produits</div><div class="kpi-t" style="color:rgba(255,255,255,.2)">→ stable</div></div>
            <div class="kpi"><div class="kpi-v">8</div><div class="kpi-l">Ruptures</div><div class="kpi-t dn">↑ +2</div></div>
          </div>
          <div class="chart-row">
            <div class="c-bars">
              <div class="c-lbl">Ventes / semaine</div>
              <div class="bars">
                <div class="bar" style="height:44%"></div><div class="bar" style="height:63%"></div>
                <div class="bar" style="height:51%"></div><div class="bar" style="height:78%"></div>
                <div class="bar" style="height:57%"></div><div class="bar" style="height:68%"></div>
                <div class="bar hi" style="height:93%"></div>
              </div>
            </div>
            <div class="c-table">
              <div class="c-lbl">Dernières ventes</div>
              <div class="mt-row"><span class="mt-n">Paracétamol 500mg</span><span class="mt-v">2 400 F</span></div>
              <div class="mt-row"><span class="mt-n">Amoxicilline 250mg</span><span class="mt-v">4 800 F</span></div>
              <div class="mt-row"><span class="mt-n">Ibuprofène 400mg</span><span class="mt-v">1 500 F</span></div>
              <div class="mt-row"><span class="mt-n">Metformine 500mg</span><span class="mt-v">3 200 F</span></div>
            </div>
            <div class="c-donut">
              <svg class="donut-svg" viewBox="0 0 42 42">
                <circle cx="21" cy="21" r="16" fill="none" stroke="rgba(255,255,255,.06)" stroke-width="5"/>
                <circle cx="21" cy="21" r="16" fill="none" stroke="#16A34A"              stroke-width="5" stroke-dasharray="63 37" stroke-dashoffset="25"  stroke-linecap="round"/>
                <circle cx="21" cy="21" r="16" fill="none" stroke="#4ADE80"              stroke-width="5" stroke-dasharray="20 80" stroke-dashoffset="-38" stroke-linecap="round"/>
                <circle cx="21" cy="21" r="16" fill="none" stroke="rgba(22,163,74,.38)" stroke-width="5" stroke-dasharray="14 86" stroke-dashoffset="-58" stroke-linecap="round"/>
              </svg>
              <div class="d-legend">
                <div class="d-item"><div class="d-dot" style="background:#16A34A"></div>Ventes 63%</div>
                <div class="d-item"><div class="d-dot" style="background:#4ADE80"></div>Stock 20%</div>
                <div class="d-item"><div class="d-dot" style="background:rgba(22,163,74,.5)"></div>Autre 17%</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="stats">
      <div class="stat"><span class="stat-v">500+</span><span class="stat-l">Pharmacies actives</span></div>
      <div class="stat"><span class="stat-v">300K+</span><span class="stat-l">Ventes enregistrées</span></div>
      <div class="stat"><span class="stat-v">99.98%</span><span class="stat-l">Uptime</span></div>
      <div class="stat"><span class="stat-v">100%</span><span class="stat-l">Cloud sécurisé</span></div>
    </div>

    <div class="l-foot">
      <a href="register" class="l-reg-link">
        Pas encore client ? <span>Créer un compte gratuit →</span>
      </a>
    </div>

  </div>
</div>

<!-- ═══════ RIGHT ═══════ -->
<div class="r">
  <div class="r-inner">

    <div class="card">

      <!-- dynamic icon -->
      <div class="card-ico" id="cardIco">
        <svg id="icoLock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
        <svg id="icoMail" class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
          <polyline points="22,6 12,13 2,6"/>
        </svg>
      </div>

      <h1 class="c-title" id="cardTitle">Connexion</h1>
      <p class="c-desc"  id="cardDesc">Bienvenue de retour ! Connectez-vous à votre espace digiPharm.</p>

      <div class="msg-area" id="msgArea"></div>

      <!-- ── Step 1: Login ── -->
      <form id="loginForm" novalidate>

        <div class="field">
          <label class="f-lbl" for="username">Nom d'utilisateur</label>
          <div class="f-wrap">
            <span class="f-ico">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
              </svg>
            </span>
            <input type="text" id="username" name="username" class="f-input" placeholder="Votre identifiant" autocomplete="username" required>
          </div>
        </div>

        <div class="field">
          <label class="f-lbl" for="password">Mot de passe</label>
          <div class="f-wrap">
            <span class="f-ico">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
              </svg>
            </span>
            <input type="password" id="password" name="password" class="f-input" placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" class="pw-btn" id="pwBtn" aria-label="Afficher/masquer">
              <svg id="eyeOn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
              <svg id="eyeOff" class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="extras">
          <label class="remember"><input type="checkbox" id="rem"> Se souvenir de moi</label>
          <a href="forgot-password" class="forgot">Mot de passe oublié ?</a>
        </div>

        <button type="submit" class="btn-go" id="loginBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
          </svg>
          <span id="loginBtnTxt">Se connecter</span>
        </button>

        <p class="card-foot-link">Pas encore client ? <a href="register">Créer un compte gratuit →</a></p>

      </form>

      <!-- ── Step 2: OTP ── -->
      <form id="otpForm" class="hidden" novalidate>

        <div class="field">
          <label class="f-lbl" for="otp">Code de vérification</label>
          <div class="f-wrap">
            <input type="text" id="otp" name="otp" class="f-input otp-style"
                   placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                   autocomplete="one-time-code" inputmode="numeric" required>
          </div>
          <div style="font-size:12px;color:var(--text-3);margin-top:6px;text-align:center;">
            Code à 6 chiffres envoyé à votre adresse email
          </div>
        </div>

        <button type="submit" class="btn-go" id="otpBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span id="otpBtnTxt">Vérifier le code</span>
        </button>

        <button type="button" class="btn-sec" id="resendBtn" disabled>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 101.85-4.36L1 10"/>
          </svg>
          <span id="resendBtnTxt">Renvoyer le code</span>
        </button>

        <button type="button" class="btn-sec" id="backBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
          </svg>
          Retour
        </button>

        <div id="countdown" class="countdown hidden"></div>

      </form>

    </div>

  </div>
</div>

</div><!-- /auth-shell -->

<script>
'use strict';

let currentStep    = 'login';
let countdownTimer = null;
let resendCountdown = 60;

const loginForm  = document.getElementById('loginForm');
const otpForm    = document.getElementById('otpForm');
const msgArea    = document.getElementById('msgArea');
const cardTitle  = document.getElementById('cardTitle');
const cardDesc   = document.getElementById('cardDesc');
const icoLock    = document.getElementById('icoLock');
const icoMail    = document.getElementById('icoMail');

const loginBtn   = document.getElementById('loginBtn');
const loginBtnTxt= document.getElementById('loginBtnTxt');
const otpBtn     = document.getElementById('otpBtn');
const otpBtnTxt  = document.getElementById('otpBtnTxt');
const resendBtn  = document.getElementById('resendBtn');
const resendBtnTxt=document.getElementById('resendBtnTxt');
const backBtn    = document.getElementById('backBtn');

/* password toggle */
document.getElementById('pwBtn').addEventListener('click', () => {
  const pw = document.getElementById('password');
  const show = pw.type === 'password';
  pw.type = show ? 'text' : 'password';
  document.getElementById('eyeOn').classList.toggle('hidden', show);
  document.getElementById('eyeOff').classList.toggle('hidden', !show);
  pw.focus();
});

/* messages */
const MSG_ICONS = {
  error:   '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
  success: '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
  info:    '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
};
function showMsg(message, type = 'info') {
  const d = document.createElement('div');
  d.className = 'msg ' + type + ' fade-in';
  d.innerHTML = (MSG_ICONS[type] || MSG_ICONS.info) + '<span>' + message + '</span>';
  msgArea.innerHTML = '';
  msgArea.appendChild(d);
}
function clearMsg() { msgArea.innerHTML = ''; }

/* step transitions */
function switchToOTP() {
  currentStep = 'otp';
  loginForm.classList.add('hidden');
  otpForm.classList.remove('hidden');
  otpForm.classList.add('slide-in-right');
  cardTitle.textContent = 'Vérification OTP';
  cardDesc.textContent  = 'Un code à 6 chiffres a été envoyé à votre adresse email.';
  icoLock.classList.add('hidden');
  icoMail.classList.remove('hidden');
  document.getElementById('otp').focus();
  startCountdown();
}
function switchToLogin() {
  currentStep = 'login';
  otpForm.classList.add('hidden');
  loginForm.classList.remove('hidden');
  loginForm.classList.add('slide-in-right');
  cardTitle.textContent = 'Connexion';
  cardDesc.textContent  = 'Bienvenue de retour ! Connectez-vous à votre espace digiPharm.';
  icoLock.classList.remove('hidden');
  icoMail.classList.add('hidden');
  stopCountdown();
  clearMsg();
}

/* countdown */
function startCountdown() {
  resendCountdown = 60;
  resendBtn.disabled = true;
  const cnt = document.getElementById('countdown');
  cnt.classList.remove('hidden');
  countdownTimer = setInterval(() => {
    resendCountdown--;
    cnt.textContent = 'Nouveau code disponible dans ' + resendCountdown + 's';
    resendBtnTxt.textContent = 'Renvoyer le code (' + resendCountdown + 's)';
    if (resendCountdown <= 0) {
      stopCountdown();
      resendBtn.disabled = false;
      resendBtnTxt.textContent = 'Renvoyer le code';
      cnt.textContent = 'Vous pouvez demander un nouveau code';
    }
  }, 1000);
}
function stopCountdown() {
  if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
  document.getElementById('countdown').classList.add('hidden');
}

/* button loading */
function setBusy(btn, txtEl, loading, label) {
  btn.disabled = loading;
  txtEl.innerHTML = loading ? '<span class="loading-spinner"></span>' : label;
}

/* fetch wrapper */
async function req(action, data) {
  try {
    const fd = new FormData();
    fd.append('action', action);
    for (const k in data) fd.append(k, data[k]);
    const r = await fetch('config/auth.php', { method: 'POST', body: fd });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return await r.json();
  } catch {
    return { success: false, message: 'Erreur réseau. Réessayez.' };
  }
}

/* login submit */
loginForm.addEventListener('submit', async e => {
  e.preventDefault();
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  if (!username || !password) { showMsg('Veuillez remplir tous les champs.', 'error'); return; }
  clearMsg();
  setBusy(loginBtn, loginBtnTxt, true, '');
  const r = await req('login', { username, password });
  setBusy(loginBtn, loginBtnTxt, false, 'Se connecter');
  if (r.success) {
    if (r.redirect) { showMsg(r.message, 'success'); setTimeout(() => window.location.href = r.redirect, 800); return; }
    showMsg(r.message, 'success');
    setTimeout(switchToOTP, 1200);
  } else {
    showMsg(r.message, 'error');
  }
});

/* otp submit */
otpForm.addEventListener('submit', async e => {
  e.preventDefault();
  const otp = document.getElementById('otp').value.trim();
  if (!otp || otp.length !== 6) { showMsg('Entrez le code à 6 chiffres.', 'error'); return; }
  clearMsg();
  setBusy(otpBtn, otpBtnTxt, true, '');
  const r = await req('verify_otp', { otp });
  setBusy(otpBtn, otpBtnTxt, false, 'Vérifier le code');
  if (r.success) {
    showMsg(r.message, 'success');
    setTimeout(() => window.location.href = r.redirect, 800);
  } else {
    showMsg(r.message, 'error');
    document.getElementById('otp').value = '';
    document.getElementById('otp').focus();
  }
});

/* resend */
resendBtn.addEventListener('click', async () => {
  clearMsg();
  setBusy(resendBtn, resendBtnTxt, true, '');
  const r = await req('resend_otp', {});
  setBusy(resendBtn, resendBtnTxt, false, 'Renvoyer le code');
  if (r.success) { showMsg(r.message, 'success'); startCountdown(); }
  else showMsg(r.message, 'error');
});

backBtn.addEventListener('click', switchToLogin);

/* OTP auto-digits + auto-submit at 6 chars */
document.getElementById('otp').addEventListener('input', function () {
  this.value = this.value.replace(/[^0-9]/g, '');
  if (this.value.length === 6) setTimeout(() => otpForm.dispatchEvent(new Event('submit')), 300);
});

/* clear messages on field input */
document.querySelectorAll('.f-input').forEach(i => i.addEventListener('input', clearMsg));

/* keyboard shortcuts */
document.addEventListener('keydown', e => {
  if (e.ctrlKey && e.key === 'h' && currentStep === 'login') { e.preventDefault(); document.getElementById('pwBtn').click(); }
  if (e.key === 'Escape' && currentStep === 'otp') switchToLogin();
});

document.addEventListener('DOMContentLoaded', () => document.getElementById('username').focus());
</script>
</body>
</html>
