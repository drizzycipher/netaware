<?php
header("Content-Type: application/json");

// Correct answers (index-based: 0=A, 1=B, 2=C, 3=D)
$correctAnswers = [
    3, // Q1: All of the above
    0, // Q2: True
    3, // Q3: Both A and C
    3, // Q4: All of the above
    0, // Q5: True
    3, // Q6: All of the above
    3, // Q7: All of the above
    0, // Q8: True
    1, // Q9: Logging in only
    1  // Q10: Personal Details
];

$data = json_decode(file_get_contents("php://input"), true);
$answers = $data["answers"] ?? [];

$score = 0;
foreach ($correctAnswers as $i => $correct) {
    if (isset($answers[$i]) && $answers[$i] === $correct) {
        $score++;
    }
}

$response = [
    "ok" => true,
    "score" => $score,
    "total" => count($correctAnswers),
    "passed" => $score >= 8 // Pass if 8 or higher
];

echo json_encode($response);
