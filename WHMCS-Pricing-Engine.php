<?php
/**
 * WHMCS Advanced Pricing Engine
 *
 * An open-source multi-currency calculation and margin optimization engine
 * designed for WHMCS hosting products, VPS, and cloud infrastructure.
 *
 * @package    WHMCS Advanced Pricing Engine
 * @author     Shihab Mahmud
 * @copyright  (c) Dizenlab (https://www.dizenlab.com)
 * @license    MIT License
 * @link       https://github.com/dizenlab/whmcs-pricing-engine
 */

declare(strict_types=1);

// Configuration & Cache Settings
define('CACHE_FILE', __DIR__ . '/rates_cache.json');
define('CACHE_LIFETIME', 3600 * 6); // 6 hours

function getExchangeRates(string $baseCurrency): array {
    $baseCurrency = strtoupper($baseCurrency);
    
    if (file_exists(CACHE_FILE)) {
        $cached = json_decode(file_get_contents(CACHE_FILE), true);
        if ($cached && isset($cached['base'],$cached['time']) && $cached['base'] ===$baseCurrency) {
            if ((time() - $cached['time']) < CACHE_LIFETIME) {
                return $cached['rates'];
            }
        }
    }

    // Server-side fallback rate fetcher
    $endpoints = [
        "https://open.er-api.com/v6/latest/{$baseCurrency}",
        "https://api.exchangerate-api.com/v4/latest/{$baseCurrency}"
    ];

    $rates = [];
    foreach ($endpoints as$url) {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);$response = @file_get_contents($url, false,$ctx);
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data['rates'])) {
                $rates =$data['rates'];
                break;
            }
        }
    }

    if (!empty($rates)) {
        file_put_contents(CACHE_FILE, json_encode([
            'base'  => $baseCurrency,
            'time'  => time(),
            'rates' => $rates
        ]));
    }

    return $rates;
}

// Ajax rates endpoint
if (isset($_GET['action']) &&$_GET['action'] === 'fetch_rates') {
    header('Content-Type: application/json');
    $base = preg_replace('/[^A-Z]/', '', strtoupper($_GET['base'] ?? 'EUR'));
    echo json_encode([
        'success' => true,
        'base'    => $base,
        'rates'   => getExchangeRates($base)
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Pricing Engine | Dizenlab</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --primary: #118296;
            --primary-hover: #0e697a;
            --border: #e2e8f0;
            --success: #10b981;
            --danger: #ef4444;
            --highlight: rgba(17, 130, 150, 0.08);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-page: #0f172a;
                --bg-card: #1e293b;
                --text-main: #f8fafc;
                --text-muted: #94a3b8;
                --border: #334155;
                --highlight: rgba(17, 130, 150, 0.15);
            }
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-page); color: var(--text-main); padding: 30px 15px; }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 2rem; font-weight: 800; }
        .header p { color: var(--text-muted); font-size: 0.9rem; margin-top: 6px; }
        .header a { color: var(--primary); text-decoration: none; font-weight: 600; }

        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 22px; margin-bottom: 24px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-6 { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; }

        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .form-group label { font-size: 0.8rem; font-weight: 600; margin-bottom: 6px; }
        input, select {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px;
            background: transparent; color: var(--text-main); font-size: 0.9rem; outline: none;
        }
        input:focus, select:focus { border-color: var(--primary); }

        .btn {
            padding: 6px 12px; font-size: 0.75rem; font-weight: 700; border: 1px solid var(--border);
            border-radius: 4px; background: transparent; color: var(--primary); cursor: pointer; text-transform: uppercase;
        }
        .btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn-danger { color: var(--danger); border-color: var(--danger); }
        .btn-danger:hover { background: var(--danger); }

        .currency-box { max-height: 180px; overflow-y: auto; border: 1px solid var(--border); border-radius: 6px; padding: 10px; display: grid; grid-template-columns: repeat(auto-fill, minmax(95px, 1fr)); gap: 8px; }
        .currency-label { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 6px; border: 1px solid var(--border); border-radius: 4px; font-size: 0.75rem; font-weight: 600; cursor: pointer; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid var(--border); padding: 12px; text-align: left; font-size: 0.85rem; }
        th { background: rgba(0,0,0,0.02); }

        .price-list { list-style: none; display: flex; flex-wrap: wrap; gap: 12px; }
        .price-list li { background: rgba(0,0,0,0.02); border: 1px solid var(--border); border-radius: 6px; padding: 10px; min-width: 130px; position: relative; }
        .badge { position: absolute; top: -7px; right: -7px; background: var(--primary); color: #fff; font-size: 0.6rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; }
        .price-val { font-size: 1.1rem; font-weight: 800; color: var(--primary); cursor: pointer; }

        .footer { text-align: center; margin-top: 40px; font-size: 0.8rem; color: var(--text-muted); }
        .footer a { color: var(--primary); text-decoration: none; }

        @media (max-width: 900px) {
            .grid-2 { grid-template-columns: 1fr; }
            .grid-6 { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>WHMCS Pricing Engine</h1>
        <p>Engineered by <strong>Shihab Mahmud</strong> &bull; Released by <a href="https://www.dizenlab.com" target="_blank">Dizenlab</a></p>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3 style="margin-bottom: 15px; font-size: 0.95rem;">Configuration</h3>
            <div class="grid-2">
                <div class="form-group">
                    <label>Base Cost / Month</label>
                    <input type="number" id="baseCost" placeholder="e.g. 10.00" step="any" min="0">
                </div>
                <div class="form-group">
                    <label>Base Currency</label>
                    <select id="baseCurrency">
                        <option value="EUR" selected>EUR (€)</option>
                        <option value="USD">USD ($)</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="BDT">BDT (৳)</option>
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Target Profit (%)</label>
                    <input type="number" id="profitPercent" placeholder="e.g. 25" min="0">
                </div>
                <div class="form-group">
                    <label>Calculation Method</label>
                    <select id="profitMethod">
                        <option value="markup">Markup (Cost + %)</option>
                        <option value="margin">Margin (% of Gross)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Target Currencies</label>
                <div style="display:flex; gap:6px; margin-bottom:8px;">
                    <button class="btn" onclick="selectCurr('top')">Top Currencies</button>
                    <button class="btn" onclick="selectCurr('clear')">Clear</button>
                </div>
                <div class="currency-box" id="currencyList"></div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 15px; font-size: 0.95rem;">Period Discount Strategy (%)</h3>
            <div class="grid-6">
                <div class="form-group"><label>1 Mo</label><input type="number" class="cycle-disc" data-months="1" placeholder="0"></div>
                <div class="form-group"><label>3 Mo</label><input type="number" class="cycle-disc" data-months="3" placeholder="3"></div>
                <div class="form-group"><label>6 Mo</label><input type="number" class="cycle-disc" data-months="6" placeholder="5"></div>
                <div class="form-group"><label>1 Yr</label><input type="number" class="cycle-disc" data-months="12" placeholder="8"></div>
                <div class="form-group"><label>2 Yr</label><input type="number" class="cycle-disc" data-months="24" placeholder="10"></div>
                <div class="form-group"><label>3 Yr</label><input type="number" class="cycle-disc" data-months="36" placeholder="15"></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 style="font-size: 0.95rem; margin-bottom: 10px;">Calculated Multi-Currency Rates</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 20%;">Currency</th>
                    <th style="width: 20%;">Rate Engine</th>
                    <th>Cycles &amp; Calculated Selling Price</th>
                </tr>
            </thead>
            <tbody id="matrixBody">
                <tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 30px;">Input base values above to compile matrix.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        Open Source Release &bull; Developed by <a href="https://www.dizenlab.com" target="_blank">Shihab Mahmud (Dizenlab)</a>
    </div>
</div>

<script>
const supportedCurrs = ["USD","EUR","GBP","BDT","INR","CAD","AUD","SGD","JPY","AED","TRY"];
const cycleLabels = { 1: "Monthly", 3: "Quarterly", 6: "Semi-Annual", 12: "Annual", 24: "Biennial", 36: "Triennial" };
let rates = {};

const currContainer = document.getElementById('currencyList');
supportedCurrs.forEach(c => {
    currContainer.innerHTML += `
        <label class="currency-label">
            <input type="checkbox" value="${c}" class="curr-cb"> <span>${c}</span>
        </label>`;
});

async function syncRates() {
    const base = document.getElementById('baseCurrency').value;
    const res = await fetch(`?action=fetch_rates&base=${base}`);
    const data = await res.json();
    if(data.rates) {
        rates = data.rates;
        calculate();
    }
}

function calculate() {
    const cost = parseFloat(document.getElementById('baseCost').value);
    if (!cost) return;

    const baseCurr = document.getElementById('baseCurrency').value;
    const margin = parseFloat(document.getElementById('profitPercent').value) || 0;
    const method = document.getElementById('profitMethod').value;
    
    const sellMonthly = method === 'markup' ? cost * (1 + (margin / 100)) : cost / (1 - (margin / 100));
    const selected = Array.from(document.querySelectorAll('.curr-cb:checked')).map(cb => cb.value);
    const tbody = document.getElementById('matrixBody');

    if(selected.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align: center;">No target currencies selected.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    selected.forEach(cur => {
        const rate = (cur === baseCurr) ? 1 : (rates[cur] || 1);
        const curCost = cost * rate;
        const curSell = sellMonthly * rate;

        let cyclesHtml = '<ul class="price-list">';
        document.querySelectorAll('.cycle-disc').forEach(input => {
            const m = parseInt(input.dataset.months);
            const disc = parseFloat(input.value) || 0;
            const finalNet = (curSell * m) * (1 - (disc / 100));
            const netProfit = finalNet - (curCost * m);

            cyclesHtml += `
                <li>
                    <div style="font-size:0.75rem; font-weight:700;">${cycleLabels[m]}</div>
                    <div class="price-val">${finalNet.toFixed(2)} ${cur}</div>
                    <div style="font-size:0.7rem; color: var(--text-muted); margin-top:4px;">
                        Profit: <span style="color:${netProfit >= 0 ? 'var(--success)' : 'var(--danger)'}; font-weight:700;">
                            ${netProfit.toFixed(2)}
                        </span>
                    </div>
                </li>`;
        });
        cyclesHtml += '</ul>';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td style="font-weight:700; color:var(--primary);">${cur}</td>
            <td>1 ${baseCurr} = ${rate.toFixed(4)} ${cur}</td>
            <td>${cyclesHtml}</td>`;
        tbody.appendChild(row);
    });
}

function selectCurr(type) {
    document.querySelectorAll('.curr-cb').forEach(cb => {
        cb.checked = (type === 'top');
    });
    calculate();
}

document.getElementById('baseCost').addEventListener('input', calculate);
document.getElementById('profitPercent').addEventListener('input', calculate);
document.getElementById('profitMethod').addEventListener('change', calculate);
document.getElementById('baseCurrency').addEventListener('change', syncRates);
document.querySelectorAll('.cycle-disc').forEach(i => i.addEventListener('input', calculate));
document.getElementById('currencyList').addEventListener('change', calculate);

syncRates();
</script>
</body>
</html>
