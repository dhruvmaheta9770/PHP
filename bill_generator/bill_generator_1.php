<?php
/* ============================================================
   DATABASE CONFIGURATION — change these four values only
   ============================================================ */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // your MySQL username
define('DB_PASS', '');            // your MySQL password
define('DB_NAME', 'bill_db');     // your database name
/* ============================================================ */

/* ---------- Connect to MySQL ---------- */
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    // Show a friendly message and stop if the DB is unreachable
    die('<div style="font-family:sans-serif;background:#1a1a2e;color:#f87171;padding:32px;text-align:center;min-height:100vh">
            <h2>⚠️ Database Connection Failed</h2>
            <p style="color:#94a3b8;margin-top:12px">' . htmlspecialchars($db->connect_error) . '</p>
            <p style="color:#64748b;margin-top:8px">Please check your DB credentials in the config section at the top of this file.</p>
         </div>');
}

$db->set_charset('utf8mb4');

/* ---------- Auto-create tables if they don't exist ---------- */
$db->multi_query("
    CREATE TABLE IF NOT EXISTS bills (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        bill_no     VARCHAR(20)    NOT NULL,
        cust_name   VARCHAR(150)   NOT NULL,
        mobile      VARCHAR(20)    NOT NULL,
        grand_total DECIMAL(12,2)  NOT NULL,
        bill_date   DATE           NOT NULL,
        created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS bill_items (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        bill_id     INT            NOT NULL,
        item_name   VARCHAR(200)   NOT NULL,
        qty         DECIMAL(10,2)  NOT NULL,
        price       DECIMAL(12,2)  NOT NULL,
        subtotal    DECIMAL(12,2)  NOT NULL,
        FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
// Flush multi_query results so subsequent queries work fine
while ($db->more_results()) { $db->next_result(); }

/* ============================================================
   Everything below is the original code — untouched
   ============================================================ */
$bill_data = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $items  = $_POST['items'] ?? [];

    if (empty($name))   $errors[] = "Customer name is required.";
    if (empty($mobile)) $errors[] = "Mobile number is required.";

    $processed_items = [];
    foreach ($items as $item) {
        $iname = trim($item['name'] ?? '');
        $qty   = floatval($item['qty'] ?? 0);
        $price = floatval($item['price'] ?? 0);
        if (!empty($iname) && $qty > 0 && $price > 0) {
            $processed_items[] = [
                'name'     => $iname,
                'qty'      => $qty,
                'price'    => $price,
                'subtotal' => $qty * $price,
            ];
        }
    }

    if (empty($processed_items)) $errors[] = "Please add at least one valid item.";

    if (empty($errors)) {
        $grand_total = array_sum(array_column($processed_items, 'subtotal'));
        $bill_no   = 'INV-' . strtoupper(substr(md5(time()), 0, 6));
        $bill_date = date('Y-m-d');

        $bill_data = [
            'name'    => $name,
            'mobile'  => $mobile,
            'items'   => $processed_items,
            'total'   => $grand_total,
            'bill_no' => $bill_no,
            'date'    => date('d M Y'),
        ];

        /* ---------- Save to database ---------- */
        $stmt = $db->prepare(
            "INSERT INTO bills (bill_no, cust_name, mobile, grand_total, bill_date)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssds', $bill_no, $name, $mobile, $grand_total, $bill_date);
        $stmt->execute();
        $bill_id = $stmt->insert_id;
        $stmt->close();

        $stmt_item = $db->prepare(
            "INSERT INTO bill_items (bill_id, item_name, qty, price, subtotal)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($processed_items as $it) {
            $stmt_item->bind_param('isddd',
                $bill_id,
                $it['name'],
                $it['qty'],
                $it['price'],
                $it['subtotal']
            );
            $stmt_item->execute();
        }
        $stmt_item->close();
        /* -------------------------------------- */
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bill Generator</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ======= DESIGN TOKENS ======= */
:root {
  --c-bg:         #0a0a12;
  --c-bg2:        #10101e;
  --c-surface:    #14142a;
  --c-surface2:   #1c1c36;
  --c-surface3:   #22224a;
  --c-border:     rgba(120,120,220,0.15);
  --c-border2:    rgba(120,120,220,0.28);

  --c-purple:     #8b5cf6;
  --c-purple-lt:  #a78bfa;
  --c-violet:     #6d28d9;
  --c-indigo:     #4f46e5;

  --c-teal:       #14b8a6;
  --c-teal-lt:    #5eead4;
  --c-teal-dk:    #0d9488;

  --c-amber:      #f59e0b;
  --c-amber-lt:   #fcd34d;
  --c-amber-dk:   #d97706;

  --c-pink:       #ec4899;
  --c-rose:       #f43f5e;

  --c-text:       #e2e8f0;
  --c-text2:      #94a3b8;
  --c-text3:      #64748b;
  --c-danger:     #f87171;

  --radius-sm:    8px;
  --radius-md:    12px;
  --radius-lg:    18px;
  --radius-xl:    24px;

  --glow-purple:  0 0 40px rgba(139,92,246,0.18);
  --glow-teal:    0 0 40px rgba(20,184,166,0.18);
  --glow-amber:   0 0 30px rgba(245,158,11,0.22);

  --font: 'Inter', sans-serif;
  --mono: 'JetBrains Mono', monospace;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: var(--font);
  background: var(--c-bg);
  color: var(--c-text);
  min-height: 100vh;
  padding: 48px 20px 80px;
  overflow-x: hidden;
}

/* ---- Background blobs ---- */
body::before, body::after {
  content: '';
  position: fixed;
  border-radius: 50%;
  pointer-events: none;
  z-index: 0;
  filter: blur(80px);
}
body::before {
  width: 600px; height: 600px;
  top: -150px; right: -150px;
  background: radial-gradient(circle, rgba(109,40,217,0.22) 0%, transparent 70%);
}
body::after {
  width: 500px; height: 500px;
  bottom: -100px; left: -120px;
  background: radial-gradient(circle, rgba(20,184,166,0.14) 0%, transparent 70%);
}

.container {
  max-width: 820px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}

/* ======= HEADER ======= */
.header {
  display: flex;
  align-items: center;
  gap: 18px;
  margin-bottom: 44px;
  animation: slideDown 0.5s cubic-bezier(.22,1,.36,1) both;
}

.header-logo {
  width: 56px; height: 56px;
  border-radius: 16px;
  background: linear-gradient(135deg, var(--c-violet), var(--c-purple));
  display: grid; place-items: center;
  font-size: 26px;
  box-shadow: 0 0 0 1px rgba(139,92,246,0.4), var(--glow-purple);
  flex-shrink: 0;
}

.header-text h1 {
  font-size: 2rem;
  font-weight: 900;
  letter-spacing: -1px;
  line-height: 1;
  background: linear-gradient(90deg, #e2e8f0 0%, #a78bfa 60%, #38bdf8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.header-text p {
  font-size: 0.82rem;
  color: var(--c-text3);
  margin-top: 4px;
  letter-spacing: 0.3px;
}

/* ======= CARD ======= */
.card {
  background: var(--c-surface);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-lg);
  padding: 28px 28px 24px;
  margin-bottom: 16px;
  animation: fadeUp 0.45s cubic-bezier(.22,1,.36,1) both;
  position: relative;
  overflow: hidden;
}

.card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(139,92,246,0.5), rgba(20,184,166,0.3), transparent);
}

.card-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 2.5px;
  text-transform: uppercase;
  color: var(--c-purple-lt);
  margin-bottom: 20px;
}

.card-label::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--c-border);
}

/* ======= FORM FIELDS ======= */
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.field  { display: flex; flex-direction: column; gap: 7px; }

label {
  font-size: 0.71rem;
  font-weight: 600;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--c-text3);
}

input[type="text"],
input[type="number"],
input[type="tel"] {
  background: var(--c-surface2);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-sm);
  color: var(--c-text);
  font-family: var(--font);
  font-size: 0.93rem;
  padding: 11px 14px;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
  width: 100%;
}

input[type="text"]:hover,
input[type="number"]:hover,
input[type="tel"]:hover {
  border-color: var(--c-border2);
  background: var(--c-surface3);
}

input[type="text"]:focus,
input[type="number"]:focus,
input[type="tel"]:focus {
  border-color: var(--c-purple);
  box-shadow: 0 0 0 3px rgba(139,92,246,0.18);
  background: var(--c-surface3);
}

input::placeholder { color: var(--c-text3); font-size: 0.88rem; }

/* ======= ITEMS TABLE ======= */
.items-cols {
  display: grid;
  grid-template-columns: 1fr 80px 105px 105px 42px;
  gap: 10px;
}

.items-head {
  padding: 0 0 10px;
  border-bottom: 1px solid var(--c-border);
  margin-bottom: 12px;
}

.items-head span {
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--c-text3);
}

.items-head span:nth-child(4) { color: var(--c-teal); }

#items-container { display: flex; flex-direction: column; gap: 10px; }

.item-row {
  display: grid;
  grid-template-columns: 1fr 80px 105px 105px 42px;
  gap: 10px;
  align-items: center;
  animation: rowSlide 0.22s cubic-bezier(.22,1,.36,1) both;
}

/* live subtotal */
.row-sub {
  font-family: var(--mono);
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--c-teal-lt);
  text-align: right;
  padding-right: 2px;
  transition: color 0.2s;
  letter-spacing: -0.3px;
}
.row-sub.zero { color: var(--c-text3); }

/* minus button */
.btn-minus {
  width: 40px; height: 40px;
  border-radius: var(--radius-sm);
  border: 1px solid rgba(248,113,113,0.25);
  background: rgba(248,113,113,0.08);
  color: var(--c-danger);
  font-size: 1.3rem; font-weight: 700;
  cursor: pointer;
  display: grid; place-items: center;
  transition: background 0.15s, border-color 0.15s, transform 0.1s;
}
.btn-minus:hover {
  background: rgba(248,113,113,0.2);
  border-color: rgba(248,113,113,0.5);
  transform: scale(1.08);
}

/* add button */
.btn-add {
  width: 100%; margin-top: 14px;
  padding: 13px;
  background: transparent;
  border: 1px dashed rgba(139,92,246,0.35);
  border-radius: var(--radius-md);
  color: var(--c-purple-lt);
  font-family: var(--font);
  font-size: 0.9rem; font-weight: 600;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
  letter-spacing: 0.3px;
}
.btn-add:hover {
  background: rgba(139,92,246,0.1);
  border-color: var(--c-purple);
  color: #fff;
}
.btn-add::before { content: '+'; font-size: 1.2rem; font-weight: 700; }

/* ======= SUMMARY BAR ======= */
.summary-bar {
  background: var(--c-surface2);
  border: 1px solid var(--c-border2);
  border-radius: var(--radius-lg);
  padding: 20px 24px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  position: relative;
  overflow: hidden;
  animation: fadeUp 0.5s ease both;
  animation-delay: 0.15s;
}

.summary-bar::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(139,92,246,0.06), rgba(20,184,166,0.04));
  pointer-events: none;
}

.sum-stats { display: flex; gap: 32px; flex-wrap: wrap; }

.sum-stat { display: flex; flex-direction: column; gap: 3px; }
.sum-stat .lbl {
  font-size: 0.58rem; font-weight: 700;
  letter-spacing: 2.5px; text-transform: uppercase;
  color: var(--c-text3);
}
.sum-stat .val {
  font-family: var(--mono);
  font-size: 1.05rem; font-weight: 600;
  color: var(--c-text);
  transition: color 0.15s;
  letter-spacing: -0.5px;
}

/* Grand total pill */
.sum-total {
  display: flex; flex-direction: column; align-items: flex-end; gap: 3px;
  background: linear-gradient(135deg, var(--c-violet), var(--c-indigo));
  border-radius: var(--radius-md);
  padding: 12px 22px;
  box-shadow: 0 4px 20px rgba(109,40,217,0.35), 0 0 0 1px rgba(139,92,246,0.4);
  flex-shrink: 0;
  min-width: 150px;
}
.sum-total .lbl {
  font-size: 0.58rem; font-weight: 700;
  letter-spacing: 2.5px; text-transform: uppercase;
  color: rgba(255,255,255,0.55);
}
.sum-total .val {
  font-family: var(--mono);
  font-size: 1.45rem; font-weight: 700;
  color: #fff;
  letter-spacing: -1px;
  transition: transform 0.18s;
}

@keyframes bump {
  0%   { transform: scale(1); }
  40%  { transform: scale(1.1); }
  100% { transform: scale(1); }
}
.bump { animation: bump 0.22s ease; }

/* ======= ERRORS ======= */
.errors {
  background: rgba(248,113,113,0.07);
  border: 1px solid rgba(248,113,113,0.3);
  border-radius: var(--radius-md);
  padding: 14px 20px;
  margin-bottom: 18px;
}
.errors p {
  color: var(--c-danger);
  font-size: 0.87rem; margin-bottom: 3px;
  display: flex; align-items: center; gap: 7px;
}
.errors p::before { content: '⚠'; }

/* ======= SUBMIT ======= */
.btn-submit {
  width: 100%; padding: 16px;
  background: linear-gradient(135deg, var(--c-violet) 0%, var(--c-indigo) 60%, var(--c-teal-dk) 100%);
  color: #fff;
  border: none; border-radius: var(--radius-md);
  font-family: var(--font);
  font-size: 0.95rem; font-weight: 700;
  letter-spacing: 1px; text-transform: uppercase;
  cursor: pointer;
  box-shadow: 0 4px 24px rgba(109,40,217,0.4);
  transition: opacity 0.2s, transform 0.15s, box-shadow 0.15s;
  position: relative; overflow: hidden;
}
.btn-submit::after {
  content: '→';
  position: absolute; right: 24px; top: 50%;
  transform: translateY(-50%);
  font-size: 1.1rem;
  transition: right 0.2s;
}
.btn-submit:hover {
  opacity: 0.92;
  transform: translateY(-2px);
  box-shadow: 0 8px 32px rgba(109,40,217,0.5);
}
.btn-submit:hover::after { right: 20px; }
.btn-submit:active { transform: translateY(0); }

/* ======= BILL OUTPUT ======= */
.bill-wrap {
  background: #fff;
  border-radius: var(--radius-xl);
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.5);
  animation: fadeUp 0.5s ease both;
  margin-top: 28px;
}

.bill-top {
  background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #1e3a5f 100%);
  padding: 32px 36px 28px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.bill-brand {
  font-family: var(--font);
  font-size: 1.6rem;
  font-weight: 900;
  color: #fff;
  letter-spacing: -1px;
}
.bill-brand em {
  font-style: normal;
  color: #fcd34d;
}
.bill-brand-sub {
  font-size: 0.72rem;
  color: rgba(255,255,255,0.45);
  letter-spacing: 2px;
  text-transform: uppercase;
  margin-top: 4px;
}

.bill-inv { text-align: right; }
.bill-inv .inv-no {
  font-family: var(--mono);
  font-size: 0.95rem; font-weight: 600;
  color: #fcd34d;
  letter-spacing: -0.3px;
}
.bill-inv .inv-date {
  font-size: 0.78rem;
  color: rgba(255,255,255,0.45);
  margin-top: 5px;
  letter-spacing: 0.3px;
}

.bill-body { padding: 28px 36px 32px; }

/* customer strip */
.bill-cust {
  display: flex; gap: 0;
  margin-bottom: 28px;
  border: 1px solid #e8e8f0;
  border-radius: 10px;
  overflow: hidden;
}
.bill-cust-col {
  flex: 1; padding: 14px 18px;
  border-right: 1px solid #e8e8f0;
}
.bill-cust-col:last-child { border-right: none; }
.bill-cust-col .c-lbl {
  font-size: 0.6rem; font-weight: 700;
  letter-spacing: 2px; text-transform: uppercase;
  color: #94a3b8; margin-bottom: 4px;
}
.bill-cust-col .c-val {
  font-size: 0.97rem; font-weight: 700; color: #1e1b4b;
}

/* items table */
.bill-table {
  width: 100%; border-collapse: collapse;
  font-size: 0.85rem; margin-bottom: 0;
}
.bill-table thead tr {
  background: #f8f7ff;
}
.bill-table thead th {
  padding: 10px 14px;
  text-align: left;
  font-size: 0.62rem; font-weight: 700;
  letter-spacing: 2px; text-transform: uppercase;
  color: #7c3aed;
  border-bottom: 2px solid #e8e8f0;
}
.bill-table thead th:last-child { text-align: right; }

.bill-table tbody tr {
  border-bottom: 1px solid #f0f0f6;
  transition: background 0.1s;
}
.bill-table tbody tr:hover { background: #fafaff; }
.bill-table tbody td {
  padding: 12px 14px;
  color: #334155;
  font-family: var(--mono);
  font-size: 0.83rem;
}
.bill-table tbody td:first-child {
  font-family: var(--font);
  color: #94a3b8;
  font-size: 0.75rem; font-weight: 600;
}
.bill-table tbody td:nth-child(2) {
  font-family: var(--font);
  color: #1e293b; font-weight: 600;
  font-size: 0.88rem;
}
.bill-table tbody td:last-child {
  text-align: right; font-weight: 700; color: #3730a3;
}

/* total row */
.bill-footer-row {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 24px;
  border-top: 2px solid #e8e8f0;
  padding-top: 20px;
  margin-top: 8px;
}

.bill-subtotals { text-align: right; }
.bill-subtotals .sub-line {
  font-size: 0.8rem; color: #94a3b8;
  display: flex; justify-content: flex-end;
  align-items: center; gap: 16px; margin-bottom: 4px;
}
.bill-subtotals .sub-line span { font-family: var(--mono); color: #475569; }

.bill-total-pill {
  background: linear-gradient(135deg, #312e81, #4338ca);
  color: #fff;
  border-radius: 12px;
  padding: 14px 24px;
  text-align: right;
  box-shadow: 0 4px 20px rgba(67,56,202,0.4);
}
.bill-total-pill .t-lbl {
  font-size: 0.6rem; font-weight: 700;
  letter-spacing: 2.5px; text-transform: uppercase;
  color: rgba(255,255,255,0.55);
  margin-bottom: 3px;
}
.bill-total-pill .t-val {
  font-family: var(--mono);
  font-size: 1.4rem; font-weight: 700;
  letter-spacing: -1px;
}

.bill-thank {
  text-align: center;
  margin-top: 24px;
  padding: 16px;
  background: #f8f7ff;
  border-radius: 8px;
  font-size: 0.78rem;
  color: #94a3b8;
  letter-spacing: 0.5px;
  border: 1px dashed #e0deff;
}
.bill-thank strong { color: #7c3aed; }

/* ======= ANIMATIONS ======= */
@keyframes slideDown {
  from { opacity:0; transform: translateY(-24px); }
  to   { opacity:1; transform: translateY(0); }
}
@keyframes fadeUp {
  from { opacity:0; transform: translateY(18px); }
  to   { opacity:1; transform: translateY(0); }
}
@keyframes rowSlide {
  from { opacity:0; transform: translateX(-12px); }
  to   { opacity:1; transform: translateX(0); }
}

/* ======= RESPONSIVE ======= */
@media (max-width: 620px) {
  .row-2 { grid-template-columns: 1fr; }
  .items-cols,
  .item-row { grid-template-columns: 1fr 64px 85px 80px 38px; gap: 6px; }
  .sum-stats { gap: 18px; }
  .bill-top { flex-direction: column; gap: 14px; }
  .bill-inv { text-align: left; }
  .bill-body { padding: 20px 18px 24px; }
  .bill-footer-row { flex-direction: column; align-items: flex-end; }
}
</style>
</head>
<body>
<div class="container">

<!-- HEADER -->
<div class="header">
  <div class="header-logo">🧾</div>
  <div class="header-text">
    <h1>Bill Generator</h1>
    <p>Create professional invoices in seconds</p>
  </div>
</div>

<!-- ERRORS -->
<?php if (!empty($errors)): ?>
<div class="errors" style="animation:fadeUp .3s ease both">
  <?php foreach ($errors as $e): ?>
    <p><?= htmlspecialchars($e) ?></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$bill_data): ?>
<!-- ======= FORM ======= -->
<form method="POST" action="">

  <!-- Customer -->
  <div class="card" style="animation-delay:.04s">
    <div class="card-label">Customer Info</div>
    <div class="row-2">
      <div class="field">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name"
               placeholder="e.g. Ramesh Patel"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="mobile">Mobile Number</label>
        <input type="tel" id="mobile" name="mobile"
               placeholder="e.g. 9876543210"
               value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Items -->
  <div class="card" style="animation-delay:.1s">
    <div class="card-label">Items</div>

    <div class="items-cols items-head">
      <span>Item Name</span>
      <span>Qty</span>
      <span>Price (₹)</span>
      <span>Subtotal</span>
      <span></span>
    </div>

    <div id="items-container">
      <div class="item-row">
        <input type="text"   name="items[0][name]"  placeholder="Item name" oninput="recalc()">
        <input type="number" name="items[0][qty]"   placeholder="1"   min="1" step="any" oninput="recalc()">
        <input type="number" name="items[0][price]" placeholder="0.00" min="0" step="any" oninput="recalc()">
        <span class="row-sub zero">₹0.00</span>
        <button type="button" class="btn-minus" onclick="removeRow(this)" title="Remove">−</button>
      </div>
    </div>

    <button type="button" class="btn-add" onclick="addRow()">Add Item</button>
  </div>

  <!-- Summary Bar -->
  <div class="summary-bar">
    <div class="sum-stats">
      <div class="sum-stat">
        <span class="lbl">Items</span>
        <span class="val" id="s-items">0</span>
      </div>
      <div class="sum-stat">
        <span class="lbl">Total Qty</span>
        <span class="val" id="s-qty">0</span>
      </div>
      <div class="sum-stat">
        <span class="lbl">Avg Price</span>
        <span class="val" id="s-avg">₹0.00</span>
      </div>
    </div>
    <div class="sum-total">
      <span class="lbl">Grand Total</span>
      <span class="val" id="s-total">₹0.00</span>
    </div>
  </div>

  <button type="submit" class="btn-submit">Generate Invoice</button>
</form>

<?php else: ?>
<!-- ======= BILL OUTPUT ======= -->
<div class="bill-wrap">

  <div class="bill-top">
    <div>
      <div class="bill-brand">MY<em>STORE</em></div>
      <div class="bill-brand-sub">Tax Invoice</div>
    </div>
    <div class="bill-inv">
      <div class="inv-no"><?= htmlspecialchars($bill_data['bill_no']) ?></div>
      <div class="inv-date">📅 <?= $bill_data['date'] ?></div>
    </div>
  </div>

  <div class="bill-body">

    <div class="bill-cust">
      <div class="bill-cust-col">
        <div class="c-lbl">Billed To</div>
        <div class="c-val"><?= htmlspecialchars($bill_data['name']) ?></div>
      </div>
      <div class="bill-cust-col">
        <div class="c-lbl">Mobile</div>
        <div class="c-val"><?= htmlspecialchars($bill_data['mobile']) ?></div>
      </div>
      <div class="bill-cust-col">
        <div class="c-lbl">Items Count</div>
        <div class="c-val"><?= count($bill_data['items']) ?> item<?= count($bill_data['items']) > 1 ? 's' : '' ?></div>
      </div>
    </div>

    <table class="bill-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Description</th>
          <th>Qty</th>
          <th>Unit Price</th>
          <th>Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bill_data['items'] as $i => $item): ?>
        <tr>
          <td><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></td>
          <td style="font-family:var(--font)"><?= htmlspecialchars($item['name']) ?></td>
          <td><?= htmlspecialchars($item['qty']) ?></td>
          <td>₹<?= number_format($item['price'], 2) ?></td>
          <td>₹<?= number_format($item['subtotal'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="bill-footer-row">
      <div class="bill-total-pill">
        <div class="t-lbl">Grand Total</div>
        <div class="t-val">₹<?= number_format($bill_data['total'], 2) ?></div>
      </div>
    </div>

    <div class="bill-thank">
      Thank you for shopping with <strong>MyStore</strong>! &nbsp;·&nbsp; Items once sold are non-refundable.
    </div>

  </div><!-- /bill-body -->
</div><!-- /bill-wrap -->

<form method="GET" action="" style="margin-top:20px">
  <button type="submit" class="btn-submit" style="background:linear-gradient(135deg,#0f172a,#1e293b)">← Create New Bill</button>
</form>

<?php endif; ?>
</div><!-- /container -->

<script>
let rowIdx = 1;

function recalc() {
  const rows = document.querySelectorAll('.item-row');
  let grand = 0, totalQty = 0, totalPrice = 0, validCount = 0;

  rows.forEach(row => {
    const qty   = parseFloat(row.querySelector('[name*="[qty]"]')?.value)   || 0;
    const price = parseFloat(row.querySelector('[name*="[price]"]')?.value) || 0;
    const sub   = qty * price;
    const pill  = row.querySelector('.row-sub');

    if (pill) {
      pill.textContent = '₹' + sub.toFixed(2);
      pill.classList.toggle('zero', sub === 0);
    }

    if (qty > 0 && price > 0) {
      grand      += sub;
      totalQty   += qty;
      totalPrice += price;
      validCount++;
    }
  });

  document.getElementById('s-items').textContent = validCount;
  document.getElementById('s-qty').textContent   = totalQty % 1 === 0 ? totalQty : totalQty.toFixed(2);
  document.getElementById('s-avg').textContent   = '₹' + (validCount ? (totalPrice / validCount).toFixed(2) : '0.00');

  const totalEl = document.getElementById('s-total');
  totalEl.textContent = '₹' + grand.toFixed(2);
  totalEl.classList.remove('bump');
  void totalEl.offsetWidth;
  totalEl.classList.add('bump');
}

function addRow() {
  const container = document.getElementById('items-container');
  const div = document.createElement('div');
  div.className = 'item-row';
  div.innerHTML = `
    <input type="text"   name="items[${rowIdx}][name]"  placeholder="Item name" oninput="recalc()">
    <input type="number" name="items[${rowIdx}][qty]"   placeholder="1"    min="1"  step="any" oninput="recalc()">
    <input type="number" name="items[${rowIdx}][price]" placeholder="0.00" min="0"  step="any" oninput="recalc()">
    <span class="row-sub zero">₹0.00</span>
    <button type="button" class="btn-minus" onclick="removeRow(this)" title="Remove">−</button>
  `;
  container.appendChild(div);
  rowIdx++;
  div.querySelector('input[type="text"]').focus();
  recalc();
}

function removeRow(btn) {
  const container = document.getElementById('items-container');
  if (container.children.length <= 1) {
    container.querySelector('.item-row').querySelectorAll('input').forEach(i => i.value = '');
    recalc();
    return;
  }
  btn.closest('.item-row').remove();
  recalc();
}

recalc();
</script>
</body>
</html>