<?php /* Shared JS helpers for all analytics pages */ ?>
const base = '/analytics/api.php';

function setText(id, v) { const el=document.getElementById(id); if(el) el.textContent=v; }

function fmt(n) {
  if (n >= 1e6) return (n/1e6).toFixed(1)+'M';
  if (n >= 1e3) return (n/1e3).toFixed(0)+'k';
  return Math.round(n).toLocaleString('fr');
}

async function fetchAI(type, extra='') {
  const days = document.getElementById('periodSelect')?.value || 30;
  try {
    const r = await fetch(`${base}?type=${type}&days=${days}${extra}`);
    const d = await r.json();
    document.getElementById('aiDot')?.classList.add('online');
    document.getElementById('aiStatus') && (document.getElementById('aiStatus').textContent = 'digiMind · en ligne');
    return d;
  } catch(e) {
    document.getElementById('aiStatus') && (document.getElementById('aiStatus').textContent = 'Service indisponible');
    return {};
  }
}

function drawLineChart(canvasId, labels, values, color) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const dpr = window.devicePixelRatio || 1;
  canvas.width  = canvas.offsetWidth  * dpr;
  canvas.height = canvas.offsetHeight * dpr;
  ctx.scale(dpr, dpr);
  const W = canvas.offsetWidth, H = canvas.offsetHeight;
  const pad = {t:12, r:12, b:28, l:64};
  const cW = W - pad.l - pad.r, cH = H - pad.t - pad.b;
  const max = Math.max(...values) * 1.1 || 1;

  ctx.strokeStyle = '#f0f0f0'; ctx.lineWidth = 1;
  [0.25,0.5,0.75,1].forEach(pct => {
    const y = pad.t + cH*(1-pct);
    ctx.beginPath(); ctx.moveTo(pad.l,y); ctx.lineTo(W-pad.r,y); ctx.stroke();
    ctx.fillStyle='#9ca3af'; ctx.font='10px system-ui'; ctx.textAlign='right';
    ctx.fillText(fmt(max*pct), pad.l-6, y+3);
  });
  const step = Math.ceil(labels.length/6);
  ctx.fillStyle='#9ca3af'; ctx.font='10px system-ui'; ctx.textAlign='center';
  labels.forEach((l,i) => {
    if(i%step!==0) return;
    const x = pad.l + (i/(labels.length-1||1))*cW;
    const d = new Date(l);
    ctx.fillText(`${d.getDate()}/${d.getMonth()+1}`, x, H-pad.b+14);
  });
  const grad = ctx.createLinearGradient(0,pad.t,0,pad.t+cH);
  grad.addColorStop(0, color+'33'); grad.addColorStop(1, color+'00');
  ctx.beginPath();
  values.forEach((v,i) => {
    const x = pad.l+(i/(values.length-1||1))*cW;
    const y = pad.t+cH*(1-v/max);
    i===0 ? ctx.moveTo(x,y) : ctx.lineTo(x,y);
  });
  ctx.lineTo(pad.l+cW,pad.t+cH); ctx.lineTo(pad.l,pad.t+cH); ctx.closePath();
  ctx.fillStyle=grad; ctx.fill();
  ctx.beginPath(); ctx.strokeStyle=color; ctx.lineWidth=2.5; ctx.lineJoin='round';
  values.forEach((v,i) => {
    const x = pad.l+(i/(values.length-1||1))*cW;
    const y = pad.t+cH*(1-v/max);
    i===0 ? ctx.moveTo(x,y) : ctx.lineTo(x,y);
  });
  ctx.stroke();
  if(values.length) {
    const lx=pad.l+cW, ly=pad.t+cH*(1-values[values.length-1]/max);
    ctx.beginPath(); ctx.arc(lx,ly,4,0,Math.PI*2);
    ctx.fillStyle=color; ctx.fill();
    ctx.strokeStyle='#fff'; ctx.lineWidth=2; ctx.stroke();
  }
}
