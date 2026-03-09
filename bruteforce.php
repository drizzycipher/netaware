<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// simple in-memory log stored in session
if (!isset($_SESSION['bf_logs'])) $_SESSION['bf_logs'] = [];
if (!isset($_SESSION['locked_until'])) $_SESSION['locked_until'] = 0;

// Handle AJAX calls
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'submit_login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $_SESSION['bf_logs'] = [];
        $_SESSION['locked_until'] = 0;
        $_SESSION['target_pw_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $_SESSION['fake_user'] = ['username' => $username ?: 'demo_user'];
        echo json_encode(['ok' => true, 'msg' => 'Simulation started']);
        exit;
    }

    if ($action === 'attacker_guess') {
        $guess = $_POST['guess'] ?? '';
        $ip = $_POST['ip'] ?? '0.0.0.0';
        $time = time();
        $matched = false;
        $locked = false;

        if ($time < intval($_SESSION['locked_until'])) {
            $locked = true;
            $status = 'locked';
        } else {
            $storedHash = $_SESSION['target_pw_hash'] ?? '';
            if (!empty($storedHash) && password_verify($guess, $storedHash)) {
                $matched = true;
                $status = 'success';
            } else {
                $status = 'fail';
            }

            $_SESSION['bf_logs'][] = [
                'time' => $time,
                'guess' => $guess,
                'status' => $status
            ];

            $fails = 0;
            foreach ($_SESSION['bf_logs'] as $r) if ($r['status'] === 'fail') $fails++;
            $lockThreshold = 200;
            if ($fails >= $lockThreshold) {
                $_SESSION['locked_until'] = $time + 60;
                $locked = true;
            }
        }

        echo json_encode([
            'matched' => $matched,
            'locked' => $locked,
            'status' => $status,
            'time' => $time
        ]);
        exit;
    }

    if ($action === 'fetch_logs') {
        $logs = array_map(function($r){ return [
            't' => date('H:i:s', $r['time']),
            'g' => strlen($r['guess']) > 8 ? substr($r['guess'],0,4).'...' : $r['guess'],
            's' => $r['status']
        ]; }, $_SESSION['bf_logs']);
        $lockedUntil = intval($_SESSION['locked_until']);
        echo json_encode(['logs' => $logs, 'locked_until' => $lockedUntil]);
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" href="photos/headerlogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>NetAware — Brute Force Simulation</title>
  <style>
    :root{
      --deep-navy:#1c2430;
      --teal:#00c7d4;
      --soft-cyan:#e9fbff;
      --pale:#f6fbfc;
      --muted:#375a6a;
      --card:#ffffff;
      --accent-2:#90d5ff;
      --danger:#dc3545;
      --success:#28a745;
      font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    }
    .custom-dashboard-btn {
  background-color: #03cae4 !important; /* teal accent color */
  color: white !important;
  border: none !important;
  border-radius: 8px !important;
  padding: 8px 16px !important;
  font-weight: 600 !important;
  transition: background 0.3s;
}

.custom-dashboard-btn:hover {
  background-color: #029bb3 !important; /* darker on hover */
}

    /* Page background and top header area */
    html,body{
      margin:0;
      background:linear-gradient(180deg,var(--pale), #eef9ff);
      color:var(--muted);
    }

    /* Sticky header at top (restored) */
    .header{
      background:var(--deep-navy);
      border-bottom:4px solid var(--teal);
      padding:12px 24px;
      display:flex;
      align-items:center;
      gap:12px;
      color:#fff;
      box-shadow:0 6px 20px rgba(0,0,0,0.06);
      position:sticky;
      top:0;
      z-index:999;
    }
  .logo-circle { width:50px; height:50px; border-radius:50%; background:#03cae4; display:flex; align-items:center; justify-content:center; }
  .logo-circle img { width:28px; height:28px; object-fit:contain; }    
  .brand{font-size:1.4rem; font-weight:700; color:#fff;}

    /* Main content area centered below header */
    .app-wrap{
      width:100%;
      max-width:1100px;
      margin:28px auto;
      padding:18px;
      box-sizing:border-box;
    }

    .page-wrap{
      display:grid;
      grid-template-columns:1fr; 
      gap:20px;
      background:var(--card);
      border-radius:12px;
      padding:20px;
      box-shadow:0 12px 30px rgba(3,202,228,0.06);
    }
    .login-panel {
      border: 1px solid rgba(3,202,228,0.12);
      background: linear-gradient(180deg,#ffffff, #fbfeff);
      padding: 16px;
      border-radius: 12px;
      box-shadow: 0 8px 30px rgba(3,202,228,0.06);
      max-width: 700px !important;
      margin: 0 auto;
      position: relative;
      transition: box-shadow 180ms ease, transform 180ms ease;
      }
    .login-panel:focus-within {
      box-shadow: 0 14px 40px rgba(3,202,228,0.12);
      transform: translateY(-2px);
    }
    .login-panel .panel-title {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
      font-weight:700;
      color:var(--deep-navy);
    }
    .login-panel .panel-sub {
      font-size:13px;
      color:var(--muted);
      margin-bottom:8px;
    }
    .center-col{display:flex;flex-direction:column;gap:18px}
    .card{background:var(--card);border-radius:12px;padding:18px}
    .story{border-left:6px solid var(--teal);background:var(--soft-cyan);padding:16px;border-radius:8px}
    h3{margin:0;color:var(--teal)}
    .login-box{max-width:520px;margin:0 auto}
    label{display:block;font-size:13px;margin-bottom:6px;color:var(--muted)}
    input[type="text"], input[type="password"], input[type="email"], textarea{
      width:100%;padding:10px 12px;border-radius:8px;border:1px solid rgba(0,0,0,0.06);box-sizing:border-box;font-size:14px;
    }
    .btn-accent { background: #03cae4; color: #fff;; border:none; border-radius:8px; padding:8px 14px; transition:0.2s; cursor:pointer; }
    .btn-accent:hover { background: #02b4ca; }
    .btn-accent[disabled] { opacity: 0.45; cursor: not-allowed; }
    .btn-outline{background:transparent;border:1px solid rgba(0,0,0,0.08);padding:8px 12px;border-radius:10px;cursor:pointer;}
    .stream{height:220px;overflow:auto;padding:8px;border-radius:8px;background:#fbfeff;border:1px solid rgba(0,0,0,0.03)}
    .stream-item{padding:8px;border-bottom:1px dashed rgba(0,0,0,0.04);display:flex;justify-content:space-between;gap:8px;align-items:center}
    .stream-item.stream-cracked {
      background: rgba(220,53,69,0.08);
      border-left: 4px solid rgba(220,53,69,0.2);
      animation: pulseCracked 0.4s ease-in-out;
    }

    @keyframes pulseCracked {
      0% { transform: scale(1); }
      50% { transform: scale(1.015); }
      100% { transform: scale(1); }
    }
      #simButtons {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 12px;
        width: 100%;
      }
    #simButtons button {
      background-color: #03cae4;
      color: white;
      font-weight: 600;
      padding: 10px 18px;
      border-radius: 6px;
      border: none;
      transition: background 0.3s ease;
      min-width: 160px;          /* ensures same width for all three buttons */
      text-align: center;
    }
    #simButtons button:hover {
      background-color: #02b3cb;
    }
    .panel-col{display:flex;flex-direction:column;gap:12px}
    .small{font-size:13px;color:var(--muted)}
    .stat-grid{display:flex;gap:10px;margin-top:8px}
    .stat{flex:1;background:linear-gradient(180deg,#f7fcff,#fff);padding:10px;border-radius:8px;text-align:center;border:1px solid rgba(0,0,0,0.03)}
    .stat strong{display:block;font-size:1.2rem;color:#07364a}
    .panel-card{background:#fff;border-radius:10px;padding:12px;box-shadow:0 6px 18px rgba(3,202,228,0.04)}
    .feedback{margin-top:6px;font-size:13px;color:var(--muted);background:#fff;padding:8px;border-radius:8px;border:1px dashed rgba(0,0,0,0.04)}
    .notif {
      display: flex;
      gap: 10px;
      padding: 12px 15px;
      border-radius: 6px;
      margin-top: 10px;
      font-size: 14px;
      align-items: flex-start;
    }

    .notif-icon {
      font-size: 18px;
      line-height: 1;
    }

    .notif-success {
      background: #e8f9f0;
      border: 1px solid #b2e5c7;
      color: #1b5e20;
    }

    .notif-info {
      background: #eaf3ff;
      border: 1px solid #bcd9ff;
      color: #134b8a;
    }

    .notif-danger {
      background: #fdecea;
      border: 1px solid #f5c6cb;
      color: #7d1b1b;
    }

    /* progress (copied style adapted) */
    #progressContainer { display:block; margin-bottom:12px; text-align:left; }
    .progress-text { font-size:0.95rem; font-weight:700; margin-bottom:8px; text-align: center; color:var(--muted) }
    .progress { height:12px; background:#eef9fb; border-radius:999px; overflow:hidden; border:1px solid rgba(0,0,0,0.03); }
    .progress > .progress-fill { height:100%; width:0%; background: linear-gradient(90deg,var(--teal),var(--accent-2)); transition: width .35s ease; display:flex; align-items:center; justify-content:center; font-size:12px; color:#fff; font-weight:700; }

    /* password toggle */
    .pw-wrap{position:relative;}
    .pw-toggle{position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#888; user-select:none;}
    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear {
      display: none;
    }

    input[type="password"]::-webkit-password-toggle-button,
    input[type="password"]::-webkit-inner-spin-button,
    input[type="password"]::-webkit-contacts-auto-fill-button {
      display: none;
    }

    input[type="password"]::-webkit-textfield-decoration-container {
      display: none;
    }

    /* modal */
    #quizBackdrop .modal-body { max-height:67vh; overflow:auto; }
    .quiz-question { margin-bottom:20px; }
    .quiz-question b { display:block; margin-bottom:6px; }
    .quiz-options label { display:block; margin:4px 0; cursor:pointer; }
    #quizBackdrop { display:none; align-items:center; justify-content:center; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:2000; }
    .na-modal.quiz { max-width:730px; width:94%; background:#fff; border-radius:10px; padding:16px 28px; box-shadow:0 20px 60px rgba(3,202,228,0.12); color:var(--muted); }
    .quiz-footer { text-align:right; margin-top:6px; }
    .swal2-container {
    z-index: 20000 !important;
    }

    @media(max-width:1000px){
      .page-wrap{grid-template-columns:1fr;}.panel-col{width:100%}
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes fadeOut {
      from { opacity: 1; }
      to { opacity: 0; }
    }
    @keyframes popIn {
      from { transform: scale(0.95); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
  </style>
</head>
<body>
  <!-- HEADER (restored & sticky) -->
  <div class="header">
    <div class="logo-circle"><img src="netaware-logo.png" alt="Logo"></div>
    <div>
      <div class="brand">NetAware Brute Force</div>
    </div>
  </div>
  <!-- Main centered app area -->
  <div class="app-wrap">
    <div class="page-wrap">
      <div class="center-col">
        <!-- Story & instructions -->
        <div class="card">
          <div class="story">
            <h3>📘 Story</h3>
            <p style="margin:8px 0 0;color:var(--muted)">You're testing how attackers try to guess passwords. Start with a weak password to see a breach simulation, then sign up with a strong one to see how much harder cracking becomes. This is a local demo, no real accounts used.</p>
          </div>
          <hr style="border:none;border-top:1px solid rgba(0,0,0,0.04);margin:14px 0">
          <h3 style="margin:0 0 8px 0">Simulation Instructions</h3>
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px">
            <div style="flex:1;min-width:240px" class="panel-card">
              <strong>How to begin</strong>
              <ol style="padding-left:18px;margin:8px 0 0;color:var(--muted)">
                <li>Type a password below and click <em>Run simulation</em>.</li>
                <li>Type a <strong>weak</strong> password first with a maximum of <strong>12 characters</strong> (e.g. <code>password123 or 1234</code>), the demo will simulate an attacker guessing it quickly.</li>
                <li>When the weak password is cracked, the demo advances to the sign-up step. Create a <strong>strong</strong> password and run again to see how much harder it is to crack.</li>
              </ol>
            </div>

            <div style="flex:1;min-width:240px" class="panel-card">
              <strong>Friendly tips</strong>
              <ul style="margin:8px 0 0;padding-left:18px;color:var(--muted)">
                <li>For a stronger password, choose a passphrase (3+ unrelated words) or a long mixed password.</li>
                <li>Use unique passwords per site + multi-factor authentication (MFA).</li>
                <li>Store complex passwords in a password manager.</li>
              </ul>
            </div>
          </div>

          <div id="explainContainer" style="margin-top:14px; border-left:6px solid var(--teal); background:var(--soft-cyan); padding:14px; border-radius:8px;">
            <strong style="color:var(--teal)">ℹ️ Explanation</strong>
            <p id="explainText" class="small" style="margin:6px 0 0; color:var(--muted);">
              Start by entering an intentionally weak password (e.g. '1234') and click Run simulation. An 'attacker' will try to brute-force it within <strong>200 attempts</strong>. After the password is cracked, the demo advances to the next step where you'll create a strong password."
            </p>
          </div>
        </div>
        <!-- Main simulation card -->
        <div class="card">
          <!-- Progress bar (0/2 -> 1/2 -> 2/2) -->
          <div id="progressContainer">
            <div class="progress-text" id="progressText">Progress: 0 / 2</div>
            <div class="progress">
            <div id="progressFill" class="progress-fill" style="width:0%;"></div>
            </div>
              <div id="simButtons" style="display:flex; gap:10px; justify-content:center; margin-top:10px;">
                  <button type="button" id="resetBtn" class="btn-accent" disabled>Reset Stage</button>

                  <button id="nextStepBtn" class="btn-accent" style="display:none;">Next Step</button>
                  <button id="startQuizBtn" class="btn-accent" style="display:none;">Start Quiz</button>

                  <button type="button" id="resetSimBtn" class="btn-accent" disabled>Reset Simulation</button>
                </div>
              </div>
          </div>
          </div>
          <div class="login-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
              
            </div>
            <div class="login-panel">
              <div class="panel-title">
              </div>
              <form id="loginForm" aria-label="Simulation login">
                <div style="margin-bottom:10px">
                  <label class="small">Email or username</label>
                  <input id="username" type="text" value="demo.user@example.com" required>
                </div>
                <div style="margin-bottom:8px">
                  <label class="small">
                    Password
                    <span style="text-decoration:underline dotted;cursor:help" title="Enter a password to see brute force behavior">what is this?</span>
                  </label>
                  <div class="pw-wrap" style="max-width:520px;">
                    <input id="password" type="password" placeholder="e.g. 1234" required style="padding-right:44px;">
                    <span id="togglePw" class="pw-toggle">👁️</span>
                  </div>
                  <div id="pwFeedback" class="feedback" style="color:var(--danger)"></div>
                </div>

                <div style="display:flex;gap:10px;margin-top:8px;">
                  <button type="submit" id="runSimBtn" class="btn-accent">Run simulation</button>
                </div>
              </form>
            </div>
            <div id="report" style="margin-top:12px"></div>
          </div>
          <div style="margin-top:14px" class="panel-card">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div><strong>Attacker stream</strong></div>
              <div class="small">Hover items for details</div>
            </div>
            <div id="stream" class="stream" aria-live="polite" style="margin-top:8px"></div>
            <div class="stat-grid" style="margin-top:12px">
              <div class="stat">
                <small>Elapsed</small>
                <strong id="elapsed">0s</strong>
              </div>
              <div class="stat">
                <small>Attempts / sec</small>
                <strong id="rate">0</strong>
              </div>
              <div class="stat">
                <small>Attempts</small>
                <strong id="attemptCount">0</strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<!-- ======= Success Modal ======= -->
<div id="successBackdrop" class="na-modal-backdrop" role="dialog" aria-modal="true" style="display:none;">
  <div class="na-modal" role="document" aria-labelledby="successTitle">
    <div>
      <h4 id="successTitle" style="color:var(--teal)">Simulation successful</h4>
    </div>
    <div class="modal-body">
      <p class="small">Great! The strong password you created was not cracked within the demo limits. This demonstrates the effectiveness of a long, mixed password.</p>
      <p class="small">Would you like to try a short quiz to test what you learned about password strength?</p>
    </div>
    <div style="text-align:right">
      <button id="startQuizBtn" class="btn-accent" style="display:none;">Start Quiz</button>
      <button id="closeSuccessBtn" class="btn-outline">Later</button>
    </div>
  </div>
</div>
<!-- ======= Quiz Modal ======= -->
<div class="modal fade" id="quizBackdrop" tabindex="-1" aria-modal="true" style="display:none;">
  <div class="na-modal quiz" role="document" aria-labelledby="quizTitle">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;margin-top:12px;">
      <h3 id="quizTitle" style="color:var(--teal); margin:0">🧠 Quick Quiz: Brute Force & Passwords</h3>
    </div>
    <div class="modal-body" id="quizBody">
      <hr>
      <!-- 1 -->
      <div class="quiz-question" data-q="1">
        <b>1. Scenario:</b>
        A login system shows multiple failed attempts coming from the same IP in rapid succession.  
        The system temporarily locks the account for 5 minutes. What defense technique is being used?<br>
        <img src="photos/accountlocked.png" alt="Account temporarily locked after repeated failed login attempts;" style="width:250px; height:auto;" class="my-2"><br>
        <h6 style="margin-top: 5px;margin-bottom: 15px; font-size: 12px"><i>Source: OpenAI. (2025). Image generated using ChatGPT based on user prompt.</i></h6>
        <div class="quiz-options">
          <label><input type="radio" name="q1" value="a"> Salting</label>
          <label><input type="radio" name="q1" value="b"> Hashing</label>
          <label><input type="radio" name="q1" value="c"> Data encryption</label>
          <label><input type="radio" name="q1" value="d"> Rate limiting or account lockout</label>
        </div>
      </div>
      <hr>
      <!-- 2 -->
      <div class="quiz-question" data-q="2">
        <b>2. Recall:</b>
        A brute-force attack tries _____ until it finds the correct password.<br>
        <div class="quiz-options">
          <label><input type="radio" name="q2" value="a"> only dictionary words</label>
          <label><input type="radio" name="q2" value="b"> every possible combination</label>
          <label><input type="radio" name="q2" value="c"> a single password repeatedly</label>
          <label><input type="radio" name="q2" value="d"> stolen passwords from other sites</label>
        </div>
      </div>
      <hr>
      <!-- 3 -->
      <div class="quiz-question" data-q="3">
        <b>3. Scenario:</b>
        In the simulation, short passwords like "1234" were cracked quickly.  
        What made the strong password phase harder for the simulated attacker?<br>
        <div class="quiz-options">
          <label><input type="radio" name="q3" value="a"> The simulation skipped attack attempts</label>
          <label><input type="radio" name="q3" value="b"> The attacker slowed down intentionally</label>
          <label><input type="radio" name="q3" value="c"> The password length and variety increased</label>
          <label><input type="radio" name="q3" value="d"> The password used only uppercase letters</label>
        </div>
      </div>
      <hr>
      <!-- 4 -->
      <div class="quiz-question" data-q="4">
        <b>4. Scenario:</b>
        A user created the password “Fluffy2025”. It mixes letters and numbers but still got marked weak.  
        Why is it unsafe?<br>
        <img src="photos/fluffy2025.png" alt="Password strength meter showing weak rating for Fluffy2025" style="width:250px; height:auto;" class="my-2"><br>
        <h6 style="margin-top: 5px;margin-bottom: 15px; font-size: 12px"><i>Source: OpenAI. (2025). Image generated using ChatGPT based on user prompt.</i></h6>
        <div class="quiz-options">
          <label><input type="radio" name="q4" value="a"> It is too long for most systems</label>
          <label><input type="radio" name="q4" value="b"> It uses forbidden characters</label>
          <label><input type="radio" name="q4" value="c"> It includes too many numbers</label>
          <label><input type="radio" name="q4" value="d"> It is based on a predictable word or name</label>
        </div>
      </div>
      <hr>
      <!-- 5 -->
      <div class="quiz-question" data-q="5">
        <b>5. Recall:</b>
        In the simulation, the number that increased as the attacker kept guessing was the _____.<br>
        <div class="quiz-options">
          <label><input type="radio" name="q5" value="a"> attempt count</label>
          <label><input type="radio" name="q5" value="b"> password length</label>
          <label><input type="radio" name="q5" value="c"> attack timer</label>
          <label><input type="radio" name="q5" value="d"> security level</label>
        </div>
      </div>
      <hr>
      <!-- 6 -->
      <div class="quiz-question" data-q="6">
        <b>6. Recall:</b>
        During the simulation, what happens when the system detects too many failed attempts?<br>
        <div class="quiz-options">
          <label><input type="radio" name="q6" value="a"> The user’s password is revealed</label>
          <label><input type="radio" name="q6" value="b"> The system enforces a cooldown or lockout</label>
          <label><input type="radio" name="q6" value="c"> The system deletes the user account</label>
          <label><input type="radio" name="q6" value="d"> The attacker automatically succeeds</label>
        </div>
      </div>
      <hr>
      <!-- 7 -->
      <div class="quiz-question" data-q="7">
        <b>7. Scenario:</b>
        A hacker uses a list of 10 million common passwords to guess user credentials quickly.  
        What type of attack is this?<br>
        <img src="photos/hackerman.png" alt="Hacker running a list of common passwords against login form" style="width:250px; height:auto;" class="my-2"><br>
        <h6 style="margin-top: 5px;margin-bottom: 15px; font-size: 12px"><i>Source: OpenAI. (2025). Image generated using ChatGPT based on user prompt.</i></h6>
        <div class="quiz-options">
          <label><input type="radio" name="q7" value="a"> Dictionary attack</label>
          <label><input type="radio" name="q7" value="b"> Brute-force attack (trying every combination)</label>
          <label><input type="radio" name="q7" value="c"> Phishing (tricking users into giving credentials)</label>
          <label><input type="radio" name="q7" value="d"> Keylogger malware (recording keystrokes)</label>
        </div>
      </div>
      <hr>
      <!-- 8 -->
      <div class="quiz-question" data-q="8">
        <b>8. Recall:</b>
        To make passwords harder to brute-force, users should increase both _____ and _____.<br>
        <div class="quiz-options">
          <label><input type="radio" name="q8" value="a"> color, font</label>
          <label><input type="radio" name="q8" value="b"> visibility, frequency</label>
          <label><input type="radio" name="q8" value="c"> length, complexity</label>
          <label><input type="radio" name="q8" value="d"> reuse, similarity</label>
        </div>
      </div>
      <hr>
      <!-- 9 -->
      <div class="quiz-question" data-q="9">
        <b>9. Recall:</b>
        What does the “attempt count” indicator in the simulation represent?<br>
        <div class="quiz-options">
          <label><input type="radio" name="q9" value="a"> The number of users online</label>
          <label><input type="radio" name="q9" value="b"> The number of correct passwords entered</label>
          <label><input type="radio" name="q9" value="c"> The number of guesses tried by the simulated attacker</label>
          <label><input type="radio" name="q9" value="d"> The number of saved accounts</label>
        </div>
      </div>
      <hr>
      <!-- 10 -->
      <div class="quiz-question" data-q="10">
        <b>10. Recall:</b>
        What is the best practice after learning about brute-force risks?<br>
        <div class="quiz-options">
          <label><input type="radio" name="q10" value="a"> Share passwords with trusted friends</label>
          <label><input type="radio" name="q10" value="b"> Use long, unique passwords and enable MFA</label>
          <label><input type="radio" name="q10" value="c"> Disable password requirements</label>
          <label><input type="radio" name="q10" value="d"> Use short passwords for convenience</label>
        </div>
      </div>

      <div id="quizResult" class="small" style="margin-top:8px; display:none;"></div>
    </div>

    <div class="quiz-footer">
      <button id="closeQuizBtn" class="btn-outline" style="margin-right:8px">Close</button>
      <button id="submitQuizBtn" class="btn-accent">Submit Quiz</button>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="attacker_gen.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", () => {
  const DASH_KEY = 'learning_module_state';
  const moduleKey = 'bruteforce'; // ✅ make sure this matches your dashboard key
  const progressData = JSON.parse(localStorage.getItem(DASH_KEY) || "{}");

  // ✅ Check if the module exists in localStorage
  const module = progressData[moduleKey];

  // ✅ If module data doesn’t exist or lessons are incomplete
  if (!module || !module.completed || module.completed.length < 3) {
    Swal.fire({
      icon: 'warning',
      title: '⚠️ Access Denied',
      html: `
        <p>You need to complete all lessons first before trying the simulation.</p>
        <p><b>Required:</b> Lesson 1, Lesson 2, and Lesson 3</p>
      `,
      confirmButtonText: 'Go Back to Dashboard',
      customClass: {
        confirmButton: 'custom-dashboard-btn'
      }
    }).then(() => {
      window.location.href = 'dashboard.html';
    });
  }
});
  // Persistence for brute force simulation
const BF_SIM_STATE_KEY = 'bf_sim_state_v1';
function saveSimState(state) {
  try { localStorage.setItem(BF_SIM_STATE_KEY, JSON.stringify(state)); }
  catch(e) { console.warn('Could not save sim state', e); }
}
function loadSimState() {
  try { const raw = localStorage.getItem(BF_SIM_STATE_KEY); return raw ? JSON.parse(raw) : null; }
  catch(e) { return null; }
}

function getCrackTimeString(password) {
  if (typeof estimateEntropyCrackTime !== 'function') return '';
  const est = estimateEntropyCrackTime(password);

  // Prefer seconds if available
  let secs = (typeof est.timeSeconds === 'number')
             ? est.timeSeconds
             : (typeof est.timeYears === 'number'
                ? est.timeYears * 60 * 60 * 24 * 365
                : NaN);

  if (!isFinite(secs)) return '5+ years';
  if (secs < 1) return `${(secs * 1000).toFixed(0)} ms`;
  if (secs < 60) return `${secs.toFixed(2)} seconds`;
  if (secs < 3600) return `${(secs / 60).toFixed(2)} minutes`;
  if (secs < 86400) return `${(secs / 3600).toFixed(2)} hours`;
  if (secs < 86400 * 31) return `${(secs / 86400).toFixed(2)} days`;
  if (secs < 86400 * 365) return `${(secs / (86400 * 30)).toFixed(2)} months`;

  // convert to years
  const years = secs / (60 * 60 * 24 * 365);

  // ★ NEW LOGIC: if the password would take 5 years or more → return "5+ years"
  if (years >= 5) return "5+ years";

  // otherwise show exact years
  return `${years.toFixed(2)} years`;
}

/* ---------------------------
   Keep original JS logic
   only update minor UI hooks
   --------------------------- */
const loginForm = document.getElementById('loginForm');
const stream = document.getElementById('stream');
const elapsedEl = document.getElementById('elapsed');
const rateEl = document.getElementById('rate');
const attemptsEl = document.getElementById('attemptCount');
const report = document.getElementById('report');
const pwInput = document.getElementById('password');
const pwFeedback = document.getElementById('pwFeedback');
const resetBtn = document.getElementById('resetBtn');
const resetSimBtn = document.getElementById('resetSimBtn');
const explainText = document.getElementById('explainText');
const progressFill = document.getElementById('progressFill');
const progressText = document.getElementById('progressText');

let running = false;
let attemptCount = 0;
// phase: 'weak' -> initial weak demo; 'strong' -> after weak cracked and sign-up
let phase = 'weak';
const phaseMaxLen = { weak: 12, strong: 16 };
pwInput.maxLength = phaseMaxLen[phase];

// progressStage: 0,1,2 corresponds to 0/2,1/2,2/2
let progressStage = 0;
function updateProgressUI(){
  const total = 2;
  const cur = progressStage;
  const pct = Math.round((cur / total) * 100);
  progressFill.style.width = pct + '%';
  progressText.textContent = `Progress: ${cur} / ${total}`;
}

function showToast(message, icon = "info") {
  Swal.fire({
    toast: true,
    position: "top-end",
    icon: icon,
    title: message,
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true
  });
}
// Initialize progress UI
updateProgressUI();
/* password visibility toggle */
document.getElementById('togglePw').addEventListener('click', function() {
  const pw = pwInput;
  if (pw.type === 'password') {
    pw.type = 'text';
    this.textContent = '🙈';
  } else {
    pw.type = 'password';
    this.textContent = '👁️';
  }
});
/* small input helper */
  pwInput.addEventListener('input', () => {
    const val = pwInput.value.trim();
    if (!val) {
      pwFeedback.textContent = "⚠️ Password cannot be blank.";
      pwFeedback.style.color = 'var(--danger)';
      return;
    }

    // Weak phase feedback logic
    if (phase === 'weak') {
        // Check for symbols
        const symbolPattern = /[!@#$%^&*(),.?":{}|<>]/;
        if (symbolPattern.test(val)) {
            pwFeedback.textContent = "⚠️ Weak passwords should not contain a combination of symbols, numbers, and capital letters.";
            pwFeedback.style.color = 'var(--warning)';
        } 
        // Check length
        else if (val.length > phaseMaxLen.weak) {
            pwFeedback.textContent = `⚠️ Too long for weak demo. Max is ${phaseMaxLen.weak}.`;
            pwFeedback.style.color = 'var(--danger)';
        } 
        // Check common weak passwords
        else if (window.attackerGen && attackerGen.weakPasswords && attackerGen.weakPasswords.includes(val.toLowerCase())) {
            pwFeedback.textContent = "ℹ Attackers try this password first in weak demo.";
            pwFeedback.style.color = 'var(--warning)';
        } 
        // Valid weak password
        else {
            pwFeedback.textContent = "✅ Looks good for weak demo.";
            pwFeedback.style.color = 'var(--success)';
        }
        return;
    }

    // Strong phase feedback logic (improved)
    if (window.attackerGen && attackerGen.weakPasswords && attackerGen.weakPasswords.includes(val.toLowerCase())) {
      pwFeedback.textContent = "⚠️ This is a very common password!";
      pwFeedback.style.color = 'var(--danger)';
    } else if (val.length < 8) {
      pwFeedback.textContent = "⚠️ Too short, try at least 8 characters.";
      pwFeedback.style.color = 'var(--danger)';
    } else {
      const hasUpper = /[A-Z]/.test(val);
      const hasLower = /[a-z]/.test(val);
      const hasNumber = /\d/.test(val);
      const hasSymbol = /[^A-Za-z0-9]/.test(val);

      if (!hasSymbol) {
        pwFeedback.textContent = "ℹ Add a symbol (!, @, #, $, etc.) for stronger security.";
        pwFeedback.style.color = 'var(--warning)';
      } else if (!hasNumber) {
        pwFeedback.textContent = "ℹ Add a number for stronger security.";
        pwFeedback.style.color = 'var(--warning)';
      } else if (!hasUpper || !hasLower) {
        pwFeedback.textContent = "ℹ Mix uppercase and lowercase letters for strength.";
        pwFeedback.style.color = 'var(--warning)';
      } else {
        pwFeedback.textContent = "Looks good.";
        pwFeedback.style.color = 'var(--success)';
      }
    }
  });
function addStream(line, note = '', attempt = '') {
  const d = document.createElement('div');
  d.className = 'stream-item';
  d.setAttribute('aria-live', 'polite');
  if (attempt) d.setAttribute('data-attempt', String(attempt));
  if (line) d.setAttribute('data-guess', String(line));
  d.title = note;
  d.innerHTML = `<div style="color:#066;font-weight:600">${escapeHtml(line)}</div><div class="small">${escapeHtml(note)}</div>`;
  stream.prepend(d);
  while (stream.children.length > 80) stream.removeChild(stream.lastChild);
  return d;
}

function markCracked(attempt, guess) {
  const el = stream.querySelector(`[data-attempt="${attempt}"]`);
  if (el) {
    el.classList.add('stream-cracked');
    el.setAttribute('aria-live', 'assertive');
  } else {
    const nodes = Array.from(stream.querySelectorAll('.stream-item'));
    const match = nodes.find(n => (n.getAttribute('data-guess') || '') === String(guess));
    if (match) {
      match.classList.add('stream-cracked');
      match.setAttribute('aria-live', 'assertive');
    }
  }
}
function addLiveLog(line) {
  // live log removed — kept as no-op to avoid runtime errors during simulation
  console.debug('liveLog (removed):', line);
}

async function postJSON(data) {
  const res = await fetch(location.href, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams(data)
  });
  return res.json();
}
function escapeHtml(string) {
  if(!string) return '';
  return String(string)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/* --------------- Main simulation logic --------------- */
loginForm.addEventListener('submit', async function(e){
  e.preventDefault();
  
  const val = pwInput.value.trim();

  // Strong password enforcement
  if (phase === 'strong') {
    if (val.length < 8) {
      Swal.fire({
        icon: 'warning',
        title: 'Too Short',
        text: 'Strong passwords should be 8 or more characters.',
        confirmButtonText: 'OK',
    confirmButtonColor: '#03cae4'
      });
      return;
    }
    const hasUpper = /[A-Z]/.test(val);
    const hasLower = /[a-z]/.test(val);
    const hasNumber = /\d/.test(val);
    const hasSymbol = /[^A-Za-z0-9]/.test(val);

    let missing = [];
    if (!hasSymbol) missing.push('a symbol');
    if (!hasNumber) missing.push('a number');
    if (!hasUpper || !hasLower) missing.push('both uppercase and lowercase letters');

    if (missing.length) {
      Swal.fire({
        icon: 'warning',
        title: 'Password Not Strong Enough',
        html: `Please include ${missing.join(', ')} in your password before running the simulation.`
      });
      return;
    }
  }
  if (running) return;
  if (!val) {
    Swal.fire({
      icon: 'warning',
      title: 'Password Required',
      text: 'Please enter a password first.'
    });
    return;
  }
      // Prevent re-running completed weak or strong phases
  if (phase === 'weak' && progressStage === 1) {
    await Swal.fire({
      title: 'Weak phase completed',
      html: 'You already finished the weak password phase.<br>Proceed to the next step to continue.',
      icon: 'info',
      confirmButtonText: 'Next Step',
      cancelButtonText: 'Later',
      showCancelButton: true,
      allowOutsideClick: false
    }).then(async (result) => {
      if (result.isConfirmed) {
        const nextBtn = document.getElementById('nextStepBtn');
        if (nextBtn) nextBtn.click();
      }
    });
    return;
  }

  if (phase === 'strong' && progressStage === 2) {
    await Swal.fire({
      title: 'Simulation completed',
      html: 'You already completed this simulation.<br>Start the quiz to test your knowledge.',
      icon: 'info',
      confirmButtonText: 'Start Quiz',
      cancelButtonText: 'Later',
      showCancelButton: true,
      allowOutsideClick: false
    }).then((result) => {
      if (result.isConfirmed) {
        const quizBtn = document.getElementById('startQuizBtn');
        if (quizBtn) quizBtn.click();
      }
    });
    return;
  }

  if (phase === 'weak' && val.length > phaseMaxLen.weak) {
    Swal.fire({
      icon: 'info',
      title: 'Too Long',
       confirmButtonText: 'OK',
    confirmButtonColor: '#03cae4', // custom button color
      text: `Weak demo only allows up to ${phaseMaxLen.weak} characters. Shorten your password or proceed to next phase.`
    });
    return;
  }
  if (phase === 'strong' && val.length <= 7) {
    Swal.fire({
      icon: 'warning',
      title: 'Too Short',
      text: 'Strong passwords should be 8 or more characters.',
      confirmButtonText: 'OK',
    confirmButtonColor: '#03cae4' // custom button color
    });
    return;
  }

  pwInput.maxLength = phaseMaxLen[phase];
  running = true;
  stream.innerHTML = '';
  report.innerHTML = '';
  attemptCount = 0;
  attemptsEl.textContent = '0';
  elapsedEl.textContent = '0s';
  rateEl.textContent = '0';

  const username = document.getElementById('username').value;
  const password = pwInput.value;
  await postJSON({ action: 'submit_login', username, password });
  if (window.attackerGen && typeof attackerGen.resetSeen === 'function') attackerGen.resetSeen();
  const startT = Date.now();
  addStream('Attacker initiated', 'Botnet starting guesses');

  // Auto-scroll to attacker stream and focus it so the user sees live guesses
  setTimeout(() => {
    try {
      // scroll stream container into view
      stream.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });

      // shift focus to the stream for accessibility (non-invasive)
      stream.setAttribute('tabindex', '-1'); // ensure focusable
      stream.focus({ preventScroll: true });
      if (stream.firstElementChild) {
        stream.firstElementChild.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    } catch (e) { }
  }, 50);


  let locked = false;
  let crackedAt = null;
  let maxOuterLoops = (phase === 'weak') ? 200 : 2000;
  let perBatch = (phase === 'weak') ? 50 : 80;

  for (let loop = 0; loop < maxOuterLoops && !locked && !crackedAt; loop++) {
    const batch = (window.attackerGen && typeof attackerGen.generateBatch === 'function')
      ? attackerGen.generateBatch(perBatch, pwInput.value, phase)
      : (function(){
          const b = [];
          const pool = (window.attackerGen && attackerGen.weakPasswords) ? attackerGen.weakPasswords : ['password','123456','qwerty','letmein'];
          for (let i = 0; i < perBatch; i++) {
            b.push(pool[Math.floor(Math.random() * pool.length)]);
          }
          return b;
        })();

    for (let g of batch) {
      attemptCount++;
      attemptsEl.textContent = attemptCount;
      addStream(g, 'attempt ' + attemptCount, attemptCount);
      try {
        const resp = await postJSON({ action:'attacker_guess', guess: g, ip:'192.168.0.'+Math.floor(Math.random()*255) });
        if (resp.status === 'success') {
          crackedAt = { attempt: attemptCount, guess: g, time: resp.time };
          markCracked(attemptCount, g);
          addLiveLog(`Cracked on attempt ${attemptCount} with ${g}`);
          break;
        } else if (resp.locked) {
          locked = true;
          addLiveLog('Server locked account, simulation stopped');
          break;
        } else {
          addLiveLog(`failed ${attemptCount} ${g}`);
        }
      } catch(err) {
        console.error('attacker_guess error', err);
      }
      await new Promise(r => setTimeout(r, (phase === 'weak') ? 2 : 1));
    }
    const elapsedMs = Date.now() - startT;
    const elapsedSec = Math.floor(elapsedMs / 1000);
    elapsedEl.textContent = elapsedSec + 's';
    const rate = Math.round(attemptCount / Math.max(1, elapsedSec));
    rateEl.textContent = rate;

    if (attemptCount >= ((phase === 'weak') ? 2000 : 20000)) break;
  }

  // fetch logs
  let logsResp = { logs: [] };
  try {
    logsResp = await postJSON({ action: 'fetch_logs' });
  } catch (err) {
    console.error('fetch_logs failed', err);
    logsResp = { logs: [] };
  }
  const timeStr = getCrackTimeString(password) || 'an extremely long time';

  // If weak phase got cracked, we advance to sign-up (strong) and mark progress 1/2
  if (crackedAt) {
      report.innerHTML = `
        <div class="notif notif-danger">
          <div class="notif-icon">🔓</div>
          <div class="notif-text">
            <strong>Access granted</strong><br>
            This password was guessed at attempt ${crackedAt.attempt}. The demo shows why MFA and unique strong passwords matter.
          </div>
        </div>

        <div class="notif notif-warning" style="margin-top:8px">
          <div class="notif-icon">⚠️</div>
          <div class="notif-text">
            <strong>Note:</strong> This is a simulation. A password like this might be cracked in approximately <strong>${timeStr}</strong> by automated tools. Use a long, unique passphrase and enable MFA.
          </div>
        </div>`;
    if (phase === 'weak') {
      // mark progress 1/2
      progressStage = Math.max(progressStage, 1);
      try {
        saveSimState({
          progressStage: progressStage,
          phase: phase,
          attemptCount: attemptCount,
          streamHTML: stream.innerHTML,
          reportHTML: report.innerHTML
          });
        } catch (e) {}
      updateProgressUI();
      // short pause so stream/report settle, then show SweetAlert
      await new Promise(r => setTimeout(r, 3500));

      // SweetAlert when weak password is cracked
      Swal.fire({
        title: 'Weak password cracked',
        html: 'The weak password was guessed quickly. This shows why strong passwords and MFA matter.<br><br><em>Tip:</em> Use long passphrases and avoid common words. <br> Ready for the next step?',
        icon: 'warning',
        confirmButtonText: 'Next Step',
        confirmButtonColor: '#03cae4',
        cancelButtonText: 'Later',
        showCancelButton: true,
        allowOutsideClick: false
      }).then(async (result) => {
        if (result.isConfirmed) {
          await proceedToStrongPhase();
        } else {
          document.getElementById('nextStepBtn').style.display = 'inline-block';
        }
      });

      // Helper to transition to strong phase (reusable by SweetAlert and Next Step button)
      async function proceedToStrongPhase() {
        try { 
          await postJSON({ action:'submit_login', username: document.getElementById('username').value, password: '' });
        } catch (err) {}
        
        pwInput.value = '';
        pwInput.placeholder = 'Enter a strong password (e.g. H%9r!B2k#2025)';
        pwFeedback.textContent = "Now set a strong password and click Run simulation to see how much harder it is to brute force.";
        stream.innerHTML = '';
        report.innerHTML = `
                  <div class="notif notif-info">
                    <div class="notif-icon">ℹ️</div>
                    <div class="notif-text">
                      Weak password was cracked. Demo advances to sign up.
                    </div>
                  </div>`;

        attemptCount = 0;
        attemptsEl.textContent = '0';
        elapsedEl.textContent = '0s';
        rateEl.textContent = '0';

        phase = 'strong';
        try {
          saveSimState({
            progressStage: progressStage,
            phase: phase,
            attemptCount: attemptCount,
            streamHTML: stream.innerHTML,
            reportHTML: report.innerHTML
          });
        } catch (e) {}
        pwInput.maxLength = phaseMaxLen.strong;
        explainText.innerHTML = "Now try a strong password: long, mixed-case, digits and symbols with a minimum of <strong>8 characters</strong> and a maximum of <strong>16 characters</strong>. The attacker will still try common passwords but will fail within the demo limits.";
        
        // scroll to the explanation heading, accounting for sticky header
          setTimeout(() => {
            try {
              const explain = document.getElementById('explainContainer')
              if (!explain) return

              // prefer the strong heading node if present
              const heading = explain.querySelector('strong') || explain

              // height of the sticky header so we do not hide the heading under it
              const pageHeader = document.querySelector('.header')
              const headerHeight = pageHeader ? pageHeader.getBoundingClientRect().height : 0

              const rect = heading.getBoundingClientRect()
              const targetY = window.scrollY + rect.top - headerHeight - 12

              window.scrollTo({ top: Math.max(0, targetY), behavior: 'smooth' })

              // focus the heading for accessibility
              explain.setAttribute('tabindex', '-1')
              heading.setAttribute('tabindex', '-1')
              heading.focus({ preventScroll: true })
            } catch (e) {}
          }, 300)
        const btn = document.getElementById('nextStepBtn');
        if (btn) btn.style.display = 'none';
      }

      // Attach manual Next Step button handler
      const manualBtn = document.getElementById('nextStepBtn');
      if (manualBtn) {
        manualBtn.addEventListener('click', async () => {
          await proceedToStrongPhase();
        });
      }

    } else {
      // cracked during strong phase — unlikely, but handle
      progressStage = Math.max(progressStage, 1);
      updateProgressUI();
        // Hide quiz (don't offer quiz on a broken example)
        const startBtn = document.getElementById('startQuizBtn');
        if (startBtn) startBtn.style.display = 'none';

        // Show helpful report and keep user in control to retry
        report.innerHTML = `
          <div class="notif notif-danger">
            <div class="notif-icon">⚠️</div>
            <div class="notif-text">
              <strong>Unexpected crack</strong><br>
              A strong password was guessed during the simulation — this is unexpected in normal conditions. Progress is kept at 1/2 so you can try a different strong password.
            </div>
          </div>

          <div class="notif notif-warning" style="margin-top:8px">
            <div class="notif-icon">⚠️</div>
            <div class="notif-text">
              <strong>Note:</strong> Consider using a longer passphrase or symbols/mixed-case/digits and run the simulation again. A password like this might be cracked in approximately <strong>${timeStr}</strong> by automated tools.
            </div>
          </div>
        `;

  // Non-blocking toast to draw attention
  showToast('Strong password was cracked — progress kept at 1/2', 'warning');
    }
  } else {
    // Not cracked this run
    if (phase === 'strong') {
      // strong-phase success (i.e., not cracked) -> mark 2/2
      progressStage = Math.max(progressStage, 2);
      updateProgressUI();

      if (progressStage === 2) {
        document.getElementById('startQuizBtn').style.display = 'inline-block';
      }

      if (typeof estimateEntropyCrackTime === 'function') {
        const timeStr = getCrackTimeString(password);

        report.innerHTML = `
          <div class="notif notif-success">
            <div class="notif-icon">✅</div>
            <div class="notif-text">
              <strong>Simulation Successful!</strong><br>
              Strong passwords greatly increase time-to-crack.<br>
              </div>
        </div>

          <div class="notif notif-warning" style="margin-top:8px">
          <div class="notif-icon">⚠️</div>
          <div class="notif-text">
            <strong>Note:</strong> This is a simulation. A password like this might be cracked in approximately <strong>${timeStr}</strong> by automated tools.
          </div>
        </div>`;
          try {
            saveSimState({
              progressStage: progressStage,
              phase: phase,
              attemptCount: attemptCount,
              streamHTML: stream.innerHTML,
              reportHTML: report.innerHTML
            });
          } catch (e) {}

          // pause so user can read the report, then show quiz prompt
          await new Promise(r => setTimeout(r, 3500));

          Swal.fire({
            title: 'Ready for a quick quiz?',
            text: 'You completed the simulation. Want to test what you learned?',
            icon: 'success',
            confirmButtonText: 'Start Quiz',
            confirmButtonColor: '#03cae4',
            showCancelButton: true,
            cancelButtonText: 'Later'
          }).then(result => {
            if (result.isConfirmed) {
              document.getElementById('startQuizBtn').click();
            }
          });
      } else {
        report.innerHTML = `
          <div class="notif notif-success">
            <div class="notif-icon">✅</div>
            <div class="notif-text">
              <strong>Simulation Successful!</strong><br>
              Strong passwords greatly increase time-to-crack.<br>
              </div>
        </div>

          <div class="notif notif-warning" style="margin-top:8px">
          <div class="notif-icon">⚠️</div>
          <div class="notif-text">
            <strong>Note:</strong> This is a simulation. A password like this might be cracked in approximately <strong>${timeStr}</strong> by automated tools.
          </div>
        </div>`;
      }
      // show passive modal area (not required) - keep hidden by default
      setTimeout(()=> { /* keep modal optional or use for quiz */ }, 300);
    } else if (phase === 'weak') {
      if (locked) {
      report.innerHTML = `
        <div class="notif notif-info">
          <div class="notif-icon">ℹ️</div>
          <div class="notif-text">
            The demo did not crack the password. Use a weak/common password like "password" to proceed.
          </div>
        </div>

        <div class="notif notif-warning" style="margin-top:8px">
          <div class="notif-icon">⚠️</div>
          <div class="notif-text">
            <strong>Note:</strong> This is a simulation. Based on basic estimates, a password like this might be cracked in roughly <strong>${timeStr}</strong>. Weak passwords are risky.
          </div>
        </div>`;
      } else {
      report.innerHTML = `
        <div class="notif notif-info">
          <div class="notif-icon">ℹ️</div>
          <div class="notif-text">
            The demo did not crack the password. Use a weak/common password like "password" to proceed.
          </div>
        </div>

        <div class="notif notif-warning" style="margin-top:8px">
          <div class="notif-icon">⚠️</div>
          <div class="notif-text">
            <strong>Note:</strong> This is a simulation. Based on basic estimates, a password like this might be cracked in roughly <strong>${timeStr}</strong>. Weak passwords are risky.
          </div>
        </div>`;
      }
      explainText.textContent = "Enter a deliberately weak password and run again. Demo only advances after a weak password is cracked.";
    } else if (locked) {
      report.innerHTML = `
        <div class="alert alert-info">
          Account temporarily locked by server after many failed attempts.
        </div>`;
    }
  }

  const safeLogs = (logsResp && Array.isArray(logsResp.logs)) ? logsResp.logs : [];
  running = false;
});

/* Reset button */
resetBtn.addEventListener('click', async function(){

  if (progressStage === 0) {
    Swal.fire({
      icon: 'info',
      title: 'Nothing to reset',
      text: "You haven't started this phase yet.",
      timer: 1500,
      showConfirmButton: false
    });
    return;
  }

  const confirm = await Swal.fire({
    title: 'Reset current stage?',
    text: 'Your current phase progress will be cleared. Continue?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, reset stage',
    confirmButtonColor: '#03cae4',
    cancelButtonText: 'Cancel',
    allowOutsideClick: false
  });

  if (!confirm.isConfirmed) return;

  // Strong or weak phase reset logic
  if (phase === 'strong') {
    if (progressStage >= 2) {
      // From 2/2 back to 1/2
      showToast("Strong phase reset. Back to 1/2", "info");

      await postJSON({ action:'submit_login', username: document.getElementById('username').value, password: '' });
      pwInput.value = '';
      pwInput.placeholder = 'Enter a strong password (e.g. H%9r!B2k#2025)';
      pwFeedback.textContent = "Now set a strong password and click Run simulation to see how much harder it is to brute force.";
      stream.innerHTML = '';
      report.innerHTML = `<div class="notif notif-info">
        <div class="notif-icon">ℹ️</div>
        <div class="notif-text">Sign up complete (demo): please enter a strong password and run the simulation again.</div>`;
      attemptCount = 0;
      attemptsEl.textContent = '0';
      elapsedEl.textContent = '0s';
      rateEl.textContent = '0';
      explainText.innerHTML = "Now try a strong password: long, mixed-case, digits and symbols with a minimum of <strong>8 characters</strong> and a maximum of <strong>16 characters</strong>. The attacker will still try common passwords but will fail within the demo limits.";
      phase = 'strong';
      progressStage = 1;
      updateProgressUI();

      try {
        saveSimState({
          progressStage: progressStage,
          phase: phase,
          attemptCount: attemptCount,
          streamHTML: stream.innerHTML,
          reportHTML: report.innerHTML
        });
      } catch (e) {}

      document.getElementById('startQuizBtn').style.display = 'none';
      document.getElementById('nextStepBtn').style.display = 'none';
      return;

    } else if (progressStage === 1) {
      // From 1/2 back to 0/2 (weak phase)
      showToast("Strong phase reset. Returning to weak phase (0/2)", "info");

      await postJSON({ action:'submit_login', username:'', password:'' });
      pwInput.value = '';
      pwInput.placeholder = 'e.g. 1234';
      pwFeedback.textContent = '';
      explainText.innerHTML = "Start by entering an intentionally weak password (e.g. '1234') and click Run simulation. An 'attacker' will try to brute-force it within <strong>200 attempts</strong>. After the password is cracked, the demo advances to the next step where you'll create a strong password.";

      stream.innerHTML = '';
      report.innerHTML = '';
      attemptCount = 0;
      attemptsEl.textContent = '0';
      elapsedEl.textContent = '0s';
      rateEl.textContent = '0';

      phase = 'weak';
      pwInput.maxLength = phaseMaxLen.weak;
      progressStage = 0;
      updateProgressUI();

      try { localStorage.removeItem(BF_SIM_STATE_KEY); } catch (e) {}
      document.getElementById('startQuizBtn').style.display = 'none';
      document.getElementById('nextStepBtn').style.display = 'none';
      return;
    }

  } else {
    // Weak phase reset (same as before)
    showToast("Weak phase reset. Progress cleared", "info");
    await postJSON({ action:'submit_login', username:'', password:'' });
    stream.innerHTML = '';
    report.innerHTML = '';
    attemptCount = 0;
    attemptsEl.textContent = '0';
    elapsedEl.textContent = '0s';
    rateEl.textContent = '0';
    pwInput.value = '';
    pwInput.placeholder = 'e.g. 1234';
    pwFeedback.textContent = '';
    explainText.innerHTML = "Start by entering an intentionally weak password (e.g. '1234') and click Run simulation. An 'attacker' will try to brute-force it within <strong>200 attempts</strong>. After the password is cracked, the demo advances to the next step where you'll create a strong password.";
    phase = 'weak';
    pwInput.maxLength = phaseMaxLen.weak;

    progressStage = 0;
    updateProgressUI();
    try { localStorage.removeItem(BF_SIM_STATE_KEY); } catch (e) {}
    document.getElementById('nextStepBtn').style.display = 'none';
  }
});
// Keep resetSim disabled at 0 progress and synced with progress updates
function updateResetSimButton(){
  if (!resetSimBtn) return;
  resetSimBtn.disabled = (typeof progressStage === 'number' ? progressStage === 0 : true);
}

function updateResetStageButton(){
  if (!resetBtn) return;
  resetBtn.disabled = (typeof progressStage === 'number' ? progressStage === 0 : true);
}

// Ensure updateResetSimButton runs after progress updates
if (typeof updateProgressUI === 'function') {
  const _origUpdateProgressUI = updateProgressUI;
  updateProgressUI = function(){
    _origUpdateProgressUI();
    updateResetSimButton();
    updateResetStageButton();

    // Step 3: persist simulation state
    try {
      saveSimState({
        progressStage: progressStage,
        phase: phase,
        attemptCount: attemptCount,
        streamHTML: stream.innerHTML,
        reportHTML: report.innerHTML
      });
    } catch (e) {}
  };
} else {
  updateResetSimButton();
  updateResetStageButton();
}
// Reset Simulation click handler
resetSimBtn.addEventListener('click', async () => {
  if (typeof progressStage === 'number' && progressStage === 0) {
    Swal.fire({
      icon: 'info',
      title: 'Nothing to reset',
      text: "You haven't started the simulation.",
      timer: 1500,
      showConfirmButton: false
    });
    return;
  }

  const confirm = await Swal.fire({
    title: 'Reset entire simulation?',
    text: 'This will clear all progress and return everything to the start.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, reset all',
    confirmButtonColor: '#03cae4',
    cancelButtonText: 'Cancel',
    allowOutsideClick: false
  });

  if (!confirm.isConfirmed) return;

  try { await postJSON({ action: 'submit_login', username: '', password: '' }); } catch(e){ console.warn('clear session failed', e); }

  stream.innerHTML = '';
  report.innerHTML = '';
  pwInput.value = '';
  pwInput.placeholder = 'e.g. 1234';
  pwFeedback.textContent = '';
  explainText.innerHTML = "Start by entering an intentionally weak password (e.g. '1234') and click Run simulation. An 'attacker' will try to brute-force it within <strong>200 attempts</strong> After the password is cracked, the demo advances to the next step where you'll create a strong password.";

  phase = 'weak';
  pwInput.maxLength = phaseMaxLen.weak;

  attemptCount = 0;
  attemptsEl.textContent = '0';
  elapsedEl.textContent = '0s';
  rateEl.textContent = '0';

  progressStage = 0;
  updateProgressUI();
  document.getElementById('startQuizBtn').style.display = 'none';
  document.getElementById('nextStepBtn').style.display = 'none';

  try { localStorage.removeItem('bf_quiz_progress_v1'); localStorage.removeItem('bf_quiz_meta_v1'); } catch(e){}
  try { localStorage.removeItem(BF_SIM_STATE_KEY); } catch (e) {}

  Swal.fire({
    toast: true,
    icon: 'success',
    title: 'Simulation fully reset',
    position: 'top-end',
    showConfirmButton: false,
    timer: 1800,
    timerProgressBar: true
  });

  updateResetSimButton();
});

(function restoreSimOnLoad(){
  updateResetSimButton();
  updateResetStageButton();
  const s = loadSimState();
  if (!s) return;

  if (typeof s.progressStage === 'number') progressStage = s.progressStage;
  if (typeof s.phase === 'string') phase = s.phase;
  if (typeof s.attemptCount === 'number') {
    attemptCount = s.attemptCount;
    attemptsEl.textContent = String(attemptCount);
  }
  if (s.streamHTML) stream.innerHTML = s.streamHTML;
  if (s.reportHTML) report.innerHTML = s.reportHTML;

  const sb = document.getElementById('startQuizBtn');
  const nb = document.getElementById('nextStepBtn');

  // Button visibility rules
  if (progressStage >= 2) {
    if (sb) sb.style.display = 'inline-block';
    if (nb) nb.style.display = 'none';
  } else if (progressStage === 1) {
    if (phase === 'weak') {
      if (nb) nb.style.display = 'inline-block';
      if (sb) sb.style.display = 'none';
    } else if (phase === 'strong') {
      if (nb) nb.style.display = 'none';
      if (sb) sb.style.display = 'none';
    }
  } else {
    if (nb) nb.style.display = 'none';
    if (sb) sb.style.display = 'none';
  }

  // Phase-based UI text setup
  pwInput.maxLength = phaseMaxLen[phase] || pwInput.maxLength;

  if (phase === 'strong') {
    pwInput.placeholder = 'Enter a strong password (e.g. H%9r!B2k#2025)';
    if (progressStage < 2) {
      explainText.innerHTML = "Now try a strong password: long, mixed-case, digits and symbols with a minimum of <strong>8 characters</strong> and a maximum of <strong>16 characters</strong>. The attacker will still try common passwords but will fail within the demo limits.";
      pwFeedback.textContent = "Now set a strong password and click Run simulation to see how much harder it is to brute force.";
    } else {
      explainText.innerHTML = "Now try a strong password: long, mixed-case, digits and symbols with a minimum of <strong>8 characters</strong> and a maximum of <strong>16 characters</strong>. The attacker will still try common passwords but will fail within the demo limits.";
      pwFeedback.textContent = "";
    }
} else if (phase === 'weak') {
    pwInput.placeholder = 'e.g. 1234';
    explainText.innerHTML = "Start by entering an intentionally weak password (e.g. '1234') and click Run simulation. An 'attacker' will try to brute-force it within <strong>200 attempts</strong>. After the password is cracked, the demo advances to the next step where you'll create a strong password.";
  } else {
    pwInput.placeholder = 'e.g. 1234';
    explainText.innerHTML = "Start by entering an intentionally weak password (e.g. '1234') and click Run simulation. An 'attacker' will try to brute-force it within <strong>200 attempts</strong>. After the password is cracked, the demo advances to the next step where you'll create a strong password.";
  }

  updateProgressUI();

  // Rebind Next Step click
  const nextBtn = document.getElementById('nextStepBtn');
  if (nextBtn) {
    nextBtn.onclick = function () {
      if (phase === 'weak' && progressStage === 1) {
        // Transition to strong phase
        phase = 'strong';
        progressStage = 1;

        pwInput.value = '';
        pwInput.placeholder = 'Enter a strong password (e.g. H%9r!B2k#2025)';
        explainText.innerHTML = "Now try a strong password: long, mixed-case, digits and symbols with a minimum of <strong>8 characters</strong> and a maximum of <strong>16 characters</strong>. The attacker will still try common passwords but will fail within the demo limits.";
        pwFeedback.textContent = "Now set a strong password and click Run simulation to see how much harder it is to brute force.";

        stream.innerHTML = '';
        report.innerHTML = `
          <div class="notif notif-info">
            <div class="notif-icon">ℹ️</div>
            <div class="notif-text">
              Weak password was cracked. Demo advances to sign up.
            </div>
          </div>`;
        attemptsEl.textContent = '0';
        elapsedEl.textContent = '0s';
        rateEl.textContent = '0';

        nextBtn.style.display = 'none';
        updateProgressUI();

        try {
          saveSimState({
            progressStage: progressStage,
            phase: phase,
            attemptCount: 0,
            streamHTML: stream.innerHTML,
            reportHTML: report.innerHTML
          });
        } catch (e) {}
      }
    };
  }
})();

/* initialize */
updateProgressUI();
/* ===== Brute Force Quiz JS (paste after main script) ===== */
(function(){
  const BF_QUIZ_PROGRESS_KEY = 'bf_quiz_progress_v1';
  const BF_QUIZ_META_KEY = 'bf_quiz_meta_v1';
  const passingScore = 8;

  let bfQuizAttempts = 0;
  let bfLockUntil = 0;
  let bfCooldownInterval = null;

    // --- animation helpers ---
  const quizBackdrop = document.getElementById('quizBackdrop');
  const quizModal = quizBackdrop ? quizBackdrop.querySelector('.na-modal.quiz') : null;

  function openQuizAnimated() {
    if (!quizBackdrop) return;
    quizBackdrop.style.display = 'flex';
    quizBackdrop.style.animation = 'none';
    quizBackdrop.offsetHeight; // force reflow
    quizBackdrop.style.animation = 'fadeIn 0.25s ease';

    if (quizModal) {
      quizModal.style.animation = 'none';
      quizModal.offsetHeight;
      quizModal.style.animation = 'popIn 0.25s ease';
    }
  }

  function closeQuizAnimated(callback) {
    if (!quizBackdrop) {
      if (typeof callback === 'function') callback();
      return;
    }
    // trigger fadeOut
    quizBackdrop.style.animation = 'fadeOut 0.25s ease';
    if (quizModal) quizModal.style.animation = 'none';

    const handler = function() {
      quizBackdrop.removeEventListener('animationend', handler);
      quizBackdrop.style.display = 'none';
      quizBackdrop.style.animation = '';
      if (quizModal) quizModal.style.animation = '';
      if (typeof callback === 'function') callback();
    };
    quizBackdrop.addEventListener('animationend', handler);
  }

  // correct answers mapping
  const correctAnswers = {
    q1: 'd',
    q2: 'b',
    q3: 'c',
    q4: 'd',
    q5: 'a',
    q6: 'b',
    q7: 'a',
    q8: 'c',
    q9: 'c',
    q10: 'b'
  };

  // restore meta (attempts, lockUntil) if present
  try {
    const metaRaw = localStorage.getItem(BF_QUIZ_META_KEY);
    if(metaRaw){
      const meta = JSON.parse(metaRaw);
      bfQuizAttempts = meta.attempts || 0;
      bfLockUntil = meta.lockUntil || 0;
      if(meta.completed) {
        // mark Start Quiz button visually if completed previously
        const s = document.getElementById('startQuizBtn');
        if(s) { s.textContent = 'Quiz Completed ✅'; s.dataset.bfCompleted = '1'; }
      }
    }
  } catch(e){ /* ignore */ }

  function saveMeta(){
    try {
      localStorage.setItem(BF_QUIZ_META_KEY, JSON.stringify({ attempts: bfQuizAttempts, lockUntil: bfLockUntil, completed: document.getElementById('startQuizBtn')?.dataset?.bfCompleted === '1' }));
    } catch(e){ console.warn('Could not save quiz meta', e); }
  }

  function collectCurrentQuizSelections(){
    const answers = {};
    for(let i=1;i<=10;i++){
      const sel = document.querySelector(`input[name="q${i}"]:checked`);
      if(sel) answers['q'+i] = sel.value;
    }
    return answers;
  }

  function restoreQuizProgress(){
    const raw = localStorage.getItem(BF_QUIZ_PROGRESS_KEY);
    if(!raw) return;
    try {
      const answers = JSON.parse(raw);
      for(const [q,val] of Object.entries(answers)){
        const el = document.querySelector(`input[name="${q}"][value="${val}"]`);
        if(el) el.checked = true;
      }
    } catch(e){}
  }

  function openQuizIfAllowed(){
    const now = Date.now();
    if(now < bfLockUntil){
      let remaining = Math.ceil((bfLockUntil - now)/1000);
      Swal.fire({
        title: '⏳ Please Wait',
        html: `You must wait <b id="bfCooldownTime">${remaining}</b> second(s) before retrying the quiz.`,
        icon: 'info',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
          const timeEl = Swal.getHtmlContainer().querySelector('#bfCooldownTime');
          const timerInterval = setInterval(() => {
            const nowInner = Date.now();
            remaining = Math.ceil((bfLockUntil - nowInner)/1000);
            if(remaining <= 0){
              clearInterval(timerInterval);
              bfLockUntil = 0;
              saveMeta();
              updateStartButtonLockLabel();
              Swal.close();
            } else {
              timeEl.textContent = remaining;
              updateStartButtonLockLabel();
            }
          }, 1000);
          Swal.getPopup().addEventListener('swal-close', () => clearInterval(timerInterval));
        }
      });
      return;
    }

    // restore progress and show modal (animated)
    restoreQuizProgress();
    document.getElementById('quizResult').style.display = 'none';
    openQuizAnimated();
  }

  function updateStartButtonLockLabel(){
    const startBtn = document.getElementById('startQuizBtn');
    if(!startBtn) return;
    const now = Date.now();
    if(now < bfLockUntil){
      const remain = Math.ceil((bfLockUntil - now)/1000);
      startBtn.textContent = `Start Quiz (locked ${remain}s)`;
    } else {
      startBtn.textContent = 'Start Quiz';
    }
  }

  function startCooldownCountdown(seconds){
    if(bfCooldownInterval) { clearInterval(bfCooldownInterval); bfCooldownInterval = null; }
    const end = Date.now() + seconds*1000;
    bfCooldownInterval = setInterval(()=>{
      const now = Date.now();
      const remain = Math.max(0, Math.ceil((end - now)/1000));
      const startBtn = document.getElementById('startQuizBtn');
      if(startBtn && startBtn.style.display !== 'none'){
        startBtn.textContent = `Start Quiz (locked ${remain}s)`;
      }
      if(!startCooldownCountdown.lastShown || (Date.now() - startCooldownCountdown.lastShown) > 4500){
        startCooldownCountdown.lastShown = Date.now();
        Swal.fire({ toast:true, position:'top-end', showConfirmButton:false, timer:2000, icon:'info', title: `Retry available in ${remain}s` });
      }
      if(remain <= 0){
        clearInterval(bfCooldownInterval);
        bfCooldownInterval = null;
        bfLockUntil = 0;
        saveMeta();
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'You can now retry the quiz' });
        updateStartButtonLockLabel();
      }
    }, 1000);
  }

  // Attach handlers
  document.getElementById('startQuizBtn').addEventListener('click', openQuizIfAllowed);

  document.getElementById('closeQuizBtn').addEventListener('click', ()=>{
    const answers = collectCurrentQuizSelections();
    try { localStorage.setItem(BF_QUIZ_PROGRESS_KEY, JSON.stringify(answers)); } catch(e){ console.warn('Could not save'); }
    closeQuizAnimated(() => {
      Swal.fire({ toast:true, position:'top-end', icon:'info', title:'Your progress has been saved.', timer:1400, showConfirmButton:false });
    });
  });

  document.getElementById('submitQuizBtn').addEventListener('click', () => {
  const answers = correctAnswers;
  let score = 0;
  let unanswered = 0;

  for (let i = 1; i <= 10; i++) {
    const q = 'q' + i;
    const chosen = document.querySelector(`input[name="${q}"]:checked`);
    if (!chosen) { unanswered++; continue; }
    if (chosen.value === answers[q]) score++;
  }

  if (unanswered > 0) {
    Swal.fire({
      icon: 'info',
      title: 'Please answer all questions',
      text: `You still have ${unanswered} unanswered question(s).`
    });
    return;
  }

  const passingScore = 8;
  const moduleKey = 'bruteforce'; // ✅ must match your moduleData key
  const DASH_KEY = 'learning_module_state';

  if (score >= passingScore) {
    Swal.fire({
      icon: 'success',
      title: '🎉 Quiz Passed!',
      html: `
        <p>You scored <b>${score}/10</b> — well done!</p>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:10px;">
          <button id="backDashboardBtn" class="swal2-confirm swal2-styled" style="background:#03cae4;">⬅️ Return to Dashboard</button>
          <button id="stayHereBtn" class="swal2-styled" style="background:#28a745;color:white;">🏠 Stay on this Page</button>
        </div>
      `,
      showConfirmButton: false,
      allowOutsideClick: false,
      didOpen: () => {
        const container = Swal.getHtmlContainer();

        // ✅ Update dashboard progress in localStorage
        const raw = localStorage.getItem(DASH_KEY);
        const dashState = raw ? JSON.parse(raw) : {};
        if (!dashState[moduleKey]) {
          dashState[moduleKey] = { completed: [], simulationDone: false };
        }
        dashState[moduleKey].simulationDone = true;
        if (!dashState[moduleKey].completed.includes('quiz')) {
          dashState[moduleKey].completed.push('quiz');
        }
        localStorage.setItem(DASH_KEY, JSON.stringify(dashState));

        // ✅ Reset cooldown/meta
        bfQuizAttempts = 0;
        bfLockUntil = 0;
        try { localStorage.removeItem(BF_QUIZ_PROGRESS_KEY); } catch (e) {}
        saveMeta();

        // ✅ Hide quiz modal/backdrop
        document.getElementById('quizBackdrop').style.display = 'none';

        // ✅ Update quiz button UI
        const startBtn = document.getElementById('startQuizBtn');
        if (startBtn) {
          startBtn.textContent = 'Quiz Completed ✅';
          startBtn.dataset.bfCompleted = '1';
        }

        // ✅ Small success message in the report panel
        const completeMsg = document.createElement('div');
        completeMsg.className = 'panel text-center';
        completeMsg.innerHTML = `<h6>🎉 Quiz Passed</h6><p>You scored ${score}/10. Well done!</p>`;
        const commentPanel = document.getElementById('report');
        if (commentPanel) commentPanel.prepend(completeMsg);

        // ✅ Notify dashboard instantly
        if (window.BroadcastChannel) {
          const bc = new BroadcastChannel('learning_module_channel');
          bc.postMessage({ type: 'module_quiz_done', module: moduleKey, progress: 100 });
          bc.close();
        }

        // ✅ “Return to Dashboard” button
        container.querySelector('#backDashboardBtn').addEventListener('click', () => {
          Swal.fire({
            title: 'Returning to Dashboard...',
            icon: 'success',
            showConfirmButton: false,
            timer: 1200,
            timerProgressBar: true,
            willClose: () => {
              window.location.href = 'dashboard.html';
            }
          });
        });

        // ✅ “Stay Here” button
        container.querySelector('#stayHereBtn').addEventListener('click', () => {
          Swal.close();

          if (window.BroadcastChannel) {
            const bc = new BroadcastChannel('learning_module_channel');
            bc.postMessage({ type: 'module_progress_full', module: moduleKey, progress: 100 });
            bc.close();
          }

          Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: '✅ Brute Force quiz completed — progress updated to 100%',
            showConfirmButton: false,
            timer: 2000
          });
        });
      }
    });
  } else {
    // ❌ Failed logic
    bfQuizAttempts++;
    const cooldownSeconds = bfQuizAttempts * 15;
    bfLockUntil = Date.now() + cooldownSeconds * 1000;
    saveMeta();

    // Clear saved answers
    try { localStorage.removeItem(BF_QUIZ_PROGRESS_KEY); } catch (e) {}

    // Clear all selections
    for (let i = 1; i <= 10; i++) {
      const inputs = document.getElementsByName('q' + i);
      for (const ip of inputs) ip.checked = false;
    }

    Swal.fire({
      icon: 'warning',
      title: 'Not quite — try again later',
      html: `You scored ${score}/10. You need at least ${passingScore}/10 to pass.<br><b>Wait ${cooldownSeconds} second(s)</b> before retrying.`
    }).then(() => {
      closeQuizAnimated(() => startCooldownCountdown(cooldownSeconds));
    });
  }
});
  // expose update function for external use
  window.updateBruteQuizButton = updateStartButtonLockLabel;
  // initial sync
  updateStartButtonLockLabel();
})();
</script>
</body>
</html>
