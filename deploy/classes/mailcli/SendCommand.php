<?php

namespace mailcli;

use core\events\EventListenerFactory;
use core\mail\SMTPMailerFactory;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendCommand extends Command{
    protected function configure(){
        $this->setName('mail:multi:send');
        $this->setDescription('send multiple mails, with template support');

        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'from email-adress');
        $this->addOption('fromname', null, InputOption::VALUE_OPTIONAL, 'from email-adress name');
        $this->addOption('data', null, InputOption::VALUE_REQUIRED, 'email-adresses and data as (WIP: CSV or) JSON-objects in array. "mail" has to contain the email-adress you want to send the message to (use a object like "mail: "{"a@example.de": "Firstname Lastname"} to add the fullname).');
        $this->addOption('subject', null, InputOption::VALUE_REQUIRED, 'subject as string, twig: or column:');
        $this->addOption('body', null, InputOption::VALUE_REQUIRED, 'subject as string, file: or column:');
        $this->addOption('ishtml', null, InputOption::VALUE_OPTIONAL, 'yes/no. default is no');
    }

    protected function execute(InputInterface $input, OutputInterface $output){
        $data = json_decode(file_get_contents($input->getOption('data')), true);
        if(count($data) < 1) {
            throw new Exception('no data or invalid data-format! (' . $input->getOption('data') . ')');
        }
        $from = $input->getOption('from');
        if(!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('invalid from email-adress');
        }

        $fromName = $input->hasOption('fromname') ? $input->getOption('fromname') : null;

        $subject = $input->getOption('subject');
        if(!$subject || strlen($subject) === 0){
            throw new Exception('missing subject');
        }

        $body = $input->getOption('body');
        if(!$body || strlen($body) === 0){
            throw new Exception('missing body');
        }

        $isHtml = $input->hasOption('isHtml') && $input->getOption('isHtml') != 'yes';

        $options = EventListenerFactory::getInstance()->fireFilterEvent('multimail_data_readed', [
            'data' => $data,
            'from' => $from,
            'fromName' => $fromName,
            'subject' => $subject,
            'body' => $body,
            'isHtml' => $isHtml,
        ]);

        foreach($options['data'] as $item) {
            try{
                if(!isset($item['mail'])) {
                    throw new Exception('no mail-adress found in data');
                }
                
                $bodyTemplate = $options['body'];
                if(preg_match("/^file:/i", $bodyTemplate)) {
                    $bodyTemplate = file_get_contents(preg_replace("/^file:/", '', $bodyTemplate));
                }
                else if(preg_match("/^column:/i", $bodyTemplate)) {
                    $bodyTemplate = $item[preg_replace("/^file:/", '', $bodyTemplate)];
                }

                $subjectTemplate = $options['subject'];
                if(preg_match("/^file:/i", $subjectTemplate)) {
                    $subjectTemplate = file_get_contents(preg_replace("/^file:/", '', $subjectTemplate));
                }
                else if(preg_match("/^column:/i", $subjectTemplate)) {
                    $subjectTemplate = $item[preg_replace("/^file:/", '', $subjectTemplate)];
                }

                $loader = new \Twig_Loader_Array( [ 'body' => $bodyTemplate, 'subject' => $subjectTemplate]);
                $twig = new \Twig_Environment($loader);
                $twig = \core\twig\TwigFunctions::decorateTwig($twig);
                
                $outputSubject = $twig->render('subject', $item);
                $outputSubject = EventListenerFactory::getInstance()->fireFilterEvent('multimail_rendered_subject', $outputSubject, ['mail' => $item, 'renderer' => $twig, 'template' => $subjectTemplate]);
                $outputBody = $twig->render('body', $item);
                $outputBody = EventListenerFactory::getInstance()->fireFilterEvent('multimail_rendered_body', $outputBody, ['mail' => $item, 'renderer' => $twig, 'template' => $bodyTemplate]);

                $mailer = SMTPMailerFactory::instance()->createMailer();
                $mailer->send($options['from'], $options['fromName'], [$item['mail']], $outputSubject, $outputBody, $options['isHtml']);

                EventListenerFactory::getInstance()->fireFilterEvent('multimail_post_send', null, [
                    'subject' => $outputSubject,
                    'body' => $outputBody,
                    'mail' => $item,
                    'options' => $options,
                    'to' => $item['mail'],
                ]);

                $output->writeln($item['mail'] . ': ok');
            }
            catch(Exception $e) {
                $output->writeln($item['mail'] . ': failed (' . $e->getMessage() . ')');
            }    
        }
    }
}