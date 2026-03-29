<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — BakeFlow POS</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:   #C4748E;
            --primary-d: #A85A74;
            --primary-l: #F4C5D8;
            --accent:    #5A9E9A;
            --accent-l:  #B2DAD8;
            --bg:        #FAF5F2;
            --surface:   #FFFFFF;
            --card:      #FFFFFF;
            --text:      #3D1F14;
            --muted:     #8B5E4E;
            --danger:    #c0392b;
            --border:    rgba(74, 42, 53, 0.1);
            --radius:    12px;
        }

        html {
            height: 100%;
            background: linear-gradient(135deg, #FCE9DD 0%, #F4C5D8 50%, #B2DAD8 100%);
            background-attachment: fixed;
        }

        body {
            min-height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: transparent;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 24px;
            width: 100%;
            max-width: 380px;
            padding: 16px;
        }

        .brand {
            text-align: center;
        }

        .brand h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #4A2A35;
            letter-spacing: -0.5px;
        }

        .brand h1 span { color: #5A9E9A; }
        .brand p { color: #5A3040; font-size: 0.9rem; margin-top: 4px; }

        .login-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 32px 28px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(150, 80, 50, 0.14);
        }

        .error-msg {
            background: rgba(192,57,43,0.1);
            border: 1px solid var(--danger);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.85rem;
            color: var(--danger);
            margin-bottom: 16px;
            text-align: center;
        }

        .tabs {
            display: flex;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 24px;
            background: var(--bg);
        }

        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            padding: 10px;
            color: var(--muted);
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }

        .tab-btn.active {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            color: var(--muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--text);
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* PIN display */
        .pin-display {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .pin-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid var(--muted);
            background: transparent;
            transition: background 0.2s, border-color 0.2s;
        }

        .pin-dot.filled {
            background: var(--primary);
            border-color: var(--primary);
        }

        /* PIN pad */
        .pin-pad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }

        .pin-btn {
            background: var(--primary-l);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px 10px;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text);
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            user-select: none;
        }

        .pin-btn:active,
        .pin-btn:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .pin-btn.action {
            font-size: 0.85rem;
            font-weight: 400;
            background: var(--accent-l);
            color: var(--muted);
            border-color: var(--border);
        }

        .pin-btn.action:hover {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .pin-btn.zero {
            grid-column: 2;
        }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-submit:hover  { background: var(--primary-d); }
        .btn-submit:active { transform: scale(0.98); }

        /* Tab panels */
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .footer-note {
            text-align: center;
            font-size: 0.75rem;
            color: var(--muted);
        }
    </style>
</head>
<body>

<div class="login-wrap">
    <div class="brand">
        <h1>Bake<span>Flow</span> POS</h1>
        <p>Bakery Point of Sale System</p>
    </div>

    <div class="login-card">
        <?php if (!empty($error)): ?>
        <div class="error-msg"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('cashier')">Cashier PIN</button>
            <button class="tab-btn" onclick="switchTab('admin')">Admin Login</button>
        </div>

        <!-- Cashier PIN tab -->
        <div class="tab-panel active" id="tab-cashier">
            <form method="POST" action="/login" id="pin-form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="pin" id="pin-value">

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="cashier-username" placeholder="Enter username" autocomplete="username" required>
                </div>

                <div class="pin-display" id="pin-display">
                    <div class="pin-dot" id="dot-0"></div>
                    <div class="pin-dot" id="dot-1"></div>
                    <div class="pin-dot" id="dot-2"></div>
                    <div class="pin-dot" id="dot-3"></div>
                </div>

                <div class="pin-pad">
                    <button type="button" class="pin-btn" onclick="pinKey('1')">1</button>
                    <button type="button" class="pin-btn" onclick="pinKey('2')">2</button>
                    <button type="button" class="pin-btn" onclick="pinKey('3')">3</button>
                    <button type="button" class="pin-btn" onclick="pinKey('4')">4</button>
                    <button type="button" class="pin-btn" onclick="pinKey('5')">5</button>
                    <button type="button" class="pin-btn" onclick="pinKey('6')">6</button>
                    <button type="button" class="pin-btn" onclick="pinKey('7')">7</button>
                    <button type="button" class="pin-btn" onclick="pinKey('8')">8</button>
                    <button type="button" class="pin-btn" onclick="pinKey('9')">9</button>
                    <button type="button" class="pin-btn action" onclick="pinClear()">Clear</button>
                    <button type="button" class="pin-btn zero" onclick="pinKey('0')">0</button>
                    <button type="button" class="pin-btn action" onclick="pinBack()">&#9003;</button>
                </div>

                <button type="submit" class="btn-submit" id="pin-submit" disabled>Login</button>
            </form>
        </div>

        <!-- Admin login tab -->
        <div class="tab-panel" id="tab-admin">
            <form method="POST" action="/login" id="admin-form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="admin" autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn-submit">Login</button>
            </form>
        </div>
    </div>

    <p class="footer-note">BakeFlow POS &copy; <?= date('Y') ?></p>
</div>

<script>
let pinValue = '';
const MAX_PIN = 6;

function pinKey(digit) {
    if (pinValue.length >= MAX_PIN) return;
    pinValue += digit;
    updatePinDisplay();
}

function pinBack() {
    pinValue = pinValue.slice(0, -1);
    updatePinDisplay();
}

function pinClear() {
    pinValue = '';
    updatePinDisplay();
}

function updatePinDisplay() {
    // Update dots (show up to 4 dots; expand if PIN > 4)
    const dots = document.querySelectorAll('.pin-dot');
    // Ensure we have enough dots
    const display = document.getElementById('pin-display');
    // Rebuild dots based on max PIN length
    const dotCount = Math.max(4, pinValue.length);
    display.innerHTML = '';
    for (let i = 0; i < dotCount; i++) {
        const dot = document.createElement('div');
        dot.className = 'pin-dot' + (i < pinValue.length ? ' filled' : '');
        display.appendChild(dot);
    }

    document.getElementById('pin-value').value = pinValue;
    document.getElementById('pin-submit').disabled = pinValue.length < 4;
}

// Submit PIN form on Enter key
document.getElementById('pin-form').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && pinValue.length >= 4) {
        document.getElementById('pin-value').value = pinValue;
        this.submit();
    }
});

// Tab switching
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach((b, i) => {
        b.classList.toggle('active', (tab === 'cashier' && i === 0) || (tab === 'admin' && i === 1));
    });
    document.getElementById('tab-cashier').classList.toggle('active', tab === 'cashier');
    document.getElementById('tab-admin').classList.toggle('active', tab === 'admin');
}

// Keyboard support for PIN
document.addEventListener('keydown', function(e) {
    const active = document.getElementById('tab-cashier').classList.contains('active');
    if (!active) return;
    const focused = document.activeElement;
    if (focused && focused.tagName === 'INPUT' && focused.type !== 'hidden') return;

    if (e.key >= '0' && e.key <= '9') pinKey(e.key);
    else if (e.key === 'Backspace') pinBack();
    else if (e.key === 'Escape') pinClear();
    else if (e.key === 'Enter' && pinValue.length >= 4) {
        document.getElementById('pin-value').value = pinValue;
        document.getElementById('pin-form').submit();
    }
});
</script>

</body>
</html>
