# website_projects
A website for a trainee to find their intrested trainers . php code with html embedded
TraineeConnect - Trainer Trainee Management Platform
====================================================

1. Overview
-----------

TraineeConnect is a responsive web platform that connects trainees with
professional trainers. It provides:

- Trainer discovery and booking
- Learning management (videos, study materials, quizzes, tasks)
- Progress tracking and certificates
- Ratings and reviews for trainers
- Full admin control and basic analytics

Tech stack:
- Frontend: HTML (inside PHP files), CSS, JavaScript
- Backend: PHP + Python (for analytics)
- Database: MySQL


2. Project Structure
--------------------

Root folder (this folder):

- config.php
  Database connection, session start and helper functions.

- index.php
  Public landing page:
  - Introduction to the platform
  - Category highlights (Corporate Coaching, Fitness, etc.)
  - Trainer search by category and location
  - Complaints and feedback form
  - Hidden link to Admin login (in the footer)

- trainee.php
  Trainee module:
  - Trainee register and login
  - Browse trainers and programs
  - Booking system with session date, time and duration
  - Attend sessions (videos and study materials)
  - Progress tracking (completion percentage, quiz scores)
  - Download certificates
  - Rate trainers (5 star ratings and reviews)

- trainer.php
  Trainer module:
  - Trainer register and login (only after admin approval)
  - Manage profile (bio, experience, location)
  - Create and manage training programs (category, duration, price, availability)
  - Upload videos and study materials
  - Booking management (accept or reject trainee requests)
  - View trainee performance (progress and quiz scores)
  - Issue certificates for trainees
  - View ratings and total payments received (simulated)

- admin.php
  Admin module:
  - Admin login
  - Approve or reject trainers (trainer approval workflow)
  - Manage trainees and trainers (view / delete)
  - Manage training categories
  - View platform analytics (users, bookings, popular categories)
  - Use Python analytics script for extra metrics (optional)
  - Handle complaints and feedback submitted from homepage
  - Control homepage intro text and general content

- assets\style.css
  Global styles for all pages:
  - Modern blue/green color palette
  - Card based layout and dashboard style interfaces
  - Mobile responsive layout

- assets\app.js
  Client side interactivity:
  - Tab switching (login/register forms)
  - 5 star rating input widget
  - Basic calendar slot click handling

- analytics\analytics.py
  Python script used by admin analytics:
  - Reads summary data from PHP (stdin)
  - Calculates derived metrics and prints JSON

- database.sql
  SQL script to create the required MySQL database and tables.

- readme.txt
  This file.


3. Database Setup
-----------------

Database name used by the project: trainee_platform

To create the database and tables:

1) Open phpMyAdmin or a MySQL client.
2) Import the file:
   database.sql

This will:
- Create the "trainee_platform" database
- Create all required tables:
  users, trainers, categories, training_programs, bookings, payments,
  materials, videos, quizzes, tasks, progress, certificates, ratings,
  feedback, settings


4. Configure Database Connection
--------------------------------

Open config.php and set your MySQL details:

- $dbHost = 'localhost';
- $dbName = 'trainee_platform';
- $dbUser = 'root';
- $dbPass = '';  (change if your MySQL has a password)

Make sure these match your local MySQL setup.


5. Creating an Admin User (Easy Method)
---------------------------------------

There is no default admin user. Use this easy one time method:

1) Create a file in the TRAINEE folder called:
   create_admin.php

2) Put this code inside:

   <?php
   require __DIR__ . '/config.php';

   $email = 'admin@example.com';
   $password = 'admin123';

   $hash = password_hash($password, PASSWORD_DEFAULT);

   $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND role = "admin"');
   $stmt->execute([$email]);
   $existing = $stmt->fetch();

   if ($existing) {
       echo "Admin already exists with email {$email}";
   } else {
       $stmt = $pdo->prepare(
           'INSERT INTO users (name, email, password_hash, role, location, created_at)
            VALUES (?, ?, ?, "admin", ?, NOW())'
       );
       $stmt->execute(['Admin', $email, $hash, 'Head Office']);
       echo "Admin created. Email: {$email} | Password: {$password}";
   }

3) Run your server and open this in the browser:

   http://localhost/TRAINEE/create_admin.php

4) After you see the success message, delete the file:

   create_admin.php

Admin login credentials:
- Email:    admin@example.com
- Password: admin123


6. Running the Application
--------------------------

Option A: XAMPP / WAMP
----------------------

1) Copy the TRAINEE folder to your web root:
   - XAMPP: C:\xampp\htdocs\TRAINEE
   - WAMP:  C:\wamp64\www\TRAINEE

2) Start Apache and MySQL from your control panel.

3) In your browser, open:
   http://localhost/TRAINEE/index.php


Option B: PHP Built-in Server
-----------------------------

1) Open a terminal in the TRAINEE folder.

2) Run:
   php -S localhost:8000

3) In your browser, open:
   http://localhost:8000/index.php


7. Main User Flows
------------------

Public (index.php)
------------------
- View introduction and categories.
- Search trainers by category and location.
- Submit complaints and feedback (stored in "feedback" table).
- Access admin login via hidden footer link.

Trainee (trainee.php)
---------------------
- Register and login as trainee.
- Browse trainers and their programs.
- Request bookings with date, time and duration.
- After trainer accepts, proceed to simulated payment.
- Attend sessions:
  - Watch videos and view study materials.
  - Mark lessons complete to update progress.
  - Attempt quizzes (time window controlled by admin/trainer data).
- Track progress with charts and percentages.
- Download certificates issued by trainers.
- Rate trainers with 5 star system and review text.

Trainer (trainer.php)
---------------------
- Register as trainer (goes to "pending" state).
- Login only after admin approval.
- Complete profile (bio, experience, location).
- Create and manage training programs (category, price, duration).
- Upload videos and study materials.
- View trainee booking requests and accept or reject them.
- Monitor trainee performance (progress and quiz scores).
- Issue certificates with download links.
- View ratings and total payments received (simulated).

Admin (admin.php)
-----------------
- Login using an admin account from the "users" table.
- Approve or reject new trainers.
- View, edit or delete trainees and trainers.
- Manage global training categories.
- View analytics:
  - Number of users and trainers
  - Number of bookings
  - Popular categories
  - Python based analytics summary (if Python is installed)
- View and manage complaints and feedback (new / handled).
- Update homepage intro text and basic platform content.


8. Notes
--------

- There are no separate .html files. All pages are .php files containing HTML.
- Styles are in assets\style.css and JavaScript in assets\app.js.
- Payments are simulated; you can integrate a real payment gateway later.
- Python (analytics\analytics.py) is optional; the platform still runs without it.

