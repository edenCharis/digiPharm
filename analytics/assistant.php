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

.content { padding: 0; flex: 1; display: flex; min-height: 0; }

/* Conversation list */
.conv-sidebar { width: 260px; flex-shrink: 0; border-right: 1px solid var(--border-lt); display: flex; flex-direction: column; background: var(--surface-alt); min-height: 0; }
.conv-new { margin: 14px; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); color: var(--text); font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background .12s; }
.conv-new:hover { background: var(--green-lt); border-color: var(--green); color: var(--green-dk); }
.conv-new svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.conv-list { flex: 1; overflow-y: auto; padding: 0 8px 12px; display: flex; flex-direction: column; gap: 2px; }
.conv-item { display: flex; align-items: center; gap: 8px; padding: 9px 10px; border-radius: 8px; cursor: pointer; color: var(--text-2); transition: background .12s; }
.conv-item:hover { background: var(--surface); }
.conv-item.active { background: var(--surface); color: var(--text); font-weight: 600; box-shadow: inset 3px 0 0 var(--green); }
.conv-item-title { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; }
.conv-item-del { width: 22px; height: 22px; border-radius: 6px; border: none; background: none; color: var(--text-3); cursor: pointer; display: none; align-items: center; justify-content: center; flex-shrink: 0; }
.conv-item:hover .conv-item-del { display: flex; }
.conv-item-del:hover { background: var(--red-lt); color: var(--red); }
.conv-item-del svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.conv-empty { padding: 16px; font-size: 12.5px; color: var(--text-3); text-align: center; }

/* Chat area */
.assist-wrap { flex: 1; display: flex; flex-direction: column; min-width: 0; min-height: 0; }
.assist-body { flex: 1; overflow-y: auto; padding: 28px 24px; display: flex; flex-direction: column; gap: 16px; max-width: 760px; width: 100%; margin: 0 auto; }

.assist-msg-row { display: flex; flex-direction: column; max-width: 78%; }
.assist-msg-row.user { align-self: flex-end; align-items: flex-end; }
.assist-msg-row.bot  { align-self: flex-start; align-items: flex-start; }

.assist-msg { padding: 12px 16px; border-radius: 14px; font-size: 14px; line-height: 1.6; white-space: pre-wrap; }
.assist-msg.user { background: var(--green); color: #fff; border-bottom-right-radius: 4px; }
.assist-msg.bot  { background: var(--surface); border: 1px solid var(--border); color: var(--text); border-bottom-left-radius: 4px; }
.assist-msg.error { background: var(--red-lt); color: #991b1b; border: 1px solid #fecaca; }
.assist-msg.typing { background: none; border: none; color: var(--text-3); font-style: italic; padding: 0; }

.assist-msg-edit { opacity: 0; transition: opacity .12s; display: flex; align-items: center; gap: 4px; margin-top: 4px; font-size: 11px; color: var(--text-3); cursor: pointer; }
.assist-msg-row.user:hover .assist-msg-edit { opacity: 1; }
.assist-msg-edit svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.assist-msg-edit:hover { color: var(--text); }

.edit-box { width: 320px; max-width: 60vw; }
.edit-textarea { width: 100%; border: 1px solid var(--green); border-radius: 10px; padding: 10px 14px; font-size: 14px; font-family: inherit; resize: vertical; min-height: 60px; outline: none; }
.edit-actions { display: flex; gap: 8px; margin-top: 6px; justify-content: flex-end; }
.edit-btn { padding: 6px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: var(--surface); color: var(--text-2); }
.edit-btn.primary { background: var(--green); border-color: var(--green); color: #fff; }
.edit-btn:hover { opacity: .88; }

.assist-empty { margin: auto; text-align: center; color: var(--text-3); max-width: 420px; }
.assist-empty .ico { width: 52px; height: 52px; border-radius: 14px; background: var(--green-lt); display: grid; place-items: center; margin: 0 auto 16px; }
.assist-empty .ico svg { width: 26px; height: 26px; stroke: var(--green); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.assist-empty h2 { font-size: 17px; color: var(--text); margin-bottom: 6px; }
.assist-empty p { font-size: 13px; line-height: 1.6; margin-bottom: 18px; }
.assist-suggestions { display: flex; flex-direction: column; gap: 8px; }
.assist-suggestion { text-align: left; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); color: var(--text-2); font-size: 13px; cursor: pointer; transition: background .12s; }
.assist-suggestion:hover { background: var(--surface-alt); }

.assist-input-wrap { display: flex; gap: 10px; padding: 16px 24px 24px; border-top: 1px solid var(--border-lt); background: var(--bg); flex-shrink: 0; max-width: 760px; width: 100%; margin: 0 auto; }
.assist-input { flex: 1; border: 1px solid var(--border); border-radius: 22px; padding: 12px 18px; font-size: 14px; outline: none; resize: none; font-family: inherit; background: var(--surface); max-height: 140px; }
.assist-input:focus { border-color: var(--green); }
.assist-send { width: 42px; height: 42px; border-radius: 50%; background: var(--green); border: none; color: #fff; cursor: pointer; display: grid; place-items: center; flex-shrink: 0; }
.assist-send:disabled { opacity: .5; cursor: default; }
.assist-send svg { width: 18px; height: 18px; stroke: #fff; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

@media (max-width: 860px) {
  .conv-sidebar { display: none; }
}
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
    <div class="conv-sidebar">
      <button class="conv-new" onclick="newConversation()">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle conversation
      </button>
      <div class="conv-list" id="convList">
        <div class="conv-empty">Chargement…</div>
      </div>
    </div>

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

let currentConversationId = parseInt(new URLSearchParams(location.search).get('c')) || null;
let currentMessages = [];

function fmtRelative(iso) {
  const d = new Date(iso.replace(' ', 'T'));
  const diffH = (Date.now() - d.getTime()) / 36e5;
  if (diffH < 24) return d.toLocaleTimeString('fr', { hour: '2-digit', minute: '2-digit' });
  if (diffH < 24 * 7) return d.toLocaleDateString('fr', { weekday: 'short' });
  return d.toLocaleDateString('fr', { day: '2-digit', month: '2-digit' });
}

async function loadConversations() {
  const res = await fetch('/analytics/chat-api.php?action=list');
  const json = await res.json();
  const list = document.getElementById('convList');
  const convs = json.conversations || [];
  if (!convs.length) {
    list.innerHTML = '<div class="conv-empty">Aucune conversation pour l\'instant</div>';
    return;
  }
  list.innerHTML = convs.map(c => `
    <div class="conv-item${c.id == currentConversationId ? ' active' : ''}" onclick="openConversation(${c.id})">
      <span class="conv-item-title">${escapeHtml(c.title)}</span>
      <span class="conv-item-del" onclick="event.stopPropagation();deleteConversation(${c.id})" title="Supprimer">
        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
      </span>
    </div>
  `).join('');
}

function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

async function openConversation(id) {
  currentConversationId = id;
  history.replaceState(null, '', '?c=' + id);
  document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
  loadConversations();

  const res = await fetch('/analytics/chat-api.php?action=get&id=' + id);
  const json = await res.json();
  if (json.messages) {
    currentMessages = json.messages;
    renderMessages(currentMessages);
  }
}

function newConversation() {
  currentConversationId = null;
  currentMessages = [];
  history.replaceState(null, '', location.pathname);
  document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
  renderMessages([]);
}

function askSuggestion(btn) {
  document.getElementById('assistInput').value = btn.textContent;
  sendAssist();
}

function renderMessages(messages) {
  currentMessages = messages;
  const body = document.getElementById('assistBody');
  if (!messages.length) {
    body.innerHTML = `<div class="assist-empty" id="assistEmpty">
      <div class="ico"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
      <h2>Posez une question sur votre pharmacie</h2>
      <p>digiMind répond à partir de vos données réelles : ventes, stock, alertes, fournisseurs.</p>
      <div class="assist-suggestions">
        <button class="assist-suggestion" onclick="askSuggestion(this)">Quels produits sont en rupture ou stock faible ?</button>
        <button class="assist-suggestion" onclick="askSuggestion(this)">Comment évolue mon chiffre d'affaires ce mois-ci ?</button>
        <button class="assist-suggestion" onclick="askSuggestion(this)">Quels sont mes fournisseurs les plus fiables ?</button>
      </div>
    </div>`;
    return;
  }
  body.innerHTML = messages.map(m => {
    if (m.role === 'user') {
      return `<div class="assist-msg-row user" data-id="${m.id}">
        <div class="assist-msg user">${escapeHtml(m.content)}</div>
        <div class="assist-msg-edit" onclick="startEdit(${m.id})">
          <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Modifier
        </div>
      </div>`;
    }
    return `<div class="assist-msg-row bot"><div class="assist-msg bot">${escapeHtml(m.content)}</div></div>`;
  }).join('');
  body.scrollTop = body.scrollHeight;
}

function startEdit(messageId) {
  const msg = currentMessages.find(m => m.id === messageId);
  if (!msg) return;
  const row = document.querySelector(`.assist-msg-row[data-id="${messageId}"]`);
  row.innerHTML = `
    <div class="edit-box">
      <textarea class="edit-textarea" id="editTextarea">${escapeHtml(msg.content)}</textarea>
      <div class="edit-actions">
        <button class="edit-btn" onclick="renderMessages(currentMessages)">Annuler</button>
        <button class="edit-btn primary" onclick="saveEdit(${messageId})">Envoyer</button>
      </div>
    </div>`;
  document.getElementById('editTextarea').focus();
}

async function saveEdit(messageId) {
  const content = document.getElementById('editTextarea').value.trim();
  if (!content) return;

  const body = document.getElementById('assistBody');
  body.insertAdjacentHTML('beforeend', '<div class="assist-msg-row bot" id="editTyping"><div class="assist-msg typing">digiMind réfléchit…</div></div>');
  body.scrollTop = body.scrollHeight;

  try {
    const res = await fetch('/analytics/chat-api.php?action=edit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: currentConversationId, message_id: messageId, content }),
    });
    const json = await res.json();
    renderMessages(json.messages || []);
    if (json.available === false) {
      document.getElementById('assistBody').insertAdjacentHTML('beforeend',
        `<div class="assist-msg-row bot"><div class="assist-msg error">${escapeHtml(json.error || 'digiMind est indisponible.')}</div></div>`);
    }
    loadConversations();
  } catch (e) {
    document.getElementById('editTyping')?.remove();
  }
}

async function sendAssist() {
  const input = document.getElementById('assistInput');
  const question = input.value.trim();
  if (!question) return;

  const sendBtn = document.getElementById('assistSend');
  input.value = '';
  sendBtn.disabled = true;

  const body = document.getElementById('assistBody');
  document.getElementById('assistEmpty')?.remove();
  body.insertAdjacentHTML('beforeend', `<div class="assist-msg-row user"><div class="assist-msg user">${escapeHtml(question)}</div></div>`);
  body.insertAdjacentHTML('beforeend', '<div class="assist-msg-row bot" id="assistTyping"><div class="assist-msg typing">digiMind réfléchit…</div></div>');
  body.scrollTop = body.scrollHeight;

  const wasNew = !currentConversationId;

  try {
    const res = await fetch('/analytics/chat-api.php?action=send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: currentConversationId || 0, question }),
    });
    const json = await res.json();

    if (json.conversation_id) {
      currentConversationId = json.conversation_id;
      history.replaceState(null, '', '?c=' + currentConversationId);
    }
    renderMessages(json.messages || []);
    if (json.available === false) {
      document.getElementById('assistBody').insertAdjacentHTML('beforeend',
        `<div class="assist-msg-row bot"><div class="assist-msg error">${escapeHtml(json.error || 'digiMind est indisponible.')}</div></div>`);
    }
    loadConversations();
  } catch (e) {
    document.getElementById('assistTyping')?.remove();
    document.getElementById('assistBody').insertAdjacentHTML('beforeend',
      '<div class="assist-msg-row bot"><div class="assist-msg error">Erreur réseau — réessayez.</div></div>');
  } finally {
    sendBtn.disabled = false;
    input.focus();
  }
}

async function deleteConversation(id) {
  if (!confirm('Supprimer cette conversation ?')) return;
  await fetch('/analytics/chat-api.php?action=delete', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ conversation_id: id }),
  });
  if (id === currentConversationId) newConversation();
  loadConversations();
}

loadConversations();
if (currentConversationId) openConversation(currentConversationId);
</script>
</body>
</html>
