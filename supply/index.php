<?php
/**
 * digiSupply landing page — shown if no token in URL.
 * Suppliers always arrive via emailed link (order.php?token=xxx).
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>digiSupply — Portail fournisseur</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--blue:#1e40af;--blue-dark:#1e3a8a;--blue-light:#dbeafe;--text:#111827;--muted:#6b7280;--border:#e5e7eb;--bg:#f8fafc}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:48px 40px;max-width:440px;width:100%;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.06)}
.logo{font-size:22px;font-weight:800;color:var(--blue);letter-spacing:-0.5px;margin-bottom:32px}
.logo span{color:var(--text)}
.icon{font-size:48px;margin-bottom:24px}
h1{font-size:22px;font-weight:700;color:var(--text);margin-bottom:12px}
p{color:var(--muted);font-size:14px;line-height:1.7;margin-bottom:24px}
.token-form input{width:100%;padding:12px 16px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;margin-bottom:12px;color:var(--text)}
.token-form input:focus{outline:none;border-color:var(--blue)}
.btn{width:100%;padding:13px;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer}
.btn:hover{background:var(--blue-dark)}
.footer{margin-top:32px;font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="card">
  <div class="logo">digi<span>Supply</span></div>
  <div class="icon">📦</div>
  <h1>Portail fournisseur</h1>
  <p>Vous avez reçu un email contenant un lien sécurisé pour accéder à votre commande.<br>
     Si vous avez perdu le lien, entrez votre code d'accès ci-dessous.</p>

  <form class="token-form" method="get" action="/supply/order.php">
    <input type="text" name="token" placeholder="Collez votre code d'accès ici…" required autocomplete="off">
    <button type="submit" class="btn">Accéder à ma commande →</button>
  </form>

  <div class="footer">digiSupply · digiMind · Digital Technologies Congo</div>
</div>
</body>
</html>
