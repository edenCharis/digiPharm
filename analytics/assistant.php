<?php
require_once __DIR__ . '/config/auth.php';
ai_check_auth();
$user       = ai_user();
$activePage = 'assistant';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>digiMind — Assistant</title>
<style>
<?php include __DIR__ . '/includes/common.css.php'; ?>

.content { padding: 0; flex: 1; display: flex; flex-direction: column; min-height: 0; }
.assist-wrap { flex: 1; display: flex; flex-direction: column; max-width: 760px; width: 100%; margin: 0 auto; min-height: 0; }

.assist-body { flex: 1; overflow-y: auto; padding: 28px 20px; display: flex; flex-direction: column; gap: 16px; }
.assist-msg { max-width: 78%; padding: 12px 16px; border-radius: 14px; font-size: 14px; line-height: 1.6; white-space: pre-wrap; }
.assist-msg.user { align-self: flex-end; background: var(--green); color: #fff; border-bottom-right-radius: 4px; }
.assist-msg.bot  { align-self: flex-start; background: var(--surface); border: 1px solid var(--border); color: var(--text); border-bottom-left-radius: 4px; }
.assist-msg.error { align-self: flex-start; background: var(--red-lt); color: #991b1b; border: 1px solid #fecaca; }
.assist-msg.typing { align-self: flex-start; color: var(--text-3); font-style: italic; }

.assist-empty { margin: auto; text-align: center; color: var(--text-3); max-width: 420px; }
.assist-empty .ico { width: 52px; height: 52px; border-radius: 14px; background: var(--green-lt); display: grid; place-items: center; margin: 0 auto 16px; }
.assist-empty .ico svg { width: 26px; height: 26px; stroke: var(--green); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.assist-empty h2 { font-size: 17px; color: var(--text); margin-bottom: 6px; }
.assist-empty p { font-size: 13px; line-height: 1.6; margin-bottom: 18px; }
.assist-suggestions { display: flex; flex-direction: column; gap: 8px; }
.assist-suggestion { text-align: left; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); color: var(--text-2); font-size: 13px; cursor: pointer; transition: background .12s; }
.assist-suggestion:hover { background: var(--surface-alt); }

.assist-input-wrap { display: flex; gap: 10px; padding: 16px 20px 24px; border-top: 1px solid var(--border-lt); background: var(--bg); flex-shrink: 0; }
.assist-input { flex: 1; border: 1px solid var(--border); border-radius: 22px; padding: 12px 18px; font-size: 14px; outline: none; resize: none; font-family: inherit; background: var(--surface); max-height: 140px; }
.assist-input:focus { border-color: var(--green); }
.assist-send { width: 42px; height: 42px; border-radius: 50%; background: var(--green); border: none; color: #fff; cursor: pointer; display: grid; place-items: center; flex-shrink: 0; }
.assist-send:disabled { opacity: .5; cursor: default; }
.assist-send svg { width: 18px; height: 18px; stroke: #fff; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="flex-direction:row;align-items:center;gap:8px;">
      <button class="hamburger" onclick="openSidebar()" aria-label="Menu">
        <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-title">Assistant digiMind</div>
        <div class="topbar-meta"><?= htmlspecialchars($user['pharmacy_name']) ?></div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="assist-wrap">
      <div class="assist-body" id="assistBody">
        <div class="assist-empty" id="assistEmpty">
          <div class="ico"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
          <h2>Posez une question sur votre pharmacie</h2>
          <p>digiMind répond à partir de vos données réelles : ventes, stock, alertes, fournisseurs.</p>
          <div class="assist-suggestions">
            <button class="assist-suggestion" onclick="askSuggestion(this)">Quels produits sont en rupture ou stock faible ?</button>
            <button class="assist-suggestion" onclick="askSuggestion(this)">Comment évolue mon chiffre d'affaires ce mois-ci ?</button>
            <button class="assist-suggestion" onclick="askSuggestion(this)">Quels sont mes fournisseurs les plus fiables ?</button>
          </div>
        </div>
      </div>
      <div class="assist-input-wrap">
        <textarea class="assist-input" id="assistInput" rows="1" placeholder="Écrivez votre question…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendAssist();}"></textarea>
        <button class="assist-send" id="assistSend" onclick="sendAssist()" aria-label="Envoyer">
          <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function openSidebar()  { document.getElementById('sidebarOverlay').classList.add('open'); document.querySelector('.sidebar').classList.add('open'); }
function closeSidebar() { document.getElementById('sidebarOverlay').classList.remove('open'); document.querySelector('.sidebar').classList.remove('open'); }

let assistHistory = [];

function askSuggestion(btn) {
  document.getElementById('assistInput').value = btn.textContent;
  sendAssist();
}

function appendAssistMsg(role, text) {
  const body = document.getElementById('assistBody');
  const empty = document.getElementById('assistEmpty');
  if (empty) empty.remove();
  const div = document.createElement('div');
  div.className = 'assist-msg ' + role;
  div.textContent = text;
  body.appendChild(div);
  body.scrollTop = body.scrollHeight;
  return div;
}

async function sendAssist() {
  const input = document.getElementById('assistInput');
  const question = input.value.trim();
  if (!question) return;

  const sendBtn = document.getElementById('assistSend');
  input.value = '';
  sendBtn.disabled = true;

  appendAssistMsg('user', question);
  const typingEl = appendAssistMsg('typing', 'digiMind réfléchit…');

  try {
    const res = await fetch('/analytics/api.php?type=chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question, history: assistHistory }),
    });
    const json = await res.json();
    typingEl.remove();

    if (json.available === false || !json.reply) {
      appendAssistMsg('error', json.error || 'digiMind est momentanément indisponible.');
    } else {
      appendAssistMsg('bot', json.reply);
      assistHistory.push({ role: 'user', content: question });
      assistHistory.push({ role: 'assistant', content: json.reply });
      assistHistory = assistHistory.slice(-8);
    }
  } catch (e) {
    typingEl.remove();
    appendAssistMsg('error', 'Erreur réseau — réessayez.');
  } finally {
    sendBtn.disabled = false;
    input.focus();
  }
}
</script>
</body>
</html>
