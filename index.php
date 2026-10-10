<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>API Scheduler</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
  <div class="wrap">
    <header>
      <h1>API Scheduler</h1>
      <p class="subtitle">Local sequential HTTP request scheduler &middot; mock endpoint</p>
    </header>

    <section class="panel" id="setup-panel">
      <label for="values">Test values (one per line)</label>
      <textarea id="values" rows="10" placeholder="TEST_VALUE_1&#10;TEST_VALUE_2&#10;TEST_VALUE_3"></textarea>

      <div class="row">
        <div class="field">
          <label for="interval">Interval</label>
          <select id="interval">
            <option value="15">15 seconds</option>
            <option value="30">30 seconds</option>
          </select>
        </div>
        <div class="field buttons">
          <button id="start-btn" class="btn btn-start">START</button>
          <button id="stop-btn" class="btn btn-stop" disabled>STOP</button>
          <button id="reset-btn" class="btn btn-ghost">New batch</button>
        </div>
      </div>
      <p class="hint" id="setup-hint"></p>
    </section>

    <section class="panel" id="status-panel">
      <div class="status-header">
        <span id="status-badge" class="badge badge-idle">NO BATCH</span>
        <span id="countdown" class="countdown"></span>
      </div>

      <div class="grid">
        <div class="stat"><div class="stat-label">Total</div><div class="stat-value" id="total-count">-</div></div>
        <div class="stat"><div class="stat-label">Processed</div><div class="stat-value" id="processed-count">-</div></div>
        <div class="stat"><div class="stat-label">Remaining</div><div class="stat-value" id="remaining-count">-</div></div>
        <div class="stat"><div class="stat-label">Last HTTP status</div><div class="stat-value" id="last-http-status">-</div></div>
      </div>

      <div class="detail">
        <label>Current value</label>
        <div class="mono" id="current-value">-</div>
      </div>
      <div class="detail">
        <label>Last API response</label>
        <pre class="mono response-box" id="last-response">-</pre>
      </div>
      <div class="detail">
        <label>Items</label>
        <div class="items-table" id="items-table"></div>
      </div>
    </section>

    <footer><span>Batch id: <span id="batch-id-label">none</span></span></footer>
  </div>

  <script src="assets/app.js"></script>
</body>
</html>