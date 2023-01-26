<?php
/**
 * Copyright (C) 2023-2023 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

/**
 * Copyright (C) 2023-2023 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

namespace TbSwitftMailerModule;

use Configuration;
use Context;
use PrestaShopException;
use Swift_Attachment;
use Swift_Image;
use Swift_Mailer;
use Swift_MailTransport;
use Swift_Message;
use Swift_Plugins_DecoratorPlugin;
use Swift_SmtpTransport;
use Swift_Transport;
use TbSwiftMailer;
use Thirtybees\Core\Mail\MailAddress;
use Thirtybees\Core\Mail\MailAttachement;
use Thirtybees\Core\Mail\MailTemplate;
use Thirtybees\Core\Mail\MailTransport;
use Thirtybees\Core\Mail\Template\SimpleMailTemplate;
use Tools;
use Translate;

class SwiftMailerTransport implements MailTransport
{

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->l('SwiftMailer');
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function l($string)
    {
        return Translate::getModuleTranslation('tbswitfmailer', $string, 'tbswiftmailer');
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->l('Sends email using swiftmailer library (deprecated)');
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function getConfigUrl()
    {
        return Context::getContext()->link->getAdminLink('AdminModules', true, ['configure' => 'tbswiftmailer']);
    }

    /**
     * @param int $idShop
     * @param int $idLang
     * @param MailAddress $fromAddress
     * @param MailAddress[] $toAddresses
     * @param MailAddress[] $bccAddresses
     * @param MailAddress $replyTo
     * @param string $subject
     * @param MailTemplate[] $templates
     * @param array $templateVars
     * @param MailAttachement[] $attachements
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function sendMail(
        int         $idShop,
        int         $idLang,
        MailAddress $fromAddress,
        array       $toAddresses,
        array       $bccAddresses,
        MailAddress $replyTo,
        string      $subject,
        array       $templates,
        array       $templateVars,
        array       $attachements
    ): bool
    {
        // backwards compatibility
        $mailMethod = (int)$this->getConfig('PS_MAIL_METHOD', $idShop, TbSwiftMailer::MAIL_METHOD_NONE);
        if ($mailMethod === TbSwiftMailer::MAIL_METHOD_NONE) {
            return true;
        }

        $message = Swift_Message::newInstance();
        $message->setSubject($subject);
        $message->setCharset('utf-8');
        $message->setId(static::generateId());
        $message->setFrom($fromAddress->getAddress(), $fromAddress->getName());
        $message->setReplyTo($replyTo->getAddress(), $replyTo->getName());

        $templateVars = $this->processTemplateVars($templateVars, $message);
        $pluginParams = [];
        foreach ($toAddresses as $address) {
            $message->addTo($address->getAddress(), $address->getName());
            $pluginParams[$address->getAddress()] = $templateVars;
        }
        foreach ($bccAddresses as $address) {
            $message->addBcc($address->getAddress(), $address->getName());
        }

        $connection = $this->getConnection($mailMethod, $idShop);
        $swift = Swift_Mailer::newInstance($connection);

        if ($this->useDecorator($templates)) {
            $swift->registerPlugin(new Swift_Plugins_DecoratorPlugin($pluginParams));
            foreach ($templates as $template) {
                $message->addPart($template->getTemplate(), $template->getContentType(), 'utf-8');
            }
        } else {
            foreach ($templates as $template) {
                $message->addPart($template->renderTemplate($templateVars), $template->getContentType(), 'utf-8');
            }
        }

        foreach ($attachements as $attachement) {
            $message->attach((Swift_Attachment::newInstance())
                ->setFilename($attachement->getName())
                ->setContentType($attachement->getMime())
                ->setBody($attachement->getContent())
            );
        }

        return $swift->send($message);
    }

    /**
     * @param string $key
     * @param int $idShop
     * @param string|int|null $default
     *
     * @return string|int|null
     * @throws PrestaShopException
     */
    private function getConfig($key, $idShop, $default=null)
    {
        $value = Configuration::get($key, null, null, $idShop);
        if ($value === false || $value === null) {
            return $default;
        }
        return $value;
    }

    /**
     * @param int $mailMethod
     * @param int $idShop
     *
     * @return Swift_Transport
     * @throws PrestaShopException
     */
    protected function getConnection(int $mailMethod, int $idShop)
    {
        if ($mailMethod === TbSwiftMailer::MAIL_METHOD_SMTP) {
            $server = $this->getConfig('PS_MAIL_SERVER', $idShop);
            if (!$server) {
                throw new PrestaShopException(Tools::displayError('Invalid SMTP server'));
            }
            $port = (int)$this->getConfig('PS_MAIL_SMTP_PORT', $idShop, 25);
            $encryption = $this->getConfig('PS_MAIL_SMTP_ENCRYPTION', $idShop);
            if (!in_array($encryption, ['ssl', 'tls'])) {
                $encryption = null;
            }

            return Swift_SmtpTransport::newInstance($server, $port, $encryption)
                ->setUsername($this->getConfig('PS_MAIL_USER', $idShop))
                ->setPassword($this->getConfig('PS_MAIL_PASSWD', $idShop));
        } else {
            /** @noinspection PhpDeprecationInspection */
            return Swift_MailTransport::newInstance();
        }
    }

    /**
     * Generates unique message ID
     *
     * @return string
     */
    protected static function generateId()
    {
        $params = [
            'utctime' => gmdate('YmdHis'),
            'randint' => mt_rand(),
            'customstr' => 'swift',
            'hostname' => ((isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : php_uname('n')),
        ];
        return vsprintf("%s.%d.%s@%s", $params);
    }

    /**
     * Returns true, if all templates are SimpleMailTemplate. In that case,
     * Swift_Plugins_DecoratorPlugin can be used to replace template placeholder.
     *
     * If some template is different, we need to explicitly render templates instead
     *
     * @param MailTemplate[] $templates
     *
     * @return bool
     */
    private function useDecorator(array $templates)
    {
        foreach ($templates as $template) {
            if (! ($template instanceof SimpleMailTemplate)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Attach all parameters of imageFile types
     *
     * @param array $templateVars
     * @param Swift_Message $message
     * @return array
     */
    private function processTemplateVars(array $templateVars, Swift_Message $message)
    {
        foreach ($templateVars as &$parameter) {
            if (is_array($parameter) && isset($parameter['type']) && $parameter['type'] === 'imageFile') {
                $filepath = $parameter['filepath'];
                if ($filepath && file_exists($filepath)) {
                    $parameter = $message->embed(Swift_Image::fromPath($parameter['filepath']));
                } else {
                    $parameter = '';
                }
            }
        }
        return $templateVars;
    }

}