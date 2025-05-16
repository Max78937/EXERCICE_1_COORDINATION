<?php
header('Content-Type: application/json');

function repondreJson($succes, $message, $donnees = null) {
    $reponse = [
        'succes' => $succes,
        'message' => $message
    ];
    
    if ($donnees !== null) {
        $reponse['donnees'] = $donnees;
    }
    
    echo json_encode($reponse, JSON_PRETTY_PRINT);
    exit;
}

$nombreQuestions = 50;
$fichierSortie = 'questions.json';

$urlApi = "https://opentdb.com/api.php?amount={$nombreQuestions}&type=multiple";

$reponse = file_get_contents($urlApi);

if ($reponse === false) {
    repondreJson(false, "Impossible de récupérer les données depuis l'API Open Trivia Database");
}

$donnees = json_decode($reponse, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    repondreJson(false, "Le format de la réponse n'est pas un JSON valide");
}

$resultat = file_put_contents($fichierSortie, json_encode($donnees, JSON_PRETTY_PRINT));

if ($resultat === false) {
    repondreJson(false, "Impossible de sauvegarder le fichier JSON");
}

repondreJson(
    true,
    "Les questions ont été récupérées et sauvegardées avec succès",
    [
        'fichier' => $fichierSortie,
        'nombreQuestions' => count($donnees['results'])
    ]
); 