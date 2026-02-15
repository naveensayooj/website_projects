<?php
require __DIR__ . '/config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_submit'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $text = trim($_POST['message'] ?? '');
    if ($text !== '') {
        $stmt = $pdo->prepare('INSERT INTO feedback (name, email, message, status, created_at) VALUES (?, ?, ?, "new", NOW())');
        $stmt->execute([$name, $email, $text]);
        $message = 'Thank you, your feedback has been submitted.';
    }
}

$categories = $pdo->query('SELECT id, name, description FROM categories ORDER BY name')->fetchAll();

$introStmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = "homepage_intro"');
$introStmt->execute();
$homepageIntro = $introStmt->fetchColumn();

$trainers = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_submit'])) {
    $cat = $_GET['category'] ?? '';
    $loc = $_GET['location'] ?? '';
    $sql = 'SELECT t.*, c.name AS category_name FROM trainers t
            LEFT JOIN training_programs p ON p.trainer_id = t.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE t.status = "approved"';
    $params = [];
    if ($cat !== '') {
        $sql .= ' AND c.id = ?';
        $params[] = $cat;
    }
    if ($loc !== '') {
        $sql .= ' AND t.location LIKE ?';
        $params[] = '%' . $loc . '%';
    }
    $sql .= ' GROUP BY t.id ORDER BY t.created_at DESC LIMIT 8';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trainers = $stmt->fetchAll();
} else {
    $stmt = $pdo->query('SELECT t.*, c.name AS category_name FROM trainers t
        LEFT JOIN training_programs p ON p.trainer_id = t.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE t.status = "approved"
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT 6');
    $trainers = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TraineeConnect – Find Your Professional Trainer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="navbar">
    <div class="container navbar-inner">
        <a href="index.php" class="brand">
            <div class="brand-mark">TC</div>
            <span>TraineeConnect</span>
        </a>
        <nav class="nav-links">
            <a href="#categories" class="nav-link">Categories</a>
            <a href="#search" class="nav-link">Search Trainers</a>
            <a href="#feedback" class="nav-link">Complaints & Feedback</a>
            <a href="trainee.php" class="btn outline">Trainee Login</a>
            <a href="trainer.php" class="btn">Trainer Login</a>
        </nav>
    </div>
    </header>

<main>
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <h1 class="hero-title">Connect trainees with world‑class trainers in minutes.</h1>
                <p class="hero-subtitle">
                    <?php echo e($homepageIntro ?: 'Discover certified trainers, book sessions, complete structured programs, and track performance under full administrative control.'); ?>
                </p>
                <div class="hero-pills">
                    <span class="pill">Corporate Coaching</span>
                    <span class="pill">Fitness</span>
                    <span class="pill">Life Coaching</span>
                    <span class="pill">Leadership</span>
                    <span class="pill">Counselling</span>
                    <span class="pill">Motivation</span>
                </div>
                <div class="hero-actions">
                    <a href="#search" class="btn">Search Trainers</a>
                    <a href="trainee.php" class="btn outline">Start as Trainee</a>
                </div>
            </div>
            <div>
                <div class="hero-card">
                    <div class="card-chip">Live platform snapshot</div>
                    <div class="hero-metrics">
                        <div class="metric">
                            <div class="metric-label">Active trainees</div>
                            <div class="metric-value">
                                <?php
                                $count = $pdo->query('SELECT COUNT(*) AS c FROM users WHERE role = "trainee"')->fetch();
                                echo (int)($count['c'] ?? 0);
                                ?>
                            </div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Approved trainers</div>
                            <div class="metric-value">
                                <?php
                                $count = $pdo->query('SELECT COUNT(*) AS c FROM trainers WHERE status = "approved"')->fetch();
                                echo (int)($count['c'] ?? 0);
                                ?>
                            </div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Bookings this month</div>
                            <div class="metric-value">
                                <?php
                                $count = $pdo->query('SELECT COUNT(*) AS c FROM bookings WHERE MONTH(created_at) = MONTH(CURDATE())')->fetch();
                                echo (int)($count['c'] ?? 0);
                                ?>
                            </div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Avg. rating</div>
                            <div class="metric-value">
                                <?php
                                $row = $pdo->query('SELECT AVG(rating) AS r FROM ratings')->fetch();
                                echo $row && $row['r'] ? number_format($row['r'], 1) . '★' : '—';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="categories" class="section">
        <div class="container">
            <div class="section-header">
                <div>
                    <div class="section-title">Training categories</div>
                    <div class="section-subtitle">Browse structured programs across skills and well‑being.</div>
                </div>
            </div>
            <div class="grid">
                <?php
                $defaultCategories = [
                    'Corporate Coaching' => 'Improve productivity, communication and high‑performance team culture.',
                    'Fitness' => 'Personal training, strength, conditioning and holistic health.',
                    'Life Coaching' => 'Goal‑setting, habits and personal transformation journeys.',
                    'Leadership' => 'Executive presence, decision‑making and people leadership.',
                    'Counselling' => 'Emotional wellness, stress management and support.',
                    'Motivation' => 'Inspiration, discipline and career acceleration.'
                ];
                if (!$categories) {
                    foreach ($defaultCategories as $name => $desc): ?>
                        <article class="card">
                            <div class="card-chip">Featured</div>
                            <h3 class="card-title"><?php echo e($name); ?></h3>
                            <p class="card-text"><?php echo e($desc); ?></p>
                        </article>
                    <?php endforeach;
                } else {
                    foreach ($categories as $cat): ?>
                        <article class="card">
                            <div class="card-chip">Category</div>
                            <h3 class="card-title"><?php echo e($cat['name']); ?></h3>
                            <p class="card-text"><?php echo e($cat['description']); ?></p>
                        </article>
                    <?php endforeach;
                } ?>
            </div>
        </div>
    </section>

    <section id="search" class="section">
        <div class="container">
            <div class="section-header">
                <div>
                    <div class="section-title">Search trainers</div>
                    <div class="section-subtitle">Filter by category, location and availability.</div>
                </div>
            </div>

            <div class="card">
                <form method="get" class="search-row">
                    <div class="field">
                        <label for="category">Category</label>
                        <select name="category" id="category">
                            <option value="">Any category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo e($cat['id']); ?>" <?php echo isset($_GET['category']) && $_GET['category'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" placeholder="City / Country" value="<?php echo e($_GET['location'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label>&nbsp;</label>
                        <button type="submit" name="search_submit" class="btn" style="width:100%;">Search</button>
                    </div>
                </form>
                <div class="tag-row">
                    <span class="tag"><span class="status-dot green"></span> Available today</span>
                    <span class="tag"><span class="status-dot red"></span> Fully booked</span>
                </div>
                <div class="grid" style="margin-top:1rem;">
                    <?php if (!$trainers): ?>
                        <p style="font-size:0.85rem;color:#6b7280;">No trainers found. Try adjusting your filters.</p>
                    <?php else: ?>
                        <?php foreach ($trainers as $t): ?>
                            <article class="card">
                                <div class="trainer-card">
                                    <div class="avatar">
                                        <?php echo strtoupper(substr($t['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="card-title"><?php echo e($t['name']); ?></div>
                                        <div style="font-size:0.8rem;color:#6b7280;"><?php echo e($t['location']); ?></div>
                                        <div style="margin-top:0.2rem;">
                                            <span class="badge">
                                                <?php echo e($t['category_name'] ?? 'Multiple categories'); ?>
                                            </span>
                                        </div>
                                        <div class="rating-stars">
                                            <?php
                                            $row = $pdo->prepare('SELECT AVG(rating) AS r, COUNT(*) AS c FROM ratings WHERE trainer_id = ?');
                                            $row->execute([$t['id']]);
                                            $row = $row->fetch();
                                            if ($row && $row['c'] > 0) {
                                                echo '★ ' . number_format($row['r'], 1) . ' (' . (int)$row['c'] . ')';
                                            } else {
                                                echo 'No ratings yet';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top:0.5rem;display:flex;justify-content:space-between;align-items:center;">
                                    <a href="trainee.php?action=browse&trainer=<?php echo (int)$t['id']; ?>" class="btn outline" style="font-size:0.8rem;">View &amp; Book</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section id="feedback" class="section">
        <div class="container">
            <div class="section-header">
                <div>
                    <div class="section-title">Complaints & feedback</div>
                    <div class="section-subtitle">Tell us what is working and what needs improvement.</div>
                </div>
            </div>
            <div class="card">
                <?php if ($message): ?>
                    <div class="alert success"><?php echo e($message); ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="search-row">
                        <div class="field">
                            <label for="fb-name">Name</label>
                            <input type="text" id="fb-name" name="name" placeholder="Optional">
                        </div>
                        <div class="field">
                            <label for="fb-email">Email</label>
                            <input type="email" id="fb-email" name="email" placeholder="Optional">
                        </div>
                    </div>
                    <div class="field" style="margin-top:0.5rem;">
                        <label for="fb-message">Your complaint / feedback</label>
                        <textarea id="fb-message" name="message" required></textarea>
                    </div>
                    <div style="margin-top:0.8rem;display:flex;justify-content:flex-end;">
                        <button type="submit" name="feedback_submit" class="btn">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    <div class="container">
        <div>© <?php echo date('Y'); ?> TraineeConnect. All rights reserved.</div>
        <div style="margin-top:0.35rem;">
            <a href="admin.php" class="hidden-admin">Admin access</a>
        </div>
    </div>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
