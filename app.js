// app.js — dashboard logic for API Scheduler (PHP/MySQL backend)
// The server + cron job are the source of truth for run state; this script
// creates/starts/stops batches and polls status.php to render it, and its
// own polling also nudges progress along while the tab is open.

const STORAGE_KEY = "apiScheduler.batchId";

const els = {
  values: document.getElementById("values"),
  interval: document.getElementById("interval"),
  startBtn: document.getElementById("start-btn"),
  stopBtn: document.getElementById("stop-btn"),
  resetBtn: document.getElementById("reset-btn"),
  hint: document.getElementById("setup-hint"),
  badge: document.getElementById("status-badge"),
  countdown: document.getElementById("countdown"),
  total: document.getElementById("total-count"),
  processed: document.getElementById("processed-count"),
  remaining: document.getElementById("remaining-count"),
  lastHttpStatus: document.getElementById("last-http-status"),
  currentValue: document.getElementById("current-value"),
  lastResponse: document.getElementById("last-response"),
  itemsTable: document.getElementById("items-table"),
  batchIdLabel: document.getElementById("batch-id-label"),
};

let batchId = localStorage.getItem(STORAGE_KEY) || null;
let pollTimer = null;

function setHint(msg) { els.hint.textContent = msg || ""; }

function badgeClassFor(status) {
  switch (status) {
    case "RUNNING": return "badge badge-running";
    case "STOPPED": return "badge badge-stopped";
    case "COMPLETED": return "badge badge-completed";
    case "CREATED": return "badge badge-created";
    default: return "badge badge-idle";
  }
}

function renderIdle() {
  els.badge.className = "badge badge-idle";
  els.badge.textContent = "NO BATCH";
  els.countdown.textContent = "";
  els.total.textContent = "-";
  els.processed.textContent = "-";
  els.remaining.textContent = "-";
  els.lastHttpStatus.textContent = "-";
  els.currentValue.textContent = "-";
  els.lastResponse.textContent = "-";
  els.itemsTable.innerHTML = "";
  els.batchIdLabel.textContent = "none";
  els.startBtn.disabled = false;
  els.stopBtn.disabled = true;
  els.values.disabled = false;
  els.interval.disabled = false;
}

function renderStatus(data) {
  els.badge.className = badgeClassFor(data.status);
  els.badge.textContent = data.status;

  if (data.status === "RUNNING" && data.countdown_seconds !== null && data.countdown_seconds !== undefined) {
    els.countdown.textContent = `next request in ${data.countdown_seconds}s`;
  } else {
    els.countdown.textContent = "";
  }

  els.total.textContent = data.total_count;
  els.processed.textContent = data.processed_count;
  els.remaining.textContent = data.remaining_count;
  els.lastHttpStatus.textContent = data.last_http_status ?? "-";
  els.currentValue.textContent = data.current_value ?? "-";
  els.lastResponse.textContent = data.last_response
    ? formatResponse(data.last_response)
    : (data.last_error ? `error: ${data.last_error}` : "-");

  els.batchIdLabel.textContent = data.id;
  renderItems(data.items || []);

  const running = data.status === "RUNNING";
  els.startBtn.disabled = running;
  els.stopBtn.disabled = !running;
  els.values.disabled = true;
  els.interval.disabled = true;

  if (data.status === "COMPLETED") stopPolling();
}

function formatResponse(text) {
  try { return JSON.stringify(JSON.parse(text), null, 2); } catch { return text; }
}

function renderItems(items) {
  els.itemsTable.innerHTML = items.map((item) => `
      <div class="item-row">
        <span>${item.seq + 1}. ${escapeHtml(item.value)}</span>
        <span class="item-status ${item.status}">${item.status}</span>
      </div>`).join("");
}

function escapeHtml(str) {
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML;
}

async function fetchStatus() {
  if (!batchId) return;
  const res = await fetch(`api/status.php?id=${batchId}`);
  if (!res.ok) {
    if (res.status === 404) clearBatch();
    return;
  }
  renderStatus(await res.json());
}

function startPolling() {
  if (pollTimer) return;
  pollTimer = setInterval(fetchStatus, 1000);
}

function stopPolling() {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}

function clearBatch() {
  batchId = null;
  localStorage.removeItem(STORAGE_KEY);
  stopPolling();
  renderIdle();
}

async function createBatch() {
  const values = els.values.value;
  const interval = parseInt(els.interval.value, 10);

  const res = await fetch("api/create_batch.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ values, interval_seconds: interval }),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    setHint(err.detail || "Could not create batch. Add at least one non-empty value.");
    return null;
  }

  setHint("");
  const data = await res.json();
  batchId = data.id;
  localStorage.setItem(STORAGE_KEY, batchId);
  return data;
}

async function startExisting() {
  const res = await fetch(`api/start_batch.php?id=${batchId}`, { method: "POST" });
  if (!res.ok) { setHint("Could not start batch."); return; }
  renderStatus(await res.json());
}

async function stopExisting() {
  const res = await fetch(`api/stop_batch.php?id=${batchId}`, { method: "POST" });
  if (res.ok) renderStatus(await res.json());
}

els.startBtn.addEventListener("click", async () => {
  els.startBtn.disabled = true;
  try {
    if (!batchId) {
      const created = await createBatch();
      if (!created) { els.startBtn.disabled = false; return; }
    }
    await startExisting();
    startPolling();
    fetchStatus();
  } catch (e) {
    setHint("Unexpected error starting the batch.");
    els.startBtn.disabled = false;
  }
});

els.stopBtn.addEventListener("click", async () => { await stopExisting(); });

els.resetBtn.addEventListener("click", () => {
  clearBatch();
  els.values.value = "";
  setHint("");
});

(function init() {
  if (batchId) { startPolling(); fetchStatus(); }
  else { renderIdle(); }
})();