<?php
require __DIR__ . '/config.php';

$section = $_GET['section'] ?? 'profile';
$info = '';
$error = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['trainer']);
    redirect('trainer.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trainer_register'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $location = trim($_POST['location'] ?? '');
    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM trainers WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'A trainer with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO trainers (name, email, password_hash, bio, experience, location, status, created_at)
                                   VALUES (?, ?, ?, "", "", ?, "pending", NOW())');
            $stmt->execute([$name, $email, $hash, $location]);
            $info = 'Registration submitted. Admin approval is required before you can login.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trainer_login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM trainers WHERE email = ?');
    $stmt->execute([$email]);
    $trainer = $stmt->fetch();
    if ($trainer && password_verify($password, $trainer['password_hash'])) {
        if ($trainer['status'] !== 'approved') {
            $error = 'Your profile is awaiting admin approval.';
        } else {
            $_SESSION['trainer'] = [
                'id' => $trainer['id'],
                'name' => $trainer['name'],
            ];
            redirect('trainer.php');
        }
    } else {
        $error = 'Invalid login credentials.';
    }
}

if (isset($_SESSION['trainer'])) {
    $trainerId = $_SESSION['trainer']['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $bio = trim($_POST['bio'] ?? '');
        $experience = trim($_POST['experience'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $pdo->prepare('UPDATE trainers SET bio = ?, experience = ?, location = ? WHERE id = ?')
            ->execute([$bio, $experience, $location, $trainerId]);
        $info = 'Profile updated.';
        $section = 'profile';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_program'])) {
        $programId = (int)($_POST['program_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $duration = (int)($_POST['duration_hours'] ?? 1);
        $price = (int)($_POST['price'] ?? 0);
        $availability = trim($_POST['availability'] ?? '');
        if ($title === '' || !$categoryId) {
            $error = 'Title and category are required.';
        } else {
            if ($programId) {
                $stmt = $pdo->prepare('UPDATE training_programs
                    SET title = ?, category_id = ?, description = ?, duration_hours = ?, price = ?, availability_slots = ?, is_active = ?
                    WHERE id = ? AND trainer_id = ?');
                $stmt->execute([$title, $categoryId, $description, $duration, $price, $availability, isset($_POST['is_active']) ? 1 : 0, $programId, $trainerId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO training_programs (trainer_id, category_id, title, description, duration_hours, price, availability_slots, is_active)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
                $stmt->execute([$trainerId, $categoryId, $title, $description, $duration, $price, $availability]);
            }
            $info = 'Training program saved.';
            $section = 'programs';
        }
    }

    if (isset($_GET['delete_program'])) {
        $pid = (int)$_GET['delete_program'];
        $pdo->prepare('DELETE FROM training_programs WHERE id = ? AND trainer_id = ?')->execute([$pid, $trainerId]);
        $info = 'Program removed.';
        $section = 'programs';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_video'])) {
        $programId = (int)($_POST['program_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        if ($programId && $title && $url) {
            $pdo->prepare('INSERT INTO videos (trainer_id, program_id, title, url) VALUES (?, ?, ?, ?)')
                ->execute([$trainerId, $programId, $title, $url]);
            $info = 'Video added.';
            $section = 'materials';
        } else {
            $error = 'Please fill in all video fields.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_material'])) {
        $programId = (int)($_POST['program_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        if ($programId && $title && $url) {
            $pdo->prepare('INSERT INTO materials (trainer_id, program_id, title, url, type) VALUES (?, ?, ?, ?, "document")')
                ->execute([$trainerId, $programId, $title, $url]);
            $info = 'Material added.';
            $section = 'materials';
        } else {
            $error = 'Please fill in all material fields.';
        }
    }

    if (isset($_GET['booking']) && isset($_GET['action']) && in_array($_GET['action'], ['accept', 'reject'], true)) {
        $bookingId = (int)$_GET['booking'];
        $newStatus = $_GET['action'] === 'accept' ? 'accepted' : 'rejected';
        $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ? AND trainer_id = ?')
            ->execute([$newStatus, $bookingId, $trainerId]);
        $info = 'Booking updated.';
        $section = 'bookings';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_certificate'])) {
        $programId = (int)($_POST['program_id'] ?? 0);
        $traineeId = (int)($_POST['trainee_id'] ?? 0);
        $filePath = trim($_POST['file_path'] ?? '');
        if ($programId && $traineeId && $filePath !== '') {
            $pdo->prepare('INSERT INTO certificates (trainee_id, program_id, trainer_id, file_path, issued_at)
                           VALUES (?, ?, ?, ?, NOW())')
                ->execute([$traineeId, $programId, $trainerId, $filePath]);
            $info = 'Certificate issued.';
            $section = 'certificates';
        } else {
            $error = 'Please provide certificate link.';
        }
    }
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trainer Area – TraineeConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if (!isset($_SESSION['trainer'])): ?>
    <div class="layout-auth">
        <aside class="auth-aside">
            <div class="auth-aside-inner">
                <h2 class="hero-title">Build and scale your training practice.</h2>
                <p class="hero-subtitle">
                    Publish structured programs, manage bookings and track learner performance
                    in one professional dashboard.
                </p>
                <div class="hero-pills">
                    <span class="pill">Booking management</span>
                    <span class="pill">Performance analytics</span>
                    <span class="pill">Certificate issuing</span>
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
                            <button type="submit" name="trainer_login" class="btn" style="width:100%;">Login as trainer</button>
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
                        <div class="field" style="margin-top:0.6rem%;">
                            <label for="reg-location">Location</label>
                            <input id="reg-location" type="text" name="location" placeholder="City / Country">
                        </div>
                        <div class="field" style="margin-top:0.6rem;">
                            <label for="reg-password">Password</label>
                            <input id="reg-password" type="password" name="password" required>
                        </div>
                        <div style="margin-top:0.9rem;">
                            <button type="submit" name="trainer_register" class="btn" style="width:100%;">Register as trainer</button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>
<?php else:
    $stmt = $pdo->prepare('SELECT * FROM trainers WHERE id = ?');
    $stmt->execute([$trainerId]);
    $trainer = $stmt->fetch();
    ?>
    <div class="dashboard">
        <aside class="sidebar">
            <div>
                <div class="sidebar-title">Trainer</div>
                <strong><?php echo e($trainer['name']); ?></strong>
            </div>
            <nav class="sidebar-nav">
                <a href="trainer.php?section=profile" class="sidebar-link <?php echo $section === 'profile' ? 'active' : ''; ?>">Profile</a>
                <a href="trainer.php?section=programs" class="sidebar-link <?php echo $section === 'programs' ? 'active' : ''; ?>">Training programs</a>
                <a href="trainer.php?section=materials" class="sidebar-link <?php echo $section === 'materials' ? 'active' : ''; ?>">Upload materials</a>
                <a href="trainer.php?section=bookings" class="sidebar-link <?php echo $section === 'bookings' ? 'active' : ''; ?>">Booking management</a>
                <a href="trainer.php?section=performance" class="sidebar-link <?php echo $section === 'performance' ? 'active' : ''; ?>">Track performance</a>
                <a href="trainer.php?section=certificates" class="sidebar-link <?php echo $section === 'certificates' ? 'active' : ''; ?>">Issue certificates</a>
                <a href="trainer.php?section=ratings" class="sidebar-link <?php echo $section === 'ratings' ? 'active' : ''; ?>">View ratings</a>
                <a href="trainer.php?logout=1" class="sidebar-link">Logout</a>
            </nav>
        </aside>
        <main class="main">
            <header class="main-header">
                <div>
                    <h2 class="section-title">
                        <?php
                        echo match ($section) {
                            'profile' => 'Your trainer profile',
                            'programs' => 'Training programs',
                            'materials' => 'Upload materials',
                            'bookings' => 'Booking management',
                            'performance' => 'Trainee performance',
                            'certificates' => 'Issue certificates',
                            'ratings' => 'Ratings & reviews',
                            default => 'Trainer dashboard'
                        };
                        ?>
                    </h2>
                </div>
                <div class="chips">
                    <span class="chip">Status: <?php echo e(ucfirst($trainer['status'])); ?></span>
                </div>
            </header>
            <?php if ($error): ?>
                <div class="alert error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($info): ?>
                <div class="alert success"><?php echo e($info); ?></div>
            <?php endif; ?>

            <?php if ($section === 'profile'): ?>
                <section>
                    <form method="post">
                        <div class="field">
                            <label>Name</label>
                            <input type="text" value="<?php echo e($trainer['name']); ?>" disabled>
                        </div>
                        <div class="field" style="margin-top:0.6rem;">
                            <label>Email</label>
                            <input type="email" value="<?php echo e($trainer['email']); ?>" disabled>
                        </div>
                        <div class="field" style="margin-top:0.6rem;">
                            <label>Bio</label>
                            <textarea name="bio"><?php echo e($trainer['bio']); ?></textarea>
                        </div>
                        <div class="field" style="margin-top:0.6rem;">
                            <label>Experience</label>
                            <textarea name="experience"><?php echo e($trainer['experience']); ?></textarea>
                        </div>
                        <div class="field" style="margin-top:0.6rem;">
                            <label>Location</label>
                            <input type="text" name="location" value="<?php echo e($trainer['location']); ?>">
                        </div>
                        <button type="submit" name="update_profile" class="btn" style="margin-top:0.9rem;">Update profile</button>
                    </form>
                </section>
            <?php elseif ($section === 'programs'):
                $stmt = $pdo->prepare('SELECT p.*, c.name AS category_name FROM training_programs p JOIN categories c ON c.id = p.category_id WHERE trainer_id = ? ORDER BY p.title');
                $stmt->execute([$trainerId]);
                $programs = $stmt->fetchAll();
                ?>
                <section>
                    <div class="card">
                        <h3 class="card-title">Create / edit program</h3>
                        <form method="post" style="margin-top:0.6rem;">
                            <input type="hidden" name="program_id" value="">
                            <div class="search-row">
                                <div class="field">
                                    <label>Title</label>
                                    <input type="text" name="title" required>
                                </div>
                                <div class="field">
                                    <label>Category</label>
                                    <select name="category_id" required>
                                        <option value="">Select</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo e($cat['id']); ?>"><?php echo e($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="search-row" style="margin-top:0.4rem;">
                                <div class="field">
                                    <label>Duration (hours)</label>
                                    <input type="number" name="duration_hours" min="1" value="1">
                                </div>
                                <div class="field">
                                    <label>Price</label>
                                    <input type="number" name="price" min="0" value="0">
                                </div>
                            </div>
                            <div class="field" style="margin-top:0.4rem;">
                                <label>Availability slots (e.g. Mon 09:00, Wed 14:00)</label>
                                <input type="text" name="availability" placeholder="Free text, used in calendar display">
                            </div>
                            <div class="field" style="margin-top:0.4rem;">
                                <label>Description</label>
                                <textarea name="description"></textarea>
                            </div>
                            <label style="display:flex;align-items:center;gap:0.4rem;margin-top:0.5rem;font-size:0.8rem;">
                                <input type="checkbox" name="is_active" checked> Visible to trainees
                            </label>
                            <button type="submit" name="save_program" class="btn" style="margin-top:0.8rem;">Save program</button>
                        </form>
                    </div>

                    <div class="grid" style="margin-top:1rem;">
                        <?php foreach ($programs as $p): ?>
                            <article class="card">
                                <div class="card-chip"><?php echo e($p['category_name']); ?></div>
                                <div class="card-title" style="margin-top:0.4rem;"><?php echo e($p['title']); ?></div>
                                <p class="card-text">
                                    <?php echo (int)$p['duration_hours']; ?> hrs • ₹<?php echo (int)$p['price']; ?>
                                </p>
                                <p class="card-text">Availability: <?php echo e($p['availability_slots']); ?></p>
                                <p class="card-text">
                                    Visibility:
                                    <span class="tag">
                                        <span class="status-dot <?php echo $p['is_active'] ? 'green' : 'red'; ?>"></span>
                                        <?php echo $p['is_active'] ? 'Active' : 'Hidden'; ?>
                                    </span>
                                </p>
                                <a href="trainer.php?section=programs&delete_program=<?php echo (int)$p['id']; ?>" class="btn outline" style="margin-top:0.5rem;font-size:0.8rem;">Remove</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php elseif ($section === 'materials'):
                $stmt = $pdo->prepare('SELECT id, title FROM training_programs WHERE trainer_id = ? ORDER BY title');
                $stmt->execute([$trainerId]);
                $programs = $stmt->fetchAll();
                ?>
                <section>
                    <div class="grid">
                        <article class="card">
                            <h3 class="card-title">Upload video (URL)</h3>
                            <form method="post" style="margin-top:0.6rem;">
                                <div class="field">
                                    <label>Program</label>
                                    <select name="program_id" required>
                                        <option value="">Select</option>
                                        <?php foreach ($programs as $p): ?>
                                            <option value="<?php echo (int)$p['id']; ?>"><?php echo e($p['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field" style="margin-top:0.4rem;">
                                    <label>Title</label>
                                    <input type="text" name="title" required>
                                </div>
                                <div class="field" style="margin-top:0.4rem;">
                                    <label>Video URL (YouTube, etc.)</label>
                                    <input type="url" name="url" required>
                                </div>
                                <button type="submit" name="add_video" class="btn" style="margin-top:0.8rem;">Add video</button>
                            </form>
                        </article>
                        <article class="card">
                            <h3 class="card-title">Upload study material (URL)</h3>
                            <form method="post" style="margin-top:0.6rem;">
                                <div class="field">
                                    <label>Program</label>
                                    <select name="program_id" required>
                                        <option value="">Select</option>
                                        <?php foreach ($programs as $p): ?>
                                            <option value="<?php echo (int)$p['id']; ?>"><?php echo e($p['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field" style="margin-top:0.4rem;">
                                    <label>Title</label>
                                    <input type="text" name="title" required>
                                </div>
                                <div class="field" style="margin-top:0.4rem;">
                                    <label>File URL (PDF, slides, etc.)</label>
                                    <input type="url" name="url" required>
                                </div>
                                <button type="submit" name="add_material" class="btn" style="margin-top:0.8rem;">Add material</button>
                            </form>
                        </article>
                    </div>
                </section>
            <?php elseif ($section === 'bookings'):
                $stmt = $pdo->prepare('SELECT b.*, u.name AS trainee_name, p.title AS program_title
                    FROM bookings b
                    JOIN users u ON u.id = b.trainee_id
                    JOIN training_programs p ON p.id = b.program_id
                    WHERE b.trainer_id = ?
                    ORDER BY b.session_date DESC, b.session_time DESC');
                $stmt->execute([$trainerId]);
                $bookings = $stmt->fetchAll();
                ?>
                <section>
                    <div class="grid">
                        <?php foreach ($bookings as $b): ?>
                            <article class="card">
                                <div class="card-chip"><?php echo e($b['program_title']); ?></div>
                                <div class="card-title" style="margin-top:0.4rem;"><?php echo e($b['trainee_name']); ?></div>
                                <p class="card-text">
                                    <?php echo e($b['session_date']); ?> at <?php echo e(substr($b['session_time'], 0, 5)); ?> • <?php echo (int)$b['duration_minutes']; ?> mins
                                </p>
                                <p class="card-text">
                                    Status:
                                    <span class="tag">
                                        <span class="status-dot <?php echo $b['status'] === 'rejected' ? 'red' : 'green'; ?>"></span>
                                        <?php echo ucfirst($b['status']); ?>
                                    </span>
                                </p>
                                <?php if ($b['status'] === 'pending'): ?>
                                    <div style="margin-top:0.5rem;display:flex;gap:0.5rem;">
                                        <a href="trainer.php?section=bookings&booking=<?php echo (int)$b['id']; ?>&action=accept" class="btn" style="font-size:0.8rem;">Accept</a>
                                        <a href="trainer.php?section=bookings&booking=<?php echo (int)$b['id']; ?>&action=reject" class="btn outline" style="font-size:0.8rem;">Reject</a>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$bookings): ?>
                            <p style="font-size:0.85rem;color:#6b7280;">No booking requests yet.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php elseif ($section === 'performance'):
                $stmt = $pdo->prepare('SELECT DISTINCT u.id, u.name
                    FROM progress pr
                    JOIN training_programs p ON p.id = pr.program_id
                    JOIN users u ON u.id = pr.trainee_id
                    WHERE p.trainer_id = ?');
                $stmt->execute([$trainerId]);
                $trainees = $stmt->fetchAll();
                ?>
                <section>
                    <div class="grid">
                        <?php foreach ($trainees as $tr):
                            $stmt2 = $pdo->prepare('SELECT pr.*, p.title AS program_title
                                FROM progress pr
                                JOIN training_programs p ON p.id = pr.program_id
                                WHERE p.trainer_id = ? AND pr.trainee_id = ?');
                            $stmt2->execute([$trainerId, $tr['id']]);
                            $rows = $stmt2->fetchAll();
                            ?>
                            <article class="card">
                                <div class="card-chip">Trainee</div>
                                <div class="card-title" style="margin-top:0.4rem;"><?php echo e($tr['name']); ?></div>
                                <?php foreach ($rows as $r): ?>
                                    <p class="card-text" style="margin-top:0.3rem;">
                                        <?php echo e($r['program_title']); ?> –
                                        <?php echo (int)$r['completion_percent']; ?>% complete,
                                        Quiz: <?php echo $r['quiz_score'] !== null ? (int)$r['quiz_score'] . '/100' : 'NA'; ?>
                                    </p>
                                    <div class="progress-bar" style="margin-bottom:0.2rem;">
                                        <div class="progress-bar-inner" style="width:<?php echo (int)$r['completion_percent']; ?>%;"></div>
                                    </div>
                                <?php endforeach; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$trainees): ?>
                            <p style="font-size:0.85rem;color:#6b7280;">No trainee progress to display yet.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php elseif ($section === 'certificates'):
                $stmt = $pdo->prepare('SELECT DISTINCT u.id, u.name FROM bookings b JOIN users u ON u.id = b.trainee_id WHERE b.trainer_id = ? AND b.status IN ("paid","completed")');
                $stmt->execute([$trainerId]);
                $trainees = $stmt->fetchAll();
                $stmt = $pdo->prepare('SELECT id, title FROM training_programs WHERE trainer_id = ?');
                $stmt->execute([$trainerId]);
                $programs = $stmt->fetchAll();
                ?>
                <section>
                    <div class="card">
                        <h3 class="card-title">Issue certificate</h3>
                        <form method="post" style="margin-top:0.6rem;">
                            <div class="search-row">
                                <div class="field">
                                    <label>Trainee</label>
                                    <select name="trainee_id" required>
                                        <option value="">Select</option>
                                        <?php foreach ($trainees as $tr): ?>
                                            <option value="<?php echo (int)$tr['id']; ?>"><?php echo e($tr['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Program</label>
                                    <select name="program_id" required>
                                        <option value="">Select</option>
                                        <?php foreach ($programs as $p): ?>
                                            <option value="<?php echo (int)$p['id']; ?>"><?php echo e($p['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="field" style="margin-top:0.4rem;">
                                <label>Certificate file URL</label>
                                <input type="url" name="file_path" required placeholder="https://...pdf">
                            </div>
                            <button type="submit" name="issue_certificate" class="btn" style="margin-top:0.8rem;">Issue certificate</button>
                        </form>
                    </div>
                </section>
            <?php elseif ($section === 'ratings'):
                $stmt = $pdo->prepare('SELECT r.*, u.name AS trainee_name
                    FROM ratings r
                    JOIN users u ON u.id = r.trainee_id
                    WHERE r.trainer_id = ?
                    ORDER BY r.created_at DESC');
                $stmt->execute([$trainerId]);
                $ratings = $stmt->fetchAll();
                $stmt = $pdo->prepare('SELECT SUM(pay.amount) AS total
                    FROM payments pay
                    JOIN bookings b ON b.id = pay.booking_id
                    WHERE b.trainer_id = ? AND pay.status = "paid"');
                $stmt->execute([$trainerId]);
                $total = $stmt->fetchColumn() ?: 0;
                ?>
                <section>
                    <div class="card">
                        <div class="card-title">Payments received</div>
                        <p class="card-text">Total simulated payments: ₹<?php echo (int)$total; ?></p>
                    </div>
                    <div class="grid" style="margin-top:1rem;">
                        <?php foreach ($ratings as $r): ?>
                            <article class="card">
                                <div class="card-chip">Rating</div>
                                <div class="card-title" style="margin-top:0.4rem;"><?php echo e($r['trainee_name']); ?></div>
                                <p class="card-text">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= $r['rating'] ? '★' : '☆'; ?>
                                    <?php endfor; ?>
                                </p>
                                <?php if ($r['review']): ?>
                                    <p class="card-text">“<?php echo e($r['review']); ?>”</p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$ratings): ?>
                            <p style="font-size:0.85rem;color:#6b7280;">No ratings received yet.</p>
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

