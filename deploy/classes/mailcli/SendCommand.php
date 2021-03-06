<?php

namespace mailcli;

use core\events\EventListenerFactory;
use core\mail\SMTPMailerFactory;
use core\mail\v2\MailerFactory;
use core\twig\TwigFunctions;
use Exception;
use Swift_Attachment;
use Swift_Message;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class SendCommand extends Command
{
    protected function configure()
    {
        $this->setName('mail:multi:send');
        $this->setDescription('send multiple mails, with twig-template support');

        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'from email-address');
        $this->addOption('fromname', null, InputOption::VALUE_OPTIONAL, 'from email-address name');
        $this->addOption('data', null, InputOption::VALUE_REQUIRED, 'email-addresses and data as CSV(;-separated), YAML-items or JSON-objects in array. "mail" has to contain the email-address you want to send the message to.');
        $this->addOption('subject', null, InputOption::VALUE_REQUIRED, 'subject as string, twig: or column:');
        $this->addOption('body', null, InputOption::VALUE_REQUIRED, 'subject as string, file: or column:');
        $this->addOption('ishtml', null, InputOption::VALUE_OPTIONAL, 'yes/no. default is no');

        $this->addOption('rangefrom', null, InputOption::VALUE_OPTIONAL, 'start-index of working-batch');
        $this->addOption('rangeto', null, InputOption::VALUE_OPTIONAL, 'end-index of working-batch');
    }

    private function readCSV(string $filepath): array
    {
        $data = [];
        $headers = [];

        $firstRow = true;
        if (($handle = fopen($filepath, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, ";")) !== FALSE) {
                if ($firstRow) {
                    $headers = $row;
                    $firstRow = false;
                } else {
                    $newRow = [];
                    foreach ($row as $id => $value) {
                        if (isset($headers[$id])) {
                            $newRow[$headers[$id]] = $value;
                        } else {
                            $newRow[$id] = $value;
                        }
                    }
                    $data[] = $newRow;
                }
            }
            fclose($handle);
        }
        return $data;
    }

    private function readYAML(string $filePath): array
    {
        $data = yaml_parse_file($filePath);
        return $data[array_keys($data)[0]];
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dataFilepath = $input->getOption('data');
        if (preg_match("/\.csv$/i", $dataFilepath)) {
            $data = $this->readCSV($dataFilepath);
        } else if (preg_match("/\.y(a)?ml$/i", $dataFilepath) && function_exists('yaml_parse_file')) {
            $data = $this->readYAML($dataFilepath);
        } else {
            $data = json_decode(file_get_contents($dataFilepath), true);
        }

        if (!is_array($data) || count($data) < 1) {
            throw new Exception('no data or invalid data-format! (' . $input->getOption('data') . ')');
        }
        $from = $input->getOption('from');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('invalid from email-address');
        }

        $fromName = $input->hasOption('fromname') ? $input->getOption('fromname') : null;
        $rangeFrom = $input->hasOption('rangefrom') && $input->getOption('rangefrom') > 0 ? (int) $input->getOption('rangefrom') : 0;
        $rangeTo = $input->hasOption('rangeto') && $input->getOption('rangeto') > 0 ? (int) $input->getOption('rangeto') : count($data);

        $subject = $input->getOption('subject');
        if (!$subject || strlen($subject) === 0) {
            throw new Exception('missing subject');
        }

        $body = $input->getOption('body');
        if (!$body || strlen($body) === 0) {
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
            'rangeFrom' => $rangeFrom,
            'rangeTo' => $rangeTo,
        ]);

        foreach ($options['data'] as $index => $item) {
            try {
                if ($index >= $rangeFrom && $index <= $rangeTo) {
                    if (!isset($item['mail'])) {
                        throw new Exception('no mail-address found in data');
                    }
                    if (!filter_var($item['mail'], FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('invalid to email-address: ' . $item['mail']);
                    }

                    $bodyTemplate = $options['body'];
                    if (preg_match("/^file:/i", $bodyTemplate)) {
                        $bodyTemplate = file_get_contents(preg_replace("/^file:/", '', $bodyTemplate));
                    } else if (preg_match("/^column:/i", $bodyTemplate)) {
                        $bodyTemplate = $item[preg_replace("/^file:/", '', $bodyTemplate)];
                    }

                    $subjectTemplate = $options['subject'];
                    if (preg_match("/^file:/i", $subjectTemplate)) {
                        $subjectTemplate = file_get_contents(preg_replace("/^file:/", '', $subjectTemplate));
                    } else if (preg_match("/^column:/i", $subjectTemplate)) {
                        $subjectTemplate = $item[preg_replace("/^file:/", '', $subjectTemplate)];
                    }

                    $loader = new ArrayLoader(['body' => $bodyTemplate, 'subject' => $subjectTemplate]);
                    $twig = new Environment($loader);
                    $twig = TwigFunctions::decorateTwig($twig);

                    $outputSubject = $twig->render('subject', $item);
                    $outputSubject = EventListenerFactory::getInstance()->fireFilterEvent('multimail_rendered_subject', $outputSubject, ['mail' => $item, 'renderer' => $twig, 'template' => $subjectTemplate]);
                    $outputBody = $twig->render('body', $item);
                    $outputBody = EventListenerFactory::getInstance()->fireFilterEvent('multimail_rendered_body', $outputBody, ['mail' => $item, 'renderer' => $twig, 'template' => $bodyTemplate]);

                    if (class_exists('core\mail\v2\MailerFactory')) {
                        $attachments = [];
                        foreach ($item as $key => $value) {
                            if(preg_match("/^file:/", $value) && file_exists($value)) {
                                $attachments[] = $value;
                            }
                            else if(isset($item['_' . $key . '_type']) && $item['_' . $key . '_type'] == 'attachment' && file_exists($value)) {
                                $attachments[] = $value;
                            }
                            else if(isset($value['_type']) && isset($value['uri']) && $value['_type'] == 'attachment' && file_exists($value['uri'])) {
                                $attachments[] = $value['uri'];
                            }
                        }

                        $mailer = MailerFactory::getMailer();
                        $mail = new Swift_Message($outputSubject);

                        if ($options['fromName']) {
                            $mail->setFrom([$from => $options['fromName']]);
                        } else {
                            $mail->setFrom($from);
                        }

                        $mail->setTo($item['mail']);
                        if (isset($options['bcc']) && strlen(trim($options['bcc'])) > 0) {
                            $mail->setBcc($options['bcc']);
                        }
                        $mail->setBody($outputBody);

                        foreach ($attachments as $attachment) {
                            $mail->attach(Swift_Attachment::fromPath(realpath($attachment)));
                        }

                        $mailer->send($mail);
                    } else {
                        //fallback to old mailer
                        $mailer = SMTPMailerFactory::instance()->createMailer();
                        $mailer->send($options['from'], $options['fromName'], [$item['mail']], $outputSubject,
                            $outputBody, $options['isHtml']);
                    }

                    EventListenerFactory::getInstance()->fireFilterEvent('multimail_post_send', null, [
                        'subject' => $outputSubject,
                        'body' => $outputBody,
                        'mail' => $item,
                        'options' => $options,
                        'to' => $item['mail'],
                    ]);

                    $output->writeln($item['mail'] . ': ok');
                } else {
                    $output->writeln($item['mail'] . ': not processed');
                }
            } catch (Exception $e) {
                $output->writeln(($item['mail'] ?? 'item ' . $index) . ': failed (' . $e->getMessage() . ')');
            }
        }
    }
}