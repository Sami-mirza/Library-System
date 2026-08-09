<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: Dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibrarySystem</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #111;
            background: #fff;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        /* Nav */
        nav {
            border-bottom: 1px solid #eee;
        }
        .nav-inner {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 20px;
            font-weight: 800;
            text-decoration: none;
            color: #111;
            letter-spacing: -0.5px;
        }
        .nav-links a {
            margin-left: 28px;
            text-decoration: none;
            color: #555;
            font-size: 15px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-links a:hover { color: #111; }
        .nav-links a.btn {
            background: #111;
            color: #fff;
            padding: 10px 20px;
            border-radius: 6px;
            margin-left: 24px;
        }
        
        /* Hero */
        .hero {
            padding: 120px 24px 100px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .hero h1 {
            font-size: clamp(42px, 6vw, 76px);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -2.5px;
            margin-bottom: 28px;
            max-width: 900px;
        }
        .hero p {
            font-size: 20px;
            color: #555;
            max-width: 580px;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-black {
            background: #111;
            color: #fff;
        }
        .btn-black:hover {
            background: #333;
            transform: translateY(-1px);
        }
        .btn-white {
            background: #fff;
            color: #111;
            border: 1px solid #ddd;
        }
        .btn-white:hover {
            border-color: #111;
        }
        
        /* Divider */
        .divider {
            border: none;
            border-top: 1px solid #eee;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* Section */
        .section {
            padding: 100px 24px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .section h2 {
            font-size: clamp(28px, 3.5vw, 42px);
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 20px;
            line-height: 1.15;
        }
        .section > p {
            font-size: 18px;
            color: #555;
            max-width: 600px;
            line-height: 1.7;
        }
        
        /* Feature list */
        .features-list {
            margin-top: 60px;
        }
        .feature-item {
            padding: 36px 0;
            border-bottom: 1px solid #f0f0f0;
            display: grid;
            grid-template-columns: 70px 1fr;
            gap: 24px;
            align-items: start;
        }
        .feature-item:last-child {
            border-bottom: none;
        }
        .feature-num {
            font-size: 13px;
            font-weight: 700;
            color: #bbb;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding-top: 6px;
        }
        .feature-item h3 {
            font-size: 21px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #111;
            letter-spacing: -0.3px;
        }
        .feature-item p {
            font-size: 16px;
            color: #666;
            line-height: 1.7;
            max-width: 520px;
        }
        
        /* CTA */
        .cta {
            background: #111;
            color: #fff;
            padding: 120px 24px;
            text-align: center;
        }
        .cta-inner {
            max-width: 700px;
            margin: 0 auto;
        }
        .cta h2 {
            font-size: clamp(28px, 4vw, 44px);
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 16px;
        }
        .cta p {
            color: rgba(255,255,255,0.45);
            font-size: 18px;
            margin-bottom: 36px;
        }
        
        /* Footer */
        footer {
            padding: 40px 24px;
            text-align: center;
            color: #aaa;
            font-size: 14px;
        }
        
        @media (max-width: 600px) {
            .nav-links a:not(.btn) { display: none; }
            .hero { padding-top: 80px; padding-bottom: 60px; }
            .section { padding: 60px 24px; }
            .feature-item { grid-template-columns: 50px 1fr; gap: 16px; }
        }
    </style>
</head>
<body>

    <nav>
        <div class="nav-inner">
            <a href="#" class="logo"> LibrarySystem</a>
            <div class="nav-links">
                <a href="login.php">Sign in</a>
                <a href="Signup.php" class="btn">Get started</a>
            </div>
        </div>
    </nav>

    <section class="hero">
        <h1>Library management that just works.</h1>
        <p>Stop digging through spreadsheets and paper logs. Track your books, borrowers, and due dates in one place. Built for school libraries, not tech teams.</p>
        <div class="btn-group">
            <a href="Signup.php" class="btn btn-black">Create free account</a>
            <a href="login.php" class="btn btn-white">Sign in</a>
        </div>
    </section>

    <hr class="divider">

    <section class="section">
        <h2>Everything in one place.</h2>
        <p>No scattered notebooks. No forgotten due dates. Add your inventory, log borrowings, and let the system keep track of the rest.</p>
        
        <div class="features-list">
            <div class="feature-item">
                <div class="feature-num">01</div>
                <div>
                    <h3>Inventory that makes sense</h3>
                    <p>Add books with titles, copy counts, and shelf locations. See what's available and what's checked out at a glance.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-num">02</div>
                <div>
                    <h3>Borrowing records that stick</h3>
                    <p>Log borrower names, classes, phone numbers, and return dates. Search by any field when you need to find something fast.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-num">03</div>
                <div>
                    <h3>Overdue books, surfaced automatically</h3>
                    <p>Late returns show up in red. Get a count of what's overdue without running a single report.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-num">04</div>
                <div>
                    <h3>Ask questions, get answers</h3>
                    <p>The built-in AI understands your data. Ask "Who has the most overdue books?" or "Where is the Physics textbook?" and get a real answer.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="cta-inner">
            <h2>Get your library organized today.</h2>
            <p>Free to use. Takes two minutes to set up.</p>
            <a href="Signup.php" class="btn btn-black" style="background:#fff;color:#111;">Create free account</a>
        </div>
    </section>

    <footer>
        <p>LibrarySystem — Built for school libraries.</p>
    </footer>

</body>
</html>