<?php

namespace mailcli;

use core\mail\SMTPMailerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestMailCommand extends Command{
    protected function configure(){
        $this->setName('mail:test');
        $this->setDescription('send a test mail to the configured SMTP');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output){
        $to = [
            'mail@example.com' => 'Test User'
        ];
        $mailer = SMTPMailerFactory::instance()->createMailer();
        $mailer->send('aoop@example.com', 'Test-Mailer', $to, 'a simple test-mail', 'with a simple text-body', false);
    }    
}    