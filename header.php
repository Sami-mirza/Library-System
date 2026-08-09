<?php
$darkMode = isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true';
?>

<!DOCTYPE html>
<html data-theme="<?php echo $darkMode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --bg: #f0f2f5;
            --card: #ffffff;
            --text: #333333;
            --text-secondary: #666666;
            --navbar: #2c3e50;
            --accent: #4CAF50;
            --accent-blue: #2196F3;
            --accent-purple: #9C27B0;
            --shadow: 0 4px 15px rgba(0,0,0,0.1);
            --error-bg: #f8d7da;
            --error-text: #721c24;
            --success-bg: #d4edda;
            --success-text: #155724;
        }

        [data-theme="dark"] {
            --bg: #1a1a2e;
            --card: #16213e;
            --text: #e0e0e0;
            --text-secondary: #a0a0a0;
            --navbar: #0f3460;
            --accent: #4CAF50;
            --accent-blue: #64b5f6;
            --accent-purple: #ba68c8;
            --shadow: 0 4px 15px rgba(0,0,0,0.3);
            --error-bg: #5c2a2a;
            --error-text: #ff9999;
            --success-bg: #2a5c3a;
            --success-text: #99ff99;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; transition: background-color 0.3s, color 0.3s; }

        body {
            font-family: Arial;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .navbar {
            background: var(--navbar);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .theme-toggle {
            background: none;
            border: 2px solid white;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .theme-toggle:hover { background: rgba(255,255,255,0.1); }

        .navbar a { color: white; text-decoration: none; }

        .error-box {
            background: var(--error-bg);
            color: var(--error-text);
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #f44336;
            margin-bottom: 20px;
        }

        .success-box {
            background: var(--success-bg);
            color: var(--success-text);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        /* LOADING OVERLAY STYLES */
        #loadingOverlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        #loadingOverlay .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid #2196F3;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        #loadingOverlay p {
            color: white;
            margin-top: 20px;
            font-family: Arial, sans-serif;
            font-size: 16px;
        }

        #loadingOverlay .timeout-msg {
            display: none;
            color: #ffcc00;
            margin-top: 10px;
            font-size: 14px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ========== RESPONSIVE STYLES ========== */
        /* Tablets */
        @media (max-width: 768px) {
            .navbar { padding: 15px 20px; }
            .navbar h1 { font-size: 22px; }
            .nav-right { gap: 15px; font-size: 14px; }
            .theme-toggle { padding: 6px 12px; font-size: 13px; }
            .error-box { padding: 15px; margin-bottom: 15px; }
            .success-box { padding: 10px; margin-bottom: 12px; }
        }

        /* Mobile phones */
        @media (max-width: 480px) {
            .navbar {
                padding: 12px 15px;
                flex-wrap: wrap;
                gap: 10px;
            }
            .navbar h1 { font-size: 18px; }
            .nav-right {
                gap: 10px;
                font-size: 13px;
                flex-wrap: wrap;
                justify-content: flex-end;
                width: 100%;
            }
            .theme-toggle {
                padding: 5px 10px;
                font-size: 12px;
                border-width: 1.5px;
            }
            .error-box { padding: 12px; font-size: 14px; }
            .success-box { padding: 10px; font-size: 14px; }
            #loadingOverlay p { font-size: 14px; }
            #loadingOverlay .timeout-msg { font-size: 12px; }
        }
    </style>
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';

            html.setAttribute('data-theme', next);
            document.cookie = 'darkMode=' + (next === 'dark') + '; path=/; max-age=31536000';

            updateToggleButton(next);
        }

        function updateToggleButton(theme) {
            const icon = document.getElementById('theme-icon');
            const text = document.getElementById('theme-text');

            if (theme === 'dark') {
                icon.textContent = '☀️';
                text.textContent = 'Light';
            } else {
                icon.textContent = '🌙';
                text.textContent = 'Dark';
            }
        }

        // ========== LOADING OVERLAY LOGIC ==========
        document.addEventListener('DOMContentLoaded', function() {
            var overlay = document.getElementById('loadingOverlay');
            var timeoutMsg = document.querySelector('#loadingOverlay .timeout-msg');
            var timeoutId;

            // Show loading on form submit
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function() {
                    overlay.style.display = 'flex';
                    // Show timeout message after 8 seconds
                    timeoutId = setTimeout(function() {
                        if (timeoutMsg) timeoutMsg.style.display = 'block';
                    }, 8000);
                });
            });

            // Show loading on internal link clicks
            document.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    var href = this.getAttribute('href');
                    if (href && !href.startsWith('#') && !href.startsWith('http') && !href.startsWith('mailto:') && !href.startsWith('javascript:')) {
                        overlay.style.display = 'flex';
                        timeoutId = setTimeout(function() {
                            if (timeoutMsg) timeoutMsg.style.display = 'block';
                        }, 8000);
                    }
                });
            });

            // Hide loading when page fully loads
            window.addEventListener('pageshow', function() {
                overlay.style.display = 'none';
                if (timeoutMsg) timeoutMsg.style.display = 'none';
                if (timeoutId) clearTimeout(timeoutId);
            });
        });
    </script>
</head>
<body>

<!-- ========== LOADING OVERLAY ========== -->
<div id="loadingOverlay">
    <div class="spinner"></div>
    <p>Loading...</p>
    <p class="timeout-msg">⏳ This is taking longer than usual. Please wait...</p>
</div>