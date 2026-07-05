<?php
require_once __DIR__ . '/config/auth.php';
ai_check_auth();
$user      = $u = ai_user();
$activePage = 'account';
$pageTitle  = 'Mon compte';
$db = analytics_db();

// Load full user row for otp_email
$st = $db->prepare("SELECT display_name,email,otp_email FROM ai_users WHERE id=?");
$st->execute([$user['id']]);
$full = $st->fetch() ?: [];
$jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$initials = strtoupper(substr($user['display_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>digiMind — Mon compte</title>
<style>
<?php include __DIR__ . '/includes/common.css.php'; ?>

/* ── Responsive sidebar ────────────────────────────────────────────────── */
.sidebar { transition: transform .25s ease; }
.sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:99; }
.sidebar-overlay.open { display:block; }
.hamburger { display:none; background:none; border:none; cursor:pointer; padding:4px; color:var(--text-2); }
.hamburger svg { width:22px; height:22px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
@media(max-width:768px){
  .sidebar { transform:translateX(-100%); }
  .sidebar.open { transform:translateX(0); }
  .main { margin-left:0!important; }
  .hamburger { display:flex; }
  .content { padding:16px 16px 28px; }
  .topbar { padding:0 16px; }
  .acc-grid { grid-template-columns:1fr!important; }
}

/* ── Account layout ────────────────────────────────────────────────────── */
.acc-grid { display:grid; grid-template-columns:1fr 320px; gap:20px; align-items:start; }
.panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:18px; }
.panel:last-child { margin-bottom:0; }
.panel-head { padding:14px 20px; border-bottom:1px solid var(--border-lt); display:flex; align-items:center; gap:10px; }
.panel-head svg { width:16px; height:16px; stroke:var(--green); fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
.panel-title { font-size:14px; font-weight:700; }
.panel-body { padding:20px; display:flex; flex-direction:column; gap:16px; }
.field-row { display:flex; flex-direction:column; gap:5px; }
.field-label { font-size:12px; font-weight:600; color:var(--text-2); }
.field-sub { font-size:11.5px; color:var(--text-3); margin-top:2px; }
.tf { padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-size:13.5px; color:var(--text); background:var(--surface); outline:none; width:100%; transition:border-color .15s; }
.tf:focus { border-color:var(--green); }
.tf:disabled { background:var(--surface-alt); color:var(--text-3); cursor:not-allowed; }
.tf-row { display:flex; gap:8px; }
.tf-row .tf { flex:1; }
.send-btn { padding:9px 14px; border:1px solid var(--green); border-radius:8px; background:var(--green-lt); color:var(--green-dk); font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap; transition:background .15s; }
.send-btn:hover { background:#d1fae5; }
.send-btn:disabled { opacity:.5; cursor:not-allowed; }
.otp-row { display:none; flex-direction:column; gap:5px; }
.otp-row.visible { display:flex; }
.otp-input { text-align:center; font-size:22px; font-weight:700; letter-spacing:6px; font-variant-numeric:tabular-nums; }
.save-btn { padding:9px 18px; border:none; border-radius:8px; background:var(--green); color:#fff; font-size:13.5px; font-weight:600; cursor:pointer; transition:background .15s; align-self:flex-start; }
.save-btn:hover { background:var(--green-dk); }
.save-btn:disabled { opacity:.5; cursor:not-allowed; }
.notice { padding:10px 14px; border-radius:8px; font-size:13px; display:none; align-items:center; gap:8px; }
.notice.ok    { background:var(--green-lt); color:var(--green-dk); border:1px solid #bbf7d0; display:flex; }
.notice.error { background:var(--red-lt);   color:var(--red);      border:1px solid #fecaca; display:flex; }

/* ── Account tabs ──────────────────────────────────────────────────────── */
.acc-tabs { display:flex; border-bottom:1px solid var(--border); margin-bottom:20px; overflow-x:auto; scrollbar-width:none; }
.acc-tabs::-webkit-scrollbar { display:none; }
.acc-tab { padding:10px 18px; font-size:13px; font-weight:500; color:var(--text-3); cursor:pointer; border:none; border-bottom:2px solid transparent; background:none; white-space:nowrap; margin-bottom:-1px; transition:color .12s,border-color .12s; }
.acc-tab.active { color:var(--green); border-bottom-color:var(--green); font-weight:600; }
.acc-panel { display:none; flex-direction:column; gap:16px; }
.acc-panel.active { display:flex; }

/* ── Info card ─────────────────────────────────────────────────────────── */
.info-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:24px 20px; position:sticky; top:72px; }
.info-avatar { width:64px; height:64px; background:var(--green); border-radius:50%; display:grid; place-items:center; font-size:26px; font-weight:800; color:#fff; margin:0 auto 16px; }
.info-name { text-align:center; font-size:16px; font-weight:700; margin-bottom:2px; }
.info-email { text-align:center; font-size:12.5px; color:var(--text-3); margin-bottom:16px; }
.info-meta { display:flex; flex-direction:column; gap:8px; }
.info-row { display:flex; align-items:center; gap:10px; padding:9px 12px; background:var(--surface-alt); border-radius:8px; }
.info-row svg { width:14px; height:14px; stroke:var(--text-3); fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
.info-row-text { font-size:12.5px; color:var(--text-2); }
.plan-badge { display:inline-block; padding:2px 9px; border-radius:99px; font-size:11px; font-weight:700; background:var(--green-lt); color:var(--green-dk); text-transform:capitalize; }
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="flex-direction:row;align-items:center;gap:10px;">
      <button class="hamburger" onclick="openSidebar()" aria-label="Menu">
        <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-title">Mon compte</div>
        <div class="topbar-meta">Gérez votre profil et votre sécurité</div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="acc-grid">

      <!-- Left column: tabbed forms -->
      <?php
        $stP = $db->prepare("SELECT name, plan FROM ai_pharmacies WHERE id=?");
        $stP->execute([$user['pharmacy_id']]);
        $pharm = $stP->fetch() ?: [];
      ?>
      <div>
        <!-- Tab nav -->
        <div class="acc-tabs">
          <button class="acc-tab active" data-tab="profile" onclick="switchAccTab('profile')">Profil</button>
          <button class="acc-tab" data-tab="security" onclick="switchAccTab('security')">Sécurité</button>
          <?php if ($user['role'] === 'admin'): ?>
          <button class="acc-tab" data-tab="pharmacy" onclick="switchAccTab('pharmacy')">Pharmacie</button>
          <?php endif; ?>
        </div>

        <!-- Tab: Profil — name + OTP email -->
        <div class="acc-panel active" id="tab-profile">
          <div id="noticeProfile" class="notice"></div>
          <div class="field-row">
            <label class="field-label">Nom d'affichage</label>
            <input class="tf" id="displayName" type="text" value="<?= htmlspecialchars($full['display_name'] ?? '') ?>" placeholder="Votre nom" maxlength="120">
          </div>
          <div class="field-row">
            <label class="field-label">Email de réception des codes OTP</label>
            <input class="tf" id="otpEmail" type="email"
                   value="<?= htmlspecialchars($full['otp_email'] ?? '') ?>"
                   placeholder="Laisser vide pour utiliser l'email de connexion">
            <div class="field-sub">Les codes de sécurité seront envoyés ici. Si vide, l'email de connexion est utilisé.</div>
          </div>
          <button class="save-btn" onclick="saveProfile()">Enregistrer</button>
        </div>

        <!-- Tab: Sécurité — email change + password -->
        <div class="acc-panel" id="tab-security">
          <div style="font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.6px;">Changer l'email de connexion</div>
          <div id="noticeEmail" class="notice"></div>
          <div class="field-row">
            <label class="field-label">Adresse actuelle</label>
            <input class="tf" type="email" value="<?= htmlspecialchars($full['email'] ?? '') ?>" disabled>
          </div>
          <div class="field-row">
            <label class="field-label">Nouvelle adresse</label>
            <div class="tf-row">
              <input class="tf" id="newEmail" type="email" placeholder="nouvelle@email.com">
              <button class="send-btn" id="sendOtpBtn" onclick="sendOtp()">Envoyer code</button>
            </div>
          </div>
          <div class="otp-row" id="otpRow">
            <label class="field-label">Code de vérification (6 chiffres)</label>
            <input class="tf otp-input" id="otpCode" type="text" inputmode="numeric" maxlength="6" placeholder="000000">
            <button class="save-btn" onclick="verifyOtp()">Confirmer</button>
          </div>

          <hr style="border:none;border-top:1px solid var(--border-lt);margin:4px 0">
          <div style="font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.6px;">Changer le mot de passe</div>
          <div id="noticePassword" class="notice"></div>
          <div class="field-row">
            <label class="field-label">Mot de passe actuel</label>
            <input class="tf" id="currentPwd" type="password" placeholder="••••••••" autocomplete="current-password">
          </div>
          <div class="field-row">
            <label class="field-label">Nouveau mot de passe</label>
            <input class="tf" id="newPwd" type="password" placeholder="Minimum 8 caractères" autocomplete="new-password">
          </div>
          <div class="field-row">
            <label class="field-label">Confirmer</label>
            <input class="tf" id="confirmPwd" type="password" placeholder="••••••••" autocomplete="new-password">
          </div>
          <button class="save-btn" onclick="savePassword()">Changer le mot de passe</button>
        </div>

        <!-- Tab: Pharmacie (admin only) -->
        <?php if ($user['role'] === 'admin'): ?>
        <div class="acc-panel" id="tab-pharmacy">
          <div id="noticePharmacy" class="notice"></div>
          <div class="field-row">
            <label class="field-label">Nom de la pharmacie</label>
            <input class="tf" id="pharmacyName" type="text"
                   value="<?= htmlspecialchars($pharm['name'] ?? '') ?>"
                   placeholder="Nom officiel" maxlength="150">
          </div>
          <div class="field-row">
            <label class="field-label">Plan</label>
            <input class="tf" type="text" value="<?= htmlspecialchars(ucfirst($pharm['plan'] ?? '')) ?>" disabled>
            <div class="field-sub">Pour changer de plan, contactez le support.</div>
          </div>
          <button class="save-btn" onclick="savePharmacy()">Enregistrer</button>
        </div>
        <?php endif; ?>

      </div><!-- /left -->

      <!-- Right: identity card -->
      <div>
        <div class="info-card">
          <div class="info-avatar"><?= $initials ?></div>
          <div class="info-name" id="cardName"><?= htmlspecialchars($user['display_name']) ?></div>
          <div class="info-email" id="cardEmail"><?= htmlspecialchars($full['email'] ?? '') ?></div>
          <div class="info-meta">
            <div class="info-row">
              <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
              <span class="info-row-text"><?= htmlspecialchars($user['pharmacy_name']) ?></span>
            </div>
            <div class="info-row">
              <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span class="info-row-text"><?= $user['role'] === 'admin' ? 'Administrateur' : 'Lecteur' ?></span>
            </div>
            <?php if (!empty($pharm['plan'])): ?>
            <div class="info-row" style="justify-content:space-between">
              <div style="display:flex;align-items:center;gap:10px">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <span class="info-row-text">Plan</span>
              </div>
              <span class="plan-badge"><?= htmlspecialchars(ucfirst($pharm['plan'])) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
              <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <span class="info-row-text" id="cardOtpEmail"><?= $full['otp_email'] ? htmlspecialchars($full['otp_email']) : '<em style="color:var(--text-3)">Non configuré</em>' ?></span>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /grid -->
  </div>
</div>

<script>
async function post(action, data) {
  const fd = new FormData();
  fd.append('action', action);
  for (const [k, v] of Object.entries(data)) fd.append(k, v);
  const r = await fetch('/analytics/account-save.php', {method:'POST', body:fd});
  return r.json();
}

function showNotice(id, res) {
  const el = document.getElementById(id);
  el.className = 'notice ' + (res.ok ? 'ok' : 'error');
  el.textContent = res.ok ? res.message : res.error;
  if (res.ok) setTimeout(() => { el.className = 'notice'; }, 4000);
}

function switchAccTab(id) {
  document.querySelectorAll('.acc-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === id));
  document.querySelectorAll('.acc-panel').forEach(p => p.classList.toggle('active', p.id === 'tab-' + id));
}

async function saveProfile() {
  const name     = document.getElementById('displayName').value.trim();
  const otpEmail = document.getElementById('otpEmail').value.trim();
  const [r1, r2] = await Promise.all([
    post('profile',    {display_name: name}),
    post('otp_email',  {otp_email: otpEmail}),
  ]);
  const ok = r1.ok && r2.ok;
  showNotice('noticeProfile', ok ? {ok:true, message:'Profil enregistré'} : {ok:false, error: r1.error || r2.error});
  if (ok) {
    document.getElementById('cardName').textContent = name;
    const card = document.getElementById('cardOtpEmail');
    card.innerHTML = otpEmail ? otpEmail : '<em style="color:var(--text-3)">Non configuré</em>';
  }
}

async function sendOtp() {
  const email = document.getElementById('newEmail').value.trim();
  document.getElementById('sendOtpBtn').disabled = true;
  const res = await post('otp_send', {email});
  showNotice('noticeEmail', res);
  if (res.ok) {
    document.getElementById('otpRow').classList.add('visible');
    document.getElementById('otpCode').focus();
  } else {
    document.getElementById('sendOtpBtn').disabled = false;
  }
}

async function verifyOtp() {
  const code = document.getElementById('otpCode').value.trim();
  const res = await post('otp_verify', {code});
  showNotice('noticeEmail', res);
  if (res.ok) {
    document.getElementById('otpRow').classList.remove('visible');
    document.getElementById('sendOtpBtn').disabled = false;
    document.getElementById('newEmail').value = '';
    document.getElementById('otpCode').value = '';
    document.getElementById('cardEmail').textContent = res.new_email;
    // reload the current-email display
    location.reload();
  }
}

async function savePassword() {
  const res = await post('password', {
    current_password: document.getElementById('currentPwd').value,
    new_password:     document.getElementById('newPwd').value,
    confirm_password: document.getElementById('confirmPwd').value,
  });
  showNotice('noticePassword', res);
  if (res.ok) {
    document.getElementById('currentPwd').value = '';
    document.getElementById('newPwd').value = '';
    document.getElementById('confirmPwd').value = '';
  }
}

async function savePharmacy() {
  const name = document.getElementById('pharmacyName')?.value.trim();
  if (!name) return;
  const res = await post('pharmacy', {pharmacy_name: name});
  showNotice('noticePharmacy', res);
  if (res.ok) {
    document.querySelector('.sidebar-pharmacy').textContent = name;
  }
}

// Mobile sidebar
function openSidebar() {
  document.querySelector('.sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('open');
}
function closeSidebar() {
  document.querySelector('.sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
}
</script>
</body>
</html>
