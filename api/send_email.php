<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Отправка email через SMTP (Hostinger)
 * 
 * @param string $to Email получателя
 * @param string $subject Тема письма
 * @param string $body HTML содержимое письма
 * @param array $attachments Массив вложений ['path' => '/path/to/file.pdf', 'name' => 'filename.pdf']
 * @param string|null $replyTo Email для ответов (опционально)
 * @return bool Успешность отправки
 */
function sendEmailSMTP($to, $subject, $body, $attachments = [], $replyTo = null) {
    $logFile = __DIR__ . '/../data/mail.log';
    
    try {
        // Загрузка конфигурации
        $config = require __DIR__ . '/smtp_config.php';
        
        $mail = new PHPMailer(true);
        
        // Настройки SMTP
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = $config['auth'];
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        
        // Отправитель
        $mail->setFrom($config['from_email'], $config['from_name']);
        
        // Получатель
        $mail->addAddress($to);
        
        // Reply-To
        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        } elseif (isset($config['reply_to'])) {
            $mail->addReplyTo($config['reply_to']);
        }
        
        // Содержимое письма
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Вложения
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $name = $attachment['name'] ?? basename($attachment['path']);
                    $mail->addAttachment($attachment['path'], $name);
                }
            }
        }
        
        // Отправка
        $result = $mail->send();
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - SMTP Mail sent to: $to | Subject: $subject | Status: SUCCESS\n", FILE_APPEND);
        
        return true;
        
    } catch (Exception $e) {
        $error = "SMTP Error: {$mail->ErrorInfo}";
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - SMTP Mail error to: $to | Error: $error\n", FILE_APPEND);
        return false;
    }
}

/**
 * Отправка email копии (CC) нескольким получателям
 */
function sendEmailSMTPWithCC($to, $cc, $subject, $body, $attachments = [], $replyTo = null) {
    $logFile = __DIR__ . '/../data/mail.log';
    
    try {
        $config = require __DIR__ . '/smtp_config.php';
        
        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = $config['auth'];
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to);
        
        // Добавляем копии
        if (is_array($cc)) {
            foreach ($cc as $ccEmail) {
                $mail->addCC($ccEmail);
            }
        } else {
            $mail->addCC($cc);
        }
        
        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        } elseif (isset($config['reply_to'])) {
            $mail->addReplyTo($config['reply_to']);
        }
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $name = $attachment['name'] ?? basename($attachment['path']);
                    $mail->addAttachment($attachment['path'], $name);
                }
            }
        }
        
        $result = $mail->send();
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - SMTP Mail sent to: $to (CC: " . (is_array($cc) ? implode(',', $cc) : $cc) . ") | Subject: $subject | Status: SUCCESS\n", FILE_APPEND);
        
        return true;
        
    } catch (Exception $e) {
        $error = "SMTP Error: {$mail->ErrorInfo}";
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - SMTP Mail error to: $to | Error: $error\n", FILE_APPEND);
        return false;
    }
}
