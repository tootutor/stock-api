<?php
class TTOMail {

    protected $attchements = array();
    protected $mailer;

    public function __construct($from, $to, $subject, $message) {
      $this->mailer = new PHPMailer();

      $this->mailer->AddAddress($to);
      $this->mailer->SetFrom($from);
      $this->mailer->Subject = $subject;
      $this->mailer->MsgHTML($message);
    }

    public static function create($from, $to, $subject, $message) {
      $instance = new Self($from, $to, $subject, $message);
      return $instance;
    }

    public static function createAndSend($from, $to, $subject, $message) {
			$instance = new Self($from, $to, $subject, $message);
			return $instance->send();
    }

    public static function createAndSendAdmin($subject, $message) {
    	$from = $to = ADMINEMAIL;
			$instance = new Self($from, $to, $subject, $message);
			return $instance->send();
    }

    public function send()
    {
			if(!empty($this->attachments)) {
				foreach($this->attachments as $attachment) {
					$this->mailer->AddAttachment($attachment);
				}
			}
			
			return $this->mailer->send();        
    }

    public function attach($attachment)
    {
			$this->attachments[] = $_SERVER['DOCUMENT_ROOT'].$attachment;
			return $this;
    }
}
?>