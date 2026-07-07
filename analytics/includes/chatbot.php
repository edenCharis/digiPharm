<style>
.chat-fab {
  position: fixed; right: 24px; bottom: 24px; z-index: 500;
  width: 52px; height: 52px; border-radius: 50%;
  background: var(--green); color: #fff; border: none; cursor: pointer;
  display: grid; place-items: center; box-shadow: 0 4px 16px rgba(0,0,0,.2);
  transition: transform .15s;
}
.chat-fab:hover { transform: scale(1.06); }
.chat-fab svg { width: 24px; height: 24px; stroke: #fff; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.chat-panel {
  position: fixed; right: 24px; bottom: 88px; z-index: 500;
  width: 360px; max-width: calc(100vw - 32px); height: 480px; max-height: calc(100vh - 120px);
  background: var(--surface); border: 1px solid var(--border); border-radius: 14px;
  box-shadow: 0 8px 32px rgba(0,0,0,.18);
  display: none; flex-direction: column; overflow: hidden;
}
.chat-panel.open { display: flex; }

.chat-head {
  display: flex; align-items: center; gap: 10px; padding: 14px 16px;
  background: var(--green); color: #fff; flex-shrink: 0;
}
.chat-head-icon { width: 30px; height: 30px; border-radius: 8px; background: rgba(255,255,255,.15); display: grid; place-items: center; flex-shrink: 0; }
.chat-head-icon svg { width: 16px; height: 16px; stroke: #fff; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.chat-head-title { font-size: 13.5px; font-weight: 700; }
.chat-head-sub { font-size: 11px; opacity: .85; }
.chat-close { margin-left: auto; background: none; border: none; color: #fff; cursor: pointer; opacity: .85; padding: 4px; }
.chat-close:hover { opacity: 1; }
.chat-close svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; }

.chat-body { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 10px; background: var(--bg); }
.chat-msg { max-width: 85%; padding: 9px 12px; border-radius: 12px; font-size: 13px; line-height: 1.5; white-space: pre-wrap; }
.chat-msg.user { align-self: flex-end; background: var(--green); color: #fff; border-bottom-right-radius: 3px; }
.chat-msg.bot  { align-self: flex-start; background: var(--surface); border: 1px solid var(--border); color: var(--text); border-bottom-left-radius: 3px; }
.chat-msg.error { align-self: flex-start; background: var(--red-lt); color: #991b1b; border: 1px solid #fecaca; }
.chat-msg.typing { align-self: flex-start; color: var(--text-3); font-style: italic; }

.chat-empty { text-align: center; color: var(--text-3); font-size: 12.5px; padding: 20px 10px; line-height: 1.6; }

.chat-input-wrap { display: flex; gap: 8px; padding: 10px; border-top: 1px solid var(--border); background: var(--surface); flex-shrink: 0; }
.chat-input { flex: 1; border: 1px solid var(--border); border-radius: 20px; padding: 9px 14px; font-size: 13px; outline: none; resize: none; font-family: inherit; }
.chat-input:focus { border-color: var(--green); }
.chat-send { width: 36px; height: 36px; border-radius: 50%; background: var(--green); border: none; color: #fff; cursor: pointer; display: grid; place-items: center; flex-shrink: 0; }
.chat-send:disabled { opacity: .5; cursor: default; }
.chat-send svg { width: 16px; height: 16px; stroke: #fff; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

@media (max-width: 480px) {
  .chat-panel { right: 16px; left: 16px; width: auto; bottom: 82px; }
  .chat-fab { right: 16px; bottom: 16px; }
}
</style>

<button class="chat-fab" id="chatFab" onclick="toggleChat()" title="Demander à digiMind" aria-label="Ouvrir le chat digiMind">
  <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
</button>

<div class="chat-panel" id="chatPanel">
  <div class="chat-head">
    <div class="chat-head-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
    <div>
      <div class="chat-head-title">digiMind</div>
      <div class="chat-head-sub">Posez une question sur votre pharmacie</div>
    </div>
    <a href="/analytics/assistant.php" class="chat-close" title="Ouvrir en plein écran" aria-label="Ouvrir en plein écran">
      <svg viewBox="0 0 24 24"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
    </a>
    <button class="chat-close" onclick="toggleChat()" aria-label="Fermer">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="chat-body" id="chatBody">
    <div class="chat-empty" id="chatEmpty">
      Exemples : « Quels produits sont en rupture ? », « Comment évolue mon chiffre d'affaires ? », « Quels fournisseurs sont fiables ? »
    </div>
  </div>
  <div class="chat-input-wrap">
    <textarea class="chat-input" id="chatInput" rows="1" placeholder="Écrivez votre question…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChat();}"></textarea>
    <button class="chat-send" id="chatSend" onclick="sendChat()" aria-label="Envoyer">
      <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </div>
</div>

<script>
let chatHistory = [];
let chatOpen = false;

function toggleChat() {
  chatOpen = !chatOpen;
  document.getElementById('chatPanel').classList.toggle('open', chatOpen);
  if (chatOpen) document.getElementById('chatInput').focus();
}

function appendChatMsg(role, text) {
  const body = document.getElementById('chatBody');
  const empty = document.getElementById('chatEmpty');
  if (empty) empty.remove();
  const div = document.createElement('div');
  div.className = 'chat-msg ' + role;
  div.textContent = text;
  body.appendChild(div);
  body.scrollTop = body.scrollHeight;
  return div;
}

async function sendChat() {
  const input = document.getElementById('chatInput');
  const question = input.value.trim();
  if (!question) return;

  const sendBtn = document.getElementById('chatSend');
  input.value = '';
  sendBtn.disabled = true;

  appendChatMsg('user', question);
  const typingEl = appendChatMsg('typing', 'digiMind réfléchit…');

  try {
    const res = await fetch('/analytics/api.php?type=chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question, history: chatHistory }),
    });
    const json = await res.json();
    typingEl.remove();

    if (json.available === false || !json.reply) {
      appendChatMsg('error', json.error || 'digiMind est momentanément indisponible.');
    } else {
      appendChatMsg('bot', json.reply);
      chatHistory.push({ role: 'user', content: question });
      chatHistory.push({ role: 'assistant', content: json.reply });
      chatHistory = chatHistory.slice(-8);
    }
  } catch (e) {
    typingEl.remove();
    appendChatMsg('error', 'Erreur réseau — réessayez.');
  } finally {
    sendBtn.disabled = false;
  }
}
</script>
