<?php

namespace EastWood;

/**
 * Mail - A simple email tool library
 *
 * @author  EastWood (haowei) <boss@haowei.me>
 *
 */

class Mail
{

    /**
     * Mail version
     * @const string
     */
    const VERSION = '1.0.0';

    /**
     * SMTP protocol newline
     */
    const CRLF = "\r\n";

    /**
     * meta data
     * @var array
     */
    private $raw = [];

    /**
     * response data
     * @var array
     */
    private $debugTrace = [];

    /**
     * socket handler
     * @var
     */
    private $socket;

    /**
     * socket connect timeout
     * @var int
     */
    private $timeout = 5;

    /**
     * socket scheme
     * @var string
     */
    private $scheme = 'tcp';

    /**
     * post data separator
     * @var
     */
    private $separator;

    /**
     * post data character
     * @var string
     */
    private $charset = 'UTF-8';

    /**
     * socket connect remote host
     * @var
     */
    private $host;

    /**
     * socket connect remote port
     * @var
     */
    private $port;

    /**
     * recipients list
     * @var array
     */
    private $rcpt = [];

    /**
     * sender email address
     * @var
     */
    private $from;

    /**
     * reply address
     * @var string
     */
    private $reply;

    /**
     * body recipients list
     * @var array
     */
    private $to = [];

    /**
     * body cc list
     * @var array
     */
    private $cc = [];

    /**
     * body bcc list
     * @var array
     */
    private $bcc = [];

    /**
     * mail subject
     * @var string
     */
    private $subject = '';

    /**
     * mail body
     * @var string
     */
    private $body = '';

    /**
     * attachment list
     * @var array
     */
    private $attachment = [];


    /**
     * Mail constructor
     *
     * @param array|null $argv
     */
    public function __construct(array $argv = null)
    {
        if ($argv) {
            foreach ($argv as $property => $value) {
                if (property_exists($this, $property)) {
                    $this->$property = $value;
                }
            }
        }
    }

    /**
     * Connect SMTP server
     *
     * @param string $host
     * @param int $port
     * @param string $scheme
     * @param int $timeout
     * @return \EastWood\Mail
     * @throws \ErrorException
     */
    public function connectServer($host = null, $port = 0, $scheme = null, $timeout = 0)
    {
        if ($host) $this->host = $host;
        if ($port) $this->port = $port;
        if ($scheme) $this->scheme = $scheme;
        if ($timeout) $this->timeout = $timeout;

        // Only catch SMTP server connection exception
        set_error_handler(function ($code, $message, $file, $line) {
            throw new \ErrorException($message, $code, 1, $file, $line);
        });
        $this->socket = fsockopen($this->scheme . '://' . $this->host, $this->port, $code, $message, $this->timeout);
        restore_error_handler();
        $this->readLine(220);
        $this->writeLine('HELO ' . $this->host, 250);
        return $this;
    }

    /**
     * Authentication identity
     *
     * @param string $user
     * @param string $password
     * @return \EastWood\Mail
     * @throws \ErrorException
     */
    public function authentication($user, $password)
    {
        $this->separator = '----=_Part_' . md5($user . time()) . uniqid();
        $this->setFrom($user);
        $this->writeLine('AUTH LOGIN', 334);
        $this->writeLine(base64_encode($this->from), 334);
        $this->writeLine(base64_encode($password), 235);
        return $this;
    }

    /**
     * Authentication method aliases
     *
     * @param string $user
     * @param string $password
     * @return \EastWood\Mail
     * @throws \ErrorException
     */
    public function auth()
    {
        return call_user_func_array([$this, 'authentication'], func_get_args());
    }

    /**
     * setting mail body character
     *
     * @param string $charset
     * @return \EastWood\Mail
     */
    public function setCharset($charset)
    {
        $this->charset = strtoupper($charset);
        return $this;
    }

    /**
     * Set sender address
     *
     * @param string $from
     * @return \EastWood\Mail
     */
    public function setFrom($from)
    {
        $this->from = $from;
        return $this;
    }

    /**
     * Set reply address
     *
     * @param string $reply
     * @return $this
     */
    public function setReply($reply)
    {
        $this->reply = $reply;
        return $this;
    }

    /**
     * Add to
     *
     * @param string $to
     * @return \EastWood\Mail
     */
    public function addTo($to)
    {
        $this->to[] = $to;
        return $this;
    }

    /**
     * Add cc
     *
     * @param string $cc
     * @return \EastWood\Mail
     */
    public function addCc($cc)
    {
        $this->cc[] = $cc;
        return $this;
    }

    /**
     * Add bcc
     *
     * @param string $bcc
     * @return \EastWood\Mail
     */
    public function addBcc($bcc)
    {
        $this->bcc[] = $bcc;
        return $this;
    }

    /**
     * Add an attachment
     * Please use file stream for large files
     *
     * @param string $path
     * @param resource $stream [optional]
     * @throws \ErrorException
     * @return \EastWood\Mail
     */
    public function addAttachment($path, $stream = null)
    {
        if (is_resource($stream)) {
            $binary = '';
            fseek($stream, SEEK_SET);
            while (!feof($stream)) $binary .= fgets($stream);
            fclose($stream);
            $this->attachment[] = [
                'name' => $path,
                'body' => base64_encode($binary)
            ];
        } else {
            if (!is_file($path)) {
                $message = json_encode([
                    'error' => 'IOException',
                    'message' => sprintf('%s file not found')
                ]);
                throw new \ErrorException($message);
            }
            $this->attachment[] = [
                'name' => basename($path),
                'body' => base64_encode(file_get_contents($path))
            ];
        }
        return $this;
    }

    /**
     * Set mail subject
     *
     * @param string $subject
     * @return \EastWood\Mail
     */
    public function subject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set mail body
     *
     * @param string $body
     * @return \EastWood\Mail
     */
    public function body($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Sending
     *
     * @return bool
     * @throws \ErrorException
     */
    public function send()
    {
        $this->rcpt = array_merge($this->to, $this->cc, $this->bcc);
        $this->writeLine('MAIL FROM: <' . $this->from . '>', 250);
        foreach ($this->rcpt as $rcpt)
            $this->writeLine('RCPT TO: <' . $rcpt . '>', 250);

        $this->writeLine('DATA', 354);
        $this->writeLine('From: ' . $this->from);
        $this->writeLine('To: ' . implode(',', $this->to));
        if ($this->reply) $this->writeLine('Reply-To: ' . $this->reply);
        if ($this->cc) $this->writeLine('Cc: ' . implode(',', $this->cc));
        if ($this->bcc) $this->writeLine('Bcc: ' . implode(',', $this->bcc));
        $this->writeLine('Date: ' . date('r'));
        $this->writeLine('X-Mailer: ' . __CLASS__ . ' v' . Mail::VERSION);
        $this->writeLine('Subject: =?' . $this->charset . '?B?' . base64_encode($this->subject) . '?=');
        $this->writeLine('Content-Type: multipart/mixed;');
        $this->writeLine("\t" . 'boundary="' . $this->separator . '"');
        $this->writeLine('MIME-Version: 1.0');
        $this->writeLine('');
        $this->writeLine('--' . $this->separator);
        $this->writeLine('Content-Type: text/html;charset=' . $this->charset);
        $this->writeLine('Content-Transfer-Encoding: base64');
        $this->writeLine('');
        $this->writeLine(base64_encode($this->body));
        $this->writeLine('--' . $this->separator);
        if ($this->attachment) {
            foreach ($this->attachment as $part) {
                $this->writeLine('Content-Type: application/octet-stream; name="' . $part['name'] . '"');
                $this->writeLine('Content-Transfer-Encoding: base64');
                $this->writeLine('Content-Disposition: attachment; filename="' . $part['name'] . '"');
                $this->writeLine('');
                $this->writeLine($part['body']);
                $this->writeLine('--' . $this->separator);
            }
        }
        $this->writeLine('');
        $this->writeLine('.', 250);
        $this->writeLine('QUIT', 221);
        return true;
    }

    /**
     * Get expected code
     * @param int $code
     * @throws \ErrorException
     */
    private function readLine($code)
    {
        $line = trim(fgets($this->socket));
        $this->debugTrace[] = $line;
        if (($retCode = substr($line, 0, 3)) != $code) {
            $message = json_encode([
                'error' => 'InvalidException',
                'message' => sprintf('expected code is %s, but gets %s', $code, $retCode)
            ]);
            throw new \ErrorException($message);
        }
    }

    /**
     * Write IO stream
     *
     * @param string $text
     * @param int $code
     * @return bool|int
     * @throws \ErrorException
     */
    private function writeLine($text, $code = 0)
    {
        $this->raw[] = $text;
        $length = fputs($this->socket, $text . self::CRLF);
        if ($code > 0) $this->readLine($code);
        return $length;
    }
}

