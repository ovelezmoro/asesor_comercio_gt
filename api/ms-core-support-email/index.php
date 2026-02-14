<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$app->setBasePath($basePath);

$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

$defaultRecipient = getenv('CONTACT_EMAIL') ?: 'ventas@acgt.com.pe';
$complaintsRecipient = getenv('COMPLAINTS_EMAIL') ?: $defaultRecipient;
$mailFrom = getenv('MAIL_FROM') ?: 'no-reply@localhost';

$jsonResponse = function (Response $response, array $payload, int $status = 200): Response {
    $response->getBody()->write(json_encode($payload));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
};

$sendMail = function (string $to, string $subject, string $body, ?string $replyTo = null) use ($mailFrom): bool {
    $headers = [
        'From: ' . $mailFrom,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    if ($replyTo) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    return mail($to, $subject, $body, implode("\r\n", $headers));
};

$getField = function (array $data, string $key): string {
    return isset($data[$key]) ? trim((string) $data[$key]) : '';
};

$app->post('/contact', function (Request $request, Response $response) use ($jsonResponse, $sendMail, $getField, $defaultRecipient) {
    $data = (array) $request->getParsedBody();

    $firstName = $getField($data, 'first_name');
    $lastName = $getField($data, 'last_name');
    $email = $getField($data, 'email');
    $subject = $getField($data, 'subject');
    $message = $getField($data, 'message');

    if ($firstName === '' || $email === '' || $message === '') {
        return $jsonResponse($response, [
            'success' => false,
            'message' => 'Missing required fields.',
        ], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $jsonResponse($response, [
            'success' => false,
            'message' => 'Invalid email address.',
        ], 400);
    }

    $fullName = trim($firstName . ' ' . $lastName);
    $mailSubject = 'Contacto web: ' . ($subject !== '' ? $subject : 'Consulta');
    $mailBody = "Nombre: {$fullName}\n" .
        "Correo: {$email}\n" .
        "Asunto: {$subject}\n\n" .
        "Mensaje:\n{$message}\n";

    if (!$sendMail($defaultRecipient, $mailSubject, $mailBody, $email)) {
        return $jsonResponse($response, [
            'success' => false,
            'message' => 'Unable to send email.',
        ], 500);
    }

    return $jsonResponse($response, [
        'success' => true,
        'message' => 'Message sent.',
    ]);
});

$app->post('/complaints', function (Request $request, Response $response) use ($jsonResponse, $sendMail, $getField, $complaintsRecipient) {
    $data = (array) $request->getParsedBody();

    $complainantName = $getField($data, 'complainant_name');
    $complainantEmail = $getField($data, 'complainant_email');
    $documentType = $getField($data, 'document_type');
    $documentNumber = $getField($data, 'document_number');
    $claimType = $getField($data, 'claim_type');
    $claimDetail = $getField($data, 'claim_detail');
    $claimRequest = $getField($data, 'claim_request');

    if ($complainantName === '' || $complainantEmail === '' || $claimDetail === '' || $claimRequest === '') {
        return $jsonResponse($response, [
            'success' => false,
            'message' => 'Missing required fields.',
        ], 400);
    }

    if (!filter_var($complainantEmail, FILTER_VALIDATE_EMAIL)) {
        return $jsonResponse($response, [
            'success' => false,
            'message' => 'Invalid email address.',
        ], 400);
    }

    $mailSubject = 'Libro de reclamaciones: ' . ($claimType !== '' ? $claimType : 'registro');
    $mailBody = "Reclamante: {$complainantName}\n" .
        "Correo: {$complainantEmail}\n" .
        "Documento: {$documentType} {$documentNumber}\n" .
        "Telefono: " . $getField($data, 'complainant_phone') . "\n" .
        "Direccion: " . $getField($data, 'complainant_address') . "\n" .
        "Es menor: " . $getField($data, 'is_minor') . "\n\n" .
        "Bien contratado: " . $getField($data, 'contracted_good') . "\n" .
        "Monto reclamado: " . $getField($data, 'claimed_amount') . "\n" .
        "Descripcion: " . $getField($data, 'contracted_description') . "\n\n" .
        "Fecha incidente: " . $getField($data, 'incident_date') . "\n" .
        "Tipo: {$claimType}\n\n" .
        "Detalle:\n{$claimDetail}\n\n" .
        "Pedido:\n{$claimRequest}\n";

    if (!$sendMail($complaintsRecipient, $mailSubject, $mailBody, $complainantEmail)) {
        return $jsonResponse($response, [
            'success' => false,
            'message' => 'Unable to send email.',
        ], 500);
    }

    return $jsonResponse($response, [
        'success' => true,
        'message' => 'Complaint sent.',
    ]);
});

$app->run();