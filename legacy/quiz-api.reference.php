<?php
// ═══════════════════════════════════════════════════════════════════════════════
// REDACTED REFERENCE COPY — NOT EXECUTABLE, NOT DEPLOYED
//
// This is the legacy production endpoint with its credentials removed, kept in
// version control so the legacy behaviour stays reviewable. The verbatim
// original is preserved at legacy/quiz-api.php and is gitignored because it
// contains live credentials (SECURITY.md L1 — rotate them).
//
// Known vulnerabilities in this file, all fixed in the replacement:
//   L1  credentials hard-coded in the web root
//   L4  score is supplied by the client and stored unverified
//   L5  no authentication, no CSRF, wildcard CORS on a write endpoint
//   L6  raw PDOException text returned to the caller
//   L7  no duplicate-submission protection
//   L11 3-byte `utf8` connection charset corrupts 4-byte characters
// ═══════════════════════════════════════════════════════════════════════════════

// L5: any origin may POST here.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// L1: the real file contains live values here. Redacted.
$host     = "__REDACTED__";
$db_name  = "__REDACTED__";
$username = "__REDACTED__";
$password = "__REDACTED__";

try {
    // L11: `charset=utf8` is 3-byte MySQL utf8, not utf8mb4.
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // L7: no unique constraint anywhere — nothing prevents duplicate submissions.
    $tableSql = "CREATE TABLE IF NOT EXISTS quiz_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id VARCHAR(100) NOT NULL,
        roll VARCHAR(50) NOT NULL,
        name VARCHAR(100) NOT NULL,
        guardian VARCHAR(100) NOT NULL,
        score INT NOT NULL,
        total_questions INT NOT NULL,
        time_taken INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";
    $conn->exec($tableSql);

} catch (PDOException $exception) {
    // L6: leaks connection and schema detail to the caller.
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $exception->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

// ─── ACTION 1: SUBMIT SCORE ───────────────────────────────────────────────────
// L4/L9: quiz_id, roll, name, guardian, score and timeTaken all come straight
// from the request body. The server verifies none of them and recomputes
// nothing, so any score can be submitted for any name on any quiz.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        echo json_encode(["status" => "error", "message" => "Invalid input data"]);
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO quiz_submissions (quiz_id, roll, name, guardian, score, total_questions, time_taken) VALUES (:quiz_id, :roll, :name, :guardian, :score, :total_questions, :time_taken)");
        $stmt->execute([
            ':quiz_id'         => $data['quizId'] ?? '',
            ':roll'            => $data['roll'] ?? '',
            ':name'            => $data['name'] ?? '',
            ':guardian'        => $data['guardian'] ?? '',
            ':score'           => (int)($data['score'] ?? 0),
            ':total_questions' => (int)($data['totalQuestions'] ?? 0),
            ':time_taken'      => (int)($data['timeTaken'] ?? 0)
        ]);
        echo json_encode(["status" => "success"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]); // L6
    }

// ─── ACTION 2: FETCH LEADERBOARD ──────────────────────────────────────────────
// Returns every submission for every quiz, unpaginated and unauthenticated.
// Ranking and per-quiz filtering happen client-side.
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'leaderboard') {
    try {
        $stmt = $conn->query("SELECT quiz_id as quizId, roll, name, guardian, score, total_questions as totalQuestions, time_taken as timeTaken, UNIX_TIMESTAMP(created_at)*1000 as timestamp FROM quiz_submissions ORDER BY created_at ASC");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$r) {
            $r['score']          = (int)$r['score'];
            $r['totalQuestions'] = (int)$r['totalQuestions'];
            $r['timeTaken']      = (int)$r['timeTaken'];
            $r['timestamp']      = (int)$r['timestamp'];
        }

        echo json_encode($results);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]); // L6
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid action or request method"]);
}
