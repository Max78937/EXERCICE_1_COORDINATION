<?php
header('Content-Type: application/json');

// Mode debug
$DEBUG = 1;


function interrogerLlama($question) {
    $url = 'http://localhost:11434/api/generate';
    
    $prompt = "Tu es un assistant spécialisé dans les quiz. Ta tâche est de répondre à des questions à choix multiples.\n\n";
    $prompt .= "EXEMPLES DE QUESTIONS ET RÉPONSES :\n";
    $prompt .= "Question : What is the capital of France?\n";
    $prompt .= "A) London\n";
    $prompt .= "B) Berlin\n";
    $prompt .= "C) Paris\n";
    $prompt .= "D) Madrid\n";
    $prompt .= "Réponse : C\n\n";
    
    $prompt .= "Question : Who painted the Mona Lisa?\n";
    $prompt .= "A) Van Gogh\n";
    $prompt .= "B) Picasso\n";
    $prompt .= "C) Da Vinci\n";
    $prompt .= "D) Rembrandt\n";
    $prompt .= "Réponse : C\n\n";
    
    $prompt .= "RÈGLES STRICTES :\n";
    $prompt .= "1. Réponds UNIQUEMENT avec une lettre (A, B, C ou D)\n";
    $prompt .= "2. Ne donne AUCUNE explication\n";
    $prompt .= "3. Ne répète pas la question\n";
    $prompt .= "4. Ne mets pas de point après la lettre\n";
    $prompt .= "5. Ne mets pas la réponse entre parenthèses\n\n";
    
    $prompt .= "QUESTION ACTUELLE :\n";
    $prompt .= "Question : {$question['question']}\n\n";
    $prompt .= "Réponses possibles :\n";
    
    
    $reponses = array_merge($question['incorrect_answers'], [$question['correct_answer']]);
    shuffle($reponses);
    

    $ordreReponses = [];
    foreach (range('A', 'D') as $index => $lettre) {
        $prompt .= "{$lettre}) {$reponses[$index]}\n";
        $ordreReponses[$lettre] = $reponses[$index];
    }
    
    $prompt .= "\nRappel : Réponds UNIQUEMENT avec la lettre (A, B, C ou D)";

    $data = [
        'model' => 'llama3.2',
        'prompt' => $prompt,
        'stream' => false,
        'temperature' => 0.3
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
            'timeout' => 30
        ]
    ];

    $context = stream_context_create($options);
    
    $reponse = @file_get_contents($url, false, $context);
    
    if ($reponse === false) {
        return [
            'erreur' => true,
            'message' => 'Erreur lors de la communication avec le modèle Llama'
        ];
    }

    $resultat = json_decode($reponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'erreur' => true,
            'message' => 'Réponse invalide du modèle Llama : ' . json_last_error_msg()
        ];
    }

    return [
        'erreur' => false,
        'reponse' => $resultat['response'] ?? null,
        'ordre_reponses' => $ordreReponses
    ];
}

function verifierReponse($reponseLlama, $question, $ordreReponses) {

    $reponseNettoyee = trim(strtoupper($reponseLlama));
    
    preg_match('/[A-D]/', $reponseNettoyee, $matches);
    $lettreReponse = $matches[0] ?? null;

    if (!$lettreReponse) {
        return [
            'correcte' => false,
            'message' => 'Format de réponse invalide'
        ];
    }

    $lettreCorrecte = array_search($question['correct_answer'], $ordreReponses);

    return [
        'correcte' => $lettreReponse === $lettreCorrecte,
        'lettre_choisie' => $lettreReponse,
        'lettre_correcte' => $lettreCorrecte,
        'reponse_choisie' => $ordreReponses[$lettreReponse] ?? null,
        'reponse_correcte' => $question['correct_answer']
    ];
}

$questions = json_decode(file_get_contents('questions.json'), true)['results'] ?? [];

if (empty($questions)) {
    echo json_encode([
        'succes' => false,
        'message' => 'Aucune question trouvée dans le fichier questions.json'
    ]);
    exit;
}

$resultats = [
    'total' => count($questions),
    'correctes' => 0,
    'erreurs' => 0,
    'details' => []
];

foreach ($questions as $index => $question) {
    $reponseLlama = interrogerLlama($question);
    
    if ($reponseLlama['erreur']) {
        $resultats['erreurs']++;
        $resultats['details'][] = [
            'question' => $question['question'],
            'statut' => 'erreur',
            'message' => $reponseLlama['message']
        ];
        continue;
    }

    $verification = verifierReponse($reponseLlama['reponse'], $question, $reponseLlama['ordre_reponses']);
    
    if ($verification['correcte']) {
        $resultats['correctes']++;
    }

    $resultats['details'][] = [
        'question' => $question['question'],
        'reponse_llama' => $reponseLlama['reponse'],
        'reponse_choisie' => $verification['reponse_choisie'],
        'reponse_correcte' => $verification['reponse_correcte'],
        'statut' => $verification['correcte'] ? 'correct' : 'incorrect',
        'lettre_choisie' => $verification['lettre_choisie'],
        'lettre_correcte' => $verification['lettre_correcte']
    ];
}

$resultats['pourcentage_reussite'] = ($resultats['correctes'] / $resultats['total']) * 100;
$resultats['nb_questions_incorrectes'] = $resultats['total'] - $resultats['correctes'] - $resultats['erreurs'];

$reponse = [
    'succes' => true,
    'message' => 'Analyse terminée',
    'donnees' => [
        'pourcentage_reussite' => round($resultats['pourcentage_reussite'], 2),
        'nb_questions_incorrectes' => $resultats['nb_questions_incorrectes']
    ]
];

if ($DEBUG) {
    $reponse['donnees']['details'] = $resultats['details'];
}

echo json_encode($reponse, JSON_PRETTY_PRINT); 