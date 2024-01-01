<?php

$curl = curl_init();

// Retrieve the request's body and parse it as JSON
$requestBody = file_get_contents('php://input');
$payload = json_decode($requestBody, true);

if (!isset($payload['messages'])) {
    // Invalid JSON
    $error = "I'm sorry, I couldn't read your message. Can you please clear the conversation and try again? not in payload";
    printResponse(false, $error, 401);
    return;
}

if (!is_array($payload['messages'])) {
    // Invalid or missing "messages" key
    $error = "I'm sorry, I couldn't read your message. Can you please clear the conversation and try again? not array";
    printResponse(false, $error, 401);
    return;
}

$identity = "Dr. Vi, as a specialized AI for interacting with patients regarding their health concerns, your primary function is to provide concise, accurate, and empathetic responses to inquiries about potential illnesses. When a patient approaches you with symptoms or questions about a specific condition, your response should be structured as follows: Firstly, briefly acknowledge their concern and provide a clear, layman's terms explanation of the possible illness, focusing on key symptoms and typical progression. Avoid medical jargon to ensure comprehension. Secondly, offer general advice on lifestyle adjustments or over-the-counter remedies that might alleviate symptoms, emphasizing that these are not substitutes for professional medical advice. Thirdly, based on the symptoms or conditions described, recommend a type of specialist (e.g., cardiologist, neurologist) they should consider consulting. Importantly, do not attempt to diagnose, predict outcomes, or suggest prescription treatments. Always remind the patient that a face-to-face medical consultation is essential for an accurate diagnosis and treatment plan. Keep your responses focused and relevant, avoiding extraneous details that may overwhelm or confuse the patient. Your goal is to inform and guide, not to replace professional medical judgment.";

// Append the system message at the beginning of the messages array
array_unshift(
    $payload["messages"],
    array(
        "role" => "system",
        "content" => $identity
    )
);

$payload = json_encode($payload, JSON_PRETTY_PRINT);

$data = array(
    "model" => "gpt-3.5-turbo", // will change to gpt4 in production, gpt3.5 for testing to save money
    "presence_penalty" => 1.5, // change it accordingly
    "frequency_penalty" => 2.0, // change it accordingly
    "temperature" => 0.5, // change it accordingly
    "max_tokens" => 2000, // change it accordingly
    "messages" => json_decode($payload, true)["messages"]
);
$jsonPayload = json_encode($data, JSON_PRETTY_PRINT);


curl_setopt_array(
    $curl,
    array(
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Bearer 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'
        ),
    )
);

$response = curl_exec($curl);

if ($response === false) {
    $error = "Empty response";
    printResponse(false, $error, 500);
} else {
    $httpStatusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($httpStatusCode !== 200) {
        $error = "Status code not 200";
        printResponse(false, $response, $httpStatusCode);
    } else {
        $responseArray = json_decode($response, true);
        if (isset($responseArray['choices'][0]['message']['content'])) {
            $content = $responseArray['choices'][0]['message']['content'];
            printResponse(true, $content, $httpStatusCode);
        } else {
            $error = "Unhandled error";
            printResponse(false, $error, $httpStatusCode);
        }
    }
}

curl_close($curl);

function printResponse($status, $message, $httpStatusCode) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($httpStatusCode);
    $response = json_encode(array('status' => $status, 'message' => $message), JSON_PRETTY_PRINT);
    echo $response;
}
