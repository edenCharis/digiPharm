<?php // Public landing page — no auth required ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>digiPharm — La gestion de pharmacie réinventée pour le Congo</title>
<meta name="description" content="Caisse, stocks, rapports et conformité SFEC. 14 jours gratuits. Conçu pour les pharmacies congolaises.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --green:       #1ea84b;
  --green-dark:  #0d6e2f;
  --green-glow:  rgba(30,168,75,.22);
  --green-light: #5edd85;
  --ink:         #080d09;
  --ink-soft:    #111a12;
  --surface:     #ffffff;
  --gray-50:     #f6f8f6;
  --gray-100:    #edf0ed;
  --gray-400:    #8a9a8d;
  --gray-600:    #4a5e4d;
  --text-dim:    rgba(255,255,255,.52);
  --text-mute:   rgba(255,255,255,.28);
  --border-dark: rgba(255,255,255,.08);
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
  background: rgba(8,13,9,.82); backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border-dark);
  transition: background .3s;
}
.nav.scrolled { background: rgba(8,13,9,.97); }
.nav-inner { max-width: 1200px; margin: 0 auto; width: 100%; display: flex; align-items: center; justify-content: space-between; }

.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.logo-mark {
  width: 34px; height: 34px; background: var(--green); border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font); font-size: 17px; font-weight: 900; color: #fff;
}
.logo-name { font-family: var(--font); font-size: 17px; font-weight: 700; color: #fff; letter-spacing: -.3px; }

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
  padding: 8px 20px; background: var(--green); border-radius: 8px;
  text-decoration: none; font-size: 13.5px; font-weight: 700; color: #fff;
  transition: all .2s;
}
.btn-nav-solid:hover { background: var(--green-dark); transform: translateY(-1px); }

/* ══════════════════════════════════════════════════════
   HERO — full viewport, dark dramatic
══════════════════════════════════════════════════════ */
.hero {
  min-height: 100vh;
  display: grid; grid-template-columns: 1fr 1fr;
  align-items: center; gap: 60px;
  padding: 100px 80px 80px;
  max-width: 1360px; margin: 0 auto;
  position: relative;
}

/* mesh gradient background — full page */
.hero-bg {
  position: fixed; inset: 0; z-index: -1; pointer-events: none;
  background: var(--ink);
  overflow: hidden;
}
.hero-bg::before {
  content: '';
  position: absolute; width: 900px; height: 900px; border-radius: 50%;
  background: radial-gradient(circle, rgba(30,168,75,.13) 0%, transparent 65%);
  top: -300px; left: -200px;
  animation: drift 12s ease-in-out infinite alternate;
}
.hero-bg::after {
  content: '';
  position: absolute; width: 600px; height: 600px; border-radius: 50%;
  background: radial-gradient(circle, rgba(30,168,75,.08) 0%, transparent 65%);
  bottom: -100px; right: 0;
  animation: drift 9s ease-in-out infinite alternate-reverse;
}
@keyframes drift { from { transform: translate(0,0); } to { transform: translate(30px,40px); } }

/* grid dot texture */
.hero-grid {
  position: fixed; inset: 0; z-index: -1; pointer-events: none;
  background-image: radial-gradient(rgba(255,255,255,.04) 1px, transparent 1px);
  background-size: 32px 32px;
  mask-image: radial-gradient(ellipse 60% 60% at 30% 50%, black 0%, transparent 100%);
}

/* hero content */
.hero-left { position: relative; z-index: 2; }

.hero-pill {
  display: inline-flex; align-items: center; gap: 8px;
  border: 1px solid rgba(30,168,75,.35); background: rgba(30,168,75,.08);
  border-radius: 100px; padding: 5px 14px 5px 8px;
  font-size: 12px; font-weight: 600; color: var(--green-light);
  letter-spacing: .04em; text-transform: uppercase; margin-bottom: 30px;
}
.hero-pill-dot {
  width: 22px; height: 22px; background: rgba(30,168,75,.2); border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}
.hero-pill-dot::after {
  content: ''; width: 7px; height: 7px; background: var(--green-light);
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
  background: linear-gradient(135deg, var(--green-light) 0%, var(--green) 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}

.hero-sub {
  font-size: 17px; color: var(--text-dim); line-height: 1.72;
  max-width: 450px; margin-bottom: 40px;
}

.hero-ctas { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 36px; }
.btn-cta-green {
  display: inline-flex; align-items: center; gap: 9px;
  background: var(--green); color: #fff; text-decoration: none;
  padding: 14px 28px; border-radius: 10px; font-family: var(--font);
  font-size: 15px; font-weight: 700;
  box-shadow: 0 0 0 0 var(--green-glow);
  transition: all .2s;
}
.btn-cta-green:hover {
  background: #23c957; transform: translateY(-2px);
  box-shadow: 0 10px 40px var(--green-glow);
}
.btn-cta-green svg { width: 16px; height: 16px; }
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
.fine-item svg { width: 13px; height: 13px; color: var(--green); opacity: .75; }

/* ══════════════════════════════════════════════════════
   DASHBOARD MOCKUP
══════════════════════════════════════════════════════ */
.hero-right { position: relative; z-index: 2; }

.mockup-outer {
  background: linear-gradient(135deg, rgba(30,168,75,.08) 0%, rgba(255,255,255,.02) 100%);
  border: 1px solid rgba(255,255,255,.07); border-radius: 18px;
  padding: 3px;
  box-shadow: 0 50px 130px rgba(0,0,0,.65), 0 0 0 1px rgba(255,255,255,.04);
  transform: perspective(1400px) rotateY(-5deg) rotateX(2deg);
  transition: transform .5s ease;
}
.mockup-outer:hover { transform: perspective(1400px) rotateY(-2deg) rotateX(0deg); }

.mockup-chrome {
  background: #131a14; border-radius: 16px; overflow: hidden;
}
.chrome-bar {
  display: flex; align-items: center; gap: 8px; padding: 10px 16px;
  background: #0e1510; border-bottom: 1px solid rgba(255,255,255,.05);
}
.chrome-dot { width: 10px; height: 10px; border-radius: 50%; }
.cd-r { background: #ff5f57; } .cd-y { background: #febc2e; } .cd-g { background: #28c840; }
.chrome-url {
  flex: 1; background: rgba(255,255,255,.05); border-radius: 5px;
  padding: 4px 12px; font-size: 10.5px; color: rgba(255,255,255,.25);
  font-family: monospace; margin: 0 10px;
}

.app-shell { display: flex; height: 330px; }

/* mini sidebar */
.ms {
  width: 52px; background: #0c1209; border-right: 1px solid rgba(255,255,255,.05);
  display: flex; flex-direction: column; align-items: center; padding: 12px 0; gap: 5px;
}
.ms-logo {
  width: 28px; height: 28px; background: var(--green); border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 900; color: #fff; margin-bottom: 12px;
}
.ms-item {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
}
.ms-item.on { background: rgba(30,168,75,.2); }
.ms-item svg { width: 14px; height: 14px; stroke: rgba(255,255,255,.22); stroke-width: 1.8; }
.ms-item.on svg { stroke: var(--green-light); }

/* mini content */
.mc { flex: 1; background: #0d1a0e; padding: 14px; overflow: hidden; }
.mc-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.mc-head { font-size: 11px; font-weight: 700; color: rgba(255,255,255,.65); font-family: var(--font); }
.mc-avatar { width: 22px; height: 22px; background: var(--green); border-radius: 50%; font-size: 9px; font-weight: 700; color: #fff; display: flex; align-items: center; justify-content: center; }

.mc-cards { display: grid; grid-template-columns: repeat(4,1fr); gap: 7px; margin-bottom: 12px; }
.mc-card {
  background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.05);
  border-radius: 8px; padding: 8px;
}
.mc-val { font-size: 14px; font-weight: 800; color: #fff; font-family: var(--font); line-height: 1; }
.mc-lbl { font-size: 8px; color: rgba(255,255,255,.28); margin-top: 3px; }
.mc-trend { font-size: 7.5px; color: var(--green-light); margin-top: 3px; }
.mc-trend.down { color: #ff7070; }

.mc-bottom { display: flex; gap: 8px; }
.mc-chart {
  flex: 1.4; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.04);
  border-radius: 8px; padding: 10px; overflow: hidden;
}
.mc-chart-lbl { font-size: 7.5px; color: rgba(255,255,255,.3); margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.bars { display: flex; align-items: flex-end; gap: 4px; height: 72px; }
.bar { flex: 1; border-radius: 3px 3px 0 0; background: rgba(30,168,75,.2); }
.bar.hi { background: var(--green); }

.mc-list {
  flex: 1; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.04);
  border-radius: 8px; padding: 8px; overflow: hidden;
}
.mc-list-lbl { font-size: 7.5px; color: rgba(255,255,255,.3); margin-bottom: 7px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.ml-row { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,.035); }
.ml-row:last-child { border: none; }
.ml-name { font-size: 8px; color: rgba(255,255,255,.45); }
.ml-val  { font-size: 8px; font-weight: 700; color: var(--green-light); }

/* ══════════════════════════════════════════════════════
   CONTENT SECTIONS — white background below hero
══════════════════════════════════════════════════════ */
.content-wrapper { background: var(--surface); }

/* ── section shared ── */
.section { padding: 96px 80px; }
.section-inner { max-width: 1200px; margin: 0 auto; }
.eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  font-size: 11.5px; font-weight: 700; color: var(--green);
  text-transform: uppercase; letter-spacing: .1em; margin-bottom: 14px;
}
.eyebrow::before { content: ''; width: 20px; height: 2px; background: var(--green); border-radius: 1px; }
h2.sec-title {
  font-family: var(--font); font-size: clamp(28px, 3vw, 42px);
  font-weight: 800; letter-spacing: -1px; line-height: 1.15;
  color: var(--ink); margin-bottom: 14px;
}
.sec-sub { font-size: 16px; color: var(--gray-400); line-height: 1.7; max-width: 500px; }

/* ── features ── */
.features { background: var(--surface); }
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
  background: linear-gradient(145deg, rgba(30,168,75,.04), transparent);
}
.feat-card:hover { border-color: rgba(30,168,75,.2); transform: translateY(-3px); box-shadow: 0 16px 48px rgba(30,168,75,.07); }
.feat-card:hover::after { opacity: 1; }
.feat-icon {
  width: 44px; height: 44px; background: rgba(30,168,75,.1); border-radius: 11px;
  display: flex; align-items: center; justify-content: center; margin-bottom: 18px;
  color: var(--green);
}
.feat-icon svg { width: 20px; height: 20px; }
.feat-card h3 { font-family: var(--font); font-size: 16px; font-weight: 700; color: var(--ink); margin-bottom: 9px; }
.feat-card p { font-size: 14px; color: var(--gray-400); line-height: 1.68; }

/* ── spotlight ── */
.spotlight { background: var(--ink); padding: 96px 80px; }
.spotlight-inner {
  max-width: 1200px; margin: 0 auto;
  display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 80px;
}
.spotlight-text .eyebrow { color: var(--green-light); }
.spotlight-text .eyebrow::before { background: var(--green-light); }
.spotlight-text h2 { color: #fff; }
.spotlight-text .sec-sub { color: rgba(255,255,255,.45); max-width: 420px; }
.spotlight-list { margin-top: 32px; display: flex; flex-direction: column; gap: 18px; }
.spl-item { display: flex; gap: 14px; align-items: flex-start; }
.spl-check {
  width: 22px; height: 22px; background: rgba(30,168,75,.18); border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;
}
.spl-check svg { width: 11px; height: 11px; color: var(--green-light); stroke-width: 2.5; }
.spl-text h4 { font-family: var(--font); font-size: 14.5px; font-weight: 700; color: #fff; margin-bottom: 3px; }
.spl-text p { font-size: 13.5px; color: rgba(255,255,255,.42); line-height: 1.6; }
.spotlight-visual {
  background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.07);
  border-radius: 16px; padding: 28px; min-height: 280px;
  display: flex; align-items: center; justify-content: center;
}
/* receipt card */
.receipt {
  background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08);
  border-radius: 12px; padding: 20px; width: 100%; max-width: 270px;
}
.receipt-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.receipt-head span { font-size: 11.5px; font-weight: 700; color: rgba(255,255,255,.65); font-family: var(--font); }
.receipt-badge { background: rgba(30,168,75,.2); color: var(--green-light); font-size: 10px; font-weight: 700; padding: 3px 9px; border-radius: 100px; }
.r-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,.05); }
.r-row:last-of-type { border: none; }
.r-name { font-size: 11px; color: rgba(255,255,255,.45); }
.r-qty  { font-size: 10.5px; color: rgba(255,255,255,.22); }
.r-price { font-size: 11px; font-weight: 600; color: rgba(255,255,255,.65); }
.r-total { display: flex; justify-content: space-between; margin-top: 12px; padding-top: 12px; border-top: 1.5px solid rgba(30,168,75,.3); }
.r-total span { font-size: 13px; font-weight: 700; color: #fff; }
.r-total .rt-val { color: var(--green-light); }

/* ── how it works ── */
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
  font-family: var(--font); font-size: 20px; font-weight: 800; color: var(--green);
  position: relative; z-index: 1; box-shadow: 0 4px 16px rgba(0,0,0,.06);
}
.how-step h3 { font-family: var(--font); font-size: 16px; font-weight: 700; color: var(--ink); }
.how-step p { font-size: 13.5px; color: var(--gray-400); line-height: 1.68; max-width: 200px; }

/* ── pricing ── */
.pricing { background: var(--surface); }
.pricing-head { text-align: center; margin-bottom: 60px; }
.pricing-head .sec-sub { margin: 0 auto; }
.plans { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; max-width: 780px; margin: 0 auto; }
.plan {
  border: 1.5px solid var(--gray-100); border-radius: 18px; padding: 34px;
  transition: all .25s;
}
.plan.featured {
  border-color: var(--green);
  box-shadow: 0 0 0 1px var(--green), 0 20px 60px rgba(30,168,75,.1);
}
.plan-badge {
  display: inline-block; background: var(--green); color: #fff;
  font-size: 11px; font-weight: 700; padding: 3px 12px; border-radius: 100px;
  letter-spacing: .04em; text-transform: uppercase; margin-bottom: 18px;
}
.plan-name { font-family: var(--font); font-size: 22px; font-weight: 800; color: var(--ink); margin-bottom: 5px; }
.plan-desc { font-size: 13.5px; color: var(--gray-400); margin-bottom: 22px; line-height: 1.6; }
.plan-price { display: flex; align-items: baseline; gap: 3px; margin-bottom: 6px; }
.plan-price-val { font-family: var(--font); font-size: 46px; font-weight: 800; color: var(--ink); letter-spacing: -2px; line-height: 1; }
.plan-price-cur { font-size: 20px; font-weight: 700; color: var(--ink); margin-top: 4px; }
.plan-price-period { font-size: 14px; color: var(--gray-400); }
.plan-trial { font-size: 12.5px; color: var(--green); background: rgba(30,168,75,.07); padding: 7px 12px; border-radius: 7px; margin-bottom: 22px; display: inline-block; font-weight: 500; }
.plan-sep { height: 1px; background: var(--gray-100); margin-bottom: 20px; }
.plan-feats { list-style: none; display: flex; flex-direction: column; gap: 10px; margin-bottom: 28px; }
.plan-feats li { display: flex; align-items: center; gap: 9px; font-size: 13.5px; color: var(--gray-600); }
.plan-feats li svg { width: 15px; height: 15px; color: var(--green); flex-shrink: 0; }
.plan-feats li.off { color: var(--gray-400); }
.plan-feats li.off svg { color: var(--gray-100); }
.btn-plan {
  display: block; text-align: center; text-decoration: none;
  padding: 13px; border-radius: 10px; font-family: var(--font);
  font-size: 14.5px; font-weight: 700; transition: all .2s;
}
.btn-plan-solid { background: var(--green); color: #fff; }
.btn-plan-solid:hover { background: var(--green-dark); transform: translateY(-1px); }
.btn-plan-outline { border: 1.5px solid var(--gray-100); color: var(--ink); }
.btn-plan-outline:hover { border-color: var(--green); color: var(--green); background: rgba(30,168,75,.04); }
.plan-note { text-align: center; margin-top: 16px; font-size: 12.5px; color: var(--gray-400); }

/* ── SFEC stripe ── */
.sfec {
  background: linear-gradient(135deg, #0a4820 0%, #0d6e2f 50%, #0a4820 100%);
  padding: 72px 80px; position: relative; overflow: hidden;
}
.sfec::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse at 50% -20%, rgba(255,255,255,.07) 0%, transparent 60%);
}
.sfec-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 60px; flex-wrap: wrap; position: relative; }
.sfec-text h2 { font-family: var(--font); font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 10px; letter-spacing: -.5px; }
.sfec-text p { font-size: 15px; color: rgba(255,255,255,.65); line-height: 1.7; max-width: 460px; }
.sfec-box {
  background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
  border-radius: 14px; padding: 24px 32px; text-align: center; flex-shrink: 0;
}
.sfec-box-icon { font-size: 32px; margin-bottom: 8px; }
.sfec-box-lbl { font-size: 11px; color: rgba(255,255,255,.6); margin-bottom: 5px; letter-spacing: .04em; }
.sfec-box-val { font-size: 13px; font-weight: 700; color: #fff; line-height: 1.5; }

/* ── final CTA ── */
.cta {
  background: var(--ink); padding: 100px 80px; text-align: center; position: relative; overflow: hidden;
}
.cta::before {
  content: ''; position: absolute; top: -200px; left: 50%; transform: translateX(-50%);
  width: 600px; height: 600px; border-radius: 50%;
  background: radial-gradient(circle, rgba(30,168,75,.15) 0%, transparent 65%);
  pointer-events: none;
}
.cta h2 { font-family: var(--font); font-size: clamp(30px, 3.5vw, 48px); font-weight: 800; color: #fff; letter-spacing: -1px; margin-bottom: 14px; position: relative; }
.cta p { font-size: 17px; color: var(--text-dim); margin-bottom: 36px; position: relative; }
.cta-btns { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; position: relative; }
.btn-white { background: #fff; color: var(--green); padding: 14px 32px; border-radius: 10px; font-family: var(--font); font-size: 15px; font-weight: 700; text-decoration: none; transition: all .2s; box-shadow: 0 4px 24px rgba(0,0,0,.2); }
.btn-white:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,0,0,.3); }
.btn-dark-ghost { background: rgba(255,255,255,.1); color: #fff; padding: 14px 32px; border-radius: 10px; font-family: var(--font); font-size: 15px; font-weight: 600; text-decoration: none; border: 1px solid rgba(255,255,255,.2); transition: all .2s; backdrop-filter: blur(8px); }
.btn-dark-ghost:hover { background: rgba(255,255,255,.16); }

/* ── footer ── */
.footer {
  background: #05080a; padding: 56px 80px 32px;
  display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 48px;
}
.footer-brand p { font-size: 13.5px; color: rgba(255,255,255,.28); line-height: 1.75; max-width: 210px; margin-top: 12px; }
.footer-col h4 { font-size: 11px; font-weight: 700; color: rgba(255,255,255,.3); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 16px; }
.footer-col a { display: block; color: rgba(255,255,255,.4); text-decoration: none; font-size: 13.5px; margin-bottom: 10px; transition: color .15s; }
.footer-col a:hover { color: #fff; }
.footer-bottom {
  background: #05080a; padding: 18px 80px;
  border-top: 1px solid rgba(255,255,255,.05);
  display: flex; justify-content: space-between; align-items: center;
}
.footer-bottom p { font-size: 12px; color: rgba(255,255,255,.18); }

/* ══════════════════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════════════════ */
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
  .plans { grid-template-columns: 1fr; }
  .how-steps { grid-template-columns: 1fr; }
  .how-steps::before { display: none; }
  .sfec { padding: 64px 24px; }
  .sfec-inner { flex-direction: column; text-align: center; }
  .cta { padding: 70px 24px; }
  .footer { grid-template-columns: 1fr 1fr; padding: 48px 24px 24px; }
  .footer-bottom { padding: 16px 24px; flex-direction: column; gap: 8px; text-align: center; }
}
</style>
</head>
<body>

<!-- background layers -->
<div class="hero-bg"></div>
<div class="hero-grid"></div>

<!-- NAV -->
<nav class="nav" id="nav">
  <div class="nav-inner">
    <a href="landing" class="logo">
      <div class="logo-mark">✚</div>
      <span class="logo-name">digiPharm</span>
    </a>
    <div class="nav-links">
      <a class="nav-link" href="#features">Fonctionnalités</a>
      <a class="nav-link" href="#pricing">Tarifs</a>
      <a class="nav-link" href="#sfec">SFEC</a>
    </div>
    <div class="nav-actions">
      <a class="btn-nav-ghost" href="login">Connexion</a>
      <a class="btn-nav-solid" href="register">Essai gratuit</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<div class="hero">
  <div class="hero-left">
    <div class="hero-pill">
      <span class="hero-pill-dot"></span>
      Spécialement conçu pour le Congo
    </div>

    <h1 class="hero-title">
      Gérez votre<br>
      pharmacie avec<br>
      <em>intelligence.</em>
    </h1>

    <p class="hero-sub">
      Caisse, stocks, équipes et conformité SFEC — centralisés en une seule plateforme. Simple à prendre en main, puissante au quotidien.
    </p>

    <div class="hero-ctas">
      <a href="register" class="btn-cta-green">
        Démarrer gratuitement
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
      <a href="#features" class="btn-cta-ghost">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4V8z" fill="currentColor" stroke="none"/></svg>
        Découvrir les fonctionnalités
      </a>
    </div>

    <div class="hero-fine">
      <div class="fine-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        14 jours gratuits
      </div>
      <div class="fine-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
        Sans carte bancaire
      </div>
      <div class="fine-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Conforme SFEC
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
          <div class="chrome-url">pharma.digitaltechnologiescongo.com/admin</div>
        </div>
        <div class="app-shell">
          <!-- mini sidebar -->
          <div class="ms">
            <div class="ms-logo">✚</div>
            <div class="ms-item on">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10h4v-6h6v6h4V10"/></svg>
            </div>
            <div class="ms-item">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            </div>
            <div class="ms-item">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
            </div>
            <div class="ms-item">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </div>
          </div>
          <!-- mini content -->
          <div class="mc">
            <div class="mc-row">
              <span class="mc-head">Tableau de bord · Juillet 2026</span>
              <div class="mc-avatar">A</div>
            </div>
            <div class="mc-cards">
              <div class="mc-card">
                <div class="mc-val">97k</div>
                <div class="mc-lbl">Revenus</div>
                <div class="mc-trend">↑ +12%</div>
              </div>
              <div class="mc-card">
                <div class="mc-val">42</div>
                <div class="mc-lbl">Ventes</div>
                <div class="mc-trend">↑ +8%</div>
              </div>
              <div class="mc-card">
                <div class="mc-val">186</div>
                <div class="mc-lbl">Produits</div>
                <div class="mc-trend">→ stable</div>
              </div>
              <div class="mc-card">
                <div class="mc-val">8</div>
                <div class="mc-lbl">Ruptures</div>
                <div class="mc-trend down">↑ +2</div>
              </div>
            </div>
            <div class="mc-bottom">
              <div class="mc-chart">
                <div class="mc-chart-lbl">Ventes / semaine</div>
                <div class="bars">
                  <div class="bar" style="height:42%"></div>
                  <div class="bar" style="height:65%"></div>
                  <div class="bar" style="height:48%"></div>
                  <div class="bar" style="height:78%"></div>
                  <div class="bar" style="height:55%"></div>
                  <div class="bar" style="height:68%"></div>
                  <div class="bar hi" style="height:92%"></div>
                </div>
              </div>
              <div class="mc-list">
                <div class="mc-list-lbl">Dernières ventes</div>
                <div class="ml-row"><span class="ml-name">Paracétamol 500mg</span><span class="ml-val">2 400 F</span></div>
                <div class="ml-row"><span class="ml-name">Amoxicilline 250mg</span><span class="ml-val">4 800 F</span></div>
                <div class="ml-row"><span class="ml-name">Ibuprofène 400mg</span><span class="ml-val">1 500 F</span></div>
                <div class="ml-row"><span class="ml-name">Metformine 500mg</span><span class="ml-val">3 200 F</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- END HERO -->

<!-- ALL BELOW-FOLD CONTENT on white background -->
<div class="content-wrapper">

  <!-- FEATURES -->
  <section class="section features" id="features">
    <div class="section-inner">
      <div class="features-head">
        <div class="eyebrow">Fonctionnalités</div>
        <h2 class="sec-title">Tout ce dont votre pharmacie<br>a besoin. Rien de plus.</h2>
        <p class="sec-sub">Un seul outil, pensé de bout en bout pour les pharmaciens congolais.</p>
      </div>
      <div class="feat-grid">
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="shopping-cart"></i></div>
          <h3>Caisse ultra-rapide</h3>
          <p>Traitez une vente en moins de 30 secondes. POS optimisé, recherche produit instantanée, rendu monnaie automatique.</p>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="package"></i></div>
          <h3>Stock intelligent</h3>
          <p>Alertes automatiques avant rupture. Suivi des entrées/sorties, historique complet, seuils personnalisables.</p>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="bar-chart-2"></i></div>
          <h3>Rapports en temps réel</h3>
          <p>Chiffre d'affaires, produits les plus vendus, marges — toujours disponibles, toujours à jour.</p>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="users"></i></div>
          <h3>Gestion des équipes</h3>
          <p>Rôles distincts : administrateur, caissier, vendeur, gestionnaire de stock. Chacun accède à ce qui le concerne.</p>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i data-lucide="truck"></i></div>
          <h3>Fournisseurs & commandes</h3>
          <p>Gérez vos fournisseurs, suivez vos approvisionnements et centralisez vos bons de commande sans papier.</p>
        </div>
        <div class="feat-card" style="border-style:dashed;background:linear-gradient(145deg,rgba(30,168,75,.02),transparent);">
          <div class="feat-icon" style="background:rgba(30,168,75,.06);">
            <i data-lucide="sparkles"></i>
          </div>
          <h3>IA Pro — Bientôt</h3>
          <p>Prédictions de stock, analyse des tendances, suggestions de commandes automatisées par intelligence artificielle.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- SPOTLIGHT -->
  <div class="spotlight">
    <div class="spotlight-inner">
      <div class="spotlight-text">
        <div class="eyebrow">Ventes simplifiées</div>
        <h2 class="sec-title">De la prescription au reçu en quelques secondes.</h2>
        <p class="sec-sub">Le vendeur cherche le produit, l'ajoute au panier — le caissier valide et le reçu est imprimé. Fluide.</p>
        <div class="spotlight-list">
          <div class="spl-item">
            <div class="spl-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
            <div class="spl-text">
              <h4>Recherche produit instantanée</h4>
              <p>Par nom commercial, DCI ou code-barre.</p>
            </div>
          </div>
          <div class="spl-item">
            <div class="spl-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
            <div class="spl-text">
              <h4>Flux vendeur / caissier</h4>
              <p>Le vendeur prépare, le caissier encaisse — sans friction.</p>
            </div>
          </div>
          <div class="spl-item">
            <div class="spl-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
            <div class="spl-text">
              <h4>Stock mis à jour en temps réel</h4>
              <p>Chaque vente décrémente automatiquement le stock.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="spotlight-visual">
        <div class="receipt">
          <div class="receipt-head">
            <span>Vente #VT-2847</span>
            <span class="receipt-badge">✓ Validée</span>
          </div>
          <div class="r-row"><span class="r-name">Paracétamol 500mg</span><span class="r-qty">×3</span><span class="r-price">2 400 F</span></div>
          <div class="r-row"><span class="r-name">Amoxicilline 250mg</span><span class="r-qty">×1</span><span class="r-price">4 800 F</span></div>
          <div class="r-row"><span class="r-name">Ibuprofène 400mg</span><span class="r-qty">×2</span><span class="r-price">3 000 F</span></div>
          <div class="r-total"><span>Total</span><span class="rt-val">10 200 FCFA</span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- HOW IT WORKS -->
  <section class="section how">
    <div class="section-inner" style="text-align:center;">
      <div class="eyebrow" style="justify-content:center;display:flex;">Comment ça marche</div>
      <h2 class="sec-title">Opérationnel en 24 heures.</h2>
      <div class="how-steps">
        <div class="how-step">
          <div class="how-num">1</div>
          <h3>Inscrivez votre pharmacie</h3>
          <p>Formulaire en 2 minutes. Aucun engagement. Aucune carte requise.</p>
        </div>
        <div class="how-step">
          <div class="how-num">2</div>
          <h3>Recevez vos accès</h3>
          <p>Notre équipe active votre compte sous 24h avec vos identifiants.</p>
        </div>
        <div class="how-step">
          <div class="how-num">3</div>
          <h3>Gérez votre pharmacie</h3>
          <p>Accédez à votre tableau de bord depuis n'importe quel appareil.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- PRICING -->
  <section class="section pricing" id="pricing">
    <div class="section-inner">
      <div class="pricing-head">
        <div class="eyebrow" style="justify-content:center;display:flex;">Tarifs</div>
        <h2 class="sec-title" style="text-align:center;">Simple et transparent.</h2>
        <p class="sec-sub" style="margin:0 auto;">14 jours d'essai sur les deux formules. Aucune carte bancaire requise.</p>
      </div>
      <div class="plans">
        <div class="plan">
          <div class="plan-name">Basique</div>
          <div class="plan-desc">L'essentiel pour gérer votre pharmacie au quotidien.</div>
          <div class="plan-price">
            <span class="plan-price-cur">$</span>
            <span class="plan-price-val">10</span>
            <span class="plan-price-period">&nbsp;HT / mois</span>
          </div>
          <div class="plan-trial">🎁 14 jours gratuits — sans engagement</div>
          <div class="plan-sep"></div>
          <ul class="plan-feats">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Caisse & point de vente</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Gestion des stocks & alertes</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Multi-rôles (admin, vendeur, caissier)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Rapports journaliers & mensuels</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Support par email</li>
            <li class="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg> Intelligence artificielle</li>
          </ul>
          <a href="register?plan=basic" class="btn-plan btn-plan-outline">Commencer l'essai gratuit</a>
        </div>
        <div class="plan featured">
          <div class="plan-badge">⭐ Recommandé</div>
          <div class="plan-name">Pro + IA</div>
          <div class="plan-desc">Toutes les fonctionnalités Basique, plus l'intelligence artificielle.</div>
          <div class="plan-price">
            <span class="plan-price-cur">$</span>
            <span class="plan-price-val">25</span>
            <span class="plan-price-period">&nbsp;HT / mois</span>
          </div>
          <div class="plan-trial">🎁 14 jours gratuits — sans engagement</div>
          <div class="plan-sep"></div>
          <ul class="plan-feats">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> <strong>Tout le forfait Basique</strong></li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> IA — Prédictions de stock</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> IA — Analyse des tendances</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> IA — Suggestions de commandes auto</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Facturation SFEC certifiée</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg> Support prioritaire 24h/24</li>
          </ul>
          <a href="register?plan=pro" class="btn-plan btn-plan-solid">Commencer l'essai gratuit</a>
        </div>
      </div>
      <p class="plan-note">✦ &nbsp;14 jours gratuits · Sans carte bancaire · Annulation à tout moment</p>
    </div>
  </section>

  <!-- SFEC -->
  <section class="sfec" id="sfec">
    <div class="sfec-inner">
      <div class="sfec-text">
        <h2>Application conforme SFEC</h2>
        <p>digiPharm est intégré au Système de Facturation Électronique Certifiée du gouvernement de la République du Congo. Chaque reçu est certifié en temps réel avec QR code officiel.</p>
      </div>
      <div class="sfec-box">
        <div class="sfec-box-icon">🏛️</div>
        <div class="sfec-box-lbl">Certifié par</div>
        <div class="sfec-box-val">République du Congo<br>Direction Générale des Impôts</div>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="cta">
    <h2>Prêt à moderniser votre pharmacie ?</h2>
    <p>Rejoignez digiPharm et profitez de 14 jours d'essai gratuit, sans engagement.</p>
    <div class="cta-btns">
      <a href="register" class="btn-white">Inscription gratuite →</a>
      <a href="mailto:support@digitaltechnologiescongo.com" class="btn-dark-ghost">Parler à l'équipe</a>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-brand">
      <a href="landing" class="logo">
        <div class="logo-mark">✚</div>
        <span class="logo-name">digiPharm</span>
      </a>
      <p>La solution de gestion de pharmacie nouvelle génération pour le Congo.</p>
    </div>
    <div class="footer-col">
      <h4>Produit</h4>
      <a href="#features">Fonctionnalités</a>
      <a href="#pricing">Tarifs</a>
      <a href="#sfec">SFEC</a>
    </div>
    <div class="footer-col">
      <h4>Entreprise</h4>
      <a href="https://digitaltechnologiescongo.com" target="_blank">Digitech Congo</a>
      <a href="mailto:support@digitaltechnologiescongo.com">Contact</a>
    </div>
    <div class="footer-col">
      <h4>Compte</h4>
      <a href="login">Connexion</a>
      <a href="register">S'inscrire</a>
    </div>
  </footer>
  <div class="footer-bottom">
    <p>© 2026 Digital Technologies Congo · Tous droits réservés</p>
    <p>Brazzaville, République du Congo</p>
  </div>

</div><!-- /content-wrapper -->

<script>
lucide.createIcons();

// Sticky nav
const nav = document.getElementById('nav');
window.addEventListener('scroll', () => {
  nav.classList.toggle('scrolled', window.scrollY > 20);
}, { passive: true });

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const t = document.querySelector(a.getAttribute('href'));
    if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
});
</script>
</body>
</html>
