<style>
.dg-modal-overlay { position: fixed; inset: 0; background: rgba(17,24,39,.45); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; }
.dg-modal-overlay.open { display: flex; }
.dg-modal { background: var(--surface); border-radius: 14px; padding: 24px; width: 100%; max-width: 380px; box-shadow: 0 12px 40px rgba(0,0,0,.22); }
.dg-modal-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--green-lt); color: var(--green); display: grid; place-items: center; margin-bottom: 14px; }
.dg-modal-icon.danger { background: var(--red-lt); color: var(--red); }
.dg-modal-icon svg { width: 22px; height: 22px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.dg-modal-title { font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 6px; }
.dg-modal-msg { font-size: 13.5px; color: var(--text-2); line-height: 1.6; margin-bottom: 20px; white-space: pre-wrap; }
.dg-modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
.dg-modal-btn { padding: 9px 16px; border-radius: 9px; font-size: 13.5px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: var(--surface); color: var(--text-2); transition: opacity .12s; }
.dg-modal-btn:hover { opacity: .85; }
.dg-modal-btn.primary { background: var(--green); border-color: var(--green); color: #fff; }
.dg-modal-btn.primary.danger { background: var(--red); border-color: var(--red); }
</style>

<div class="dg-modal-overlay" id="dgModalOverlay" onclick="if(event.target===this)_dgModalClose(false)">
  <div class="dg-modal">
    <div class="dg-modal-icon" id="dgModalIcon"></div>
    <div class="dg-modal-title" id="dgModalTitle"></div>
    <div class="dg-modal-msg" id="dgModalMsg"></div>
    <div class="dg-modal-actions" id="dgModalActions"></div>
  </div>
</div>

<script>
const DG_ICON_INFO  = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
const DG_ICON_WARN  = '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

let _dgModalResolve = null;

function _dgModalKey(e) { if (e.key === 'Escape') _dgModalClose(false); }

function _dgModalClose(value) {
  document.getElementById('dgModalOverlay').classList.remove('open');
  document.removeEventListener('keydown', _dgModalKey);
  if (_dgModalResolve) { const r = _dgModalResolve; _dgModalResolve = null; r(value); }
}

function _dgModalShow({ title, message, danger, buttons }) {
  return new Promise(resolve => {
    _dgModalResolve = resolve;
    document.getElementById('dgModalOverlay').classList.add('open');
    const titleEl = document.getElementById('dgModalTitle');
    titleEl.textContent = title || '';
    titleEl.style.display = title ? 'block' : 'none';
    document.getElementById('dgModalMsg').textContent = message;
    const iconEl = document.getElementById('dgModalIcon');
    iconEl.className = 'dg-modal-icon' + (danger ? ' danger' : '');
    iconEl.innerHTML = danger ? DG_ICON_WARN : DG_ICON_INFO;

    const actions = document.getElementById('dgModalActions');
    actions.innerHTML = '';
    buttons.forEach(b => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dg-modal-btn' + (b.primary ? ' primary' : '') + (b.danger ? ' danger' : '');
      btn.textContent = b.label;
      btn.onclick = () => _dgModalClose(b.value);
      actions.appendChild(btn);
    });
    document.addEventListener('keydown', _dgModalKey);
  });
}

/** Real confirm dialog. Usage: if (!(await dgModalConfirm('Supprimer ?'))) return; */
function dgModalConfirm(message, opts = {}) {
  return _dgModalShow({
    title: opts.title ?? 'Confirmation',
    message,
    danger: !!opts.danger,
    buttons: [
      { label: opts.cancelText || 'Annuler', value: false },
      { label: opts.confirmText || 'Confirmer', value: true, primary: true, danger: !!opts.danger },
    ],
  });
}

/** Real alert dialog. Usage: await dgModalAlert('Erreur…', { danger: true }); */
function dgModalAlert(message, opts = {}) {
  return _dgModalShow({
    title: opts.title ?? '',
    message,
    danger: !!opts.danger,
    buttons: [{ label: opts.okText || 'OK', value: true, primary: true }],
  });
}
</script>
