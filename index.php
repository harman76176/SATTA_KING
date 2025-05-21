<?php
session_start();

$usersFile = "users.json";
$betsFile = "bets.json";
$resultFile = "result.json";
$depositsFile = "deposits.json";
$withdrawalsFile = "withdrawals.json"; // New file for withdrawals

function loadJson($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true);
}

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Error message variable
$error = '';
$success = '';

// User Login Handler
if (isset($_POST['login_user'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $users = loadJson($usersFile);
    if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) { // Verifying password
        $_SESSION['user'] = $username;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid user credentials!";
    }
}

// Admin Login Handler
if (isset($_POST['login_admin'])) {
    if ($_POST['username'] == "harman76176" && $_POST['password'] == "Orbit@1234") {
        $_SESSION['admin'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid admin login!";
    }
}

// Admin Generate User Credentials
if (isset($_POST['generate_user']) && isset($_SESSION['admin'])) {
    $newUsername = $_POST['new_username'];
    $newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT); // Hashing password
    $users = loadJson($usersFile);
    
    if (!isset($users[$newUsername])) {
        $users[$newUsername] = ['password' => $newPassword, 'balance' => 10]; // Default balance
        saveJson($usersFile, $users);
        $success = "User  credentials generated successfully!";
    } else {
        $error = "Username already exists!";
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Place Bet
if (isset($_POST['place_bet']) && isset($_SESSION['user'])) {
    $bets = loadJson($betsFile);
    $users = loadJson($usersFile);
    $user = $_SESSION['user'];
    $number = intval($_POST['bet_number']);
    $betAmount = intval($_POST['bet_amount']);
    if ($users[$user]['balance'] >= $betAmount && $betAmount > 0) {
        $users[$user]['balance'] -= $betAmount;
        $bets[] = ['user' => $user, 'number' => $number, 'amount' => $betAmount];
        saveJson($usersFile, $users);
        saveJson($betsFile, $bets);
        $success = "Bet placed successfully!";
    } else {
        $error = "Insufficient balance or invalid amount.";
    }
    header("Location: index.php");
    exit;
}

// Set Result
if (isset($_POST['set_result']) && isset($_SESSION['admin'])) {
    $bets = loadJson($betsFile);
    $users = loadJson($usersFile);
    $result = intval($_POST['result']);
    $userResults = [];
    foreach ($bets as $bet) {
        if ($bet['number'] == $result) {
            $users[$bet['user']]['balance'] += $bet['amount'] * 2;
            $userResults[$bet['user']] = ['status' => 'win', 'amount' => $bet['amount'] * 2];
        } else {
            $userResults[$bet['user']] = ['status' => 'lose', 'amount' => 0];
        }
    }
    saveJson($usersFile, $users);
    saveJson($resultFile, ['number' => $result, 'time' => time(), 'userResults' => $userResults]);
    saveJson($betsFile, []);
    $success = "Result declared: $result";
    header("Location: index.php");
    exit;
}

// User Deposit
if (isset($_POST['submit_deposit']) && isset($_SESSION['user'])) {
    $code = $_POST['deposit_code'];
    $amount = intval($_POST['deposit_amount']);
    if (strlen($code) == 4 && $amount >= 200) {
        $deposits = loadJson($depositsFile);
        $deposits[] = [
            'user' => $_SESSION['user'],
            'code' => $code,
            'amount' => $amount,
            'status' => 'pending'
        ];
        saveJson($depositsFile, $deposits);
        $success = "Deposit request submitted successfully!";
    } else {
        $error = "Invalid code or amount. Minimum ₹200 and 4-digit code required.";
    }
    header("Location: index.php");
    exit;
}

// User Withdraw
if (isset($_POST['withdraw_request']) && isset($_SESSION['user'])) {
    $amount = intval($_POST['withdraw_amount']);
    $upi_details = $_POST['upi_details'];
    $users = loadJson($usersFile);
    $withdrawals = loadJson($withdrawalsFile);
    $user = $_SESSION['user'];

    if ($users[$user]['balance'] >= 1500 && $amount >= 1500 && $amount <= $users[$user]['balance']) {
        $withdrawals[] = [
            'user' => $user,
            'amount' => $amount,
            'status' => 'pending',
            'upi_details' => $upi_details
        ];
        $users[$user]['balance'] -= $amount; // Deduct amount from user's balance
        saveJson($withdrawalsFile, $withdrawals);
        saveJson($usersFile, $users);
        $success = "Withdraw request submitted successfully!";
    } else {
        $error = "Invalid withdraw amount or insufficient balance. Minimum ₹1500 required.";
    }
    header("Location: index.php");
    exit;
}

// Admin Approve/Reject Deposit
if (isset($_POST['verify_deposit']) && isset($_SESSION['admin'])) {
    $index = $_POST['index'];
    $action = $_POST['action'];
    $deposits = loadJson($depositsFile);
    $users = loadJson($usersFile);
    $deposit = &$deposits[$index];
    if ($action === "done") {
        $deposit['status'] = 'approved';
        $users[$deposit['user']]['balance'] += $deposit['amount'];
        saveJson($usersFile, $users);
        $success = "Deposit approved successfully!";
    } else {
        $deposit['status'] = 'rejected';
        $success = "Deposit rejected.";
    }
    saveJson($depositsFile, $deposits);
    header("Location: index.php");
    exit;
}

// Admin Approve/Reject Withdraw
if (isset($_POST['verify_withdraw']) && isset($_SESSION['admin'])) {
    $index = $_POST['index'];
    $action = $_POST['action'];
    $withdrawals = loadJson($withdrawalsFile);
    $users = loadJson($usersFile);
    $withdrawal = &$withdrawals[$index];

    if ($action === "paid") {
        $withdrawal['status'] = 'paid';
        // Here you can add logic to transfer the amount to the user's account if needed
    } elseif ($action === "rejected") {
        $withdrawal['status'] = 'rejected';
        // Refund amount to user's balance if rejected
        $users[$withdrawal['user']]['balance'] += $withdrawal['amount'];
    }
    saveJson($withdrawalsFile, $withdrawals);
    saveJson($usersFile, $users);
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SATTA KING</title>
    <style>
        body { font-family: Arial; background: #f3f3f3; text-align: center; }
        .box { width: 320px; margin: 40px auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 0 10px #aaa; }
        input, button { padding: 10px; width: 90%; margin: 8px 0; }
        .admin-btn { background: #222; color: white; margin-top: 10px; cursor: pointer; }
        .hidden { display: none; }
    </style>
    <script>
        function toggleForm(formId) {
            var form = document.getElementById(formId);
            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
            } else {
                form.classList.add('hidden');
            }
        }
    </script>
</head>
<body>

<?php if (!isset($_SESSION['user']) && !isset($_SESSION['admin'])): ?>
    <div class="box">
        <h2>Login</h2>
        <?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>
        <?php if ($success) echo "<p style='color:green;'>$success</p>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button name="login_user">Login as User</button>
        </form>
        <form method="POST">
            <h3>Admin Login</h3>
            <button type="button" onclick="document.getElementById('admin-form').style.display='block'" class="admin-btn">Admin Login</button>
            <div id="admin-form" style="display:none;">
                <input type="text" name="username" placeholder="Admin Username"><br>
                <input type="password" name="password" placeholder="Admin Password"><br>
                <button name="login_admin">Login as Admin</button>
            </div>
        </form>
    </div>

<?php elseif (isset($_SESSION['admin'])): ?>
    <div class="box">
        <h2>Admin Panel</h2>
        <h3>Generate User Credentials</h3>
        <form method="POST">
            <input type="text" name="new_username" placeholder="New Username" required>
            <input type="password" name="new_password" placeholder="New Password" required>
            <button name="generate_user">Generate User</button>
        </form>
        <h3>Set Result</h3>
        <form method="POST">
            <input type="number" name="result" placeholder="Enter Winning Number" required>
            <button name="set_result">Declare Result</button>
        </form>
        <h3>Verify Deposits</h3>
        <?php
        $deposits = loadJson($depositsFile);
        foreach ($deposits as $i => $d) {
            if ($d['status'] === 'pending') {
                echo "<form method='POST'>
                        <input type='hidden' name='index' value='$i'>
                        <p>User: {$d['user']} | Code: {$d['code']} | ₹{$d['amount']}</p>
                        <button name='verify_deposit' value='1' name='action' onclick=\"this.form.action.value='done'\">Done</button>
                        <button name='verify_deposit' value='1' name='action' onclick=\"this.form.action.value='reject'\">Not Done</button>
                        <input type='hidden' name='action' value=''>
                      </form><hr>";
            }
        }
        ?>
        <h3>Verify Withdrawals</h3>
        <?php
        $withdrawals = loadJson($withdrawalsFile);
        foreach ($withdrawals as $i => $w) {
            if ($w['status'] === 'pending') {
                echo "<form method='POST'>
                        <input type='hidden' name='index' value='$i'>
                        <p>User: {$w['user']} | Amount: ₹{$w['amount']} | UPI: {$w['upi_details']}</p>
                        <button name='verify_withdraw' value='1' name='action' onclick=\"this.form.action.value='paid'\">Paid</button>
                        <button name='verify_withdraw' value='1' name='action' onclick=\"this.form.action.value='rejected'\">Reject</button>
                        <input type='hidden' name='action' value=''>
                      </form><hr>";
            }
        }
        ?>
        <p><a href="?logout=1">Logout</a></p>
    </div>

<?php else: ?>
    <div class="box">
        <h2>Welcome, <?= $_SESSION['user'] ?></h2>
        <?php
        $users = loadJson($usersFile);
        $user = $_SESSION['user'];
        ?>
        <p>Balance: ₹<span id="balance"><?= $users[$user]['balance'] ?></span></p>
        <form method="POST">
            <input type="number" name="bet_number" min="1" max="100" placeholder="Enter number to bet" required>
            <input type="number" name="bet_amount" min="1" max="<?= $users[$user]['balance'] ?>" placeholder="Enter bet amount" required>
            <button name="place_bet">Place Bet</button>
        </form>
        <button onclick="refreshBalance()">Refresh Balance</button>

        <h3>
            <button onclick="toggleForm('depositForm')">Deposit</button>
        </h3>
        <div id="depositForm" class="hidden">
            <p>Game me paise deposit karne ke liye niche buttons par click karke payment karein. Aapko code milega, use deposit section me daalein amount ke sath, aapka deposit ho jayega.</p>
            <button onclick="window.location.href='https://manual-payment.onrender.com'">Payment Gateway</button>
            <form method="POST">
                <input type="text" name="deposit_code" maxlength="4" placeholder="4-digit code" required>
                <input type="number" name="deposit_amount" min="200" placeholder="Amount (Min ₹200)" required>
                <button name="submit_deposit">Submit Deposit</button>
            </form>
        </div>

        <h3>
            <button onclick="toggleForm('withdrawForm')">Withdraw</button>
        </h3>
        <div id="withdrawForm" class="hidden">
            <?php if ($users[$user]['balance'] >= 1500): ?>
                <form method="POST">
                    <input type="number" name="withdraw_amount" min="1500" max="<?= $users[$user]['balance'] ?>" placeholder="Enter amount to withdraw" required><br>
                    <input type="text" name="upi_details" placeholder="Enter UPI ID" required><br>
                    <button name="withdraw_request">Submit Withdraw Request</button>
                </form>
            <?php else: ?>
                <p>Your balance is too low to make a withdrawal. Minimum ₹1500 required.</p>
            <?php endif; ?>
        </div>

        <h4>Deposit History</h4>
        <?php
        $deposits = loadJson($depositsFile);
        foreach ($deposits as $d) {
            if ($d['user'] == $user) {
                echo "<p>₹{$d['amount']} | Code: {$d['code']} | Status: {$d['status']}</p>";
            }
        }
        ?>

        <h4>Withdraw History</h4>
        <?php
        $withdrawals = loadJson($withdrawalsFile);
        foreach ($withdrawals as $w) {
            if ($w['user'] == $user) {
                echo "<p>₹{$w['amount']} | UPI: {$w['upi_details']} | Status: {$w['status']}</p>";
            }
        }
        ?>

        <div id="result">Checking result...</div>

        <script>
            async function checkResult() {
                const res = await fetch('result.json?' + Date.now());
                const data = await res.json();
                let shown = localStorage.getItem("result_shown");
                if (!shown || shown != data.time) {
                    localStorage.setItem("result_shown", data.time);
                    document.getElementById("result").innerHTML = "Result: " + data.number;
                    const user = "<?= $_SESSION['user'] ?>";
                    if (data.userResults && data.userResults[user]) {
                        const result = data.userResults[user];
                        alert(result.status === "win" ? "🎉 आप जीत गए ₹" + result.amount : "😞 आप हार गए इस बार।");
                    }
                }
            }

            setInterval(checkResult, 3000);
            checkResult();

            function refreshBalance() {
                fetch('users.json?' + Date.now())
                    .then(response => response.json())
                    .then(data => {
                        const user = "<?= $_SESSION['user'] ?>";
                        document.getElementById("balance").textContent = data[user].balance;
                    });
            }
        </script>

        <p><a href="?logout=1">Logout</a></p>
    </div>
<?php endif; ?>
</body>
</html>
