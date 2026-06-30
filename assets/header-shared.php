<style>
/* ── Top bar ─────────────────────────────────────────────── */
.header {
    position: sticky; top: 0; z-index: 100;
    background: var(--ds-surface);
    border-bottom: 1px solid var(--ds-border-light);
}
.header-content {
    display: flex; align-items: center;
    height: 60px;
    padding: 0 20px;
    gap: 12px;
}
.header-left { display: flex; align-items: center; gap: 12px; flex: 1; }
.header-right { display: flex; align-items: center; gap: 4px; }

.menu-toggle {
    display: flex; align-items: center; justify-content: center;
    width: 38px; height: 38px;
    border-radius: 50%; border: none; background: transparent;
    cursor: pointer; color: var(--ds-text-600);
    transition: background .15s;
    flex-shrink: 0;
}
.menu-toggle:hover { background: var(--ds-surface-alt); }
.menu-toggle i { width: 20px; height: 20px; }

.header-page-title {
    font-family: 'Roboto', sans-serif;
    font-size: 17px; font-weight: 500;
    color: var(--ds-text-900);
    white-space: nowrap;
}

/* ── Icon buttons ─────────────────────────────────────────── */
.header-btn {
    position: relative;
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    background: transparent; border: none; border-radius: 50%;
    cursor: pointer; color: var(--ds-text-600);
    transition: background .15s;
}
.header-btn:hover { background: var(--ds-surface-alt); }
.header-btn i { width: 20px; height: 20px; }

/* ── Notification bell ───────────────────────────────────── */
.notif-wrapper { position: relative; }
.notif-badge {
    position: absolute; top: 2px; right: 2px;
    background: var(--ds-red); color: #fff;
    font-size: 10px; font-weight: 700;
    min-width: 16px; height: 16px; border-radius: 99px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 3px;
    border: 2px solid var(--ds-surface);
}
.notif-panel {
    position: absolute; top: calc(100% + 8px); right: -8px;
    width: 340px; background: var(--ds-surface);
    border: 1px solid var(--ds-border-light);
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,.12);
    opacity: 0; visibility: hidden; transform: translateY(-6px);
    transition: all .18s ease;
    z-index: 200;
    overflow: hidden;
}
.notif-panel.open { opacity: 1; visibility: visible; transform: translateY(0); }
.notif-panel-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 18px 12px;
    font-size: 14px; font-weight: 600; color: var(--ds-text-900);
    border-bottom: 1px solid var(--ds-border-light);
}
.notif-count-pill {
    background: var(--ds-red); color: #fff;
    font-size: 11px; font-weight: 700;
    padding: 2px 7px; border-radius: 99px;
}
.notif-list { max-height: 340px; overflow-y: auto; }
.notif-empty {
    display: flex; flex-direction: column; align-items: center;
    gap: 8px; padding: 36px 16px;
    color: var(--ds-text-400); font-size: 13px;
}
.notif-empty i { width: 32px; height: 32px; color: var(--ds-green); }
.notif-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 18px; text-decoration: none;
    border-bottom: 1px solid var(--ds-border-light);
    transition: background .12s;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: var(--ds-surface-alt); }
.notif-icon {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 2px;
}
.notif-icon i { width: 17px; height: 17px; }
.notif-warning .notif-icon { background: #fef3c7; color: #b45309; }
.notif-danger .notif-icon  { background: #fee2e2; color: var(--ds-red); }
.notif-info .notif-icon    { background: var(--ds-green-bg); color: var(--ds-green); }
.notif-title { font-size: 13px; font-weight: 500; color: var(--ds-text-900); line-height: 1.3; }
.notif-sub   { font-size: 11.5px; color: var(--ds-text-600); margin-top: 2px; }

/* ── User menu ───────────────────────────────────────────── */
.user-wrapper { position: relative; }
.user-btn {
    display: flex; align-items: center; gap: 6px;
    padding: 4px 8px 4px 4px;
    background: transparent; border: none; border-radius: 99px;
    cursor: pointer; transition: background .15s;
}
.user-btn:hover { background: var(--ds-surface-alt); }
.avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--ds-green);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 600; font-size: 13px;
    flex-shrink: 0;
}
.avatar.lg { width: 40px; height: 40px; font-size: 16px; }

.user-panel {
    position: absolute; top: calc(100% + 8px); right: 0;
    width: 240px; background: var(--ds-surface);
    border: 1px solid var(--ds-border-light);
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,.12);
    opacity: 0; visibility: hidden; transform: translateY(-6px);
    transition: all .18s ease;
    z-index: 200; overflow: hidden;
}
.user-panel.open { opacity: 1; visibility: visible; transform: translateY(0); }
.user-panel-head {
    display: flex; align-items: center; gap: 12px;
    padding: 16px;
    border-bottom: 1px solid var(--ds-border-light);
}
.up-name  { font-size: 13.5px; font-weight: 600; color: var(--ds-text-900); }
.up-role  { font-size: 12px; color: var(--ds-text-600); margin-top: 2px; }
.user-panel-menu { padding: 6px; }
.up-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 8px;
    text-decoration: none; color: var(--ds-text-600);
    font-size: 13.5px; font-weight: 500;
    transition: all .12s;
}
.up-item i { width: 16px; height: 16px; }
.up-item:hover { background: var(--ds-surface-alt); color: var(--ds-text-900); }
.up-item.danger { color: var(--ds-red); }
.up-item.danger:hover { background: #fee2e2; }
.up-sep { height: 1px; background: var(--ds-border-light); margin: 4px 0; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Sidebar toggle ─────────────────────────────────────
    const sidebar    = document.getElementById('sidebar');
    const overlay    = document.getElementById('sidebarOverlay');
    const menuBtn    = document.getElementById('menuToggle');
    const closeBtn   = document.getElementById('sidebarClose');

    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', function () {
            if (window.innerWidth >= 768) {
                sidebar.classList.toggle('collapsed');
            } else {
                sidebar.classList.add('show');
                if (overlay) overlay.classList.add('show');
            }
        });
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // ── Notifications dropdown ─────────────────────────────
    const notifBtn   = document.getElementById('notifBtn');
    const notifPanel = document.getElementById('notifPanel');
    const userBtn    = document.getElementById('userMenuToggle');
    const userPanel  = document.getElementById('userDropdown');

    function closeAll() {
        notifPanel?.classList.remove('open');
        userPanel?.classList.remove('open');
    }

    notifBtn?.addEventListener('click', function (e) {
        e.stopPropagation();
        const open = notifPanel.classList.contains('open');
        closeAll();
        if (!open) notifPanel.classList.add('open');
    });

    userBtn?.addEventListener('click', function (e) {
        e.stopPropagation();
        const open = userPanel.classList.contains('open');
        closeAll();
        if (!open) userPanel.classList.add('open');
    });

    document.addEventListener('click', closeAll);
    notifPanel?.addEventListener('click', e => e.stopPropagation());
    userPanel?.addEventListener('click',  e => e.stopPropagation());
});
</script>
