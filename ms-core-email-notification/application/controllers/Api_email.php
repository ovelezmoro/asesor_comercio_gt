<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

class Api_email extends CI_Controller
{
    private $last_error = '';

    public function contact()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            return $this->json_response([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }

        $firstName = $this->get_field('first_name');
        $lastName = $this->get_field('last_name');
        $email = $this->get_field('email');
        $subject = $this->get_field('subject');
        $message = $this->get_field('message');

        if ($firstName === '' || $email === '' || $message === '') {
            return $this->json_response([
                'success' => false,
                'message' => 'Missing required fields.',
            ], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json_response([
                'success' => false,
                'message' => 'Invalid email address.',
            ], 400);
        }

        $recipient = getenv('CONTACT_EMAIL') ?: 'ventas@acgt.com.pe';
        $fullName = trim($firstName . ' ' . $lastName);
        $mailSubject = 'Contacto web: ' . ($subject !== '' ? $subject : 'Consulta');
        $mailBody = "Nombre: {$fullName}\n" .
            "Correo: {$email}\n" .
            "Asunto: {$subject}\n\n" .
            "Mensaje:\n{$message}\n";

        $sent = $this->send_mail($recipient, $mailSubject, $mailBody, $email);

        if (!$sent) {
            return $this->json_response([
                'success' => false,
                'message' => 'Unable to send email.',
                'error' => $this->last_error,
            ], 500);
        }

        return $this->json_response([
            'success' => true,
            'message' => 'Message sent.',
        ]);
    }

    public function complaints()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            return $this->json_response([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
        }

        $complainantName = $this->get_field('complainant_name');
        $complainantEmail = $this->get_field('complainant_email');
        $documentType = $this->get_field('document_type');
        $documentNumber = $this->get_field('document_number');
        $claimType = $this->get_field('claim_type');
        $claimDetail = $this->get_field('claim_detail');
        $claimRequest = $this->get_field('claim_request');

        if ($complainantName === '' || $complainantEmail === '' || $claimDetail === '' || $claimRequest === '') {
            return $this->json_response([
                'success' => false,
                'message' => 'Missing required fields.',
            ], 400);
        }

        if (!filter_var($complainantEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->json_response([
                'success' => false,
                'message' => 'Invalid email address.',
            ], 400);
        }

        $recipient = getenv('COMPLAINTS_EMAIL') ?: (getenv('CONTACT_EMAIL') ?: 'ventas@acgt.com.pe');
        $mailSubject = 'Libro de reclamaciones: ' . ($claimType !== '' ? $claimType : 'registro');
        $mailBody = "Reclamante: {$complainantName}\n" .
            "Correo: {$complainantEmail}\n" .
            "Documento: {$documentType} {$documentNumber}\n" .
            "Telefono: " . $this->get_field('complainant_phone') . "\n" .
            "Direccion: " . $this->get_field('complainant_address') . "\n" .
            "Es menor: " . $this->get_field('is_minor') . "\n\n" .
            "Bien contratado: " . $this->get_field('contracted_good') . "\n" .
            "Monto reclamado: " . $this->get_field('claimed_amount') . "\n" .
            "Descripcion: " . $this->get_field('contracted_description') . "\n\n" .
            "Fecha incidente: " . $this->get_field('incident_date') . "\n" .
            "Tipo: {$claimType}\n\n" .
            "Detalle:\n{$claimDetail}\n\n" .
            "Pedido:\n{$claimRequest}\n";

        $sent = $this->send_mail($recipient, $mailSubject, $mailBody, $complainantEmail);

        if (!$sent) {
            return $this->json_response([
                'success' => false,
                'message' => 'Unable to send email.',
                'error' => $this->last_error,
            ], 500);
        }

        return $this->json_response([
            'success' => true,
            'message' => 'Complaint sent.',
        ]);
    }

    private function send_mail($to, $subject, $body, $replyTo = '')
    {
        $host = getenv('SMTP_HOST') ?: '';
        $port = (int) (getenv('SMTP_PORT') ?: 587);
        $user = getenv('SMTP_USER') ?: '';
        $pass = getenv('SMTP_PASS') ?: '';
        $secure = getenv('SMTP_SECURE') ?: 'tls';
        $fromEmail = getenv('MAIL_FROM') ?: 'no-reply@localhost';
        $fromName = getenv('MAIL_FROM_NAME') ?: 'ACGT';
        
        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';

            if ($host) {
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = $port;
                $mail->SMTPAuth = !empty($user);
                if (!empty($user)) {
                    $mail->Username = $user;
                    $mail->Password = $pass;
                }
                if ($secure) {
                    $mail->SMTPSecure = $secure;
                }
            } else {
                $mail->isMail();
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            if ($replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }

            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);

            return $mail->send();
        } catch (MailerException $exception) {
            $this->last_error = $exception->getMessage();
            log_message('error', 'PHPMailer Error: ' . $exception->getMessage());
            return false;
        }
    }

    private function get_field($key)
    {
        $value = $this->input->post($key, true);
        return is_string($value) ? trim($value) : '';
    }

    private function json_response(array $payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload));

        return;
    }
}
