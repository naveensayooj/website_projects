<?php
require __DIR__ . '/config.php';

$section = $_GET['section'] ?? 'trainers';
$info = '';
$error = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    redirect('admin.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = "admin"');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin'] = [
            'id' => $admin['id'],
            'name' => $admin['name'],
        ];
        redirect('admin.php');
    } else {
        $error = 'Invalid admin credentials.';
    }
}

if (isset($_SESSION['admin'])) {
    if (isset($_GET['approve_trainer'])) {
        $tid = (int)$_GET['approve_trainer'];
        $pdo->prepare('UPDATE trainers SET status = "approved" WHERE id = ?')->execute([$tid]);
        $info = 'Trainer approved.';
        $section = 'trainers';
    }

    if (isset($_GET['reject_trainer'])) {
        $tid = (int)$_GET['reject_trainer'];
        $pdo->prepare('DELETE FROM trainers WHERE id = ? AND status = "pending"')->execute([$tid]);
        $info = 'Trainer removed.';
        $section = 'trainers';
    }

    if (isset($_GET['delete_user'])) {
        $uid = (int)$_GET['delete_user'];
        $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "trainee"')->execute([$uid]);
        $info = 'Trainee removed.';
        $section = 'users';
    }

    if (isset($_GET['delete_trainer'])) {
        $tid = (int)$_GET['delete_trainer'];
        $pdo->prepare('DELETE FROM trainers WHERE id = ?')->execute([$tid]);
        $info = 'Trainer removed.';
        $section = 'users';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name === '') {
            $error = 'Category name is required.';
        } else {
            if ($id) {
                $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
                $stmt->execute([$name, $description, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
                $stmt->execute([$name, $description]);
            }
            $info = 'Category saved.';
            $section = 'users';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['homepage_intro'])) {
        $intro = trim($_POST['homepage_intro'] ?? '');
        $stmt = $pdo->prepare('REPLACE INTO settings (`key`, `value`) VALUES ("homepage_intro", ?)');
        $stmt->execute([$intro]);
        $info = 'Homepage content updated.';
        $section = 'content';
    }

    if (isset($_GET['feedback_status']) && isset($_GET['feedback_id'])) {
        $fid = (int)$_GET['feedback_id'];
        $status = $_GET['feedback_status'] === 'handled' ? 'handled' : 'new';
        $pdo->prepare('UPDATE feedback SET status = ? WHERE id = ?')->execute([$status, $fid]);
        $info = 'Feedback updated.';
        $section = 'feedback';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Area – TraineeConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if (!isset($_SESSION['admin'])): ?>
    <div class="layout-auth">
        <main class="auth-main" style="grid-column:1/-1;">
            <div class="auth-card">
                <h2 class="section-title">Admin login</h2>
                <p class="section-subtitle">Restricted access for platform administrators.</p>
                <?php if ($error): ?>
                    <div class="alert error"><?php echo e($error); ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" type="email" name="email" required>
                    </div>
                    <div class="field" style="margin-top:0.6rem;">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" required>
                    </div>
                    <button type="submit" name="admin_login" class="btn" style="margin-top:0.9rem;width:100%;">Login</button>
                </form>
            </div>
        </main>
    </div>
<?php else:
    $adminName = $_SESSION['admin']['name'];
    ?>
    <div class="dashboard">
        <aside class="sidebar">
            <div>
                <div class="sidebar-title">Admin</div>
                <strong><?php echo e($adminName); ?></strong>
            </div>
            <nav class="sidebar-nav">
                <a href="admin.php?section=trainers" class="sidebar-link <?php echo $section === 'trainers' ? 'active' : ''; ?>">Approve trainers</a>
                <a href="admin.php?section=users" class="sidebar-link <?php echo $section === 'users' ? 'active' : ''; ?>">Users & categories</a>
                <a href="admin.php?section=analytics" class="sidebar-link <?php echo $section === 'analytics' ? 'active' : ''; ?>">Analytics</a>
                <a href="admin.php?section=feedback" class="sidebar-link <?php echo $section === 'feedback' ? 'active' : ''; ?>">Complaints & feedback</a>
                <a href="admin.php?section=content" class="sidebar-link <?php echo $section === 'content' ? 'active' : ''; ?>">Platform content</a>
                <a href="admin.php?logout=1" class="sidebar-link">Logout</a>
            </nav>
        </aside>
        <main class="main">
            <header class="main-header">
                <div>
                    <h2 class="section-title">
                        <?php
                        echo match ($section) {
                            'trainers' => 'Approve trainers',
                            'users' => 'Manage users & categories',
                            'analytics' => 'Platform analytics',
                            'feedback' => 'Complaints & feedback',
                            'content' => 'Control platform content',
                            default => 'Admin dashboard'
                        };
                        ?>
                    </h2>
                </div>
            </header>
            <?php if ($error): ?>
                <div class="alert error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($info): ?>
                <div class="alert success"><?php echo e($info); ?></div>
            <?php endif; ?>

            <?php if ($section === 'trainers'):
                $pending = $pdo->query('SELECT * FROM trainers WHERE status = "pending" ORDER BY created_at')->fetchAll();
                $approved = $pdo->query('SELECT * FROM trainers WHERE status = "approved" ORDER BY created_at DESC LIMIT 8')->fetchAll();
                ?>
                <section>
                    <div class="grid">
                        <article class="card">
                            <h3 class="card-title">Pending trainer approvals</h3>
                            <?php if (!$pending): ?>
                                <p class="card-text">No pending trainers.</p>
                            <?php else: ?>
                                <?php foreach ($pending as $t): ?>
                                    <div style="border-top:1px solid #e5e7eb;padding-top:0.4rem;margin-top:0.4rem;">
                                        <strong><?php echo e($t['name']); ?></strong>
                                        <div class="card-text"><?php echo e($t['email']); ?> • <?php echo e($t['location']); ?></div>
                                        <div style="margin-top:0.4rem;display:flex;gap:0.4rem;">
                                            <a href="admin.php?section=trainers&approve_trainer=<?php echo (int)$t['id']; ?>" class="btn" style="font-size:0.8rem;">Approve</a>
                                            <a href="admin.php?section=trainers&reject_trainer=<?php echo (int)$t['id']; ?>" class="btn outline" style="font-size:0.8rem;">Reject</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </article>
                        <article class="card">
                            <h3 class="card-title">Recently approved trainers</h3>
                            <?php foreach ($approved as $t): ?>
                                <div style="border-top:1px solid #e5e7eb;padding-top:0.4rem;margin-top:0.4rem;">
                                    <strong><?php echo e($t['name']); ?></strong>
                                    <div class="card-text"><?php echo e($t['email']); ?> • <?php echo e($t['location']); ?></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$approved): ?>
                                <p class="card-text">No approved trainers yet.</p>
                            <?php endif; ?>
                        </article>
                    </div>
                </section>
            <?php elseif ($section === 'users'):
                $trainees = $pdo->query('SELECT * FROM users WHERE role = "trainee" ORDER BY created_at DESC LIMIT 20')->fetchAll();
                $trainers = $pdo->query('SELECT * FROM trainers ORDER BY created_at DESC LIMIT 20')->fetchAll();
                $categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
                ?>
                <section>
                    <div class="grid">
                        <article class="card">
                            <h3 class="card-title">Trainees</h3>
                            <?php foreach ($trainees as $u): ?>
                                <div style="border-top:1px solid #e5e7eb;padding-top:0.4rem;margin-top:0.4rem;">
                                    <strong><?php echo e($u['name']); ?></strong>
                                    <div class="card-text"><?php echo e($u['email']); ?> • <?php echo e($u['location']); ?></div>
                                    <a href="admin.php?section=users&delete_user=<?php echo (int)$u['id']; ?>" class="btn outline" style="font-size:0.8rem;margin-top:0.3rem;">Delete</a>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$trainees): ?>
                                <p class="card-text">No trainees yet.</p>
                            <?php endif; ?>
                        </article>
                        <article class="card">
                            <h3 class="card-title">Trainers</h3>
                            <?php foreach ($trainers as $t): ?>
                                <div style="border-top:1px solid #e5e7eb;padding-top:0.4rem;margin-top:0.4rem;">
                                    <strong><?php echo e($t['name']); ?></strong>
                                    <div class="card-text"><?php echo e($t['email']); ?> • <?php echo e($t['location']); ?> • <?php echo ucfirst($t['status']); ?></div>
                                    <a href="admin.php?section=users&delete_trainer=<?php echo (int)$t['id']; ?>" class="btn outline" style="font-size:0.8rem;margin-top:0.3rem;">Delete</a>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$trainers): ?>
                                <p class="card-text">No trainers yet.</p>
                            <?php endif; ?>
                        </article>
                        <article class="card">
                            <h3 class="card-title">Training categories</h3>
                            <form method="post" style="margin-top:0.4rem;">
                                <input type="hidden" name="id" value="">
                                <div class="field">
                                    <label>Name</label>
                                    <input type="text" name="name" required>
                                </div>
                                <div class="field" style="margin-top:0.4rem;">
                                    <label>Description</label>
                                    <textarea name="description"></textarea>
                                </div>
                                <button type="submit" name="save_category" class="btn" style="margin-top:0.6rem;">Add category</button>
                            </form>
                            <div style="margin-top:0.8rem;">
                                <?php foreach ($categories as $c): ?>
                                    <div class="card-text">
                                        <?php echo e($c['name']); ?> – <?php echo e($c['description']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    </div>
                </section>
            <?php elseif ($section === 'feedback'):
                $feedback = $pdo->query('SELECT * FROM feedback ORDER BY created_at DESC LIMIT 30')->fetchAll();
                ?>
                <section>
                    <div class="grid">
                        <?php foreach ($feedback as $f): ?>
                            <article class="card">
                                <div class="card-chip">Feedback</div>
                                <div class="card-title" style="margin-top:0.4rem;"><?php echo e($f['name'] ?: 'Anonymous'); ?></div>
                                <p class="card-text"><?php echo e($f['message']); ?></p>
                                <p class="card-text">
                                    Status:
                                    <span class="tag">
                                        <span class="status-dot <?php echo $f['status'] === 'handled' ? 'green' : 'red'; ?>"></span>
                                        <?php echo ucfirst($f['status']); ?>
                                    </span>
                                </p>
                                <div style="margin-top:0.4rem;display:flex;gap:0.4rem;">
                                    <a href="admin.php?section=feedback&feedback_id=<?php echo (int)$f['id']; ?>&feedback_status=handled" class="btn" style="font-size:0.8rem;">Mark handled</a>
                                    <a href="admin.php?section=feedback&feedback_id=<?php echo (int)$f['id']; ?>&feedback_status=new" class="btn outline" style="font-size:0.8rem;">Mark new</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$feedback): ?>
                            <p style="font-size:0.85rem;color:#6b7280;">No feedback submitted yet.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php elseif ($section === 'content'):
                $stmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = "homepage_intro"');
                $stmt->execute();
                $intro = $stmt->fetchColumn() ?: '';
                ?>
                <section>
                    <div class="card">
                        <h3 class="card-title">Homepage introduction</h3>
                        <form method="post" style="margin-top:0.4rem;">
                            <div class="field">
                                <label>Intro text</label>
                                <textarea name="homepage_intro" rows="4" placeholder="Intro text displayed on the landing page"><?php echo e($intro); ?></textarea>
                            </div>
                            <button type="submit" class="btn" style="margin-top:0.6rem;">Save intro</button>
                        </form>
                    </div>
                </section>
            <?php elseif ($section === 'analytics'):
                $totalUsers = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "trainee"')->fetchColumn();
                $totalTrainers = $pdo->query('SELECT COUNT(*) FROM trainers WHERE status = "approved"')->fetchColumn();
                $totalBookings = $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
                $popularCategories = $pdo->query('SELECT c.name, COUNT(b.id) AS count
                    FROM bookings b
                    JOIN training_programs p ON p.id = b.program_id
                    JOIN categories c ON c.id = p.category_id
                    GROUP BY c.id
                    ORDER BY count DESC
                    LIMIT 5')->fetchAll();

                $raw = [
                    'users' => (int)$totalUsers,
                    'trainers' => (int)$totalTrainers,
                    'bookings' => (int)$totalBookings,
                    'categories' => array_map(fn($row) => ['name' => $row['name'], 'count' => (int)$row['count']], $popularCategories),
                ];

                $pythonSummary = null;
                $pythonError = null;
                $pythonPath = 'python';
                $script = __DIR__ . DIRECTORY_SEPARATOR . 'analytics' . DIRECTORY_SEPARATOR . 'analytics.py';
                if (file_exists($script)) {
                    $descriptorSpec = [
                        0 => ['pipe', 'r'],
                        1 => ['pipe', 'w'],
                        2 => ['pipe', 'w'],
                    ];
                    $process = @proc_open("$pythonPath " . escapeshellarg($script), $descriptorSpec, $pipes);
                    if (is_resource($process)) {
                        fwrite($pipes[0], json_encode($raw));
                        fclose($pipes[0]);
                        $output = stream_get_contents($pipes[1]);
                        $err = stream_get_contents($pipes[2]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        $status = proc_close($process);
                        if ($status === 0) {
                            $pythonSummary = json_decode($output, true);
                        } else {
                            $pythonError = $err ?: 'Python exited with status ' . $status;
                        }
                    }
                }
                ?>
                <section>
                    <div class="grid">
                        <article class="card">
                            <h3 class="card-title">Key metrics</h3>
                            <p class="card-text">Trainees: <?php echo (int)$totalUsers; ?></p>
                            <p class="card-text">Approved trainers: <?php echo (int)$totalTrainers; ?></p>
                            <p class="card-text">Total bookings: <?php echo (int)$totalBookings; ?></p>
                        </article>
                        <article class="card">
                            <h3 class="card-title">Popular categories</h3>
                            <?php foreach ($popularCategories as $row): ?>
                                <p class="card-text">
                                    <?php echo e($row['name']); ?>
                                    <span class="progress-bar" style="display:inline-block;width:60%;margin-left:0.4rem;vertical-align:middle;">
                                        <span class="progress-bar-inner" style="width:<?php echo min(100, $row['count'] * 20); ?>%;"></span>
                                    </span>
                                    (<?php echo (int)$row['count']; ?>)
                                </p>
                            <?php endforeach; ?>
                            <?php if (!$popularCategories): ?>
                                <p class="card-text">No bookings yet.</p>
                            <?php endif; ?>
                        </article>
                        <article class="card">
                            <h3 class="card-title">Python analytics</h3>
                            <?php if ($pythonSummary): ?>
                                <p class="card-text">Generated by Python analytics module.</p>
                                <pre style="font-size:0.75rem;background:#0b1120;color:#e5e7eb;border-radius:12px;padding:0.6rem;overflow:auto;"><?php echo e(json_encode($pythonSummary, JSON_PRETTY_PRINT)); ?></pre>
                            <?php else: ?>
                                <p class="card-text">Python analytics module not available or failed to run.</p>
                                <?php if ($pythonError): ?>
                                    <p class="card-text"><?php echo e($pythonError); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </article>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
<?php endif; ?>

<script src="assets/app.js"></script>
</body>
</html>

