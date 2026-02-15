<?php
require __DIR__ . '/config.php';

$section = $_GET['section'] ?? 'browse';
$info = '';
$error = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['user']);
    redirect('trainee.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trainee_register'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $location = trim($_POST['location'] ?? '');
    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND role = "trainee"');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, location, created_at) VALUES (?, ?, ?, "trainee", ?, NOW())');
            $stmt->execute([$name, $email, $hash, $location]);
            $info = 'Registration successful. You can log in now.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trainee_login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = "trainee"');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => 'trainee',
        ];
        redirect('trainee.php');
    } else {
        $error = 'Invalid login credentials.';
    }
}

if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'trainee') {
    $traineeId = $_SESSION['user']['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booking'])) {
        $trainerId = (int)($_POST['trainer_id'] ?? 0);
        $programId = (int)($_POST['program_id'] ?? 0);
        $date = $_POST['session_date'] ?? '';
        $slot = $_POST['slot'] ?? '';
        $duration = (int)($_POST['duration'] ?? 60);
        if ($trainerId && $programId && $date !== '' && $slot !== '') {
            $stmt = $pdo->prepare('INSERT INTO bookings (trainee_id, trainer_id, program_id, session_date, session_time, duration_minutes, status, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, "pending", NOW())');
            $stmt->execute([$traineeId, $trainerId, $programId, $date, $slot, $duration]);
            $info = 'Booking request submitted. Please wait for trainer confirmation.';
            $section = 'browse';
        } else {
            $error = 'Please choose program, date and time.';
        }
    }

    if (isset($_GET['pay'])) {
        $bookingId = (int)$_GET['pay'];
        $stmt = $pdo->prepare('SELECT b.*, p.price FROM bookings b
            JOIN training_programs p ON p.id = b.program_id
            WHERE b.id = ? AND b.trainee_id = ? AND b.status = "accepted"');
        $stmt->execute([$bookingId, $traineeId]);
        $booking = $stmt->fetch();
        if ($booking) {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO payments (booking_id, amount, status, method, paid_at)
                           VALUES (?, ?, "paid", "simulated", NOW())')
                ->execute([$bookingId, $booking['price']]);
            $pdo->prepare('UPDATE bookings SET status = "paid" WHERE id = ?')->execute([$bookingId]);
            $pdo->commit();
            $info = 'Payment successful. Session confirmed.';
            $section = 'attend';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_progress'])) {
        $programId = (int)($_POST['program_id'] ?? 0);
        if ($programId) {
            $stmt = $pdo->prepare('SELECT * FROM progress WHERE trainee_id = ? AND program_id = ?');
            $stmt->execute([$traineeId, $programId]);
            $row = $stmt->fetch();
            if ($row) {
                $completed = min($row['completed_lessons'] + 1, $row['total_lessons']);
                $percent = $row['total_lessons'] > 0 ? round($completed / $row['total_lessons'] * 100) : 0;
                $pdo->prepare('UPDATE progress SET completed_lessons = ?, completion_percent = ?, last_accessed = NOW() WHERE id = ?')
                    ->execute([$completed, $percent, $row['id']]);
            } else {
                $total = 5;
                $pdo->prepare('INSERT INTO progress (trainee_id, program_id, completed_lessons, total_lessons, completion_percent, last_accessed)
                               VALUES (?, ?, 1, ?, 20, NOW())')
                    ->execute([$traineeId, $programId, $total]);
            }
            $info = 'Progress updated.';
            $section = 'progress';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
        $programId = (int)($_POST['program_id'] ?? 0);
        $score = (int)($_POST['score'] ?? 0);
        if ($programId) {
            $stmt = $pdo->prepare('SELECT * FROM progress WHERE trainee_id = ? AND program_id = ?');
            $stmt->execute([$traineeId, $programId]);
            $row = $stmt->fetch();
            if ($row) {
                $percent = max($row['completion_percent'], min(100, $score));
                $pdo->prepare('UPDATE progress SET quiz_score = ?, completion_percent = ?, last_accessed = NOW() WHERE id = ?')
                    ->execute([$score, $percent, $row['id']]);
            } else {
                $pdo->prepare('INSERT INTO progress (trainee_id, program_id, completed_lessons, total_lessons, quiz_score, completion_percent, last_accessed)
                               VALUES (?, ?, 0, 5, ?, ?, NOW())')
                    ->execute([$traineeId, $programId, $score, $score]);
            }
            $info = 'Quiz submitted.';
            $section = 'progress';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_trainer'])) {
        $trainerId = (int)($_POST['trainer_id'] ?? 0);
        $programId = (int)($_POST['program_id'] ?? 0);
        $rating = (int)($_POST['rating_value'] ?? 0);
        $review = trim($_POST['review'] ?? '');
        if ($trainerId && $rating > 0) {
            $stmt = $pdo->prepare('REPLACE INTO ratings (trainee_id, trainer_id, program_id, rating, review, created_at)
                                   VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$traineeId, $trainerId, $programId ?: null, $rating, $review]);
            $info = 'Thank you for rating your trainer.';
            $section = 'rate';
        } else {
            $error = 'Please select a rating.';
        }
    }
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trainee Area – TraineeConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'trainee'): ?>
    <div class="layout-auth">
        <aside class="auth-aside">
            <div class="auth-aside-inner">
                <h2 class="hero-title">Own your learning journey.</h2>
                <p class="hero-subtitle">
                    Book 1‑to‑1 or group sessions, access materials and track your performance
                    in a single dashboard.
                </p>
                <div class="hero-pills">
                    <span class="pill">Smart progress charts</span>
                    <span class="pill">Time‑bound quizzes</span>
                    <span class="pill">Instant certificates</span>
                </div>
            </div>
        </aside>
        <main class="auth-main">
            <div class="auth-card" data-tab-container>
                <div class="tabs">
                    <button type="button" class="tab active" data-tab-target="login">Login</button>
                    <button type="button" class="tab" data-tab-target="register">Register</button>
                </div>
                <?php if ($error): ?>
                    <div class="alert error"><?php echo e($error); ?></div>
                <?php endif; ?>
                <?php if ($info): ?>
                    <div class="alert success"><?php echo e($info); ?></div>
                <?php endif; ?>
                <section data-tab="login">
                    <form method="post">
                        <div class="field">
                            <label for="login-email">Email</label>
                            <input id="login-email" type="email" name="email" required>
                        </div>
                        <div class="field" style="margin-top:0.6rem;">
                            <label for="login-password">Password</label>
                            <input id="login-password" type="password" name="password" required>
                        </div>
                        <div style="margin-top:0.9rem;">
                            <button type="submit" name="trainee_login" class="btn" style="width:100%;">Login as trainee</button>
                        </div>
                    </form>
                </section>
                <section data-tab="register" hidden>
                    <form method="post">
                        <div class="field">
                            <label for="reg-name">Full name</label>
                            <input id="reg-name" type="text" name="name" required>
                        </div>
                        <div class="field" style="margin-top:0.6rem;">
                            <label for="reg-email">Email</label>
                            <input id="reg-email" type="email" name="email" required>
                        </div>
                        <div class="field" style="margin-top:0.6rem;">
                            <label for="reg-location">Location</label>
                            <input id="reg-location" type="text" name="location" placeholder="City / Country">
                        </div>
                        <div class="field" style="margin-top:0.6rem;">
                            <label for="reg-password">Password</label>
                            <input id="reg-password" type="password" name="password" required>
                        </div>
                        <div style="margin-top:0.9rem;">
                            <button type="submit" name="trainee_register" class="btn" style="width:100%;">Create trainee account</button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>
<?php else: ?>
    <div class="dashboard">
        <aside class="sidebar">
            <div>
                <div class="sidebar-title">Trainee</div>
                <strong><?php echo e($_SESSION['user']['name']); ?></strong>
            </div>
            <nav class="sidebar-nav">
                <a href="trainee.php?section=browse" class="sidebar-link <?php echo $section === 'browse' ? 'active' : ''; ?>">Browse trainers</a>
                <a href="trainee.php?section=attend" class="sidebar-link <?php echo $section === 'attend' ? 'active' : ''; ?>">Attend sessions</a>
                <a href="trainee.php?section=progress" class="sidebar-link <?php echo $section === 'progress' ? 'active' : ''; ?>">Track progress</a>
                <a href="trainee.php?section=certificate" class="sidebar-link <?php echo $section === 'certificate' ? 'active' : ''; ?>">Certificates</a>
                <a href="trainee.php?section=rate" class="sidebar-link <?php echo $section === 'rate' ? 'active' : ''; ?>">Rate trainer</a>
                <a href="trainee.php?logout=1" class="sidebar-link">Logout</a>
            </nav>
        </aside>
        <main class="main">
            <header class="main-header">
                <div>
                    <h2 class="section-title">
                        <?php
                        echo match ($section) {
                            'browse' => 'Browse & book trainers',
                            'attend' => 'Attend training sessions',
                            'progress' => 'Your learning progress',
                            'certificate' => 'Your certificates',
                            'rate' => 'Rate your trainers',
                            default => 'Trainee dashboard'
                        };
                        ?>
                    </h2>
                    <p class="section-subtitle">
                        <?php
                        echo match ($section) {
                            'browse' => 'Discover trainers by category, location and availability.',
                            'attend' => 'Join confirmed sessions and access materials & quizzes.',
                            'progress' => 'Visualise completion rates and quiz performance.',
                            'certificate' => 'Download certificates issued by trainers.',
                            'rate' => 'Share feedback using the 5‑star rating system.',
                            default => ''
                        };
                        ?>
                    </p>
                </div>
            </header>
            <?php if ($error): ?>
                <div class="alert error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($info): ?>
                <div class="alert success"><?php echo e($info); ?></div>
            <?php endif; ?>

            <?php if ($section === 'browse'):
                $where = 't.status = "approved"';
                $params = [];
                $cat = $_GET['cat'] ?? '';
                $loc = $_GET['loc'] ?? '';
                if ($cat !== '') {
                    $where .= ' AND c.id = ?';
                    $params[] = $cat;
                }
                if ($loc !== '') {
                    $where .= ' AND t.location LIKE ?';
                    $params[] = '%' . $loc . '%';
                }
                $sql = "SELECT t.*, c.name AS category_name
                        FROM trainers t
                        LEFT JOIN training_programs p ON p.trainer_id = t.id
                        LEFT JOIN categories c ON c.id = p.category_id
                        WHERE $where
                        GROUP BY t.id
                        ORDER BY t.created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $trainers = $stmt->fetchAll();
                ?>
                <section>
                    <form method="get" class="search-row">
                        <input type="hidden" name="section" value="browse">
                        <div class="field">
                            <label for="b-cat">Category</label>
                            <select id="b-cat" name="cat">
                                <option value="">Any</option>
                                <?php foreach ($categories as $catRow): ?>
                                    <option value="<?php echo e($catRow['id']); ?>" <?php echo $cat === (string)$catRow['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($catRow['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="b-loc">Location</label>
                            <input id="b-loc" type="text" name="loc" value="<?php echo e($loc); ?>" placeholder="City / Country">
                        </div>
                        <div class="field">
                            <label>&nbsp;</label>
                            <button class="btn" style="width:100%;">Filter</button>
                        </div>
                    </form>
                    <div class="grid" style="margin-top:1rem;">
                        <?php if (!$trainers): ?>
                            <p style="font-size:0.85rem;color:#6b7280;">No trainers match your filters yet.</p>
                        <?php else: ?>
                            <?php foreach ($trainers as $t):
                                $programs = $pdo->prepare('SELECT p.*, c.name AS category_name FROM training_programs p JOIN categories c ON c.id = p.category_id WHERE p.trainer_id = ? AND p.is_active = 1 ORDER BY p.title');
                                $programs->execute([$t['id']]);
                                $programs = $programs->fetchAll();
                                ?>
                                <article class="card">
                                    <div class="trainer-card">
                                        <div class="avatar"><?php echo strtoupper(substr($t['name'], 0, 1)); ?></div>
                                        <div>
                                            <div class="card-title"><?php echo e($t['name']); ?></div>
                                            <div style="font-size:0.8rem;color:#6b7280;"><?php echo e($t['location']); ?></div>
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
                                    <?php if ($programs): ?>
                                        <div style="margin-top:0.7rem;">
                                            <?php foreach ($programs as $p): ?>
                                                <form method="post" style="border-top:1px solid #e5e7eb;padding-top:0.5rem;margin-top:0.4rem;">
                                                    <strong style="font-size:0.88rem;"><?php echo e($p['title']); ?></strong>
                                                    <div style="font-size:0.78rem;color:#6b7280;margin-top:0.1rem;"><?php echo e($p['category_name']); ?> • <?php echo (int)$p['duration_hours']; ?> hrs • ₹<?php echo (int)$p['price']; ?></div>
                                                    <div class="search-row" style="margin-top:0.4rem;">
                                                        <div class="field">
                                                            <label>Date</label>
                                                            <input type="date" name="session_date" required>
                                                        </div>
                                                        <div class="field">
                                                            <label>Time slot</label>
                                                            <select name="slot" required>
                                                                <option value="">Select slot</option>
                                                                <option value="09:00">09:00</option>
                                                                <option value="11:00">11:00</option>
                                                                <option value="14:00">14:00</option>
                                                                <option value="16:00">16:00</option>
                                                            </select>
                                                        </div>
                                                        <div class="field">
                                                            <label>Duration</label>
                                                            <select name="duration">
                                                                <option value="60">1 hour</option>
                                                                <option value="90">1.5 hours</option>
                                                                <option value="120">2 hours</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="trainer_id" value="<?php echo (int)$t['id']; ?>">
                                                    <input type="hidden" name="program_id" value="<?php echo (int)$p['id']; ?>">
                                                    <div style="margin-top:0.6rem;display:flex;justify-content:flex-end;">
                                                        <button type="submit" name="create_booking" class="btn">Request booking</button>
                                                    </div>
                                                </form>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p style="font-size:0.78rem;color:#9ca3af;margin-top:0.4rem;">Trainer has not published programs yet.</p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php elseif ($section === 'attend'):
                $stmt = $pdo->prepare('SELECT b.*, t.name AS trainer_name, p.title AS program_title
                    FROM bookings b
                    JOIN trainers t ON t.id = b.trainer_id
                    JOIN training_programs p ON p.id = b.program_id
                    WHERE b.trainee_id = ?
                    ORDER BY b.session_date DESC, b.session_time DESC');
                $stmt->execute([$traineeId]);
                $bookings = $stmt->fetchAll();
                ?>
                <section>
                    <div class="grid">
                        <?php foreach ($bookings as $b): ?>
                            <article class="card">
                                <div class="card-chip">
                                    Session • <?php echo e($b['program_title']); ?>
                                </div>
                                <div class="card-title" style="margin-top:0.4rem;">
                                    <?php echo e($b['trainer_name']); ?>
                                </div>
                                <p class="card-text">
                                    <?php echo e($b['session_date']); ?> at <?php echo e(substr($b['session_time'], 0, 5)); ?> •
                                    <?php echo (int)$b['duration_minutes']; ?> mins
                                </p>
                                <p class="card-text">
                                    Status:
                                    <strong><?php echo ucfirst($b['status']); ?></strong>
                                </p>
                                <?php if ($b['status'] === 'accepted'): ?>
                                    <a href="trainee.php?section=attend&pay=<?php echo (int)$b['id']; ?>" class="btn" style="margin-top:0.4rem;">Proceed to payment</a>
                                <?php elseif ($b['status'] === 'paid' || $b['status'] === 'completed'):
                                    $materials = $pdo->prepare('SELECT * FROM materials WHERE program_id = ?');
                                    $materials->execute([$b['program_id']]);
                                    $materials = $materials->fetchAll();
                                    $videos = $pdo->prepare('SELECT * FROM videos WHERE program_id = ?');
                                    $videos->execute([$b['program_id']]);
                                    $videos = $videos->fetchAll();
                                    ?>
                                    <div style="margin-top:0.6rem;">
                                        <div class="section-subtitle" style="margin-bottom:0.3rem;">Videos</div>
                                        <?php if ($videos): ?>
                                            <ul style="padding-left:1.1rem;margin:0;font-size:0.8rem;">
                                                <?php foreach ($videos as $v): ?>
                                                    <li><a href="<?php echo e($v['url']); ?>" target="_blank"><?php echo e($v['title']); ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="card-text">No videos uploaded yet.</p>
                                        <?php endif; ?>
                                        <div class="section-subtitle" style="margin:0.4rem 0 0.3rem;">Study materials</div>
                                        <?php if ($materials): ?>
                                            <ul style="padding-left:1.1rem;margin:0;font-size:0.8rem;">
                                                <?php foreach ($materials as $m): ?>
                                                    <li><a href="<?php echo e($m['url']); ?>" target="_blank"><?php echo e($m['title']); ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="card-text">No materials uploaded yet.</p>
                                        <?php endif; ?>
                                        <form method="post" style="margin-top:0.6rem;">
                                            <input type="hidden" name="program_id" value="<?php echo (int)$b['program_id']; ?>">
                                            <button type="submit" name="mark_progress" class="btn outline" style="font-size:0.8rem;">Mark lesson completed</button>
                                        </form>
                                        <?php
                                        $quiz = $pdo->prepare('SELECT * FROM quizzes WHERE program_id = ? LIMIT 1');
                                        $quiz->execute([$b['program_id']]);
                                        $quiz = $quiz->fetch();
                                        if ($quiz):
                                            $now = date('Y-m-d H:i:s');
                                            $active = (!$quiz['active_from'] || $quiz['active_from'] <= $now) &&
                                                (!$quiz['active_to'] || $quiz['active_to'] >= $now);
                                            ?>
                                            <div style="margin-top:0.6rem;">
                                                <div class="section-subtitle">Quiz: <?php echo e($quiz['title']); ?></div>
                                                <p class="card-text">
                                                    Status:
                                                    <span class="tag">
                                                        <span class="status-dot <?php echo $active ? 'green' : 'red'; ?>"></span>
                                                        <?php echo $active ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </p>
                                                <?php if ($active): ?>
                                                    <form method="post" style="margin-top:0.4rem;">
                                                        <input type="hidden" name="program_id" value="<?php echo (int)$b['program_id']; ?>">
                                                        <div class="field">
                                                            <label>Your quiz score (0–100)</label>
                                                            <input type="number" name="score" min="0" max="100" required>
                                                        </div>
                                                        <button type="submit" name="submit_quiz" class="btn" style="margin-top:0.4rem;font-size:0.8rem;">Submit quiz</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php elseif ($section === 'progress'):
                $stmt = $pdo->prepare('SELECT pr.*, p.title AS program_title, t.name AS trainer_name
                    FROM progress pr
                    JOIN training_programs p ON p.id = pr.program_id
                    JOIN trainers t ON t.id = p.trainer_id
                    WHERE pr.trainee_id = ?
                    ORDER BY pr.last_accessed DESC');
                $stmt->execute([$traineeId]);
                $items = $stmt->fetchAll();
                ?>
                <section>
                    <div class="grid">
                        <?php foreach ($items as $item): ?>
                            <article class="card">
                                <div class="card-chip"><?php echo e($item['program_title']); ?></div>
                                <div class="card-title" style="margin-top:0.4rem;"><?php echo e($item['trainer_name']); ?></div>
                                <p class="card-text">
                                    Completed lessons: <?php echo (int)$item['completed_lessons']; ?> / <?php echo (int)$item['total_lessons']; ?>
                                </p>
                                <div class="progress-bar" style="margin-top:0.4rem;">
                                    <div class="progress-bar-inner" style="width:<?php echo (int)$item['completion_percent']; ?>%;"></div>
                                </div>
                                <p class="card-text" style="margin-top:0.4rem;">
                                    Quiz score: <?php echo $item['quiz_score'] !== null ? (int)$item['quiz_score'] . '/100' : 'Not attempted'; ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                            <p style="font-size:0.85rem;color:#6b7280;">No progress to display yet. Start attending sessions.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php elseif ($section === 'certificate'):
                $stmt = $pdo->prepare('SELECT c.*, p.title AS program_title, t.name AS trainer_name
                    FROM certificates c
                    JOIN training_programs p ON p.id = c.program_id
                    JOIN trainers t ON t.id = p.trainer_id
                    WHERE c.trainee_id = ?
                    ORDER BY c.issued_at DESC');
                $stmt->execute([$traineeId]);
                $certs = $stmt->fetchAll();
                ?>
                <section>
                    <div class="grid">
                        <?php foreach ($certs as $c): ?>
                            <article class="card">
                                <div class="card-chip">Certificate</div>
                                <div class="card-title" style="margin-top:0.4rem;"><?php echo e($c['program_title']); ?></div>
                                <p class="card-text">Issued by <?php echo e($c['trainer_name']); ?> on <?php echo e($c['issued_at']); ?></p>
                                <a href="<?php echo e($c['file_path']); ?>" class="btn" style="margin-top:0.5rem;" target="_blank">Download</a>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$certs): ?>
                            <p style="font-size:0.85rem;color:#6b7280;">No certificates available yet.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php elseif ($section === 'rate'):
                $stmt = $pdo->prepare('SELECT DISTINCT t.id, t.name, t.location
                    FROM bookings b
                    JOIN trainers t ON t.id = b.trainer_id
                    WHERE b.trainee_id = ? AND b.status IN ("paid","completed")');
                $stmt->execute([$traineeId]);
                $ts = $stmt->fetchAll();
                ?>
                <section>
                    <div class="grid">
                        <?php foreach ($ts as $t): ?>
                            <article class="card">
                                <div class="trainer-card">
                                    <div class="avatar"><?php echo strtoupper(substr($t['name'], 0, 1)); ?></div>
                                    <div>
                                        <div class="card-title"><?php echo e($t['name']); ?></div>
                                        <div class="card-text"><?php echo e($t['location']); ?></div>
                                    </div>
                                </div>
                                <form method="post" style="margin-top:0.6rem;">
                                    <input type="hidden" name="trainer_id" value="<?php echo (int)$t['id']; ?>">
                                    <input type="hidden" name="program_id" value="">
                                    <div class="field">
                                        <label>Rate trainer</label>
                                        <div class="rating-input">
                                            <input type="hidden" name="rating_value" value="0">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span data-value="<?php echo $i; ?>">★</span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="field" style="margin-top:0.5rem;">
                                        <label>Review (optional)</label>
                                        <textarea name="review" placeholder="Share your experience"></textarea>
                                    </div>
                                    <button type="submit" name="rate_trainer" class="btn" style="margin-top:0.5rem;">Submit rating</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$ts): ?>
                            <p style="font-size:0.85rem;color:#6b7280;">You can rate trainers after completing at least one paid session.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
<?php endif; ?>

<script src="assets/app.js"></script>
</body>
</html>

