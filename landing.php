<?php
// Public landing page — no auth required
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>digiPharm — La gestion de pharmacie réinventée pour le Congo</title>
<meta name="description" content="Caisse, stocks, rapports et conformité SFEC. Tout en un. 14 jours gratuits.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --green:#188038;--green-dark:#0d652d;--green-bg:#e6f4ea;--green-light:#f0faf3;
  --border:#dadce0;--border-light:#e8eaed;
  --text-900:#202124;--text-600:#5f6368;--text-400:#80868b;
  --surface:#ffffff;--surface-alt:#f8f9fa;
  --radius:12px;
  --shadow:0 1px 3px rgba(0,0,0,.10),0 1px 2px rgba(0,0,0,.06);
  --shadow-md:0 4px 16px rgba(0,0,0,.10);
  --shadow-lg:0 8px 40px rgba(0,0,0,.13);
}
html{scroll-behavior:smooth}
body{font-family:'Roboto',system-ui,sans-serif;color:var(--text-900);background:var(--surface);line-height:1.6;-webkit-font-smoothing:antialiased}

/* ─── NAV ─── */
.nav{
  position:fixed;top:0;left:0;right:0;z-index:200;
  background:rgba(255,255,255,.95);backdrop-filter:blur(16px);
  border-bottom:1px solid var(--border-light);
  height:64px;display:flex;align-items:center;padding:0 40px;
}
.nav-inner{max-width:1180px;margin:0 auto;width:100%;display:flex;align-items:center;justify-content:space-between}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--text-900)}
.nav-logo-icon{
  width:36px;height:36px;background:var(--green);border-radius:9px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.nav-logo-name{font-size:18px;font-weight:700;letter-spacing:-.3px}
.nav-logo-name span{color:var(--green)}
.nav-actions{display:flex;align-items:center;gap:8px}
.nav-link{
  padding:8px 14px;border-radius:8px;text-decoration:none;
  color:var(--text-600);font-size:14px;font-weight:500;transition:background .15s;
}
.nav-link:hover{background:var(--surface-alt);color:var(--text-900)}
.btn-nav{
  padding:9px 20px;background:var(--green);color:#fff;
  border-radius:9px;text-decoration:none;font-size:14px;font-weight:500;
  display:flex;align-items:center;gap:6px;transition:background .15s;
}
.btn-nav:hover{background:var(--green-dark)}
.btn-nav svg{width:14px;height:14px}

/* ─── HERO ─── */
.hero-section{
  padding:120px 40px 80px;
  max-width:1180px;margin:0 auto;
  display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;
}
.hero-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:var(--green-bg);color:var(--green);
  padding:5px 14px;border-radius:100px;font-size:13px;font-weight:500;
  margin-bottom:22px;
}
.hero-badge svg{width:13px;height:13px}
.hero-title{
  font-size:clamp(34px,4vw,54px);font-weight:700;
  line-height:1.13;letter-spacing:-1.2px;
  color:var(--text-900);margin-bottom:20px;
}
.hero-title span{color:var(--green)}
.hero-sub{font-size:17px;color:var(--text-600);line-height:1.72;margin-bottom:36px;max-width:460px}
.hero-ctas{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.btn-primary-lg{
  padding:14px 26px;background:var(--green);color:#fff;border-radius:10px;
  text-decoration:none;font-size:15px;font-weight:500;
  display:inline-flex;align-items:center;gap:8px;
  box-shadow:0 2px 8px rgba(24,128,56,.28);transition:all .2s;
}
.btn-primary-lg:hover{background:var(--green-dark);box-shadow:0 4px 18px rgba(24,128,56,.38);transform:translateY(-1px)}
.btn-primary-lg svg{width:16px;height:16px}
.btn-outline-lg{
  padding:14px 26px;border:1.5px solid var(--border);color:var(--text-600);
  border-radius:10px;text-decoration:none;font-size:15px;font-weight:500;
  transition:all .2s;
}
.btn-outline-lg:hover{border-color:var(--green);color:var(--green);background:var(--green-light)}
.hero-fine{
  margin-top:16px;font-size:13px;color:var(--text-400);
  display:flex;align-items:center;gap:7px;
}
.hero-fine svg{width:13px;height:13px;color:var(--green)}

/* ─── DASHBOARD MOCKUP ─── */
.mockup-wrap{position:relative}
.mockup-glow{
  position:absolute;inset:-20px;z-index:-1;
  background:radial-gradient(ellipse at 60% 50%, rgba(24,128,56,.10) 0%, transparent 70%);
  border-radius:24px;
}
.mockup-frame{
  background:#f1f3f4;border-radius:16px;padding:12px;
  box-shadow:var(--shadow-lg),0 0 0 1px rgba(0,0,0,.05);
}
.mockup-dots{display:flex;gap:6px;margin-bottom:10px}
.mockup-dots span{width:10px;height:10px;border-radius:50%}
.d1{background:#ff5f57}.d2{background:#febc2e}.d3{background:#28c840}
.mockup-window{background:#fff;border-radius:10px;overflow:hidden;border:1px solid #e0e0e0}
.mockup-bar{
  background:#f8f9fa;border-bottom:1px solid #e8eaed;
  padding:8px 12px;display:flex;align-items:center;gap:8px;
}
.mockup-url{
  background:#fff;border:1px solid #dadce0;border-radius:20px;
  padding:4px 14px;font-size:11px;color:#5f6368;flex:1;text-align:center;
}
.mockup-body{display:flex;height:260px}
.mockup-sidebar{
  width:54px;background:#f8f9fa;border-right:1px solid #e8eaed;
  padding:10px 7px;display:flex;flex-direction:column;gap:6px;align-items:center;
}
.m-nav{
  width:36px;height:34px;border-radius:8px;
  display:flex;align-items:center;justify-content:center;
}
.m-nav svg{width:15px;height:15px;color:#80868b;stroke-width:1.8}
.m-nav.active{background:#e6f4ea}
.m-nav.active svg{color:#188038}
.mockup-content{flex:1;padding:14px;overflow:hidden}
.m-title{font-size:12px;font-weight:600;color:#202124;margin-bottom:10px}
.m-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-bottom:10px}
.m-stat{background:#f8f9fa;border-radius:7px;padding:9px;border:1px solid #e8eaed}
.m-stat-v{font-size:14px;font-weight:700;color:#202124}
.m-stat-l{font-size:9px;color:#5f6368;margin-top:2px}
.m-stat-s{font-size:9px;color:#188038;margin-top:3px}
.m-stat-s.warn{color:#f59e0b}
.m-thead{
  display:grid;grid-template-columns:2fr 1fr 1fr;
  padding:5px 7px;font-size:9px;color:#80868b;text-transform:uppercase;
  border-bottom:1px solid #e8eaed;letter-spacing:.4px;
}
.m-row{
  display:grid;grid-template-columns:2fr 1fr 1fr;
  padding:5px 7px;font-size:10px;color:#202124;
  border-bottom:1px solid #f1f3f4;align-items:center;
}
.m-badge{
  font-size:9px;padding:2px 7px;border-radius:100px;
  font-weight:500;display:inline-block;
}
.m-badge.ok{background:#e6f4ea;color:#188038}
.m-badge.warn{background:#fef3c7;color:#92400e}
.m-badge.err{background:#fce8e6;color:#d93025}

/* ─── TRUST ─── */
.trust-bar{
  border-top:1px solid var(--border);border-bottom:1px solid var(--border);
  background:var(--surface-alt);padding:18px 40px;text-align:center;
}
.trust-bar p{
  font-size:13px;color:var(--text-400);
  display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;
  max-width:1180px;margin:0 auto;
}
.trust-bar svg{color:var(--green);width:14px;height:14px;flex-shrink:0}
.tdot{color:var(--border-light)}

/* ─── SECTION ─── */
.section{padding:88px 40px}
.section-inner{max-width:1180px;margin:0 auto}
.section-header{text-align:center;margin-bottom:56px}
.eyebrow{
  font-size:12px;font-weight:600;color:var(--green);
  text-transform:uppercase;letter-spacing:1.2px;margin-bottom:12px;
}
.section-title{font-size:34px;font-weight:700;letter-spacing:-.5px;margin-bottom:14px}
.section-sub{font-size:16px;color:var(--text-600);max-width:520px;margin:0 auto;line-height:1.7}

/* ─── FEATURES ─── */
.features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
.feat-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);padding:30px;
  transition:box-shadow .2s,transform .2s;
}
.feat-card:hover{box-shadow:var(--shadow-md);transform:translateY(-3px)}
.feat-icon{
  width:46px;height:46px;border-radius:11px;background:var(--green-bg);
  display:flex;align-items:center;justify-content:center;
  margin-bottom:18px;color:var(--green);
}
.feat-icon svg{width:21px;height:21px}
.feat-title{font-size:16px;font-weight:600;margin-bottom:9px;color:var(--text-900)}
.feat-desc{font-size:14px;color:var(--text-600);line-height:1.68}

/* ─── PRICING ─── */
.pricing-section{background:var(--surface-alt)}
.plans-grid{
  display:grid;grid-template-columns:1fr 1fr;
  gap:24px;max-width:820px;margin:0 auto;
}
.plan-card{
  background:var(--surface);border:1.5px solid var(--border);
  border-radius:16px;padding:36px;position:relative;
  transition:box-shadow .2s;
}
.plan-card:hover{box-shadow:var(--shadow-md)}
.plan-card.featured{
  border-color:var(--green);
  box-shadow:0 0 0 1px var(--green),var(--shadow-md);
}
.plan-badge{
  position:absolute;top:-14px;left:50%;transform:translateX(-50%);
  background:var(--green);color:#fff;
  padding:4px 18px;border-radius:100px;font-size:12px;font-weight:600;
  white-space:nowrap;letter-spacing:.3px;
}
.plan-name{font-size:13px;font-weight:500;color:var(--text-400);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px}
.plan-price{font-size:46px;font-weight:700;letter-spacing:-2px;line-height:1}
.plan-price sup{font-size:20px;font-weight:500;vertical-align:top;margin-top:9px;display:inline-block}
.plan-price .unit{font-size:15px;font-weight:400;color:var(--text-400);letter-spacing:0}
.plan-period{font-size:12px;color:var(--text-400);margin-top:5px;margin-bottom:20px}
.plan-trial{
  background:var(--green-bg);color:var(--green);
  font-size:13px;font-weight:500;padding:8px 12px;
  border-radius:8px;margin-bottom:22px;text-align:center;
}
.plan-sep{height:1px;background:var(--border);margin-bottom:20px}
.plan-feats{list-style:none;margin-bottom:28px;display:flex;flex-direction:column;gap:1px}
.plan-feats li{
  display:flex;align-items:flex-start;gap:9px;
  font-size:13.5px;color:var(--text-600);padding:5px 0;
}
.plan-feats li svg{width:15px;height:15px;color:var(--green);flex-shrink:0;margin-top:2px}
.plan-feats li.off{color:var(--text-400)}
.plan-feats li.off svg{color:var(--border)}
.btn-plan{
  display:block;width:100%;padding:13px;
  border-radius:10px;font-size:15px;font-weight:500;
  text-align:center;text-decoration:none;transition:all .2s;cursor:pointer;
}
.btn-plan.outline{border:1.5px solid var(--border);color:var(--text-600);background:transparent}
.btn-plan.outline:hover{border-color:var(--green);color:var(--green);background:var(--green-light)}
.btn-plan.solid{background:var(--green);color:#fff;box-shadow:0 2px 8px rgba(24,128,56,.28)}
.btn-plan.solid:hover{background:var(--green-dark)}

/* ─── SFEC STRIPE ─── */
.sfec-section{
  background:linear-gradient(135deg,#1a6b3a 0%,#0d652d 100%);
  color:#fff;padding:64px 40px;
}
.sfec-inner{
  max-width:1180px;margin:0 auto;
  display:flex;align-items:center;justify-content:space-between;
  gap:48px;flex-wrap:wrap;
}
.sfec-text h2{font-size:27px;font-weight:700;margin-bottom:10px}
.sfec-text p{font-size:15px;opacity:.85;max-width:480px;line-height:1.65}
.sfec-box{
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);
  border-radius:16px;padding:28px 36px;text-align:center;min-width:190px;flex-shrink:0;
}
.sfec-box-icon{font-size:36px;margin-bottom:10px}
.sfec-box-lbl{font-size:12px;opacity:.75;margin-bottom:4px;letter-spacing:.3px}
.sfec-box-val{font-size:14px;font-weight:700;line-height:1.45}

/* ─── HOW ─── */
.how-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:40px;counter-reset:steps}
.how-step{position:relative;padding-left:52px}
.how-step::before{
  counter-increment:steps;content:counter(steps);
  position:absolute;left:0;top:2px;
  width:36px;height:36px;border-radius:50%;
  background:var(--green-bg);color:var(--green);
  font-size:14px;font-weight:700;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.how-step h3{font-size:16px;font-weight:600;margin-bottom:8px}
.how-step p{font-size:14px;color:var(--text-600);line-height:1.65}

/* ─── CTA FINAL ─── */
.cta-section{
  padding:100px 40px;text-align:center;
  background:var(--surface);
}
.cta-title{font-size:38px;font-weight:700;letter-spacing:-.5px;margin-bottom:14px}
.cta-sub{font-size:17px;color:var(--text-600);margin-bottom:36px;max-width:480px;margin-left:auto;margin-right:auto}
.cta-fine{margin-top:16px;font-size:13px;color:var(--text-400)}

/* ─── FOOTER ─── */
.footer{
  border-top:1px solid var(--border);background:var(--surface-alt);
  padding:32px 40px;
}
.footer-inner{
  max-width:1180px;margin:0 auto;
  display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:16px;
}
.footer-logo{display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--text-600)}
.footer-logo-icon{width:26px;height:26px;background:var(--green);border-radius:6px;display:flex;align-items:center;justify-content:center}
.footer-logo-name{font-size:14px;font-weight:600}
.footer-links{display:flex;gap:24px}
.footer-links a{color:var(--text-400);text-decoration:none;font-size:13px}
.footer-links a:hover{color:var(--green)}
.footer-copy{font-size:12px;color:var(--text-400)}

/* ─── RESPONSIVE ─── */
@media(max-width:960px){
  .features-grid{grid-template-columns:repeat(2,1fr)}
  .how-grid{grid-template-columns:1fr}
}
@media(max-width:768px){
  .nav{padding:0 20px}
  .nav-link{display:none}
  .hero-section{grid-template-columns:1fr;padding:100px 24px 60px;gap:40px}
  .hero-title{font-size:32px}
  .plans-grid{grid-template-columns:1fr}
  .section{padding:60px 24px}
  .sfec-inner{flex-direction:column;text-align:center}
  .features-grid{grid-template-columns:1fr}
  .cta-section{padding:70px 24px}
  .footer-inner{flex-direction:column;align-items:flex-start;gap:12px}
}
</style>
</head>
<body>

<!-- ─── NAV ─── -->
<nav class="nav">
  <div class="nav-inner">
    <a href="landing.php" class="nav-logo">
      <div class="nav-logo-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
      </div>
      <span class="nav-logo-name">digi<span>Pharm</span></span>
    </a>
    <div class="nav-actions">
      <a href="#features" class="nav-link">Fonctionnalités</a>
      <a href="#pricing" class="nav-link">Tarifs</a>
      <a href="index.php" class="nav-link">Connexion</a>
      <a href="register-pharmacy.php" class="btn-nav">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        Essai gratuit
      </a>
    </div>
  </div>
</nav>

<!-- ─── HERO ─── -->
<div class="hero-section">
  <div>
    <div class="hero-badge">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Conçu pour les pharmacies du Congo
    </div>
    <h1 class="hero-title">La gestion de pharmacie,<br><span>réinventée.</span></h1>
    <p class="hero-sub">Caisse, stocks, fournisseurs, rapports et conformité SFEC — tout en une seule plateforme. Simple, rapide, taillée pour les pharmacies congolaises.</p>
    <div class="hero-ctas">
      <a href="register-pharmacy.php" class="btn-primary-lg">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
        Démarrer gratuitement
      </a>
      <a href="#pricing" class="btn-outline-lg">Voir les tarifs</a>
    </div>
    <p class="hero-fine">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      14 jours gratuits · Aucune carte bancaire
    </p>
  </div>

  <!-- Dashboard mockup -->
  <div class="mockup-wrap">
    <div class="mockup-glow"></div>
    <div class="mockup-frame">
      <div class="mockup-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
      <div class="mockup-window">
        <div class="mockup-bar">
          <div class="mockup-url">pharma.digitaltechnologiescongo.com/admin</div>
        </div>
        <div class="mockup-body">
          <div class="mockup-sidebar">
            <div class="m-nav active">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </div>
            <div class="m-nav">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            </div>
            <div class="m-nav">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
            </div>
            <div class="m-nav">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </div>
            <div class="m-nav">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/></svg>
            </div>
          </div>
          <div class="mockup-content">
            <div class="m-title">Tableau de bord — Juillet 2026</div>
            <div class="m-stats">
              <div class="m-stat">
                <div class="m-stat-v">482 500</div>
                <div class="m-stat-l">CA (FCFA)</div>
                <div class="m-stat-s">↑ +12% ce mois</div>
              </div>
              <div class="m-stat">
                <div class="m-stat-v">47</div>
                <div class="m-stat-l">Ventes aujourd'hui</div>
                <div class="m-stat-s">↑ +5 vs hier</div>
              </div>
              <div class="m-stat">
                <div class="m-stat-v">238</div>
                <div class="m-stat-l">Produits en stock</div>
                <div class="m-stat-s warn">⚠ 4 en rupture</div>
              </div>
            </div>
            <div class="m-thead"><span>Produit</span><span>Stock</span><span>Statut</span></div>
            <div class="m-row"><span>Doliprane 1000mg</span><span>145</span><span><span class="m-badge ok">En stock</span></span></div>
            <div class="m-row"><span>Amoxicilline 500mg</span><span>3</span><span><span class="m-badge warn">Faible</span></span></div>
            <div class="m-row"><span>Ibuprofen 400mg</span><span>78</span><span><span class="m-badge ok">En stock</span></span></div>
            <div class="m-row"><span>Metformine 850mg</span><span>0</span><span><span class="m-badge err">Rupture</span></span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ─── TRUST BAR ─── -->
<div class="trust-bar">
  <p>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    Données hébergées en Afrique Centrale
    <span class="tdot">·</span>
    Certifié SFEC par la DGI Congo
    <span class="tdot">·</span>
    Interface 100% en français
    <span class="tdot">·</span>
    Support local réactif
    <span class="tdot">·</span>
    Accessible sur mobile, tablette et PC
  </p>
</div>

<!-- ─── FEATURES ─── -->
<section class="section" id="features">
  <div class="section-inner">
    <div class="section-header">
      <div class="eyebrow">Fonctionnalités</div>
      <h2 class="section-title">Tout ce dont votre pharmacie a besoin</h2>
      <p class="section-sub">Un seul outil, pensé de bout en bout pour les pharmaciens congolais.</p>
    </div>
    <div class="features-grid">
      <div class="feat-card">
        <div class="feat-icon"><i data-lucide="shopping-cart"></i></div>
        <div class="feat-title">Caisse ultra-rapide</div>
        <p class="feat-desc">Traitez une vente en moins de 30 secondes. Interface POS optimisée, recherche par nom ou code-barre, calcul automatique du rendu monnaie.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><i data-lucide="package"></i></div>
        <div class="feat-title">Stock intelligent</div>
        <p class="feat-desc">Alertes automatiques avant rupture de stock. Suivi des entrées et sorties, historique complet des mouvements, seuils personnalisables.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><i data-lucide="bar-chart-2"></i></div>
        <div class="feat-title">Rapports en temps réel</div>
        <p class="feat-desc">Chiffre d'affaires journalier et mensuel, produits les plus vendus, marges et bénéfices. Toujours disponibles, toujours à jour.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><i data-lucide="users"></i></div>
        <div class="feat-title">Gestion des équipes</div>
        <p class="feat-desc">Rôles distincts : administrateur, caissier, vendeur, gestionnaire de stock. Chacun accède uniquement à ce qui le concerne.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><i data-lucide="truck"></i></div>
        <div class="feat-title">Fournisseurs & commandes</div>
        <p class="feat-desc">Gérez vos fournisseurs, suivez vos approvisionnements et centralisez tous vos bons de commande sans papier.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><i data-lucide="file-check-2"></i></div>
        <div class="feat-title">Facturation SFEC certifiée</div>
        <p class="feat-desc">Chaque vente est certifiée auprès de la Direction Générale des Impôts. Reçus conformes avec numéro de certification et QR code officiel.</p>
      </div>
    </div>
  </div>
</section>

<!-- ─── PRICING ─── -->
<section class="section pricing-section" id="pricing">
  <div class="section-inner">
    <div class="section-header">
      <div class="eyebrow">Tarifs</div>
      <h2 class="section-title">Simple et transparent</h2>
      <p class="section-sub">14 jours d'essai gratuit sur les deux forfaits. Sans engagement, sans carte bancaire requise.</p>
    </div>
    <div class="plans-grid">

      <!-- BASIC -->
      <div class="plan-card">
        <div class="plan-name">Basique</div>
        <div class="plan-price"><sup>$</sup>10<span class="unit"> /mois</span></div>
        <div class="plan-period">Hors taxes · Facturation mensuelle</div>
        <div class="plan-trial">🎁 14 jours gratuits — aucune carte requise</div>
        <div class="plan-sep"></div>
        <ul class="plan-feats">
          <li><i data-lucide="check"></i> Caisse & point de vente</li>
          <li><i data-lucide="check"></i> Gestion des stocks & alertes</li>
          <li><i data-lucide="check"></i> Catalogue produits & catégories</li>
          <li><i data-lucide="check"></i> Gestion des fournisseurs</li>
          <li><i data-lucide="check"></i> Rapports journaliers & mensuels</li>
          <li><i data-lucide="check"></i> Impression de reçus</li>
          <li><i data-lucide="check"></i> Jusqu'à <strong>3 utilisateurs</strong></li>
          <li><i data-lucide="check"></i> Support par email</li>
          <li class="off"><i data-lucide="minus"></i> Intelligence artificielle</li>
          <li class="off"><i data-lucide="minus"></i> Facturation SFEC certifiée</li>
        </ul>
        <a href="register-pharmacy.php?plan=basic" class="btn-plan outline">Commencer l'essai gratuit</a>
      </div>

      <!-- PRO -->
      <div class="plan-card featured">
        <div class="plan-badge">⭐ Recommandé</div>
        <div class="plan-name">Pro + IA</div>
        <div class="plan-price"><sup>$</sup>25<span class="unit"> /mois</span></div>
        <div class="plan-period">Hors taxes · Facturation mensuelle</div>
        <div class="plan-trial">🎁 14 jours gratuits — aucune carte requise</div>
        <div class="plan-sep"></div>
        <ul class="plan-feats">
          <li><i data-lucide="check"></i> <strong>Tout ce qui est dans Basique</strong></li>
          <li><i data-lucide="sparkles"></i> IA — Prévision des réapprovisionnements</li>
          <li><i data-lucide="sparkles"></i> IA — Détection d'anomalies de ventes</li>
          <li><i data-lucide="sparkles"></i> IA — Analyse des tendances & alertes</li>
          <li><i data-lucide="check"></i> Facturation <strong>SFEC certifiée</strong> (Congo)</li>
          <li><i data-lucide="check"></i> Rapports avancés exportables PDF/Excel</li>
          <li><i data-lucide="check"></i> Jusqu'à <strong>15 utilisateurs</strong></li>
          <li><i data-lucide="check"></i> Support prioritaire (réponse &lt; 24h)</li>
          <li><i data-lucide="check"></i> Tableau de bord analytique avancé</li>
          <li><i data-lucide="check"></i> Accès aux nouvelles fonctionnalités en avant-première</li>
        </ul>
        <a href="register-pharmacy.php?plan=pro" class="btn-plan solid">Commencer l'essai gratuit</a>
      </div>

    </div>
  </div>
</section>

<!-- ─── SFEC ─── -->
<section class="sfec-section">
  <div class="sfec-inner">
    <div class="sfec-text">
      <h2>Application conforme SFEC</h2>
      <p>digiPharm est intégré au Système de Facturation Électronique Certifiée du gouvernement de la République du Congo. Chaque reçu est certifié en temps réel avec un QR code officiel et un numéro unique de certification.</p>
    </div>
    <div class="sfec-box">
      <div class="sfec-box-icon">🏛️</div>
      <div class="sfec-box-lbl">Certifié par</div>
      <div class="sfec-box-val">République du Congo<br>Direction Générale<br>des Impôts</div>
    </div>
  </div>
</section>

<!-- ─── HOW IT WORKS ─── -->
<section class="section">
  <div class="section-inner">
    <div class="section-header">
      <div class="eyebrow">Comment ça marche</div>
      <h2 class="section-title">Opérationnel en 5 minutes</h2>
      <p class="section-sub">Pas de formation nécessaire. Pas de technicien à appeler.</p>
    </div>
    <div class="how-grid">
      <div class="how-step">
        <h3>Créez votre compte</h3>
        <p>Remplissez le formulaire en 2 minutes. Choisissez votre forfait et démarrez votre essai gratuit immédiatement — aucune carte bancaire requise.</p>
      </div>
      <div class="how-step">
        <h3>Configurez votre pharmacie</h3>
        <p>Ajoutez vos produits, vos utilisateurs et vos fournisseurs. Un assistant de démarrage vous guide étape par étape.</p>
      </div>
      <div class="how-step">
        <h3>Gérez en toute sérénité</h3>
        <p>Ventes, stocks, rapports — tout est centralisé. Vos équipes accèdent à leurs outils depuis n'importe quel appareil.</p>
      </div>
    </div>
  </div>
</section>

<!-- ─── CTA FINAL ─── -->
<section class="cta-section">
  <div class="eyebrow" style="text-align:center">Prêt à commencer ?</div>
  <h2 class="cta-title">Modernisez votre pharmacie aujourd'hui</h2>
  <p class="cta-sub">Rejoignez les premières pharmacies congolaises à offrir une expérience entièrement digitale à leurs clients.</p>
  <a href="register-pharmacy.php" class="btn-primary-lg" style="display:inline-flex">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
    Créer votre compte gratuitement
  </a>
  <p class="cta-fine">14 jours gratuits · Aucune carte bancaire · Résiliable à tout moment</p>
</section>

<!-- ─── FOOTER ─── -->
<footer class="footer">
  <div class="footer-inner">
    <a href="landing.php" class="footer-logo">
      <div class="footer-logo-icon">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
      </div>
      <span class="footer-logo-name">digiPharm</span>
    </a>
    <div class="footer-links">
      <a href="#features">Fonctionnalités</a>
      <a href="#pricing">Tarifs</a>
      <a href="index.php">Connexion</a>
      <a href="register-pharmacy.php">S'inscrire</a>
    </div>
    <div class="footer-copy">© 2026 Digital Technologies Congo · Tous droits réservés</div>
  </div>
</footer>

<script>
lucide.createIcons();
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const t = document.querySelector(a.getAttribute('href'));
    if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
});
// Sticky nav shadow on scroll
const nav = document.querySelector('.nav');
window.addEventListener('scroll', () => {
  nav.style.boxShadow = window.scrollY > 10 ? '0 1px 8px rgba(0,0,0,.10)' : '';
}, { passive: true });
</script>
</body>
</html>
