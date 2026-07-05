<?php
require_once dirname(__DIR__) . '/config/auth.php';
sa_check_auth();

// Connect to digipharmai_db (analytics DB)
require_once dirname(__DIR__, 2) . '/analytics/config/db.php';
$adb = analytics_db();

$current_page = 'dm_users';

// Stats
$total_users   = (int) $adb->query("SELECT COUNT(*) FROM ai_users")->fetchColumn();
$active_users  = (int) $adb->query("SELECT COUNT(*) FROM ai_users WHERE is_active = 1")->fetchColumn();
$total_pharmas = (int) $adb->query("SELECT COUNT(*) FROM ai_pharmacies")->fetchColumn();

// All users with pharmacy name
$users = $adb->query("
    SELECT u.*, p.name AS pharmacy_name
    FROM ai_users u
    LEFT JOIN ai_pharmacies p ON p.id = u.pharmacy_id
    ORDER BY u.created_at DESC
")->fetchAll();

require_once dirname(__DIR__) . '/config/layout_header.php';
?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
        <h1 class="page-title">Utilisateurs digiMind</h1>
        <p class="page-sub">Gestion des accès à l'assistant IA analytique</p>
    </div>
    <button class="btn-primary" onclick="openModal()">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
        Nouvel utilisateur
    </button>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
    <div class="kpi-card teal">
        <div class="kpi-value"><?= $total_users ?></div>
        <div class="kpi-label">Utilisateurs total</div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-value"><?= $active_users ?></div>
        <div class="kpi-label">Comptes actifs</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value"><?= $total_pharmas ?></div>
        <div class="kpi-label">Pharmacies connectées</div>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Tous les comptes</h2>
        <span class="badge"><?= $total_users ?> utilisateurs</span>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Pharmacie</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th>Dernière connexion</th>
                    <th>Créé le</th>
                    <th style="width:100px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($u['display_name'] ?: $u['email']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($u['email']) ?></div>
                </td>
                <td><?= htmlspecialchars($u['pharmacy_name'] ?? '—') ?></td>
                <td>
                    <?php if ($u['role'] === 'admin'): ?>
                        <span class="badge badge-green">Admin</span>
                    <?php else: ?>
                        <span class="badge badge-gray">Lecteur</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($u['is_active']): ?>
                        <span class="badge badge-green">Actif</span>
                    <?php else: ?>
                        <span class="badge badge-red">Inactif</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--text-muted);font-size:13px">
                    <?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '—' ?>
                </td>
                <td style="color:var(--text-muted);font-size:13px">
                    <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                </td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn-icon" title="Modifier" onclick='openModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn-icon btn-icon-red" title="<?= $u['is_active'] ? 'Désactiver' : 'Activer' ?>" onclick="toggleUser(<?= $u['id'] ?>, <?= $u['is_active'] ? 0 : 1 ?>)">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px">
                                <?php if ($u['is_active']): ?>
                                    <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                <?php else: ?>
                                    <polyline points="20 6 9 17 4 12"/>
                                <?php endif; ?>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
            <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">Aucun utilisateur digiMind</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="userModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;display:none;align-items:center;justify-content:center">
    <div style="background:#1a1f2e;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:28px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <h3 id="modalTitle" style="font-size:16px;font-weight:700;color:#fff">Nouvel utilisateur</h3>
            <button onclick="closeModal()" style="background:none;border:none;color:#8b92a5;cursor:pointer;font-size:20px;line-height:1">×</button>
        </div>
        <form id="userForm" onsubmit="saveUser(event)">
            <input type="hidden" id="userId" value="">
            <div class="form-group">
                <label class="form-label">Nom d'affichage</label>
                <input type="text" id="displayName" class="form-input" placeholder="Dr. Jean Dupont">
            </div>
            <div class="form-group">
                <label class="form-label">Email (identifiant)</label>
                <input type="email" id="userEmail" class="form-input" placeholder="jean@pharmacie.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">Mot de passe <span id="pwdNote" style="font-size:11px;color:#8b92a5">(laisser vide pour ne pas changer)</span></label>
                <input type="password" id="userPassword" class="form-input" placeholder="••••••••" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="form-label">Pharmacie</label>
                <select id="pharmacyId" class="form-input" required>
                    <option value="">— Sélectionner —</option>
                    <?php
                    $pharmas = $adb->query("SELECT id, name FROM ai_pharmacies ORDER BY name")->fetchAll();
                    foreach ($pharmas as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Rôle</label>
                <select id="userRole" class="form-input">
                    <option value="viewer">Lecteur</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div id="modalNotice" style="display:none;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px"></div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" onclick="closeModal()" class="btn-secondary">Annuler</button>
                <button type="submit" class="btn-primary" id="saveBtn">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(user) {
    const m = document.getElementById('userModal');
    m.style.display = 'flex';
    document.getElementById('pwdNote').style.display = user ? 'inline' : 'none';
    if (user) {
        document.getElementById('modalTitle').textContent  = 'Modifier l\'utilisateur';
        document.getElementById('userId').value            = user.id;
        document.getElementById('displayName').value       = user.display_name || '';
        document.getElementById('userEmail').value         = user.email;
        document.getElementById('pharmacyId').value        = user.pharmacy_id;
        document.getElementById('userRole').value          = user.role;
        document.getElementById('userPassword').value      = '';
    } else {
        document.getElementById('modalTitle').textContent = 'Nouvel utilisateur';
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
    }
    document.getElementById('modalNotice').style.display = 'none';
}
function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}
async function saveUser(e) {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    btn.disabled = true; btn.textContent = '…';
    const body = new FormData();
    body.append('id',          document.getElementById('userId').value);
    body.append('display_name',document.getElementById('displayName').value);
    body.append('email',       document.getElementById('userEmail').value);
    body.append('password',    document.getElementById('userPassword').value);
    body.append('pharmacy_id', document.getElementById('pharmacyId').value);
    body.append('role',        document.getElementById('userRole').value);
    const r = await fetch('/superadmin/digimind/user-save.php', { method:'POST', body });
    const j = await r.json();
    const n = document.getElementById('modalNotice');
    n.style.display = 'block';
    if (j.ok) {
        n.style.background = '#052e16'; n.style.color = '#4ade80'; n.style.border = '1px solid #166534';
        n.textContent = j.message;
        setTimeout(() => location.reload(), 800);
    } else {
        n.style.background = '#1f0707'; n.style.color = '#f87171'; n.style.border = '1px solid #7f1d1d';
        n.textContent = j.error;
        btn.disabled = false; btn.textContent = 'Enregistrer';
    }
}
async function toggleUser(id, active) {
    if (!confirm(active ? 'Activer ce compte ?' : 'Désactiver ce compte ?')) return;
    const body = new FormData();
    body.append('action', 'toggle'); body.append('id', id); body.append('is_active', active);
    const r = await fetch('/superadmin/digimind/user-save.php', { method:'POST', body });
    const j = await r.json();
    if (j.ok) location.reload();
    else alert(j.error);
}
document.getElementById('userModal').addEventListener('click', e => {
    if (e.target === document.getElementById('userModal')) closeModal();
});
</script>

<?php require_once dirname(__DIR__) . '/config/layout_footer.php'; ?>
