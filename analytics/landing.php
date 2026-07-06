<?php // Public landing page — no auth required ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>digiMind — L'IA analytique pour votre pharmacie</title>
<meta name="description" content="Briefings IA quotidiens, alertes de risque, prévisions de fin de mois. 7 jours gratuits. Conçu pour les pharmacies congolaises.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --teal:        #0d9488;
  --teal-dark:   #0f766e;
  --teal-glow:   rgba(13,148,136,.22);
  --teal-light:  #5eead4;
  --teal-xlight: #99f6e4;
  --green:       #1a7f4b;
  --ink:         #030f0e;
  --ink-soft:    #071413;
  --surface:     #ffffff;
  --gray-50:     #f6f9f9;
  --gray-100:    #edf2f2;
  --gray-400:    #89a3a2;
  --gray-600:    #4a6664;
  --text-dim:    rgba(255,255,255,.52);
  --text-mute:   rgba(255,255,255,.28);
  --border-dark: rgba(255,255,255,.07);
  --font:        'Plus Jakarta Sans', system-ui, sans-serif;
  --font-body:   'Inter', system-ui, sans-serif;
}
html { scroll-behavior: smooth; }
body { font-family: var(--font-body); color: var(--ink); background: var(--ink); -webkit-font-smoothing: antialiased; }

/* ══════════════════════════════════════════════════════
   NAV
══════════════════════════════════════════════════════ */
.nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 200;
  height: 62px; display: flex; align-items: center; padding: 0 48px;
  background: rgba(3,15,14,.82); backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border-dark);
  transition: background .3s;
}
.nav.scrolled { background: rgba(3,15,14,.97); }
.nav-inner { max-width: 1200px; margin: 0 auto; width: 100%; display: flex; align-items: center; justify-content: space-between; }

.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.logo-mark {
  width: 34px; height: 34px; border-radius: 9px;
  background: linear-gradient(135deg, var(--teal) 0%, var(--green) 100%);
  display: flex; align-items: center; justify-content: center;
}
.logo-mark svg { width: 16px; height: 16px; color: #fff; }
.logo-name { font-family: var(--font); font-size: 17px; font-weight: 700; color: #fff; letter-spacing: -.3px; }
.logo-badge {
  font-size: 9px; font-weight: 700; color: var(--teal-light);
  background: rgba(13,148,136,.2); border: 1px solid rgba(13,148,136,.3);
  padding: 2px 7px; border-radius: 100px; letter-spacing: .06em;
  text-transform: uppercase; margin-left: 2px;
}

.nav-links { display: flex; gap: 2px; }
.nav-link {
  padding: 7px 14px; border-radius: 7px; text-decoration: none;
  font-size: 14px; font-weight: 500; color: var(--text-dim);
  transition: color .15s, background .15s;
}
.nav-link:hover { color: #fff; background: rgba(255,255,255,.07); }

.nav-actions { display: flex; align-items: center; gap: 10px; }
.btn-nav-ghost {
  padding: 8px 18px; border-radius: 8px; text-decoration: none;
  font-size: 13.5px; font-weight: 600; color: rgba(255,255,255,.75);
  border: 1px solid rgba(255,255,255,.15); transition: all .15s;
}
.btn-nav-ghost:hover { color: #fff; border-color: rgba(255,255,255,.35); background: rgba(255,255,255,.06); }
.btn-nav-solid {
  padding: 8px 20px; background: var(--teal); border-radius: 8px;
  text-decoration: none; font-size: 13.5px; font-weight: 700; color: #fff;
  transition: all .2s;
}
.btn-nav-solid:hover { background: var(--teal-dark); transform: translateY(-1px); }

/* ══════════════════════════════════════════════════════
   HERO
══════════════════════════════════════════════════════ */
.hero {
  min-height: 100vh;
  display: grid; grid-template-columns: 1fr 1fr;
  align-items: center; gap: 60px;
  padding: 100px 80px 80px;
  max-width: 1360px; margin: 0 auto;
  position: relative;
}

.hero-bg {
  position: fixed; inset: 0; z-index: -1; pointer-events: none;
  background: var(--ink);
  overflow: hidden;
}
.hero-bg::before {
  content: '';
  position: absolute; width: 900px; height: 900px; border-radius: 50%;
  background: radial-gradient(circle, rgba(13,148,136,.14) 0%, transparent 65%);
  top: -300px; left: -200px;
  animation: drift 12s ease-in-out infinite alternate;
}
.hero-bg::after {
  content: '';
  position: absolute; width: 600px; height: 600px; border-radius: 50%;
  background: radial-gradient(circle, rgba(26,127,75,.08) 0%, transparent 65%);
  bottom: -100px; right: 0;
  animation: drift 9s ease-in-out infinite alternate-reverse;
}
@keyframes drift { from { transform: translate(0,0); } to { transform: translate(30px,40px); } }

.hero-grid {
  position: fixed; inset: 0; z-index: -1; pointer-events: none;
  background-image: radial-gradient(rgba(255,255,255,.035) 1px, transparent 1px);
  background-size: 32px 32px;
  mask-image: radial-gradient(ellipse 60% 60% at 30% 50%, black 0%, transparent 100%);
}

.hero-left { position: relative; z-index: 2; }

.hero-pill {
  display: inline-flex; align-items: center; gap: 8px;
  border: 1px solid rgba(13,148,136,.35); background: rgba(13,148,136,.08);
  border-radius: 100px; padding: 5px 14px 5px 8px;
  font-size: 12px; font-weight: 600; color: var(--teal-light);
  letter-spacing: .04em; text-transform: uppercase; margin-bottom: 30px;
}
.hero-pill-dot {
  width: 22px; height: 22px; background: rgba(13,148,136,.2); border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}
.hero-pill-dot::after {
  content: ''; width: 7px; height: 7px; background: var(--teal-light);
  border-radius: 50%; animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.4; transform:scale(.8); } }

h1.hero-title {
  font-family: var(--font); font-size: clamp(38px, 4.8vw, 62px);
  font-weight: 800; line-height: 1.07; letter-spacing: -2px;
  color: #fff; margin-bottom: 22px;
}
h1.hero-title em {
  font-style: italic; font-weight: 800;
  background: linear-gradient(135deg, var(--teal-xlight) 0%, var(--teal) 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}

.hero-sub {
  font-size: 17px; color: var(--text-dim); line-height: 1.72;
  max-width: 450px; margin-bottom: 40px;
}

.hero-ctas { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 36px; }
.btn-cta-teal {
  display: inline-flex; align-items: center; gap: 9px;
  background: var(--teal); color: #fff; text-decoration: none;
  padding: 14px 28px; border-radius: 10px; font-family: var(--font);
  font-size: 15px; font-weight: 700;
  box-shadow: 0 0 0 0 var(--teal-glow);
  transition: all .2s;
}
.btn-cta-teal:hover {
  background: var(--teal-dark); transform: translateY(-2px);
  box-shadow: 0 10px 40px var(--teal-glow);
}
.btn-cta-teal svg { width: 16px; height: 16px; }
.btn-cta-ghost {
  display: inline-flex; align-items: center; gap: 8px;
  color: rgba(255,255,255,.65); text-decoration: none;
  font-size: 15px; font-weight: 500; transition: color .15s;
}
.btn-cta-ghost:hover { color: #fff; }
.btn-cta-ghost svg { width: 15px; height: 15px; }

.hero-fine {
  display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
}
.fine-item {
  display: flex; align-items: center; gap: 7px;
  font-size: 12.5px; color: var(--text-mute); font-weight: 500;
}
.fine-item svg { width: 13px; height: 13px; color: var(--teal); opacity: .75; }

/* ══════════════════════════════════════════════════════
   DASHBOARD MOCKUP — digiMind AI briefing UI
══════════════════════════════════════════════════════ */
.hero-right { position: relative; z-index: 2; }

.mockup-outer {
  background: linear-gradient(135deg, rgba(13,148,136,.08) 0%, rgba(255,255,255,.02) 100%);
  border: 1px solid rgba(255,255,255,.07); border-radius: 18px;
  padding: 3px;
  box-shadow: 0 50px 130px rgba(0,0,0,.65), 0 0 0 1px rgba(255,255,255,.04);
  transform: perspective(1400px) rotateY(-5deg) rotateX(2deg);
  transition: transform .5s ease;
}
.mockup-outer:hover { transform: perspective(1400px) rotateY(-2deg) rotateX(0deg); }
.mockup-chrome { background: #080f0e; border-radius: 16px; overflow: hidden; }
.chrome-bar {
  display: flex; align-items: center; gap: 8px; padding: 10px 16px;
  background: #050d0c; border-bottom: 1px solid rgba(255,255,255,.05);
}
.chrome-dot { width: 10px; height: 10px; border-radius: 50%; }
.cd-r { background: #ff5f57; } .cd-y { background: #febc2e; } .cd-g { background: #28c840; }
.chrome-url {
  flex: 1; background: rgba(255,255,255,.05); border-radius: 5px;
  padding: 4px 12px; font-size: 10.5px; color: rgba(255,255,255,.25);
  font-family: monospace; margin: 0 10px;
}

.app-shell { display: flex; height: 360px; }

/* sidebar */
.ms {
  width: 52px; background: #030e0d; border-right: 1px solid rgba(255,255,255,.05);
  display: flex; flex-direction: column; align-items: center; padding: 12px 0; gap: 5px;
}
.ms-logo {
  width: 28px; height: 28px;
  background: linear-gradient(135deg, var(--teal) 0%, var(--green) 100%);
  border-radius: 7px;
  display: flex; align-items: center; justify-content: center; margin-bottom: 12px;
}
.ms-logo svg { width: 13px; height: 13px; color: #fff; }
.ms-item { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.ms-item.on { background: rgba(13,148,136,.2); }
.ms-item svg { width: 14px; height: 14px; stroke: rgba(255,255,255,.22); stroke-width: 1.8; fill: none; }
.ms-item.on svg { stroke: var(--teal-light); }

/* content area */
.mc { flex: 1; background: #071413; padding: 12px; overflow: hidden; }
.mc-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.mc-head { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.5); font-family: var(--font); }
.mc-greeting { font-size: 9.5px; color: var(--teal-light); font-weight: 600; }

/* KPI strip */
.kpi-strip {
  display: grid; grid-template-columns: repeat(4, 1fr);
  border: 1px solid rgba(255,255,255,.06); border-radius: 8px;
  overflow: hidden; margin-bottom: 10px;
}
.kpi-item {
  padding: 8px 9px; border-right: 1px solid rgba(255,255,255,.06);
}
.kpi-item:last-child { border-right: none; }
.kpi-icon-wrap {
  width: 18px; height: 18px; border-radius: 4px;
  display: flex; align-items: center; justify-content: center; margin-bottom: 5px;
}
.kpi-icon-wrap.red   { background: rgba(239,68,68,.18); }
.kpi-icon-wrap.teal  { background: rgba(13,148,136,.2); }
.kpi-icon-wrap.amber { background: rgba(249,115,22,.18); }
.kpi-icon-wrap.blue  { background: rgba(59,130,246,.18); }
.kpi-icon-wrap svg { width: 9px; height: 9px; fill: none; stroke-width: 2.5; }
.kpi-icon-wrap.red  svg { stroke: #ef4444; }
.kpi-icon-wrap.teal svg { stroke: var(--teal-light); }
.kpi-icon-wrap.amber svg { stroke: #f97316; }
.kpi-icon-wrap.blue svg  { stroke: #60a5fa; }
.kpi-val  { font-size: 12px; font-weight: 800; color: #fff; line-height: 1; font-family: var(--font); }
.kpi-lbl  { font-size: 6.5px; color: rgba(255,255,255,.3); margin-top: 2px; }

/* briefing tabs + cards */
.mc-tabs { display: flex; gap: 4px; margin-bottom: 8px; }
.mc-tab {
  font-size: 7.5px; font-weight: 600; padding: 3px 8px; border-radius: 4px;
  color: rgba(255,255,255,.3); cursor: default;
}
.mc-tab.on { background: rgba(13,148,136,.25); color: var(--teal-light); }

.brief-cards { display: flex; flex-direction: column; gap: 5px; }
.brief-card {
  background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.06);
  border-radius: 6px; padding: 7px 9px; display: flex; gap: 7px; align-items: flex-start;
}
.brief-card.crit { border-left: 2px solid #ef4444; }
.brief-card.warn { border-left: 2px solid #f97316; }
.brief-card.info { border-left: 2px solid var(--teal); }
.bc-sev {
  font-size: 6px; font-weight: 800; padding: 2px 5px; border-radius: 3px;
  text-transform: uppercase; letter-spacing: .04em; flex-shrink: 0; margin-top: 1px;
}
.bc-sev.red   { background: rgba(239,68,68,.18); color: #ef4444; }
.bc-sev.amber { background: rgba(249,115,22,.18); color: #f97316; }
.bc-sev.teal  { background: rgba(13,148,136,.18); color: var(--teal-light); }
.bc-title { font-size: 8px; font-weight: 700; color: rgba(255,255,255,.7); margin-bottom: 2px; font-family: var(--font); }
.bc-desc  { font-size: 7px; color: rgba(255,255,255,.3); line-height: 1.4; }
.bc-impact { font-size: 7px; font-weight: 700; color: #ef4444; margin-top: 2px; }
.bc-impact.teal { color: var(--teal-light); }

/* health score mini card */
.health-bar {
  background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.05);
  border-radius: 6px; padding: 7px 9px; margin-top: 5px;
  display: flex; align-items: center; justify-content: space-between;
}
.hb-label { font-size: 7.5px; color: rgba(255,255,255,.35); }
.hb-score { font-size: 14px; font-weight: 800; color: var(--teal-light); font-family: var(--font); }
.hb-badge { font-size: 7px; font-weight: 700; color: #fff; background: var(--teal); padding: 2px 6px; border-radius: 3px; }
.hb-bar-wrap { height: 3px; background: rgba(255,255,255,.07); border-radius: 2px; margin-top: 4px; }
.hb-bar-fill { height: 100%; background: linear-gradient(90deg, var(--teal), var(--teal-light)); border-radius: 2px; width: 78%; }

/* ══════════════════════════════════════════════════════
   CONTENT SECTIONS
══════════════════════════════════════════════════════ */
.content-wrapper { background: var(--surface); }

.section { padding: 96px 80px; }
.section-inner { max-width: 1200px; margin: 0 auto; }
.eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  font-size: 11.5px; font-weight: 700; color: var(--teal);
  text-transform: uppercase; letter-spacing: .1em; margin-bottom: 14px;
}
.eyebrow::before { content: ''; width: 20px; height: 2px; background: var(--teal); border-radius: 1px; }
h2.sec-title {
  font-family: var(--font); font-size: clamp(28px, 3vw, 42px);
  font-weight: 800; letter-spacing: -1px; line-height: 1.15;
  color: var(--ink); margin-bottom: 14px;
}
.sec-sub { font-size: 16px; color: var(--gray-400); line-height: 1.7; max-width: 500px; }

/* features */
.features-head { text-align: center; margin-bottom: 60px; }
.features-head .sec-sub { margin: 0 auto; }
.feat-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; }
.feat-card {
  background: var(--gray-50); border: 1px solid var(--gray-100);
  border-radius: 14px; padding: 28px; transition: all .25s; position: relative; overflow: hidden;
}
.feat-card::after {
  content: ''; position: absolute; inset: 0; border-radius: 14px;
  opacity: 0; transition: opacity .3s;
  background: linear-gradient(145deg, rgba(13,148,136,.04), transparent);
}
.feat-card:hover { border-color: rgba(13,148,136,.2); transform: translateY(-3px); box-shadow: 0 16px 48px rgba(13,148,136,.07); }
.feat-card:hover::after { opacity: 1; }
.feat-icon {
  width: 44px; height: 44px; background: rgba(13,148,136,.1); border-radius: 11px;
  display: flex; align-items: center; justify-content: center; margin-bottom: 18px;
  color: var(--teal);
}
.feat-icon svg { width: 20px; height: 20px; }
.feat-card h3 { font-family: var(--font); font-size: 16px; font-weight: 700; color: var(--ink); margin-bottom: 9px; }
.feat-card p { font-size: 14px; color: var(--gray-400); line-height: 1.68; }
.feat-ai { font-size: 10.5px; font-weight: 700; color: var(--teal); text-transform: uppercase; letter-spacing: .06em; margin-top: 12px; }

/* spotlight — dark section */
.spotlight { background: var(--ink); padding: 96px 80px; }
.spotlight-inner {
  max-width: 1200px; margin: 0 auto;
  display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 80px;
}
.spotlight-text .eyebrow { color: var(--teal-light); }
.spotlight-text .eyebrow::before { background: var(--teal-light); }
.spotlight-text h2 { color: #fff; }
.spotlight-text .sec-sub { color: rgba(255,255,255,.45); max-width: 420px; }
.spotlight-list { margin-top: 32px; display: flex; flex-direction: column; gap: 18px; }
.spl-item { display: flex; gap: 14px; align-items: flex-start; }
.spl-check {
  width: 22px; height: 22px; background: rgba(13,148,136,.18); border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;
}
.spl-check svg { width: 11px; height: 11px; color: var(--teal-light); stroke-width: 2.5; }
.spl-text h4 { font-family: var(--font); font-size: 14.5px; font-weight: 700; color: #fff; margin-bottom: 3px; }
.spl-text p { font-size: 13.5px; color: rgba(255,255,255,.42); line-height: 1.6; }

/* briefing preview card */
.spotlight-visual {
  background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.07);
  border-radius: 16px; padding: 24px; display: flex; flex-direction: column; gap: 12px;
}
.sv-header { display: flex; align-items: center; justify-content: space-between; }
.sv-title { font-family: var(--font); font-size: 13px; font-weight: 700; color: rgba(255,255,255,.7); }
.sv-score { display: flex; align-items: center; gap: 8px; }
.sv-score-val { font-family: var(--font); font-size: 22px; font-weight: 800; color: var(--teal-light); }
.sv-score-lbl { font-size: 11px; color: rgba(255,255,255,.3); }
.sv-kpis { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.sv-kpi {
  background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.06);
  border-radius: 10px; padding: 12px;
}
.sv-kpi-val { font-family: var(--font); font-size: 18px; font-weight: 800; color: #fff; line-height: 1; }
.sv-kpi-lbl { font-size: 10px; color: rgba(255,255,255,.3); margin-top: 4px; }
.sv-kpi-val.red   { color: #ef4444; }
.sv-kpi-val.teal  { color: var(--teal-light); }
.sv-kpi-val.amber { color: #f97316; }
.sv-kpi-val.blue  { color: #60a5fa; }
.sv-alert {
  background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.2);
  border-radius: 8px; padding: 12px 14px;
  display: flex; gap: 10px; align-items: flex-start;
}
.sv-alert-dot {
  width: 8px; height: 8px; background: #ef4444; border-radius: 50%;
  flex-shrink: 0; margin-top: 3px; box-shadow: 0 0 6px #ef4444;
}
.sv-alert-text p { font-size: 11.5px; color: rgba(255,255,255,.65); line-height: 1.5; }
.sv-alert-text strong { color: #ef4444; }
.sv-bar-row { display: flex; align-items: center; justify-content: space-between; }
.sv-bar-lbl { font-size: 10.5px; color: rgba(255,255,255,.35); }
.sv-bar-val { font-size: 10.5px; font-weight: 700; color: var(--teal-light); }
.sv-progress { height: 5px; background: rgba(255,255,255,.07); border-radius: 3px; margin-top: 5px; }
.sv-progress-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, var(--teal), var(--teal-light)); }

/* how it works */
.how { background: var(--gray-50); }
.how-steps {
  display: grid; grid-template-columns: repeat(3,1fr); gap: 32px;
  margin-top: 60px; position: relative;
}
.how-steps::before {
  content: ''; position: absolute;
  top: 28px; left: calc(16.66% + 20px); right: calc(16.66% + 20px);
  height: 1px; background: var(--gray-100);
}
.how-step { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 14px; }
.how-num {
  width: 56px; height: 56px; background: #fff; border: 2px solid var(--gray-100);
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  font-family: var(--font); font-size: 20px; font-weight: 800; color: var(--teal);
  position: relative; z-index: 1; box-shadow: 0 4px 16px rgba(0,0,0,.06);
}
.how-step h3 { font-family: var(--font); font-size: 16px; font-weight: 700; color: var(--ink); }
.how-step p { font-size: 13.5px; color: var(--gray-400); line-height: 1.68; max-width: 200px; }

/* pricing — single card, centered */
.pricing { background: var(--surface); }
.pricing-head { text-align: center; margin-bottom: 60px; }
.pricing-head .sec-sub { margin: 0 auto; }
.plan-solo {
  max-width: 440px; margin: 0 auto;
  border: 2px solid var(--teal);
  box-shadow: 0 0 0 1px var(--teal), 0 20px 60px rgba(13,148,136,.12);
  border-radius: 20px; padding: 40px;
}
.plan-badge {
  display: inline-block; background: var(--teal); color: #fff;
  font-size: 11px; font-weight: 700; padding: 3px 12px; border-radius: 100px;
  letter-spacing: .04em; text-transform: uppercase; margin-bottom: 18px;
}
.plan-name { font-family: var(--font); font-size: 24px; font-weight: 800; color: var(--ink); margin-bottom: 5px; }
.plan-desc { font-size: 13.5px; color: var(--gray-400); margin-bottom: 22px; line-height: 1.6; }
.plan-price { display: flex; align-items: baseline; gap: 3px; margin-bottom: 6px; }
.plan-price-val { font-family: var(--font); font-size: 56px; font-weight: 800; color: var(--ink); letter-spacing: -2px; line-height: 1; }
.plan-price-cur { font-size: 22px; font-weight: 700; color: var(--ink); margin-top: 4px; }
.plan-price-period { font-size: 15px; color: var(--gray-400); }
.plan-trial {
  font-size: 12.5px; color: var(--teal); background: rgba(13,148,136,.07);
  padding: 7px 12px; border-radius: 7px; margin-bottom: 24px; display: inline-block; font-weight: 500;
}
.plan-sep { height: 1px; background: var(--gray-100); margin-bottom: 22px; }
.plan-feats { list-style: none; display: flex; flex-direction: column; gap: 11px; margin-bottom: 30px; }
.plan-feats li { display: flex; align-items: center; gap: 9px; font-size: 13.5px; color: var(--gray-600); }
.plan-feats li svg { width: 16px; height: 16px; color: var(--teal); flex-shrink: 0; }
.btn-plan-solid {
  display: block; text-align: center; text-decoration: none;
  padding: 15px; border-radius: 10px; font-family: var(--font);
  font-size: 15px; font-weight: 700; transition: all .2s;
  background: var(--teal); color: #fff;
}
.btn-plan-solid:hover { background: var(--teal-dark); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(13,148,136,.25); }
.plan-note { text-align: center; margin-top: 16px; font-size: 12.5px; color: var(--gray-400); }

/* compatible strip */
.compat {
  background: linear-gradient(135deg, #052e28 0%, #0d6e5f 50%, #052e28 100%);
  padding: 72px 80px; position: relative; overflow: hidden;
}
.compat::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse at 50% -20%, rgba(255,255,255,.06) 0%, transparent 60%);
}
.compat-inner {
  max-width: 1200px; margin: 0 auto;
  display: flex; align-items: center; justify-content: space-between; gap: 60px; flex-wrap: wrap; position: relative;
}
.compat-text h2 { font-family: var(--font); font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 10px; letter-spacing: -.5px; }
.compat-text p { font-size: 15px; color: rgba(255,255,255,.6); line-height: 1.7; max-width: 440px; }
.compat-badge {
  background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
  border-radius: 14px; padding: 22px 32px; text-align: center; flex-shrink: 0;
  display: flex; flex-direction: column; align-items: center; gap: 8px;
}
.compat-badge svg { width: 32px; height: 32px; color: var(--teal-light); }
.compat-badge-lbl { font-size: 11px; color: rgba(255,255,255,.5); letter-spacing: .04em; }
.compat-badge-val { font-size: 13px; font-weight: 700; color: #fff; }

/* final CTA */
.cta {
  background: var(--ink); padding: 100px 80px; text-align: center; position: relative; overflow: hidden;
}
.cta::before {
  content: ''; position: absolute; top: -200px; left: 50%; transform: translateX(-50%);
  width: 600px; height: 600px; border-radius: 50%;
  background: radial-gradient(circle, rgba(13,148,136,.15) 0%, transparent 65%);
  pointer-events: none;
}
.cta h2 { font-family: var(--font); font-size: clamp(30px, 3.5vw, 48px); font-weight: 800; color: #fff; letter-spacing: -1px; margin-bottom: 14px; position: relative; }
.cta p { font-size: 17px; color: var(--text-dim); margin-bottom: 36px; position: relative; }
.cta-btns { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; position: relative; }
.btn-white { background: #fff; color: var(--teal); padding: 14px 32px; border-radius: 10px; font-family: var(--font); font-size: 15px; font-weight: 700; text-decoration: none; transition: all .2s; box-shadow: 0 4px 24px rgba(0,0,0,.2); }
.btn-white:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,0,0,.3); }
.btn-dark-ghost { background: rgba(255,255,255,.1); color: #fff; padding: 14px 32px; border-radius: 10px; font-family: var(--font); font-size: 15px; font-weight: 600; text-decoration: none; border: 1px solid rgba(255,255,255,.2); transition: all .2s; backdrop-filter: blur(8px); }
.btn-dark-ghost:hover { background: rgba(255,255,255,.16); }

/* footer */
.footer {
  background: #030a09; padding: 56px 80px 32px;
  display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 48px;
}
.footer-brand p { font-size: 13.5px; color: rgba(255,255,255,.28); line-height: 1.75; max-width: 210px; margin-top: 12px; }
.footer-col h4 { font-size: 11px; font-weight: 700; color: rgba(255,255,255,.3); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 16px; }
.footer-col a { display: block; color: rgba(255,255,255,.4); text-decoration: none; font-size: 13.5px; margin-bottom: 10px; transition: color .15s; }
.footer-col a:hover { color: #fff; }
.footer-bottom {
  background: #030a09; padding: 18px 80px;
  border-top: 1px solid rgba(255,255,255,.05);
  display: flex; justify-content: space-between; align-items: center;
}
.footer-bottom p { font-size: 12px; color: rgba(255,255,255,.18); }

/* responsive */
@media (max-width: 1024px) {
  .hero { grid-template-columns: 1fr; padding: 110px 40px 60px; }
  .hero-right { display: none; }
  .spotlight-inner { grid-template-columns: 1fr; }
  .spotlight-visual { display: none; }
  .feat-grid { grid-template-columns: repeat(2,1fr); }
  .footer { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
  .nav { padding: 0 20px; }
  .nav-links { display: none; }
  .hero { padding: 90px 24px 60px; }
  h1.hero-title { letter-spacing: -1px; }
  .section { padding: 64px 24px; }
  .feat-grid { grid-template-columns: 1fr; }
  .how-steps { grid-template-columns: 1fr; }
  .how-steps::before { display: none; }
  .compat { padding: 64px 24px; }
  .compat-inner { flex-direction: column; text-align: center; }
  .cta { padding: 70px 24px; }
  .footer { grid-template-columns: 1fr 1fr; padding: 48px 24px 24px; }
  .footer-bottom { padding: 16px 24px; flex-direction: column; gap: 8px; text-align: center; }
}
</style>
</head>
<body>

<div class="hero-bg"></div>
<div class="hero-grid"></div>

<!-- NAV -->
<nav class="nav" id="nav">
  <div class="nav-inner">
    <a href="/analytics/landing" class="logo">
      <div class="logo-mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
        </svg>
      </div>
      <span class="logo-name">digiMind</span>
      <span class="logo-badge">AI</span>
    </a>
    <div class="nav-links">
      <a class="nav-link" href="#features">Fonctionnalités</a>
      <a class="nav-link" href="#pricing">Tarifs</a>
      <a class="nav-link" href="#compat">Compatibilité</a>
    </div>
    <div class="nav-actions">
      <a class="btn-nav-ghost" href="/analytics/">Connexion</a>
      <a class="btn-nav-solid" href="/analytics/register">Essai gratuit</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<div class="hero">
  <div class="hero-left">
    <div class="hero-pill">
      <span class="hero-pill-dot"></span>
      Intelligence artificielle · Pharmacies
    </div>

    <h1 class="hero-title">
      Votre pharmacie<br>
      pilotée par<br>
      <em>l'intelligence.</em>
    </h1>

    <p class="hero-sub">
      Chaque matin, digiMind analyse vos données et vous livre un briefing IA complet — risques, opportunités, prévisions — pour décider en quelques secondes, pas en heures.
    </p>

    <div class="hero-ctas">
      <a href="/analytics/register" class="btn-cta-teal">
        Démarrer l'essai gratuit
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
      <a href="#features" class="btn-cta-ghost">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4V8z" fill="currentColor" stroke="none"/></svg>
        Voir les fonctionnalités
      </a>
    </div>

    <div class="hero-fine">
      <div class="fine-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        7 jours gratuits
      </div>
      <div class="fine-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
        Sans carte bancaire
      </div>
      <div class="fine-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Compatible digiPharm ERP
      </div>
    </div>
  </div>

  <!-- Dashboard mockup -->
  <div class="hero-right">
    <div class="mockup-outer">
      <div class="mockup-chrome">
        <div class="chrome-bar">
          <div class="chrome-dot cd-r"></div>
          <div class="chrome-dot cd-y"></div>
          <div class="chrome-dot cd-g"></div>
          <div class="chrome-url">pharma.digitaltechnologiescongo.com/analytics</div>
        </div>
        <div class="app-shell">
          <!-- sidebar -->
          <div class="ms">
            <div class="ms-logo">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="ms-item on">
              <svg viewBox="0 0 24 24" stroke="currentColor"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10h4v-6h6v6h4V10"/></svg>
            </div>
            <div class="ms-item">
              <svg viewBox="0 0 24 24" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <div class="ms-item">
              <svg viewBox="0 0 24 24" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </div>
            <div class="ms-item">
              <svg viewBox="0 0 24 24" stroke="currentColor"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
            </div>
            <div class="ms-item">
              <svg viewBox="0 0 24 24" stroke="currentColor"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
          </div>
          <!-- content -->
          <div class="mc">
            <div class="mc-row">
              <span class="mc-head">Briefing IA · Juillet 2026</span>
              <span class="mc-greeting">Score santé : 78/100</span>
            </div>
            <!-- KPI strip -->
            <div class="kpi-strip">
              <div class="kpi-item">
                <div class="kpi-icon-wrap red">
                  <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
                </div>
                <div class="kpi-val red">47 500 F</div>
                <div class="kpi-lbl">CA à risque</div>
              </div>
              <div class="kpi-item">
                <div class="kpi-icon-wrap teal">
                  <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
                </div>
                <div class="kpi-val teal">82 000 F</div>
                <div class="kpi-lbl">CA récupérable</div>
              </div>
              <div class="kpi-item">
                <div class="kpi-icon-wrap amber">
                  <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4-7 4A2 2 0 002 8v8"/></svg>
                </div>
                <div class="kpi-val amber">12 produits</div>
                <div class="kpi-lbl">À traiter</div>
              </div>
              <div class="kpi-item">
                <div class="kpi-icon-wrap blue">
                  <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/></svg>
                </div>
                <div class="kpi-val blue">67%</div>
                <div class="kpi-lbl">Obj. mensuel</div>
              </div>
            </div>
            <!-- tabs -->
            <div class="mc-tabs">
              <span class="mc-tab on">Ce qui menace</span>
              <span class="mc-tab">Opportunités</span>
              <span class="mc-tab">Actions</span>
              <span class="mc-tab">Prévisions</span>
            </div>
            <!-- briefing cards -->
            <div class="brief-cards">
              <div class="brief-card crit">
                <span class="bc-sev red">Critique</span>
                <div>
                  <div class="bc-title">Rupture imminente — Amoxicilline 500mg</div>
                  <div class="bc-desc">Stock restant : 4 unités · Ventes moy. : 8/j · Rupture dans 12h</div>
                  <div class="bc-impact">Impact CA : –24 000 FCFA/jour</div>
                </div>
              </div>
              <div class="brief-card warn">
                <span class="bc-sev amber">Attention</span>
                <div>
                  <div class="bc-title">3 produits expirent dans 18 jours</div>
                  <div class="bc-desc">Valeur à risque : 15 200 FCFA · Promotion recommandée</div>
                </div>
              </div>
              <div class="brief-card info">
                <span class="bc-sev teal">Info IA</span>
                <div>
                  <div class="bc-title">Probabilité objectif : 67% — Accélération possible</div>
                  <div class="bc-desc">Il reste 26 jours ce mois · CA moyen requis : 12 400 F/j</div>
                  <div class="bc-impact teal">Recommandation : Promotion Vitamines C ce week-end</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- CONTENT -->
<div class="content-wrapper">

  <!-- FEATURES -->
  <section class="section" id="features">
    <div class="section-inner">
      <div class="features-head">
        <div class="eyebrow">Ce que fait digiMind</div>
        <h2 class="sec-title">Votre analyste IA travaille<br>pendant que vous dormez.</h2>
        <p class="sec-sub">Chaque fonctionnalité est conçue pour transformer vos données brutes en décisions concrètes.</p>
      </div>
      <div class="feat-grid">
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="brain"></i></div>
          <h3>Briefing IA quotidien</h3>
          <p>Chaque matin, un résumé complet généré automatiquement : risques critiques, opportunités, actions prioritaires — classés par impact business.</p>
          <div class="feat-ai">✦ Analyse IA temps réel</div>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="triangle-alert"></i></div>
          <h3>Alertes & Risques</h3>
          <p>Détection proactive des ruptures imminentes, péremptions à risque et baisses de CA anormales. Avec le montant exact du chiffre d'affaires menacé.</p>
          <div class="feat-ai">✦ Score de confiance IA</div>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="trending-up"></i></div>
          <h3>Tendances & Prévisions</h3>
          <p>Visualisez l'évolution de vos ventes sur 7, 30 ou 90 jours. Prévision IA de votre CA fin de mois basée sur la tendance actuelle.</p>
          <div class="feat-ai">✦ Modèle prédictif</div>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="package"></i></div>
          <h3>Inventaire Intelligent</h3>
          <p>Taux de rotation par produit, identification des surplus de stock (capital immobilisé) et recommandations de commande optimisées par l'IA.</p>
          <div class="feat-ai">✦ Optimisation du stock</div>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="truck"></i></div>
          <h3>Score Fournisseurs</h3>
          <p>L'IA calcule un score de fiabilité pour chaque fournisseur (délais, taux de complétion) et recommande le meilleur pour chaque commande urgente.</p>
          <div class="feat-ai">✦ Évaluation continue</div>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="smartphone"></i></div>
          <h3>100% Mobile</h3>
          <p>Consultez votre briefing depuis votre téléphone, où que vous soyez. Interface optimisée pour mobile — aussi fluide que sur desktop.</p>
          <div class="feat-ai">✦ Responsive natif</div>
        </div>
      </div>
    </div>
  </section>

  <!-- SPOTLIGHT -->
  <div class="spotlight">
    <div class="spotlight-inner">
      <div class="spotlight-text">
        <div class="eyebrow">Briefing IA</div>
        <h2 class="sec-title">Un analyste expert, disponible à 6h du matin.</h2>
        <p class="sec-sub">Plus besoin de fouiller dans des tableurs. digiMind analyse tout, priorise ce qui compte, et vous dit exactement quoi faire.</p>
        <div class="spotlight-list">
          <div class="spl-item">
            <div class="spl-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
            <div class="spl-text">
              <h4>5 angles d'analyse</h4>
              <p>Menaces, opportunités, actions, prévisions, découvertes IA — structurés en onglets clairs.</p>
            </div>
          </div>
          <div class="spl-item">
            <div class="spl-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
            <div class="spl-text">
              <h4>Impact chiffré en FCFA</h4>
              <p>Chaque alerte quantifie précisément le chiffre d'affaires à risque ou récupérable.</p>
            </div>
          </div>
          <div class="spl-item">
            <div class="spl-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
            <div class="spl-text">
              <h4>Score de santé pharmacie</h4>
              <p>Un score global /100 qui synthétise la performance de votre pharmacie en une seule valeur.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="spotlight-visual">
        <div class="sv-header">
          <span class="sv-title">Score santé IA · Pharmacie Galy</span>
        </div>
        <div style="display:flex;align-items:center;gap:16px;padding:16px 0 8px;">
          <div class="sv-score">
            <span class="sv-score-val">78</span>
            <span class="sv-score-lbl">/ 100</span>
          </div>
          <span class="sv-badge" style="background:rgba(13,148,136,.2);color:#5eead4;font-size:11px;font-weight:700;padding:4px 12px;border-radius:100px;">Bon</span>
        </div>
        <div class="sv-kpis">
          <div class="sv-kpi">
            <div class="sv-kpi-val red">47 500 F</div>
            <div class="sv-kpi-lbl">CA à risque aujourd'hui</div>
          </div>
          <div class="sv-kpi">
            <div class="sv-kpi-val teal">82 000 F</div>
            <div class="sv-kpi-lbl">CA récupérable</div>
          </div>
          <div class="sv-kpi">
            <div class="sv-kpi-val amber">12 produits</div>
            <div class="sv-kpi-lbl">Produits à traiter</div>
          </div>
          <div class="sv-kpi">
            <div class="sv-kpi-val blue">67%</div>
            <div class="sv-kpi-lbl">Prob. objectif mensuel</div>
          </div>
        </div>
        <div class="sv-alert">
          <div class="sv-alert-dot"></div>
          <div class="sv-alert-text">
            <p><strong>Critique</strong> — Amoxicilline 500mg en rupture dans 12h.<br>
            Impact : <strong>–24 000 FCFA/jour</strong>. Commander maintenant.</p>
          </div>
        </div>
        <div>
          <div class="sv-bar-row">
            <span class="sv-bar-lbl">Probabilité objectif mensuel</span>
            <span class="sv-bar-val">67%</span>
          </div>
          <div class="sv-progress"><div class="sv-progress-fill" style="width:67%"></div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- HOW IT WORKS -->
  <section class="section how">
    <div class="section-inner" style="text-align:center;">
      <div class="eyebrow" style="justify-content:center;display:flex;">Comment ça marche</div>
      <h2 class="sec-title">Opérationnel en moins de 10 minutes.</h2>
      <div class="how-steps">
        <div class="how-step">
          <div class="how-num">1</div>
          <h3>Inscription gratuite</h3>
          <p>Formulaire simple, pas de carte bancaire. 7 jours pour tester sans risque.</p>
        </div>
        <div class="how-step">
          <div class="how-num">2</div>
          <h3>Connexion à digiPharm</h3>
          <p>digiMind se synchronise automatiquement avec vos données ERP digiPharm.</p>
        </div>
        <div class="how-step">
          <div class="how-num">3</div>
          <h3>Décidez mieux, chaque matin</h3>
          <p>Votre briefing IA vous attend dès la première synchronisation.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- PRICING -->
  <section class="section pricing" id="pricing">
    <div class="section-inner">
      <div class="pricing-head">
        <div class="eyebrow" style="justify-content:center;display:flex;">Tarif</div>
        <h2 class="sec-title" style="text-align:center;">Une formule. Tout inclus.</h2>
        <p class="sec-sub" style="margin:0 auto;">Pas de frais cachés, pas de fonctionnalités bridées. Tout digiMind pour 20$/mois.</p>
      </div>
      <div class="plan-solo">
        <div class="plan-badge">⚡ Tout inclus</div>
        <div class="plan-name">digiMind AI</div>
        <div class="plan-desc">L'intelligence artificielle complète pour piloter votre pharmacie.</div>
        <div class="plan-price">
          <span class="plan-price-cur">$</span>
          <span class="plan-price-val">20</span>
          <span class="plan-price-period">&nbsp;HT / mois</span>
        </div>
        <div class="plan-trial">🎁 7 jours d'essai gratuit — sans engagement</div>
        <div class="plan-sep"></div>
        <ul class="plan-feats">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Briefing IA quotidien complet</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Alertes & détection des risques</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Tendances & prévisions fin de mois</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Analyse inventaire intelligente</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Score de fiabilité fournisseurs</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Gestion des commandes IA</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Accès multi-utilisateurs (admin + lecteurs)</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Support prioritaire inclus</li>
        </ul>
        <a href="/analytics/register" class="btn-plan-solid">Commencer l'essai gratuit →</a>
      </div>
      <p class="plan-note">✦ &nbsp;7 jours gratuits · Sans carte bancaire · Annulation à tout moment</p>
    </div>
  </section>

  <!-- COMPAT -->
  <section class="compat" id="compat">
    <div class="compat-inner">
      <div class="compat-text">
        <h2>Conçu pour digiPharm ERP</h2>
        <p>digiMind se connecte nativement à digiPharm. Vos données de ventes, stocks et fournisseurs sont synchronisées automatiquement — sans import manuel, sans tableur.</p>
      </div>
      <div class="compat-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
        </svg>
        <div class="compat-badge-lbl">Synchronisation native avec</div>
        <div class="compat-badge-val">digiPharm ERP</div>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="cta">
    <h2>Votre pharmacie mérite<br>une intelligence artificielle.</h2>
    <p>7 jours gratuits pour découvrir digiMind. Aucune carte requise.</p>
    <div class="cta-btns">
      <a href="/analytics/register" class="btn-white">Démarrer l'essai gratuit →</a>
      <a href="mailto:service-client@digitaltechnologiescongo.com" class="btn-dark-ghost">Parler à l'équipe</a>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-brand">
      <a href="/analytics/landing" class="logo">
        <div class="logo-mark">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <span class="logo-name">digiMind</span>
      </a>
      <p>L'assistant IA analytique pour les pharmacies congolaises.</p>
    </div>
    <div class="footer-col">
      <h4>Produit</h4>
      <a href="#features">Fonctionnalités</a>
      <a href="#pricing">Tarifs</a>
      <a href="#compat">Compatibilité</a>
    </div>
    <div class="footer-col">
      <h4>Entreprise</h4>
      <a href="https://digitaltechnologiescongo.com" target="_blank">Digitech Congo</a>
      <a href="/landing">digiPharm ERP</a>
      <a href="mailto:service-client@digitaltechnologiescongo.com">Contact</a>
    </div>
    <div class="footer-col">
      <h4>Compte</h4>
      <a href="/analytics/">Connexion</a>
      <a href="/analytics/register">S'inscrire</a>
    </div>
  </footer>
  <div class="footer-bottom">
    <p>© 2026 Digital Technologies Congo · Tous droits réservés</p>
    <p>Brazzaville, République du Congo</p>
  </div>
</div>

<script>
lucide.createIcons();
const nav = document.getElementById('nav');
window.addEventListener('scroll', () => {
  nav.classList.toggle('scrolled', window.scrollY > 20);
}, { passive: true });
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const t = document.querySelector(a.getAttribute('href'));
    if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
});
</script>
</body>
</html>
